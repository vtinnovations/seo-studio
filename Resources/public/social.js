/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

/* SEO Studio — live social-media preview card (title/description). */
(function () {
    'use strict';

    function val(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function initCard(root) {
        if (root.dataset.seoStudioSocialInit) {
            return;
        }
        root.dataset.seoStudioSocialInit = '1';

        var titleEl = root.querySelector('[data-role="title"]');
        var descEl = root.querySelector('[data-role="desc"]');
        var fbTitle = root.dataset.fallbackTitle || '';
        var fbDesc = root.dataset.fallbackDesc || '';

        function update() {
            var title = val('ctrl_seoSocialTitle') || val('ctrl_pageTitle') || fbTitle;
            var desc = val('ctrl_seoSocialDescription') || val('ctrl_description') || fbDesc;
            if (titleEl) {
                titleEl.textContent = title || 'Kein Titel';
            }
            if (descEl) {
                descEl.textContent = desc;
            }
        }

        ['ctrl_seoSocialTitle', 'ctrl_pageTitle', 'ctrl_seoSocialDescription', 'ctrl_description'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', update);
            }
        });

        update();
    }

    function initAll() {
        document.querySelectorAll('[data-seo-studio-social]').forEach(initCard);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
