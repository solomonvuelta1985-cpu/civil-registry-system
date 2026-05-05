<?php
/**
 * PDF Backup Manager — Backup Operations Console
 * iScan Civil Registry Records Management System
 *
 * Admin-only page to search, audit, restore, preview, export, deduplicate,
 * reconcile, and clean up PDF backups created when certificate records are updated.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

requireAuth();
requireAdmin();
setSecurityHeaders();

$csrfMeta  = csrfTokenMeta();

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_type   = $_GET['cert_type'] ?? '';
$filter_status = $_GET['status']    ?? '';
$filter_q      = trim($_GET['q'] ?? '');
$filter_from   = $_GET['from'] ?? '';
$filter_to     = $_GET['to']   ?? '';
$filter_user   = (int)($_GET['user_id'] ?? 0);
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$valid_types = ['birth', 'death', 'marriage', 'marriage_license'];

$where  = [];
$params = [];
if ($filter_type && in_array($filter_type, $valid_types, true)) {
    $where[] = 'b.cert_type = :ctype';
    $params[':ctype'] = $filter_type;
}
if ($filter_status === 'restored') {
    $where[] = 'b.restored_at IS NOT NULL';
} elseif ($filter_status === 'pending') {
    $where[] = 'b.restored_at IS NULL';
}
if ($filter_q !== '') {
    $where[] = '(b.original_path LIKE :q OR b.backup_path LIKE :q)';
    $params[':q'] = '%' . $filter_q . '%';
}
if ($filter_from !== '') {
    $where[] = 'b.backed_up_at >= :from_d';
    $params[':from_d'] = $filter_from . ' 00:00:00';
}
if ($filter_to !== '') {
    $where[] = 'b.backed_up_at <= :to_d';
    $params[':to_d'] = $filter_to . ' 23:59:59';
}
if ($filter_user > 0) {
    $where[] = 'b.backed_up_by = :uid';
    $params[':uid'] = $filter_user;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Backup directory size + file count
    $backup_dir   = UPLOAD_DIR . 'backup/';
    $backup_size  = 0;
    $backup_files_on_disk = 0;
    if (is_dir($backup_dir)) {
        $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rit as $f) { if ($f->isFile()) { $backup_size += $f->getSize(); $backup_files_on_disk++; } }
    }

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_backups b {$where_sql}");
    $countStmt->execute($params);
    $total_rows  = (int)$countStmt->fetchColumn();
    $total_pages = max(1, (int)ceil($total_rows / $per_page));

    // Page rows
    $stmt = $pdo->prepare(
        "SELECT b.*,
                u1.full_name AS backed_up_by_name,
                u2.full_name AS restored_by_name
           FROM pdf_backups b
      LEFT JOIN users u1 ON u1.id = b.backed_up_by
      LEFT JOIN users u2 ON u2.id = b.restored_by
         {$where_sql}
       ORDER BY b.backed_up_at DESC
          LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $backups = $stmt->fetchAll();

    // Stats — all-time
    $stats = $pdo->query(
        "SELECT
            COUNT(*)                                    AS total,
            SUM(restored_at IS NOT NULL)                AS restored,
            SUM(restored_at IS NULL)                    AS pending,
            SUM(verified_at IS NOT NULL)                AS verified,
            COALESCE(MAX(file_size), 0)                 AS largest_bytes,
            SUM(backed_up_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS last_7d,
            (SELECT MIN(backed_up_at) FROM pdf_backups WHERE restored_at IS NULL) AS oldest_pending
         FROM pdf_backups"
    )->fetch();

    // Distinct users for the user filter dropdown
    $users = $pdo->query(
        "SELECT DISTINCT u.id, u.full_name
           FROM pdf_backups b
           JOIN users u ON u.id = b.backed_up_by
          ORDER BY u.full_name"
    )->fetchAll();

} catch (PDOException $e) {
    error_log('pdf_backup_manager: ' . $e->getMessage());
    $backups = [];
    $total_rows = 0;
    $total_pages = 1;
    $backup_size = 0;
    $backup_files_on_disk = 0;
    $stats = ['total'=>0,'restored'=>0,'pending'=>0,'verified'=>0,'largest_bytes'=>0,'last_7d'=>0,'oldest_pending'=>null];
    $users = [];
}

$qs_base = http_build_query(array_filter([
    'cert_type' => $filter_type,
    'status'    => $filter_status,
    'q'         => $filter_q,
    'from'      => $filter_from,
    'to'        => $filter_to,
    'user_id'   => $filter_user ?: null,
], fn($v) => $v !== null && $v !== ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Backup Manager - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>

    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        :root {
            --md-primary: #6750A4;
            --md-on-primary: #FFFFFF;
            --color-success: #22c55e;
            --color-success-bg: #dcfce7;
            --color-error: #ef4444;
            --color-error-bg: #fee2e2;
            --color-warning: #f59e0b;
            --color-warning-bg: #fef3c7;
            --color-info: #3b82f6;
            --color-info-bg: #dbeafe;
            --color-purple: #8b5cf6;
            --color-purple-bg: #ede9fe;
            --elevation-1: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --elevation-2: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06);
            --elevation-3: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter','Segoe UI',sans-serif; background:#f8fafc; color:#1c1b1f; line-height:1.6; }
        .content { padding:32px 36px; max-width:1600px; }

        /* Hero */
        .hero {
            background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);
            color:#fff; padding:32px 36px; border-radius:16px;
            margin-bottom:24px; box-shadow:var(--elevation-2);
            position:relative; overflow:hidden;
        }
        .hero::before {
            content:''; position:absolute; top:0; right:0;
            width:300px; height:300px;
            background:radial-gradient(circle,rgba(139,92,246,0.18) 0%,transparent 70%);
            border-radius:50%; transform:translate(30%,-30%);
        }
        .hero h1 { font-size:28px; font-weight:800; margin-bottom:8px; display:flex; align-items:center; gap:12px; position:relative; }
        .hero p  { font-size:15px; opacity:.9; max-width:720px; position:relative; }

        /* Tabs */
        .tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid #e2e8f0; flex-wrap:wrap; }
        .tab {
            padding:12px 20px; cursor:pointer; font-size:14px; font-weight:600;
            color:#64748b; border-bottom:3px solid transparent; margin-bottom:-2px;
            display:inline-flex; align-items:center; gap:8px; transition:all .15s;
            background:none; border-left:none; border-right:none; border-top:none;
        }
        .tab:hover { color:#1e293b; }
        .tab.active { color:var(--md-primary); border-bottom-color:var(--md-primary); }

        .tab-panel { display:none; }
        .tab-panel.active { display:block; }

        /* Summary cards */
        .summary-cards {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:14px; margin-bottom:22px;
        }
        .summary-card {
            background:#fff; border-radius:12px; padding:18px 18px;
            box-shadow:var(--elevation-1); border-top:3px solid #e2e8f0;
            transition:transform .15s, box-shadow .15s;
        }
        .summary-card:hover { transform:translateY(-1px); box-shadow:var(--elevation-2); }
        .summary-card.total    { border-color:var(--color-info); }
        .summary-card.pending  { border-color:var(--color-warning); }
        .summary-card.restored { border-color:var(--color-success); }
        .summary-card.size     { border-color:var(--color-purple); }
        .summary-card.disk     { border-color:#0ea5e9; }
        .summary-card.recent   { border-color:#f43f5e; }
        .summary-card .lbl { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.5px; font-weight:600; }
        .summary-card .num { font-size:28px; font-weight:800; line-height:1.1; margin-top:6px; color:#1e293b; }
        .summary-card .sub { font-size:11px; color:#94a3b8; margin-top:4px; }

        /* Card */
        .card { background:#fff; border-radius:14px; box-shadow:var(--elevation-1); overflow:hidden; margin-bottom:20px; }
        .card-header {
            padding:18px 22px; border-bottom:1px solid #e2e8f0; background:#fafbfc;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
        }
        .card-header h3 { font-size:16px; font-weight:700; color:#1e293b; }
        .card-body { padding:20px 22px; }
        .card-body.flush { padding:0; }

        /* Filters bar */
        .filter-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:10px; align-items:end;
        }
        .filter-grid label { font-size:11px; color:#64748b; font-weight:600; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:.4px; }
        .filter-grid input, .filter-grid select {
            width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:8px;
            font-size:13px; font-family:inherit; background:#fff; color:#374151;
        }
        .filter-grid input:focus, .filter-grid select:focus { outline:none; border-color:var(--md-primary); }
        .filter-actions { display:flex; gap:8px; }

        /* Buttons */
        .btn {
            display:inline-flex; align-items:center; gap:6px;
            padding:8px 14px; border-radius:8px; border:none;
            font-size:13px; font-weight:600; cursor:pointer; transition:all .15s;
            font-family:inherit;
        }
        .btn-primary   { background:var(--md-primary); color:#fff; }
        .btn-primary:hover { background:#5a3d99; box-shadow:var(--elevation-1); }
        .btn-secondary { background:var(--color-purple); color:#fff; }
        .btn-secondary:hover { background:#7c3aed; box-shadow:var(--elevation-1); }
        .btn-info      { background:var(--color-info); color:#fff; }
        .btn-info:hover { background:#2563eb; }
        .btn-success   { background:var(--color-success); color:#fff; }
        .btn-success:hover { background:#16a34a; }
        .btn-warning   { background:var(--color-warning); color:#fff; }
        .btn-warning:hover { background:#d97706; }
        .btn-danger    { background:var(--color-error); color:#fff; }
        .btn-danger:hover { background:#dc2626; }
        .btn-outline   { background:#fff; color:#475569; border:1px solid #e2e8f0; }
        .btn-outline:hover { background:#f1f5f9; }
        .btn-sm        { padding:5px 10px; font-size:12px; }
        .btn:disabled  { opacity:.5; cursor:not-allowed; }

        /* Bulk toolbar */
        .bulk-toolbar {
            background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;
            padding:10px 16px; margin-bottom:14px;
            display:none; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .bulk-toolbar.visible { display:flex; }
        .bulk-toolbar .count { font-weight:600; color:#1e40af; font-size:14px; margin-right:auto; }

        /* Table */
        table { width:100%; border-collapse:collapse; font-size:13px; }
        thead th {
            background:#f8fafc; padding:11px 14px; text-align:left;
            font-size:11px; font-weight:700; color:#64748b;
            text-transform:uppercase; letter-spacing:.5px;
            border-bottom:2px solid #e2e8f0; white-space:nowrap;
        }
        tbody td { padding:13px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#fafbfc; }
        tbody tr.selected { background:#eff6ff; }

        .badge {
            display:inline-flex; align-items:center; gap:4px;
            padding:3px 9px; border-radius:999px; font-size:11px; font-weight:600; white-space:nowrap;
        }
        .badge-restored { background:var(--color-success-bg); color:#166534; }
        .badge-pending  { background:var(--color-info-bg); color:#1e40af; }
        .badge-verified { background:var(--color-purple-bg); color:#4c1d95; }
        .badge-birth    { background:#e9d8fd; color:#44337a; }
        .badge-death    { background:#fed7d7; color:#742a2a; }
        .badge-marriage { background:#fefcbf; color:#744210; }
        .badge-ml       { background:#e2e8f0; color:#4a5568; }
        .badge-corrupt  { background:var(--color-error-bg); color:#991b1b; }
        .badge-missing  { background:var(--color-warning-bg); color:#92400e; }
        .badge-ok       { background:var(--color-success-bg); color:#166534; }

        .mono {
            font-family:'SF Mono','Consolas',monospace; font-size:11px; color:#475569;
            background:#f1f5f9; padding:3px 6px; border-radius:4px; display:inline-block;
            max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
            vertical-align:middle;
        }

        .row-actions { display:flex; gap:5px; flex-wrap:wrap; }

        /* Pagination */
        .pagination { display:flex; gap:5px; justify-content:center; padding:18px; flex-wrap:wrap; }
        .page-btn {
            padding:6px 12px; border:1px solid #e2e8f0; border-radius:7px;
            background:#fff; font-size:13px; cursor:pointer; text-decoration:none; color:#475569;
        }
        .page-btn.active { background:var(--md-primary); color:#fff; border-color:var(--md-primary); }
        .page-btn:hover:not(.active) { background:#f1f5f9; }

        /* Empty */
        .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; font-size:14px; }

        /* Cleanup card */
        .cleanup-modes { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        @media (max-width:768px) { .cleanup-modes { grid-template-columns:1fr; } }
        .cleanup-mode {
            border:1px solid #e2e8f0; border-radius:10px; padding:18px;
            background:#fafbfc;
        }
        .cleanup-mode h4 { font-size:13px; font-weight:700; color:#1e293b; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
        .cleanup-mode p { font-size:12px; color:#64748b; margin-bottom:12px; }
        .cleanup-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .cleanup-row input[type=number] {
            width:80px; padding:7px 9px; border:1px solid #cbd5e0; border-radius:7px; font-size:13px;
        }

        /* Tab content cards */
        .tool-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
        .tool-card {
            background:#fff; border-radius:12px; padding:18px 20px;
            border-left:4px solid var(--color-info); box-shadow:var(--elevation-1);
        }
        .tool-card.danger  { border-left-color:var(--color-error); }
        .tool-card.warn    { border-left-color:var(--color-warning); }
        .tool-card.purple  { border-left-color:var(--color-purple); }
        .tool-card h4 { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:6px; }
        .tool-card p  { font-size:12px; color:#64748b; margin-bottom:12px; line-height:1.5; }

        /* Reconcile / dedupe results */
        .result-block { margin-top:16px; }
        .result-block h5 {
            font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
            color:#475569; margin-bottom:8px; display:flex; align-items:center; gap:6px;
        }
        .dedupe-group {
            border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px; overflow:hidden;
        }
        .dedupe-group-header {
            background:#f8fafc; padding:10px 14px; font-size:13px; font-weight:600; color:#1e293b;
            display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        }
        .dedupe-group-header .save-hint { color:var(--color-success); font-size:12px; font-weight:600; margin-left:auto; }
        .dedupe-member { padding:8px 14px; border-top:1px solid #f1f5f9; display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:12px; }
        .dedupe-member.keep { background:#f0fdf4; }
        .dedupe-member input[type=checkbox] { transform:scale(1.1); }

        /* Detail modal */
        .detail-grid { display:grid; grid-template-columns:140px 1fr; gap:8px 14px; font-size:13px; }
        .detail-grid dt { color:#64748b; font-weight:600; }
        .detail-grid dd { color:#1e293b; word-break:break-all; }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">
    <div class="hero">
        <h1><i data-lucide="archive-restore" style="width:28px;height:28px;"></i>PDF Backup Manager</h1>
        <p>Browse, search, preview, restore, deduplicate, and reconcile every PDF backup created when certificate records are updated. Restored backups are protected from cleanup.</p>
    </div>

    <!-- Summary cards -->
    <div class="summary-cards">
        <div class="summary-card total">
            <div class="lbl">Total Backups</div>
            <div class="num"><?= number_format((int)$stats['total']) ?></div>
            <div class="sub"><?= number_format((int)$stats['last_7d']) ?> in last 7 days</div>
        </div>
        <div class="summary-card pending">
            <div class="lbl">Pending</div>
            <div class="num"><?= number_format((int)$stats['pending']) ?></div>
            <div class="sub">
                <?php if (!empty($stats['oldest_pending'])): ?>
                Oldest: <?= date('M j, Y', strtotime($stats['oldest_pending'])) ?>
                <?php else: ?>—<?php endif; ?>
            </div>
        </div>
        <div class="summary-card restored">
            <div class="lbl">Restored</div>
            <div class="num"><?= number_format((int)$stats['restored']) ?></div>
            <div class="sub">Protected from cleanup</div>
        </div>
        <div class="summary-card size">
            <div class="lbl">Backup Size</div>
            <div class="num"><?= round($backup_size / 1024 / 1024, 1) ?> <span style="font-size:14px;">MB</span></div>
            <div class="sub">Largest: <?= round((int)$stats['largest_bytes'] / 1024 / 1024, 2) ?> MB</div>
        </div>
        <div class="summary-card disk">
            <div class="lbl">Files on Disk</div>
            <div class="num"><?= number_format($backup_files_on_disk) ?></div>
            <div class="sub">DB rows: <?= number_format((int)$stats['total']) ?></div>
        </div>
        <div class="summary-card recent">
            <div class="lbl">Verified</div>
            <div class="num"><?= number_format((int)$stats['verified']) ?></div>
            <div class="sub">Hash-checked</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" data-tab="registry"><i data-lucide="list" style="width:16px;height:16px;"></i> Registry</button>
        <button class="tab" data-tab="dedupe"><i data-lucide="copy-check" style="width:16px;height:16px;"></i> Near-Duplicates</button>
        <button class="tab" data-tab="reconcile"><i data-lucide="scan-search" style="width:16px;height:16px;"></i> Reconcile</button>
        <button class="tab" data-tab="cleanup"><i data-lucide="trash-2" style="width:16px;height:16px;"></i> Cleanup</button>
    </div>

    <!-- ── Registry Tab ─────────────────────────────────────────────────────── -->
    <div class="tab-panel active" id="tab-registry">

        <!-- Filters -->
        <div class="card">
            <div class="card-header"><h3><i data-lucide="filter" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i> Filters</h3></div>
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div>
                            <label>Search filename / path</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>" placeholder="e.g. cert_2026">
                        </div>
                        <div>
                            <label>Type</label>
                            <select name="cert_type">
                                <option value="">All Types</option>
                                <option value="birth"            <?= $filter_type === 'birth'            ? 'selected':'' ?>>Birth</option>
                                <option value="death"            <?= $filter_type === 'death'            ? 'selected':'' ?>>Death</option>
                                <option value="marriage"         <?= $filter_type === 'marriage'         ? 'selected':'' ?>>Marriage</option>
                                <option value="marriage_license" <?= $filter_type === 'marriage_license' ? 'selected':'' ?>>Marriage License</option>
                            </select>
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <option value="">All</option>
                                <option value="pending"  <?= $filter_status === 'pending'  ? 'selected':'' ?>>Pending</option>
                                <option value="restored" <?= $filter_status === 'restored' ? 'selected':'' ?>>Restored</option>
                            </select>
                        </div>
                        <div>
                            <label>Backed Up By</label>
                            <select name="user_id">
                                <option value="0">Anyone</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= $filter_user === (int)$u['id'] ? 'selected':'' ?>>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>From Date</label>
                            <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>">
                        </div>
                        <div>
                            <label>To Date</label>
                            <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>">
                        </div>
                        <div class="filter-actions" style="grid-column: span 2;">
                            <button type="submit" class="btn btn-primary"><i data-lucide="search" style="width:14px;height:14px;"></i> Apply</button>
                            <a href="pdf_backup_manager.php" class="btn btn-outline"><i data-lucide="x" style="width:14px;height:14px;"></i> Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk toolbar -->
        <div class="bulk-toolbar" id="bulkToolbar">
            <span class="count" id="bulkCount">0 selected</span>
            <button class="btn btn-info btn-sm" onclick="bulkVerify()"><i data-lucide="shield-check" style="width:13px;height:13px;"></i> Verify Hashes</button>
            <button class="btn btn-success btn-sm" onclick="bulkExport()"><i data-lucide="download" style="width:13px;height:13px;"></i> Export ZIP</button>
            <button class="btn btn-danger btn-sm" onclick="bulkDelete()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete Selected</button>
            <button class="btn btn-outline btn-sm" onclick="clearSelection()"><i data-lucide="x" style="width:13px;height:13px;"></i></button>
        </div>

        <!-- Registry table -->
        <div class="card">
            <div class="card-header">
                <h3>Backup Registry — <?= number_format($total_rows) ?> result<?= $total_rows === 1 ? '' : 's' ?></h3>
            </div>

            <?php if (empty($backups)): ?>
            <div class="empty-state">
                <i data-lucide="archive" style="width:48px;height:48px;opacity:.3;"></i>
                <p style="margin-top:10px;">No backups found<?= ($filter_type || $filter_status || $filter_q || $filter_from || $filter_to || $filter_user) ? ' for this filter' : ' yet' ?>.</p>
                <?php if (!$filter_type && !$filter_status && !$filter_q && !$filter_from && !$filter_to && !$filter_user): ?>
                <p style="margin-top:6px;font-size:12px;">Backups are created automatically when you update a record with a new PDF.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="selectAll"></th>
                        <th>Type</th>
                        <th>Record</th>
                        <th>Filename</th>
                        <th>Backed Up</th>
                        <th>By</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $type_badges = ['birth'=>'badge-birth','death'=>'badge-death','marriage'=>'badge-marriage','marriage_license'=>'badge-ml'];
                    $type_labels = ['birth'=>'Birth','death'=>'Death','marriage'=>'Marriage','marriage_license'=>'M. License'];
                ?>
                <?php foreach ($backups as $b): ?>
                <tr data-id="<?= (int)$b['id'] ?>" data-restored="<?= $b['restored_at'] ? '1' : '0' ?>">
                    <td>
                        <?php if (!$b['restored_at']): ?>
                            <input type="checkbox" class="row-check" value="<?= (int)$b['id'] ?>">
                        <?php else: ?>
                            <i data-lucide="lock" style="width:13px;height:13px;color:#94a3b8;" title="Restored backups are protected"></i>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $type_badges[$b['cert_type']] ?? '' ?>"><?= $type_labels[$b['cert_type']] ?? htmlspecialchars($b['cert_type']) ?></span></td>
                    <td>#<?= (int)$b['record_id'] ?></td>
                    <td><span class="mono" title="<?= htmlspecialchars($b['original_path']) ?>"><?= htmlspecialchars(basename($b['original_path'])) ?></span></td>
                    <td style="color:#64748b;font-size:12px;white-space:nowrap;"><?= date('M d, Y g:i A', strtotime($b['backed_up_at'])) ?></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($b['backed_up_by_name'] ?? 'System') ?></td>
                    <td style="font-size:12px;color:#64748b;">
                        <?= $b['file_size'] ? round($b['file_size']/1024, 1) . ' KB' : '—' ?>
                    </td>
                    <td>
                        <?php if ($b['restored_at']): ?>
                            <span class="badge badge-restored"><i data-lucide="check" style="width:11px;height:11px;"></i> Restored</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                            <?php if ($b['verified_at']): ?>
                            <span class="badge badge-verified" style="margin-left:3px;" title="Hash-verified <?= date('M j', strtotime($b['verified_at'])) ?>"><i data-lucide="shield-check" style="width:10px;height:10px;"></i></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions" style="justify-content:flex-end;">
                            <button class="btn btn-outline btn-sm" title="Preview" onclick="previewBackup(<?= (int)$b['id'] ?>)"><i data-lucide="eye" style="width:12px;height:12px;"></i></button>
                            <button class="btn btn-outline btn-sm" title="Details" onclick="showDetails(<?= (int)$b['id'] ?>)"><i data-lucide="info" style="width:12px;height:12px;"></i></button>
                            <button class="btn btn-outline btn-sm" title="Download" onclick="downloadBackup(<?= (int)$b['id'] ?>)"><i data-lucide="download" style="width:12px;height:12px;"></i></button>
                            <?php if (!$b['restored_at']): ?>
                            <button class="btn btn-info btn-sm" title="Restore"
                                    onclick="restoreBackup(<?= (int)$b['id'] ?>, '<?= htmlspecialchars(addslashes(basename($b['original_path']))) ?>')">
                                <i data-lucide="rotate-ccw" style="width:12px;height:12px;"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?page=<?= $p ?><?= $qs_base ? '&'.$qs_base : '' ?>"
                   class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Dedupe Tab ───────────────────────────────────────────────────────── -->
    <div class="tab-panel" id="tab-dedupe">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="copy-check" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i> Near-Duplicate Backup Finder</h3>
            </div>
            <div class="card-body">
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">
                    Computes a 64-bit similarity fingerprint for each backup file and groups files that are
                    near-identical (Hamming distance ≤ threshold) within the same record. Useful when a record's
                    PDF was updated several times and accumulated near-identical backups.
                </p>
                <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                    <div>
                        <label style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:4px;">SCOPE</label>
                        <select id="dedupeScope" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                            <option value="pending">Pending only (recommended)</option>
                            <option value="all">All backups</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:4px;">HAMMING THRESHOLD</label>
                        <input type="number" id="dedupeThreshold" value="4" min="0" max="16" style="width:80px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                    </div>
                    <div>
                        <label style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:4px;">&nbsp;</label>
                        <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#475569;padding:8px 0;">
                            <input type="checkbox" id="dedupeForce"> Force recompute fingerprints
                        </label>
                    </div>
                    <button class="btn btn-purple" style="background:var(--color-purple);color:#fff;" onclick="runDedupe()">
                        <i data-lucide="play" style="width:14px;height:14px;"></i> Scan
                    </button>
                </div>
                <div id="dedupeStatus" style="margin-top:14px;font-size:13px;color:#64748b;"></div>
                <div id="dedupeResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ── Reconcile Tab ────────────────────────────────────────────────────── -->
    <div class="tab-panel" id="tab-reconcile">
        <div class="card">
            <div class="card-header">
                <h3><i data-lucide="scan-search" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i> Reconcile DB &amp; Disk</h3>
            </div>
            <div class="card-body">
                <p style="color:#64748b;font-size:13px;margin-bottom:14px;">
                    Walks <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">uploads/backup/</code>
                    and cross-references with the <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">pdf_backups</code> table.
                    Reports DB rows whose backup file is missing AND files on disk that have no DB row (orphans).
                </p>
                <button class="btn btn-info" onclick="runReconcile()">
                    <i data-lucide="play" style="width:14px;height:14px;"></i> Run Reconcile
                </button>
                <div id="reconcileStatus" style="margin-top:14px;font-size:13px;color:#64748b;"></div>
                <div id="reconcileResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ── Cleanup Tab ──────────────────────────────────────────────────────── -->
    <div class="tab-panel" id="tab-cleanup">
        <div class="card">
            <div class="card-header"><h3><i data-lucide="trash-2" style="width:16px;height:16px;display:inline;vertical-align:middle;"></i> Cleanup &amp; Retention</h3></div>
            <div class="card-body">
                <div class="cleanup-modes">
                    <div class="cleanup-mode">
                        <h4><i data-lucide="calendar-clock" style="width:14px;height:14px;"></i> Retention by age</h4>
                        <p>Delete pending backups older than N days. Restored backups are kept permanently.</p>
                        <div class="cleanup-row">
                            <input type="number" id="cleanupDays" value="90" min="7" max="365">
                            <span style="font-size:13px;color:#475569;">days</span>
                            <button class="btn btn-warning btn-sm" onclick="runCleanup('age')"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Run</button>
                        </div>
                    </div>
                    <div class="cleanup-mode">
                        <h4><i data-lucide="layers" style="width:14px;height:14px;"></i> Retention per record</h4>
                        <p>For each record, keep only the latest N pending backups; prune the rest.</p>
                        <div class="cleanup-row">
                            <span style="font-size:13px;color:#475569;">keep latest</span>
                            <input type="number" id="cleanupKeepN" value="3" min="1" max="20">
                            <span style="font-size:13px;color:#475569;">per record</span>
                            <button class="btn btn-warning btn-sm" onclick="runCleanup('keep_n')"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Run</button>
                        </div>
                    </div>
                </div>
                <div id="cleanupResult" style="margin-top:14px;font-size:13px;color:#64748b;"></div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/notiflix-config.js"></script>
<script>
lucide.createIcons();
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ── Tabs ──────────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab').forEach(t => {
    t.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(x => x.classList.remove('active'));
        t.classList.add('active');
        document.getElementById('tab-' + t.dataset.tab).classList.add('active');
    });
});

// ── Selection ─────────────────────────────────────────────────────────────────
const selectAll = document.getElementById('selectAll');
function rowChecks() { return document.querySelectorAll('.row-check'); }
function selectedIds() { return Array.from(rowChecks()).filter(c => c.checked).map(c => parseInt(c.value, 10)); }

if (selectAll) {
    selectAll.addEventListener('change', () => {
        rowChecks().forEach(c => c.checked = selectAll.checked);
        updateBulkToolbar();
    });
}
rowChecks().forEach(c => c.addEventListener('change', () => {
    c.closest('tr').classList.toggle('selected', c.checked);
    updateBulkToolbar();
}));

function updateBulkToolbar() {
    const ids = selectedIds();
    const tb  = document.getElementById('bulkToolbar');
    document.getElementById('bulkCount').textContent = ids.length + ' selected';
    tb.classList.toggle('visible', ids.length > 0);
}
function clearSelection() {
    rowChecks().forEach(c => { c.checked = false; c.closest('tr').classList.remove('selected'); });
    if (selectAll) selectAll.checked = false;
    updateBulkToolbar();
}

// ── Row actions ───────────────────────────────────────────────────────────────
function previewBackup(id) {
    window.open('../api/pdf_backup_serve.php?id=' + id + '&disposition=inline', '_blank');
}
function downloadBackup(id) {
    window.location.href = '../api/pdf_backup_serve.php?id=' + id + '&disposition=attachment';
}

async function showDetails(id) {
    Notiflix.Loading.standard('Loading details…');
    try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('backup_id', id);
        const res  = await fetch('../api/pdf_backup_verify.php', { method:'POST', body:fd });
        const data = await res.json();

        const row = document.querySelector(`tr[data-id="${id}"]`);
        const cells = row ? row.querySelectorAll('td') : [];
        const filename = cells[3]?.querySelector('.mono')?.title || '';
        const backedUp = cells[4]?.textContent?.trim() || '';
        const by       = cells[5]?.textContent?.trim() || '';
        const size     = cells[6]?.textContent?.trim() || '';

        const valid = data.valid === true;
        const reason = data.reason || (data.valid ? 'OK' : 'Unknown');

        const html = `
            <dl class="detail-grid" style="text-align:left;">
                <dt>Backup ID</dt><dd>#${id}</dd>
                <dt>Original path</dt><dd style="font-family:monospace;font-size:12px;">${filename}</dd>
                <dt>Backed up</dt><dd>${backedUp}</dd>
                <dt>By</dt><dd>${by}</dd>
                <dt>File size</dt><dd>${size}</dd>
                <dt>Integrity</dt><dd>${valid
                    ? '<span class="badge badge-ok">✓ Valid</span>'
                    : '<span class="badge badge-corrupt">✗ ' + reason + '</span>'}</dd>
            </dl>`;
        Notiflix.Loading.remove();
        Notiflix.Report.info('Backup #' + id, html, 'Close', { plainText: false, messageMaxLength: 9999 });
    } catch (e) {
        Notiflix.Loading.remove();
        Notiflix.Notify.failure('Failed to load details');
    }
}

async function restoreBackup(id, filename) {
    Notiflix.Confirm.show(
        'Restore Backup',
        'Restore "' + filename + '" as the current PDF for this record? The current file (if any) will itself be backed up first.',
        'Restore', 'Cancel',
        async () => {
            Notiflix.Loading.standard('Restoring…');
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('backup_id', id);
            try {
                const res  = await fetch('../api/pdf_restore.php', { method:'POST', body:fd });
                const data = await res.json();
                Notiflix.Loading.remove();
                if (data.success) { Notiflix.Notify.success('PDF restored.'); setTimeout(() => location.reload(), 1200); }
                else Notiflix.Notify.failure(data.message || 'Restore failed');
            } catch (e) {
                Notiflix.Loading.remove();
                Notiflix.Notify.failure('Network error');
            }
        }
    );
}

// ── Bulk actions ──────────────────────────────────────────────────────────────
async function bulkDelete() {
    const ids = selectedIds();
    if (!ids.length) return;
    Notiflix.Confirm.show('Bulk Delete',
        `Delete ${ids.length} selected backup(s)? Restored backups in your selection will be skipped.`,
        'Delete', 'Cancel',
        async () => {
            Notiflix.Loading.standard('Deleting…');
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            ids.forEach(id => fd.append('ids[]', id));
            try {
                const res  = await fetch('../api/pdf_backup_bulk_delete.php', { method:'POST', body:fd });
                const data = await res.json();
                Notiflix.Loading.remove();
                if (data.success) { Notiflix.Notify.success(data.message); setTimeout(() => location.reload(), 1500); }
                else Notiflix.Notify.failure(data.message || 'Delete failed');
            } catch (e) { Notiflix.Loading.remove(); Notiflix.Notify.failure('Network error'); }
        },
        null, { okButtonBackground: '#ef4444' }
    );
}

async function bulkExport() {
    const ids = selectedIds();
    if (!ids.length) return;
    Notiflix.Notify.info('Building ZIP…');
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../api/pdf_backup_bulk_export.php';
    form.style.display = 'none';
    form.innerHTML = `<input type="hidden" name="csrf_token" value="${CSRF}">`;
    ids.forEach(id => form.innerHTML += `<input type="hidden" name="ids[]" value="${id}">`);
    document.body.appendChild(form);
    form.submit();
    setTimeout(() => form.remove(), 1000);
}

async function bulkVerify() {
    const ids = selectedIds();
    if (!ids.length) return;
    Notiflix.Loading.standard('Verifying ' + ids.length + ' backup(s)…');
    let ok = 0, bad = 0;
    for (const id of ids) {
        try {
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            fd.append('backup_id', id);
            const res  = await fetch('../api/pdf_backup_verify.php', { method:'POST', body:fd });
            const data = await res.json();
            if (data.valid) ok++; else bad++;
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                const status = row.querySelector('td:nth-child(8)');
                if (data.valid) {
                    if (!status.querySelector('.badge-verified')) {
                        status.insertAdjacentHTML('beforeend', ' <span class="badge badge-verified" title="Just verified"><i data-lucide="shield-check" style="width:10px;height:10px;"></i></span>');
                    }
                } else {
                    status.insertAdjacentHTML('beforeend', ` <span class="badge badge-corrupt" title="${(data.reason||'')}">✗</span>`);
                }
            }
        } catch (e) { bad++; }
    }
    Notiflix.Loading.remove();
    lucide.createIcons();
    Notiflix.Notify.success(`Verified ${ok} valid, ${bad} failed.`);
}

// ── Reconcile ─────────────────────────────────────────────────────────────────
async function runReconcile() {
    const status = document.getElementById('reconcileStatus');
    const out    = document.getElementById('reconcileResults');
    status.textContent = 'Scanning…';
    out.innerHTML = '';

    try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        const res  = await fetch('../api/pdf_backup_reconcile.php', { method:'POST', body:fd });
        const data = await res.json();
        if (!data.success) { status.textContent = 'Failed: ' + (data.message || 'Unknown'); return; }

        status.innerHTML = `<strong>${data.db_total}</strong> DB rows • <strong>${data.disk_total}</strong> files on disk • <strong style="color:#ef4444;">${data.missing.length}</strong> missing • <strong style="color:#f59e0b;">${data.orphans.length}</strong> orphan(s)`;

        let html = '';
        if (data.missing.length) {
            html += `<div class="result-block"><h5><i data-lucide="file-x" style="width:14px;height:14px;color:#ef4444;"></i> Missing files (DB row, no file on disk)</h5>`;
            html += `<table><thead><tr><th>ID</th><th>Type</th><th>Record</th><th>Backup Path</th><th>Backed Up</th><th>Status</th></tr></thead><tbody>`;
            data.missing.forEach(m => {
                html += `<tr><td>#${m.id}</td><td><span class="badge badge-${m.cert_type === 'marriage_license' ? 'ml' : m.cert_type}">${m.cert_type}</span></td><td>#${m.record_id}</td><td><span class="mono">${m.backup_path}</span></td><td style="font-size:12px;color:#64748b;">${m.backed_up_at}</td><td>${m.restored_at ? '<span class="badge badge-restored">Restored</span>' : '<span class="badge badge-pending">Pending</span>'}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }
        if (data.orphans.length) {
            html += `<div class="result-block"><h5><i data-lucide="file-question" style="width:14px;height:14px;color:#f59e0b;"></i> Orphan files (file on disk, no DB row)</h5>`;
            html += `<table><thead><tr><th>Path</th><th>Size</th><th>Modified</th></tr></thead><tbody>`;
            data.orphans.forEach(o => {
                html += `<tr><td><span class="mono">${o.backup_path}</span></td><td style="font-size:12px;color:#64748b;">${(o.size/1024).toFixed(1)} KB</td><td style="font-size:12px;color:#64748b;">${o.mtime}</td></tr>`;
            });
            html += `</tbody></table></div>`;
        }
        if (!data.missing.length && !data.orphans.length) {
            html = `<div class="empty-state"><i data-lucide="check-circle" style="width:40px;height:40px;color:#22c55e;"></i><p style="margin-top:8px;color:#166534;font-weight:600;">All in sync — no missing files or orphans.</p></div>`;
        }
        out.innerHTML = html;
        lucide.createIcons();

    } catch (e) { status.textContent = 'Network error: ' + e.message; }
}

// ── Dedupe ────────────────────────────────────────────────────────────────────
async function runDedupe() {
    const status = document.getElementById('dedupeStatus');
    const out    = document.getElementById('dedupeResults');
    status.textContent = 'Computing fingerprints — this may take a moment for large archives…';
    out.innerHTML = '';

    try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('threshold', document.getElementById('dedupeThreshold').value);
        fd.append('scope',     document.getElementById('dedupeScope').value);
        if (document.getElementById('dedupeForce').checked) fd.append('force', '1');
        const res  = await fetch('../api/pdf_backup_dedupe.php', { method:'POST', body:fd });
        const data = await res.json();
        if (!data.success) { status.textContent = 'Failed: ' + (data.message || 'Unknown'); return; }

        const totalReclaim = data.groups.reduce((s, g) => s + (g.total_size - (g.members[0]?.file_size || 0)), 0);
        status.innerHTML = `<strong>${data.groups.length}</strong> duplicate group(s) • <strong>${data.computed}</strong> fingerprints computed (<strong>${data.cached}</strong> cached, <strong>${data.missing}</strong> missing) • Reclaimable: <strong style="color:var(--color-success);">${(totalReclaim/1024/1024).toFixed(2)} MB</strong>`;

        if (!data.groups.length) {
            out.innerHTML = `<div class="empty-state"><i data-lucide="check-circle" style="width:40px;height:40px;color:#22c55e;"></i><p style="margin-top:8px;color:#166534;font-weight:600;">No near-duplicate backups found at threshold ${data.threshold}.</p></div>`;
            lucide.createIcons();
            return;
        }

        let html = `<div style="margin-bottom:10px;display:flex;gap:8px;align-items:center;">
            <button class="btn btn-danger btn-sm" onclick="dedupeBulkDelete()"><i data-lucide="trash-2" style="width:13px;height:13px;"></i> Delete All Selected</button>
            <span style="font-size:12px;color:#64748b;">By default the newest member of each group is kept.</span>
        </div>`;

        data.groups.forEach((g, gi) => {
            html += `<div class="dedupe-group"><div class="dedupe-group-header">
                <span class="badge badge-${g.cert_type === 'marriage_license' ? 'ml' : g.cert_type}">${g.cert_type}</span>
                <strong>Record #${g.record_id}</strong>
                <span style="color:#64748b;">${g.count} files, ${(g.total_size/1024/1024).toFixed(2)} MB total</span>
                <span class="save-hint">↓ Reclaim ${((g.total_size - (g.members[0]?.file_size || 0))/1024/1024).toFixed(2)} MB</span>
            </div>`;
            g.members.forEach((m, mi) => {
                const isKeep = mi === 0;
                html += `<div class="dedupe-member ${isKeep ? 'keep' : ''}">
                    <input type="checkbox" class="dedupe-check" data-id="${m.id}" ${isKeep ? '' : 'checked'}>
                    ${isKeep ? '<span class="badge badge-restored">Keep (newest)</span>' : '<span class="badge badge-pending">Delete</span>'}
                    <span class="mono">${m.original}</span>
                    <span style="color:#64748b;">${(m.file_size/1024).toFixed(1)} KB</span>
                    <span style="color:#64748b;font-size:11px;">${m.backed_up_at}</span>
                    <span style="color:#94a3b8;font-size:11px;font-family:monospace;">fp:${m.sim_hash}</span>
                    <button class="btn btn-outline btn-sm" onclick="previewBackup(${m.id})" style="margin-left:auto;"><i data-lucide="eye" style="width:11px;height:11px;"></i></button>
                </div>`;
            });
            html += `</div>`;
        });
        out.innerHTML = html;
        lucide.createIcons();

    } catch (e) { status.textContent = 'Network error: ' + e.message; }
}

async function dedupeBulkDelete() {
    const ids = Array.from(document.querySelectorAll('.dedupe-check:checked')).map(c => parseInt(c.dataset.id, 10));
    if (!ids.length) { Notiflix.Notify.warning('Nothing selected for deletion'); return; }
    Notiflix.Confirm.show('Delete duplicate backups',
        `Delete ${ids.length} duplicate backup(s)? Restored backups will be skipped.`,
        'Delete', 'Cancel',
        async () => {
            Notiflix.Loading.standard('Deleting…');
            const fd = new FormData();
            fd.append('csrf_token', CSRF);
            ids.forEach(id => fd.append('ids[]', id));
            try {
                const res  = await fetch('../api/pdf_backup_bulk_delete.php', { method:'POST', body:fd });
                const data = await res.json();
                Notiflix.Loading.remove();
                if (data.success) { Notiflix.Notify.success(data.message); setTimeout(() => runDedupe(), 800); }
                else Notiflix.Notify.failure(data.message || 'Delete failed');
            } catch (e) { Notiflix.Loading.remove(); Notiflix.Notify.failure('Network error'); }
        },
        null, { okButtonBackground: '#ef4444' }
    );
}

// ── Cleanup ───────────────────────────────────────────────────────────────────
async function runCleanup(mode) {
    let confirmMsg;
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('mode', mode);
    if (mode === 'age') {
        const days = parseInt(document.getElementById('cleanupDays').value, 10);
        if (isNaN(days) || days < 7 || days > 365) { Notiflix.Notify.warning('Days must be between 7 and 365'); return; }
        fd.append('older_than_days', days);
        confirmMsg = `Delete pending backups older than ${days} days?`;
    } else {
        const keep = parseInt(document.getElementById('cleanupKeepN').value, 10);
        if (isNaN(keep) || keep < 1 || keep > 20) { Notiflix.Notify.warning('Keep N must be between 1 and 20'); return; }
        fd.append('keep_per_record', keep);
        confirmMsg = `For each record, keep the latest ${keep} pending backup(s) and delete the rest?`;
    }

    Notiflix.Confirm.show('Run cleanup', confirmMsg, 'Run', 'Cancel',
        async () => {
            Notiflix.Loading.standard('Cleaning up…');
            try {
                const res  = await fetch('../api/pdf_backup_cleanup.php', { method:'POST', body:fd });
                const data = await res.json();
                Notiflix.Loading.remove();
                document.getElementById('cleanupResult').textContent = data.message || '';
                if (data.success) { Notiflix.Notify.success(data.message); setTimeout(() => location.reload(), 1500); }
                else Notiflix.Notify.failure(data.message || 'Cleanup failed');
            } catch (e) { Notiflix.Loading.remove(); Notiflix.Notify.failure('Network error'); }
        },
        null, { okButtonBackground: '#f59e0b' }
    );
}
</script>
</body>
</html>
