# AI SEO Studio für Contao 5

KI-gestützte **SEO / GEO / AEO**-Suite als **ein** Bundle mit schaltbaren Feature-Modulen.
Deterministisch wo möglich, LLM nur wo Sprache erzeugt oder beurteilt wird.

- **SEO** – klassische Suchmaschinen (Google & Co.): Titel, Beschreibungen, Struktur, Schema.
- **GEO** – *Generative Engine Optimization*: sichtbar/zitierbar in KI-Suchen (ChatGPT-Suche, Perplexity, Gemini).
- **AEO** – *Answer Engine Optimization*: direkte Antworten (FAQ, Antwort-zuerst-Einstiege).

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
| **SEO/GEO/AEO-Score** | Reifegrad 0–100 pro Seite (Meta, Struktur, Antwort-zuerst, Formate, FAQ, Aktualität, Schema), im Dashboard **nach SEO / GEO / AEO aufgeschlüsselt** | teils |
| **KI-Crawler-Audit** | robots.txt-Prüfung für GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Bingbot inkl. Korrektur-Vorschlag | – |
| **Struktur-Audit** | Überschriften-Hierarchie + Antwort-zuerst-Check des Einstiegsabsatzes (pro Seite) | teils |
| **Duplikate** | Identische Titel/Beschreibungen site-weit + Warnung beim Speichern | – |
| **Strukturierte Daten** | JSON-LD: Organization, BreadcrumbList, WebPage, Article – Injektion vor `</head>`, funktioniert mit **jedem** Template (auch Page-Builder) | – |
| **llms.txt** | `/llms.txt` aus der Seitenstruktur, optional mit KI-Kurzbeschreibung | optional |
| **Freshness** | `lastmod` in der Sitemap + `dateModified` im Schema aus echten Änderungsdaten; Monitor für veraltete Seiten | – |
| **Bild-Audit** | Fehlende Bildgrößen (1-Klick-Fix), übergroße Originale, WebP-Empfehlung | – |

Dashboard und Analyse lassen sich per **Startpunkt-Auswahl** auf einen einzelnen Site-Root eingrenzen (bei mehreren Startpunkten). Backend in Light- und Dark-Mode.

---

## Backend-Aufbau

Alles liegt in der Menügruppe **SEO STUDIO** (linke Backend-Navigation, ggf. runterscrollen):

1. **Übersicht** – SEO/GEO/AEO-Score-Ring + SEO/GEO/AEO-Aufteilung, Abdeckungs-Balken, To-do-Liste mit Direktlinks.
2. **Inhalte & Meta** – Titel/Beschreibungen, **FAQ** und **Glossar** per KI erzeugen (nur leere Felder / Entwürfe).
3. **Analyse** – Crawler, Struktur, Duplikate, SEO/GEO/AEO-Score, Aktualität, Bilder (read-only, zwei Klick-Aktionen).
4. **FAQ** / **Glossar** – generierte Inhalte kuratieren und **veröffentlichen** (neue Einträge sind immer erst Entwurf).
5. **Einstellungen** – Lizenz, KI-Anbieter, Funktions-Toggles, Verhalten, Schema.org.

Einzelne Blöcke optimierst du direkt am Inhaltselement über „Mit KI optimieren“. Pro Seite gibt es außerdem: **SEO-Score-Panel** (mit Fokus-Keyword + 1-Klick-Fixes), **Social-Vorschau** und das **Meta-/FAQ-Panel**.

> Tipp: Stern ⭐ neben dem Modultitel klicken → landet oben in der Favoritenleiste.

---

## Einrichtung – Schnellstart

### 1. Installieren
Contao Manager → ZIP hochladen **oder** `composer require vtinnovations/seo-studio`, danach **Datenbank-Migration** ausführen (Contao Manager → „Datenbank aktualisieren“ oder `vendor/bin/contao-console contao:migrate`).

### 2. Lizenz eintragen (Pflicht – auch für die Demo)
**SEO Studio → Einstellungen → Reiter „Lizenz“** → Schlüssel eintragen → „Lizenz aktivieren“.
- **Demo-Lizenz** (kostenlos auf [v-t.one](https://v-t.one)): schaltet die Kernfunktionen für einige Tage frei – SEO-Score, Analyse, Schema.org, Social, Meta.
- **Vollversion**: alle Module.
- Ohne gültigen Schlüssel bleiben alle Funktionen gesperrt.

### 3. KI-Anbieter verbinden
**Einstellungen → KI-Einstellungen**:
- Anbieter: **Anthropic (Claude)**, **OpenAI** oder **OpenAI-kompatibel** (eigene Basis-URL).
- **API-Schlüssel** eintragen (wird verschlüsselt gespeichert, nie im Klartext angezeigt).
- Optional: Modell überschreiben, monatliches **Token-Budget** (harter Stopp) setzen.
- „Verbindung testen“ prüft den Schlüssel.

> Ohne KI-Schlüssel laufen alle **deterministischen** Prüfungen trotzdem (Score, Crawler, Struktur, Duplikate, Schema, Social). Nur die **erzeugenden** Funktionen (Meta/Text/FAQ/Glossar/Alt-Text) brauchen den Schlüssel.

### 4. Funktionen wählen
**Einstellungen → Funktionen**: jede der ~15 Funktionen einzeln an/aus. Deaktivierte verschwinden komplett (Menü, Buttons, Panels).

### 5. Organisationsdaten für Schema.org
**Einstellungen → Strukturierte Daten**: Organisation-Name, Logo-URL, Social-Profile (sameAs), Schalter für Organization/Breadcrumb/Article. Nötig für saubere JSON-LD-Ausgabe.

### 6. Frontend-Module platzieren (optional)
Für sichtbare FAQ-/Glossar-Ausgabe je ein Frontend-Modul anlegen und in eine Seite/Artikel einbinden:
- **„FAQ (SEO Studio)“** – Akkordeon + FAQPage-Schema.
- **„Glossar (SEO Studio)“** – A–Z-Liste + Detailseiten.

Danach: Seiten befüllen (Meta/FAQ/Glossar erzeugen), Entwürfe unter **FAQ**/**Glossar** prüfen und **veröffentlichen**.

---

## Was man einstellen kann/muss

| Ort | Einstellung | Pflicht? |
|---|---|---|
| Einstellungen → Lizenz | Lizenzschlüssel (Demo oder Voll) | **Ja** |
| Einstellungen → KI | Anbieter + API-Schlüssel | Nur für KI-Funktionen |
| Einstellungen → KI | Modell, Token-Budget, „keine externen Aufrufe“ | Optional |
| Einstellungen → Funktionen | Feature-Toggles | Optional |
| Einstellungen → Verhalten | Schreibmodus, Cron-Batchgröße, **Sprach-Override**, Meta-Cron | Optional |
| Einstellungen → Strukturierte Daten | Organisation, Logo, sameAs, Schema-Schalter, llms.txt-Text | Empfohlen |
| Seite → Metadaten | **Fokus-Keyword**, Social-Titel/-Beschreibung, OG-Bild | Pro Seite |
| Seitenstruktur → Root | **Sprache** des Startpunkts (steuert die KI-Ausgabesprache!) | Pro Site |

> **Sprache:** KI-Ausgaben (Meta/FAQ/Glossar) folgen der Seiten-/Root-Sprache. Steht ein Root fälschlich auf `en`, kommen englische Texte. Fix: Root-Sprache korrigieren **oder** in „Verhalten → Sprach-Override“ z. B. `de` erzwingen.

---

## Lizenzmodell

Bezahltes Produkt mit optionaler **kostenloser Demo-Lizenz**. Jede Freischaltung – auch die Demo – wird gegen den Lizenzserver ([v-t.one](https://v-t.one)) geprüft; Umfang und Laufzeit legt der Server fest. Zwischenspeicherung mit Kulanzfenster, tägliche Hintergrund-Neuprüfung. Für Entwicklung/Staging: `SEO_STUDIO_LICENSE_BYPASS=1` (nie in Produktion).

---

## Anforderungen

- Contao 5.3+, PHP 8.2+
- Shared-Hosting-tauglich: kein `exec()`, kein Node, alles PHP + HttpClient + Contao-Cron
- API-Schlüssel verschlüsselt (defuse/php-encryption, Schlüsseldatei getrennt vom Chiffrat)
- Monatliches Token-Budget mit hartem Stopp

## Grundsätze

- Redakteurs-Inhalte werden nie still überschrieben: Vorschlagen → Vorschau → Übernehmen; Massenläufe füllen ausschließlich leere Felder.
- Erzeugte FAQ/Glossar-Einträge sind immer erst **Entwurf** – nichts wird ungeprüft öffentlich.
- LLM-Aufrufe nur per Klick oder Cron-Batch, nie beim Seitenaufbau.
- Deaktivierte Features hinterlassen null Spuren im Backend.
