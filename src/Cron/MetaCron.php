<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Job\JobLock;
use VTinnovations\SeoStudio\Core\Security\BudgetExceededException;
use VTinnovations\SeoStudio\Feature\Meta\MetaGenerator;

/**
 * Bulk meta generation: fills EMPTY pageTitle/description on published
 * regular pages, batch-wise (Plesk-friendly: bounded work per run, DB lock
 * against overlapping runs). Opt-in via "metaCronEnabled" — bulk LLM spend
 * must never be a surprise.
 */
#[AsCronJob('hourly')]
final class MetaCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly FeatureState $featureState,
        private readonly MetaGenerator $generator,
        private readonly JobLock $lock,
        private readonly LoggerInterface $logger,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function __invoke(): void
    {
        // Background work is gated independently of any request-scoped check:
        // the cron runs without a session, so it must decide for itself. The
        // evaluation uses the persisted verified host, not a Host header.
        if (!$this->entitlement->isLicensed()) {
            return;
        }

        if (!$this->featureState->isEnabled('meta') || !(bool) $this->config->get('metaCronEnabled', false)) {
            return;
        }

        if (!$this->lock->acquire('meta_cron', 1800)) {
            return;
        }

        try {
            $batchSize = min(50, max(1, (int) $this->config->get('cronBatchSize', 5)));

            $pageIds = $this->connection->fetchFirstColumn(
                "SELECT id FROM tl_page
                 WHERE type = 'regular' AND published = '1'
                   AND (pageTitle = '' OR description = '' OR description IS NULL)
                 ORDER BY id
                 LIMIT " . $batchSize,
            );

            foreach ($pageIds as $pageId) {
                try {
                    $this->generator->fillEmpty((int) $pageId);
                } catch (BudgetExceededException $e) {
                    $this->logger->warning('SEO Studio meta cron stopped: ' . $e->getMessage());

                    return;
                } catch (\Throwable $e) {
                    // One broken page must not stall the whole queue.
                    $this->logger->error(sprintf('SEO Studio meta cron: page %d failed: %s', $pageId, $e->getMessage()));
                }
            }
        } finally {
            $this->lock->release('meta_cron');
        }
    }
}
