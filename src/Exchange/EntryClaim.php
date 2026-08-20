<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Exchange;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;

/**
 * The once-per-backend-session module-entry signal.
 *
 * Contract, in order:
 *   1. an authenticated Contao BACKEND user must exist (never frontend, CLI,
 *      cron or a queue worker);
 *   2. the claim marker lives in the backend session bag, so it disappears on
 *      logout, session invalidation, expiry and at the next login — it is not a
 *      database flag, not browser storage and not a process static;
 *   3. the marker is set BEFORE delivery, so a timeout or an unreachable
 *      endpoint cannot cause a second attempt in the same session;
 *   4. delivery is deferred to kernel.terminate, after the response has been
 *      sent, and never influences rendering;
 *   5. the key comes only from a cryptographically authenticated record.
 *
 * Parallel tabs are serialized by PHP's own session locking, so exactly one
 * request wins the claim. The marker holds a bare "1": never the key, never the
 * domain, never a session identifier or digest of one.
 *
 * The pending payload is kept in this service's memory for the remainder of the
 * request. It is deliberately NOT put into request attributes, the session or a
 * log — the profiler must not be able to display a licence key.
 */
final class EntryClaim
{
    private const BAG = 'contao_backend';

    private const MARKER_PREFIX = 'seoStudioEntrySignal.';

    /** @var array{domain: string, key: string}|null */
    private ?array $pending = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TokenChecker $tokenChecker,
        private readonly EntitlementEvaluator $entitlement,
        private readonly SignalTransport $transport,
    ) {
    }

    /**
     * Called when the protected backend surface is actually entered. Safe to
     * call repeatedly: only the first authenticated call per session claims.
     */
    public function claim(): void
    {
        if ($this->pending !== null) {
            return;
        }

        if (!$this->tokenChecker->hasBackendUser()) {
            return;
        }

        $bag = $this->backendBag();
        if ($bag === null) {
            return;
        }

        $marker = self::MARKER_PREFIX . PackagePolicy::PROJECT_SLUG;
        if ($bag->get($marker) !== null) {
            return;
        }

        $record = $this->entitlement->authenticatedRecord();
        if ($record === null) {
            // No authentic record: nothing may be sent and nothing is claimed,
            // so a later activation in the same session can still signal.
            return;
        }

        $state = $this->entitlement->current();
        $domain = $state->matchedHost ?? $record->domain();
        $key = $record->licenceKey();

        if ($domain === '' || $key === '') {
            return;
        }

        // Claim first, deliver afterwards.
        $bag->set($marker, 1);

        $this->pending = ['domain' => $domain, 'key' => $key];
    }

    /**
     * Delivers a claimed signal. Called from the terminate listener so the
     * administrator never waits for it.
     */
    public function flush(): void
    {
        if ($this->pending === null) {
            return;
        }

        $payload = $this->pending;
        $this->pending = null;

        $this->transport->moduleEntry($payload['domain'], $payload['key']);
    }

    public function hasPending(): bool
    {
        return $this->pending !== null;
    }

    /**
     * Dropped together with the licence itself, so a later activation in the
     * same session may signal again.
     */
    public function resetClaim(): void
    {
        $bag = $this->backendBag();
        $bag?->remove(self::MARKER_PREFIX . PackagePolicy::PROJECT_SLUG);
        $this->pending = null;
    }

    private function backendBag(): ?AttributeBagInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        try {
            $session = $request->getSession();
            if (!$session->isStarted() && !$this->tokenChecker->hasBackendUser()) {
                return null;
            }

            $bag = $session->getBag(self::BAG);
        } catch (\Throwable) {
            // No backend bag registered (frontend scope, CLI, tests).
            return null;
        }

        return $bag instanceof AttributeBagInterface ? $bag : null;
    }
}
