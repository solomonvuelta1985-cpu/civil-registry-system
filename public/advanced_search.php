<?php
/**
 * Advanced Search
 * Full-text search across all certificates with OCR content
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAuth();
if (!hasAnyPermission(['birth_view', 'marriage_view', 'death_view'])) {
    http_response_code(403); exit('Access denied.');
}

$search_results = [];
$search_performed = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])) {
    $search_performed = true;
    $search_results = performSearch($pdo, $_GET);
}

function performSearch($pdo, $params) {
    $query = $params['q'] ?? '';
    $type = $params['type'] ?? 'all';
    $date_from = $params['date_from'] ?? '';
    $date_to = $params['date_to'] ?? '';
    $municipality = $params['municipality'] ?? '';
    $workflow_state = $params['workflow_state'] ?? 'all';
    $query = mb_substr(trim((string)$query), 0, 100);
    $date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_from) ? $date_from : '';
    $date_to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date_to) ? $date_to : '';
    $municipality = mb_substr(trim((string)$municipality), 0, 100);
    $type = in_array($type, ['all', 'birth', 'marriage', 'death'], true) ? $type : 'all';
    $workflow_state = in_array($workflow_state, ['all', 'draft', 'pending_review', 'verified', 'approved', 'rejected', 'archived'], true) ? $workflow_state : 'all';

    $queries = [];
    $bind_params = [];

    $queries[] = [
        "SELECT 'birth' AS cert_type, id, registry_no,
                CONCAT(child_first_name, ' ', child_middle_name, ' ', child_last_name) AS name,
                date_of_registration, date_of_registration_format,
                date_of_registration_partial_month, date_of_registration_partial_year,
                date_of_registration_partial_day, child_place_of_birth AS location,
                (SELECT current_state FROM workflow_states WHERE certificate_type = 'birth'
                    AND certificate_id = certificate_of_live_birth.id LIMIT 1) AS workflow_state,
                created_at
         FROM certificate_of_live_birth
         WHERE status = 'Active'",
        function () use (&$bind_params, $query, $date_from, $date_to, $municipality) {
            $where = '';
            if ($query) {
                $where .= " AND (MATCH(child_first_name, child_middle_name, child_last_name,
                    mother_first_name, mother_last_name, father_first_name, father_last_name)
                    AGAINST (? IN NATURAL LANGUAGE MODE) OR registry_no LIKE ?)";
                $bind_params[] = $query;
                $bind_params[] = "%$query%";
            }
            if ($date_from) { $where .= ' AND date_of_registration >= ?'; $bind_params[] = $date_from; }
            if ($date_to) { $where .= ' AND date_of_registration <= ?'; $bind_params[] = $date_to; }
            if ($municipality) { $where .= ' AND child_place_of_birth LIKE ?'; $bind_params[] = "%$municipality%"; }
            return $where;
        }
    ];
    $queries[] = [
        "SELECT 'marriage' AS cert_type, id, registry_no,
                CONCAT(husband_first_name, ' ', husband_last_name, ' & ', wife_first_name, ' ', wife_last_name) AS name,
                date_of_registration, date_of_registration_format,
                date_of_registration_partial_month, date_of_registration_partial_year,
                date_of_registration_partial_day, place_of_marriage AS location,
                (SELECT current_state FROM workflow_states WHERE certificate_type = 'marriage'
                    AND certificate_id = certificate_of_marriage.id LIMIT 1) AS workflow_state,
                created_at
         FROM certificate_of_marriage
         WHERE status = 'Active'",
        function () use (&$bind_params, $query, $date_from, $date_to, $municipality) {
            $where = '';
            if ($query) {
                $where .= ' AND (husband_first_name LIKE ? OR husband_last_name LIKE ? OR wife_first_name LIKE ? OR wife_last_name LIKE ? OR registry_no LIKE ?)';
                for ($i = 0; $i < 5; $i++) $bind_params[] = "%$query%";
            }
            if ($date_from) { $where .= ' AND date_of_registration >= ?'; $bind_params[] = $date_from; }
            if ($date_to) { $where .= ' AND date_of_registration <= ?'; $bind_params[] = $date_to; }
            if ($municipality) { $where .= ' AND place_of_marriage LIKE ?'; $bind_params[] = "%$municipality%"; }
            return $where;
        }
    ];
    $queries[] = [
        "SELECT 'death' AS cert_type, id, registry_no,
                CONCAT(deceased_first_name, ' ', deceased_middle_name, ' ', deceased_last_name) AS name,
                date_of_registration, date_of_registration_format,
                date_of_registration_partial_month, date_of_registration_partial_year,
                date_of_registration_partial_day, place_of_death AS location,
                (SELECT current_state FROM workflow_states WHERE certificate_type = 'death'
                    AND certificate_id = certificate_of_death.id LIMIT 1) AS workflow_state,
                created_at
         FROM certificate_of_death
         WHERE status = 'Active'",
        function () use (&$bind_params, $query, $date_from, $date_to, $municipality) {
            $where = '';
            if ($query) {
                $where .= ' AND (deceased_first_name LIKE ? OR deceased_middle_name LIKE ? OR deceased_last_name LIKE ? OR registry_no LIKE ?)';
                for ($i = 0; $i < 4; $i++) $bind_params[] = "%$query%";
            }
            if ($date_from) { $where .= ' AND date_of_registration >= ?'; $bind_params[] = $date_from; }
            if ($date_to) { $where .= ' AND date_of_registration <= ?'; $bind_params[] = $date_to; }
            if ($municipality) { $where .= ' AND place_of_death LIKE ?'; $bind_params[] = "%$municipality%"; }
            return $where;
        }
    ];
    $sql_parts = [];
    foreach ($queries as [$base, $where_builder]) {
        if ($type !== 'all' && !str_contains($base, "'$type' AS cert_type")) continue;
        $sql_parts[] = $base . $where_builder();
    }
    if (!$sql_parts) return [];
    $sql = implode(' UNION ALL ', $sql_parts) . ' ORDER BY date_of_registration DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind_params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allowed = [];
    if (hasPermission('birth_view')) $allowed[] = 'birth';
    if (hasPermission('marriage_view')) $allowed[] = 'marriage';
    if (hasPermission('death_view')) $allowed[] = 'death';
    if ($type !== 'all') $allowed = array_values(array_intersect($allowed, [$type]));
    return array_values(array_filter($rows, static function ($row) use ($allowed, $workflow_state) {
        return in_array($row['cert_type'] ?? '', $allowed, true)
            && ($workflow_state === 'all' || ($row['workflow_state'] ?? 'draft') === $workflow_state);
    }));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search - iScan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            text-align: center;
        }

        header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .search-box {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .search-main {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-main input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1.1rem;
        }

        .search-main input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-main button {
            padding: 15px 40px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .search-main button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .results-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .results-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .result-item {
            padding: 20px;
            border-bottom: 1px solid #f1f3f5;
            transition: background 0.2s;
            cursor: pointer;
        }

        .result-item:hover {
            background: #f8f9fa;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .result-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #212529;
        }

        .result-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .result-badge.birth { background: #e7f3ff; color: #004085; }
        .result-badge.marriage { background: #fff0f6; color: #6a1b33; }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .result-detail {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6c757d;
        }

        .empty-state .icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .search-main {
                flex-direction: column;
            }

            .filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <div class="container">
        <header>
            <h1>🔍 Advanced Search</h1>
            <p>Search across all civil registry records</p>
        </header>

        <div class="search-box">
            <form method="GET" action="">
                <div class="search-main">
                    <input
                        type="text"
                        name="q"
                        placeholder="Search by name, registry number, or keyword..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        autofocus
                    >
                    <button type="submit">🔍 Search</button>
                </div>

                <div class="filters">
                    <div class="filter-group">
                        <label>Certificate Type:</label>
                        <select name="type">
                            <option value="all">All Types</option>
                            <option value="birth" <?= ($_GET['type'] ?? '') === 'birth' ? 'selected' : '' ?>>Birth</option>
                            <option value="marriage" <?= ($_GET['type'] ?? '') === 'marriage' ? 'selected' : '' ?>>Marriage</option>
                            <option value="death" <?= ($_GET['type'] ?? '') === 'death' ? 'selected' : '' ?>>Death</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Date From:</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label>Date To:</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label>Municipality:</label>
                        <input type="text" name="municipality" placeholder="e.g., Baggao" value="<?= htmlspecialchars($_GET['municipality'] ?? '') ?>">
                    </div>
                </div>
            </form>
        </div>

        <div class="results-section">
            <div class="results-header">
                <?php if ($search_performed): ?>
                    📊 Found <?= count($search_results) ?> result(s)
                <?php else: ?>
                    💡 Enter search criteria above
                <?php endif; ?>
            </div>

            <?php if ($search_performed && count($search_results) > 0): ?>
                <?php foreach ($search_results as $result): ?>
                <?php $result_type = in_array($result['cert_type'] ?? '', ['birth', 'marriage', 'death', 'license'], true) ? $result['cert_type'] : ''; ?>
                <div class="result-item" onclick="viewCertificate('<?= htmlspecialchars($result_type, ENT_QUOTES, 'UTF-8') ?>', <?= (int)$result['id'] ?>)">
                    <div class="result-header">
                        <div class="result-title"><?= htmlspecialchars($result['name']) ?></div>
                        <span class="result-badge <?= htmlspecialchars($result_type, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($result_type), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="result-details">
                        <div class="result-detail">
                            <strong>Registry:</strong> <?= htmlspecialchars($result['registry_no'] ?? 'N/A') ?>
                        </div>
                        <div class="result-detail">
                            <strong>Date:</strong> <?= htmlspecialchars(format_registration_date(
                                $result['date_of_registration'] ?? null,
                                $result['date_of_registration_format'] ?? 'full',
                                isset($result['date_of_registration_partial_month']) ? (int)$result['date_of_registration_partial_month'] : null,
                                isset($result['date_of_registration_partial_year'])  ? (int)$result['date_of_registration_partial_year']  : null,
                                isset($result['date_of_registration_partial_day'])   ? (int)$result['date_of_registration_partial_day']   : null
                            )) ?>
                        </div>
                        <div class="result-detail">
                            <strong>Location:</strong> <?= htmlspecialchars($result['location'] ?? 'N/A') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php elseif ($search_performed): ?>
                <div class="empty-state">
                    <div class="icon">🔍</div>
                    <h3>No Results Found</h3>
                    <p>Try different search terms or adjust your filters</p>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">💡</div>
                    <h3>Start Searching</h3>
                    <p>Enter a name, registry number, or keyword to search</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewCertificate(type, id) {
            const urls = {
                'birth': 'certificate_of_live_birth.php',
                'marriage': 'certificate_of_marriage.php',
                'death': 'certificate_of_death.php'
            };

            window.location.href = `${urls[type]}?id=${id}`;
        }
    </script>
</body>
</html>
