<?php
/**
 * Family Relations Page
 * Two states:
 *   - No id    -> search view (search birth records by name or registry no.)
 *   - ?id=N    -> family view (siblings, parents' marriage, parent deaths)
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

if (!hasPermission('birth_view')) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query     = isset($_GET['q']) ? sanitize_input(trim($_GET['q'])) : '';

$mode = $record_id > 0 ? 'family' : 'search';

$search_results = [];
$source_record  = null;
$is_fuzzy_search = false;

if ($mode === 'family') {
    $stmt = $pdo->prepare("SELECT * FROM certificate_of_live_birth WHERE id = ? LIMIT 1");
    $stmt->execute([$record_id]);
    $source_record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source_record) {
        $mode = 'search';
        $record_id = 0;
    }
} elseif ($query !== '') {
    // Tokenized strict-then-fuzzy search, mirroring the pattern in
    // public/records_viewer.php so multi-word queries like "Juan Dela Cruz"
    // match across first_name + last_name fields rather than literal substring.
    $tokens = [];
    foreach (preg_split('/\s+/', $query) as $t) {
        if ($t !== '') $tokens[] = $t;
    }

    $search_fields = [
        'child_first_name',
        'child_middle_name',
        'child_last_name',
        'mother_first_name',
        'mother_last_name',
        'father_first_name',
        'father_last_name',
        'registry_no',
    ];

    $build_clause = function (array $tokens, array $fields, string $mode) {
        $params = [];
        $i = 0;
        if ($mode === 'strict') {
            $token_clauses = [];
            foreach ($tokens as $token) {
                $field_clauses = [];
                foreach ($fields as $f) {
                    $name = ':s_' . $i++;
                    $field_clauses[] = "{$f} LIKE {$name}";
                    $params[$name] = "%{$token}%";
                }
                $token_clauses[] = '(' . implode(' OR ', $field_clauses) . ')';
            }
            return ['(' . implode(' AND ', $token_clauses) . ')', $params];
        }
        // fuzzy: any token in any field
        $field_clauses = [];
        foreach ($tokens as $token) {
            foreach ($fields as $f) {
                $name = ':s_' . $i++;
                $field_clauses[] = "{$f} LIKE {$name}";
                $params[$name] = "%{$token}%";
            }
        }
        return ['(' . implode(' OR ', $field_clauses) . ')', $params];
    };

    $run_query = function (string $where, array $binds) use ($pdo) {
        $sql = "SELECT id, registry_no, child_first_name, child_middle_name, child_last_name,
                       child_date_of_birth, mother_first_name, mother_last_name,
                       father_first_name, father_last_name
                  FROM certificate_of_live_birth
                 WHERE status = 'Active' AND {$where}
              ORDER BY child_last_name, child_first_name
                 LIMIT 50";
        $stmt = $pdo->prepare($sql);
        foreach ($binds as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    // Pass 1: strict — every token must hit some field.
    [$where, $binds] = $build_clause($tokens, $search_fields, 'strict');
    $search_results = $run_query($where, $binds);

    // Pass 2: fuzzy fallback — only when 2+ tokens AND strict returned nothing.
    if (empty($search_results) && count($tokens) > 1) {
        [$where, $binds] = $build_clause($tokens, $search_fields, 'fuzzy');
        $search_results = $run_query($where, $binds);
        if (!empty($search_results)) {
            $is_fuzzy_search = true;
        }
    }
}

function fr_full_name($first, $middle, $last) {
    $parts = array_filter([trim($first ?? ''), trim($middle ?? ''), trim($last ?? '')], function ($v) {
        return $v !== '';
    });
    return implode(' ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo csrfTokenMeta(); ?>
    <title>Family Relations - Civil Registry</title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>
    <script src="../assets/js/notiflix-config.js"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/record-preview-modal.css?v=5">
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
        .content { margin-left: 260px; padding: 88px 24px 24px; min-height: 100vh; }
        @media (max-width: 768px) { .content { margin-left: 0; padding: 80px 16px 16px; } }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; color: #0F172A; }
        .page-title svg { width: 24px; height: 24px; }

        .page-subtitle { color: #64748B; font-size: 13px; margin-bottom: 18px; }

        .search-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 18px;
        }
        .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .search-form input[type="text"] {
            flex: 1;
            min-width: 240px;
            padding: 10px 14px;
            border: 1px solid #CBD5E1;
            border-radius: 4px;
            font-size: 14px;
        }
        .search-form button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            background: #2563EB;
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .search-form button:hover { background: #1D4ED8; }

        .results-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            overflow: hidden;
        }
        .results-header {
            padding: 12px 16px;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E8F0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #475569;
        }
        .results-list { list-style: none; }
        .results-list li.result-item {
            border-bottom: 1px solid #F1F5F9;
        }
        .results-list li.result-item:last-child { border-bottom: none; }
        .result-row {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .result-info { flex: 1; min-width: 0; }
        .result-toggle {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            color: #475569;
            width: 30px;
            height: 30px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .result-toggle:hover { background: #EFF6FF; border-color: #2563EB; color: #2563EB; }
        .result-toggle-chevron { width: 16px; height: 16px; transition: transform 0.2s ease; }
        .result-toggle.result-toggle-open .result-toggle-chevron { transform: rotate(90deg); }
        .result-family-panel {
            padding: 0 16px 16px 60px;
            background: #F8FAFC;
            border-top: 1px solid #F1F5F9;
        }
        @media (max-width: 768px) {
            .result-family-panel { padding: 0 12px 12px 12px; }
        }
        .results-name { font-weight: 600; color: #0F172A; }
        .results-meta { font-size: 12px; color: #64748B; margin-top: 2px; }
        .results-select {
            padding: 6px 14px;
            background: #2563EB;
            color: #FFFFFF;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .results-select:hover { background: #1D4ED8; }

        .empty-state { text-align: center; padding: 50px 20px; color: #94A3B8; font-size: 13px; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #2563EB;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .back-link:hover { text-decoration: underline; }

        .family-container { max-width: 900px; }
    </style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>
    <?php include '../includes/mobile_header.php'; ?>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="page-header">
            <h1 class="page-title">
                <i data-lucide="users"></i>
                Family Relations
            </h1>
        </div>

        <?php if ($mode === 'search'): ?>

            <div class="page-subtitle">
                Search a birth record to view siblings, parents, and related civil registry records.
            </div>

            <div class="search-card">
                <form method="GET" class="search-form" action="">
                    <input type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search by child's name or registry number..." autofocus>
                    <button type="submit">
                        <i data-lucide="search" style="width:16px;height:16px;"></i> Search
                    </button>
                </form>
            </div>

            <?php if ($query !== ''): ?>
                <div class="results-card">
                    <div class="results-header">
                        <?php
                        $count = count($search_results);
                        $word  = $count === 1 ? 'result' : 'results';
                        $label = $is_fuzzy_search ? 'possible matches' : $word;
                        echo $count . ' ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                            . ' for "' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . '"';
                        ?>
                        <?php if ($is_fuzzy_search): ?>
                            <span style="color:#92400E;font-weight:500;text-transform:none;letter-spacing:normal;">&middot; no exact match — showing near results</span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($search_results)): ?>
                        <div class="empty-state">No matching birth records found.</div>
                    <?php else: ?>
                        <ul class="results-list">
                            <?php foreach ($search_results as $r): ?>
                                <?php
                                $childName  = fr_full_name($r['child_first_name'], $r['child_middle_name'], $r['child_last_name']);
                                $fatherName = fr_full_name($r['father_first_name'], '', $r['father_last_name']);
                                $motherName = fr_full_name($r['mother_first_name'], '', $r['mother_last_name']);
                                $reg        = $r['registry_no'] ?? '';
                                $dob        = $r['child_date_of_birth'] ?? '';
                                ?>
                                <li class="result-item" data-record-id="<?= (int)$r['id'] ?>">
                                    <div class="result-row">
                                        <button type="button" class="result-toggle" aria-expanded="false" data-record-id="<?= (int)$r['id'] ?>">
                                            <i data-lucide="chevron-right" class="result-toggle-chevron"></i>
                                        </button>
                                        <div class="result-info">
                                            <div class="results-name"><?= htmlspecialchars($childName !== '' ? $childName : '(unnamed)', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="results-meta">
                                                <?= $reg !== '' ? 'Reg# ' . htmlspecialchars($reg, ENT_QUOTES, 'UTF-8') : 'No Reg#' ?>
                                                <?= $dob !== '' ? ' &middot; Born ' . htmlspecialchars(date('M j, Y', strtotime($dob)), ENT_QUOTES, 'UTF-8') : '' ?>
                                                <?php if ($fatherName !== '' || $motherName !== ''): ?>
                                                    &middot; Parents: <?= htmlspecialchars(trim(($fatherName ?: '?') . ' & ' . ($motherName ?: '?')), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a class="results-select" href="?id=<?= (int)$r['id'] ?>" title="Open full family view">Open &rarr;</a>
                                    </div>
                                    <div class="result-family-panel" id="resultFamily-<?= (int)$r['id'] ?>" style="display:none;"></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <script src="../assets/js/family_relations_render.js?v=1"></script>
                <script src="../assets/js/record-preview-modal.js?v=5"></script>
                <script>
                    (function () {
                        const cache = {};

                        function setLoading(panel) {
                            panel.innerHTML = `<div class="fr-panel-loading"><i class="fas fa-spinner"></i> Loading family relations...</div>`;
                        }

                        function setError(panel, msg) {
                            panel.innerHTML = `<div class="fr-empty">${msg || 'Failed to load family relations.'}</div>`;
                        }

                        async function loadInto(id, panel) {
                            if (cache[id]) {
                                FamilyRelationsRender.render(cache[id], panel, {
                                    skipHeader: true,
                                    onView: function (vid, type) {
                                        if (typeof recordPreviewModal !== 'undefined' && recordPreviewModal && typeof recordPreviewModal.open === 'function') {
                                            recordPreviewModal.open(vid, type);
                                        }
                                    }
                                });
                                return;
                            }
                            setLoading(panel);
                            try {
                                const res = await fetch(`../api/family_relations.php?id=${id}`, { credentials: 'same-origin' });
                                const result = await res.json();
                                if (!result.success) {
                                    setError(panel, result.message);
                                    return;
                                }
                                cache[id] = result.data;
                                FamilyRelationsRender.render(result.data, panel, {
                                    skipHeader: true,
                                    onView: function (vid, type) {
                                        if (typeof recordPreviewModal !== 'undefined' && recordPreviewModal && typeof recordPreviewModal.open === 'function') {
                                            recordPreviewModal.open(vid, type);
                                        }
                                    }
                                });
                            } catch (err) {
                                console.error(err);
                                setError(panel, 'An error occurred while loading family relations.');
                            }
                        }

                        document.querySelectorAll('.result-toggle').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const id = btn.getAttribute('data-record-id');
                                const panel = document.getElementById(`resultFamily-${id}`);
                                if (!panel) return;
                                const isOpen = panel.style.display !== 'none';
                                if (isOpen) {
                                    panel.style.display = 'none';
                                    btn.setAttribute('aria-expanded', 'false');
                                    btn.classList.remove('result-toggle-open');
                                } else {
                                    panel.style.display = 'block';
                                    btn.setAttribute('aria-expanded', 'true');
                                    btn.classList.add('result-toggle-open');
                                    loadInto(id, panel);
                                }
                            });
                        });
                    })();
                </script>
            <?php endif; ?>

        <?php else: /* family mode */ ?>

            <a class="back-link" href="family_relations.php">
                <i data-lucide="arrow-left" style="width:14px;height:14px;"></i> Back to search
            </a>

            <div class="family-container">
                <div id="frPageContainer">
                    <div class="fr-panel-loading">
                        <i class="fas fa-spinner"></i> Loading family relations...
                    </div>
                </div>
            </div>

            <script src="../assets/js/family_relations_render.js?v=1"></script>
            <script src="../assets/js/record-preview-modal.js?v=5"></script>
            <script>
                (function () {
                    const recordId = <?= (int)$record_id ?>;
                    const container = document.getElementById('frPageContainer');

                    fetch(`../api/family_relations.php?id=${recordId}`, { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(result => {
                            if (!result.success) {
                                container.innerHTML = `<div class="fr-empty">${result.message || 'Failed to load family relations.'}</div>`;
                                return;
                            }
                            FamilyRelationsRender.render(result.data, container, {
                                onView: function (id, type) {
                                    if (typeof recordPreviewModal !== 'undefined' && recordPreviewModal && typeof recordPreviewModal.open === 'function') {
                                        recordPreviewModal.open(id, type);
                                    }
                                }
                            });
                        })
                        .catch(err => {
                            console.error(err);
                            container.innerHTML = `<div class="fr-empty">An error occurred while loading family relations.</div>`;
                        });
                })();
            </script>

        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
    <?php include '../includes/sidebar_scripts.php'; ?>
</body>
</html>
