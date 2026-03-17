<?php
/**
 * PDF Backup Manager
 * iScan Civil Registry Records Management System
 *
 * Admin-only page to view, restore, and clean up PDF backups.
 * Backups are created automatically when a record's PDF is replaced via update.
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
$csrfField = csrfTokenField();

// Filters
$filter_type   = $_GET['cert_type'] ?? '';
$filter_status = $_GET['status']    ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

// Allowed types
$valid_types = ['birth', 'death', 'marriage', 'marriage_license'];

// Build query
$where = [];
$params = [];
if ($filter_type && in_array($filter_type, $valid_types)) {
    $where[]  = 'b.cert_type = :ctype';
    $params[':ctype'] = $filter_type;
}
if ($filter_status === 'restored') {
    $where[] = 'b.restored_at IS NOT NULL';
} elseif ($filter_status === 'pending') {
    $where[] = 'b.restored_at IS NULL';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Backup directory size
    $backup_dir  = UPLOAD_DIR . 'backup/';
    $backup_size = 0;
    if (is_dir($backup_dir)) {
        $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backup_dir));
        foreach ($rit as $f) { if ($f->isFile()) $backup_size += $f->getSize(); }
    }

    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pdf_backups b {$where_sql}");
    $countStmt->execute($params);
    $total_rows = (int)$countStmt->fetchColumn();
    $total_pages = max(1, ceil($total_rows / $per_page));

    // Fetch backups
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
    $stmt->execute(array_merge($params, [':limit' => $per_page, ':offset' => $offset]));
    $backups = $stmt->fetchAll();

    // Summary stats
    $statsStmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(restored_at IS NOT NULL) AS restored,
            SUM(restored_at IS NULL) AS pending
         FROM pdf_backups"
    );
    $bstats = $statsStmt->fetch();

} catch (PDOException $e) {
    error_log('pdf_backup_manager: ' . $e->getMessage());
    $backups = [];
    $total_rows = 0;
    $total_pages = 1;
    $backup_size = 0;
    $bstats = ['total' => 0, 'restored' => 0, 'pending' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Backup Manager - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">

    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'Inter','Segoe UI',Arial,sans-serif;background:#f4f6f9;color:#1a202c; }
        .main-content { margin-left:260px;padding:28px;min-height:100vh; }
        .page-header { margin-bottom:24px; }
        .page-header h1 { font-size:1.6rem;font-weight:700; }
        .page-header p { color:#718096;font-size:0.95rem;margin-top:4px; }

        .stats-row { display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap; }
        .stat-card { background:#fff;border-radius:12px;padding:18px 22px;box-shadow:0 1px 8px rgba(0,0,0,0.06);flex:1;min-width:130px; }
        .stat-card .label { font-size:0.75rem;color:#718096;text-transform:uppercase;letter-spacing:0.06em; }
        .stat-card .value { font-size:1.8rem;font-weight:700;color:#2d3748;margin-top:4px; }

        .card { background:#fff;border-radius:12px;box-shadow:0 1px 8px rgba(0,0,0,0.06);overflow:hidden;margin-bottom:24px; }
        .card-header { display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:10px; }
        .card-header h2 { font-size:1rem;font-weight:600;color:#2d3748; }
        .card-body { padding:22px; }

        .filters { display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
        .filters select, .filters input { padding:8px 12px;border:1px solid #cbd5e0;border-radius:8px;font-size:0.88rem;background:#fff; }

        table { width:100%;border-collapse:collapse; }
        thead th { background:#f7fafc;padding:11px 16px;text-align:left;font-size:0.75rem;font-weight:600;color:#4a5568;text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid #e2e8f0; }
        tbody td { padding:13px 16px;border-bottom:1px solid #f0f0f0;font-size:0.88rem;vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#f7fafc; }

        .badge { display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:0.75rem;font-weight:600; }
        .badge-restored { background:#c6f6d5;color:#22543d; }
        .badge-pending  { background:#ebf8ff;color:#2b6cb0; }
        .badge-birth    { background:#e9d8fd;color:#44337a; }
        .badge-death    { background:#fed7d7;color:#742a2a; }
        .badge-marriage { background:#fefcbf;color:#744210; }
        .badge-ml       { background:#e2e8f0;color:#4a5568; }

        .mono { font-family:monospace;font-size:0.75rem;color:#4a5568;background:#edf2f7;padding:3px 6px;border-radius:4px;display:inline-block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle; }

        .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:7px;border:none;font-size:0.85rem;font-weight:600;cursor:pointer;transition:background 0.2s; }
        .btn-danger  { background:#fff5f5;color:#c53030;border:1px solid #feb2b2; }
        .btn-danger:hover  { background:#fed7d7; }
        .btn-restore { background:#ebf8ff;color:#2b6cb0;border:1px solid #bee3f8; }
        .btn-restore:hover { background:#bee3f8; }
        .btn-sm { padding:5px 10px;font-size:0.8rem; }

        .cleanup-box { background:#fffbeb;border:1px solid #f6ad55;border-radius:10px;padding:18px 22px; }
        .cleanup-box h3 { font-size:0.95rem;font-weight:600;color:#744210;margin-bottom:10px; }
        .cleanup-row { display:flex;align-items:center;gap:12px;flex-wrap:wrap; }
        .cleanup-row input[type=number] { width:90px;padding:8px 10px;border:1px solid #cbd5e0;border-radius:8px;font-size:0.9rem; }
        .cleanup-row label { font-size:0.88rem;color:#4a5568; }

        .pagination { display:flex;gap:6px;justify-content:center;padding:20px; }
        .page-btn { padding:7px 13px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;font-size:0.85rem;cursor:pointer;text-decoration:none;color:#4a5568; }
        .page-btn.active { background:#3182ce;color:#fff;border-color:#3182ce; }
        .page-btn:hover:not(.active) { background:#f7fafc; }

        .empty-state { text-align:center;padding:60px;color:#a0aec0; }
        .empty-state p { margin-top:10px; }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">
    <div class="page-header">
        <h1><i data-lucide="archive-restore" style="display:inline;vertical-align:middle;margin-right:8px;"></i>PDF Backup Manager</h1>
        <p>View and restore PDF backups created when certificate records are updated.</p>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="label">Total Backups</div>
            <div class="value"><?= number_format((int)$bstats['total']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Pending</div>
            <div class="value"><?= number_format((int)$bstats['pending']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Restored</div>
            <div class="value"><?= number_format((int)$bstats['restored']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Backup Size</div>
            <div class="value" style="font-size:1.2rem;padding-top:6px;"><?= round($backup_size / 1024 / 1024, 1) ?> MB</div>
        </div>
    </div>

    <!-- Cleanup Box -->
    <div class="card">
        <div class="card-header"><h2>Cleanup Old Backups</h2></div>
        <div class="card-body">
            <div class="cleanup-box">
                <h3><i data-lucide="trash-2" style="display:inline;vertical-align:middle;margin-right:6px;width:16px;height:16px;"></i>Retention Policy</h3>
                <p style="font-size:0.85rem;color:#718096;margin-bottom:14px;">
                    Delete backup files older than the specified number of days. Backups that have been restored are kept permanently.
                </p>
                <div class="cleanup-row">
                    <label>Delete backups older than</label>
                    <input type="number" id="cleanupDays" value="90" min="7" max="365">
                    <label>days</label>
                    <button class="btn btn-danger" onclick="runCleanup()">
                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                        Clean Up
                    </button>
                    <span id="cleanupResult" style="font-size:0.85rem;color:#718096;"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Backups Table -->
    <div class="card">
        <div class="card-header">
            <h2>Backup Registry</h2>
            <form method="GET" class="filters">
                <select name="cert_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="birth"            <?= $filter_type === 'birth'            ? 'selected' : '' ?>>Birth</option>
                    <option value="death"            <?= $filter_type === 'death'            ? 'selected' : '' ?>>Death</option>
                    <option value="marriage"         <?= $filter_type === 'marriage'         ? 'selected' : '' ?>>Marriage</option>
                    <option value="marriage_license" <?= $filter_type === 'marriage_license' ? 'selected' : '' ?>>Marriage License</option>
                </select>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending"  <?= $filter_status === 'pending'  ? 'selected' : '' ?>>Pending</option>
                    <option value="restored" <?= $filter_status === 'restored' ? 'selected' : '' ?>>Restored</option>
                </select>
            </form>
        </div>

        <?php if (empty($backups)): ?>
        <div class="empty-state">
            <i data-lucide="archive" style="width:48px;height:48px;opacity:0.3;"></i>
            <p>No backups found<?= ($filter_type || $filter_status) ? ' for this filter' : ' yet' ?>.</p>
            <?php if (!$filter_type && !$filter_status): ?>
            <p style="margin-top:6px;font-size:0.82rem;">Backups are created automatically when you update a record with a new PDF.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Record ID</th>
                    <th>Original Filename</th>
                    <th>Backed Up</th>
                    <th>By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $i => $b): ?>
                <?php
                $type_badges = [
                    'birth' => 'badge-birth', 'death' => 'badge-death',
                    'marriage' => 'badge-marriage', 'marriage_license' => 'badge-ml'
                ];
                $type_labels = [
                    'birth' => 'Birth', 'death' => 'Death',
                    'marriage' => 'Marriage', 'marriage_license' => 'ML'
                ];
                ?>
                <tr>
                    <td style="color:#a0aec0;"><?= $offset + $i + 1 ?></td>
                    <td>
                        <span class="badge <?= $type_badges[$b['cert_type']] ?? '' ?>">
                            <?= $type_labels[$b['cert_type']] ?? htmlspecialchars($b['cert_type']) ?>
                        </span>
                    </td>
                    <td>#<?= (int)$b['record_id'] ?></td>
                    <td>
                        <span class="mono" title="<?= htmlspecialchars($b['original_path']) ?>">
                            <?= htmlspecialchars(basename($b['original_path'])) ?>
                        </span>
                    </td>
                    <td style="color:#718096;font-size:0.83rem;white-space:nowrap;">
                        <?= date('M d, Y g:i A', strtotime($b['backed_up_at'])) ?>
                    </td>
                    <td style="font-size:0.83rem;">
                        <?= htmlspecialchars($b['backed_up_by_name'] ?? 'System') ?>
                    </td>
                    <td>
                        <?php if ($b['restored_at']): ?>
                            <span class="badge badge-restored">✓ Restored</span>
                            <br><small style="color:#a0aec0;font-size:0.75rem;">
                                <?= date('M d, Y', strtotime($b['restored_at'])) ?>
                                <?= $b['restored_by_name'] ? 'by ' . htmlspecialchars($b['restored_by_name']) : '' ?>
                            </small>
                        <?php else: ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$b['restored_at']): ?>
                        <button class="btn btn-restore btn-sm"
                                onclick="restoreBackup(<?= (int)$b['id'] ?>, '<?= htmlspecialchars(addslashes(basename($b['original_path']))) ?>', this)">
                            <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                            Restore
                        </button>
                        <?php else: ?>
                        <span style="color:#a0aec0;font-size:0.8rem;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a href="?page=<?= $p ?>&cert_type=<?= urlencode($filter_type) ?>&status=<?= urlencode($filter_status) ?>"
                   class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/notiflix-config.js"></script>
<script>
    lucide.createIcons();
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function restoreBackup(backupId, filename, btn) {
        Notiflix.Confirm.show(
            'Restore Backup',
            'Restore "' + filename + '" as the current PDF for this record? The current file (if any) will be backed up first.',
            'Restore', 'Cancel',
            async () => {
                btn.disabled = true; btn.textContent = 'Restoring...';
                const fd = new FormData();
                fd.append('csrf_token', CSRF);
                fd.append('backup_id', backupId);
                try {
                    const res  = await fetch('../api/pdf_restore.php', { method:'POST', body:fd });
                    const data = await res.json();
                    if (data.success) {
                        Notiflix.Notify.success('PDF restored successfully.');
                        setTimeout(() => location.reload(), 1400);
                    } else {
                        Notiflix.Notify.failure(data.message);
                        btn.disabled = false; btn.innerHTML = '<i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i> Restore';
                        lucide.createIcons();
                    }
                } catch(e) {
                    Notiflix.Notify.failure('Network error');
                    btn.disabled = false;
                }
            }
        );
    }

    async function runCleanup() {
        const days = parseInt(document.getElementById('cleanupDays').value);
        if (days < 7 || days > 365) { Notiflix.Notify.warning('Days must be between 7 and 365'); return; }

        Notiflix.Confirm.show(
            'Clean Up Backups',
            'Delete all backup files older than ' + days + ' days that have NOT been restored? This cannot be undone.',
            'Delete', 'Cancel',
            async () => {
                const fd = new FormData();
                fd.append('csrf_token', CSRF);
                fd.append('older_than_days', days);
                try {
                    const res  = await fetch('../api/pdf_backup_cleanup.php', { method:'POST', body:fd });
                    const data = await res.json();
                    if (data.success) {
                        Notiflix.Notify.success(data.message);
                        document.getElementById('cleanupResult').textContent = data.message;
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        Notiflix.Notify.failure(data.message);
                    }
                } catch(e) { Notiflix.Notify.failure('Network error'); }
            },
            null, { okButtonBackground: '#e53e3e' }
        );
    }
</script>
</body>
</html>
