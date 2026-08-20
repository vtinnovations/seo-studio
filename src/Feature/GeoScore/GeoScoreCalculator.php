<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\GeoScore;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\Translations;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Feature\Audit\AnswerFirstChecker;
use VTinnovations\SeoStudio\Feature\Audit\HeadingAuditor;

/**
 * GEO readiness score (0-100) per page. Mostly deterministic; the only LLM
 * component is the answer-first check. Skipped without key/budget — it then
 * leaves the score entirely instead of counting as zero.
 *
 * Components (weights):
 *   meta 25 · answerFirst 20 · headings 15 · structuredFormats 15 · faq 15 ·
 *   schema 10 = 100. freshness is INFORMATION ONLY (max 0, never scored).
 */
final class GeoScoreCalculator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly HeadingAuditor $headingAuditor,
        private readonly AnswerFirstChecker $answerFirstChecker,
    ) {
    }

    /**
     * Computes + persists the score. Returns the components for display.
     *
     * @return array{score: int, components: array<string, array{points: float, max: int, note: string, measured?: bool}>}
     */
    public function compute(int $pageId, bool $withLlm = true): array
    {
        $page = $this->connection->fetchAssociative(
            'SELECT id, pageTitle, description, tstamp FROM tl_page WHERE id = ?',
            [$pageId],
        );

        if ($page === false) {
            throw new \RuntimeException(Translations::text('error.pageNotFound'));
        }

        $components = [];

        // meta (25)
        $metaPoints = (trim((string) $page['pageTitle']) !== '' ? 12.5 : 0)
            + (trim((string) $page['description']) !== '' ? 12.5 : 0);
        $components['meta'] = [
            'points' => (float) $metaPoints,
            'max' => 25,
            'note' => $metaPoints === 25.0 ? Translations::text('geo.metaComplete') : Translations::text('geo.metaIncomplete'),
        ];

        // headings (15)
        $findings = $this->headingAuditor->audit($pageId);
        $hasError = array_filter($findings, static fn (array $f): bool => $f['severity'] === 'error') !== [];
        $hasWarning = array_filter($findings, static fn (array $f): bool => $f['severity'] === 'warning') !== [];
        $headingPoints = $hasError ? 0 : ($hasWarning ? 8 : 15);
        $components['headings'] = [
            'points' => (float) $headingPoints,
            'max' => 15,
            'note' => $hasError ? Translations::text('geo.structureError') : ($hasWarning ? Translations::text('geo.structureSkips') : Translations::text('geo.structureClean')),
        ];

        // structured formats (15): lists, tables, accordions on the page
        $structured = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_content c
             JOIN tl_article a ON a.id = c.pid AND c.ptable IN ('', 'tl_article')
             WHERE a.pid = ? AND a.published = '1' AND c.invisible = ''
               AND c.type IN ('list', 'table', 'accordionSingle', 'accordionStart', 'accordion')",
            [$pageId],
        );
        $components['structuredFormats'] = [
            'points' => $structured > 0 ? 15.0 : 0.0,
            'max' => 15,
            'note' => $structured > 0 ? 'Listen/Tabellen vorhanden' : 'Keine Listen/Tabellen (KI-Antworten zitieren strukturierte Formate)',
        ];

        // faq (15)
        $faqCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_seo_studio_faq WHERE pid = ? AND published = '1'",
            [$pageId],
        );
        $components['faq'] = [
            'points' => $faqCount > 0 ? 15.0 : 0.0,
            'max' => 15,
            'note' => $faqCount > 0 ? Translations::text('geo.faqPublished', $faqCount) : Translations::text('geo.faqNone'),
        ];

        // freshness — informational only, see below
        $lastmod = max(
            (int) $page['tstamp'],
            (int) $this->connection->fetchOne(
                "SELECT COALESCE(MAX(tstamp), 0) FROM tl_article WHERE pid = ? AND published = '1'",
                [$pageId],
            ),
        );
        $ageDays = $lastmod > 0 ? (int) floor((time() - $lastmod) / 86400) : 9999;

        // INFORMATION, NOT A RATING (max 0). Nobody can touch every page every
        // fortnight, and a finished page does not get worse by resting. Age is
        // worth knowing — for news and prices it matters — but it must not
        // silently drain the score of a perfectly good page.
        $components['freshness'] = [
            'points' => 0.0,
            'max' => 0,
            'note' => Translations::text('geo.lastChanged', $ageDays),
            'info' => true,
        ];

        // schema (10)
        $schemaOn = (bool) $this->config->get('featureSchema', false);
        $orgSet = trim((string) $this->config->get('schemaOrgName', '')) !== '';
        $schemaPoints = $schemaOn ? ($orgSet ? 10.0 : 7.0) : 0.0;
        $components['schema'] = [
            'points' => $schemaPoints,
            'max' => 10,
            'note' => $schemaOn ? ($orgSet ? 'JSON-LD aktiv inkl. Organisation' : 'JSON-LD aktiv, Organisationsname fehlt') : 'Schema-Feature deaktiviert',
        ];

        // answer-first (20) — the only LLM component
        // 'measured' => false means NOT MEASURED, not "scored zero". Consumers
        // must drop the component entirely instead of counting 0 of 20 — an
        // unrun check would otherwise read as a catastrophic AEO rating.
        if ($withLlm) {
            try {
                $verdict = $this->answerFirstChecker->check($pageId);
                $components['answerFirst'] = [
                    'points' => round($verdict->score / 100 * 20, 1),
                    'max' => 20,
                    'note' => $verdict->reason,
                    'measured' => true,
                ];
            } catch (\Throwable $e) {
                $components['answerFirst'] = [
                    'points' => 0.0,
                    'max' => 20,
                    'note' => Translations::text('geo.aiCheckSkipped') . $e->getMessage(),
                    'measured' => false,
                ];
            }
        } else {
            $components['answerFirst'] = [
                'points' => 0.0,
                'max' => 20,
                'note' => 'KI-Check nicht angefordert',
                'measured' => false,
            ];
        }

        // Aggregate over what was actually MEASURED and actually counts. A
        // component with max 0 is pure information (freshness), an unmeasured
        // one never ran (answer-first without an API key) — neither may drag
        // the score down, so both stay out of numerator and denominator.
        $achieved = 0.0;
        $maxTotal = 0.0;

        foreach ($components as $c) {
            if (($c['max'] ?? 0) <= 0 || ($c['measured'] ?? true) === false) {
                continue;
            }

            $achieved += (float) $c['points'];
            $maxTotal += (float) $c['max'];
        }

        $score = $maxTotal > 0.0 ? max(0, min(100, (int) round($achieved / $maxTotal * 100))) : 0;

        $this->persist($pageId, $score, $components);

        return ['score' => $score, 'components' => $components];
    }

    /**
     * Batch: compute the N pages whose score is missing or oldest.
     *
     * @return int number of pages scored
     */
    public function computeBatch(int $limit = 10, bool $withLlm = true): int
    {
        $pageIds = $this->connection->fetchFirstColumn(
            "SELECT p.id FROM tl_page p
             LEFT JOIN tl_seo_studio_score s ON s.pageId = p.id
             WHERE p.type = 'regular' AND p.published = '1'
             ORDER BY COALESCE(s.tstamp, 0), p.id
             LIMIT " . max(1, min(50, $limit)),
        );

        $done = 0;
        foreach ($pageIds as $pageId) {
            try {
                $this->compute((int) $pageId, $withLlm);
                ++$done;
            } catch (\VTinnovations\SeoStudio\Core\Security\BudgetExceededException) {
                // Budget hard stop — continue deterministically.
                $this->compute((int) $pageId, false);
                ++$done;
                $withLlm = false;
            } catch (\Throwable) {
                // Skip broken page, keep the batch going.
            }
        }

        return $done;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getScores(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT s.pageId, s.score, s.components, s.tstamp, p.title
             FROM tl_seo_studio_score s
             JOIN tl_page p ON p.id = s.pageId
             ORDER BY s.score ASC',
        );
    }

    /**
     * @param array<string, array{points: float, max: int, note: string, measured?: bool}> $components
     */
    private function persist(int $pageId, int $score, array $components): void
    {
        $payload = json_encode($components, JSON_UNESCAPED_UNICODE);

        $updated = $this->connection->update(
            'tl_seo_studio_score',
            ['tstamp' => time(), 'score' => $score, 'components' => $payload],
            ['pageId' => $pageId],
        );

        if ($updated === 0) {
            $this->connection->insert('tl_seo_studio_score', [
                'tstamp' => time(),
                'pageId' => $pageId,
                'score' => $score,
                'components' => $payload,
            ]);
        }
    }
}
