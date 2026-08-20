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

use Psr\Log\LoggerInterface;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Manual "is my AI config working?" probe for the settings screen. Sends a
 * single, minimal prompt to the configured provider and reports a UI-safe
 * verdict.
 *
 * Guard rails (the project guidelines §3.2):
 *   - Honours the egress kill-switch: with external calls blocked it returns a
 *     clear, non-failing message and never hits the network.
 *   - Data minimisation: a one-word probe, no site content.
 *   - Never logs or returns the key or the raw response body.
 *
 * Phase 1 scope: this is the ONLY production code path allowed to call out, and
 * only on explicit admin action (POST + token).
 */
final class ConnectionTester
{
    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly ConfigProvider $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function test(): TestResult
    {
        if ($this->config->externalCallsBlocked()) {
            return TestResult::failure(
                Translations::text('error.egressBlockedTest'),
            );
        }

        try {
            $client = $this->factory->default();
        } catch (\RuntimeException $e) {
            return TestResult::failure('Konfigurationsfehler: ' . $e->getMessage());
        }

        $bundle = new PromptBundle(
            systemPrompt: 'You are a connection test. Reply with the single word: OK.',
            userPrompt: 'ping',
            model: $this->modelFor($client),
            temperature: 0.0,
            maxTokens: 8,
            purpose: 'connection_test',
        );

        try {
            $response = $client->complete($bundle);
        } catch (AiException $e) {
            // Log kind only, never the key/body (the message is already scrubbed).
            $this->logger->warning('seo-studio connection test failed', ['kind' => $e->kind->value]);

            return TestResult::failure('Verbindung fehlgeschlagen: ' . $e->getMessage());
        }

        return TestResult::success(sprintf(
            'Verbindung ok (%s / %s, %d ms).',
            $response->provider,
            $response->model,
            $response->durationMs,
        ));
    }

    private function modelFor(AiClientInterface $client): string
    {
        $model = trim((string) $this->config->get('aiModel', ''));

        return $model !== '' ? $model : $client->defaultModel();
    }
}
