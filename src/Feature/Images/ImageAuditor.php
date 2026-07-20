<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Images;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Symfony\Component\Yaml\Yaml;

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
             WHERE type IN ('image', 'picture') AND invisible = ''",
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
             WHERE type IN ('image', 'picture') AND invisible = '' AND singleSRC IS NOT NULL
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
     * READ + RECOMMEND: is a modern default format (webp/avif) configured?
     *
     * @return array{configured: bool, snippet: string}
     */
    public function checkWebpConfig(): array
    {
        $configured = false;

        $configFile = $this->projectDir . '/config/config.yaml';
        if (!is_file($configFile)) {
            $configFile = $this->projectDir . '/config/config.yml';
        }

        if (is_file($configFile)) {
            try {
                $parsed = Yaml::parseFile($configFile);
                $formats = $parsed['contao']['image']['preview']['default_format'] ?? null;
                $validExtensions = $parsed['contao']['image']['valid_extensions'] ?? null;
                $imagineOptions = $parsed['contao']['image']['imagine_options'] ?? [];

                $configured = (\is_array($imagineOptions) && ($imagineOptions['format'] ?? '') === 'webp')
                    || $formats === 'webp'
                    || (\is_array($validExtensions) && \in_array('avif', $validExtensions, true));
            } catch (\Throwable) {
                // Unparseable config — recommend anyway.
            }
        }

        $snippet = "# config/config.yaml — moderne Bildformate (von SEO Studio empfohlen)\n"
            . "contao:\n"
            . "    image:\n"
            . "        imagine_options:\n"
            . "            format: webp\n"
            . "            webp_quality: 80\n";

        return ['configured' => $configured, 'snippet' => $snippet];
    }
}
