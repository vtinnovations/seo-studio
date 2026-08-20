<?php

declare(strict_types=1);

/*
 * All user-facing text of AI SEO Studio, English.
 *
 * The bundle contains no hardcoded interface strings: every label, hint,
 * message and error is read from here through
 * VTinnovations\SeoStudio\Core\Config\Translations, which has no inline
 * fallbacks. A key that is missing renders as "[key]" and fails the release
 * guard, so a forgotten translation is visible instead of silently English or
 * German.
 *
 * This file and its counterpart are generated from one shared key list, so both
 * languages always carry exactly the same keys.
 */

// ── Licence management (Contao → Settings) and module notice ────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['activateButton'] = 'Verify & activate licence';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['activated'] = 'The licence was activated. AI SEO Studio is active.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['confirmRemoval'] = 'Please confirm the removal first.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['dateFormat'] = 'Y-m-d H:i';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['denied'] = 'You are not allowed to manage the licence.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factActiveDomain'] = 'Active domain';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factConfiguredDomains'] = 'Configured domains';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factKey'] = 'Key';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factLastVerified'] = 'Last verified';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factPackage'] = 'Package';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factProduct'] = 'Product';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factStored'] = 'Licence stored';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factValidFrom'] = 'Valid from';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factValidUntil'] = 'Valid until';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['failed'] = 'The licence could not be verified. Please check the key and try again.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['invalidToken'] = 'The form could not be validated. Please try again.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyHint'] = 'Required to activate. Leave empty when updating an already stored licence.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyLabel'] = 'Licence key';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyMissing'] = 'Please enter a valid licence key.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyPlaceholder'] = 'XXXXX-XXXXX-XXXXX-XXXXX';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['moduleBlocked'] = 'AI SEO Studio is not licensed. Please activate your licence under Settings.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['no'] = 'no';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noDomain'] = 'This installation has no configured domain. Set the domain on your website root page first.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noDomainHint'] = 'This installation has no configured domain. Set the domain on your website root page (Site structure → root page → “Domain name”) and reload this page.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noneConfigured'] = 'none configured';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['nothingStored'] = 'There is no stored licence for this installation.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['openSettings'] = 'Go to Settings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['refreshButton'] = 'Update licence';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['refreshed'] = 'The licence was updated.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeButton'] = 'Remove licence';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeConfirmLabel'] = 'Yes, remove the stored licence from this installation';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeConfirmPrompt'] = 'Remove the stored licence? Every AI SEO Studio function is disabled immediately.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeHint'] = 'Removing the licence disables every AI SEO Studio function immediately. Your pages, FAQ entries and glossary entries are kept.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removed'] = 'The licence was removed. AI SEO Studio is inactive.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_licensed'] = 'Pro licence active. All features unlocked.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_absent'] = 'No licence stored. Enter your licence key to activate AI SEO Studio.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_expired'] = 'Licence expired. AI SEO Studio is inactive until a valid licence is applied.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_needs_refresh'] = 'The stored licence predates the current licence format. Please use “Update licence”.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_no_host_match'] = 'The stored licence is not issued for a domain configured on this installation.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_not_started'] = 'The licence is not valid yet.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_package_not_accepted'] = 'The stored licence is not valid for this product.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_unverifiable'] = 'The stored licence could not be verified.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['unlimited'] = 'unlimited';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['unreachable'] = 'The licence server could not be reached. The current licence state is unchanged.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['yes'] = 'yes';

// ── Errors and refusals returned by endpoints and services ──────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['apiKeyMissing'] = 'No API key stored. Please set one in the settings.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['egressBlocked'] = 'External calls are disabled (“no external calls”). Please allow them in the settings first.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['egressBlockedTest'] = 'Test not performed: “no external calls” is active. Allow external calls to test.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['featureDisabled'] = 'This function is disabled.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidBaseUrl'] = 'Invalid base URL. Only http(s) with a hostname is allowed.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidEntryId'] = 'Invalid entry ID.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidPageId'] = 'Invalid page ID.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidRequest'] = 'Invalid request.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noLicence'] = 'No valid licence.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noTheme'] = 'No theme present — image sizes need a theme.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['notLicensed'] = 'AI SEO Studio is not licensed. Please activate the licence under Settings.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['notLoggedIn'] = 'Not signed in.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['pageNotFound'] = 'Page not found.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['providerNonJson'] = '%s returned HTTP 200 with a non-JSON body.';

// ── Per-page SEO checklist (PageSeoAnalyzer) ────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altComplete']['label'] = 'Every image has alt text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altMissing']['hint'] = 'Alt text matters for image search and accessibility.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altMissing']['label'] = '%d image(s) without alt text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['charactersCount'] = '%d characters.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionLength']['hint'] = '%d characters — 120–155 is ideal.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionLength']['label'] = 'Description length';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionMissing']['hint'] = 'Otherwise search engines show arbitrary text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionMissing']['label'] = 'Meta description missing';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['enoughText']['label'] = 'Enough text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['imagesCount'] = '%d image(s).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordAbsent']['hint'] = 'The focus keyword should appear in the body text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordAbsent']['label'] = 'Focus keyword does not appear in the text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityGood']['hint'] = '%.1f %%.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityGood']['label'] = 'Keyword density good';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityHigh']['hint'] = '%.1f %% — this quickly reads as spam (0.5–2.5 %% is ideal).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityHigh']['label'] = 'Keyword density high';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityLow']['hint'] = '%.1f %% — feel free to mention it a little more often.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityLow']['label'] = 'Keyword density low';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInDescription']['hint'] = 'Raises the click rate in search results.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInDescription']['label'] = 'Focus keyword in the description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInFirstParagraph']['hint'] = 'Naming it early states the topic clearly.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInFirstParagraph']['label'] = 'Focus keyword in the first paragraph';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInH1']['hint'] = 'The main heading should contain the keyword.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInH1']['label'] = 'Focus keyword in the H1';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInSubheading']['hint'] = 'Reinforces topical relevance.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInSubheading']['label'] = 'Focus keyword in a subheading';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInTitle']['hint'] = '“%s” should appear in the title.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInTitle']['label'] = 'Focus keyword in the page title';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInUrl']['hint'] = 'A readable alias containing the keyword helps.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInUrl']['label'] = 'Focus keyword in the URL';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['littleText']['hint'] = '%d words — more content helps the ranking.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['littleText']['label'] = 'Little text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['multipleH1']['hint'] = 'Use exactly one H1 per page.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['multipleH1']['label'] = '%d H1 headings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noH1']['hint'] = 'Fine if the layout renders the page title as the H1.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noH1']['label'] = 'No H1 in the content';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noSubheadings']['hint'] = 'Subheadings improve readability and answer-engine visibility.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noSubheadings']['label'] = 'No subheadings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noText']['hint'] = 'The page has no readable text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noText']['label'] = 'No text content';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityGood']['hint'] = 'Readability %d/100.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityGood']['label'] = 'Easy to understand';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityHard']['hint'] = 'Readability %d/100 — use shorter words and sentences.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityHard']['label'] = 'Hard to understand';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityMedium']['hint'] = 'Readability %d/100 — phrase it more simply.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityMedium']['label'] = 'Moderately easy to understand';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsRepeated']['hint'] = '%d sentences in a row start the same way — add variety.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsRepeated']['label'] = 'Repetitive sentence openings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsVaried']['label'] = 'Varied sentence openings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesGood']['hint'] = 'Ø %.0f words per sentence.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesGood']['label'] = 'Sentences read well';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesLongish']['hint'] = 'Ø %.0f words per sentence — shorter sentences read more easily.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesLongish']['label'] = 'Sentences a little long';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesTooLong']['hint'] = 'Ø %.0f words per sentence — shorten them noticeably.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesTooLong']['label'] = 'Sentences too long';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['subheadings']['hint'] = '%d of them.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['subheadings']['label'] = 'Subheadings present';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleLength']['hint'] = '%d characters — 30–60 is ideal.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleLength']['label'] = 'Page title length';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleMissing']['hint'] = 'Without a title there is no good search result.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleMissing']['label'] = 'Page title missing';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleSet']['label'] = 'Page title set';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsFew']['hint'] = '%d %% — connectors such as “also” and “therefore” guide the reader.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsFew']['label'] = 'Few transition words';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsGood']['hint'] = '%d %% of sentences use transition words.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsGood']['label'] = 'Good flow';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsNone']['hint'] = 'Only %d %% of sentences are connected — add “also”, “therefore”, “first of all”.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsNone']['label'] = 'Almost no transition words';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['wordsCount'] = '%d words.';

// ── SEO score panel ─────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['aiSuggestion'] = 'AI suggestion';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['allGood'] = 'all clear';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['analysisFailed'] = 'SEO analysis not possible: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['fixWithAi'] = 'fix with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['generateMeta'] = 'AI: generate title & description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['keywordTip'] = 'Tip: enter a <strong>focus keyword</strong> above (or have the AI suggest one) — SEO Studio then also checks whether it appears in the right places.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['noContent'] = 'No analysable content on this page yet.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['panelTitle'] = 'SEO rating of this page';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['suggestKeyword'] = 'AI: suggest a focus keyword';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['warningCount'] = '%d note(s)';

// ── Text / headline optimiser ───────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['applyButton'] = 'Insert into the field';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['badgeTitle'] = 'SEO form check: %d/100 — open the element for the full check including content';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['headline'] = 'Heading';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['rewriteButton'] = 'Optimise with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['text'] = 'Text';

// ── Structure, heading and duplicate audits ─────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['duplicateWarning'] = 'SEO Studio: %s is identical to page “%s” (ID %d) — duplicates weaken both pages in the ranking.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fieldDescription'] = 'Description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fieldPageTitle'] = 'Page title';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['headingsOk'] = 'Heading structure is fine.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['multipleH1'] = '%d H1 headings in the content (“%s” …) — use exactly one H1 per page.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['needsSubheadings'] = '%d words but only one heading — subheadings improve readability and how quotable the page is.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['noH1'] = 'No H1 in the page content. Fine if the layout renders the page title as the H1 — otherwise set a heading to H1.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['noIntroParagraph'] = 'No opening paragraph found — the page needs a text paragraph at the start.';

// ── AI crawler catalogue ────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['bingbot'] = 'Bing index — the basis for ChatGPT web search and Copilot';
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['claude'] = 'Training/index for Claude';
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['googleExtended'] = 'Gemini training (not Google Search!)';

// ── SEO/GEO/AEO score notes ─────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['aiCheckSkipped'] = 'AI check skipped: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['faqNone'] = 'No FAQ published';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['faqPublished'] = '%d published FAQ entries';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['lastChanged'] = 'Last changed %d days ago';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['metaComplete'] = 'Title and description set';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['metaIncomplete'] = 'Page title/description incomplete';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureClean'] = 'Structure is clean';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureError'] = 'Structure error (H1)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureSkips'] = 'Heading levels skipped';

// ── Meta generation panel ───────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['applyButton'] = 'Insert into the fields';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['applyHint'] = 'Inserting only fills the form fields — nothing is stored until you save.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['descriptionLabel'] = 'Description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['proposeButton'] = 'Propose title & description with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['titleLabel'] = 'Title';

// ── FAQ generation ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['faq']['generateButton'] = 'Create FAQ drafts with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['faq']['generated'] = '%d FAQ drafts created (unpublished). Curate them under SEO Studio → FAQ.';

// ── Glossary panel ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['glossary']['proposeMetaButton'] = 'Propose SEO title & description with AI';

// ── Social preview ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['noTitle'] = 'No title';
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['notSavedYet'] = 'Page not saved yet.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['previewHint'] = 'This is how the page looks when shared on Facebook, LinkedIn and X. Empty fields fall back to the page title/description. The image preview appears after saving.';

// ── llms.txt output ─────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['llms']['pagesHeading'] = 'Pages';

// ── Dashboard ───────────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['aiMissing'] = '<strong>AI not connected yet.</strong> The audits (crawlers, structure, images) already run; for text and meta generation, add an AI provider and key under <a href="%s">Settings</a>.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allAllowed'] = 'all allowed';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allAssigned'] = 'all assigned';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allRoots'] = 'All start points';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['bad'] = 'weak';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barCrawlers'] = 'AI crawlers allowed';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barFreshness'] = 'Freshness (≤ 14 days) — for information only';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barImages'] = 'Images with an image size';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barMeta'] = 'Titles & descriptions';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['coverage'] = 'Coverage';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['good'] = 'good';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerAeo'] = 'Answer engines';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerGeo'] = 'Generative search';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerSeo'] = 'Classic search';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['mid'] = 'fair';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['nBlocked'] = '%d blocked';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['nOpen'] = '%d open';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['noCoverage'] = 'No coverage figures (features disabled).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['notCheckedYet'] = 'not checked yet';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['root'] = 'Start point';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreSub'] = '%d of %d pages scored — combines classic SEO, GEO (generative search) and AEO (answer engines)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreSubEstimated'] = 'estimated from coverage — run "Calculate SEO·GEO·AEO score" (Analysis) once for the full score';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreTitle'] = 'SEO · GEO · AEO score';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['showExample'] = 'Show example';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['triScorePending'] = 'The SEO/GEO/AEO breakdown appears once the score has been calculated (Analysis → "Calculate scores").';

// ── Feature toggle labels (settings) ────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['audit'] = 'Audits (robots.txt AI crawlers, structure)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['faq'] = 'FAQ generation + FAQPage schema (frontend module)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['freshness'] = 'Freshness (dateModified in schema + sitemap lastmod)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['geoScore'] = 'SEO/GEO/AEO score (per-page visibility maturity)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['glossary'] = 'AI glossary (terms + definitions, frontend module, schema)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['images'] = 'Image audit + optimization wizard';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineAltText'] = 'Inline check: alt texts (vision)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineLinkText'] = 'Inline check: link texts';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['llmsTxt'] = 'llms.txt (machine-readable site overview for AI agents)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['meta'] = 'Meta generation (page title + description)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['optimize'] = 'Text optimization (headlines + text blocks: check/rewrite/generate)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['pageScore'] = 'Per-page SEO score (focus keyword, checklist, traffic light in page list)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['schema'] = 'Structured data (JSON-LD: Organization, Breadcrumb, Article)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['social'] = 'Social media preview (Open Graph + Twitter/X cards, image + live preview)';

// ── Inline field help (settings) ────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['llmsTxt'] = 'Something other than Schema.org: <code>llms.txt</code> is a file AI systems fetch to learn in two sentences what this website is about — like a robots.txt, but for content instead of access. One sentence is enough, and the AI writes it from your real pages at the push of a button.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['llmsTxtEmpty'] = 'none yet — generate with AI or leave empty';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgLogo'] = 'The full address of your logo, starting with https://. Right-click the logo on your site → "Copy image address". Optional.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgName'] = 'The official company or website name, e.g. "V&T Innovations Ltd". While this field is empty you are missing 3 GEO points per page.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgSameAs'] = 'Your profiles elsewhere — LinkedIn, Instagram, Facebook, Wikipedia. One full URL per line. This tells search engines that those profiles and this website are the same company. Optional; leaving it empty is perfectly fine.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaArticle'] = 'For news items only: author and date are included. Without the news module this has no effect.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaBreadcrumb'] = 'The page path in the site tree. Google then shows "Home › Services › Consulting" instead of a bare URL.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaIntro'] = '<strong>What is this for?</strong> Google, ChatGPT &amp; co. read your page as text and have to guess who is behind it. Here you record that once in machine-readable form: who the company is, what it is called, where its logo and profiles are. SEO Studio turns that into an invisible data sheet (JSON-LD) on every page. You do not need to understand any of it — filling in the fields is enough. <strong>If you do only one thing: enter the company name.</strong>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaOrganization'] = 'The company details above. The basis for the info panel beside Google results.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaTypesIntro'] = '<strong>Which data sheets get delivered.</strong> When in doubt leave all three ticked — they never do harm and only apply where they fit.';

// ── Worked examples (dashboard tasks) ───────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['answerFirst'] = '<p><strong>Before</strong> — the reader waits four lines for the answer:</p><pre>In today\'s digital world, a professional online presence matters more than ever. Many companies face the challenge of standing out online. At V&amp;T we accompany you on that journey. We build websites for freelancers.</pre><p><strong>After</strong> — answer in sentence 1, reasoning after:</p><pre>We build Contao websites for freelancers and small teams. Your site is ready in four to six weeks — concept, design and implementation from one source. Afterwards you maintain the content yourself, without having to call us.</pre><p class="seo-studio-ex-hint">Test: cover everything from the second sentence on. Is the answer still there? Then it works. Cut every warm-up sentence — "In today\'s world", "Welcome", "Did you know".</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['faq'] = '<p><strong>Three real questions, phrased the way a customer asks them:</strong></p><pre>Q: What does a Contao website cost?
A: A five-page website starts at £1,900. The price depends on page count, features and design effort. You get a fixed-price quote after a 30-minute call.</pre><pre>Q: How long does it take?
A: Four to six weeks from order. Half of that time goes on content — the sooner your texts and images arrive, the sooner it goes live.</pre><pre>Q: Can I maintain the site myself afterwards?
A: Yes. Contao is built for it, and handover includes a one-hour walkthrough. After that you manage texts, images and new pages without us.</pre><p class="seo-studio-ex-hint">What makes a good FAQ answer: <em>the first sentence answers the question completely</em>, followed by one or two sentences of reasoning. No counter-questions, and never open with "it depends". Under "FAQ" the AI drafts these from your real page content.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['headings'] = '<p><strong>Wrong</strong> — two H1s, and H2 is skipped:</p><pre>H1  Welcome
H1  Our services
H3  Consulting</pre><p><strong>Right</strong> — one H1, no gaps between levels:</p><pre>H1  Web design for freelancers
H2  Our services
H3  Consulting
H3  Implementation
H2  Process and pricing</pre><p class="seo-studio-ex-hint">You set the level on the content element, in the field next to the headline (h1–h6). Anything subordinate takes the next number up — never jump two steps at once.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['meta'] = '<p><strong>Instead of empty or "Home":</strong></p><p class="seo-studio-ex-label">Page title (50–60 characters)</p><pre>Web design for freelancers — V&amp;T Innovations</pre><p class="seo-studio-ex-label">Description (120–155 characters)</p><pre>We build Contao websites for freelancers and small teams. From concept to handover — online in four weeks.</pre><p class="seo-studio-ex-hint">Rule: the title names <em>service + audience</em>, the description ends with a concrete benefit. Both fields can also be filled by "Generate titles &amp; descriptions".</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['schema'] = '<p><strong>In Settings → Structured data, field "Organization: name":</strong></p><pre>V&amp;T Innovations</pre><p class="seo-studio-ex-hint">The name your customers know you by — including the legal form if you normally use it ("Example Ltd"). One field, filled in once, applies to every page.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['structuredFormats'] = '<p><strong>Instead of prose:</strong> "We offer consulting, implementation and maintenance, and we also take care of hosting and training."</p><p><strong>As a list</strong> (content element "Unordered list"):</p><pre>Our services at a glance

• Consulting — concept, structure, audience
• Implementation — Contao website, responsive, accessible
• Maintenance — updates, backups, support
• Hosting — European servers, GDPR-compliant
• Training — so you can maintain it yourself</pre><p><strong>Or as a table</strong> (content element "Table") when you are comparing values:</p><pre>Package  | Scope            | Price
Basic    | 5 pages          | from £1,900
Business | 15 pages + blog  | from £3,900</pre><p class="seo-studio-ex-hint">Why this counts: ChatGPT and Google AI quote such blocks almost verbatim, because they can be cleanly lifted out of the page. One element per page is enough.</p>';

// ── Explainer ───────────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['explain']['body'] = '<p>The suite helps you with the SEO/GEO/AEO of your website — right inside the backend. Three areas:</p><ul><li><strong>Content &amp; Meta</strong> — generate titles/descriptions and glossary definitions with AI (empty fields only).</li><li><strong>Analysis</strong> — check crawler access, structure, duplicates, GEO score, freshness and images.</li><li><strong>FAQ</strong> and <strong>Glossary</strong> — curate and publish generated content.</li></ul><p>Individual text and headline blocks are optimized directly on the content element via "Optimize with AI".</p><p><strong>SEO Studio never generates anything on its own.</strong> Every AI action runs only on your click (or the optional cron, which is off by default). A frontend title such as <code>"Page - Site name"</code> comes from Contao itself — it appends the start point name and falls back to the navigation title when the page title is empty.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['explain']['summary'] = 'What does AI SEO Studio do? & why do titles appear "on their own"?';

// ── Dashboard to-do list ────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['allClear'] = 'All clear — nothing outstanding.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersBlocked'] = '%d AI crawler(s) blocked';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersCheckCta'] = 'Check now (Analysis)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersCta'] = 'Crawler audit (Analysis)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersUnchecked'] = 'Crawler access not checked yet';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['faqCta'] = 'Curate FAQ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['faqDrafts'] = '%d FAQ draft(s) awaiting approval';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['glossaryCta'] = 'Curate glossary';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['glossaryDrafts'] = '%d glossary draft(s) awaiting approval';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['heading'] = 'To do';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['images'] = '%d image(s) without an image size';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['imagesCta'] = 'Image assistant (Analysis)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['meta'] = '%d page(s) without a title/description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['metaCta'] = 'Open Content & Meta';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['noScore'] = 'GEO score never calculated';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['noScoreCta'] = 'GEO score (Analysis)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['stale'] = 'For information: %d page(s) unchanged for more than 14 days (does not count towards the score)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['staleCta'] = 'Freshness (Analysis)';

// ── Settings screen (fields, legends, status lines) ────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiApiKey'] = 'API key';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiBaseUrl'] = 'Base URL (only for "compatible")';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiModel'] = 'Model (empty = default)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiProvider'] = 'AI provider';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatus'] = 'Token usage this month: %s of %s (%d%%)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatusUnlimited'] = 'Token usage this month: %s (no limit set)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['cronBatchSize'] = 'Cron batch size';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keyEmpty'] = 'no key stored yet';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keySet'] = 'stored — leave empty to keep, "!delete" to remove';
$GLOBALS['TL_LANG']['SEO_STUDIO']['languageOverride'] = 'Language override (empty = root page language)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendAi'] = 'AI settings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendBehavior'] = 'Behavior';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendFeatures'] = 'Features';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendSchema'] = 'Structured data (Schema.org)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsSummaryGenerate'] = 'Generate with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsTxtSummary'] = 'llms.txt: short site description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['metaCronEnabled'] = 'Cron: auto-fill empty titles/descriptions (consumes tokens)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['monthlyTokenBudget'] = 'Monthly token budget (0 = unlimited)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['noExternalCalls'] = 'No external calls (disable AI entirely)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['save'] = 'Save';
$GLOBALS['TL_LANG']['SEO_STUDIO']['saved'] = 'Settings saved.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgLogo'] = 'Organization: logo URL';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgName'] = 'Organization: name';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgSameAs'] = 'Organization: profiles (sameAs, one URL per line)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['answerFirst'] = 'Turn the first paragraph into a direct answer (answer in sentence 1)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['checkAll'] = 'Check all pages';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['computeWithAi'] = 'Calculate score with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['faq'] = 'Generate and publish three FAQ entries';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['headings'] = 'Tidy up the headings — exactly one H1, no skipped levels';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['meta'] = 'Fill in the page title and description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['more'] = '… and %d further task(s) on other pages.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openContent'] = 'Open content';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openFaq'] = 'Open FAQ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openPage'] = 'Open page';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openSettings'] = 'Open settings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['schema'] = 'One-off: enter the organization name — lifts all %d pages (+3 each)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['structuredFormats'] = 'Add a bullet list or table element';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['unmeasured'] = 'Note: on %d page(s) the AI check never ran — those points are not missing, they were never measured.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['testConnection'] = 'Test connection';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeMode'] = 'Write mode';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModeFillEmpty'] = 'Fill empty fields only';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModePropose'] = 'Propose → preview → apply';
