<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Deterministic robots.txt audit for every published root page: which AI
 * crawlers may read the site, is a sitemap announced, and what to paste into
 * the root page's robots.txt field to fix it. No LLM involved.
 *
 * Fetching the site's OWN robots.txt is not gated by the egress switch — it
 * never leaves the user's domain.
 */
final class RobotsAuditor
{
    private const TIMEOUT_SECS = 10;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Runs the audit for all root pages and caches the result.
     *
     * @return list<array<string, mixed>>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->resolveDomains() as $domain) {
            $results[] = $this->auditDomain($domain['host'], $domain['scheme'], $domain['title']);
        }

        $this->config->set('robotsAuditResult', $results);
        $this->config->set('robotsAuditTime', time());

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCachedResults(): array
    {
        $cached = $this->config->get('robotsAuditResult', []);

        return \is_array($cached) ? array_values($cached) : [];
    }

    public function getCacheTime(): int
    {
        return (int) $this->config->get('robotsAuditTime', 0);
    }

    /**
     * Recommended robots.txt block for all crawlers currently blocked.
     *
     * @param array<string, mixed> $domainResult
     */
    public function buildFixSuggestion(array $domainResult): string
    {
        $blocked = [];
        foreach ((array) ($domainResult['crawlers'] ?? []) as $token => $verdict) {
            if (!(bool) ($verdict['allowed'] ?? true)) {
                $blocked[] = (string) $token;
            }
        }

        if ($blocked === []) {
            return '';
        }

        $lines = ['# KI-Crawler erlauben (AI SEO Studio)'];
        foreach ($blocked as $token) {
            $lines[] = 'User-agent: ' . $token;
        }
        $lines[] = 'Allow: /';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDomain(string $host, string $scheme, string $title): array
    {
        $url = $scheme . '://' . $host . '/robots.txt';

        $result = [
            'domain' => $host,
            'title' => $title,
            'url' => $url,
            'status' => 'ok',
            'error' => '',
            'crawlers' => [],
            'sitemaps' => [],
            'sitemapAnnounced' => false,
        ];

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT_SECS,
                'max_redirects' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $statusCode === 200 ? $response->getContent(false) : '';
        } catch (\Throwable $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();

            return $result;
        }

        if ($statusCode === 404 || trim($body) === '') {
            // No robots.txt = everything allowed, but no sitemap announcement.
            $result['status'] = 'missing';
            foreach (array_keys(AiCrawler::catalogue()) as $token) {
                $result['crawlers'][$token] = ['allowed' => true, 'explicit' => false];
            }

            return $result;
        }

        if ($statusCode !== 200) {
            $result['status'] = 'error';
            $result['error'] = 'HTTP ' . $statusCode;

            return $result;
        }

        $parser = RobotsParser::parse($body);

        foreach (array_keys(AiCrawler::catalogue()) as $token) {
            $result['crawlers'][$token] = [
                'allowed' => $parser->isAllowed($token),
                'explicit' => $parser->hasExplicitGroup($token),
            ];
        }

        $result['sitemaps'] = $parser->getSitemaps();
        $result['sitemapAnnounced'] = $parser->getSitemaps() !== [];

        return $result;
    }

    /**
     * @return list<array{host: string, scheme: string, title: string}>
     */
    private function resolveDomains(): array
    {
        $roots = $this->connection->fetchAllAssociative(
            "SELECT title, dns, useSSL FROM tl_page WHERE type = 'root' AND published = '1'",
        );

        $seen = [];
        $domains = [];

        foreach ($roots as $root) {
            $host = trim((string) $root['dns']);
            $scheme = ((string) ($root['useSSL'] ?? '1')) === '1' ? 'https' : 'http';

            if ($host === '') {
                // Root without fixed domain — fall back to the current request host.
                $request = $this->requestStack->getCurrentRequest();
                if ($request === null) {
                    continue;
                }
                $host = $request->getHost();
                $scheme = $request->getScheme();
            }

            if (isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;

            $domains[] = [
                'host' => $host,
                'scheme' => $scheme,
                'title' => (string) $root['title'],
            ];
        }

        return $domains;
    }
}
