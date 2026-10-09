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
use Psr\Log\LoggerInterface;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;

/**
 * Renews the licence lease without anybody having to ask.
 *
 * This exists because the two other ways the stored state can change are both
 * unreliable in the one case that matters. A vendor push needs the installation
 * to be reachable, and an installation running on a licence that has been moved
 * away from it may be offline, behind changed DNS, firewalled, or deliberately
 * dropping our requests. An administrator pressing "Update licence" needs an
 * administrator who wants to know — and somebody exploiting a domain transfer
 * is the last person who will ever press it.
 *
 * So the re-check runs on its own. ProvisioningWorkflow::refreshIfDue() owns
 * every decision about whether anything should actually happen: the signed
 * lease deadline, the attempt throttle, and the three conditions under which
 * pulling would re-bind a host that has just been released. This class only
 * provides the heartbeat.
 *
 * Deliberately NOT gated on the current entitlement, unlike the bundle's other
 * cron: an unlicensed-because-stale installation is exactly the one that needs
 * to talk to the vendor, and gating on isLicensed() would leave it stuck.
 */
#[AsCronJob('hourly')]
final class ProvisioningUpkeepCron
{
    public function __construct(
        private readonly ProvisioningWorkflow $workflow,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        try {
            $result = $this->workflow->refreshIfDue();
        } catch (\Throwable $e) {
            // A failed re-check must never break the cron run for every other
            // job. The lease is what enforces the outcome, not this call
            // succeeding, so failing quietly here still fails closed later.
            $this->logger->warning('SEO Studio provisioning upkeep failed: ' . $e->getMessage());

            return;
        }

        // Only the outcomes worth a line. The routine "nothing to do" answers
        // would be hourly noise. The category is a safe internal label — it
        // carries no key, host, payload, digest or signature.
        if (!\in_array($result, [ProvisioningWorkflow::NOT_DUE, ProvisioningWorkflow::NOTHING_STORED], true)) {
            $this->logger->info('SEO Studio provisioning upkeep', [
                'operation' => 'upkeep',
                'result' => $result,
            ]);
        }
    }
}
