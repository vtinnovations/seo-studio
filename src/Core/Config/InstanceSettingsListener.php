<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use VTinnovations\SeoStudio\Exchange\EntryClaim;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;

/**
 * Handles the three licence actions posted by the Contao → Settings section.
 *
 * Every action passes the same three gates before anything happens:
 *   1. an administrator must be logged in (checked here, not only by the menu);
 *   2. Contao's request token must validate (the settings form carries it);
 *   3. the action must be one of the three known values.
 *
 * Only then is the licence key read and the vendor contacted. The browser never
 * talks to the vendor itself, and the administrator sees a generic result
 * message — the precise internal category goes to the safe operational log.
 *
 * Contao's DC_File reloads the page after this callback (post/redirect/get), so
 * the section re-renders from freshly evaluated state.
 */
#[AsCallback(table: 'tl_settings', target: 'config.onsubmit')]
final class InstanceSettingsListener
{
    private const FIELD_ACTION = 'vtoneSeoStudioAction';

    private const FIELD_KEY = 'vtoneSeoStudioKey';

    private const FIELD_CONFIRM = 'vtoneSeoStudioConfirmRemove';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly ContaoCsrfTokenManager $tokenManager,
        private readonly ProvisioningWorkflow $workflow,
        private readonly EntitlementEvaluator $entitlement,
        private readonly EntryClaim $entryClaim,
        private readonly string $csrfTokenName,
    ) {
    }

    public function __invoke(DataContainer $dc): void
    {
        unset($dc);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !$request->isMethod('POST')) {
            return;
        }

        $action = (string) $request->request->get(self::FIELD_ACTION, '');
        if (!\in_array($action, ['activate', 'refresh', 'remove'], true)) {
            return;
        }

        if (!$this->isAdministrator()) {
            Message::addError($this->text('denied'));

            return;
        }

        if (!$this->hasValidToken($request)) {
            Message::addError($this->text('invalidToken'));

            return;
        }

        $key = (string) $request->request->get(self::FIELD_KEY, '');

        $result = match ($action) {
            'activate' => $this->workflow->activate($key),
            'refresh' => $this->workflow->refresh($key !== '' ? $key : null),
            'remove' => $this->remove($request),
            default => 'unknown_action',
        };

        if ($result === ProvisioningWorkflow::OK) {
            // A new licence (or the absence of one) may signal again in this
            // session.
            $this->entryClaim->resetClaim();
            $this->entitlement->invalidate();

            Message::addConfirmation(match ($action) {
                'remove' => $this->text('removed'),
                'refresh' => $this->text('refreshed'),
                default => $this->text('activated'),
            });

            return;
        }

        Message::addError($this->message($result));
    }

    private function remove(Request $request): string
    {
        // Deliberately a server-side confirmation instead of a JavaScript
        // dialog: removal disables the product, so it must not be one stray
        // click, and it must still work without scripting.
        if (!$request->request->getBoolean(self::FIELD_CONFIRM)) {
            return 'remove_not_confirmed';
        }

        return $this->workflow->remove();
    }

    /**
     * Contao grants ROLE_ADMIN to administrators (BackendUser::getRoles), so
     * this is the framework's own answer to "may this user change instance-wide
     * settings?" — checked here rather than trusted from the menu.
     */
    private function isAdministrator(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }

    private function hasValidToken(Request $request): bool
    {
        $token = (string) $request->request->get('REQUEST_TOKEN', '');

        if ($token === '') {
            return false;
        }

        return $this->tokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $token));
    }

    /**
     * Generic administrator wording for every internal category. The category
     * itself is never displayed.
     */
    private function message(string $category): string
    {
        return match ($category) {
            ProvisioningWorkflow::KEY_MALFORMED => $this->text('keyMissing'),
            ProvisioningWorkflow::NO_CONFIGURED_HOST => $this->text('noDomain'),
            ProvisioningWorkflow::NOTHING_STORED => $this->text('nothingStored'),
            'remove_not_confirmed' => $this->text('confirmRemoval'),
            'transport_failure' => $this->text('unreachable'),
            default => $this->text('failed'),
        };
    }

    private function text(string $key): string
    {
        return Translations::text('licence.' . $key);
    }
}
