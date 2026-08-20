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

            return $this->consume($outcome, $key, $domain, $now, false, 'activate');
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

            return $this->consume($outcome, $key, $domain, $now, false, 'refresh');
        });
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
                $stored?->version(),
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
        string $licenceKey,
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

        $stored = $this->store->load();

        // license_version counts within ONE licence, so only a package for the
        // SAME key can be a rollback of what is stored. An administrator
        // entering a different key (a replacement purchase, a moved site, an
        // upgraded subscription) gets a freshly issued package whose own
        // counter legitimately starts lower than the outgoing licence's — that
        // is a supersession, not a downgrade, and comparing the two versions
        // rejected genuine activations the vendor had already approved.
        $supersedesStored = $stored !== null && hash_equals($stored->licenceKey(), $licenceKey);

        $result = $this->acceptance->accept(
            $outcome->payloadB64,
            $outcome->envelope,
            $domain,
            $supersedesStored ? $stored->version() : null,
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
     * pair is rolled back.
     */
    private function persist(ProvisioningRecord $record, int $now): bool
    {
        $activated = $this->store->activate(
            $record->bytes,
            $record->envelope(),
            fn (ProvisioningRecord $candidate): bool => $this->acceptance->checkStored($candidate, $now)->isAccepted(),
        );

        // Cached entitlement must never survive a state change, in either
        // direction.
        $this->entitlement->invalidate();

        return $activated;
    }
}
