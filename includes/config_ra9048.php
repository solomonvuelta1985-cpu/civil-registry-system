<?php
/**
 * RA 9048/10172 Configuration
 * Civil Registry Transactions Module
 *
 * The RA 9048 tables (petitions, legal_instruments, court_decrees,
 * petition_corrections, petition_supporting_docs) live in the SAME database
 * as the main civil registry tables (iscan_db). They were originally designed
 * for a separate database; that decision was reverted because:
 *   - cross-DB foreign keys aren't supported in MySQL/MariaDB
 *   - cross-DB joins (COLB lookup) are slower and brittle on Synology
 *   - separate-DB backups easy to forget on Hyper Backup
 *
 * For backward compatibility with code that already references $pdo_ra,
 * we alias it to the main $pdo. New code should just use $pdo.
 */

// Load main config (gives $pdo, constants, auth helpers, etc.)
require_once __DIR__ . '/config.php';

// =====================================================================
// FEATURE TOGGLE GUARD
// Module is paused via RA9048_FEATURE_ENABLED in includes/config.php (.env).
// Every RA 9048 entry point includes this file, so blocking here halts the
// whole module — pages, APIs, and the document server.
// To re-enable, set RA9048_FEATURE_ENABLED=true in .env.
// See docs/RA9048_FEATURE_TOGGLE.md for the full procedure.
// =====================================================================
if (!defined('RA9048_FEATURE_ENABLED') || !RA9048_FEATURE_ENABLED) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_api = (strpos($script, '/api/') !== false);

    if ($is_api) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'RA 9048/10172 module is temporarily disabled.',
            'code'    => 'RA9048_FEATURE_DISABLED',
        ]);
    } else {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Feature unavailable</title>';
        echo '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:80px auto;padding:24px;border:1px solid #e5e7eb;border-radius:8px">';
        echo '<h2 style="margin:0 0 8px">RA 9048/10172 module is temporarily disabled</h2>';
        echo '<p style="color:#6b7280">This feature has been paused by the administrator. Please check back later.</p>';
        echo '<p style="color:#6b7280;font-size:12px">If you are an admin, set <code>RA9048_FEATURE_ENABLED=true</code> in your <code>.env</code> file to re-enable.</p>';
        echo '</div>';
    }
    exit;
}

// Backward-compat alias. RA 9048 tables now share iscan_db with the main app.
$pdo_ra = $pdo;

// Upload path for RA 9048 documents
define('RA9048_UPLOAD_PATH', UPLOAD_PATH . 'ra9048/');

// Path to .docx/.doc/.pptx templates used by the document generator
define('RA9048_TEMPLATES_PATH', __DIR__ . '/../documents/templates/');

// =====================================================================
// LCRO office-wide constants — appear on every generated document.
// Update here when MCR changes, office relocates, etc.
// =====================================================================
define('LCRO_OFFICE_NAME',         'OFFICE OF THE CIVIL REGISTRAR');
define('LCRO_OFFICE_MUNICIPALITY', 'BAGGAO');
define('LCRO_OFFICE_PROVINCE',     'CAGAYAN');
define('LCRO_OFFICE_ADDRESS',      'Ground Floor, Executive Building, Zone 4, San Jose, Baggao, Cagayan, 3506');
define('LCRO_OFFICE_EMAIL',        'mcrbaggao@gmail.com');
define('LCRO_MCR_FULL_NAME',       'ATANACIO G. TUNGPALAN');
define('LCRO_MCR_TITLE',           'Municipal Civil Registrar');

/**
 * Map a petition_subtype to the legal citation strings used in generated documents.
 *
 * @param string $subtype  CCE_minor | CCE_10172 | CFN
 * @return array{law: string, irr: string, mc: ?string, nature_label: string}
 */
function ra9048_citation(string $subtype): array
{
    switch ($subtype) {
        case 'CCE_10172':
            return [
                'law'          => 'R.A. 9048 as amended by R.A. 10172',
                'irr'          => 'Administrative Order No. 1, series of 2012',
                'mc'           => 'Memorandum Circular No. 2013-1',
                'nature_label' => 'CORRECTION OF CLERICAL ERROR',
            ];
        case 'CFN':
            return [
                'law'          => 'R.A. 9048',
                'irr'          => 'Administrative Order No. 1, series of 2001',
                'mc'           => null,
                'nature_label' => 'CHANGE OF FIRST NAME',
            ];
        case 'CCE_minor':
        default:
            return [
                'law'          => 'R.A. 9048',
                'irr'          => 'Administrative Order No. 1, series of 2001',
                'mc'           => null,
                'nature_label' => 'CORRECTION OF CLERICAL ERROR',
            ];
    }
}

/**
 * Determine whether a petition subtype requires newspaper publication.
 * CCE-minor: posting only. CFN and CCE_10172: posting + publication.
 */
function ra9048_requires_publication(string $subtype): bool
{
    return $subtype === 'CFN' || $subtype === 'CCE_10172';
}

// (Legacy second PDO connection removed — RA 9048 tables share iscan_db.)
