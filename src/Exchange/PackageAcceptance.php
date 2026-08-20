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

use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Core\Content\HostName;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;

/**
 * Decides whether a complete vendor package may become the active state.
 *
 * The order below is deliberate and must not be rearranged:
 *
 *   1. strict Base64 decode of the payload;
 *   2. verify the envelope SIGNATURE — before its MD5 is trusted at all;
 *   3. constant-time compare of the envelope MD5 against the MD5 of the exact
 *      decoded bytes (the tamper tripwire, never proof of authenticity);
 *   4. parse the decoded JSON for reading — never re-serialize it for step 3;
 *   5. verify the document signature over canonical fields;
 *   6. product, schema, host-set, tier, date and status invariants;
 *   7. rollback prevention against what is already stored.
 *
 * A caller may act on the result only when isAccepted() is true. Every other
 * path leaves the existing state untouched — a rejected package never erases a
 * previously valid licence.
 */
final class PackageAcceptance
{
    /** Fail-closed categories, also asserted by the test suite. */
    public const NOT_BASE64 = 'payload_not_base64';

    public const ENVELOPE_SIGNATURE = 'envelope_signature_invalid';

    public const DIGEST_MISMATCH = 'digest_mismatch';

    public const DOCUMENT_MALFORMED = 'document_malformed';

    public const DOCUMENT_SIGNATURE = 'document_signature_invalid';

    public const SCHEMA = 'schema_unsupported';

    public const PRODUCT = 'product_mismatch';

    public const DOMAIN_MISMATCH = 'domain_mismatch';

    public const HOST_SET_INVALID = 'host_set_not_canonical';

    public const HOST_NOT_MEMBER = 'host_not_in_signed_set';

    public const ALLOWANCE = 'allowance_invalid';

    public const NO_INTERSECTION = 'no_configured_intersection';

    public const DATES = 'dates_invalid';

    public const PACKAGE = 'package_not_accepted';

    public const STATUS = 'status_not_valid';

    public const ROLLBACK = 'version_rollback';

    public const ENVELOPE_MISMATCH = 'envelope_document_mismatch';

    private const MAX_PAYLOAD_BYTES = 65536;

    /**
     * Rejection categories that are only reachable AFTER every signature and
     * digest check has already passed — the record is genuinely vendor-signed,
     * it simply does not entitle this installation.
     *
     * The module-entry signal is allowed to transmit the key of such a record
     * (that is how a copied or lapsed installation becomes visible to the
     * vendor). Product/schema mismatches are excluded: that material belongs to
     * something else and must never be transmitted as ours.
     *
     * @var list<string>
     */
    private const AUTHENTIC_BUT_WITHHELD = [
        self::STATUS,
        self::PACKAGE,
        self::DOMAIN_MISMATCH,
        self::HOST_NOT_MEMBER,
        self::HOST_SET_INVALID,
        self::ALLOWANCE,
        self::NO_INTERSECTION,
        self::DATES,
    ];

    /**
     * True when the given rejection category still implies an authentic,
     * vendor-signed record.
     */
    public static function isVendorSigned(string $category): bool
    {
        return \in_array($category, self::AUTHENTIC_BUT_WITHHELD, true);
    }

    public function __construct(
        private readonly SignatureVerifier $verifier,
        private readonly TrustAnchors $anchors,
        private readonly HostInventory $inventory,
    ) {
    }

    /**
     * @param string    $payloadB64     "license_payload_b64" exactly as received
     * @param \stdClass $envelope       the integrity envelope, still in the
     *                                  object form it was decoded in, so its
     *                                  signed bytes can be reproduced exactly
     * @param string    $expectedDomain the host this operation was performed
     *                                  for (the domain we sent, or the updater
     *                                  body domain)
     * @param int|null  $storedVersion  currently stored version, or null
     * @param bool      $requireNewer   true for vendor-initiated pushes, which
     *                                  must strictly increase the version
     */
    public function accept(
        string $payloadB64,
        \stdClass $envelope,
        string $expectedDomain,
        ?int $storedVersion,
        int $now,
        bool $requireNewer = false,
    ): AcceptanceResult {
        if ($this->anchors->isEmpty()) {
            // No pinned key: we reached verification and cannot perform it.
            // Fail closed; never fall back to an unsigned or MD5-only decision.
            return AcceptanceResult::rejected(TrustAnchors::CATEGORY_EMPTY);
        }

        if ($payloadB64 === '' || \strlen($payloadB64) > self::MAX_PAYLOAD_BYTES) {
            return AcceptanceResult::rejected(self::NOT_BASE64);
        }

        $bytes = base64_decode($payloadB64, true);
        if ($bytes === false || $bytes === '') {
            return AcceptanceResult::rejected(self::NOT_BASE64);
        }

        $envelopeArray = CanonicalForm::toArray($envelope);

        $keyId = $envelopeArray['key_id'] ?? null;
        $algorithm = $envelopeArray['signature_algorithm'] ?? null;
        $envelopeSignature = $envelopeArray['signature'] ?? null;
        $digest = $envelopeArray['license_md5'] ?? null;

        if (!\is_string($keyId) || !\is_string($algorithm) || !\is_string($envelopeSignature) || !\is_string($digest)) {
            return AcceptanceResult::rejected(self::ENVELOPE_SIGNATURE);
        }

        if ($this->anchors->find($keyId, $algorithm, TrustAnchor::PURPOSE_ENVELOPE, $now) === null) {
            return AcceptanceResult::rejected(TrustAnchors::CATEGORY_UNKNOWN);
        }

        try {
            $envelopeMessage = CanonicalForm::encode($envelope);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::ENVELOPE_SIGNATURE);
        }

        if (!$this->verifier->verifyNamedKey($keyId, $algorithm, TrustAnchor::PURPOSE_ENVELOPE, $envelopeMessage, $envelopeSignature, $now)) {
            return AcceptanceResult::rejected(self::ENVELOPE_SIGNATURE);
        }

        // Only now may the envelope's digest be trusted, and it is compared
        // against the untouched decoded bytes — not against a re-encoding.
        if (preg_match('/^[a-f0-9]{32}$/', $digest) !== 1 || !hash_equals($digest, md5($bytes))) {
            return AcceptanceResult::rejected(self::DIGEST_MISMATCH);
        }

        $record = ProvisioningRecord::parse($bytes, $envelopeArray);
        if ($record === null) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        try {
            $document = CanonicalForm::decode($bytes);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        if (!$document instanceof \stdClass) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        try {
            $documentMessage = CanonicalForm::encode($document);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        // The document names no key, so every currently usable document-purpose
        // key is tried.
        if (!$this->verifier->verifyAnyKey(TrustAnchor::PURPOSE_DOCUMENT, $documentMessage, $record->signature(), $now)) {
            return AcceptanceResult::rejected(self::DOCUMENT_SIGNATURE);
        }

        return $this->checkInvariants($record, $expectedDomain, $storedVersion, $now, $requireNewer);
    }

    /**
     * Re-checks a record that is already on disk, through the SAME pipeline
     * used for a fresh package: envelope signature, exact-byte digest, document
     * signature, then every product/host/tier/date invariant.
     *
     * This runs on every evaluation, which is what makes hand-editing the
     * stored files pointless: altered bytes break the digest, an altered
     * envelope breaks its signature, and neither can be re-signed without the
     * vendor's private key.
     */
    public function checkStored(ProvisioningRecord $record, int $now): AcceptanceResult
    {
        if ($this->anchors->isEmpty()) {
            return AcceptanceResult::rejected(TrustAnchors::CATEGORY_EMPTY);
        }

        $envelope = $record->envelopeObject();

        try {
            $envelopeMessage = CanonicalForm::encode($envelope);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::ENVELOPE_SIGNATURE);
        }

        if ($this->anchors->find($record->envelopeKeyId(), $record->envelopeAlgorithm(), TrustAnchor::PURPOSE_ENVELOPE, $now) === null) {
            return AcceptanceResult::rejected(TrustAnchors::CATEGORY_UNKNOWN);
        }

        if (!$this->verifier->verifyNamedKey(
            $record->envelopeKeyId(),
            $record->envelopeAlgorithm(),
            TrustAnchor::PURPOSE_ENVELOPE,
            $envelopeMessage,
            $record->envelopeSignature(),
            $now,
        )) {
            return AcceptanceResult::rejected(self::ENVELOPE_SIGNATURE);
        }

        $digest = $record->envelopeDigest();
        if (preg_match('/^[a-f0-9]{32}$/', $digest) !== 1 || !hash_equals($digest, md5($record->bytes))) {
            return AcceptanceResult::rejected(self::DIGEST_MISMATCH);
        }

        try {
            $document = CanonicalForm::decode($record->bytes);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        if (!$document instanceof \stdClass) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        try {
            $documentMessage = CanonicalForm::encode($document);
        } catch (\JsonException) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        if (!$this->verifier->verifyAnyKey(TrustAnchor::PURPOSE_DOCUMENT, $documentMessage, $record->signature(), $now)) {
            return AcceptanceResult::rejected(self::DOCUMENT_SIGNATURE);
        }

        // The record's own operation host is the expectation here; there is no
        // outbound request to compare against and no version to supersede.
        return $this->checkInvariants($record, $record->domain(), null, $now, false);
    }

    private function checkInvariants(
        ProvisioningRecord $record,
        string $expectedDomain,
        ?int $storedVersion,
        int $now,
        bool $requireNewer,
    ): AcceptanceResult {
        if ($record->schemaVersion() !== ProvisioningRecord::SCHEMA_VERSION) {
            return AcceptanceResult::rejected(self::SCHEMA);
        }

        // The slug is the machine identifier and is matched byte-for-byte; the
        // project title is a catalogue display name and is matched on its
        // letters and digits (see PackagePolicy::projectMatches()).
        if (!PackagePolicy::projectMatches($record->project()) || $record->projectSlug() !== PackagePolicy::PROJECT_SLUG) {
            return AcceptanceResult::rejected(self::PRODUCT);
        }

        $envelope = $record->envelope();
        if (
            !PackagePolicy::projectMatches($envelope['project'] ?? null)
            || ($envelope['project_slug'] ?? null) !== PackagePolicy::PROJECT_SLUG
            || $record->envelopeVersion() !== $record->version()
        ) {
            return AcceptanceResult::rejected(self::ENVELOPE_MISMATCH);
        }

        if ($record->validationStatus() !== 'valid') {
            return AcceptanceResult::rejected(self::STATUS);
        }

        // Tier model is Pro Only: nothing else authorises, and free_available
        // is not consulted at all.
        if (!PackagePolicy::acceptsPackage($record->package())) {
            return AcceptanceResult::rejected(self::PACKAGE);
        }

        if (!PackagePolicy::keyLooksWellFormed($record->licenceKey())) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        // The operation host must be exactly the host we asked about.
        if (!HostName::equals($record->domain(), $expectedDomain)) {
            return AcceptanceResult::rejected(self::DOMAIN_MISMATCH);
        }

        // The signed set is validated as received. It is never sorted,
        // de-duplicated or widened locally — that would change what was signed.
        if (!HostName::isCanonicalSet($record->domains())) {
            return AcceptanceResult::rejected(self::HOST_SET_INVALID);
        }

        if (!\in_array($record->domain(), $record->domains(), true)) {
            return AcceptanceResult::rejected(self::HOST_NOT_MEMBER);
        }

        // A positive allowance is required, but "count <= allowance" is
        // deliberately NOT enforced: the vendor lets existing bindings survive
        // an allowance reduction, and 9999 is an instance-bound report, never a
        // wildcard.
        if ($record->maxDomains() < 1) {
            return AcceptanceResult::rejected(self::ALLOWANCE);
        }

        if ($this->inventory->intersect($record->domains()) === []) {
            return AcceptanceResult::rejected(self::NO_INTERSECTION);
        }

        if (!$this->datesAreConsistent($record)) {
            return AcceptanceResult::rejected(self::DATES);
        }

        if ($record->version() < 1) {
            return AcceptanceResult::rejected(self::ROLLBACK);
        }

        if ($storedVersion !== null) {
            if ($requireNewer && $record->version() <= $storedVersion) {
                return AcceptanceResult::rejected(self::ROLLBACK);
            }

            if (!$requireNewer && $record->version() < $storedVersion) {
                return AcceptanceResult::rejected(self::ROLLBACK);
            }
        }

        unset($now);

        return AcceptanceResult::accepted($record);
    }

    private function datesAreConsistent(ProvisioningRecord $record): bool
    {
        if ($record->issuedAt() < 1 || $record->startsAt() < 1 || $record->verifiedAt() < 1) {
            return false;
        }

        if ($record->isLifetime()) {
            // Lifetime means no expiry at all — a date here is contradictory.
            return $record->expiresAt() === null;
        }

        $expires = $record->expiresAt();

        // A time-limited package without an expiry is invalid by definition.
        return $expires !== null && $expires > $record->startsAt();
    }
}
