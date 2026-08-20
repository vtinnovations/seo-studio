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

use Contao\System;

/**
 * The single way this bundle produces user-facing text.
 *
 * Every label, hint, message and error comes from
 * $GLOBALS['TL_LANG']['SEO_STUDIO'] — that is, from
 * contao/languages/<lang>/default.php. There are deliberately NO inline
 * fallbacks: a literal in the source would be one language nobody can
 * translate, and it would silently win over the language files.
 *
 * A missing key therefore renders as "[dash.root]" instead of text. That is on
 * purpose: it is language-neutral, immediately visible in review, and the
 * release guard fails the build when a referenced key is absent from EN or DE.
 *
 * Static by design: Contao instantiates widgets, DCA callbacks and BE_MOD
 * modules with `new`, so an injected service would not be reachable from half
 * the call sites.
 */
final class Translations
{
    private const ROOT = 'SEO_STUDIO';

    private function __construct()
    {
    }

    /**
     * The translation for a dotted key, e.g. "licence.activated".
     *
     * Extra arguments are applied with sprintf, so counted strings stay
     * translatable ("%d of %d pages" vs "%d von %d Seiten").
     */
    public static function text(string $key, mixed ...$parameters): string
    {
        $value = self::lookup($key);

        if ($value === null) {
            return '[' . $key . ']';
        }

        if ($parameters === []) {
            return $value;
        }

        try {
            return sprintf($value, ...$parameters);
        } catch (\Throwable) {
            // A translator supplied the wrong placeholders; show the text
            // rather than breaking the screen.
            return $value;
        }
    }

    public static function has(string $key): bool
    {
        return self::lookup($key) !== null;
    }

    private static function lookup(string $key): ?string
    {
        self::ensureLoaded();

        /** @var mixed $value */
        $value = $GLOBALS['TL_LANG'][self::ROOT] ?? null;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($value) || !isset($value[$segment])) {
                return null;
            }

            $value = $value[$segment];
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The backend loads default.php on its own; frontend listeners, the cron
     * and CLI commands do not, so make sure the file is there before reading.
     */
    private static function ensureLoaded(): void
    {
        if (isset($GLOBALS['TL_LANG'][self::ROOT]) && \is_array($GLOBALS['TL_LANG'][self::ROOT])) {
            return;
        }

        try {
            System::loadLanguageFile('default');
        } catch (\Throwable) {
            // No Contao framework available (unit tests): the caller gets the
            // bracketed key, never an invented sentence.
        }
    }
}
