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
use VTinnovations\SeoStudio\Feature\Meta\MetaGenerator;

/**
 * Backend AJAX endpoint: generate a pageTitle + description proposal for one
 * page. The APPLY step happens client-side (the JS fills the form fields, the
 * editor reviews and saves) — the server never writes editor content here.
 */
final class MetaController extends AbstractController
{
    public function __construct(
        private readonly MetaGenerator $generator,
        private readonly FeatureState $featureState,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function generateAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => Translations::text('error.notLoggedIn')], 403);
        }

        if (!$this->entitlement->isLicensed()) {
            return new JsonResponse(['error' => Translations::text('error.noLicence')], 403);
        }

        if (!$this->featureState->isEnabled('meta')) {
            return new JsonResponse(['error' => Translations::text('error.featureDisabled')], 403);
        }

        $pageId = $request->request->getInt('pageId');
        if ($pageId <= 0) {
            return new JsonResponse(['error' => Translations::text('error.invalidPageId')], 400);
        }

        try {
            $proposal = $this->generator->propose($pageId);
        } catch (AiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        } catch (BudgetExceededException|\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'pageTitle' => $proposal->pageTitle,
            'description' => $proposal->description,
        ]);
    }
}
