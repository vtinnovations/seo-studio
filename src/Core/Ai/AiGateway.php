<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Ai;

use Psr\Log\LoggerInterface;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\Translations;
use VTinnovations\SeoStudio\Core\Security\BudgetExceededException;
use VTinnovations\SeoStudio\Core\Security\TokenBudget;

/**
 * The ONE entry point features use for LLM calls. Wraps the provider client
 * with: monthly token budget (hard stop BEFORE the call, usage recorded
 * after), model fallback resolution (bundle model -> configured model ->
 * provider default) and call logging (purpose + tokens, never content).
 *
 * Features never talk to AiClientFactory directly — budget enforcement must
 * be impossible to bypass.
 */
final class AiGateway
{
    public function __construct(
        private readonly AiClientFactory $factory,
        private readonly ConfigProvider $config,
        private readonly TokenBudget $budget,
        private readonly LoggerInterface $logger,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    /**
     * @throws AiException
     * @throws BudgetExceededException
     */
    public function complete(PromptBundle $bundle): AiResponse
    {
        // Independent gate at the paid-capability boundary: whatever called us —
        // a controller, a DCA panel, the cron, a future service — no generation
        // happens without a valid licence.
        if (!$this->entitlement->isLicensed()) {
            throw new \RuntimeException(Translations::text('error.notLicensed'));
        }

        $this->budget->assertBudgetAvailable();

        $client = $this->factory->default();

        if ($bundle->model === '') {
            $configured = trim((string) $this->config->get('aiModel', ''));
            $bundle = $bundle->withModel($configured !== '' ? $configured : $client->defaultModel());
        }

        $response = $client->complete($bundle);

        $this->budget->recordUsage($response->totalTokens());

        $this->logger->info('SEO Studio AI call', [
            'purpose' => $bundle->purpose,
            'provider' => $response->provider,
            'model' => $response->model,
            'tokens' => $response->totalTokens(),
            'durationMs' => $response->durationMs,
        ]);

        return $response;
    }
}
