(function () {
    'use strict';
    if (window.__documentEditorLoaded) return;
    window.__documentEditorLoaded = true;

    var EDITABLE_SELECTOR = 'h1,h2,h3,h4,h5,h6,p,td,th,li,div,span,label,figcaption,blockquote,address';
    var SKIP_SELECTOR = '.no-print, .print-button, .doc-editor-toolbar, script, style, link, meta, input, textarea, select, button, iframe, [contenteditable]';
    var originalHTML = null;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function injectStyles() {
        if (document.getElementById('doc-editor-styles')) return;
        var style = document.createElement('style');
        style.id = 'doc-editor-styles';
        style.textContent = [
            '.doc-editor-toolbar{position:fixed;top:16px;right:16px;z-index:2147483000;display:flex;gap:8px;align-items:center;background:#1f2937;padding:10px 14px;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,.35);font-family:Segoe UI,Tahoma,Arial,sans-serif}',
            '.doc-editor-toolbar button{border:none;border-radius:6px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit}',
            '.doc-editor-edit-btn{background:#2563eb;color:#fff}',
            '.doc-editor-done-btn{background:#16a34a;color:#fff}',
            '.doc-editor-reset-btn{background:#4b5563;color:#fff}',
            '.doc-editor-hint{color:#d1d5db;font-size:12px;margin-right:6px;display:none}',
            '.doc-editing .doc-editor-hint{display:inline}',
            '.doc-editor-toolbar .doc-editor-state{color:#fbbf24;font-size:12px;font-weight:600;margin-right:6px}',
            '.doc-editing [data-doc-editable]:hover{outline:1.5px dashed #2563eb !important;outline-offset:1px;background:rgba(37,99,235,.06);border-radius:2px}',
            '.doc-editing [data-doc-editable]:focus{outline:2px solid #2563eb !important;background:rgba(37,99,235,.08)}',
            '@media print{.doc-editor-toolbar{display:none !important}}'
        ].join('\n');
        document.head.appendChild(style);
    }

    function isEditableTarget(el) {
        if (!el || el.nodeType !== 1) return false;
        if (el.closest(SKIP_SELECTOR)) return false;
        if (!el.textContent.trim()) return false;
        if (el.querySelector('input,textarea,select,button,iframe')) return false;
        var hasDirectText = false;
        for (var i = 0; i < el.childNodes.length; i++) {
            if (el.childNodes[i].nodeType === 3 && el.childNodes[i].nodeValue.trim()) {
                hasDirectText = true;
                break;
            }
        }
        return hasDirectText;
    }

    function enableEditing() {
        if (originalHTML === null) {
            originalHTML = document.body.innerHTML;
        }
        var nodes = document.querySelectorAll(EDITABLE_SELECTOR);
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (!isEditableTarget(el)) continue;
            el.setAttribute('contenteditable', 'true');
            el.setAttribute('data-doc-editable', '1');
            el.setAttribute('spellcheck', 'false');
        }
        document.body.classList.add('doc-editing');
    }

    function disableEditing() {
        var nodes = document.querySelectorAll('[data-doc-editable]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].removeAttribute('contenteditable');
        }
        document.body.classList.remove('doc-editing');
    }

    function resetDocument() {
        if (originalHTML === null) return;
        disableEditing();
        document.body.innerHTML = originalHTML;
        originalHTML = null;
        window.scrollTo(0, 0);
    }

    function init() {
        if (!document.body) return;
        injectStyles();

        var toolbar = document.createElement('div');
        toolbar.className = 'doc-editor-toolbar';
        var state = document.createElement('span');
        state.className = 'doc-editor-state';
        state.textContent = 'Read only';
        var hint = document.createElement('span');
        hint.className = 'doc-editor-hint';
        hint.textContent = 'Click any text to edit it';
        var editBtn = document.createElement('button');
        editBtn.className = 'doc-editor-edit-btn';
        editBtn.textContent = '\u270f Edit';
        var doneBtn = document.createElement('button');
        doneBtn.className = 'doc-editor-done-btn';
        doneBtn.textContent = '\u2713 Done & Print';
        var resetBtn = document.createElement('button');
        resetBtn.className = 'doc-editor-reset-btn';
        resetBtn.textContent = '\u21a9 Reset';
        toolbar.appendChild(state);
        toolbar.appendChild(hint);
        toolbar.appendChild(editBtn);
        toolbar.appendChild(doneBtn);
        toolbar.appendChild(resetBtn);
        document.body.appendChild(toolbar);

        if (document.querySelector('.print-button')) {
            toolbar.style.top = '90px';
        }

        editBtn.addEventListener('click', function () {
            enableEditing();
            state.textContent = 'Editing';
            editBtn.style.display = 'none';
            doneBtn.style.display = 'inline-block';
            resetBtn.style.display = 'inline-block';
        });

        doneBtn.addEventListener('click', function () {
            disableEditing();
            state.textContent = 'Read only';
            editBtn.style.display = 'inline-block';
            window.print();
        });

        resetBtn.addEventListener('click', function () {
            resetDocument();
            state.textContent = 'Read only';
            editBtn.style.display = 'inline-block';
            doneBtn.style.display = 'inline-block';
            resetBtn.style.display = 'inline-block';
        });
    }

    ready(init);
})();
