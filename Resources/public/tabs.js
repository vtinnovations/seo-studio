/* SEO Studio — backend tab switching (Analyse module). Remembers the active
   tab across POST redirects via localStorage. */
(function () {
    'use strict';

    function initTabs(root) {
        if (root.dataset.seoTabsInit) {
            return;
        }
        root.dataset.seoTabsInit = '1';

        // Each tab group remembers its own active tab.
        var STORE_KEY = 'seoStudioTab:' + (root.dataset.tabsKey || 'default');

        // Only this group's own tabs/panels (not nested groups).
        var tabs = Array.prototype.slice.call(root.querySelectorAll('.seo-studio-tab'))
            .filter(function (t) { return t.closest('.seo-studio-tabs') === root; });
        var panels = Array.prototype.slice.call(root.querySelectorAll('.seo-studio-tabpanel'))
            .filter(function (p) { return p.closest('.seo-studio-tabs') === root; });
        if (tabs.length === 0) {
            return;
        }

        function activate(id) {
            var found = false;
            tabs.forEach(function (tab) {
                var on = tab.dataset.seoTab === id;
                tab.classList.toggle('is-active', on);
                if (on) {
                    found = true;
                }
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.dataset.seoPanel !== id;
            });
            return found;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var id = tab.dataset.seoTab;
                activate(id);
                try {
                    window.localStorage.setItem(STORE_KEY, id);
                } catch (e) {
                    /* private mode — ignore */
                }
            });
        });

        // Restore the last active tab (falls back to the first).
        var saved = null;
        try {
            saved = window.localStorage.getItem(STORE_KEY);
        } catch (e) {
            /* ignore */
        }
        if (!saved || !activate(saved)) {
            activate(tabs[0].dataset.seoTab);
        }
    }

    function initAll() {
        document.querySelectorAll('.seo-studio-tabs').forEach(initTabs);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
