<?php
/**
 * Folder Browser — Tree View for Upload Folders
 * Browse records by certificate type → year → last name folder structure.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

setSecurityHeaders();
$csrfMeta = csrfTokenMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folder Browser - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>

    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css?v=4">

    <script src="<?= asset_url('pdfjs') ?>"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= asset_url("pdfjs_worker") ?>';
        }
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #F8FAFC;
            color: #1E293B;
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        :root {
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8FAFC;
            --bg-tertiary: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --border-light: #F1F5F9;
            --border-medium: #E2E8F0;
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --primary-light: #DBEAFE;
            --primary-lighter: #EFF6FF;
            --success: #059669;
            --success-light: #D1FAE5;
            --warning: #D97706;
            --warning-light: #FEF3C7;
            --danger: #DC2626;
            --danger-light: #FEE2E2;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.03);
            --shadow-md: 0 2px 8px 0 rgba(0,0,0,0.06);
            --radius-sm: 8px;
            --radius-md: 12px;
        }

        .content { padding: 24px 32px; max-width: 1700px; }

        /* Page Header */
        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-light);
        }
        .page-title {
            font-size: 24px; font-weight: 700; color: var(--text-primary);
            display: flex; align-items: center; gap: 12px;
        }
        .page-title [data-lucide] { color: var(--primary); width: 24px; height: 24px; }

        /* Breadcrumb */
        .breadcrumb-bar {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg-primary); border: 1px solid var(--border-medium);
            border-radius: var(--radius-sm); padding: 10px 16px;
            margin-bottom: 16px; flex-wrap: wrap;
        }
        .breadcrumb-item {
            font-size: 13px; font-weight: 500; color: var(--primary);
            cursor: pointer; text-decoration: none;
            padding: 2px 6px; border-radius: 4px;
        }
        .breadcrumb-item:hover { background: var(--primary-lighter); }
        .breadcrumb-item.active { color: var(--text-primary); font-weight: 600; cursor: default; }
        .breadcrumb-item.active:hover { background: none; }
        .breadcrumb-sep { color: var(--text-tertiary); font-size: 12px; }
        .breadcrumb-search {
            margin-left: auto;
            padding: 6px 12px; border: 1px solid var(--border-medium);
            border-radius: var(--radius-sm); font-size: 13px; width: 220px;
            outline: none; background: var(--bg-secondary);
        }
        .breadcrumb-search:focus { border-color: var(--primary); background: #fff; }

        /* Layout */
        .browser-layout {
            display: flex; gap: 16px; align-items: flex-start;
        }

        /* Tree Panel */
        .tree-panel {
            width: 280px; min-width: 280px; max-height: calc(100vh - 200px);
            background: var(--bg-primary); border: 1px solid var(--border-medium);
            border-radius: var(--radius-md); overflow-y: auto;
            box-shadow: var(--shadow-sm);
        }
        .tree-panel-header {
            padding: 14px 16px; border-bottom: 1px solid var(--border-medium);
            font-weight: 600; font-size: 13px; color: var(--text-secondary);
            text-transform: uppercase; letter-spacing: 0.05em;
            background: var(--bg-tertiary); position: sticky; top: 0; z-index: 2;
        }

        .tree-node { user-select: none; }
        .tree-node-row {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 12px; cursor: pointer; font-size: 13px;
            color: var(--text-primary); border-left: 3px solid transparent;
            transition: all 0.15s;
        }
        .tree-node-row:hover { background: var(--bg-tertiary); }
        .tree-node-row.selected {
            background: var(--primary-lighter); border-left-color: var(--primary);
            font-weight: 600; color: var(--primary);
        }
        .tree-node-row [data-lucide] { width: 16px; height: 16px; flex-shrink: 0; }
        .tree-node-row .chevron { color: var(--text-tertiary); transition: transform 0.2s; }
        .tree-node-row .chevron.open { transform: rotate(90deg); }
        .tree-node-row .folder-icon { color: var(--warning); }
        .tree-node-row .type-icon { color: var(--primary); }
        .tree-count {
            margin-left: auto; background: var(--bg-tertiary); color: var(--text-secondary);
            font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: 10px;
        }
        .tree-node-row.selected .tree-count {
            background: var(--primary-light); color: var(--primary);
        }
        .tree-children { display: none; }
        .tree-children.open { display: block; }
        .tree-level-1 .tree-node-row { padding-left: 28px; }
        .tree-level-2 .tree-node-row { padding-left: 48px; font-size: 12px; }

        /* Records Panel */
        .records-panel {
            flex: 1; min-width: 0;
            background: var(--bg-primary); border: 1px solid var(--border-medium);
            border-radius: var(--radius-md); overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .records-panel-header {
            padding: 14px 18px; border-bottom: 1px solid var(--border-medium);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--bg-tertiary);
        }
        .records-panel-title {
            font-weight: 600; font-size: 14px; color: var(--text-primary);
        }
        .records-count {
            font-size: 12px; color: var(--text-secondary); font-weight: 500;
        }

        /* Records Table */
        .records-table {
            width: 100%; border-collapse: collapse; table-layout: fixed;
        }
        .records-table thead { background: var(--bg-secondary); position: sticky; top: 0; z-index: 1; }
        .records-table th {
            padding: 10px 12px; text-align: left; font-size: 11px;
            font-weight: 700; color: var(--text-primary); text-transform: uppercase;
            letter-spacing: 0.06em; border-bottom: 2px solid var(--border-medium);
            white-space: nowrap;
        }
        .records-table td {
            padding: 9px 12px; font-size: 13px; color: var(--text-primary);
            border-bottom: 1px solid var(--border-light);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .records-table tbody tr:nth-child(even) { background: var(--bg-secondary); }
        .records-table tbody tr:hover { background: var(--primary-lighter); }

        .row-number { width: 40px; text-align: center; color: var(--text-tertiary); font-size: 12px; }
        .record-name-link {
            color: var(--primary); font-weight: 600; text-decoration: none;
            cursor: pointer;
        }
        .record-name-link:hover { text-decoration: underline; }

        .col-row-num { width: 40px; }
        .col-registry { width: 120px; }
        .col-name { width: auto; }
        .col-date { width: 110px; }
        .col-place { width: 140px; }
        .col-actions { width: 70px; text-align: center; }

        /* Status badges */
        .status-badge {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 2px 8px; border-radius: 999px; font-size: 10px;
            font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            vertical-align: middle; margin-left: 6px;
        }
        .status-badge.archived { background: #FEF3C7; color: #92400E; }

        /* Action dropdown */
        .action-dropdown { position: relative; display: inline-block; }
        .action-dropdown-btn {
            background: var(--primary); color: #fff; border: none;
            border-radius: var(--radius-sm); padding: 5px 10px;
            cursor: pointer; display: flex; align-items: center;
        }
        .action-dropdown-btn:hover { background: var(--primary-hover); }
        .action-dropdown-menu {
            display: none; position: absolute; right: 0; top: 100%;
            background: #fff; border: 1.5px solid var(--border-medium);
            border-radius: var(--radius-sm); min-width: 150px;
            box-shadow: var(--shadow-md); z-index: 100; padding: 4px 0;
        }
        .action-dropdown-menu.show { display: block; }
        .action-dropdown-item {
            display: flex; align-items: center; gap: 8px; width: 100%;
            padding: 8px 14px; border: none; background: none;
            font-size: 13px; cursor: pointer; color: var(--text-primary);
        }
        .action-dropdown-item:hover { background: var(--bg-tertiary); }
        .action-dropdown-item [data-lucide] { width: 15px; height: 15px; }
        .action-dropdown-item.view-action { color: var(--success); }
        .action-dropdown-item.edit-action { color: var(--primary); }
        .action-dropdown-item.delete-action { color: var(--danger); }

        /* Pagination */
        .pagination-bar {
            display: flex; align-items: center; justify-content: center;
            gap: 6px; padding: 14px 18px; border-top: 1px solid var(--border-medium);
        }
        .pagination-btn {
            width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-medium); border-radius: var(--radius-sm);
            background: #fff; cursor: pointer; font-size: 13px; font-weight: 600;
            color: var(--text-primary); text-decoration: none;
        }
        .pagination-btn:hover { background: var(--bg-tertiary); }
        .pagination-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .pagination-btn.disabled { opacity: 0.3; pointer-events: none; }
        .pagination-btn [data-lucide] { width: 16px; height: 16px; }
        .pagination-info { font-size: 12px; color: var(--text-tertiary); padding: 0 4px; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 60px 20px; color: var(--text-tertiary);
        }
        .empty-state [data-lucide] { width: 48px; height: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; margin-top: 6px; }

        /* Loading skeleton */
        .skeleton-row td { padding: 12px; }
        .skeleton-bar {
            height: 14px; background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%);
            background-size: 200% 100%; animation: shimmer 1.5s infinite;
            border-radius: 4px;
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        /* Responsive */
        @media (max-width: 1024px) {
            .browser-layout { flex-direction: column; }
            .tree-panel { width: 100%; min-width: auto; max-height: 300px; }
        }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">
            <i data-lucide="folder-tree"></i> Folder Browser
        </h1>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb-bar" id="breadcrumbBar">
        <span class="breadcrumb-item active">All Folders</span>
        <input type="text" class="breadcrumb-search" id="folderSearch" placeholder="Search within folder..." autocomplete="off">
    </div>

    <!-- Layout -->
    <div class="browser-layout">
        <!-- Tree Panel -->
        <div class="tree-panel">
            <div class="tree-panel-header">
                <i data-lucide="folders" style="width:14px;height:14px;display:inline;vertical-align:middle;margin-right:4px;"></i>
                Folders
            </div>
            <div id="folderTree">
                <div style="padding:20px;text-align:center;color:var(--text-tertiary);">Loading...</div>
            </div>
        </div>

        <!-- Records Panel -->
        <div class="records-panel">
            <div class="records-panel-header">
                <span class="records-panel-title" id="panelTitle">Select a folder</span>
                <span class="records-count" id="recordsCount"></span>
            </div>
            <div id="recordsContainer">
                <div class="empty-state">
                    <i data-lucide="folder-open"></i>
                    <p>Select a folder from the tree to view records.</p>
                </div>
            </div>
            <div class="pagination-bar" id="paginationBar" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
const BASE_API = '../api/folder_browse.php';

let currentState = { type: null, year: null, lastName: null, page: 1, search: '' };
let treeData = [];

// --- Init ---
document.addEventListener('DOMContentLoaded', async () => {
    lucide.createIcons();
    await loadTree();
    setupSearch();
});

// --- Tree ---
async function loadTree() {
    try {
        const resp = await fetch(`${BASE_API}?action=tree`);
        const data = await resp.json();
        if (!data.success) return;
        treeData = data.tree;
        renderTree(data.tree);
    } catch (e) {
        document.getElementById('folderTree').innerHTML =
            '<div style="padding:16px;color:var(--danger);">Failed to load folder tree.</div>';
    }
}

function renderTree(tree) {
    const container = document.getElementById('folderTree');
    let html = '';
    for (const typeNode of tree) {
        const typeIcon = getTypeIcon(typeNode.type);
        html += `<div class="tree-node tree-level-0">
            <div class="tree-node-row" data-type="${typeNode.type}" onclick="toggleTreeNode(this); selectFolder('${typeNode.type}', null, null)">
                <i data-lucide="chevron-right" class="chevron"></i>
                <i data-lucide="${typeIcon}" class="type-icon"></i>
                <span>${typeNode.label}</span>
                <span class="tree-count">${typeNode.count}</span>
            </div>
            <div class="tree-children">`;

        for (const yearNode of typeNode.children) {
            const yearVal = yearNode.year || '__no_year__';
            html += `<div class="tree-node tree-level-1">
                <div class="tree-node-row" data-type="${typeNode.type}" data-year="${yearVal}" onclick="toggleTreeNode(this); selectFolder('${typeNode.type}', '${yearVal}', null)">
                    <i data-lucide="chevron-right" class="chevron"></i>
                    <i data-lucide="folder" class="folder-icon"></i>
                    <span>${yearNode.label}</span>
                    <span class="tree-count">${yearNode.count}</span>
                </div>
                <div class="tree-children">`;

            for (const nameNode of yearNode.children) {
                html += `<div class="tree-node tree-level-2">
                    <div class="tree-node-row" data-type="${typeNode.type}" data-year="${yearVal}" data-lastname="${nameNode.name}" onclick="selectFolder('${typeNode.type}', '${yearVal}', '${nameNode.name}')">
                        <i data-lucide="folder" class="folder-icon"></i>
                        <span>${nameNode.name}</span>
                        <span class="tree-count">${nameNode.count}</span>
                    </div>
                </div>`;
            }
            html += `</div></div>`;
        }
        html += `</div></div>`;
    }
    container.innerHTML = html;
    lucide.createIcons();
}

function toggleTreeNode(row) {
    const chevron = row.querySelector('.chevron');
    const children = row.parentElement.querySelector('.tree-children');
    if (children) {
        children.classList.toggle('open');
        chevron?.classList.toggle('open');
    }
}

function getTypeIcon(type) {
    const icons = { birth: 'baby', death: 'user-x', marriage: 'heart', marriage_license: 'clipboard-list' };
    return icons[type] || 'file';
}

function getTypeLabel(type) {
    const labels = { birth: 'Birth', death: 'Death', marriage: 'Marriage', marriage_license: 'Marriage License' };
    return labels[type] || type;
}

// --- Folder Selection ---
function selectFolder(type, year, lastName) {
    if (year === '__no_year__' || year === 'null') year = null;
    currentState = { type, year, lastName, page: 1, search: document.getElementById('folderSearch').value.trim() };

    // Highlight in tree
    document.querySelectorAll('.tree-node-row.selected').forEach(el => el.classList.remove('selected'));
    let selector = `.tree-node-row[data-type="${type}"]`;
    if (lastName) selector += `[data-lastname="${lastName}"]`;
    else if (year) selector += `[data-year="${year}"]:not([data-lastname])`;
    else selector += ':not([data-year])';
    const row = document.querySelector(selector);
    if (row) row.classList.add('selected');

    updateBreadcrumb();
    loadRecords();
}

function updateBreadcrumb() {
    const bar = document.getElementById('breadcrumbBar');
    const search = bar.querySelector('.breadcrumb-search');
    let html = `<span class="breadcrumb-item" onclick="resetFolder()">All Folders</span>`;

    if (currentState.type) {
        html += `<span class="breadcrumb-sep"><i data-lucide="chevron-right" style="width:12px;height:12px;"></i></span>`;
        const isLast = !currentState.year && !currentState.lastName;
        html += `<span class="breadcrumb-item ${isLast ? 'active' : ''}" onclick="selectFolder('${currentState.type}', null, null)">${getTypeLabel(currentState.type)}</span>`;
    }
    if (currentState.year) {
        html += `<span class="breadcrumb-sep"><i data-lucide="chevron-right" style="width:12px;height:12px;"></i></span>`;
        const isLast = !currentState.lastName;
        html += `<span class="breadcrumb-item ${isLast ? 'active' : ''}" onclick="selectFolder('${currentState.type}', '${currentState.year}', null)">${currentState.year}</span>`;
    }
    if (currentState.lastName) {
        html += `<span class="breadcrumb-sep"><i data-lucide="chevron-right" style="width:12px;height:12px;"></i></span>`;
        html += `<span class="breadcrumb-item active">${currentState.lastName}</span>`;
    }

    bar.innerHTML = html;
    bar.appendChild(search);
    lucide.createIcons();
}

function resetFolder() {
    currentState = { type: null, year: null, lastName: null, page: 1, search: '' };
    document.getElementById('folderSearch').value = '';
    document.querySelectorAll('.tree-node-row.selected').forEach(el => el.classList.remove('selected'));
    updateBreadcrumb();
    document.getElementById('panelTitle').textContent = 'Select a folder';
    document.getElementById('recordsCount').textContent = '';
    document.getElementById('recordsContainer').innerHTML = `
        <div class="empty-state">
            <i data-lucide="folder-open"></i>
            <p>Select a folder from the tree to view records.</p>
        </div>`;
    document.getElementById('paginationBar').style.display = 'none';
    lucide.createIcons();
}

// --- Records ---
async function loadRecords() {
    if (!currentState.type) return;

    const container = document.getElementById('recordsContainer');
    container.innerHTML = buildSkeletonRows(5);
    document.getElementById('paginationBar').style.display = 'none';

    const params = new URLSearchParams({
        action: 'list',
        type: currentState.type,
        page: currentState.page,
        per_page: 25,
    });
    if (currentState.year) params.set('year', currentState.year);
    if (currentState.lastName) params.set('last_name', currentState.lastName);
    if (currentState.search) params.set('search', currentState.search);

    try {
        const resp = await fetch(`${BASE_API}?${params}`);
        const data = await resp.json();
        if (!data.success) {
            container.innerHTML = `<div class="empty-state"><p>${data.message || 'Error loading records.'}</p></div>`;
            return;
        }
        renderRecords(data);
    } catch (e) {
        container.innerHTML = '<div class="empty-state"><p>Failed to load records.</p></div>';
    }
}

function renderRecords(data) {
    const { records, pagination, type } = data;
    const container = document.getElementById('recordsContainer');
    const panelTitle = document.getElementById('panelTitle');
    const recordsCount = document.getElementById('recordsCount');

    let title = getTypeLabel(type) + ' Records';
    if (currentState.year) title += ` / ${currentState.year}`;
    if (currentState.lastName) title += ` / ${currentState.lastName}`;
    panelTitle.textContent = title;
    recordsCount.textContent = `${pagination.total_records} record${pagination.total_records !== 1 ? 's' : ''}`;

    if (records.length === 0) {
        container.innerHTML = `<div class="empty-state"><i data-lucide="inbox"></i><p>No records in this folder.</p></div>`;
        document.getElementById('paginationBar').style.display = 'none';
        lucide.createIcons();
        return;
    }

    const cols = getColumnsForType(type);
    let html = `<div style="overflow-x:auto;"><table class="records-table"><thead><tr>
        <th class="col-row-num">#</th>`;
    for (const col of cols) {
        html += `<th class="${col.cls || ''}">${col.label}</th>`;
    }
    html += `<th class="col-actions">Actions</th></tr></thead><tbody>`;

    records.forEach((rec, i) => {
        const rowNum = pagination.from + i;
        const isArchived = (rec.status || 'Active') === 'Archived';
        html += `<tr class="${isArchived ? 'row-archived' : ''}">`;
        html += `<td class="row-number">${rowNum}</td>`;

        for (const col of cols) {
            const val = col.render(rec);
            html += `<td>${val}</td>`;
        }

        html += `<td class="col-actions">
            <div class="action-dropdown">
                <button class="action-dropdown-btn" onclick="toggleActionDropdown(event, this)">
                    <i data-lucide="more-vertical" style="width:14px;height:14px;"></i>
                </button>
                <div class="action-dropdown-menu">
                    <button class="action-dropdown-item view-action" onclick="openPreview(${rec.id}, '${type}'); closeAllDropdowns();">
                        <i data-lucide="file-text"></i><span>View</span>
                    </button>
                </div>
            </div>
        </td>`;
        html += `</tr>`;
    });

    html += `</tbody></table></div>`;
    container.innerHTML = html;
    lucide.createIcons();

    renderPagination(pagination);
}

function getColumnsForType(type) {
    const esc = v => v ? String(v).replace(/</g, '&lt;') : '';
    const name = (rec, f, m, l) => {
        const full = [rec[f], rec[m], rec[l]].filter(Boolean).join(' ');
        return full || 'N/A';
    };
    const nameLink = (rec, id, type, f, m, l) => {
        const n = name(rec, f, m, l);
        return `<a href="javascript:void(0)" class="record-name-link" onclick="openPreview(${id}, '${type}')">${esc(n)}</a>`;
    };
    const fmtDate = v => {
        if (!v) return '';
        const d = new Date(v + 'T00:00:00');
        if (isNaN(d)) return esc(v);
        return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    switch (type) {
        case 'birth': return [
            { label: 'Registry No.', cls: 'col-registry', render: r => esc(r.registry_no || '') },
            { label: 'Child', cls: 'col-name', render: r => nameLink(r, r.id, type, 'child_first_name', 'child_middle_name', 'child_last_name') },
            { label: 'Sex', cls: '', render: r => esc(r.child_sex || '') },
            { label: 'Birth Date', cls: 'col-date', render: r => fmtDate(r.child_date_of_birth) },
            { label: 'Father', cls: '', render: r => esc(name(r, 'father_first_name', 'father_middle_name', 'father_last_name')) },
            { label: 'Mother', cls: '', render: r => esc(name(r, 'mother_first_name', 'mother_middle_name', 'mother_last_name')) },
        ];
        case 'death': return [
            { label: 'Registry No.', cls: 'col-registry', render: r => esc(r.registry_no || '') },
            { label: 'Deceased', cls: 'col-name', render: r => nameLink(r, r.id, type, 'deceased_first_name', 'deceased_middle_name', 'deceased_last_name') },
            { label: 'Sex', cls: '', render: r => esc(r.sex || '') },
            { label: 'Date of Death', cls: 'col-date', render: r => fmtDate(r.date_of_death) },
            { label: 'Age', cls: '', render: r => r.age ? `${esc(r.age)} ${esc(r.age_unit || 'years')}` : '' },
            { label: 'Place', cls: 'col-place', render: r => esc(r.place_of_death || '') },
        ];
        case 'marriage': return [
            { label: 'Registry No.', cls: 'col-registry', render: r => esc(r.registry_no || '') },
            { label: 'Husband', cls: 'col-name', render: r => nameLink(r, r.id, type, 'husband_first_name', 'husband_middle_name', 'husband_last_name') },
            { label: 'Wife', cls: '', render: r => esc(name(r, 'wife_first_name', 'wife_middle_name', 'wife_last_name')) },
            { label: 'Marriage Date', cls: 'col-date', render: r => fmtDate(r.date_of_marriage) },
            { label: 'Place', cls: 'col-place', render: r => esc(r.place_of_marriage || '') },
        ];
        case 'marriage_license': return [
            { label: 'Registry No.', cls: 'col-registry', render: r => esc(r.registry_no || '') },
            { label: 'Groom', cls: 'col-name', render: r => nameLink(r, r.id, type, 'groom_first_name', 'groom_middle_name', 'groom_last_name') },
            { label: 'Bride', cls: '', render: r => esc(name(r, 'bride_first_name', 'bride_middle_name', 'bride_last_name')) },
            { label: 'Application Date', cls: 'col-date', render: r => fmtDate(r.date_of_application) },
        ];
        default: return [];
    }
}

// --- Pagination ---
function renderPagination(p) {
    const bar = document.getElementById('paginationBar');
    if (p.total_pages <= 1) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';

    let html = '';
    html += pgBtn('<i data-lucide="chevrons-left"></i>', 1, p.current_page === 1);
    html += pgBtn('<i data-lucide="chevron-left"></i>', p.current_page - 1, p.current_page === 1);

    const start = Math.max(1, p.current_page - 2);
    const end = Math.min(p.total_pages, p.current_page + 2);

    if (start > 1) {
        html += pgBtn('1', 1, false, p.current_page === 1);
        if (start > 2) html += '<span class="pagination-info">...</span>';
    }
    for (let i = start; i <= end; i++) {
        html += pgBtn(String(i), i, false, i === p.current_page);
    }
    if (end < p.total_pages) {
        if (end < p.total_pages - 1) html += '<span class="pagination-info">...</span>';
        html += pgBtn(String(p.total_pages), p.total_pages, false, p.current_page === p.total_pages);
    }

    html += pgBtn('<i data-lucide="chevron-right"></i>', p.current_page + 1, p.current_page === p.total_pages);
    html += pgBtn('<i data-lucide="chevrons-right"></i>', p.total_pages, p.current_page === p.total_pages);

    html += `<span class="pagination-info" style="margin-left:8px;">${p.from}-${p.to} of ${p.total_records}</span>`;

    bar.innerHTML = html;
    lucide.createIcons();
}

function pgBtn(label, page, disabled, active) {
    const cls = ['pagination-btn'];
    if (disabled) cls.push('disabled');
    if (active) cls.push('active');
    return `<a href="javascript:void(0)" class="${cls.join(' ')}" onclick="goToPage(${page})">${label}</a>`;
}

function goToPage(page) {
    currentState.page = page;
    loadRecords();
}

// --- Search ---
function setupSearch() {
    let debounce;
    document.getElementById('folderSearch').addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(() => {
            currentState.search = this.value.trim();
            currentState.page = 1;
            if (currentState.type) loadRecords();
        }, 350);
    });
}

// --- Skeleton loading ---
function buildSkeletonRows(n) {
    let html = '<table class="records-table"><tbody>';
    for (let i = 0; i < n; i++) {
        html += '<tr class="skeleton-row">';
        for (let j = 0; j < 6; j++) {
            const w = 40 + Math.random() * 60;
            html += `<td><div class="skeleton-bar" style="width:${w}%"></div></td>`;
        }
        html += '</tr>';
    }
    return html + '</tbody></table>';
}

// --- Actions ---
function toggleActionDropdown(event, btn) {
    event.stopPropagation();
    closeAllDropdowns();
    const menu = btn.nextElementSibling;
    menu.classList.toggle('show');
    lucide.createIcons();
}

function closeAllDropdowns() {
    document.querySelectorAll('.action-dropdown-menu.show').forEach(m => m.classList.remove('show'));
}

document.addEventListener('click', closeAllDropdowns);

function openPreview(id, type) {
    if (typeof recordPreviewModal !== 'undefined') {
        recordPreviewModal.open(id, type);
    } else {
        const formPages = { birth: 'certificate_of_live_birth.php', death: 'certificate_of_death.php', marriage: 'certificate_of_marriage.php', marriage_license: 'application_for_marriage_license.php' };
        window.location.href = (formPages[type] || '#') + '?id=' + id;
    }
}
</script>

<script src="../assets/js/family_relations_render.js?v=1"></script>
<script src="../assets/js/record-preview-modal.js?v=5"></script>
<?php include '../includes/sidebar_scripts.php'; ?>
</body>
</html>
