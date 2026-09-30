<?php
require_once __DIR__ . '/branding.php';
$current_page=basename($_SERVER['PHP_SELF']);
if(!function_exists('isAdmin'))require_once __DIR__.'/auth.php';
$__is_admin=function_exists('isAdmin')?isAdmin():false;$__pending_devices=0;
if($__is_admin){if(!function_exists('countPendingDevices'))@require_once __DIR__.'/device_auth.php';if(function_exists('countPendingDevices')){try{$__pending_devices=countPendingDevices();}catch(Throwable $e){$__pending_devices=0;}}}
?>
<nav class="sidebar" id="sidebar"><div class="sidebar-header"><div class="sidebar-logo"><img src="<?= htmlspecialchars(branding_logo_url('app'), ENT_QUOTES, 'UTF-8') ?>" alt="CRDMS Logo"></div><h4><span>CRDMS</span></h4></div><ul class="sidebar-menu">
<li class="sidebar-heading">Dashboard</li><li><a href="<?= BASE_URL ?>admin/dashboard.php" class="<?php echo ($current_page=='dashboard.php'||$current_page=='dashboard_modern.php')?'active':''; ?>" title="Dashboard Overview"><i data-lucide="layout-grid"></i> <span>Dashboard</span></a></li>
<li class="sidebar-divider"></li><li class="sidebar-heading">Registration</li><li><a href="<?= BASE_URL ?>public/certificate_of_live_birth.php" class="<?php echo $current_page=='certificate_of_live_birth.php'?'active':''; ?>" title="Register Birth Certificate"><i data-lucide="file-text"></i> <span>Birth Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/certificate_of_marriage.php" class="<?php echo $current_page=='certificate_of_marriage.php'?'active':''; ?>" title="Register Marriage Certificate"><i data-lucide="file-signature"></i> <span>Marriage Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/certificate_of_death.php" class="<?php echo $current_page=='certificate_of_death.php'?'active':''; ?>" title="Register Death Certificate"><i data-lucide="file-minus"></i> <span>Death Certificate</span></a></li><li><a href="<?= BASE_URL ?>public/application_for_marriage_license.php" class="<?php echo $current_page=='application_for_marriage_license.php'?'active':''; ?>" title="Marriage License Application"><i data-lucide="clipboard-list"></i> <span>Marriage License</span></a></li>
<li class="sidebar-divider"></li><li class="sidebar-heading">Records</li><li><a href="<?= BASE_URL ?>public/birth_records.php" class="<?php echo $current_page=='birth_records.php'?'active':''; ?>" title="View &amp; Manage Birth Records"><i data-lucide="folder"></i> <span>Birth Records</span></a></li><li><a href="<?= BASE_URL ?>public/marriage_records.php" class="<?php echo $current_page=='marriage_records.php'?'active':''; ?>" title="View &amp; Manage Marriage Records"><i data-lucide="folders"></i> <span>Marriage Records</span></a></li><li><a href="<?= BASE_URL ?>public/death_records.php" class="<?php echo $current_page=='death_records.php'?'active':''; ?>" title="View &amp; Manage Death Records"><i data-lucide="folder-closed"></i> <span>Death Records</span></a></li><li><a href="<?= BASE_URL ?>public/marriage_license_records.php" class="<?php echo $current_page=='marriage_license_records.php'?'active':''; ?>" title="View &amp; Manage Marriage License Applications"><i data-lucide="folder-search"></i> <span>License Records</span></a></li><li><a href="<?= BASE_URL ?>public/folder_browser.php" class="<?php echo $current_page=='folder_browser.php'?'active':''; ?>" title="Browse Records by Folder"><i data-lucide="folder-tree"></i> <span>Folder Browser</span></a></li>
<?php if(function_exists('hasPermission')&&hasPermission('birth_crf_1a_view')): ?><li><a href="<?= BASE_URL ?>public/crf_1a_records.php" class="<?php echo $current_page=='crf_1a_records.php'?'active':''; ?>" title="CRF No. 1A Issuance Records"><i data-lucide="file-check-2"></i> <span>CRF No. 1A Records</span></a></li><?php endif; ?><?php if(function_exists('hasPermission')&&hasPermission('death_crf_2a_view')): ?><li><a href="<?= BASE_URL ?>public/crf_2a_records.php" class="<?php echo $current_page=='crf_2a_records.php'?'active':''; ?>" title="CRF No. 2A Issuance Records"><i data-lucide="file-heart"></i> <span>CRF No. 2A Records</span></a></li><?php endif; ?><?php if(function_exists('hasPermission')&&hasPermission('marriage_crf_3a_view')): ?><li><a href="<?= BASE_URL ?>public/crf_3a_records.php" class="<?php echo $current_page=='crf_3a_records.php'?'active':''; ?>" title="CRF No. 3A Issuance Records"><i data-lucide="heart-handshake"></i> <span>CRF No. 3A Records</span></a></li><?php endif; ?><li><a href="<?= BASE_URL ?>public/double_registration.php" class="<?php echo $current_page=='double_registration.php'?'active':''; ?>" title="Double Registration Detection (PSA MC 2019-23)"><i data-lucide="link-2"></i> <span>Double Registration</span></a></li><li><a href="<?= BASE_URL ?>public/family_relations.php" class="<?php echo $current_page=='family_relations.php'?'active':''; ?>" title="Discover siblings and related civil registry records"><i data-lucide="users"></i> <span>Family Relations</span></a></li>
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
    <li class="sidebar-group<?= $__group_open ? ' is-open' : '' ?>">
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
<li class="sidebar-divider"></li><li class="sidebar-heading">Administration</li><li><a href="<?= BASE_URL ?>admin/users.php" class="<?php echo $current_page=='users.php'?'active':''; ?>" title="Manage System Users"><i data-lucide="users"></i> <span>Users</span></a></li><li><a href="<?= BASE_URL ?>admin/security_logs.php" class="<?php echo $current_page=='security_logs.php'?'active':''; ?>" title="Security Logs"><i data-lucide="shield"></i> <span>Security Logs</span></a></li><li><a href="<?= BASE_URL ?>admin/activity_logs.php" class="<?php echo $current_page=='activity_logs.php'?'active':''; ?>" title="User Activity Logs"><i data-lucide="history"></i> <span>Activity Logs</span></a></li><li><a href="<?= BASE_URL ?>admin/settings.php" class="<?php echo $current_page=='settings.php'?'active':''; ?>" title="System Configuration"><i data-lucide="settings"></i> <span>Settings</span></a></li><?php endif; ?>
</ul></nav>
<script>
(function () {
    const sidebarMenu = document.querySelector('#sidebar .sidebar-menu');
    if (!sidebarMenu) return;

    const groupToggles = sidebarMenu.querySelectorAll('[data-sidebar-group-toggle]');
    groupToggles.forEach((toggle) => {
        toggle.addEventListener('click', function () {
            const wasExpanded = this.getAttribute('aria-expanded') === 'true';

            groupToggles.forEach((otherToggle) => {
                otherToggle.setAttribute('aria-expanded', 'false');
                const otherPanel = document.getElementById(otherToggle.getAttribute('aria-controls'));
                if (otherPanel) otherPanel.hidden = true;
                const otherGroup = otherToggle.closest('.sidebar-group');
                if (otherGroup) otherGroup.classList.remove('is-open');
            });

            if (!wasExpanded) {
                this.setAttribute('aria-expanded', 'true');
                const panel = document.getElementById(this.getAttribute('aria-controls'));
                if (panel) panel.hidden = false;
                const group = this.closest('.sidebar-group');
                if (group) group.classList.add('is-open');
            }
        });
    });
})();
</script>
