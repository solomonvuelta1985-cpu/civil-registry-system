<?php
/**
 * RA 9048/10172 — Records Listing
 * Tabbed view: Petition | Legal Instrument | Court Decree
 * With search, date filter, pagination, edit/delete, and export links.
 */

require_once '../../includes/session_config.php';
require_once '../../includes/config_ra9048.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/security.php';

requireAuth();

$active_tab = sanitize_input($_GET['type'] ?? 'petition');
if (!in_array($active_tab, ['petition', 'legal_instrument', 'court_decree'])) {
    $active_tab = 'petition';
}

$can_delete = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrfTokenMeta() ?>
    <title>RA 9048 Records - <?= APP_SHORT_NAME ?></title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../../assets/js/notiflix-config.js"></script>

    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/certificate-forms-shared.css?v=2.1">
    <link rel="stylesheet" href="../../assets/css/ra9048.css?v=1.0">
</head>
<body>
    <?php include '../../includes/preloader.php'; ?>
    <?php include '../../includes/mobile_header.php'; ?>
    <?php include '../../includes/sidebar_nav.php'; ?>
    <?php include '../../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="main-content-wrapper">
            <div class="form-content-container">
                <!-- System Header -->
                <div class="system-header">
                    <div class="system-logo">
                        <img src="../../assets/img/LOGO1.png" alt="Logo">
                    </div>
                    <div class="system-title-container">
                        <h1 class="system-title">Civil Registry Document Management System (CRDMS)</h1>
                        <p class="system-subtitle">Lalawigan ng Cagayan - Bayan ng Baggao</p>
                    </div>
                </div>

                <!-- Page Header -->
                <div class="ra9048-page-header">
                    <h1>RA 9048 / 10172 Records</h1>
                    <p>View, search, and manage all RA 9048 transaction records</p>
                </div>

                <!-- Tab Bar -->
                <div class="ra9048-tab-bar">
                    <button class="ra9048-tab <?= $active_tab === 'petition' ? 'ra9048-tab--active' : '' ?>" data-tab="petition">
                        <i data-lucide="file-pen"></i> Petition
                        <span class="ra9048-tab-count" id="petitionCount">0</span>
                    </button>
                    <button class="ra9048-tab <?= $active_tab === 'legal_instrument' ? 'ra9048-tab--active' : '' ?>" data-tab="legal_instrument">
                        <i data-lucide="scale"></i> Legal Instrument
                        <span class="ra9048-tab-count" id="legalInstrumentCount">0</span>
                    </button>
                    <button class="ra9048-tab <?= $active_tab === 'court_decree' ? 'ra9048-tab--active' : '' ?>" data-tab="court_decree">
                        <i data-lucide="gavel"></i> Court Decree
                        <span class="ra9048-tab-count" id="courtDecreeCount">0</span>
                    </button>
                </div>

                <!-- Filter Bar -->
                <div class="ra9048-filter-bar">
                    <div class="ra9048-filter-group">
                        <div class="ra9048-search-box">
                            <i data-lucide="search" style="width:16px;height:16px;color:#94a3b8;"></i>
                            <input type="text" id="searchInput" placeholder="Search records..." class="ra9048-search-input">
                        </div>
                        <input type="date" id="dateFrom" class="ra9048-date-input" title="From date">
                        <input type="date" id="dateTo" class="ra9048-date-input" title="To date">
                        <button type="button" id="clearFilters" class="ra9048-btn-clear" title="Clear filters">
                            <i data-lucide="x"></i> Clear
                        </button>
                    </div>
                    <div class="ra9048-filter-actions">
                        <a href="index.php" class="ra9048-btn-action" title="New Record">
                            <i data-lucide="plus"></i> New
                        </a>
                        <div class="ra9048-export-dropdown">
                            <button type="button" class="ra9048-btn-action ra9048-btn-export" id="exportBtn">
                                <i data-lucide="download"></i> Export
                            </button>
                            <div class="ra9048-export-menu" id="exportMenu">
                                <a href="#" class="ra9048-export-option" data-format="xls">
                                    <i data-lucide="file-spreadsheet"></i> Export as Excel (.xls)
                                </a>
                                <a href="#" class="ra9048-export-option" data-format="csv">
                                    <i data-lucide="file-text"></i> Export as CSV (.csv)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="ra9048-records-table-wrapper">
                    <table class="ra9048-records-table" id="recordsTable">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody">
                            <tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">Loading records...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="ra9048-pagination" id="pagination"></div>

            </div>
        </div>
    </div>

    <script>
    (function() {
        const API_URL = '../../api/ra9048/records_search.php';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const CAN_DELETE = <?= $can_delete ? 'true' : 'false' ?>;

        let currentTab = '<?= $active_tab ?>';
        let currentPage = 1;
        let searchTimeout = null;

        // Table column configs per tab
        const tabConfigs = {
            petition: {
                columns: [
                    { label: '#', field: 'id', width: '50px' },
                    { label: 'Type', field: 'petition_type' },
                    { label: 'Date Filed', field: 'date_of_filing' },
                    { label: 'Document Owner/s', field: 'document_owner_names' },
                    { label: 'Petitioner/s', field: 'petitioner_names' },
                    { label: 'Document', field: 'document_type' },
                    { label: 'Fee', field: 'fee_amount' },
                    { label: 'PDF', field: 'pdf_filename' },
                    { label: 'Actions', field: '_actions' }
                ],
                editUrl: 'petition.php?id=',
                deleteApi: '../../api/ra9048/petition_delete.php',
                badgeField: 'petition_type',
                badgeMap: { 'CCE': 'badge-cce', 'CFN': 'badge-cfn' }
            },
            legal_instrument: {
                columns: [
                    { label: '#', field: 'id', width: '50px' },
                    { label: 'Type', field: 'instrument_type' },
                    { label: 'Date Filed', field: 'date_of_filing' },
                    { label: 'Document Owner/s', field: 'document_owner_names' },
                    { label: 'Affiant/s', field: 'affiant_names' },
                    { label: 'Document', field: 'document_type' },
                    { label: 'Registry No.', field: 'registry_number' },
                    { label: 'PDF', field: 'pdf_filename' },
                    { label: 'Actions', field: '_actions' }
                ],
                editUrl: 'legal_instrument.php?id=',
                deleteApi: '../../api/ra9048/legal_instrument_delete.php',
                badgeField: 'instrument_type',
                badgeMap: { 'AUSF': 'badge-ausf', 'Supplemental': 'badge-supplemental', 'Legitimation': 'badge-legitimation' }
            },
            court_decree: {
                columns: [
                    { label: '#', field: 'id', width: '50px' },
                    { label: 'Type', field: 'decree_type' },
                    { label: 'Court', field: '_court_info' },
                    { label: 'Case No.', field: 'case_number' },
                    { label: 'Date of Decree', field: 'date_of_decree' },
                    { label: 'Document Owner/s', field: 'document_owner_names' },
                    { label: 'Document', field: 'document_type' },
                    { label: 'PDF', field: 'pdf_filename' },
                    { label: 'Actions', field: '_actions' }
                ],
                editUrl: 'court_decree.php?id=',
                deleteApi: '../../api/ra9048/court_decree_delete.php',
                badgeField: 'decree_type',
                badgeMap: {}
            }
        };

        // Build table header
        function renderHeader() {
            const config = tabConfigs[currentTab];
            const tr = document.createElement('tr');
            config.columns.forEach(col => {
                const th = document.createElement('th');
                th.textContent = col.label;
                if (col.width) th.style.width = col.width;
                tr.appendChild(th);
            });
            document.getElementById('tableHead').innerHTML = '';
            document.getElementById('tableHead').appendChild(tr);
        }

        // Fetch records
        function loadRecords() {
            const search = document.getElementById('searchInput').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            const params = new URLSearchParams({
                type: currentTab,
                search: search,
                page: currentPage,
                per_page: 15,
                date_from: dateFrom,
                date_to: dateTo
            });

            fetch(API_URL + '?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        renderRows(data.records);
                        renderPagination(data.pagination);
                        updateTabCount(currentTab, data.pagination.total_records);
                    } else {
                        showEmptyState('Error loading records.');
                    }
                })
                .catch(() => showEmptyState('Failed to connect to server.'));
        }

        // Render table rows
        function renderRows(records) {
            const tbody = document.getElementById('tableBody');
            const config = tabConfigs[currentTab];

            if (!records.length) {
                showEmptyState('No records found.');
                return;
            }

            tbody.innerHTML = '';
            records.forEach(rec => {
                const tr = document.createElement('tr');
                config.columns.forEach(col => {
                    const td = document.createElement('td');

                    if (col.field === '_actions') {
                        td.innerHTML = renderActions(rec, config);
                    } else if (col.field === '_court_info') {
                        const parts = [rec.court_branch, rec.court_city_municipality, rec.court_province].filter(Boolean);
                        td.textContent = parts.join(', ') || '—';
                        td.style.fontSize = '0.82rem';
                    } else if (col.field === 'pdf_filename') {
                        td.innerHTML = rec.pdf_filename
                            ? '<span style="color:#22c55e;" title="PDF attached"><i data-lucide="file-check" style="width:16px;height:16px;"></i></span>'
                            : '<span style="color:#cbd5e1;">—</span>';
                    } else if (col.field === 'fee_amount') {
                        td.textContent = rec.fee_amount ? '₱' + parseFloat(rec.fee_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—';
                    } else if (col.field === config.badgeField) {
                        const val = rec[col.field] || '';
                        const badgeClass = config.badgeMap[val] || 'badge-default';
                        const label = col.field === 'decree_type' && val === 'Other' ? (rec.decree_type_other || 'Other') : val;
                        td.innerHTML = '<span class="ra9048-badge ' + badgeClass + '">' + escapeHtml(label) + '</span>';
                    } else if (col.field === 'date_of_filing' || col.field === 'date_of_decree') {
                        td.textContent = rec[col.field] ? formatDate(rec[col.field]) : '—';
                    } else if (col.field === 'document_type') {
                        td.innerHTML = rec.document_type ? '<span class="ra9048-badge badge-doc">' + escapeHtml(rec.document_type) + '</span>' : '—';
                    } else {
                        td.textContent = rec[col.field] || '—';
                    }

                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            lucide.createIcons();
        }

        function renderActions(rec, config) {
            let html = '<div class="ra9048-actions">';
            html += '<a href="' + config.editUrl + rec.id + '" class="ra9048-action-btn ra9048-action-edit" title="Edit"><i data-lucide="pencil" style="width:14px;height:14px;"></i></a>';
            if (CAN_DELETE) {
                html += '<button type="button" class="ra9048-action-btn ra9048-action-delete" data-id="' + rec.id + '" data-name="' + escapeHtml(rec.document_owner_names) + '" title="Delete"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></button>';
            }
            html += '</div>';
            return html;
        }

        function showEmptyState(msg) {
            const config = tabConfigs[currentTab];
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="' + config.columns.length + '" style="text-align:center;padding:40px;color:#94a3b8;">' + escapeHtml(msg) + '</td></tr>';
        }

        // Pagination
        function renderPagination(p) {
            const container = document.getElementById('pagination');
            if (p.total_pages <= 1) {
                container.innerHTML = '<span class="ra9048-pagination-info">Showing ' + p.from + '–' + p.to + ' of ' + p.total_records + ' records</span>';
                return;
            }

            let html = '<span class="ra9048-pagination-info">Showing ' + p.from + '–' + p.to + ' of ' + p.total_records + ' records</span>';
            html += '<div class="ra9048-pagination-buttons">';

            if (p.current_page > 1) {
                html += '<button class="ra9048-page-btn" data-page="' + (p.current_page - 1) + '">&laquo; Prev</button>';
            }

            const start = Math.max(1, p.current_page - 2);
            const end = Math.min(p.total_pages, p.current_page + 2);
            for (let i = start; i <= end; i++) {
                html += '<button class="ra9048-page-btn ' + (i === p.current_page ? 'ra9048-page-btn--active' : '') + '" data-page="' + i + '">' + i + '</button>';
            }

            if (p.current_page < p.total_pages) {
                html += '<button class="ra9048-page-btn" data-page="' + (p.current_page + 1) + '">Next &raquo;</button>';
            }

            html += '</div>';
            container.innerHTML = html;
        }

        function updateTabCount(tab, count) {
            const countMap = { petition: 'petitionCount', legal_instrument: 'legalInstrumentCount', court_decree: 'courtDecreeCount' };
            const el = document.getElementById(countMap[tab]);
            if (el) el.textContent = count;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str || '';
            return div.innerHTML;
        }

        // --- Event Listeners ---

        // Tabs
        document.querySelectorAll('.ra9048-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.ra9048-tab').forEach(t => t.classList.remove('ra9048-tab--active'));
                this.classList.add('ra9048-tab--active');
                currentTab = this.dataset.tab;
                currentPage = 1;
                // Update URL without reload
                history.replaceState(null, '', 'records.php?type=' + currentTab);
                renderHeader();
                loadRecords();
            });
        });

        // Search with debounce
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { currentPage = 1; loadRecords(); }, 300);
        });

        // Date filters
        document.getElementById('dateFrom').addEventListener('change', () => { currentPage = 1; loadRecords(); });
        document.getElementById('dateTo').addEventListener('change', () => { currentPage = 1; loadRecords(); });

        // Clear filters
        document.getElementById('clearFilters').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            currentPage = 1;
            loadRecords();
        });

        // Pagination clicks (delegated)
        document.getElementById('pagination').addEventListener('click', function(e) {
            const btn = e.target.closest('[data-page]');
            if (btn) {
                currentPage = parseInt(btn.dataset.page);
                loadRecords();
            }
        });

        // Delete (delegated)
        document.getElementById('tableBody').addEventListener('click', function(e) {
            const btn = e.target.closest('.ra9048-action-delete');
            if (!btn) return;

            const id = btn.dataset.id;
            const name = btn.dataset.name;
            const config = tabConfigs[currentTab];

            Notiflix.Confirm.show(
                'Delete Record',
                'Move record for "' + name + '" to trash?',
                'Delete',
                'Cancel',
                function() {
                    const formData = new FormData();
                    formData.append('record_id', id);
                    formData.append('delete_type', 'soft');
                    formData.append('csrf_token', CSRF_TOKEN);

                    fetch(config.deleteApi, { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Notiflix.Notify.success(data.message || 'Record deleted.');
                                loadRecords();
                            } else {
                                Notiflix.Notify.failure(data.message || 'Delete failed.');
                            }
                        })
                        .catch(() => Notiflix.Notify.failure('Network error.'));
                }
            );
        });

        // Export dropdown toggle
        document.getElementById('exportBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('exportMenu').classList.toggle('ra9048-export-menu--open');
        });
        document.addEventListener('click', () => {
            document.getElementById('exportMenu').classList.remove('ra9048-export-menu--open');
        });

        // Export options
        document.querySelectorAll('.ra9048-export-option').forEach(opt => {
            opt.addEventListener('click', function(e) {
                e.preventDefault();
                const format = this.dataset.format;
                const search = document.getElementById('searchInput').value;
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;
                const params = new URLSearchParams({ type: currentTab, format: format, search: search, date_from: dateFrom, date_to: dateTo });
                window.location.href = 'export.php?' + params.toString();
                document.getElementById('exportMenu').classList.remove('ra9048-export-menu--open');
            });
        });

        // Initial load
        renderHeader();
        loadRecords();
    })();
    </script>

    <script>lucide.createIcons();</script>
    <?php include '../../includes/sidebar_scripts.php'; ?>
</body>
</html>
