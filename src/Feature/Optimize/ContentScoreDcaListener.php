<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Shows a traffic-light score on every editorial row in a list (content
 * elements of an article, FAQ entries, glossary terms), so editors see at a
 * glance which blocks need work instead of opening each one.
 *
 * Deliberately FORM-ONLY (deterministic, no LLM): a list can hold dozens of
 * rows and one AI call per row would be slow and expensive. The semantic
 * criteria (coherence, topic, substance) are added when a row is opened.
 *
 * PRIORITY MATTERS: Contao installs its own DCA callbacks — including
 * tl_content's `list.label.label_callback` — from a loadDataContainer hook at
 * priority -16. Registering at the default priority would have our callback
 * silently replaced, so we run later (-32) and WRAP whatever is there.
 */
#[AsHook('loadDataContainer', priority: -32)]
final class ContentScoreDcaListener
{
    /**
     * Views that should carry score badges. tl_article has no text of its own —
     * it is decorated client-side with the average of its content elements.
     */
    private const TABLES = ['tl_content', 'tl_article', 'tl_seo_studio_faq', 'tl_seo_studio_glossary'];

    /** Per table: [headline field, text field]. */
    private const FIELDS = [
        'tl_content' => ['headline', 'text'],
        'tl_seo_studio_faq' => ['question', 'answer'],
        'tl_seo_studio_glossary' => ['term', 'definition'],
    ];

    public function __construct(
        private readonly FeatureState $featureState,
        private readonly FieldScorer $scorer,
        private readonly \Contao\CoreBundle\Csrf\ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(string $table): void
    {
        if (!\in_array($table, self::TABLES, true) || !$this->featureState->isEnabled('optimize')) {
            return;
        }

        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        // Client-side fallback for list views PHP cannot reach (see class doc).
        // The token manager only works inside a real request — during cache
        // warmup the DCA is loaded too, so failing here must stay harmless.
        try {
            $token = $this->csrfTokenManager->getDefaultTokenValue();
        } catch (\Throwable) {
            $token = null;
        }

        if ($token !== null) {
            $GLOBALS['TL_JAVASCRIPT']['seo_studio_list_scores'] = 'bundles/vtinnovationsseostudio/list-scores.js';
            $GLOBALS['TL_MOOTOOLS'][] = '<script>window.SeoStudioListScores={url:'
                . json_encode('/contao/seostudio/list-scores', JSON_THROW_ON_ERROR) . ',token:'
                . json_encode($token, JSON_THROW_ON_ERROR) . '};</script>';
        }

        // Server-side badge only where the row has its own editorial fields.
        if (!isset(self::FIELDS[$table])) {
            return;
        }

        $config = &$GLOBALS['TL_DCA'][$table]['list']['label'];
        $previous = $config['label_callback'] ?? null;
        $badge = fn (array $row): string => $this->badge($table, $row);


        $config['label_callback'] = static function (array $row, string $label = '', ...$rest) use ($previous, $badge) {
            // Let the original callback build the row first (Contao renders the
            // content-element preview here and may return [label, preview, state]).
            $result = $label;

            if (\is_array($previous) && \count($previous) === 2) {
                $result = \Contao\System::importStatic($previous[0])->{$previous[1]}($row, $label, ...$rest);
            } elseif (\is_callable($previous)) {
                $result = $previous($row, $label, ...$rest);
            }

            $prefix = $badge($row);
            if ($prefix === '') {
                return $result;
            }

            if (\is_array($result)) {
                $result[0] = $prefix . (string) ($result[0] ?? '');

                return $result;
            }

            return $prefix . (string) $result;
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function badge(string $table, array $row): string
    {

        [$headlineField, $textField] = self::FIELDS[$table];

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $scores = [];

        $headline = $this->headlineValue($row[$headlineField] ?? null);
        if ($headline !== '') {
            $scores[] = $this->scorer->score('headline', $headline)['score'];
        }

        $text = trim(strip_tags((string) ($row[$textField] ?? '')));
        if ($text !== '') {
            $scores[] = $this->scorer->score('text', (string) $row[$textField])['score'];
        }

        // Nothing editorial in this row (image, spacer, row wrapper …).
        if ($scores === []) {
            return '';
        }

        $score = (int) round(array_sum($scores) / \count($scores));
        $color = $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad');

        return '<span class="seo-studio-pagebadge seo-studio-pagebadge--' . $color . '"'
            . ' title="' . $e(Translations::text('optimize.badgeTitle', $score)) . '">'
            . $score . '</span> ';
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
