<?php
/**
 * Analytics Dashboard
 * System-wide statistics and performance metrics
 */

require_once '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch statistics
$stats = [
    'total_certificates' => getTotalCertificates($pdo),
    'this_month' => getThisMonthCount($pdo),
    'workflow_stats' => getWorkflowStats($pdo),
    'user_performance' => getTopPerformers($pdo),
    'monthly_trends' => getMonthlyTrends($pdo),
    'quality_metrics' => getQualityMetrics($pdo)
];

function getTotalCertificates($pdo) {
    $birth = $pdo->query("SELECT COUNT(*) FROM certificate_of_live_birth WHERE status = 'Active'")->fetchColumn();
    $marriage = $pdo->query("SELECT COUNT(*) FROM certificate_of_marriage WHERE status = 'Active'")->fetchColumn();
    return ['birth' => $birth, 'marriage' => $marriage, 'total' => $birth + $marriage];
}

function getThisMonthCount($pdo) {
    $birth = $pdo->query("SELECT COUNT(*) FROM certificate_of_live_birth WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
    $marriage = $pdo->query("SELECT COUNT(*) FROM certificate_of_marriage WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
    return $birth + $marriage;
}

function getWorkflowStats($pdo) {
    $stmt = $pdo->query("SELECT current_state, COUNT(*) as count FROM workflow_states GROUP BY current_state");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats = array_fill_keys(['draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived'], 0);
    foreach ($results as $row) {
        $stats[$row['current_state']] = (int)$row['count'];
    }
    return $stats;
}

function getTopPerformers($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT
                u.full_name,
                COUNT(*) as records_created,
                NULL as avg_quality
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE al.action = 'CREATE'
              AND al.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY u.id, u.full_name
            ORDER BY records_created DESC
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Return empty if table doesn't exist yet
        return [];
    }
}

function getMonthlyTrends($pdo) {
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM (
            SELECT created_at FROM certificate_of_live_birth
            UNION ALL
            SELECT created_at FROM certificate_of_marriage
        ) all_certs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getQualityMetrics($pdo) {
    try {
        $total_with_quality = $pdo->query("SELECT COUNT(*) FROM workflow_states WHERE data_quality_score IS NOT NULL")->fetchColumn();
        $avg_quality = $pdo->query("SELECT AVG(data_quality_score) FROM workflow_states WHERE data_quality_score IS NOT NULL")->fetchColumn();
        $high_quality = $pdo->query("SELECT COUNT(*) FROM workflow_states WHERE data_quality_score >= 90")->fetchColumn();

        return [
            'total' => $total_with_quality,
            'average' => round($avg_quality ?? 0, 2),
            'high_quality' => $high_quality,
            'percentage' => $total_with_quality > 0 ? round(($high_quality / $total_with_quality) * 100, 1) : 0
        ];
    } catch (PDOException $e) {
        // Return defaults if table doesn't exist yet
        return [
            'total' => 0,
            'average' => 0,
            'high_quality' => 0,
            'percentage' => 0
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - iScan</title>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            max-width: 1600px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .stat-sublabel {
            color: #adb5bd;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 20px;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Analytics Dashboard</h1>
            <p>System-wide statistics and performance metrics</p>
        </header>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_certificates']['total']) ?></div>
                <div class="stat-label">Total Records</div>
                <div class="stat-sublabel">
                    <?= number_format($stats['total_certificates']['birth']) ?> Birth |
                    <?= number_format($stats['total_certificates']['marriage']) ?> Marriage
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['this_month']) ?></div>
                <div class="stat-label">This Month</div>
                <div class="stat-sublabel">New certificates created</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= $stats['quality_metrics']['average'] ?>%</div>
                <div class="stat-label">Avg Quality Score</div>
                <div class="stat-sublabel"><?= $stats['quality_metrics']['percentage'] ?>% above 90%</div>
            </div>

            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['workflow_stats']['approved']) ?></div>
                <div class="stat-label">Approved Records</div>
                <div class="stat-sublabel"><?= number_format($stats['workflow_stats']['pending_review']) ?> pending review</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">📈 Monthly Trend (Last 12 Months)</div>
                <canvas id="monthly-chart"></canvas>
            </div>

            <div class="chart-card">
                <div class="chart-title">🔄 Workflow Status Distribution</div>
                <canvas id="workflow-chart"></canvas>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>👤 Top Performers (Last 30 Days)</th>
                        <th>Records Created</th>
                        <th>Avg Quality Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['user_performance'] as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                        <td><?= number_format($user['records_created']) ?></td>
                        <td><?= $user['avg_quality'] ? number_format($user['avg_quality'], 1) . '%' : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthly-chart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($stats['monthly_trends'], 'month')) ?>,
                datasets: [{
                    label: 'Certificates Created',
                    data: <?= json_encode(array_column($stats['monthly_trends'], 'count')) ?>,
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Workflow Chart
        const workflowCtx = document.getElementById('workflow-chart').getContext('2d');
        new Chart(workflowCtx, {
            type: 'doughnut',
            data: {
                labels: ['Draft', 'Pending Review', 'Verified', 'Approved', 'Rejected', 'Archived'],
                datasets: [{
                    data: [
                        <?= $stats['workflow_stats']['draft'] ?>,
                        <?= $stats['workflow_stats']['pending_review'] ?>,
                        <?= $stats['workflow_stats']['verified'] ?>,
                        <?= $stats['workflow_stats']['approved'] ?>,
                        <?= $stats['workflow_stats']['rejected'] ?>,
                        <?= $stats['workflow_stats']['archived'] ?>
                    ],
                    backgroundColor: [
                        '#6c757d',
                        '#ffc107',
                        '#0dcaf0',
                        '#198754',
                        '#dc3545',
                        '#adb5bd'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
