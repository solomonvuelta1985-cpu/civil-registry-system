/**
 * Double Registration Comparison Modal
 * Corporate / institutional design for PSA MC 2019-23 double registration detection
 *
 * Usage:
 *   const modal = new DoubleRegComparisonModal();
 *   modal.open(recordAId, recordBId, 'birth');
 */

class DoubleRegComparisonModal {
    constructor() {
        this.backdrop = null;
        this.modal = null;
        this.recordA = null;
        this.recordB = null;
        this.recordAId = null;
        this.recordBId = null;
        this.certificateType = 'birth';

        // PDF state for each pane — default scale 1.5 for readable size
        this.pdfA = { doc: null, page: 1, total: 0, scale: 1.5, canvas: null, ctx: null };
        this.pdfB = { doc: null, page: 1, total: 0, scale: 1.5, canvas: null, ctx: null };

        // PDF viewer state
        this.syncScroll = true;
        this._isSyncing = false;

        // Optional callback when modal closes
        this.onClose = null;

        this.init();
    }

    init() {
        this.createModalStructure();
        this.attachEventListeners();
    }

    createModalStructure() {
        // Backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'double-reg-backdrop';
        document.body.appendChild(this.backdrop);

        // Modal
        this.modal = document.createElement('div');
        this.modal.className = 'double-reg-modal';
        this.modal.innerHTML = `
            <div class="double-reg-dialog">
                <!-- Header -->
                <div class="double-reg-header">
                    <div>
                        <div class="double-reg-header-title">
                            <svg class="dr-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/></svg>
                            <h2>Double Registration Comparison</h2>
                        </div>
                        <p class="double-reg-header-subtitle" id="drSubtitle">Compare two records to confirm double registration</p>
                    </div>
                    <button type="button" class="double-reg-close" id="drCloseBtn" title="Close (ESC)">
                        <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="double-reg-body" id="drBody">
                    <div class="double-reg-loading" id="drLoading">
                        <div class="spinner"></div>
                        <span>Loading records for comparison...</span>
                    </div>

                    <!-- Summary Bar -->
                    <div class="dr-summary-bar" id="drSummaryBar" style="display:none;">
                        <div class="dr-summary-stats">
                            <span class="dr-stat-match">
                                <span class="dr-dot dr-dot-green"></span>
                                <strong id="drMatchCount">0</strong> matches
                            </span>
                            <span class="dr-stat-differ">
                                <span class="dr-dot dr-dot-amber"></span>
                                <strong id="drDifferCount">0</strong> discrepancies
                            </span>
                        </div>
                        <div class="dr-summary-verdict" id="drSummaryVerdict"></div>
                    </div>

                    <!-- Section 1: Side-by-Side PDF Viewer -->
                    <div class="double-reg-pdf-section split-view" id="drPdfSection" style="display:none;">
                        <!-- Toolbar -->
                        <div class="dr-pdf-toolbar">
                            <div class="dr-pdf-toolbar-left">
                                <span class="dr-toolbar-label" id="drToolbarLabelA">
                                    <span class="dr-dot dr-dot-green"></span>
                                    <strong>1st Registration</strong>
                                    <span class="dr-toolbar-regnum" id="drToolbarRegA"></span>
                                </span>
                                <span class="dr-toolbar-divider">vs</span>
                                <span class="dr-toolbar-label" id="drToolbarLabelB">
                                    <span class="dr-dot dr-dot-blue"></span>
                                    <strong>2nd Registration</strong>
                                    <span class="dr-toolbar-regnum" id="drToolbarRegB"></span>
                                </span>
                            </div>
                            <div class="dr-pdf-toolbar-right">
                                <button type="button" class="dr-pdf-ctrl-btn active" id="drSyncToggle" title="Sync scrolling between both documents">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                                    Sync
                                </button>
                                <div class="dr-pdf-ctrl-separator"></div>
                                <button type="button" class="dr-pdf-ctrl-btn" data-action="zoom-out" title="Zoom Out">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
                                </button>
                                <span class="dr-zoom-display" id="drZoomDisplay">150%</span>
                                <button type="button" class="dr-pdf-ctrl-btn" data-action="zoom-in" title="Zoom In">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="11" x2="11" y1="8" y2="14"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Side-by-side PDF viewer -->
                        <div class="dr-pdf-viewer">
                            <div class="dr-pdf-canvas-wrap" id="drCanvasWrapA">
                                <div class="dr-split-pane-label primary" id="drSplitLabelA">1st Registration</div>
                                <div class="dr-pdf-placeholder">No PDF available</div>
                            </div>
                            <div class="dr-pdf-canvas-wrap" id="drCanvasWrapB">
                                <div class="dr-split-pane-label duplicate" id="drSplitLabelB">2nd Registration</div>
                                <div class="dr-pdf-placeholder">No PDF available</div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Field Comparison Table -->
                    <div class="double-reg-comparison-section" id="drComparisonSection" style="display:none;">
                        <h3 class="dr-section-toggle" data-section="comparison">
                            <svg class="dr-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            Field Comparison
                            <span class="dr-section-badge" id="drComparisonBadge"></span>
                        </h3>
                        <div class="dr-section-body" id="drComparisonWrap">
                            <table class="double-reg-comparison-table">
                                <thead>
                                    <tr>
                                        <th style="width:22%">Field</th>
                                        <th style="width:32%">Record A</th>
                                        <th style="width:32%">Record B</th>
                                        <th style="width:14%">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="drComparisonBody"></tbody>
                            </table>
                            <div class="dr-comparison-footer" id="drComparisonFooter"></div>
                        </div>
                    </div>

                    <!-- Section 3: Confirmation -->
                    <div class="double-reg-confirmation-section" id="drConfirmSection" style="display:none;">
                        <h3 class="dr-section-toggle" data-section="confirmation">
                            <svg class="dr-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            Confirmation
                        </h3>
                        <div class="dr-section-body" id="drConfirmWrap">
                            <div class="dr-determination-box" id="drDetermination"></div>

                            <div class="dr-checkbox-row">
                                <input type="checkbox" id="drNeedsCorrection">
                                <label for="drNeedsCorrection">
                                    The 1st Registration has errors that need RA 9048 correction
                                </label>
                            </div>

                            <textarea class="dr-notes-area" id="drNotes" placeholder="Optional notes (e.g., reason for linking, discrepancy details)..." rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="double-reg-footer" id="drFooter">
                    <button type="button" class="dr-btn dr-btn-outline" id="drCancelBtn">
                        <svg width="15" height="15" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
                        Close
                    </button>
                    <div id="drActionButtons" style="display:none;gap:8px;">
                        <button type="button" class="dr-btn dr-btn-secondary" id="drNotMatchBtn">
                            Not a Match
                        </button>
                        <button type="button" class="dr-btn dr-btn-primary" id="drConfirmBtn">
                            Confirm as Double Registration
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }

    attachEventListeners() {
        this.backdrop.addEventListener('click', () => this.close());
        document.getElementById('drCloseBtn').addEventListener('click', () => this.close());
        document.getElementById('drCancelBtn').addEventListener('click', () => this.close());
        document.getElementById('drNotMatchBtn').addEventListener('click', () => this.handleNotMatch());
        document.getElementById('drConfirmBtn').addEventListener('click', () => this.handleConfirmLink());

        // Sync scroll toggle
        document.getElementById('drSyncToggle').addEventListener('click', () => this.toggleSyncScroll());

        // Synchronized scrolling between PDF panes
        const wrapA = document.getElementById('drCanvasWrapA');
        const wrapB = document.getElementById('drCanvasWrapB');
        wrapA.addEventListener('scroll', () => this._handleSyncScroll('A'));
        wrapB.addEventListener('scroll', () => this._handleSyncScroll('B'));

        // Zoom controls
        this.modal.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            if (action === 'zoom-in') this.zoom(0.25);
            if (action === 'zoom-out') this.zoom(-0.25);
        });

        // Collapsible sections
        this.modal.addEventListener('click', (e) => {
            const toggle = e.target.closest('.dr-section-toggle');
            if (toggle) {
                this.toggleSection(toggle.dataset.section);
            }
        });

        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.close();
            }
        });
    }

    async open(recordAId, recordBId, certificateType = 'birth', matchScore = null) {
        this.recordAId = recordAId;
        this.recordBId = recordBId;
        this.certificateType = certificateType;
        // Weighted similarity score (0-100) from find_potential_duplicates / record_links.match_score.
        // Supplied by the caller so the modal headline matches the table headline.
        this.matchScore = (matchScore === null || matchScore === undefined || isNaN(matchScore))
            ? null
            : Number(matchScore);

        // Show modal
        this.backdrop.classList.add('show');
        this.modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Reset UI
        document.getElementById('drLoading').style.display = 'flex';
        document.getElementById('drSummaryBar').style.display = 'none';
        document.getElementById('drPdfSection').style.display = 'none';
        document.getElementById('drComparisonSection').style.display = 'none';
        document.getElementById('drConfirmSection').style.display = 'none';
        document.getElementById('drActionButtons').style.display = 'none';

        // Reset PDF scale
        this.pdfA.scale = 1.5;
        this.pdfB.scale = 1.5;

        try {
            // Fetch both records in parallel
            const base = window.APP_BASE || '';
            const [respA, respB] = await Promise.all([
                fetch(`${base}/api/record_details.php?id=${recordAId}&type=${certificateType}`),
                fetch(`${base}/api/record_details.php?id=${recordBId}&type=${certificateType}`)
            ]);

            const dataA = await respA.json();
            const dataB = await respB.json();

            if (!dataA.success || !dataB.success) {
                throw new Error('Failed to load one or both records');
            }

            this.recordA = dataA.record;
            this.recordB = dataB.record;

            // Determine which is 1st Registration (earlier) and 2nd Registration (later)
            this.determinePrimaryDuplicate();

            // Hide loading, show sections
            document.getElementById('drLoading').style.display = 'none';
            document.getElementById('drSummaryBar').style.display = 'flex';
            document.getElementById('drComparisonSection').style.display = 'block';
            document.getElementById('drPdfSection').style.display = 'flex';
            document.getElementById('drConfirmSection').style.display = 'block';
            document.getElementById('drActionButtons').style.display = 'flex';

            // Render all sections
            this.renderPdfLabels();
            this.renderComparison();
            this.renderDetermination();

            // Load PDFs in parallel
            this.loadPdf('A', this.recordA.pdf_filename);
            this.loadPdf('B', this.recordB.pdf_filename);

        } catch (err) {
            console.error('Double reg comparison error:', err);
            document.getElementById('drLoading').innerHTML = `
                <div style="color:#DC2626; text-align:center;">
                    <p style="font-weight:600;">Failed to load records</p>
                    <p style="font-size:12px;">${err.message || 'Unknown error'}</p>
                </div>
            `;
        }
    }

    determinePrimaryDuplicate() {
        const dateA = this.recordA.date_of_registration || '';
        const dateB = this.recordB.date_of_registration || '';

        if (dateA && dateB && dateA !== dateB) {
            this.primaryRecord = dateA < dateB ? this.recordA : this.recordB;
            this.duplicateRecord = dateA < dateB ? this.recordB : this.recordA;
        } else {
            this.primaryRecord = this.recordA.id < this.recordB.id ? this.recordA : this.recordB;
            this.duplicateRecord = this.recordA.id < this.recordB.id ? this.recordB : this.recordA;
        }

        this.primaryId = parseInt(this.primaryRecord.id);
        this.duplicateId = parseInt(this.duplicateRecord.id);
    }

    renderPdfLabels() {
        const isPrimaryA = parseInt(this.recordA.id) === this.primaryId;
        const regA = this.recordA.registry_no || 'N/A';
        const regB = this.recordB.registry_no || 'N/A';

        // Set header subtitle
        document.getElementById('drSubtitle').textContent =
            `Comparing Reg# ${regA} vs Reg# ${regB}`;

        // Update toolbar labels
        const toolbarRegA = document.getElementById('drToolbarRegA');
        const toolbarRegB = document.getElementById('drToolbarRegB');
        const toolbarLabelA = document.getElementById('drToolbarLabelA');
        const toolbarLabelB = document.getElementById('drToolbarLabelB');
        const splitLabelA = document.getElementById('drSplitLabelA');
        const splitLabelB = document.getElementById('drSplitLabelB');

        if (isPrimaryA) {
            toolbarLabelA.querySelector('strong').textContent = '1st Registration';
            toolbarLabelA.querySelector('.dr-dot').className = 'dr-dot dr-dot-green';
            toolbarRegA.textContent = `Reg# ${regA}`;
            splitLabelA.textContent = `1st Reg — Reg# ${regA}`;
            splitLabelA.className = 'dr-split-pane-label primary';

            toolbarLabelB.querySelector('strong').textContent = '2nd Registration';
            toolbarLabelB.querySelector('.dr-dot').className = 'dr-dot dr-dot-blue';
            toolbarRegB.textContent = `Reg# ${regB}`;
            splitLabelB.textContent = `2nd Reg — Reg# ${regB}`;
            splitLabelB.className = 'dr-split-pane-label duplicate';
        } else {
            toolbarLabelA.querySelector('strong').textContent = '2nd Registration';
            toolbarLabelA.querySelector('.dr-dot').className = 'dr-dot dr-dot-blue';
            toolbarRegA.textContent = `Reg# ${regA}`;
            splitLabelA.textContent = `2nd Reg — Reg# ${regA}`;
            splitLabelA.className = 'dr-split-pane-label duplicate';

            toolbarLabelB.querySelector('strong').textContent = '1st Registration';
            toolbarLabelB.querySelector('.dr-dot').className = 'dr-dot dr-dot-green';
            toolbarRegB.textContent = `Reg# ${regB}`;
            splitLabelB.textContent = `1st Reg — Reg# ${regB}`;
            splitLabelB.className = 'dr-split-pane-label primary';
        }

        // Update comparison table headers
        const ths = this.modal.querySelectorAll('.double-reg-comparison-table th');
        if (ths.length >= 3) {
            ths[1].innerHTML = `<span class="dr-badge ${isPrimaryA ? 'dr-badge-green' : 'dr-badge-blue'}">${isPrimaryA ? '1st' : '2nd'}</span> Reg# ${regA}`;
            ths[2].innerHTML = `<span class="dr-badge ${isPrimaryA ? 'dr-badge-blue' : 'dr-badge-green'}">${isPrimaryA ? '2nd' : '1st'}</span> Reg# ${regB}`;
        }
    }

    renderComparison() {
        // Identity-determining fields per PSA MC 2019-23. A discrepancy in any of these
        // means the records may NOT be the same person; minor field disagreements (typos,
        // city naming) shouldn't outweigh a clean match on these.
        const CRITICAL_BIRTH_FIELDS = new Set([
            'child_first_name', 'child_middle_name', 'child_last_name',
            'child_date_of_birth', 'child_sex',
            'mother_first_name', 'mother_middle_name', 'mother_last_name',
            'father_first_name', 'father_middle_name', 'father_last_name',
        ]);

        const comparisonFields = [
            { field: 'registry_no', label: 'Registry No.' },
            { field: 'date_of_registration', label: 'Date of Registration' },
            { field: 'child_first_name', label: 'Child First Name' },
            { field: 'child_middle_name', label: 'Child Middle Name' },
            { field: 'child_last_name', label: 'Child Last Name' },
            { field: 'child_date_of_birth', label: 'Date of Birth' },
            { field: 'child_sex', label: 'Sex' },
            { field: 'birth_order', label: 'Birth Order' },
            { field: 'mother_first_name', label: 'Mother First Name' },
            { field: 'mother_middle_name', label: 'Mother Middle Name' },
            { field: 'mother_last_name', label: 'Mother Last Name' },
            { field: 'father_first_name', label: 'Father First Name' },
            { field: 'father_middle_name', label: 'Father Middle Name' },
            { field: 'father_last_name', label: 'Father Last Name' },
            { field: 'child_place_of_birth', label: 'Place of Birth' },
            { field: 'barangay', label: 'Barangay' },
        ];

        const tbody = document.getElementById('drComparisonBody');
        tbody.innerHTML = '';
        let matchCount = 0;
        let discrepancyCount = 0;
        let criticalDiscCount = 0;
        let minorDiscCount = 0;
        let totalFields = 0;

        comparisonFields.forEach(({ field, label }) => {
            const valA = (this.recordA[field] || '').toString().trim();
            const valB = (this.recordB[field] || '').toString().trim();

            // Skip if both empty
            if (!valA && !valB) return;

            totalFields++;
            const isMatch = valA.toUpperCase() === valB.toUpperCase();
            const isCritical = CRITICAL_BIRTH_FIELDS.has(field);
            if (isMatch) {
                matchCount++;
            } else {
                discrepancyCount++;
                if (isCritical) criticalDiscCount++;
                else minorDiscCount++;
            }

            const matchSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            const differSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

            // Row class: match | critical-discrepancy (red, identity-determining) | minor-discrepancy (amber, cosmetic)
            const rowClass = isMatch
                ? 'match-row'
                : (isCritical ? 'discrepancy-row dr-row-critical' : 'discrepancy-row dr-row-minor');

            const statusLabel = isMatch
                ? 'Match'
                : (isCritical ? 'Critical' : 'Minor');

            const tr = document.createElement('tr');
            tr.className = rowClass;
            const diffAttr = isMatch ? '' : ' class="dr-cell-diff"';
            tr.innerHTML = `
                <td><strong>${label}</strong>${isCritical && !isMatch ? ' <span class="dr-critical-tag">CRITICAL</span>' : ''}</td>
                <td${diffAttr}>${this.escapeHtml(valA) || '<em style="color:#94A3B8">—</em>'}</td>
                <td${diffAttr}>${this.escapeHtml(valB) || '<em style="color:#94A3B8">—</em>'}</td>
                <td>
                    <span class="dr-status-cell">
                        ${isMatch ? matchSvg : differSvg} ${statusLabel}
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Footer summary
        document.getElementById('drComparisonFooter').innerHTML = `
            <span><strong>Matches:</strong> ${matchCount} fields</span>
            <span><strong>Discrepancies:</strong> ${discrepancyCount} (${criticalDiscCount} critical, ${minorDiscCount} minor)</span>
        `;

        // Section badge
        document.getElementById('drComparisonBadge').textContent =
            `${matchCount} match${matchCount !== 1 ? 'es' : ''}, ${criticalDiscCount} critical, ${minorDiscCount} minor`;

        // Summary bar
        document.getElementById('drMatchCount').textContent = matchCount;
        document.getElementById('drDifferCount').textContent = discrepancyCount;

        // Verdict — use the weighted match_score from the DB so this matches the table headline.
        // Fall back to a plain field-equality percentage only if the caller didn't supply one.
        const score = (this.matchScore !== null)
            ? this.matchScore
            : (totalFields > 0 ? (matchCount / totalFields) * 100 : 0);
        const pct = Math.round(score * 10) / 10; // one decimal
        const pctInt = Math.round(score);

        // Verdict label is driven by critical discrepancies, not just the percentage.
        // A 90% similarity score with a critical-field mismatch is NOT a confident match.
        let verdictText, fillClass;
        if (criticalDiscCount === 0 && score >= 75) {
            verdictText = 'Likely the same person';
            fillClass = 'high';
        } else if (criticalDiscCount === 0 && score >= 50) {
            verdictText = 'Probable match — review minor differences';
            fillClass = 'medium';
        } else if (criticalDiscCount >= 1 && criticalDiscCount <= 2) {
            verdictText = `Inconclusive — ${criticalDiscCount} critical field differ${criticalDiscCount === 1 ? 's' : ''}`;
            fillClass = 'medium';
        } else if (criticalDiscCount >= 3) {
            verdictText = `Likely different people — ${criticalDiscCount} critical fields differ`;
            fillClass = 'low';
        } else {
            verdictText = 'Low similarity';
            fillClass = 'low';
        }

        document.getElementById('drSummaryVerdict').innerHTML = `
            ${verdictText}
            <div class="dr-verdict-bar">
                <div class="dr-verdict-bar-fill ${fillClass}" style="width:${pctInt}%"></div>
            </div>
            ${pct}%
        `;
    }

    renderDetermination() {
        const regNo = this.primaryRecord.registry_no || 'N/A';
        const dateStr = this.primaryRecord.date_of_registration || 'unknown date';
        const box = document.getElementById('drDetermination');
        box.innerHTML = `
            <strong>Auto-determined 1st Registration:</strong>
            Reg# ${this.escapeHtml(regNo)} (registered ${this.escapeHtml(dateStr)}) is the
            <strong>1st Registration</strong> (earlier) and will remain <strong>For Issuance</strong>.
            The other record will be blocked from issuance.
        `;
    }

    // ── Sync Scroll ────────────────────────────────────────

    toggleSyncScroll() {
        this.syncScroll = !this.syncScroll;
        document.getElementById('drSyncToggle').classList.toggle('active', this.syncScroll);
    }

    _handleSyncScroll(sourcePane) {
        if (!this.syncScroll || this._isSyncing) return;

        // In tabbed mode, sync scroll position so switching tabs shows the same area
        // In split mode, sync both panes in real-time
        const sourceWrap = document.getElementById(sourcePane === 'A' ? 'drCanvasWrapA' : 'drCanvasWrapB');
        const targetWrap = document.getElementById(sourcePane === 'A' ? 'drCanvasWrapB' : 'drCanvasWrapA');

        // Calculate scroll percentage (handles different canvas sizes gracefully)
        const maxScrollTop = sourceWrap.scrollHeight - sourceWrap.clientHeight;
        const maxScrollLeft = sourceWrap.scrollWidth - sourceWrap.clientWidth;
        const pctY = maxScrollTop > 0 ? sourceWrap.scrollTop / maxScrollTop : 0;
        const pctX = maxScrollLeft > 0 ? sourceWrap.scrollLeft / maxScrollLeft : 0;

        const targetMaxY = targetWrap.scrollHeight - targetWrap.clientHeight;
        const targetMaxX = targetWrap.scrollWidth - targetWrap.clientWidth;

        this._isSyncing = true;
        targetWrap.scrollTop = pctY * targetMaxY;
        targetWrap.scrollLeft = pctX * targetMaxX;
        this._isSyncing = false;
    }

    // ── Collapsible Sections ───────────────────────────────

    toggleSection(sectionId) {
        const bodyMap = {
            'comparison': 'drComparisonWrap',
            'confirmation': 'drConfirmWrap'
        };
        const bodyId = bodyMap[sectionId];
        if (!bodyId) return;

        const body = document.getElementById(bodyId);
        const toggle = this.modal.querySelector(`[data-section="${sectionId}"]`);

        body.classList.toggle('collapsed');
        toggle.classList.toggle('collapsed');
    }

    // ── PDF Loading & Rendering ────────────────────────────

    async loadPdf(pane, pdfFilename) {
        const wrap = document.getElementById(pane === 'A' ? 'drCanvasWrapA' : 'drCanvasWrapB');
        const splitLabel = wrap.querySelector('.dr-split-pane-label');
        const splitLabelHtml = splitLabel ? splitLabel.outerHTML : '';

        if (!pdfFilename) {
            wrap.innerHTML = splitLabelHtml + '<div class="dr-pdf-placeholder">No PDF uploaded for this record</div>';
            return;
        }

        wrap.innerHTML = splitLabelHtml + '<div class="dr-pdf-placeholder"><div class="spinner" style="width:24px;height:24px;border:2px solid #E2E8F0;border-top-color:#1E3A8A;border-radius:50%;animation:drSpin 0.8s linear infinite;"></div><br>Loading PDF...</div>';

        try {
            const base = window.APP_BASE || '';
            const response = await fetch(`${base}/api/serve_pdf.php?file=${encodeURIComponent(pdfFilename)}`);

            if (!response.ok) {
                throw new Error('PDF load failed');
            }

            const arrayBuffer = await response.arrayBuffer();

            if (typeof pdfjsLib === 'undefined') {
                throw new Error('PDF.js not loaded');
            }

            const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
            const pdfDoc = await loadingTask.promise;

            const pdfState = this['pdf' + pane];
            pdfState.doc = pdfDoc;
            pdfState.total = pdfDoc.numPages;
            pdfState.page = 1;

            // Create canvas, preserving split label
            wrap.innerHTML = splitLabelHtml;
            const canvas = document.createElement('canvas');
            wrap.appendChild(canvas);
            pdfState.canvas = canvas;
            pdfState.ctx = canvas.getContext('2d');

            await this.renderPdfPage(pane);

        } catch (err) {
            console.error(`PDF load error (pane ${pane}):`, err);
            wrap.innerHTML = splitLabelHtml + '<div class="dr-pdf-placeholder" style="color:#DC2626;">Failed to load PDF</div>';
        }
    }

    async renderPdfPage(pane) {
        const pdfState = this['pdf' + pane];
        if (!pdfState.doc) return;

        const page = await pdfState.doc.getPage(pdfState.page);
        const scaledViewport = page.getViewport({ scale: pdfState.scale });

        pdfState.canvas.width = scaledViewport.width;
        pdfState.canvas.height = scaledViewport.height;

        await page.render({
            canvasContext: pdfState.ctx,
            viewport: scaledViewport
        }).promise;
    }

    zoom(delta) {
        // Zoom both panes together
        ['A', 'B'].forEach(pane => {
            const pdfState = this['pdf' + pane];
            const newScale = Math.max(0.75, Math.min(4.0, pdfState.scale + delta));
            if (newScale === pdfState.scale) return;
            pdfState.scale = newScale;
            this.renderPdfPage(pane);
        });

        document.getElementById('drZoomDisplay').textContent = Math.round(this.pdfA.scale * 100) + '%';
    }

    // ── Actions ────────────────────────────────────────────

    handleNotMatch() {
        if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
            Notiflix.Notify.info('Records dismissed as not a match.', { timeout: 3000 });
        }
        this.close();
    }

    async handleConfirmLink() {
        const confirmBtn = document.getElementById('drConfirmBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Linking...';

        const needsCorrection = document.getElementById('drNeedsCorrection').checked;
        const notes = document.getElementById('drNotes').value.trim();

        try {
            const base = window.APP_BASE || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const response = await fetch(`${base}/api/record_link.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    primary_certificate_type: this.certificateType,
                    primary_certificate_id: this.primaryId,
                    duplicate_certificate_type: this.certificateType,
                    duplicate_certificate_id: this.duplicateId,
                    needs_correction: needsCorrection,
                    link_reason: notes,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.success) {
                if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                    Notiflix.Notify.success(
                        `Double registration confirmed. 2nd Reg (Reg# ${this.duplicateRecord.registry_no || 'N/A'}) is now blocked from issuance.`,
                        { timeout: 5000, position: 'right-top' }
                    );
                }
                this.close();

                // Refresh the page if on records viewer to show badges
                if (window.location.pathname.includes('records')) {
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                throw new Error(data.message || 'Failed to create link');
            }
        } catch (err) {
            console.error('Link creation error:', err);
            if (typeof Notiflix !== 'undefined' && Notiflix.Notify) {
                Notiflix.Notify.failure(err.message || 'Failed to link records', { timeout: 5000 });
            } else {
                alert('Error: ' + (err.message || 'Failed to link records'));
            }
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm as Double Registration';
        }
    }

    close() {
        this.backdrop.classList.remove('show');
        this.modal.classList.remove('show');
        document.body.style.overflow = '';

        // Clean up PDF docs
        if (this.pdfA.doc) { this.pdfA.doc.destroy(); this.pdfA.doc = null; }
        if (this.pdfB.doc) { this.pdfB.doc.destroy(); this.pdfB.doc = null; }

        // Fire onClose callback if set
        if (typeof this.onClose === 'function') {
            this.onClose();
            this.onClose = null;
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
