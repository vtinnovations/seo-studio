<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Security\LicenseManager;

/**
 * Daily re-verification against the licence server so a revoked or expired key
 * takes effect without the operator re-entering it. Only re-checks when the
 * cache is older than a day; a transient server error keeps the grace window.
 */
#[AsCronJob('daily')]
final class LicenseRefreshCron
{
    public function __construct(
        private readonly LicenseManager $licenseManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(): void
    {
        if (!$this->licenseManager->isCacheStale()) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        $domain = $request?->getHost() ?? '';

        $this->licenseManager->refresh($domain);
    }
}
