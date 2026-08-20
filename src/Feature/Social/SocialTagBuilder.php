<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Social;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Builds the Open Graph + Twitter/X card meta tags for a page.
 *
 * Resolution per value: explicit social override → page meta (pageTitle /
 * description) → sensible fallback. The image resolves a page field UUID,
 * else a global default from settings; never a broken tag.
 */
final class SocialTagBuilder
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ConfigProvider $config,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> attr ("property"|"name"), key, content
     */
    public function build(PageModel $pageModel): array
    {
        $title = $this->firstNonEmpty([
            (string) ($pageModel->seoSocialTitle ?? ''),
            (string) $pageModel->pageTitle,
            (string) $pageModel->title,
        ]);

        $description = $this->firstNonEmpty([
            (string) ($pageModel->seoSocialDescription ?? ''),
            (string) $pageModel->description,
        ]);

        $url = '';
        try {
            $url = $pageModel->getAbsoluteUrl();
        } catch (\Throwable) {
            // routing not available — omit og:url
        }

        $image = $this->resolveImage((string) ($pageModel->seoOgImage ?? ''));
        $siteName = $this->rootTitle($pageModel);

        $tags = [];
        $og = static function (string $key, string $value) use (&$tags): void {
            if ($value !== '') {
                $tags[] = ['property', $key, $value];
            }
        };
        $tw = static function (string $key, string $value) use (&$tags): void {
            if ($value !== '') {
                $tags[] = ['name', $key, $value];
            }
        };

        $og('og:type', 'website');
        $og('og:title', $title);
        $og('og:description', $description);
        $og('og:url', $url);
        $og('og:site_name', $siteName);
        $og('og:image', $image);

        $tw('twitter:card', $image !== '' ? 'summary_large_image' : 'summary');
        $tw('twitter:title', $title);
        $tw('twitter:description', $description);
        $tw('twitter:image', $image);

        return $tags;
    }

    public function toHtml(PageModel $pageModel): string
    {
        $html = '';
        foreach ($this->build($pageModel) as [$attr, $key, $content]) {
            $html .= sprintf(
                '<meta %s="%s" content="%s">' . "\n",
                $attr,
                htmlspecialchars($key, ENT_QUOTES),
                htmlspecialchars($content, ENT_QUOTES),
            );
        }

        return $html;
    }

    /**
     * @param list<string> $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Resolve an og:image: page field UUID → global default → absolute URL.
     */
    private function resolveImage(string $uuid): string
    {
        $path = '';

        if ($uuid !== '') {
            $path = $this->pathFromUuid($uuid);
        }

        if ($path === '') {
            $default = trim((string) $this->config->get('socialDefaultImage', ''));
            if ($default !== '') {
                $path = str_starts_with($default, '0x') || \strlen($default) === 16
                    ? $this->pathFromUuid($default)
                    : $default;
            }
        }

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $base = $this->baseUrl();

        return $base !== '' ? $base . '/' . ltrim($path, '/') : '';
    }

    private function pathFromUuid(string $uuid): string
    {
        $this->framework->initialize();

        $filesAdapter = $this->framework->getAdapter(FilesModel::class);
        $model = $filesAdapter->findByUuid($uuid);

        return $model !== null ? (string) $model->path : '';
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
