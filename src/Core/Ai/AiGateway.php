<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Ai;

use Psr\Log\LoggerInterface;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;
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
    ) {
    }

    /**
     * @throws AiException
     * @throws BudgetExceededException
     */
    public function complete(PromptBundle $bundle): AiResponse
    {
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
