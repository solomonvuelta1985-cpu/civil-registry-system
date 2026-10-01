/* CRF No. 3A generator: marriage source record, live A4 preview, and immutable issuance. */
(function () {
    'use strict';

    class Crf3AGenerator {
        constructor() {
            this.record = null;
            this.existingIssuances = [];
            this.checkComplete = false;
            this.checkPending = false;
            this.replacesIssuanceId = 0;
            this.scale = 1;
            this.rotation = 0;
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'crf1a-generator-backdrop';
            this.backdrop.setAttribute('aria-hidden', 'true');
            this.backdrop.innerHTML = `<div class="crf1a-generator-modal" role="dialog" aria-modal="true" aria-labelledby="crf3aTitle">
                <div class="crf1a-generator-header"><div><h2 id="crf3aTitle"><i data-lucide="heart-handshake"></i> Generate CRF No. 3A</h2><p>Marriage-Available certification - source values are captured as an issuance snapshot</p></div><button type="button" class="crf1a-close" data-close aria-label="Close"><i data-lucide="x"></i></button></div>
                <div class="crf1a-generator-body"><div class="crf1a-generator-form-panel"><div class="crf1a-section-title"><i data-lucide="clipboard-pen-line"></i><span>Issuance Details</span></div>
                    <form id="crf3aForm" novalidate>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Page Number <span class="required">*</span></label><input name="page_number" class="crf1a-form-control" required maxlength="50"></div><div class="crf1a-form-group"><label>Book Number <span class="required">*</span></label><input name="book_number" class="crf1a-form-control" required maxlength="50"></div></div>
                        <div class="crf1a-form-group"><label>Requester</label><input name="requester_name" class="crf1a-form-control" maxlength="150" placeholder="Enter requester name"></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Amount Paid <span class="required">*</span></label><input name="amount_paid" class="crf1a-form-control" inputmode="decimal" placeholder="0.00" required></div><div class="crf1a-form-group"><label>O.R. Number <span class="required">*</span></label><input name="or_number" class="crf1a-form-control" required maxlength="100"></div></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Date Paid <span class="required">*</span></label><input name="date_paid" type="date" class="crf1a-form-control" required></div><div class="crf1a-form-group"><label>Issue Date</label><input name="issue_date" type="date" class="crf1a-form-control" readonly></div></div>
                        <div class="crf1a-section-title" style="margin-top:20px;"><i data-lucide="heart-handshake"></i><span>Marriage Details</span></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Citizenship <span class="required">*</span></label><input name="husband_nationality" class="crf1a-form-control" placeholder="Enter citizenship" maxlength="100" required></div><div class="crf1a-form-group"><label>Wife Citizenship <span class="required">*</span></label><input name="wife_nationality" class="crf1a-form-control" placeholder="Enter citizenship" maxlength="100" required></div></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Civil Status <span class="required">*</span></label><select name="husband_civil_status" class="crf1a-form-control" required><option value="">-- Select --</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced/Separated</option><option>Unknown/Not Stated</option></select></div><div class="crf1a-form-group"><label>Wife Civil Status <span class="required">*</span></label><select name="wife_civil_status" class="crf1a-form-control" required><option value="">-- Select --</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced/Separated</option><option>Unknown/Not Stated</option></select></div></div>
                        <div class="crf1a-form-help">Citizenship, civil status, and parent nationalities are copied from the marriage record when available and remain editable for this issuance. Changes here do not modify the source marriage record.</div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Mother Name <span class="required">*</span></label><input name="husband_mother_name" class="crf1a-form-control" placeholder="Enter mother name" maxlength="255" required></div><div class="crf1a-form-group"><label>Wife Mother Name <span class="required">*</span></label><input name="wife_mother_name" class="crf1a-form-control" placeholder="Enter mother name" maxlength="255" required></div></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Mother Nationality <span class="required">*</span></label><input name="husband_mother_nationality" class="crf1a-form-control" placeholder="Enter nationality" maxlength="100" required></div><div class="crf1a-form-group"><label>Wife Mother Nationality <span class="required">*</span></label><input name="wife_mother_nationality" class="crf1a-form-control" placeholder="Enter nationality" maxlength="100" required></div></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Father Name <span class="required">*</span></label><input name="husband_father_name" class="crf1a-form-control" placeholder="Enter father name" maxlength="255" required></div><div class="crf1a-form-group"><label>Wife Father Name <span class="required">*</span></label><input name="wife_father_name" class="crf1a-form-control" placeholder="Enter father name" maxlength="255" required></div></div>
                        <div class="crf1a-two-col"><div class="crf1a-form-group"><label>Husband Father Nationality <span class="required">*</span></label><input name="husband_father_nationality" class="crf1a-form-control" placeholder="Enter nationality" maxlength="100" required></div><div class="crf1a-form-group"><label>Wife Father Nationality <span class="required">*</span></label><input name="wife_father_nationality" class="crf1a-form-control" placeholder="Enter nationality" maxlength="100" required></div></div>
                        <div class="crf1a-section-title" style="margin-top:20px;"><i data-lucide="badge-check"></i><span>Registrar and Verification</span></div>
                        <div class="crf1a-form-group"><label>Municipal Civil Registrar Name <span class="required">*</span></label><input name="mcr_full_name" class="crf1a-form-control" placeholder="Enter registrar name" maxlength="150" required></div>
                        <div class="crf1a-form-group"><label>Municipal Civil Registrar Position <span class="required">*</span></label><input name="mcr_title" class="crf1a-form-control" placeholder="Enter registrar position" maxlength="100" required></div>
                        <div class="crf1a-form-group"><label>Verified By Name <span class="required">*</span></label><input name="verified_by_name" class="crf1a-form-control" placeholder="Enter verifier name" maxlength="150" required></div>
                        <div class="crf1a-form-group"><label>Verified By Position <span class="required">*</span></label><input name="verified_by_position" class="crf1a-form-control" placeholder="Enter verifier position" maxlength="100" required></div>
                        <div class="crf1a-form-help">Required fields are validated before generation. The source marriage record will not be changed.</div>
                        <div class="crf1a-form-actions"><button type="button" class="crf1a-btn crf1a-btn-secondary" data-close>Cancel</button><button type="submit" class="crf1a-btn crf1a-btn-primary"><i data-lucide="file-output"></i> Generate PDF</button></div><div class="crf1a-status" role="status" aria-live="polite"></div>
                    </form>
                </div><div class="crf1a-generator-preview-panel"><div class="crf1a-preview-toolbar"><div class="crf1a-preview-title"><span>Live A4 preview</span><strong data-crf-id>ID assigned on generate</strong></div><div class="crf1a-preview-controls"><button type="button" class="crf1a-preview-control-btn" data-zoom-out title="Zoom out"><i data-lucide="zoom-out"></i></button><span class="crf1a-preview-zoom-display" data-zoom>100%</span><button type="button" class="crf1a-preview-control-btn" data-zoom-in title="Zoom in"><i data-lucide="zoom-in"></i></button><span class="crf1a-preview-divider"></span><button type="button" class="crf1a-preview-control-btn" data-rotate-left title="Rotate left"><i data-lucide="rotate-ccw"></i></button><button type="button" class="crf1a-preview-control-btn" data-rotate-right title="Rotate right"><i data-lucide="rotate-cw"></i></button></div></div><div class="crf1a-document-wrap" data-preview></div></div></div></div>
                <div class="crf1a-generation-overlay" aria-hidden="true"><div class="crf1a-generation-card"><span class="crf1a-generation-spinner"></span><strong>Generating PDF...</strong><span>Please wait while the immutable issuance is created.</span></div></div>`;
            document.body.appendChild(this.backdrop);
            this.form = this.backdrop.querySelector('form');
            this.preview = this.backdrop.querySelector('[data-preview]');
            this.status = this.backdrop.querySelector('.crf1a-status');
            this.button = this.form.querySelector('button[type="submit"]');
            this.addIssuanceKindField();
            this.backdrop.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => this.close()));
            this.backdrop.addEventListener('click', event => { if (event.target === this.backdrop) this.close(); });
            this.form.addEventListener('input', () => this.render());
            this.form.addEventListener('change', () => this.render());
            this.form.addEventListener('submit', event => { event.preventDefault(); this.confirmGeneration(); });
            this.backdrop.querySelector('[data-zoom-out]').addEventListener('click', () => this.zoom(-.25));
            this.backdrop.querySelector('[data-zoom-in]').addEventListener('click', () => this.zoom(.25));
            this.backdrop.querySelector('[data-rotate-left]').addEventListener('click', () => this.rotate(-90));
            this.backdrop.querySelector('[data-rotate-right]').addEventListener('click', () => this.rotate(90));
            this.icons();
        }

        addIssuanceKindField() {
            const group = document.createElement('div');
            group.className = 'crf1a-form-group';
            group.innerHTML = '<label>Issuance Type <span class="required">*</span></label><select name="issuance_kind" class="crf1a-form-control" required><option value="Original">Original</option><option value="Corrected">Corrected</option><option value="Reprint">Reprint</option></select><div class="crf1a-form-help">Original is the first issuance. Corrected creates a linked new record. Reprint creates a new immutable copy without overwriting the original.</div>';
            this.form.insertBefore(group, this.form.firstElementChild);
            this.issuanceKind = this.form.elements.issuance_kind;
            this.issuanceKind.addEventListener('change', () => this.render());
        }

        openFromRecordId(id) {
            id = Number(id) || 0;
            if (!id) return this.notify('Invalid marriage record.', true);
            this.confirm('Generate CRF No. 3A', 'Start generating a CRF No. 3A for this marriage record?<br><br><span style="color:#475569">The source marriage record will not be changed.</span>', 'Continue', '#2563EB', () => {
                window.recordPreviewModal?.close();
                this.load(id);
            });
        }

        async load(id) {
            this.show();
            this.setStatus('Loading marriage record...');
            try {
                const response = await fetch(`../api/record_details.php?id=${id}&type=marriage`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success || !data.record) throw new Error(data.message || 'Marriage record could not be loaded.');
                this.open(data.record);
                await this.loadExisting(id);
            } catch (error) {
                this.setStatus(error.message, true);
            }
        }

        async openFromIssuance(item) {
            const id = Number(item?.marriage_record_id) || 0;
            if (!id) return this.notify('The source marriage record is not available.', true);
            await this.load(id);
            this.replacesIssuanceId = Number(item?.id || item?.issuance_id) || 0;
            const display = item?.record_snapshot?.display || {};
            const manual = item?.record_snapshot?.manual_overrides || {};
            ['page_number', 'book_number', 'requester_name', 'amount_paid', 'or_number', 'date_paid', 'mcr_full_name', 'mcr_title', 'verified_by_name', 'verified_by_position', 'husband_mother_name', 'husband_father_name', 'wife_mother_name', 'wife_father_name', 'husband_nationality', 'husband_civil_status', 'husband_mother_nationality', 'husband_father_nationality', 'wife_nationality', 'wife_civil_status', 'wife_mother_nationality', 'wife_father_nationality'].forEach(key => this.set(key, item[key] ?? manual[key] ?? display[key] ?? ''));
            this.set('issuance_kind', 'Corrected');
            this.issuanceKind?.dispatchEvent(new Event('change', { bubbles: true }));
            this.render();
        }

        open(record) {
            this.record = record || {};
            this.replacesIssuanceId = 0;
            this.form.reset();
            this.set('issuance_kind', 'Original');
            this.set('issue_date', this.today());
            this.set('date_paid', this.today());
            this.set('husband_mother_name', this.record.husband_mother_name || '');
            this.set('husband_father_name', this.record.husband_father_name || '');
            this.set('wife_mother_name', this.record.wife_mother_name || '');
            this.set('wife_father_name', this.record.wife_father_name || '');
            this.set('husband_nationality', this.record.husband_citizenship || this.record.husband_nationality || '');
            this.set('wife_nationality', this.record.wife_citizenship || this.record.wife_nationality || '');
            this.set('husband_civil_status', this.record.husband_civil_status || '');
            this.set('wife_civil_status', this.record.wife_civil_status || '');
            this.set('husband_mother_nationality', this.record.husband_mother_citizenship || this.record.husband_mother_nationality || '');
            this.set('husband_father_nationality', this.record.husband_father_citizenship || this.record.husband_father_nationality || '');
            this.set('wife_mother_nationality', this.record.wife_mother_citizenship || this.record.wife_mother_nationality || '');
            this.set('wife_father_nationality', this.record.wife_father_citizenship || this.record.wife_father_nationality || '');
            ['mcr_full_name', 'mcr_title', 'verified_by_name', 'verified_by_position'].forEach(key => this.set(key, ''));
            this.existingIssuances = [];
            this.checkComplete = false;
            this.checkPending = false;
            this.scale = 1;
            this.rotation = 0;
            this.backdrop.querySelector('[data-crf-id]').textContent = 'ID assigned on generate';
            this.show();
            this.setStatus('');
            this.render();
        }

        async loadExisting(id) {
            this.checkPending = true;
            this.setStatus('Checking existing CRF No. 3A issuances...');
            try {
                const response = await fetch(`../api/crf_3a_records.php?marriage_record_id=${id}&per_page=100`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to check existing issuances.');
                this.existingIssuances = data.data?.records || [];
                if (this.existingIssuances.length && !this.replacesIssuanceId) {
                    this.set('issuance_kind', 'Corrected');
                    this.replacesIssuanceId = Number(this.existingIssuances[0].id) || 0;
                }
                this.checkComplete = true;
                this.render();
            } catch (error) {
                this.setStatus(error.message, true);
            } finally {
                this.checkPending = false;
            }
        }

        confirmGeneration() {
            if (!this.record?.id) return this.setStatus('No source marriage record selected.', true);
            if (!this.form.reportValidity()) return this.setStatus('Complete the required fields.', true);
            if (this.checkPending) return this.setStatus('Still checking existing CRF issuances.', true);
            if (!this.checkComplete) return this.setStatus('Existing-issuance check did not finish. Reopen the generator.', true);
            const values = this.values();
            const kind = values.issuance_kind || 'Original';
            const hasExisting = this.existingIssuances.length > 0;
            if (kind === 'Reprint') this.replacesIssuanceId = 0;
            if (kind === 'Corrected' && !this.replacesIssuanceId && hasExisting) this.replacesIssuanceId = Number(this.existingIssuances[0].id) || 0;
            if (kind === 'Corrected' && !this.replacesIssuanceId) return this.setStatus('Select an existing CRF record before creating a corrected issuance.', true);
            if (kind === 'Original' && hasExisting) return this.setStatus('An issuance already exists for this marriage record. Choose Corrected or Reprint to prevent redundancy.', true);
            const numbers = this.existingIssuances.map(item => item.crf_number).filter(Boolean).slice(0, 3);
            const existingText = numbers.length ? '<br><br>Existing: ' + this.escape(numbers.join(', ')) : '';
            const message = kind === 'Reprint'
                ? 'Create a new Reprint issuance using these same source and payment details?<br><br><span style="color:#475569">The existing CRF will remain unchanged.</span>' + existingText
                : kind === 'Corrected'
                    ? 'Create a new Corrected issuance linked to the previous CRF?<br><br><span style="color:#475569">The source marriage record and previous issuance will remain unchanged.</span>' + existingText
                    : 'Generate this Original CRF No. 3A PDF and save it as a new immutable issuance?<br><br><span style="color:#475569">The source marriage record will remain unchanged.</span>';
            const title = kind === 'Reprint' ? 'Generate Reprint' : kind === 'Corrected' ? 'Generate Corrected CRF' : 'Generate CRF No. 3A';
            this.confirm(title, message, kind === 'Original' ? 'Generate PDF' : 'Create New Record', '#2563EB', () => this.generate());
        }

        async generate() {
            const selectedKind = this.values().issuance_kind || 'Original';
            if (this.replacesIssuanceId && selectedKind !== 'Corrected') this.set('issuance_kind', 'Corrected');
            const formData = new FormData(this.form);
            formData.append('marriage_record_id', String(this.record.id));
            if (this.replacesIssuanceId) formData.append('replaces_issuance_id', String(this.replacesIssuanceId));
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            if (token) formData.append('csrf_token', token);
            this.button.disabled = true;
            this.backdrop.classList.add('is-generating');
            this.setStatus('Generating immutable CRF PDF...');
            try {
                const response = await fetch('../api/crf_3a_generate.php', { method: 'POST', body: formData, headers: token ? { 'X-CSRF-Token': token } : {}, credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Generation failed.');
                this.backdrop.querySelector('[data-crf-id]').textContent = data.data.crf_number;
                this.existingIssuances.unshift(data.data);
                this.setStatus(`${data.data.crf_number} generated and saved for reprint.`, false, true);
                this.notify('CRF No. 3A generated successfully.');
            } catch (error) {
                this.setStatus(error.message, true);
                this.notify(error.message, true);
            } finally {
                this.backdrop.classList.remove('is-generating');
                this.button.disabled = false;
            }
        }

        render() {
            if (!this.record) return;
            const inputs = this.values();
            this.preview.innerHTML = this.markup(this.record, inputs, this.backdrop.querySelector('[data-crf-id]').textContent);
            const documentNode = this.preview.querySelector('.crf1a-document');
            if (documentNode) documentNode.style.transform = `scale(${this.scale}) rotate(${this.rotation}deg)`;
        }

        values() { const values = {}; new FormData(this.form).forEach((value, key) => { values[key] = String(value || '').trim(); }); return values; }

        registryNumber(record) {
            const number = String(record?.registry_no || '').trim();
            if (number) return number;
            return ({ not_readable: 'Not Readable', no_entry: 'No Entry' })[record?.registry_no_status] || 'No Entry';
        }

        registrationDate(record) {
            const format = record?.date_of_registration_format || 'full';
            const date = String(record?.date_of_registration || '').trim();
            if (format === 'not_readable') return 'Not Readable';
            if (format === 'no_entry' || format === 'na' || (format === 'full' && !date)) return 'No Entry';
            const month = Number(record?.date_of_registration_partial_month || 0);
            const year = Number(record?.date_of_registration_partial_year || 0);
            const day = Number(record?.date_of_registration_partial_day || 0);
            const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            if (format === 'month_only') return months[month] || 'No Entry';
            if (format === 'year_only') return year ? String(year) : 'No Entry';
            if (format === 'month_year') {
                if (date) { const d = new Date(date + 'T00:00:00'); return Number.isNaN(d.getTime()) ? date : `${months[d.getMonth() + 1]} ${d.getFullYear()}`; }
                return month && year ? `${months[month]} ${year}` : 'No Entry';
            }
            if (format === 'month_day') return month && day ? `${months[month]} ${day}` : 'No Entry';
            return date || 'No Entry';
        }

        markup(record, inputs, crfNumber) {
            const source = record || {};
            record = { ...source, registry_no: this.registryNumber(source), date_of_registration: this.registrationDate(source) };
            const config = window.CRF3A_OFFICE_CONFIG || {};
            const draft = crfNumber === 'ID assigned on generate';
            const escape = value => this.escape(value);
            const placeholder = (value, text) => String(value || '').trim() ? escape(value) : (draft ? `<span class="doc-preview-placeholder">${escape(text)}</span>` : '&nbsp;');
            const line = value => `<span class="crf3a-party-line">${String(value || '').trim() ? escape(value) : '&nbsp;'}</span>`;
            const row = (label, husband, wife) => `<tr><td class="crf3a-label">${escape(label)}</td><td class="crf3a-party">${line(husband)}</td><td class="crf3a-party">${line(wife)}</td></tr>`;
            const sharedRow = (label, value) => `<tr><td class="crf3a-label">${escape(label)}</td><td colspan="2"><span class="crf3a-shared-line">${String(value || '').trim() ? escape(value) : '&nbsp;'}</span></td></tr>`;
            const date = value => { if (!value) return ''; const parsed = new Date(String(value) + 'T00:00:00'); return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); };
            const ageAtMarriage = prefix => { const dob = record[`${prefix}_date_of_birth`]; const marriage = record.date_of_marriage; if (!dob || !marriage) return ''; const birth = new Date(String(dob) + 'T00:00:00'); const event = new Date(String(marriage) + 'T00:00:00'); let age = event.getFullYear() - birth.getFullYear(); const beforeBirthday = event.getMonth() < birth.getMonth() || (event.getMonth() === birth.getMonth() && event.getDate() < birth.getDate()); if (beforeBirthday) age--; return age >= 0 ? `${age} ${age === 1 ? 'year' : 'years'}` : ''; };
            const dobAge = prefix => { const dob = date(record[`${prefix}_date_of_birth`]); const age = ageAtMarriage(prefix); return dob && age ? `${dob} / ${age}` : dob; };
            const full = prefix => [record[`${prefix}_first_name`], record[`${prefix}_middle_name`], record[`${prefix}_last_name`]].filter(Boolean).join(' ');
            const parent = (key, sourceKey) => inputs[key] || record[sourceKey] || '';
            const logo = (path, alt, size) => path === '__hidden__' ? '' : `<img src="../${escape(path || '')}" alt="${escape(alt)}" style="display:block;width:${size};height:${size};object-fit:contain">`;
            const office = String(config.office_name || 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR');
            const officeHtml = office === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR' ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR' : escape(office);
            return `<div class="crf1a-document"><div class="doc-header"><div class="doc-logo doc-logo-seal">${logo(config.logo_seal || 'assets/img/LOGO1.png', 'Baggao seal', '27mm')}</div><div class="doc-logo doc-logo-baggao">${logo(config.logo_baggao || 'assets/img/CRF1A_BAGGAO_REFERENCE.png', 'Baggao logo', '27mm')}</div><div class="doc-header-copy"><div class="doc-republic">Republic of the Philippines</div><div class="doc-province">Province of ${escape(config.province || 'CAGAYAN')}</div><div class="doc-municipality">MUNICIPALITY OF ${escape(config.municipality || 'BAGGAO')}</div><div class="doc-office">${officeHtml}</div><div class="doc-address">${escape(config.address || '')}</div></div><div class="doc-header-right"><div class="doc-logo doc-logo-pilipinas">${logo(config.logo_pilipinas || 'assets/img/CRF1A_BAGONG_PILIPINAS.png', 'Bagong Pilipinas', '28mm')}</div><div class="doc-meta">CRF ID<strong>${escape(crfNumber)}</strong></div></div></div><div class="doc-rule"></div><div class="doc-title">Civil Registry Form No. 3A</div><div class="doc-subtitle">(Marriage-Available)</div><div class="doc-date">Date: ${escape(date(inputs.issue_date))}</div><div class="doc-intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="doc-intro-statement">We certify that, among others, the following facts of Marriage<br>appear in our Register of Marriages on page <span class="doc-short-line">${escape(inputs.page_number)}</span> Book number <span class="doc-short-line">${escape(inputs.book_number)}</span>.</div></div><div class="crf3a-details"><table class="crf3a-details-table"><thead><tr><th class="crf3a-label"></th><th class="crf3a-party">HUSBAND</th><th class="crf3a-party">WIFE</th></tr></thead><tbody>${row('Name', full('husband'), full('wife'))}${row('Date of Birth / Age', dobAge('husband'), dobAge('wife'))}${row('Citizenship', inputs.husband_nationality, inputs.wife_nationality)}${row('Civil Status', inputs.husband_civil_status, inputs.wife_civil_status)}${row('Name of Mother', parent('husband_mother_name', 'husband_mother_name'), parent('wife_mother_name', 'wife_mother_name'))}${row('Nationality', inputs.husband_mother_nationality, inputs.wife_mother_nationality)}${row('Name of Father', parent('husband_father_name', 'husband_father_name'), parent('wife_father_name', 'wife_father_name'))}${row('Nationality', inputs.husband_father_nationality, inputs.wife_father_nationality)}${sharedRow('Civil Registry Number', record.registry_no)}${sharedRow('Date of Marriage', date(record.date_of_marriage))}${sharedRow('Place of Marriage', record.place_of_marriage)}${sharedRow('Date of Registration', date(record.date_of_registration))}</tbody></table></div><div class="crf3a-certification">This certification is issued to <span class="doc-requester-line">${inputs.requester_name ? escape(inputs.requester_name) : '&nbsp;'}</span> upon his/her<br>request.</div><div class="crf3a-signature"><strong>${placeholder(inputs.mcr_full_name, 'Enter registrar name')}</strong><div>${placeholder(inputs.mcr_title, 'Enter registrar position')}</div></div><div class="crf3a-verified"><div class="crf3a-verified-heading"><span>VERIFIED BY:</span><span class="crf3a-verified-line">${placeholder(inputs.verified_by_name, 'Enter verifier name')}</span></div><div class="crf3a-verified-position">${placeholder(inputs.verified_by_position, 'Enter verifier position')}</div></div><div class="doc-payment"><div class="doc-payment-row"><span class="doc-payment-label">Amount paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">${escape(inputs.amount_paid)}</span></span></div><div class="doc-payment-row"><span class="doc-payment-label">O.R. Number</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">${escape(inputs.or_number)}</span></span></div><div class="doc-payment-row"><span class="doc-payment-label">Date paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value"><span class="doc-line">${escape(inputs.date_paid)}</span></span></div></div><div class="doc-note"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ${escape(crfNumber)}</small></div></div>`;
        }

        buildDocumentMarkup(record, inputs, crfNumber) { return this.markup(record, inputs, crfNumber); }
        show() { this.backdrop.classList.add('is-open'); this.backdrop.setAttribute('aria-hidden', 'false'); document.body.classList.add('crf1a-open'); }
        close() { this.backdrop.classList.remove('is-open'); this.backdrop.setAttribute('aria-hidden', 'true'); document.body.classList.remove('crf1a-open'); }
        zoom(step) { this.scale = Math.min(2.5, Math.max(.5, this.scale + step)); this.render(); this.backdrop.querySelector('[data-zoom]').textContent = Math.round(this.scale * 100) + '%'; }
        rotate(step) { this.rotation = (this.rotation + step + 360) % 360; this.render(); }
        set(name, value) { const element = this.form.elements[name]; if (element) element.value = value || ''; }
        setStatus(message, error = false, success = false) { this.status.textContent = message || ''; this.status.className = 'crf1a-status' + (error ? ' error' : '') + (success ? ' success' : ''); }
        today() { return window.CRF3A_DEFAULT_ISSUE_DATE || new Date().toISOString().slice(0, 10); }
        confirm(title, message, button, color, yes) { if (typeof Notiflix === 'undefined' || !Notiflix.Confirm) { if (window.confirm(message.replace(/<[^>]+>/g, ''))) yes(); return; } Notiflix.Confirm.show(title, message, 'Cancel', button, () => {}, yes, { width: '520px', borderRadius: '12px', plainText: false, messageMaxLength: 1000, okButtonColor: '#374151', okButtonBackground: '#F3F4F6', cancelButtonColor: '#fff', cancelButtonBackground: color, backOverlayColor: 'rgba(0,0,0,.6)' }); }
        notify(message, error = false) { if (typeof Notiflix !== 'undefined' && Notiflix.Notify) (error ? Notiflix.Notify.failure : Notiflix.Notify.success)(message); }
        escape(value) { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; }
        icons() { if (window.lucide?.createIcons) window.lucide.createIcons(); }
    }

    function init() { if (!window.crf3aGenerator) window.crf3aGenerator = new Crf3AGenerator(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.Crf3AGenerator = Crf3AGenerator;
})();
