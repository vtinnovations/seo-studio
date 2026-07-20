<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Meta;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * Generates pageTitle (<title>) + meta description proposals for a tl_page.
 *
 * Deterministic guard rails: content is truncated to a token cap, the result
 * is length-clamped server-side, and pages without any content get a clear
 * error instead of a hallucinated description.
 */
final class MetaGenerator
{
    private const SCHEMA = [
        'name' => 'meta_result',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'pageTitle' => ['type' => 'string', 'description' => 'SEO page title, 45-60 characters'],
                'description' => ['type' => 'string', 'description' => 'Meta description, 120-155 characters'],
            ],
            'required' => ['pageTitle', 'description'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly ContentExtractor $extractor,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws AiException
     * @throws \RuntimeException when the page has no extractable content
     */
    public function propose(int $pageId): MetaProposal
    {
        $content = $this->extractor->forPage($pageId);

        if ($content->isEmpty() && $content->pageTitle === '') {
            throw new \RuntimeException('Die Seite hat keinen extrahierbaren Inhalt — bitte erst Inhalte anlegen.');
        }

        $siteName = $this->resolveSiteName($pageId);

        $system = 'Du bist ein SEO-Experte. Du erstellst prägnante Seitentitel und Meta-Descriptions. '
            . 'Antworte ausschließlich über das Tool/JSON-Schema. Sprache der Ausgabe: ' . $content->language . '. '
            . 'Regeln: pageTitle 45-60 Zeichen, wichtigstes Thema zuerst, kein Keyword-Stuffing, keine Anführungszeichen. '
            . 'description 120-155 Zeichen, aktiver Stil, konkreter Nutzen, keine Phrasen wie "Willkommen auf".';

        $user = "Seite: {$content->pageTitle}\n"
            . ($siteName !== '' ? "Website: {$siteName}\n" : '')
            . "Gliederung:\n{$content->headingOutline()}\n\n"
            . "Inhalt:\n{$content->truncatedPlaintext(2500)}";

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.4,
            maxTokens: 400,
            responseSchema: self::SCHEMA,
            purpose: 'meta_generation',
        ));

        $json = $response->asJson();
        if ($json === null || !\is_string($json['pageTitle'] ?? null) || !\is_string($json['description'] ?? null)) {
            throw new AiException('KI-Antwort war kein gültiges JSON.', \VTinnovations\SeoStudio\Core\Ai\AiExceptionKind::InvalidResponse);
        }

        return new MetaProposal(
            pageTitle: $this->clamp(trim($json['pageTitle']), 70),
            description: $this->clamp(trim($json['description']), 170),
        );
    }

    /**
     * Bulk path (cron): fill ONLY empty fields, never overwrite.
     * Returns true when something was written.
     */
    public function fillEmpty(int $pageId): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pageTitle, description FROM tl_page WHERE id = ?',
            [$pageId],
        );

        if ($row === false) {
            return false;
        }

        $needsTitle = trim((string) $row['pageTitle']) === '';
        $needsDescription = trim((string) $row['description']) === '';

        if (!$needsTitle && !$needsDescription) {
            return false;
        }

        $proposal = $this->propose($pageId);

        $update = [];
        if ($needsTitle) {
            $update['pageTitle'] = $proposal->pageTitle;
        }
        if ($needsDescription) {
            $update['description'] = $proposal->description;
        }

        $this->connection->update('tl_page', $update, ['id' => $pageId]);

        return true;
    }

    /**
     * Bulk fill for a page root (or the whole site when $rootId is null):
     * fills ONLY empty pageTitle/description on published regular pages,
     * up to $limit pages per call (LLM latency — callers batch).
     *
     * @return array{done: int, failed: int, remaining: int}
     */
    public function bulkFill(?int $rootId, int $limit = 10): array
    {
        $pageIds = $this->findPagesWithEmptyMeta($rootId);
        $batch = \array_slice($pageIds, 0, max(1, min(25, $limit)));

        $done = 0;
        $failed = 0;

        foreach ($batch as $pageId) {
            try {
                if ($this->fillEmpty($pageId)) {
                    ++$done;
                }
            } catch (\VTinnovations\SeoStudio\Core\Security\BudgetExceededException $e) {
                throw $e; // hard stop — bubble up, caller reports it
            } catch (\Throwable) {
                ++$failed; // one broken page must not stall the batch
            }
        }

        return [
            'done' => $done,
            'failed' => $failed,
            'remaining' => max(0, \count($pageIds) - \count($batch)),
        ];
    }

    /**
     * Published regular pages with empty pageTitle or description, optionally
     * scoped to one root page (descendants resolved in PHP — portable).
     *
     * @return list<int>
     */
    public function findPagesWithEmptyMeta(?int $rootId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, pid, type, pageTitle, description FROM tl_page WHERE published = '1' ORDER BY sorting",
        );

        $childrenByPid = [];
        foreach ($rows as $row) {
            $childrenByPid[(int) $row['pid']][] = $row;
        }

        $result = [];
        $collect = function (int $parentId, int $depth) use (&$collect, &$result, $childrenByPid): void {
            if ($depth > 25) {
                return;
            }

            foreach ($childrenByPid[$parentId] ?? [] as $page) {
                if ((string) $page['type'] === 'regular'
                    && (trim((string) $page['pageTitle']) === '' || trim((string) $page['description']) === '')
                ) {
                    $result[] = (int) $page['id'];
                }
                $collect((int) $page['id'], $depth + 1);
            }
        };

        if ($rootId !== null) {
            $collect($rootId, 0);
        } else {
            foreach ($childrenByPid[0] ?? [] as $root) {
                $collect((int) $root['id'], 0);
            }
        }

        return $result;
    }

    private function clamp(string $value, int $maxChars): string
    {
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        $cut = mb_substr($value, 0, $maxChars - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        return ($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut) . '…';
    }

    private function resolveSiteName(int $pageId): string
    {
        // Root page title = site name in Contao convention.
        $currentId = $pageId;
        for ($i = 0; $i < 25; ++$i) {
            $row = $this->connection->fetchAssociative(
                'SELECT pid, type, title FROM tl_page WHERE id = ?',
                [$currentId],
            );

            if ($row === false) {
                break;
            }

            if (($row['type'] ?? '') === 'root') {
                return (string) $row['title'];
            }

            $currentId = (int) $row['pid'];
            if ($currentId === 0) {
                break;
            }
        }

        return '';
    }
}
