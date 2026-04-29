/**
 * RA 9048 / RA 10172 Petition Form — interactive logic
 *
 * - Toggles fee + citation + petition-number prefix per subtype
 * - Shows/hides CFN ground section, publication section
 * - Adds/removes correction rows and supporting-document rows
 * - Auto-computes opposition_deadline = publication_date_2 + 1 day
 * - Warns when subtype = CCE_10172 with a SEX correction but no medical certification doc listed
 * - Composes hidden petition_number from prefix + sequence input
 * - Mirrors petition_subtype to legacy petition_type field (CCE_minor/CCE_10172 -> CCE; CFN -> CFN)
 */
(function () {
    'use strict';

    // ---------- subtype config ----------
    const SUBTYPE_CONFIG = {
        CCE_minor: {
            prefix: 'CCE-',
            fee: 1000.00,
            law: 'R.A. 9048',
            requiresPublication: false,
            isCfn: false,
            legacyType: 'CCE'
        },
        CCE_10172: {
            prefix: 'CCE-',
            fee: 1000.00,
            law: 'R.A. 9048 as amended by R.A. 10172',
            requiresPublication: true,
            isCfn: false,
            legacyType: 'CCE'
        },
        CFN: {
            prefix: 'CFN-',
            fee: 3000.00,
            law: 'R.A. 9048',
            requiresPublication: true,
            isCfn: true,
            legacyType: 'CFN'
        }
    };

    // ---------- helpers ----------
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function refreshLucideIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function addDays(dateStr, days) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    // ---------- subtype toggle ----------
    function getSelectedSubtype() {
        const checked = $('input[name="petition_subtype"]:checked');
        return checked ? checked.value : '';
    }

    function applySubtype() {
        const subtype = getSelectedSubtype();
        const cfg = SUBTYPE_CONFIG[subtype];
        if (!cfg) return;

        const feeText = $('#feeText');
        const feeInput = $('#fee_amount');
        const specialLaw = $('#special_law');
        const prefixSpan = $('#petitionNumberPrefix');
        const cfnSection = $('#cfnGroundSection');
        const pubSection = $('#publicationSection');
        const legacyTypeInput = $('#petition_type_hidden');

        if (feeText) feeText.textContent = cfg.fee.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        if (feeInput) feeInput.value = cfg.fee.toFixed(2);
        if (specialLaw) specialLaw.value = cfg.law;
        if (prefixSpan) prefixSpan.textContent = cfg.prefix;
        if (legacyTypeInput) legacyTypeInput.value = cfg.legacyType;

        if (cfnSection) cfnSection.style.display = cfg.isCfn ? '' : 'none';
        if (pubSection) pubSection.style.display = cfg.requiresPublication ? '' : 'none';

        composePetitionNumber();
        updateMedicalCertificationHint();
    }

    // ---------- petition number composition ----------
    function composePetitionNumber() {
        const prefixSpan = $('#petitionNumberPrefix');
        const seqInput = $('#petition_number_seq');
        const hidden = $('#petition_number');
        if (!prefixSpan || !seqInput || !hidden) return;

        const seq = (seqInput.value || '').trim();
        hidden.value = seq ? (prefixSpan.textContent + seq) : '';
    }

    // ---------- petitioner: is_self toggle ----------
    function applySelfPetitionToggle() {
        const cb = $('#is_self_petition');
        const relField = $('#relationField');
        const relInput = $('#relation_to_owner');
        if (!cb || !relField) return;

        if (cb.checked) {
            relField.style.opacity = '0.5';
            if (relInput) relInput.disabled = true;
        } else {
            relField.style.opacity = '';
            if (relInput) relInput.disabled = false;
        }
    }

    // ---------- corrections grid ----------
    let correctionsCounter = (window.RA9048_INIT && window.RA9048_INIT.correctionRows) || 0;

    function buildCorrectionRow(idx) {
        const row = document.createElement('div');
        row.className = 'grid-row';
        row.dataset.rowIndex = String(idx);
        row.innerHTML =
            '<div class="grid-col grid-col-num">' + (idx + 1) + '</div>' +
            '<div class="grid-col grid-col-nature">' +
                '<select name="corrections[' + idx + '][nature]">' +
                    '<option value="CCE">CCE</option>' +
                    '<option value="CFN">CFN</option>' +
                '</select>' +
            '</div>' +
            '<div class="grid-col grid-col-desc">' +
                '<input type="text" name="corrections[' + idx + '][description]" placeholder="e.g. FATHER\'S FIRST NAME">' +
            '</div>' +
            '<div class="grid-col grid-col-val">' +
                '<input type="text" name="corrections[' + idx + '][value_from]">' +
            '</div>' +
            '<div class="grid-col grid-col-val">' +
                '<input type="text" name="corrections[' + idx + '][value_to]">' +
            '</div>' +
            '<div class="grid-col grid-col-action">' +
                '<button type="button" class="row-delete" title="Remove row"><i data-lucide="trash-2"></i></button>' +
            '</div>';
        return row;
    }

    function addCorrection() {
        const body = $('#correctionsBody');
        if (!body) return;
        const row = buildCorrectionRow(correctionsCounter++);
        body.appendChild(row);
        refreshLucideIcons();
    }

    // ---------- supporting docs grid ----------
    let supportingCounter = (window.RA9048_INIT && window.RA9048_INIT.supportingRows) || 0;

    function buildSupportingRow(idx) {
        const row = document.createElement('div');
        row.className = 'grid-row';
        row.dataset.rowIndex = String(idx);
        row.innerHTML =
            '<div class="grid-col grid-col-num">' + (idx + 1) + '</div>' +
            '<div class="grid-col grid-col-doc">' +
                '<input type="text" name="supporting_docs[' + idx + '][doc_label]" placeholder="e.g. Police Clearance">' +
            '</div>' +
            '<div class="grid-col grid-col-action">' +
                '<button type="button" class="row-delete" title="Remove row"><i data-lucide="trash-2"></i></button>' +
            '</div>';
        return row;
    }

    function addSupporting() {
        const body = $('#supportingBody');
        if (!body) return;
        const row = buildSupportingRow(supportingCounter++);
        body.appendChild(row);
        refreshLucideIcons();
    }

    function renumberRows(bodyId, colSelector) {
        const body = $('#' + bodyId);
        if (!body) return;
        $$('.grid-row', body).forEach(function (row, i) {
            const numCol = $(colSelector, row);
            if (numCol) numCol.textContent = String(i + 1);
        });
    }

    // ---------- medical certification hint (sex correction guard) ----------
    function updateMedicalCertificationHint() {
        const hint = $('#medCertHint');
        if (!hint) return;
        const subtype = getSelectedSubtype();
        if (subtype !== 'CCE_10172') {
            hint.style.display = 'none';
            return;
        }
        const hasSexCorrection = $$('input[name^="corrections["][name$="[description]"]').some(function (inp) {
            return /^\s*sex\s*$/i.test(inp.value);
        });
        const hasMedCert = $$('input[name^="supporting_docs["][name$="[doc_label]"]').some(function (inp) {
            return /medical\s*certif/i.test(inp.value);
        });
        hint.style.display = (hasSexCorrection && !hasMedCert) ? '' : 'none';
        refreshLucideIcons();
    }

    // ---------- opposition deadline auto-calc ----------
    function recomputeOppositionDeadline() {
        const pub2 = $('#publication_date_2');
        const out = $('#opposition_deadline');
        if (!pub2 || !out) return;
        out.value = pub2.value ? addDays(pub2.value, 1) : '';
    }

    // ---------- posting end date suggestion ----------
    function suggestPostingEndDate() {
        const start = $('#posting_start_date');
        const end = $('#posting_end_date');
        if (!start || !end || !start.value) return;
        // 10 working days ≈ 14 calendar days (skipping weekends roughly).
        // Templates show ~14-day spans (e.g. Apr 21 → May 5). Use 14 as a practical default.
        if (!end.value) end.value = addDays(start.value, 13);
    }

    // ---------- event wiring ----------
    document.addEventListener('DOMContentLoaded', function () {
        // Subtype radios
        $$('input[name="petition_subtype"]').forEach(function (r) {
            r.addEventListener('change', applySubtype);
        });
        applySubtype(); // initial

        // Petition number sequence
        const seqInput = $('#petition_number_seq');
        if (seqInput) {
            seqInput.addEventListener('input', composePetitionNumber);
        }

        // Self-petition toggle
        const selfCb = $('#is_self_petition');
        if (selfCb) {
            selfCb.addEventListener('change', applySelfPetitionToggle);
            applySelfPetitionToggle();
        }

        // Add row buttons
        const addCorrBtn = $('#addCorrectionBtn');
        if (addCorrBtn) addCorrBtn.addEventListener('click', addCorrection);
        const addSuppBtn = $('#addSupportingBtn');
        if (addSuppBtn) addSuppBtn.addEventListener('click', addSupporting);

        // Delegated row-delete + grid input changes (for med-cert hint)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.row-delete');
            if (!btn) return;
            const row = btn.closest('.grid-row');
            const grid = btn.closest('.grid-table');
            if (!row || !grid) return;
            row.remove();
            if (grid.id === 'correctionsGrid') renumberRows('correctionsBody', '.grid-col-num');
            if (grid.id === 'supportingGrid') renumberRows('supportingBody', '.grid-col-num');
            updateMedicalCertificationHint();
        });

        document.addEventListener('input', function (e) {
            if (!e.target.matches) return;
            if (e.target.matches('input[name^="corrections["], input[name^="supporting_docs["]')) {
                updateMedicalCertificationHint();
            }
        });

        // Publication date 2 → opposition deadline
        const pub2 = $('#publication_date_2');
        if (pub2) pub2.addEventListener('change', recomputeOppositionDeadline);

        // Posting start → suggest end
        const postStart = $('#posting_start_date');
        if (postStart) postStart.addEventListener('change', suggestPostingEndDate);

        // Owner lookup modal
        wireOwnerLookup();

        // Petition number uniqueness check
        wirePetitionNumberCheck();

        refreshLucideIcons();
    });

    // ---------- Owner lookup (queries api/ra9048/lookup_owner.php) ----------
    function wireOwnerLookup() {
        const lookupBtn   = $('#lookupOwnerBtn');
        const lookupModal = $('#ownerLookupModal');
        const lookupClose = $('#lookupCloseBtn');
        const queryInput  = $('#lookupQuery');
        const resultsBox  = $('#lookupResults');
        if (!lookupBtn || !lookupModal) return;

        const openModal = function () {
            lookupModal.style.display = 'flex';
            if (queryInput) {
                queryInput.value = '';
                setTimeout(function () { queryInput.focus(); }, 50);
            }
            renderResults([]);
        };
        const closeModal = function () { lookupModal.style.display = 'none'; };

        lookupBtn.addEventListener('click', openModal);
        if (lookupClose) lookupClose.addEventListener('click', closeModal);
        lookupModal.addEventListener('click', function (e) {
            if (e.target.classList.contains('lookup-modal-backdrop')) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && lookupModal.style.display === 'flex') closeModal();
        });

        let debounceTimer = null;
        if (queryInput) {
            queryInput.addEventListener('input', function () {
                const q = queryInput.value.trim();
                clearTimeout(debounceTimer);
                if (q.length < 2) {
                    renderResults([], 'Type at least 2 characters…');
                    return;
                }
                debounceTimer = setTimeout(function () { runLookup(q); }, 250);
            });
        }

        function renderResults(items, emptyMsg) {
            if (!resultsBox) return;
            resultsBox.innerHTML = '';
            if (!items || items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'lookup-result-empty';
                empty.textContent = emptyMsg || 'No matching records.';
                resultsBox.appendChild(empty);
                return;
            }
            items.forEach(function (item) {
                const el = document.createElement('div');
                el.className = 'lookup-result-item';
                el.textContent = item.display_label || item.document_owner_names || ('Record #' + item.id);
                el.addEventListener('click', function () { applyLookupSelection(item); closeModal(); });
                resultsBox.appendChild(el);
            });
        }

        function runLookup(q) {
            renderResults([], 'Searching…');
            fetch('../../api/ra9048/lookup_owner.php?q=' + encodeURIComponent(q), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (resp) { return resp.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        renderResults(json.data || []);
                    } else {
                        renderResults([], (json && json.message) || 'Lookup failed.');
                    }
                })
                .catch(function () { renderResults([], 'Lookup failed (network).'); });
        }

        function applyLookupSelection(item) {
            const setVal = function (id, val) {
                const el = document.getElementById(id);
                if (el && (val !== undefined && val !== null)) el.value = val;
            };
            setVal('document_owner_names',      item.document_owner_names);
            setVal('owner_dob',                 item.owner_dob);
            setVal('owner_birthplace_city',     item.owner_birthplace_city);
            setVal('owner_birthplace_province', item.owner_birthplace_province);
            setVal('owner_birthplace_country',  item.owner_birthplace_country);
            setVal('registry_number',           item.registry_no);
            setVal('father_full_name',          item.father_full_name);
            setVal('mother_full_name',          item.mother_full_name);
            // Default document_type to COLB since lookup currently only searches births
            const docType = document.getElementById('document_type');
            if (docType && !docType.value) docType.value = 'COLB';

            if (window.Notiflix && Notiflix.Notify && Notiflix.Notify.success) {
                Notiflix.Notify.success('Document owner pre-filled from existing record.');
            }
        }
    }

    // ---------- Petition number uniqueness check ----------
    function wirePetitionNumberCheck() {
        const seqInput = $('#petition_number_seq');
        const hidden   = $('#petition_number');
        const help     = $('#petitionNumberHelp');
        if (!seqInput || !hidden || !help) return;

        // Stash the original help-text so we can restore it after a check.
        const originalHelpHTML = help.innerHTML;
        const originalColor    = help.style.color;

        // Determine if we're editing an existing record (so we exclude its id from the check).
        const editId = (window.RA9048_INIT && window.RA9048_INIT.editMode)
            ? (function () {
                const recIdInput = document.querySelector('input[name="record_id"]');
                return recIdInput ? parseInt(recIdInput.value, 10) || 0 : 0;
            })()
            : 0;

        let timer = null;
        const trigger = function () {
            composePetitionNumber(); // make sure hidden is up to date
            const number = hidden.value;
            clearTimeout(timer);

            if (!number) {
                resetHelp();
                return;
            }

            timer = setTimeout(function () { runCheck(number); }, 350);
        };

        seqInput.addEventListener('blur', trigger);
        seqInput.addEventListener('input', function () {
            // Clear any prior verdict while user is typing
            resetHelp();
        });

        // Re-run check when subtype changes (since the prefix changes)
        $$('input[name="petition_subtype"]').forEach(function (r) {
            r.addEventListener('change', trigger);
        });

        function runCheck(number) {
            const url = '../../api/ra9048/check_petition_number.php'
                + '?number=' + encodeURIComponent(number)
                + (editId ? '&exclude_id=' + editId : '');
            fetch(url, { method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (resp) { return resp.json(); })
                .then(function (json) {
                    if (!json || !json.success || !json.data) {
                        resetHelp();
                        return;
                    }
                    if (json.data.reason === 'invalid_format') {
                        showHelp('Invalid format. Use e.g. <code>0130-2025</code>.', '#b91c1c');
                        return;
                    }
                    if (json.data.available) {
                        showHelp('✓ Available.', '#15803d');
                    } else {
                        showHelp('⚠ Already used by another petition.', '#b91c1c');
                    }
                })
                .catch(function () { resetHelp(); });
        }

        function showHelp(html, color) {
            help.innerHTML = html;
            help.style.color = color || '';
        }

        function resetHelp() {
            help.innerHTML = originalHelpHTML;
            help.style.color = originalColor;
        }
    }
})();
