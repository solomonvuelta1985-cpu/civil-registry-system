<?php
/**
 * Batch Upload Interface
 * Bulk processing of PDFs for historical record digitization
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = $_SESSION['user_id'] ?? 1;
$current_user_name = $_SESSION['full_name'] ?? 'Administrator';

// Get active batches
$active_batches = getActiveBatches($pdo, $current_user_id);

function getActiveBatches($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            u.full_name as created_by_name
        FROM batch_uploads b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.status != 'completed'
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Upload - iScan</title>
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

        .upload-section {
            background: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .upload-zone:hover, .upload-zone.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .upload-zone.dragover {
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .upload-zone h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        .upload-zone p {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .file-input {
            display: none;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .batch-config {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .config-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .config-item label {
            font-weight: 500;
            color: #495057;
        }

        .config-item select, .config-item input[type="text"] {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .config-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .file-list {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: none;
        }

        .file-list.show {
            display: block;
        }

        .file-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-icon {
            font-size: 1.5rem;
        }

        .file-details {
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 500;
            color: #212529;
        }

        .file-size {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .file-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .file-remove:hover {
            background: #bb2d3b;
        }

        .upload-progress {
            margin-top: 20px;
            display: none;
        }

        .upload-progress.show {
            display: block;
        }

        .progress-bar {
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s;
        }

        .progress-text {
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .batches-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .section-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 1.2rem;
            color: #495057;
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

        .badge.uploading { background: #cfe2ff; color: #084298; }
        .badge.queued { background: #e7f3ff; color: #004085; }
        .badge.processing { background: #fff3cd; color: #856404; }
        .badge.completed { background: #d1e7dd; color: #0a3622; }
        .badge.failed { background: #f8d7da; color: #58151c; }

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
            .batch-config {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📦 Batch Upload</h1>
            <p>Upload multiple PDFs for bulk processing</p>
        </header>

        <!-- Upload Section -->
        <div class="upload-section">
            <form id="batch-upload-form" enctype="multipart/form-data">
                <div class="upload-zone" id="upload-zone">
                    <div class="upload-icon">📁</div>
                    <h3>Drop PDFs here or click to browse</h3>
                    <p>Supports multiple files (max <?= ini_get('upload_max_filesize') ?> each)</p>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('file-input').click()">
                        Select Files
                    </button>
                    <input type="file" id="file-input" class="file-input" multiple accept=".pdf" onchange="handleFileSelect(event)">
                </div>

                <div class="file-list" id="file-list">
                    <!-- Files will be listed here -->
                </div>

                <div class="batch-config">
                    <div class="config-item">
                        <label for="batch-name">Batch Name *</label>
                        <input type="text" id="batch-name" name="batch_name" placeholder="e.g., Historical Records 2020-2024" required>
                    </div>

                    <div class="config-item">
                        <label for="cert-type">Certificate Type *</label>
                        <select id="cert-type" name="certificate_type" required>
                            <option value="">Select type...</option>
                            <option value="birth">Birth Certificate</option>
                            <option value="marriage">Marriage Certificate</option>
                            <option value="death">Death Certificate</option>
                        </select>
                    </div>

                    <div class="config-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="auto-ocr" name="auto_ocr" checked>
                            <span>Enable Auto-OCR</span>
                        </label>
                    </div>

                    <div class="config-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="auto-validate" name="auto_validate" checked>
                            <span>Auto-Validate Data</span>
                        </label>
                    </div>
                </div>

                <div class="upload-progress" id="upload-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="progress-text" id="progress-text">Uploading: 0%</div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" id="upload-btn" disabled>
                        🚀 Start Batch Upload
                    </button>
                    <button type="button" class="btn" onclick="resetForm()" style="background: #6c757d; color: white;">
                        🔄 Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Batches -->
        <div class="batches-section">
            <div class="section-header">
                📊 Active Batches
            </div>
            <?php if (count($active_batches) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Type</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Files</th>
                        <th>Created By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_batches as $batch): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($batch['batch_name']) ?></strong></td>
                        <td><span class="badge"><?= ucfirst($batch['certificate_type']) ?></span></td>
                        <td>
                            <div class="progress-bar" style="margin: 0;">
                                <div class="progress-fill" style="width: <?= $batch['progress_percentage'] ?>%"></div>
                            </div>
                            <small><?= number_format($batch['progress_percentage'], 1) ?>%</small>
                        </td>
                        <td><span class="badge <?= $batch['status'] ?>"><?= ucwords(str_replace('_', ' ', $batch['status'])) ?></span></td>
                        <td>
                            <?= $batch['processed_files'] ?> / <?= $batch['total_files'] ?>
                            (✅ <?= $batch['successful_files'] ?> | ❌ <?= $batch['failed_files'] ?>)
                        </td>
                        <td><?= htmlspecialchars($batch['created_by_name'] ?? 'Unknown') ?></td>
                        <td><?= date('M d, Y', strtotime($batch['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                <h3>No Active Batches</h3>
                <p>Upload files above to create a new batch</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let selectedFiles = [];

        // Drag and drop handlers
        const uploadZone = document.getElementById('upload-zone');

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');

            const files = Array.from(e.dataTransfer.files).filter(file => file.type === 'application/pdf');
            addFiles(files);
        });

        // File selection handler
        function handleFileSelect(event) {
            const files = Array.from(event.target.files);
            addFiles(files);
        }

        function addFiles(files) {
            files.forEach(file => {
                if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            });

            renderFileList();
            document.getElementById('upload-btn').disabled = selectedFiles.length === 0;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            renderFileList();
            document.getElementById('upload-btn').disabled = selectedFiles.length === 0;
        }

        function renderFileList() {
            const fileList = document.getElementById('file-list');

            if (selectedFiles.length === 0) {
                fileList.classList.remove('show');
                return;
            }

            fileList.classList.add('show');
            fileList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-icon">📄</div>
                        <div class="file-details">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${formatFileSize(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="file-remove" onclick="removeFile(${index})">Remove</button>
                </div>
            `).join('');
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function resetForm() {
            selectedFiles = [];
            document.getElementById('batch-upload-form').reset();
            renderFileList();
            document.getElementById('upload-btn').disabled = true;
            document.getElementById('upload-progress').classList.remove('show');
        }

        // Form submission
        document.getElementById('batch-upload-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const batchName = document.getElementById('batch-name').value;
            const certType = document.getElementById('cert-type').value;
            const autoOCR = document.getElementById('auto-ocr').checked;
            const autoValidate = document.getElementById('auto-validate').checked;

            if (!batchName || !certType) {
                alert('Please fill in all required fields');
                return;
            }

            if (selectedFiles.length === 0) {
                alert('Please select at least one PDF file');
                return;
            }

            // Show progress
            const progressDiv = document.getElementById('upload-progress');
            const progressFill = document.getElementById('progress-fill');
            const progressText = document.getElementById('progress-text');
            progressDiv.classList.add('show');

            // Disable upload button
            document.getElementById('upload-btn').disabled = true;

            // Create batch record first
            const batchData = new FormData();
            batchData.append('batch_name', batchName);
            batchData.append('certificate_type', certType);
            batchData.append('total_files', selectedFiles.length);
            batchData.append('auto_ocr', autoOCR ? '1' : '0');
            batchData.append('auto_validate', autoValidate ? '1' : '0');

            try {
                const batchResponse = await fetch('../api/batch_create.php', {
                    method: 'POST',
                    body: batchData
                });

                const batchResult = await batchResponse.json();

                if (!batchResult.success) {
                    throw new Error(batchResult.error || 'Failed to create batch');
                }

                const batchId = batchResult.batch_id;

                // Upload files one by one
                for (let i = 0; i < selectedFiles.length; i++) {
                    const file = selectedFiles[i];
                    const fileData = new FormData();
                    fileData.append('batch_id', batchId);
                    fileData.append('pdf_file', file);
                    fileData.append('processing_order', i + 1);

                    const progress = ((i + 1) / selectedFiles.length) * 100;
                    progressFill.style.width = progress + '%';
                    progressText.textContent = `Uploading: ${Math.round(progress)}% (${i + 1}/${selectedFiles.length})`;

                    await fetch('../api/batch_upload_file.php', {
                        method: 'POST',
                        body: fileData
                    });
                }

                alert(`Batch upload completed! ${selectedFiles.length} files queued for processing.`);
                window.location.reload();

            } catch (error) {
                alert('Error: ' + error.message);
                document.getElementById('upload-btn').disabled = false;
            }
        });
    </script>
</body>
</html>
