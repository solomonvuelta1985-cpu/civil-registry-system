/* CRF No. 2A generator: death record source, live legal-size preview, and immutable issuance. */
(function () {
    'use strict';
    class Crf2AGenerator {
        constructor() {
            this.record = null; this.existingIssuances = []; this.checkComplete = false; this.checkPending = false; this.replacesIssuanceId = 0;
            this.scale = 1; this.rotation = 0; this.backdrop = document.createElement('div');
            this.backdrop.className = 'crf1a-generator-backdrop'; this.backdrop.setAttribute('aria-hidden', 'true');
            this.backdrop.innerHTML = `<div class="crf1a-generator-modal" role="dialog" aria-modal="true" aria-labelledby="crf2aTitle"><div class="crf1a-generator-header"><div><h2 id="crf2aTitle"><i data-lucide="file-check-2"></i> Generate CRF No. 2A</h2><p>Death-Available certification - source values are captured as an issuance snapshot</p></div><button type="button" class="crf1a-close" data-close aria-label="Close"><i data-lucide="x"></i></button></div><div class="crf1a-generator-body"><div class="crf1a-generator-form-panel"><div class="crf1a-section-title"><i data-lucide="clipboard-pen-line"></i><span>Issuance Details</span></div><form id="crf2aForm" novalidate><div class="crf1a-two-col"><div class="crf1a-form-group"><label>Page Number <span class="required">*</span></label><input name="page_number" class="crf1a-form-control" required maxlength="50"></div><div class="crf1a-form-group"><label>Book Number <span class="required">*</span></label><input name="book_number" class="crf1a-form-control" required maxlength="50"></div></div><div class="crf1a-form-group"><label>Requester</label><input name="requester_name" class="crf1a-form-control" maxlength="150"></div><div class="crf1a-two-col"><div class="crf1a-form-group"><label>Amount Paid <span class="required">*</span></label><input name="amount_paid" class="crf1a-form-control" inputmode="decimal" placeholder="0.00" required></div><div class="crf1a-form-group"><label>O.R. Number <span class="required">*</span></label><input name="or_number" class="crf1a-form-control" required maxlength="100"></div></div><div class="crf1a-two-col"><div class="crf1a-form-group"><label>Date Paid <span class="required">*</span></label><input name="date_paid" type="date" class="crf1a-form-control" required></div><div class="crf1a-form-group"><label>Issue Date</label><input name="issue_date" type="date" class="crf1a-form-control" readonly></div></div><div class="crf1a-section-title" style="margin-top:20px;"><i data-lucide="file-heart"></i><span>Death Details</span></div><div class="crf1a-form-group"><label>Civil Status <span class="required">*</span></label><select name="civil_status" class="crf1a-form-control" required><option value="">-- Select Civil Status --</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced/Separated</option><option>Unknown/Not Stated</option></select></div><div class="crf1a-form-group"><label>Citizenship <span class="required">*</span></label><input name="citizenship" class="crf1a-form-control" placeholder="Enter citizenship" maxlength="100" required></div><div class="crf1a-form-group"><label>Cause of Death <span class="required">*</span></label><textarea name="cause_of_death" class="crf1a-form-control" rows="3" placeholder="Enter cause of death" maxlength="500" required></textarea></div><div class="crf1a-section-title" style="margin-top:20px;"><i data-lucide="badge-check"></i><span>Registrar and Certification</span></div><div class="crf1a-form-group"><label>Municipal Civil Registrar Name <span class="required">*</span></label><input name="mcr_full_name" class="crf1a-form-control" placeholder="Enter registrar name" maxlength="150" required></div><div class="crf1a-form-group"><label>Municipal Civil Registrar Position <span class="required">*</span></label><input name="mcr_title" class="crf1a-form-control" placeholder="Enter registrar position" maxlength="100" required></div><div class="crf1a-form-group"><label>Certified By Name <span class="required">*</span></label><input name="certified_by_name" class="crf1a-form-control" placeholder="Enter certifier name" maxlength="150" required></div><div class="crf1a-form-group"><label>Certified By Position <span class="required">*</span></label><input name="certified_by_position" class="crf1a-form-control" placeholder="Enter certifier position" maxlength="100" required></div><div class="crf1a-form-help">Required fields are validated before generation. Missing legacy death details may be completed here for this immutable issuance; the source death record is not changed.</div><div class="crf1a-form-actions"><button type="button" class="crf1a-btn crf1a-btn-secondary" data-close>Cancel</button><button type="submit" class="crf1a-btn crf1a-btn-primary"><i data-lucide="file-output"></i> Generate PDF</button></div><div class="crf1a-status" role="status" aria-live="polite"></div></form></div><div class="crf1a-generator-preview-panel"><div class="crf1a-preview-toolbar"><div class="crf1a-preview-title"><span>Live legal-size preview</span><strong data-crf-id>ID assigned on generate</strong></div><div class="crf1a-preview-controls"><button type="button" class="crf1a-preview-control-btn" data-zoom-out title="Zoom out"><i data-lucide="zoom-out"></i></button><span class="crf1a-preview-zoom-display" data-zoom>100%</span><button type="button" class="crf1a-preview-control-btn" data-zoom-in title="Zoom in"><i data-lucide="zoom-in"></i></button><span class="crf1a-preview-divider"></span><button type="button" class="crf1a-preview-control-btn" data-rotate-left title="Rotate left"><i data-lucide="rotate-ccw"></i></button><button type="button" class="crf1a-preview-control-btn" data-rotate-right title="Rotate right"><i data-lucide="rotate-cw"></i></button></div></div><div class="crf1a-document-wrap" data-preview></div></div></div></div><div class="crf1a-generation-overlay" aria-hidden="true"><div class="crf1a-generation-card"><span class="crf1a-generation-spinner"></span><strong>Generating PDF...</strong><span>Please wait while the immutable issuance is created.</span></div></div>`;
            document.body.appendChild(this.backdrop); this.form = this.backdrop.querySelector('form'); this.preview = this.backdrop.querySelector('[data-preview]'); this.status = this.backdrop.querySelector('.crf1a-status'); this.button = this.form.querySelector('button[type="submit"]'); this.addIssuanceKindField();
            this.backdrop.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => this.close()));
            this.backdrop.addEventListener('click', e => { if (e.target === this.backdrop) this.close(); });
            this.form.addEventListener('input', () => this.render()); this.form.addEventListener('submit', e => { e.preventDefault(); this.confirmGeneration(); });
            this.backdrop.querySelector('[data-zoom-out]').addEventListener('click', () => this.zoom(-.25)); this.backdrop.querySelector('[data-zoom-in]').addEventListener('click', () => this.zoom(.25));
            this.backdrop.querySelector('[data-rotate-left]').addEventListener('click', () => this.rotate(-90)); this.backdrop.querySelector('[data-rotate-right]').addEventListener('click', () => this.rotate(90)); this.icons();
        }
        addIssuanceKindField() { const group=document.createElement('div'); group.className='crf1a-form-group'; group.innerHTML='<label>Issuance Type <span class="required">*</span></label><select name="issuance_kind" class="crf1a-form-control" required><option value="Original">Original</option><option value="Corrected">Corrected</option><option value="Reprint">Reprint</option></select><div class="crf1a-form-help">Original is the first issuance. Corrected creates a new record linked to the previous issuance. Reprint creates a new immutable copy without overwriting the original.</div>'; this.form.insertBefore(group,this.form.firstElementChild); this.issuanceKind=this.form.elements['issuance_kind']; this.issuanceKind.addEventListener('change',()=>this.render()); }
        openFromRecordId(id) { id = Number(id) || 0; if (!id) return this.notify('Invalid death record.', true); this.confirm('Generate CRF No. 2A', 'Start generating a CRF No. 2A for this death record?<br><br><span style="color:#475569">The source death record will not be changed.</span>', 'Continue', '#2563EB', () => { window.recordPreviewModal?.close(); this.load(id); }); }
        async load(id) { this.show(); this.setStatus('Loading death record...'); try { const r = await fetch(`../api/record_details.php?id=${id}&type=death`, { credentials:'same-origin' }); const d = await r.json(); if (!r.ok || !d.success || !d.record) throw new Error(d.message || 'Death record could not be loaded.'); this.open(d.record); await this.loadExisting(id); } catch (e) { this.setStatus(e.message, true); } }
        async openFromIssuance(item) { const id = Number(item?.death_record_id) || 0; if (!id) return this.notify('The source death record is not available.', true); await this.load(id); this.replacesIssuanceId = Number(item?.id || item?.issuance_id) || 0; const display = item?.record_snapshot?.display || {}; const manual = item?.record_snapshot?.manual_overrides || {}; ['page_number','book_number','requester_name','amount_paid','or_number','date_paid','mcr_full_name','mcr_title','certified_by_name','certified_by_position'].forEach(k => this.set(k, item[k] || '')); this.set('civil_status', display.civil_status || ''); this.set('citizenship', display.citizenship || ''); this.set('cause_of_death', display.cause_of_death || ''); this.set('remarks_html', manual.remarks_html || ''); this.set('issuance_kind', 'Corrected'); this.issuanceKind?.dispatchEvent(new Event('change', { bubbles: true })); this.render(); }
        open(record) { this.record = record || {}; this.replacesIssuanceId = 0; this.form.reset(); this.set('issuance_kind', 'Original'); this.set('issue_date', this.today()); this.set('date_paid', this.today()); this.set('civil_status', this.record.civil_status || ''); this.set('citizenship', this.record.citizenship || ''); this.set('cause_of_death', this.record.cause_of_death || ''); this.set('mcr_full_name', ''); this.set('mcr_title', ''); this.set('certified_by_name', ''); this.set('certified_by_position', ''); this.existingIssuances=[]; this.checkComplete=false; this.checkPending=false; this.scale=1; this.rotation=0; this.backdrop.querySelector('[data-crf-id]').textContent='ID assigned on generate'; this.show(); this.setStatus(''); this.render(); }
        async loadExisting(id) { this.checkPending=true; this.setStatus('Checking existing CRF No. 2A issuances...'); try { const r=await fetch(`../api/crf_2a_records.php?death_record_id=${id}&per_page=100`,{credentials:'same-origin'}); const d=await r.json(); if(!r.ok||!d.success) throw new Error(d.message||'Unable to check existing issuances.'); this.existingIssuances=d.data?.records||[]; if(this.existingIssuances.length&&!this.replacesIssuanceId){this.set('issuance_kind','Corrected');this.replacesIssuanceId=Number(this.existingIssuances[0].id)||0;} this.checkComplete=true; this.render(); } catch(e){ this.setStatus(e.message,true); } finally{ this.checkPending=false; } }
        confirmGeneration() { if(!this.record?.id) return this.setStatus('No source death record selected.',true); if(!this.form.reportValidity()) return this.setStatus('Complete the required fields.',true); if(this.checkPending) return this.setStatus('Still checking existing CRF issuances.',true); if(!this.checkComplete) return this.setStatus('Existing-issuance check did not finish. Reopen the generator.',true); const values=this.values(); const kind=values.issuance_kind||'Original'; const has=this.existingIssuances.length>0; if(kind==='Reprint') this.replacesIssuanceId=0; if(kind==='Corrected'&&!this.replacesIssuanceId&&has) this.replacesIssuanceId=Number(this.existingIssuances[0].id)||0; if(kind==='Corrected'&&!this.replacesIssuanceId) return this.setStatus('Select an existing CRF record before creating a corrected issuance.',true); if(kind==='Original'&&has) return this.setStatus('An issuance already exists for this death record. Choose Corrected or Reprint to prevent redundancy.',true); const nums=this.existingIssuances.map(x=>x.crf_number).filter(Boolean).slice(0,3); const existingText=nums.length?'<br><br>Existing: '+this.escape(nums.join(', ')):''; const msg=kind==='Reprint'?'Create a new Reprint issuance using these same source and payment details?<br><br><span style="color:#475569">The existing CRF will remain unchanged.</span>'+existingText:kind==='Corrected'?'Create a new Corrected issuance linked to the previous CRF?<br><br><span style="color:#475569">The source death record and previous issuance will remain unchanged.</span>'+existingText:'Generate this Original CRF No. 2A PDF and save it as a new immutable issuance?<br><br><span style="color:#475569">The source death record will remain unchanged.</span>'; const title=kind==='Reprint'?'Generate Reprint':kind==='Corrected'?'Generate Corrected CRF':'Generate CRF No. 2A'; this.confirm(title,msg,kind==='Original'?'Generate PDF':'Create New Record','#2563EB',()=>this.generate()); }
        async generate() { const selectedKind=this.values().issuance_kind||'Original'; if(this.replacesIssuanceId&&selectedKind!=='Corrected'){this.set('issuance_kind','Corrected');} const fd=new FormData(this.form); fd.append('death_record_id',String(this.record.id)); if(this.replacesIssuanceId) fd.append('replaces_issuance_id',String(this.replacesIssuanceId)); const token=document.querySelector('meta[name="csrf-token"]')?.content||''; if(token) fd.append('csrf_token',token); this.button.disabled=true; this.backdrop.classList.add('is-generating'); this.setStatus('Generating immutable CRF PDF...'); try { const r=await fetch('../api/crf_2a_generate.php',{method:'POST',body:fd,headers:token?{'X-CSRF-Token':token}:{},credentials:'same-origin'}); const d=await r.json(); if(!r.ok||!d.success) throw new Error(d.message||'Generation failed.'); this.backdrop.querySelector('[data-crf-id]').textContent=d.data.crf_number; this.existingIssuances.unshift(d.data); this.setStatus(`${d.data.crf_number} generated and saved for reprint.`,false,true); this.notify('CRF No. 2A generated successfully.'); } catch(e){ this.setStatus(e.message,true); this.notify(e.message,true); } finally { this.backdrop.classList.remove('is-generating'); this.button.disabled=false; } }
        render() { if(!this.record) return; const i=this.values(); const merged={...this.record,civil_status:i.civil_status,citizenship:i.citizenship,cause_of_death:i.cause_of_death}; this.preview.innerHTML=this.markup(merged,i,this.backdrop.querySelector('[data-crf-id]').textContent); this.preview.querySelectorAll('.crf1a-document').forEach(doc=>doc.style.transform=`scale(${this.scale}) rotate(${this.rotation}deg)`); }
        values(){ const o={}; new FormData(this.form).forEach((v,k)=>o[k]=k==='remarks_html'?String(v||''):k==='cause_of_death'?String(v??'').replace(/\r\n?/g,'\n'):String(v||'').trim()); return o; }
        registryNumber(r){const number=String(r?.registry_no||'').trim();if(number)return number;return({not_readable:'Not Readable',no_entry:'No Entry'})[r?.registry_no_status]||'No Entry';}
        formatDate(value) {
            if (!value) return '';
            const parsed = new Date(String(value) + 'T00:00:00');
            return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
        }
        registrationDate(r) {
            const format = r?.date_of_registration_format || 'full';
            const raw = String(r?.date_of_registration || '').trim();
            if (format === 'not_readable') return 'Not Readable';
            if (format === 'no_entry' || format === 'na' || (format === 'full' && !raw)) return 'No Entry';
            const month = Number(r?.date_of_registration_partial_month || 0);
            const year = Number(r?.date_of_registration_partial_year || 0);
            const day = Number(r?.date_of_registration_partial_day || 0);
            const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            if (format === 'month_only') return months[month] || 'No Entry';
            if (format === 'year_only') return year ? String(year) : 'No Entry';
            if (format === 'month_year') {
                if (raw) {
                    const parsed = new Date(raw + 'T00:00:00');
                    return isNaN(parsed) ? raw : months[parsed.getMonth() + 1] + ' ' + parsed.getFullYear();
                }
                return month && year ? months[month] + ' ' + year : 'No Entry';
            }
            if (format === 'month_day') return month && day ? months[month] + ' ' + day : 'No Entry';
            if (!raw) return 'No Entry';
            const parsed = new Date(raw + 'T00:00:00');
            return isNaN(parsed) ? raw : parsed.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
        }
        wrapCauseLines(value) {
            const paragraphs = String(value ?? '').replace(/\r\n?/g, '\n').split('\n');
            const lines = [];
            paragraphs.forEach(paragraph => {
                if (paragraph === '') { lines.push(''); return; }
                while (this.causeDisplayWidth(paragraph) > 30) {
                    let width = 0;
                    let cutAt = 0;
                    let spaceCut = 0;
                    for (const character of paragraph) {
                        const characterWidth = this.causeCharacterWidth(character);
                        if (width + characterWidth > 30) break;
                        width += characterWidth;
                        cutAt += character.length;
                        if (character === ' ') spaceCut = cutAt;
                    }
                    cutAt = spaceCut || Math.max(1, cutAt);
                    lines.push(paragraph.slice(0, cutAt));
                    paragraph = paragraph.slice(cutAt);
                }
                lines.push(paragraph);
            });
            return lines.length ? lines : [''];
        }
        causeCharacterWidth(character) {
            if (/\p{Mark}/u.test(character)) return 0;
            const code = character.codePointAt(0);
            return code >= 0x1100 && (
                code <= 0x115f || code === 0x2329 || code === 0x232a ||
                (code >= 0x2e80 && code <= 0xa4cf && code !== 0x303f) ||
                (code >= 0xac00 && code <= 0xd7a3) ||
                (code >= 0xf900 && code <= 0xfaff) ||
                (code >= 0xfe10 && code <= 0xfe6f) ||
                (code >= 0xff01 && code <= 0xff60) ||
                (code >= 0xffe0 && code <= 0xffe6) ||
                (code >= 0x1f300 && code <= 0x1faff) ||
                (code >= 0x20000 && code <= 0x3fffd)
            ) ? 2 : 1;
        }
        causeDisplayWidth(value) {
            let width = 0;
            for (const character of String(value ?? '')) width += this.causeCharacterWidth(character);
            return width;
        }
        causePagePlan(lines) {
            if (lines.length <= 10) return { firstPageLines: lines, continuationPages: [] };
            const firstPageLines = lines.slice(0, 10);
            let remaining = lines.slice(firstPageLines.length);
            const continuationPages = [];
            while (remaining.length > 30) {
                const count = Math.min(45, remaining.length - 30);
                continuationPages.push({ lines: remaining.slice(0, count), includeFooter: false });
                remaining = remaining.slice(count);
            }
            continuationPages.push({ lines: remaining, includeFooter: true });
            return { firstPageLines, continuationPages };
        }
        footerMarkup(i, crf, positions) {
            const manualEntry = value => { const text = String(value ?? ''); return text.trim() ? `<span class="crf-manual-entry">${this.escape(text.toUpperCase())}</span>` : '&nbsp;'; };
            const pv = (value, placeholder) => String(value || '').trim() ? manualEntry(value) : (crf === 'ID assigned on generate' ? `<span class="doc-preview-placeholder">${this.escape(placeholder)}</span>` : '&nbsp;');
            const line = value => `<span class="doc-line">${manualEntry(value)}</span>`;
            return `<div class="doc-certification" style="top:${positions.certification}mm">This certification is issued to <span class="doc-requester-line">${manualEntry(i.requester_name)}</span> upon his/her<br>request.</div><div class="doc-signature" style="top:${positions.signature}mm"><strong>${pv(i.mcr_full_name, 'Enter registrar name')}</strong><div>${pv(i.mcr_title, 'Enter registrar position')}</div></div><div class="doc-certified" style="top:${positions.certified}mm"><div class="doc-certified-heading"><span>Certified by:</span><span class="doc-certified-line">${pv(i.certified_by_name, 'Enter certifier name')}</span></div><div class="doc-certified-position">${pv(i.certified_by_position, 'Enter certifier position')}</div></div><div class="doc-payment" style="top:${positions.payment}mm"><div class="doc-payment-row"><span class="doc-payment-label">Amount paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(i.amount_paid)}</span></div><div class="doc-payment-row"><span class="doc-payment-label">O.R. Number</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(i.or_number)}</span></div><div class="doc-payment-row"><span class="doc-payment-label">Date paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(this.formatDate(i.date_paid))}</span></div></div><div class="doc-note" style="top:${positions.note}mm"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ${this.escape(crf)}</small></div>`;
        }
        certificationMarkup(i, crf, top) {
            const text = String(i.requester_name || '').trim();
            const requester = text ? `<span class="crf-manual-entry">${this.escape(text.toUpperCase())}</span>` : '&nbsp;';
            return `<div class="doc-certification" style="top:${top}mm">This certification is issued to <span class="doc-requester-line">${requester}</span> upon his/her<br>request.</div>`;
        }
        closingMarkup(i, crf, positions) {
            const manualEntry = value => { const text = String(value ?? ''); return text.trim() ? `<span class="crf-manual-entry">${this.escape(text.toUpperCase())}</span>` : '&nbsp;'; };
            const pv = (value, placeholder) => String(value || '').trim() ? manualEntry(value) : (crf === 'ID assigned on generate' ? `<span class="doc-preview-placeholder">${this.escape(placeholder)}</span>` : '&nbsp;');
            const line = value => `<span class="doc-line">${manualEntry(value)}</span>`;
            return `<div class="doc-signature" style="top:${positions.signature}mm"><strong>${pv(i.mcr_full_name, 'Enter registrar name')}</strong><div>${pv(i.mcr_title, 'Enter registrar position')}</div></div><div class="doc-certified" style="top:${positions.certified}mm"><div class="doc-certified-heading"><span>Certified by:</span><span class="doc-certified-line">${pv(i.certified_by_name, 'Enter certifier name')}</span></div><div class="doc-certified-position">${pv(i.certified_by_position, 'Enter certifier position')}</div></div><div class="doc-payment" style="top:${positions.payment}mm"><div class="doc-payment-row"><span class="doc-payment-label">Amount paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(i.amount_paid)}</span></div><div class="doc-payment-row"><span class="doc-payment-label">O.R. Number</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(i.or_number)}</span></div><div class="doc-payment-row"><span class="doc-payment-label">Date paid</span><span class="doc-payment-colon">:</span><span class="doc-payment-value">${line(this.formatDate(i.date_paid))}</span></div></div><div class="doc-note" style="top:${positions.note}mm"><strong>Note:</strong> A mark, erasure or alteration of any entry invalidates this certification.<br><small>System ID: ${this.escape(crf)}</small></div>`;
        }
        markup(r, i, crf) {
            const c = window.CRF2A_OFFICE_CONFIG || {};
            const draft = crf === 'ID assigned on generate';
            const manualEntry = value => { const text = String(value ?? ''); return text.trim() ? `<span class="crf-manual-entry">${this.escape(text.toUpperCase())}</span>` : '&nbsp;'; };
            const pv = (value, placeholder) => String(value || '').trim() ? manualEntry(value) : (draft ? `<span class="doc-preview-placeholder">${this.escape(placeholder)}</span>` : '&nbsp;');
            const line = (value, isManual = false) => `<span class="doc-line">${String(value || '').trim() ? (isManual ? manualEntry(value) : this.escape(value)) : '&nbsp;'}</span>`;
            const short = value => `<span class="doc-short-line">${value ? manualEntry(value) : '&nbsp;'}</span>`;
            const row = (label, value, isManual = true) => `<div class="doc-row"><span class="doc-label">${this.escape(label)}</span><span class="doc-colon">:</span><span class="doc-value">${line(value, isManual)}</span></div>`;
            const date = value => {
                if (!value) return '';
                const parsed = new Date(value + 'T00:00:00');
                return isNaN(parsed) ? value : parsed.toLocaleDateString('en-US', { month: 'long', day: '2-digit', year: 'numeric' });
            };
            const full = [r.deceased_first_name, r.deceased_middle_name, r.deceased_last_name].filter(Boolean).join(' ');
            const place = [r.place_of_death, r.municipality, r.province].filter(Boolean).filter((value, index, all) => all.indexOf(value) === index).join(', ');
            const logo = (path, alt, size) => path === '__hidden__' ? '' : `<img src="../${this.escape(path || '')}" alt="${alt}" style="display:block;width:${size};height:${size};object-fit:contain">`;
            const office = String(c.office_name || 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR');
            const officeHtml = office === 'OFFICE OF THE MUNICIPAL CIVIL REGISTRAR' ? 'OFFICE OF THE MUNICIPAL CIVIL<br>REGISTRAR' : this.escape(office);
            const causeLines = this.wrapCauseLines(String(i.cause_of_death || '').toUpperCase());
            const plan = this.causePagePlan(causeLines);
            const causeBlock = (lines, label = 'Cause of Death') => `<div class="doc-row doc-cause-row"><span class="doc-label">${this.escape(label)}</span><span class="doc-colon">:</span><div class="doc-value doc-cause-lines">${lines.map(value => `<div class="doc-cause-line">${value ? manualEntry(value) : '&nbsp;'}</div>`).join('')}</div></div>`;
            const header = `<div class="doc-header"><div class="doc-logo doc-logo-seal">${logo(c.logo_seal || 'assets/img/LOGO1.png', 'Baggao seal', '27mm')}</div><div class="doc-logo doc-logo-baggao">${logo(c.logo_baggao || 'assets/img/CRF1A_BAGGAO_REFERENCE.png', 'Baggao logo', '27mm')}</div><div class="doc-header-copy"><div class="doc-republic">Republic of the Philippines</div><div class="doc-province">Province of ${this.escape(c.province || 'CAGAYAN')}</div><div class="doc-municipality">MUNICIPALITY OF ${this.escape(c.municipality || 'BAGGAO')}</div><div class="doc-office">${officeHtml}</div><div class="doc-address">${this.escape(c.address || '')}</div></div><div class="doc-header-right"><div class="doc-logo doc-logo-pilipinas">${logo(c.logo_pilipinas || 'assets/img/CRF1A_BAGONG_PILIPINAS.png', 'Bagong Pilipinas', '28mm')}</div><div class="doc-meta">CRF ID<strong>${this.escape(crf)}</strong></div></div></div>`;
            const intro = `<div class="doc-rule"></div><div class="doc-title">Civil Registry Form No. 2A</div><div class="doc-subtitle">(Death-Available)</div><div class="doc-date">Date: ${manualEntry(date(i.issue_date))}</div><div class="doc-intro"><strong>TO WHOM IT MAY CONCERN:</strong><div class="doc-intro-statement">We certify that, among others, the following facts of death<br>appear in our Register of Deaths on page ${short(i.page_number)} Book number ${short(i.book_number)}.</div></div>`;
            const regularRows = row('Registry Number', this.registryNumber(r)) + row('Date of Registration', this.registrationDate(r)) + row('Name of the Deceased', full) + row('Sex', r.sex) + row('Age', r.age ? (r.age + ' ' + (r.age_unit === 'years' ? (Number(r.age) === 1 ? 'year' : 'years') : r.age_unit)) : '') + row('Civil Status', i.civil_status, true) + row('Citizenship', i.citizenship, true) + row('Date of Death', date(r.date_of_death)) + row('Place of Death', place);
            const pages = [];
            const mainShift = Math.max(0, causeLines.length - 1) * 4.6;
            let remarksTop;
            let certificationTop;
            let noteTop;
            if (plan.continuationPages.length) {
                const finalCauseLines = plan.continuationPages[plan.continuationPages.length - 1].lines.length;
                const finalCauseFooterTop = 55 + finalCauseLines * 4.6;
                certificationTop = finalCauseFooterTop;
                remarksTop = finalCauseFooterTop + 15;
                noteTop = finalCauseFooterTop + 89;
            } else {
                certificationTop = 154 + mainShift;
                remarksTop = certificationTop + 15;
                noteTop = 243 + mainShift;
            }
            const singleLimit = Math.max(1, Math.floor(Math.max(0, 330.2 - noteTop - 14) / 4.5 * 82));
            const firstLimit = Math.max(singleLimit, Math.min(2300, Math.floor(Math.max(0, 330.2 - remarksTop - 12) / 4.5 * 82)));
            const remarksPages = window.CrfRemarksEditor?.plan(i.remarks_html || '', singleLimit, firstLimit, 3600) || [];
            const remarksMarkup = (html, top, continued = false) => html ? `<div class="doc-remarks" style="top:${top}mm"><div class="doc-remarks-heading">${continued ? 'REMARKS (CONTINUED)' : 'REMARKS'}</div><div class="doc-remarks-content">${html}</div></div>` : '';
            let mainFooter = '';
            if (!plan.continuationPages.length) {
                if (!remarksPages.length) mainFooter = this.footerMarkup(i, crf, { certification: certificationTop, signature: 178 + mainShift, certified: 199 + mainShift, payment: 219 + mainShift, note: 243 + mainShift });
                else if (remarksPages.length === 1) {
                    const sig = Math.max(178 + mainShift, remarksTop + window.CrfRemarksEditor.heightMm(remarksPages[0]) + 10);
                    mainFooter = this.certificationMarkup(i, crf, certificationTop) + remarksMarkup(remarksPages[0], remarksTop) + this.closingMarkup(i, crf, { signature: sig, certified: sig + 21, payment: sig + 41, note: sig + 65 });
                } else mainFooter = this.certificationMarkup(i, crf, certificationTop) + remarksMarkup(remarksPages[0], remarksTop);
            }
            pages.push(`<div class="crf1a-document crf2a-document">${header}${intro}<div class="doc-grid">${regularRows}${causeBlock(plan.firstPageLines)}</div>${mainFooter}</div>`);
            plan.continuationPages.forEach(page => {
                const continuationTop = 55 + page.lines.length * 4.6;
                const title = page.lines.length ? 'Civil Registry Form No. 2A - continuation' : 'Civil Registry Form No. 2A - certification and payment';
                const cause = page.lines.length ? `<div class="doc-continuation-cause">${causeBlock(page.lines, 'Cause of Death (continued)')}</div>` : '';
                let footer = '';
                if (page.includeFooter) {
                    if (!remarksPages.length) footer = this.footerMarkup(i, crf, { certification: continuationTop, signature: continuationTop + 24, certified: continuationTop + 45, payment: continuationTop + 65, note: continuationTop + 89 });
                    else if (remarksPages.length === 1) {
                        const top = continuationTop + 15;
                        const sig = Math.max(continuationTop + 24, top + window.CrfRemarksEditor.heightMm(remarksPages[0]) + 10);
                        footer = this.certificationMarkup(i, crf, continuationTop) + remarksMarkup(remarksPages[0], top) + this.closingMarkup(i, crf, { signature: sig, certified: sig + 21, payment: sig + 41, note: sig + 65 });
                    } else footer = this.certificationMarkup(i, crf, continuationTop) + remarksMarkup(remarksPages[0], continuationTop + 15);
                }
                pages.push(`<div class="crf1a-document crf2a-document"><div class="doc-continuation-heading">${title}<span class="doc-continuation-id">CRF ID ${this.escape(crf)}</span></div>${cause}${footer}</div>`);
            });
            if (remarksPages.length > 1) {
                remarksPages.slice(1).forEach((chunk, index, remaining) => {
                    const last = index === remaining.length - 1;
                    const footerTop = 35 + window.CrfRemarksEditor.heightMm(chunk) + 12;
                    const footer = last ? this.closingMarkup(i, crf, { signature: footerTop, certified: footerTop + 21, payment: footerTop + 41, note: footerTop + 65 }) : '';
                    pages.push(`<div class="crf1a-document crf2a-document"><div class="doc-continuation-heading">Civil Registry Form No. 2A - continuation<span class="doc-continuation-id">CRF ID ${this.escape(crf)}</span></div>${remarksMarkup(chunk, 35, true)}${footer}</div>`);
                });
            }
            return pages.join('');
        }
        attachRemarksEditor() { this.backdrop.querySelector('.crf1a-preview-title span').textContent='Live legal-size preview'; this.remarksEditor = window.CrfRemarksEditor?.attach(this.backdrop, this.form, () => this.render()); }
        show(){this.backdrop.classList.add('is-open');this.backdrop.setAttribute('aria-hidden','false');document.body.classList.add('crf1a-open');}
        close(){this.backdrop.classList.remove('is-open');this.backdrop.setAttribute('aria-hidden','true');document.body.classList.remove('crf1a-open');}
        zoom(s){this.scale=Math.min(2.5,Math.max(.5,this.scale+s));this.render();this.backdrop.querySelector('[data-zoom]').textContent=Math.round(this.scale*100)+'%';}
        rotate(s){this.rotation=(this.rotation+s+360)%360;this.render();}
        set(n,v){const el=this.form.elements[n];if(el)el.value=v||'';} setStatus(m,e=false,s=false){this.status.textContent=m||'';this.status.className='crf1a-status'+(e?' error':'')+(s?' success':'');} today(){return window.CRF2A_DEFAULT_ISSUE_DATE||new Date().toISOString().slice(0,10);} confirm(t,m,b,c,yes){if(typeof Notiflix==='undefined'||!Notiflix.Confirm){if(confirm(m.replace(/<[^>]+>/g,'')))yes();return;}Notiflix.Confirm.show(t,m,'Cancel',b,()=>{},yes,{width:'520px',borderRadius:'12px',plainText:false,messageMaxLength:900,okButtonColor:'#374151',okButtonBackground:'#F3F4F6',cancelButtonColor:'#fff',cancelButtonBackground:c,backOverlayColor:'rgba(0,0,0,.6)'});} notify(m,e=false){if(typeof Notiflix!=='undefined'&&Notiflix.Notify)(e?Notiflix.Notify.failure:Notiflix.Notify.success)(m);} escape(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML;} icons(){if(window.lucide?.createIcons)lucide.createIcons();}
    }
    function init(){if(!window.crf2aGenerator){window.crf2aGenerator=new Crf2AGenerator();window.crf2aGenerator.attachRemarksEditor();}} if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init(); window.Crf2AGenerator=Crf2AGenerator;
})();
