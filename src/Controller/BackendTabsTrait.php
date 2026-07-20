<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller;

/**
 * Shared tab-container markup for the SEO Studio backend modules. Pairs with
 * Resources/public/tabs.js (JS switching + per-group localStorage memory) and
 * the .seo-studio-tab* CSS in backend.css.
 */
trait BackendTabsTrait
{
    /**
     * Wraps the given tabs in a tab container. Each tab is [id, label, body];
     * empty bodies are skipped. Returns the body verbatim when only one tab
     * remains (no point in a single tab).
     *
     * @param string $key stable id → own localStorage slot for the active tab
     * @param list<array{0: string, 1: string, 2: string}> $tabs
     */
    protected function renderTabs(string $key, array $tabs): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tabs = array_values(array_filter($tabs, static fn (array $t): bool => trim($t[2]) !== ''));

        if ($tabs === []) {
            return '';
        }

        if (\count($tabs) === 1) {
            return $tabs[0][2];
        }

        $nav = '<div class="seo-studio-tabnav" role="tablist">';
        $panels = '';
        foreach ($tabs as $i => [$id, $label, $body]) {
            $nav .= '<button type="button" class="seo-studio-tab' . ($i === 0 ? ' is-active' : '') . '" data-seo-tab="' . $e($id) . '" role="tab">' . $e($label) . '</button>';
            $panels .= '<div class="seo-studio-tabpanel" data-seo-panel="' . $e($id) . '"' . ($i === 0 ? '' : ' hidden') . '>' . $body . '</div>';
        }
        $nav .= '</div>';

        return '<div class="seo-studio-tabs" data-tabs-key="' . $e($key) . '">' . $nav . $panels . '</div>';
    }

    /**
     * Registers the tab assets. Call from render() before emitting tabs.
     */
    protected function registerTabAssets(): void
    {
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_tabs'] = 'bundles/vtinnovationsseostudio/tabs.js';
    }

    /**
     * Wraps a module in the shared design shell: the Contao button bar +
     * flash messages, then a padded, max-width column holding an optional
     * intro "hero" card and the module body (tabs / fieldsets / forms).
     *
     * Gives every SEO Studio module the same spacing and card look as the
     * dashboard without each module re-implementing the chrome.
     */
    protected function renderShell(string $intro, string $body): string
    {
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $hero = trim($intro) !== ''
            ? '<div class="seo-studio-hero">' . $intro . '</div>'
            : '';

        return '<div id="tl_buttons"></div>'
            . \Contao\Message::generate()
            . '<div class="seo-studio-shell">' . $this->licenseBanner() . $hero . $body . '</div>';
    }

    /**
     * A licence-state strip shown above every module: demo countdown or an
     * expiry lock-out prompt. Nothing when fully licensed.
     */
    protected function licenseBanner(): string
    {
        $guard = \Contao\System::getContainer()->get(\VTinnovations\SeoStudio\Core\Security\LicenseGuard::class);
        if (!$guard instanceof \VTinnovations\SeoStudio\Core\Security\LicenseGuard) {
            return '';
        }

        $settingsUrl = '<a href="' . \htmlspecialchars($this->seoStudioModuleUrl('seo_settings'), ENT_QUOTES) . '">Einstellungen → Lizenz</a>';

        return match ($guard->state()) {
            \VTinnovations\SeoStudio\Core\Security\LicenseGuard::STATE_DEMO => '<div class="seo-studio-license seo-studio-license--demo">'
                . '<strong>Demo-Lizenz</strong> — noch ' . $guard->demoDaysLeft() . ' Tag(e). '
                . 'Einige Funktionen sind gesperrt. Vollversion schaltet alles frei: '
                . '<a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a>.</div>',
            \VTinnovations\SeoStudio\Core\Security\LicenseGuard::STATE_UNLICENSED => '<div class="seo-studio-license seo-studio-license--expired">'
                . '<strong>Keine gültige Lizenz.</strong> Trage deinen Lizenzschlüssel ein (' . $settingsUrl . ') — '
                . 'auch die Demo benötigt einen Schlüssel. Kostenlose Demo &amp; Vollversion auf '
                . '<a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a>.</div>',
            default => '',
        };
    }

    private function seoStudioModuleUrl(string $module): string
    {
        $router = \Contao\System::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        return $router->generate('contao_backend', ['do' => $module]);
    }

    /**
     * A "start point" (site root) filter dropdown. Shown only when the install
     * has more than one root — a single-site install needs no chooser.
     *
     * @param callable(mixed):string $e
     */
    protected function renderRootFilter(callable $e, \VTinnovations\SeoStudio\Core\Content\RootScope $scope, int $rootId, string $module): string
    {
        $roots = $scope->roots();
        if (\count($roots) < 2) {
            return '';
        }

        $options = '<option value="0">' . $e('Alle Startpunkte') . '</option>';
        foreach ($roots as $root) {
            $sel = $root['id'] === $rootId ? ' selected' : '';
            $options .= '<option value="' . $root['id'] . '"' . $sel . '>' . $e($root['title']) . '</option>';
        }

        return '<form method="get" class="seo-studio-rootfilter">'
            . '<input type="hidden" name="do" value="' . $e($module) . '">'
            . '<label>' . $e('Startpunkt') . ': '
            . '<select name="root" class="tl_select" onchange="this.form.submit()">' . $options . '</select></label>'
            . '</form>';
    }
}
