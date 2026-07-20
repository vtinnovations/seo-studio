/* SEO Studio — global text/headline optimizer (score + rewrite + generate). */
(function () {
    'use strict';

    function fieldValue(fieldId) {
        if (window.tinymce && window.tinymce.get(fieldId)) {
            return window.tinymce.get(fieldId).getContent();
        }
        var el = document.getElementById(fieldId);
        return el ? el.value : null;
    }

    function setFieldValue(fieldId, value, isHeadline) {
        if (window.tinymce && window.tinymce.get(fieldId)) {
            window.tinymce.get(fieldId).setContent(value);
            return true;
        }
        var el = document.getElementById(fieldId);
        if (!el) {
            return false;
        }
        // Plain input/textarea: strip tags for headlines, keep for text areas.
        var out = value;
        if (isHeadline) {
            var tmp = document.createElement('div');
            tmp.innerHTML = value;
            out = (tmp.textContent || tmp.innerText || '').trim();
        }
        el.value = out;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    function initPanel(root) {
        if (root.dataset.seoStudioInit) {
            return;
        }
        root.dataset.seoStudioInit = '1';

        var btnCheck = root.querySelector('.seo-studio-opt-check');
        var btnRewrite = root.querySelector('.seo-studio-opt-rewrite');
        var verdict = root.querySelector('.seo-studio-verdict');
        var status = root.querySelector('.seo-studio-status');
        var result = root.querySelector('.seo-studio-result');
        var reason = root.querySelector('.seo-studio-reason');
        var altList = root.querySelector('.seo-studio-alternatives');
        var rewriteBox = root.querySelector('.seo-studio-rewrite-box');
        var rewritePreview = root.querySelector('.seo-studio-rewrite-preview');
        var btnApply = root.querySelector('.seo-studio-opt-apply');
        var btnDiscard = root.querySelector('.seo-studio-opt-discard');

        var isHeadline = root.dataset.fieldType === 'headline';
        var pendingRewrite = null;

        function setStatus(text, isError) {
            status.textContent = text || '';
            status.style.color = isError ? '#c33' : '';
        }

        function request(mode, onOk) {
            var value = fieldValue(root.dataset.target);
            if (value === null) {
                setStatus('Zielfeld nicht gefunden.', true);
                return;
            }

            btnCheck.disabled = true;
            btnRewrite.disabled = true;
            result.hidden = true;
            verdict.innerHTML = '';
            rewriteBox.hidden = true;

            var body = new URLSearchParams();
            body.set('table', root.dataset.table);
            body.set('rowId', root.dataset.rowId);
            body.set('fieldType', root.dataset.fieldType);
            body.set('mode', mode);
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
                .then(onOk)
                .catch(function (err) {
                    setStatus(err.message, true);
                })
                .then(function () {
                    btnCheck.disabled = false;
                    btnRewrite.disabled = false;
                });
        }

        btnCheck.addEventListener('click', function () {
            setStatus('Prüfe …', false);
            request('score', function (data) {
                setStatus('', false);
                verdict.innerHTML = '<span class="seo-studio-badge seo-studio-badge--' + data.color + '">' + data.score + '/100</span>';
                reason.textContent = data.reason;
                altList.innerHTML = '';
                (data.alternatives || []).forEach(function (alt) {
                    var li = document.createElement('li');
                    var span = document.createElement('span');
                    span.textContent = alt;
                    li.appendChild(span);
                    // Only headlines get an apply button for alternatives.
                    if (isHeadline) {
                        var apply = document.createElement('button');
                        apply.type = 'button';
                        apply.className = 'tl_submit seo-studio-apply-alt';
                        apply.textContent = 'Übernehmen';
                        apply.addEventListener('click', function () {
                            setFieldValue(root.dataset.target, alt, true)
                                ? setStatus('Übernommen — bitte prüfen und speichern.', false)
                                : setStatus('Zielfeld nicht gefunden.', true);
                        });
                        li.appendChild(apply);
                    }
                    altList.appendChild(li);
                });
                result.hidden = false;
            });
        });

        btnRewrite.addEventListener('click', function () {
            var current = fieldValue(root.dataset.target);
            var empty = !current || current.replace(/<[^>]*>/g, '').trim() === '';
            setStatus(empty ? 'Erzeuge aus Seiteninhalt …' : 'Optimiere …', false);
            request(empty ? 'generate' : 'rewrite', function (data) {
                setStatus('', false);
                pendingRewrite = data.rewrite || '';
                reason.textContent = data.reason || '';
                altList.innerHTML = '';
                // Show the proposal as text (headlines) or rendered HTML (text).
                if (isHeadline) {
                    rewritePreview.textContent = pendingRewrite.replace(/<[^>]*>/g, '').trim();
                } else {
                    rewritePreview.innerHTML = pendingRewrite;
                }
                rewriteBox.hidden = false;
                result.hidden = false;
            });
        });

        btnApply.addEventListener('click', function () {
            if (pendingRewrite === null) {
                return;
            }
            setFieldValue(root.dataset.target, pendingRewrite, isHeadline)
                ? setStatus('Übernommen — bitte prüfen und speichern.', false)
                : setStatus('Zielfeld nicht gefunden.', true);
        });

        btnDiscard.addEventListener('click', function () {
            pendingRewrite = null;
            rewriteBox.hidden = true;
            setStatus('', false);
        });
    }

    function initAll() {
        document.querySelectorAll('[data-seo-studio-optimize]').forEach(initPanel);
    }

    document.addEventListener('DOMContentLoaded', initAll);
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:frame-load', initAll);
    initAll();
})();
