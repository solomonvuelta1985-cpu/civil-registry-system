/**
 * Family Relations Renderer
 * Shared renderer used by:
 *  - public/family_relations.php (full-page family view)
 *  - assets/js/record-preview-modal.js (in-modal Family Relations section for birth records)
 *
 * Usage: FamilyRelationsRender.render(data, containerEl, { onView: (id, type) => {} })
 *   - data: payload returned by api/family_relations.php
 *   - containerEl: HTMLElement to render into
 *   - options.onView: optional callback invoked when a "View" link is clicked.
 *     If omitted, falls back to recordPreviewModal.open(id, type) when available.
 */
(function (global) {
    'use strict';

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return '';
        const d = new Date(value);
        if (isNaN(d.getTime())) return escapeHtml(value);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function confidenceBadge(level) {
        if (level === 'high') return '<span class="fr-badge fr-badge-high">High</span>';
        if (level === 'medium') return '<span class="fr-badge fr-badge-medium">Medium</span>';
        return '<span class="fr-badge fr-badge-low">Low</span>';
    }

    function viewButton(item) {
        return `<button type="button" class="fr-view-btn" data-id="${item.id}" data-type="${item.type}">View &rarr;</button>`;
    }

    function recordRow(item) {
        const reg = item.registry_no ? `<span class="fr-pill fr-pill-reg">Reg# ${escapeHtml(item.registry_no)}</span>` : '<span class="fr-pill fr-pill-muted">No Reg#</span>';
        const dateText = item.date_label ? `${escapeHtml(item.date_label)} ${formatDate(item.date)}` : formatDate(item.date);
        const dateChip = item.date ? `<span class="fr-pill fr-pill-date">${dateText}</span>` : '';
        const placeChip = item.place ? `<span class="fr-pill fr-pill-place">${escapeHtml(item.place)}</span>` : '';
        return `
            <div class="fr-row">
                <div class="fr-row-main">
                    <div class="fr-row-name">${escapeHtml(item.display_name)}</div>
                    <div class="fr-row-meta fr-row-pills">${reg}${dateChip}${placeChip}</div>
                </div>
                <div class="fr-row-actions">
                    ${confidenceBadge(item.confidence)}
                    ${viewButton(item)}
                </div>
            </div>
        `;
    }

    function emptyState(text) {
        return `<div class="fr-empty">${escapeHtml(text)}</div>`;
    }

    function renderParents(source, parentsMarriage) {
        const fatherValue = source.father_filled
            ? `<span class="fr-name-strong">${escapeHtml(source.father_name)}</span>`
            : `<span class="fr-pill fr-pill-muted">Not stated on birth record</span>`;
        const motherValue = source.mother_filled
            ? `<span class="fr-name-strong">${escapeHtml(source.mother_name)}</span>`
            : `<span class="fr-pill fr-pill-muted">Not stated on birth record</span>`;

        let marriageBlock;
        if (!parentsMarriage.stated) {
            marriageBlock = `
                <div class="fr-marriage-meta">
                    <span class="fr-marriage-label">Marriage</span>
                    <span class="fr-pill fr-pill-muted">Parents not married per birth record</span>
                </div>
            `;
        } else {
            const date = formatDate(parentsMarriage.date);
            const placePill = parentsMarriage.place ? `<span class="fr-pill fr-pill-place">${escapeHtml(parentsMarriage.place)}</span>` : '';
            const datePill = date ? `<span class="fr-pill fr-pill-date">${date}</span>` : '';
            const matched = parentsMarriage.matched || [];

            if (matched.length === 0) {
                marriageBlock = `
                    <div class="fr-marriage-meta">
                        <span class="fr-marriage-label">Marriage</span>
                        ${datePill}
                        ${placePill}
                    </div>
                    <div class="fr-empty-inline">No marriage record found in this office.</div>
                `;
            } else {
                const rows = matched.map(recordRow).join('');
                marriageBlock = `
                    <div class="fr-marriage-meta">
                        <span class="fr-marriage-label">Marriage</span>
                        ${datePill}
                        ${placePill}
                    </div>
                    <div class="fr-sub-list">${rows}</div>
                `;
            }
        }

        return `
            <section class="fr-section fr-section-parents">
                <header class="fr-section-header">
                    <span class="fr-section-title">Parents</span>
                </header>
                <div class="fr-section-body">
                    <div class="fr-parent-row">
                        <span class="fr-parent-label">Father</span>
                        <span class="fr-parent-value">${fatherValue}</span>
                    </div>
                    <div class="fr-parent-row">
                        <span class="fr-parent-label">Mother</span>
                        <span class="fr-parent-value">${motherValue}</span>
                    </div>
                    ${marriageBlock}
                </div>
            </section>
        `;
    }

    function renderSiblings(label, siblings) {
        if (!label || !siblings) return '';
        const count = siblings.length;
        const titleLabel = label || 'Siblings';
        const body = count === 0
            ? emptyState('No matching sibling records found in this office.')
            : siblings.map(recordRow).join('');
        return `
            <section class="fr-section fr-section-siblings">
                <header class="fr-section-header">
                    <span class="fr-section-title">${escapeHtml(titleLabel)}</span>
                    <span class="fr-count-badge">${count}</span>
                </header>
                <div class="fr-section-body">${body}</div>
            </section>
        `;
    }

    function renderParentDeaths(source, parentDeaths) {
        if (!source.father_filled && !source.mother_filled) return '';

        function block(parentLabel, parentName, list) {
            const labelHtml = `
                <div class="fr-death-label">
                    <span class="fr-parent-label">${escapeHtml(parentLabel)}</span>
                    <span class="fr-name-strong">${escapeHtml(parentName)}</span>
                </div>
            `;
            if (!list || list.length === 0) {
                return `
                    <div class="fr-death-group">
                        ${labelHtml}
                        <div class="fr-empty-inline">No death record found in this office.</div>
                    </div>
                `;
            }
            const rows = list.map(recordRow).join('');
            return `
                <div class="fr-death-group fr-death-group-found">
                    ${labelHtml}
                    <div class="fr-sub-list">${rows}</div>
                </div>
            `;
        }

        let body = '';
        if (source.father_filled) body += block('Father', source.father_name, parentDeaths.father || []);
        if (source.mother_filled) body += block('Mother', source.mother_name, parentDeaths.mother || []);

        return `
            <section class="fr-section fr-section-deaths">
                <header class="fr-section-header">
                    <span class="fr-section-title">Parent Death Records</span>
                </header>
                <div class="fr-section-body">${body}</div>
            </section>
        `;
    }

    function renderHeader(source) {
        const name = escapeHtml(source.child_name || '(unnamed)');
        const reg = source.registry_no ? `Reg# ${escapeHtml(source.registry_no)}` : 'No Reg#';
        const dob = source.child_date_of_birth ? `Born ${formatDate(source.child_date_of_birth)}` : '';
        const meta = [reg, dob].filter(Boolean).join(' &middot; ');
        return `
            <div class="fr-header">
                <div class="fr-header-title">Family of ${name}</div>
                <div class="fr-header-meta">${meta} &middot;
                    <button type="button" class="fr-view-btn" data-id="${source.id}" data-type="birth">View birth record &rarr;</button>
                </div>
            </div>
            <div class="fr-disclaimer">
                Matches are based on names as written on the forms. Verify before relying on them for legal purposes.
            </div>
        `;
    }

    function attachHandlers(container, options) {
        const onView = (options && options.onView) || function (id, type) {
            // recordPreviewModal is declared at module scope by record-preview-modal.js
            if (typeof recordPreviewModal !== 'undefined' && recordPreviewModal && typeof recordPreviewModal.open === 'function') {
                recordPreviewModal.open(id, type);
            }
        };
        container.querySelectorAll('.fr-view-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const type = btn.getAttribute('data-type');
                if (id > 0 && type) onView(id, type);
            });
        });
    }

    function render(data, container, options) {
        if (!container) return;
        if (!data || !data.source) {
            container.innerHTML = '<div class="fr-empty">No data available.</div>';
            return;
        }
        const showHeader = !(options && options.skipHeader === true);
        const html = (showHeader ? renderHeader(data.source) : '')
            + renderParents(data.source, data.parents_marriage || { stated: false, matched: [] })
            + renderSiblings(data.siblings_label || '', data.siblings || [])
            + renderParentDeaths(data.source, data.parent_deaths || {});

        container.innerHTML = html;
        attachHandlers(container, options || {});

        if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
    }

    global.FamilyRelationsRender = { render: render };
})(window);
