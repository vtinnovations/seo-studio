<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Controller;

use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Shared tab-container markup for the SEO Studio backend modules. Pairs with
 * Resources/public/tabs.js (JS switching + per-group localStorage memory) and
 * the .seo-studio-tab* CSS in backend.css.
 */
trait BackendTabsTrait
{
    /**
     * Translation lookup for the hand-built backend screens.
     *
     * Dotted keys resolve inside $GLOBALS['TL_LANG']['SEO_STUDIO'], i.e. in
     * contao/languages/<lang>/default.php. There is no inline fallback on
     * purpose — see Translations.
     */
    protected function trans(string $key): string
    {
        return Translations::text($key);
    }

    /**
     * Same lookup with sprintf arguments, for counted strings such as
     * "%d of %d pages".
     */
    protected function transf(string $key, mixed ...$args): string
    {
        return Translations::text($key, ...$args);
    }

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
            . '<div class="seo-studio-shell">' . $hero . $body . '</div>';
    }

    /**
     * Module-entry gate for every SEO Studio backend module.
     *
     * Two jobs, in this order:
     *   1. entering a protected module is the trigger for the once-per-backend-
     *      session usage signal, so the claim is made here (delivery happens
     *      after the response and can never delay rendering);
     *   2. without a valid licence the module renders nothing but a pointer to
     *      Contao → Settings.
     *
     * The menu group is not even registered while unlicensed; this is the second,
     * independent gate for the case where that registration is bypassed.
     *
     * Returns null when the module may render normally.
     */
    protected function entitlementNotice(): ?string
    {
        $container = \Contao\System::getContainer();

        /** @var \VTinnovations\SeoStudio\Exchange\EntryClaim $entryClaim */
        $entryClaim = $container->get(\VTinnovations\SeoStudio\Exchange\EntryClaim::class);
        $entryClaim->claim();

        /** @var \VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator $entitlement */
        $entitlement = $container->get(\VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator::class);

        if ($entitlement->isLicensed()) {
            return null;
        }

        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div id="tl_buttons"></div>' . \Contao\Message::generate()
            . '<div class="tl_gerror">'
            . $e($this->trans('licence.moduleBlocked'))
            . ' <a href="' . $e($this->seoStudioModuleUrl('settings')) . '">'
            . $e($this->trans('licence.openSettings'))
            . '</a></div>';
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

        $options = '<option value="0">' . $e($this->trans('dash.allRoots')) . '</option>';
        foreach ($roots as $root) {
            $sel = $root['id'] === $rootId ? ' selected' : '';
            $options .= '<option value="' . $root['id'] . '"' . $sel . '>' . $e($root['title']) . '</option>';
        }

        return '<form method="get" class="seo-studio-rootfilter">'
            . '<input type="hidden" name="do" value="' . $e($module) . '">'
            . '<label>' . $e($this->trans('dash.root')) . ': '
            . '<select name="root" class="tl_select" onchange="this.form.submit()">' . $options . '</select></label>'
            . '</form>';
    }
}
