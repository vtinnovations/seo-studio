<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Security;

/**
 * One pinned public verification key.
 *
 * Public keys are not secrets, but their AUTHENTICITY is security critical:
 * an attacker who can substitute this material can sign anything. The bytes
 * are therefore fixed in code, self-checked against a published fingerprint,
 * and never loaded from configuration, the database or a remote response.
 */
final class TrustAnchor
{
    public const PURPOSE_DOCUMENT = 'document';

    public const PURPOSE_ENVELOPE = 'envelope';

    public const PURPOSE_REQUEST = 'request';

    public const ALGORITHM_ED25519 = 'ed25519';

    /**
     * @param list<string> $purposes
     */
    public function __construct(
        public readonly string $keyId,
        public readonly string $algorithm,
        public readonly string $publicKey,
        public readonly array $purposes,
        public readonly int $activeFrom = 0,
        public readonly ?int $retiresAt = null,
    ) {
    }

    /**
     * Structural sanity of the pinned material. A zero-length, truncated or
     * wrong-curve key must never take part in verification.
     */
    public function isStructurallyValid(): bool
    {
        if ($this->keyId === '' || $this->purposes === []) {
            return false;
        }

        if ($this->algorithm !== self::ALGORITHM_ED25519) {
            return false;
        }

        return \strlen($this->publicKey) === \SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
    }

    public function isUsableAt(int $timestamp): bool
    {
        if ($timestamp < $this->activeFrom) {
            return false;
        }

        return $this->retiresAt === null || $timestamp <= $this->retiresAt;
    }

    public function serves(string $purpose): bool
    {
        return \in_array($purpose, $this->purposes, true);
    }

    /**
     * Lowercase hexadecimal SHA-256 of the raw key bytes. Used only for the
     * build/readiness self-check — a fingerprint is never a substitute for the
     * key or for a signature.
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->publicKey);
    }
}
