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
use VTinnovations\SeoStudio\Feature\Optimize\TextOptimizer;

/**
 * Backend AJAX endpoint for the global text/headline optimizer.
 * Modes: score (rate) / rewrite (SEO-optimize) / generate (fill from context).
 * The current, possibly unsaved editor value comes from the request.
 */
final class OptimizeController extends AbstractController
{
    private const ALLOWED_TABLES = ['tl_content', 'tl_news', 'tl_calendar_events'];

    public function __construct(
        private readonly TextOptimizer $optimizer,
        private readonly FeatureState $featureState,
    ) {
    }

    public function optimizeAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => 'Nicht angemeldet.'], 403);
        }

        if (!$this->featureState->isEnabled('optimize')) {
            return new JsonResponse(['error' => 'Funktion ist deaktiviert.'], 403);
        }

        $table = (string) $request->request->get('table', '');
        $rowId = $request->request->getInt('rowId');
        $fieldType = (string) $request->request->get('fieldType', '');
        $mode = (string) $request->request->get('mode', 'score');
        $value = (string) $request->request->get('value', '');

        if (!\in_array($table, self::ALLOWED_TABLES, true)
            || $rowId <= 0
            || !\in_array($fieldType, ['headline', 'text'], true)
            || !\in_array($mode, ['score', 'rewrite', 'generate'], true)
        ) {
            return new JsonResponse(['error' => 'Ungültige Anfrage.'], 400);
        }

        try {
            $result = $this->optimizer->run($table, $rowId, $fieldType, $mode, $value);
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        } catch (BudgetExceededException|\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse($result->toArray());
    }
}
