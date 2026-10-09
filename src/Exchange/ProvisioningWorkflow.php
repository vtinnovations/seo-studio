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

use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;

/**
 * The three administrator operations and the vendor-initiated one, each with
 * exactly one implementation:
 *
 *   activate  — verify a newly entered key, then persist
 *   refresh   — re-verify with the stored key and current version
 *   remove    — drop the authoritative state
 *   apply     — accept an authenticated vendor push
 *
 * All four run inside the store's exclusive transaction, so a compare-and-set
 * against the currently stored version cannot race another request or node.
 *
 * Failure semantics are the same everywhere and are deliberate: a transport
 * error, malformed answer, failed signature or vendor denial leaves the previous
 * state exactly as it was. Nothing in this class can invent, extend or downgrade
 * a licence locally.
 */
final class ProvisioningWorkflow
{
    public const OK = 'ok';

    public const NO_CONFIGURED_HOST = 'no_configured_host';

    public const KEY_MALFORMED = 'key_malformed';

    public const NOTHING_STORED = 'nothing_stored';

    /** The lease has not run out yet, or the throttle is still holding. */
    public const NOT_DUE = 'not_due';

    /** Withdrawn: the unattended re-check deliberately stops here. */
    public const WITHDRAWN = 'withdrawn';

    /** No configured host is authorised, so re-binding is not ours to decide. */
    public const NO_HOST_MATCH = 'no_host_match';

    /**
     * Shortest interval between two unattended attempts. Separate from the
     * lease: it stops an unreachable vendor turning an hourly cron into a
     * per-request retry storm.
     */
    private const ATTEMPT_THROTTLE = 3600;

    public function __construct(
        private readonly VerifyClient $client,
        private readonly PackageAcceptance $acceptance,
        private readonly ProvisioningStore $store,
        private readonly HostInventory $inventory,
        private readonly EntitlementEvaluator $entitlement,
        private readonly Journal $journal,
        private readonly OperationLog $log,
    ) {
    }

    /**
     * Administrator entered a key. Returns OK or a safe category.
     */
    public function activate(string $licenceKey, ?int $now = null): string
    {
        $now ??= time();
        $key = trim($licenceKey);

        if (!PackagePolicy::keyLooksWellFormed($key)) {
            return self::KEY_MALFORMED;
        }

        $domain = $this->inventory->outboundHost();
        if ($domain === null) {
            return self::NO_CONFIGURED_HOST;
        }

        return $this->store->transaction(function () use ($key, $domain, $now): string {
            $outcome = $this->client->exchange(VerifyClient::ACTION_ACTIVATE, $key, $domain, null, $now);

            return $this->consume($outcome, $domain, $now, false, 'activate');
        });
    }

    /**
     * Administrator refresh. Uses the stored key unless a replacement is given,
     * and always sends the stored version so the vendor can decide what to
     * return.
     */
    public function refresh(?string $replacementKey, ?int $now = null): string
    {
        $now ??= time();

        $domain = $this->inventory->outboundHost();
        if ($domain === null) {
            return self::NO_CONFIGURED_HOST;
        }

        return $this->store->transaction(function () use ($replacementKey, $domain, $now): string {
            $stored = $this->store->load();
            $key = $replacementKey !== null && trim($replacementKey) !== ''
                ? trim($replacementKey)
                : $stored?->licenceKey();

            if ($key === null) {
                return self::NOTHING_STORED;
            }

            if (!PackagePolicy::keyLooksWellFormed($key)) {
                return self::KEY_MALFORMED;
            }

            $outcome = $this->client->exchange(
                VerifyClient::ACTION_REFRESH,
                $key,
                $domain,
                $stored?->version() ?? 0,
                $now,
            );

            return $this->consume($outcome, $domain, $now, false, 'refresh');
        });
    }

    /**
     * The UNATTENDED re-check, driven by the hourly cron.
     *
     * This is the half that actually enforces a domain transfer. A vendor push
     * is best-effort and cannot be relied on: the installation that lost the
     * licence may be offline, behind changed DNS, firewalled, or deliberately
     * dropping our requests, and dropping them is precisely what somebody
     * exploiting the transfer hole does. Entitlement is therefore a lease, and
     * this is what renews it — an installation that is never reachable runs out
     * on its own, with nobody having to click anything.
     *
     * Three conditions stop it pulling, and each one is a hole if it is missing:
     *
     *   withdrawn  — the vendor's /api/v1/verify binds the domain it is asked
     *                about on a REFRESH, not only on an activation. A released
     *                host that kept polling would therefore re-bind itself and
     *                silently re-license, defeating the revocation it had
     *                already accepted. Only a vendor push or an administrator
     *                pressing "Update licence" may bring it back.
     *   superseded — the same hole reached through a restored backup: the stored
     *                record verifies, but the rollback barrier has moved past
     *                it, so it is a withdrawn state wearing an older file's
     *                clothes.
     *   no host    — when no configured host is authorised any more, asking the
     *                vendor about whatever host this installation now serves
     *                would make re-binding an unattended decision. That is an
     *                administrator's call.
     */
    public function refreshIfDue(?int $now = null): string
    {
        $now ??= time();

        // A future value counts as "never attempted", so forward-dating the
        // marker cannot switch the re-check off — it only brings it forward.
        $attempted = $this->store->lastAttempt();
        if ($attempted > 0 && $attempted <= $now && $now - $attempted < self::ATTEMPT_THROTTLE) {
            return self::NOT_DUE;
        }

        $record = $this->store->load();
        if ($record === null) {
            return self::NOTHING_STORED;
        }

        if ($record->isWithdrawal()) {
            return self::WITHDRAWN;
        }

        if ($record->version() < $this->store->floor($record->licenceKey())) {
            return self::WITHDRAWN;
        }

        if ($this->inventory->matchedHost($record->domains()) === null) {
            return self::NO_HOST_MATCH;
        }

        if ($now < PackagePolicy::recheckDueAt($record)) {
            return self::NOT_DUE;
        }

        // Stamped BEFORE the call, so a vendor endpoint that hangs cannot turn
        // the throttle into a no-op.
        $this->store->markAttempt($now);

        return $this->refresh(null, $now);
    }

    /**
     * Administrator removal: the instance returns to plain Contao behaviour at
     * once, and every cached decision is dropped.
     */
    public function remove(): string
    {
        if (!$this->store->exists()) {
            $this->entitlement->invalidate();

            return self::NOTHING_STORED;
        }

        $this->store->remove();
        $this->entitlement->invalidate();

        $this->log->info('SEO Studio provisioning removed', [
            'operation' => 'remove',
            'result' => self::OK,
        ]);

        return self::OK;
    }

    /**
     * Applies an already authenticated vendor push. The caller has verified the
     * HTTP request signature and claimed the request id; this method performs
     * the package-level verification and the atomic swap.
     *
     * @return array{status: string, version: int}
     */
    public function apply(InboundRequest $request, int $now): array
    {
        \assert($request->body instanceof \stdClass);

        $payload = $request->body->license_payload_b64 ?? null;
        $envelope = $request->body->integrity ?? null;

        if (!\is_string($payload) || $payload === '' || !$envelope instanceof \stdClass) {
            return ['status' => 'package_malformed', 'version' => 0];
        }

        return $this->store->transaction(function () use ($payload, $envelope, $request, $now): array {
            $stored = $this->store->load();

            $result = $this->acceptance->accept(
                $payload,
                $envelope,
                $request->domain,
                $stored,
                $now,
                true,
            );

            if (!$result->isAccepted()) {
                $this->log->warning('SEO Studio provisioning push rejected', [
                    'operation' => 'push',
                    'request_id' => $request->requestId,
                    'result' => $result->category,
                    'domain' => $request->domain,
                ]);

                return ['status' => $result->category, 'version' => 0];
            }

            $record = $result->record;
            \assert($record instanceof ProvisioningRecord);

            if (!$this->persist($record, $now)) {
                return ['status' => 'activation_failed', 'version' => 0];
            }

            $this->log->info('SEO Studio provisioning push applied', [
                'operation' => 'push',
                'request_id' => $request->requestId,
                'result' => self::OK,
                'license_version' => $record->version(),
                'domain' => $request->domain,
            ]);

            $this->journal->prune($now);

            return ['status' => self::OK, 'version' => $record->version()];
        });
    }

    private function consume(
        VerifyOutcome $outcome,
        string $domain,
        int $now,
        bool $requireNewer,
        string $operation,
    ): string {
        if (!$outcome->hasPackage()) {
            // Nothing usable arrived. Whatever was valid stays valid.
            return $outcome->category;
        }

        \assert($outcome->payloadB64 !== null && $outcome->envelope !== null);

        // The ordering rule is applied against the whole stored record, which
        // is what lets it scope the comparison to the same licence key (see
        // PackageAcceptance::movesForward()).
        $result = $this->acceptance->accept(
            $outcome->payloadB64,
            $outcome->envelope,
            $domain,
            $this->store->load(),
            $now,
            $requireNewer,
        );

        if (!$result->isAccepted()) {
            $this->log->warning('SEO Studio provisioning package rejected', [
                'operation' => $operation,
                'request_id' => $outcome->requestId ?? '',
                'result' => $result->category,
                'domain' => $domain,
            ]);

            return $result->category;
        }

        $record = $result->record;
        \assert($record instanceof ProvisioningRecord);

        if (!$this->persist($record, $now)) {
            return 'activation_failed';
        }

        $this->log->info('SEO Studio provisioning stored', [
            'operation' => $operation,
            'request_id' => $outcome->requestId ?? '',
            'result' => self::OK,
            'license_version' => $record->version(),
            'domain' => $domain,
        ]);

        return self::OK;
    }

    /**
     * Atomic swap plus a full post-write re-verification: the bytes that end up
     * on disk must pass exactly the same pipeline again, otherwise the previous
     * pair is rolled back. A successful swap then raises the rollback barrier.
     */
    private function persist(ProvisioningRecord $record, int $now): bool
    {
        $activated = $this->store->activate(
            $record->bytes,
            $record->envelope(),
            fn (ProvisioningRecord $candidate): bool => $this->acceptance->checkStored($candidate, $now)->isAccepted(),
        );

        if ($activated) {
            // Raise the rollback barrier for this licence, withdrawals
            // included — a withdrawal's version is precisely the number that
            // has to make the older, still perfectly signed licence file on the
            // administrator's backup drive worthless.
            $this->store->raise($record->licenceKey(), $record->version());
        }

        // Cached entitlement must never survive a state change, in either
        // direction.
        $this->entitlement->invalidate();

        return $activated;
    }
}
