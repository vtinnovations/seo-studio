🇩🇪 [Deutsche Version](README.md) — this is the English alternate-language version.

# AI SEO Studio for Contao 5

An AI-powered **SEO / GEO / AEO** suite delivered as **one** bundle with independently switchable feature modules.
Deterministic wherever possible, LLM only where language is generated or judged.

- **SEO** – classic search engines (Google & co.): titles, descriptions, structure, schema.
- **GEO** – *Generative Engine Optimization*: visible/citable in AI search (ChatGPT Search, Perplexity, Gemini).
- **AEO** – *Answer Engine Optimization*: direct answers (FAQ, answer-first openings).

Current status: a native, production-ready Contao 5 bundle (version 1.0.0). Every module described below is fully implemented and genuinely reachable through its backend/frontend entry point — nothing in this document describes a planned or future feature.

---

## Feature overview

| Module | Description | AI |
|---|---|---|
| **Per-page SEO score** | 0–100 traffic light directly in the page list + checklist in the page form (focus keyword, title/description, H1, subheadings, word count, alt texts, keyword placement, **readability**: sentence length, passive voice, Flesch, transition words, sentence openings) | – |
| **1-click AI fixes** | On the SEO score panel: "AI: suggest focus keyword" and "AI: generate title & description" – fills the fields, you review and save | ✔ |
| **Meta generation** | Page title + description with one click (preview → apply); a bulk run per root fills **only empty** fields; optional cron | ✔ |
| **Text optimization** | "Optimize with AI" button under **every** headline/text field (tl_content incl. Draggo, News, Events): score, rewrite (rich-text-aware), generate from page content | ✔ |
| **Inline checks** | Alt texts (vision – the model sees the image) + link texts ("click here" detection) directly on the element | ✔ |
| **FAQ** | Q&A drafts from genuine page content (centrally in "Content & Meta" **or** per page), curation in the backend, frontend module with **FAQPage** schema | ✔ |
| **AI glossary** | Enter terms or have them suggested from the website → answer-first definitions as drafts, A–Z frontend module + detail pages with **DefinedTermSet** schema, import from a legacy glossary bundle | ✔ |
| **Social media preview** | Open Graph + Twitter/X card fields per page (title/description/image) + live preview card; tags are emitted to the frontend automatically | – |
| **SEO/GEO/AEO score** | 0–100 maturity per page (meta, structure, answer-first, formats, FAQ, schema — freshness is informational only and does not count), broken down **by SEO / GEO / AEO** on the dashboard | partial |
| **AI crawler audit** | robots.txt check for GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended, Bingbot, including a fix suggestion | – |
| **Structure audit** | Heading hierarchy + answer-first check of the opening paragraph (per page) | partial |
| **Duplicates** | Identical titles/descriptions site-wide + a warning on save | – |
| **Structured data** | JSON-LD: Organization, BreadcrumbList, WebPage, Article, FAQPage, DefinedTermSet – injected before `</head>`, works with **any** template (including page builders) | – |
| **llms.txt** | `/llms.txt` built from the page structure, optionally with an AI-written short summary | optional |
| **Freshness** | `lastmod` in the sitemap + `dateModified` in schema, both from genuine change dates; monitor for stale pages | – |
| **Image audit** | Missing image sizes (1-click fix), oversized originals, WebP recommendation | – |

> **Structured data in detail:** the three toggles under "Settings → Structured data" (Organization/Breadcrumb/Article) cover exactly those three types. **WebPage** instead follows the Freshness toggle, **FAQPage** follows the FAQ toggle, and **DefinedTermSet/DefinedTerm** follows the Glossary toggle — there are deliberately no separate checkboxes for these.

Dashboard and analysis can be scoped to a single site root via **root selection** (when more than one root exists). Backend available in light and dark mode.

---

## Feature status

Every feature in this bundle is **Pro only**: without an activated licence, none of them is reachable at all (see [Licensing](#licensing)). Within the licence, all modules listed below are equally unlocked — there is no further tiering.

| Module | Status | Requires an AI key |
|---|---|---|
| Per-page SEO score (checklist, traffic light) | Pro only | No (1-click fixes: yes) |
| Meta generation | Pro only | Yes |
| Text optimization | Pro only | Partial (measurement is deterministic; rewrite/generate use AI) |
| Inline checks (alt text, link text) | Pro only | Yes |
| FAQ generation + frontend module | Pro only | Yes (generation), No (display) |
| AI glossary + frontend module | Pro only | Yes (generation), No (display) |
| Social media preview | Pro only | No |
| SEO/GEO/AEO score | Pro only | Partial (answer-first component optional via AI) |
| AI crawler audit | Pro only | No |
| Structure audit | Pro only | Partial (answer-first check optional via AI) |
| Duplicate detection | Pro only | No |
| Structured data (JSON-LD) | Pro only | No |
| llms.txt | Pro only | No (AI short summary optional) |
| Freshness monitor | Pro only | No |
| Image audit + size wizard | Pro only | No |

---

## Backend structure

Everything lives in the **SEO STUDIO** menu group (left backend navigation, scroll down if needed):

1. **Overview** – SEO/GEO/AEO score ring + SEO/GEO/AEO breakdown, coverage bars, prioritized to-do list with direct links.
2. **Content & Meta** – generate titles/descriptions, **FAQ**, and **glossary** entries via AI (empty fields / drafts only).
3. **Analysis** – crawler, structure, duplicates, SEO/GEO/AEO score, freshness, images (read-only, two click actions).
4. **FAQ** / **Glossary** – curate and **publish** generated content (new entries are always drafts first, with one exception; see [Known limitations](#known-limitations)).
5. **Settings** – AI provider, feature toggles, behaviour, schema.org.

This order matches the actual backend navigation. Individual blocks are optimized directly on the content element via "Optimize with AI." Every page additionally offers: the **SEO score panel** (with focus keyword + 1-click fixes), the **social preview**, and the **meta/FAQ panel**.

> Tip: click the ⭐ next to a module title to pin it to the favourites bar.

---

## System requirements

- **Contao** 5.3 or newer, **PHP** 8.2 or newer.
- PHP extensions: `sodium` and `json` are **required** (licence signature verification and data exchange, respectively); `curl` and `intl` are **recommended** (server-to-server usage signals and activation on internationalised domain names, respectively). Without `intl`, a non-ASCII domain name is rejected outright during activation rather than handled imprecisely.
- Composer dependencies: `symfony/http-client`, `symfony/http-foundation` (6.4/7.0-compatible), `defuse/php-encryption` ^2.4.
- **Shared-hosting compatible:** no `exec()`/`shell_exec()`/`proc_open()` and no Node.js build step are required. Everything runs in PHP, through the regular HTTP client and Contao's cron.
- Write permission on Contao's `var/` directory (see [Runtime directories](#runtime-directories)).
- For AI features and licence management: outbound HTTPS connections from the server (not the browser) to the configured AI provider or the vendor's licensing service.
- For analysis features with real values: at least one page-structure root with a configured **domain name**.

## Installation

1. **Install the package.** Contao Manager → upload the ZIP, **or**:
   ```bash
   composer require vtinnovations/seo-studio
   ```
2. **Update the database.** Contao Manager → "Update database", or:
   ```bash
   vendor/bin/contao-console contao:migrate
   ```
3. **Activate the licence** (see below) – without an activated licence the bundle stays completely invisible.
4. **Run "Update database" again.** The database fields for unlocked features (e.g. `tl_page.seoFocusKeyword`) are only created once the respective feature is active.
5. **Clear the cache** if the new menu group doesn't appear immediately (see [Cache clearing](#deployment)).

No additional executable and no separate service is required; installation runs entirely through Composer/Contao Manager and the Contao console.

## Licensing

AI SEO Studio is a **Pro product with exactly one licence tier**: there is no free tier, no trial period, and no automatic fallback to a free mode after expiry. Without an activated licence, the installation behaves exactly as if the bundle were not installed — no "SEO STUDIO" menu group, no panels, no frontend output (structured data and the FAQ/glossary frontend modules stay silent too). Existing page, FAQ, and glossary data is never touched or deleted by this — it is simply not processed or rendered until a valid licence is present again.

### Activating, refreshing, removing

1. **Set a domain on the root page.** Page structure → root page → "Domain name." Without a configured domain, the installation has no identity and activation is not possible.
2. Open **Contao → Settings → "AI SEO Studio Licence management,"** enter the licence key, click **"Verify & activate licence."**
3. Run "Update database" again afterwards (see above).

Further buttons in the same section:

- **"Update licence"** – re-checks the current licence status (e.g. after a renewal).
- **"Remove licence"** – asks for confirmation first, then disables all features immediately, **deletes no content**.

Both actions require an authenticated administrator session with a valid Contao security token. If a request to the licensing service fails (e.g. a network issue), the previously stored licence status remains unchanged — nothing is discarded or silently downgraded.

The licence applies **per instance** and is bound to the configured hostnames — exactly, with no wildcards: `example.com`, `www.example.com`, and `shop.example.com` are three distinct identities.

### Licence states

The settings page shows the current state as a status box: a plain-language headline plus one line of facts (masked licence key, package, version, bound domain, licensed domains, domain allowance, valid from, valid until, last verified):

| State | Meaning |
|---|---|
| No licence | Fresh install, nothing stored |
| Active | Licence valid, domain matches, all features unlocked |
| Domain mismatch | Licence valid, but not issued for the currently configured domain |
| Not yet valid | The licence's start date is in the future |
| Expired | Validity date has passed — no automatic fallback to a free tier |
| Refresh needed | The licence was issued before a later extension; a one-time click on "Update licence" resolves this |
| Invalid / unverifiable | The stored licence data fails verification (e.g. due to tampering) |

Every non-active state has the same result: the bundle remains completely invisible until the licence is valid again.

### Server communication (transparency)

Communication with the vendor's licensing service runs **exclusively server-to-server** over trusted HTTPS — the administrator's browser never contacts the licensing service directly. Exactly the following kinds of operations exist:

- **Activation and refresh** – triggered by an explicit administrator click (see above).
- **Vendor-initiated, signed licence updates** – e.g. after a renewal; verified locally against a cryptographic signature before being applied. Any failed check leaves the previously valid state unchanged.
- **A brief, server-side usage signal** on backend requests – runs only **after** the response has been delivered to the browser, so it never delays or affects what is displayed, and is silently discarded if unreachable.

The licence key **never** appears in full in the user interface, in ordinary logs, or in diagnostic output — the settings page shows it masked (four leading and four trailing characters around a fixed-width mask, so not even the real length is disclosed), which is enough to recognise which key is installed and not enough to reuse it, plus the uncritical facts listed above. The licence status is stored as a vendor-signed package outside the public web root; any change to the stored data invalidates the licence on the next check and cannot be "repaired" locally — only a fresh, valid issuance from the licensing service restores it.

## Connecting an AI provider

**Settings → AI settings**:

- **Provider:** Anthropic (Claude), OpenAI, or OpenAI-compatible (custom base URL, e.g. for self-hosted or alternative models with an OpenAI-compatible API).
- Enter the **API key** – stored **encrypted** (`defuse/php-encryption`, with the encryption key kept separate from the encrypted value, both outside the public web root) and **never shown in plain text**; leaving the field empty on save keeps the existing key.
- Optional: override the model (a default is set per provider), a monthly **token budget** (hard stop — at 100% every further AI request is refused, at 80% a warning appears on the settings page), and a "no external calls" switch to disable all AI network access entirely.
- **"Test connection"** performs a single, minimal request to the configured provider and reports success/failure including response time — this check does not count against the token budget.

> Without an AI key, all **deterministic** checks still run (core SEO score, crawler audit, basic structure check, duplicates, schema, social). Only the **generative** functions (meta, text, FAQ, glossary generation, alt-text suggestions, focus-keyword suggestion) require the key.

## Choosing features

**Settings → Features**: each of the 14 features can be switched on/off individually (see [Feature status](#feature-status) for the full list). Disabled features leave no trace in the backend — no menu entry, no button, no panel, no database fields for their settings.

## Organisation data for schema.org

**Settings → Structured data**: organisation name, logo URL, social profiles (`sameAs`), toggles for Organization/Breadcrumb/Article (see the note on WebPage/FAQPage/DefinedTermSet above), and the text used for `llms.txt`. Recommended for clean JSON-LD output.

## Placing frontend modules

For visible FAQ/glossary output, create one frontend module each and add it to a page/article:

- **"FAQ (SEO Studio)"** – accordion display of the current page's published FAQ entries + FAQPage schema.
- **"Glossary (SEO Studio)"** – A–Z list of published terms + dedicated detail pages with DefinedTerm schema.

Afterwards: populate pages (generate meta/FAQ/glossary), then review and **publish** drafts under **FAQ**/**Glossary**.

## What can/must be configured

| Location | Setting | Required? |
|---|---|---|
| Settings → AI | Provider + API key | Only for AI features |
| Settings → AI | Model, token budget, "no external calls" | Optional |
| Settings → Features | Feature toggles | Optional |
| Settings → Behaviour | Write mode, cron batch size, **language override**, meta cron | Optional |
| Settings → Structured data | Organisation, logo, sameAs, schema toggles, llms.txt text | Recommended |
| Page → Metadata | **Focus keyword**, social title/description, OG image | Per page |
| Page structure → Root | **Language** of the root page (drives the AI output language!) | Per site |

> **Language:** AI output (meta/FAQ/glossary) follows the page's/root's language. If a root is incorrectly set to `en`, English text is generated. Fix: correct the root language **or** force e.g. `de` under "Behaviour → Language override."

## Permissions and access control

Every backend module, panel, and AJAX endpoint in this bundle requires an authenticated Contao backend session with administrator rights and a valid Contao security token — there is no separate, parallel permission mechanism. The public `/llms.txt` route is deliberately unauthenticated (it only serves already-public page information) and consistently returns "not found" without a valid licence or when the feature is disabled. The one route this bundle opens for inbound calls from the licensing service does not require a browser session but a valid cryptographic signature from the vendor; every other call is rejected.

## Security model

- **Access control:** backend functionality is bound to Contao's own user and permission system; in addition, every function independently checks server-side whether it is licensed and enabled before doing anything.
- **Authenticity and integrity:** both the locally stored licence status and vendor-initiated updates are transmitted/stored cryptographically signed and re-verified before every use; any failed check always leads to the safer, more restrictive state, never a more permissive one.
- **Confidential storage:** the licence status and the AI API key are stored outside the public web root; the API key is additionally encrypted, with its key file kept separate from the encrypted value.
- **Trusted communication:** all external communication runs exclusively over HTTPS and exclusively server-to-server, never from the browser.
- **Safe failure behaviour:** any failure in licence, signature, or consistency checking results in a more restrictive, never a more permissive, state; nothing is "guessed" or optimistically assumed on failure.
- **Log redaction:** ordinary application logs never contain licence keys, API keys, signatures, or checksums — only uncritical facts are logged (e.g. operation type, result category, duration).

This description deliberately omits internal class, file, or protocol details of the security mechanisms — that is intentional, not an omission of substance.

## Operational safety

- Every write to the locally stored licence status and the encrypted credentials runs under an exclusive lock and atomically: an interrupted write (e.g. due to a crash) cannot leave the stored state half-written or unusable.
- The hourly meta cron is protected by a time-limited lock so two overlapping runs are excluded; if the token budget is hit mid-run, the entire batch stops cleanly and logs it instead of continuing or crashing; a failure on a single page only skips that one page.
- Generated FAQ and glossary entries are always created as **drafts** (one documented exception concerns glossary import of existing content; see [Known limitations](#known-limitations)) and must be reviewed and published by a person.
- Bulk runs (meta generation, cron) only ever change empty fields — existing editorial content is never overwritten.

These guarantees apply to the processes named; the bundle makes no broader rollback or transactional guarantee for Contao itself beyond that.

## Runtime directories

This bundle stores its own runtime data in a subdirectory of Contao's `var/` directory — i.e. outside the public web root and not reachable over HTTP. Stored there: the signed licence status, and the encrypted AI API key together with its separate key file. This directory must be writable by the web server process; a backup/deployment process should treat it like other `var/` contents (not part of the versioned source code, but part of the instance's data).

## External communication

| Purpose | Destination | Triggered by |
|---|---|---|
| AI generation (meta, text, FAQ, glossary, alt text, focus keyword) | the configured AI provider (Anthropic, OpenAI, or OpenAI-compatible) | clicking an "AI: …" action, or the optional meta cron |
| Connection test | the configured AI provider | clicking "Test connection" |
| robots.txt check | the site's own website (not a third party) | clicking "AI crawler audit" in Analysis |
| Licence activation/refresh/removal | the vendor's licensing service | an administrator click in the licence settings |
| Usage signal | the vendor's licensing service | a backend request (after the response has been delivered, without affecting what is displayed) |

All connections run server-to-server over HTTPS. Without an AI key, or with "no external calls" enabled, the first two rows never occur; the deterministic features are unaffected.

## Logging

Ordinary application logs (e.g. on cron failures) contain the operation type, result, and timing, but never API keys, licence keys, signatures, checksums, or full AI response text. Failed connection tests and cron runs report a general error category, not internal diagnostic detail.

## Deployment

- **Install/update:** `composer require vtinnovations/seo-studio` followed by `vendor/bin/contao-console contao:migrate` (or the corresponding Contao Manager actions).
- **Clear cache:** via Contao Manager ("Clear cache") or the standard Contao/Symfony console command `vendor/bin/contao-console cache:clear` — especially recommended after activating/removing the licence or toggling features, so the backend navigation reflects the new state immediately.
- No bundle-specific deployment step beyond this; there is no build process and no JavaScript dependencies to install.

## Tests

The bundle ships a PHPUnit test suite (`tests/`) and a standalone command-line tool (`tools/release-guard.php`):

```bash
composer install
vendor/bin/phpunit
php tools/release-guard.php        # or: composer run release-guard
```

`tools/release-guard.php` is primarily an internal release safety check (signature verification, consistency of internally used keys, protection against accidentally logging confidential data, among others) and, as part of that, also covers full translation parity: **289 interface-text keys, present identically in German and English**, matching `sprintf` placeholders per language, no inline fallback text in the code, and no newly introduced hardcoded interface text. It was run as part of this documentation review and passed completely (111 checks, no failures).

`phpstan.neon` configures static analysis at level 8 for `src/` (`vendor/bin/phpstan analyse`, after `composer install`).

## Languages

All interface text lives in `contao/languages/en/default.php` and `contao/languages/de/default.php` – **289 keys, identical in both languages**. The code contains no hardcoded interface text: everything is read exclusively through `Core\Config\Translations`, with **no inline fallback**. If a key is missing, `[key]` is shown instead of text – visibly, rather than silently falling back to English or German.

Adding another language means copying `default.php` (and the `tl_*.php` files) into a new language directory and translating the values – no code changes are needed for that.

Deliberately not translated: the **prompt text** sent to the language model (its exact wording is part of the instruction, not the interface) and the **German-language readability analysis** (Flesch-Amstad formula, transition words, passive-voice and filler-word detection) – that is domain logic for German text, not interface labelling (see [Known limitations](#known-limitations)). The AI's **output language** is determined independently by the root page's language or the language override.

## Principles

- Editorial content is never silently overwritten: propose → preview → apply; bulk runs only ever fill empty fields.
- Generated FAQ/glossary entries always start as **drafts** – nothing goes live without review (exception below).
- LLM calls happen only on click or in a cron batch, never while a page is being rendered.
- Disabled features leave zero trace in the backend.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "SEO STUDIO" menu group is missing entirely | No valid licence | Check the licence state under Settings → Licence management (see [Licence states](#licence-states)) |
| Menu group doesn't appear immediately after activation | Backend cache | Clear the cache (see [Cache clearing](#deployment)) |
| "AI: …" buttons do nothing / show an error | No API key set, an invalid key, or "no external calls" enabled | Check Settings → AI, use "Test connection" |
| AI generation stops with a budget error | Monthly token budget reached | Increase the budget or wait until next month; drafts already generated are kept |
| AI produces text in the wrong language | The root page has the wrong language configured | Correct the root language, or set a "Behaviour → Language override" |
| Newly expected database fields are missing (e.g. focus keyword) | Database migration wasn't re-run after activation | Run "Update database" again |
| Activation isn't possible | No domain name configured on the root page | Enter a domain name on the root page in the page structure |
| "Update licence" is requested for no obvious reason | The licence was issued before a later feature extension | Click "Update licence" once |
| The FAQ/glossary frontend module shows nothing | No published entries, or the feature is disabled | Review and publish drafts under FAQ/Glossary; check the feature toggle in settings |

## Known limitations

- **Readability analysis is German-specific.** The Flesch-Amstad calculation, transition-word detection, and passive-voice/filler-word heuristics in the SEO score and text optimization are tuned to German language rules and are applied regardless of a page's actual language. On non-German pages, this part of the scoring does not produce a meaningful assessment; all other checks (length, structure, duplicates, meta, schema) are language-independent.
- **A single licence model.** There is no free tier and no automatic fallback after expiry — an expired licence disables all features until renewed.
- **One exception to the draft rule.** When importing from a legacy glossary bundle, the import carries over that bundle's existing publication status unchanged (already-published legacy entries stay published) instead of forcing "draft," as every other AI generation path does.
- **The connection test does not count against the token budget**, but still incurs real provider cost and requires a valid key.

## Licensing and copyright information

© VT Innovations Team. This package is licensed under **LGPL-3.0-or-later** (see `composer.json`). The product name "AI SEO Studio" and its licensing as a Pro product (see [Licensing](#licensing)) are a separate matter: the bundle licence (LGPL) governs use and distribution of the source code; purchasing a product licence unlocks the server-enforced features.

---

🇩🇪 German version: [README.md](README.md)
