<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Security\BudgetExceededException;
use VTinnovations\SeoStudio\Feature\Faq\FaqGenerator;

/**
 * Backend AJAX endpoint: generate FAQ candidates for one page (unpublished,
 * for curation in the "SEO Studio → FAQ" backend module).
 */
final class FaqGenerateController extends AbstractController
{
    public function __construct(
        private readonly FaqGenerator $generator,
        private readonly FeatureState $featureState,
    ) {
    }

    public function generateAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => 'Nicht angemeldet.'], 403);
        }

        if (!$this->featureState->isEnabled('faq')) {
            return new JsonResponse(['error' => 'Funktion ist deaktiviert.'], 403);
        }

        $pageId = $request->request->getInt('pageId');
        if ($pageId <= 0) {
            return new JsonResponse(['error' => 'Ungültige Seiten-ID.'], 400);
        }

        try {
            $created = $this->generator->generateForPage($pageId, $request->request->getInt('count', 5));
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        } catch (BudgetExceededException|\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'created' => $created,
            'message' => sprintf('%d FAQ-Entwürfe erstellt (unveröffentlicht). Kuratieren unter SEO Studio → FAQ.', $created),
        ]);
    }
}
