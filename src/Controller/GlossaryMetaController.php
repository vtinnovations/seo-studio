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
use VTinnovations\SeoStudio\Feature\Glossary\GlossaryGenerator;

/**
 * Backend AJAX endpoint: propose metaTitle + metaDescription for one glossary
 * entry. Apply happens client-side (JS fills the form fields, editor saves).
 */
final class GlossaryMetaController extends AbstractController
{
    public function __construct(
        private readonly GlossaryGenerator $generator,
        private readonly FeatureState $featureState,
    ) {
    }

    public function metaAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => 'Nicht angemeldet.'], 403);
        }

        if (!$this->featureState->isEnabled('glossary')) {
            return new JsonResponse(['error' => 'Funktion ist deaktiviert.'], 403);
        }

        $entryId = $request->request->getInt('entryId');
        if ($entryId <= 0) {
            return new JsonResponse(['error' => 'Ungültige Eintrags-ID.'], 400);
        }

        try {
            $proposal = $this->generator->proposeMeta($entryId);
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        } catch (BudgetExceededException|\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'pageTitle' => $proposal['metaTitle'],
            'description' => $proposal['metaDescription'],
        ]);
    }
}
