/* SEO Studio — meta proposal panel in tl_page (vanilla JS, no build step). */
(function () {
    'use strict';

    function initPanel(root) {
        if (root.dataset.seoStudioInit) {
            return;
        }
        root.dataset.seoStudioInit = '1';

        var btnGenerate = root.querySelector('.seo-studio-generate');
        var btnApply = root.querySelector('.seo-studio-apply');
        var btnDiscard = root.querySelector('.seo-studio-discard');
        var status = root.querySelector('.seo-studio-status');
        var result = root.querySelector('.seo-studio-result');
        var proposal = null;

        initFaq(root);

        if (!btnGenerate) {
            return; // meta section disabled, FAQ only
        }

        function setStatus(text, isError) {
            status.textContent = text || '';
            status.style.color = isError ? '#c33' : '';
        }

        function fieldInput(name) {
            return document.getElementById('ctrl_' + name);
        }

        // Configurable targets: tl_page uses pageTitle/description, the
        // glossary panel overrides via data attributes.
        var idParam = root.dataset.idParam || 'pageId';
        var targetTitle = root.dataset.targetTitle || 'pageTitle';
        var targetDescription = root.dataset.targetDescription || 'description';

        btnGenerate.addEventListener('click', function () {
            btnGenerate.disabled = true;
            setStatus('Generiere Vorschlag …', false);
            result.hidden = true;

            var body = new URLSearchParams();
            body.set(idParam, root.dataset.pageId);
            body.set('REQUEST_TOKEN', root.dataset.token);

            fetch(root.dataset.url, {
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
                .then(function (data) {
                    proposal = data;
                    root.querySelector('[data-role="pageTitle"]').textContent = data.pageTitle;
                    root.querySelector('[data-role="pageTitleLen"]').textContent = '(' + data.pageTitle.length + ' Zeichen)';
                    root.querySelector('[data-role="description"]').textContent = data.description;
                    root.querySelector('[data-role="descriptionLen"]').textContent = '(' + data.description.length + ' Zeichen)';
                    result.hidden = false;
                    setStatus('', false);
                })
                .catch(function (err) {
                    setStatus(err.message, true);
                })
                .then(function () {
                    btnGenerate.disabled = false;
                });
        });

        btnApply.addEventListener('click', function () {
            if (!proposal) {
                return;
            }

            var titleInput = fieldInput(targetTitle);
            var descriptionInput = fieldInput(targetDescription);

            if (titleInput) {
                titleInput.value = proposal.pageTitle;
                titleInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (descriptionInput) {
                descriptionInput.value = proposal.description;
                descriptionInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            setStatus(titleInput || descriptionInput
                ? 'Übernommen — bitte prüfen und speichern.'
                : 'Zielfelder nicht gefunden.', !titleInput && !descriptionInput);
        });

        btnDiscard.addEventListener('click', function () {
            proposal = null;
            result.hidden = true;
            setStatus('', false);
        });
    }

    function initFaq(root) {
        var btn = root.querySelector('.seo-studio-faq-generate');
        if (!btn) {
            return;
        }

        var count = root.querySelector('.seo-studio-faq-count');
        var status = root.querySelector('.seo-studio-faq-status');

        btn.addEventListener('click', function () {
            btn.disabled = true;
            status.textContent = 'Generiere FAQ-Entwürfe … (kann 30 s dauern)';
            status.style.color = '';

            var body = new URLSearchParams();
            body.set('pageId', root.dataset.pageId);
            body.set('count', count.value);
            body.set('REQUEST_TOKEN', root.dataset.token);

            fetch(btn.dataset.url, {
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
                .then(function (data) {
                    status.textContent = data.message;
                })
                .catch(function (err) {
                    status.textContent = err.message;
                    status.style.color = '#c33';
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    function initAll() {
        document.querySelectorAll('[data-seo-studio-meta]').forEach(initPanel);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
