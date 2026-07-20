<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\LlmsTxt;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * One-paragraph AI brand summary for llms.txt. Click-triggered from the
 * settings screen, stored in config — never generated on request.
 */
final class SummaryGenerator
{
    public function __construct(
        private readonly AiGateway $ai,
        private readonly ContentExtractor $extractor,
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly LlmsTxtBuilder $builder,
    ) {
    }

    /**
     * Generates + stores the summary. Returns the text.
     */
    public function generateAndStore(): string
    {
        // Homepage = first regular page under the first published root.
        $homeId = $this->connection->fetchOne(
            "SELECT p.id FROM tl_page p
             JOIN tl_page r ON p.pid = r.id AND r.type = 'root' AND r.published = '1'
             WHERE p.type = 'regular' AND p.published = '1'
             ORDER BY r.sorting, p.sorting
             LIMIT 1",
        );

        if ($homeId === false) {
            throw new \RuntimeException('Keine veröffentlichte Startseite gefunden.');
        }

        $content = $this->extractor->forPage((int) $homeId);
        if ($content->isEmpty()) {
            throw new \RuntimeException('Die Startseite hat keinen extrahierbaren Inhalt.');
        }

        $siteName = trim((string) $this->config->get('schemaOrgName', ''));

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: 'Du fasst Websites für KI-Agenten zusammen. Antworte mit GENAU EINEM Absatz '
                . '(2-3 Sätze, max. 60 Wörter), sachlich, ohne Marketing-Floskeln, ohne Markdown. '
                . 'Sprache: ' . $content->language . '.',
            userPrompt: ($siteName !== '' ? "Website: {$siteName}\n" : '')
                . "Startseiten-Inhalt:\n" . $content->truncatedPlaintext(2000),
            model: '',
            temperature: 0.3,
            maxTokens: 200,
            purpose: 'llms_txt_summary',
        ));

        $summary = trim($response->content);
        if ($summary === '') {
            throw new \RuntimeException('KI lieferte keine Zusammenfassung.');
        }

        $this->config->set('llmsTxtSummaryText', $summary);
        $this->builder->invalidateCache();

        return $summary;
    }
}
