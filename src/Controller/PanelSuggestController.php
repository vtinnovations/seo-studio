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
use VTinnovations\SeoStudio\Feature\InlinePanel\AdapterRegistry;

/**
 * Backend AJAX endpoint for all inline panels. The adapter id selects the
 * field logic; the current (possibly unsaved) editor value comes from the
 * request so the verdict matches what the editor actually sees.
 */
final class PanelSuggestController extends AbstractController
{
    private const ALLOWED_TABLES = ['tl_content', 'tl_news', 'tl_calendar_events'];

    public function __construct(
        private readonly AdapterRegistry $registry,
        private readonly FeatureState $featureState,
    ) {
    }

    public function suggestAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => 'Nicht angemeldet.'], 403);
        }

        $adapterId = (string) $request->request->get('adapter', '');
        $table = (string) $request->request->get('table', '');
        $rowId = $request->request->getInt('rowId');
        $value = (string) $request->request->get('value', '');

        $adapter = $this->registry->get($adapterId);
        if ($adapter === null || !\in_array($table, self::ALLOWED_TABLES, true) || $rowId <= 0) {
            return new JsonResponse(['error' => 'Ungültige Anfrage.'], 400);
        }

        if (!$this->featureState->isEnabled($adapter->getFeatureId())) {
            return new JsonResponse(['error' => 'Funktion ist deaktiviert.'], 403);
        }

        try {
            $result = $adapter->suggest($table, $rowId, $value);
        } catch (BudgetExceededException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        return new JsonResponse($result->toArray());
    }
}
