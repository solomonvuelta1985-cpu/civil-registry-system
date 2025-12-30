<?php
/**
 * PDF Comparison Viewer
 * Side-by-side comparison of form data vs PDF document
 * Helps with manual verification during workflow review
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $_SESSION['user_id'] ?? 1;

// Get parameters
$certificate_type = isset($_GET['type']) ? $_GET['type'] : 'birth';
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    die('Certificate ID is required');
}

// Fetch certificate data
$certificate_data = getCertificateData($pdo, $certificate_type, $certificate_id);
if (!$certificate_data) {
    die('Certificate not found');
}

// Fetch PDF attachment info
$pdf_info = getPDFAttachment($pdo, $certificate_type, $certificate_id);

// Fetch OCR data if available
$ocr_data = getOCRData($pdo, $certificate_type, $certificate_id);

// Fetch validation discrepancies
$discrepancies = getValidationDiscrepancies($pdo, $certificate_type, $certificate_id);

// Fetch workflow state
$workflow_state = getWorkflowState($pdo, $certificate_type, $certificate_id);

/**
 * Helper Functions
 */
function getCertificateData($pdo, $type, $id) {
    $table = $type === 'birth' ? 'certificate_of_live_birth' : 'certificate_of_marriage';

    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPDFAttachment($pdo, $type, $id) {
    $stmt = $pdo->prepare("
        SELECT * FROM pdf_attachments
        WHERE certificate_type = ? AND certificate_id = ? AND is_current_version = 1
        ORDER BY uploaded_at DESC LIMIT 1
    ");
    $stmt->execute([$type, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getOCRData($pdo, $type, $id) {
    $stmt = $pdo->prepare("
        SELECT ocr_text, ocr_confidence_score, ocr_data_json
        FROM pdf_attachments
        WHERE certificate_type = ? AND certificate_id = ? AND is_current_version = 1
        ORDER BY uploaded_at DESC LIMIT 1
    ");
    $stmt->execute([$type, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getValidationDiscrepancies($pdo, $type, $id) {
    $stmt = $pdo->prepare("
        SELECT * FROM validation_discrepancies
        WHERE certificate_type = ? AND certificate_id = ? AND status = 'open'
        ORDER BY severity DESC, detected_at DESC
    ");
    $stmt->execute([$type, $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getWorkflowState($pdo, $type, $id) {
    $stmt = $pdo->prepare("
        SELECT * FROM workflow_states
        WHERE certificate_type = ? AND certificate_id = ?
    ");
    $stmt->execute([$type, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Define field groups for display
$birth_fields = [
    'Registry Information' => ['registry_no', 'date_of_registration'],
    'Child Information' => ['child_first_name', 'child_middle_name', 'child_last_name', 'child_date_of_birth', 'child_place_of_birth'],
    'Mother Information' => ['mother_first_name', 'mother_middle_name', 'mother_last_name'],
    'Father Information' => ['father_first_name', 'father_middle_name', 'father_last_name'],
    'Marriage Information' => ['date_of_marriage', 'place_of_marriage']
];

$marriage_fields = [
    'Registry Information' => ['registry_no', 'date_of_registration'],
    'Marriage Details' => ['date_of_marriage', 'place_of_marriage'],
    'Husband Information' => ['husband_first_name', 'husband_middle_name', 'husband_last_name'],
    'Wife Information' => ['wife_first_name', 'wife_middle_name', 'wife_last_name']
];

$field_groups = $certificate_type === 'birth' ? $birth_fields : $marriage_fields;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Comparison Viewer - <?= ucfirst($certificate_type) ?> Certificate #<?= $certificate_id ?></title>

    <!-- PDF.js -->
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
    </script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.5rem;
        }

        .header-info {
            font-size: 0.9rem;
            opacity: 0.95;
        }

        .workflow-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 15px;
            background: rgba(255,255,255,0.2);
        }

        .main-container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 100px);
        }

        .panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 1.1rem;
            color: #495057;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        /* PDF Viewer Styles */
        #pdf-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        #pdf-canvas {
            max-width: 100%;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .pdf-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .pdf-controls button {
            padding: 8px 16px;
            border: none;
            background: #667eea;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .pdf-controls button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .pdf-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pdf-controls span {
            padding: 0 10px;
            font-weight: 500;
        }

        .zoom-controls {
            display: flex;
            gap: 5px;
        }

        /* Form Data Styles */
        .field-group {
            margin-bottom: 25px;
        }

        .field-group-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .field-item {
            display: grid;
            grid-template-columns: 180px 1fr;
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .field-item:hover {
            background: #f8f9fa;
        }

        .field-item.has-discrepancy {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .field-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .field-value {
            color: #212529;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .field-value.empty {
            color: #adb5bd;
            font-style: italic;
        }

        /* Discrepancies Alert */
        .discrepancies-alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .discrepancies-alert h3 {
            color: #856404;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .discrepancy-item {
            padding: 10px;
            background: white;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .discrepancy-field {
            font-weight: 600;
            color: #856404;
        }

        .discrepancy-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 5px;
        }

        .discrepancy-value {
            padding: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        /* OCR Info */
        .ocr-info {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .ocr-info h3 {
            color: #004085;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .ocr-confidence {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .confidence-bar {
            flex: 1;
            height: 12px;
            background: #dee2e6;
            border-radius: 6px;
            overflow: hidden;
        }

        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc3545, #ffc107, #198754);
            transition: width 0.3s;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-verify {
            background: #0dcaf0;
            color: white;
        }

        .btn-approve {
            background: #198754;
            color: white;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-edit {
            background: #ffc107;
            color: #000;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .main-container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .panel {
                min-height: 600px;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
            }

            .field-item {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .action-buttons {
                flex-wrap: wrap;
            }

            .btn {
                flex: 1;
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>🔍 PDF Comparison Viewer</h1>
                <div class="header-info">
                    <?= ucfirst($certificate_type) ?> Certificate #<?= $certificate_id ?>
                    <?php if ($certificate_data['registry_no']): ?>
                        | Registry No: <?= htmlspecialchars($certificate_data['registry_no']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if ($workflow_state): ?>
                    <span class="workflow-badge">
                        <?= ucwords(str_replace('_', ' ', $workflow_state['current_state'])) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Left Panel: Form Data -->
        <div class="panel">
            <div class="panel-header">
                📝 Form Data
                <div style="font-size: 0.85rem; font-weight: normal; color: #6c757d;">
                    Manual Entry
                </div>
            </div>
            <div class="panel-content">
                <?php if (count($discrepancies) > 0): ?>
                <div class="discrepancies-alert">
                    <h3>⚠️ <?= count($discrepancies) ?> Validation Issue(s) Found</h3>
                    <?php foreach ($discrepancies as $disc): ?>
                    <div class="discrepancy-item">
                        <div class="discrepancy-field"><?= htmlspecialchars($disc['field_name']) ?></div>
                        <div class="discrepancy-details">
                            <div class="discrepancy-value">
                                <strong>Form:</strong> <?= htmlspecialchars($disc['form_value'] ?? 'N/A') ?>
                            </div>
                            <div class="discrepancy-value">
                                <strong>PDF:</strong> <?= htmlspecialchars($disc['pdf_value'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <div style="margin-top: 5px; font-size: 0.85rem; color: #6c757d;">
                            Type: <?= ucwords(str_replace('_', ' ', $disc['discrepancy_type'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($ocr_data && $ocr_data['ocr_confidence_score']): ?>
                <div class="ocr-info">
                    <h3>🤖 OCR Data Available</h3>
                    <div class="ocr-confidence">
                        <div class="confidence-bar">
                            <div class="confidence-fill" style="width: <?= $ocr_data['ocr_confidence_score'] ?>%"></div>
                        </div>
                        <strong><?= number_format($ocr_data['ocr_confidence_score'], 1) ?>%</strong>
                    </div>
                </div>
                <?php endif; ?>

                <?php foreach ($field_groups as $group_name => $fields): ?>
                <div class="field-group">
                    <div class="field-group-title"><?= $group_name ?></div>
                    <?php foreach ($fields as $field): ?>
                        <?php
                        $has_discrepancy = false;
                        foreach ($discrepancies as $disc) {
                            if ($disc['field_name'] === $field) {
                                $has_discrepancy = true;
                                break;
                            }
                        }
                        ?>
                        <div class="field-item <?= $has_discrepancy ? 'has-discrepancy' : '' ?>">
                            <div class="field-label">
                                <?= ucwords(str_replace('_', ' ', $field)) ?>:
                                <?php if ($has_discrepancy): ?>
                                    <span style="color: #ffc107;">⚠️</span>
                                <?php endif; ?>
                            </div>
                            <div class="field-value <?= empty($certificate_data[$field]) ? 'empty' : '' ?>">
                                <?= !empty($certificate_data[$field]) ? htmlspecialchars($certificate_data[$field]) : '(Not provided)' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Panel: PDF Viewer -->
        <div class="panel">
            <div class="panel-header">
                📄 PDF Document
                <div class="pdf-controls">
                    <div class="zoom-controls">
                        <button onclick="zoomOut()">−</button>
                        <span id="zoom-level">100%</span>
                        <button onclick="zoomIn()">+</button>
                    </div>
                    <button onclick="prevPage()" id="prev-btn">◀ Previous</button>
                    <span id="page-info">Page 1 of 1</span>
                    <button onclick="nextPage()" id="next-btn">Next ▶</button>
                </div>
            </div>
            <div class="panel-content">
                <?php if ($pdf_info && file_exists('../' . $pdf_info['file_path'])): ?>
                <div id="pdf-container">
                    <canvas id="pdf-canvas"></canvas>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">📄</div>
                    <h3>No PDF Available</h3>
                    <p>No PDF document has been uploaded for this certificate.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <button class="btn btn-back" onclick="window.history.back()">← Back</button>

        <?php if ($workflow_state && $workflow_state['current_state'] === 'pending_review'): ?>
        <button class="btn btn-verify" onclick="transitionWorkflow('verify')">✓ Verify</button>
        <button class="btn btn-reject" onclick="transitionWorkflow('reject')">✗ Reject</button>
        <?php endif; ?>

        <?php if ($workflow_state && $workflow_state['current_state'] === 'verified'): ?>
        <button class="btn btn-approve" onclick="transitionWorkflow('approve')">✓✓ Approve</button>
        <button class="btn btn-reject" onclick="transitionWorkflow('reject')">✗ Reject</button>
        <?php endif; ?>

        <button class="btn btn-edit" onclick="editCertificate()">✏️ Edit Data</button>
    </div>

    <script>
        // PDF.js variables
        let pdfDoc = null;
        let currentPage = 1;
        let pageCount = 0;
        let scale = 1.5;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas ? canvas.getContext('2d') : null;

        <?php if ($pdf_info && file_exists('../' . $pdf_info['file_path'])): ?>
        // Load PDF
        const pdfUrl = '../<?= $pdf_info['file_path'] ?>';

        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
            pdfDoc = pdf;
            pageCount = pdf.numPages;
            document.getElementById('page-info').textContent = `Page ${currentPage} of ${pageCount}`;
            renderPage(currentPage);
            updateButtons();
        });

        function renderPage(pageNum) {
            pdfDoc.getPage(pageNum).then(function(page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                page.render(renderContext).promise.then(function() {
                    document.getElementById('page-info').textContent = `Page ${pageNum} of ${pageCount}`;
                });
            });
        }

        function prevPage() {
            if (currentPage <= 1) return;
            currentPage--;
            renderPage(currentPage);
            updateButtons();
        }

        function nextPage() {
            if (currentPage >= pageCount) return;
            currentPage++;
            renderPage(currentPage);
            updateButtons();
        }

        function zoomIn() {
            scale += 0.25;
            document.getElementById('zoom-level').textContent = Math.round(scale * 100) + '%';
            renderPage(currentPage);
        }

        function zoomOut() {
            if (scale <= 0.5) return;
            scale -= 0.25;
            document.getElementById('zoom-level').textContent = Math.round(scale * 100) + '%';
            renderPage(currentPage);
        }

        function updateButtons() {
            document.getElementById('prev-btn').disabled = (currentPage <= 1);
            document.getElementById('next-btn').disabled = (currentPage >= pageCount);
        }
        <?php endif; ?>

        // Workflow transitions
        function transitionWorkflow(type) {
            let notes = '';
            if (type === 'reject') {
                notes = prompt('Please provide a reason for rejection:');
                if (!notes) {
                    alert('Rejection reason is required');
                    return;
                }
            }

            if (!confirm(`Are you sure you want to ${type} this certificate?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('certificate_id', <?= $certificate_id ?>);
            formData.append('certificate_type', '<?= $certificate_type ?>');
            formData.append('transition_type', type);
            if (notes) formData.append('notes', notes);

            fetch('../api/workflow_transition.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'workflow_dashboard.php';
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Network error: ' + error);
            });
        }

        function editCertificate() {
            const url = '<?= $certificate_type === "birth" ? "certificate_of_live_birth.php" : "certificate_of_marriage.php" ?>?id=<?= $certificate_id ?>';
            window.location.href = url;
        }
    </script>
</body>
</html>
