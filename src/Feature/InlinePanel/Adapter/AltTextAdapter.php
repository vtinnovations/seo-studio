<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel\Adapter;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Feature\InlinePanel\ContextResolver;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;
use VTinnovations\SeoStudio\Feature\InlinePanel\VerdictCache;

/**
 * Alt-text check for image elements — the Vision model actually SEES the
 * file, so the verdict covers "describes the image" and not just style.
 */
final class AltTextAdapter extends AbstractAdapter
{
    private const MAX_BYTES = 4194304; // 4 MB — vision payload cap

    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function __construct(
        AiGateway $ai,
        VerdictCache $cache,
        private readonly ContextResolver $context,
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
    ) {
        parent::__construct($ai, $cache);
    }

    public function getId(): string
    {
        return 'altText';
    }

    public function getFeatureId(): string
    {
        return 'inlineAltText';
    }

    public function suggest(string $table, int $rowId, string $value): PanelResult
    {
        $value = trim($value);
        $row = $this->context->contentRow($rowId);

        if ($row === null) {
            return new PanelResult(0, 'Element nicht gefunden.', []);
        }

        $image = $this->loadImage($row);
        if ($image === null) {
            return new PanelResult(0, 'Bild konnte nicht geladen werden (fehlt, zu groß oder kein JPEG/PNG/WebP/GIF).', []);
        }

        $pageTitle = $this->context->pageTitle($this->context->pageIdForContentElement($rowId));

        // Hash the file content, not the path — replaced image = new verdict.
        $cacheKey = $this->cache->key('altText', $value, md5($image['data']), $pageTitle);

        $system = 'Du prüfst Alternativtexte von Bildern (Barrierefreiheit + Bild-SEO). Du SIEHST das Bild. '
            . 'Antworte über das JSON-Schema. Kriterien: beschreibt den Bildinhalt konkret, unter 125 Zeichen, '
            . 'keine Phrasen wie "Bild von", relevanter Kontext der Seite darf einfließen. '
            . 'Ist der Alt-Text leer, bewerte mit 0 und liefere 2-3 passende Alternativen in der Seitensprache.';

        $user = 'Aktueller Alt-Text: ' . ($value !== '' ? $value : '(leer)') . "\n"
            . ($pageTitle !== '' ? "Seite: {$pageTitle}" : '');

        return $this->cachedVerdict($cacheKey, $system, $user, 'panel_alt_text', [$image]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{mime: string, data: string}|null
     */
    private function loadImage(array $row): ?array
    {
        $uuid = $row['singleSRC'] ?? null;
        if (!\is_string($uuid) || $uuid === '') {
            return null;
        }

        $this->framework->initialize();

        if (Validator::isBinaryUuid($uuid)) {
            $uuid = StringUtil::binToUuid($uuid);
        }

        $file = $this->framework->getAdapter(FilesModel::class)->findByUuid($uuid);
        if ($file === null) {
            return null;
        }

        $path = $this->projectDir . '/' . $file->path;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME_BY_EXT[$ext] ?? null;

        if ($mime === null || !is_file($path) || (int) filesize($path) > self::MAX_BYTES) {
            return null;
        }

        $binary = @file_get_contents($path);
        if ($binary === false) {
            return null;
        }

        return ['mime' => $mime, 'data' => base64_encode($binary)];
    }
}
