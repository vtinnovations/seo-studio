<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

/**
 * Canonical host representation.
 *
 * Every host comparison in the bundle (site inventory, entitlement, outbound
 * signals) goes through normalize() so that two spellings of the SAME host
 * compare equal while two DIFFERENT hosts never do.
 *
 * Representation-only normalization — it must NEVER widen scope:
 *   - lowercase ASCII,
 *   - remove exactly one trailing dot ("example.com." -> "example.com"),
 *   - remove a valid trailing port (":8443"),
 *   - convert an IDN to its ASCII/Punycode form ("beispiel.de" stays,
 *     "münchen.de" -> "xn--mnchen-3ya.de").
 *
 * Explicitly NOT done, because each of these would grant hosts that were
 * never authorised: stripping "www.", reducing to a registrable domain,
 * suffix/substring matching, wildcard expansion, CNAME/redirect resolution.
 * "example.com", "www.example.com", "shop.example.com" and
 * "admin.shop.example.com" are four distinct identities.
 *
 * IP policy (documented, deliberate): bare IPv4/IPv6 literals are rejected.
 * A host identity must be a DNS name; an installation reached only by IP
 * cannot be authorised.
 */
final class HostName
{
    private const MAX_LENGTH = 253;

    private const MAX_LABEL_LENGTH = 63;

    /**
     * Returns the canonical form of a host, or null when the input is not a
     * usable host name. Callers treat null as "no host" — never as a wildcard.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        // A host is a host — not a URL, not a mailbox, not a pattern. Anything
        // carrying userinfo, a path, a query or whitespace is refused outright
        // instead of being "cleaned up" into something authorised.
        if (preg_match('/[\s\/\\\\@?#%]/', $value) === 1) {
            return null;
        }

        // Bracketed IPv6 ("[::1]:8080") and bare IPv6 are both refused.
        if (str_contains($value, '[') || str_contains($value, ']')) {
            return null;
        }

        $value = self::stripPort($value);
        if ($value === null) {
            return null;
        }

        // Exactly one trailing dot is removed (the DNS root label).
        if (str_ends_with($value, '.')) {
            $value = substr($value, 0, -1);
        }

        if ($value === '' || str_ends_with($value, '.')) {
            return null;
        }

        $value = self::toAscii($value);
        if ($value === null) {
            return null;
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        foreach (explode('.', $value) as $label) {
            if ($label === '' || \strlen($label) > self::MAX_LABEL_LENGTH) {
                return null;
            }

            if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Exact equality of two canonical hosts. No suffix, parent or alias logic.
     */
    public static function equals(?string $a, ?string $b): bool
    {
        $left = self::normalize($a);
        $right = self::normalize($b);

        return $left !== null && $right !== null && $left === $right;
    }

    /**
     * Normalizes a list of hosts, dropping unusable entries, and returns it
     * unique and sorted. Used for the LOCAL inventory only — a signed host set
     * received from outside is validated as-is and never re-sorted (see
     * PackageAcceptance).
     *
     * @param iterable<mixed> $hosts
     *
     * @return list<string>
     */
    public static function canonicalSet(iterable $hosts): array
    {
        $out = [];

        foreach ($hosts as $host) {
            if (!\is_string($host)) {
                continue;
            }

            $normalized = self::normalize($host);
            if ($normalized !== null) {
                $out[$normalized] = true;
            }
        }

        $list = array_keys($out);
        sort($list, SORT_STRING);

        return $list;
    }

    /**
     * True when the list is already exactly canonical: every entry equals its
     * own normalization, entries are unique and in ascending byte order. A
     * signed list that fails this is rejected rather than repaired, because
     * repairing it locally would change what was signed.
     *
     * @param list<mixed> $hosts
     */
    public static function isCanonicalSet(array $hosts): bool
    {
        if ($hosts === []) {
            return false;
        }

        $previous = null;

        foreach ($hosts as $host) {
            if (!\is_string($host) || self::normalize($host) !== $host) {
                return false;
            }

            if ($previous !== null && strcmp($host, $previous) <= 0) {
                return false;
            }

            $previous = $host;
        }

        return true;
    }

    private static function stripPort(string $value): ?string
    {
        $position = strrpos($value, ':');
        if ($position === false) {
            return $value;
        }

        $host = substr($value, 0, $position);
        $port = substr($value, $position + 1);

        if ($host === '' || preg_match('/^\d{1,5}$/', $port) !== 1) {
            return null;
        }

        $number = (int) $port;

        return $number >= 1 && $number <= 65535 ? $host : null;
    }

    /**
     * ASCII/Punycode form. Without the intl extension a non-ASCII host is
     * refused (fail closed) rather than guessed at.
     */
    private static function toAscii(string $value): ?string
    {
        if (preg_match('/^[\x21-\x7e]+$/', $value) === 1) {
            return strtolower($value);
        }

        if (!\function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return \is_string($ascii) && $ascii !== '' ? strtolower($ascii) : null;
    }
}
