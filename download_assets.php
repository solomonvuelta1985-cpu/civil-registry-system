<?php
/**
 * iScan Vendor Asset Downloader (PHP Version)
 * Downloads CDN libraries for offline mode on shared hosting
 *
 * Run via browser: https://yourdomain.com/iscan/download_assets.php
 * Or include in setup wizard
 *
 * SECURITY: Delete this file after downloading assets
 */

// Increase execution time for downloads
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

// Define base paths
define('BASE_PATH', dirname(__FILE__));
define('VENDOR_PATH', BASE_PATH . '/assets/vendor');

// Track progress
$downloads = [];
$errors = [];

// Start output buffering for real-time progress
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Download Vendor Assets</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .progress {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: "Courier New", monospace;
            font-size: 13px;
        }
        .success { color: #059669; }
        .error { color: #dc2626; }
        .info { color: #2563eb; }
        .complete {
            background: #d1fae5;
            color: #065f46;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
            font-weight: 600;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📦 Downloading Vendor Assets</h1>
    <p>Downloading CSS/JS libraries for offline mode...</p>
    <div id="progress">';

    ob_flush();
    flush();
}

/**
 * Log progress to browser
 */
function log_progress($message, $type = 'info') {
    $colors = [
        'info' => 'info',
        'success' => 'success',
        'error' => 'error'
    ];
    $class = $colors[$type] ?? 'info';

    if (php_sapi_name() !== 'cli') {
        echo "<div class='progress $class'>$message</div>";
        ob_flush();
        flush();
    } else {
        echo $message . PHP_EOL;
    }
}

/**
 * Download file with curl or file_get_contents
 */
function download_file($url, $dest) {
    // Create directory if doesn't exist
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Try curl first (more reliable)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $fp = fopen($dest, 'wb');

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'iScan Asset Downloader');

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if ($result && $httpCode == 200) {
            return true;
        } else {
            @unlink($dest);
            return false;
        }
    }

    // Fallback to file_get_contents
    if (ini_get('allow_url_fopen')) {
        $content = @file_get_contents($url);
        if ($content !== false) {
            return file_put_contents($dest, $content) !== false;
        }
    }

    return false;
}

// Start download process
log_progress('Starting vendor asset download...', 'info');
log_progress('Target directory: ' . VENDOR_PATH, 'info');

// ================================================================
// Font Awesome 6.4.0
// ================================================================
log_progress('[1/7] Downloading Font Awesome 6.4.0...', 'info');

$fa_dir = VENDOR_PATH . '/fontawesome';
$fa_css = $fa_dir . '/css/all.min.css';
$fa_webfonts = $fa_dir . '/webfonts';

@mkdir($fa_dir . '/css', 0755, true);
@mkdir($fa_webfonts, 0755, true);

// Download CSS
if (download_file('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', $fa_css)) {
    log_progress('✓ Font Awesome CSS downloaded', 'success');

    // Fix CSS paths to use local fonts
    $css = file_get_contents($fa_css);
    $css = str_replace(
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/',
        '../webfonts/',
        $css
    );
    file_put_contents($fa_css, $css);
    log_progress('✓ Font Awesome CSS paths updated', 'success');
} else {
    log_progress('✗ Font Awesome CSS download failed', 'error');
    $errors[] = 'Font Awesome CSS';
}

// Download web fonts
$fonts = ['fa-solid-900', 'fa-regular-400', 'fa-brands-400'];
foreach ($fonts as $font) {
    foreach (['woff2', 'ttf'] as $ext) {
        $url = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/{$font}.{$ext}";
        $dest = "$fa_webfonts/{$font}.{$ext}";

        if (download_file($url, $dest)) {
            log_progress("✓ Downloaded {$font}.{$ext}", 'success');
        }
    }
}

// ================================================================
// Chart.js 4.4.0
// ================================================================
log_progress('[2/7] Downloading Chart.js 4.4.0...', 'info');

$chartjs_dir = VENDOR_PATH . '/chartjs';
@mkdir($chartjs_dir, 0755, true);

if (download_file(
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
    $chartjs_dir . '/chart.umd.min.js'
)) {
    log_progress('✓ Chart.js downloaded', 'success');
} else {
    log_progress('✗ Chart.js download failed', 'error');
    $errors[] = 'Chart.js';
}

// ================================================================
// Notiflix 3.2.6
// ================================================================
log_progress('[3/7] Downloading Notiflix 3.2.6...', 'info');

$notiflix_dir = VENDOR_PATH . '/notiflix';
@mkdir($notiflix_dir, 0755, true);

if (download_file(
    'https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css',
    $notiflix_dir . '/notiflix-3.2.6.min.css'
)) {
    log_progress('✓ Notiflix CSS downloaded', 'success');
} else {
    log_progress('✗ Notiflix CSS download failed', 'error');
    $errors[] = 'Notiflix CSS';
}

if (download_file(
    'https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js',
    $notiflix_dir . '/notiflix-3.2.6.min.js'
)) {
    log_progress('✓ Notiflix JS downloaded', 'success');
} else {
    log_progress('✗ Notiflix JS download failed', 'error');
    $errors[] = 'Notiflix JS';
}

// ================================================================
// PDF.js 3.11.174
// ================================================================
log_progress('[4/7] Downloading PDF.js 3.11.174...', 'info');

$pdfjs_dir = VENDOR_PATH . '/pdfjs/build';
@mkdir($pdfjs_dir, 0755, true);

if (download_file(
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
    $pdfjs_dir . '/pdf.min.js'
)) {
    log_progress('✓ PDF.js main file downloaded', 'success');
} else {
    log_progress('✗ PDF.js main file download failed', 'error');
    $errors[] = 'PDF.js';
}

if (download_file(
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
    $pdfjs_dir . '/pdf.worker.min.js'
)) {
    log_progress('✓ PDF.js worker downloaded', 'success');
} else {
    log_progress('✗ PDF.js worker download failed', 'error');
    $errors[] = 'PDF.js Worker';
}

// ================================================================
// Lucide Icons
// ================================================================
log_progress('[5/7] Downloading Lucide icons...', 'info');

$lucide_dir = VENDOR_PATH . '/lucide';
@mkdir($lucide_dir, 0755, true);

if (download_file(
    'https://unpkg.com/lucide@latest/dist/umd/lucide.min.js',
    $lucide_dir . '/lucide.min.js'
)) {
    log_progress('✓ Lucide icons downloaded', 'success');
} else {
    log_progress('✗ Lucide icons download failed', 'error');
    $errors[] = 'Lucide';
}

// ================================================================
// Tesseract.js 4 (browser-side OCR)
// ================================================================
log_progress('[6/7] Downloading Tesseract.js 4...', 'info');

$tesseract_dir = VENDOR_PATH . '/tesseractjs';
@mkdir($tesseract_dir, 0755, true);

if (download_file(
    'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js',
    $tesseract_dir . '/tesseract.min.js'
)) {
    log_progress('✓ Tesseract.js downloaded', 'success');
} else {
    log_progress('✗ Tesseract.js download failed (optional)', 'error');
}

// ================================================================
// Summary
// ================================================================
log_progress('[7/7] Download complete!', 'info');

if (count($errors) === 0) {
    log_progress('✓ All vendor assets downloaded successfully!', 'success');
    log_progress('You can now set OFFLINE_MODE=true in your .env file', 'info');
} else {
    log_progress('⚠ Some downloads failed: ' . implode(', ', $errors), 'error');
    log_progress('The application may still work with CDN fallback', 'info');
}

if (php_sapi_name() !== 'cli') {
    echo '</div>';

    if (count($errors) === 0) {
        echo '<div class="complete">
            ✓ Download Complete! All vendor assets saved to assets/vendor/
            <br><br>
            Next steps:
            <ol style="margin-top: 10px;">
                <li>Set <code>OFFLINE_MODE=true</code> in your .env file to use local assets</li>
                <li>Delete this file (download_assets.php) for security</li>
            </ol>
        </div>';
    } else {
        echo '<div class="complete" style="background: #fef3c7; color: #92400e;">
            ⚠ Download completed with some errors
            <br><br>
            Failed downloads: ' . implode(', ', $errors) . '
            <br><br>
            The system will use CDN fallback for missing assets. Try running this script again or contact your hosting provider if issues persist.
        </div>';
    }

    echo '<p style="margin-top: 20px; text-align: center; color: #6b7280;">
        <a href="public/login.php">← Back to Application</a>
    </p>';

    echo '</div></body></html>';
}

// Verify vendor directory structure
$vendor_structure = [
    'fontawesome/css',
    'fontawesome/webfonts',
    'chartjs',
    'notiflix',
    'pdfjs/build',
    'lucide',
    'tesseractjs'
];

log_progress('Verifying directory structure...', 'info');
foreach ($vendor_structure as $dir) {
    $path = VENDOR_PATH . '/' . $dir;
    if (is_dir($path)) {
        log_progress("✓ $dir exists", 'success');
    } else {
        log_progress("✗ $dir missing", 'error');
    }
}

?>
