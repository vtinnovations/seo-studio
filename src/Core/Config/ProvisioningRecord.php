<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;

/**
 * The stored provisioning record: the EXACT bytes issued by the vendor plus the
 * signed envelope that seals them.
 *
 * The byte string is authoritative. It is never re-serialized, pretty-printed
 * or normalized, because both the MD5 tripwire and the document signature are
 * calculated over these exact bytes — a single changed space invalidates both.
 * The decoded array exists only for reading values.
 *
 * parse() enforces the SHAPE (presence and PHP type of every field) so that
 * everything downstream can rely on typed accessors. It deliberately does not
 * judge trust: signatures, digests, host sets, package policy and version
 * ordering are decided in the acceptance pipeline.
 */
final class ProvisioningRecord
{
    public const SCHEMA_VERSION = 2;

    /** The installation is entitled to what this record describes. */
    public const STATUS_VALID = 'valid';

    /**
     * The licence was moved away from this host: an authoritative WITHDRAWAL,
     * not a failed verification. The record still carries the licence's real
     * package, term and key — a revocation says where the licence may run, not
     * whether the customer still owns it.
     */
    public const STATUS_REVOKED = 'revoked';

    /** The licence's term ended. Licence-wide, unlike a revocation. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Every status this client understands. An unrecognised value is refused
     * rather than guessed at in either direction: guessing "entitled" would
     * grant on a state we cannot read, and guessing "withdrawn" would take a
     * paying customer's site down on a field we simply do not know yet.
     *
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_VALID, self::STATUS_REVOKED, self::STATUS_EXPIRED];

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $envelope
     */
    private function __construct(
        public readonly string $bytes,
        private readonly array $document,
        private readonly array $envelope,
    ) {
    }

    /**
     * @param array<string, mixed> $envelope
     */
    public static function parse(string $bytes, array $envelope): ?self
    {
        if ($bytes === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded) || !self::documentShapeIsValid($decoded)) {
            return null;
        }

        if (!self::envelopeShapeIsValid($envelope)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return new self($bytes, $decoded, $envelope);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->document;
    }

    /**
     * @return array<string, mixed>
     */
    public function envelope(): array
    {
        return $this->envelope;
    }

    /**
     * The envelope as an object, for reproducing its canonical signed bytes.
     *
     * The protocol defines the envelope as a flat set of scalars, so the
     * array/object round trip is lossless here. (Only a nested EMPTY object
     * could differ, and the envelope schema has none — parse() enforces that
     * every documented member is a scalar of the expected type.)
     */
    public function envelopeObject(): \stdClass
    {
        $object = new \stdClass();

        foreach ($this->envelope as $name => $value) {
            $object->{$name} = $value;
        }

        return $object;
    }

    public function schemaVersion(): int
    {
        return (int) $this->document['schema_version'];
    }

    public function project(): string
    {
        return (string) $this->document['project'];
    }

    public function projectSlug(): string
    {
        return (string) $this->document['project_slug'];
    }

    /**
     * The full licence key. Only ever used server-side: activation/refresh
     * packets and the once-per-session module-entry signal. It must never reach
     * a template, a JSON response, a log line or a session marker.
     */
    public function licenceKey(): string
    {
        return (string) $this->document['license_key'];
    }

    public function domain(): string
    {
        return (string) $this->document['license_domain'];
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        /** @var list<string> $domains */
        $domains = $this->document['license_domains'];

        return $domains;
    }

    public function maxDomains(): int
    {
        return (int) $this->document['license_max_domains'];
    }

    public function package(): string
    {
        return (string) $this->document['license_package'];
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        /** @var list<string> $features */
        $features = $this->document['license_features'];

        return $features;
    }

    public function version(): int
    {
        return (int) $this->document['license_version'];
    }

    public function issuedAt(): int
    {
        return (int) $this->document['license_issued_at'];
    }

    public function startsAt(): int
    {
        return (int) $this->document['license_starts_at'];
    }

    public function expiresAt(): ?int
    {
        $value = $this->document['license_expires_at'];

        return $value === null ? null : (int) $value;
    }

    public function isLifetime(): bool
    {
        return (bool) $this->document['license_lifetime'];
    }

    public function verifiedAt(): int
    {
        return (int) $this->document['license_verified_at'];
    }

    public function freeAvailable(): bool
    {
        return (bool) $this->document['free_available'];
    }

    public function validationStatus(): string
    {
        return (string) $this->document['validation_status'];
    }

    /** True when this record grants entitlement rather than withdrawing it. */
    public function isPositive(): bool
    {
        return $this->validationStatus() === self::STATUS_VALID;
    }

    public function isRevoked(): bool
    {
        return $this->validationStatus() === self::STATUS_REVOKED;
    }

    /**
     * An authoritative negative state of either kind.
     *
     * Both are authentic vendor statements and both are applied as state
     * changes; they differ only in scope. A revocation is about ONE host (the
     * signed host set says which hosts remain), an expiry is about the whole
     * licence.
     */
    public function isWithdrawal(): bool
    {
        return $this->isRevoked() || $this->validationStatus() === self::STATUS_EXPIRED;
    }

    public function hasKnownStatus(): bool
    {
        return \in_array($this->validationStatus(), self::STATUSES, true);
    }

    /**
     * Signed re-check deadline, when the vendor states one.
     *
     * Optional in the schema: no released server emits it yet, so a record
     * without it falls back to a locally fixed lease anchored on the signed
     * license_verified_at (see PackagePolicy). Reading it from the signed
     * document rather than from configuration is the whole point — the guard
     * exists against the site owner, who can edit anything unsigned.
     */
    public function refreshRequiredAt(): ?int
    {
        return $this->optionalTimestamp('license_refresh_required_at');
    }

    /** Signed hard cutoff after which stale entitlement must fail closed. */
    public function graceUntil(): ?int
    {
        return $this->optionalTimestamp('license_grace_until');
    }

    /**
     * The timestamp the lease is measured from: when the vendor last confirmed
     * this licence, or failing that when it was issued.
     */
    public function leaseAnchor(): int
    {
        $verified = $this->verifiedAt();

        return $verified > 0 ? $verified : $this->issuedAt();
    }

    private function optionalTimestamp(string $field): ?int
    {
        $value = $this->document[$field] ?? null;

        return \is_int($value) && $value > 0 ? $value : null;
    }

    public function signature(): string
    {
        return (string) $this->document['signature'];
    }

    public function envelopeKeyId(): string
    {
        return (string) $this->envelope['key_id'];
    }

    public function envelopeAlgorithm(): string
    {
        return (string) $this->envelope['signature_algorithm'];
    }

    public function envelopeDigest(): string
    {
        return (string) $this->envelope['license_md5'];
    }

    public function envelopeSignature(): string
    {
        return (string) $this->envelope['signature'];
    }

    public function envelopeVersion(): int
    {
        return (int) $this->envelope['license_version'];
    }

    public function startsAfter(int $timestamp): bool
    {
        return $timestamp < $this->startsAt();
    }

    public function hasExpiredAt(int $timestamp): bool
    {
        if ($this->isLifetime()) {
            return false;
        }

        $expires = $this->expiresAt();

        return $expires !== null && $timestamp > $expires;
    }

    /**
     * Whether both new multi-host fields are present. A record predating them
     * is rollback material only: it must be refreshed, never expanded locally.
     */
    public function hasHostSet(): bool
    {
        return \array_key_exists('license_domains', $this->document)
            && \array_key_exists('license_max_domains', $this->document);
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function documentShapeIsValid(array $document): bool
    {
        $integers = [
            'schema_version',
            'license_max_domains',
            'license_version',
            'license_issued_at',
            'license_starts_at',
            'license_verified_at',
        ];

        foreach ($integers as $field) {
            if (!\is_int($document[$field] ?? null)) {
                return false;
            }
        }

        $strings = [
            'project',
            'project_slug',
            'license_key',
            'license_domain',
            'license_package',
            'signature',
            'validation_status',
        ];

        foreach ($strings as $field) {
            $value = $document[$field] ?? null;
            if (!\is_string($value) || $value === '') {
                return false;
            }
        }

        if (!\is_bool($document['license_lifetime'] ?? null) || !\is_bool($document['free_available'] ?? null)) {
            return false;
        }

        if (!\array_key_exists('license_expires_at', $document)) {
            return false;
        }

        $expires = $document['license_expires_at'];
        if ($expires !== null && !\is_int($expires)) {
            return false;
        }

        // An EMPTY license_domains is a legal shape, not a malformed document:
        // it is what the vendor signs when the last domain slot was released.
        // Whether an empty set may AUTHORISE anything is a policy question and
        // is answered in the acceptance pipeline, where a positive state still
        // requires a non-empty canonical set.
        return self::isStringList($document['license_domains'] ?? null, true)
            && self::isStringList($document['license_features'] ?? null, true);
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private static function envelopeShapeIsValid(array $envelope): bool
    {
        foreach (['project', 'project_slug', 'license_md5', 'key_id', 'signature_algorithm', 'signature'] as $field) {
            $value = $envelope[$field] ?? null;
            if (!\is_string($value) || $value === '') {
                return false;
            }
        }

        return \is_int($envelope['license_version'] ?? null) && \is_int($envelope['generated_at'] ?? null);
    }

    private static function isStringList(mixed $value, bool $allowEmpty = false): bool
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return false;
        }

        if ($value === []) {
            return $allowEmpty;
        }

        foreach ($value as $item) {
            if (!\is_string($item) || $item === '') {
                return false;
            }
        }

        return true;
    }
}
