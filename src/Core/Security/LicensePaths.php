<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Security;

/**
 * Resolves the bundle's licence-state file under var/seostudio (the same
 * scratch tree the SecretStore uses). Holds the cached licence + trial state;
 * never commit this tree.
 */
final class LicensePaths
{
    private readonly string $scratchDir;

    public function __construct(string $projectDir)
    {
        $this->scratchDir = rtrim($projectDir, '/\\') . '/var/seostudio';
    }

    public function licenseFile(): string
    {
        return $this->ensure($this->scratchDir) . '/license.json';
    }

    private function ensure(string $dir): string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s".', $dir));
        }

        return $dir;
    }
}
