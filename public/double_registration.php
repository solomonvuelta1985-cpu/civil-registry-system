<?php
/**
 * Double Registration Management Page
 * Lists all active/historical links, correction tracking, unlink actions
 * PSA Memorandum Circular No. 2019-23
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

// Any user with birth_view can see this page
if (!hasPermission('birth_view')) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

$is_admin = isAdmin();

// Filters
$filter_status = sanitize_input($_GET['status'] ?? 'active');
$filter_correction = sanitize_input($_GET['correction'] ?? '');
$filter_search = sanitize_input($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
$params = [];

if ($filter_status === 'active' || $filter_status === 'unlinked') {
    $where_clauses[] = "rl.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_correction) && in_array($filter_correction, ['none', 'pending', 'filed', 'completed'])) {
    $where_clauses[] = "rl.correction_status = :corr";
    $params[':corr'] = $filter_correction;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total
$count_sql = "SELECT COUNT(*) FROM record_links rl {$where_sql}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

// Fetch links with joined data
$sql = "SELECT rl.*,
            u_linked.full_name AS linked_by_name,
            u_unlinked.full_name AS unlinked_by_name
        FROM record_links rl
        LEFT JOIN users u_linked ON rl.linked_by = u_linked.id
        LEFT JOIN users u_unlinked ON rl.unlinked_by = u_unlinked.id
        {$where_sql}
        ORDER BY rl.linked_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Resolve registry numbers for each link
foreach ($links as &$link) {
    $table_map = ['birth' => 'certificate_of_live_birth', 'marriage' => 'certificate_of_marriage', 'death' => 'certificate_of_death'];

    $pt = $table_map[$link['primary_certificate_type']] ?? null;
    $dt = $table_map[$link['duplicate_certificate_type']] ?? null;

    $link['primary_registry_no'] = '';
    $link['primary_child_name'] = '';
    if ($pt) {
        $s = $pdo->prepare("SELECT registry_no, child_first_name, child_last_name FROM {$pt} WHERE id = ? LIMIT 1");
        $s->execute([$link['primary_certificate_id']]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $link['primary_registry_no'] = $r['registry_no'] ?? '';
            $link['primary_child_name'] = trim(($r['child_first_name'] ?? '') . ' ' . ($r['child_last_name'] ?? ''));
        }
    }

    $link['duplicate_registry_no'] = '';
    $link['duplicate_child_name'] = '';
    if ($dt) {
        $s = $pdo->prepare("SELECT registry_no, child_first_name, child_last_name FROM {$dt} WHERE id = ? LIMIT 1");
        $s->execute([$link['duplicate_certificate_id']]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $link['duplicate_registry_no'] = $r['registry_no'] ?? '';
            $link['duplicate_child_name'] = trim(($r['child_first_name'] ?? '') . ' ' . ($r['child_last_name'] ?? ''));
        }
    }
}
unset($link);

// Summary stats
$summary_sql = "SELECT
    COUNT(*) AS total_links,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_links,
    SUM(CASE WHEN needs_correction = 1 AND status = 'active' THEN 1 ELSE 0 END) AS needs_correction,
    SUM(CASE WHEN correction_status = 'filed' AND status = 'active' THEN 1 ELSE 0 END) AS correction_filed,
    SUM(CASE WHEN correction_status = 'completed' AND status = 'active' THEN 1 ELSE 0 END) AS correction_completed
FROM record_links";
$summary = $pdo->query($summary_sql)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrfTokenMeta(); ?>
    <title>Double Registration - Civil Registry</title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../assets/js/notiflix-config.js"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css?v=4">
    <link rel="stylesheet" href="../assets/css/double-reg-comparison-modal.css">
    <script src="<?= asset_url('pdfjs') ?>"></script>
    <script>
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= asset_url("pdfjs_worker") ?>';
        }
        window.APP_BASE = '<?= rtrim(BASE_URL, '/') ?>';
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #F1F5F9; color: #1E293B; font-size: 14px; }

        .content { margin-left: 260px; padding: 24px; min-height: 100vh; }
        @media (max-width: 768px) { .content { margin-left: 0; padding: 16px; } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; color: #0F172A; }
        .page-title svg { width: 24px; height: 24px; }

        /* Summary Cards */
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .summary-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; padding: 16px; }
        .summary-card-label { font-size: 12px; font-weight: 500; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 6px; }
        .summary-card-value { font-size: 28px; font-weight: 700; color: #0F172A; }
        .summary-card.green .summary-card-value { color: #16A34A; }
        .summary-card.red .summary-card-value { color: #DC2626; }
        .summary-card.amber .summary-card-value { color: #D97706; }
        .summary-card.blue .summary-card-value { color: #2563EB; }

        /* Filters */
        .filters-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-select { padding: 7px 12px; border: 1px solid #CBD5E1; border-radius: 4px; font-size: 13px; background: #FFFFFF; color: #1E293B; }

        /* Table */
        .table-container { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 10px 14px; background: #F8FAFC; border-bottom: 2px solid #E2E8F0; font-weight: 600; color: #475569; text-align: left; white-space: nowrap; font-size: 12px; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 10px 14px; border-bottom: 1px solid #F1F5F9; vertical-align: middle; }
        tr:hover { background: #F8FAFC; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #DCFCE7; color: #166534; }
        .badge-red { background: #FEE2E2; color: #991B1B; }
        .badge-amber { background: #FEF3C7; color: #92400E; }
        .badge-gray { background: #F1F5F9; color: #64748B; }

        .action-link { color: #2563EB; text-decoration: none; font-weight: 500; cursor: pointer; font-size: 12px; }
        .action-link:hover { text-decoration: underline; }
        .action-link.danger { color: #DC2626; }

        .btn { padding: 7px 16px; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background: #2563EB; color: #FFFFFF; }
        .btn-primary:hover { background: #1D4ED8; }

        /* Pagination */
        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 14px; border-top: 1px solid #E2E8F0; font-size: 13px; color: #64748B; }
        .pagination a { color: #2563EB; text-decoration: none; padding: 4px 10px; border: 1px solid #E2E8F0; border-radius: 3px; margin: 0 2px; }
        .pagination a:hover { background: #EFF6FF; }
        .pagination a.active { background: #2563EB; color: #FFFFFF; border-color: #2563EB; }

        .empty-state { text-align: center; padding: 60px 20px; color: #94A3B8; }
        .empty-state svg { width: 48px; height: 48px; margin-bottom: 12px; }

        /* Bulk Scan Panel */
        .btn-scan { background: #7C3AED; color: #FFFFFF; }
        .btn-scan:hover { background: #6D28D9; }
        .btn-cancel { background: #EF4444; color: #FFFFFF; }
        .btn-cancel:hover { background: #DC2626; }

        #bulkScanPanel { display: none; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; margin-bottom: 18px; overflow: hidden; }
        .bulk-scan-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0; }
        .bulk-scan-header h3 { font-size: 14px; font-weight: 600; color: #0F172A; display: flex; align-items: center; gap: 8px; }
        .bulk-scan-status { font-size: 13px; color: #64748B; padding: 12px 18px; }
        .bulk-scan-status strong { color: #0F172A; }

        .progress-bar-container { height: 6px; background: #E2E8F0; border-radius: 3px; margin: 0 18px 12px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: #7C3AED; border-radius: 3px; transition: width 0.3s ease; width: 0%; }

        #bulkScanResults { padding: 0; }
        #bulkScanResults table { margin: 0; }
        .bulk-scan-empty { text-align: center; padding: 30px 18px; color: #94A3B8; font-size: 13px; }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i data-lucide="link-2"></i>
                Double Registration
            </h1>
            <?php if ($is_admin): ?>
            <button class="btn btn-scan" onclick="startBulkScan()" id="btnStartScan">
                <i data-lucide="scan-search" style="width:16px;height:16px;"></i>
                Scan All Records
            </button>
            <?php endif; ?>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card blue">
                <div class="summary-card-label">Active Links</div>
                <div class="summary-card-value"><?= (int)$summary['active_links'] ?></div>
            </div>
            <div class="summary-card amber">
                <div class="summary-card-label">Needs Correction</div>
                <div class="summary-card-value"><?= (int)$summary['needs_correction'] ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-card-label">Correction Filed</div>
                <div class="summary-card-value"><?= (int)$summary['correction_filed'] ?></div>
            </div>
            <div class="summary-card green">
                <div class="summary-card-label">Completed</div>
                <div class="summary-card-value"><?= (int)$summary['correction_completed'] ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Links</option>
                    <option value="unlinked" <?= $filter_status === 'unlinked' ? 'selected' : '' ?>>Unlinked (History)</option>
                    <option value="" <?= $filter_status === '' ? 'selected' : '' ?>>All</option>
                </select>
                <select name="correction" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Correction Status</option>
                    <option value="none" <?= $filter_correction === 'none' ? 'selected' : '' ?>>No Correction</option>
                    <option value="pending" <?= $filter_correction === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="filed" <?= $filter_correction === 'filed' ? 'selected' : '' ?>>Filed</option>
                    <option value="completed" <?= $filter_correction === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </form>
        </div>

        <!-- Bulk Scan Panel (admin only, shown when scan starts) -->
        <?php if ($is_admin): ?>
        <div id="bulkScanPanel">
            <div class="bulk-scan-header">
                <h3><i data-lucide="scan-search" style="width:16px;height:16px;"></i> Bulk Duplicate Scan</h3>
                <button class="btn btn-cancel" onclick="cancelBulkScan()" id="btnCancelScan">Cancel</button>
            </div>
            <div class="bulk-scan-status" id="bulkScanStatus">Preparing scan...</div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="bulkProgressBar"></div>
            </div>
            <div id="bulkScanResults"></div>
        </div>
        <?php endif; ?>

        <!-- Table -->
        <div class="table-container">
            <?php if (empty($links)): ?>
                <div class="empty-state">
                    <i data-lucide="link-2-off"></i>
                    <p>No double registration links found.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>1st Registration</th>
                        <th>2nd Registration</th>
                        <th>Score</th>
                        <th>Discrepancies</th>
                        <th>Correction</th>
                        <th>Linked</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $lnk): ?>
                    <tr>
                        <td>
                            <a href="javascript:void(0)" class="action-link" onclick="recordPreviewModal.open(<?= (int)$lnk['primary_certificate_id'] ?>, '<?= htmlspecialchars($lnk['primary_certificate_type']) ?>')">
                                <?= htmlspecialchars($lnk['primary_registry_no'] ?: 'N/A') ?>
                            </a>
                            <br><span style="font-size:11px;color:#64748B;"><?= htmlspecialchars($lnk['primary_child_name']) ?></span>
                        </td>
                        <td>
                            <a href="javascript:void(0)" class="action-link" onclick="recordPreviewModal.open(<?= (int)$lnk['duplicate_certificate_id'] ?>, '<?= htmlspecialchars($lnk['duplicate_certificate_type']) ?>')">
                                <?= htmlspecialchars($lnk['duplicate_registry_no'] ?: 'N/A') ?>
                            </a>
                            <br><span style="font-size:11px;color:#64748B;"><?= htmlspecialchars($lnk['duplicate_child_name']) ?></span>
                        </td>
                        <td>
                            <?php if ($lnk['match_score']): ?>
                                <span class="badge <?= $lnk['match_score'] >= 80 ? 'badge-red' : ($lnk['match_score'] >= 50 ? 'badge-amber' : 'badge-gray') ?>">
                                    <?= number_format($lnk['match_score'], 1) ?>%
                                </span>
                            <?php else: ?>
                                <span class="badge badge-gray">Manual</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lnk['has_discrepancies']): ?>
                                <?php $disc = json_decode($lnk['discrepancies'] ?? '[]', true); ?>
                                <span class="badge badge-amber"><?= count($disc) ?> field(s)</span>
                            <?php else: ?>
                                <span class="badge badge-green">Clean</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $corr_badges = ['none' => 'badge-gray', 'pending' => 'badge-amber', 'filed' => 'badge-amber', 'completed' => 'badge-green'];
                            $corr = $lnk['correction_status'] ?? 'none';
                            ?>
                            <span class="badge <?= $corr_badges[$corr] ?? 'badge-gray' ?>"><?= ucfirst($corr) ?></span>
                        </td>
                        <td>
                            <span style="font-size:12px;"><?= htmlspecialchars(date('M d, Y', strtotime($lnk['linked_at']))) ?></span>
                            <br><span style="font-size:11px;color:#64748B;"><?= htmlspecialchars($lnk['linked_by_name'] ?? '') ?></span>
                        </td>
                        <td>
                            <?php if ($lnk['status'] === 'active'): ?>
                                <span class="badge badge-green">Active</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Unlinked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="javascript:void(0)" class="action-link" onclick="openComparison(<?= (int)$lnk['primary_certificate_id'] ?>, <?= (int)$lnk['duplicate_certificate_id'] ?>, '<?= htmlspecialchars($lnk['primary_certificate_type']) ?>')">Compare</a>
                            <?php if ($is_admin && $lnk['status'] === 'active'): ?>
                                <a href="javascript:void(0)" class="action-link danger" onclick="unlinkRecords(<?= (int)$lnk['id'] ?>)" style="margin-left:8px;">Unlink</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span>Showing <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?></span>
                <div>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= urlencode($filter_status) ?>&correction=<?= urlencode($filter_correction) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>

    <script src="../assets/js/record-preview-modal.js?v=4"></script>
    <script src="../assets/js/double-reg-comparison-modal.js?v=2"></script>

    <script>
        function openComparison(primaryId, duplicateId, certType) {
            const modal = new DoubleRegComparisonModal();
            modal.open(primaryId, duplicateId, certType);
        }

        // ── Bulk Scan ──────────────────────────────────────────
        let bulkScanController = null;
        let bulkScanRunning = false;

        async function startBulkScan() {
            if (bulkScanRunning) return;
            bulkScanRunning = true;
            bulkScanController = new AbortController();

            const panel = document.getElementById('bulkScanPanel');
            const statusEl = document.getElementById('bulkScanStatus');
            const progressBar = document.getElementById('bulkProgressBar');
            const resultsEl = document.getElementById('bulkScanResults');
            const startBtn = document.getElementById('btnStartScan');

            panel.style.display = 'block';
            statusEl.textContent = 'Preparing scan...';
            progressBar.style.width = '0%';
            resultsEl.innerHTML = '';
            startBtn.disabled = true;
            startBtn.style.opacity = '0.5';

            const base = window.APP_BASE || '';
            let offset = 0;
            const batchSize = 20;
            let totalRecords = 0;
            let totalScanned = 0;
            let totalFound = 0;
            let resultsTableCreated = false;

            try {
                while (true) {
                    const resp = await fetch(`${base}/api/duplicate_bulk_scan.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        signal: bulkScanController.signal,
                        body: JSON.stringify({ type: 'birth', offset: offset, batch_size: batchSize, min_score: 60 })
                    });

                    const data = await resp.json();
                    if (!data.success) {
                        statusEl.innerHTML = `<strong style="color:#DC2626;">Error:</strong> ${data.message || 'Scan failed'}`;
                        break;
                    }

                    totalRecords = data.total_records;
                    totalScanned += data.scanned;
                    const pct = totalRecords > 0 ? Math.min(100, Math.round((totalScanned / totalRecords) * 100)) : 0;
                    progressBar.style.width = pct + '%';

                    // Append results
                    if (data.results && data.results.length > 0) {
                        if (!resultsTableCreated) {
                            resultsEl.innerHTML = `<table>
                                <thead><tr>
                                    <th>Source Record</th>
                                    <th>Potential Duplicate</th>
                                    <th>Score</th>
                                    <th>Actions</th>
                                </tr></thead>
                                <tbody id="bulkResultsBody"></tbody>
                            </table>`;
                            resultsTableCreated = true;
                        }
                        const tbody = document.getElementById('bulkResultsBody');
                        data.results.forEach(r => {
                            r.matches.forEach(m => {
                                totalFound++;
                                const scoreClass = m.match_score >= 80 ? 'badge-red' : (m.match_score >= 50 ? 'badge-amber' : 'badge-gray');
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>
                                        <strong>${escHtml(r.source_registry_no || 'N/A')}</strong>
                                        <br><span style="font-size:11px;color:#64748B;">${escHtml(r.source_name)}</span>
                                    </td>
                                    <td>
                                        <strong>${escHtml(m.registry_no || 'N/A')}</strong>
                                        <br><span style="font-size:11px;color:#64748B;">${escHtml(m.child_name || '')}</span>
                                    </td>
                                    <td><span class="badge ${scoreClass}">${Number(m.match_score).toFixed(1)}%</span></td>
                                    <td><a href="javascript:void(0)" class="action-link" onclick="openComparison(${r.source_id}, ${m.id}, 'birth')">Compare</a></td>
                                `;
                                tbody.appendChild(row);
                            });
                        });
                    }

                    statusEl.innerHTML = `Scanned <strong>${totalScanned}</strong> of <strong>${totalRecords}</strong> records — <strong>${totalFound}</strong> potential duplicate(s) found`;

                    if (!data.has_more) {
                        // Done
                        progressBar.style.width = '100%';
                        if (totalFound === 0) {
                            resultsEl.innerHTML = '<div class="bulk-scan-empty">No potential duplicates found. All records look clean.</div>';
                        }
                        statusEl.innerHTML = `Scan complete. Scanned <strong>${totalScanned}</strong> records — <strong>${totalFound}</strong> potential duplicate(s) found.`;
                        break;
                    }

                    offset = data.next_offset;
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    statusEl.innerHTML = `Scan cancelled. Scanned <strong>${totalScanned}</strong> of <strong>${totalRecords}</strong> records — <strong>${totalFound}</strong> found so far.`;
                } else {
                    console.error('Bulk scan error:', err);
                    statusEl.innerHTML = '<strong style="color:#DC2626;">Network error during scan.</strong>';
                }
            } finally {
                bulkScanRunning = false;
                startBtn.disabled = false;
                startBtn.style.opacity = '1';
                document.getElementById('btnCancelScan').style.display = 'none';
            }
        }

        function cancelBulkScan() {
            if (bulkScanController) {
                bulkScanController.abort();
            }
        }

        function escHtml(str) {
            if (!str) return '';
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function unlinkRecords(linkId) {
            if (typeof Notiflix === 'undefined') return;

            Notiflix.Confirm.prompt(
                'Unlink Records',
                'Enter a reason for unlinking these records:',
                '',
                'Unlink',
                'Cancel',
                (reason) => {
                    if (!reason || !reason.trim()) {
                        Notiflix.Notify.failure('A reason is required');
                        return;
                    }
                    const base = window.APP_BASE || '';
                    fetch(`${base}/api/record_unlink.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ link_id: linkId, reason: reason.trim() })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Notiflix.Notify.success(data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            Notiflix.Notify.failure(data.message || 'Unlink failed');
                        }
                    })
                    .catch(() => Notiflix.Notify.failure('Network error'));
                },
                () => {},
                { width: '420px', borderRadius: '12px', okButtonBackground: '#DC2626' }
            );
        }
    </script>
</body>
</html>
