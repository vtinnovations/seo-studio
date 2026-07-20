<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Images;

use Doctrine\DBAL\Connection;

/**
 * One-click fix: create (or reuse) a responsive tl_image_size preset and
 * assign it to all image/picture elements WITHOUT a size assignment.
 * Elements with an explicit assignment are never touched.
 */
final class ImageSizeWizard
{
    private const PRESET_NAME = 'SEO Studio Responsiv';

    public function __construct(
        private readonly Connection $connection,
        private readonly ImageAuditor $auditor,
    ) {
    }

    /**
     * @return array{presetId: int, assigned: int}
     */
    public function apply(): array
    {
        $presetId = $this->ensurePreset();

        $unassigned = $this->auditor->findUnassignedElements();

        $assigned = 0;
        $sizeValue = serialize(['', '', (string) $presetId]);

        foreach ($unassigned as $element) {
            $this->connection->update(
                'tl_content',
                ['size' => $sizeValue, 'tstamp' => time()],
                ['id' => $element['id']],
            );
            ++$assigned;
        }

        return ['presetId' => $presetId, 'assigned' => $assigned];
    }

    private function ensurePreset(): int
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM tl_image_size WHERE name = ?',
            [self::PRESET_NAME],
        );

        if ($existing !== false) {
            return (int) $existing;
        }

        $themeId = $this->connection->fetchOne('SELECT id FROM tl_theme ORDER BY id LIMIT 1');
        if ($themeId === false) {
            throw new \RuntimeException('Kein Theme vorhanden — Bildgrößen brauchen ein Theme.');
        }

        $this->connection->insert('tl_image_size', [
            'pid' => (int) $themeId,
            'tstamp' => time(),
            'name' => self::PRESET_NAME,
            'width' => 1200,
            'resizeMode' => 'proportional',
            'densities' => '1x, 2x',
            'lazyLoading' => '1',
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
