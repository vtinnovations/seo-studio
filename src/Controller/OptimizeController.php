<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Config\Translations;
use VTinnovations\SeoStudio\Core\Security\BudgetExceededException;
use VTinnovations\SeoStudio\Feature\Optimize\TextOptimizer;

/**
 * Backend AJAX endpoint for the global text/headline optimizer.
 * Modes: score (rate) / rewrite (SEO-optimize) / generate (fill from context).
 * The current, possibly unsaved editor value comes from the request.
 */
final class OptimizeController extends AbstractController
{
    private const ALLOWED_TABLES = [
        'tl_content',
        'tl_news',
        'tl_calendar_events',
        'tl_seo_studio_faq',
        'tl_seo_studio_glossary',
    ];

    public function __construct(
        private readonly TextOptimizer $optimizer,
        private readonly FeatureState $featureState,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function optimizeAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => Translations::text('error.notLoggedIn')], 403);
        }

        if (!$this->entitlement->isLicensed()) {
            return new JsonResponse(['error' => Translations::text('error.noLicence')], 403);
        }

        if (!$this->featureState->isEnabled('optimize')) {
            return new JsonResponse(['error' => Translations::text('error.featureDisabled')], 403);
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
            return new JsonResponse(['error' => Translations::text('error.invalidRequest')], 400);
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
