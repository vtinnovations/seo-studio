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

use Contao\Controller;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Feature\Faq\FaqGenerator;
use VTinnovations\SeoStudio\Feature\Glossary\GlossaryGenerator;
use VTinnovations\SeoStudio\Feature\Glossary\GlossaryImporter;
use VTinnovations\SeoStudio\Feature\Meta\MetaGenerator;

/**
 * BE_MOD "SEO Studio → Inhalte & Meta": the content-CREATING actions in one
 * place — bulk meta generation (page titles + descriptions) and AI glossary
 * generation/suggestion/import. Analysis lives in the separate "Analyse"
 * module; per-element text/headline optimisation happens inline on the
 * elements themselves.
 */
final class GenerateModule
{
    use BackendTabsTrait;

    public function generate(): string
    {
        $notice = $this->entitlementNotice();
        if ($notice !== null) {
            return $notice;
        }

        $container = System::getContainer();

        /** @var FeatureState $featureState */
        $featureState = $container->get(FeatureState::class);

        $requestStack = $container->get('request_stack');
        \assert($requestStack instanceof RequestStack);
        $request = $requestStack->getCurrentRequest();

        if ($request instanceof Request && $request->isMethod('POST')) {
            $this->handlePost($request, $featureState, $container);
            Controller::redirect($request->getRequestUri());
        }

        return $this->render($featureState, $container);
    }

    private function handlePost(Request $request, FeatureState $featureState, mixed $container): void
    {
        $action = (string) $request->request->get('seoStudioAction', '');

        try {
            if ($action === 'metabulk' && $featureState->isEnabled('meta')) {
                /** @var MetaGenerator $generator */
                $generator = $container->get(MetaGenerator::class);
                $rootId = $request->request->getInt('metaBulkRoot');
                $result = $generator->bulkFill($rootId > 0 ? $rootId : null, 10);

                Message::addConfirmation($result['remaining'] > 0
                    ? sprintf('%d Seite(n) befüllt%s — %d verbleiben. Erneut klicken oder den Cron übernehmen lassen.', $result['done'], $this->failNote($result['failed']), $result['remaining'])
                    : sprintf('%d Seite(n) befüllt%s. Alle Titel und Beschreibungen sind jetzt gesetzt.', $result['done'], $this->failNote($result['failed'])));
            }

            if ($action === 'faqgenerate' && $featureState->isEnabled('faq')) {
                $pageId = $request->request->getInt('faqPageId');
                if ($pageId <= 0) {
                    Message::addError('Bitte eine Seite auswählen.');
                } else {
                    /** @var FaqGenerator $faq */
                    $faq = $container->get(FaqGenerator::class);
                    $created = $faq->generateForPage($pageId, min(8, max(1, $request->request->getInt('faqCount', 5))));
                    Message::addConfirmation(sprintf(
                        '%d FAQ-Entwurf/-Entwürfe erstellt (unveröffentlicht). Kuratieren unter SEO Studio → FAQ.',
                        $created,
                    ));
                }
            }

            if ($action === 'glossarygenerate' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryGenerator $glossary */
                $glossary = $container->get(GlossaryGenerator::class);
                $terms = preg_split('/\r\n|\r|\n/', (string) $request->request->get('glossaryTerms', '')) ?: [];
                $result = $glossary->generate($terms);

                Message::addConfirmation(sprintf(
                    '%d Definition(en) als Entwurf erstellt%s. Kuratieren unter SEO Studio → Glossar.',
                    $result['created'],
                    $result['skipped'] > 0 ? sprintf(' (%d bereits vorhanden, übersprungen)', $result['skipped']) : '',
                ));
            }

            if ($action === 'glossarysuggest' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryGenerator $glossary */
                $glossary = $container->get(GlossaryGenerator::class);
                Message::addInfo('Begriffs-Vorschläge (in das Textfeld kopieren): ' . implode(', ', $glossary->suggestTerms(10)));
            }

            if ($action === 'glossaryimport' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryImporter $importer */
                $importer = $container->get(GlossaryImporter::class);
                $result = $importer->import();
                Message::addConfirmation(sprintf(
                    '%d Glossar-Eintrag/-Einträge importiert, %d übersprungen. Das alte Glossar-Bundle kann deinstalliert werden.',
                    $result['imported'],
                    $result['skipped'],
                ));
            }
        } catch (\Throwable $e) {
            Message::addError('Aktion fehlgeschlagen: ' . $e->getMessage());
        }
    }

    private function failNote(int $failed): string
    {
        return $failed > 0 ? sprintf(' (%d fehlgeschlagen)', $failed) : '';
    }

    private function render(FeatureState $featureState, mixed $container): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $token = $container->get('contao.csrf.token_manager')
            ->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

        $this->registerTabAssets();

        $intro = '<p>Hier erzeugst du Inhalte per KI. Jede Aktion läuft nur auf Klick und füllt <strong>nur leere Felder</strong> '
            . '(bestehende Inhalte werden nie überschrieben). Einzelne Text- und Überschriften-Blöcke optimierst du direkt '
            . 'am jeweiligen Inhaltselement über den Button „Mit KI optimieren“.</p>';

        $tabs = [];
        if ($featureState->isEnabled('meta')) {
            $tabs[] = ['meta', 'Seitentitel & Meta', $this->renderMeta($e, $token, $container)];
        }
        if ($featureState->isEnabled('faq')) {
            $tabs[] = ['faq', 'FAQ', $this->renderFaq($e, $token, $container)];
        }
        if ($featureState->isEnabled('glossary')) {
            $tabs[] = ['glossary', 'Glossar', $this->renderGlossary($e, $token, $container)];
        }

        $tabsHtml = $this->renderTabs('generate', $tabs);
        $body = $tabsHtml !== ''
            ? $tabsHtml
            : '<p class="tl_info">Meta- und Glossar-Funktion sind deaktiviert (SEO Studio → Einstellungen).</p>';

        return $this->renderShell($intro, $body);
    }

    private function renderMeta(callable $e, string $token, mixed $container): string
    {
        /** @var MetaGenerator $generator */
        $generator = $container->get(MetaGenerator::class);
        /** @var Connection $connection */
        $connection = $container->get('database_connection');

        $roots = $connection->fetchAllAssociative("SELECT id, title FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting");

        $options = '<option value="0">Alle Startpunkte</option>';
        foreach ($roots as $root) {
            $open = \count($generator->findPagesWithEmptyMeta((int) $root['id']));
            $options .= '<option value="' . (int) $root['id'] . '">' . $e($root['title']) . ' (' . $open . ' Seite(n) offen)</option>';
        }

        $total = \count($generator->findPagesWithEmptyMeta(null));

        return '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="metabulk">'
            . '<fieldset class="tl_tbox block"><legend>Seitentitel &amp; Beschreibungen (Meta)</legend>'
            . '<p>Füllt fehlende <code>Seitentitel</code> und <code>Beschreibung</code> im Reiter „Metadaten“ jeder Seite. 10 Seiten pro Durchlauf.</p>'
            . ($total === 0
                ? '<p class="tl_confirm">Alle veröffentlichten Seiten haben Titel und Beschreibung.</p>'
                : '<p class="tl_info">' . $total . ' Seite(n) mit leerem Titel oder leerer Beschreibung.</p>'
                    . '<div class="seo-studio-inline-row">'
                    . '<select name="metaBulkRoot" class="tl_select">' . $options . '</select>'
                    . '<button type="submit" class="tl_submit">Jetzt generieren (nur leere Felder)</button>'
                    . '</div>')
            . '</fieldset></form>';
    }

    private function renderFaq(callable $e, string $token, mixed $container): string
    {
        /** @var Connection $connection */
        $connection = $container->get('database_connection');

        $pages = $connection->fetchAllAssociative("SELECT id, title FROM tl_page WHERE type = 'regular' AND published = '1' ORDER BY title");

        $drafts = 0;
        try {
            $drafts = (int) $connection->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_faq WHERE published != '1'");
        } catch (\Throwable) {
            // table not migrated yet
        }

        $options = '';
        foreach ($pages as $page) {
            $options .= '<option value="' . (int) $page['id'] . '">' . $e($page['title']) . ' (ID ' . (int) $page['id'] . ')</option>';
        }

        return '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="faqgenerate">'
            . '<fieldset class="tl_tbox block"><legend>FAQ-Generierung</legend>'
            . '<p>Erzeugt aus dem Inhalt einer Seite FAQ-Entwürfe (unveröffentlicht) für FAQPage-Schema und Antwort-Engines. '
            . 'Kuratieren und veröffentlichen unter <strong>SEO Studio → FAQ</strong>' . ($drafts > 0 ? ' (' . $drafts . ' Entwürfe offen)' : '') . '. '
            . 'Die gleiche Funktion findest du auch direkt im SEO-Studio-Panel jeder Seite.</p>'
            . ($options === ''
                ? '<p class="tl_info">Keine veröffentlichten Seiten.</p>'
                : '<div class="seo-studio-inline-row">'
                    . '<select name="faqPageId" class="tl_select">' . $options . '</select>'
                    . '<select name="faqCount" class="tl_select" style="max-width:130px">'
                    . '<option value="3">3 Fragen</option><option value="5" selected>5 Fragen</option><option value="8">8 Fragen</option>'
                    . '</select>'
                    . '<button type="submit" class="tl_submit">FAQ-Entwürfe erstellen</button>'
                    . '</div>')
            . '</fieldset></form>';
    }

    private function renderGlossary(callable $e, string $token, mixed $container): string
    {
        /** @var Connection $connection */
        $connection = $container->get('database_connection');
        /** @var GlossaryImporter $importer */
        $importer = $container->get(GlossaryImporter::class);

        $entryCount = 0;
        $draftCount = 0;
        try {
            $entryCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM tl_seo_studio_glossary');
            $draftCount = (int) $connection->fetchOne("SELECT COUNT(*) FROM tl_seo_studio_glossary WHERE published != '1'");
        } catch (\Throwable) {
            // table not migrated yet
        }

        $html = '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="glossarygenerate">'
            . '<fieldset class="tl_tbox block"><legend>KI-Glossar</legend>'
            . '<p>' . $entryCount . ' Eintrag/Einträge' . ($draftCount > 0 ? ' (' . $draftCount . ' unveröffentlichte Entwürfe)' : '')
            . ' — Kuratierung unter <strong>SEO Studio → Glossar</strong>, Ausgabe über das Frontend-Modul „Glossar (SEO Studio)“.</p>'
            . '<div class="widget clr long"><h3><label for="ctrl_glossaryTerms">Begriffe (einer pro Zeile)</label></h3>'
            . '<textarea name="glossaryTerms" id="ctrl_glossaryTerms" class="tl_textarea" rows="4" placeholder="Content-Management-System&#10;Responsive Design"></textarea></div>'
            . '<div class="tl_submit_container" style="margin:8px 0">'
            . '<button type="submit" class="tl_submit">Definitionen generieren (Entwürfe)</button> '
            . '<button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'glossarysuggest\'">Begriffe aus Website vorschlagen</button>';

        $legacy = $importer->countLegacyEntries();
        if ($legacy !== null && $legacy > 0) {
            $html .= ' <button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'glossaryimport\';return confirm(\'' . $legacy . ' Einträge aus dem alten Glossar-Bundle importieren? Bestehende Begriffe werden übersprungen, Alt-Daten bleiben unverändert.\')">Aus Glossar-Bundle importieren (' . $legacy . ')</button>';
        }

        return $html . '</div></fieldset></form>';
    }
}
