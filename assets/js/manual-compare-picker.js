/**
 * Manual Compare Picker
 *
 * Search dialog that lets a user manually pick a second birth record to compare
 * against the current one, then hands the two IDs to DoubleRegComparisonModal.
 *
 * Usage:
 *   const picker = new ManualComparePicker();
 *   picker.open(currentRecordId);
 */

class ManualComparePicker {
    constructor() {
        this.backdrop = null;
        this.modal = null;
        this.input = null;
        this.resultsBody = null;
        this.statusEl = null;
        this.currentRecordId = null;
        this._debounceTimer = null;
        this._lastQuery = '';
        this._inFlight = null;
        this._escHandler = null;

        this.init();
    }

    init() {
        if (document.getElementById('manualComparePickerModal')) {
            this.backdrop = document.getElementById('manualComparePickerBackdrop');
            this.modal = document.getElementById('manualComparePickerModal');
            this.input = document.getElementById('mcpSearchInput');
            this.resultsBody = document.getElementById('mcpResultsBody');
            this.statusEl = document.getElementById('mcpStatus');
            return;
        }
        this.createModalStructure();
        this.attachEventListeners();
    }

    createModalStructure() {
        this.backdrop = document.createElement('div');
        this.backdrop.id = 'manualComparePickerBackdrop';
        this.backdrop.className = 'mcp-backdrop';
        document.body.appendChild(this.backdrop);

        this.modal = document.createElement('div');
        this.modal.id = 'manualComparePickerModal';
        this.modal.className = 'mcp-modal';
        this.modal.innerHTML = `
            <div class="mcp-dialog">
                <div class="mcp-header">
                    <div class="mcp-header-title">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
                        <h2>Find a record to compare with</h2>
                    </div>
                    <button type="button" class="mcp-close" id="mcpCloseBtn" title="Close (ESC)">
                        <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
                    </button>
                </div>
                <div class="mcp-body">
                    <div class="mcp-search-row">
                        <svg class="mcp-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>
                        <input type="text" id="mcpSearchInput" class="mcp-search-input"
                               placeholder="Search by full name, first name, last name, or registry number"
                               autocomplete="off" spellcheck="false">
                    </div>
                    <div class="mcp-status" id="mcpStatus">Type at least 2 characters to search...</div>
                    <div class="mcp-results-wrap">
                        <table class="mcp-results-table">
                            <thead>
                                <tr>
                                    <th style="width:14%">Registry No.</th>
                                    <th style="width:22%">Child Name</th>
                                    <th style="width:13%">Date of Birth</th>
                                    <th style="width:22%">Mother</th>
                                    <th style="width:22%">Father</th>
                                    <th style="width:7%"></th>
                                </tr>
                            </thead>
                            <tbody id="mcpResultsBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="mcp-footer">
                    <button type="button" class="mcp-btn mcp-btn-outline" id="mcpCancelBtn">Close</button>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);

        this.input = document.getElementById('mcpSearchInput');
        this.resultsBody = document.getElementById('mcpResultsBody');
        this.statusEl = document.getElementById('mcpStatus');
    }

    attachEventListeners() {
        this.backdrop.addEventListener('click', () => this.close());
        document.getElementById('mcpCloseBtn').addEventListener('click', () => this.close());
        document.getElementById('mcpCancelBtn').addEventListener('click', () => this.close());

        this.input.addEventListener('input', () => {
            const q = this.input.value.trim();
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => this.runSearch(q), 300);
        });

        this.resultsBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.mcp-select-btn');
            if (!btn) return;
            const pickedId = parseInt(btn.dataset.recordId, 10);
            if (Number.isFinite(pickedId)) this.onSelect(pickedId);
        });

        this._escHandler = (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.close();
            }
        };
        document.addEventListener('keydown', this._escHandler);
    }

    open(currentRecordId) {
        this.currentRecordId = parseInt(currentRecordId, 10);
        this.input.value = '';
        this._lastQuery = '';
        this.resultsBody.innerHTML = '';
        this.setStatus('Type at least 2 characters to search...');
        this.backdrop.classList.add('show');
        this.modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => this.input.focus(), 50);
    }

    close() {
        this.backdrop.classList.remove('show');
        this.modal.classList.remove('show');
        document.body.style.overflow = '';
        clearTimeout(this._debounceTimer);
        if (this._inFlight && typeof this._inFlight.abort === 'function') {
            this._inFlight.abort();
            this._inFlight = null;
        }
    }

    setStatus(text, kind) {
        this.statusEl.textContent = text || '';
        this.statusEl.className = 'mcp-status' + (kind ? ' mcp-status-' + kind : '');
        this.statusEl.style.display = text ? '' : 'none';
    }

    async runSearch(query) {
        if (query === this._lastQuery) return;
        this._lastQuery = query;

        if (!query || query.length < 2) {
            this.resultsBody.innerHTML = '';
            this.setStatus('Type at least 2 characters to search...');
            return;
        }

        this.setStatus('Searching...');

        if (this._inFlight && typeof this._inFlight.abort === 'function') {
            this._inFlight.abort();
        }
        const controller = new AbortController();
        this._inFlight = controller;

        try {
            const base = window.APP_BASE || '';
            const url = `${base}/api/records_search.php?type=birth&search=${encodeURIComponent(query)}&per_page=10`;
            const resp = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
            const data = await resp.json();
            if (this._lastQuery !== query) return;

            if (!data || data.success === false) {
                this.setStatus(data && data.message ? data.message : 'Search failed.', 'error');
                this.resultsBody.innerHTML = '';
                return;
            }

            const records = (data.records || []).filter(r => parseInt(r.id, 10) !== this.currentRecordId);
            this.renderResults(records, !!data.fuzzy);
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            console.error('Manual compare search error:', err);
            this.setStatus('Error searching for records.', 'error');
            this.resultsBody.innerHTML = '';
        } finally {
            this._inFlight = null;
        }
    }

    renderResults(records, fuzzy) {
        if (!records.length) {
            this.resultsBody.innerHTML = '';
            this.setStatus('No matches found. Try a partial name or different spelling.');
            return;
        }

        this.setStatus(fuzzy ? 'Showing close matches.' : `${records.length} result${records.length === 1 ? '' : 's'}.`,
                       fuzzy ? 'fuzzy' : null);

        const esc = (s) => {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        };
        const fullName = (first, middle, last) => {
            const parts = [first, middle, last].map(p => (p || '').trim()).filter(Boolean);
            return parts.length ? parts.join(' ') : '—';
        };

        const rows = records.map(r => {
            const child = fullName(r.child_first_name, r.child_middle_name, r.child_last_name);
            const mother = fullName(r.mother_first_name, r.mother_middle_name, r.mother_last_name);
            const father = fullName(r.father_first_name, r.father_middle_name, r.father_last_name);
            const dob = r.child_date_of_birth || '—';
            const reg = r.registry_no || '—';
            return `
                <tr>
                    <td class="mcp-mono">${esc(reg)}</td>
                    <td>${esc(child)}</td>
                    <td>${esc(dob)}</td>
                    <td>${esc(mother)}</td>
                    <td>${esc(father)}</td>
                    <td>
                        <button type="button" class="mcp-select-btn" data-record-id="${parseInt(r.id, 10)}">
                            Select
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        this.resultsBody.innerHTML = rows;
    }

    onSelect(pickedId) {
        if (!Number.isFinite(pickedId) || pickedId === this.currentRecordId) return;
        const currentId = this.currentRecordId;
        this.close();

        if (typeof DoubleRegComparisonModal !== 'function') {
            console.error('DoubleRegComparisonModal is not loaded.');
            if (window.Notiflix && Notiflix.Notify) {
                Notiflix.Notify.failure('Comparison modal is not available.');
            }
            return;
        }
        const modal = new DoubleRegComparisonModal();
        modal.open(currentId, pickedId, 'birth');
    }
}

window.ManualComparePicker = ManualComparePicker;
