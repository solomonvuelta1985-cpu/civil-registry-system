/* Shared optional rich-text remarks editor and page-flow helpers for CRF 1A/2A/3A. */
(function () {
    'use strict';

    const allowedTags = new Set(['P', 'DIV', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'STRIKE', 'SUB', 'SUP', 'SPAN', 'OL', 'UL', 'LI', 'BLOCKQUOTE']);
    const discardedTags = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'VIDEO', 'AUDIO', 'FORM', 'INPUT', 'BUTTON']);
    const fonts = ['Arial', 'Calibri', 'Cambria', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Tahoma'];

    function safeColor(value) {
        const color = String(value || '').trim();
        return /^#[0-9a-f]{3}(?:[0-9a-f]{3})?(?:[0-9a-f]{2})?$/i.test(color) ? color : '';
    }

    function safeStyle(value) {
        const result = [];
        String(value || '').split(';').forEach(part => {
            const colon = part.indexOf(':');
            if (colon < 1) return;
            const property = part.slice(0, colon).trim().toLowerCase();
            const raw = part.slice(colon + 1).trim();
            if (property === 'color' || property === 'background-color') {
                const color = safeColor(raw);
                if (color) result.push(`${property}:${color}`);
            } else if (property === 'font-family') {
                const family = raw.replace(/^['"]|['"]$/g, '');
                const match = fonts.find(font => font.toLowerCase() === family.toLowerCase());
                if (match) result.push(`font-family:"${match}"`);
            } else if (property === 'font-size') {
                const match = raw.match(/^(\d+(?:\.\d+)?)pt$/i);
                if (match && Number(match[1]) >= 6 && Number(match[1]) <= 48) result.push(`font-size:${Number(match[1])}pt`);
            }
        });
        return result.join(';');
    }

    function cleanNode(node, target) {
        node.childNodes.forEach(child => {
            if (child.nodeType === Node.TEXT_NODE) {
                target.appendChild(document.createTextNode(child.nodeValue || ''));
                return;
            }
            if (child.nodeType !== Node.ELEMENT_NODE) return;
            const tag = child.tagName.toUpperCase();
            if (discardedTags.has(tag)) return;
            if (!allowedTags.has(tag)) {
                cleanNode(child, target);
                return;
            }
            const outputTag = tag === 'DIV' ? 'P' : tag.toLowerCase();
            const element = document.createElement(outputTag);
            if (child.hasAttribute('style')) {
                const style = safeStyle(child.getAttribute('style'));
                if (style) element.setAttribute('style', style);
            }
            cleanNode(child, element);
            target.appendChild(element);
        });
    }

    function sanitizeHtml(value) {
        const source = document.createElement('div');
        source.innerHTML = String(value || '');
        const clean = document.createElement('div');
        cleanNode(source, clean);
        return clean.innerHTML.trim();
    }

    function isEmpty(value) {
        const source = document.createElement('div');
        source.innerHTML = sanitizeHtml(value);
        return !(source.textContent || '').replace(/[\s\u00a0\u200b]/g, '');
    }

    function decodeEntities(value) {
        const decoder = document.createElement('textarea');
        decoder.innerHTML = value;
        return decoder.value;
    }

    function textWeight(html) {
        const safe = sanitizeHtml(html);
        const tokens = safe.match(/<[^>]+>|[^<]+/gu) || [];
        const stack = [];
        let total = 0;
        tokens.forEach(token => {
            if (token[0] === '<') {
                const closing = /^<\s*\//.test(token);
                const name = (token.match(/^<\s*\/?\s*([a-z0-9]+)/i) || [])[1]?.toLowerCase() || '';
                if (name === 'br') { total += 82; return; }
                if (['p', 'div', 'li', 'blockquote'].includes(name)) total += 28;
                if (closing) {
                    for (let i = stack.length - 1; i >= 0; i--) {
                        if (stack[i].name === name) { stack.splice(i, 1); break; }
                    }
                } else if (!/^<\s*[^>]+\s*\/>$/.test(token)) {
                    const size = (token.match(/font-size\s*:\s*(\d+(?:\.\d+)?)pt/i) || [])[1];
                    stack.push({ name, size: size ? Number(size) : null });
                }
                return;
            }
            let fontScale = 1;
            for (let i = stack.length - 1; i >= 0; i--) {
                if (stack[i].size) { fontScale = Math.max(.6, stack[i].size / 10.5); break; }
            }
            total += Array.from(decodeEntities(token)).reduce((sum, ch) => sum + (ch === '\n' || ch === '\r' ? 82 : fontScale), 0);
        });
        return total;
    }

    function escapeText(value) {
        const span = document.createElement('span');
        span.textContent = value;
        return span.innerHTML;
    }

    function splitHtml(html, firstLimit, continuationLimit) {
        const safe = sanitizeHtml(html);
        if (!safe) return [];
        const tokens = safe.match(/<[^>]+>|[^<]+/gu) || [];
        const pages = [];
        let output = '';
        let units = 0;
        let limit = firstLimit;
        let stack = [];
        const closeStack = () => stack.slice().reverse().map(item => `</${item.name}>`).join('');
        const reopenStack = () => stack.map(item => item.open).join('');
        const nextPage = () => {
            const closed = output + closeStack();
            if (closed.replace(/<[^>]+>/g, '').trim()) pages.push(closed);
            output = reopenStack();
            units = 0;
            limit = continuationLimit;
        };
        tokens.forEach(token => {
            if (token[0] === '<') {
                const closing = /^<\s*\//.test(token);
                const name = (token.match(/^<\s*\/?\s*([a-z0-9]+)/i) || [])[1]?.toLowerCase() || '';
                if (closing) {
                    output += token;
                    for (let i = stack.length - 1; i >= 0; i--) {
                        if (stack[i].name === name) { stack.splice(i, 1); break; }
                    }
                } else {
                    output += token;
                    if (name === 'br') units += 82;
                    else {
                        if (['p', 'div', 'li', 'blockquote'].includes(name)) units += 28;
                        if (!/^<\s*[^>]+\s*\/>$/.test(token)) stack.push({ name, open: token });
                    }
                }
                return;
            }
            let fontScale = 1;
            for (let i = stack.length - 1; i >= 0; i--) {
                const size = (stack[i].open.match(/font-size\s*:\s*(\d+(?:\.\d+)?)pt/i) || [])[1];
                if (size) { fontScale = Math.max(.6, Number(size) / 10.5); break; }
            }
            Array.from(decodeEntities(token)).forEach(character => {
                const cost = character === '\n' || character === '\r' ? 82 : fontScale;
                if (units >= limit && output.replace(/<[^>]+>/g, '').trim()) nextPage();
                output += escapeText(character);
                units += cost;
            });
        });
        const last = output + closeStack();
        if (last.replace(/<[^>]+>/g, '').trim()) pages.push(last);
        return pages;
    }

    function plan(html, singleLimit = 1200, firstLimit = 2300, continuationLimit = 3600) {
        const safe = sanitizeHtml(html);
        if (isEmpty(safe)) return [];
        if (textWeight(safe) <= singleLimit) return [safe];
        return splitHtml(safe, firstLimit, continuationLimit);
    }

    function heightMm(html) {
        const weight = textWeight(html);
        if (!weight) return 0;
        return Math.max(4.5, Math.ceil(weight / 82) * 4.5);
    }

    class Editor {
        constructor(backdrop, form, onChange) {
            this.backdrop = backdrop;
            this.form = form;
            this.onChange = onChange || function () {};
            this.field = form.elements.namedItem('remarks_html');
            if (!this.field) {
                this.field = document.createElement('input');
                this.field.type = 'hidden';
                this.field.name = 'remarks_html';
                form.appendChild(this.field);
            }
            this.addTrigger();
            this.createDialog();
        }

        addTrigger() {
            const actions = this.form.querySelector('.crf1a-form-actions');
            if (!actions) return;
            const group = document.createElement('div');
            group.className = 'crf1a-form-group crf-remarks-entry';
            group.innerHTML = '<label>Remarks</label><button type="button" class="crf1a-btn crf1a-btn-secondary" data-remarks-open>Add/Edit Remarks</button><div class="crf1a-form-help">Optional. Remarks appear on the certificate only when entered.</div>';
            actions.parentNode.insertBefore(group, actions);
            group.querySelector('[data-remarks-open]').addEventListener('click', () => this.open());
        }

        createDialog() {
            this.dialog = document.createElement('div');
            this.dialog.className = 'crf-remarks-backdrop';
            this.dialog.hidden = true;
            const fontOptions = fonts.map(font => `<option value="${font}">${font}</option>`).join('');
            this.dialog.innerHTML = `<div class="crf-remarks-modal" role="dialog" aria-modal="true" aria-labelledby="crfRemarksTitle">
                <div class="crf-remarks-header"><h3 id="crfRemarksTitle">Add/Edit Remarks</h3><button type="button" class="crf-remarks-close" data-remarks-cancel aria-label="Close">×</button></div>
                <div class="crf-remarks-toolbar" role="toolbar" aria-label="Remarks formatting">
                    <select data-font aria-label="Font">${fontOptions}</select><select data-size aria-label="Font size">${[8,9,10,10.5,11,12,14,16,18,20,24,28,32,36,48].map(n=>`<option value="${n}">${n}</option>`).join('')}</select>
                    <button type="button" data-command="bold" title="Bold"><b>B</b></button><button type="button" data-command="italic" title="Italic"><i>I</i></button><button type="button" data-command="underline" title="Underline"><u>U</u></button><button type="button" data-command="strikeThrough" title="Strikethrough"><s>S</s></button>
                    <button type="button" data-command="subscript" title="Subscript">x₂</button><button type="button" data-command="superscript" title="Superscript">x²</button>
                    <label class="crf-remarks-color" title="Text color">A <input type="color" data-color value="#000000" aria-label="Text color"></label><label class="crf-remarks-color" title="Highlight">▰ <input type="color" data-highlight value="#fff176" aria-label="Highlight color"></label>
                    <select data-case aria-label="Change case"><option value="">Aa</option><option value="upper">UPPERCASE</option><option value="lower">lowercase</option><option value="title">Title Case</option></select><button type="button" data-command="removeFormat" title="Clear formatting">Tx</button>
                </div>
                <div class="crf-remarks-editable" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Remarks text"></div>
                <div class="crf-remarks-footer"><span>Text and formatting are preserved in the issuance copy.</span><div><button type="button" class="crf1a-btn crf1a-btn-secondary" data-remarks-clear>Clear</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-remarks-cancel>Cancel</button><button type="button" class="crf1a-btn crf1a-btn-primary" data-remarks-save>Save Remarks</button></div></div>
            </div>`;
            document.body.appendChild(this.dialog);
            this.editable = this.dialog.querySelector('.crf-remarks-editable');
            this.editable.addEventListener('input', () => this.sync());
            this.editable.addEventListener('keyup', () => this.saveSelection());
            this.editable.addEventListener('mouseup', () => this.saveSelection());
            this.editable.addEventListener('paste', event => this.pasteSanitized(event));
            this.editable.addEventListener('drop', event => this.pasteSanitized(event));
            document.addEventListener('selectionchange', () => this.saveSelection());
            this.dialog.querySelectorAll('[data-font], [data-size], [data-color], [data-highlight]').forEach(control => control.addEventListener('mousedown', () => this.saveSelection()));
            this.dialog.querySelectorAll('[data-remarks-cancel]').forEach(button => button.addEventListener('click', () => this.close(false)));
            this.dialog.querySelector('[data-remarks-save]').addEventListener('click', () => this.close(true));
            this.dialog.querySelector('[data-remarks-clear]').addEventListener('click', () => { this.editable.innerHTML = ''; this.sync(); this.editable.focus(); });
            this.dialog.querySelectorAll('[data-command]').forEach(button => button.addEventListener('mousedown', event => { this.saveSelection(); event.preventDefault(); }));
            this.dialog.querySelectorAll('[data-command]').forEach(button => button.addEventListener('click', () => {
                this.restoreSelection();
                this.editable.focus();
                document.execCommand(button.dataset.command, false, null);
                this.sync();
            }));
            this.dialog.querySelector('[data-font]').addEventListener('change', event => this.applyInlineStyle('font-family', event.target.value));
            this.dialog.querySelector('[data-size]').addEventListener('change', event => this.applyInlineStyle('font-size', `${event.target.value}pt`));
            this.dialog.querySelector('[data-color]').addEventListener('input', event => { this.restoreSelection(); this.editable.focus(); document.execCommand('foreColor', false, event.target.value); this.sync(); });
            this.dialog.querySelector('[data-highlight]').addEventListener('input', event => { this.restoreSelection(); this.editable.focus(); document.execCommand('hiliteColor', false, event.target.value); this.sync(); });
            this.dialog.querySelector('[data-case]').addEventListener('change', event => this.changeCase(event.target.value));
        }

        applyInlineStyle(property, value) {
            this.restoreSelection();
            this.editable.focus();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || selection.isCollapsed) return;
            const range = selection.getRangeAt(0);
            const span = document.createElement('span');
            span.style.setProperty(property, value);
            try { span.appendChild(range.extractContents()); range.insertNode(span); selection.removeAllRanges(); }
            catch (_) { return; }
            this.sync();
        }

        changeCase(mode) {
            if (!mode) return;
            this.restoreSelection();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount || selection.isCollapsed) return;
            const range = selection.getRangeAt(0);
            const source = range.toString();
            const converted = mode === 'upper' ? source.toLocaleUpperCase() : (mode === 'lower' ? source.toLocaleLowerCase() : source.toLocaleLowerCase().replace(/(^|[.!?]\s+|\n)(\p{L})/gu, (_, prefix, letter) => prefix + letter.toLocaleUpperCase()));
            range.deleteContents();
            range.insertNode(document.createTextNode(converted));
            selection.removeAllRanges();
            this.sync();
            this.dialog.querySelector('[data-case]').value = '';
        }

        sync() {
            this.field.value = sanitizeHtml(this.editable.innerHTML);
            this.onChange(this.field.value);
        }

        saveSelection() {
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            if (this.editable.contains(range.commonAncestorContainer)) this.savedRange = range.cloneRange();
        }

        restoreSelection() {
            if (!this.savedRange) return;
            const selection = window.getSelection();
            if (!selection) return;
            try { selection.removeAllRanges(); selection.addRange(this.savedRange); } catch (_) { this.savedRange = null; }
        }

        pasteSanitized(event) {
            event.preventDefault();
            const source = event.clipboardData || event.dataTransfer;
            const pastedHtml = source?.getData('text/html') || '';
            const pastedText = source?.getData('text/plain') || '';
            const container = document.createElement('div');
            container.innerHTML = pastedHtml ? sanitizeHtml(pastedHtml) : escapeText(pastedText).replace(/\r?\n/g, '<br>');
            this.restoreSelection();
            this.editable.focus();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) { this.editable.appendChild(container); }
            else {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                const fragment = document.createDocumentFragment();
                while (container.firstChild) fragment.appendChild(container.firstChild);
                const lastNode = fragment.lastChild;
                range.insertNode(fragment);
                if (lastNode) {
                    const next = document.createRange();
                    next.setStartAfter(lastNode);
                    next.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(next);
                    this.savedRange = next.cloneRange();
                }
            }
            this.sync();
        }

        open() {
            this.editable.innerHTML = sanitizeHtml(this.field.value);
            this.dialog.hidden = false;
            this.dialog.classList.add('is-open');
            this.editable.focus();
        }

        close(save) {
            if (save) this.sync();
            else this.editable.innerHTML = sanitizeHtml(this.field.value);
            this.dialog.classList.remove('is-open');
            this.dialog.hidden = true;
            this.onChange(this.field.value);
        }
    }

    window.CrfRemarksEditor = {
        attach(backdrop, form, onChange) { return new Editor(backdrop, form, onChange); },
        sanitize: sanitizeHtml,
        isEmpty,
        textWeight,
        heightMm,
        plan
    };
})();
