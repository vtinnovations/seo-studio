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
 * Deterministic serialization used for detached signature checks
 * ("vt-one/canonical-json-v1").
 *
 * Rules, in this order:
 *   1. drop the top-level "signature" member (a signature cannot cover itself);
 *   2. sort object members recursively in ascending BYTE order;
 *   3. keep array order untouched — list position is meaningful and signed;
 *   4. UTF-8, no pretty printing, no escaped slashes, no escaped Unicode;
 *   5. scalar types survive exactly: false is not "false", null is not 0.
 *
 * Documents are therefore decoded with objects (not associative arrays) so that
 * an empty object {} can never be re-encoded as an empty array [] — the two
 * differ byte-for-byte and would break verification.
 */
final class CanonicalForm
{
    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /**
     * Decodes JSON preserving the object/array distinction.
     *
     * @throws \JsonException
     */
    public static function decode(string $json): mixed
    {
        return json_decode($json, false, 64, JSON_THROW_ON_ERROR);
    }

    /**
     * The exact bytes a detached signature is verified against.
     *
     * @throws \JsonException
     */
    public static function encode(mixed $value, bool $withoutSignature = true): string
    {
        if ($withoutSignature && $value instanceof \stdClass) {
            $value = clone $value;
            unset($value->signature);
        }

        return json_encode(self::sortRecursively($value), self::ENCODE_FLAGS);
    }

    /**
     * Object graph as a nested associative array, for validation code that
     * prefers arrays. Never used to rebuild signed bytes.
     *
     * @return array<string, mixed>
     */
    public static function toArray(\stdClass $value): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private static function sortRecursively(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);
            uksort($members, static fn (string $a, string $b): int => strcmp($a, $b));

            $sorted = new \stdClass();
            foreach ($members as $name => $member) {
                $sorted->{$name} = self::sortRecursively($member);
            }

            return $sorted;
        }

        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::sortRecursively($item), $value);
        }

        return $value;
    }
}
