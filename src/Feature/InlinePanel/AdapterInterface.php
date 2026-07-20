<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

/**
 * One field-type adapter (headline, altText, teaser, linkText). Collected via
 * the "seo_studio.panel_adapter" tag.
 *
 * Contract: deterministic pre-checks run FIRST and short-circuit without any
 * LLM call; only language judgment/generation goes to the model.
 */
interface AdapterInterface
{
    /** Adapter id as sent by the panel JS ("headline", "altText", ...). */
    public function getId(): string;

    /** Feature id gating this adapter (FeatureState::isEnabled). */
    public function getFeatureId(): string;

    /**
     * @param string $table source table (tl_content, tl_news, ...)
     * @param int $rowId source row id
     * @param string $value CURRENT editor value (may differ from the saved row)
     */
    public function suggest(string $table, int $rowId, string $value): PanelResult;
}
