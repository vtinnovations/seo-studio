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
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Feature\Audit\AiCrawler;
use VTinnovations\SeoStudio\Feature\Audit\AnswerFirstChecker;
use VTinnovations\SeoStudio\Feature\Audit\DuplicateChecker;
use VTinnovations\SeoStudio\Feature\Audit\HeadingAuditor;
use VTinnovations\SeoStudio\Feature\Audit\RobotsAuditor;
use VTinnovations\SeoStudio\Feature\Freshness\StalePageFinder;
use VTinnovations\SeoStudio\Feature\GeoScore\GeoScoreCalculator;
use VTinnovations\SeoStudio\Feature\Images\ImageAuditor;
use VTinnovations\SeoStudio\Feature\Images\ImageSizeWizard;

/**
 * BE_MOD callback "SEO Studio → Analyse": all read-only checks in one place —
 * AI-crawler robots.txt audit, heading/answer-first structure audit, duplicate
 * meta detection, GEO score, freshness monitor, and image audit. The two
 * write actions (GEO score compute, image-size wizard) are click-triggered.
 */
final class AuditModule
{
    use BackendTabsTrait;

    public function generate(): string
    {
        $container = System::getContainer();

        /** @var FeatureState $featureState */
        $featureState = $container->get(FeatureState::class);
        /** @var RobotsAuditor $auditor */
        $auditor = $container->get(RobotsAuditor::class);

        $requestStack = $container->get('request_stack');
        \assert($requestStack instanceof \Symfony\Component\HttpFoundation\RequestStack);
        $request = $requestStack->getCurrentRequest();

        if ($request instanceof Request && $request->isMethod('POST')) {
            $action = (string) $request->request->get('seoStudioAction', 'robots');

            try {
                if ($action === 'structure' && $featureState->isEnabled('audit')) {
                    $this->runStructureAudit($request->request->getInt('seoStudioPageId'), $container);
                } elseif ($action === 'scores' && $featureState->isEnabled('geoScore')) {
                    /** @var GeoScoreCalculator $calculator */
                    $calculator = $container->get(GeoScoreCalculator::class);
                    $withLlm = $request->request->getBoolean('withLlm');
                    $done = $calculator->computeBatch(10, $withLlm);
                    Message::addConfirmation(sprintf('%d Seite(n) bewertet%s.', $done, $withLlm ? ' (mit KI-Check)' : ' (deterministisch)'));
                } elseif ($action === 'imagewizard' && $featureState->isEnabled('images')) {
                    /** @var ImageSizeWizard $wizard */
                    $wizard = $container->get(ImageSizeWizard::class);
                    $result = $wizard->apply();
                    Message::addConfirmation(sprintf('Bildgröße „SEO Studio Responsiv“ (ID %d) %d Element(en) zugewiesen.', $result['presetId'], $result['assigned']));
                } elseif ($action === 'robots' && $featureState->isEnabled('audit')) {
                    $results = $auditor->run();
                    Message::addConfirmation(sprintf('Crawler-Prüfung abgeschlossen: %d Domain(s).', \count($results)));
                }
            } catch (\Throwable $e) {
                Message::addError('Aktion fehlgeschlagen: ' . $e->getMessage());
            }

            Controller::redirect($request->getRequestUri());
        }

        return $this->render($auditor, $featureState, $container);
    }

    private function runStructureAudit(int $pageId, mixed $container): void
    {
        if ($pageId <= 0) {
            Message::addError('Bitte eine Seite auswählen.');

            return;
        }

        /** @var HeadingAuditor $headings */
        $headings = $container->get(HeadingAuditor::class);
        /** @var \VTinnovations\SeoStudio\Core\Config\ConfigProvider $config */
        $config = $container->get(\VTinnovations\SeoStudio\Core\Config\ConfigProvider::class);

        $result = [
            'pageId' => $pageId,
            'time' => time(),
            'headings' => $headings->audit($pageId),
            'answerFirst' => null,
        ];

        // Answer-first is the only LLM part — degrade gracefully without key.
        try {
            /** @var AnswerFirstChecker $answerFirst */
            $answerFirst = $container->get(AnswerFirstChecker::class);
            $result['answerFirst'] = $answerFirst->check($pageId)->toArray();
        } catch (\Throwable $e) {
            $result['answerFirstError'] = $e->getMessage();
        }

        $config->set('lastStructureAudit', $result);
        Message::addConfirmation('Struktur-Audit abgeschlossen.');
    }

    private function render(RobotsAuditor $auditor, FeatureState $featureState, mixed $container): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tokenValue = $container->get('contao.csrf.token_manager')
            ->getToken($container->getParameter('contao.csrf_token_name'))
            ->getValue();

        $this->registerTabAssets();

        /** @var \VTinnovations\SeoStudio\Core\Content\RootScope $rootScope */
        $rootScope = $container->get(\VTinnovations\SeoStudio\Core\Content\RootScope::class);
        $stack = $container->get('request_stack');
        $request = $stack instanceof \Symfony\Component\HttpFoundation\RequestStack ? $stack->getCurrentRequest() : null;
        $rootId = $rootScope->sanitize($request !== null ? (int) $request->query->get('root', 0) : 0);
        $scopeIds = $rootId > 0 ? $rootScope->pageIds($rootId) : null;

        // Build the available tabs (id, label, body) — skip disabled features.
        $audit = $featureState->isEnabled('audit');
        $tabs = [];

        if ($audit) {
            $tabs[] = ['crawler', 'KI-Crawler', $this->renderCrawler($e, $tokenValue, $auditor)];
            $tabs[] = ['structure', 'Struktur', $this->renderStructureSection($e, $tokenValue, $container, $scopeIds)];
            $tabs[] = ['duplicates', 'Duplikate', $this->renderDuplicatesSection($e, $container)];
        }
        if ($featureState->isEnabled('geoScore')) {
            $tabs[] = ['geoscore', 'SEO/GEO/AEO-Score', $this->renderGeoScore($e, $tokenValue, $featureState, $container, $scopeIds)];
        }
        if ($featureState->isEnabled('freshness')) {
            $tabs[] = ['freshness', 'Aktualität', $this->renderFreshness($e, $featureState, $container, $scopeIds)];
        }
        if ($featureState->isEnabled('images')) {
            $tabs[] = ['images', 'Bilder', $this->renderImages($e, $tokenValue, $featureState, $container)];
        }

        $intro = '<p>Prüfungen und Bewertungen deiner Website. Nichts wird verändert — du bekommst nur Berichte und '
            . 'Korrektur-Empfehlungen. Der GEO-Score und der Bild-Assistent sind die einzigen Aktionen mit Schreibzugriff '
            . '(jeweils per Klick).</p>';

        $tabsHtml = $this->renderTabs('analyse', $tabs);
        $body = $tabsHtml !== ''
            ? $this->renderRootFilter($e, $rootScope, $rootId, 'seo_analyse') . $tabsHtml
            : '<p class="tl_info">Alle Analyse-Funktionen sind deaktiviert (SEO Studio → Einstellungen).</p>';

        return $this->renderShell($intro, $body);
    }

    private function renderCrawler(callable $e, string $token, RobotsAuditor $auditor): string
    {
        $results = $auditor->getCachedResults();
        $cacheTime = $auditor->getCacheTime();
        $catalogue = AiCrawler::catalogue();

        $html = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="robots">'
            . '<fieldset class="tl_tbox block">'
            . '<legend>KI-Crawler-Audit (robots.txt)</legend>'
            . '<p>Prüft für jede Domain, ob KI-Crawler (ChatGPT, Claude, Perplexity, Gemini …) die Website lesen dürfen. '
            . 'Blockierte Crawler bedeuten: die Website kann in KI-Antworten nicht zitiert werden.</p>'
            . ($cacheTime > 0 ? '<p class="tl_info">Letzte Prüfung: ' . $e(date('d.m.Y H:i', $cacheTime)) . '</p>' : '')
            . '<div class="tl_submit_container" style="margin:8px 0 16px"><button type="submit" class="tl_submit">Jetzt prüfen</button></div>'
            . '</fieldset></form>';

        foreach ($results as $result) {
            $html .= '<fieldset class="tl_tbox block"><legend>' . $e($result['domain']) . ($result['title'] !== '' ? ' — ' . $e($result['title']) : '') . '</legend>';

            if ($result['status'] === 'error') {
                $html .= '<p class="tl_error">robots.txt konnte nicht geladen werden: ' . $e($result['error']) . '</p></fieldset>';
                continue;
            }

            if ($result['status'] === 'missing') {
                $html .= '<p class="tl_info">Keine robots.txt gefunden — damit dürfen alle Crawler zugreifen, aber es wird keine Sitemap angekündigt.</p>';
            }

            $html .= '<table class="tl_listing" style="width:100%"><thead><tr>'
                . '<th class="tl_folder_tlist">Crawler</th>'
                . '<th class="tl_folder_tlist">Zweck</th>'
                . '<th class="tl_folder_tlist">Status</th>'
                . '</tr></thead><tbody>';

            foreach ((array) $result['crawlers'] as $crawlerToken => $verdict) {
                $info = $catalogue[$crawlerToken] ?? ['label' => $crawlerToken, 'purpose' => ''];
                $allowed = (bool) ($verdict['allowed'] ?? true);
                $explicit = (bool) ($verdict['explicit'] ?? false);

                $badge = $allowed
                    ? '<span class="seo-studio-badge seo-studio-badge--good">erlaubt' . ($explicit ? '' : ' (implizit)') . '</span>'
                    : '<span class="seo-studio-badge seo-studio-badge--bad">blockiert</span>';

                $html .= '<tr class="tl_file_list"><td class="tl_file_list">' . $e($info['label']) . '</td>'
                    . '<td class="tl_file_list">' . $e($info['purpose']) . '</td>'
                    . '<td class="tl_file_list">' . $badge . '</td></tr>';
            }

            $html .= '</tbody></table>';

            $html .= $result['sitemapAnnounced']
                ? '<p class="tl_confirm" style="margin-top:8px">Sitemap angekündigt: ' . $e(implode(', ', (array) $result['sitemaps'])) . '</p>'
                : '<p class="tl_info" style="margin-top:8px">Keine Sitemap-Zeile in der robots.txt. Empfehlung: <code>Sitemap: https://' . $e($result['domain']) . '/sitemap.xml</code> ergänzen.</p>';

            $fix = $auditor->buildFixSuggestion($result);
            if ($fix !== '') {
                $html .= '<h3 style="margin-top:12px">Korrektur-Vorschlag</h3>'
                    . '<p>Diesen Block in den Startpunkt der Website (Seitenstruktur → Root-Seite → Feld „Eigene robots.txt-Einträge“) einfügen:</p>'
                    . '<pre class="seo-studio-pre">' . $e($fix) . '</pre>';
            }

            $html .= '</fieldset>';
        }

        if ($results === [] && $cacheTime === 0) {
            $html .= '<p class="tl_info">Noch keine Prüfung durchgeführt — „Jetzt prüfen“ klicken.</p>';
        }

        return $html;
    }

    /**
     * @param list<int>|null $scopeIds regular page ids to show (null = all roots)
     */
    private function renderGeoScore(callable $e, string $token, FeatureState $featureState, mixed $container, ?array $scopeIds): string
    {
        if (!$featureState->isEnabled('geoScore')) {
            return '';
        }

        /** @var GeoScoreCalculator $calculator */
        $calculator = $container->get(GeoScoreCalculator::class);
        $scores = $calculator->getScores();

        if ($scopeIds !== null) {
            $set = array_flip($scopeIds);
            $scores = array_values(array_filter($scores, static fn (array $r): bool => isset($set[(int) $r['pageId']])));
        }

        $html = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="scores">'
            . '<fieldset class="tl_tbox block"><legend>SEO/GEO/AEO-Score (Sichtbarkeits-Reifegrad)</legend>'
            . '<p>Kombinierter Score aus klassischem <strong>SEO</strong>, <strong>GEO</strong> (generative Suche) und <strong>AEO</strong> (Antwort-Engines). Bewertet pro Seite: Meta, Überschriften, Antwort-zuerst-Einstieg, strukturierte Formate, FAQ, Aktualität, Schema. 10 Seiten pro Durchlauf.</p>'
            . '<div class="tl_submit_container" style="margin:8px 0 12px">'
            . '<button type="submit" class="tl_submit">Scores berechnen (ohne KI)</button> '
            . '<button type="submit" class="tl_submit" name="withLlm" value="1">Scores berechnen (mit KI-Check)</button>'
            . '</div>';

        if ($scores !== []) {
            $html .= '<table class="tl_listing" style="width:100%"><thead><tr>'
                . '<th class="tl_folder_tlist">Seite</th><th class="tl_folder_tlist">Score</th>'
                . '<th class="tl_folder_tlist">Schwachstellen</th><th class="tl_folder_tlist">Stand</th></tr></thead><tbody>';

            foreach ($scores as $row) {
                $components = json_decode((string) $row['components'], true) ?: [];
                $weak = [];
                foreach ($components as $component) {
                    if ((float) ($component['points'] ?? 0) < (int) ($component['max'] ?? 0) / 2) {
                        $weak[] = (string) ($component['note'] ?? '');
                    }
                }

                $score = (int) $row['score'];
                $badgeClass = $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad');

                $html .= '<tr class="tl_file_list">'
                    . '<td class="tl_file_list">' . $e($row['title']) . ' <span class="seo-studio-muted">(ID ' . (int) $row['pageId'] . ')</span></td>'
                    . '<td class="tl_file_list"><span class="seo-studio-badge seo-studio-badge--' . $badgeClass . '">' . $score . '/100</span></td>'
                    . '<td class="tl_file_list">' . $e(implode(' · ', \array_slice($weak, 0, 3))) . '</td>'
                    . '<td class="tl_file_list">' . $e(date('d.m.Y H:i', (int) $row['tstamp'])) . '</td></tr>';
            }

            $html .= '</tbody></table>';
        } else {
            $html .= '<p class="tl_info">Noch keine Scores — „Scores berechnen“ klicken.</p>';
        }

        return $html . '</fieldset></form>';
    }

    /**
     * @param list<int>|null $scopeIds regular page ids to show (null = all roots)
     */
    private function renderFreshness(callable $e, FeatureState $featureState, mixed $container, ?array $scopeIds): string
    {
        if (!$featureState->isEnabled('freshness')) {
            return '';
        }

        /** @var StalePageFinder $finder */
        $finder = $container->get(StalePageFinder::class);
        $stale = $finder->find(14, 200);

        if ($scopeIds !== null) {
            $set = array_flip($scopeIds);
            $stale = array_values(array_filter($stale, static fn (array $r): bool => isset($set[(int) $r['id']])));
        }

        $html = '<fieldset class="tl_tbox block"><legend>Aktualitäts-Monitor (älter als 14 Tage)</legend>';

        if ($stale === []) {
            $html .= '<p class="tl_confirm">Alle veröffentlichten Seiten wurden in den letzten 14 Tagen aktualisiert.</p>';
        } else {
            $html .= '<p>KI-Suchmaschinen gewichten Aktualität. Älteste zuerst:</p><ul>';
            foreach ($stale as $page) {
                $html .= '<li>' . $e($page['title']) . ' <span class="seo-studio-muted">(ID ' . $page['id'] . ')</span> — vor ' . $page['ageDays'] . ' Tagen geändert</li>';
            }
            $html .= '</ul>';
        }

        return $html . '</fieldset>';
    }

    private function renderImages(callable $e, string $token, FeatureState $featureState, mixed $container): string
    {
        if (!$featureState->isEnabled('images')) {
            return '';
        }

        /** @var ImageAuditor $auditor */
        $auditor = $container->get(ImageAuditor::class);
        $audit = $auditor->run();

        $html = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="imagewizard">'
            . '<fieldset class="tl_tbox block"><legend>Bild-Audit (Performance)</legend>';

        $unassignedCount = \count($audit['unassigned']);
        if ($unassignedCount > 0) {
            $html .= '<p class="tl_error">' . $unassignedCount . ' Bild-Element(e) ohne Bildgrößen-Zuweisung — Originale werden unskaliert ausgeliefert.</p>'
                . '<div class="tl_submit_container" style="margin:8px 0">'
                . '<button type="submit" class="tl_submit" onclick="return confirm(\'Bildgröße „SEO Studio Responsiv“ (1200px, proportional, Lazy-Loading) anlegen und ' . $unassignedCount . ' Element(en) zuweisen?\')">Automatisch zuweisen</button>'
                . '</div>';
        } else {
            $html .= '<p class="tl_confirm">Alle Bild-Elemente haben eine Bildgrößen-Zuweisung.</p>';
        }

        if ($audit['oversized'] !== []) {
            $html .= '<h3 style="margin-top:10px">Übergroße Originale</h3><ul>';
            foreach ($audit['oversized'] as $file) {
                $html .= '<li><code>' . $e($file['path']) . '</code> — ' . $file['width'] . '×' . $file['height'] . ' px, ' . number_format($file['bytes'] / 1024, 0, ',', '.') . ' KB</li>';
            }
            $html .= '</ul><p class="tl_help">Empfehlung: Originale vor dem Upload auf max. 2560 px verkleinern.</p>';
        }

        if (!$audit['webp']['configured']) {
            $html .= '<h3 style="margin-top:10px">WebP nicht aktiviert</h3>'
                . '<p>Moderne Formate sparen 25-60 % Dateigröße. Diesen Block in <code>config/config.yaml</code> einfügen (SEO Studio schreibt diese Datei bewusst nie selbst):</p>'
                . '<pre class="seo-studio-pre">' . $e($audit['webp']['snippet']) . '</pre>';
        } else {
            $html .= '<p class="tl_confirm" style="margin-top:8px">Modernes Bildformat (WebP/AVIF) ist konfiguriert.</p>';
        }

        return $html . '</fieldset></form>';
    }

    /**
     * @param list<int>|null $scopeIds pages under the chosen root (null = all)
     */
    private function renderStructureSection(callable $e, string $tokenValue, mixed $container, ?array $scopeIds): string
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $container->get('database_connection');
        /** @var \VTinnovations\SeoStudio\Core\Config\ConfigProvider $config */
        $config = $container->get(\VTinnovations\SeoStudio\Core\Config\ConfigProvider::class);

        $pages = $connection->fetchAllAssociative(
            "SELECT id, title FROM tl_page WHERE type = 'regular' AND published = '1' ORDER BY title",
        );

        // Restrict the page picker to the selected start point.
        if ($scopeIds !== null) {
            $set = array_flip($scopeIds);
            $pages = array_values(array_filter($pages, static fn (array $p): bool => isset($set[(int) $p['id']])));
        }

        $last = $config->get('lastStructureAudit', null);
        $lastPageId = \is_array($last) ? (int) ($last['pageId'] ?? 0) : 0;

        $options = '';
        foreach ($pages as $page) {
            $options .= '<option value="' . (int) $page['id'] . '"' . ((int) $page['id'] === $lastPageId ? ' selected' : '') . '>'
                . $e($page['title']) . ' (ID ' . (int) $page['id'] . ')</option>';
        }

        $html = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($tokenValue) . '">'
            . '<input type="hidden" name="seoStudioAction" value="structure">'
            . '<fieldset class="tl_tbox block"><legend>Struktur-Audit (Überschriften + Antwort-zuerst)</legend>'
            . '<p>Deterministische Überschriften-Prüfung plus KI-Check, ob der Einstiegsabsatz das Thema direkt beantwortet (AEO). Prüft eine einzelne Seite' . ($scopeIds !== null ? ' aus dem gewählten Startpunkt' : '') . '.</p>'
            . ($options === ''
                ? '<p class="tl_info">Keine Seiten im gewählten Startpunkt.</p>'
                : '<div class="seo-studio-inline-row">'
                    . '<select name="seoStudioPageId" class="tl_select">' . $options . '</select>'
                    . '<button type="submit" class="tl_submit">Seite prüfen</button>'
                    . '</div>');

        if (\is_array($last) && $lastPageId > 0) {
            $html .= '<h3 style="margin-top:12px">Ergebnis (' . $e(date('d.m.Y H:i', (int) ($last['time'] ?? 0))) . ')</h3>';

            foreach ((array) ($last['headings'] ?? []) as $finding) {
                $severity = (string) ($finding['severity'] ?? 'info');
                $cls = match ($severity) {
                    'error' => 'tl_error',
                    'warning' => 'tl_info',
                    'ok' => 'tl_confirm',
                    default => 'tl_info',
                };
                $html .= '<p class="' . $cls . '">' . $e($finding['message'] ?? '') . '</p>';
            }

            $answerFirst = $last['answerFirst'] ?? null;
            if (\is_array($answerFirst)) {
                $html .= '<h4 style="margin-top:10px">Antwort-zuerst-Einstieg: '
                    . '<span class="seo-studio-badge seo-studio-badge--' . $e($answerFirst['color'] ?? 'mid') . '">' . (int) ($answerFirst['score'] ?? 0) . '/100</span></h4>'
                    . '<p>' . $e($answerFirst['reason'] ?? '') . '</p>';

                foreach ((array) ($answerFirst['alternatives'] ?? []) as $alternative) {
                    $html .= '<p class="seo-studio-note">Vorschlag: ' . $e($alternative) . '</p>';
                }
            } elseif (isset($last['answerFirstError'])) {
                $html .= '<p class="tl_info">Antwort-zuerst-Check übersprungen: ' . $e($last['answerFirstError']) . '</p>';
            }
        }

        return $html . '</fieldset></form>';
    }

    private function renderDuplicatesSection(callable $e, mixed $container): string
    {
        /** @var \VTinnovations\SeoStudio\Feature\Audit\DuplicateChecker $checker */
        $checker = $container->get(DuplicateChecker::class);

        $duplicates = $checker->findAll();

        $html = '<fieldset class="tl_tbox block"><legend>Duplikate (Seitentitel / Beschreibung)</legend>';

        if ($duplicates === []) {
            return $html . '<p class="tl_confirm">Keine doppelten Seitentitel oder Beschreibungen gefunden.</p></fieldset>';
        }

        foreach ($duplicates as $duplicate) {
            $label = $duplicate['field'] === 'pageTitle' ? 'Seitentitel' : 'Beschreibung';
            $pageList = implode(', ', array_map(
                static fn (array $p): string => $p['title'] . ' (ID ' . $p['id'] . ')',
                $duplicate['pages'],
            ));
            $html .= '<p class="tl_error">' . $e($label) . ' „' . $e(mb_substr($duplicate['value'], 0, 80)) . '“ auf ' . \count($duplicate['pages']) . ' Seiten: ' . $e($pageList) . '</p>';
        }

        return $html . '</fieldset>';
    }
}
