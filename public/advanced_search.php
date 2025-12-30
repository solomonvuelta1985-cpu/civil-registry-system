<?php
/**
 * Advanced Search
 * Full-text search across all certificates with OCR content
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

    $sql = "
        SELECT
            'birth' as cert_type,
            id,
            registry_no,
            CONCAT(child_first_name, ' ', child_middle_name, ' ', child_last_name) as name,
            date_of_registration,
            child_place_of_birth as location,
            created_at
        FROM certificate_of_live_birth
        WHERE 1=1
    ";

    if ($query) {
        $sql .= " AND (
            MATCH(child_first_name, child_middle_name, child_last_name, mother_first_name, mother_last_name, father_first_name, father_last_name) AGAINST (? IN NATURAL LANGUAGE MODE)
            OR registry_no LIKE ?
        )";
    }

    if ($date_from) {
        $sql .= " AND date_of_registration >= ?";
    }

    if ($date_to) {
        $sql .= " AND date_of_registration <= ?";
    }

    if ($municipality) {
        $sql .= " AND (child_place_of_birth LIKE ? OR place_of_marriage LIKE ?)";
    }

    $sql .= " UNION ALL ";

    $sql .= "
        SELECT
            'marriage' as cert_type,
            id,
            registry_no,
            CONCAT(husband_first_name, ' ', husband_last_name, ' & ', wife_first_name, ' ', wife_last_name) as name,
            date_of_registration,
            place_of_marriage as location,
            created_at
        FROM certificate_of_marriage
        WHERE 1=1
    ";

    if ($query) {
        $sql .= " AND (
            husband_first_name LIKE ? OR husband_last_name LIKE ?
            OR wife_first_name LIKE ? OR wife_last_name LIKE ?
            OR registry_no LIKE ?
        )";
    }

    if ($date_from) {
        $sql .= " AND date_of_registration >= ?";
    }

    if ($date_to) {
        $sql .= " AND date_of_registration <= ?";
    }

    if ($municipality) {
        $sql .= " AND place_of_marriage LIKE ?";
    }

    $sql .= " ORDER BY date_of_registration DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $bind_params = [];

    if ($query) {
        $bind_params[] = $query;
        $bind_params[] = "%$query%";
    }
    if ($date_from) $bind_params[] = $date_from;
    if ($date_to) $bind_params[] = $date_to;
    if ($municipality) {
        $bind_params[] = "%$municipality%";
        $bind_params[] = "%$municipality%";
    }

    if ($query) {
        for ($i = 0; $i < 5; $i++) {
            $bind_params[] = "%$query%";
        }
    }
    if ($date_from) $bind_params[] = $date_from;
    if ($date_to) $bind_params[] = $date_to;
    if ($municipality) $bind_params[] = "%$municipality%";

    $stmt->execute($bind_params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="result-item" onclick="viewCertificate('<?= $result['cert_type'] ?>', <?= $result['id'] ?>)">
                    <div class="result-header">
                        <div class="result-title"><?= htmlspecialchars($result['name']) ?></div>
                        <span class="result-badge <?= $result['cert_type'] ?>"><?= ucfirst($result['cert_type']) ?></span>
                    </div>
                    <div class="result-details">
                        <div class="result-detail">
                            <strong>Registry:</strong> <?= htmlspecialchars($result['registry_no'] ?? 'N/A') ?>
                        </div>
                        <div class="result-detail">
                            <strong>Date:</strong> <?= date('M d, Y', strtotime($result['date_of_registration'])) ?>
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
