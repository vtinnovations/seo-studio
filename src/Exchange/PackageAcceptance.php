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
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
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
 *
 * AUTHENTICITY AND ENTITLEMENT ARE SEPARATE QUESTIONS, and steps 1-5 answer
 * only the first. A packet can be perfectly, provably ours and still say that
 * this installation is no longer entitled to anything — that is what the vendor
 * sends when a customer moves their licence to another site. Such a packet is a
 * successful state UPDATE, not a verification failure, so step 6 runs a
 * different invariant set for it:
 *
 *   grant       — full tier, term, allowance, host-membership and
 *                 configured-intersection policy, exactly as before;
 *   withdrawal  — addressing only, because every additional condition on a
 *                 negative state is another way to refuse a genuine withdrawal,
 *                 and refusing one fails OPEN: the old installation keeps
 *                 running on the licence it no longer holds.
 *
 * A withdrawal is not short-circuited into "switch everything off" either. It
 * carries the licence's CURRENT authorised host set, so the ordinary
 * intersection with the configured inventory already gives the right answer for
 * single- and multi-host installations alike, and it does so whichever order
 * the vendor's two packets happen to arrive in.
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

    /**
     * The packet is addressed to a host this installation does not serve.
     *
     * Checked for withdrawals, where the target is absent from the signed host
     * set by construction and addressing is therefore the only thing left to
     * verify. See HostInventory::owns() for why this is load-bearing.
     */
    public const TARGET_NOT_SERVED = 'target_host_not_configured';

    /**
     * Authentic, but beaten by the rollback barrier: a licence version this
     * installation has already moved past. This is what a restored pre-transfer
     * backup looks like.
     */
    public const SUPERSEDED = 'superseded_by_barrier';

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
        self::TARGET_NOT_SERVED,
        self::SUPERSEDED,
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
        private readonly ProvisioningStore $store,
    ) {
    }

    /**
     * @param string              $payloadB64     "license_payload_b64" exactly
     *                                            as received
     * @param \stdClass            $envelope       the integrity envelope, still
     *                                            in the object form it was
     *                                            decoded in, so its signed
     *                                            bytes can be reproduced
     *                                            exactly
     * @param string              $expectedDomain the host this operation was
     *                                            performed for (the domain we
     *                                            sent, or the updater body
     *                                            domain)
     * @param ?ProvisioningRecord $stored         the currently stored record,
     *                                            or null. The whole record
     *                                            rather than its version
     *                                            alone: the ordering rule has
     *                                            to know which LICENCE the
     *                                            stored version belongs to,
     *                                            and whether what is stored is
     *                                            already a withdrawal
     * @param bool                $requireNewer   true for vendor-initiated
     *                                            pushes, which may not
     *                                            re-apply a grant at a version
     *                                            already held
     */
    public function accept(
        string $payloadB64,
        \stdClass $envelope,
        string $expectedDomain,
        ?ProvisioningRecord $stored,
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

        return $this->checkInvariants($record, $expectedDomain, $stored, $now, $requireNewer, true);
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
        // outbound request to compare against and no stored record to order
        // against — this IS the stored record.
        //
        // A stored WITHDRAWAL has to keep passing this on every read, or the
        // withdrawal would evaluate as "unverifiable" and the panel would tell
        // the administrator their files are corrupt rather than that their
        // licence has moved. The rollback barrier is still applied (inside
        // checkInvariants, to grants only), which is what makes restoring a
        // pre-transfer backup pointless.
        //
        // The addressing rule is NOT re-applied here, and that matters: it asks
        // "was this packet sent to us?", which is answered once, at intake. Ask
        // it again on every read and an installation that serves several of a
        // licence's hosts, released one, and then tidied that host out of its
        // site configuration would stop being able to read its own accepted
        // withdrawal — taking the hosts it legitimately KEPT offline.
        return $this->checkInvariants($record, $record->domain(), null, $now, false, false);
    }

    /**
     * @param bool $addressed true when this record has just ARRIVED and its
     *                        addressing is therefore still in question; false
     *                        when re-reading a record already accepted
     */
    private function checkInvariants(
        ProvisioningRecord $record,
        string $expectedDomain,
        ?ProvisioningRecord $stored,
        int $now,
        bool $requireNewer,
        bool $addressed,
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

        // An unrecognised status is refused outright rather than guessed at in
        // either direction. Guessing "entitled" would grant on a state we
        // cannot read; guessing "withdrawn" would darken a paying customer's
        // site over a field this release simply does not know yet.
        if (!$record->hasKnownStatus()) {
            return AcceptanceResult::rejected(self::STATUS);
        }

        if (!PackagePolicy::keyLooksWellFormed($record->licenceKey())) {
            return AcceptanceResult::rejected(self::DOCUMENT_MALFORMED);
        }

        // The operation host must be exactly the host we asked about, for a
        // withdrawal as much as for a grant: this is about ADDRESSING, and a
        // packet meant for someone else's installation is never ours to apply.
        if (!HostName::equals($record->domain(), $expectedDomain)) {
            return AcceptanceResult::rejected(self::DOMAIN_MISMATCH);
        }

        if ($record->version() < 1) {
            return AcceptanceResult::rejected(self::ROLLBACK);
        }

        $withdrawal = $record->isWithdrawal();

        // The signed set is validated as received. It is never sorted,
        // de-duplicated or widened locally — that would change what was signed.
        // Only a withdrawal may carry an empty set: that is the customer having
        // released their last domain.
        if (!HostName::isCanonicalSet($record->domains(), $withdrawal)) {
            return AcceptanceResult::rejected(self::HOST_SET_INVALID);
        }

        $verdict = $withdrawal
            ? $this->checkWithdrawal($record, $addressed)
            : $this->checkGrant($record);

        if ($verdict !== null) {
            return AcceptanceResult::rejected($verdict);
        }

        if ($stored !== null && !$this->movesForward($record, $stored, $requireNewer)) {
            return AcceptanceResult::rejected(self::ROLLBACK);
        }

        // The rollback BARRIER, and it is checked last on purpose.
        //
        // Where a stored record already refuses the candidate, that is the more
        // specific answer and stays ROLLBACK. What is left for the barrier is
        // the case it was actually built for: a record that nothing on disk
        // contradicts, because it IS what is on disk — a pre-transfer backup
        // the administrator has restored. Those bytes are genuinely
        // vendor-signed and verify perfectly, so the highest version this
        // installation has ever accepted is the only thing that can refuse them.
        //
        // Applied to grants ONLY. A withdrawal is written over the
        // authoritative pair, so a barrier applied to negative states as well
        // would supersede the stored withdrawal itself and take a multi-host
        // installation dark — and a withdrawal grants nothing, so there is no
        // rollback for it to be.
        //
        // Equal to the barrier is allowed: the vendor settles a domain transfer
        // at ONE revision, so a surviving host's re-issue carries the very
        // version the withdrawal raised the barrier to.
        if (!$withdrawal && $record->version() < $this->store->floor($record->licenceKey())) {
            return AcceptanceResult::rejected(self::SUPERSEDED);
        }

        unset($now);

        return AcceptanceResult::accepted($record);
    }

    /**
     * Everything a GRANT must satisfy before it may authorise this
     * installation. Null means "no objection".
     */
    private function checkGrant(ProvisioningRecord $record): ?string
    {
        // Tier model is Pro Only: nothing else authorises, and free_available
        // is not consulted at all.
        if (!PackagePolicy::acceptsPackage($record->package())) {
            return self::PACKAGE;
        }

        if (!\in_array($record->domain(), $record->domains(), true)) {
            return self::HOST_NOT_MEMBER;
        }

        // A positive allowance is required, but "count <= allowance" is
        // deliberately NOT enforced: the vendor lets existing bindings survive
        // an allowance reduction, and 9999 is an instance-bound report, never a
        // wildcard.
        if ($record->maxDomains() < 1) {
            return self::ALLOWANCE;
        }

        if ($this->inventory->intersect($record->domains()) === []) {
            return self::NO_INTERSECTION;
        }

        if (!$this->datesAreConsistent($record)) {
            return self::DATES;
        }

        return null;
    }

    /**
     * Everything a WITHDRAWAL must satisfy. Addressing, and nothing else.
     *
     * There is deliberately no tier, term, expiry, start-date, allowance,
     * host-membership or configured-intersection check here. Each of those
     * would be another way to refuse a genuine withdrawal, and a refused
     * withdrawal fails OPEN — the installation carries on with a licence it no
     * longer holds, which is the exact hole this whole path exists to close.
     *
     * The signed set the withdrawal carries is not inspected for membership
     * either: the entitlement evaluator intersects it with the configured
     * inventory in the ordinary way, which switches off an installation that
     * released its only host while leaving a multi-host installation running on
     * the hosts it kept.
     *
     * Addressing is asked only of an ARRIVING packet. Re-asking it of a stored
     * one turns a later, entirely legitimate configuration change — dropping
     * the released host from the site settings — into an outage on the hosts
     * the licence kept.
     */
    private function checkWithdrawal(ProvisioningRecord $record, bool $addressed): ?string
    {
        if (!$addressed) {
            return null;
        }

        return $this->inventory->owns($record->domain()) ? null : self::TARGET_NOT_SERVED;
    }

    /**
     * Whether the candidate is a legitimate successor to what is stored.
     *
     * license_version counts within ONE licence, so only a package for the SAME
     * key can be a rollback of what is stored. An administrator entering a
     * different key (a replacement purchase, a moved site, an upgraded
     * subscription) gets a freshly issued package whose own counter legitimately
     * starts lower than the outgoing licence's — that is a supersession, not a
     * downgrade. Cross-key comparison rejected genuine activations the vendor
     * had already approved, and a withdrawal makes "install a replacement
     * licence" the expected next step, so it matters more than ever here.
     *
     * Safe to scope this way because the rollback barrier is itself per-key.
     */
    private function movesForward(ProvisioningRecord $record, ProvisioningRecord $stored, bool $requireNewer): bool
    {
        if (!hash_equals($stored->licenceKey(), $record->licenceKey())) {
            return true;
        }

        if ($record->version() !== $stored->version()) {
            return $record->version() > $stored->version();
        }

        // Same version. A domain transfer is settled at one revision and stated
        // from both sides — the released host is sent `revoked` at N, every
        // surviving host is sent `valid` at N — and the two arrive in whichever
        // order the network decides. Insisting on a strictly higher number here
        // would refuse the second packet in exactly the case it exists for.
        //
        // Only a grant landing on top of a grant at an unchanged version is a
        // no-op, and only a push has to say so: an administrator pressing
        // "Update licence" must not see an error because the vendor legitimately
        // returned the version they already have.
        return !$requireNewer || $record->isWithdrawal() || $stored->isWithdrawal();
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
