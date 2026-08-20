🇬🇧 [English version](README.en.md)

# AI SEO Studio für Contao 5

KI-gestützte **SEO / GEO / AEO**-Suite als **ein** Bundle mit schaltbaren Feature-Modulen.
Deterministisch wo möglich, LLM nur wo Sprache erzeugt oder beurteilt wird.

- **SEO** – klassische Suchmaschinen (Google & Co.): Titel, Beschreibungen, Struktur, Schema.
- **GEO** – *Generative Engine Optimization*: sichtbar/zitierbar in KI-Suchen (ChatGPT-Suche, Perplexity, Gemini).
- **AEO** – *Answer Engine Optimization*: direkte Antworten (FAQ, Antwort-zuerst-Einstiege).

Aktueller Stand: natives, produktionsreifes Contao-5-Bundle (Version 1.0.0). Alle Module unten sind vollständig implementiert und über ihre jeweiligen Backend-/Frontend-Einstiegspunkte real erreichbar — nichts in diesem Dokument beschreibt eine geplante oder zukünftige Funktion.

---

## Funktionsübersicht

| Modul | Beschreibung | KI |
|---|---|---|
| **SEO-Score pro Seite** | 0–100-Ampel direkt in der Seitenliste + Checkliste im Seiten-Formular (Fokus-Keyword, Titel/Beschreibung, H1, Zwischenüberschriften, Wortzahl, Alt-Texte, Keyword-Platzierung, **Lesbarkeit**: Satzlänge, Passiv, Flesch, Übergangswörter, Satzanfänge) | – |
| **1-Klick-KI-Fixes** | Am SEO-Score-Panel: „KI: Fokus-Keyword vorschlagen“ und „KI: Titel & Beschreibung erzeugen“ – füllt die Felder, du prüfst und speicherst | ✔ |
| **Meta-Generierung** | Seitentitel + Beschreibung per Klick (Vorschau → Übernehmen); Massenlauf pro Startpunkt füllt **nur leere** Felder; optionaler Cron | ✔ |
| **Text-Optimierung** | Button „Mit KI optimieren“ unter **jedem** Überschrift-/Textfeld (tl_content inkl. Draggo, News, Events): Score, Umschreiben (RTE-fähig), Generieren aus Seiteninhalt | ✔ |
| **Inline-Checks** | Alt-Texte (Vision – das Modell sieht das Bild) + Linktexte („hier klicken“-Erkennung) direkt am Element | ✔ |
| **FAQ** | Q&A-Entwürfe aus echtem Seiteninhalt (zentral in „Inhalte & Meta“ **oder** pro Seite), Kuratierung im Backend, Frontend-Modul mit **FAQPage**-Schema | ✔ |
| **KI-Glossar** | Begriffe eingeben oder aus der Website vorschlagen lassen → Antwort-zuerst-Definitionen als Entwürfe, Frontend-Modul A–Z + Detailseiten mit **DefinedTermSet**-Schema, Import aus altem Glossary-Bundle | ✔ |
| **Social-Media-Vorschau** | Open-Graph- + Twitter/X-Card-Felder pro Seite (Titel/Beschreibung/Bild) + Live-Vorschaukarte; Tags werden automatisch ins Frontend ausgegeben | – |
| **SEO/GEO/AEO-Score** | Reifegrad 0–100 pro Seite (Meta, Struktur, Antwort-zuerst, Formate, FAQ, Schema — Aktualität ist reine Information und zählt nicht), im Dashboard **nach SEO / GEO / AEO aufgeschlüsselt** | teils |
| **KI-Crawler-Audit** | robots.txt-Prüfung für GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Bingbot inkl. Korrektur-Vorschlag | – |
| **Struktur-Audit** | Überschriften-Hierarchie + Antwort-zuerst-Check des Einstiegsabsatzes (pro Seite) | teils |
| **Duplikate** | Identische Titel/Beschreibungen site-weit + Warnung beim Speichern | – |
| **Strukturierte Daten** | JSON-LD: Organization, BreadcrumbList, WebPage, Article, FAQPage, DefinedTermSet – Injektion vor `</head>`, funktioniert mit **jedem** Template (auch Page-Builder) | – |
| **llms.txt** | `/llms.txt` aus der Seitenstruktur, optional mit KI-Kurzbeschreibung | optional |
| **Freshness** | `lastmod` in der Sitemap + `dateModified` im Schema aus echten Änderungsdaten; Monitor für veraltete Seiten | – |
| **Bild-Audit** | Fehlende Bildgrößen (1-Klick-Fix), übergroße Originale, WebP-Empfehlung | – |

> **Strukturierte Daten im Detail:** Die drei Schalter unter „Einstellungen → Strukturierte Daten“ (Organization/Breadcrumb/Article) decken genau diese drei Typen ab. **WebPage** folgt stattdessen dem Freshness-Schalter, **FAQPage** dem FAQ-Schalter und **DefinedTermSet/DefinedTerm** dem Glossar-Schalter — es gibt dafür bewusst keine zusätzlichen Häkchen.

Dashboard und Analyse lassen sich per **Startpunkt-Auswahl** auf einen einzelnen Site-Root eingrenzen (bei mehreren Startpunkten). Backend in Light- und Dark-Mode.

---

## Feature-Status

Alle Funktionen dieses Bundles sind **Nur Pro**: ohne aktivierte Lizenz ist keine einzige davon erreichbar (siehe [Lizenzierung](#lizenzierung)). Innerhalb der Lizenz sind alle unten aufgeführten Module gleichrangig freigeschaltet — es gibt keine weitere Abstufung.

| Modul | Status | Benötigt KI-Schlüssel |
|---|---|---|
| SEO-Score pro Seite (Checkliste, Ampel) | Nur Pro | Nein (1-Klick-Fixes: ja) |
| Meta-Generierung | Nur Pro | Ja |
| Text-Optimierung | Nur Pro | Teils (Messung deterministisch, Umschreiben/Generieren mit KI) |
| Inline-Checks (Alt-Text, Linktext) | Nur Pro | Ja |
| FAQ-Generierung + Frontend-Modul | Nur Pro | Ja (Generierung), Nein (Anzeige) |
| KI-Glossar + Frontend-Modul | Nur Pro | Ja (Generierung), Nein (Anzeige) |
| Social-Media-Vorschau | Nur Pro | Nein |
| SEO/GEO/AEO-Score | Nur Pro | Teils (Antwort-zuerst-Anteil optional per KI) |
| KI-Crawler-Audit | Nur Pro | Nein |
| Struktur-Audit | Nur Pro | Teils (Antwort-zuerst-Check optional per KI) |
| Duplikate-Erkennung | Nur Pro | Nein |
| Strukturierte Daten (JSON-LD) | Nur Pro | Nein |
| llms.txt | Nur Pro | Nein (KI-Kurzbeschreibung optional) |
| Freshness-Monitor | Nur Pro | Nein |
| Bild-Audit + Größen-Assistent | Nur Pro | Nein |

---

## Backend-Aufbau

Alles liegt in der Menügruppe **SEO STUDIO** (linke Backend-Navigation, ggf. runterscrollen):

1. **Übersicht** – SEO/GEO/AEO-Score-Ring + SEO/GEO/AEO-Aufteilung, Abdeckungs-Balken, To-do-Liste mit Direktlinks.
2. **Inhalte & Meta** – Titel/Beschreibungen, **FAQ** und **Glossar** per KI erzeugen (nur leere Felder / Entwürfe).
3. **Analyse** – Crawler, Struktur, Duplikate, SEO/GEO/AEO-Score, Aktualität, Bilder (read-only, zwei Klick-Aktionen).
4. **FAQ** / **Glossar** – generierte Inhalte kuratieren und **veröffentlichen** (neue Einträge sind immer erst Entwurf, mit einer Ausnahme: siehe [Bekannte Einschränkungen](#bekannte-einschränkungen)).
5. **Einstellungen** – KI-Anbieter, Funktions-Toggles, Verhalten, Schema.org.

Diese Reihenfolge entspricht der tatsächlichen Backend-Navigation. Einzelne Blöcke optimierst du direkt am Inhaltselement über „Mit KI optimieren“. Pro Seite gibt es außerdem: **SEO-Score-Panel** (mit Fokus-Keyword + 1-Klick-Fixes), **Social-Vorschau** und das **Meta-/FAQ-Panel**.

> Tipp: Stern ⭐ neben dem Modultitel klicken → landet oben in der Favoritenleiste.

---

## Systemvoraussetzungen

- **Contao** 5.3 oder neuer, **PHP** 8.2 oder neuer.
- PHP-Erweiterungen: `sodium` und `json` sind **erforderlich** (Signaturprüfung der Lizenz bzw. Datenaustausch); `curl` und `intl` werden **empfohlen** (serverseitige Nutzungssignale bzw. Aktivierung auf internationalisierten Domainnamen). Ohne `intl` wird ein nicht-ASCII-Domainname beim Aktivieren abgelehnt, statt ihn ungenau zu behandeln.
- Composer-Abhängigkeiten: `symfony/http-client`, `symfony/http-foundation` (jeweils 6.4/7.0-kompatibel), `defuse/php-encryption` ^2.4.
- **Shared-Hosting-tauglich:** Es wird kein `exec()`/`shell_exec()`/`proc_open()` und kein Node.js-Build-Schritt benötigt. Alles läuft in PHP, über den regulären HTTP-Client und den Contao-Cron.
- Schreibrechte auf das Contao-Verzeichnis `var/` (siehe [Laufzeitverzeichnisse](#laufzeitverzeichnisse)).
- Für KI-Funktionen und Lizenzverwaltung: ausgehende HTTPS-Verbindungen vom Server aus (nicht vom Browser aus) zum konfigurierten KI-Anbieter bzw. zum Lizenzdienst des Herstellers.
- Für Analyse-Funktionen mit echten Werten: mindestens ein Seitenstruktur-Startpunkt mit konfiguriertem **Domainnamen**.

## Installation

1. **Paket installieren.** Contao Manager → ZIP hochladen, **oder**:
   ```bash
   composer require vtinnovations/seo-studio
   ```
2. **Datenbank aktualisieren.** Contao Manager → „Datenbank aktualisieren“, oder:
   ```bash
   vendor/bin/contao-console contao:migrate
   ```
3. **Lizenz aktivieren** (siehe unten) – ohne aktivierte Lizenz bleibt das Bundle vollständig unsichtbar.
4. **Erneut „Datenbank aktualisieren“ ausführen.** Die Datenbankfelder der freigeschalteten Funktionen (z. B. `tl_page.seoFocusKeyword`) werden erst angelegt, wenn die jeweilige Funktion aktiv ist.
5. **Cache leeren**, falls die neue Menügruppe nicht sofort erscheint (siehe [Cache-Leerung](#deployment)).

Kein zusätzliches ausführbares Programm und kein separater Dienst wird benötigt; die Installation läuft vollständig über Composer/Contao Manager und die Contao-Konsole.

## Lizenzierung

AI SEO Studio ist ein **Pro-Produkt mit genau einer Lizenzstufe**: Es gibt keine kostenlose Stufe, keine Testphase und nach Ablauf keinen automatischen Rückfall auf einen freien Modus. Ohne aktivierte Lizenz verhält sich die Installation exakt so, als wäre das Bundle nicht installiert — keine Menügruppe „SEO STUDIO“, keine Panels, keine Frontend-Ausgabe (auch strukturierte Daten und die FAQ-/Glossar-Frontend-Module bleiben stumm). Vorhandene Seiten-, FAQ- und Glossar-Daten werden davon nie berührt oder gelöscht — sie werden lediglich nicht verarbeitet oder ausgegeben, bis wieder eine gültige Lizenz vorliegt.

### Aktivieren, aktualisieren, entfernen

1. **Domain am Startpunkt eintragen.** Seitenstruktur → Startpunkt → „Domainname“. Ohne konfigurierte Domain hat die Installation keine Identität, und eine Aktivierung ist nicht möglich.
2. **Contao → Einstellungen → „AI SEO Studio Licence management“** öffnen, Lizenzschlüssel eintragen, **„Verify & activate licence“** klicken.
3. Danach erneut „Datenbank aktualisieren“ ausführen (siehe oben).

Weitere Schaltflächen im selben Bereich:

- **„Update licence“** – fragt den aktuellen Lizenzstand erneut ab (z. B. nach einer Verlängerung).
- **„Remove licence“** – fragt zuerst nach einer Bestätigung, deaktiviert dann alle Funktionen sofort, **löscht keine Inhalte**.

Beide Aktionen erfordern eine angemeldete Administrator-Sitzung mit gültigem Contao-Sicherheitstoken. Schlägt eine Anfrage an den Lizenzdienst fehl (z. B. Netzwerkproblem), bleibt der zuvor gespeicherte Lizenzstand unverändert erhalten — es wird nichts verworfen oder stillschweigend heruntergestuft.

Die Lizenz gilt **instanzweit** und ist an die konfigurierten Hostnamen gebunden — exakt, ohne Wildcards: `example.com`, `www.example.com` und `shop.example.com` sind drei verschiedene Identitäten.

### Lizenzzustände

Die Einstellungsseite zeigt den aktuellen Zustand als Statusbox: eine Klartext-Überschrift sowie eine Zeile mit Fakten (maskierter Lizenzschlüssel, Paket, Version, gebundene Domain, lizenzierte Domains, Domain-Kontingent, gültig ab, gültig bis, zuletzt geprüft):

| Zustand | Bedeutung |
|---|---|
| Keine Lizenz | Frischinstallation, nichts hinterlegt |
| Aktiv | Lizenz gültig, Domain passt, alle Funktionen freigeschaltet |
| Domain passt nicht | Lizenz gültig, aber nicht für die aktuell konfigurierte Domain ausgestellt |
| Noch nicht gültig | Startdatum der Lizenz liegt in der Zukunft |
| Abgelaufen | Gültigkeitsdatum überschritten — kein automatischer Rückfall auf eine kostenlose Stufe |
| Aktualisierung nötig | Lizenz wurde vor einer späteren Erweiterung ausgestellt; ein einmaliger Klick auf „Update licence“ behebt das |
| Ungültig / nicht überprüfbar | Gespeicherte Lizenzdaten bestehen die Prüfung nicht (z. B. durch Manipulation) |

In jedem nicht aktiven Zustand ist das Ergebnis identisch: das Bundle bleibt vollständig unsichtbar, bis die Lizenz wieder gültig ist.

### Serverkommunikation (Transparenz)

Die Kommunikation mit dem Lizenzdienst des Herstellers läuft **ausschließlich serverseitig** über vertrauenswürdiges HTTPS — der Browser der Administratorin oder des Administrators kontaktiert den Lizenzdienst nie direkt. Es sind genau folgende Arten von Vorgängen vorgesehen:

- **Aktivierung und Aktualisierung** – ausgelöst durch einen expliziten Administrator-Klick (siehe oben).
- **Vom Hersteller ausgehende, signierte Lizenzaktualisierungen** – z. B. nach einer Verlängerung; werden lokal anhand einer kryptografischen Signatur geprüft, bevor sie übernommen werden. Jede fehlgeschlagene Prüfung lässt den zuvor gültigen Zustand unverändert.
- **Ein knappes, serverseitiges Nutzungssignal** beim Aufruf des Backends – läuft erst **nach** Auslieferung der Antwort an den Browser, verzögert und beeinflusst die Darstellung also nie, und wird bei Nichterreichbarkeit stillschweigend verworfen.

Der Lizenzschlüssel erscheint **zu keinem Zeitpunkt vollständig** in der Benutzeroberfläche, in regulären Protokollen oder in Diagnoseausgaben — die Einstellungsseite zeigt ihn maskiert (vier führende und vier abschließende Zeichen um eine Maske fester Breite, sodass auch die echte Länge verborgen bleibt): genug, um den hinterlegten Schlüssel zu erkennen, und zu wenig, um ihn zu verwenden — sowie die oben genannten unkritischen Fakten. Der Lizenzstand wird als vom Hersteller signiertes Paket außerhalb des öffentlichen Webverzeichnisses abgelegt; jede Änderung an den gespeicherten Daten macht die Lizenz bei der nächsten Prüfung ungültig und kann nicht lokal „repariert“ werden — nur eine erneute, gültige Ausstellung durch den Lizenzdienst stellt sie wieder her.

## KI-Anbieter verbinden

**Einstellungen → KI-Einstellungen**:

- **Anbieter:** Anthropic (Claude), OpenAI, oder OpenAI-kompatibel (eigene Basis-URL, z. B. für lokal gehostete oder alternative Modelle mit OpenAI-kompatibler API).
- **API-Schlüssel** eintragen – wird **verschlüsselt gespeichert** (`defuse/php-encryption`, Schlüsseldatei getrennt vom verschlüsselten Wert, beide außerhalb des öffentlichen Webverzeichnisses) und **nie im Klartext angezeigt**; ein leeres Feld beim Speichern behält den vorhandenen Schlüssel bei.
- Optional: Modell überschreiben (Vorgabe je Anbieter), monatliches **Token-Budget** (harter Stopp – ab 100 % lehnt jede weitere KI-Anfrage ab, ab 80 % erscheint ein Warnhinweis auf der Einstellungsseite), Schalter „keine externen Aufrufe“ zum vollständigen Abschalten aller KI-Netzwerkzugriffe.
- **„Verbindung testen“** führt eine einzelne, minimale Anfrage an den konfigurierten Anbieter aus und meldet Erfolg/Fehler inkl. Antwortzeit — diese Prüfung zählt nicht gegen das Token-Budget.

> Ohne KI-Schlüssel laufen alle **deterministischen** Prüfungen trotzdem (SEO-Score-Grundlagen, Crawler-Audit, Struktur-Grundprüfung, Duplikate, Schema, Social). Nur die **erzeugenden** Funktionen (Meta-, Text-, FAQ-, Glossar-Generierung, Alt-Text-Vorschläge, Fokus-Keyword-Vorschlag) benötigen den Schlüssel.

## Funktionen wählen

**Einstellungen → Funktionen**: jede der 14 Funktionen einzeln an/aus (siehe [Feature-Status](#feature-status) für die vollständige Liste). Deaktivierte Funktionen hinterlassen keine Spur im Backend — kein Menüpunkt, kein Button, kein Panel, keine Datenbankfelder für die zugehörigen Einstellungen.

## Organisationsdaten für Schema.org

**Einstellungen → Strukturierte Daten**: Organisation-Name, Logo-URL, Social-Profile (`sameAs`), Schalter für Organization/Breadcrumb/Article (siehe Hinweis zu WebPage/FAQPage/DefinedTermSet oben), sowie der Text für `llms.txt`. Für eine saubere JSON-LD-Ausgabe empfohlen.

## Frontend-Module platzieren

Für sichtbare FAQ-/Glossar-Ausgabe je ein Frontend-Modul anlegen und in eine Seite/Artikel einbinden:

- **„FAQ (SEO Studio)“** – Akkordeon-Darstellung der veröffentlichten FAQ der aktuellen Seite + FAQPage-Schema.
- **„Glossar (SEO Studio)“** – A–Z-Liste der veröffentlichten Begriffe + eigene Detailseiten mit DefinedTerm-Schema.

Danach: Seiten befüllen (Meta/FAQ/Glossar erzeugen), Entwürfe unter **FAQ**/**Glossar** prüfen und **veröffentlichen**.

## Was man einstellen kann/muss

| Ort | Einstellung | Pflicht? |
|---|---|---|
| Einstellungen → KI | Anbieter + API-Schlüssel | Nur für KI-Funktionen |
| Einstellungen → KI | Modell, Token-Budget, „keine externen Aufrufe“ | Optional |
| Einstellungen → Funktionen | Feature-Toggles | Optional |
| Einstellungen → Verhalten | Schreibmodus, Cron-Batchgröße, **Sprach-Override**, Meta-Cron | Optional |
| Einstellungen → Strukturierte Daten | Organisation, Logo, sameAs, Schema-Schalter, llms.txt-Text | Empfohlen |
| Seite → Metadaten | **Fokus-Keyword**, Social-Titel/-Beschreibung, OG-Bild | Pro Seite |
| Seitenstruktur → Root | **Sprache** des Startpunkts (steuert die KI-Ausgabesprache!) | Pro Site |

> **Sprache:** KI-Ausgaben (Meta/FAQ/Glossar) folgen der Seiten-/Root-Sprache. Steht ein Root fälschlich auf `en`, kommen englische Texte. Fix: Root-Sprache korrigieren **oder** in „Verhalten → Sprach-Override“ z. B. `de` erzwingen.

## Berechtigungen und Zugriffskontrolle

Sämtliche Backend-Module, Panels und AJAX-Endpunkte dieses Bundles setzen eine angemeldete Contao-Backend-Sitzung mit Administratorrechten sowie ein gültiges Contao-Sicherheitstoken voraus — es gibt keinen eigenen, parallelen Berechtigungsmechanismus. Die öffentliche Route `/llms.txt` ist bewusst unauthentifiziert (sie liefert nur bereits öffentliche Seiteninformationen) und liefert ohne gültige Lizenz oder bei deaktivierter Funktion konsequent „nicht gefunden“. Die eine Route, die dieses Bundle für eingehende Aufrufe des Lizenzdienstes öffnet, verlangt keine Browser-Sitzung, sondern eine gültige kryptografische Signatur des Herstellers; jeder andere Aufruf wird abgelehnt.

## Sicherheitsmodell

- **Zugriffskontrolle:** Backend-Funktionen sind an Contaos eigene Benutzer- und Rechteverwaltung gebunden; zusätzlich prüft jede Funktion serverseitig, ob sie lizenziert und aktiviert ist, bevor sie etwas tut.
- **Authentizität und Integrität:** Sowohl der lokal gespeicherte Lizenzstand als auch vom Hersteller eingehende Aktualisierungen werden kryptografisch signiert übertragen bzw. abgelegt und vor jeder Verwendung erneut geprüft; eine fehlgeschlagene Prüfung führt immer zum sichereren, restriktiveren Zustand, nie zu einem großzügigeren.
- **Vertrauliche Ablage:** Lizenzstand und der KI-API-Schlüssel liegen außerhalb des öffentlichen Webverzeichnisses; der API-Schlüssel zusätzlich verschlüsselt, mit vom verschlüsselten Wert getrennter Schlüsseldatei.
- **Vertrauenswürdige Kommunikation:** Jede externe Kommunikation läuft ausschließlich über HTTPS und ausschließlich serverseitig, nie aus dem Browser heraus.
- **Sicheres Fehlverhalten:** Jeder Fehler bei Lizenz-, Signatur- oder Konsistenzprüfung führt zu einem restriktiveren, nie zu einem großzügigeren Zustand; nichts wird bei einem Fehler „geraten“ oder optimistisch angenommen.
- **Schwärzung in Protokollen:** Reguläre Anwendungsprotokolle enthalten grundsätzlich keine Lizenzschlüssel, API-Schlüssel, Signaturen oder Prüfsummen — protokolliert werden nur unkritische Fakten (z. B. Vorgangsart, Ergebniskategorie, Zeitdauer).

Diese Beschreibung nennt bewusst **keine** internen Klassen-, Datei- oder Protokolldetails der Sicherheitsmechanismen — das ist Absicht, nicht Unvollständigkeit.

## Betriebssicherheit

- Alle Schreibvorgänge auf den lokal gespeicherten Lizenzstand und die verschlüsselten Zugangsdaten laufen exklusiv gesperrt und atomar: ein abgebrochener Schreibvorgang (z. B. durch einen Absturz) kann den gespeicherten Stand nicht in einen halb geschriebenen, unbrauchbaren Zustand bringen.
- Der stündliche Meta-Cron läuft durch eine zeitlich begrenzte Sperre geschützt, sodass zwei sich überlappende Läufe ausgeschlossen sind; wird während eines Laufs das Token-Budget erreicht, stoppt der gesamte Batch sauber und protokolliert das, statt weiterzumachen oder abzustürzen; ein Fehler bei einer einzelnen Seite überspringt nur diese eine Seite.
- Generierte FAQ- und Glossar-Einträge werden grundsätzlich als **Entwurf** angelegt (eine dokumentierte Ausnahme betrifft den Glossar-Import bestehender Inhalte, siehe [Bekannte Einschränkungen](#bekannte-einschränkungen)) und müssen von einer Person geprüft und veröffentlicht werden.
- Massenläufe (Meta-Generierung, Cron) ändern ausschließlich leere Felder – bereits vorhandene redaktionelle Inhalte werden nie überschrieben.

Diese Zusicherungen gelten für die genannten Abläufe; das Bundle bewirbt darüber hinaus keine allgemeine Rollback- oder Transaktionsgarantie für Contao selbst.

## Laufzeitverzeichnisse

Dieses Bundle legt eigene Laufzeitdaten in einem Unterverzeichnis von Contaos `var/`-Verzeichnis ab — also außerhalb des öffentlichen Webverzeichnisses und nicht über HTTP erreichbar. Dort abgelegt werden: der signierte Lizenzstand sowie der verschlüsselte KI-API-Schlüssel samt getrennter Schlüsseldatei. Dieses Verzeichnis muss für den Webserver-Prozess beschreibbar sein; ein Backup-/Deployment-Prozess sollte es wie andere `var/`-Inhalte behandeln (nicht Teil des versionierten Quellcodes, aber Teil der Instanzdaten).

## Externe Kommunikation

| Zweck | Ziel | Ausgelöst durch |
|---|---|---|
| KI-Generierung (Meta, Text, FAQ, Glossar, Alt-Text, Fokus-Keyword) | konfigurierter KI-Anbieter (Anthropic, OpenAI oder OpenAI-kompatibel) | Klick auf eine „Mit KI …“-Aktion oder den optionalen Meta-Cron |
| Verbindungstest | konfigurierter KI-Anbieter | Klick auf „Verbindung testen“ |
| robots.txt-Prüfung | die jeweils eigene Website (nicht ein Drittanbieter) | Klick auf „KI-Crawler-Audit“ in der Analyse |
| Lizenzaktivierung/-aktualisierung/-entfernung | Lizenzdienst des Herstellers | Administrator-Klick in den Lizenz-Einstellungen |
| Nutzungssignal | Lizenzdienst des Herstellers | Aufruf des Contao-Backends (nach Auslieferung der Antwort, ohne Einfluss auf die Darstellung) |

Alle Verbindungen laufen serverseitig über HTTPS. Ohne KI-Schlüssel bzw. mit aktivierter Option „keine externen Aufrufe“ finden die ersten beiden Zeilen nicht statt; die deterministischen Funktionen bleiben davon unberührt.

## Protokollierung

Reguläre Anwendungsprotokolle (z. B. bei Cron-Fehlern) enthalten Vorgangsart, Ergebnis und Zeitangaben, aber grundsätzlich keine API-Schlüssel, Lizenzschlüssel, Signaturen, Prüfsummen oder vollständigen KI-Antworttexte. Fehlgeschlagene Verbindungstests und Cron-Läufe melden eine allgemeine Fehlerkategorie, keine internen Diagnosedetails.

## Deployment

- **Installation/Update:** `composer require vtinnovations/seo-studio` gefolgt von `vendor/bin/contao-console contao:migrate` (oder den jeweiligen Contao-Manager-Aktionen).
- **Cache leeren:** über den Contao Manager („Cache leeren“) oder den regulären Contao/Symfony-Konsolenbefehl `vendor/bin/contao-console cache:clear` — insbesondere nach dem Aktivieren/Entfernen der Lizenz oder nach dem Umschalten von Funktionen empfehlenswert, damit die Backend-Navigation den neuen Stand sofort zeigt.
- Kein bundle-eigener Deployment-Schritt darüber hinaus; es gibt keinen Build-Prozess und keine JavaScript-Abhängigkeiten, die installiert werden müssten.

## Tests

Das Bundle bringt eine PHPUnit-Testsuite (`tests/`) sowie ein eigenständiges Kommandozeilen-Werkzeug (`tools/release-guard.php`) mit:

```bash
composer install
vendor/bin/phpunit
php tools/release-guard.php        # oder: composer run release-guard
```

`tools/release-guard.php` ist in erster Linie ein interner Release-Sicherheitscheck (u. a. Signaturprüfung, Konsistenz der intern verwendeten Schlüssel, Schutz gegen versehentliches Protokollieren vertraulicher Daten) und deckt dabei zusätzlich die vollständige Übersetzungs-Parität ab: **289 Oberflächentext-Schlüssel, in Deutsch und Englisch identisch vorhanden**, gleiche `sprintf`-Platzhalter je Sprache, keine Inline-Ausweichtexte im Code, keine neu eingeführten fest verdrahteten Oberflächentexte. Im Rahmen dieser Dokumentationsprüfung wurde es ausgeführt und bestand vollständig (111 Prüfungen, keine Fehlschläge).

`phpstan.neon` konfiguriert eine statische Analyse auf Stufe 8 für `src/` (`vendor/bin/phpstan analyse`, nach `composer install`).

## Sprachen

Alle Oberflächentexte liegen in `contao/languages/en/default.php` und `contao/languages/de/default.php` – **289 Schlüssel, in beiden Sprachen identisch**. Der Code enthält keine fest verdrahteten Oberflächentexte: gelesen wird ausschließlich über `Core\Config\Translations`, und zwar **ohne Inline-Fallback**. Fehlt ein Schlüssel, erscheint `[schlüssel]` statt Text – sichtbar statt stillschweigend englisch oder deutsch.

Eine weitere Sprache ergänzt man, indem man `default.php` (und die `tl_*.php`-Dateien) in ein neues Sprachverzeichnis kopiert und die Werte übersetzt – am Code ist dafür nichts zu ändern.

Nicht übersetzt und absichtlich so: die **Prompt-Texte** an das Sprachmodell (ihr Wortlaut ist Teil der Anweisung, nicht der Oberfläche) und die **deutschsprachige Lesbarkeitsanalyse** (Flesch-Amstad-Formel, Übergangswörter, Passiv- und Füllwort-Erkennung) – das ist Fachlogik für deutsche Texte, keine Beschriftung (siehe [Bekannte Einschränkungen](#bekannte-einschränkungen)). Die KI-**Ausgabesprache** richtet sich unabhängig davon nach der Sprache des Startpunkts bzw. dem Sprach-Override.

## Grundsätze

- Redakteurs-Inhalte werden nie still überschrieben: Vorschlagen → Vorschau → Übernehmen; Massenläufe füllen ausschließlich leere Felder.
- Erzeugte FAQ/Glossar-Einträge sind immer erst **Entwurf** – nichts wird ungeprüft öffentlich (Ausnahme: siehe unten).
- LLM-Aufrufe nur per Klick oder Cron-Batch, nie beim Seitenaufbau.
- Deaktivierte Features hinterlassen null Spuren im Backend.

## Fehlerbehebung

| Symptom | Wahrscheinliche Ursache | Lösung |
|---|---|---|
| Menügruppe „SEO STUDIO“ fehlt komplett | Keine gültige Lizenz | Lizenzzustand unter Einstellungen → Licence management prüfen (siehe [Lizenzzustände](#lizenzzustände)) |
| Menügruppe erscheint nicht sofort nach Aktivierung | Backend-Cache | Cache leeren (siehe [Cache-Leerung](#deployment)) |
| „KI: …“-Buttons tun nichts / Fehlermeldung | Kein API-Schlüssel gesetzt, Schlüssel ungültig, oder „keine externen Aufrufe“ aktiv | Einstellungen → KI prüfen, „Verbindung testen“ nutzen |
| KI-Generierung bricht mit Budget-Fehler ab | Monatliches Token-Budget erreicht | Budget erhöhen oder bis zum nächsten Monat warten; bereits erzeugte Entwürfe bleiben erhalten |
| KI liefert Texte in der falschen Sprache | Root-Seite hat falsche Sprache hinterlegt | Root-Sprache korrigieren oder „Verhalten → Sprach-Override“ setzen |
| Neu erzeugte Datenbankfelder fehlen (z. B. Fokus-Keyword) | Datenbank-Migration nach Aktivierung nicht erneut ausgeführt | „Datenbank aktualisieren“ erneut ausführen |
| Aktivierung nicht möglich | Kein Domainname am Startpunkt konfiguriert | Domainname in der Seitenstruktur am Startpunkt eintragen |
| „Update licence“ nötig ohne erkennbaren Grund | Lizenz wurde vor einer späteren Funktionserweiterung ausgestellt | Einmalig auf „Update licence“ klicken |
| Frontend-Modul FAQ/Glossar zeigt nichts | Keine veröffentlichten Einträge, oder Funktion deaktiviert | Entwürfe unter FAQ/Glossar prüfen und veröffentlichen; Funktion in den Einstellungen prüfen |

## Bekannte Einschränkungen

- **Lesbarkeitsanalyse ist deutschsprachig.** Die Flesch-Amstad-Berechnung, die Übergangswort-Erkennung und die Passiv-/Füllwort-Heuristiken in der SEO-Bewertung und der Text-Optimierung sind auf deutsche Sprachregeln zugeschnitten und werden unabhängig von der tatsächlichen Sprache der jeweiligen Seite angewendet. Bei fremdsprachigen Seiten liefert dieser Teil der Bewertung keine sinnvolle Einschätzung; alle übrigen Prüfungen (Länge, Struktur, Duplikate, Meta, Schema) sind sprachunabhängig.
- **Ein einziges Lizenzmodell.** Es gibt keine kostenlose Stufe und keinen automatischen Rückfall nach Ablauf – eine abgelaufene Lizenz schaltet alle Funktionen ab, bis erneuert wird.
- **Eine Ausnahme beim Entwurfsstatus.** Beim Import aus einem älteren Glossary-Bundle übernimmt der Import den dort hinterlegten Veröffentlichungsstatus unverändert (bereits veröffentlichte Altbestände bleiben veröffentlicht), statt ihn – wie sonst bei jeder KI-Generierung – auf „Entwurf“ zu erzwingen.
- **Der Verbindungstest zählt nicht gegen das Token-Budget**, verursacht aber weiterhin reale Anbieterkosten und benötigt einen gültigen Schlüssel.

## Lizenz- und Urheberrechtsinformationen

© VT Innovations Team. Dieses Paket wird unter der **LGPL-3.0-or-later** lizenziert (siehe `composer.json`). Der Produktname „AI SEO Studio“ und dessen Lizenzierung als Pro-Produkt (siehe [Lizenzierung](#lizenzierung)) sind hiervon unabhängig zu betrachten: Die Bündel-Lizenz (LGPL) regelt die Nutzung und Weitergabe des Quellcodes; der Erwerb einer Produktlizenz schaltet die serverseitig durchgesetzten Funktionen frei.

---

🇬🇧 Englische Version: [README.en.md](README.en.md)
