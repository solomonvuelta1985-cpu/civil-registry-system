<?php
/**
 * PDF Inventory & Integrity
 * iScan Civil Registry Records Management System
 *
 * Admin-only page to understand current PDF coverage and scan for corrupt/missing PDFs.
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

// Count last-30-day integrity events for summary
try {
    $evtStmt = $pdo->query(
        "SELECT COUNT(*) FROM security_logs
         WHERE event_type = 'PDF_INTEGRITY_FAILURE'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $recentFailures = (int)($evtStmt->fetchColumn() ?? 0);
} catch (Exception $e) {
    $recentFailures = 0;
}

// Registry and current-PDF coverage counts (for clear inventory context)
try {
    $countStmt = $pdo->query(
        "SELECT
            COUNT(*) AS non_deleted_records,
            SUM(status = 'Active') AS active_records,
            SUM(status = 'Active' AND pdf_filename IS NOT NULL AND pdf_filename != '') AS active_pdfs,
            SUM(status = 'Active' AND (pdf_filename IS NULL OR pdf_filename = '')) AS active_without_pdf,
            SUM(pdf_filename IS NOT NULL AND pdf_filename != '') AS total_pdfs
         FROM (
            SELECT status, pdf_filename FROM certificate_of_live_birth WHERE status != 'Deleted'
            UNION ALL
            SELECT status, pdf_filename FROM certificate_of_death WHERE status != 'Deleted'
            UNION ALL
            SELECT status, pdf_filename FROM certificate_of_marriage WHERE status != 'Deleted'
            UNION ALL
            SELECT status, pdf_filename FROM application_for_marriage_license WHERE status != 'Deleted'
         ) inventory"
    );
    $coverage = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $activeRecords = (int)($coverage['active_records'] ?? 0);
    $activePdfs = (int)($coverage['active_pdfs'] ?? 0);
    $activeWithoutPdf = (int)($coverage['active_without_pdf'] ?? 0);
    $totalPdfs = (int)($coverage['total_pdfs'] ?? 0);
} catch (Exception $e) {
    $activeRecords = 0;
    $activePdfs = 0;
    $activeWithoutPdf = 0;
    $totalPdfs = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $csrfMeta ?>
    <title>PDF Inventory &amp; Integrity - iScan</title>
    <?= google_fonts_tag('Inter:wght@400;500;600;700;800') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
    <style>
        :root {
            /* Material Design 3 Colors */
            --md-primary: #6750A4;
            --md-on-primary: #FFFFFF;
            --md-primary-container: #EADDFF;
            --md-on-primary-container: #21005D;
            --md-surface: #FFFBFE;
            --md-on-surface: #1C1B1F;
            --md-surface-variant: #E7E0EC;
            --md-outline: #79747E;

            /* Semantic Colors */
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

            /* Elevation Shadows */
            --elevation-1: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
            --elevation-2: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06);
            --elevation-3: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: var(--md-on-surface);
            line-height: 1.6;
        }

        .content {
            padding: 32px 36px;
            max-width: 1600px;
        }

        /* Page Header / Hero */
        .integrity-hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #fff;
            padding: 32px 36px;
            border-radius: 16px;
            margin-bottom: 28px;
            box-shadow: var(--elevation-2);
            position: relative;
            overflow: hidden;
        }

        .integrity-hero::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(59,130,246,0.15) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .integrity-hero h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .integrity-hero p {
            font-size: 15px;
            opacity: 0.9;
            max-width: 700px;
            position: relative;
        }

        .coverage-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .coverage-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: var(--elevation-1);
            border-top: 4px solid #3b82f6;
        }

        .coverage-card.pdf { border-color: #22c55e; }
        .coverage-card.missing { border-color: #f59e0b; }
        .coverage-card .num { font-size: 32px; font-weight: 800; line-height: 1.1; color: #1e293b; }
        .coverage-card.pdf .num { color: #15803d; }
        .coverage-card.missing .num { color: #b45309; }
        .coverage-card .lbl { margin-top: 6px; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
        .coverage-card .sub { margin-top: 4px; font-size: 12px; color: #94a3b8; }

        /* Alert Banner */
        .alert-banner {
            background: var(--color-error-bg);
            border: 1px solid #fca5a5;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--elevation-1);
        }

        .alert-banner span {
            color: #991b1b;
            font-weight: 600;
            font-size: 14px;
        }

        .alert-banner a {
            margin-left: auto;
            color: #991b1b;
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }

        .alert-banner a:hover { text-decoration: underline; }

        /* Scan Controls */
        .scan-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .scan-controls .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: var(--elevation-1);
        }

        .btn-primary {
            background: var(--md-primary);
            color: var(--md-on-primary);
        }

        .btn-primary:hover {
            background: #5a3d99;
            box-shadow: var(--elevation-2);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--color-purple);
            color: white;
        }

        .btn-secondary:hover {
            background: #7c3aed;
            box-shadow: var(--elevation-2);
            transform: translateY(-1px);
        }

        .scan-controls span {
            color: #64748b;
            font-size: 14px;
        }

        .scan-controls strong {
            color: #1e293b;
        }

        /* Progress Indicator */
        .progress-wrap {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: var(--elevation-1);
        }

        .progress-wrap.visible { display: flex; }

        .spinner {
            width: 24px; height: 24px;
            border: 3px solid #bfdbfe;
            border-top-color: var(--color-info);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: white;
            border-radius: 14px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: var(--elevation-2);
            border-top: 4px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--elevation-3);
        }

        .summary-card.ok { border-color: var(--color-success); }
        .summary-card.corrupt { border-color: var(--color-error); }
        .summary-card.missing { border-color: var(--color-warning); }
        .summary-card.no-hash { border-color: var(--color-purple); }
        .summary-card.total { border-color: var(--color-info); }

        .summary-card .num {
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }

        .summary-card .lbl {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .summary-card.ok .num { color: var(--color-success); }
        .summary-card.corrupt .num { color: var(--color-error); }
        .summary-card.missing .num { color: var(--color-warning); }
        .summary-card.no-hash .num { color: var(--color-purple); }
        .summary-card.total .num { color: var(--color-info); }

        /* Card Container */
        .card {
            background: white;
            border-radius: 14px;
            box-shadow: var(--elevation-2);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #fafbfc;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .card-body {
            padding: 0;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar select,
        .filter-bar input {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: inherit;
            color: #374151;
            background: white;
            transition: border-color 0.2s;
        }

        .filter-bar select:focus,
        .filter-bar input:focus {
            outline: none;
            border-color: var(--md-primary);
        }

        .filter-bar input {
            min-width: 220px;
        }

        /* Results Table */
        .results-table-wrap {
            overflow-x: auto;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .results-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .results-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .results-table tr:hover td {
            background: #fafbfc;
        }

        .results-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 16px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
        }

        .results-pagination button {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            border-radius: 7px;
            padding: 6px 10px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .results-pagination button:hover:not(:disabled) { background: #f1f5f9; }
        .results-pagination button:disabled { opacity: .45; cursor: not-allowed; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-ok { background: var(--color-success-bg); color: #166534; }
        .badge-corrupt { background: var(--color-error-bg); color: #991b1b; }
        .badge-missing { background: var(--color-warning-bg); color: #92400e; }
        .badge-no-hash { background: var(--color-purple-bg); color: #4c1d95; }

        .cert-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            background: #e0f2fe;
            color: #0369a1;
        }

        .hash-mono {
            font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            color: #64748b;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Action Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover:not(.btn-disabled) {
            transform: translateY(-1px);
            box-shadow: var(--elevation-1);
        }

        .btn-restore {
            background: var(--color-info);
            color: white;
        }

        .btn-backfill {
            background: var(--color-purple);
            color: white;
        }

        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
            font-size: 14px;
        }

        #backfill-all-btn {
            display: none;
        }

        #backfill-all-btn.visible {
            display: inline-flex;
        }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">
    <!-- Hero -->
    <div class="integrity-hero">
        <h1><i data-lucide="file-check-2" style="width:28px;height:28px;vertical-align:middle;margin-right:.4rem;"></i>PDF Inventory &amp; Integrity</h1>
        <p>See how many active records have current PDFs, identify records without a PDF, and scan stored PDFs for corruption or hash mismatches.</p>
    </div>

    <div class="coverage-cards" aria-label="PDF coverage summary">
        <div class="coverage-card">
            <div class="num"><?= number_format($activeRecords) ?></div>
            <div class="lbl">Active Registry Records</div>
            <div class="sub">The dashboard total</div>
        </div>
        <div class="coverage-card pdf">
            <div class="num"><?= number_format($activePdfs) ?></div>
            <div class="lbl">Active Records With PDF</div>
            <div class="sub">Current PDF attachments</div>
        </div>
        <div class="coverage-card missing">
            <div class="num"><?= number_format($activeWithoutPdf) ?></div>
            <div class="lbl">Active Records Without PDF</div>
            <div class="sub">No current PDF attached</div>
        </div>
    </div>

    <!-- Alert: recent failures -->
    <?php if ($recentFailures > 0): ?>
    <div class="alert-banner">
        <i data-lucide="alert-triangle" style="color:#dc2626;flex-shrink:0;"></i>
        <span><?= $recentFailures ?> integrity failure<?= $recentFailures > 1 ? 's' : '' ?> detected in the last 30 days.</span>
        <a href="../admin/security_logs.php">View Security Logs &rarr;</a>
    </div>
    <?php endif; ?>

    <!-- Scan controls -->
    <div class="scan-controls">
        <button id="run-scan-btn" class="btn btn-primary">
            <i data-lucide="search" style="width:16px;height:16px;"></i> Run Full Integrity Scan
        </button>
        <button id="backfill-all-btn" class="btn btn-secondary">
            <i data-lucide="hash" style="width:16px;height:16px;"></i> Backfill All Missing Hashes
        </button>
        <span>Current PDFs available for scanning: <strong><?= number_format($totalPdfs) ?></strong></span>
    </div>

    <!-- Progress indicator -->
    <div class="progress-wrap" id="scan-progress">
        <div class="spinner"></div>
        <span id="scan-progress-text">Scanning PDF archive in small batches&hellip;</span>
    </div>

    <!-- Summary cards (hidden until scan runs) -->
    <div class="summary-cards" id="summary-cards" style="display:none;">
        <div class="summary-card total">
            <div class="num" id="sum-total">0</div>
            <div class="lbl">Total Scanned</div>
        </div>
        <div class="summary-card ok">
            <div class="num" id="sum-ok">0</div>
            <div class="lbl">OK</div>
        </div>
        <div class="summary-card corrupt">
            <div class="num" id="sum-corrupt">0</div>
            <div class="lbl">Corrupt</div>
        </div>
        <div class="summary-card missing">
            <div class="num" id="sum-missing">0</div>
            <div class="lbl">Missing</div>
        </div>
        <div class="summary-card no-hash">
            <div class="num" id="sum-no-hash">0</div>
            <div class="lbl">No Hash</div>
        </div>
    </div>

    <!-- Results panel (hidden until scan runs) -->
    <div id="results-panel" style="display:none;">
        <div class="card">
            <div class="card-header" style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;">
                <h3 style="margin:0;flex:1;">Scan Results</h3>
                <div class="filter-bar" style="margin:0;">
                    <select id="filter-status">
                        <option value="">All Statuses</option>
                        <option value="ok">OK</option>
                        <option value="corrupt">Corrupt</option>
                        <option value="missing">Missing</option>
                        <option value="no_hash">No Hash</option>
                    </select>
                    <select id="filter-type">
                        <option value="">All Types</option>
                        <option value="birth">Birth</option>
                        <option value="death">Death</option>
                        <option value="marriage">Marriage</option>
                        <option value="marriage_license">Marriage License</option>
                    </select>
                    <input type="text" id="filter-search" placeholder="Search record ID or filename&hellip;" style="min-width:200px;">
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="results-table-wrap">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Record ID</th>
                                <th>PDF Filename</th>
                                <th>Stored Hash</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="results-tbody">
                            <tr><td colspan="6" class="empty-state">Run a scan to see results.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="results-pagination" class="results-pagination" hidden></div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_url('lucide') ?>"></script>
<script>
lucide.createIcons();

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
let allResults = [];
let scanCounts = { total: 0, ok: 0, corrupt: 0, missing: 0, no_hash: 0 };
let resultPage = 1;
const SCAN_BATCH_SIZE = 100;
const RESULT_PAGE_SIZE = 100;

// ── Run Scan ──────────────────────────────────────────────────────────────────
document.getElementById('run-scan-btn').addEventListener('click', runScan);

async function runScan() {
    const btn      = document.getElementById('run-scan-btn');
    const progress = document.getElementById('scan-progress');
    const progressText = document.getElementById('scan-progress-text');
    btn.disabled   = true;
    progress.classList.add('visible');
    document.getElementById('summary-cards').style.display = 'none';
    document.getElementById('results-panel').style.display = 'none';
    allResults = [];
    scanCounts = { total: 0, ok: 0, corrupt: 0, missing: 0, no_hash: 0 };
    resultPage = 1;

    try {
        let offset = 0;
        let totalCandidates = 0;
        let hasMore = true;

        while (hasMore) {
            const fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('offset', String(offset));
            fd.append('batch_size', String(SCAN_BATCH_SIZE));

            const res  = await fetch('../api/pdf_integrity_scan.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'The server could not scan this batch.');
            }

            totalCandidates = Number(data.total_candidates) || totalCandidates;
            allResults.push(...(data.results || []));
            const batchCounts = data.counts || {};
            ['total', 'ok', 'corrupt', 'missing', 'no_hash'].forEach(key => {
                scanCounts[key] += Number(batchCounts[key]) || 0;
            });

            offset = Number(data.next_offset) || (offset + (Number(data.scanned) || 0));
            hasMore = data.has_more === true;
            const scanned = Math.min(offset, totalCandidates || offset);
            progressText.textContent = totalCandidates
                ? `Scanning PDF archive: ${scanned.toLocaleString()} of ${totalCandidates.toLocaleString()} checked…`
                : `Scanning PDF archive: ${scanned.toLocaleString()} checked…`;
        }

        displaySummary(scanCounts);
        renderTable(allResults);

        document.getElementById('summary-cards').style.display = '';
        document.getElementById('results-panel').style.display = '';

        // Show backfill button if there are no_hash entries
        const noHashCount = scanCounts.no_hash || 0;
        const backfillBtn = document.getElementById('backfill-all-btn');
        if (noHashCount > 0) {
            backfillBtn.style.display = 'inline-flex';
        } else {
            backfillBtn.style.display = 'none';
        }

    } catch (err) {
        alert('Integrity scan stopped: ' + err.message);
    } finally {
        btn.disabled = false;
        progress.classList.remove('visible');
        progressText.textContent = 'Scanning PDF archive in small batches…';
    }
}

function displaySummary(counts) {
    document.getElementById('sum-total').textContent   = counts.total   || 0;
    document.getElementById('sum-ok').textContent      = counts.ok      || 0;
    document.getElementById('sum-corrupt').textContent = counts.corrupt  || 0;
    document.getElementById('sum-missing').textContent = counts.missing  || 0;
    document.getElementById('sum-no-hash').textContent = counts.no_hash || 0;
}

// ── Render Table ──────────────────────────────────────────────────────────────
function renderTable(rows) {
    const tbody      = document.getElementById('results-tbody');
    const filterSt   = document.getElementById('filter-status').value;
    const filterType = document.getElementById('filter-type').value;
    const search     = document.getElementById('filter-search').value.trim().toLowerCase();

    const filtered = rows.filter(r => {
        if (filterSt   && r.status    !== filterSt)   return false;
        if (filterType && r.cert_type !== filterType)  return false;
        if (search) {
            const haystack = (r.record_id + ' ' + (r.pdf_filename || '')).toLowerCase();
            if (!haystack.includes(search)) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">No results match your filters.</div></td></tr>';
        renderPagination(0, 1);
        return;
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / RESULT_PAGE_SIZE));
    resultPage = Math.min(Math.max(1, resultPage), totalPages);
    const start = (resultPage - 1) * RESULT_PAGE_SIZE;
    const pageRows = filtered.slice(start, start + RESULT_PAGE_SIZE);

    tbody.innerHTML = pageRows.map(r => buildRow(r)).join('');
    renderPagination(filtered.length, totalPages);
    lucide.createIcons();
}

function renderPagination(totalRows, totalPages) {
    const pager = document.getElementById('results-pagination');
    if (!pager || totalRows <= RESULT_PAGE_SIZE) {
        if (pager) { pager.hidden = true; pager.innerHTML = ''; }
        return;
    }

    pager.hidden = false;
    pager.innerHTML = `
        <button type="button" onclick="changeResultPage(${resultPage - 1})" ${resultPage <= 1 ? 'disabled' : ''}>Previous</button>
        <span>Page ${resultPage.toLocaleString()} of ${totalPages.toLocaleString()} · ${totalRows.toLocaleString()} results</span>
        <button type="button" onclick="changeResultPage(${resultPage + 1})" ${resultPage >= totalPages ? 'disabled' : ''}>Next</button>`;
}

function changeResultPage(page) {
    resultPage = page;
    renderTable(allResults);
    document.getElementById('results-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[ch]));
}

function safeCertType(value) {
    const type = String(value ?? '');
    return ['birth', 'death', 'marriage', 'marriage_license'].includes(type) ? type : '';
}

function badgeHtml(status) {
    const map = {
        ok:      ['badge-ok',      'check-circle', 'OK'],
        corrupt: ['badge-corrupt', 'alert-octagon','Corrupt'],
        missing: ['badge-missing', 'file-x',       'Missing'],
        no_hash: ['badge-no-hash', 'hash',          'No Hash'],
    };
    const [cls, icon, label] = map[status] || ['', 'help-circle', 'Unknown'];
    return `<span class="badge ${cls}"><i data-lucide="${icon}" style="width:12px;height:12px;"></i>${label}</span>`;
}

function typeLabel(type) {
    const map = { birth: 'Birth', death: 'Death', marriage: 'Marriage', marriage_license: 'M. License' };
    return `<span class="cert-type-badge">${map[type] || 'Unknown'}</span>`;
}

function buildRow(r) {
    const certType = safeCertType(r.cert_type);
    const recordId = Number(r.record_id) || 0;
    const status = String(r.status || '');
    const storedHash = escapeHtml(r.stored_hash || '');
    const hashDisplay = storedHash
        ? `<span class="hash-mono" title="${storedHash}">${storedHash.substring(0,16)}...</span>`
        : '<span style="color:#94a3b8;font-style:italic;">-</span>';

    let actions = '';
    if (status === 'no_hash') {
        actions = `<button class="action-btn btn-backfill" onclick="backfillHash(${JSON.stringify(certType)}, ${recordId}, this)">
                    <i data-lucide="hash" style="width:13px;height:13px;"></i> Backfill Hash
                   </button>`;
    } else if (status === 'corrupt' || status === 'missing') {
        if (r.backup_id) {
            actions = `<button class="action-btn btn-restore" onclick="restoreBackup(${Number(r.backup_id) || 0}, ${recordId}, this)">
                        <i data-lucide="archive-restore" style="width:13px;height:13px;"></i> Restore Backup
                       </button>`;
        } else {
            actions = `<span class="action-btn btn-disabled"><i data-lucide="x" style="width:13px;height:13px;"></i> No Backup</span>`;
        }
    } else {
        actions = '<span style="color:#94a3b8;font-size:.8rem;">-</span>';
    }

    return `<tr data-status="${escapeHtml(status)}" data-type="${escapeHtml(certType)}">
        <td>${badgeHtml(status)}</td>
        <td>${typeLabel(certType)}</td>
        <td>#${recordId}</td>
        <td style="font-size:.82rem;color:#374151;">${r.pdf_filename ? escapeHtml(r.pdf_filename) : '<em>-</em>'}</td>
        <td>${hashDisplay}</td>
        <td>${actions}</td>
    </tr>`;
}

// ── Filters ───────────────────────────────────────────────────────────────────
['filter-status', 'filter-type'].forEach(id =>
    document.getElementById(id).addEventListener('change', () => { resultPage = 1; renderTable(allResults); })
);
document.getElementById('filter-search').addEventListener('input', () => { resultPage = 1; renderTable(allResults); });

// ── Backfill Single Hash ──────────────────────────────────────────────────────
async function backfillHash(certType, recordId, btn) {
    btn.disabled = true;
    btn.textContent = 'Working…';

    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('cert_type', certType);
    fd.append('record_id', recordId);

    try {
        const res  = await fetch('../api/pdf_hash_backfill.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            btn.closest('tr').querySelector('.badge').outerHTML =
                '<span class="badge badge-ok"><i data-lucide="check-circle" style="width:12px;height:12px;"></i>OK</span>';
            btn.closest('tr').querySelector('.hash-mono, [style*="font-style:italic"]').outerHTML =
                `<span class="hash-mono" title="${escapeHtml(data.hash || '')}">${escapeHtml((data.hash||'').substring(0,16))}...</span>`;
            btn.closest('td').innerHTML = '<span style="color:#22c55e;font-size:.8rem;">Hash saved</span>';
            // Update allResults
            const rec = allResults.find(r => r.cert_type === certType && r.record_id == recordId);
            if (rec) { rec.status = 'ok'; rec.stored_hash = data.hash; }
            displaySummary(recount());
            lucide.createIcons();
        } else {
            alert('Backfill failed: ' + (data.message || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Backfill Hash';
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Backfill Hash';
    }
}

// ── Backfill All ──────────────────────────────────────────────────────────────
document.getElementById('backfill-all-btn').addEventListener('click', async () => {
    const noHashRows = allResults.filter(r => r.status === 'no_hash');
    if (noHashRows.length === 0) { alert('No records need backfilling.'); return; }
    if (!confirm(`Backfill SHA-256 hashes for ${noHashRows.length} records? This may take a moment.`)) return;

    const btn = document.getElementById('backfill-all-btn');
    btn.disabled = true;
    btn.textContent = 'Backfilling…';

    let done = 0;
    for (const r of noHashRows) {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('cert_type', r.cert_type);
        fd.append('record_id', r.record_id);
        try {
            const res  = await fetch('../api/pdf_hash_backfill.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                r.status = 'ok';
                r.stored_hash = data.hash;
                done++;
            }
        } catch (_) {}
    }

    displaySummary(recount());
    renderTable(allResults);
    btn.style.display = 'none';
    btn.disabled = false;
    btn.textContent = 'Backfill All Missing Hashes';
    alert(`Done! ${done} of ${noHashRows.length} records backfilled.`);
});

// ── Restore Backup ────────────────────────────────────────────────────────────
async function restoreBackup(backupId, recordId, btn) {
    if (!confirm(`Restore backup #${backupId} for record #${recordId}?\n\nThe current file (if present) will itself be backed up first.`)) return;

    btn.disabled = true;
    btn.textContent = 'Restoring…';

    const fd = new FormData();
    fd.append('csrf_token', csrfToken);
    fd.append('backup_id', backupId);

    try {
        const res  = await fetch('../api/pdf_restore.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Update row status to ok
            const rec = allResults.find(r => r.record_id == recordId && r.backup_id == backupId);
            if (rec) {
                rec.status = 'ok';
                rec.stored_hash = data.new_hash || '';
                rec.backup_id = null;
            }
            displaySummary(recount());
            renderTable(allResults);
            lucide.createIcons();
        } else {
            alert('Restore failed: ' + (data.message || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = 'Restore Backup';
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        btn.disabled = false;
        btn.textContent = 'Restore Backup';
    }
}

// ── Helper: recount summary from allResults ───────────────────────────────────
function recount() {
    const c = { total: allResults.length, ok: 0, corrupt: 0, missing: 0, no_hash: 0 };
    for (const r of allResults) c[r.status] = (c[r.status] || 0) + 1;
    return c;
}
</script>
</body>
</html>
