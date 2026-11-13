<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Images;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Symfony\Component\Yaml\Yaml;
use VTinnovations\SeoStudio\Core\Content\Visibility;

/**
 * Deterministic image/performance audit:
 *
 *   - image elements without a size (Bildgröße) assignment
 *   - oversized originals (dimension/bytes) among referenced images
 *   - config.yaml WebP/AVIF recommendation (READ + RECOMMEND only — the
 *     wizard NEVER writes config.yaml at runtime; fragile on shared hosting)
 */
final class ImageAuditor
{
    private const MAX_FILES_CHECKED = 50;
    private const OVERSIZE_PIXELS = 2560;
    private const OVERSIZE_BYTES = 512000; // 500 KB

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'unassigned' => $this->findUnassignedElements(),
            'oversized' => $this->findOversizedOriginals(),
            'webp' => $this->checkWebpConfig(),
        ];
    }

    /**
     * Image/picture elements without an image size assignment.
     *
     * @return list<array{id: int, type: string}>
     */
    public function findUnassignedElements(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, type, size FROM tl_content
             WHERE type IN ('image', 'picture') AND " . Visibility::content(),
        );

        $unassigned = [];
        foreach ($rows as $row) {
            $size = @unserialize((string) $row['size'], ['allowed_classes' => false]);
            $hasAssignment = \is_array($size) && array_filter($size, static fn ($v): bool => $v !== '' && $v !== null) !== [];

            if (!$hasAssignment) {
                $unassigned[] = ['id' => (int) $row['id'], 'type' => (string) $row['type']];
            }
        }

        return $unassigned;
    }

    /**
     * Referenced originals that are larger than sensible for web delivery.
     *
     * @return list<array{path: string, width: int, height: int, bytes: int}>
     */
    public function findOversizedOriginals(): array
    {
        $this->framework->initialize();
        $filesAdapter = $this->framework->getAdapter(FilesModel::class);

        $uuids = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT singleSRC FROM tl_content
             WHERE type IN ('image', 'picture') AND " . Visibility::content() . " AND singleSRC IS NOT NULL
             LIMIT " . self::MAX_FILES_CHECKED,
        );

        $oversized = [];

        foreach ($uuids as $uuid) {
            if (!\is_string($uuid) || $uuid === '') {
                continue;
            }

            if (Validator::isBinaryUuid($uuid)) {
                $uuid = StringUtil::binToUuid($uuid);
            }

            $file = $filesAdapter->findByUuid($uuid);
            if ($file === null) {
                continue;
            }

            $path = $this->projectDir . '/' . $file->path;
            if (!is_file($path) || !\in_array(strtolower((string) $file->extension), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }

            $bytes = (int) filesize($path);
            $dimensions = @getimagesize($path);
            $width = \is_array($dimensions) ? (int) $dimensions[0] : 0;
            $height = \is_array($dimensions) ? (int) $dimensions[1] : 0;

            if ($width > self::OVERSIZE_PIXELS || $height > self::OVERSIZE_PIXELS || $bytes > self::OVERSIZE_BYTES) {
                $oversized[] = [
                    'path' => (string) $file->path,
                    'width' => $width,
                    'height' => $height,
                    'bytes' => $bytes,
                ];
            }
        }

        return $oversized;
    }

    /**
     * READ + RECOMMEND: does any image size convert to a modern format (webp/avif)?
     *
     * Contao 5 enables WebP per image size via "formats" — in config.yaml
     * (contao.image.sizes.*, incl. "_defaults") or in the backend
     * (Themes → Image sizes → "Image formats", tl_image_size.formats).
     * There is no global switch; "imagine_options" only tunes quality.
     *
     * @return array{configured: bool, snippet: string}
     */
    public function checkWebpConfig(): array
    {
        $configured = $this->configFileConvertsToModernFormat() || $this->backendSizeConvertsToModernFormat();

        $snippet = "# config/config.yaml — moderne Bildformate (von SEO Studio empfohlen)\n"
            . "contao:\n"
            . "    image:\n"
            . "        imagine_options:\n"
            . "            webp_quality: 80\n"
            . "        sizes:\n"
            . "            _defaults:\n"
            . "                formats:\n"
            . "                    jpg: [webp, jpg]\n"
            . "                    png: [webp, png]\n"
            . "                    gif: [webp, gif]\n";

        return ['configured' => $configured, 'snippet' => $snippet];
    }

    private function configFileConvertsToModernFormat(): bool
    {
        $configFile = $this->projectDir . '/config/config.yaml';
        if (!is_file($configFile)) {
            $configFile = $this->projectDir . '/config/config.yml';
        }

        if (!is_file($configFile)) {
            return false;
        }

        try {
            $sizes = Yaml::parseFile($configFile)['contao']['image']['sizes'] ?? null;
        } catch (\Throwable) {
            return false; // Unparseable config — recommend anyway.
        }

        if (!\is_array($sizes)) {
            return false;
        }

        foreach ($sizes as $size) {
            $formats = \is_array($size) ? ($size['formats'] ?? null) : null;
            if (!\is_array($formats)) {
                continue;
            }

            $found = false;
            array_walk_recursive($formats, static function (mixed $format) use (&$found): void {
                if (\is_string($format) && \in_array(strtolower($format), ['webp', 'avif'], true)) {
                    $found = true;
                }
            });

            if ($found) {
                return true;
            }
        }

        return false;
    }

    private function backendSizeConvertsToModernFormat(): bool
    {
        try {
            return (bool) $this->connection->fetchOne(
                "SELECT 1 FROM tl_image_size WHERE formats LIKE '%webp%' OR formats LIKE '%avif%' LIMIT 1",
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
