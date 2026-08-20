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
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function metaAction(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse(['error' => Translations::text('error.notLoggedIn')], 403);
        }

        if (!$this->entitlement->isLicensed()) {
            return new JsonResponse(['error' => Translations::text('error.noLicence')], 403);
        }

        if (!$this->featureState->isEnabled('glossary')) {
            return new JsonResponse(['error' => Translations::text('error.featureDisabled')], 403);
        }

        $entryId = $request->request->getInt('entryId');
        if ($entryId <= 0) {
            return new JsonResponse(['error' => Translations::text('error.invalidEntryId')], 400);
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
