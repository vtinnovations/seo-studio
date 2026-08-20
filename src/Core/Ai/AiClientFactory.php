<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Ai;

use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Resolves a provider name to its AiClientInterface implementation. Clients are
 * injected as a tagged iterator; adding a provider is "implement + tag", no
 * factory change.
 */
final class AiClientFactory
{
    /** @var array<string, AiClientInterface> */
    private array $byProvider = [];

    /**
     * @param iterable<AiClientInterface> $clients
     */
    public function __construct(
        iterable $clients,
        private readonly ConfigProvider $config,
    ) {
        foreach ($clients as $client) {
            $this->byProvider[$client->getProviderName()] = $client;
        }
    }

    public function default(): AiClientInterface
    {
        $name = (string) $this->config->get('aiProvider', 'openai');

        // A previously configured provider may no longer be registered (e.g. an
        // install that had a removed provider selected). Fall back gracefully to
        // OpenAI, or the first available client, instead of throwing.
        if (!isset($this->byProvider[$name])) {
            $name = isset($this->byProvider['openai']) ? 'openai' : ((string) array_key_first($this->byProvider));
        }

        return $this->get($name);
    }

    public function get(string $providerName): AiClientInterface
    {
        if (!isset($this->byProvider[$providerName])) {
            throw new \RuntimeException(sprintf(
                'No AI client registered for provider "%s". Available: %s',
                $providerName,
                implode(', ', array_keys($this->byProvider)) ?: '(none)',
            ));
        }

        return $this->byProvider[$providerName];
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        return array_keys($this->byProvider);
    }
}
