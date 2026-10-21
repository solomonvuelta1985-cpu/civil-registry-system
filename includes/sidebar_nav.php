<?php
require_once __DIR__ . '/branding.php';
$current_page=basename($_SERVER['PHP_SELF']);
if(!function_exists('isAdmin'))require_once __DIR__.'/auth.php';
require_once __DIR__ . '/security.php';
$__sidebar_user_name = function_exists('getUserFullName') ? getUserFullName() : 'User';
$__sidebar_user_role = function_exists('getUserRole') ? getUserRole() : '';
$__sidebar_user_initial = strtoupper(substr(trim((string)$__sidebar_user_name), 0, 1)) ?: 'U';
$__is_admin=function_exists('isAdmin')?isAdmin():false;$__is_super_admin=function_exists('isSuperAdmin')?isSuperAdmin():false;$__pending_devices=0;
if($__is_super_admin){if(!function_exists('countPendingDevices'))@require_once __DIR__.'/device_auth.php';if(function_exists('countPendingDevices')){try{$__pending_devices=countPendingDevices();}catch(Throwable $e){$__pending_devices=0;}}}
?>
<nav class="sidebar" id="sidebar"><div class="sidebar-header"><div class="sidebar-logo"><img src="<?= htmlspecialchars(branding_logo_url('app'), ENT_QUOTES, 'UTF-8') ?>" alt="CRDMS Logo"></div><div class="sidebar-brand-copy"><h4><span>CRDMS</span></h4><small class="sidebar-brand-subtitle">Civil Registry Documents Management System</small></div></div><ul class="sidebar-menu">
<li class="sidebar-heading">Dashboard</li><li><a href="<?= BASE_URL ?>admin/dashboard.php" class="<?php echo ($current_page=='dashboard.php'||$current_page=='dashboard_modern.php')?'active':''; ?>" title="Dashboard Overview"><i data-lucide="layout-grid"></i> <span>Dashboard</span></a></li>
<li class="sidebar-divider"></li><li class="sidebar-heading">Registration</li><li><a href="<?= BASE_URL ?>public/certificate_of_live_birth.php" class="<?php echo $current_page=='certificate_of_live_birth.php'?'active':''; ?>" title="Register Birth Certificate"><i data-lucide="file-text"></i> <span>Birth Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/certificate_of_marriage.php" class="<?php echo $current_page=='certificate_of_marriage.php'?'active':''; ?>" title="Register Marriage Certificate"><i data-lucide="file-signature"></i> <span>Marriage Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/certificate_of_death.php" class="<?php echo $current_page=='certificate_of_death.php'?'active':''; ?>" title="Register Death Certificate"><i data-lucide="file-minus"></i> <span>Death Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/application_for_marriage_license.php" class="<?php echo $current_page=='application_for_marriage_license.php'?'active':''; ?>" title="Marriage License Application"><i data-lucide="clipboard-list"></i> <span>Marriage License</span></a></li>
<li class="sidebar-divider"></li><li class="sidebar-heading">Records</li>
<?php
$__record_groups = [
    'civil' => [
        'label' => 'Civil Records',
        'icon' => 'folder-open',
        'items' => [
            ['page' => 'birth_records.php', 'path' => 'public/birth_records.php', 'icon' => 'folder', 'label' => 'Birth Records', 'title' => 'View and manage birth records'],
            ['page' => 'marriage_records.php', 'path' => 'public/marriage_records.php', 'icon' => 'folders', 'label' => 'Marriage Records', 'title' => 'View and manage marriage records'],
            ['page' => 'death_records.php', 'path' => 'public/death_records.php', 'icon' => 'folder-closed', 'label' => 'Death Records', 'title' => 'View and manage death records'],
            ['page' => 'marriage_license_records.php', 'path' => 'public/marriage_license_records.php', 'icon' => 'folder-search', 'label' => 'License Records', 'title' => 'View and manage marriage license applications'],
        ],
    ],
];
$__crf_record_items = [];
if (function_exists('hasPermission') && hasPermission('birth_crf_1a_view')) {
    $__crf_record_items[] = ['page' => 'crf_1a_records.php', 'path' => 'public/crf_1a_records.php', 'icon' => 'file-check-2', 'label' => 'CRF No. 1A Records', 'title' => 'CRF No. 1A Issuance Records'];
}
if (function_exists('hasPermission') && hasPermission('death_crf_2a_view')) {
    $__crf_record_items[] = ['page' => 'crf_2a_records.php', 'path' => 'public/crf_2a_records.php', 'icon' => 'file-heart', 'label' => 'CRF No. 2A Records', 'title' => 'CRF No. 2A Issuance Records'];
}
if (function_exists('hasPermission') && hasPermission('marriage_crf_3a_view')) {
    $__crf_record_items[] = ['page' => 'crf_3a_records.php', 'path' => 'public/crf_3a_records.php', 'icon' => 'heart-handshake', 'label' => 'CRF No. 3A Records', 'title' => 'CRF No. 3A Issuance Records'];
}
if ($__crf_record_items) {
    $__record_groups['crf'] = [
        'label' => 'CRF Records',
        'icon' => 'file-check-2',
        'items' => $__crf_record_items,
    ];
}
$__active_record_group = null;
foreach ($__record_groups as $__record_group_key => $__record_group) {
    foreach ($__record_group['items'] as $__record_item) {
        if ($current_page === $__record_item['page']) {
            $__active_record_group = $__record_group_key;
            break 2;
        }
    }
}
?>
<?php foreach ($__record_groups as $__record_group_key => $__record_group): ?>
    <?php
        $__record_group_id = 'records-group-' . $__record_group_key;
        $__record_group_open = $__active_record_group === $__record_group_key;
    ?>
    <li class="sidebar-group sidebar-group--tree<?= $__record_group_open ? ' is-open' : '' ?>">
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle aria-label="<?= htmlspecialchars($__record_group['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($__record_group['label'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="<?= $__record_group_open ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($__record_group_id, ENT_QUOTES, 'UTF-8') ?>">
            <i data-lucide="<?= htmlspecialchars($__record_group['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <span class="sidebar-group-label"><?= htmlspecialchars($__record_group['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <i class="sidebar-group-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
        </button>
        <ul class="sidebar-submenu" id="<?= htmlspecialchars($__record_group_id, ENT_QUOTES, 'UTF-8') ?>"<?= $__record_group_open ? '' : ' hidden' ?>>
            <?php foreach ($__record_group['items'] as $__record_item): ?>
                <?php $__record_item_active = $current_page === $__record_item['page']; ?>
                <li>
                    <a href="<?= BASE_URL . $__record_item['path'] ?>" class="<?= $__record_item_active ? 'active' : '' ?>" title="<?= htmlspecialchars($__record_item['title'], ENT_QUOTES, 'UTF-8') ?>"<?= $__record_item_active ? ' aria-current="page"' : '' ?>>
                        <i data-lucide="<?= htmlspecialchars($__record_item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($__record_item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </li>
<?php endforeach; ?>
<li><a href="<?= BASE_URL ?>public/folder_browser.php" class="<?php echo $current_page=='folder_browser.php'?'active':''; ?>" title="Browse Records by Folder"><i data-lucide="folder-tree"></i> <span>Folder Browser</span></a></li><li><a href="<?= BASE_URL ?>public/double_registration.php" class="<?php echo $current_page=='double_registration.php'?'active':''; ?>" title="Double Registration Detection (PSA MC 2019-23)"><i data-lucide="link-2"></i> <span>Double Registration</span></a></li><li><a href="<?= BASE_URL ?>public/family_relations.php" class="<?php echo $current_page=='family_relations.php'?'active':''; ?>" title="Discover siblings and related civil registry records"><i data-lucide="users"></i> <span>Family Relations</span></a></li>
<?php if(defined('RA9048_FEATURE_ENABLED')&&RA9048_FEATURE_ENABLED): ?><li class="sidebar-divider"></li><li class="sidebar-heading">RA 9048/10172</li><li><a href="<?= BASE_URL ?>public/ra9048/index.php" class="<?php echo ($current_page=='index.php'&&strpos($_SERVER['SCRIPT_NAME'],'ra9048')!==false)?'active':''; ?>" title="RA 9048/10172 Transactions"><i data-lucide="file-pen"></i> <span>Transactions</span></a></li><li><a href="<?= BASE_URL ?>public/ra9048/records.php" class="<?php echo ($current_page=='records.php'&&strpos($_SERVER['SCRIPT_NAME'],'ra9048')!==false)?'active':''; ?>" title="View RA 9048 Records"><i data-lucide="folder-open"></i> <span>RA 9048 Records</span></a></li><?php endif; ?>
<?php if($__is_admin): ?><li class="sidebar-divider"></li><li class="sidebar-heading">Archives & Trash</li><li><a href="<?= BASE_URL ?>admin/archives.php" class="<?php echo $current_page=='archives.php'?'active':''; ?>" title="Archived Records"><i data-lucide="archive"></i> <span>Archives</span></a></li><li><a href="<?= BASE_URL ?>public/trash.php" class="<?php echo $current_page=='trash.php'?'active':''; ?>" title="View, Restore &amp; Permanently Delete Records"><i data-lucide="trash-2"></i> <span>Trash</span></a></li><?php endif; ?>
<li class="sidebar-divider"></li><li class="sidebar-heading">Reports</li><li><a href="<?= BASE_URL ?>admin/reports.php" class="<?php echo $current_page=='reports.php'?'active':''; ?>" title="Generate &amp; View Reports"><i data-lucide="bar-chart-3"></i> <span>Reports</span></a></li>
<?php if($__is_admin): ?><li class="sidebar-divider"></li><li class="sidebar-heading">Maintenance</li>
<?php
$__maintenance_groups = [
    'pdf-operations' => [
        'label' => 'PDF Operations',
        'icon' => 'file-check',
        'items' => [
            ['page' => 'pdf_integrity_report.php', 'path' => 'admin/pdf_integrity_report.php', 'icon' => 'file-check', 'label' => 'PDF Inventory', 'title' => 'PDF Inventory &amp; Integrity'],
            ['page' => 'pdf_reconciliation.php', 'path' => 'admin/pdf_reconciliation.php', 'icon' => 'scan-search', 'label' => 'PDF Reconciliation', 'title' => 'Verify active database records against PDFs'],
            ['page' => 'pdf_backup_manager.php', 'path' => 'admin/pdf_backup_manager.php', 'icon' => 'archive-restore', 'label' => 'PDF Versions', 'title' => 'PDF Version History &amp; Restore'],
            ['page' => 'reorganize_uploads.php', 'path' => 'admin/reorganize_uploads.php', 'icon' => 'folder-sync', 'label' => 'Reorganize Uploads', 'title' => 'Reorganize Upload Folders'],
        ],
    ],
    'backup-restore' => [
        'label' => 'Backup &amp; Restore',
        'icon' => 'archive-restore',
        'items' => [
            ['page' => 'pdf_protection_setup.php', 'path' => 'admin/pdf_protection_setup.php', 'icon' => 'hard-drive-download', 'label' => 'PDF Backup &amp; Recovery', 'title' => 'Configure PDF Backup &amp; Recovery'],
            ['page' => 'pdf_recovery_review.php', 'path' => 'admin/pdf_recovery_review.php', 'icon' => 'clipboard-check', 'label' => 'Recovery Review', 'title' => 'Review and approve PDF recovery mappings'],
            ['page' => 'database_backups.php', 'path' => 'admin/database_backups.php', 'icon' => 'database-backup', 'label' => 'Database Backups', 'title' => 'Create and verify full database backups'],
            ['page' => 'database_restore_preview.php', 'path' => 'admin/database_restore_preview.php', 'icon' => 'database-zap', 'label' => 'Restore Preview', 'title' => 'Validate backups in an isolated staging database'],
            ['page' => 'production_restore.php', 'path' => 'admin/production_restore.php', 'icon' => 'shield-check', 'label' => 'Controlled Restore', 'title' => 'Two-person controlled production PDF restore'],
            ['page' => 'database_live_restore.php', 'path' => 'admin/database_live_restore.php', 'icon' => 'database-zap', 'label' => 'Database Restore', 'title' => 'Two-person full database restore and rollback'],
        ],
    ],
    'system' => [
        'label' => 'System',
        'icon' => 'server-cog',
        'items' => [
            ['page' => 'devices.php', 'path' => 'admin/devices.php', 'icon' => 'monitor', 'label' => 'Devices', 'title' => 'Registered Devices', 'pending_device_badge' => true],
            ['page' => 'runtime_requirements.php', 'path' => 'admin/runtime_requirements.php', 'icon' => 'server-cog', 'label' => 'PHP-FPM Check', 'title' => 'Check NAS PHP-FPM production requirements'],
        ],
    ],
];
if (!$__is_super_admin) {
    unset($__maintenance_groups['backup-restore'], $__maintenance_groups['system']);
    $__maintenance_groups['pdf-operations']['items'] = array_values(array_filter(
        $__maintenance_groups['pdf-operations']['items'],
        static fn($item) => in_array($item['page'], ['pdf_integrity_report.php', 'pdf_reconciliation.php'], true)
    ));
}
$__active_maintenance_group = null;
foreach ($__maintenance_groups as $__group_key => $__group) {
    foreach ($__group['items'] as $__item) {
        if ($current_page === $__item['page']) {
            $__active_maintenance_group = $__group_key;
            break 2;
        }
    }
}
?>
<?php foreach ($__maintenance_groups as $__group_key => $__group): ?>
    <?php
        $__group_id = 'maintenance-group-' . $__group_key;
        $__group_open = $__active_maintenance_group === $__group_key;
    ?>
    <li class="sidebar-group sidebar-group--tree<?= $__group_open ? ' is-open' : '' ?>">
        <button type="button" class="sidebar-group-toggle" data-sidebar-group-toggle aria-label="<?= $__group['label'] ?>" title="<?= $__group['label'] ?>" aria-expanded="<?= $__group_open ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($__group_id, ENT_QUOTES, 'UTF-8') ?>">
            <i data-lucide="<?= htmlspecialchars($__group['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <span class="sidebar-group-label"><?= $__group['label'] ?></span>
            <i class="sidebar-group-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
        </button>
        <ul class="sidebar-submenu" id="<?= htmlspecialchars($__group_id, ENT_QUOTES, 'UTF-8') ?>"<?= $__group_open ? '' : ' hidden' ?>>
            <?php foreach ($__group['items'] as $__item): ?>
                <?php $__item_active = $current_page === $__item['page']; ?>
                <li>
                    <a href="<?= BASE_URL . $__item['path'] ?>" class="<?= $__item_active ? 'active' : '' ?>" title="<?= $__item['title'] ?>"<?= $__item_active ? ' aria-current="page"' : '' ?>>
                        <i data-lucide="<?= htmlspecialchars($__item['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span><?= $__item['label'] ?><?php if (!empty($__item['pending_device_badge']) && $__pending_devices > 0): ?>
                            <span style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 6px;margin-left:6px;background:#e53e3e;color:#fff;font-size:.7rem;font-weight:700;border-radius:999px;vertical-align:middle;"><?= (int) $__pending_devices ?></span>
                        <?php endif; ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </li>
<?php endforeach; ?>
<li class="sidebar-divider"></li><li class="sidebar-heading">Administration</li><li><a href="<?= BASE_URL ?>admin/users.php" class="<?php echo $current_page=='users.php'?'active':''; ?>" title="Manage System Users"><i data-lucide="users"></i> <span>Users</span></a></li><li><a href="<?= BASE_URL ?>admin/security_logs.php" class="<?php echo $current_page=='security_logs.php'?'active':''; ?>" title="Security Logs"><i data-lucide="shield"></i> <span>Security Logs</span></a></li><li><a href="<?= BASE_URL ?>admin/activity_logs.php" class="<?php echo $current_page=='activity_logs.php'?'active':''; ?>" title="User Activity Logs"><i data-lucide="history"></i> <span>Activity Logs</span></a></li><?php if($__is_super_admin): ?><li><a href="<?= htmlspecialchars(BASE_URL . 'public/mfa_management.php', ENT_QUOTES, 'UTF-8') ?>" class="<?= $current_page === 'mfa_management.php' ? 'active' : '' ?>" title="Manage authenticator devices"><i data-lucide="shield-check"></i> <span>My MFA</span></a></li><li><a href="<?= BASE_URL ?>admin/settings.php" class="<?php echo $current_page=='settings.php'?'active':''; ?>" title="System Configuration"><i data-lucide="settings"></i> <span>Settings</span></a></li><?php endif; ?><?php endif; ?>
</ul>
<div class="sidebar-mobile-account" aria-label="Account actions">
    <div class="sidebar-mobile-account-user">
        <span class="sidebar-mobile-account-avatar" aria-hidden="true"><?= htmlspecialchars($__sidebar_user_initial, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="sidebar-mobile-account-identity">
            <strong><?= htmlspecialchars($__sidebar_user_name, ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars($__sidebar_user_role, ENT_QUOTES, 'UTF-8') ?></small>
        </span>
    </div>
    <div class="sidebar-mobile-account-actions">
        <button type="button" class="sidebar-mobile-password-button" data-change-password-open aria-haspopup="dialog" aria-expanded="false" aria-controls="changePasswordModal">
            <i data-lucide="key-round" aria-hidden="true"></i><span>Change Password</span>
        </button>
        <a class="sidebar-mobile-logout-button" href="<?= htmlspecialchars(BASE_URL . 'public/logout.php', ENT_QUOTES, 'UTF-8') ?>">
            <i data-lucide="log-out" aria-hidden="true"></i><span>Logout</span>
        </a>
    </div>
</div>
</nav>
<script>
(function () {
    const sidebarMenu = document.querySelector('#sidebar .sidebar-menu');
    if (!sidebarMenu) return;

    const scrollStorageKey = 'crdmsSidebarMenuScrollTop';
    try {
        const savedScrollTop = sessionStorage.getItem(scrollStorageKey);
        if (savedScrollTop !== null) {
            const scrollTop = Math.max(0, Number(savedScrollTop) || 0);
            sessionStorage.removeItem(scrollStorageKey);
            const restoreScroll = () => { sidebarMenu.scrollTop = scrollTop; };
            requestAnimationFrame(() => {
                restoreScroll();
                requestAnimationFrame(restoreScroll);
            });
        }
    } catch (error) {
        // Keep sidebar navigation working when browser storage is unavailable.
    }

    sidebarMenu.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!link || !sidebarMenu.contains(link) || event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
            (link.target && link.target !== '_self')) {
            return;
        }

        try {
            sessionStorage.setItem(scrollStorageKey, String(sidebarMenu.scrollTop));
        } catch (error) {
            // Scroll restoration is optional; never block a menu link.
        }
    });

    const groupToggles = sidebarMenu.querySelectorAll('[data-sidebar-group-toggle]');
    groupToggles.forEach((toggle) => {
        toggle.addEventListener('click', function () {
            const wasExpanded = this.getAttribute('aria-expanded') === 'true';
            const panel = document.getElementById(this.getAttribute('aria-controls'));
            const group = this.closest('.sidebar-group');
            this.setAttribute('aria-expanded', wasExpanded ? 'false' : 'true');
            if (panel) panel.hidden = wasExpanded;
            if (group) group.classList.toggle('is-open', !wasExpanded);
        });
    });
})();
</script>

<?php require_once __DIR__ . '/profile_password_dialog.php'; ?>
