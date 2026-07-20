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
use VTinnovations\SeoStudio\Feature\PageScore\KeywordSuggester;

/**
 * Backend AJAX endpoints for the per-page SEO checklist's 1-click fixes.
 * The APPLY step stays client-side: the server proposes, the JS fills the
 * form field, the editor reviews and saves. No editor content is written here.
 */
final class PageFixController extends AbstractController
{
    public function __construct(
        private readonly KeywordSuggester $keywordSuggester,
        private readonly FeatureState $featureState,
    ) {
    }

    public function suggestKeywordAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => 'Nicht angemeldet.'], 403);
        }

        if (!$this->featureState->isEnabled('pageScore')) {
            return new JsonResponse(['error' => 'Funktion ist deaktiviert.'], 403);
        }

        $pageId = $request->request->getInt('pageId');
        if ($pageId <= 0) {
            return new JsonResponse(['error' => 'Ungültige Seiten-ID.'], 400);
        }

        try {
            $keyword = $this->keywordSuggester->suggest($pageId);
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        } catch (BudgetExceededException|\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse(['keyword' => $keyword]);
    }
}
