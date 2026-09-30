<?php
/**
 * System Settings - Admin only
 * Civil Registry Document Management System (CRDMS)
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/settings.php';
require_once '../includes/branding.php';

requireAuth();
if (!isAdmin()) {
    http_response_code(403);
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

/**
 * Settings rendered on this page. Add an entry here to expose a new toggle.
 * Type is currently 'boolean' only; extend the renderer if other types are needed.
 */
$TOGGLE_DEFINITIONS = [
    [
        'key'         => 'ocr_enabled',
        'label'       => 'OCR / Scan Now Badge',
        'description' => 'When ON, the floating "Scan Now" badge appears on certificate forms and OCR engines are loaded. Turn OFF to hide the badge and skip loading OCR scripts.',
        'category'    => 'OCR',
    ],
    [
        'key'         => 'maintenance_mode',
        'label'       => 'Emergency Maintenance Override',
        'description' => 'Turn ON to force maintenance immediately. Turn OFF to return control to the scheduled maintenance window, if one is active.',
        'category'    => 'System',
        'danger'      => true,
    ],
];

$flash = null;
$logoDefinitions = branding_logo_definitions();
$scheduleInput = [
    'starts_at' => '',
    'ends_at' => '',
    'message' => 'The system will be unavailable during the scheduled maintenance window.',
];
$initialSchedule = maintenance_get_public_schedule();
if ($initialSchedule) {
    $scheduleInput['starts_at'] = maintenance_datetime_local_input($initialSchedule['starts_at']);
    $scheduleInput['ends_at'] = maintenance_datetime_local_input($initialSchedule['ends_at']);
    $scheduleInput['message'] = $initialSchedule['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRFToken();

    if (($_POST['form_action'] ?? '') === 'maintenance_schedule') {
        $scheduleAction = (string)($_POST['schedule_action'] ?? '');
        $scheduleInput = [
            'starts_at' => trim((string)($_POST['maintenance_starts_at'] ?? '')),
            'ends_at' => trim((string)($_POST['maintenance_ends_at'] ?? '')),
            'message' => trim((string)($_POST['scheduled_maintenance_message'] ?? '')),
        ];

        if ($scheduleAction === 'save') {
            if ($scheduleInput['starts_at'] === '' || $scheduleInput['ends_at'] === '' || $scheduleInput['message'] === '') {
                $flash = ['type' => 'error', 'message' => 'Enter a start, end, and maintenance message.'];
            } elseif (mb_strlen($scheduleInput['message']) > 500) {
                $flash = ['type' => 'error', 'message' => 'The maintenance message must be 500 characters or fewer.'];
            } else {
                $startsAtUtc = maintenance_local_datetime_to_utc($scheduleInput['starts_at']);
                $endsAtUtc = maintenance_local_datetime_to_utc($scheduleInput['ends_at']);
                $startDate = maintenance_parse_utc_datetime($startsAtUtc);
                $endDate = maintenance_parse_utc_datetime($endsAtUtc);
                if (!$startDate || !$endDate) {
                    $flash = ['type' => 'error', 'message' => 'Enter valid start and end dates and times.'];
                } elseif ($endDate <= $startDate) {
                    $flash = ['type' => 'error', 'message' => 'The maintenance end must be later than its start.'];
                } else {
                    $wasScheduled = maintenance_get_public_schedule() !== null;
                    $schedule = [
                        'id' => bin2hex(random_bytes(12)),
                        'status' => 'scheduled',
                        'starts_at' => $startsAtUtc,
                        'ends_at' => $endsAtUtc,
                        'message' => $scheduleInput['message'],
                        'published_at' => gmdate('Y-m-d\TH:i:s\Z'),
                        'updated_by' => getUserId(),
                    ];
                    if (set_setting($pdo, 'maintenance_schedule', $schedule, getUserId())) {
                        log_activity($pdo, 'settings_updated', $wasScheduled ? 'Maintenance schedule updated' : 'Maintenance schedule created', getUserId());
                        $_SESSION['settings_flash'] = ['type' => 'success', 'message' => $wasScheduled ? 'Maintenance schedule updated. Users will be notified.' : 'Maintenance schedule saved. Users will be notified.'];
                        header('Location: ' . BASE_URL . 'admin/settings.php');
                        exit;
                    }
                    $flash = ['type' => 'error', 'message' => 'The maintenance schedule could not be saved. Please try again.'];
                }
            }
        } elseif ($scheduleAction === 'cancel') {
            $schedule = maintenance_get_schedule();
            $publicSchedule = maintenance_get_public_schedule();
            $submittedId = trim((string)($_POST['schedule_id'] ?? ''));
            if (!$schedule || !$publicSchedule || $submittedId === '' || !hash_equals((string)$schedule['id'], $submittedId)) {
                $flash = ['type' => 'error', 'message' => 'There is no current maintenance schedule to cancel. Refresh the page and try again.'];
            } else {
                $schedule['status'] = 'cancelled';
                $schedule['cancelled_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $schedule['updated_by'] = getUserId();
                if (set_setting($pdo, 'maintenance_schedule', $schedule, getUserId())) {
                    log_activity($pdo, 'settings_updated', 'Maintenance schedule cancelled', getUserId());
                    $_SESSION['settings_flash'] = ['type' => 'success', 'message' => 'Maintenance schedule cancelled. Users will be notified.'];
                    header('Location: ' . BASE_URL . 'admin/settings.php');
                    exit;
                }
                $flash = ['type' => 'error', 'message' => 'The maintenance schedule could not be cancelled. Please try again.'];
            }
        } else {
            $flash = ['type' => 'error', 'message' => 'Choose save or cancel for the maintenance schedule.'];
        }
    } elseif (($_POST['form_action'] ?? '') === 'branding') {
        $slot = trim((string) ($_POST['logo_slot'] ?? ''));
        $brandingAction = trim((string) ($_POST['branding_action'] ?? ''));
        if (!isset($logoDefinitions[$slot])) {
            $flash = ['type' => 'error', 'message' => 'Choose a valid logo slot.'];
        } elseif ($brandingAction === 'upload') {
            $uploadError = '';
            if (branding_store_logo_upload($pdo, $slot, $_FILES['logo_file'] ?? [], getUserId(), $uploadError)) {
                log_activity($pdo, 'settings_updated', $logoDefinitions[$slot]['label'] . ' uploaded', getUserId());
                $_SESSION['settings_flash'] = ['type' => 'success', 'message' => $logoDefinitions[$slot]['label'] . ' updated.'];
                header('Location: ' . BASE_URL . 'admin/settings.php');
                exit;
            }
            $flash = ['type' => 'error', 'message' => $uploadError];
        } elseif ($brandingAction === 'hide' && $slot !== 'app') {
            $settingKey = $logoDefinitions[$slot]['setting_key'];
            $previousPath = (string) get_setting($settingKey, '');
            if (set_setting($pdo, $settingKey, BRANDING_LOGO_HIDDEN_VALUE, getUserId())) {
                branding_delete_uploaded_logo_if_unused($previousPath, $settingKey);
                log_activity($pdo, 'settings_updated', $logoDefinitions[$slot]['label'] . ' hidden', getUserId());
                $_SESSION['settings_flash'] = ['type' => 'success', 'message' => $logoDefinitions[$slot]['label'] . ' hidden from CRF forms.'];
                header('Location: ' . BASE_URL . 'admin/settings.php');
                exit;
            }
            $flash = ['type' => 'error', 'message' => 'The logo could not be hidden. Please try again.'];
        } elseif ($brandingAction === 'reset') {
            $settingKey = $logoDefinitions[$slot]['setting_key'];
            $previousPath = (string) get_setting($settingKey, '');
            if (set_setting($pdo, $settingKey, '', getUserId())) {
                branding_delete_uploaded_logo_if_unused($previousPath, $settingKey);
                log_activity($pdo, 'settings_updated', $logoDefinitions[$slot]['label'] . ' reset to default', getUserId());
                $_SESSION['settings_flash'] = ['type' => 'success', 'message' => $logoDefinitions[$slot]['label'] . ' reset to its default.'];
                header('Location: ' . BASE_URL . 'admin/settings.php');
                exit;
            }
            $flash = ['type' => 'error', 'message' => 'The logo setting could not be reset. Please try again.'];
        } else {
            $flash = ['type' => 'error', 'message' => 'Choose upload, hide, or reset.'];
        }
    } elseif (($_POST['form_action'] ?? '') === 'save_settings') {
        $allowed_keys = array_column($TOGGLE_DEFINITIONS, 'key');
    $changes = [];
    $manualMaintenanceEnabled = false;

    foreach ($allowed_keys as $key) {
        $submitted = isset($_POST[$key]) && $_POST[$key] === '1';
        $current = (bool) get_setting($key, false);
        if ($submitted !== $current) {
            if (set_setting($pdo, $key, $submitted ? 'true' : 'false', getUserId())) {
                $changes[] = $key . '=' . ($submitted ? 'true' : 'false');
                if ($key === 'maintenance_mode' && $submitted) {
                    $manualMaintenanceEnabled = true;
                }
            }
        }
    }

    // Maintenance message (free-text). Trim and cap at 500 chars to keep the
    // setting row sensible. An empty value falls back to the seeded default
    // when read via get_setting().
    if (isset($_POST['maintenance_message'])) {
        $new_message = trim((string) $_POST['maintenance_message']);
        if (mb_strlen($new_message) > 500) {
            $new_message = mb_substr($new_message, 0, 500);
        }
        $current_message = (string) get_setting('maintenance_message', '');
        if ($new_message !== $current_message) {
            if (set_setting($pdo, 'maintenance_message', $new_message, getUserId())) {
                $changes[] = 'maintenance_message updated';
            }
        }
    }

    if ($manualMaintenanceEnabled) {
        $manualMessage = trim((string)($_POST['maintenance_message'] ?? get_setting('maintenance_message', '')));
        if ($manualMessage === '') $manualMessage = 'The system is undergoing scheduled maintenance. Please try again shortly.';
        set_setting($pdo, 'maintenance_manual_event', [
            'id' => bin2hex(random_bytes(12)),
            'message' => $manualMessage,
            'started_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ], getUserId());
    }
    if (!empty($changes)) {
        log_activity($pdo, 'settings_updated', implode('; ', $changes), getUserId());
        $_SESSION['settings_flash'] = ['type' => 'success', 'message' => 'Settings saved.'];
    } else {
        $_SESSION['settings_flash'] = ['type' => 'info', 'message' => 'No changes.'];
    }
    header('Location: ' . BASE_URL . 'admin/settings.php');
    exit;
    } else {
        $flash = ['type' => 'error', 'message' => 'Choose a valid settings action.'];
    }
}

if ($flash === null && isset($_SESSION['settings_flash']) && is_array($_SESSION['settings_flash'])) {
    $flash = $_SESSION['settings_flash'];
    unset($_SESSION['settings_flash']);
}

$maintenanceSchedule = maintenance_get_public_schedule();
$storedMaintenanceSchedule = maintenance_get_schedule();
$maintenanceEffective = maintenance_is_active();
$manualMaintenanceOverride = (bool)get_setting('maintenance_mode', false);
$maintenanceStatusLabel = $maintenanceEffective
    ? ($manualMaintenanceOverride ? 'ACTIVE — emergency override' : 'ACTIVE — scheduled window')
    : ($maintenanceSchedule ? 'SCHEDULED' : 'OFF');
$current_page = 'settings.php';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Civil Registry</title>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="../assets/css/sidebar.css?v=20260929-groups">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            color: #1a1a1a;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .page-container {
            padding: 20px;
            max-width: 960px;
            margin: 0 auto;
        }
        .page-header {
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.02em;
        }
        .page-title [data-lucide] { color: #3b82f6; }
        .page-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .flash {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash.success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .flash.info    { background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; }
        .flash.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .category-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .category-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .category-title {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .branding-list { display: grid; gap: 10px; padding: 16px 24px 8px; }
        .branding-item { min-width: 0; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
        .branding-item summary {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr) auto;
            align-items: center;
            gap: 14px;
            min-height: 76px;
            padding: 10px 14px;
            cursor: pointer;
            list-style: none;
        }
        .branding-item summary::-webkit-details-marker { display: none; }
        .branding-item summary::marker { content: ''; }
        .branding-item summary:focus-visible { outline: 3px solid #7ca3fc; outline-offset: -3px; border-radius: 9px; }
        .branding-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            padding: 6px;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
        }
        .branding-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .branding-preview-empty {
            color: #6b7280;
            font-size: 0.625rem;
            text-align: center;
            line-height: 1.2;
        }
        .branding-copy { min-width: 0; }
        .branding-name { display: block; margin-bottom: 3px; color: #111827; font-size: 0.9375rem; font-weight: 700; }
        .branding-status { color: #6b7280; font-size: 0.8125rem; }
        .branding-summary-action { display: inline-flex; align-items: center; gap: 6px; color: #2563eb; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; }
        .branding-summary-action svg, .branding-summary-action [data-lucide] { width: 16px; height: 16px; transition: transform .18s ease; }
        .branding-item[open] .branding-summary-action svg, .branding-item[open] .branding-summary-action [data-lucide] { transform: rotate(180deg); }
        .branding-panel { padding: 16px 20px 18px; border-top: 1px solid #e5e7eb; }
        .branding-description { margin: 0 0 14px; color: #6b7280; font-size: 0.8125rem; }
        .branding-upload { display: flex; align-items: end; gap: 12px; }
        .branding-file-group { flex: 1; min-width: 0; }
        .branding-file-group label { display: block; margin-bottom: 6px; }
        .branding-item input[type="file"] {
            display: block;
            width: 100%;
            font-size: 0.8125rem;
        }
        .branding-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .branding-secondary-actions { margin-top: 10px; }
        .btn-secondary {
            color: #374151;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
        }
        .btn-secondary:hover:not(:disabled) { background: #e5e7eb; }
        .btn:disabled { cursor: not-allowed; opacity: 0.55; }
        .branding-note {
            padding: 4px 24px 16px;
            color: #6b7280;
            font-size: 0.8125rem;
        }
        .toggle-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding: 20px 24px;
            border-bottom: 1px solid #f3f4f6;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-info { flex: 1; }
        .toggle-label {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }
        .toggle-desc {
            font-size: 0.8125rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #d1d5db;
            transition: 0.2s;
            border-radius: 28px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: #ffffff;
            transition: 0.2s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: #3b82f6; }
        input:checked + .slider:before { transform: translateX(24px); }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 0;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.15s;
            font-family: inherit;
        }
        .btn-primary { background-color: #3b82f6; color: #ffffff; }
        .btn-primary:hover { background-color: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

        /* Danger / System category styling */
        .category-card.danger { border-color: #fde68a; }
        .category-card.danger .category-header {
            background: #fffbeb;
            border-bottom-color: #fde68a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .category-card.danger .category-title { color: #92400e; }
        .category-card.danger .category-header [data-lucide] { color: #b45309; width: 18px; height: 18px; }
        .toggle-row.danger input:checked + .slider { background-color: #d97706; }

        /* Maintenance message textarea row */
        .text-row {
            padding: 20px 24px;
            border-top: 1px solid #f3f4f6;
        }
        .text-row label {
            display: block;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }
        .text-row .text-desc {
            font-size: 0.8125rem;
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .text-row textarea {
            width: 100%;
            min-height: 84px;
            padding: 10px 12px;
            font-size: 0.875rem;
            font-family: inherit;
            color: #1f2937;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            resize: vertical;
            line-height: 1.5;
        }
        .text-row textarea:focus {
            outline: none;
            border-color: #d97706;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
        }

        .schedule-card-body { padding: 20px 24px; }
        .schedule-status { padding: 12px 14px; margin-bottom: 18px; border-radius: 8px; background: #f3f4f6; color: #374151; font-size: 0.875rem; }
        .schedule-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .schedule-grid label { display: block; font-size: 0.875rem; font-weight: 600; color: #111827; }
        .schedule-grid input, .schedule-grid textarea { display: block; width: 100%; margin-top: 6px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; color: #1f2937; background: #fff; }
        .schedule-grid textarea { min-height: 84px; resize: vertical; grid-column: 1 / -1; }
        .schedule-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .btn-secondary { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .btn-secondary:hover { background: #f9fafb; }
        @media (max-width: 640px) { .schedule-grid { grid-template-columns: 1fr; } .schedule-grid textarea { grid-column: auto; } }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .content { margin-left: 0; padding-top: 120px; }
            .top-navbar { left: 0; }
            .mobile-header { display: block; }
            .sidebar-overlay.active { display: block; }
        }
        @media (max-width: 768px) { .toggle-row { flex-direction: column; gap: 14px; } }

        /* Settings page visual refresh */
        :root {
            --set-ink: #17243b;
            --set-muted: #66758b;
            --set-line: #e3e9f2;
            --set-blue: #356df3;
            --set-shadow: 0 12px 32px rgba(24, 45, 82, .055);
        }

        body { background: #f3f6fb; color: var(--set-ink); }
        .page-container { max-width: 1240px; padding: 28px 30px 48px; }
        .page-header {
            position: relative;
            overflow: hidden;
            min-height: 142px;
            padding: 30px 34px;
            border: 1px solid #dce6f5;
            border-radius: 18px;
            background: linear-gradient(115deg, #fff, #f8faff 62%, #eef4ff);
            box-shadow: var(--set-shadow);
        }
        .page-header::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            top: -124px;
            right: -70px;
            border-radius: 50%;
            background: rgba(67, 122, 249, .075);
            pointer-events: none;
        }
        .page-title { position: relative; z-index: 1; gap: 14px; color: #14223a; font-size: 1.85rem; }
        .page-title [data-lucide] {
            width: 42px;
            height: 42px;
            padding: 10px;
            border-radius: 13px;
            color: var(--set-blue);
            background: #eaf0ff;
        }
        .page-subtitle { position: relative; z-index: 1; margin: 8px 0 0 56px; color: #61718a; font-size: .94rem; }

        .category-card {
            margin-bottom: 22px;
            border: 1px solid var(--set-line);
            border-radius: 16px;
            background: #fff;
            box-shadow: var(--set-shadow);
        }
        .category-header {
            min-height: 62px;
            padding: 18px 26px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(180deg, #fff, #fbfcfe);
        }
        .category-title { color: #263650; font-size: .88rem; letter-spacing: .075em; }

        .branding-list { padding: 16px 26px 8px; }
        .branding-item { border-color: #e4eaf2; border-radius: 11px; }
        .branding-item:hover, .branding-item[open] { border-color: #c7d7f6; }
        .branding-name { color: #1d2b43; font-size: .94rem; }
        .branding-status, .branding-description { color: var(--set-muted); }
        .branding-preview {
            border-color: #cbd6e5;
            background: radial-gradient(ellipse at center, #fff, #f6f8fc);
        }
        .branding-preview img { filter: drop-shadow(0 3px 5px rgba(32, 51, 79, .09)); }
        .branding-item input[type="file"] {
            min-height: 43px;
            padding: 6px;
            border: 1px solid #e1e7f0;
            border-radius: 9px;
            color: #5f6f85;
            background: #fafbfd;
        }
        .branding-item input[type="file"]::file-selector-button {
            margin-right: 10px;
            padding: 7px 11px;
            border: 0;
            border-radius: 6px;
            color: #334968;
            background: #eaf0fa;
            font: 600 .78rem Inter, sans-serif;
            cursor: pointer;
        }
        .branding-note { color: #718096; }

        .toggle-row { align-items: center; padding: 22px 26px; }
        .toggle-label { color: #1d2b43; font-size: .98rem; }
        .toggle-desc, .text-row .text-desc { color: var(--set-muted); font-size: .86rem; }
        .switch { width: 50px; height: 27px; }
        .slider { background: #d8dfeb; }
        .slider:before { width: 21px; height: 21px; }
        input:checked + .slider { background: var(--set-blue); }
        input:checked + .slider:before { transform: translateX(22px); }

        .category-card.danger { border-color: #f1dfb9; }
        .category-card.danger .category-header {
            min-height: 64px;
            background: linear-gradient(100deg, #fffaf0, #fffdf8);
        }
        .category-card.danger .category-title { color: #8b5013; }
        .category-card.danger .category-header [data-lucide] { width: 19px; height: 19px; }
        .toggle-row.danger input:checked + .slider { background: #d88925; }

        .text-row, .schedule-card-body { padding: 22px 26px; }
        .text-row textarea, .schedule-grid input, .schedule-grid textarea { border-color: #dce3ed; border-radius: 10px; }
        .text-row textarea:focus, .schedule-grid input:focus, .schedule-grid textarea:focus {
            outline: none;
            border-color: #7ca3fc;
            box-shadow: 0 0 0 3px rgba(53, 109, 243, .12);
        }
        .text-row textarea { min-height: 96px; }
        .schedule-status {
            padding: 15px 18px;
            border: 1px solid #dce7fb;
            border-left: 4px solid #4d7ff0;
            border-radius: 10px;
            color: #405574;
            background: linear-gradient(100deg, #f1f6ff, #f9fbff);
            line-height: 1.7;
        }
        .schedule-status strong { color: #244578; }
        .schedule-grid { gap: 18px; }
        .schedule-grid label { color: #263650; font-size: .85rem; }
        .schedule-grid input, .schedule-grid textarea { min-height: 44px; margin-top: 8px; padding: 11px 13px; }

        .btn { min-height: 43px; padding: 10px 17px; border-radius: 9px; font-weight: 600; }
        .btn-primary { background: var(--set-blue); box-shadow: 0 4px 10px rgba(53, 109, 243, .15); }
        .btn-primary:hover { background: #285fe4; }
        .btn-secondary { border-color: #d9e0ea; color: #3f5069; }
        .form-actions { padding: 5px 0 22px; }
        .flash { border-radius: 11px; }

        @media (max-width: 640px) {
            .schedule-grid { grid-template-columns: 1fr; }
            .schedule-grid textarea { grid-column: auto; }
        }
        @media (max-width: 768px) {
            .page-container { padding: 18px 16px 32px; }
            .page-header { min-height: auto; padding: 23px 20px; border-radius: 14px; }
            .page-title { font-size: 1.5rem; }
            .page-title [data-lucide] { width: 38px; height: 38px; }
            .page-subtitle { margin-left: 52px; font-size: .86rem; }
            .category-header, .toggle-row { padding-right: 20px; padding-left: 20px; }
            .branding-list { padding-right: 20px; padding-left: 20px; }
            .schedule-card-body, .text-row { padding-right: 20px; padding-left: 20px; }
            .toggle-row { flex-direction: column; align-items: flex-start; gap: 14px; }
            .toggle-row .switch { align-self: flex-end; margin-top: -38px; }
            .form-actions .btn, .schedule-actions .btn { width: 100%; justify-content: center; }
            .schedule-actions { flex-direction: column-reverse; }
        }
        @media (max-width: 560px) {
            .branding-item summary { grid-template-columns: 48px minmax(0, 1fr) auto; gap: 10px; padding: 9px 10px; }
            .branding-preview { width: 48px; height: 48px; }
            .branding-panel { padding: 14px; }
            .branding-upload { align-items: stretch; flex-direction: column; }
            .branding-upload .btn { justify-content: center; }
            .branding-actions .btn { flex: 1; justify-content: center; }
        }
</style>
</head>
<body>
    <?php include '../includes/preloader.php'; ?>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <h4 style="display:flex;align-items:center;gap:8px;"><img src="<?= htmlspecialchars(branding_logo_url('app'), ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:30px;height:30px;object-fit:contain;"> Civil Registry</h4>
            <button id="mobileSidebarToggle">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php include '../includes/sidebar_nav.php'; ?>
    <?php include '../includes/top_navbar.php'; ?>

    <div class="content">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i data-lucide="settings"></i>
                        System Settings
                    </h1>
                    <p class="page-subtitle">Manage system features and the logos shown across iSCAN.</p>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?= htmlspecialchars($flash['type']) ?>">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'alert-circle' : 'info') ?>"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <section class="category-card" aria-labelledby="branding-title">
                <div class="category-header">
                    <div class="category-title" id="branding-title">Branding Logos</div>
                </div>
                <div class="branding-list">
                    <?php foreach ($logoDefinitions as $slot => $definition):
                        $previewUrl = branding_logo_url($slot);
                        $storedPath = (string) get_setting($definition['setting_key'], '');
                        $hasCustomLogo = trim($storedPath) !== '';
                        $isLogoHidden = branding_is_logo_hidden($storedPath);
                        $logoStatus = $isLogoHidden ? 'Hidden' : ($previewUrl === '' ? 'Logo unavailable' : ($hasCustomLogo ? 'Custom logo' : 'Default logo'));
                        $slotId = preg_replace('/[^a-z0-9_-]/i', '-', $slot);
                    ?>
                        <details class="branding-item">
                            <summary>
                                <span class="branding-preview">
                                    <?php if ($isLogoHidden): ?><span class="branding-preview-empty">Hidden</span><?php elseif ($previewUrl !== ''): ?>
                                        <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                    <?php else: ?>
                                        <span class="branding-preview-empty">No logo</span>
                                    <?php endif; ?>
                                </span>
                                <span class="branding-copy">
                                    <span class="branding-name"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="branding-status"><?= htmlspecialchars($logoStatus, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <span class="branding-summary-action">Change <i data-lucide="chevron-down" aria-hidden="true"></i></span>
                            </summary>
                            <div class="branding-panel">
                                <p class="branding-description"><?= htmlspecialchars($definition['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <form class="branding-upload" method="post" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="form_action" value="branding">
                                    <input type="hidden" name="branding_action" value="upload">
                                    <input type="hidden" name="logo_slot" value="<?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="branding-file-group">
                                        <label class="toggle-label" for="logo-file-<?= htmlspecialchars($slotId, ENT_QUOTES, 'UTF-8') ?>">Choose PNG or JPEG (up to 5 MB)</label>
                                        <input id="logo-file-<?= htmlspecialchars($slotId, ENT_QUOTES, 'UTF-8') ?>"
                                               type="file"
                                               name="logo_file"
                                               accept="image/png,image/jpeg"
                                               required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i data-lucide="upload"></i> Upload Logo</button>
                                </form>
                                <form class="branding-actions branding-secondary-actions" method="post" action="">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="form_action" value="branding">
                                    <input type="hidden" name="logo_slot" value="<?= htmlspecialchars($slot, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if ($slot !== 'app'): ?>
                                        <button type="submit" name="branding_action" value="hide" class="btn btn-secondary" <?= $isLogoHidden ? 'disabled' : '' ?>>
                                            <i data-lucide="eye-off"></i> Hide Logo
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="branding_action" value="reset" class="btn btn-secondary" <?= $hasCustomLogo ? '' : 'disabled' ?>>
                                        <i data-lucide="rotate-ccw"></i> Reset to Default
                                    </button>
                                </form>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
                <p class="branding-note">The CRF logo settings apply to Forms 1A, 2A, and 3A. Use Hide Logo to omit a logo. Reset to Default restores the configured fallback.</p>
            </section>

            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_action" value="save_settings">

                <?php
                $by_category = [];
                foreach ($TOGGLE_DEFINITIONS as $def) {
                    $by_category[$def['category']][] = $def;
                }
                foreach ($by_category as $category => $defs):
                    $is_system = ($category === 'System');
                ?>
                    <div class="category-card <?= $is_system ? 'danger' : '' ?>">
                        <div class="category-header">
                            <?php if ($is_system): ?>
                                <i data-lucide="alert-triangle"></i>
                            <?php endif; ?>
                            <div class="category-title"><?= htmlspecialchars($category) ?></div>
                        </div>
                        <?php foreach ($defs as $def):
                            $checked = (bool) get_setting($def['key'], false);
                            $is_danger = !empty($def['danger']);
                        ?>
                            <div class="toggle-row <?= $is_danger ? 'danger' : '' ?>">
                                <div class="toggle-info">
                                    <div class="toggle-label"><?= htmlspecialchars($def['label']) ?></div>
                                    <div class="toggle-desc"><?= htmlspecialchars($def['description']) ?></div>
                                </div>
                                <label class="switch" title="<?= htmlspecialchars($def['label']) ?>">
                                    <input type="checkbox"
                                           name="<?= htmlspecialchars($def['key']) ?>"
                                           value="1"
                                           <?= $checked ? 'checked' : '' ?>
                                           <?= $is_danger ? 'data-confirm="1" data-confirm-label="' . htmlspecialchars($def['label'], ENT_QUOTES) . '"' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($is_system): ?>
                            <?php $maintenance_message = (string) get_setting('maintenance_message', ''); ?>
                            <div class="text-row">
                                <label for="maintenance_message">Maintenance Message / ETA</label>
                                <div class="text-desc">Shown to non-admin users on the maintenance page. Include an ETA here if known (e.g., &ldquo;Back online by 5:00 PM&rdquo;).</div>
                                <textarea id="maintenance_message"
                                          name="maintenance_message"
                                          maxlength="500"
                                          placeholder="The system is undergoing scheduled maintenance. Please try again shortly."><?= htmlspecialchars($maintenance_message) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
            <?php
                $canCancelSchedule = $maintenanceSchedule !== null && $storedMaintenanceSchedule && ($storedMaintenanceSchedule['status'] ?? '') === 'scheduled';
                $scheduleId = $canCancelSchedule ? (string)$storedMaintenanceSchedule['id'] : '';
            ?>
            <section class="category-card" aria-labelledby="schedule-title">
                <div class="category-header"><div class="category-title" id="schedule-title">Scheduled Maintenance</div></div>
                <div class="schedule-card-body">
                    <div class="schedule-status">
                        <strong>Current status: <?= htmlspecialchars($maintenanceStatusLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if ($maintenanceSchedule): ?>
                            <br>Window: <?= htmlspecialchars($maintenanceSchedule['starts_at_display'], ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($maintenanceSchedule['ends_at_display'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                    <p class="text-desc">Set one maintenance window. Users receive a notice when you save it and again when it starts. App timezone: <?= htmlspecialchars(date_default_timezone_get(), ENT_QUOTES, 'UTF-8') ?>. Emergency override remains available above.</p>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="form_action" value="maintenance_schedule">
                        <input type="hidden" name="schedule_id" value="<?= htmlspecialchars($scheduleId, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="schedule-grid">
                            <label for="maintenance_starts_at">Start date and time
                                <input id="maintenance_starts_at" name="maintenance_starts_at" type="datetime-local" step="60" value="<?= htmlspecialchars($scheduleInput['starts_at'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <label for="maintenance_ends_at">End date and time
                                <input id="maintenance_ends_at" name="maintenance_ends_at" type="datetime-local" step="60" value="<?= htmlspecialchars($scheduleInput['ends_at'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </label>
                            <label for="scheduled_maintenance_message">Notice for users
                                <textarea id="scheduled_maintenance_message" name="scheduled_maintenance_message" maxlength="500" required><?= htmlspecialchars($scheduleInput['message'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </label>
                        </div>
                        <div class="schedule-actions">
                            <?php if ($canCancelSchedule): ?>
                                <button type="submit" name="schedule_action" value="cancel" class="btn btn-secondary" onclick="return confirm('Cancel this maintenance schedule? Users will be notified.');">Cancel Schedule</button>
                            <?php endif; ?>
                            <button type="submit" name="schedule_action" value="save" class="btn btn-primary"><i data-lucide="calendar-clock"></i> Save Schedule</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <?php include '../includes/sidebar_scripts.php'; ?>
    <script>
        if (window.lucide) { lucide.createIcons(); }

        // Confirmation prompt for destructive toggles (e.g. Maintenance Mode).
        // Fires only when the user is turning the toggle ON.
        document.querySelectorAll('input[type="checkbox"][data-confirm="1"]').forEach(function(cb) {
            cb.addEventListener('change', function(e) {
                if (!cb.checked) return; // turning OFF needs no confirmation
                var label = cb.getAttribute('data-confirm-label') || 'this setting';
                var ok = window.confirm(
                    'Enable ' + label + '?\n\n' +
                    'All non-admin users will be logged out on their next request. ' +
                    'Only Administrators may use the system while the override or schedule is active. Turning the override OFF returns control to the scheduled window.'
                );
                if (!ok) {
                    cb.checked = false;
                }
            });
        });
    </script>
</body>
</html>
