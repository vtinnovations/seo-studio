<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Content;

/**
 * Normalized page content, extracted from the DB (no HTTP rendering).
 */
final class ExtractedContent
{
    /**
     * @param list<HeadingNode> $headings
     */
    public function __construct(
        public readonly int $pageId,
        public readonly string $pageTitle,
        public readonly string $language,
        public readonly string $plaintext,
        public readonly array $headings,
        public readonly string $firstParagraph,
        public readonly int $wordCount,
    ) {
    }

    public function isEmpty(): bool
    {
        return trim($this->plaintext) === '';
    }

    /**
     * Plaintext truncated to a rough token cap (~4 chars/token heuristic) so
     * prompts never explode. Cuts at a word boundary.
     */
    public function truncatedPlaintext(int $maxTokens = 3000): string
    {
        $maxChars = $maxTokens * 4;
        if (mb_strlen($this->plaintext) <= $maxChars) {
            return $this->plaintext;
        }

        $cut = mb_substr($this->plaintext, 0, $maxChars);
        $lastSpace = mb_strrpos($cut, ' ');

        return ($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut) . ' …';
    }

    /**
     * "H2: Foo\nH3: Bar" — compact outline for prompts.
     */
    public function headingOutline(): string
    {
        return implode("\n", array_map(
            static fn (HeadingNode $h): string => sprintf('H%d: %s', $h->level, $h->text),
            $this->headings,
        ));
    }
}
