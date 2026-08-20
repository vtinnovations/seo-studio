<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Job;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use VTinnovations\SeoStudio\Core\Content\SiteInventory;
use VTinnovations\SeoStudio\Exchange\EntryClaim;
use VTinnovations\SeoStudio\Exchange\SignalTransport;

/**
 * Dispatches both server-to-server signals AFTER the response has been sent, so
 * neither one can slow a page down or change what an administrator sees.
 *
 * "Relevant application invocation" is defined here as: a Contao BACKEND main
 * request that is not an XHR. That keeps one signal per administrator page view
 * instead of one per asset, poll or inline panel call, and keeps public frontend
 * traffic out of it entirely.
 *
 * The module-entry event is a separate shape with its own once-per-session
 * claim; this listener only delivers what EntryClaim has already claimed.
 */
#[AsEventListener(event: KernelEvents::TERMINATE, priority: -128)]
final class UsageSignalListener
{
    private bool $signalled = false;

    public function __construct(
        private readonly SiteInventory $inventory,
        private readonly SignalTransport $transport,
        private readonly EntryClaim $entryClaim,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        // Always give a claimed module-entry signal its chance, even when the
        // per-invocation event is not applicable to this request.
        $this->entryClaim->flush();

        if ($this->signalled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get('_scope') !== 'backend' || $request->isXmlHttpRequest()) {
            return;
        }

        $domain = $this->inventory->outboundHost();
        if ($domain === null) {
            return;
        }

        $this->signalled = true;

        $this->transport->invocation($domain);
    }
}
