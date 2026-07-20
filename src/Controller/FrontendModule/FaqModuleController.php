<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Frontend module "FAQ (SEO Studio)": published Q&A of the current page as
 * accordion + FAQPage JSON-LD. Renders nothing when the page has no
 * published FAQs or the feature is off.
 */
#[AsFrontendModule(type: 'seo_studio_faq', category: 'miscellaneous', template: 'seo_studio_faq')]
final class FaqModuleController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FeatureState $featureState,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $page = $this->getPageModel();

        if ($page === null || !$this->featureState->isEnabled('faq')) {
            return new Response('');
        }

        $faqs = $this->connection->fetchAllAssociative(
            "SELECT question, answer FROM tl_seo_studio_faq WHERE pid = ? AND published = '1' ORDER BY sorting",
            [(int) $page->id],
        );

        if ($faqs === []) {
            return new Response('');
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq): array => [
                '@type' => 'Question',
                'name' => (string) $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(strip_tags((string) $faq['answer'])),
                ],
            ], $faqs),
        ];

        $template->set('faqs', $faqs);
        $template->set('json_ld', json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

        return $template->getResponse();
    }
}
