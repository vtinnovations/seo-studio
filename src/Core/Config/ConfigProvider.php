<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;

use Doctrine\DBAL\Connection;

/**
 * Cached key/value configuration backed by tl_seo_studio_config.
 *
 * Values are JSON-encoded in the DB so booleans, ints, arrays and strings
 * round-trip losslessly. The full table is loaded ONCE per request and cached
 * in-process, so calling get() from loadDataContainer hooks is cheap.
 *
 * The table may not exist yet (fresh install before contao:migrate) — all()
 * degrades to defaults instead of throwing, so the backend never white-screens
 * mid-installation.
 *
 * SECRETS DO NOT LIVE HERE — API keys go through SecretStore (encrypted at
 * rest with a separate key file under var/seostudio/).
 */
final class ConfigProvider
{
    /** @var array<string, mixed>|null In-process cache, cleared on write. */
    private ?array $cache = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $values = $this->getDefaults();

        try {
            /** @var array<string, string> $rows */
            $rows = $this->connection->fetchAllKeyValue('SELECT name, value FROM tl_seo_studio_config');

            foreach ($rows as $name => $value) {
                $decoded = json_decode((string) $value, true);
                $values[$name] = $decoded ?? $value;
            }
        } catch (\Throwable) {
            // Table absent (pre-migration) — run on defaults.
        }

        return $this->cache = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException(sprintf('Config value for "%s" is not JSON-encodable.', $key));
        }

        $updated = $this->connection->update(
            'tl_seo_studio_config',
            ['tstamp' => time(), 'value' => $encoded],
            ['name' => $key],
        );

        if ($updated === 0) {
            $this->connection->insert('tl_seo_studio_config', [
                'tstamp' => time(),
                'name' => $key,
                'value' => $encoded,
            ]);
        }

        $this->cache = null;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Egress kill-switch. When true, NO external/AI call may leave the server.
     * Every egress path must consult this (the AI clients enforce it too).
     */
    public function externalCallsBlocked(): bool
    {
        return (bool) $this->get('noExternalCalls', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            // AI
            'aiProvider' => 'anthropic',
            'aiModel' => '',
            'aiBaseUrl' => '',
            'noExternalCalls' => false,
            'monthlyTokenBudget' => 0, // 0 = unlimited

            // Behavior
            'writeMode' => 'propose', // propose | fillEmpty (bulk cron is ALWAYS fill-empty-only)
            'cronBatchSize' => 5,
            'languageOverride' => '',
            'metaCronEnabled' => false, // bulk LLM spend is opt-in

            // Feature toggles
            'featureMeta' => true,
            'featureAudit' => true,
            'featureSchema' => true,
            'featureLlmsTxt' => true,
            'featureFreshness' => true,
            'featureFaq' => true,
            'featureOptimize' => true,
            'featureInlineAltText' => true,
            'featureInlineLinkText' => true,
            'featureImages' => true,
            'featureGeoScore' => true,
            'featureGlossary' => true,
            'featurePageScore' => true,
            'featureSocial' => true,

            // Schema.org data
            'schemaOrgName' => '',
            'schemaOrgLogo' => '',
            'schemaOrgSameAs' => [],
            'schemaEnableOrganization' => true,
            'schemaEnableBreadcrumb' => true,
            'schemaEnableArticle' => true,

            // llms.txt
            'llmsTxtAiSummary' => false,
            'llmsTxtSummaryText' => '',
        ];
    }
}
