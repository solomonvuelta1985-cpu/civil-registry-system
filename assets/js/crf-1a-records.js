/* CRF No. 1A issuance history, detail preview, source lookup, and reprint UI. */
(function () {
    'use strict';

    const apiUrl = '../api/crf_1a_records.php';
    const esc = value => {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    };
    const formatDate = value => {
        if (!value) return '—';
        const date = new Date(String(value) + 'T00:00:00');
        return isNaN(date) ? esc(value) : date.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
    };
    const money = value => value === null || value === undefined || value === '' ? '—' : Number(value).toFixed(2);

    class Crf1ARecords {
        constructor() {
            this.page = 1;
            this.searchTimer = null;
            this.sort = { by: 'crf_id', dir: 'desc' };
            this.detailBackdrop = null;
            this.bind();
            this.load();
        }

        bind() {
            const search = document.getElementById('crf1aRecordsSearch');
            const issueYear = document.getElementById('crf1aIssueYearFilter');
            const dateFrom = document.getElementById('crf1aDateFrom');
            const dateTo = document.getElementById('crf1aDateTo');
            const sortBy = document.getElementById('crf1aSortByFilter');
            const sortDir = document.getElementById('crf1aSortDirFilter');
            issueYear?.addEventListener('input', () => this.scheduleLoad());
            issueYear?.addEventListener('change', () => this.scheduleLoad(0));
            [dateFrom, dateTo].forEach(input => input?.addEventListener('change', () => this.scheduleLoad(0)));
            search?.addEventListener('input', () => this.scheduleLoad());
            sortBy?.addEventListener('change', () => { this.sort.by = sortBy.value; this.page = 1; this.load(); });
            sortDir?.addEventListener('change', () => { this.sort.dir = sortDir.value; this.page = 1; this.load(); });
            this.syncSortControls();
            document.getElementById('crf1aClearFilters')?.addEventListener('click', () => {
                clearTimeout(this.searchTimer);
                ['crf1aRecordsSearch', 'crf1aIssueYearFilter', 'crf1aDateFrom', 'crf1aDateTo'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
                this.page = 1; this.load();
            });
        }

        scheduleLoad(delay = 300) {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                const issueYear = document.getElementById('crf1aIssueYearFilter')?.value.trim() || '';
                const message = document.getElementById('crf1aRecordsMessage');
                if (issueYear && !/^\d{4}$/.test(issueYear)) {
                    if (message) {
                        message.className = 'crf1a-records-message error';
                        message.textContent = 'Issue Year must be a four-digit year.';
                    }
                    return;
                }
                this.page = 1;
                this.load();
            }, Math.max(0, delay));
        }

        async load() {
            const host = document.getElementById('crf1aRecordsTableHost');
            const message = document.getElementById('crf1aRecordsMessage');
            if (!host) return;
            host.innerHTML = '<div class="crf1a-records-empty">Loading CRF records…</div>';
            message.className = 'crf1a-records-message';
            message.textContent = '';
            const params = new URLSearchParams({ action:'list', page:String(this.page), per_page:'25', sort_by:this.sort.by, sort_dir:this.sort.dir });
            const values = { search:'crf1aRecordsSearch', issue_year:'crf1aIssueYearFilter', date_paid_from:'crf1aDateFrom', date_paid_to:'crf1aDateTo' };
            Object.entries(values).forEach(([key, id]) => { const value = document.getElementById(id)?.value.trim(); if (value) params.set(key, value); });
            try {
                const response = await fetch(`${apiUrl}?${params}`, { credentials:'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load CRF records.');
                this.render(data.data?.records || [], data.data?.pagination || {});
            } catch (error) {
                message.className = 'crf1a-records-message error';
                message.textContent = error.message;
                host.innerHTML = '<div class="crf1a-records-empty"><i data-lucide="triangle-alert"></i><p>Records could not be loaded.</p></div>';
                this.icons();
            }
        }

        render(records, pagination) {
            const host = document.getElementById('crf1aRecordsTableHost');
            const message = document.getElementById('crf1aRecordsMessage');
            message.textContent = `${pagination.total_records || 0} issuance record${Number(pagination.total_records) === 1 ? '' : 's'}`;
            if (!records.length) {
                host.innerHTML = '<div class="crf1a-records-empty"><i data-lucide="inbox"></i><p>No CRF No. 1A records found.</p></div>';
                this.renderPagination({}); this.icons(); return;
            }
            const canArchive = window.CRF1A_CAN_ARCHIVE === true;
            const canDelete = window.CRF1A_CAN_DELETE === true;
            const sortHeader = (label, key) => {
                const active = this.sort.by === key;
                const direction = active ? (this.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none';
                const indicator = active ? (this.sort.dir === 'asc' ? '↑' : '↓') : '↕';
                return `<th scope="col" aria-sort="${direction}"><button type="button" class="crf1a-sort-btn${active ? ' is-active' : ''}" data-sort="${key}" aria-label="Sort by ${esc(label)}">${esc(label)} <span class="crf1a-sort-indicator" aria-hidden="true">${indicator}</span></button></th>`;
            };
            let html = `<div class="crf1a-records-table-wrap"><table class="crf1a-records-table"><thead><tr>${sortHeader('CRF ID', 'crf_id')}${sortHeader('Registry No.', 'registry')}${sortHeader('Child', 'child')}${sortHeader('Page / Book', 'page_book')}${sortHeader('Payment', 'amount')}${sortHeader('Date Paid', 'date_paid')}${sortHeader('Issue Date', 'issue_date')}<th scope="col">Actions</th></tr></thead><tbody>`;
            records.forEach(record => {
                html += `<tr><td class="crf-number">${esc(record.crf_number)}</td><td>${esc(record.registry_no_snapshot || '—')}</td><td>${esc(record.child_name_snapshot || '—')}</td><td>${esc(record.page_number)} / ${esc(record.book_number)}</td><td>₱${esc(money(record.amount_paid))}<br><span class="muted">O.R. ${esc(record.or_number)}</span></td><td>${formatDate(record.date_paid)}</td><td>${formatDate(record.issue_date)}</td><td><div class="crf1a-records-actions"><button type="button" class="crf1a-icon-btn" title="Preview issuance" data-crf-detail="${Number(record.id) || 0}"><i data-lucide="eye"></i></button><a class="crf1a-icon-btn" title="Download PDF" href="${esc(record.download_url || '#')}"><i data-lucide="download"></i></a><button type="button" class="crf1a-icon-btn" title="View source birth record" data-crf-source="${Number(record.birth_record_id) || 0}"><i data-lucide="file-text"></i></button></div></td></tr>`;
            });
            host.innerHTML = html + '</tbody></table></div>';
            const actionGroups = host.querySelectorAll('.crf1a-records-actions');
            records.forEach((record, index) => {
                const actions = actionGroups[index];
                if (!actions) return;
                if (canArchive) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'crf1a-icon-btn crf1a-icon-btn-archive';
                    button.title = 'Archive issuance';
                    button.setAttribute('aria-label', `Archive ${record.crf_number || 'CRF No. 1A issuance'}`);
                    button.dataset.crfArchive = String(Number(record.id) || 0);
                    button.dataset.crfNumber = String(record.crf_number || '');
                    button.innerHTML = '<i data-lucide="archive"></i>';
                    actions.appendChild(button);
                }
                if (canDelete) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'crf1a-icon-btn crf1a-icon-btn-delete';
                    button.title = 'Move issuance to trash';
                    button.setAttribute('aria-label', `Delete ${record.crf_number || 'CRF No. 1A issuance'}`);
                    button.dataset.crfDelete = String(Number(record.id) || 0);
                    button.dataset.crfNumber = String(record.crf_number || '');
                    button.innerHTML = '<i data-lucide="trash-2"></i>';
                    actions.appendChild(button);
                }
            });
            host.querySelectorAll('[data-crf-detail]').forEach(button => button.addEventListener('click', () => this.openDetail(Number(button.dataset.crfDetail))));
            host.querySelectorAll('[data-crf-source]').forEach(button => button.addEventListener('click', () => this.openSource(Number(button.dataset.crfSource))));
            host.querySelectorAll('[data-crf-archive]').forEach(button => button.addEventListener('click', () => this.archiveIssuance(Number(button.dataset.crfArchive), button.dataset.crfNumber || '')));
            host.querySelectorAll('[data-crf-delete]').forEach(button => button.addEventListener('click', () => this.deleteIssuance(Number(button.dataset.crfDelete), button.dataset.crfNumber || '')));
            host.querySelectorAll('[data-sort]').forEach(button => button.addEventListener('click', () => this.sortBy(button.dataset.sort)));
            this.renderPagination(pagination); this.icons();
        }

        sortBy(key) {
            if (!key) return;
            if (this.sort.by === key) {
                this.sort.dir = this.sort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort.by = key;
                this.sort.dir = ['crf_id', 'amount', 'date_paid', 'issue_date'].includes(key) ? 'desc' : 'asc';
            }
            this.syncSortControls();
            this.page = 1;
            this.load();
        }

        syncSortControls() {
            const sortBy = document.getElementById('crf1aSortByFilter');
            const sortDir = document.getElementById('crf1aSortDirFilter');
            if (sortBy) sortBy.value = this.sort.by;
            if (sortDir) sortDir.value = this.sort.dir;
        }

        renderPagination(pagination) {
            const host = document.getElementById('crf1aRecordsPagination');
            if (!host || !pagination.total_pages || pagination.total_pages <= 1) { if (host) host.innerHTML = ''; return; }
            let html = `<button type="button" data-page="${Math.max(1, Number(pagination.current_page) - 1)}" ${Number(pagination.current_page) <= 1 ? 'disabled' : ''}>‹</button>`;
            for (let page = 1; page <= Number(pagination.total_pages); page++) {
                if (page > 5 && page < Number(pagination.total_pages) - 2) { if (page === 6) html += '<span>…</span>'; continue; }
                html += `<button type="button" data-page="${page}" class="${page === Number(pagination.current_page) ? 'active' : ''}">${page}</button>`;
            }
            html += `<button type="button" data-page="${Math.min(Number(pagination.total_pages), Number(pagination.current_page) + 1)}" ${Number(pagination.current_page) >= Number(pagination.total_pages) ? 'disabled' : ''}>›</button>`;
            host.innerHTML = html;
            host.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => { this.page = Number(button.dataset.page) || 1; this.load(); }));
        }

        async openDetail(id) {
            if (!id) return;
            try {
                const response = await fetch(`${apiUrl}?action=detail&id=${id}`, { credentials:'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load issuance.');
                this.showDetail(data.data);
            } catch (error) { if (typeof Notiflix !== 'undefined') Notiflix.Notify.failure(error.message); }
        }

        showDetail(record) {
            this.closeDetail();
            const snapshot = record.record_snapshot?.display || {};
            const sourceRecord = record.record_snapshot?.raw || {};
            const previewInputs = {
                issue_date: record.issue_date || '',
                page_number: record.page_number || '',
                book_number: record.book_number || '',
                population_reference_no: record.population_reference_no || '',
                requester_name: record.requester_name || '',
                amount_paid: record.amount_paid ?? '',
                or_number: record.or_number || '',
                date_paid: record.date_paid || '',
                certified_by_name: record.certified_by_name || window.CRF1A_DEFAULT_CERTIFIED_BY?.name || '',
                certified_by_position: record.certified_by_position || window.CRF1A_DEFAULT_CERTIFIED_BY?.position || ''
            };
            const hasHtmlPreview = window.crf1aGenerator && typeof window.crf1aGenerator.buildDocumentMarkup === 'function';
            const previewMarkup = hasHtmlPreview
                ? window.crf1aGenerator.buildDocumentMarkup(sourceRecord, previewInputs, record.crf_number)
                : '';
            const previewPanel = hasHtmlPreview
                ? `<div class="crf1a-record-preview"><div class="crf1a-preview-toolbar"><span>Issued A4 preview</span><strong>${esc(record.crf_number)}</strong></div><div class="crf1a-document-wrap">${previewMarkup}</div></div>`
                : `<div class="crf1a-record-pdf"><iframe class="crf1a-pdf-frame" src="${esc(record.pdf_url)}" title="${esc(record.crf_number)} PDF"></iframe></div>`;
            const details = [
                ['CRF ID', record.crf_number], ['Registry No.', record.registry_no_snapshot], ['Child', record.child_name_snapshot],
                ['Page / Book', `${record.page_number || '—'} / ${record.book_number || '—'}`], ['Amount Paid', `₱${money(record.amount_paid)}`],
                ['O.R. Number', record.or_number], ['Date Paid', formatDate(record.date_paid)], ['Issued', formatDate(record.issue_date)],
                ['Issued By', record.created_by_name || '—'], ['Source Birth Record', record.birth_record_id]
            ];
            const sourceRows = [['Sex', snapshot.sex], ['Date of Birth', snapshot.date_of_birth], ['Place of Birth', snapshot.place_of_birth], ['Mother', snapshot.name_of_mother], ['Father', snapshot.name_of_father]];
            const detailHtml = details.concat(sourceRows).map(([label, value]) => `<div class="crf1a-detail-row"><span class="crf1a-detail-label">${esc(label)}</span><span class="crf1a-detail-value">${esc(value || '—')}</span></div>`).join('');
            const correctedAction = window.CRF1A_CAN_GENERATE === true && window.crf1aGenerator
                ? '<button type="button" class="modal-btn modal-btn-primary" data-corrected-copy><i data-lucide="file-pen-line"></i><span>Generate Corrected Copy</span></button>'
                : '';
            const sourceAction = record.birth_record_id
                ? `<button type="button" class="modal-btn modal-btn-outline" data-source-record="${Number(record.birth_record_id) || 0}"><i data-lucide="file-text"></i><span>Source Record</span></button>`
                : '';
            const downloadAction = `<a class="modal-btn modal-btn-info" href="${esc(record.download_url)}"><i data-lucide="file-down"></i><span>Download</span></a>`;
            this.detailBackdrop = document.createElement('div');
            this.detailBackdrop.className = 'crf1a-record-backdrop is-open';
            this.detailBackdrop.innerHTML = `<div class="crf1a-record-modal" role="dialog" aria-modal="true" aria-labelledby="crf1aDetailTitle"><div class="crf1a-record-header"><div><h2 id="crf1aDetailTitle">${esc(record.crf_number)}</h2><p>Immutable issuance detail and A4 preview</p></div><button type="button" class="crf1a-close" data-detail-close aria-label="Close"><i data-lucide="x"></i></button></div><div class="crf1a-record-modal-body"><div class="crf1a-record-details">${detailHtml}</div>${previewPanel}</div><div class="crf1a-record-modal-footer"><div class="crf1a-record-modal-footer-left"><button type="button" class="modal-btn modal-btn-outline" data-detail-close><i data-lucide="x"></i><span>Close</span></button></div><div class="crf1a-record-modal-footer-right">${correctedAction}${sourceAction}${downloadAction}</div></div></div>`;
            document.body.appendChild(this.detailBackdrop);
            this.detailBackdrop.querySelector('[data-corrected-copy]')?.addEventListener('click', () => {
                this.confirmAction(
                    'Edit CRF No. 1A',
                    `Open <strong>${esc(record.crf_number)}</strong> as a corrected-copy draft?<br><br><span style="color:#475569;">The original issued record will remain unchanged.</span>`,
                    'Continue Editing',
                    '#334db0',
                    () => {
                        this.closeDetail();
                        window.crf1aGenerator.openFromIssuance(record);
                    }
                );
            });
            this.detailBackdrop.querySelectorAll('[data-detail-close]').forEach(button => button.addEventListener('click', () => this.closeDetail()));
            this.detailBackdrop.addEventListener('click', event => { if (event.target === this.detailBackdrop) this.closeDetail(); });
            this.detailBackdrop.querySelector('[data-source-record]')?.addEventListener('click', () => this.openSource(Number(record.birth_record_id)));
            this.icons();
        }

        openSource(id) {
            const preview = window.recordPreviewModal
                || (typeof recordPreviewModal !== 'undefined' ? recordPreviewModal : null);
            if (!id || !preview) {
                if (typeof Notiflix !== 'undefined') Notiflix.Notify.info('The source birth record preview is not available.');
                return;
            }
            // Close the issuance modal first so the source-record preview gets focus
            // and is not trapped behind the issuance dialog on smaller screens.
            this.closeDetail();
            preview.open(id, 'birth');
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
                    width: '500px',
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

        archiveIssuance(id, crfNumber) {
            if (!id) return;
            this.confirmAction(
                'Archive CRF No. 1A',
                `Archive <strong>${esc(crfNumber)}</strong>? It will be removed from the active issuance table.`,
                'Archive',
                '#d97706',
                () => this.submitArchive(id)
            );
        }

        async submitArchive(id) {
            if (typeof Notiflix !== 'undefined') Notiflix.Loading?.circle('Archiving...');
            const formData = new FormData();
            formData.append('record_id', String(id));
            formData.append('record_type', 'crf_1a');
            formData.append('action', 'archive');
            this.appendCsrfToken(formData);
            try {
                const response = await fetch('../api/archive_toggle.php', { method: 'POST', body: formData, headers: this.csrfHeaders(), credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to archive the issuance.');
                if (typeof Notiflix !== 'undefined') Notiflix.Notify.success(data.message);
                this.load();
            } catch (error) {
                if (typeof Notiflix !== 'undefined') Notiflix.Notify.failure(error.message); else window.alert(error.message);
            } finally {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading?.remove();
            }
        }

        deleteIssuance(id, crfNumber) {
            if (!id) return;
            this.confirmAction(
                'Delete CRF No. 1A',
                `Move <strong>${esc(crfNumber)}</strong> to the Trash? The issued document file will be retained for audit purposes.`,
                'Delete',
                '#dc2626',
                () => this.submitDelete(id)
            );
        }

        async submitDelete(id) {
            if (typeof Notiflix !== 'undefined') Notiflix.Loading?.circle('Moving to Trash...');
            const formData = new FormData();
            formData.append('record_id', String(id));
            formData.append('delete_type', 'soft');
            this.appendCsrfToken(formData);
            try {
                const response = await fetch('../api/crf_1a_delete.php', { method: 'POST', body: formData, headers: this.csrfHeaders(), credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to delete the issuance.');
                if (typeof Notiflix !== 'undefined') Notiflix.Notify.success(data.message);
                this.load();
            } catch (error) {
                if (typeof Notiflix !== 'undefined') Notiflix.Notify.failure(error.message); else window.alert(error.message);
            } finally {
                if (typeof Notiflix !== 'undefined') Notiflix.Loading?.remove();
            }
        }

        getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        appendCsrfToken(formData) {
            const token = this.getCsrfToken();
            if (token) formData.append('csrf_token', token);
        }

        csrfHeaders() {
            const token = this.getCsrfToken();
            return token ? { 'X-CSRF-Token': token } : {};
        }

        closeDetail() { if (this.detailBackdrop) { this.detailBackdrop.remove(); this.detailBackdrop = null; } }
        icons() { if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons(); }
    }

    function init() { if (!document.getElementById('crf1aRecordsTableHost')) return; window.crf1aRecords = new Crf1ARecords(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    window.Crf1ARecords = Crf1ARecords;
    window.openCrfIssuancePreview = function (id) {
        if (!window.crf1aRecords) window.crf1aRecords = new Crf1ARecords();
        window.crf1aRecords.openDetail(Number(id) || 0);
    };
})();
