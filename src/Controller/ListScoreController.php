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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Config\Translations;
use VTinnovations\SeoStudio\Feature\Optimize\FieldScorer;

/**
 * Returns deterministic form scores for a batch of list rows.
 *
 * Used by list-scores.js to decorate backend lists whose rendering cannot be
 * reached from PHP — notably Contao 5.7's content-element grid, which bypasses
 * the DCA label callback. Client-side injection keeps us out of core templates:
 * if the markup ever changes, the script simply finds nothing and no badge is
 * drawn — never a broken backend.
 *
 * Form checks only: no AI call, so a list of 50 rows stays fast and free.
 */
final class ListScoreController extends AbstractController
{
    /** Per table: [headline field, text field]. */
    private const FIELDS = [
        'tl_content' => ['headline', 'text'],
        'tl_news' => ['headline', 'teaser'],
        'tl_calendar_events' => ['title', 'teaser'],
        'tl_seo_studio_faq' => ['question', 'answer'],
        'tl_seo_studio_glossary' => ['term', 'definition'],
    ];

    private const MAX_IDS = 200;

    public function __construct(
        private readonly Connection $connection,
        private readonly FieldScorer $scorer,
        private readonly FeatureState $featureState,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function scoresAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => Translations::text('error.notLoggedIn')], 403);
        }

        if (!$this->entitlement->isLicensed()) {
            return new JsonResponse(['scores' => []]);
        }

        if (!$this->featureState->isEnabled('optimize')) {
            return new JsonResponse(['scores' => []]);
        }

        $table = (string) $request->request->get('table', '');
        if ($table !== 'tl_article' && !isset(self::FIELDS[$table])) {
            return new JsonResponse(['scores' => []]);
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->request->all('ids')),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return new JsonResponse(['scores' => []]);
        }

        // An article has no text of its own — it scores as the average of the
        // content elements it holds.
        if ($table === 'tl_article') {
            return new JsonResponse(['scores' => $this->articleScores(\array_slice($ids, 0, self::MAX_IDS))]);
        }

        [$headlineField, $textField] = self::FIELDS[$table];

        // Hidden/unpublished rows still get a badge, but marked as not counting.
        $stateField = match ($table) {
            'tl_content' => 'invisible',
            'tl_seo_studio_faq', 'tl_seo_studio_glossary' => 'published',
            default => null,
        };

        try {
            $rows = $this->connection->fetchAllAssociative(
                \sprintf(
                    'SELECT id, %s, %s%s FROM %s WHERE id IN (?)',
                    $headlineField,
                    $textField,
                    $stateField !== null ? ', ' . $stateField : '',
                    $table,
                ),
                [\array_slice($ids, 0, self::MAX_IDS)],
                [ArrayParameterType::INTEGER],
            );
        } catch (\Throwable) {
            return new JsonResponse(['scores' => []]);
        }

        $scores = [];

        foreach ($rows as $row) {
            $parts = [];

            $headline = $this->headlineValue($row[$headlineField] ?? null);
            if ($headline !== '') {
                $parts[] = $this->scorer->score('headline', $headline)['score'];
            }

            $text = trim(strip_tags((string) ($row[$textField] ?? '')));
            if ($text !== '') {
                $parts[] = $this->scorer->score('text', (string) $row[$textField])['score'];
            }

            if ($parts === []) {
                continue; // image, spacer, wrapper … nothing editorial to rate
            }

            $score = (int) round(array_sum($parts) / \count($parts));

            $muted = match ($stateField) {
                'invisible' => (string) ($row['invisible'] ?? '') === '1',
                'published' => (string) ($row['published'] ?? '1') !== '1',
                default => false,
            };

            $scores[(string) $row['id']] = [
                'score' => $score,
                'color' => $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad'),
                'muted' => $muted,
            ];
        }

        return new JsonResponse(['scores' => $scores]);
    }

    /**
     * Average form score of the visible content elements inside each article.
     *
     * @param list<int> $articleIds
     * @return array<int|string, array{score: int, color: string}>
     */
    private function articleScores(array $articleIds): array
    {
        try {
            // Hidden elements never reach a visitor, so they never count.
            $rows = $this->connection->fetchAllAssociative(
                "SELECT pid, headline, text FROM tl_content
                 WHERE ptable IN ('', 'tl_article') AND pid IN (?) AND invisible = ''",
                [$articleIds],
                [ArrayParameterType::INTEGER],
            );

            /** @var array<int, string> $published */
            $published = $this->connection->fetchAllKeyValue(
                'SELECT id, published FROM tl_article WHERE id IN (?)',
                [$articleIds],
                [ArrayParameterType::INTEGER],
            );
        } catch (\Throwable) {
            return [];
        }

        /** @var array<int, list<int>> $byArticle */
        $byArticle = [];

        foreach ($rows as $row) {
            $parts = [];

            $headline = $this->headlineValue($row['headline'] ?? null);
            if ($headline !== '') {
                $parts[] = $this->scorer->score('headline', $headline)['score'];
            }

            $text = trim(strip_tags((string) ($row['text'] ?? '')));
            if ($text !== '') {
                $parts[] = $this->scorer->score('text', (string) $row['text'])['score'];
            }

            if ($parts !== []) {
                $byArticle[(int) $row['pid']][] = (int) round(array_sum($parts) / \count($parts));
            }
        }

        $scores = [];

        foreach ($byArticle as $articleId => $elementScores) {
            $score = (int) round(array_sum($elementScores) / \count($elementScores));

            $scores[(string) $articleId] = [
                'score' => $score,
                'color' => $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad'),
                // Unpublished articles are invisible to visitors and therefore
                // excluded from the page score — say so instead of pretending.
                'muted' => (string) ($published[$articleId] ?? '1') !== '1',
            ];
        }

        return $scores;
    }

    /**
     * Contao stores headlines as a serialized {value, unit} array.
     */
    private function headlineValue(mixed $raw): string
    {
        if (!\is_string($raw) || $raw === '') {
            return '';
        }

        if (str_starts_with($raw, 'a:')) {
            $decoded = @unserialize($raw, ['allowed_classes' => false]);

            return \is_array($decoded) ? trim((string) ($decoded['value'] ?? '')) : '';
        }

        return trim($raw);
    }
}
