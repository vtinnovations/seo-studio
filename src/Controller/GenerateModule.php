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
                    ? $this->transf('generate.metaBulkPartial', $result['done'], $this->failNote($result['failed']), $result['remaining'])
                    : $this->transf('generate.metaBulkComplete', $result['done'], $this->failNote($result['failed'])));
            }

            if ($action === 'faqgenerate' && $featureState->isEnabled('faq')) {
                $pageId = $request->request->getInt('faqPageId');
                if ($pageId <= 0) {
                    Message::addError($this->trans('error.pageNotSelected'));
                } else {
                    /** @var FaqGenerator $faq */
                    $faq = $container->get(FaqGenerator::class);
                    $created = $faq->generateForPage($pageId, min(8, max(1, $request->request->getInt('faqCount', 5))));
                    Message::addConfirmation($this->transf('generate.faqGenerated', $created));
                }
            }

            if ($action === 'glossarygenerate' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryGenerator $glossary */
                $glossary = $container->get(GlossaryGenerator::class);
                $terms = preg_split('/\r\n|\r|\n/', (string) $request->request->get('glossaryTerms', '')) ?: [];
                $result = $glossary->generate($terms);

                Message::addConfirmation($this->transf(
                    'generate.glossaryGenerated',
                    $result['created'],
                    $result['skipped'] > 0 ? $this->transf('generate.glossarySkippedNote', $result['skipped']) : '',
                ));
            }

            if ($action === 'glossarysuggest' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryGenerator $glossary */
                $glossary = $container->get(GlossaryGenerator::class);
                Message::addInfo($this->trans('generate.glossarySuggestionsPrefix') . implode(', ', $glossary->suggestTerms(10)));
            }

            if ($action === 'glossaryimport' && $featureState->isEnabled('glossary')) {
                /** @var GlossaryImporter $importer */
                $importer = $container->get(GlossaryImporter::class);
                $result = $importer->import();
                Message::addConfirmation($this->transf(
                    'generate.glossaryImported',
                    $result['imported'],
                    $result['skipped'],
                ));
            }
        } catch (\Throwable $e) {
            Message::addError($this->trans('error.actionFailedPrefix') . $e->getMessage());
        }
    }

    private function failNote(int $failed): string
    {
        return $failed > 0 ? $this->transf('generate.failedNote', $failed) : '';
    }

    private function render(FeatureState $featureState, mixed $container): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $token = $container->get('contao.csrf.token_manager')
            ->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

        $this->registerTabAssets();

        $intro = $this->trans('generate.intro');

        $tabs = [];
        if ($featureState->isEnabled('meta')) {
            $tabs[] = ['meta', $this->trans('generate.tabMeta'), $this->renderMeta($e, $token, $container)];
        }
        if ($featureState->isEnabled('faq')) {
            $tabs[] = ['faq', 'FAQ', $this->renderFaq($e, $token, $container)];
        }
        if ($featureState->isEnabled('glossary')) {
            $tabs[] = ['glossary', $this->trans('generate.tabGlossary'), $this->renderGlossary($e, $token, $container)];
        }

        $tabsHtml = $this->renderTabs('generate', $tabs);
        $body = $tabsHtml !== ''
            ? $tabsHtml
            : '<p class="tl_info">' . $this->trans('generate.disabledNotice') . '</p>';

        return $this->renderShell($intro, $body);
    }

    private function renderMeta(callable $e, string $token, mixed $container): string
    {
        /** @var MetaGenerator $generator */
        $generator = $container->get(MetaGenerator::class);
        /** @var Connection $connection */
        $connection = $container->get('database_connection');

        $roots = $connection->fetchAllAssociative("SELECT id, title FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting");

        $options = '<option value="0">' . $e($this->trans('dash.allRoots')) . '</option>';
        foreach ($roots as $root) {
            $open = \count($generator->findPagesWithEmptyMeta((int) $root['id']));
            $options .= '<option value="' . (int) $root['id'] . '">' . $e($root['title']) . $e($this->transf('generate.rootOpenSuffix', $open)) . '</option>';
        }

        $total = \count($generator->findPagesWithEmptyMeta(null));

        return '<form method="post" action="">'
            . '<input type="hidden" name="REQUEST_TOKEN" value="' . $e($token) . '">'
            . '<input type="hidden" name="seoStudioAction" value="metabulk">'
            . '<fieldset class="tl_tbox block"><legend>' . $this->trans('generate.metaLegend') . '</legend>'
            . '<p>' . $this->trans('generate.metaHelp') . '</p>'
            . ($total === 0
                ? '<p class="tl_confirm">' . $e($this->trans('generate.metaAllDone')) . '</p>'
                : '<p class="tl_info">' . $e($this->transf('generate.metaOpenCount', $total)) . '</p>'
                    . '<div class="seo-studio-inline-row">'
                    . '<select name="metaBulkRoot" class="tl_select">' . $options . '</select>'
                    . '<button type="submit" class="tl_submit">' . $e($this->trans('generate.metaGenerateButton')) . '</button>'
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
            . '<fieldset class="tl_tbox block"><legend>' . $this->trans('generate.faqLegend') . '</legend>'
            . '<p>' . $this->transf('generate.faqHelp', $drafts > 0 ? $this->transf('generate.faqDraftsOpen', $drafts) : '') . '</p>'
            . ($options === ''
                ? '<p class="tl_info">' . $e($this->trans('generate.faqNoPages')) . '</p>'
                : '<div class="seo-studio-inline-row">'
                    . '<select name="faqPageId" class="tl_select">' . $options . '</select>'
                    . '<select name="faqCount" class="tl_select" style="max-width:130px">'
                    . '<option value="3">' . $e($this->transf('generate.faqCountOption', 3)) . '</option>'
                    . '<option value="5" selected>' . $e($this->transf('generate.faqCountOption', 5)) . '</option>'
                    . '<option value="8">' . $e($this->transf('generate.faqCountOption', 8)) . '</option>'
                    . '</select>'
                    . '<button type="submit" class="tl_submit">' . $e($this->trans('generate.faqCreateButton')) . '</button>'
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
            . '<fieldset class="tl_tbox block"><legend>' . $this->trans('generate.glossaryLegend') . '</legend>'
            . '<p>' . $this->transf('generate.glossaryHelp', $entryCount, $draftCount > 0 ? $this->transf('generate.glossaryDraftsNote', $draftCount) : '') . '</p>'
            . '<div class="widget clr long"><h3><label for="ctrl_glossaryTerms">' . $e($this->trans('generate.glossaryTermsLabel')) . '</label></h3>'
            . '<textarea name="glossaryTerms" id="ctrl_glossaryTerms" class="tl_textarea" rows="4" placeholder="' . $this->trans('generate.glossaryTermsPlaceholder') . '"></textarea></div>'
            . '<div class="tl_submit_container" style="margin:8px 0">'
            . '<button type="submit" class="tl_submit">' . $e($this->trans('generate.glossaryGenerateButton')) . '</button> '
            . '<button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'glossarysuggest\'">' . $e($this->trans('generate.glossarySuggestButton')) . '</button>';

        $legacy = $importer->countLegacyEntries();
        if ($legacy !== null && $legacy > 0) {
            $html .= ' <button type="submit" class="tl_submit" onclick="this.form.seoStudioAction.value=\'glossaryimport\';return confirm(\'' . $this->transf('generate.glossaryImportConfirm', $legacy) . '\')">' . $e($this->transf('generate.glossaryImportButton', $legacy)) . '</button>';
        }

        return $html . '</div></fieldset></form>';
    }
}
