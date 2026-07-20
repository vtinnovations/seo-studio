<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

final class AdapterRegistry
{
    /** @var array<string, AdapterInterface> */
    private array $adapters = [];

    /**
     * @param iterable<AdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->getId()] = $adapter;
        }
    }

    public function get(string $id): ?AdapterInterface
    {
        return $this->adapters[$id] ?? null;
    }
}
