<?php
/**
 * Asset URL Resolver
 * Returns local vendor asset paths when OFFLINE_MODE=true (no internet required),
 * or CDN URLs when OFFLINE_MODE=false (default for XAMPP/development).
 *
 * Usage in any PHP page:
 *   require_once __DIR__ . '/../includes/asset_urls.php';
 *   <link href="<?= asset_url('fontawesome_css') ?>" rel="stylesheet">
 *   <script src="<?= asset_url('notiflix_js') ?>"></script>
 *
 * To enable offline mode, set OFFLINE_MODE=true in .env
 */

function asset_url(string $asset): string {
    $offline = env('OFFLINE_MODE', 'false');
    $isOffline = ($offline === true || $offline === 'true' || $offline === '1');
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '/iscan';

    $local = [
        'fontawesome_css' => $base . '/assets/vendor/fontawesome/css/all.min.css',
        'chartjs'         => $base . '/assets/vendor/chartjs/chart.umd.min.js',
        'notiflix_css'    => $base . '/assets/vendor/notiflix/notiflix-3.2.6.min.css',
        'notiflix_js'     => $base . '/assets/vendor/notiflix/notiflix-3.2.6.min.js',
        'pdfjs'           => $base . '/assets/vendor/pdfjs/build/pdf.min.js',
        'pdfjs_worker'    => $base . '/assets/vendor/pdfjs/build/pdf.worker.min.js',
        'lucide'          => $base . '/assets/vendor/lucide/lucide.min.js',
        'tesseractjs'     => $base . '/assets/vendor/tesseractjs/tesseract.min.js',
    ];

    $cdn = [
        'fontawesome_css' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        'chartjs'         => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
        'notiflix_css'    => 'https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.css',
        'notiflix_js'     => 'https://cdn.jsdelivr.net/npm/notiflix@3.2.6/dist/notiflix-3.2.6.min.js',
        'pdfjs'           => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
        'pdfjs_worker'    => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
        'lucide'          => 'https://unpkg.com/lucide@latest',
        'tesseractjs'     => 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js',
    ];

    $map = $isOffline ? $local : $cdn;
    return $map[$asset] ?? '';
}

/**
 * Returns the Google Fonts <link> tag, or an empty string when offline.
 * Offline fallback relies on system fonts (Inter, Segoe UI, Arial).
 */
function google_fonts_tag(string $families = 'Inter:wght@300;400;500;600;700'): string {
    $offline = env('OFFLINE_MODE', 'false');
    $isOffline = ($offline === true || $offline === 'true' || $offline === '1');
    if ($isOffline) {
        return ''; // System font stack defined in CSS fallback
    }
    $encoded = urlencode($families);
    return '<link rel="preconnect" href="https://fonts.googleapis.com">' . PHP_EOL
        . '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . PHP_EOL
        . '    <link href="https://fonts.googleapis.com/css2?family=' . $encoded . '&display=swap" rel="stylesheet">';
}
