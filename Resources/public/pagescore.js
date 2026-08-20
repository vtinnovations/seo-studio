/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

/* SEO Studio — per-page checklist 1-click AI fixes (keyword + meta). */
(function () {
    'use strict';

    function setInput(id, value) {
        var el = document.getElementById(id);
        if (!el) {
            return false;
        }
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function initPanel(root) {
        if (root.dataset.seoStudioPsInit) {
            return;
        }
        root.dataset.seoStudioPsInit = '1';

        var status = root.querySelector('.seo-studio-pf-status');

        function setStatus(text, isError) {
            if (!status) {
                return;
            }
            status.textContent = text || '';
            status.style.color = isError ? '#c33' : '';
        }

        function busy(on) {
            root.querySelectorAll('.seo-studio-pf-keyword, .seo-studio-pf-meta').forEach(function (b) {
                b.disabled = on;
            });
        }

        function post(url, onOk) {
            var body = new URLSearchParams();
            body.set('pageId', root.dataset.pageId);
            body.set('REQUEST_TOKEN', root.dataset.token);

            busy(true);
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.error || ('HTTP ' + response.status));
                        }
                        return data;
                    });
                })
                .then(onOk)
                .catch(function (err) {
                    setStatus(err.message, true);
                })
                .then(function () {
                    busy(false);
                });
        }

        root.addEventListener('click', function (ev) {
            var kw = ev.target.closest('.seo-studio-pf-keyword');
            if (kw) {
                ev.preventDefault();
                setStatus('KI sucht ein Fokus-Keyword …', false);
                post(root.dataset.keywordUrl, function (data) {
                    if (setInput('ctrl_seoFocusKeyword', data.keyword || '')) {
                        setStatus('Vorschlag: „' + data.keyword + '“ — prüfen und speichern.', false);
                    } else {
                        setStatus('Keyword-Feld nicht gefunden.', true);
                    }
                });
                return;
            }

            var meta = ev.target.closest('.seo-studio-pf-meta');
            if (meta) {
                ev.preventDefault();
                setStatus('KI erzeugt Titel & Beschreibung …', false);
                post(root.dataset.metaUrl, function (data) {
                    var okT = setInput('ctrl_pageTitle', data.pageTitle || '');
                    var okD = setInput('ctrl_description', data.description || '');
                    if (okT || okD) {
                        setStatus('Titel & Beschreibung eingesetzt — prüfen und speichern.', false);
                    } else {
                        setStatus('Titel-/Beschreibungsfeld nicht gefunden.', true);
                    }
                });
            }
        });
    }

    function initAll() {
        document.querySelectorAll('[data-seo-studio-pagescore]').forEach(initPanel);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
