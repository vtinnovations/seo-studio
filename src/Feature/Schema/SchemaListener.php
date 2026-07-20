<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Schema;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Input;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Deterministic JSON-LD injection:
 *
 *   - Organization (name/logo/sameAs from settings, fallback root title)
 *   - BreadcrumbList from the page trail
 *   - WebPage with dateModified (freshness feature) = max(page, articles)
 *   - Article for news detail pages (auto_item -> tl_news alias)
 *
 * Injection happens on kernel.response, straight before </head> — NOT via
 * TL_HEAD, because custom fe_page templates (page builders etc.) drop
 * TL_HEAD and the schema would silently vanish.
 */
#[AsEventListener(priority: -768)]
final class SchemaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly ConfigProvider $config,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->scopeMatcher->isFrontendRequest($event->getRequest())) {
            return;
        }

        if (!$this->featureState->isEnabled('schema')) {
            return;
        }

        $pageModel = $event->getRequest()->attributes->get('pageModel');
        if (!$pageModel instanceof PageModel) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || !str_contains($content, '</head>')) {
            return;
        }

        $scripts = $this->buildScripts($pageModel);
        if ($scripts === '') {
            return;
        }

        $pos = strpos($content, '</head>');
        $response->setContent(substr($content, 0, (int) $pos) . $scripts . substr($content, (int) $pos));
    }

    private function buildScripts(PageModel $pageModel): string
    {

        $graphs = [];

        if ((bool) $this->config->get('schemaEnableOrganization', true)) {
            $graphs[] = $this->buildOrganization($pageModel);
        }

        if ((bool) $this->config->get('schemaEnableBreadcrumb', true)) {
            $graphs[] = $this->buildBreadcrumb($pageModel);
        }

        if ($this->featureState->isEnabled('freshness')) {
            $graphs[] = $this->buildWebPage($pageModel);
        }

        if ((bool) $this->config->get('schemaEnableArticle', true)) {
            $graphs[] = $this->buildNewsArticle($pageModel);
        }

        $html = '';
        foreach (array_filter($graphs) as $graph) {
            $json = json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            if ($json !== false) {
                $html .= '<script type="application/ld+json">' . $json . '</script>' . "\n";
            }
        }

        return $html;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildOrganization(PageModel $pageModel): ?array
    {
        $rootTitle = $this->rootTitle($pageModel);
        $name = trim((string) $this->config->get('schemaOrgName', ''));
        if ($name === '') {
            $name = $rootTitle;
        }

        if ($name === '') {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $this->baseUrl(),
        ];

        $logo = trim((string) $this->config->get('schemaOrgLogo', ''));
        if ($logo !== '') {
            $data['logo'] = $logo;
        }

        $sameAs = array_values(array_filter((array) $this->config->get('schemaOrgSameAs', []), 'is_string'));
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildBreadcrumb(PageModel $pageModel): ?array
    {
        $trail = array_map('intval', (array) $pageModel->trail);
        if ($trail === []) {
            return null;
        }

        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        $items = [];
        $position = 1;

        foreach ($trail as $pageId) {
            $page = $pageAdapter->findByPk($pageId);
            if ($page === null || $page->type === 'root') {
                continue;
            }

            try {
                $url = $page->getAbsoluteUrl();
            } catch (\Throwable) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => (string) $page->title,
                'item' => $url,
            ];
        }

        if (\count($items) < 2) {
            return null; // A one-item breadcrumb is noise.
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildWebPage(PageModel $pageModel): ?array
    {
        $lastmod = (int) $pageModel->tstamp;

        $articleMax = $this->connection->fetchOne(
            "SELECT MAX(tstamp) FROM tl_article WHERE pid = ? AND published = '1'",
            [(int) $pageModel->id],
        );
        $lastmod = max($lastmod, (int) $articleMax);

        if ($lastmod <= 0) {
            return null;
        }

        try {
            $url = $pageModel->getAbsoluteUrl();
        } catch (\Throwable) {
            $url = '';
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => (string) ($pageModel->pageTitle !== '' ? $pageModel->pageTitle : $pageModel->title),
            'dateModified' => date('c', $lastmod),
        ];

        if ($url !== '') {
            $data['url'] = $url;
        }

        if ((string) $pageModel->description !== '') {
            $data['description'] = (string) $pageModel->description;
        }

        return $data;
    }

    /**
     * News detail page detection: auto_item -> tl_news alias.
     *
     * @return array<string, mixed>|null
     */
    private function buildNewsArticle(PageModel $pageModel): ?array
    {
        $this->framework->initialize();
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $autoItem = $inputAdapter->get('auto_item');
        if (!\is_string($autoItem) || $autoItem === '') {
            return null;
        }

        try {
            $news = $this->connection->fetchAssociative(
                "SELECT headline, teaser, date, tstamp FROM tl_news WHERE alias = ? AND published = '1'",
                [$autoItem],
            );
        } catch (\Throwable) {
            return null; // news bundle not installed
        }

        if ($news === false) {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => (string) $news['headline'],
            'datePublished' => date('c', (int) $news['date']),
            'dateModified' => date('c', max((int) $news['tstamp'], (int) $news['date'])),
            'inLanguage' => \is_string($pageModel->language) && $pageModel->language !== '' ? $pageModel->language : 'de',
        ];

        $teaser = trim(strip_tags((string) $news['teaser']));
        if ($teaser !== '') {
            $data['description'] = $teaser;
        }

        $publisher = trim((string) $this->config->get('schemaOrgName', ''));
        if ($publisher === '') {
            $publisher = $this->rootTitle($pageModel);
        }
        if ($publisher !== '') {
            $data['publisher'] = ['@type' => 'Organization', 'name' => $publisher];
        }

        return $data;
    }

    private function rootTitle(PageModel $pageModel): string
    {
        $rootId = (int) $pageModel->rootId;
        if ($rootId <= 0) {
            return '';
        }

        $title = $this->connection->fetchOne('SELECT title FROM tl_page WHERE id = ?', [$rootId]);

        return \is_string($title) ? $title : '';
    }

    private function baseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request !== null ? $request->getSchemeAndHttpHost() : '';
    }
}
