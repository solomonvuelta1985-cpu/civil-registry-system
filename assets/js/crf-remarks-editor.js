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
            } else if (property === 'line-height') {
                const lineHeight = Number(raw);
                if ([1, 1.15, 1.5, 2].includes(lineHeight)) result.push(`line-height:${lineHeight}`);
            } else if (property === 'margin-bottom') {
                const match = raw.match(/^(0|1\.5)mm$/i);
                if (match) result.push(`margin-bottom:${match[1]}mm`);
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
            const isRemarksRoot = tag === 'DIV' && child.getAttribute('data-remarks-root') === '1';
            const outputTag = tag === 'DIV' && !isRemarksRoot ? 'P' : tag.toLowerCase();
            const element = document.createElement(outputTag);
            if (isRemarksRoot) element.setAttribute('data-remarks-root', '1');
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

    function styleNumber(tag, property, unit = '') {
        const style = (tag.match(/\bstyle\s*=\s*(["'])(.*?)\1/i) || [])[2] || '';
        const match = style.match(new RegExp(`(?:^|;)\\s*${property}\\s*:\\s*(\\d+(?:\\.\\d+)?)(?:${unit})?`, 'i'));
        return match ? Number(match[1]) : null;
    }

    function inheritedNumber(stack, property, fallback) {
        for (let i = stack.length - 1; i >= 0; i--) {
            if (stack[i][property] !== null && stack[i][property] !== undefined) return stack[i][property];
        }
        return fallback;
    }

    function addParagraphWeight(stack, cost) {
        for (let i = stack.length - 1; i >= 0; i--) {
            if (stack[i].paragraphUnits !== null && stack[i].paragraphUnits !== undefined) {
                stack[i].paragraphUnits += cost;
                return;
            }
        }
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
                if (name === 'br') {
                    const cost = 82 * inheritedNumber(stack, 'lineHeight', 1);
                    total += cost;
                    addParagraphWeight(stack, cost);
                    return;
                }
                if (closing) {
                    for (let i = stack.length - 1; i >= 0; i--) {
                        if (stack[i].name === name) {
                            const closed = stack.splice(i, 1)[0];
                            if (closed.paragraphUnits !== null && closed.paragraphUnits !== undefined) {
                                total += Math.max(0, 82 * closed.paragraphLineHeight - closed.paragraphUnits);
                            }
                            if (closed.marginBottom) total += closed.marginBottom * 82 / 4.5;
                            break;
                        }
                    }
                } else if (!/^<\s*[^>]+\s*\/>$/.test(token)) {
                    stack.push({
                        name,
                        size: styleNumber(token, 'font-size', 'pt'),
                        lineHeight: styleNumber(token, 'line-height'),
                        marginBottom: styleNumber(token, 'margin-bottom', 'mm'),
                        paragraphUnits: ['p', 'li'].includes(name) ? 0 : null,
                        paragraphLineHeight: styleNumber(token, 'line-height') ?? inheritedNumber(stack, 'lineHeight', 1)
                    });
                }
                return;
            }
            const fontScale = inheritedNumber(stack, 'size', 9.5) / 8;
            const lineScale = inheritedNumber(stack, 'lineHeight', 1);
            Array.from(decodeEntities(token)).forEach(character => {
                const cost = (character === '\n' || character === '\r' ? 82 : fontScale) * lineScale;
                total += cost;
                addParagraphWeight(stack, cost);
            });
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
            stack.forEach(item => { if (item.paragraphUnits !== null && item.paragraphUnits !== undefined) item.paragraphUnits = 0; });
        };
        tokens.forEach(token => {
            if (token[0] === '<') {
                const closing = /^<\s*\//.test(token);
                const name = (token.match(/^<\s*\/?\s*([a-z0-9]+)/i) || [])[1]?.toLowerCase() || '';
                const paragraphStart = !closing && ['p', 'li'].includes(name);
                const paragraphLineHeight = styleNumber(token, 'line-height') ?? inheritedNumber(stack, 'lineHeight', 1);
                const hasPageText = output.replace(/<[^>]+>/g, '').trim();
                if (paragraphStart && units + 82 * paragraphLineHeight > limit && hasPageText) nextPage();
                else if (!closing && name !== 'br' && units >= limit && hasPageText) nextPage();
                if (closing) {
                    output += token;
                    for (let i = stack.length - 1; i >= 0; i--) {
                        if (stack[i].name === name) {
                            const closed = stack.splice(i, 1)[0];
                            if (closed.paragraphUnits !== null && closed.paragraphUnits !== undefined) {
                                units += Math.max(0, 82 * closed.paragraphLineHeight - closed.paragraphUnits);
                            }
                            if (closed.marginBottom) units += closed.marginBottom * 82 / 4.5;
                            break;
                        }
                    }
                } else {
                    output += token;
                    if (name === 'br') {
                        const cost = 82 * inheritedNumber(stack, 'lineHeight', 1);
                        units += cost;
                        addParagraphWeight(stack, cost);
                    }
                    else {
                        if (!/^<\s*[^>]+\s*\/>$/.test(token)) stack.push({
                            name,
                            open: token,
                            size: styleNumber(token, 'font-size', 'pt'),
                            lineHeight: styleNumber(token, 'line-height'),
                            marginBottom: styleNumber(token, 'margin-bottom', 'mm'),
                            paragraphUnits: ['p', 'li'].includes(name) ? 0 : null,
                            paragraphLineHeight: paragraphLineHeight
                        });
                    }
                }
                return;
            }
            const fontScale = inheritedNumber(stack, 'size', 9.5) / 8;
            const lineScale = inheritedNumber(stack, 'lineHeight', 1);
            Array.from(decodeEntities(token)).forEach(character => {
                const cost = (character === '\n' || character === '\r' ? 82 : fontScale) * lineScale;
                if (units >= limit && output.replace(/<[^>]+>/g, '').trim()) nextPage();
                output += escapeText(character);
                units += cost;
                addParagraphWeight(stack, cost);
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
            this.savedRemarksHtml = '';
            this.templates = [];
            this.templatesLoaded = false;
            this.templatesLoading = false;
            this.templateLoadPromise = null;
            this.pendingTemplateId = 0;
            this.pendingTemplateName = '';
            this.openingRemarksHtml = '';
            this.format = { fontFamily: 'Courier New', fontSize: 9.5, lineHeight: 1, paragraphSpace: 0 };
            this.contentRoot = null;
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
            const fontOptions = fonts.map(font => `<option value="${font}"${font === 'Courier New' ? ' selected' : ''}>${font}</option>`).join('');
            this.dialog.innerHTML = `<div class="crf-remarks-modal" role="dialog" aria-modal="true" aria-labelledby="crfRemarksTitle">
                <div class="crf-remarks-header"><h3 id="crfRemarksTitle">Add/Edit Remarks</h3><button type="button" class="crf-remarks-close" data-remarks-cancel aria-label="Close">×</button></div>
                <div class="crf-remarks-toolbar" role="toolbar" aria-label="Remarks formatting">
                    <select data-font aria-label="Font">${fontOptions}</select><select data-size aria-label="Font size">${[6,7,8,9,9.5,10,10.5,11,12,14,16,18,20,24,28,32,36,48].map(n=>`<option value="${n}"${n===9.5?' selected':''}>${n}</option>`).join('')}</select>
                    <select data-line-spacing aria-label="Line spacing" title="Line spacing"><option value="1" selected>1.0</option><option value="1.15">1.15</option><option value="1.5">1.5</option><option value="2">2.0</option></select><select data-paragraph-space aria-label="Paragraph spacing" title="Paragraph spacing after each paragraph"><option value="0" selected>No paragraph gap</option><option value="1.5">1.5 mm after paragraphs</option></select>
                    <button type="button" data-command="bold" title="Bold"><b>B</b></button><button type="button" data-command="italic" title="Italic"><i>I</i></button><button type="button" data-command="underline" title="Underline"><u>U</u></button><button type="button" data-command="strikeThrough" title="Strikethrough"><s>S</s></button>
                    <button type="button" data-command="subscript" title="Subscript">x₂</button><button type="button" data-command="superscript" title="Superscript">x²</button>
                    <label class="crf-remarks-color" title="Text color">A <input type="color" data-color value="#000000" aria-label="Text color"></label><label class="crf-remarks-color" title="Highlight">▰ <input type="color" data-highlight value="#fff176" aria-label="Highlight color"></label>
                    <select data-case aria-label="Change case"><option value="">Aa</option><option value="upper">UPPERCASE</option><option value="lower">lowercase</option><option value="title">Title Case</option></select><button type="button" data-command="removeFormat" title="Clear formatting">Tx</button><button type="button" data-remarks-saved title="View remarks from previous CRF issuances" aria-label="View previous remarks" disabled>History</button>
                </div>
                <div class="crf-remarks-editable" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Remarks text"></div>
                <div class="crf-remarks-footer"><span>Text and formatting are preserved in the issuance copy.</span><div><button type="button" class="crf1a-btn crf1a-btn-secondary" data-template-open>Save as Template</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-remarks-clear>Clear</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-remarks-cancel>Cancel</button><button type="button" class="crf1a-btn crf1a-btn-primary" data-remarks-save>Save Remarks</button></div></div>
            </div>`;
            document.body.appendChild(this.dialog);
            this.editable = this.dialog.querySelector('.crf-remarks-editable');
            this.savedDialog = document.createElement('div');
            this.savedDialog.className = 'crf-remarks-saved-backdrop';
            this.savedDialog.hidden = true;
            this.savedDialog.innerHTML = `<div class="crf-remarks-saved-modal" role="dialog" aria-modal="true" aria-labelledby="crfSavedRemarksTitle"><div class="crf-remarks-header"><h3 id="crfSavedRemarksTitle">Remarks Library</h3><button type="button" class="crf-remarks-close" data-saved-remarks-close aria-label="Close">×</button></div><div class="crf-remarks-library-tabs" role="tablist" aria-label="Remarks library"><button type="button" class="crf-remarks-library-tab" data-library-tab="history" role="tab" aria-selected="true">Issuance History</button><button type="button" class="crf-remarks-library-tab" data-library-tab="templates" role="tab" aria-selected="false">My Templates</button></div><div class="crf-remarks-saved-body"><section class="crf-remarks-library-panel" data-library-panel="history" role="tabpanel"><div class="crf-remarks-saved-preview" data-saved-remarks-preview></div></section><section class="crf-remarks-library-panel" data-library-panel="templates" role="tabpanel" hidden><div class="crf-remarks-template-form"><label for="crfRemarksTemplateName">Template name</label><input id="crfRemarksTemplateName" type="text" maxlength="120" required data-template-name placeholder="Name this template"><div class="crf-remarks-template-draft" data-template-draft-preview></div><div class="crf-remarks-template-form-actions"><span data-template-status role="status" aria-live="polite"></span><button type="button" class="crf1a-btn crf1a-btn-primary" data-template-save-current>Save Current Remarks</button></div></div><div class="crf-remarks-template-list" data-template-list></div></section></div><div class="crf-remarks-footer"><span data-library-footer>Showing the latest saved remarks from this CRF's issuance history.</span><div><button type="button" class="crf1a-btn crf1a-btn-secondary" data-saved-remarks-close>Close</button><button type="button" class="crf1a-btn crf1a-btn-primary" data-saved-remarks-restore>Restore to Editor</button></div></div></div>`;
            document.body.appendChild(this.savedDialog);
            this.savedPreview = this.savedDialog.querySelector('[data-saved-remarks-preview]');
            this.savedRestoreButton = this.savedDialog.querySelector('[data-saved-remarks-restore]');
            this.savedRemarksButton = this.dialog.querySelector('[data-remarks-saved]');
            this.savedRemarksButton.disabled = false;
            this.savedRemarksButton.addEventListener('click', () => this.openSavedRemarks());
            this.savedDialog.querySelectorAll('[data-library-tab]').forEach(button => button.addEventListener('click', () => this.showLibraryTab(button.dataset.libraryTab)));
            this.savedDialog.querySelectorAll('[data-saved-remarks-close]').forEach(button => button.addEventListener('click', () => this.closeSavedRemarks()));
            this.savedDialog.querySelector('[data-saved-remarks-restore]').addEventListener('click', () => this.restoreSavedRemarks());
            this.savedDialog.addEventListener('click', event => { if (event.target === this.savedDialog) this.closeSavedRemarks(); });
            this.savedDialog.querySelector('[data-template-save-current]').addEventListener('click', () => this.saveTemplateFromEditor());
            this.savedDialog.querySelector('[data-template-list]').addEventListener('click', event => this.handleTemplateAction(event));
            this.dialog.querySelector('[data-template-open]').addEventListener('click', () => this.openTemplateSave());
            this.editable.addEventListener('input', () => this.sync());
            this.editable.addEventListener('keyup', () => this.saveSelection());
            this.editable.addEventListener('mouseup', () => this.saveSelection());
            this.editable.addEventListener('paste', event => this.pasteSanitized(event));
            this.editable.addEventListener('drop', event => this.pasteSanitized(event));
            document.addEventListener('selectionchange', () => this.saveSelection());
            this.dialog.querySelectorAll('[data-font], [data-size], [data-line-spacing], [data-paragraph-space], [data-color], [data-highlight]').forEach(control => control.addEventListener('mousedown', () => this.saveSelection()));
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
            this.dialog.querySelector('[data-font]').addEventListener('change', event => this.setBlockFormat('fontFamily', event.target.value));
            this.dialog.querySelector('[data-size]').addEventListener('change', event => this.setBlockFormat('fontSize', Number(event.target.value)));
            this.dialog.querySelector('[data-line-spacing]').addEventListener('change', event => this.setBlockFormat('lineHeight', Number(event.target.value)));
            this.dialog.querySelector('[data-paragraph-space]').addEventListener('change', event => this.setBlockFormat('paragraphSpace', Number(event.target.value)));
            this.dialog.querySelector('[data-color]').addEventListener('input', event => { this.restoreSelection(); this.editable.focus(); document.execCommand('foreColor', false, event.target.value); this.sync(); });
            this.dialog.querySelector('[data-highlight]').addEventListener('input', event => { this.restoreSelection(); this.editable.focus(); document.execCommand('hiliteColor', false, event.target.value); this.sync(); });
            this.dialog.querySelector('[data-case]').addEventListener('change', event => this.changeCase(event.target.value));
            this.editable.addEventListener('keydown', event => this.handleTab(event));
        }

        readFormat(source) {
            if (!source) return;
            const family = String(source.style?.fontFamily || '').replace(/["']/g, '').trim();
            const safeFamily = fonts.find(font => font.toLowerCase() === family.toLowerCase());
            const size = Number.parseFloat(source.style?.fontSize || '');
            const lineHeight = Number.parseFloat(source.style?.lineHeight || '');
            if (safeFamily) this.format.fontFamily = safeFamily;
            if ([6, 7, 8, 9, 9.5, 10, 10.5, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 48].includes(size)) this.format.fontSize = size;
            if ([1, 1.15, 1.5, 2].includes(lineHeight)) this.format.lineHeight = lineHeight;
        }

        loadEditable(html) {
            this.format = { fontFamily: 'Courier New', fontSize: 9.5, lineHeight: 1, paragraphSpace: 0 };
            this.editable.innerHTML = sanitizeHtml(html);
            let root = Array.from(this.editable.children).find(node => node.tagName === 'DIV' && node.getAttribute('data-remarks-root') === '1');
            if (root) this.readFormat(root);
            else {
                const styled = this.editable.querySelector('[style]');
                this.readFormat(styled);
                root = document.createElement('div');
                root.setAttribute('data-remarks-root', '1');
                while (this.editable.firstChild) root.appendChild(this.editable.firstChild);
                this.editable.appendChild(root);
            }
            const paragraph = root.querySelector('p,div,li,blockquote');
            const space = Number.parseFloat(paragraph?.style?.marginBottom || '');
            this.format.paragraphSpace = space === 1.5 ? 1.5 : 0;
            this.contentRoot = root;
            this.syncFormatControls();
            this.normalizeBlockStyles();
        }

        syncFormatControls() {
            this.dialog.querySelector('[data-font]').value = this.format.fontFamily;
            this.dialog.querySelector('[data-size]').value = String(this.format.fontSize);
            this.dialog.querySelector('[data-line-spacing]').value = String(this.format.lineHeight);
            this.dialog.querySelector('[data-paragraph-space]').value = String(this.format.paragraphSpace);
        }

        normalizeBlockStyles() {
            if (!this.contentRoot || !this.contentRoot.isConnected) {
                this.contentRoot = Array.from(this.editable.children).find(node => node.tagName === 'DIV' && node.getAttribute('data-remarks-root') === '1');
            }
            if (!this.contentRoot) return;
            this.contentRoot.style.fontFamily = `"${this.format.fontFamily}"`;
            this.contentRoot.style.fontSize = `${this.format.fontSize}pt`;
            this.contentRoot.style.lineHeight = String(this.format.lineHeight);
            const blocks = this.contentRoot.querySelectorAll('p,div,li,blockquote');
            blocks.forEach(block => {
                block.style.removeProperty('font-family');
                block.style.removeProperty('font-size');
                block.style.removeProperty('line-height');
            });
            this.contentRoot.querySelectorAll('[style]').forEach(child => {
                child.style.removeProperty('font-family');
                child.style.removeProperty('font-size');
                child.style.removeProperty('line-height');
                child.style.removeProperty('margin-bottom');
                if (!child.getAttribute('style')?.trim()) child.removeAttribute('style');
            });
            blocks.forEach(block => {
                const containsAnotherBlock = block.querySelector('p,div,li,blockquote');
                block.style.marginBottom = containsAnotherBlock ? '' : `${this.format.paragraphSpace}mm`;
            });
        }

        setBlockFormat(property, value) {
            this.saveSelection();
            this.format[property] = value;
            this.syncFormatControls();
            this.normalizeBlockStyles();
            this.sync();
            this.editable.focus();
            this.restoreSelection();
        }

        handleTab(event) {
            if (event.key !== 'Tab') return;
            event.preventDefault();
            const selection = window.getSelection();
            if (!selection || !selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            if (event.shiftKey) {
                let node = selection.anchorNode;
                node = node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement;
                while (node && node !== this.contentRoot && !node.matches?.('p,div,li,blockquote')) node = node.parentElement;
                const block = node && node !== this.contentRoot ? node : this.contentRoot;
                if (!block) return;
                const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
                let text = walker.nextNode();
                while (text && !text.nodeValue) text = walker.nextNode();
                const indent = '\u00a0'.repeat(4);
                if (!text?.nodeValue?.startsWith(indent)) return;
                const remove = document.createRange();
                remove.setStart(text, 0);
                remove.setEnd(text, indent.length);
                remove.deleteContents();
                remove.collapse(true);
                selection.removeAllRanges();
                selection.addRange(remove);
                this.savedRange = remove.cloneRange();
            } else {
                range.deleteContents();
                const indent = document.createTextNode('\u00a0'.repeat(4));
                range.insertNode(indent);
                range.setStartAfter(indent);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
                this.savedRange = range.cloneRange();
            }
            this.sync();
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
            this.normalizeBlockStyles();
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
            this.pendingTemplateId = 0;
            this.pendingTemplateName = '';
            this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
            this.openingRemarksHtml = this.field.value;
            this.loadEditable(this.field.value);
            this.dialog.hidden = false;
            this.dialog.classList.add('is-open');
            this.editable.focus();
        }

        setSavedRemarks(html) {
            this.savedRemarksHtml = sanitizeHtml(String(html || ''));
            const hasSavedRemarks = !isEmpty(this.savedRemarksHtml);
            this.savedRemarksButton.disabled = false;
            this.savedRemarksButton.title = hasSavedRemarks
                ? 'View remarks from previous CRF issuances'
                : 'Check the CRF issuance history for saved remarks';
            this.savedRestoreButton.disabled = !hasSavedRemarks;
            return hasSavedRemarks;
        }

        openSavedRemarks() {
            this.savedPreview.innerHTML = isEmpty(this.savedRemarksHtml)
                ? '<p class="crf-remarks-saved-empty">No saved remarks were found in this CRF\'s issuance history.</p>'
                : this.savedRemarksHtml;
            this.savedDialog.querySelector('[data-template-name]').value = '';
            this.savedDialog.hidden = false;
            this.savedDialog.classList.add('is-open');
            this.showLibraryTab('history');
            this.savedDialog.querySelector('[data-saved-remarks-close]')?.focus();
        }

        showLibraryTab(tab) {
            const activeTab = tab === 'templates' ? 'templates' : 'history';
            this.savedDialog.querySelectorAll('[data-library-tab]').forEach(button => {
                button.setAttribute('aria-selected', button.dataset.libraryTab === activeTab ? 'true' : 'false');
            });
            this.savedDialog.querySelectorAll('[data-library-panel]').forEach(panel => {
                panel.hidden = panel.dataset.libraryPanel !== activeTab;
            });
            this.savedDialog.querySelector('[data-saved-remarks-restore]').hidden = activeTab !== 'history';
            this.savedDialog.querySelector('[data-library-footer]').textContent = activeTab === 'history'
                ? 'Showing the latest saved remarks from this CRF\'s issuance history.'
                : 'Templates are private to your account and reusable across CRF forms.';
            if (activeTab === 'templates') {
                const currentHtml = sanitizeHtml(this.field.value);
                const nameField = this.savedDialog.querySelector('[data-template-name]');
                const draft = this.savedDialog.querySelector('[data-template-draft-preview]');
                draft.innerHTML = currentHtml;
                if (!nameField.value) nameField.value = this.pendingTemplateName || this.firstNonEmptyLine(currentHtml);
                this.loadTemplates(true);
            }
        }

        async loadTemplates(force = false) {
            if (this.templatesLoading) return this.templateLoadPromise;
            if (this.templatesLoaded && !force) return this.templates;
            this.templatesLoading = true;
            this.setTemplateStatus('Loading your templates...');
            this.templateLoadPromise = (async () => {
                try {
                    const response = await fetch('../api/crf_remarks_templates.php', { credentials: 'same-origin' });
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load your remarks templates.');
                    this.templates = Array.isArray(data.data?.templates) ? data.data.templates : [];
                    this.templatesLoaded = true;
                    this.renderTemplates();
                    this.setTemplateStatus('');
                    return this.templates;
                } catch (error) {
                    this.setTemplateStatus(error.message || 'Unable to load your remarks templates.', true);
                    return null;
                } finally {
                    this.templatesLoading = false;
                    this.templateLoadPromise = null;
                }
            })();
            return this.templateLoadPromise;
        }

        renderTemplates() {
            const list = this.savedDialog.querySelector('[data-template-list]');
            if (!this.templates.length) {
                list.innerHTML = '<p class="crf-remarks-saved-empty">You have no saved templates yet. Enter remarks, then choose Save as Template.</p>';
                return;
            }
            list.innerHTML = this.templates.map(template => `<article class="crf-remarks-template-card" data-template-id="${Number(template.id) || 0}"><div class="crf-remarks-template-card-header"><strong>${escapeText(template.name || '')}</strong><span>${escapeText(template.updated_at || '')}</span></div><div class="crf-remarks-template-card-preview">${sanitizeHtml(template.remarks_html || '')}</div><div class="crf-remarks-template-card-actions"><button type="button" class="crf1a-btn crf1a-btn-secondary" data-template-action="replace">Replace</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-template-action="append">Append</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-template-action="edit">Edit</button><button type="button" class="crf1a-btn crf1a-btn-secondary" data-template-action="delete">Delete</button></div></article>`).join('');
        }

        setTemplateStatus(message, error = false) {
            const status = this.savedDialog.querySelector('[data-template-status]');
            if (!status) return;
            status.textContent = message || '';
            status.classList.toggle('error', Boolean(error));
        }

        firstNonEmptyLine(html) {
            const source = document.createElement('div');
            source.innerHTML = sanitizeHtml(html);
            const text = (source.innerText || source.textContent || '').replace(/\u00a0/g, ' ');
            return text.split(/\r?\n/).map(line => line.trim()).find(Boolean)?.slice(0, 120) || '';
        }

        openTemplateSave() {
            this.sync();
            if (isEmpty(this.field.value)) {
                this.notifyTemplate('Enter remarks before saving a template.', true);
                return;
            }
            const nameField = this.savedDialog.querySelector('[data-template-name]');
            nameField.value = this.pendingTemplateId
                ? this.pendingTemplateName
                : this.firstNonEmptyLine(this.field.value);
            this.savedDialog.querySelector('[data-template-draft-preview]').innerHTML = sanitizeHtml(this.field.value);
            this.savedDialog.querySelector('[data-template-save-current]').textContent = this.pendingTemplateId ? 'Update Template' : 'Save Current Remarks';
            this.setTemplateStatus('');
            this.savedDialog.hidden = false;
            this.savedDialog.classList.add('is-open');
            this.showLibraryTab('templates');
            nameField.focus();
        }

        notifyTemplate(message, error = false) {
            const notify = typeof Notiflix !== 'undefined' && Notiflix.Notify
                ? (error ? Notiflix.Notify.failure : Notiflix.Notify.info)
                : null;
            if (typeof notify === 'function') notify(message);
            else window.alert(message);
        }

        async saveTemplateFromEditor() {
            this.sync();
            const name = this.savedDialog.querySelector('[data-template-name]').value.trim();
            const html = sanitizeHtml(this.field.value);
            if (!name) return this.setTemplateStatus('Enter a name for this template.', true);
            if (isEmpty(html)) return this.setTemplateStatus('Enter remarks before saving a template.', true);
            const formData = new FormData();
            formData.append('action', this.pendingTemplateId ? 'update' : 'create');
            formData.append('name', name);
            formData.append('remarks_html', html);
            if (this.pendingTemplateId) formData.append('id', String(this.pendingTemplateId));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrf) formData.append('csrf_token', csrf);
            const button = this.savedDialog.querySelector('[data-template-save-current]');
            button.disabled = true;
            this.setTemplateStatus(this.pendingTemplateId ? 'Updating template...' : 'Saving template...');
            try {
                await this.loadTemplates();
                const response = await fetch('../api/crf_remarks_templates.php', {
                    method: 'POST', body: formData,
                    headers: csrf ? { 'X-CSRF-Token': csrf } : {}, credentials: 'same-origin'
                });
                const data = await response.json();
                if (!response.ok || !data.success || !data.data?.template) throw new Error(data.message || 'Unable to save this template.');
                const saved = data.data.template;
                const index = this.templates.findIndex(item => Number(item.id) === Number(saved.id));
                if (index >= 0) this.templates[index] = saved;
                else this.templates.unshift(saved);
                this.templates.sort((left, right) => String(right.updated_at || '').localeCompare(String(left.updated_at || '')));
                this.templatesLoaded = true;
                this.renderTemplates();
                this.pendingTemplateId = 0;
                this.pendingTemplateName = '';
                this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
                this.savedDialog.querySelector('[data-template-save-current]').textContent = 'Save Current Remarks';
                this.setTemplateStatus(data.message || 'Template saved.');
            } catch (error) {
                this.setTemplateStatus(error.message || 'Unable to save this template.', true);
            } finally {
                button.disabled = false;
            }
        }

        async handleTemplateAction(event) {
            const button = event.target.closest('[data-template-action]');
            if (!button) return;
            const card = button.closest('[data-template-id]');
            const template = this.templates.find(item => Number(item.id) === Number(card?.dataset.templateId));
            if (!template) return;
            const action = button.dataset.templateAction;
            if (action === 'delete') {
                if (!window.confirm(`Delete the template "${template.name}"?`)) return;
                return this.deleteTemplate(template, button);
            }
            if (action === 'edit') {
                this.pendingTemplateId = Number(template.id);
                this.pendingTemplateName = String(template.name || '');
                this.dialog.querySelector('[data-template-open]').textContent = 'Update Template';
                this.loadEditable(template.remarks_html || '');
                this.sync();
                this.closeSavedRemarks();
                return;
            }
            this.applyTemplate(template, action);
        }

        applyTemplate(template, mode) {
            const html = sanitizeHtml(template.remarks_html || '');
            const current = sanitizeHtml(this.field.value);
            if (mode === 'replace' && !isEmpty(current) && !window.confirm('Replace the current remarks with this template?')) return;
            this.pendingTemplateId = 0;
            this.pendingTemplateName = '';
            this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
            if (mode === 'replace' || isEmpty(current)) {
                this.loadEditable(html);
            } else {
                this.loadEditable(current);
                const roots = Array.from(this.editable.children).filter(node => node.nodeType === Node.ELEMENT_NODE && node.tagName === 'DIV' && node.getAttribute('data-remarks-root') === '1');
                const lastRoot = roots[roots.length - 1] || this.contentRoot;
                if (lastRoot) lastRoot.insertAdjacentHTML('beforeend', '<p><br></p>');
                const source = document.createElement('div');
                source.innerHTML = html;
                const templateRoot = Array.from(source.children).find(node => node.tagName === 'DIV' && node.getAttribute('data-remarks-root') === '1');
                if (templateRoot) this.editable.appendChild(templateRoot.cloneNode(true));
                else {
                    const wrapper = document.createElement('div');
                    wrapper.setAttribute('data-remarks-root', '1');
                    while (source.firstChild) wrapper.appendChild(source.firstChild);
                    this.editable.appendChild(wrapper);
                }
            }
            this.sync();
            this.closeSavedRemarks();
        }

        async deleteTemplate(template, button) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', String(template.id));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrf) formData.append('csrf_token', csrf);
            button.disabled = true;
            this.setTemplateStatus('Deleting template...');
            try {
                const response = await fetch('../api/crf_remarks_templates.php', {
                    method: 'POST', body: formData,
                    headers: csrf ? { 'X-CSRF-Token': csrf } : {}, credentials: 'same-origin'
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to delete this template.');
                this.templates = this.templates.filter(item => Number(item.id) !== Number(template.id));
                if (Number(this.pendingTemplateId) === Number(template.id)) {
                    this.pendingTemplateId = 0;
                    this.pendingTemplateName = '';
                    this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
                }
                this.renderTemplates();
                this.setTemplateStatus('Template deleted.');
            } catch (error) {
                this.setTemplateStatus(error.message || 'Unable to delete this template.', true);
                button.disabled = false;
            }
        }

        closeSavedRemarks() {
            this.savedDialog.classList.remove('is-open');
            this.savedDialog.hidden = true;
            this.editable.focus();
        }

        restoreSavedRemarks() {
            if (isEmpty(this.savedRemarksHtml)) return;
            const current = sanitizeHtml(this.field.value);
            if (!isEmpty(current) && current !== this.savedRemarksHtml && !window.confirm('Replace the current remarks with the remarks saved on this CRF?')) return;
            this.pendingTemplateId = 0;
            this.pendingTemplateName = '';
            this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
            this.loadEditable(this.savedRemarksHtml);
            this.sync();
            this.closeSavedRemarks();
        }

        close(save) {
            if (save) {
                this.sync();
                this.openingRemarksHtml = this.field.value;
            } else {
                this.field.value = this.openingRemarksHtml;
                this.loadEditable(this.field.value);
            }
            this.pendingTemplateId = 0;
            this.pendingTemplateName = '';
            this.dialog.querySelector('[data-template-open]').textContent = 'Save as Template';
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
