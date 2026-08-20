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

/**
 * The only destinations this bundle may talk to about provisioning.
 *
 * They are compile-time constants assembled from fragments. Nothing —
 * configuration, database content, request input, a DNS alias, a redirect or a
 * remote response — can point these somewhere else. Callers additionally pin
 * the expected host, so a tampered fragment cannot silently redirect traffic.
 */
final class Endpoint
{
    private const HOST_PARTS = ['www', '.', 'v-t', '.', 'one'];

    private const VERIFY_PATH = '/api/v1/verify';

    private const SIGNAL_PATH = '/rest/api/v1/log-envoke';

    private const UPDATER_PREFIX = '/rest/api/v1/';

    private const UPDATER_SUFFIX = '-license-updater';

    private function __construct()
    {
    }

    /** Activation and administrator refresh. */
    public static function verify(): string
    {
        return 'https://' . self::host() . self::VERIFY_PATH;
    }

    /** Both server-to-server signal shapes share this destination. */
    public static function signal(): string
    {
        return 'https://' . self::host() . self::SIGNAL_PATH;
    }

    /** The exact host the TLS peer must present. */
    public static function host(): string
    {
        return implode('', self::HOST_PARTS);
    }

    /**
     * The inbound path this installation exposes for vendor-initiated updates.
     * Public by necessity (it is server-to-server and cannot use a browser
     * session), and authenticated cryptographically instead.
     */
    public static function updaterPath(): string
    {
        return self::UPDATER_PREFIX . PackagePolicy::PROJECT_SLUG . self::UPDATER_SUFFIX;
    }
}
