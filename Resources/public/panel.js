/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

/* SEO Studio — generic inline suggestion panel (headline/alt/teaser/linkText). */
(function () {
    'use strict';

    function getFieldValue(fieldId) {
        if (window.tinymce && window.tinymce.get(fieldId)) {
            return window.tinymce.get(fieldId).getContent({ format: 'text' });
        }
        var el = document.getElementById(fieldId);
        return el ? el.value : null;
    }

    function setFieldValue(fieldId, value) {
        if (window.tinymce && window.tinymce.get(fieldId)) {
            window.tinymce.get(fieldId).setContent(value);
            return true;
        }
        var el = document.getElementById(fieldId);
        if (!el) {
            return false;
        }
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    function initPanel(root) {
        if (root.dataset.seoStudioInit) {
            return;
        }
        root.dataset.seoStudioInit = '1';

        var btn = root.querySelector('.seo-studio-check');
        var verdict = root.querySelector('.seo-studio-verdict');
        var status = root.querySelector('.seo-studio-status');
        var result = root.querySelector('.seo-studio-result');
        var reason = root.querySelector('.seo-studio-reason');
        var list = root.querySelector('.seo-studio-alternatives');

        function setStatus(text, isError) {
            status.textContent = text || '';
            status.style.color = isError ? '#c33' : '';
        }

        btn.addEventListener('click', function () {
            var value = getFieldValue(root.dataset.target);
            if (value === null) {
                setStatus('Zielfeld nicht gefunden.', true);
                return;
            }

            btn.disabled = true;
            setStatus('Prüfe …', false);
            result.hidden = true;
            verdict.innerHTML = '';

            var body = new URLSearchParams();
            body.set('adapter', root.dataset.adapter);
            body.set('table', root.dataset.table);
            body.set('rowId', root.dataset.rowId);
            body.set('value', value);
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
                    verdict.innerHTML = '<span class="seo-studio-badge seo-studio-badge--' + data.color + '">' + data.score + '/100</span>';
                    reason.textContent = data.reason;
                    list.innerHTML = '';

                    (data.alternatives || []).forEach(function (alt) {
                        var li = document.createElement('li');
                        var text = document.createElement('span');
                        text.textContent = alt;
                        var apply = document.createElement('button');
                        apply.type = 'button';
                        apply.className = 'tl_submit seo-studio-apply-alt';
                        apply.textContent = 'Übernehmen';
                        apply.addEventListener('click', function () {
                            setFieldValue(root.dataset.target, alt)
                                ? setStatus('Übernommen — bitte prüfen und speichern.', false)
                                : setStatus('Zielfeld nicht gefunden.', true);
                        });
                        li.appendChild(text);
                        li.appendChild(apply);
                        list.appendChild(li);
                    });

                    result.hidden = false;
                    setStatus('', false);
                })
                .catch(function (err) {
                    setStatus(err.message, true);
                })
                .then(function () {
                    btn.disabled = false;
                });
        });
    }

    function initAll() {
        document.querySelectorAll('[data-seo-studio-panel]').forEach(initPanel);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
