<?php
/**
 * Tesseract OCR Diagnostic Page
 * Run this to see if Tesseract is working
 */

echo "<h1>Tesseract OCR Diagnostic</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Check 1: Look for Tesseract in common locations
echo "<h2>1. Checking Tesseract Installation:</h2>";

$possiblePaths = [
    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
    'C:\\tesseract\\tesseract.exe',
    dirname(__DIR__) . '\\tesseract\\tesseract.exe'
];

$found = false;
$tesseractPath = null;

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ Found Tesseract at: <code>$path</code></p>";
        $tesseractPath = $path;
        $found = true;
        break;
    } else {
        echo "<p class='error'>❌ Not found: <code>$path</code></p>";
    }
}

// Check 2: Try to run tesseract from PATH
echo "<h2>2. Checking System PATH:</h2>";
$output = [];
$returnCode = 0;
exec('tesseract --version 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "<p class='success'>✅ Tesseract found in system PATH!</p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    $tesseractPath = 'tesseract';
    $found = true;
} else {
    echo "<p class='error'>❌ Tesseract not in system PATH</p>";
    echo "<p class='info'>Error code: $returnCode</p>";
}

// Check 3: Try to run with full path
if ($tesseractPath && $found) {
    echo "<h2>3. Testing Tesseract Execution:</h2>";
    $cmd = "\"$tesseractPath\" --version 2>&1";
    $output = [];
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0) {
        echo "<p class='success'>✅ Tesseract can be executed!</p>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    } else {
        echo "<p class='error'>❌ Cannot execute Tesseract</p>";
        echo "<p>Command: <code>$cmd</code></p>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
}

// Summary
echo "<h2>Summary:</h2>";
if ($found) {
    echo "<p class='success'><strong>✅ SERVER OCR SHOULD WORK!</strong></p>";
    echo "<p>Tesseract path: <code>$tesseractPath</code></p>";
    echo "<p class='info'>Your OCR processing should be FAST (2-5 seconds per page)</p>";
} else {
    echo "<p class='error'><strong>❌ SERVER OCR WILL NOT WORK</strong></p>";
    echo "<p class='error'>System will fall back to slow browser OCR (2-3 minutes)</p>";

    echo "<h3>Solutions:</h3>";
    echo "<ol>";
    echo "<li><strong>Install Tesseract OCR:</strong><br>";
    echo "Download from: <a href='https://github.com/UB-Mannheim/tesseract/wiki' target='_blank'>https://github.com/UB-Mannheim/tesseract/wiki</a><br>";
    echo "Install to: <code>C:\\Program Files\\Tesseract-OCR\\</code></li>";

    echo "<li><strong>Add to System PATH:</strong><br>";
    echo "1. Right-click 'This PC' → Properties<br>";
    echo "2. Advanced system settings → Environment Variables<br>";
    echo "3. Edit 'Path' → Add: <code>C:\\Program Files\\Tesseract-OCR</code><br>";
    echo "4. Restart Apache/XAMPP</li>";

    echo "<li><strong>Verify installation:</strong><br>";
    echo "Open CMD and type: <code>tesseract --version</code></li>";
    echo "</ol>";
}

// Check 4: Database check
echo "<h2>4. Checking Database (OCR Cache):</h2>";
try {
    require_once 'includes/config.php';

    $stmt = $pdo->query("SHOW TABLES LIKE 'ocr_cache'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ OCR cache table exists</p>";

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM ocr_cache");
        $result = $stmt->fetch();
        echo "<p class='info'>Cached results: {$result['count']}</p>";
    } else {
        echo "<p class='error'>❌ OCR cache table not found</p>";
        echo "<p>Run the migration script to create it</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='public/certificate_of_live_birth.php'>← Back to Birth Certificate Form</a></p>";
