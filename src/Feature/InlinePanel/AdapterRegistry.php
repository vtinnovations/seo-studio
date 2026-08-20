<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
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
