<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;
use VTinnovations\SeoStudio\Core\Content\ExtractedContent;

/**
 * Deterministic per-page SEO analysis (Yoast/Rank-Math style). NO LLM calls —
 * every check is a rule over the page's real content, so it's free and instant
 * and safe to run on list rendering. The focus keyword drives the keyword
 * group; the basics/readability groups always run.
 */
final class PageSeoAnalyzer
{
    /**
     * German transition words (Übergangswörter) — a Yoast readability signal.
     * Text that connects its sentences reads as coherent argument, not a list.
     * @var list<string>
     */
    private const TRANSITION_WORDS = [
        'außerdem', 'zudem', 'darüber hinaus', 'ferner', 'weiterhin', 'ebenso', 'auch',
        'daher', 'deshalb', 'deswegen', 'folglich', 'somit', 'infolgedessen',
        'jedoch', 'dennoch', 'trotzdem', 'allerdings', 'andererseits', 'einerseits', 'hingegen',
        'zunächst', 'zuerst', 'danach', 'anschließend', 'schließlich', 'zuletzt', 'abschließend',
        'beispielsweise', 'zum beispiel', 'insbesondere', 'vor allem', 'nämlich',
        'zusammenfassend', 'kurz gesagt', 'erstens', 'zweitens', 'drittens',
        'weil', 'obwohl', 'während', 'sodass', 'damit', 'sofern',
    ];

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
            $checks[] = new SeoCheck('basics', 'bad', 'Seitentitel fehlt', 'Ohne Titel keine gute Suchdarstellung.', 2.0, 'meta');
        } elseif ($len < 30 || $len > 65) {
            $checks[] = new SeoCheck('basics', 'warn', 'Seitentitel-Länge', $len . ' Zeichen — ideal sind 30–60.', 1.0, 'meta');
        } else {
            $checks[] = new SeoCheck('basics', 'good', 'Seitentitel gesetzt', $len . ' Zeichen.', 2.0);
        }

        // Meta description.
        $desc = trim((string) $page['description']);
        $dlen = mb_strlen($desc);
        if ($desc === '') {
            $checks[] = new SeoCheck('basics', 'bad', 'Meta-Beschreibung fehlt', 'Suchmaschinen zeigen sonst zufälligen Text.', 2.0, 'meta');
        } elseif ($dlen < 100 || $dlen > 160) {
            $checks[] = new SeoCheck('basics', 'warn', 'Beschreibungs-Länge', $dlen . ' Zeichen — ideal sind 120–155.', 1.0, 'meta');
        } else {
            $checks[] = new SeoCheck('basics', 'good', 'Meta-Beschreibung gesetzt', $dlen . ' Zeichen.', 2.0);
        }

        // Exactly one H1.
        $h1 = array_filter($content->headings, static fn ($h): bool => $h->level === 1);
        if (\count($h1) === 1) {
            $checks[] = new SeoCheck('basics', 'good', 'Genau eine H1', '');
        } elseif (\count($h1) === 0) {
            $checks[] = new SeoCheck('basics', 'warn', 'Keine H1 im Inhalt', 'OK, wenn das Layout den Seitentitel als H1 rendert.', 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('basics', 'bad', \count($h1) . ' H1-Überschriften', 'Genau eine H1 pro Seite verwenden.', 1.0, 'optimize');
        }

        // Subheadings present.
        $subs = array_filter($content->headings, static fn ($h): bool => $h->level >= 2);
        if ($content->wordCount > 300 && $subs === []) {
            $checks[] = new SeoCheck('basics', 'warn', 'Keine Zwischenüberschriften', 'Zwischenüberschriften verbessern Lesbarkeit und AEO.', 1.0, 'optimize');
        } elseif ($subs !== []) {
            $checks[] = new SeoCheck('basics', 'good', 'Zwischenüberschriften vorhanden', \count($subs) . ' Stück.');
        }

        // Content length.
        if ($content->wordCount === 0) {
            $checks[] = new SeoCheck('basics', 'bad', 'Kein Textinhalt', 'Die Seite hat keinen erfassbaren Text.', 2.0, 'optimize');
        } elseif ($content->wordCount < 150) {
            $checks[] = new SeoCheck('basics', 'warn', 'Wenig Text', $content->wordCount . ' Wörter — mehr Inhalt hilft dem Ranking.', 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('basics', 'good', 'Ausreichend Text', $content->wordCount . ' Wörter.');
        }

        // Image alt texts.
        [$imgTotal, $imgMissingAlt] = $this->imageAltStats($content->pageId);
        if ($imgTotal > 0) {
            if ($imgMissingAlt === 0) {
                $checks[] = new SeoCheck('basics', 'good', 'Alle Bilder mit Alt-Text', $imgTotal . ' Bild(er).');
            } else {
                $checks[] = new SeoCheck('basics', 'bad', $imgMissingAlt . ' Bild(er) ohne Alt-Text', 'Alt-Texte sind wichtig für Bild-SEO und Barrierefreiheit.', 1.0, 'images');
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
            new SeoCheck('keyword', $inTitle ? 'good' : 'bad', 'Keyword im Seitentitel', $inTitle ? '' : '„' . $keyword . '“ sollte im Titel stehen.', 2.0, $inTitle ? '' : 'meta'),
            new SeoCheck('keyword', $inDesc ? 'good' : 'warn', 'Keyword in der Beschreibung', $inDesc ? '' : 'Erhöht die Klickrate in den Suchergebnissen.', 1.5, $inDesc ? '' : 'meta'),
            new SeoCheck('keyword', $inAlias ? 'good' : 'warn', 'Keyword in der URL', $inAlias ? '' : 'Ein sprechender Alias mit Keyword hilft.', 1.0),
            new SeoCheck('keyword', $inH1 ? 'good' : 'warn', 'Keyword in der H1', $inH1 ? '' : 'Die Hauptüberschrift sollte das Keyword enthalten.', 1.5, $inH1 ? '' : 'optimize'),
            new SeoCheck('keyword', $inFirst ? 'good' : 'warn', 'Keyword im ersten Absatz', $inFirst ? '' : 'Früh im Text nennt das Thema klar.', 1.5, $inFirst ? '' : 'optimize'),
            new SeoCheck('keyword', $inSub ? 'good' : 'warn', 'Keyword in einer Zwischenüberschrift', $inSub ? '' : 'Verstärkt die thematische Relevanz.', 1.0, $inSub ? '' : 'optimize'),
        ];

        // Density.
        if ($content->wordCount > 0) {
            $occurrences = mb_substr_count($body, $kw);
            $density = $occurrences / max(1, $content->wordCount) * 100;
            if ($occurrences === 0) {
                $checks[] = new SeoCheck('keyword', 'bad', 'Keyword kommt im Text nicht vor', 'Das Fokus-Keyword sollte im Fließtext auftauchen.', 1.5, 'optimize');
            } elseif ($density > 3.0) {
                $checks[] = new SeoCheck('keyword', 'warn', 'Keyword-Dichte hoch', sprintf('%.1f %% — wirkt schnell wie Spam (ideal 0,5–2,5 %%).', $density), 1.0);
            } elseif ($density < 0.3) {
                $checks[] = new SeoCheck('keyword', 'warn', 'Keyword-Dichte niedrig', sprintf('%.1f %% — ruhig etwas häufiger nennen.', $density), 1.0);
            } else {
                $checks[] = new SeoCheck('keyword', 'good', 'Keyword-Dichte gut', sprintf('%.1f %%.', $density), 1.0);
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
            $checks[] = new SeoCheck('readability', 'good', 'Sätze gut lesbar', sprintf('Ø %.0f Wörter/Satz.', $avgWords));
        } elseif ($avgWords <= 25) {
            $checks[] = new SeoCheck('readability', 'warn', 'Sätze etwas lang', sprintf('Ø %.0f Wörter/Satz — kürzere Sätze lesen sich leichter.', $avgWords), 1.0);
        } else {
            $checks[] = new SeoCheck('readability', 'bad', 'Sätze zu lang', sprintf('Ø %.0f Wörter/Satz — deutlich kürzen.', $avgWords), 1.0, 'optimize');
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
        $syllables = $this->estimateSyllables($text);
        $flesch = 180 - $avgWords - (58.5 * $syllables / max(1, $content->wordCount));
        if ($flesch >= 60) {
            $checks[] = new SeoCheck('readability', 'good', 'Gut verständlich', sprintf('Lesbarkeit %.0f/100.', max(0, min(100, $flesch))));
        } elseif ($flesch >= 40) {
            $checks[] = new SeoCheck('readability', 'warn', 'Mittel verständlich', sprintf('Lesbarkeit %.0f/100 — einfacher formulieren.', max(0, min(100, $flesch))), 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('readability', 'bad', 'Schwer verständlich', sprintf('Lesbarkeit %.0f/100 — kürzere Wörter und Sätze.', max(0, min(100, $flesch))), 1.0, 'optimize');
        }

        // Transition words (Übergangswörter): share of sentences that use a connector.
        $withTransition = 0;
        foreach ($sentences as $sentence) {
            $s = mb_strtolower($sentence);
            foreach (self::TRANSITION_WORDS as $word) {
                if (str_contains($s, $word)) {
                    ++$withTransition;
                    break;
                }
            }
        }
        $transitionRatio = $withTransition / $sentenceCount;
        if ($transitionRatio >= 0.30) {
            $checks[] = new SeoCheck('readability', 'good', 'Gute Textführung', sprintf('%.0f %% der Sätze mit Übergangswörtern.', $transitionRatio * 100));
        } elseif ($transitionRatio >= 0.15) {
            $checks[] = new SeoCheck('readability', 'warn', 'Wenige Übergangswörter', sprintf('%.0f %% — Verbindungen wie „außerdem", „daher" führen den Leser.', $transitionRatio * 100), 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('readability', 'bad', 'Kaum Übergangswörter', 'Sätze wirken zusammenhanglos — Verbindungswörter einbauen.', 1.0, 'optimize');
        }

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
            $checks[] = new SeoCheck('readability', 'warn', 'Gleiche Satzanfänge', $maxRun . ' Sätze in Folge beginnen gleich — für Abwechslung sorgen.', 1.0, 'optimize');
        } else {
            $checks[] = new SeoCheck('readability', 'good', 'Abwechslungsreiche Satzanfänge', '');
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
                "SELECT c.alt FROM tl_content c
                 JOIN tl_article a ON a.id = c.pid AND c.ptable IN ('', 'tl_article')
                 WHERE a.pid = ? AND c.invisible = '' AND c.type IN ('image', 'picture') AND c.singleSRC IS NOT NULL",
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

    private function estimateSyllables(string $text): int
    {
        // Vowel-group count — rough but stable across German/English.
        $vowels = preg_match_all('/[aeiouäöüy]+/iu', $text);

        return max(1, (int) $vowels);
    }

    private function slugish(string $kw): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $kw), '-');
    }
}
