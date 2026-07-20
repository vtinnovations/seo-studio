<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller;

use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Content\RootScope;
use VTinnovations\SeoStudio\Core\Security\SecretStore;
use VTinnovations\SeoStudio\Feature\Freshness\StalePageFinder;
use VTinnovations\SeoStudio\Feature\Images\ImageAuditor;
use VTinnovations\SeoStudio\Feature\Meta\MetaGenerator;

/**
 * BE_MOD "SEO Studio → Übersicht": a visual, at-a-glance dashboard — overall
 * health donut, coverage bars, and a prioritised to-do list with deep links —
 * plus the "what does this do / it doesn't run on its own" explainers.
 * Read-only; every number links to where you fix it.
 */
final class DashboardModule
{
    use BackendTabsTrait;

    /**
     * Maps each GeoScoreCalculator component to a search-visibility layer:
     * SEO (classic findability), GEO (generative search), AEO (answer engines).
     */
    private const SCORE_BUCKET = [
        'meta' => 'seo',
        'headings' => 'seo',
        'structuredFormats' => 'geo',
        'freshness' => 'geo',
        'schema' => 'geo',
        'answerFirst' => 'aeo',
        'faq' => 'aeo',
    ];

    public function generate(): string
    {
        $container = System::getContainer();

        /** @var FeatureState $featureState */
        $featureState = $container->get(FeatureState::class);
        /** @var SecretStore $secrets */
        $secrets = $container->get(SecretStore::class);
        /** @var Connection $db */
        $db = $container->get('database_connection');
        /** @var ConfigProvider $config */
        $config = $container->get(ConfigProvider::class);
        /** @var RootScope $rootScope */
        $rootScope = $container->get(RootScope::class);

        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $rootId = $rootScope->sanitize($this->currentRootId($container));
        $data = $this->collect($featureState, $db, $config, $container, $rootScope, $rootId);

        $html = '<div id="tl_buttons"></div>' . Message::generate();

        $html .= $this->renderLicenseBanner($e, $container);
        $html .= $this->renderRootFilter($e, $rootScope, $rootId, 'seo_dashboard');

        if (!$secrets->has('ai_api_key')) {
            $html .= '<div class="seo-studio-hint seo-studio-hint--warn">'
                . '<strong>KI noch nicht verbunden.</strong> Die Prüfungen (Crawler, Struktur, Bilder) laufen bereits; '
                . 'für Text- und Meta-Generierung unter <a href="' . $e($this->moduleUrl('seo_settings')) . '">Einstellungen</a> '
                . 'einen KI-Anbieter + Schlüssel eintragen.</div>';
        }

        $html .= '<div class="seo-studio-dash">';
        $html .= $this->renderHealth($e, $data);
        $html .= $this->renderCoverage($e, $data);
        $html .= '</div>';

        $html .= $this->renderTodos($e, $data);
        $html .= $this->renderExplainers($e);

        return $html;
    }

    /**
     * Gathers every dashboard number in one place (all cheap DB reads).
     *
     * @return array<string, mixed>
     */
    private function collect(FeatureState $featureState, Connection $db, ConfigProvider $config, mixed $container, RootScope $rootScope, int $rootId): array
    {
        $scopeIds = $rootId > 0 ? $rootScope->pageIds($rootId) : null;
        $d = [
            'totalPages' => 0,
            'metaEnabled' => $featureState->isEnabled('meta'),
            'metaOpen' => 0,
            'geoEnabled' => $featureState->isEnabled('geoScore'),
            'geoAvg' => null,
            'geoGood' => 0,
            'geoMid' => 0,
            'geoBad' => 0,
            'geoScored' => 0,
            'seoPart' => null,
            'geoPart' => null,
            'aeoPart' => null,
            'freshEnabled' => $featureState->isEnabled('freshness'),
            'stale' => 0,
            'imagesEnabled' => $featureState->isEnabled('images'),
            'imagesUnassigned' => 0,
            'auditEnabled' => $featureState->isEnabled('audit'),
            'crawlersBlocked' => null,
            'crawlersChecked' => false,
            'faqEnabled' => $featureState->isEnabled('faq'),
            'faqPublished' => 0,
            'faqDrafts' => 0,
            'glossaryEnabled' => $featureState->isEnabled('glossary'),
            'glossaryPublished' => 0,
            'glossaryDrafts' => 0,
        ];

        $d['totalPages'] = $scopeIds !== null
            ? \count($scopeIds)
            : (int) $db->fetchOne("SELECT COUNT(*) FROM tl_page WHERE type = 'regular' AND published = '1'");

        if ($d['metaEnabled']) {
            /** @var MetaGenerator $meta */
            $meta = $container->get(MetaGenerator::class);
            $d['metaOpen'] = \count($meta->findPagesWithEmptyMeta($rootId > 0 ? $rootId : null));
        }

        // Scope helper for tl_seo_studio_score.pageId: a real IN clause, or 1=1.
        $cond = $scopeIds !== null ? 'pageId IN (:ids)' : '1=1';
        $params = $scopeIds !== null ? ['ids' => $scopeIds] : [];
        $types = $scopeIds !== null ? ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER] : [];

        if ($d['geoEnabled'] && $scopeIds !== []) {
            $d['geoScored'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_score WHERE $cond", $params, $types);
            if ($d['geoScored'] > 0) {
                $d['geoAvg'] = (int) round((float) $db->fetchOne("SELECT AVG(score) FROM tl_seo_studio_score WHERE $cond", $params, $types));
                $d['geoGood'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_score WHERE $cond AND score >= 80", $params, $types);
                $d['geoMid'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_score WHERE $cond AND score >= 50 AND score < 80", $params, $types);
                $d['geoBad'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_score WHERE $cond AND score < 50", $params, $types);

                [$d['seoPart'], $d['geoPart'], $d['aeoPart']] = $this->scoreBreakdown(
                    $db->fetchFirstColumn("SELECT components FROM tl_seo_studio_score WHERE $cond", $params, $types),
                );
            }
        }

        if ($d['freshEnabled']) {
            /** @var StalePageFinder $finder */
            $finder = $container->get(StalePageFinder::class);
            $stale = $finder->find(14, 200);
            if ($scopeIds !== null) {
                $set = array_flip($scopeIds);
                $stale = array_filter($stale, static fn (array $r): bool => isset($set[$r['id']]));
            }
            $d['stale'] = \count($stale);
        }

        if ($d['imagesEnabled']) {
            /** @var ImageAuditor $auditor */
            $auditor = $container->get(ImageAuditor::class);
            $d['imagesUnassigned'] = \count($auditor->findUnassignedElements());
        }

        if ($d['auditEnabled']) {
            $cached = $config->get('robotsAuditResult', []);
            if (\is_array($cached) && $cached !== []) {
                $d['crawlersChecked'] = true;
                $blocked = 0;
                foreach ($cached as $domain) {
                    foreach ((array) ($domain['crawlers'] ?? []) as $verdict) {
                        if (!(bool) ($verdict['allowed'] ?? true)) {
                            ++$blocked;
                        }
                    }
                }
                $d['crawlersBlocked'] = $blocked;
            }
        }

        if ($d['faqEnabled']) {
            try {
                $d['faqPublished'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_faq WHERE published = '1'");
                $d['faqDrafts'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_faq WHERE published != '1'");
            } catch (\Throwable) {
            }
        }

        if ($d['glossaryEnabled']) {
            try {
                $d['glossaryPublished'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_glossary WHERE published = '1'");
                $d['glossaryDrafts'] = (int) $db->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_glossary WHERE published != '1'");
            } catch (\Throwable) {
            }
        }

        return $d;
    }

    /**
     * Aggregates per-page component scores into SEO / GEO / AEO layer scores.
     *
     * @param list<mixed> $rows JSON component blobs from tl_seo_studio_score
     * @return array{0: int|null, 1: int|null, 2: int|null} seo, geo, aeo (0–100 or null)
     */
    private function scoreBreakdown(array $rows): array
    {
        $bucket = ['seo' => [0.0, 0.0], 'geo' => [0.0, 0.0], 'aeo' => [0.0, 0.0]];

        foreach ($rows as $json) {
            $components = json_decode((string) $json, true);
            if (!\is_array($components)) {
                continue;
            }

            foreach ($components as $key => $c) {
                $layer = self::SCORE_BUCKET[$key] ?? null;
                if ($layer === null || !\is_array($c)) {
                    continue;
                }
                $bucket[$layer][0] += (float) ($c['points'] ?? 0);
                $bucket[$layer][1] += (float) ($c['max'] ?? 0);
            }
        }

        $pct = static fn (array $x): ?int => $x[1] > 0.0 ? (int) round($x[0] / $x[1] * 100) : null;

        return [$pct($bucket['seo']), $pct($bucket['geo']), $pct($bucket['aeo'])];
    }

    /**
     * @param array<string, mixed> $d
     */
    private function renderHealth(callable $e, array $d): string
    {
        // Overall health = GEO score average when present, otherwise a simple
        // coverage score so the ring is never empty on a fresh install.
        $score = $d['geoAvg'];
        $label = 'SEO · GEO · AEO Score';
        $sub = $d['geoScored'] . ' von ' . $d['totalPages'] . ' Seiten bewertet — kombiniert klassisches SEO, GEO (generative Suche) und AEO (Antwort-Engines)';

        if ($score === null) {
            $score = $this->coverageScore($d);
            $label = 'SEO · GEO · AEO Score';
            $sub = 'geschätzt aus Abdeckung — für den vollen SEO/GEO/AEO-Score einmal „SEO·GEO·AEO-Score berechnen“ (Analyse) laufen lassen';
        }

        $color = $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad');

        $card = '<div class="seo-studio-card seo-studio-card--health">'
            . '<h3>' . $e($label) . '</h3>'
            . $this->donut((int) $score, $color)
            . '<p class="seo-studio-card-sub">' . $e($sub) . '</p>';

        if ($d['geoAvg'] !== null && $d['geoScored'] > 0) {
            $card .= '<div class="seo-studio-legend">'
                . '<span><i class="dot dot--good"></i>' . $d['geoGood'] . ' gut</span>'
                . '<span><i class="dot dot--mid"></i>' . $d['geoMid'] . ' mittel</span>'
                . '<span><i class="dot dot--bad"></i>' . $d['geoBad'] . ' schwach</span>'
                . '</div>';
        }

        $card .= $this->renderTriScore($e, $d);

        return $card . '</div>';
    }

    /**
     * The SEO / GEO / AEO layer breakdown under the overall ring.
     *
     * @param callable(mixed):string $e
     * @param array<string, mixed> $d
     */
    private function renderTriScore(callable $e, array $d): string
    {
        $layers = [
            ['SEO', $d['seoPart'], 'Klassische Suche'],
            ['GEO', $d['geoPart'], 'Generative Suche'],
            ['AEO', $d['aeoPart'], 'Antwort-Engines'],
        ];

        if ($d['seoPart'] === null && $d['geoPart'] === null && $d['aeoPart'] === null) {
            return '<p class="seo-studio-card-sub">SEO-/GEO-/AEO-Aufteilung erscheint, sobald der Score berechnet ist (Analyse → „Scores berechnen“).</p>';
        }

        $html = '<div class="seo-studio-triscore">';
        foreach ($layers as [$labelText, $value, $desc]) {
            $v = (int) $value;
            $color = $v >= 80 ? 'good' : ($v >= 50 ? 'mid' : 'bad');
            $html .= '<div class="seo-studio-triscore-row">'
                . '<div class="seo-studio-triscore-head">'
                . '<span><strong>' . $e($labelText) . '</strong> <span class="seo-studio-muted">' . $e($desc) . '</span></span>'
                . '<span>' . ($value === null ? '–' : $v) . '</span></div>'
                . '<div class="seo-studio-bar"><div class="seo-studio-bar-fill seo-studio-bar-fill--' . $color . '" style="width:' . $v . '%"></div></div>'
                . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $d
     */
    private function renderCoverage(callable $e, array $d): string
    {
        $bars = [];

        if ($d['metaEnabled'] && $d['totalPages'] > 0) {
            $done = $d['totalPages'] - $d['metaOpen'];
            $bars[] = ['Titel & Beschreibungen', $done, $d['totalPages']];
        }
        if ($d['freshEnabled'] && $d['totalPages'] > 0) {
            $bars[] = ['Aktualität (≤ 14 Tage)', $d['totalPages'] - $d['stale'], $d['totalPages']];
        }
        if ($d['imagesEnabled']) {
            $totalImg = (int) $d['imagesUnassigned'];
            // We only know the unassigned count cheaply; show it as a simple gauge.
            $bars[] = ['Bilder mit Bildgröße', $totalImg === 0 ? 1 : 0, 1, $totalImg === 0 ? 'alle zugewiesen' : $totalImg . ' offen'];
        }
        if ($d['auditEnabled']) {
            if ($d['crawlersChecked']) {
                $bars[] = ['KI-Crawler erlaubt', $d['crawlersBlocked'] === 0 ? 1 : 0, 1, $d['crawlersBlocked'] === 0 ? 'alle erlaubt' : $d['crawlersBlocked'] . ' blockiert'];
            } else {
                $bars[] = ['KI-Crawler erlaubt', 0, 1, 'noch nicht geprüft'];
            }
        }

        $html = '<div class="seo-studio-card seo-studio-card--coverage"><h3>Abdeckung</h3>';

        if ($bars === []) {
            return $html . '<p class="seo-studio-card-sub">Keine Abdeckungs-Kennzahlen (Funktionen deaktiviert).</p></div>';
        }

        foreach ($bars as $bar) {
            $labelText = $bar[0];
            $done = (int) $bar[1];
            $total = max(1, (int) $bar[2]);
            $pct = (int) round($done / $total * 100);
            $note = $bar[3] ?? ($done . ' / ' . $total);
            $color = $pct >= 80 ? 'good' : ($pct >= 50 ? 'mid' : 'bad');

            $html .= '<div class="seo-studio-bar-row">'
                . '<div class="seo-studio-bar-head"><span>' . $e($labelText) . '</span><span class="seo-studio-muted">' . $e((string) $note) . '</span></div>'
                . '<div class="seo-studio-bar"><div class="seo-studio-bar-fill seo-studio-bar-fill--' . $color . '" style="width:' . $pct . '%"></div></div>'
                . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $d
     */
    private function renderTodos(callable $e, array $d): string
    {
        $todos = [];

        if ($d['metaEnabled'] && $d['metaOpen'] > 0) {
            $todos[] = ['mid', $d['metaOpen'] . ' Seite(n) ohne Titel/Beschreibung', 'Inhalte & Meta öffnen', $this->moduleUrl('seo_generate')];
        }
        if ($d['imagesEnabled'] && $d['imagesUnassigned'] > 0) {
            $todos[] = ['mid', $d['imagesUnassigned'] . ' Bild(er) ohne Bildgröße', 'Bild-Assistent (Analyse)', $this->moduleUrl('seo_analyse')];
        }
        if ($d['auditEnabled'] && $d['crawlersChecked'] && (int) $d['crawlersBlocked'] > 0) {
            $todos[] = ['bad', $d['crawlersBlocked'] . ' KI-Crawler blockiert', 'Crawler-Audit (Analyse)', $this->moduleUrl('seo_analyse')];
        }
        if ($d['auditEnabled'] && !$d['crawlersChecked']) {
            $todos[] = ['mid', 'Crawler-Zugang noch nicht geprüft', 'Jetzt prüfen (Analyse)', $this->moduleUrl('seo_analyse')];
        }
        if ($d['freshEnabled'] && $d['stale'] > 0) {
            $todos[] = ['mid', $d['stale'] . ' Seite(n) seit über 14 Tagen unverändert', 'Aktualität (Analyse)', $this->moduleUrl('seo_analyse')];
        }
        if ($d['geoEnabled'] && $d['geoScored'] === 0) {
            $todos[] = ['mid', 'GEO-Score noch nie berechnet', 'GEO-Score (Analyse)', $this->moduleUrl('seo_analyse')];
        }
        if ($d['faqEnabled'] && (int) $d['faqDrafts'] > 0) {
            $todos[] = ['info', $d['faqDrafts'] . ' FAQ-Entwurf/-Entwürfe warten auf Freigabe', 'FAQ kuratieren', $this->moduleUrl('seo_faq')];
        }
        if ($d['glossaryEnabled'] && (int) $d['glossaryDrafts'] > 0) {
            $todos[] = ['info', $d['glossaryDrafts'] . ' Glossar-Entwurf/-Entwürfe warten auf Freigabe', 'Glossar kuratieren', $this->moduleUrl('seo_glossary')];
        }

        $html = '<div class="seo-studio-card seo-studio-card--todos"><h3>Zu erledigen</h3>';

        if ($todos === []) {
            return $html . '<p class="tl_confirm" style="margin:0">Alles im grünen Bereich — keine offenen Punkte.</p></div>';
        }

        $html .= '<ul class="seo-studio-todos">';
        foreach ($todos as [$sev, $text, $cta, $url]) {
            $html .= '<li class="seo-studio-todo seo-studio-todo--' . $sev . '">'
                . '<span class="seo-studio-todo-text">' . $e($text) . '</span>'
                . '<a class="seo-studio-todo-cta" href="' . $e($url) . '">' . $e($cta) . ' →</a>'
                . '</li>';
        }

        return $html . '</ul></div>';
    }

    private function renderExplainers(callable $e): string
    {
        return '<details class="seo-studio-explainer"><summary>Was macht AI SEO Studio? &amp; warum erscheinen Titel „von allein“?</summary>'
            . '<p>Die Suite unterstützt dich beim SEO/GEO/AEO deiner Website — direkt im Backend. Drei Bereiche:</p>'
            . '<ul>'
            . '<li><strong>Inhalte &amp; Meta</strong> — Titel/Beschreibungen und Glossar-Definitionen per KI erzeugen (nur leere Felder).</li>'
            . '<li><strong>Analyse</strong> — Crawler-Zugang, Struktur, Duplikate, GEO-Score, Aktualität und Bilder prüfen.</li>'
            . '<li><strong>FAQ</strong> und <strong>Glossar</strong> — generierte Inhalte kuratieren und veröffentlichen.</li>'
            . '</ul>'
            . '<p>Einzelne Text- und Überschriften-Blöcke optimierst du direkt am Inhaltselement über „Mit KI optimieren“.</p>'
            . '<p><strong>SEO Studio erzeugt nichts von allein.</strong> Jede KI-Aktion läuft nur auf deinen Klick (oder den optionalen, '
            . 'standardmäßig ausgeschalteten Cron). Ein Frontend-Titel wie <code>„Seite - Websitename“</code> kommt von Contao selbst — '
            . 'es hängt den Startpunkt-Namen an und nutzt bei leerem Seitentitel den Navigationsnamen.</p>'
            . '</details>';
    }

    /**
     * A 0–100 fallback score from coverage signals (before any GEO score exists).
     *
     * @param array<string, mixed> $d
     */
    private function coverageScore(array $d): int
    {
        $parts = [];
        if ($d['metaEnabled'] && $d['totalPages'] > 0) {
            $parts[] = ($d['totalPages'] - $d['metaOpen']) / $d['totalPages'];
        }
        if ($d['freshEnabled'] && $d['totalPages'] > 0) {
            $parts[] = ($d['totalPages'] - $d['stale']) / $d['totalPages'];
        }
        if ($d['imagesEnabled']) {
            $parts[] = $d['imagesUnassigned'] === 0 ? 1.0 : 0.5;
        }
        if ($d['auditEnabled'] && $d['crawlersChecked']) {
            $parts[] = (int) $d['crawlersBlocked'] === 0 ? 1.0 : 0.4;
        }

        if ($parts === []) {
            return 0;
        }

        return (int) round(array_sum($parts) / \count($parts) * 100);
    }

    /**
     * Inline SVG donut (no external chart lib — CSP-safe).
     */
    private function donut(int $value, string $color): string
    {
        $value = max(0, min(100, $value));
        $r = 52;
        $circ = 2 * M_PI * $r;
        $dash = $circ * $value / 100;
        $stroke = match ($color) {
            'good' => '#4caf50',
            'mid' => '#ff9800',
            default => '#f44336',
        };

        return '<svg class="seo-studio-donut" viewBox="0 0 128 128" width="128" height="128" role="img" aria-label="' . $value . ' von 100">'
            . '<circle cx="64" cy="64" r="' . $r . '" fill="none" stroke="var(--seo-studio-border)" stroke-width="12"/>'
            . '<circle cx="64" cy="64" r="' . $r . '" fill="none" stroke="' . $stroke . '" stroke-width="12" stroke-linecap="round"'
            . ' stroke-dasharray="' . round($dash, 1) . ' ' . round($circ - $dash, 1) . '" transform="rotate(-90 64 64)"/>'
            . '<text x="64" y="60" text-anchor="middle" class="seo-studio-donut-value">' . $value . '</text>'
            . '<text x="64" y="80" text-anchor="middle" class="seo-studio-donut-unit">/ 100</text>'
            . '</svg>';
    }

    private function moduleUrl(string $module): string
    {
        $router = System::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        return $router->generate('contao_backend', ['do' => $module]);
    }

    private function currentRootId(mixed $container): int
    {
        $stack = $container->get('request_stack');
        $request = $stack instanceof \Symfony\Component\HttpFoundation\RequestStack ? $stack->getCurrentRequest() : null;

        return $request !== null ? (int) $request->query->get('root', 0) : 0;
    }

    /**
     * @param callable(mixed):string $e
     */
    private function renderLicenseBanner(callable $e, mixed $container): string
    {
        $guard = $container->get(\VTinnovations\SeoStudio\Core\Security\LicenseGuard::class);
        if (!$guard instanceof \VTinnovations\SeoStudio\Core\Security\LicenseGuard) {
            return '';
        }

        return match ($guard->state()) {
            \VTinnovations\SeoStudio\Core\Security\LicenseGuard::STATE_DEMO => '<div class="seo-studio-license seo-studio-license--demo">'
                . '<strong>Demo-Lizenz</strong> — noch ' . $guard->demoDaysLeft() . ' Tag(e). '
                . 'Einige Funktionen sind gesperrt. Vollversion schaltet alles frei: '
                . '<a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a>.</div>',
            \VTinnovations\SeoStudio\Core\Security\LicenseGuard::STATE_UNLICENSED => '<div class="seo-studio-license seo-studio-license--expired">'
                . '<strong>Keine gültige Lizenz.</strong> Trage deinen Lizenzschlüssel ein '
                . '(<a href="' . $e($this->moduleUrl('seo_settings')) . '">Einstellungen → Lizenz</a>) — '
                . 'auch die Demo benötigt einen Schlüssel. Kostenlose Demo &amp; Vollversion auf '
                . '<a href="https://v-t.one" target="_blank" rel="noreferrer">v-t.one</a>.</div>',
            default => '',
        };
    }
}
