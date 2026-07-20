<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\LlmsTxt;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * Builds llms.txt (llmstxt.org convention): H1 site name, optional blockquote
 * summary, then link lists of indexable pages grouped per root page.
 *
 * Deterministic; the optional AI brand summary is generated ONCE from the
 * settings screen and stored — never on request.
 *
 * Result is cached (config row) for an hour — the route is public and cheap
 * to hammer.
 */
final class LlmsTxtBuilder
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigProvider $config,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function build(string $requestHost): string
    {
        $cache = $this->config->get('llmsTxtCache', null);
        if (\is_array($cache)
            && ($cache['host'] ?? '') === $requestHost
            && (int) ($cache['time'] ?? 0) > time() - self::CACHE_TTL
            && \is_string($cache['content'] ?? null)
        ) {
            return $cache['content'];
        }

        $content = $this->generate($requestHost);

        $this->config->set('llmsTxtCache', [
            'host' => $requestHost,
            'time' => time(),
            'content' => $content,
        ]);

        return $content;
    }

    public function invalidateCache(): void
    {
        $this->config->set('llmsTxtCache', null);
    }

    private function generate(string $requestHost): string
    {
        $this->framework->initialize();

        $roots = $this->connection->fetchAllAssociative(
            "SELECT id, title, dns FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting",
        );

        // Prefer the root matching the request host; fall back to all roots.
        $matching = array_values(array_filter(
            $roots,
            static fn (array $r): bool => (string) $r['dns'] === $requestHost,
        ));
        if ($matching !== []) {
            $roots = $matching;
        }

        $siteName = trim((string) $this->config->get('schemaOrgName', ''));
        if ($siteName === '' && $roots !== []) {
            $siteName = (string) $roots[0]['title'];
        }

        $lines = ['# ' . ($siteName !== '' ? $siteName : $requestHost), ''];

        $summary = trim((string) $this->config->get('llmsTxtSummaryText', ''));
        if ($summary !== '') {
            $lines[] = '> ' . str_replace("\n", ' ', $summary);
            $lines[] = '';
        }

        $pageAdapter = $this->framework->getAdapter(PageModel::class);

        // One query, tree walk in PHP (portable — no recursive CTE needed).
        $allPages = $this->connection->fetchAllAssociative(
            "SELECT id, pid, title, description, type, hide, robots
             FROM tl_page WHERE published = '1' ORDER BY sorting",
        );

        $childrenByPid = [];
        foreach ($allPages as $page) {
            $childrenByPid[(int) $page['pid']][] = $page;
        }

        foreach ($roots as $root) {
            $pages = $this->collectIndexablePages($childrenByPid, (int) $root['id']);

            if ($pages === []) {
                continue;
            }

            if (\count($roots) > 1) {
                $lines[] = '## ' . (string) $root['title'];
            } else {
                $lines[] = '## Seiten';
            }

            foreach ($pages as $page) {
                $model = $pageAdapter->findByPk((int) $page['id']);
                if ($model === null) {
                    continue;
                }

                try {
                    $url = $model->getAbsoluteUrl();
                } catch (\Throwable) {
                    continue;
                }

                $title = str_replace(["\r", "\n", ']'], ['', '', ''], (string) $page['title']);
                $description = trim(str_replace(["\r", "\n"], ' ', (string) $page['description']));

                $lines[] = '- [' . $title . '](' . $url . ')' . ($description !== '' ? ': ' . $description : '');
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * Depth-first walk in sorting order, collecting indexable regular pages.
     *
     * @param array<int, list<array<string, mixed>>> $childrenByPid
     * @return list<array<string, mixed>>
     */
    private function collectIndexablePages(array $childrenByPid, int $parentId, int $depth = 0): array
    {
        if ($depth > 25) {
            return [];
        }

        $result = [];

        foreach ($childrenByPid[$parentId] ?? [] as $page) {
            $robots = (string) $page['robots'];

            if ((string) $page['type'] === 'regular'
                && (string) $page['hide'] !== '1'
                && ($robots === '' || str_starts_with($robots, 'index'))
            ) {
                $result[] = $page;
            }

            $result = [...$result, ...$this->collectIndexablePages($childrenByPid, (int) $page['id'], $depth + 1)];
        }

        return $result;
    }
}
