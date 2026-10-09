<?php

declare(strict_types=1);

/*
 * All user-facing text of AI SEO Studio, German.
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
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['activateButton'] = 'Lizenz prüfen und aktivieren';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['activated'] = 'Die Lizenz wurde aktiviert. AI SEO Studio ist aktiv.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['confirmRemoval'] = 'Bitte das Entfernen zuerst bestätigen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['dateFormat'] = 'd.m.Y H:i';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['denied'] = 'Sie dürfen die Lizenz nicht verwalten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factActiveDomain'] = 'Aktive Domain';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factConfiguredDomains'] = 'Konfigurierte Domains';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factKey'] = 'Schlüssel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factLastVerified'] = 'Zuletzt geprüft';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factPackage'] = 'Paket';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factProduct'] = 'Produkt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factStored'] = 'Lizenz hinterlegt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factValidFrom'] = 'Gültig ab';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['factValidUntil'] = 'Gültig bis';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['failed'] = 'Die Lizenz konnte nicht geprüft werden. Bitte den Schlüssel prüfen und erneut versuchen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['invalidToken'] = 'Das Formular konnte nicht validiert werden. Bitte erneut versuchen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyHint'] = 'Für die Aktivierung erforderlich. Beim Aktualisieren einer bereits hinterlegten Lizenz leer lassen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyLabel'] = 'Lizenzschlüssel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyMissing'] = 'Bitte einen gültigen Lizenzschlüssel eingeben.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['keyPlaceholder'] = 'XXXXX-XXXXX-XXXXX-XXXXX';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['moduleBlocked'] = 'AI SEO Studio ist nicht lizenziert. Bitte die Lizenz unter Einstellungen aktivieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['no'] = 'nein';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noDomain'] = 'Für diese Installation ist keine Domain konfiguriert. Bitte zuerst die Domain am Startpunkt eintragen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noDomainHint'] = 'Für diese Installation ist keine Domain konfiguriert. Bitte die Domain am Startpunkt eintragen (Seitenstruktur → Startpunkt → „Domainname“) und diese Seite neu laden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['noneConfigured'] = 'keine konfiguriert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['nothingStored'] = 'Für diese Installation ist keine Lizenz hinterlegt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['openSettings'] = 'Zu den Einstellungen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['refreshButton'] = 'Lizenz aktualisieren';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['refreshed'] = 'Die Lizenz wurde aktualisiert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeButton'] = 'Lizenz entfernen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeConfirmLabel'] = 'Ja, die hinterlegte Lizenz von dieser Installation entfernen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeConfirmPrompt'] = 'Die hinterlegte Lizenz entfernen? Alle Funktionen von AI SEO Studio werden sofort deaktiviert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removeHint'] = 'Das Entfernen deaktiviert alle Funktionen von AI SEO Studio sofort. Seiten, FAQ- und Glossar-Einträge bleiben erhalten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['removed'] = 'Die Lizenz wurde entfernt. AI SEO Studio ist inaktiv.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_licensed'] = 'Pro-Lizenz aktiv. Alle Funktionen freigeschaltet.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_absent'] = 'Keine Lizenz hinterlegt. Bitte den Lizenzschlüssel eingeben, um AI SEO Studio zu aktivieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_expired'] = 'Lizenz abgelaufen. AI SEO Studio ist inaktiv, bis eine gültige Lizenz vorliegt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_lease_expired'] = 'Diese Lizenz konnte zu lange nicht mit dem Lizenzserver bestätigt werden. AI SEO Studio ist inaktiv, bis die Verbindung wieder funktioniert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_needs_refresh'] = 'Die hinterlegte Lizenz stammt aus einem älteren Lizenzformat. Bitte „Lizenz aktualisieren“ verwenden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_no_host_match'] = 'Die hinterlegte Lizenz gilt für keine auf dieser Installation konfigurierte Domain.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_not_started'] = 'Die Lizenz ist noch nicht gültig.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_package_not_accepted'] = 'Die hinterlegte Lizenz gilt nicht für dieses Produkt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_revoked'] = 'Diese Lizenz wurde auf eine andere Installation übertragen und gilt für keine hier konfigurierte Domain mehr.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['status_unlicensed_unverifiable'] = 'Die hinterlegte Lizenz konnte nicht geprüft werden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['unlimited'] = 'unbegrenzt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['unreachable'] = 'Der Lizenzserver war nicht erreichbar. Der aktuelle Lizenzstatus bleibt unverändert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['yes'] = 'ja';

// ── Errors and refusals returned by endpoints and services ──────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['actionFailedPrefix'] = 'Aktion fehlgeschlagen: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiNoText'] = 'KI lieferte keinen Text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['insufficientContext'] = 'Zu wenig Kontext auf der Seite, um Inhalt zu erzeugen — bitte erst Text ergänzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['summaryGenerationFailed'] = 'Zusammenfassung fehlgeschlagen: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['apiKeyMissing'] = 'Kein API-Schlüssel hinterlegt. Bitte in den Einstellungen setzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['egressBlocked'] = 'Externe Aufrufe sind deaktiviert („Keine externen Aufrufe“). Bitte erst in den Einstellungen freigeben.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['egressBlockedTest'] = 'Test nicht ausgeführt: „Keine externen Aufrufe“ ist aktiv. Zum Testen erst freigeben.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['featureDisabled'] = 'Funktion ist deaktiviert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidBaseUrl'] = 'Ungültige Basis-URL. Nur http(s) mit Hostname erlaubt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidEntryId'] = 'Ungültige Eintrags-ID.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidPageId'] = 'Ungültige Seiten-ID.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['invalidRequest'] = 'Ungültige Anfrage.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noLicence'] = 'Keine gültige Lizenz.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noTheme'] = 'Kein Theme vorhanden — Bildgrößen brauchen ein Theme.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['notLicensed'] = 'AI SEO Studio ist nicht lizenziert. Bitte die Lizenz unter Einstellungen aktivieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['notLoggedIn'] = 'Nicht angemeldet.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['pageNotFound'] = 'Seite nicht gefunden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['pageNotSelected'] = 'Bitte eine Seite auswählen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['providerNonJson'] = '%s lieferte einen 200 mit nicht-JSON-Body.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiIncompleteAnswer'] = 'KI-Antwort unvollständig — bitte erneut prüfen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiIncompleteResponse'] = 'KI-Antwort war unvollständig.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiInvalidJson'] = 'KI-Antwort war kein gültiges JSON.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiNetworkError'] = 'Netzwerkfehler zur %s-API: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiNoContent'] = '%s lieferte keinen Inhalt (%s: %s).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['aiNoSummary'] = 'KI lieferte keine Zusammenfassung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['budgetExhausted'] = 'Monatliches Token-Budget (%s) ist aufgebraucht. KI-Funktionen sind bis Monatsende pausiert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['configurationError'] = 'Konfigurationsfehler: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['connectionFailed'] = 'Verbindung fehlgeschlagen: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['elementNotFound'] = 'Element nicht gefunden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['generationFailed'] = 'Generierung fehlgeschlagen: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['glossaryEntryNotFound'] = 'Glossar-Eintrag nicht gefunden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['homepageNoContent'] = 'Die Startseite hat keinen extrahierbaren Inhalt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noExtractableContent'] = 'Die Seite hat keinen extrahierbaren Inhalt — bitte erst Inhalte anlegen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noExtractableContentFaq'] = 'Die Seite hat keinen extrahierbaren Inhalt — FAQ-Generierung braucht Text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['error']['noPublishedHomepage'] = 'Keine veröffentlichte Startseite gefunden.';

// ── Per-page SEO checklist (PageSeoAnalyzer) ────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['activeVoice']['label'] = 'Aktive Sprache';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altComplete']['label'] = 'Alle Bilder mit Alt-Text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altMissing']['hint'] = 'Alt-Texte sind wichtig für Bild-SEO und Barrierefreiheit.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['altMissing']['label'] = '%d Bild(er) ohne Alt-Text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['charactersCount'] = '%d Zeichen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionLength']['hint'] = '%d Zeichen — ideal sind 120–155.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionLength']['label'] = 'Beschreibungs-Länge';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionMissing']['hint'] = 'Suchmaschinen zeigen sonst zufälligen Text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionMissing']['label'] = 'Meta-Beschreibung fehlt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['descriptionSet']['label'] = 'Meta-Beschreibung gesetzt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['enoughText']['label'] = 'Ausreichend Text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['imagesCount'] = '%d Bild(er).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordAbsent']['hint'] = 'Das Fokus-Keyword sollte im Fließtext auftauchen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordAbsent']['label'] = 'Keyword kommt im Text nicht vor';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityGood']['hint'] = '%.1f %%.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityGood']['label'] = 'Keyword-Dichte gut';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityHigh']['hint'] = '%.1f %% — wirkt schnell wie Spam (ideal 0,5–2,5 %%).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityHigh']['label'] = 'Keyword-Dichte hoch';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityLow']['hint'] = '%.1f %% — ruhig etwas häufiger nennen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordDensityLow']['label'] = 'Keyword-Dichte niedrig';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInDescription']['hint'] = 'Erhöht die Klickrate in den Suchergebnissen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInDescription']['label'] = 'Keyword in der Beschreibung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInFirstParagraph']['hint'] = 'Früh im Text nennt das Thema klar.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInFirstParagraph']['label'] = 'Keyword im ersten Absatz';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInH1']['hint'] = 'Die Hauptüberschrift sollte das Keyword enthalten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInH1']['label'] = 'Keyword in der H1';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInSubheading']['hint'] = 'Verstärkt die thematische Relevanz.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInSubheading']['label'] = 'Keyword in einer Zwischenüberschrift';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInTitle']['hint'] = '„%s“ sollte im Titel stehen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInTitle']['label'] = 'Keyword im Seitentitel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInUrl']['hint'] = 'Ein sprechender Alias mit Keyword hilft.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['keywordInUrl']['label'] = 'Keyword in der URL';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['littleText']['hint'] = '%d Wörter — mehr Inhalt hilft dem Ranking.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['littleText']['label'] = 'Wenig Text';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['multipleH1']['hint'] = 'Genau eine H1 pro Seite verwenden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['multipleH1']['label'] = '%d H1-Überschriften';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noH1']['hint'] = 'OK, wenn das Layout den Seitentitel als H1 rendert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noH1']['label'] = 'Keine H1 im Inhalt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noSubheadings']['hint'] = 'Zwischenüberschriften verbessern Lesbarkeit und AEO.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noSubheadings']['label'] = 'Keine Zwischenüberschriften';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noText']['hint'] = 'Die Seite hat keinen erfassbaren Text.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['noText']['label'] = 'Kein Textinhalt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['passiveHigh']['hint'] = 'Aktive Formulierungen wirken direkter.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['passiveHigh']['label'] = 'Viel Passiv';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityGood']['hint'] = 'Lesbarkeit %d/100.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityGood']['label'] = 'Gut verständlich';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityHard']['hint'] = 'Lesbarkeit %d/100 — kürzere Wörter und Sätze.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityHard']['label'] = 'Schwer verständlich';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityMedium']['hint'] = 'Lesbarkeit %d/100 — einfacher formulieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['readabilityMedium']['label'] = 'Mittel verständlich';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsRepeated']['hint'] = '%d Sätze in Folge beginnen gleich — für Abwechslung sorgen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsRepeated']['label'] = 'Gleiche Satzanfänge';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentenceStartsVaried']['label'] = 'Abwechslungsreiche Satzanfänge';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesGood']['hint'] = 'Ø %.0f Wörter/Satz.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesGood']['label'] = 'Sätze gut lesbar';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesLongish']['hint'] = 'Ø %.0f Wörter/Satz — kürzere Sätze lesen sich leichter.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesLongish']['label'] = 'Sätze etwas lang';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesTooLong']['hint'] = 'Ø %.0f Wörter/Satz — deutlich kürzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['sentencesTooLong']['label'] = 'Sätze zu lang';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['singleH1']['label'] = 'Genau eine H1';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['subheadings']['hint'] = '%d Stück.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['subheadings']['label'] = 'Zwischenüberschriften vorhanden';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleLength']['hint'] = '%d Zeichen — ideal sind 30–60.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleLength']['label'] = 'Seitentitel-Länge';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleMissing']['hint'] = 'Ohne Titel keine gute Suchdarstellung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleMissing']['label'] = 'Seitentitel fehlt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['titleSet']['label'] = 'Seitentitel gesetzt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsFew']['hint'] = '%d %% — Verbindungen wie „außerdem“, „daher“ führen den Leser.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsFew']['label'] = 'Wenige Übergangswörter';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsGood']['hint'] = '%d %% der Sätze mit Übergangswörtern.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsGood']['label'] = 'Gute Textführung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsNone']['hint'] = 'Nur %d %% der Sätze verbunden — „außerdem“, „daher“, „zunächst“ einbauen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['transitionsNone']['label'] = 'Kaum Übergangswörter';
$GLOBALS['TL_LANG']['SEO_STUDIO']['check']['wordsCount'] = '%d Wörter.';

// ── Feld-Score („Mit KI optimieren“-Panel, FieldScorer) ──────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['avgWordsPerSentence'] = 'Ø %.0f Wörter/Satz';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['charCount'] = '%d Zeichen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['firstSentenceWords'] = '%d Wörter im 1. Satz';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['activeVoice']['fix'] = 'Passiv vermeiden — aktiv formulieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['answersIntent']['fix'] = 'Zu vage — entweder als Frage formulieren oder das Thema mit mindestens vier bedeutungstragenden Wörtern benennen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['answersIntent']['label'] = 'Beantwortet ein Nutzeranliegen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['concrete']['fix'] = 'Zu allgemein — benenne das konkrete Thema statt einer Floskel wie „Unsere Leistungen“.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['concrete']['label'] = 'Konkret statt Floskel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['duplicate']['fix'] = 'Eine andere Überschrift der Seite lautet fast gleich — klar abgrenzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['duplicate']['label'] = 'Keine Dopplung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['format']['fix'] = 'Keine Anführungszeichen, kein Markdown, kein HTML.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['format']['label'] = 'Sauberes Format';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['length']['label'] = 'Länge 30–65 Zeichen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['length']['tooLong'] = 'Zu lang (%d Zeichen) — auf höchstens 65 Zeichen kürzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['length']['tooShort'] = 'Zu kurz (%d Zeichen) — auf 30–65 Zeichen ausbauen und konkreter werden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['notEmpty']['fix'] = 'Überschrift darf nicht leer sein.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['headline']['notEmpty']['label'] = 'Nicht leer';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['keywordMissing']['fix'] = 'Das Fokus-Keyword „%s“ kommt nicht vor — nur einbauen, wenn es thematisch wirklich passt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['keywordPresent']['label'] = 'Fokus-Keyword enthalten';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['paragraphCount'] = '%d Absätze';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['questionForm'] = 'Frageform';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['readabilityScore'] = 'Lesbarkeit %d/100';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['repeatCount'] = '%d Wiederholung(en)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['statementForm'] = 'Aussageform';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['allowedHtml']['fix'] = 'Nur <p>, <strong>, <em>, <ul>, <ol>, <li> verwenden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['allowedHtml']['label'] = 'Erlaubtes HTML';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['answerFirst']['fix'] = 'Der erste Satz muss das Thema direkt beantworten und höchstens 25 Wörter haben.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['answerFirst']['label'] = 'Antwort-zuerst';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['minLength']['fix'] = 'Zu wenig Text (%d Wörter) — auf mindestens %d Wörter ausbauen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['minLength']['label'] = 'Mindestlänge (40 Wörter)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['noFiller']['fix'] = 'Floskeln streichen („In der heutigen Zeit“, „Willkommen“).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['noFiller']['label'] = 'Keine Floskeln';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['noRepeats']['fix'] = '%d Satz/Sätze wiederholen sich wörtlich — der Text muss echten Inhalt liefern, nicht denselben Satz vervielfachen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['noRepeats']['label'] = 'Keine Satzwiederholungen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['paragraphs']['fix'] = 'Langer Text in einem Block — in mehrere Absätze gliedern.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['paragraphs']['label'] = 'Gegliederte Absätze';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['readability']['fix'] = 'Lesbarkeit %d/100 — kürzere Wörter und Sätze verwenden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['readability']['label'] = 'Verständlichkeit';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['shortSentences']['fix'] = 'Sätze zu lang (Ø %.0f Wörter) — auf höchstens 18 Wörter je Satz kürzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['shortSentences']['label'] = 'Kurze Sätze';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['substantial']['fix'] = 'Nur %d Wörter — Suchmaschinen bevorzugen Substanz, Richtwert %d+ Wörter.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['substantial']['label'] = 'Substanzieller Umfang (120+ Wörter)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['tooPassive']['fix'] = 'Zu viel Passiv — aktiv umformulieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['transitions']['fix'] = 'Nur %d %% der Sätze mit Verbindungswörtern — „außerdem“, „daher“, „zunächst“ führen den Leser.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['transitions']['label'] = 'Übergangswörter';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['variety']['fix'] = 'Sehr viele Wortwiederholungen (nur %.0f %% verschiedene Wörter) — abwechslungsreich und inhaltlich formulieren.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['text']['variety']['label'] = 'Sprachliche Vielfalt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['transitionsPercent'] = '%d %% der Sätze';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['varietyPercent'] = '%.0f %% verschiedene Wörter';
$GLOBALS['TL_LANG']['SEO_STUDIO']['fieldScore']['wordCount'] = '%d Wörter';

// ── SEO score panel ─────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['aiSuggestion'] = 'KI-Vorschlag';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['allGood'] = 'alles im grünen Bereich';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['analysisFailed'] = 'SEO-Analyse nicht möglich: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['fixWithAi'] = 'mit KI beheben';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['generateMeta'] = 'KI: Titel & Beschreibung erzeugen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['keywordTip'] = 'Tipp: Trage oben ein <strong>Fokus-Keyword</strong> ein (oder lass es dir per KI vorschlagen) — dann prüft SEO Studio zusätzlich, ob es an den richtigen Stellen vorkommt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['noContent'] = 'Noch kein analysierbarer Inhalt auf dieser Seite.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['panelTitle'] = 'SEO-Bewertung dieser Seite';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['suggestKeyword'] = 'KI: Fokus-Keyword vorschlagen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['score']['warningCount'] = '%d Hinweis(e)';

// ── Text / headline optimiser ───────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['altText']['loadFailed'] = 'Bild konnte nicht geladen werden (fehlt, zu groß oder kein JPEG/PNG/WebP/GIF).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['applyButton'] = 'In Feld übernehmen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['badgeTitle'] = 'SEO-Formcheck: %d/100 — Element öffnen für die vollständige Prüfung inklusive Inhalt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['explainAllSatisfied'] = 'Alle Kriterien erfüllt: %s.%s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['explainHintPrefix'] = ' Hinweis: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['explainOpenSuffix'] = ' — Offen: %s%s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['explainSatisfiedPrefix'] = 'Erfüllt: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['headline'] = 'Überschrift';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['linkText']['empty'] = 'Kein Linktext gesetzt — Screenreader und KI-Crawler sehen nur die URL.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['linkText']['good'] = 'Aussagekräftiger Linktext.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['linkText']['vague'] = 'Floskel-Linktext („%s“) — beschreibt das Linkziel nicht.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['rewriteButton'] = 'Mit KI optimieren';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticCoherent']['fix'] = 'Die Aussagen hängen inhaltlich nicht zusammen%s.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticCoherent']['label'] = 'Roter Faden';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticOnTopic']['fix'] = 'Der Inhalt passt nicht zum Thema dieser Seite.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticOnTopic']['label'] = 'Zum Seitenthema passend';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticSubstantial']['fix'] = 'Zu wenig Substanz — konkrete, nachvollziehbare Aussagen statt allgemeinem Gerede.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['semanticSubstantial']['label'] = 'Konkrete Aussagen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['optimize']['text'] = 'Text';

// ── Structure, heading and duplicate audits ─────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['duplicateWarning'] = 'SEO Studio: %s ist identisch mit Seite „%s“ (ID %d) — Duplikate schwächen beide Seiten im Ranking.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fieldDescription'] = 'Beschreibung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fieldPageTitle'] = 'Seitentitel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['headingsOk'] = 'Überschriften-Struktur in Ordnung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['multipleH1'] = '%d H1-Überschriften im Inhalt („%s“ …) — genau eine H1 pro Seite.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['needsSubheadings'] = '%d Wörter, aber nur eine Überschrift — Zwischenüberschriften verbessern Lesbarkeit und AEO-Zitierfähigkeit.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['noH1'] = 'Keine H1 im Seiteninhalt. OK, wenn das Layout den Seitentitel als H1 rendert — sonst eine Überschrift auf H1 stellen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['noIntroParagraph'] = 'Kein Einstiegsabsatz gefunden — die Seite braucht einen Textabsatz am Anfang.';

// ── Analyse-Modul (SEO Studio → Analyse) ─────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['answerFirstHeading'] = 'Antwort-zuerst-Einstieg: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['answerFirstSkipped'] = 'Antwort-zuerst-Check übersprungen: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['answerFirstSuggestion'] = 'Vorschlag: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['badgeAllowed'] = 'erlaubt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['badgeBlocked'] = 'blockiert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['badgeImplicit'] = ' (implizit)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['colAsOf'] = 'Stand';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['colPage'] = 'Seite';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['colPurpose'] = 'Zweck';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['colWeaknesses'] = 'Schwachstellen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerCheckButton'] = 'Jetzt prüfen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerCheckDone'] = 'Crawler-Prüfung abgeschlossen: %d Domain(s).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerHelp'] = 'Prüft für jede Domain, ob KI-Crawler (ChatGPT, Claude, Perplexity, Gemini …) die Website lesen dürfen. Blockierte Crawler bedeuten: die Website kann in KI-Antworten nicht zitiert werden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerLastChecked'] = 'Letzte Prüfung: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerLegend'] = 'KI-Crawler-Audit (robots.txt)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['crawlerNotCheckedYet'] = 'Noch keine Prüfung durchgeführt — „Jetzt prüfen“ klicken.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['disabledNotice'] = 'Alle Analyse-Funktionen sind deaktiviert (SEO Studio → Einstellungen).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['duplicateFound'] = '%s „%s“ auf %d Seiten: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['duplicatesLegend'] = 'Duplikate (Seitentitel / Beschreibung)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['duplicatesNone'] = 'Keine doppelten Seitentitel oder Beschreibungen gefunden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fixSuggestionHeading'] = 'Korrektur-Vorschlag';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['fixSuggestionHelp'] = 'Diesen Block in den Startpunkt der Website (Seitenstruktur → Root-Seite → Feld „Eigene robots.txt-Einträge“) einfügen:';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['freshnessAllFresh'] = 'Alle veröffentlichten Seiten wurden in den letzten 14 Tagen aktualisiert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['freshnessChangedDaysAgo'] = ' — vor %d Tagen geändert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['freshnessIntro'] = 'KI-Suchmaschinen gewichten Aktualität. Älteste zuerst:';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['freshnessLegend'] = 'Aktualitäts-Monitor (älter als 14 Tage)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['geoScoreComputeButton'] = 'Scores berechnen (ohne KI)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['geoScoreComputeLlmButton'] = 'Scores berechnen (mit KI-Check)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['geoScoreEmpty'] = 'Noch keine Scores — „Scores berechnen“ klicken.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['geoScoreHelp'] = 'Kombinierter Score aus klassischem <strong>SEO</strong>, <strong>GEO</strong> (generative Suche) und <strong>AEO</strong> (Antwort-Engines). Bewertet pro Seite: Meta, Überschriften, Antwort-zuerst-Einstieg, strukturierte Formate, FAQ, Aktualität, Schema. 10 Seiten pro Durchlauf.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['geoScoreLegend'] = 'SEO/GEO/AEO-Score (Sichtbarkeits-Reifegrad)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imageSizeAssigned'] = 'Bildgröße „SEO Studio Responsiv“ (ID %d) %d Element(en) zugewiesen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesAllAssigned'] = 'Alle Bild-Elemente haben eine Bildgrößen-Zuweisung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesAssignButton'] = 'Automatisch zuweisen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesAssignConfirm'] = 'Bildgröße „SEO Studio Responsiv“ (1200px, proportional, Lazy-Loading) anlegen und %d Element(en) zuweisen?';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesLegend'] = 'Bild-Audit (Performance)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesOversizedHeading'] = 'Übergroße Originale';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesOversizedHint'] = 'Empfehlung: Originale vor dem Upload auf max. 2560 px verkleinern.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['imagesUnassigned'] = '%d Bild-Element(e) ohne Bildgrößen-Zuweisung — Originale werden unskaliert ausgeliefert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['intro'] = '<p>Prüfungen und Bewertungen deiner Website. Nichts wird verändert — du bekommst nur Berichte und Korrektur-Empfehlungen. Der GEO-Score und der Bild-Assistent sind die einzigen Aktionen mit Schreibzugriff (jeweils per Klick).</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['robotsLoadError'] = 'robots.txt konnte nicht geladen werden: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['robotsMissing'] = 'Keine robots.txt gefunden — damit dürfen alle Crawler zugreifen, aber es wird keine Sitemap angekündigt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['scoresComputed'] = '%d Seite(n) bewertet%s.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['scoresDeterministic'] = ' (deterministisch)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['scoresWithLlm'] = ' (mit KI-Check)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['sitemapAnnounced'] = 'Sitemap angekündigt: %s';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['sitemapMissing'] = 'Keine Sitemap-Zeile in der robots.txt. Empfehlung: <code>Sitemap: https://%s/sitemap.xml</code> ergänzen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureCheckButton'] = 'Seite prüfen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureDone'] = 'Struktur-Audit abgeschlossen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureHelp'] = 'Deterministische Überschriften-Prüfung plus KI-Check, ob der Einstiegsabsatz das Thema direkt beantwortet (AEO). Prüft eine einzelne Seite%s.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureLegend'] = 'Struktur-Audit (Überschriften + Antwort-zuerst)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureNoPages'] = 'Keine Seiten im gewählten Startpunkt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureResultHeading'] = 'Ergebnis (%s)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['structureScopeSuffix'] = ' aus dem gewählten Startpunkt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabCrawler'] = 'KI-Crawler';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabDuplicates'] = 'Duplikate';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabFreshness'] = 'Aktualität';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabGeoScore'] = 'SEO/GEO/AEO-Score';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabImages'] = 'Bilder';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['tabStructure'] = 'Struktur';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['webpConfigured'] = 'Modernes Bildformat (WebP/AVIF) ist konfiguriert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['webpDisabledHeading'] = 'WebP nicht aktiviert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['audit']['webpHelp'] = 'Moderne Formate sparen 25-60 % Dateigröße. Diesen Block in <code>config/config.yaml</code> einfügen (SEO Studio schreibt diese Datei bewusst nie selbst):';

// ── AI crawler catalogue ────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['bingbot'] = 'Bing-Index — Grundlage für ChatGPT-Websuche und Copilot';
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['claude'] = 'Training/Index für Claude';
$GLOBALS['TL_LANG']['SEO_STUDIO']['crawler']['googleExtended'] = 'Gemini-Training (nicht die Google-Suche!)';

// ── SEO/GEO/AEO score notes ─────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['aiCheckSkipped'] = 'KI-Check übersprungen: ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['faqNone'] = 'Keine FAQ veröffentlicht';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['faqPublished'] = '%d veröffentlichte FAQ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['lastChanged'] = 'Letzte Änderung vor %d Tagen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['metaComplete'] = 'Titel + Beschreibung gesetzt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['metaIncomplete'] = 'Seitentitel/Beschreibung unvollständig';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureClean'] = 'Struktur sauber';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureError'] = 'Struktur-Fehler (H1)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['geo']['structureSkips'] = 'Ebenen-Sprünge';

// ── Meta generation panel ───────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['applyButton'] = 'In Felder übernehmen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['applyHint'] = 'Übernahme füllt nur die Formularfelder — gespeichert wird erst mit „Speichern“.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['descriptionLabel'] = 'Beschreibung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['proposeButton'] = 'Titel & Beschreibung mit KI vorschlagen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['meta']['titleLabel'] = 'Titel';

// ── FAQ generation ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['faq']['generateButton'] = 'FAQ-Entwürfe mit KI erstellen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['faq']['generated'] = '%d FAQ-Entwürfe erstellt (unveröffentlicht). Kuratieren unter SEO Studio → FAQ.';

// ── Glossary panel ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['glossary']['proposeMetaButton'] = 'SEO-Titel & -Beschreibung mit KI vorschlagen';

// ── Social preview ──────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['noTitle'] = 'Kein Titel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['notSavedYet'] = 'Seite noch nicht gespeichert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['social']['previewHint'] = 'So erscheint die Seite geteilt auf Facebook, LinkedIn & X. Leere Felder greifen auf Seitentitel/Beschreibung zurück. Bild-Vorschau nach dem Speichern.';

// ── llms.txt output ─────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['llms']['pagesHeading'] = 'Seiten';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llms']['summaryGenerated'] = 'llms.txt-Zusammenfassung erzeugt: „%s“';

// ── Dashboard ───────────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['aiMissing'] = '<strong>KI noch nicht verbunden.</strong> Die Prüfungen (Crawler, Struktur, Bilder) laufen bereits; für Text- und Meta-Generierung unter <a href="%s">Einstellungen</a> einen KI-Anbieter + Schlüssel eintragen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allAllowed'] = 'alle erlaubt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allAssigned'] = 'alle zugewiesen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['allRoots'] = 'Alle Startpunkte';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['bad'] = 'schwach';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barCrawlers'] = 'KI-Crawler erlaubt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barFreshness'] = 'Aktualität (≤ 14 Tage) — nur zur Info';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barImages'] = 'Bilder mit Bildgröße';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['barMeta'] = 'Titel & Beschreibungen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['coverage'] = 'Abdeckung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['good'] = 'gut';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerAeo'] = 'Antwort-Engines';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerGeo'] = 'Generative Suche';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['layerSeo'] = 'Klassische Suche';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['mid'] = 'mittel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['nBlocked'] = '%d blockiert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['nOpen'] = '%d offen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['noCoverage'] = 'Keine Abdeckungs-Kennzahlen (Funktionen deaktiviert).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['notCheckedYet'] = 'noch nicht geprüft';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['root'] = 'Startpunkt';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreSub'] = '%d von %d Seiten bewertet — kombiniert klassisches SEO, GEO (generative Suche) und AEO (Antwort-Engines)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreSubEstimated'] = 'geschätzt aus Abdeckung — für den vollen SEO/GEO/AEO-Score einmal „SEO·GEO·AEO-Score berechnen“ (Analyse) laufen lassen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['scoreTitle'] = 'SEO · GEO · AEO Score';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['showExample'] = 'Beispiel ansehen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['triScorePending'] = 'SEO-/GEO-/AEO-Aufteilung erscheint, sobald der Score berechnet ist (Analyse → „Scores berechnen“).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['dash']['untitledPage'] = 'Seite %d';

// ── Content generation panel (Inhalte & Meta) ───────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['disabledNotice'] = 'Meta- und Glossar-Funktion sind deaktiviert (SEO Studio → Einstellungen).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['failedNote'] = ' (%d fehlgeschlagen)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqCountOption'] = '%d Fragen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqCreateButton'] = 'FAQ-Entwürfe erstellen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqDraftsOpen'] = ' (%d Entwürfe offen)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqGenerated'] = '%d FAQ-Entwurf/-Entwürfe erstellt (unveröffentlicht). Kuratieren unter SEO Studio → FAQ.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqHelp'] = 'Erzeugt aus dem Inhalt einer Seite FAQ-Entwürfe (unveröffentlicht) für FAQPage-Schema und Antwort-Engines. Kuratieren und veröffentlichen unter <strong>SEO Studio → FAQ</strong>%s. Die gleiche Funktion findest du auch direkt im SEO-Studio-Panel jeder Seite.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqLegend'] = 'FAQ-Generierung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['faqNoPages'] = 'Keine veröffentlichten Seiten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryDraftsNote'] = ' (%d unveröffentlichte Entwürfe)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryGenerateButton'] = 'Definitionen generieren (Entwürfe)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryGenerated'] = '%d Definition(en) als Entwurf erstellt%s. Kuratieren unter SEO Studio → Glossar.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryHelp'] = '%d Eintrag/Einträge%s — Kuratierung unter <strong>SEO Studio → Glossar</strong>, Ausgabe über das Frontend-Modul „Glossar (SEO Studio)“.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryImportButton'] = 'Aus Glossar-Bundle importieren (%d)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryImportConfirm'] = '%d Einträge aus dem alten Glossar-Bundle importieren? Bestehende Begriffe werden übersprungen, Alt-Daten bleiben unverändert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryImported'] = '%d Glossar-Eintrag/-Einträge importiert, %d übersprungen. Das alte Glossar-Bundle kann deinstalliert werden.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryLegend'] = 'KI-Glossar';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossarySkippedNote'] = ' (%d bereits vorhanden, übersprungen)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossarySuggestButton'] = 'Begriffe aus Website vorschlagen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossarySuggestionsPrefix'] = 'Begriffs-Vorschläge (in das Textfeld kopieren): ';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryTermsLabel'] = 'Begriffe (einer pro Zeile)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['glossaryTermsPlaceholder'] = 'Content-Management-System&#10;Responsive Design';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['intro'] = '<p>Hier erzeugst du Inhalte per KI. Jede Aktion läuft nur auf Klick und füllt <strong>nur leere Felder</strong> (bestehende Inhalte werden nie überschrieben). Einzelne Text- und Überschriften-Blöcke optimierst du direkt am jeweiligen Inhaltselement über den Button „Mit KI optimieren“.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaAllDone'] = 'Alle veröffentlichten Seiten haben Titel und Beschreibung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaBulkComplete'] = '%d Seite(n) befüllt%s. Alle Titel und Beschreibungen sind jetzt gesetzt.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaBulkPartial'] = '%d Seite(n) befüllt%s — %d verbleiben. Erneut klicken oder den Cron übernehmen lassen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaGenerateButton'] = 'Jetzt generieren (nur leere Felder)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaHelp'] = 'Füllt fehlende <code>Seitentitel</code> und <code>Beschreibung</code> im Reiter „Metadaten“ jeder Seite. 10 Seiten pro Durchlauf.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaLegend'] = 'Seitentitel &amp; Beschreibungen (Meta)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['metaOpenCount'] = '%d Seite(n) mit leerem Titel oder leerer Beschreibung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['rootOpenSuffix'] = ' (%d Seite(n) offen)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['tabGlossary'] = 'Glossar';
$GLOBALS['TL_LANG']['SEO_STUDIO']['generate']['tabMeta'] = 'Seitentitel & Meta';

// ── Feature toggle labels (settings) ────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['audit'] = 'Audits (robots.txt KI-Crawler, Struktur)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['disabledHint'] = 'Deaktivierte Funktionen verschwinden komplett aus dem Backend (Menüpunkte, Buttons, Panels).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['faq'] = 'FAQ-Generierung + FAQPage-Schema (Frontend-Modul)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['freshness'] = 'Freshness (dateModified im Schema + Sitemap lastmod)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['geoScore'] = 'SEO/GEO/AEO-Score (Sichtbarkeits-Reifegrad pro Seite)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['glossary'] = 'KI-Glossar (Begriffe + Definitionen, Frontend-Modul, Schema)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['images'] = 'Bild-Audit + Optimierungs-Assistent';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineAltText'] = 'Inline-Check: Alt-Texte (Vision)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineLinkText'] = 'Inline-Check: Linktexte';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['llmsTxt'] = 'llms.txt (maschinenlesbare Website-Übersicht für KI-Agenten)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['meta'] = 'Meta-Generierung (Seitentitel + Beschreibung)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['optimize'] = 'Text-Optimierung (Überschriften + Textblöcke: Check/Umschreiben/Generieren)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['pageScore'] = 'SEO-Bewertung pro Seite (Fokus-Keyword, Checkliste, Ampel in der Seitenliste)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['schema'] = 'Strukturierte Daten (JSON-LD: Organization, Breadcrumb, Article)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['social'] = 'Social-Media-Vorschau (Open Graph + Twitter/X Cards, Bild + Live-Vorschau)';

// ── Inline field help (settings) ────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['llmsTxt'] = 'Etwas anderes als Schema.org: <code>llms.txt</code> ist eine Datei, die KI-Systeme aufrufen, um in zwei Sätzen zu erfahren, worum es auf dieser Website geht — wie eine robots.txt, nur für Inhalt statt Zugriff. Ein Satz genügt, KI schreibt ihn auf Knopfdruck aus deinen echten Seiten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['llmsTxtEmpty'] = 'noch keine — per KI erzeugen oder leer lassen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgLogo'] = 'Vollständige Adresse deines Logos, beginnend mit https://. Rechtsklick aufs Logo im Frontend → „Bildadresse kopieren“. Optional.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgName'] = 'Der offizielle Firmen- oder Websitename, z. B. „V&T Innovations GmbH“. Solange dieses Feld leer ist, fehlen dir 3 GEO-Punkte pro Seite.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['orgSameAs'] = 'Deine Profile anderswo — LinkedIn, Instagram, Facebook, XING, Wikipedia. Eine vollständige URL pro Zeile. Damit erkennen Suchmaschinen, dass diese Profile und diese Website dieselbe Firma sind. Optional, leer lassen ist völlig in Ordnung.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaArticle'] = 'Nur für Nachrichten-Beiträge: Autor und Datum werden mitgeliefert. Ohne News-Modul wirkungslos.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaBreadcrumb'] = 'Der Pfad der Seite im Seitenbaum. Google zeigt dann „Start › Leistungen › Beratung“ statt einer nackten URL.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaIntro'] = '<strong>Wozu das Ganze?</strong> Google, ChatGPT &amp; Co. lesen deine Seite als Text und müssen raten, wer dahintersteht. Hier hinterlegst du das einmal maschinenlesbar: Wer ist die Firma, wie heißt sie, wo ist ihr Logo, wo ihre Profile. SEO Studio schreibt daraus ein unsichtbares Datenblatt (JSON-LD) in jede Seite. Du musst nichts davon können — Felder ausfüllen genügt. <strong>Wenn du nur eine Sache machst: trag den Firmennamen ein.</strong>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaOrganization'] = 'Die Firmenangaben von oben. Grundlage für den Info-Kasten rechts neben den Google-Treffern.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['help']['schemaTypesIntro'] = '<strong>Welche Datenblätter ausgeliefert werden.</strong> Im Zweifel alle drei angehakt lassen — sie schaden nie und greifen nur, wo sie passen.';

// ── Worked examples (dashboard tasks) ───────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['answerFirst'] = '<p><strong>Vorher</strong> — der Leser wartet vier Zeilen auf die Antwort:</p><pre>In der heutigen digitalen Welt ist eine professionelle Onlinepräsenz wichtiger denn je. Viele Unternehmen stehen vor der Herausforderung, sich im Netz zu behaupten. Wir bei V&amp;T begleiten Sie auf diesem Weg. Wir entwickeln Websites für Freelancer.</pre><p><strong>Nachher</strong> — Antwort in Satz 1, Begründung danach:</p><pre>Wir entwickeln Contao-Websites für Freelancer und kleine Teams. In vier bis sechs Wochen steht deine Seite — Konzept, Design und Umsetzung aus einer Hand. Danach pflegst du die Inhalte selbst, ohne uns anrufen zu müssen.</pre><p class="seo-studio-ex-hint">Test: Deck alles ab dem zweiten Satz ab. Steht die Antwort trotzdem da? Dann passt es. Streiche jeden Aufwärmsatz — „In der heutigen Zeit“, „Willkommen“, „Wussten Sie schon“.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['faq'] = '<p><strong>Drei echte Fragen, wie ein Kunde sie stellt:</strong></p><pre>F: Was kostet eine Contao-Website?
A: Eine Website mit fünf Seiten kostet ab 1.900 €. Der Preis hängt von Seitenzahl, Funktionen und Designaufwand ab. Ein Festpreis-Angebot bekommst du nach einem 30-minütigen Gespräch.</pre><pre>F: Wie lange dauert die Umsetzung?
A: Vier bis sechs Wochen ab Auftrag. Die Hälfte der Zeit entfällt auf Inhalte — je früher deine Texte und Bilder da sind, desto schneller geht es live.</pre><pre>F: Kann ich die Seite später selbst pflegen?
A: Ja. Contao ist dafür gebaut, und zur Übergabe gehört eine einstündige Einweisung. Texte, Bilder und neue Seiten pflegst du danach ohne uns.</pre><p class="seo-studio-ex-hint">Merkmale einer guten FAQ-Antwort: <em>erster Satz beantwortet die Frage vollständig</em>, danach ein bis zwei Sätze Begründung. Keine Rückfragen, kein „das kommt darauf an“ als erstes Wort. Unter „FAQ“ erzeugt die KI Entwürfe aus deinen echten Seiteninhalten.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['headings'] = '<p><strong>Falsch</strong> — zwei H1, und H2 wird übersprungen:</p><pre>H1  Willkommen
H1  Unsere Leistungen
H3  Beratung</pre><p><strong>Richtig</strong> — eine H1, lückenlose Ebenen:</p><pre>H1  Webdesign für Freelancer
H2  Unsere Leistungen
H3  Beratung
H3  Umsetzung
H2  Ablauf und Preise</pre><p class="seo-studio-ex-hint">Die Ebene stellst du am Inhaltselement im Feld neben der Überschrift ein (h1–h6). Untergeordnetes bekommt die nächsthöhere Zahl — nie zwei Stufen auf einmal.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['meta'] = '<p><strong>Statt leer oder „Startseite“:</strong></p><p class="seo-studio-ex-label">Seitentitel (50–60 Zeichen)</p><pre>Webdesign für Freelancer — V&amp;T Innovations</pre><p class="seo-studio-ex-label">Beschreibung (120–155 Zeichen)</p><pre>Wir bauen Contao-Websites für Freelancer und kleine Teams. Von der Konzeption bis zur Übergabe — in vier Wochen online.</pre><p class="seo-studio-ex-hint">Regel: Der Titel nennt <em>Leistung + Zielgruppe</em>, die Beschreibung endet mit einem konkreten Nutzen. Beide Felder füllt „Titel &amp; Beschreibungen erzeugen“ auch per KI.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['schema'] = '<p><strong>In Einstellungen → Strukturierte Daten, Feld „Organisation: Name“:</strong></p><pre>V&amp;T Innovations</pre><p class="seo-studio-ex-hint">Der Name, unter dem dich Kunden kennen — mit Rechtsform, wenn du sie sonst auch führst („Muster GmbH“). Ein Feld, einmal ausfüllen, wirkt auf allen Seiten.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['examples']['structuredFormats'] = '<p><strong>Statt Fließtext:</strong> „Wir bieten Beratung, Umsetzung und Wartung an, außerdem kümmern wir uns um Hosting und Schulungen.“</p><p><strong>Als Aufzählung</strong> (Inhaltselement „Aufzählung“):</p><pre>Unsere Leistungen im Überblick

• Beratung — Konzept, Struktur, Zielgruppe
• Umsetzung — Contao-Website, responsiv, barrierefrei
• Wartung — Updates, Backups, Support
• Hosting — deutsche Server, DSGVO-konform
• Schulung — damit du selbst pflegen kannst</pre><p><strong>Oder als Tabelle</strong> (Inhaltselement „Tabelle“), wenn du Werte vergleichst:</p><pre>Paket    | Umfang           | Preis
Basis    | 5 Seiten         | ab 1.900 €
Business | 15 Seiten + Blog | ab 3.900 €</pre><p class="seo-studio-ex-hint">Warum das zählt: ChatGPT und Google-KI zitieren solche Blöcke fast wörtlich, weil sie sich sauber aus der Seite herausschneiden lassen. Ein Element pro Seite genügt.</p>';

// ── Explainer ───────────────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['explain']['body'] = '<p>Die Suite hilft bei SEO/GEO/AEO der Website — direkt im Backend. Drei Bereiche:</p><ul><li><strong>Inhalte &amp; Meta</strong> — Titel/Beschreibungen und Glossar-Definitionen per KI erzeugen (nur leere Felder).</li><li><strong>Analyse</strong> — Crawler-Zugriff, Struktur, Duplikate, GEO-Score, Aktualität und Bilder prüfen.</li><li><strong>FAQ</strong> und <strong>Glossar</strong> — erzeugte Inhalte kuratieren und veröffentlichen.</li></ul><p>Einzelne Text- und Überschriftenblöcke optimierst du direkt am Inhaltselement über „Mit KI optimieren“.</p><p><strong>SEO Studio erzeugt nie etwas von allein.</strong> Jede KI-Aktion läuft nur auf deinen Klick (oder den optionalen Cron, der standardmäßig aus ist). Ein Frontend-Titel wie <code>„Seite - Websitename“</code> kommt von Contao selbst — es hängt den Startpunktnamen an und nutzt den Navigationstitel, wenn der Seitentitel leer ist.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['explain']['summary'] = 'Was macht AI SEO Studio? & warum erscheinen Titel „von allein“?';

// ── Dashboard to-do list ────────────────────────────────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['allClear'] = 'Alles im grünen Bereich — keine offenen Punkte.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersBlocked'] = '%d KI-Crawler blockiert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersCheckCta'] = 'Jetzt prüfen (Analyse)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersCta'] = 'Crawler-Audit (Analyse)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['crawlersUnchecked'] = 'Crawler-Zugang noch nicht geprüft';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['faqCta'] = 'FAQ kuratieren';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['faqDrafts'] = '%d FAQ-Entwurf/-Entwürfe warten auf Freigabe';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['glossaryCta'] = 'Glossar kuratieren';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['glossaryDrafts'] = '%d Glossar-Entwurf/-Entwürfe warten auf Freigabe';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['heading'] = 'Zu erledigen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['images'] = '%d Bild(er) ohne Bildgröße';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['imagesCta'] = 'Bild-Assistent (Analyse)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['meta'] = '%d Seite(n) ohne Titel/Beschreibung';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['metaCta'] = 'Inhalte & Meta öffnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['noScore'] = 'GEO-Score noch nie berechnet';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['noScoreCta'] = 'GEO-Score (Analyse)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['stale'] = 'Zur Info: %d Seite(n) seit über 14 Tagen unverändert (zählt nicht in den Score)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['todo']['staleCta'] = 'Aktualität (Analyse)';

// ── Settings screen (fields, legends, status lines) ────────────────────
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiApiKey'] = 'API-Schlüssel';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiBaseUrl'] = 'Basis-URL (nur für "kompatibel")';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiModel'] = 'Modell (leer = Standard)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiProvider'] = 'KI-Anbieter';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiProviderCompatible'] = 'OpenAI-kompatibel (eigene URL)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatus'] = 'Token-Verbrauch diesen Monat: %s von %s (%d%%)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatusUnlimited'] = 'Token-Verbrauch diesen Monat: %s (kein Limit gesetzt)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['cronBatchSize'] = 'Cron-Batchgröße';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keyEmpty'] = 'noch kein Schlüssel gespeichert';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keySet'] = 'gespeichert — leer lassen zum Behalten, "!delete" zum Löschen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['languageOverride'] = 'Sprach-Override (leer = Sprache der Startseite)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendAi'] = 'KI-Einstellungen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendBehavior'] = 'Verhalten';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendFeatures'] = 'Funktionen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendSchema'] = 'Strukturierte Daten (Schema.org)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsSummaryGenerate'] = 'Mit KI erzeugen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsTxtSummary'] = 'llms.txt: Kurzbeschreibung der Website';
$GLOBALS['TL_LANG']['SEO_STUDIO']['metaCronEnabled'] = 'Cron: leere Titel/Beschreibungen automatisch füllen (verbraucht Tokens)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['monthlyTokenBudget'] = 'Monatliches Token-Budget (0 = unbegrenzt)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['noExternalCalls'] = 'Keine externen Aufrufe (KI komplett deaktivieren)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['save'] = 'Speichern';
$GLOBALS['TL_LANG']['SEO_STUDIO']['saved'] = 'Einstellungen gespeichert.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgLogo'] = 'Organisation: Logo-URL';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgName'] = 'Organisation: Name';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgSameAs'] = 'Organisation: Profile (sameAs, eine URL pro Zeile)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['settingsIntro'] = '<p>Zentrale Einstellungen für KI-Anbieter, Funktionen, Verhalten und strukturierte Daten. Deaktivierte Funktionen verschwinden komplett aus dem Backend.</p>';
$GLOBALS['TL_LANG']['SEO_STUDIO']['settingsConnectionOk'] = 'Verbindung ok (%s / %s, %d ms).';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['answerFirst'] = 'Ersten Absatz zur direkten Antwort machen (Antwort in Satz 1)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['checkAll'] = 'Alle Seiten prüfen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['computeWithAi'] = 'Score mit KI berechnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['faq'] = 'Drei FAQ erzeugen und freigeben';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['headings'] = 'Überschriften ordnen — genau eine H1, keine übersprungenen Ebenen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['meta'] = 'Seitentitel und Beschreibung ausfüllen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['more'] = '… und %d weitere Aufgabe(n) auf anderen Seiten.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openContent'] = 'Inhalte öffnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openFaq'] = 'FAQ öffnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openPage'] = 'Seite öffnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['openSettings'] = 'Einstellungen öffnen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['schema'] = 'Einmalig: Firmennamen eintragen — hebt alle %d Seiten (+3 je Seite)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['structuredFormats'] = 'Ein Aufzählungs- oder Tabellen-Element einfügen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['task']['unmeasured'] = 'Hinweis: Auf %d Seite(n) lief der KI-Check nie — diese Punkte fehlen nicht, sie wurden nie gemessen.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['testConnection'] = 'Verbindung testen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeMode'] = 'Schreibmodus';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModeFillEmpty'] = 'Nur leere Felder automatisch füllen';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModePropose'] = 'Vorschlagen → Vorschau → Übernehmen';
