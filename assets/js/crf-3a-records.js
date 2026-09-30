/* CRF No. 3A records, matching the CRF No. 1A/2A issuance preview experience. */
(function () {
    'use strict';

    const apiUrl = '../api/crf_3a_records.php';
    const esc = value => { const node = document.createElement('div'); node.textContent = value === null || value === undefined ? '' : String(value); return node.innerHTML; };
    const formatDate = value => { if (!value) return '—'; const date = new Date(String(value) + 'T00:00:00'); return Number.isNaN(date.getTime()) ? esc(value) : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }); };
    const money = value => value === null || value === undefined || value === '' ? '—' : Number(value).toFixed(2);

    class Crf3ARecords {
        constructor() {
            this.page = 1; this.sort = { by: 'crf_id', dir: 'desc' }; this.timer = null; this.detailBackdrop = null;
            this.bind(); this.load();
        }

        bind() {
            const search = document.getElementById('crf3aRecordsSearch');
            const year = document.getElementById('crf3aIssueYearFilter');
            const from = document.getElementById('crf3aDateFrom');
            const to = document.getElementById('crf3aDateTo');
            const sortBy = document.getElementById('crf3aSortByFilter');
            const sortDir = document.getElementById('crf3aSortDirFilter');
            [search, year].forEach(input => input?.addEventListener('input', () => this.schedule()));
            [from, to].forEach(input => input?.addEventListener('change', () => this.load()));
            sortBy?.addEventListener('change', () => { this.sort.by = sortBy.value; this.page = 1; this.load(); });
            sortDir?.addEventListener('change', () => { this.sort.dir = sortDir.value; this.page = 1; this.load(); });
            document.getElementById('crf3aClearFilters')?.addEventListener('click', () => { [search, year, from, to].forEach(input => { if (input) input.value = ''; }); this.page = 1; this.load(); });
        }

        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                const year = document.getElementById('crf3aIssueYearFilter')?.value.trim() || '';
                if (year && !/^\d{4}$/.test(year)) return this.message('Issue Year must be a four-digit year.', true);
                this.page = 1; this.load();
            }, 300);
        }

        async load() {
            const host = document.getElementById('crf3aRecordsTableHost');
            if (!host) return;
            host.innerHTML = '<div class="crf1a-records-empty">Loading CRF No. 3A records...</div>';
            this.message('');
            const params = new URLSearchParams({ action: 'list', page: String(this.page), per_page: '25', sort_by: this.sort.by, sort_dir: this.sort.dir });
            [['search', 'crf3aRecordsSearch'], ['issue_year', 'crf3aIssueYearFilter'], ['date_paid_from', 'crf3aDateFrom'], ['date_paid_to', 'crf3aDateTo']].forEach(([key, id]) => { const value = document.getElementById(id)?.value.trim(); if (value) params.set(key, value); });
            try {
                const response = await fetch(`${apiUrl}?${params}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load CRF No. 3A records.');
                this.render(data.data?.records || [], data.data?.pagination || {});
            } catch (error) {
                this.message(error.message, true); host.innerHTML = '<div class="crf1a-records-empty">Records could not be loaded.</div>';
            }
        }

        render(records, pagination) {
            const host = document.getElementById('crf3aRecordsTableHost');
            this.message(`${pagination.total_records || 0} issuance record${Number(pagination.total_records) === 1 ? '' : 's'}`);
            if (!records.length) {
                host.innerHTML = '<div class="crf1a-records-empty"><i data-lucide="inbox"></i><p>No CRF No. 3A records found.</p></div>';
                this.pagination(pagination); this.icons(); return;
            }
            const canArchive = window.CRF3A_CAN_ARCHIVE === true;
            const canDelete = window.CRF3A_CAN_DELETE === true;
            let html = '<div class="crf1a-records-table-wrap"><table class="crf1a-records-table"><thead><tr><th>CRF ID</th><th>REGISTRY NO.</th><th>HUSBAND</th><th>WIFE</th><th>PAGE / BOOK</th><th>PAYMENT</th><th>DATE PAID</th><th>ISSUE DATE</th><th>ACTIONS</th></tr></thead><tbody>';
            records.forEach(record => {
                const kind = record.issuance_kind || 'Original';
                const badge = `<span class="crf1a-issuance-badge crf1a-issuance-${esc(kind.toLowerCase())}">${esc(kind)}</span>`;
                html += `<tr><td><button type="button" class="crf1a-crf-id-link crf-number" data-preview-id="${Number(record.id)}">${esc(record.crf_number)}<br>${badge}</button></td><td>${esc(record.registry_no_snapshot || '—')}</td><td>${esc(record.husband_name_snapshot || '—')}</td><td>${esc(record.wife_name_snapshot || '—')}</td><td>${esc(record.page_number || '—')} / ${esc(record.book_number || '—')}</td><td>₱${money(record.amount_paid)}<br><span class="muted">O.R. ${esc(record.or_number || '—')}</span></td><td>${formatDate(record.date_paid)}</td><td>${formatDate(record.issue_date)}</td><td><div class="crf1a-records-actions"><button type="button" class="crf1a-icon-btn" data-preview-id="${Number(record.id)}" title="View" aria-label="View"><i data-lucide="eye"></i></button><a class="crf1a-icon-btn" href="${esc(record.download_url)}" title="Download" aria-label="Download"><i data-lucide="download"></i></a>${canArchive ? `<button type="button" class="crf1a-icon-btn crf1a-icon-btn-archive" data-archive-id="${Number(record.id)}" title="Archive" aria-label="Archive"><i data-lucide="archive"></i></button>` : ''}${canDelete ? `<button type="button" class="crf1a-icon-btn crf1a-icon-btn-delete" data-delete-id="${Number(record.id)}" title="Delete" aria-label="Delete"><i data-lucide="trash-2"></i></button>` : ''}</div></td></tr>`;
            });
            host.innerHTML = html + '</tbody></table></div>';
            host.querySelectorAll('[data-preview-id]').forEach(button => button.addEventListener('click', () => this.openDetail(Number(button.dataset.previewId))));
            host.querySelectorAll('[data-archive-id]').forEach(button => button.addEventListener('click', () => this.archive(Number(button.dataset.archiveId))));
            host.querySelectorAll('[data-delete-id]').forEach(button => button.addEventListener('click', () => this.delete(Number(button.dataset.deleteId))));
            this.pagination(pagination); this.icons();
        }

        pagination(pagination) {
            const host = document.getElementById('crf3aRecordsPagination'); if (!host) return;
            const pages = Number(pagination.total_pages || 0); if (pages <= 1) { host.innerHTML = ''; return; }
            const current = Number(pagination.current_page || 1);
            host.innerHTML = `<button type="button" ${current <= 1 ? 'disabled' : ''} data-page="${current - 1}">‹</button><span>${current} of ${pages}</span><button type="button" ${current >= pages ? 'disabled' : ''} data-page="${current + 1}">›</button>`;
            host.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => { if (!button.disabled) { this.page = Number(button.dataset.page); this.load(); } }));
        }

        async openDetail(id) {
            if (!id) return;
            this.loader(true);
            try {
                const response = await fetch(`${apiUrl}?action=detail&id=${id}`, { credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load issuance.');
                this.showDetail(data.data);
            } catch (error) {
                if (window.Notiflix?.Notify) Notiflix.Notify.failure(error.message); else window.alert(error.message);
            } finally { this.loader(false); }
        }

        showDetail(record) {
            this.closeDetail();
            const snapshot = record.record_snapshot?.display || {};
            const sourceRecord = record.record_snapshot?.raw || {};
            const manual = record.record_snapshot?.manual_overrides || {};
            const previewInputs = { issue_date: record.issue_date || '', page_number: record.page_number || '', book_number: record.book_number || '', requester_name: record.requester_name || '', amount_paid: record.amount_paid ?? '', or_number: record.or_number || '', date_paid: record.date_paid || '', mcr_full_name: record.mcr_full_name || '', mcr_title: record.mcr_title || '', verified_by_name: record.verified_by_name || '', verified_by_position: record.verified_by_position || '', ...manual };
            const generator = window.crf3aGenerator;
            const previewMarkup = generator && typeof generator.markup === 'function' ? generator.markup(sourceRecord, previewInputs, record.crf_number) : '';
            const previewPanel = previewMarkup ? `<section class="crf1a-record-preview"><div class="crf1a-preview-toolbar"><div class="crf1a-preview-title"><span>Issued A4 preview</span><strong>${esc(record.crf_number)}</strong></div><div class="crf1a-preview-controls" aria-label="Preview controls"><button type="button" class="crf1a-preview-control-btn" data-record-preview-zoom-out title="Zoom out"><i data-lucide="zoom-out"></i></button><span class="crf1a-preview-zoom-display" data-record-preview-zoom>100%</span><button type="button" class="crf1a-preview-control-btn" data-record-preview-zoom-in title="Zoom in"><i data-lucide="zoom-in"></i></button><span class="crf1a-preview-divider"></span><button type="button" class="crf1a-preview-control-btn" data-record-preview-rotate-left title="Rotate left"><i data-lucide="rotate-ccw"></i></button><button type="button" class="crf1a-preview-control-btn" data-record-preview-rotate-right title="Rotate right"><i data-lucide="rotate-cw"></i></button></div></div><div class="crf1a-document-wrap">${previewMarkup}</div></section>` : `<section class="crf1a-record-pdf"><iframe class="crf1a-pdf-frame" src="${esc(record.pdf_url)}" title="${esc(record.crf_number)} PDF"></iframe></section>`;
            const rows = values => values.map(([label, value]) => `<div class="crf1a-detail-row"><span class="crf1a-detail-label">${esc(label)}</span><span class="crf1a-detail-value">${esc(value || '—')}</span></div>`).join('');
            const section = (title, icon, values) => `<section class="crf1a-detail-section"><div class="crf1a-detail-section-title"><i data-lucide="${icon}"></i><span>${esc(title)}</span></div>${rows(values)}</section>`;
            const kind = record.issuance_kind || 'Original';
            const history = Array.isArray(record.history) ? record.history : [];
            const historyHtml = `<section class="crf1a-detail-section crf1a-history-panel"><div class="crf1a-detail-section-title"><i data-lucide="history"></i><span>Generation History</span></div>${history.length ? `<div class="crf1a-history-list">${history.map(item => { const when = item.created_at ? new Date(String(item.created_at).replace(' ', 'T')).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : '—'; return `<div class="crf1a-history-item"><strong>${esc(String(item.action || '').replace(/_/g, ' '))}</strong><span>${esc(item.actor_name || 'System')} · ${esc(when)}</span><small>${esc(item.details || '')}</small></div>`; }).join('')}</div>` : '<div class="crf1a-history-empty">No history recorded yet.</div>'}</section>`;
            const detailHtml = `<div class="crf1a-detail-panel">${section('Issuance Details', 'file-check-2', [['CRF ID', record.crf_number], ['Issuance Type', kind], ['Registry No.', record.registry_no_snapshot], ['Husband', record.husband_name_snapshot], ['Wife', record.wife_name_snapshot], ['Page / Book', `${record.page_number || '—'} / ${record.book_number || '—'}`]])}${section('Payment and Dates', 'receipt', [['Amount Paid', `₱${money(record.amount_paid)}`], ['O.R. Number', record.or_number], ['Date Paid', formatDate(record.date_paid)], ['Issued', formatDate(record.issue_date)]])}${section('Marriage Details', 'heart-handshake', [['Date of Marriage', snapshot.date_of_marriage || sourceRecord.date_of_marriage], ['Place of Marriage', snapshot.place_of_marriage || sourceRecord.place_of_marriage], ['Date of Registration', snapshot.date_of_registration || sourceRecord.date_of_registration], ['Husband Civil Status', record.husband_civil_status], ['Wife Civil Status', record.wife_civil_status], ['Husband Nationality', record.husband_nationality], ['Wife Nationality', record.wife_nationality]])}${section('Source and Audit', 'file-text', [['Created By', record.created_by_name], ['Source Marriage Record', record.marriage_record_id], ['PDF Hash', record.pdf_hash ? `${String(record.pdf_hash).slice(0, 16)}…` : '—']])}${historyHtml}</div>`;
            const correctedAction = window.CRF3A_CAN_GENERATE !== false ? '<button type="button" class="modal-btn modal-btn-primary" data-correct><i data-lucide="file-pen-line"></i><span>Generate Corrected Copy</span></button>' : '';
            const sourceAction = record.marriage_record_id ? '<button type="button" class="modal-btn modal-btn-outline" data-source><i data-lucide="file-text"></i><span>Source Record</span></button>' : '';
            const printAction = record.pdf_url ? '<button type="button" class="modal-btn modal-btn-success" data-crf-print title="Print this CRF No. 3A"><i data-lucide="printer"></i><span>Print</span><span class="btn-shortcut">P</span></button>' : '';
            const downloadAction = `<a class="modal-btn modal-btn-info" href="${esc(record.download_url)}" download><i data-lucide="file-down"></i><span>Download</span><span class="btn-shortcut">D</span></a>`;
            this.detailBackdrop = document.createElement('div'); this.detailBackdrop.className = 'crf1a-record-backdrop is-open';
            this.detailBackdrop.innerHTML = `<div class="crf1a-record-modal" role="dialog" aria-modal="true" aria-labelledby="crf3aDetailTitle"><div class="crf1a-record-header"><div><h2 id="crf3aDetailTitle"><i data-lucide="heart-handshake"></i> ${esc(record.crf_number)}</h2><p>Immutable marriage issuance detail and A4 preview <span class="crf1a-issuance-badge crf1a-issuance-${esc(kind.toLowerCase())}">${esc(kind)}</span></p></div><button type="button" class="crf1a-close" data-close aria-label="Close"><i data-lucide="x"></i></button></div><div class="crf1a-record-modal-body"><div class="crf1a-record-details">${detailHtml}</div>${previewPanel}</div><div class="crf1a-record-modal-footer"><div class="crf1a-record-modal-footer-left"><button type="button" class="modal-btn modal-btn-outline" data-close><i data-lucide="x"></i><span>Close</span></button></div><div class="crf1a-record-modal-footer-right">${correctedAction}${sourceAction}${printAction}${downloadAction}</div></div></div>`;
            document.body.appendChild(this.detailBackdrop);
            this.detailBackdrop.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => this.closeDetail()));
            this.detailBackdrop.addEventListener('click', event => { if (event.target === this.detailBackdrop) this.closeDetail(); });
            this.detailBackdrop.querySelector('[data-correct]')?.addEventListener('click', () => { this.closeDetail(); window.crf3aGenerator?.openFromIssuance(record); });
            this.detailBackdrop.querySelector('[data-source]')?.addEventListener('click', () => { this.closeDetail(); window.recordPreviewModal?.open(Number(record.marriage_record_id), 'marriage'); });
            this.detailBackdrop.querySelector('[data-crf-print]')?.addEventListener('click', () => this.printIssuance(record));
            this.bindDetailPreviewControls(); this.icons();
        }

        bindDetailPreviewControls() {
            const root = this.detailBackdrop?.querySelector('.crf1a-record-preview'); const documentNode = root?.querySelector('.crf1a-document'); if (!root || !documentNode) return;
            let scale = 1; let rotation = 0;
            const update = () => { const zoom = root.querySelector('[data-record-preview-zoom]'); if (zoom) zoom.textContent = `${Math.round(scale * 100)}%`; };
            const apply = () => { documentNode.style.transformOrigin = 'top center'; documentNode.style.transform = `scale(${scale}) rotate(${rotation}deg)`; };
            root.querySelector('[data-record-preview-zoom-out]')?.addEventListener('click', () => { scale = Math.max(.5, scale - .25); update(); apply(); });
            root.querySelector('[data-record-preview-zoom-in]')?.addEventListener('click', () => { scale = Math.min(2.5, scale + .25); update(); apply(); });
            root.querySelector('[data-record-preview-rotate-left]')?.addEventListener('click', () => { rotation = (rotation + 270) % 360; apply(); });
            root.querySelector('[data-record-preview-rotate-right]')?.addEventListener('click', () => { rotation = (rotation + 90) % 360; apply(); });
            update();
        }

        printIssuance(record) {
            const documentNode = this.detailBackdrop?.querySelector('.crf1a-record-preview .crf1a-document');
            if (!documentNode) return this.notify('The A4 preview is not ready yet.', true);
            if (record?.pdf_url) {
                fetch(record.pdf_url + '&action=print', { credentials: 'same-origin', cache: 'no-store' }).catch(() => {});
            }
            const printWindow = window.open('', '_blank');
            if (!printWindow) return this.notify('Please allow pop-ups to print the CRF preview.', true);
            const printableDocument = documentNode.cloneNode(true); printableDocument.style.transform = 'none'; printableDocument.style.transformOrigin = '';
            printableDocument.querySelectorAll('img').forEach(image => { image.src = image.currentSrc || image.src; });
            const cssUrl = new URL('../assets/css/crf-1a.css?v=26', window.location.href).href;
            printWindow.document.open(); printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(record?.crf_number || 'CRF No. 3A')}</title><link rel="stylesheet" href="${cssUrl}"><style>@page{size:A4 portrait;margin:0}html,body{width:210mm;min-height:297mm;margin:0;padding:0;background:#fff}body{overflow:hidden}.crf1a-print-page{width:210mm;height:297mm;margin:0;overflow:hidden;page-break-after:avoid;break-after:avoid}.crf1a-document-wrap{min-width:0!important;width:210mm!important;margin:0!important}.crf1a-document{width:210mm!important;height:297mm!important;min-height:297mm!important;margin:0!important;box-shadow:none!important}@media screen{body{background:#e2e8f0;padding:12px}.crf1a-print-page{box-shadow:0 5px 18px rgba(15,23,42,.15)}}@media print{.crf1a-print-page,.crf1a-document{page-break-after:avoid!important;break-after:avoid!important}}</style></head><body><main class="crf1a-print-page"><div class="crf1a-document-wrap">${printableDocument.outerHTML}</div></main></body></html>`); printWindow.document.close();
            const images = Array.from(printWindow.document.images); const waitForImages = Promise.all(images.map(image => image.complete ? Promise.resolve() : new Promise(resolve => { image.onload = image.onerror = resolve; }))); const stylesheets = Array.from(printWindow.document.querySelectorAll('link[rel="stylesheet"]')); const waitForStyles = Promise.all(stylesheets.map(link => link.sheet ? Promise.resolve() : new Promise(resolve => { link.onload = link.onerror = resolve; setTimeout(resolve, 1500); }))); const waitForFonts = printWindow.document.fonts?.ready || Promise.resolve(); Promise.all([waitForImages, waitForStyles, waitForFonts]).then(() => setTimeout(() => { printWindow.focus(); printWindow.print(); }, 250)); printWindow.onafterprint = () => printWindow.close();
        }

        closeDetail() { if (this.detailBackdrop) { this.detailBackdrop.remove(); this.detailBackdrop = null; } }
        async archive(id) { if (!await this.ask('Archive CRF No. 3A?', 'The issuance will be hidden from the active list and kept in Archives.')) return; const formData = new FormData(); formData.append('record_id', id); formData.append('record_type', 'crf_3a'); formData.append('action', 'archive'); this.csrf(formData); try { const response = await fetch('../api/archive_toggle.php', { method: 'POST', body: formData, headers: this.headers(), credentials: 'same-origin' }); const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.message || 'Archive failed.'); this.load(); this.notify(data.message); } catch (error) { this.notify(error.message, true); } }
        async delete(id) { if (!await this.ask('Move CRF No. 3A to Trash?', 'The issuance will be moved to Trash and can be restored by an administrator.')) return; const formData = new FormData(); formData.append('record_id', id); formData.append('delete_type', 'soft'); this.csrf(formData); try { const response = await fetch('../api/crf_3a_delete.php', { method: 'POST', body: formData, headers: this.headers(), credentials: 'same-origin' }); const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.message || 'Delete failed.'); this.load(); this.notify(data.message); } catch (error) { this.notify(error.message, true); } }
        ask(title, message) { return new Promise(resolve => { if (window.Notiflix?.Confirm) Notiflix.Confirm.show(title, message, 'Cancel', 'Continue', () => resolve(false), () => resolve(true), { plainText: true, backOverlayColor: 'rgba(0,0,0,.6)' }); else resolve(window.confirm(message)); }); }
        loader(open) { let element = document.getElementById('crf3aDetailLoader'); if (!element) { element = document.createElement('div'); element.id = 'crf3aDetailLoader'; element.className = 'crf1a-detail-loader'; element.innerHTML = '<div class="crf1a-detail-loader-card"><span class="crf1a-detail-loader-spinner"></span><strong>Loading preview...</strong><span>Please wait.</span></div>'; document.body.appendChild(element); } element.classList.toggle('is-open', open); }
        message(message, error = false) { const element = document.getElementById('crf3aRecordsMessage'); if (element) { element.textContent = message; element.className = 'crf1a-records-message' + (error ? ' error' : ''); } }
        csrf(formData) { const token = document.querySelector('meta[name="csrf-token"]')?.content || ''; if (token) formData.append('csrf_token', token); }
        headers() { const token = document.querySelector('meta[name="csrf-token"]')?.content || ''; return token ? { 'X-CSRF-Token': token } : {}; }
        notify(message, error = false) { if (window.Notiflix?.Notify) (error ? Notiflix.Notify.failure : Notiflix.Notify.success)(message); else if (error) window.alert(message); }
        icons() { if (window.lucide?.createIcons) window.lucide.createIcons(); }
    }

    function init() { if (document.getElementById('crf3aRecordsTableHost')) window.crf3aRecords = new Crf3ARecords(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.Crf3ARecords = Crf3ARecords;
    window.openCrf3AIssuancePreview = id => { if (!window.crf3aRecords) window.crf3aRecords = new Crf3ARecords(); window.crf3aRecords.openDetail(Number(id) || 0); };
})();
