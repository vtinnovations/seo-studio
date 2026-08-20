/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

/*
 * SEO Studio — score badges in backend lists.
 *
 * Contao 5.7 renders the content-element grid through its own mechanism that
 * bypasses the DCA label callback, so those rows cannot be decorated from PHP.
 * This script does it in the browser instead, using the record id/table that
 * Contao's own operations-menu controller already puts on each row.
 *
 * Maintenance-safe by design: every step is optional. If Contao changes the
 * markup, the selectors simply match nothing, no badge is drawn and the backend
 * keeps working exactly as before — no error, no broken layout.
 */
(function () {
    'use strict';

    var ID_ATTR = 'data-contao--operations-menu-record-id-value';
    var TABLE_ATTR = 'data-contao--operations-menu-record-table-value';

    function labelTarget(holder) {
        // Tree/list rows (articles, pages): the label column. The title sits
        // there as a bare text node, so we prepend to the column itself.
        var left = holder.querySelector('.tl_left');
        if (left) {
            return left;
        }

        // Grid rows (content elements): first childless element with short text.
        var nodes = holder.querySelectorAll('div, span, h1, h2, h3, h4, a');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (el.children.length === 0) {
                var text = (el.textContent || '').trim();
                if (text.length > 0 && text.length < 80) {
                    return el;
                }
            }
        }
        return null;
    }

    function decorate(holder, verdict) {
        if (!verdict || holder.querySelector('.seo-studio-pagebadge')) {
            return;
        }

        var target = labelTarget(holder);
        if (!target) {
            return;
        }

        var badge = document.createElement('span');
        badge.className = 'seo-studio-pagebadge seo-studio-pagebadge--' + verdict.color
            + (verdict.muted ? ' seo-studio-pagebadge--muted' : '');
        badge.textContent = verdict.score;
        badge.title = verdict.muted
            ? 'SEO-Formcheck: ' + verdict.score
                + '/100 — nicht veröffentlicht, zählt NICHT zur Seitenbewertung'
            : 'SEO-Formcheck: ' + verdict.score
                + '/100 — Element öffnen für die vollständige Prüfung inklusive Inhalt';
        target.insertBefore(badge, target.firstChild);
    }

    function run() {
        var config = window.SeoStudioListScores;
        if (!config || !config.url || !config.token) {
            return;
        }

        var holders = document.querySelectorAll('[' + ID_ATTR + ']');
        if (!holders.length) {
            return;
        }

        // Group rows by table — a view normally has exactly one.
        var byTable = {};
        Array.prototype.forEach.call(holders, function (holder) {
            var table = holder.getAttribute(TABLE_ATTR);
            var id = parseInt(holder.getAttribute(ID_ATTR), 10);
            if (!table || !id) {
                return;
            }
            (byTable[table] = byTable[table] || []).push({id: id, holder: holder});
        });

        Object.keys(byTable).forEach(function (table) {
            var rows = byTable[table];
            var body = new URLSearchParams();
            body.set('table', table);
            body.set('REQUEST_TOKEN', config.token);
            rows.forEach(function (r) { body.append('ids[]', r.id); });

            fetch(config.url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    if (!data || !data.scores) {
                        return;
                    }
                    rows.forEach(function (r) { decorate(r.holder, data.scores[String(r.id)]); });
                })
                .catch(function () { /* silent — badges are a bonus, never a blocker */ });
        });
    }

    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('turbo:load', run);
    document.addEventListener('turbo:frame-load', run);
    run();
})();
