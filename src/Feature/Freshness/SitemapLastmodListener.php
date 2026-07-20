<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Freshness;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\SitemapEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Adds <lastmod> to the Contao sitemap. Contao emits only <loc>; AI/search
 * crawlers use lastmod for recrawl priority and freshness signals.
 *
 * Mapping strategy (DB-only, no routing round-trip): the URL path's last
 * segment (minus suffix) is a page alias or a news/event alias. lastmod =
 * max(page tstamp, newest published article of the page) or the news tstamp.
 */
#[AsEventListener(ContaoCoreEvents::SITEMAP, priority: -100)]
final class SitemapLastmodListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(SitemapEvent $event): void
    {
        if (!$this->featureState->isEnabled('freshness')) {
            return;
        }

        $document = $event->getDocument();
        $urlElements = $document->getElementsByTagName('url');
        if ($urlElements->length === 0) {
            return;
        }

        $pageMap = $this->buildPageMap();
        $newsMap = $this->buildNewsMap();

        /** @var \DOMElement $urlElement */
        foreach ($urlElements as $urlElement) {
            $locList = $urlElement->getElementsByTagName('loc');
            if ($locList->length === 0 || $urlElement->getElementsByTagName('lastmod')->length > 0) {
                continue;
            }

            $alias = $this->aliasFromUrl((string) $locList->item(0)?->textContent);

            $lastmod = $newsMap[$alias] ?? $pageMap[$alias] ?? 0;
            if ($lastmod <= 0) {
                continue;
            }

            $urlElement->appendChild($document->createElementNS(
                'http://www.sitemaps.org/schemas/sitemap/0.9',
                'lastmod',
                date('Y-m-d', $lastmod),
            ));
        }
    }

    /**
     * @return array<string, int> alias => lastmod
     */
    private function buildPageMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT p.id, p.alias, p.type, GREATEST(p.tstamp, COALESCE((
                 SELECT MAX(a.tstamp) FROM tl_article a WHERE a.pid = p.id AND a.published = '1'
             ), 0)) AS lastmod
             FROM tl_page p
             WHERE p.published = '1'",
        );

        $map = [];
        foreach ($rows as $row) {
            $lastmod = (int) $row['lastmod'];

            // URLs may be alias-based (/ueber-uns.html) or id-based (/107).
            $map[(string) $row['id']] = $lastmod;

            $alias = (string) $row['alias'];
            if ($alias !== '') {
                $map[$this->lastSegment($alias)] = $lastmod;
            }

            // "/" (empty path) = the site's index page.
            if ($alias === 'index' || $alias === '/') {
                $map[''] = $lastmod;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int> alias => lastmod
     */
    private function buildNewsMap(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT alias, GREATEST(tstamp, date) AS lastmod FROM tl_news WHERE published = '1' AND alias != ''",
            );
        } catch (\Throwable) {
            return []; // news bundle not installed
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['alias']] = (int) $row['lastmod'];
        }

        return $map;
    }

    private function aliasFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = (string) preg_replace('/\.[a-z0-9]+$/i', '', $path); // strip .html suffix

        return $this->lastSegment(trim($path, '/'));
    }

    private function lastSegment(string $alias): string
    {
        $pos = strrpos($alias, '/');

        return $pos === false ? $alias : substr($alias, $pos + 1);
    }
}
