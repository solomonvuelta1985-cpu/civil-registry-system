<?php
// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Ensure auth helpers are available so role checks work even if a page
// includes this nav before auth.php. Safe-guard for non-Admin roles
// (Encoder/Viewer) which should not see administrative menu items.
if (!function_exists('isAdmin')) {
    require_once __DIR__ . '/auth.php';
}
$__is_admin = function_exists('isAdmin') ? isAdmin() : false;
?>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../assets/img/LOGO1.png" alt="CRDMS Logo">
        </div>
        <h4><span>CRDMS</span></h4>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard Section -->
        <li class="sidebar-heading">Dashboard</li>
        <li>
            <a href="../admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php' || $current_page == 'dashboard_modern.php') ? 'active' : ''; ?>" title="Dashboard Overview">
                <i data-lucide="layout-grid"></i> <span>Dashboard</span>
            </a>
        </li>

        <!-- Registration Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Registration</li>
        <li>
            <a href="../public/certificate_of_live_birth.php" class="<?php echo $current_page == 'certificate_of_live_birth.php' ? 'active' : ''; ?>" title="Register Birth Certificate">
                <i data-lucide="file-text"></i> <span>Birth Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_marriage.php" class="<?php echo $current_page == 'certificate_of_marriage.php' ? 'active' : ''; ?>" title="Register Marriage Certificate">
                <i data-lucide="file-signature"></i> <span>Marriage Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_death.php" class="<?php echo $current_page == 'certificate_of_death.php' ? 'active' : ''; ?>" title="Register Death Certificate">
                <i data-lucide="file-minus"></i> <span>Death Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/application_for_marriage_license.php" class="<?php echo $current_page == 'application_for_marriage_license.php' ? 'active' : ''; ?>" title="Marriage License Application">
                <i data-lucide="clipboard-list"></i> <span>Marriage License</span>
            </a>
        </li>

        <!-- Records Management Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Records</li>
        <li>
            <a href="../public/birth_records.php" class="<?php echo $current_page == 'birth_records.php' ? 'active' : ''; ?>" title="View & Manage Birth Records">
                <i data-lucide="folder"></i> <span>Birth Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_records.php" class="<?php echo $current_page == 'marriage_records.php' ? 'active' : ''; ?>" title="View & Manage Marriage Records">
                <i data-lucide="folders"></i> <span>Marriage Records</span>
            </a>
        </li>
        <li>
            <a href="../public/death_records.php" class="<?php echo $current_page == 'death_records.php' ? 'active' : ''; ?>" title="View & Manage Death Records">
                <i data-lucide="folder-closed"></i> <span>Death Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_license_records.php" class="<?php echo $current_page == 'marriage_license_records.php' ? 'active' : ''; ?>" title="View & Manage Marriage License Applications">
                <i data-lucide="folder-search"></i> <span>License Records</span>
            </a>
        </li>

        <?php if ($__is_admin): ?>
        <!-- Archives & Trash Section (Admin only) -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Archives & Trash</li>
        <li>
            <a href="../admin/archives.php" class="<?php echo $current_page == 'archives.php' ? 'active' : ''; ?>" title="Archived Records">
                <i data-lucide="archive"></i> <span>Archives</span>
            </a>
        </li>
        <li>
            <a href="../public/trash.php" class="<?php echo $current_page == 'trash.php' ? 'active' : ''; ?>" title="View, Restore & Permanently Delete Records">
                <i data-lucide="trash-2"></i> <span>Trash</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Reports Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Reports</li>
        <li>
            <a href="../admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" title="Generate & View Reports">
                <i data-lucide="bar-chart-3"></i> <span>Reports</span>
            </a>
        </li>

        <?php if ($__is_admin): ?>
        <!-- Maintenance Section (Admin only) -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Maintenance</li>
        <li>
            <a href="../admin/devices.php" class="<?php echo $current_page == 'devices.php' ? 'active' : ''; ?>" title="Registered Devices">
                <i data-lucide="monitor"></i> <span>Devices</span>
            </a>
        </li>
        <li>
            <a href="../admin/pdf_integrity_report.php" class="<?php echo $current_page == 'pdf_integrity_report.php' ? 'active' : ''; ?>" title="PDF Integrity Report">
                <i data-lucide="file-check"></i> <span>PDF Integrity</span>
            </a>
        </li>
        <li>
            <a href="../admin/pdf_backup_manager.php" class="<?php echo $current_page == 'pdf_backup_manager.php' ? 'active' : ''; ?>" title="PDF Backup Manager">
                <i data-lucide="hard-drive"></i> <span>PDF Backups</span>
            </a>
        </li>

        <!-- Administration Section (Admin only) -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Administration</li>
        <li>
            <a href="../admin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>" title="Manage System Users">
                <i data-lucide="users"></i> <span>Users</span>
            </a>
        </li>
        <li>
            <a href="../admin/security_logs.php" class="<?php echo $current_page == 'security_logs.php' ? 'active' : ''; ?>" title="Security Event Logs">
                <i data-lucide="shield"></i> <span>Security Logs</span>
            </a>
        </li>
        <li>
            <a href="../admin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" title="System Configuration">
                <i data-lucide="settings"></i> <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
