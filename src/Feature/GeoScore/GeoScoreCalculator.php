<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\GeoScore;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Feature\Audit\AnswerFirstChecker;
use VTinnovations\SeoStudio\Feature\Audit\HeadingAuditor;

/**
 * GEO readiness score (0-100) per page. Mostly deterministic; the only LLM
 * component is the answer-first check (skipped gracefully without key/budget
 * — the weight is then redistributed so pages aren't punished for a missing
 * API key).
 *
 * Components (weights):
 *   meta 20 · headings 15 · answerFirst 20 · structuredFormats 10 ·
 *   faq 10 · freshness 15 · schema 10
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
     * @return array{score: int, components: array<string, array{points: float, max: int, note: string}>}
     */
    public function compute(int $pageId, bool $withLlm = true): array
    {
        $page = $this->connection->fetchAssociative(
            'SELECT id, pageTitle, description, tstamp FROM tl_page WHERE id = ?',
            [$pageId],
        );

        if ($page === false) {
            throw new \RuntimeException('Seite nicht gefunden.');
        }

        $components = [];

        // meta (20)
        $metaPoints = (trim((string) $page['pageTitle']) !== '' ? 10 : 0)
            + (trim((string) $page['description']) !== '' ? 10 : 0);
        $components['meta'] = [
            'points' => (float) $metaPoints,
            'max' => 20,
            'note' => $metaPoints === 20 ? 'Titel + Beschreibung gesetzt' : 'Seitentitel/Beschreibung unvollständig',
        ];

        // headings (15)
        $findings = $this->headingAuditor->audit($pageId);
        $hasError = array_filter($findings, static fn (array $f): bool => $f['severity'] === 'error') !== [];
        $hasWarning = array_filter($findings, static fn (array $f): bool => $f['severity'] === 'warning') !== [];
        $headingPoints = $hasError ? 0 : ($hasWarning ? 8 : 15);
        $components['headings'] = [
            'points' => (float) $headingPoints,
            'max' => 15,
            'note' => $hasError ? 'Struktur-Fehler (H1)' : ($hasWarning ? 'Ebenen-Sprünge' : 'Struktur sauber'),
        ];

        // structured formats (10): lists, tables, accordions on the page
        $structured = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_content c
             JOIN tl_article a ON a.id = c.pid AND c.ptable IN ('', 'tl_article')
             WHERE a.pid = ? AND c.invisible = '' AND c.type IN ('list', 'table', 'accordionSingle', 'accordionStart', 'accordion')",
            [$pageId],
        );
        $components['structuredFormats'] = [
            'points' => $structured > 0 ? 10.0 : 0.0,
            'max' => 10,
            'note' => $structured > 0 ? 'Listen/Tabellen vorhanden' : 'Keine Listen/Tabellen (KI-Antworten zitieren strukturierte Formate)',
        ];

        // faq (10)
        $faqCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_seo_studio_faq WHERE pid = ? AND published = '1'",
            [$pageId],
        );
        $components['faq'] = [
            'points' => $faqCount > 0 ? 10.0 : 0.0,
            'max' => 10,
            'note' => $faqCount > 0 ? $faqCount . ' veröffentlichte FAQ' : 'Keine FAQ veröffentlicht',
        ];

        // freshness (15)
        $lastmod = max(
            (int) $page['tstamp'],
            (int) $this->connection->fetchOne(
                "SELECT COALESCE(MAX(tstamp), 0) FROM tl_article WHERE pid = ? AND published = '1'",
                [$pageId],
            ),
        );
        $ageDays = $lastmod > 0 ? (int) floor((time() - $lastmod) / 86400) : 9999;
        $freshnessPoints = match (true) {
            $ageDays <= 14 => 15.0,
            $ageDays <= 90 => 10.0,
            $ageDays <= 365 => 5.0,
            default => 0.0,
        };
        $components['freshness'] = [
            'points' => $freshnessPoints,
            'max' => 15,
            'note' => sprintf('Letzte Änderung vor %d Tagen', $ageDays),
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
        $llmOk = false;
        if ($withLlm) {
            try {
                $verdict = $this->answerFirstChecker->check($pageId);
                $components['answerFirst'] = [
                    'points' => round($verdict->score / 100 * 20, 1),
                    'max' => 20,
                    'note' => $verdict->reason,
                ];
                $llmOk = true;
            } catch (\Throwable $e) {
                $components['answerFirst'] = [
                    'points' => 0.0,
                    'max' => 20,
                    'note' => 'KI-Check übersprungen: ' . $e->getMessage(),
                ];
            }
        } else {
            $components['answerFirst'] = ['points' => 0.0, 'max' => 20, 'note' => 'KI-Check nicht angefordert'];
        }

        // Aggregate. Without the LLM component, scale the deterministic part
        // to 100 so a missing API key doesn't read as a bad page.
        $achieved = array_sum(array_map(static fn (array $c): float => $c['points'], $components));
        $maxTotal = $llmOk ? 100 : 80;
        if (!$llmOk) {
            $achieved -= $components['answerFirst']['points'];
        }

        $score = max(0, min(100, (int) round($achieved / $maxTotal * 100)));

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
     * @param array<string, array{points: float, max: int, note: string}> $components
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
