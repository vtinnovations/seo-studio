<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Ai;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Security\SecretStore;

/**
 * Shared fixtures for provider-client tests: a ConfigProvider over an in-memory
 * settings row set and a SecretStore in a throwaway project directory.
 */
trait ProviderTestKit
{
    private ?string $projectDir = null;

    protected function tearDown(): void
    {
        if ($this->projectDir !== null && is_dir($this->projectDir)) {
            $this->removeTree($this->projectDir);
        }

        $this->projectDir = null;
    }

    /**
     * @param array<string, mixed> $settings stored config values (JSON-encoded as in tl_seo_studio_config)
     */
    private function config(array $settings = []): ConfigProvider
    {
        $rows = array_map(
            static fn (mixed $value): string => (string) json_encode($value),
            $settings,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($rows);

        return new ConfigProvider($connection);
    }

    private function secrets(?string $apiKey = 'test-api-key'): SecretStore
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-ai-test-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);

        $store = new SecretStore($this->projectDir);
        if ($apiKey !== null) {
            $store->set('ai_api_key', $apiKey);
        }

        return $store;
    }

    private function removeTree(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($dir);
    }
}
