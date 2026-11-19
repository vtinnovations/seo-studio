/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

/*
 * SEO Studio — live word/character counter under every TinyMCE editor.
 *
 * Reads only; never changes Contao's TinyMCE setup. Uses two TinyMCE APIs
 * that are stable across v4–v7: the global "AddEditor" event and
 * editor.getContent({format: 'text'}). If TinyMCE is absent or changes,
 * nothing is drawn and the backend keeps working exactly as before.
 *
 * Word rule = the server's (ContentExtractor: preg_split('/\s+/u')), so the
 * live number matches the counts in the SEO scores.
 */
(function () {
    'use strict';

    if (window.SeoStudioWordCountLoaded) {
        return; // Turbo can evaluate the script again — set up only once.
    }
    window.SeoStudioWordCountLoaded = true;

    var hooked = false;

    function label(words, chars) {
        var cfg = window.SeoStudioWordCountConfig || {};
        var tpl = cfg.label || '%words% words · %chars% characters';

        return tpl.replace('%words%', String(words)).replace('%chars%', String(chars));
    }

    function countWords(text) {
        var trimmed = (text || '').trim();

        return trimmed === '' ? 0 : trimmed.split(/\s+/u).length;
    }

    function attach(editor) {
        try {
            if (!editor || editor.inline) {
                return;
            }

            var container = editor.getContainer && editor.getContainer();
            if (!container || !container.parentNode) {
                return; // not rendered yet — the "init" handler retries
            }

            // Already attached to THIS container? (Turbo restores and
            // re-created editors get a fresh container.)
            var next = container.nextElementSibling;
            if (next && next.classList.contains('seo-studio-wordcount')) {
                editor.seoStudioCounter = next;
            } else {
                var box = document.createElement('div');
                box.className = 'seo-studio-wordcount';
                box.setAttribute('aria-live', 'polite');
                container.insertAdjacentElement('afterend', box);
                editor.seoStudioCounter = box;
            }

            if (!editor.seoStudioCounterBound) {
                editor.seoStudioCounterBound = true;
                editor.on('input change keyup paste SetContent Undo Redo', function () {
                    update(editor);
                });
            }

            update(editor);
        } catch (e) { /* silent — the counter is a bonus, never a blocker */ }
    }

    function update(editor) {
        var box = editor.seoStudioCounter;
        if (!box) {
            return;
        }

        try {
            var text = (editor.getContent({format: 'text'}) || '').replace(/ /g, ' ');
            var chars = text.replace(/\s+/gu, ' ').trim().length;

            // Text lives in a child <span>: Contao's theme zeroes the font size
            // of divs inside a widget and only re-sizes their children.
            var span = box.firstElementChild;
            if (!span) {
                box.textContent = '';
                span = document.createElement('span');
                box.appendChild(span);
            }
            span.textContent = label(countWords(text), chars);
        } catch (e) { /* editor removed mid-update */ }
    }

    function whenReady(editor) {
        if (!editor) {
            return;
        }
        if (editor.initialized || (editor.getContainer && editor.getContainer())) {
            attach(editor);
        }
        if (!editor.initialized && !editor.seoStudioInitBound) {
            editor.seoStudioInitBound = true;
            editor.on('init', function () { attach(editor); });
        }
    }

    function scan() {
        var tm = window.tinymce;
        if (!tm) {
            return false;
        }

        if (!hooked && typeof tm.on === 'function') {
            hooked = true;
            tm.on('AddEditor', function (e) {
                whenReady(e.editor);
            });
        }

        // Editors created before we hooked in — they may still be loading
        // (skin/plugins), so wait for their "init" instead of skipping them.
        var editors = typeof tm.get === 'function' ? tm.get() : [];
        (Array.isArray(editors) ? editors : []).forEach(whenReady);

        return true;
    }

    // Events alone are not enough: a site's own (outdated) be_tinyMCE template
    // may create editors before we hook in, and a failing plugin or licence
    // check can swallow the "init" we wait for. So after every page load we
    // also re-scan for ~15 s and attach to whatever editor is ready by then.
    var timer = null;

    function run() {
        scan();
        if (timer !== null) {
            clearInterval(timer);
        }
        var tries = 0;
        timer = setInterval(function () {
            scan();
            if (++tries >= 30) {
                clearInterval(timer);
                timer = null;
            }
        }, 500);
    }

    // Support aid: run SeoStudioWordCountDebug() in the browser console.
    window.SeoStudioWordCountDebug = function () {
        var tm = window.tinymce;
        var editors = tm && typeof tm.get === 'function' ? tm.get() : [];

        return {
            tinymce: tm ? (tm.majorVersion + '.' + tm.minorVersion) : 'not loaded',
            hooked: hooked,
            counters: document.querySelectorAll('.seo-studio-wordcount').length,
            editors: (Array.isArray(editors) ? editors : []).map(function (e) {
                return {id: e.id, initialized: !!e.initialized, hasContainer: !!(e.getContainer && e.getContainer()), counter: !!e.seoStudioCounter};
            })
        };
    };

    document.addEventListener('DOMContentLoaded', run);
    document.addEventListener('turbo:load', run);
    document.addEventListener('turbo:frame-load', run);
    run();
})();
