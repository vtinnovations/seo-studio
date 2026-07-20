<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;

/**
 * Renders the per-page SEO checklist panel shown in the tl_page edit form:
 * a traffic-light score plus grouped checks with ✓ / ⚠ / ✗ and hints,
 * plus 1-click AI fixes (focus-keyword suggestion, title/description).
 */
final class SeoChecklistRenderer
{
    private const GROUP_LABELS = [
        'basics' => 'Grundlagen',
        'keyword' => 'Fokus-Keyword',
        'readability' => 'Lesbarkeit',
    ];

    public function __construct(
        private readonly PageSeoAnalyzer $analyzer,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function render(int $pageId): string
    {
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_pagescore'] = 'bundles/vtinnovationsseostudio/pagescore.js';

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        try {
            $report = $this->analyzer->analyze($pageId);
        } catch (\Throwable $ex) {
            return '<div class="widget clr"><p class="tl_info">SEO-Analyse nicht möglich: ' . $e($ex->getMessage()) . '</p></div>';
        }

        if ($report->checks === []) {
            return '<div class="widget clr"><p class="tl_info">Noch kein analysierbarer Inhalt auf dieser Seite.</p></div>';
        }

        $summary = $report->problemCount() > 0
            ? $report->problemCount() . ' Problem(e)' . ($report->warningCount() > 0 ? ', ' . $report->warningCount() . ' Hinweis(e)' : '')
            : ($report->warningCount() > 0 ? $report->warningCount() . ' Hinweis(e)' : 'alles im grünen Bereich');

        $token = $this->csrfTokenManager->getDefaultTokenValue();

        $html = '<div class="widget clr seo-studio-scorepanel" data-seo-studio-pagescore'
            . ' data-page-id="' . $pageId . '"'
            . ' data-token="' . $e($token) . '"'
            . ' data-keyword-url="/contao/seostudio/page/suggest-keyword"'
            . ' data-meta-url="/contao/seostudio/meta/generate">'
            . '<div class="seo-studio-score-head">'
            . '<span class="seo-studio-scoreball seo-studio-scoreball--' . $report->color() . '">' . $report->score . '</span>'
            . '<div><strong>SEO-Bewertung dieser Seite</strong>'
            . '<div class="seo-studio-muted">' . $e($summary) . ' · Stand: nach dem letzten Speichern</div></div>'
            . '</div>';

        // 1-click AI actions.
        $html .= '<div class="seo-studio-pf-actions">'
            . '<button type="button" class="tl_submit seo-studio-pf-keyword">KI: Fokus-Keyword vorschlagen</button> '
            . '<button type="button" class="tl_submit seo-studio-pf-meta">KI: Titel &amp; Beschreibung erzeugen</button> '
            . '<span class="seo-studio-pf-status seo-studio-muted"></span>'
            . '</div>';

        if ($report->focusKeyword === '') {
            $html .= '<p class="tl_info" style="margin:8px 0">Tipp: Trage oben ein <strong>Fokus-Keyword</strong> ein (oder lass es dir per KI vorschlagen) — dann prüft SEO Studio zusätzlich, ob es an den richtigen Stellen vorkommt.</p>';
        }

        // Group the checks.
        $byGroup = [];
        foreach ($report->checks as $check) {
            $byGroup[$check->group][] = $check;
        }

        foreach (self::GROUP_LABELS as $group => $groupLabel) {
            if (empty($byGroup[$group])) {
                continue;
            }

            $html .= '<h4 class="seo-studio-checkgroup">' . $e($groupLabel) . '</h4><ul class="seo-studio-checklist">';
            foreach ($byGroup[$group] as $check) {
                $icon = match ($check->status) {
                    'good' => '✓',
                    'warn' => '⚠',
                    default => '✗',
                };
                $html .= '<li class="seo-studio-check seo-studio-check--' . $e($check->status) . '">'
                    . '<span class="seo-studio-check-icon">' . $icon . '</span>'
                    . '<span class="seo-studio-check-label">' . $e($check->label)
                    . ($check->hint !== '' ? ' <span class="seo-studio-muted">— ' . $e($check->hint) . '</span>' : '')
                    . $this->fixAction($check)
                    . '</span></li>';
            }
            $html .= '</ul>';
        }

        $html .= '<p class="tl_help" style="margin-top:8px">Nach dem Speichern aktualisiert sich die Bewertung. Bild-Alt-Texte pflegst du am jeweiligen Inhaltselement bzw. im SEO-Studio unter „Bilder".</p>';

        return $html . '</div>';
    }

    /**
     * Renders an inline fix affordance for checks that still need work.
     */
    private function fixAction(SeoCheck $check): string
    {
        if ($check->status === 'good' || $check->fix === '') {
            return '';
        }

        return match ($check->fix) {
            'meta' => ' <button type="button" class="seo-studio-pf-link seo-studio-pf-meta">mit KI beheben</button>',
            'keyword' => ' <button type="button" class="seo-studio-pf-link seo-studio-pf-keyword">KI-Vorschlag</button>',
            default => '',
        };
    }
}
