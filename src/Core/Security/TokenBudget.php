<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Security;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Monthly token budget with hard stop.
 *
 * Usage counters live as rows in tl_seo_studio_config (name "tokenUsage:YYYY-MM",
 * plain integer as JSON). assertBudgetAvailable() is called BEFORE every LLM
 * call; recordUsage() after. At >=80% the settings UI shows a warning, at 100%
 * every further call throws BudgetExceededException.
 *
 * budget = 0 means unlimited.
 */
final class TokenBudget
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
    ) {
    }

    /**
     * @throws BudgetExceededException
     */
    public function assertBudgetAvailable(): void
    {
        $budget = $this->getMonthlyBudget();
        if ($budget <= 0) {
            return;
        }

        if ($this->getUsageThisMonth() >= $budget) {
            throw new BudgetExceededException(sprintf(
                'Monatliches Token-Budget (%s) ist aufgebraucht. KI-Funktionen sind bis Monatsende pausiert.',
                number_format($budget, 0, ',', '.'),
            ));
        }
    }

    public function recordUsage(int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }

        $key = $this->currentKey();

        // Atomic increment; value column is JSON but a bare int is valid JSON.
        $updated = $this->connection->executeStatement(
            'UPDATE tl_seo_studio_config SET tstamp = ?, value = CAST(CAST(value AS SIGNED) + ? AS CHAR) WHERE name = ?',
            [time(), $tokens, $key],
        );

        if ($updated === 0) {
            $this->connection->insert('tl_seo_studio_config', [
                'tstamp' => time(),
                'name' => $key,
                'value' => (string) $tokens,
            ]);
        }
    }

    public function getUsageThisMonth(): int
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT value FROM tl_seo_studio_config WHERE name = ?',
                [$this->currentKey()],
            );
        } catch (\Throwable) {
            return 0;
        }

        return \is_string($value) ? (int) $value : 0;
    }

    public function getMonthlyBudget(): int
    {
        return (int) $this->config->get('monthlyTokenBudget', 0);
    }

    /** Ratio 0.0–1.0+, or null when unlimited. */
    public function getUsageRatio(): ?float
    {
        $budget = $this->getMonthlyBudget();
        if ($budget <= 0) {
            return null;
        }

        return $this->getUsageThisMonth() / $budget;
    }

    public function isWarning(): bool
    {
        $ratio = $this->getUsageRatio();

        return $ratio !== null && $ratio >= 0.8;
    }

    private function currentKey(): string
    {
        return 'tokenUsage:' . date('Y-m');
    }
}
