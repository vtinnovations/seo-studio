<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Glossary;

use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * AI glossary: generates answer-first definitions for terms (chunked, one
 * LLM call per ~5 terms) and suggests site-relevant terms from real page
 * content. Entries are created UNPUBLISHED — editors curate and publish.
 */
final class GlossaryGenerator
{
    private const DEFINITION_SCHEMA = [
        'name' => 'glossary_result',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'definitions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'term' => ['type' => 'string'],
                            'definition' => ['type' => 'string', 'description' => '60-120 Wörter, erster Satz = direkte Definition'],
                            'metaTitle' => ['type' => 'string', 'description' => 'SEO-Seitentitel, 45-60 Zeichen, enthält den Begriff'],
                            'metaDescription' => ['type' => 'string', 'description' => 'Meta-Description, 120-155 Zeichen'],
                        ],
                        'required' => ['term', 'definition', 'metaTitle', 'metaDescription'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['definitions'],
            'additionalProperties' => false,
        ],
    ];

    private const SUGGEST_SCHEMA = [
        'name' => 'term_suggestions',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'terms' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['terms'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly ContentExtractor $extractor,
        private readonly Slug $slug,
    ) {
    }

    /**
     * Generates definitions for the given terms; skips terms that already
     * exist. Chunked so one broken call doesn't lose the whole batch.
     *
     * @param list<string> $terms
     * @return array{created: int, skipped: int}
     */
    public function generate(array $terms): array
    {
        $terms = array_values(array_unique(array_filter(array_map('trim', $terms))));
        if ($terms === []) {
            return ['created' => 0, 'skipped' => 0];
        }

        $existing = array_map(
            'mb_strtolower',
            $this->connection->fetchFirstColumn('SELECT term FROM tl_seo_studio_glossary'),
        );

        $newTerms = array_values(array_filter(
            $terms,
            static fn (string $term): bool => !\in_array(mb_strtolower($term), $existing, true),
        ));
        $skipped = \count($terms) - \count($newTerms);

        $language = $this->resolveLanguage();
        $siteName = trim((string) $this->config->get('schemaOrgName', ''));

        $created = 0;
        $lastError = null;

        foreach (array_chunk($newTerms, 5) as $chunk) {
            $created += $this->generateChunk($chunk, $language, $siteName, $lastError);
        }

        // Every chunk failed — surface the reason instead of a silent "0".
        if ($created === 0 && $lastError !== null) {
            throw new \RuntimeException('Generierung fehlgeschlagen: ' . $lastError->getMessage(), 0, $lastError);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Suggests glossary-worthy terms from real site content (homepage),
     * excluding terms that already exist.
     *
     * @return list<string>
     */
    public function suggestTerms(int $limit = 10): array
    {
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

        $existing = $this->connection->fetchFirstColumn('SELECT term FROM tl_seo_studio_glossary');

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: 'Du schlägst Glossar-Begriffe für eine Website vor (Fachbegriffe, die Besucher '
                . 'nachschlagen würden — gut für SEO und KI-Suchmaschinen). Antworte über das JSON-Schema. '
                . 'Genau ' . max(1, min(20, $limit)) . ' Begriffe, Sprache: ' . $content->language . '. '
                . 'Nur Begriffe mit echtem Bezug zum Website-Inhalt.',
            userPrompt: "Website-Inhalt:\n" . $content->truncatedPlaintext(2500)
                . ($existing !== [] ? "\n\nBereits im Glossar (NICHT wiederholen):\n- " . implode("\n- ", array_map('strval', $existing)) : ''),
            model: '',
            temperature: 0.5,
            maxTokens: 500,
            responseSchema: self::SUGGEST_SCHEMA,
            purpose: 'glossary_suggest',
        ));

        $json = $response->asJson() ?? [];
        $terms = [];
        foreach ((array) ($json['terms'] ?? []) as $term) {
            if (\is_string($term) && trim($term) !== '') {
                $terms[] = trim($term);
            }
        }

        return $terms;
    }

    /**
     * @param list<string> $chunk
     */
    private function generateChunk(array $chunk, string $language, string $siteName, ?\Throwable &$lastError = null): int
    {
        $system = 'Du schreibst Glossar-Definitionen für eine Website. Antworte über das JSON-Schema. '
            . 'Regeln pro Definition: 60-120 Wörter, der ERSTE Satz definiert den Begriff direkt '
            . '(Antwort-zuerst — KI-Suchmaschinen zitieren solche Absätze), danach Kontext/Beispiel. '
            . 'Sachlich, kein Marketing, keine Anrede. Reiner Fließtext ohne Markdown/HTML. '
            . 'Zusätzlich pro Begriff SEO-Metadaten für die Detailseite: metaTitle (45-60 Zeichen, enthält den Begriff) '
            . 'und metaDescription (120-155 Zeichen). '
            . 'Sprache: ' . $language . '.';

        $user = ($siteName !== '' ? "Website: {$siteName}\n" : '')
            . "Begriffe:\n- " . implode("\n- ", $chunk);

        try {
            $response = $this->ai->complete(new PromptBundle(
                systemPrompt: $system,
                userPrompt: $user,
                model: '',
                temperature: 0.3,
                maxTokens: 2000,
                responseSchema: self::DEFINITION_SCHEMA,
                purpose: 'glossary_generation',
            ));
        } catch (\VTinnovations\SeoStudio\Core\Security\BudgetExceededException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $lastError = $e;

            return 0; // chunk failed — others may still succeed
        }

        $json = $response->asJson() ?? [];
        $created = 0;

        foreach ((array) ($json['definitions'] ?? []) as $entry) {
            $term = trim((string) ($entry['term'] ?? ''));
            $definition = trim((string) ($entry['definition'] ?? ''));

            if ($term === '' || $definition === '') {
                continue;
            }

            $this->insertEntry(
                $term,
                '<p>' . htmlspecialchars($definition, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
                false,
                trim((string) ($entry['metaTitle'] ?? '')),
                trim((string) ($entry['metaDescription'] ?? '')),
            );
            ++$created;
        }

        return $created;
    }

    /**
     * Insert with a collision-safe alias. Shared by generator and importer.
     */
    public function insertEntry(
        string $term,
        string $definitionHtml,
        bool $published,
        string $metaTitle = '',
        string $metaDescription = '',
    ): void {
        $base = $this->slug->generate($term, [], fn (string $alias): bool => $this->aliasExists($alias));

        $this->connection->insert('tl_seo_studio_glossary', [
            'tstamp' => time(),
            'term' => mb_substr($term, 0, 255),
            'alias' => mb_substr($base, 0, 255),
            'definition' => $definitionHtml,
            'metaTitle' => mb_substr($metaTitle, 0, 255),
            'metaDescription' => mb_substr($metaDescription, 0, 500),
            'published' => $published ? '1' : '',
        ]);
    }

    /**
     * SEO title + description for one existing entry (backend panel).
     *
     * @return array{metaTitle: string, metaDescription: string}
     */
    public function proposeMeta(int $entryId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT term, definition FROM tl_seo_studio_glossary WHERE id = ?',
            [$entryId],
        );

        if ($row === false) {
            throw new \RuntimeException('Glossar-Eintrag nicht gefunden.');
        }

        $siteName = trim((string) $this->config->get('schemaOrgName', ''));
        $plainDefinition = trim(strip_tags((string) $row['definition']));

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: 'Du erstellst SEO-Metadaten für eine Glossar-Detailseite. Antworte über das JSON-Schema. '
                . 'Regeln: metaTitle 45-60 Zeichen, enthält den Begriff, keine Anführungszeichen. '
                . 'metaDescription 120-155 Zeichen, fasst die Definition zusammen, aktiver Stil. '
                . 'Sprache = Sprache der Definition.',
            userPrompt: 'Begriff: ' . $row['term'] . "\n"
                . ($siteName !== '' ? "Website: {$siteName}\n" : '')
                . "Definition:\n" . mb_substr($plainDefinition, 0, 2000),
            model: '',
            temperature: 0.4,
            maxTokens: 300,
            responseSchema: [
                'name' => 'glossary_meta',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'metaTitle' => ['type' => 'string'],
                        'metaDescription' => ['type' => 'string'],
                    ],
                    'required' => ['metaTitle', 'metaDescription'],
                    'additionalProperties' => false,
                ],
            ],
            purpose: 'glossary_meta',
        ));

        $json = $response->asJson() ?? [];

        $metaTitle = trim((string) ($json['metaTitle'] ?? ''));
        $metaDescription = trim((string) ($json['metaDescription'] ?? ''));

        if ($metaTitle === '' || $metaDescription === '') {
            throw new \RuntimeException('KI-Antwort war unvollständig.');
        }

        return ['metaTitle' => $metaTitle, 'metaDescription' => $metaDescription];
    }

    private function aliasExists(string $alias): bool
    {
        return $this->connection->fetchOne(
            'SELECT id FROM tl_seo_studio_glossary WHERE alias = ?',
            [$alias],
        ) !== false;
    }

    private function resolveLanguage(): string
    {
        $override = trim((string) $this->config->get('languageOverride', ''));
        if ($override !== '') {
            return $override;
        }

        $language = $this->connection->fetchOne(
            "SELECT language FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting LIMIT 1",
        );

        return \is_string($language) && $language !== '' ? $language : 'de';
    }
}
