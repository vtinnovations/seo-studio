<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Ai\Provider;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\SeoStudio\Core\Ai\AiClientInterface;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Security\SecretStore;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Shared plumbing for HTTP-based providers: the egress kill-switch, the
 * SecretStore-backed key lookup, and base-URL hardening. Concrete clients
 * implement only the request/response shaping.
 */
abstract class AbstractHttpClient implements AiClientInterface
{
    protected const SECRET_NAME = 'ai_api_key';

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly ConfigProvider $config,
        protected readonly SecretStore $secretStore,
    ) {
    }

    /**
     * Enforce the "no external calls" mode BEFORE any network work. Defence in
     * depth — also enforced one layer up, but a client must never be the leak.
     *
     * @throws AiException
     */
    protected function assertEgressAllowed(): void
    {
        if ($this->config->externalCallsBlocked()) {
            throw new AiException(
                'Externe Aufrufe sind deaktiviert ("Keine externen Aufrufe"). Bitte erst in den Einstellungen freigeben.',
                AiExceptionKind::EgressBlocked,
            );
        }
    }

    /**
     * @throws AiException
     */
    protected function requireApiKey(): string
    {
        $key = $this->secretStore->get(self::SECRET_NAME);
        if ($key === null || $key === '') {
            throw new AiException(
                'Kein API-Schluessel hinterlegt. Bitte in den Einstellungen setzen.',
                AiExceptionKind::Auth,
            );
        }

        return $key;
    }

    /**
     * Validates an admin-configured base URL: http/https only, with a host.
     * Defends against accidental file://, gopher://, etc. (SSRF surface,
     * the project guidelines §3.4). Returns the trimmed base, or the provider default when
     * empty.
     *
     * @throws AiException
     */
    protected function resolveBaseUrl(string $default): string
    {
        $configured = trim((string) $this->config->get('aiBaseUrl', ''));
        if ($configured === '') {
            return $default;
        }

        $scheme = strtolower((string) parse_url($configured, PHP_URL_SCHEME));
        $host = (string) parse_url($configured, PHP_URL_HOST);

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new AiException(
                'Ungueltige Basis-URL. Nur http(s) mit Hostname erlaubt.',
                AiExceptionKind::BadRequest,
            );
        }

        return rtrim($configured, '/');
    }
}
