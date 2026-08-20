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
 * Detached signature checks against the pinned ring.
 *
 * Everything here fails CLOSED: a missing crypto extension, an unknown key id,
 * an algorithm outside the allowlist, malformed Base64, a wrong signature
 * length and a failed verification all return false. There is no "verification
 * unavailable, continue anyway" branch, and no caller may treat an exception as
 * success.
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly TrustAnchors $anchors,
    ) {
    }

    /**
     * Verifies a signature whose key is named by the packet (integrity
     * envelope, updater request).
     */
    public function verifyNamedKey(
        string $keyId,
        string $algorithm,
        string $purpose,
        string $message,
        string $signature,
        int $now,
    ): bool {
        if ($this->anchors->isEmpty() || !$this->supported()) {
            return false;
        }

        $anchor = $this->anchors->find($keyId, $algorithm, $purpose, $now);
        if ($anchor === null) {
            return false;
        }

        return $this->verifyWith($anchor, $message, $signature);
    }

    /**
     * Verifies a signature that names no key (the licence document) by trying
     * every currently usable key for that purpose.
     */
    public function verifyAnyKey(string $purpose, string $message, string $signature, int $now): bool
    {
        if ($this->anchors->isEmpty() || !$this->supported()) {
            return false;
        }

        foreach ($this->anchors->forPurpose($purpose, $now) as $anchor) {
            if ($this->verifyWith($anchor, $message, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safe internal category for diagnostics. Purely descriptive — it must
     * never be turned into a bypass by a caller.
     */
    public function unavailableCategory(): ?string
    {
        if (!$this->supported()) {
            return 'signature_backend_unavailable';
        }

        return $this->anchors->isEmpty() ? TrustAnchors::CATEGORY_EMPTY : null;
    }

    public function verifyWith(TrustAnchor $anchor, string $message, string $signature): bool
    {
        if (!$this->supported() || !$anchor->isStructurallyValid()) {
            return false;
        }

        $raw = base64_decode($signature, true);
        if ($raw === false || \strlen($raw) !== \SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($raw, $message, $anchor->publicKey);
        } catch (\Throwable) {
            return false;
        }
    }

    private function supported(): bool
    {
        return \function_exists('sodium_crypto_sign_verify_detached')
            && \defined('SODIUM_CRYPTO_SIGN_BYTES')
            && \defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES');
    }
}
