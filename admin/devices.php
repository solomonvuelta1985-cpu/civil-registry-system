<?php
/**
 * Registered Devices Management
 * iScan Civil Registry Records Management System
 *
 * Admin-only page to register, view, and revoke device fingerprints.
 * Works with the ENABLE_DEVICE_LOCK setting in .env to restrict
 * system access to only pre-approved physical devices.
 */

require_once '../includes/session_config.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/device_auth.php';

requireAuth();
requireAdmin();
setSecurityHeaders();

$devices      = getAllDevices();
$activeCount  = countActiveDevices();
$lockEnabled  = isDeviceLockEnabled();
$csrfField    = csrfTokenField();
$csrfMeta     = csrfTokenMeta();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Devices - <?= htmlspecialchars(APP_SHORT_NAME) ?></title>
    <?= $csrfMeta ?>

    <?= google_fonts_tag('Inter:wght@300;400;500;600;700') ?>
    <link rel="stylesheet" href="<?= asset_url('fontawesome_css') ?>">
    <script src="<?= asset_url('lucide') ?>"></script>
    <link rel="stylesheet" href="<?= asset_url('notiflix_css') ?>">
    <script src="<?= asset_url('notiflix_js') ?>"></script>

    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="../assets/js/device-fingerprint.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', 'Segoe UI', Arial, sans-serif; background: #f4f6f9; color: #1a202c; }

        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; color: #1a202c; }
        .page-header p  { color: #718096; font-size: 0.95rem; margin-top: 4px; }

        /* Banner */
        .banner {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px; border-radius: 10px; margin-bottom: 22px;
            font-size: 0.9rem; font-weight: 500;
        }
        .banner.warning { background: #fffbeb; border: 1px solid #f6ad55; color: #744210; }
        .banner.success { background: #f0fff4; border: 1px solid #68d391; color: #22543d; }
        .banner i { font-size: 1.1rem; flex-shrink: 0; }

        /* Stats row */
        .stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 18px 22px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06); flex: 1; min-width: 140px;
        }
        .stat-card .label { font-size: 0.75rem; color: #718096; text-transform: uppercase; letter-spacing: 0.06em; }
        .stat-card .value { font-size: 1.8rem; font-weight: 700; color: #2d3748; margin-top: 4px; }

        /* Card */
        .card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06); overflow: hidden;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px; border-bottom: 1px solid #e2e8f0;
        }
        .card-header h2 { font-size: 1rem; font-weight: 600; color: #2d3748; }

        /* Register button */
        .btn-register {
            display: inline-flex; align-items: center; gap: 8px;
            background: #3182ce; color: #fff; border: none; border-radius: 8px;
            padding: 10px 18px; font-size: 0.9rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-register:hover { background: #2b6cb0; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f7fafc; padding: 12px 16px;
            text-align: left; font-size: 0.75rem; font-weight: 600;
            color: #4a5568; text-transform: uppercase; letter-spacing: 0.06em;
            border-bottom: 1px solid #e2e8f0;
        }
        tbody td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; font-size: 0.9rem; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f7fafc; }

        /* Fingerprint display */
        .fp-code {
            font-family: 'Courier New', monospace; font-size: 0.78rem;
            color: #4a5568; background: #edf2f7; padding: 4px 8px;
            border-radius: 4px; cursor: pointer; display: inline-block;
            max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .fp-code:hover { background: #e2e8f0; }

        /* Status badge */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px; font-size: 0.78rem; font-weight: 600;
        }
        .badge.active  { background: #c6f6d5; color: #22543d; }
        .badge.revoked { background: #fed7d7; color: #742a2a; }

        /* Action buttons */
        .btn-action {
            padding: 6px 12px; border-radius: 6px; border: none;
            font-size: 0.82rem; font-weight: 500; cursor: pointer; transition: background 0.2s;
        }
        .btn-revoke     { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .btn-revoke:hover { background: #fed7d7; }
        .btn-reactivate { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
        .btn-reactivate:hover { background: #c6f6d5; }

        .empty-state { text-align: center; padding: 52px; color: #a0aec0; }
        .empty-state svg { width: 52px; height: 52px; margin-bottom: 14px; opacity: 0.4; }
        .empty-state p { font-size: 0.95rem; }

        /* Modal overlay */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #fff; border-radius: 16px; padding: 32px;
            width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal-box h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 6px; }
        .modal-box p  { color: #718096; font-size: 0.88rem; margin-bottom: 20px; }
        .modal-fp {
            font-family: monospace; font-size: 0.75rem; color: #4a5568;
            background: #edf2f7; padding: 10px; border-radius: 6px;
            word-break: break-all; margin-bottom: 18px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid #cbd5e0;
            border-radius: 8px; font-size: 0.9rem; font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: #3182ce; box-shadow: 0 0 0 3px rgba(49,130,206,0.15);
        }
        .form-group textarea { resize: vertical; min-height: 70px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 8px; }
        .btn-cancel { padding: 10px 18px; background: #edf2f7; color: #4a5568; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; }
        .btn-save   { padding: 10px 22px; background: #3182ce; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>

<?php require_once '../includes/top_navbar.php'; ?>
<?php require_once '../includes/sidebar_nav.php'; ?>

<div class="content">

    <div class="page-header">
        <h1><i data-lucide="monitor-check" style="display:inline;vertical-align:middle;margin-right:8px;"></i>Registered Devices</h1>
        <p>Manage which physical devices are allowed to access this system.</p>
    </div>

    <!-- Device lock status banner -->
    <?php if (!$lockEnabled): ?>
    <div class="banner warning">
        <i data-lucide="alert-triangle"></i>
        <div>
            <strong>Device Lock is DISABLED.</strong>
            All devices can currently log in. After registering your devices below,
            set <code>ENABLE_DEVICE_LOCK=true</code> in your <code>.env</code> file to enforce the restriction.
        </div>
    </div>
    <?php else: ?>
    <div class="banner success">
        <i data-lucide="shield-check"></i>
        <strong>Device Lock is ACTIVE.</strong>&nbsp;
        Only registered devices listed below can log in.
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="label">Registered Devices</div>
            <div class="value"><?= count($devices) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Active</div>
            <div class="value"><?= $activeCount ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Revoked</div>
            <div class="value"><?= count($devices) - $activeCount ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Lock Status</div>
            <div class="value" style="font-size:1.1rem;padding-top:6px;">
                <?= $lockEnabled
                    ? '<span style="color:#22543d;">&#10003; Enforced</span>'
                    : '<span style="color:#c05621;">&#9888; Disabled</span>' ?>
            </div>
        </div>
    </div>

    <!-- Devices table -->
    <div class="card">
        <div class="card-header">
            <h2>Device Registry</h2>
            <button class="btn-register" onclick="openRegisterModal()">
                <i data-lucide="plus-circle"></i> Register This Device
            </button>
        </div>

        <?php if (empty($devices)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8M12 17v4"/>
            </svg>
            <p>No devices registered yet.<br>Click <strong>Register This Device</strong> to add the current PC.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Device Name</th>
                    <th>Device ID (fingerprint)</th>
                    <th>Registered By</th>
                    <th>Registered</th>
                    <th>Last Seen</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($devices as $i => $d): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($d['device_name']) ?></strong>
                        <?php if (!empty($d['notes'])): ?>
                            <br><small style="color:#a0aec0;"><?= htmlspecialchars($d['notes']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="fp-code"
                              title="<?= htmlspecialchars($d['fingerprint_hash']) ?>"
                              onclick="copyText('<?= htmlspecialchars($d['fingerprint_hash']) ?>', this)">
                            <?= substr($d['fingerprint_hash'], 0, 18) ?>…
                        </span>
                    </td>
                    <td><?= htmlspecialchars($d['registered_by_name'] ?? 'System') ?></td>
                    <td style="white-space:nowrap;color:#718096;font-size:0.83rem;">
                        <?= date('M d, Y', strtotime($d['registered_at'])) ?>
                    </td>
                    <td style="white-space:nowrap;color:#718096;font-size:0.83rem;">
                        <?= $d['last_seen_at']
                            ? date('M d, Y g:i A', strtotime($d['last_seen_at'])) . '<br><small>' . htmlspecialchars($d['last_seen_ip'] ?? '') . '</small>'
                            : '<em style="color:#cbd5e0;">Never</em>' ?>
                    </td>
                    <td>
                        <span class="badge <?= strtolower($d['status']) ?>">
                            <?= $d['status'] === 'Active'
                                ? '<i data-lucide="check-circle" style="width:13px;height:13px;"></i>'
                                : '<i data-lucide="x-circle" style="width:13px;height:13px;"></i>' ?>
                            <?= htmlspecialchars($d['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($d['status'] === 'Active'): ?>
                            <button class="btn-action btn-revoke"
                                    onclick="confirmAction(<?= $d['id'] ?>, 'revoke', '<?= htmlspecialchars(addslashes($d['device_name'])) ?>')">
                                Revoke
                            </button>
                        <?php else: ?>
                            <button class="btn-action btn-reactivate"
                                    onclick="confirmAction(<?= $d['id'] ?>, 'reactivate', '<?= htmlspecialchars(addslashes($d['device_name'])) ?>')">
                                Reactivate
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Register Device Modal -->
<div class="modal-overlay" id="registerModal" onclick="handleOverlayClick(event)">
    <div class="modal-box">
        <h3><i data-lucide="monitor-plus" style="display:inline;vertical-align:middle;margin-right:6px;"></i>Register This Device</h3>
        <p>The current device's fingerprint will be saved. Give it a recognizable name.</p>

        <div class="modal-fp" id="modalFpDisplay">Generating fingerprint...</div>

        <form id="registerForm">
            <?= $csrfField ?>
            <input type="hidden" id="modalFpInput" name="device_fingerprint" value="">

            <div class="form-group">
                <label for="deviceName">Device Name <span style="color:#e53e3e;">*</span></label>
                <input type="text" id="deviceName" name="device_name"
                       placeholder="e.g. Front Desk PC, Encoder Station 2"
                       maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="deviceNotes">Notes (optional)</label>
                <textarea id="deviceNotes" name="notes"
                          placeholder="e.g. Room 3 desktop, Windows 11"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">Register Device</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/notiflix-config.js"></script>
<script>
    lucide.createIcons();

    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Modal ──────────────────────────────────────────────────────────────
    async function openRegisterModal() {
        document.getElementById('registerModal').classList.add('open');
        const fp = await window.DeviceFingerprint.get();
        document.getElementById('modalFpDisplay').textContent = fp;
        document.getElementById('modalFpInput').value = fp;
    }

    function closeModal() {
        document.getElementById('registerModal').classList.remove('open');
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById('registerModal')) closeModal();
    }

    // ── Register form submit ───────────────────────────────────────────────
    document.getElementById('registerForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const fp   = document.getElementById('modalFpInput').value;
        const name = document.getElementById('deviceName').value.trim();
        const notes = document.getElementById('deviceNotes').value.trim();

        if (!fp || !name) {
            Notiflix.Notify.warning('Device name is required.');
            return;
        }

        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('device_fingerprint', fp);
        formData.append('device_name', name);
        formData.append('notes', notes);

        try {
            const res  = await fetch('../api/device_save.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                Notiflix.Notify.success(data.message);
                closeModal();
                setTimeout(() => location.reload(), 1400);
            } else {
                Notiflix.Notify.failure(data.message);
            }
        } catch (err) {
            Notiflix.Notify.failure('Network error. Please try again.');
        }
    });

    // ── Revoke / Reactivate ───────────────────────────────────────────────
    function confirmAction(deviceId, action, deviceName) {
        const label = action === 'revoke' ? 'Revoke' : 'Reactivate';
        const color = action === 'revoke' ? '#e53e3e' : '#38a169';

        Notiflix.Confirm.show(
            label + ' Device',
            label + ' "' + deviceName + '"?',
            label,
            'Cancel',
            async () => {
                const formData = new FormData();
                formData.append('csrf_token', CSRF_TOKEN);
                formData.append('device_id', deviceId);
                formData.append('action', action);

                try {
                    const res  = await fetch('../api/device_delete.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        Notiflix.Notify.success(data.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        Notiflix.Notify.failure(data.message);
                    }
                } catch (err) {
                    Notiflix.Notify.failure('Network error.');
                }
            },
            null,
            { okButtonBackground: color }
        );
    }

    // ── Copy fingerprint to clipboard ─────────────────────────────────────
    function copyText(text, el) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = el.textContent;
            el.textContent = 'Copied!';
            setTimeout(() => { el.textContent = orig; }, 1800);
        });
    }
</script>
</body>
</html>
