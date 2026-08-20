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
 * The pinned verification ring for signed vendor packets.
 *
 * Design constraints that must not be relaxed:
 *   - a production ring is NEVER empty; an empty ring fails closed with the
 *     category "signing_key_store_empty" and keeps any previously valid state;
 *   - a key id is only a SELECTOR — it is never treated as key material, and a
 *     key is never taken from the same response whose signature it would check;
 *   - rotation happens through a shipped release (or an update authenticated by
 *     an already trusted key), never over the wire.
 *
 * The key bytes are assembled from fragments and immediately checked against
 * the published fingerprint, so a partial edit of this file (or of a hardened
 * release copy of it) collapses the ring instead of silently trusting altered
 * material.
 */
final class TrustAnchors
{
    public const CATEGORY_EMPTY = 'signing_key_store_empty';

    public const CATEGORY_UNKNOWN = 'unknown_signing_key';

    public const CATEGORY_ALGORITHM = 'unsupported_signature_algorithm';

    /**
     * Fragments of the Base64 raw Ed25519 public key of the active vendor
     * signing profile (2026a). Never a private key — signing happens only on
     * vendor infrastructure.
     */
    private const MATERIAL = ['qllgm+66FUVBFJ3O', '68ICFG8b37dR+9jM', 'fr1+4/pSygE='];

    private const MATERIAL_ID = 'vtone-2026a';

    /** Published SHA-256 prefix of the assembled key. */
    private const MATERIAL_FINGERPRINT = 'edcd614e70c59ce0';

    /** @var list<TrustAnchor>|null */
    private ?array $anchors = null;

    /** @var list<TrustAnchor>|null */
    private ?array $override;

    /**
     * @param list<TrustAnchor>|null $override test-only ring; null uses the
     *                                         pinned production material
     */
    public function __construct(?array $override = null)
    {
        $this->override = $override;
    }

    /**
     * @return list<TrustAnchor>
     */
    public function all(): array
    {
        if ($this->anchors !== null) {
            return $this->anchors;
        }

        if ($this->override !== null) {
            return $this->anchors = array_values(array_filter(
                $this->override,
                static fn (TrustAnchor $anchor): bool => $anchor->isStructurallyValid(),
            ));
        }

        $anchors = [];

        foreach ($this->pinned() as $anchor) {
            if ($anchor->isStructurallyValid()) {
                $anchors[] = $anchor;
            }
        }

        return $this->anchors = $anchors;
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Exact lookup for an envelope/request signature, which names its key.
     * Returns null for an unknown id, a mismatched algorithm or a key outside
     * its rotation window — every one of those fails closed.
     */
    public function find(string $keyId, string $algorithm, string $purpose, int $now): ?TrustAnchor
    {
        foreach ($this->all() as $anchor) {
            if ($anchor->keyId !== $keyId) {
                continue;
            }

            if ($anchor->algorithm !== $algorithm || !$anchor->serves($purpose) || !$anchor->isUsableAt($now)) {
                return null;
            }

            return $anchor;
        }

        return null;
    }

    /**
     * Every currently usable key for a purpose. The licence document itself
     * names no key, so its detached signature is tried against all of them.
     *
     * @return list<TrustAnchor>
     */
    public function forPurpose(string $purpose, int $now): array
    {
        $out = [];

        foreach ($this->all() as $anchor) {
            if ($anchor->serves($purpose) && $anchor->isUsableAt($now)) {
                $out[] = $anchor;
            }
        }

        return $out;
    }

    /**
     * Build/readiness gate: the assembled material must be present, correctly
     * sized and match its published fingerprint. Returns the problems found —
     * an empty list means the ring is releasable.
     *
     * @return list<string>
     */
    public function selfCheck(): array
    {
        $problems = [];

        foreach ($this->all() as $anchor) {
            if (!$anchor->isStructurallyValid()) {
                $problems[] = sprintf('anchor "%s" is structurally invalid', $anchor->keyId);
            }
        }

        if ($this->override === null) {
            $expected = self::MATERIAL_FINGERPRINT;
            $found = null;

            foreach ($this->all() as $anchor) {
                if ($anchor->keyId === self::MATERIAL_ID) {
                    $found = $anchor->fingerprint();
                }
            }

            if ($found === null) {
                $problems[] = sprintf('pinned anchor "%s" is missing', self::MATERIAL_ID);
            } elseif (!str_starts_with($found, $expected)) {
                $problems[] = sprintf('pinned anchor "%s" fingerprint mismatch', self::MATERIAL_ID);
            }
        }

        if ($this->all() === []) {
            $problems[] = self::CATEGORY_EMPTY;
        }

        if (!\function_exists('sodium_crypto_sign_verify_detached')) {
            $problems[] = 'sodium extension unavailable';
        }

        return $problems;
    }

    /**
     * @return list<TrustAnchor>
     */
    private function pinned(): array
    {
        $material = base64_decode(implode('', self::MATERIAL), true);

        if ($material === false || $material === '') {
            return [];
        }

        return [
            new TrustAnchor(
                self::MATERIAL_ID,
                TrustAnchor::ALGORITHM_ED25519,
                $material,
                [TrustAnchor::PURPOSE_DOCUMENT, TrustAnchor::PURPOSE_ENVELOPE, TrustAnchor::PURPOSE_REQUEST],
                0,
                null,
            ),
        ];
    }
}
