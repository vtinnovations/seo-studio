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
     * A fully written-out example per task — the thing that turns "insert a
     * list element" into something you can copy, adapt and be done with.
     *
     * Deliberately static: it costs nothing, is instant, and is available even
     * without an API key. For text tailored to a specific page, "Mit KI
     * optimieren" sits directly on the element.
     *
     * Trusted authored HTML — never concatenated with user data.
     */
    private const EXAMPLES = [
        'meta' => '<p><strong>Statt leer oder „Startseite“:</strong></p>'
            . '<p class="seo-studio-ex-label">Seitentitel (50–60 Zeichen)</p>'
            . '<pre>Webdesign für Freelancer — V&amp;T Innovations</pre>'
            . '<p class="seo-studio-ex-label">Beschreibung (120–155 Zeichen)</p>'
            . '<pre>Wir bauen Contao-Websites für Freelancer und kleine Teams. '
            . 'Von der Konzeption bis zur Übergabe — in vier Wochen online.</pre>'
            . '<p class="seo-studio-ex-hint">Regel: Der Titel nennt <em>Leistung + Zielgruppe</em>, die Beschreibung '
            . 'endet mit einem konkreten Nutzen. Beide Felder füllt „Titel &amp; Beschreibungen erzeugen“ auch per KI.</p>',

        'headings' => '<p><strong>Falsch</strong> — zwei H1, und H2 wird übersprungen:</p>'
            . "<pre>H1  Willkommen\nH1  Unsere Leistungen\nH3  Beratung</pre>"
            . '<p><strong>Richtig</strong> — eine H1, lückenlose Ebenen:</p>'
            . "<pre>H1  Webdesign für Freelancer\nH2  Unsere Leistungen\nH3  Beratung\nH3  Umsetzung\nH2  Ablauf und Preise</pre>"
            . '<p class="seo-studio-ex-hint">Die Ebene stellst du am Inhaltselement im Feld neben der Überschrift ein '
            . '(h1–h6). Untergeordnetes bekommt die nächsthöhere Zahl — nie zwei Stufen auf einmal.</p>',

        'structuredFormats' => '<p><strong>Statt Fließtext:</strong> „Wir bieten Beratung, Umsetzung und Wartung an, '
            . 'außerdem kümmern wir uns um Hosting und Schulungen.“</p>'
            . '<p><strong>Als Aufzählung</strong> (Inhaltselement „Aufzählung“):</p>'
            . "<pre>Unsere Leistungen im Überblick\n\n• Beratung — Konzept, Struktur, Zielgruppe\n"
            . "• Umsetzung — Contao-Website, responsiv, barrierefrei\n• Wartung — Updates, Backups, Support\n"
            . "• Hosting — deutsche Server, DSGVO-konform\n• Schulung — damit du selbst pflegen kannst</pre>"
            . '<p><strong>Oder als Tabelle</strong> (Inhaltselement „Tabelle“), wenn du Werte vergleichst:</p>'
            . "<pre>Paket    | Umfang           | Preis\nBasis    | 5 Seiten         | ab 1.900 €\n"
            . 'Business | 15 Seiten + Blog | ab 3.900 €</pre>'
            . '<p class="seo-studio-ex-hint">Warum das zählt: ChatGPT und Google-KI zitieren solche Blöcke fast wörtlich, '
            . 'weil sie sich sauber aus der Seite herausschneiden lassen. Ein Element pro Seite genügt.</p>',

        'faq' => '<p><strong>Drei echte Fragen, wie ein Kunde sie stellt:</strong></p>'
            . "<pre>F: Was kostet eine Contao-Website?\nA: Eine Website mit fünf Seiten kostet ab 1.900 €. "
            . 'Der Preis hängt von Seitenzahl, Funktionen und Designaufwand ab. Ein Festpreis-Angebot bekommst du nach '
            . 'einem 30-minütigen Gespräch.</pre>'
            . "<pre>F: Wie lange dauert die Umsetzung?\nA: Vier bis sechs Wochen ab Auftrag. Die Hälfte der Zeit "
            . 'entfällt auf Inhalte — je früher deine Texte und Bilder da sind, desto schneller geht es live.</pre>'
            . "<pre>F: Kann ich die Seite später selbst pflegen?\nA: Ja. Contao ist dafür gebaut, und zur Übergabe "
            . 'gehört eine einstündige Einweisung. Texte, Bilder und neue Seiten pflegst du danach ohne uns.</pre>'
            . '<p class="seo-studio-ex-hint">Merkmale einer guten FAQ-Antwort: <em>erster Satz beantwortet die Frage '
            . 'vollständig</em>, danach ein bis zwei Sätze Begründung. Keine Rückfragen, kein „das kommt darauf an“ '
            . 'als erstes Wort. Unter „FAQ“ erzeugt die KI Entwürfe aus deinen echten Seiteninhalten.</p>',

        'answerFirst' => '<p><strong>Vorher</strong> — der Leser wartet vier Zeilen auf die Antwort:</p>'
            . '<pre>In der heutigen digitalen Welt ist eine professionelle Onlinepräsenz wichtiger denn je. '
            . 'Viele Unternehmen stehen vor der Herausforderung, sich im Netz zu behaupten. Wir bei V&amp;T '
            . 'begleiten Sie auf diesem Weg. Wir entwickeln Websites für Freelancer.</pre>'
            . '<p><strong>Nachher</strong> — Antwort in Satz 1, Begründung danach:</p>'
            . '<pre>Wir entwickeln Contao-Websites für Freelancer und kleine Teams. In vier bis sechs Wochen '
            . 'steht deine Seite — Konzept, Design und Umsetzung aus einer Hand. Danach pflegst du die Inhalte '
            . 'selbst, ohne uns anrufen zu müssen.</pre>'
            . '<p class="seo-studio-ex-hint">Test: Deck alles ab dem zweiten Satz ab. Steht die Antwort trotzdem da? '
            . 'Dann passt es. Streiche jeden Aufwärmsatz — „In der heutigen Zeit“, „Willkommen“, „Wussten Sie schon“.</p>',

        'schema' => '<p><strong>In Einstellungen → Strukturierte Daten, Feld „Organisation: Name“:</strong></p>'
            . '<pre>V&amp;T Innovations</pre>'
            . '<p class="seo-studio-ex-hint">Der Name, unter dem dich Kunden kennen — mit Rechtsform, wenn du sie '
            . 'sonst auch führst („Muster GmbH“). Ein Feld, einmal ausfüllen, wirkt auf allen Seiten.</p>',
    ];

    /**
     * Maps each GeoScoreCalculator component to a search-visibility layer:
     * SEO (classic findability), GEO (generative search), AEO (answer engines).
     *
     * 'freshness' is deliberately absent: page age is information, not a
     * verdict. Nobody can revisit every page every fortnight, and a finished
     * page does not get worse by resting.
     */
    private const SCORE_BUCKET = [
        'meta' => 'seo',
        'headings' => 'seo',
        'structuredFormats' => 'geo',
        'schema' => 'geo',
        'answerFirst' => 'aeo',
        'faq' => 'aeo',
    ];

    public function generate(): string
    {
        $notice = $this->entitlementNotice();
        if ($notice !== null) {
            return $notice;
        }

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

        $html .= $this->renderRootFilter($e, $rootScope, $rootId, 'seo_dashboard');

        if (!$secrets->has('ai_api_key')) {
            $html .= '<div class="seo-studio-hint seo-studio-hint--warn">'
                . $this->transf('dash.aiMissing', $e($this->moduleUrl('seo_settings')))
                . '</div>';
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
            'layerHints' => ['seo' => [], 'geo' => [], 'aeo' => []],
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

                $scoreRows = $db->fetchAllAssociative(
                    "SELECT s.pageId, s.components, p.title FROM tl_seo_studio_score s
                     JOIN tl_page p ON p.id = s.pageId WHERE $cond",
                    $params,
                    $types);
                [$d['seoPart'], $d['geoPart'], $d['aeoPart']] = $this->scoreBreakdown(array_column($scoreRows, 'components'));

                // One article per page, so a task can link to the exact content
                // element list instead of dumping the user in the article tree.
                $articleByPage = [];
                foreach ($db->fetchAllAssociative(
                    "SELECT pid, MIN(id) AS id FROM tl_article WHERE published = '1' GROUP BY pid") as $row) {
                    $articleByPage[(int) $row['pid']] = (int) $row['id'];
                }

                $d['layerHints'] = $this->layerHints($scoreRows, $articleByPage);
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

                // A check that never ran must not drag its layer down. Counting
                // an unrun answer-first check as 0/20 turned a perfectly fine
                // page into "AEO 33". Scores written before this flag existed
                // are recognised by their note.
                $measured = $c['measured']
                    ?? !str_starts_with((string) ($c['note'] ?? ''), 'KI-Check ');

                if (!$measured) {
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
     * Turns the weakest components into a NAMED TO-DO LIST, not a diagnosis.
     *
     * "2 von 2 Seiten: keine Liste" leaves the reader asking *which* page and
     * *where do I click*. So every entry names one page, states one action in
     * the imperative, shows what it is worth, and links to the exact screen
     * that performs it. Sorted by points, because the top row should always be
     * the one worth doing first.
     *
     * @param list<array<string, mixed>> $rows pageId + title + components JSON
     * @param array<int, int> $articleByPage pageId => first published article id
     * @return array<string, list<array{0: string, 1: string, 2: string, 3: string}>> layer => [text, cta, url, exampleHtml]
     */
    private function layerHints(array $rows, array $articleByPage = []): array
    {
        $tasks = ['seo' => [], 'geo' => [], 'aeo' => []];
        $schemaPages = 0;
        $unmeasured = 0;

        // Several roots happily share the title "Startseite". Two identical
        // task rows would be unusable, so ambiguous titles carry their ID.
        $titleCount = array_count_values(array_map(
            static fn (array $r): string => trim((string) ($r['title'] ?? '')),
            $rows));

        foreach ($rows as $row) {
            $components = json_decode((string) ($row['components'] ?? ''), true);
            if (!\is_array($components)) {
                continue;
            }

            $pageId = (int) ($row['pageId'] ?? 0);
            $title = trim((string) ($row['title'] ?? '')) ?: 'Seite ' . $pageId;
            if (($titleCount[$title] ?? 0) > 1) {
                $title .= ' (ID ' . $pageId . ')';
            }
            $articleId = $articleByPage[$pageId] ?? 0;

            // Where the content of this page is actually edited.
            $contentUrl = $articleId > 0
                ? $this->backendUrl(['do' => 'article', 'table' => 'tl_content', 'id' => $articleId])
                : $this->moduleUrl('article');
            $pageUrl = $this->backendUrl(['do' => 'page', 'act' => 'edit', 'id' => $pageId]);

            foreach ($components as $key => $c) {
                $layer = self::SCORE_BUCKET[$key] ?? null;
                if ($layer === null || !\is_array($c)) {
                    continue;
                }

                $measured = $c['measured'] ?? !str_starts_with((string) ($c['note'] ?? ''), 'KI-Check ');
                if (!$measured) {
                    ++$unmeasured;
                    continue;
                }

                $gain = (int) round((float) ($c['max'] ?? 0) - (float) ($c['points'] ?? 0));
                if ($gain <= 0) {
                    continue;
                }

                // Schema is one site-wide setting, not a per-page job — counted
                // here, emitted once below.
                if ($key === 'schema') {
                    ++$schemaPages;
                    continue;
                }

                $openContent = $this->trans('task.openContent');

                $task = match ($key) {
                    'meta' => [$this->trans('task.meta'), $this->trans('task.openPage'), $pageUrl],
                    'headings' => [$this->trans('task.headings'), $openContent, $contentUrl],
                    'structuredFormats' => [$this->trans('task.structuredFormats'), $openContent, $contentUrl],
                    'faq' => [$this->trans('task.faq'), $this->trans('task.openFaq'), $this->moduleUrl('seo_faq')],
                    'answerFirst' => [$this->trans('task.answerFirst'), $openContent, $contentUrl],
                    default => null,
                };

                if ($task === null) {
                    continue;
                }

                $tasks[$layer][] = [$gain, '„' . $title . '“ — ' . $task[0] . ' (+' . $gain . ')', $task[1], $task[2], $this->example($key)];
            }
        }

        if ($schemaPages > 0) {
            $tasks['geo'][] = [
                3 * $schemaPages,
                $this->transf('task.schema', $schemaPages),
                $this->trans('task.openSettings'),
                $this->moduleUrl('seo_settings'),
                $this->example('schema'),
            ];
        }

        if ($unmeasured > 0) {
            $tasks['aeo'][] = [
                0,
                $this->transf('task.unmeasured', $unmeasured),
                $this->trans('task.computeWithAi'),
                $this->moduleUrl('seo_analyse'),
                '',
            ];
        }

        // Biggest win first, then trim: a list of twenty is as useless as none.
        $hints = [];
        foreach ($tasks as $layer => $list) {
            usort($list, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
            $rest = \count($list) - 4;
            $list = \array_slice($list, 0, 4);

            $hints[$layer] = array_map(static fn (array $t): array => [$t[1], $t[2], $t[3], $t[4] ?? ''], $list);

            if ($rest > 0) {
                $hints[$layer][] = [
                    $this->transf('task.more', $rest),
                    $this->trans('task.checkAll'),
                    $this->moduleUrl('seo_analyse'),
                    '',
                ];
            }
        }

        return $hints;
    }

    /**
     * The worked example for a component, translated when a language file
     * supplies one. German lives in {@see EXAMPLES} as the fallback.
     */
    private function example(string $key): string
    {
        return isset(self::EXAMPLES[$key])
            ? $this->trans('examples.' . $key)
            : '';
    }

    /**
     * @param array<string, int|string> $params
     */
    private function backendUrl(array $params): string
    {
        $router = System::getContainer()->get('router');
        \assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        return $router->generate('contao_backend', $params);
    }

    /**
     * @param array<string, mixed> $d
     */
    private function renderHealth(callable $e, array $d): string
    {
        // Overall health = GEO score average when present, otherwise a simple
        // coverage score so the ring is never empty on a fresh install.
        $score = $d['geoAvg'];
        $label = $this->trans('dash.scoreTitle');
        $sub = $this->transf('dash.scoreSub', $d['geoScored'], $d['totalPages']);

        if ($score === null) {
            $score = $this->coverageScore($d);
            $sub = $this->trans('dash.scoreSubEstimated');
        }

        $color = $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad');

        $card = '<div class="seo-studio-card seo-studio-card--health">'
            . '<h3>' . $e($label) . '</h3>'
            . $this->donut((int) $score, $color)
            . '<p class="seo-studio-card-sub">' . $e($sub) . '</p>';

        if ($d['geoAvg'] !== null && $d['geoScored'] > 0) {
            $card .= '<div class="seo-studio-legend">'
                . '<span><i class="dot dot--good"></i>' . $d['geoGood'] . ' ' . $e($this->trans('dash.good')) . '</span>'
                . '<span><i class="dot dot--mid"></i>' . $d['geoMid'] . ' ' . $e($this->trans('dash.mid')) . '</span>'
                . '<span><i class="dot dot--bad"></i>' . $d['geoBad'] . ' ' . $e($this->trans('dash.bad')) . '</span>'
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
            ['SEO', $d['seoPart'], $this->trans('dash.layerSeo'), 'seo'],
            ['GEO', $d['geoPart'], $this->trans('dash.layerGeo'), 'geo'],
            ['AEO', $d['aeoPart'], $this->trans('dash.layerAeo'), 'aeo'],
        ];

        if ($d['seoPart'] === null && $d['geoPart'] === null && $d['aeoPart'] === null) {
            return '<p class="seo-studio-card-sub">' . $e($this->trans('dash.triScorePending')) . '</p>';
        }

        $hints = \is_array($d['layerHints'] ?? null) ? $d['layerHints'] : [];

        $html = '<div class="seo-studio-triscore">';
        foreach ($layers as [$labelText, $value, $desc, $key]) {
            $v = (int) $value;
            $color = $v >= 80 ? 'good' : ($v >= 50 ? 'mid' : 'bad');
            $html .= '<div class="seo-studio-triscore-row">'
                . '<div class="seo-studio-triscore-head">'
                . '<span><strong>' . $e($labelText) . '</strong> <span class="seo-studio-muted">' . $e($desc) . '</span></span>'
                . '<span>' . ($value === null ? '–' : $v) . '</span></div>'
                . '<div class="seo-studio-bar"><div class="seo-studio-bar-fill seo-studio-bar-fill--' . $color . '" style="width:' . $v . '%"></div></div>';

            // What to actually do about a weak layer — named, counted, linked,
            // and with a written-out example one click away.
            foreach ($hints[$key] ?? [] as $hint) {
                [$text, $cta, $url] = $hint;
                $example = $hint[3] ?? '';

                $html .= '<p class="seo-studio-layerhint">' . $e($text)
                    . ' <a href="' . $e($url) . '">' . $e($cta) . ' →</a></p>';

                if ($example !== '') {
                    $html .= '<details class="seo-studio-example"><summary>' . $e($this->trans('dash.showExample')) . '</summary>'
                        . '<div class="seo-studio-example-body">' . $example . '</div></details>';
                }
            }

            $html .= '</div>';
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
            $bars[] = [$this->trans('dash.barMeta'), $done, $d['totalPages']];
        }
        if ($d['freshEnabled'] && $d['totalPages'] > 0) {
            // Neutral bar: this one informs, it does not judge.
            $bars[] = [$this->trans('dash.barFreshness'), $d['totalPages'] - $d['stale'], $d['totalPages'], null, 'info'];
        }
        if ($d['imagesEnabled']) {
            $totalImg = (int) $d['imagesUnassigned'];
            // We only know the unassigned count cheaply; show it as a simple gauge.
            $bars[] = [
                $this->trans('dash.barImages'),
                $totalImg === 0 ? 1 : 0,
                1,
                $totalImg === 0 ? $this->trans('dash.allAssigned') : $this->transf('dash.nOpen', $totalImg),
            ];
        }
        if ($d['auditEnabled']) {
            $crawlerLabel = $this->trans('dash.barCrawlers');
            if ($d['crawlersChecked']) {
                $bars[] = [
                    $crawlerLabel,
                    $d['crawlersBlocked'] === 0 ? 1 : 0,
                    1,
                    $d['crawlersBlocked'] === 0 ? $this->trans('dash.allAllowed') : $this->transf('dash.nBlocked', $d['crawlersBlocked']),
                ];
            } else {
                $bars[] = [$crawlerLabel, 0, 1, $this->trans('dash.notCheckedYet')];
            }
        }

        $html = '<div class="seo-studio-card seo-studio-card--coverage"><h3>' . $e($this->trans('dash.coverage')) . '</h3>';

        if ($bars === []) {
            return $html . '<p class="seo-studio-card-sub">' . $e($this->trans('dash.noCoverage')) . '</p></div>';
        }

        foreach ($bars as $bar) {
            $labelText = $bar[0];
            $done = (int) $bar[1];
            $total = max(1, (int) $bar[2]);
            $pct = (int) round($done / $total * 100);
            $note = $bar[3] ?? ($done . ' / ' . $total);
            $color = ($bar[4] ?? '') === 'info'
                ? 'info'
                : ($pct >= 80 ? 'good' : ($pct >= 50 ? 'mid' : 'bad'));

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
            $todos[] = ['mid', $this->transf('todo.meta', $d['metaOpen']), $this->trans('todo.metaCta'), $this->moduleUrl('seo_generate')];
        }
        if ($d['imagesEnabled'] && $d['imagesUnassigned'] > 0) {
            $todos[] = ['mid', $this->transf('todo.images', $d['imagesUnassigned']), $this->trans('todo.imagesCta'), $this->moduleUrl('seo_analyse')];
        }
        if ($d['auditEnabled'] && $d['crawlersChecked'] && (int) $d['crawlersBlocked'] > 0) {
            $todos[] = ['bad', $this->transf('todo.crawlersBlocked', $d['crawlersBlocked']), $this->trans('todo.crawlersCta'), $this->moduleUrl('seo_analyse')];
        }
        if ($d['auditEnabled'] && !$d['crawlersChecked']) {
            $todos[] = ['mid', $this->trans('todo.crawlersUnchecked'), $this->trans('todo.crawlersCheckCta'), $this->moduleUrl('seo_analyse')];
        }
        if ($d['freshEnabled'] && $d['stale'] > 0) {
            // Info, not a defect: age is worth knowing for news and prices,
            // but an untouched page is not a broken page.
            $todos[] = ['info', $this->transf('todo.stale', $d['stale']), $this->trans('todo.staleCta'), $this->moduleUrl('seo_analyse')];
        }
        if ($d['geoEnabled'] && $d['geoScored'] === 0) {
            $todos[] = ['mid', $this->trans('todo.noScore'), $this->trans('todo.noScoreCta'), $this->moduleUrl('seo_analyse')];
        }
        if ($d['faqEnabled'] && (int) $d['faqDrafts'] > 0) {
            $todos[] = ['info', $this->transf('todo.faqDrafts', $d['faqDrafts']), $this->trans('todo.faqCta'), $this->moduleUrl('seo_faq')];
        }
        if ($d['glossaryEnabled'] && (int) $d['glossaryDrafts'] > 0) {
            $todos[] = ['info', $this->transf('todo.glossaryDrafts', $d['glossaryDrafts']), $this->trans('todo.glossaryCta'), $this->moduleUrl('seo_glossary')];
        }

        $html = '<div class="seo-studio-card seo-studio-card--todos"><h3>' . $e($this->trans('todo.heading')) . '</h3>';

        if ($todos === []) {
            return $html . '<p class="tl_confirm" style="margin:0">' . $e($this->trans('todo.allClear')) . '</p></div>';
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
        $body = '<p>Die Suite unterstützt dich beim SEO/GEO/AEO deiner Website — direkt im Backend. Drei Bereiche:</p>'
            . '<ul>'
            . '<li><strong>Inhalte &amp; Meta</strong> — Titel/Beschreibungen und Glossar-Definitionen per KI erzeugen (nur leere Felder).</li>'
            . '<li><strong>Analyse</strong> — Crawler-Zugang, Struktur, Duplikate, GEO-Score, Aktualität und Bilder prüfen.</li>'
            . '<li><strong>FAQ</strong> und <strong>Glossar</strong> — generierte Inhalte kuratieren und veröffentlichen.</li>'
            . '</ul>'
            . '<p>Einzelne Text- und Überschriften-Blöcke optimierst du direkt am Inhaltselement über „Mit KI optimieren“.</p>'
            . '<p><strong>SEO Studio erzeugt nichts von allein.</strong> Jede KI-Aktion läuft nur auf deinen Klick (oder den optionalen, '
            . 'standardmäßig ausgeschalteten Cron). Ein Frontend-Titel wie <code>„Seite - Websitename“</code> kommt von Contao selbst — '
            . 'es hängt den Startpunkt-Namen an und nutzt bei leerem Seitentitel den Navigationsnamen.</p>';

        return '<details class="seo-studio-explainer"><summary>'
            . $e($this->trans('explain.summary'))
            . '</summary>' . $this->trans('explain.body') . '</details>';
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

}
