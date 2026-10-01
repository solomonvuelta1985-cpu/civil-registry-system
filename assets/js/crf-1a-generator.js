/* CRF No. 1A generator: source lookup, live A4 preview, and immutable issuance. */
(function () {
    'use strict';

    class Crf1AGenerator {
        constructor() {
            this.backdrop = null;
            this.modal = null;
            this.form = null;
            this.record = null;
            this.pdfFrame = null;
            this.previewHost = null;
            this.status = null;
            this.generateButton = null;
            this.loadingOverlay = null;
            this.existingIssuances = [];
            this.replacesIssuanceId = 0;
            this.issuanceCheckPending = false;
            this.issuanceCheckComplete = false;
            this.previewScale = 1;
            this.previewRotation = 0;
            this.previewCurrentPage = 1;
            this.previewTotalPages = 1;
            this.createModal();
        }

        createModal() {
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'crf1a-generator-backdrop';
            this.backdrop.setAttribute('aria-hidden', 'true');
            this.backdrop.innerHTML = `
                <div class="crf1a-generator-modal" role="dialog" aria-modal="true" aria-labelledby="crf1aGeneratorTitle">
                    <div class="crf1a-generator-header">
                        <div>
                            <h2 id="crf1aGeneratorTitle"><i data-lucide="file-check-2"></i> Generate CRF No. 1A</h2>
                            <p>Birth-Available certification • source values are captured as an issuance snapshot</p>
                        </div>
                        <button type="button" class="crf1a-close" data-crf1a-close aria-label="Close"><i data-lucide="x"></i></button>
                    </div>
                    <div class="crf1a-generator-body">
                        <div class="crf1a-generator-form-panel">
                            <div class="crf1a-section-title"><i data-lucide="clipboard-pen-line"></i><span>Issuance Details</span></div>
                            <form id="crf1aGeneratorForm" novalidate>
                                <div class="crf1a-two-col">
                                    <div class="crf1a-form-group"><label for="crf1aPageNumber">Page Number <span class="required">*</span></label><input id="crf1aPageNumber" name="page_number" class="crf1a-form-control" maxlength="50" required></div>
                                    <div class="crf1a-form-group"><label for="crf1aBookNumber">Book Number <span class="required">*</span></label><input id="crf1aBookNumber" name="book_number" class="crf1a-form-control" maxlength="50" required></div>
                                </div>
                                <div class="crf1a-form-group"><label for="crf1aPopulationReference">Population Reference No.</label><input id="crf1aPopulationReference" name="population_reference_no" class="crf1a-form-control" maxlength="100"></div>
                                <div class="crf1a-form-group"><label for="crf1aRequester">Requester</label><input id="crf1aRequester" name="requester_name" class="crf1a-form-control" maxlength="150"></div>
                                <div class="crf1a-two-col">
                                    <div class="crf1a-form-group"><label for="crf1aAmountPaid">Amount Paid <span class="required">*</span></label><input id="crf1aAmountPaid" name="amount_paid" class="crf1a-form-control" inputmode="decimal" placeholder="0.00" required></div>
                                    <div class="crf1a-form-group"><label for="crf1aOrNumber">O.R. Number <span class="required">*</span></label><input id="crf1aOrNumber" name="or_number" class="crf1a-form-control" maxlength="100" required></div>
                                </div>
                                <div class="crf1a-two-col">
                                    <div class="crf1a-form-group"><label for="crf1aDatePaid">Date Paid <span class="required">*</span></label><input id="crf1aDatePaid" name="date_paid" type="date" class="crf1a-form-control" required></div>
                                    <div class="crf1a-form-group"><label for="crf1aIssueDate">Issue Date</label><input id="crf1aIssueDate" name="issue_date" type="date" class="crf1a-form-control" readonly></div>
                                </div>
                                <div class="crf1a-section-title" style="margin-top:20px;"><i data-lucide="badge-check"></i><span>Registrar and Certification</span></div>
                                <div class="crf1a-form-group"><label for="crf1aMcrName">Municipal Civil Registrar Name <span class="required">*</span></label><input id="crf1aMcrName" name="mcr_full_name" class="crf1a-form-control" maxlength="150" placeholder="Enter registrar name" required></div>
                                <div class="crf1a-form-group"><label for="crf1aMcrPosition">Municipal Civil Registrar Position <span class="required">*</span></label><input id="crf1aMcrPosition" name="mcr_title" class="crf1a-form-control" maxlength="100" placeholder="Enter registrar position" required></div>
                                <div class="crf1a-form-group"><label for="crf1aCertifiedName">Certified By Name <span class="required">*</span></label><input id="crf1aCertifiedName" name="certified_by_name" class="crf1a-form-control" maxlength="150" placeholder="Enter certifier name" required></div>
                                <div class="crf1a-form-group"><label for="crf1aCertifiedPosition">Certified By Position <span class="required">*</span></label><input id="crf1aCertifiedPosition" name="certified_by_position" class="crf1a-form-control" maxlength="100" placeholder="Enter certifier position" required></div>
                                <div class="crf1a-form-help">Page Number, Book Number, Amount Paid, O.R. Number, Date Paid, registrar name and position, and certifier name and position are required. The generated CRF ID is assigned only after successful generation.</div>
                                <div class="crf1a-form-actions">
                                    <button type="button" class="crf1a-btn crf1a-btn-secondary" data-crf1a-close>Cancel</button>
                                    <button type="submit" class="crf1a-btn crf1a-btn-primary" id="crf1aGenerateButton"><i data-lucide="file-output"></i> Generate PDF</button>
                                </div>
                                <div class="crf1a-status" id="crf1aStatus" role="status" aria-live="polite"></div>
                            </form>
                        </div>
                        <div class="crf1a-generator-preview-panel">
                            <div class="crf1a-preview-toolbar">
                                <div class="crf1a-preview-title"><span>Live A4 preview</span><strong id="crf1aPreviewId">ID assigned on generate</strong></div>
                                <div class="crf1a-preview-controls" aria-label="Preview controls">
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-zoom-out title="Zoom out" aria-label="Zoom out"><i data-lucide="zoom-out"></i></button>
                                    <span class="crf1a-preview-zoom-display" data-crf1a-preview-zoom>100%</span>
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-zoom-in title="Zoom in" aria-label="Zoom in"><i data-lucide="zoom-in"></i></button>
                                    <span class="crf1a-preview-divider" aria-hidden="true"></span>
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-prev title="Previous page" aria-label="Previous page" disabled><i data-lucide="chevron-left"></i></button>
                                    <span class="crf1a-preview-page-info"><span data-crf1a-preview-current>1</span> of <span data-crf1a-preview-total>1</span></span>
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-next title="Next page" aria-label="Next page" disabled><i data-lucide="chevron-right"></i></button>
                                    <span class="crf1a-preview-divider" aria-hidden="true"></span>
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-rotate-left title="Rotate left" aria-label="Rotate left"><i data-lucide="rotate-ccw"></i></button>
                                    <button type="button" class="crf1a-preview-control-btn" data-crf1a-preview-rotate-right title="Rotate right" aria-label="Rotate right"><i data-lucide="rotate-cw"></i></button>
                                </div>
                            </div>
                            <div class="crf1a-document-wrap" id="crf1aPreviewHost"></div>
                            <div class="crf1a-pdf-viewer" id="crf1aPdfViewer" hidden><iframe class="crf1a-pdf-frame" title="Generated CRF No. 1A PDF"></iframe></div>
                        </div>
                    </div>
                </div>
                <div class="crf1a-generation-overlay" aria-hidden="true">
                    <div class="crf1a-generation-card" role="status" aria-live="polite">
                        <span class="crf1a-generation-spinner" aria-hidden="true"></span>
                        <strong>Generating PDF...</strong>
                        <span>Please wait while the immutable issuance is created.</span>
                    </div>
                </div>`;
            document.body.appendChild(this.backdrop);
            this.modal = this.backdrop.querySelector('.crf1a-generator-modal');
            this.form = this.backdrop.querySelector('#crf1aGeneratorForm');
            this.previewHost = this.backdrop.querySelector('#crf1aPreviewHost');
            this.pdfFrame = this.backdrop.querySelector('.crf1a-pdf-frame');
            this.status = this.backdrop.querySelector('#crf1aStatus');
            this.generateButton = this.backdrop.querySelector('#crf1aGenerateButton');
            this.addIssuanceKindField();
            this.loadingOverlay = this.backdrop.querySelector('.crf1a-generation-overlay');
            this.backdrop.querySelector('[data-crf1a-preview-zoom-out]').addEventListener('click', () => this.zoomPreview(-0.25));
            this.backdrop.querySelector('[data-crf1a-preview-zoom-in]').addEventListener('click', () => this.zoomPreview(0.25));
            this.backdrop.querySelector('[data-crf1a-preview-prev]').addEventListener('click', () => this.changePreviewPage(-1));
            this.backdrop.querySelector('[data-crf1a-preview-next]').addEventListener('click', () => this.changePreviewPage(1));
            this.backdrop.querySelector('[data-crf1a-preview-rotate-left]').addEventListener('click', () => this.rotatePreview(-90));
            this.backdrop.querySelector('[data-crf1a-preview-rotate-right]').addEventListener('click', () => this.rotatePreview(90));
            this.backdrop.querySelectorAll('[data-crf1a-close]').forEach(button => button.addEventListener('click', () => this.close()));
            this.backdrop.addEventListener('click', event => { if (event.target === this.backdrop) this.close(); });
            this.form.addEventListener('submit', event => { event.preventDefault(); this.confirmGeneration(); });
            this.form.addEventListener('input', () => this.renderPreview());
            document.addEventListener('keydown', event => { if (event.key === 'Escape' && this.backdrop.classList.contains('is-open')) this.close(); });
            this.refreshIcons();
        }

        addIssuanceKindField() {
            const group = document.createElement('div');
            group.className = 'crf1a-form-group';
            group.innerHTML = '<label for="crf1aIssuanceKind">Issuance Type <span class="required">*</span></label><select id="crf1aIssuanceKind" name="issuance_kind" class="crf1a-form-control" required><option value="Original">Original</option><option value="Corrected">Corrected</option><option value="Reprint">Reprint</option></select><div class="crf1a-form-help">Original is the first issuance. Corrected creates a new record linked to the previous issuance. Reprint creates a new immutable copy without overwriting the original.</div>';
            this.form.insertBefore(group, this.form.firstElementChild);
            this.issuanceKind = this.form.elements.issuance_kind;
            this.issuanceKind.addEventListener('change', () => this.renderPreview());
        }

        openFromRecordId(recordId) {
            const id = Number(recordId) || 0;
            if (id <= 0) return this.notify('Invalid birth record.', true);
            const sourcePreview = window.recordPreviewModal
                || (typeof recordPreviewModal !== 'undefined' ? recordPreviewModal : null);
            this.confirmAction(
                'Generate CRF No. 1A',
                'Start generating a CRF No. 1A for this birth record?<br><br><span style="color:#475569;">The source birth record will not be changed.</span>',
                'Continue',
                '#2563EB',
                () => {
                    if (sourcePreview && typeof sourcePreview.close === 'function') sourcePreview.close();
                    return this.loadFromRecordId(id);
                }
            );
        }

        async loadFromRecordId(id) {
            this.setStatus('Loading birth record…');
            this.show();
            try {
                const response = await fetch(`../api/record_details.php?id=${id}&type=birth`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success || !data.record) throw new Error(data.message || 'Birth record could not be loaded.');
                this.open(data.record);
                await this.loadExistingIssuances(id);
            } catch (error) {
                this.setStatus(error.message || 'Birth record could not be loaded.', true);
            }
        }

        async openFromIssuance(issuance) {
            const sourceId = Number(issuance?.birth_record_id) || 0;
            if (sourceId <= 0) return this.notify('The source birth record is not available.', true);
            this.show();
            this.setStatus('Loading the source birth record for a corrected copy…');
            try {
                const response = await fetch(`../api/record_details.php?id=${sourceId}&type=birth`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success || !data.record) throw new Error(data.message || 'Birth record could not be loaded.');
                this.open(data.record);
                this.replacesIssuanceId = Number(issuance?.id || issuance?.issuance_id) || 0;
                this.setValue('crf1aPageNumber', issuance.page_number);
                this.setValue('crf1aBookNumber', issuance.book_number);
                this.setValue('crf1aPopulationReference', issuance.population_reference_no);
                this.setValue('crf1aRequester', issuance.requester_name);
                this.setValue('crf1aAmountPaid', issuance.amount_paid);
                this.setValue('crf1aOrNumber', issuance.or_number);
                this.setValue('crf1aDatePaid', issuance.date_paid);
                this.setValue('crf1aMcrName', issuance.mcr_full_name || '');
                this.setValue('crf1aMcrPosition', issuance.mcr_title || '');
                this.setValue('crf1aCertifiedName', issuance.certified_by_name || '');
                this.setValue('crf1aCertifiedPosition', issuance.certified_by_position || '');
                this.setValue('crf1aIssuanceKind', 'Corrected');
                this.renderPreview();
                await this.loadExistingIssuances(sourceId);
                if (this.issuanceCheckComplete) {
                    this.setStatus(`Corrected copy of ${issuance.crf_number || 'the selected issuance'} loaded. Generate to create a new immutable CRF ID.`);
                }
            } catch (error) {
                this.setStatus(error.message || 'Birth record could not be loaded.', true);
            }
        }

        open(record) {
            this.record = record || {};
            this.replacesIssuanceId = 0;
            this.existingIssuances = [];
            this.issuanceCheckPending = false;
            this.issuanceCheckComplete = false;
            this.show();
            const today = this.today();
            this.form.reset();
            this.setValue('crf1aIssuanceKind', 'Original');
            this.setValue('crf1aIssueDate', today);
            this.setValue('crf1aDatePaid', today);
            this.setValue('crf1aMcrName', '');
            this.setValue('crf1aMcrPosition', '');
            this.setValue('crf1aCertifiedName', '');
            this.setValue('crf1aCertifiedPosition', '');
            this.previewScale = 1;
            this.previewRotation = 0;
            this.previewCurrentPage = 1;
            this.updatePreviewControls();
            this.backdrop.querySelector('#crf1aPdfViewer').hidden = true;
            this.previewHost.hidden = false;
            this.backdrop.querySelector('#crf1aPreviewId').textContent = 'ID assigned on generate';
            this.pdfFrame.removeAttribute('src');
            this.setStatus('');
            this.renderPreview();
            this.refreshIcons();
        }

        zoomPreview(step) {
            this.previewScale = Math.min(2.5, Math.max(0.5, this.previewScale + step));
            this.updatePreviewControls();
            this.applyPreviewTransform();
        }

        rotatePreview(step) {
            this.previewRotation = (this.previewRotation + step + 360) % 360;
            this.applyPreviewTransform();
        }

        changePreviewPage(step) {
            const nextPage = this.previewCurrentPage + step;
            if (nextPage < 1 || nextPage > this.previewTotalPages) return;
            this.previewCurrentPage = nextPage;
            this.updatePreviewControls();
        }

        updatePreviewControls() {
            const zoom = this.backdrop?.querySelector('[data-crf1a-preview-zoom]');
            const current = this.backdrop?.querySelector('[data-crf1a-preview-current]');
            const total = this.backdrop?.querySelector('[data-crf1a-preview-total]');
            const previous = this.backdrop?.querySelector('[data-crf1a-preview-prev]');
            const next = this.backdrop?.querySelector('[data-crf1a-preview-next]');
            if (zoom) zoom.textContent = `${Math.round(this.previewScale * 100)}%`;
            if (current) current.textContent = String(this.previewCurrentPage);
            if (total) total.textContent = String(this.previewTotalPages);
            if (previous) previous.disabled = this.previewCurrentPage <= 1;
            if (next) next.disabled = this.previewCurrentPage >= this.previewTotalPages;
        }

        applyPreviewTransform() {
            const documentNode = this.previewHost?.querySelector('.crf1a-document');
            if (!documentNode) return;
            documentNode.style.transformOrigin = 'top center';
            documentNode.style.transform = `scale(${this.previewScale}) rotate(${this.previewRotation}deg)`;
        }

        async loadExistingIssuances(recordId) {
            this.existingIssuances = [];
            this.issuanceCheckPending = true;
            this.issuanceCheckComplete = false;
            this.setStatus('Checking for existing CRF No. 1A issuances…');
            try {
                const query = new URLSearchParams({
                    birth_record_id: String(recordId),
                    per_page: '100',
                    sort_by: 'crf_id',
                    sort_dir: 'desc'
                });
                const response = await fetch(`../api/crf_1a_records.php?${query.toString()}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to check existing CRF issuances.');
                this.existingIssuances = Array.isArray(data.data?.records) ? data.data.records : [];
                if (this.existingIssuances.length && !this.replacesIssuanceId) {
                    this.setValue('crf1aIssuanceKind', 'Corrected');
                    this.replacesIssuanceId = Number(this.existingIssuances[0]?.id || this.existingIssuances[0]?.issuance_id) || 0;
                }
                this.issuanceCheckComplete = true;
            } catch (error) {
                this.existingIssuances = [];
                this.setStatus(error.message || 'Unable to check existing CRF issuances.', true);
            } finally {
                this.issuanceCheckPending = false;
            }
        }

        show() {
            this.backdrop.classList.add('is-open');
            this.backdrop.setAttribute('aria-hidden', 'false');
            document.body.classList.add('crf1a-open');
        }

        close() {
            this.backdrop.classList.remove('is-open');
            this.backdrop.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('crf1a-open');
        }

        confirmAction(title, message, buttonLabel, buttonColor, callback) {
            if (typeof Notiflix === 'undefined' || !Notiflix.Confirm) {
                if (window.confirm(message.replace(/<[^>]+>/g, ''))) callback();
                return;
            }
            Notiflix.Confirm.show(
                title,
                message,
                'Cancel',
                buttonLabel,
                function () { /* cancelled */ },
                callback,
                {
                    width: '520px',
                    borderRadius: '12px',
                    backgroundColor: '#FFFFFF',
                    titleColor: '#111827',
                    titleFontSize: '20px',
                    messageColor: '#1F2937',
                    messageFontSize: '15px',
                    messageMaxLength: 600,
                    plainText: false,
                    okButtonColor: '#374151',
                    okButtonBackground: '#F3F4F6',
                    cancelButtonColor: '#FFFFFF',
                    cancelButtonBackground: buttonColor,
                    buttonsBorderRadius: '8px',
                    cssAnimationStyle: 'zoom',
                    backOverlayColor: 'rgba(0,0,0,0.6)'
                }
            );
        }

        confirmGeneration() {
            if (!this.record || !this.record.id) return this.setStatus('No source birth record selected.', true);
            if (!this.form.reportValidity()) return this.setStatus('Complete the required issuance fields.', true);
            if (this.issuanceCheckPending) return this.setStatus('Still checking for existing CRF issuances. Please wait a moment.', true);
            if (!this.issuanceCheckComplete) return this.setStatus('The existing-issuance check did not finish. Close and reopen the generator, then try again.', true);

            const existing = Array.isArray(this.existingIssuances) ? this.existingIssuances : [];
            const hasExisting = existing.length > 0;
            const issuanceKind = this.value('crf1aIssuanceKind') || 'Original';
            if (issuanceKind === 'Reprint') this.replacesIssuanceId = 0;
            if (issuanceKind === 'Corrected' && !this.replacesIssuanceId && hasExisting) {
                this.replacesIssuanceId = Number(existing[0]?.id || existing[0]?.issuance_id) || 0;
            }
            if (issuanceKind === 'Corrected' && !this.replacesIssuanceId) return this.setStatus('Select an existing CRF record before creating a corrected issuance.', true);
            if (issuanceKind === 'Original' && hasExisting) return this.setStatus('An issuance already exists for this birth record. Choose Corrected or Reprint to prevent redundancy.', true);
            const existingNumbers = existing
                .map(item => String(item?.crf_number || '').trim())
                .filter(Boolean)
                .slice(0, 3);
            const existingSummary = existingNumbers.length
                ? `<br><br><span style="color:#475569;">Existing issuance${existingNumbers.length > 1 ? 's' : ''}: ${this.escape(existingNumbers.join(', '))}${existing.length > existingNumbers.length ? '...' : ''}</span>`
                : '';
            const message = issuanceKind === 'Reprint'
                ? 'Create a new Reprint issuance using these same source and payment details?<br><br><span style="color:#475569;">The existing CRF will remain unchanged.</span>' + existingSummary
                : issuanceKind === 'Corrected'
                    ? 'Create a new Corrected issuance linked to the previous CRF?<br><br><span style="color:#475569;">The source birth record and previous issuance will remain unchanged.</span>' + existingSummary
                    : 'Generate this Original CRF No. 1A PDF and save it as a new immutable issuance?<br><br><span style="color:#475569;">The source birth record will remain unchanged.</span>';
            this.confirmAction(
                issuanceKind === 'Reprint' ? 'Generate Reprint' : issuanceKind === 'Corrected' ? 'Generate Corrected CRF' : 'Generate CRF No. 1A',
                message,
                issuanceKind === 'Original' ? 'Generate PDF' : 'Create New Record',
                '#2563EB',
                () => this.generate()
            );
        }

        async generate() {
            if (!this.record || !this.record.id) return this.setStatus('No source birth record selected.', true);
            if (!this.form.reportValidity()) return this.setStatus('Complete the required issuance fields.', true);
            const selectedKind = this.value('crf1aIssuanceKind') || 'Original';
            if (this.replacesIssuanceId && selectedKind !== 'Corrected') this.setValue('crf1aIssuanceKind', 'Corrected');
            const formData = new FormData(this.form);
            formData.append('birth_record_id', String(this.record.id));
            if (this.replacesIssuanceId) formData.append('replaces_issuance_id', String(this.replacesIssuanceId));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (csrf) formData.append('csrf_token', csrf);
            this.generateButton.disabled = true;
            this.setGenerating(true);
            this.setStatus('Generating immutable CRF PDF…');
            try {
                const response = await fetch('../api/crf_1a_generate.php', { method: 'POST', body: formData, headers: csrf ? { 'X-CSRF-Token': csrf } : {}, credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success || !data.data) throw new Error(data.message || 'Generation failed.');
                const result = data.data;
                this.backdrop.querySelector('#crf1aPreviewId').textContent = result.crf_number;
                // Keep the stable browser A4 preview visible after generation.
                // The immutable PDF is saved and remains available from the records table.
                this.previewHost.hidden = false;
                this.backdrop.querySelector('#crf1aPdfViewer').hidden = true;
                this.pdfFrame.removeAttribute('src');
                this.renderPreview(result.crf_number);
                this.existingIssuances = [{ id: result.issuance_id, issuance_id: result.issuance_id, crf_number: result.crf_number, issuance_kind: result.issuance_kind }, ...this.existingIssuances];
                this.issuanceCheckComplete = true;
                this.setStatus(`${result.crf_number} generated and saved for reprint.`, false, true);
                this.notify('CRF No. 1A generated successfully.');
            } catch (error) {
                this.setStatus(error.message || 'Generation failed. No issuance was saved.', true);
                this.notify(error.message || 'Generation failed.', true);
            } finally {
                this.setGenerating(false);
                this.generateButton.disabled = false;
            }
        }

        setGenerating(isGenerating) {
            this.backdrop.classList.toggle('is-generating', Boolean(isGenerating));
            if (this.loadingOverlay) this.loadingOverlay.setAttribute('aria-hidden', isGenerating ? 'false' : 'true');
        }

        renderPreview(crfNumber = '') {
            if (!this.previewHost || !this.record) return;
            const inputs = {
                issue_date: this.value('crf1aIssueDate') || this.today(),
                page_number: this.value('crf1aPageNumber'),
                book_number: this.value('crf1aBookNumber'),
                population_reference_no: this.value('crf1aPopulationReference'),
                requester_name: this.value('crf1aRequester'),
                amount_paid: this.value('crf1aAmountPaid'),
                or_number: this.value('crf1aOrNumber'),
                date_paid: this.value('crf1aDatePaid'),
                mcr_full_name: this.value('crf1aMcrName'),
                mcr_title: this.value('crf1aMcrPosition'),
                certified_by_name: this.value('crf1aCertifiedName'),
                certified_by_position: this.value('crf1aCertifiedPosition')
            };
            this.previewHost.innerHTML = this.buildDocumentMarkup(this.record, inputs, crfNumber || 'ID assigned on generate');
            this.applyPreviewTransform();
        }

        buildDocumentMarkup(record, inputs, crfNumber) {
            const r = record || {};
            const issueDate = String(inputs?.issue_date || this.today());
            const page = String(inputs?.page_number || '').trim();
            const book = String(inputs?.book_number || '').trim();
            const values = {
                registry_no: this.registryNumber(r),
                date_of_registration: this.registrationDate(r),
                population_reference_no: inputs?.population_reference_no || '',
                name_of_child: this.fullName(r, 'child'),
                sex: r.child_sex || '',
                date_of_birth: this.sourceDate(r, 'child_date_of_birth'),
                place_of_birth: this.placeOfBirth(r),
                name_of_mother: this.fullName(r, 'mother'),
                mother_citizenship: r.mother_citizenship || '',
                name_of_father: this.fullName(r, 'father'),
                father_citizenship: r.father_citizenship || '',
                parents_marriage_date: this.marriageDate(r),
                parents_marriage_place: r.place_of_marriage || ''
            };
            const line = value => {
                const text = String(value || '');
                const fontSize = text.length > 68 ? '6.5pt' : (text.length > 48 ? '7.5pt' : '');
                return `<span class="doc-line"${fontSize ? ` style="font-size:${fontSize}"` : ''}>${text ? this.escape(text) : '&nbsp;'}</span>`;
            };
            const shortLine = value => `<span class="doc-short-line">${value ? this.escape(value) : '&nbsp;'}</span>`;
            const requester = String(inputs?.requester_name || '').trim();
            const requesterSize = requester.length > 68 ? '6.5pt' : (requester.length > 48 ? '7.5pt' : '');
            const requesterLine = `<span class="doc-requester-line"${requesterSize ? ` style="font-size:${requesterSize}"` : ''}>${requester ? this.escape(requester) : '&nbsp;'}</span>`;
            const row = (label, value) => `<div class="doc-row"><span class="doc-label">${this.escape(label)}</span><span class="doc-colon">:</span><span class="doc-value">${line(value)}</span></div>`;
             const config = window.CRF1A_OFFICE_CONFIG || {};
             const isDraftPreview = String(crfNumber || '').trim() === 'ID assigned on generate';
             const previewValue = (value, placeholder) => {
                 const text = String(value ?? '').trim();
                 if (text) return this.escape(text);
                 return isDraftPreview ? `<span class="doc-preview-placeholder">${this.escape(placeholder)}</span>` : '&nbsp;';
             };
             const registrarName = previewValue(inputs?.mcr_full_name, 'Enter registrar name');
             const registrarPosition = previewValue(inputs?.mcr_title, 'Enter registrar position');
             const certifiedName = previewValue(inputs?.certified_by_name, 'Enter certifier name');
             const certifiedPosition = previewValue(inputs?.certified_by_position, 'Enter certifier position');
             const logoSeal = config.logo_seal === '__hidden__' ? '' : `<img src="../${this.escape(config.logo_seal || 'assets/img/LOGO1.png')}" alt="Baggao seal" style="display:block;width:27mm;height:27mm;">`;
            const logoBaggao = config.logo_baggao === '__hidden__' ? '' : `<img src="../${this.escape(config.logo_baggao || 'assets/img/CRF1A_BAGGAO_REFERENCE.png')}" alt="Baggao reference logo" style="display:block;width:27mm;height:27mm;">`;
            const logoPilipinas = config.logo_pilipinas === '__hidden__' ? '' : `<img src="../${this.escape(config.logo_pilipinas || 'assets/img/CRF1A_BAGONG_PILIPINAS.png')}" alt="Bagong Pilipinas" style="display:block;width:34mm;height:28mm;">`;
            const officeName = String(config.office_name || 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR').trim();
            const officeHtml = officeName === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR'
                ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR'
                : this.escape(officeName);
            const issueDateText = this.displayDate(issueDate);
            const amount = String(inputs?.amount_paid ?? '').trim();
            const amountText = amount === '' ? '' : (Number(amount) >= 0 && Number.isFinite(Number(amount)) ? Number(amount).toFixed(2) : amount);
            return `<div class="crf1a-document">
                <div class="doc-header"><div class="doc-logo doc-logo-seal">${logoSeal}</div><div class="doc-logo doc-logo-baggao">${logoBaggao}</div><div class="doc-header-copy">
                    <div class="doc-republic">Republic of the Philippines</div><div class="doc-province">Province of ${this.escape(config.province || 'CAGAYAN')}</div>
                    <div class="doc-municipality">MUNICIPALITY OF ${this.escape(config.municipality || 'BAGGAO')}</div>
                    <div class="doc-office">${officeHtml}</div>
                    <div class="doc-address">${this.escape(config.address || '')}</div>
                </div><div class="doc-header-right"><div class="doc-logo doc-logo-pilipinas">${logoPilipinas}</div><div class="doc-meta">CRF ID<strong>${this.escape(crfNumber || '')}</strong></div></div></div>
                <div class="doc-rule"></div><div class="doc-title">Civil Registry Form No. 1A</div><div class="doc-subtitle">(Birth-Available)</div>
                <div class="doc-date">Date: ${this.escape(issueDateText)}</div>
                <div class="doc-intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="doc-intro-statement">We certify that, among others, the following facts of birth<br>appear in our Register of Births on page ${shortLine(page)} Book number ${shortLine(book)}.</div></div>
                <div class="doc-grid">${row('Registry Number', values.registry_no)}${row('Date of Registration', values.date_of_registration)}${row('Population Reference No.', values.population_reference_no)}${row('Name of Child', values.name_of_child)}${row('Sex', values.sex)}${row('Date of Birth', values.date_of_birth)}${row('Place of Birth', values.place_of_birth)}${row('Name of Mother', values.name_of_mother)}${row('Citizenship of Mother', values.mother_citizenship)}${row('Name of father', values.name_of_father)}${row('Citizenship of Father', values.father_citizenship)}${row('Date of marriage of parents', values.parents_marriage_date)}${row('Place of Marriage of parents', values.parents_marriage_place)}</div>
                <div class="doc-certification">This certification is issued to ${requesterLine} upon his/her<br>request.</div>
                <div class="doc-signature"><strong>${registrarName}</strong><div>${registrarPosition}</div></div>
                <div class="doc-certified"><div class="doc-certified-heading"><span>Certified by:</span><span class="doc-certified-line">${certifiedName}</span></div><div class="doc-certified-position">${certifiedPosition}</div></div>
                 <div class="doc-payment"><div class="doc-payment-row"><span class="doc-payment-label">Amount paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(amountText)}</span></div><div class="doc-payment-row"><span class="doc-payment-label">O.R. Number</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(inputs?.or_number || '')}</span></div><div class="doc-payment-row"><span class="doc-payment-label">Date paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(inputs?.date_paid || '')}</span></div></div>
                 <div class="doc-note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ${this.escape(crfNumber || '')}</small></div>
            </div>`;
        }

        sourceDate(record, field) {
            const format = record[field + '_format'] || 'full';
            const date = record[field] || '';
            const month = Number(record[field + '_partial_month'] || 0);
            const year = Number(record[field + '_partial_year'] || 0);
            const day = Number(record[field + '_partial_day'] || 0);
            const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            if (format === 'month_only') return months[month] || '';
            if (format === 'year_only') return year ? String(year) : '';
            if (format === 'month_year') {
                if (date) { const d = new Date(date + 'T00:00:00'); return isNaN(d) ? date : `${months[d.getMonth() + 1]} ${d.getFullYear()}`; }
                return month && year ? `${months[month]} ${year}` : '';
            }
            if (format === 'month_day') return month && day ? `${months[month]} ${day}` : '';
            if (format === 'na') return '';
            if (format === 'not_readable') return 'Not Readable';
            if (format === 'no_entry') return 'No Entry';
            if (format === 'dont_know') return "Don't Know";
            if (format === 'forgotten') return 'Forgotten';
            if (format === 'not_married') return 'Not Married';
            if (!date) return '';
            const d = new Date(date + 'T00:00:00');
            return isNaN(d) ? date : `${months[d.getMonth() + 1].slice(0, 3)} ${String(d.getDate()).padStart(2, '0')}, ${d.getFullYear()}`;
        }

        registryNumber(record) {
            const number = String(record?.registry_no || '').trim();
            if (number) return number;
            return ({ not_readable: 'Not Readable', no_entry: 'No Entry' })[record?.registry_no_status] || 'No Entry';
        }

        registrationDate(record) {
            const format = record?.date_of_registration_format || 'full';
            const date = String(record?.date_of_registration || '').trim();
            if (format === 'na' || (format === 'full' && !date)) return 'No Entry';
            return this.sourceDate(record || {}, 'date_of_registration') || 'No Entry';
        }

        marriageDate(record) {
            const other = String(record.date_of_marriage_others || '').trim();
            return other ? other.replaceAll('_', ' ').toUpperCase() : this.sourceDate(record, 'date_of_marriage');
        }

        placeOfBirth(record) {
            const place = String(record.child_place_of_birth || '').trim();
            const barangay = String(record.barangay || '').trim();
            const type = String(record.place_type || '').trim();
            if (place) return barangay && !place.toLowerCase().includes(barangay.toLowerCase()) ? `${place}, ${barangay}` : place;
            if ((type === 'Home' || type === 'Other') && barangay) return `${type}, ${barangay}`;
            return barangay;
        }

        fullName(record, prefix) { return [record[prefix + '_first_name'], record[prefix + '_middle_name'], record[prefix + '_last_name']].filter(v => String(v || '').trim()).join(' '); }
        displayDate(value) { const d = new Date(String(value || '') + 'T00:00:00'); return isNaN(d) ? value : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); }
        today() {
            if (window.CRF1A_DEFAULT_ISSUE_DATE) return String(window.CRF1A_DEFAULT_ISSUE_DATE);
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }
        value(id) { return String(this.backdrop.querySelector('#' + id)?.value || '').trim(); }
        setValue(id, value) { const element = this.backdrop.querySelector('#' + id); if (element) element.value = value || ''; }
        setStatus(message, error = false, success = false) { this.status.textContent = message || ''; this.status.className = 'crf1a-status' + (error ? ' error' : '') + (success ? ' success' : ''); }
        notify(message, error = false) { if (typeof Notiflix !== 'undefined' && Notiflix.Notify) (error ? Notiflix.Notify.failure : Notiflix.Notify.success)(message); }
        escape(value) { const div = document.createElement('div'); div.textContent = value === null || value === undefined ? '' : String(value); return div.innerHTML; }
        refreshIcons() { if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons(); }
    }

    function initCrf1A() {
        if (!window.crf1aGenerator) window.crf1aGenerator = new Crf1AGenerator();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCrf1A); else initCrf1A();
    window.Crf1AGenerator = Crf1AGenerator;
})();
