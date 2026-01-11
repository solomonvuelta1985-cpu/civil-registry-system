<?php
/**
 * Workflow Management Dashboard
 * View and manage certificates through their workflow lifecycle
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user (for testing, defaulting to admin)
$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_name = $_SESSION['full_name'] ?? 'Administrator';

// Get filter parameters
$filter_state = isset($_GET['state']) ? $_GET['state'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get workflow statistics
$stats = getWorkflowStatistics($pdo);

// Get records based on filters
$records = getWorkflowRecords($pdo, $filter_state, $filter_type);

/**
 * Helper Functions
 */
function getWorkflowStatistics($pdo) {
    $sql = "
        SELECT
            current_state,
            COUNT(*) as count
        FROM workflow_states
        GROUP BY current_state
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'draft' => 0,
        'pending_review' => 0,
        'verified' => 0,
        'approved' => 0,
        'rejected' => 0,
        'archived' => 0,
        'total' => 0
    ];

    foreach ($results as $row) {
        $stats[$row['current_state']] = (int)$row['count'];
        $stats['total'] += (int)$row['count'];
    }

    return $stats;
}

function getWorkflowRecords($pdo, $state, $type) {
    $sql = "
        SELECT
            ws.certificate_type,
            ws.certificate_id,
            ws.current_state,
            ws.data_quality_score,
            ws.verified_by,
            ws.verified_at,
            ws.approved_by,
            ws.approved_at,
            ws.rejected_by,
            ws.rejected_at,
            ws.rejection_reason,
            ws.updated_at,
            CASE
                WHEN ws.certificate_type = 'birth' THEN (
                    SELECT CONCAT(child_first_name, ' ', child_last_name)
                    FROM certificate_of_live_birth
                    WHERE id = ws.certificate_id
                )
                WHEN ws.certificate_type = 'marriage' THEN (
                    SELECT CONCAT(husband_first_name, ' ', husband_last_name, ' & ', wife_first_name, ' ', wife_last_name)
                    FROM certificate_of_marriage
                    WHERE id = ws.certificate_id
                )
            END as record_name,
            CASE
                WHEN ws.certificate_type = 'birth' THEN (
                    SELECT registry_no FROM certificate_of_live_birth WHERE id = ws.certificate_id
                )
                WHEN ws.certificate_type = 'marriage' THEN (
                    SELECT registry_no FROM certificate_of_marriage WHERE id = ws.certificate_id
                )
            END as registry_no,
            CASE
                WHEN ws.certificate_type = 'birth' THEN (
                    SELECT date_of_registration FROM certificate_of_live_birth WHERE id = ws.certificate_id
                )
                WHEN ws.certificate_type = 'marriage' THEN (
                    SELECT date_of_registration FROM certificate_of_marriage WHERE id = ws.certificate_id
                )
            END as date_of_registration
        FROM workflow_states ws
        WHERE 1=1
    ";

    $params = [];

    if ($state !== 'all') {
        $sql .= " AND ws.current_state = ?";
        $params[] = $state;
    }

    if ($type !== 'all') {
        $sql .= " AND ws.certificate_type = ?";
        $params[] = $type;
    }

    $sql .= " ORDER BY ws.updated_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Dashboard - iScan</title>

    <!-- Notiflix - Modern Notification Library -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css">
    <script src="https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js"></script>
    <script src="../assets/js/notiflix-config.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.draft { border-color: #6c757d; }
        .stat-card.pending { border-color: #ffc107; }
        .stat-card.verified { border-color: #0dcaf0; }
        .stat-card.approved { border-color: #198754; }
        .stat-card.rejected { border-color: #dc3545; }
        .stat-card.archived { border-color: #6c757d; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 500;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 150px;
        }

        .records-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge.draft { background: #e9ecef; color: #495057; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.verified { background: #cff4fc; color: #055160; }
        .badge.approved { background: #d1e7dd; color: #0a3622; }
        .badge.rejected { background: #f8d7da; color: #58151c; }
        .badge.archived { background: #e9ecef; color: #495057; }

        .badge.birth { background: #e7f3ff; color: #004085; }
        .badge.marriage { background: #fff0f6; color: #6a1b33; }
        .badge.death { background: #f0f0f0; color: #212529; }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-view { background: #0d6efd; color: white; }
        .btn-verify { background: #0dcaf0; color: white; }
        .btn-approve { background: #198754; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-reopen { background: #ffc107; color: #000; }

        .quality-score {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .score-bar {
            width: 60px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc3545, #ffc107, #198754);
            transition: width 0.3s;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 10px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 Workflow Dashboard</h1>
            <p>Manage certificate workflow and approvals</p>
        </header>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card draft">
                <div class="stat-number"><?= $stats['draft'] ?></div>
                <div class="stat-label">Draft</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?= $stats['pending_review'] ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card verified">
                <div class="stat-number"><?= $stats['verified'] ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-number"><?= $stats['approved'] ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-number"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat-card archived">
                <div class="stat-number"><?= $stats['archived'] ?></div>
                <div class="stat-label">Archived</div>
            </div>
        </div>

        <!-- Filters -->
        <form class="filters" method="GET" action="">
            <div class="filter-group">
                <label>Workflow State:</label>
                <select name="state" onchange="this.form.submit()">
                    <option value="all" <?= $filter_state === 'all' ? 'selected' : '' ?>>All States</option>
                    <option value="draft" <?= $filter_state === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="pending_review" <?= $filter_state === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="verified" <?= $filter_state === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="approved" <?= $filter_state === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filter_state === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="archived" <?= $filter_state === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Certificate Type:</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="birth" <?= $filter_type === 'birth' ? 'selected' : '' ?>>Birth</option>
                    <option value="marriage" <?= $filter_type === 'marriage' ? 'selected' : '' ?>>Marriage</option>
                    <option value="death" <?= $filter_type === 'death' ? 'selected' : '' ?>>Death</option>
                </select>
            </div>
        </form>

        <!-- Records Table -->
        <div class="records-table">
            <?php if (count($records) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Registry No</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>State</th>
                        <th>Quality</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($record['registry_no'] ?? 'N/A') ?></strong></td>
                        <td><?= htmlspecialchars($record['record_name'] ?? 'Unknown') ?></td>
                        <td><span class="badge <?= $record['certificate_type'] ?>"><?= ucfirst($record['certificate_type']) ?></span></td>
                        <td><span class="badge <?= str_replace('_', '-', $record['current_state']) ?>"><?= ucwords(str_replace('_', ' ', $record['current_state'])) ?></span></td>
                        <td>
                            <?php if ($record['data_quality_score']): ?>
                            <div class="quality-score">
                                <div class="score-bar">
                                    <div class="score-fill" style="width: <?= $record['data_quality_score'] ?>%"></div>
                                </div>
                                <span><?= number_format($record['data_quality_score'], 1) ?>%</span>
                            </div>
                            <?php else: ?>
                            <span style="color: #6c757d;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($record['date_of_registration'] ?? 'now')) ?></td>
                        <td>
                            <div class="actions">
                                <a href="<?= $record['certificate_type'] ?>_certificate.php?id=<?= $record['certificate_id'] ?>" class="btn btn-view" title="View">👁️</a>

                                <?php if ($record['current_state'] === 'pending_review'): ?>
                                    <button class="btn btn-verify" onclick="transition(<?= $record['certificate_id'] ?>, '<?= $record['certificate_type'] ?>', 'verify')" title="Verify">✓</button>
                                    <button class="btn btn-reject" onclick="rejectRecord(<?= $record['certificate_id'] ?>, '<?= $record['certificate_type'] ?>')" title="Reject">✗</button>
                                <?php endif; ?>

                                <?php if ($record['current_state'] === 'verified'): ?>
                                    <button class="btn btn-approve" onclick="transition(<?= $record['certificate_id'] ?>, '<?= $record['certificate_type'] ?>', 'approve')" title="Approve">✓✓</button>
                                    <button class="btn btn-reject" onclick="rejectRecord(<?= $record['certificate_id'] ?>, '<?= $record['certificate_type'] ?>')" title="Reject">✗</button>
                                <?php endif; ?>

                                <?php if ($record['current_state'] === 'rejected'): ?>
                                    <button class="btn btn-reopen" onclick="transition(<?= $record['certificate_id'] ?>, '<?= $record['certificate_type'] ?>', 'reopen')" title="Reopen">🔄</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                </svg>
                <h3>No Records Found</h3>
                <p>No certificates match the current filters</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function transition(certId, certType, transitionType) {
            Notiflix.Confirm.show(
                'Confirm Transition',
                `Are you sure you want to ${transitionType} this certificate?`,
                'Confirm',
                'Cancel',
                function() {
                    // Show loading
                    Notiflix.Loading.circle(`Processing ${transitionType}...`);

                    const formData = new FormData();
                    formData.append('certificate_id', certId);
                    formData.append('certificate_type', certType);
                    formData.append('transition_type', transitionType);

                    fetch('../api/workflow_transition.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Notiflix.Loading.remove();

                        if (data.success) {
                            Notiflix.Notify.success(data.message);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            Notiflix.Notify.failure('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        Notiflix.Loading.remove();
                        Notiflix.Notify.failure('Network error: ' + error);
                    });
                },
                function() {
                    // User cancelled
                }
            );
        }

        function rejectRecord(certId, certType) {
            // Use native prompt for input, then Notiflix for confirmation and notifications
            const reason = prompt('Please provide a reason for rejection:');

            if (!reason || reason.trim() === '') {
                Notiflix.Notify.warning('Rejection reason is required');
                return;
            }

            Notiflix.Confirm.show(
                'Confirm Rejection',
                'Are you sure you want to reject this certificate?',
                'Reject',
                'Cancel',
                function() {
                    // Show loading
                    Notiflix.Loading.circle('Processing rejection...');

                    const formData = new FormData();
                    formData.append('certificate_id', certId);
                    formData.append('certificate_type', certType);
                    formData.append('transition_type', 'reject');
                    formData.append('notes', reason);

                    fetch('../api/workflow_transition.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Notiflix.Loading.remove();

                        if (data.success) {
                            Notiflix.Notify.success(data.message);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            Notiflix.Notify.failure('Error: ' + data.error);
                        }
                    })
                    .catch(error => {
                        Notiflix.Loading.remove();
                        Notiflix.Notify.failure('Network error: ' + error);
                    });
                },
                function() {
                    // User cancelled
                },
                {
                    titleColor: '#EF4444',
                    okButtonBackground: '#EF4444',
                }
            );
        }
    </script>
</body>
</html>
