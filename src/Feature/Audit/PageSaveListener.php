<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * On-save duplicate warning: identical pageTitle/description on another
 * published page → non-blocking backend notice.
 */
#[AsCallback(table: 'tl_page', target: 'config.onsubmit')]
final class PageSaveListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly DuplicateChecker $duplicateChecker,
    ) {
    }

    public function __invoke(DataContainer $dc): void
    {
        if (!$this->featureState->isEnabled('audit')) {
            return;
        }

        foreach ($this->duplicateChecker->forPage((int) $dc->id) as $warning) {
            Message::addInfo($warning);
        }
    }
}
