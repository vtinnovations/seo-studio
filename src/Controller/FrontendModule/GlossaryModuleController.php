<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Input;
use Contao\ModuleModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Frontend module "Glossar (SEO Studio)": A-Z list of published terms with
 * DefinedTermSet JSON-LD; detail view via auto_item with DefinedTerm JSON-LD.
 */
#[AsFrontendModule(type: 'seo_studio_glossary', category: 'miscellaneous', template: 'seo_studio_glossary')]
final class GlossaryModuleController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FeatureState $featureState,
        private readonly ContaoFramework $framework,
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if (!$this->entitlement->isLicensed()) {
            return new Response('');
        }

        if (!$this->featureState->isEnabled('glossary')) {
            return new Response('');
        }

        $this->framework->initialize();
        $autoItem = $this->framework->getAdapter(Input::class)->get('auto_item');

        if (\is_string($autoItem) && $autoItem !== '') {
            return $this->renderDetail($template, $autoItem, $request);
        }

        return $this->renderList($template, $request);
    }

    private function renderList(FragmentTemplate $template, Request $request): Response
    {
        $entries = $this->connection->fetchAllAssociative(
            "SELECT term, alias, definition FROM tl_seo_studio_glossary WHERE published = '1' ORDER BY term",
        );

        if ($entries === []) {
            return new Response('');
        }

        $groups = [];
        foreach ($entries as $entry) {
            $letter = mb_strtoupper(mb_substr((string) $entry['term'], 0, 1));
            if (!preg_match('/[A-ZÄÖÜ]/u', $letter)) {
                $letter = '#';
            }
            $groups[$letter][] = $entry;
        }
        ksort($groups);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTermSet',
            'name' => 'Glossar',
            'url' => $request->getUri(),
            'hasDefinedTerm' => array_map(static fn (array $entry): array => [
                '@type' => 'DefinedTerm',
                'name' => (string) $entry['term'],
                'description' => trim(strip_tags((string) $entry['definition'])),
            ], $entries),
        ];

        $template->set('view', 'list');
        $template->set('groups', $groups);
        $template->set('base_url', rtrim(strtok($request->getUri(), '?') ?: '', '/'));
        $template->set('json_ld', json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

        return $template->getResponse();
    }

    private function renderDetail(FragmentTemplate $template, string $alias, Request $request): Response
    {
        $entry = $this->connection->fetchAssociative(
            "SELECT term, alias, definition, metaTitle, metaDescription FROM tl_seo_studio_glossary WHERE alias = ? AND published = '1'",
            [$alias],
        );

        if ($entry === false) {
            throw new PageNotFoundException('Glossary term not found: ' . $alias);
        }

        // Own <title> + meta description per term (instead of the host page's).
        $plainDefinition = trim(strip_tags((string) $entry['definition']));
        $metaTitle = trim((string) $entry['metaTitle']) !== '' ? (string) $entry['metaTitle'] : (string) $entry['term'];
        $metaDescription = trim((string) $entry['metaDescription']) !== ''
            ? (string) $entry['metaDescription']
            : mb_substr($plainDefinition, 0, 155);

        $responseContext = $this->responseContextAccessor->getResponseContext();
        if ($responseContext !== null && $responseContext->has(HtmlHeadBag::class)) {
            $headBag = $responseContext->get(HtmlHeadBag::class);
            $headBag->setTitle($metaTitle);
            $headBag->setMetaDescription($metaDescription);
            $headBag->setCanonicalUri($request->getUri());
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'DefinedTerm',
            'name' => (string) $entry['term'],
            'description' => trim(strip_tags((string) $entry['definition'])),
            'url' => $request->getUri(),
        ];

        $template->set('view', 'detail');
        $template->set('entry', $entry);
        $template->set('json_ld', json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

        return $template->getResponse();
    }
}
