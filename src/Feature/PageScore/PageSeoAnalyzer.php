<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use VTinnovations\SeoStudio\Core\Config\Translations;
use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;
use VTinnovations\SeoStudio\Core\Content\GermanText;
use VTinnovations\SeoStudio\Core\Content\ExtractedContent;

/**
 * Deterministic per-page SEO analysis (Yoast/Rank-Math style). NO LLM calls —
 * every check is a rule over the page's real content, so it's free and instant
 * and safe to run on list rendering. The focus keyword drives the keyword
 * group; the basics/readability groups always run.
 */
final class PageSeoAnalyzer
{
    /** @var array<int, SeoReport> per-request memo (list rendering hits the same page once). */
    private array $memo = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContentExtractor $extractor,
    ) {
    }

    public function analyze(int $pageId): SeoReport
    {
        if (isset($this->memo[$pageId])) {
            return $this->memo[$pageId];
        }

        $page = $this->connection->fetchAssociative(
            'SELECT pageTitle, title, description, alias, seoFocusKeyword FROM tl_page WHERE id = ?',
            [$pageId],
        );

        if ($page === false) {
            return $this->memo[$pageId] = new SeoReport(0, []);
        }

        $keyword = trim((string) ($page['seoFocusKeyword'] ?? ''));
        $content = $this->extractor->forPage($pageId);

        $checks = [
            ...$this->basicsChecks($page, $content),
            ...($keyword !== '' ? $this->keywordChecks($keyword, $page, $content) : []),
            ...$this->readabilityChecks($content),
        ];

        return $this->memo[$pageId] = SeoReport::fromChecks($checks, $keyword);
    }

    public function clearMemo(int $pageId): void
    {
        unset($this->memo[$pageId]);
    }

    /**
     * @param array<string, mixed> $page
     * @return list<SeoCheck>
     */
    private function basicsChecks(array $page, ExtractedContent $content): array
    {
        $checks = [];

        // Page title (the real <title>).
        $title = trim((string) $page['pageTitle']);
        $len = mb_strlen($title);
        if ($title === '') {
            $checks[] = new SeoCheck('basics', 'bad', Translations::text('check.titleMissing.label'), Translations::text('check.titleMissing.hint'), 2.0, 'meta');
        } elseif ($len < 30 || $len > 65) {
            $checks[] = new SeoCheck('basics', 'warn', Translations::text('check.titleLength.label'), Translations::text('check.titleLength.hint', $len), 1.0, 'meta');
        } else {
            $checks[] = new SeoCheck('basics', 'good', Translations::text('check.titleSet.label'), Translations::text('check.charactersCount', $len), 2.0);
        }

        // Meta description.
        $desc = trim((string) $page['description']);
        $dlen = mb_strlen($desc);
        if ($desc === '') {
            $checks[] = new SeoCheck('basics', 'bad', Translations::text('check.descriptionMissing.label'), Translations::text('check.descriptionMissing.hint'), 2.0, 'meta');
        } elseif ($dlen < 100 || $dlen > 160) {
            $checks[] = new SeoCheck('basics', 'warn', Translations::text('check.descriptionLength.label'), Translations::text('check.descriptionLength.hint', $dlen), 1.0, 'meta');
        } else {
            $checks[] = new SeoCheck('basics', 'good', 'Meta-Beschreibung gesetzt', $dlen . ' Zeichen.', 2.0);
        }

        // Exactly one H1.
        $h1 = array_filter($content->headings, static fn ($h): bool => $h->level === 1);
        if (\count($h1) === 1) {
            $checks[] = new SeoCheck('basics', 'good', 'Genau eine H1', '');
        } elseif (\count($h1) === 0) {
            $checks[] = new SeoCheck('basics', 'warn', Translations::text('check.noH1.label'), Translations::text('check.noH1.hint'), 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('basics', 'bad', Translations::text('check.multipleH1.label', \count($h1)), Translations::text('check.multipleH1.hint'), 1.0, 'optimize');
        }

        // Subheadings present.
        $subs = array_filter($content->headings, static fn ($h): bool => $h->level >= 2);
        if ($content->wordCount > 300 && $subs === []) {
            $checks[] = new SeoCheck('basics', 'warn', Translations::text('check.noSubheadings.label'), Translations::text('check.noSubheadings.hint'), 1.0, 'optimize');
        } elseif ($subs !== []) {
            $checks[] = new SeoCheck('basics', 'good', Translations::text('check.subheadings.label'), Translations::text('check.subheadings.hint', \count($subs)));
        }

        // Content length.
        if ($content->wordCount === 0) {
            $checks[] = new SeoCheck('basics', 'bad', Translations::text('check.noText.label'), Translations::text('check.noText.hint'), 2.0, 'optimize');
        } elseif ($content->wordCount < 150) {
            $checks[] = new SeoCheck('basics', 'warn', Translations::text('check.littleText.label'), Translations::text('check.littleText.hint', $content->wordCount), 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('basics', 'good', Translations::text('check.enoughText.label'), Translations::text('check.wordsCount', $content->wordCount));
        }

        // Image alt texts.
        [$imgTotal, $imgMissingAlt] = $this->imageAltStats($content->pageId);
        if ($imgTotal > 0) {
            if ($imgMissingAlt === 0) {
                $checks[] = new SeoCheck('basics', 'good', Translations::text('check.altComplete.label'), Translations::text('check.imagesCount', $imgTotal));
            } else {
                $checks[] = new SeoCheck('basics', 'bad', Translations::text('check.altMissing.label', $imgMissingAlt), Translations::text('check.altMissing.hint'), 1.0, 'images');
            }
        }

        return $checks;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<SeoCheck>
     */
    private function keywordChecks(string $keyword, array $page, ExtractedContent $content): array
    {
        $kw = mb_strtolower($keyword);
        $inTitle = str_contains(mb_strtolower((string) $page['pageTitle']), $kw);
        $inAlias = str_contains(mb_strtolower((string) $page['alias']), $this->slugish($kw));
        $inDesc = str_contains(mb_strtolower((string) $page['description']), $kw);
        $inFirst = str_contains(mb_strtolower($content->firstParagraph), $kw);
        $body = mb_strtolower($content->plaintext);

        $h1Text = '';
        $inSub = false;
        foreach ($content->headings as $h) {
            if ($h->level === 1) {
                $h1Text .= ' ' . mb_strtolower($h->text);
            } elseif (str_contains(mb_strtolower($h->text), $kw)) {
                $inSub = true;
            }
        }
        $inH1 = str_contains($h1Text, $kw);

        $checks = [
            new SeoCheck('keyword', $inTitle ? 'good' : 'bad', Translations::text('check.keywordInTitle.label'), $inTitle ? '' : Translations::text('check.keywordInTitle.hint', $keyword), 2.0, $inTitle ? '' : 'meta'),
            new SeoCheck('keyword', $inDesc ? 'good' : 'warn', Translations::text('check.keywordInDescription.label'), $inDesc ? '' : Translations::text('check.keywordInDescription.hint'), 1.5, $inDesc ? '' : 'meta'),
            new SeoCheck('keyword', $inAlias ? 'good' : 'warn', Translations::text('check.keywordInUrl.label'), $inAlias ? '' : Translations::text('check.keywordInUrl.hint'), 1.0),
            new SeoCheck('keyword', $inH1 ? 'good' : 'warn', Translations::text('check.keywordInH1.label'), $inH1 ? '' : Translations::text('check.keywordInH1.hint'), 1.5, $inH1 ? '' : 'optimize'),
            new SeoCheck('keyword', $inFirst ? 'good' : 'warn', Translations::text('check.keywordInFirstParagraph.label'), $inFirst ? '' : Translations::text('check.keywordInFirstParagraph.hint'), 1.5, $inFirst ? '' : 'optimize'),
            new SeoCheck('keyword', $inSub ? 'good' : 'warn', Translations::text('check.keywordInSubheading.label'), $inSub ? '' : Translations::text('check.keywordInSubheading.hint'), 1.0, $inSub ? '' : 'optimize'),
        ];

        // Density.
        if ($content->wordCount > 0) {
            $occurrences = mb_substr_count($body, $kw);
            $density = $occurrences / max(1, $content->wordCount) * 100;
            if ($occurrences === 0) {
                $checks[] = new SeoCheck('keyword', 'bad', Translations::text('check.keywordAbsent.label'), Translations::text('check.keywordAbsent.hint'), 1.5, 'optimize');
            } elseif ($density > 3.0) {
                $checks[] = new SeoCheck('keyword', 'warn', Translations::text('check.keywordDensityHigh.label'), Translations::text('check.keywordDensityHigh.hint', $density), 1.0);
            } elseif ($density < 0.3) {
                $checks[] = new SeoCheck('keyword', 'warn', Translations::text('check.keywordDensityLow.label'), Translations::text('check.keywordDensityLow.hint', $density), 1.0);
            } else {
                $checks[] = new SeoCheck('keyword', 'good', Translations::text('check.keywordDensityGood.label'), Translations::text('check.keywordDensityGood.hint', $density), 1.0);
            }
        }

        return $checks;
    }

    /**
     * @return list<SeoCheck>
     */
    private function readabilityChecks(ExtractedContent $content): array
    {
        if ($content->wordCount < 40) {
            return [];
        }

        $text = $content->plaintext;
        $sentences = array_values(array_filter(preg_split('/[.!?]+/u', $text) ?: [], static fn (string $s): bool => trim($s) !== ''));
        $sentenceCount = max(1, \count($sentences));
        $avgWords = $content->wordCount / $sentenceCount;

        $checks = [];

        // Average sentence length.
        if ($avgWords <= 18) {
            $checks[] = new SeoCheck('readability', 'good', Translations::text('check.sentencesGood.label'), Translations::text('check.sentencesGood.hint', $avgWords));
        } elseif ($avgWords <= 25) {
            $checks[] = new SeoCheck('readability', 'warn', Translations::text('check.sentencesLongish.label'), Translations::text('check.sentencesLongish.hint', $avgWords), 1.0);
        } else {
            $checks[] = new SeoCheck('readability', 'bad', Translations::text('check.sentencesTooLong.label'), Translations::text('check.sentencesTooLong.hint', $avgWords), 1.0, 'optimize');
        }

        // Passive voice (German heuristic: "wird/werden/wurde + …" density).
        $passive = preg_match_all('/\b(wird|werden|wurde|wurden|geworden)\b/u', mb_strtolower($text));
        $passiveRatio = $passive / $sentenceCount;
        if ($passiveRatio <= 0.25) {
            $checks[] = new SeoCheck('readability', 'good', 'Aktive Sprache', '');
        } else {
            $checks[] = new SeoCheck('readability', 'warn', 'Viel Passiv', 'Aktive Formulierungen wirken direkter.', 1.0, 'optimize');
        }

        // Flesch (German Amstad approximation).
        $flesch = GermanText::fleschAmstad($text, $content->wordCount, $sentenceCount);
        $fleschShown = (int) round($flesch);
        $checks[] = match (GermanText::grade($flesch, GermanText::FLESCH_GOOD, GermanText::FLESCH_OK)) {
            'good' => new SeoCheck('readability', 'good', Translations::text('check.readabilityGood.label'), Translations::text('check.readabilityGood.hint', $fleschShown)),
            'warn' => new SeoCheck('readability', 'warn', Translations::text('check.readabilityMedium.label'), Translations::text('check.readabilityMedium.hint', $fleschShown), 1.0, 'optimize'),
            default => new SeoCheck('readability', 'bad', Translations::text('check.readabilityHard.label'), Translations::text('check.readabilityHard.hint', $fleschShown), 1.0, 'optimize'),
        };

        // Transition words (Übergangswörter): share of sentences that use a connector.
        $transitions = GermanText::transitionRatio($sentences) * 100;
        $transitionsShown = (int) round($transitions);
        $checks[] = match (GermanText::grade($transitions, GermanText::TRANSITION_GOOD, GermanText::TRANSITION_OK)) {
            'good' => new SeoCheck('readability', 'good', Translations::text('check.transitionsGood.label'), Translations::text('check.transitionsGood.hint', $transitionsShown)),
            'warn' => new SeoCheck('readability', 'warn', Translations::text('check.transitionsFew.label'), Translations::text('check.transitionsFew.hint', $transitionsShown), 1.0, 'optimize'),
            default => new SeoCheck('readability', 'bad', Translations::text('check.transitionsNone.label'), Translations::text('check.transitionsNone.hint', $transitionsShown), 1.0, 'optimize'),
        };

        // Consecutive sentences starting with the same word (monotone rhythm).
        $maxRun = 1;
        $run = 1;
        $prevFirst = null;
        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/u', trim($sentence)) ?: [];
            $first = mb_strtolower($words[0] ?? '');
            if ($first !== '' && $first === $prevFirst) {
                ++$run;
                $maxRun = max($maxRun, $run);
            } else {
                $run = 1;
            }
            $prevFirst = $first;
        }
        if ($maxRun >= 3) {
            $checks[] = new SeoCheck('readability', 'warn', Translations::text('check.sentenceStartsRepeated.label'), Translations::text('check.sentenceStartsRepeated.hint', $maxRun), 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('readability', 'good', Translations::text('check.sentenceStartsVaried.label'), '');
        }

        return $checks;
    }

    /**
     * @return array{0: int, 1: int} total images, images missing alt
     */
    private function imageAltStats(int $pageId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                // Unpublished articles and hidden elements never reach a visitor,
                // so they must not influence the score either.
                "SELECT c.alt FROM tl_content c
                 JOIN tl_article a ON a.id = c.pid AND c.ptable IN ('', 'tl_article')
                 WHERE a.pid = ? AND a.published = '1' AND c.invisible = ''
                   AND c.type IN ('image', 'picture') AND c.singleSRC IS NOT NULL",
                [$pageId],
            );
        } catch (\Throwable) {
            return [0, 0];
        }

        $total = \count($rows);
        $missing = 0;
        foreach ($rows as $row) {
            if (trim((string) $row['alt']) === '') {
                ++$missing;
            }
        }

        return [$total, $missing];
    }

    private function slugish(string $kw): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $kw), '-');
    }
}
