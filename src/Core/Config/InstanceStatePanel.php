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

use Contao\System;
use Contao\Widget;
use VTinnovations\SeoStudio\Core\Content\SiteInventory;
use VTinnovations\SeoStudio\Exchange\EntryClaim;

/**
 * The licence section inside Contao → Settings.
 *
 * A plain Contao backend widget: the state is rendered SERVER-SIDE on every
 * request, so there is no loading placeholder that can hang, and the three
 * controls are real submit buttons inside Contao's own settings form. That form
 * already carries Contao's request token and is reachable only by an
 * administrator, and the posted action is handled by InstanceSettingsListener.
 *
 * The layout is a single framed box holding the status (headline plus a single
 * line of facts), the key field and the three actions in one row — the same
 * shape the sibling V-T.ONE packages present on this settings screen, so all of
 * them read as one product family.
 *
 * The only script is a confirm() dialog on "Remove licence". Nothing depends on
 * it: the listener still refuses a removal that does not carry the confirmation
 * field, so the dialog is a courtesy, not the safeguard.
 *
 * The stored licence key is shown MASKED only — four leading and four trailing
 * characters, with a fixed number of dots in between, so an administrator can
 * tell which key is installed while the panel never discloses a usable key or
 * even its real length.
 */
final class InstanceStatePanel extends Widget
{
    /**
     * @var string
     */
    protected $strTemplate = 'be_widget';

    protected $blnSubmitInput = false;

    /** Fixed mask width: the real key length is not disclosed either. */
    private const MASK_WIDTH = 16;

    public function generate(): string
    {
        $container = System::getContainer();

        /** @var EntitlementEvaluator $entitlement */
        $entitlement = $container->get(EntitlementEvaluator::class);
        /** @var SiteInventory $inventory */
        $inventory = $container->get(SiteInventory::class);
        /** @var EntryClaim $entryClaim */
        $entryClaim = $container->get(EntryClaim::class);

        // Entering the authoritative licence surface is a module entry: claim
        // the once-per-session signal here (delivered after the response).
        $entryClaim->claim();

        $state = $entitlement->current();
        // Only the vendor-signed record carries the key, the domain allowance
        // and the timestamps. It is null whenever nothing authentic is stored,
        // and the facts that depend on it are then simply omitted.
        $record = $entitlement->authenticatedRecord();
        $configured = $inventory->configuredHosts();

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="seo-studio-licence">'
            . '<div class="seo-studio-licence__box">'
            . $this->renderState($e, $state, $record, $configured)
            . $this->renderControls($e, $state, $configured)
            . '</div></div>';
    }

    /**
     * @param callable(mixed):string $e
     * @param list<string>           $configured
     */
    private function renderState(callable $e, EntitlementState $state, ?ProvisioningRecord $record, array $configured): string
    {
        // One key per state, so every status is translatable on its own.
        $message = Translations::text('licence.status_' . $state->status);

        // Contao's own message class stays on the box for its semantics; the
        // box look itself comes from backend.css.
        [$contaoClass, $variant] = match (true) {
            $state->licensed => ['tl_confirm', 'ok'],
            $state->status === EntitlementState::ABSENT => ['tl_info', 'info'],
            default => ['tl_error', 'error'],
        };

        $facts = $this->facts($state, $record, $configured);

        return '<div class="seo-studio-licence__state seo-studio-licence__state--' . $variant . ' ' . $contaoClass . '">'
            . '<p class="seo-studio-licence__headline">' . $e($message) . '</p>'
            . ($facts === [] ? '' : '<p class="seo-studio-licence__facts">' . $e(implode(' · ', $facts)) . '</p>')
            . '</div>';
    }

    /**
     * The one-line summary under the headline, as "Label: value" parts.
     *
     * Five facts and no more — masked key, package, and the three dates — the
     * same five every V-T.ONE section on this screen shows. Version, bound
     * domain, the signed domain set and the allowance are record internals an
     * administrator never acts on from here; the configured domains still
     * appear while something is wrong, because then they are the fix.
     *
     * A part is omitted rather than filled with a placeholder when its source
     * is unavailable, so the line never claims to know something it does not.
     *
     * @param list<string> $configured
     *
     * @return list<string>
     */
    private function facts(EntitlementState $state, ?ProvisioningRecord $record, array $configured): array
    {
        $format = Translations::text('licence.dateFormat');

        if (!$state->hasStoredRecord()) {
            return [
                Translations::text('licence.factProduct') . ': ' . PackagePolicy::TITLE . ' (' . PackagePolicy::PROJECT . ')',
                Translations::text('licence.factConfiguredDomains') . ': ' . ($configured === []
                    ? Translations::text('licence.noneConfigured')
                    : implode(', ', $configured)),
            ];
        }

        $facts = [];

        if ($record !== null && $record->licenceKey() !== '') {
            $facts[] = Translations::text('licence.factKey') . ': ' . $this->maskedKey($record->licenceKey());
        }

        if ($state->package !== '') {
            $facts[] = Translations::text('licence.factPackage') . ': ' . strtoupper($state->package);
        }

        if ($record !== null) {
            $facts[] = Translations::text('licence.factValidFrom') . ': ' . date($format, $record->startsAt());
        }

        $facts[] = Translations::text('licence.factValidUntil') . ': ' . match (true) {
            $state->lifetime => Translations::text('licence.unlimited'),
            $state->expiresAt !== null => date($format, $state->expiresAt),
            default => '—',
        };

        if ($record !== null) {
            $facts[] = Translations::text('licence.factLastVerified') . ': ' . date($format, $record->verifiedAt());
        }

        // Only relevant while something is wrong: a stored licence that does
        // not entitle this installation is usually a domain mismatch.
        if (!$state->licensed) {
            $facts[] = Translations::text('licence.factConfiguredDomains') . ': ' . ($configured === []
                ? Translations::text('licence.noneConfigured')
                : implode(', ', $configured));
        }

        return $facts;
    }

    /**
     * Four leading and four trailing characters around a fixed-width mask.
     *
     * A key too short to keep both ends recognisable is masked completely: half
     * of a short key is not a hint, it is the key.
     */
    private function maskedKey(string $key): string
    {
        $key = trim($key);
        $mask = str_repeat('•', self::MASK_WIDTH);

        if (mb_strlen($key) <= 8) {
            return $mask;
        }

        return mb_substr($key, 0, 4) . $mask . mb_substr($key, -4);
    }

    /**
     * @param callable(mixed):string $e
     * @param list<string>           $configured
     */
    private function renderControls(callable $e, EntitlementState $state, array $configured): string
    {
        if ($configured === []) {
            // Activation is impossible without a configured identity, so no
            // control is offered that could not work.
            return '<p class="seo-studio-licence__hint tl_info">'
                . $e(Translations::text('licence.noDomainHint'))
                . '</p>';
        }

        $field = '<div class="seo-studio-licence__field">'
            . '<label class="seo-studio-licence__label" for="ctrl_vtoneSeoStudioKey">'
            . $e(Translations::text('licence.keyLabel')) . '</label>'
            . '<input type="text" name="vtoneSeoStudioKey" id="ctrl_vtoneSeoStudioKey" class="tl_text seo-studio-licence__input"'
            . ' value="" placeholder="' . $e(Translations::text('licence.keyPlaceholder')) . '"'
            . ' autocomplete="off" spellcheck="false" maxlength="' . PackagePolicy::KEY_MAX_LENGTH . '">'
            . '</div>';

        $buttons = '<button type="submit" name="vtoneSeoStudioAction" value="activate" class="tl_submit">'
            . $e(Translations::text('licence.activateButton')) . '</button>'
            . '<button type="submit" name="vtoneSeoStudioAction" value="refresh" class="tl_submit">'
            . $e(Translations::text('licence.refreshButton')) . '</button>';

        if ($state->hasStoredRecord()) {
            // The hidden field is what the listener actually checks; the dialog
            // only gives the administrator a chance to step back first.
            $buttons .= '<input type="hidden" name="vtoneSeoStudioConfirmRemove" value="1">'
                . '<button type="submit" name="vtoneSeoStudioAction" value="remove" class="tl_submit"'
                . ' onclick="return confirm(' . $e($this->jsString(Translations::text('licence.removeConfirmPrompt'))) . ')">'
                . $e(Translations::text('licence.removeButton')) . '</button>';
        }

        return $field . '<div class="seo-studio-licence__actions">' . $buttons . '</div>';
    }

    /**
     * A translated sentence as a JavaScript string literal.
     *
     * json_encode escapes the quotes and every character that could end the
     * literal early, so a translator's apostrophe cannot break the handler.
     */
    private function jsString(string $text): string
    {
        return (string) json_encode(
            $text,
            JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }
}
