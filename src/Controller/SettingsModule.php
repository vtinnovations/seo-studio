<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller;

use Contao\Controller;
use Contao\Message;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Ai\ConnectionTester;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\FeatureRegistry;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Security\LicenseGuard;
use VTinnovations\SeoStudio\Core\Security\LicenseManager;
use VTinnovations\SeoStudio\Core\Security\SecretStore;
use VTinnovations\SeoStudio\Core\Security\TokenBudget;

/**
 * BE_MOD callback "SEO Studio → Einstellungen". Contao instantiates this via
 * `new` (no DI) — services come from the container (declared public).
 *
 * POST handling is strict PRG: process → Message::add*() → 303 redirect.
 * Contao 5 backend (Turbo) requires form responses to redirect.
 */
final class SettingsModule
{
    use BackendTabsTrait;

    public function generate(): string
    {
        $container = System::getContainer();

        /** @var ConfigProvider $config */
        $config = $container->get(ConfigProvider::class);
        /** @var SecretStore $secrets */
        $secrets = $container->get(SecretStore::class);
        /** @var FeatureRegistry $registry */
        $registry = $container->get(FeatureRegistry::class);
        /** @var FeatureState $featureState */
        $featureState = $container->get(FeatureState::class);
        /** @var TokenBudget $budget */
        $budget = $container->get(TokenBudget::class);

        $requestStack = $container->get('request_stack');
        \assert($requestStack instanceof \Symfony\Component\HttpFoundation\RequestStack);
        $request = $requestStack->getCurrentRequest();

        if ($request instanceof Request && $request->isMethod('POST')) {
            $this->handlePost($request, $config, $secrets, $registry, $container);
            Controller::redirect($request->getRequestUri());
        }

        return $this->render($config, $secrets, $registry, $featureState, $budget, $container);
    }

    private function handlePost(
        Request $request,
        ConfigProvider $config,
        SecretStore $secrets,
        FeatureRegistry $registry,
        mixed $container,
    ): void {
        $action = (string) $request->request->get('seoStudioAction', 'save');

        if ($action === 'test') {
            /** @var ConnectionTester $tester */
            $tester = $container->get(ConnectionTester::class);
            $result = $tester->test();

            $result->ok ? Message::addConfirmation($result->message) : Message::addError($result->message);

            return;
        }

        if ($action === 'license') {
            /** @var LicenseManager $lm */
            $lm = $container->get(LicenseManager::class);
            $key = (string) $request->request->get('license_key', '');

            if ($lm->activate($key, $request->getHost())) {
                Message::addConfirmation($this->trans('licenseActivated', 'Lizenz aktiviert. Vielen Dank!'));
            } else {
                Message::addError($this->trans('licenseInvalid', 'Lizenz ungültig') . ': ' . ($lm->lastMessage() ?: '—'));
            }

            return;
        }

        if ($action === 'llmssummary') {
            try {
                /** @var \VTinnovations\SeoStudio\Feature\LlmsTxt\SummaryGenerator $generator */
                $generator = $container->get(\VTinnovations\SeoStudio\Feature\LlmsTxt\SummaryGenerator::class);
                $summary = $generator->generateAndStore();
                Message::addConfirmation('llms.txt-Zusammenfassung erzeugt: „' . $summary . '“');
            } catch (\Throwable $e) {
                Message::addError('Zusammenfassung fehlgeschlagen: ' . $e->getMessage());
            }

            return;
        }

        // ── Save ────────────────────────────────────────────────────────
        $provider = (string) $request->request->get('aiProvider', 'anthropic');
        if (!\in_array($provider, ['anthropic', 'openai', 'compatible'], true)) {
            $provider = 'anthropic';
        }

        $writeMode = (string) $request->request->get('writeMode', 'propose');
        if (!\in_array($writeMode, ['propose', 'fillEmpty'], true)) {
            $writeMode = 'propose';
        }

        $sameAs = array_values(array_filter(array_map(
            'trim',
            explode("\n", (string) $request->request->get('schemaOrgSameAs', '')),
        ), static fn (string $line): bool => $line !== ''));

        $values = [
            'aiProvider' => $provider,
            'aiModel' => trim((string) $request->request->get('aiModel', '')),
            'aiBaseUrl' => trim((string) $request->request->get('aiBaseUrl', '')),
            'noExternalCalls' => $request->request->getBoolean('noExternalCalls'),
            'monthlyTokenBudget' => max(0, $request->request->getInt('monthlyTokenBudget')),
            'writeMode' => $writeMode,
            'cronBatchSize' => min(50, max(1, $request->request->getInt('cronBatchSize', 5))),
            'metaCronEnabled' => $request->request->getBoolean('metaCronEnabled'),
            'languageOverride' => trim((string) $request->request->get('languageOverride', '')),
            'schemaOrgName' => trim((string) $request->request->get('schemaOrgName', '')),
            'schemaOrgLogo' => trim((string) $request->request->get('schemaOrgLogo', '')),
            'schemaOrgSameAs' => $sameAs,
            'schemaEnableOrganization' => $request->request->getBoolean('schemaEnableOrganization'),
            'schemaEnableBreadcrumb' => $request->request->getBoolean('schemaEnableBreadcrumb'),
            'schemaEnableArticle' => $request->request->getBoolean('schemaEnableArticle'),
            'llmsTxtAiSummary' => $request->request->getBoolean('llmsTxtAiSummary'),
        ];

        foreach (array_keys($registry->all()) as $featureId) {
            $values['feature' . ucfirst($featureId)] = $request->request->getBoolean('feature_' . $featureId);
        }

        $config->setMany($values);

        // API key: blank = keep the stored one; "!delete" clears it.
        $apiKey = trim((string) $request->request->get('aiApiKey', ''));
        if ($apiKey === '!delete') {
            $secrets->delete('ai_api_key');
        } elseif ($apiKey !== '') {
            $secrets->set('ai_api_key', $apiKey);
        }

        Message::addConfirmation($this->trans('saved', 'Einstellungen gespeichert.'));
    }

    private function render(
        ConfigProvider $config,
        SecretStore $secrets,
        FeatureRegistry $registry,
        FeatureState $featureState,
        TokenBudget $budget,
        mixed $container,
    ): string {
        $tokenValue = $container->get('contao.csrf.token_manager')
            ->getToken($container->getParameter('contao.csrf_token_name'))
            ->getValue();

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $provider = (string) $config->get('aiProvider', 'anthropic');
        $keySet = $secrets->has('ai_api_key');

        // Token budget status line
        $budgetInfo = '';
        $usage = $budget->getUsageThisMonth();
        $limit = $budget->getMonthlyBudget();
        if ($limit > 0) {
            $pct = min(100, (int) round($usage / $limit * 100));
            $cls = $pct >= 100 ? 'tl_error' : ($pct >= 80 ? 'tl_info' : 'tl_confirm');
            $budgetInfo = sprintf(
                '<p class="%s">%s</p>',
                $cls,
                $e(sprintf(
                    $this->trans('budgetStatus', 'Token-Verbrauch diesen Monat: %s von %s (%d%%)'),
                    number_format($usage, 0, ',', '.'),
                    number_format($limit, 0, ',', '.'),
                    $pct,
                )),
            );
        } elseif ($usage > 0) {
            $budgetInfo = sprintf(
                '<p class="tl_info">%s</p>',
                $e(sprintf(
                    $this->trans('budgetStatusUnlimited', 'Token-Verbrauch diesen Monat: %s (kein Limit gesetzt)'),
                    number_format($usage, 0, ',', '.'),
                )),
            );
        }

        // Feature toggles
        $featureRows = '';
        foreach ($registry->all() as $id => $feature) {
            $allowed = $featureState->tierAllows($feature);
            $checked = (bool) $config->get('feature' . ucfirst($id), false);
            $label = $this->trans('features.' . $id, $feature->getLabel());

            $featureRows .= sprintf(
                '<div class="widget w50 cbx"><div id="ctrl_feature_%1$s" class="tl_checkbox_single_container">'
                . '<input type="checkbox" name="feature_%1$s" id="opt_feature_%1$s" class="tl_checkbox" value="1"%2$s%3$s> '
                . '<label for="opt_feature_%1$s">%4$s%5$s</label></div></div>',
                $e($id),
                $checked && $allowed ? ' checked' : '',
                $allowed ? '' : ' disabled',
                $e($label),
                $allowed ? '' : ' <span class="seo-studio-muted">(' . $e($this->trans('tierLocked', 'höherer Tarif nötig')) . ')</span>',
            );
        }

        $selected = static fn (string $a, string $b): string => $a === $b ? ' selected' : '';
        $checkedIf = static fn (bool $v): string => $v ? ' checked' : '';

        $t = fn (string $key, string $fallback): string => $e($this->trans($key, $fallback));

        $this->registerTabAssets();

        // ── AI settings ─────────────────────────────────────────────
        $aiFieldset = '<fieldset class="tl_tbox block">'
            . '<legend>' . $t('legendAi', 'KI-Einstellungen') . '</legend>'
            . $budgetInfo
            . '<div class="widget w50"><h3><label for="ctrl_aiProvider">' . $t('aiProvider', 'KI-Anbieter') . '</label></h3>'
            . '<select name="aiProvider" id="ctrl_aiProvider" class="tl_select">'
            . '<option value="anthropic"' . $selected($provider, 'anthropic') . '>Anthropic (Claude)</option>'
            . '<option value="openai"' . $selected($provider, 'openai') . '>OpenAI</option>'
            . '<option value="compatible"' . $selected($provider, 'compatible') . '>OpenAI-kompatibel (eigene URL)</option>'
            . '</select></div>'
            . '<div class="widget w50"><h3><label for="ctrl_aiModel">' . $t('aiModel', 'Modell (leer = Standard)') . '</label></h3>'
            . '<input type="text" name="aiModel" id="ctrl_aiModel" class="tl_text" value="' . $e($config->get('aiModel', '')) . '" placeholder="claude-haiku-4-5 / gpt-4o-mini"></div>'
            . '<div class="widget w50"><h3><label for="ctrl_aiApiKey">' . $t('aiApiKey', 'API-Schlüssel') . '</label></h3>'
            . '<input type="password" name="aiApiKey" id="ctrl_aiApiKey" class="tl_text" value="" autocomplete="new-password" placeholder="'
            . ($keySet ? $t('keySet', 'gespeichert — leer lassen zum Behalten, "!delete" zum Löschen') : $t('keyEmpty', 'noch kein Schlüssel gespeichert')) . '">'
            . '</div>'
            . '<div class="widget w50"><h3><label for="ctrl_aiBaseUrl">' . $t('aiBaseUrl', 'Basis-URL (nur für "kompatibel")') . '</label></h3>'
            . '<input type="text" name="aiBaseUrl" id="ctrl_aiBaseUrl" class="tl_text" value="' . $e($config->get('aiBaseUrl', '')) . '" placeholder="https://..."></div>'
            . '<div class="widget w50"><h3><label for="ctrl_monthlyTokenBudget">' . $t('monthlyTokenBudget', 'Monatliches Token-Budget (0 = unbegrenzt)') . '</label></h3>'
            . '<input type="number" name="monthlyTokenBudget" id="ctrl_monthlyTokenBudget" class="tl_text" min="0" value="' . $e((int) $config->get('monthlyTokenBudget', 0)) . '"></div>'
            . '<div class="widget w50 cbx m12"><div class="tl_checkbox_single_container">'
            . '<input type="checkbox" name="noExternalCalls" id="ctrl_noExternalCalls" class="tl_checkbox" value="1"' . $checkedIf((bool) $config->get('noExternalCalls', false)) . '> '
            . '<label for="ctrl_noExternalCalls">' . $t('noExternalCalls', 'Keine externen Aufrufe (KI komplett deaktivieren)') . '</label></div></div>'
            . '</fieldset>';

        // ── Features ────────────────────────────────────────────────
        $featuresFieldset = '<fieldset class="tl_tbox block">'
            . '<legend>' . $t('legendFeatures', 'Funktionen') . '</legend>'
            . '<p>Deaktivierte Funktionen verschwinden komplett aus dem Backend (Menüpunkte, Buttons, Panels).</p>'
            . $featureRows
            . '</fieldset>';

        // ── Behavior ────────────────────────────────────────────────
        $behaviorFieldset = '<fieldset class="tl_tbox block">'
            . '<legend>' . $t('legendBehavior', 'Verhalten') . '</legend>'
            . '<div class="widget w50"><h3><label for="ctrl_writeMode">' . $t('writeMode', 'Schreibmodus') . '</label></h3>'
            . '<select name="writeMode" id="ctrl_writeMode" class="tl_select">'
            . '<option value="propose"' . $selected((string) $config->get('writeMode', 'propose'), 'propose') . '>' . $t('writeModePropose', 'Vorschlagen → Vorschau → Übernehmen') . '</option>'
            . '<option value="fillEmpty"' . $selected((string) $config->get('writeMode', 'propose'), 'fillEmpty') . '>' . $t('writeModeFillEmpty', 'Nur leere Felder automatisch füllen') . '</option>'
            . '</select></div>'
            . '<div class="widget w50"><h3><label for="ctrl_cronBatchSize">' . $t('cronBatchSize', 'Cron-Batchgröße') . '</label></h3>'
            . '<input type="number" name="cronBatchSize" id="ctrl_cronBatchSize" class="tl_text" min="1" max="50" value="' . $e((int) $config->get('cronBatchSize', 5)) . '"></div>'
            . '<div class="widget w50"><h3><label for="ctrl_languageOverride">' . $t('languageOverride', 'Sprach-Override (leer = Sprache der Startseite)') . '</label></h3>'
            . '<input type="text" name="languageOverride" id="ctrl_languageOverride" class="tl_text" value="' . $e($config->get('languageOverride', '')) . '" placeholder="de"></div>'
            . '<div class="widget w50 cbx m12"><div class="tl_checkbox_single_container">'
            . '<input type="checkbox" name="metaCronEnabled" id="ctrl_metaCronEnabled" class="tl_checkbox" value="1"' . $checkedIf((bool) $config->get('metaCronEnabled', false)) . '> '
            . '<label for="ctrl_metaCronEnabled">' . $t('metaCronEnabled', 'Cron: leere Titel/Beschreibungen automatisch füllen (verbraucht Tokens)') . '</label></div></div>'
            . '</fieldset>';

        // ── Schema.org ──────────────────────────────────────────────
        $schemaFieldset = '<fieldset class="tl_tbox block">'
            . '<legend>' . $t('legendSchema', 'Strukturierte Daten (Schema.org)') . '</legend>'
            . '<div class="widget w50"><h3><label for="ctrl_schemaOrgName">' . $t('schemaOrgName', 'Organisation: Name') . '</label></h3>'
            . '<input type="text" name="schemaOrgName" id="ctrl_schemaOrgName" class="tl_text" value="' . $e($config->get('schemaOrgName', '')) . '"></div>'
            . '<div class="widget w50"><h3><label for="ctrl_schemaOrgLogo">' . $t('schemaOrgLogo', 'Organisation: Logo-URL') . '</label></h3>'
            . '<input type="text" name="schemaOrgLogo" id="ctrl_schemaOrgLogo" class="tl_text" value="' . $e($config->get('schemaOrgLogo', '')) . '" placeholder="https://.../logo.png"></div>'
            . '<div class="widget clr long"><h3><label for="ctrl_schemaOrgSameAs">' . $t('schemaOrgSameAs', 'Organisation: Profile (sameAs, eine URL pro Zeile)') . '</label></h3>'
            . '<textarea name="schemaOrgSameAs" id="ctrl_schemaOrgSameAs" class="tl_textarea" rows="3">' . $e(implode("\n", (array) $config->get('schemaOrgSameAs', []))) . '</textarea></div>'
            . '<div class="widget w50 cbx"><div class="tl_checkbox_single_container">'
            . '<input type="checkbox" name="schemaEnableOrganization" id="ctrl_seOrg" class="tl_checkbox" value="1"' . $checkedIf((bool) $config->get('schemaEnableOrganization', true)) . '> '
            . '<label for="ctrl_seOrg">Organization</label></div></div>'
            . '<div class="widget w50 cbx"><div class="tl_checkbox_single_container">'
            . '<input type="checkbox" name="schemaEnableBreadcrumb" id="ctrl_seBc" class="tl_checkbox" value="1"' . $checkedIf((bool) $config->get('schemaEnableBreadcrumb', true)) . '> '
            . '<label for="ctrl_seBc">BreadcrumbList</label></div></div>'
            . '<div class="widget w50 cbx"><div class="tl_checkbox_single_container">'
            . '<input type="checkbox" name="schemaEnableArticle" id="ctrl_seArt" class="tl_checkbox" value="1"' . $checkedIf((bool) $config->get('schemaEnableArticle', true)) . '> '
            . '<label for="ctrl_seArt">Article (News)</label></div></div>'
            . '<div class="widget clr long"><h3>' . $t('llmsTxtSummary', 'llms.txt: Kurzbeschreibung der Website') . '</h3>'
            . '<p>' . ($config->get('llmsTxtSummaryText', '') !== '' ? $e($config->get('llmsTxtSummaryText', '')) : '<em>noch keine — per KI erzeugen oder leer lassen</em>') . '</p>'
            . '<button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'llmssummary\'">' . $t('llmsSummaryGenerate', 'Mit KI erzeugen') . '</button></div>'
            . '</fieldset>';

        $licenseFieldset = $this->renderLicenseFieldset($e, $container);

        $tabs = $this->renderTabs('settings', [
            ['license', $this->trans('legendLicense', 'Lizenz'), $licenseFieldset],
            ['ai', $this->trans('legendAi', 'KI-Einstellungen'), $aiFieldset],
            ['features', $this->trans('legendFeatures', 'Funktionen'), $featuresFieldset],
            ['behavior', $this->trans('legendBehavior', 'Verhalten'), $behaviorFieldset],
            ['schema', $this->trans('legendSchema', 'Strukturierte Daten'), $schemaFieldset],
        ]);

        $intro = '<p>Zentrale Einstellungen für KI-Anbieter, Funktionen, Verhalten und strukturierte Daten. '
            . 'Deaktivierte Funktionen verschwinden komplett aus dem Backend.</p>';

        $form = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($tokenValue) . '">'
            . '<input type="hidden" name="seoStudioAction" value="save">'
            . '<div class="tl_formbody_edit">' . $tabs . '</div>'
            . '<div class="tl_formbody_submit"><div class="tl_submit_container">'
            . '<button type="submit" class="tl_submit">' . $t('save', 'Speichern') . '</button> '
            . '<button type="submit" class="tl_submit" name="seoStudioAction" value="test" onclick="this.form.seoStudioAction.value=\'test\'">' . $t('testConnection', 'Verbindung testen') . '</button>'
            . '</div></div>'
            . '</form>';

        return $this->renderShell($intro, $form);
    }

    /**
     * @param callable(mixed):string $e
     */
    private function renderLicenseFieldset(callable $e, mixed $container): string
    {
        /** @var LicenseManager $lm */
        $lm = $container->get(LicenseManager::class);
        /** @var LicenseGuard $guard */
        $guard = $container->get(LicenseGuard::class);

        $status = match ($guard->state()) {
            LicenseGuard::STATE_LICENSED => '<p class="tl_confirm">' . $e($this->trans('licenseActive', 'Lizenz aktiv'))
                . ($lm->getExpiresAt() !== null
                    ? ' — ' . $e($this->trans('licenseUntil', 'gültig bis')) . ' ' . date('d.m.Y', (int) $lm->getExpiresAt())
                    : ' (' . $e($this->trans('licenseLifetime', 'unbegrenzt')) . ')') . '.</p>',
            LicenseGuard::STATE_DEMO => '<p class="tl_info">' . $e(sprintf(
                $this->trans('licenseDemo', 'Demo-Lizenz aktiv — noch %d Tag(e). Frei: SEO-Score, Analyse, Schema.org, Social, Meta. Gesperrt: Text-Optimierung, Glossar, FAQ, SEO/GEO/AEO-Score, Aktualität, llms.txt, Bilder.'),
                $guard->demoDaysLeft(),
            )) . '</p>',
            default => '<p class="tl_error">' . $e($this->trans('licenseUnlicensedMsg', 'Keine gültige Lizenz — bitte Schlüssel eintragen. Auch die Demo benötigt einen (kostenlosen) Schlüssel; alle Funktionen sind bis dahin gesperrt.')) . '</p>',
        };

        $key = $e($lm->getLicenseKey());

        return '<fieldset class="tl_tbox block"><legend>' . $this->trans('legendLicense', 'Lizenz') . '</legend>'
            . $status
            . '<p>' . $this->trans('licenseIntro', 'Kostenlose Demo-Lizenz und Vollversion erhältlich auf')
            . ' <a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a>.</p>'
            . '<div class="widget clr long"><h3><label for="ctrl_license_key">' . $this->trans('licenseKeyLabel', 'Lizenzschlüssel') . '</label></h3>'
            . '<input type="text" name="license_key" id="ctrl_license_key" class="tl_text" value="' . $key . '" autocomplete="off" spellcheck="false" placeholder="' . $e($this->trans('licenseKeyPlaceholder', 'Lizenzschlüssel eintragen')) . '"></div>'
            . '<div class="tl_submit_container" style="margin-top:8px">'
            . '<button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'license\'">' . $this->trans('licenseActivate', 'Lizenz aktivieren') . '</button>'
            . '</div>'
            . '</fieldset>';
    }

    private function trans(string $key, string $fallback): string
    {
        $value = $GLOBALS['TL_LANG']['SEO_STUDIO'] ?? null;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($value) || !isset($value[$segment])) {
                return $fallback;
            }
            $value = $value[$segment];
        }

        return \is_string($value) && $value !== '' ? $value : $fallback;
    }
}
