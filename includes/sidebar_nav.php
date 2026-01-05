<?php
// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);
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
        <li class="sidebar-heading">Overview</li>
        <li>
            <a href="../admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php' || $current_page == 'dashboard_modern.php') ? 'active' : ''; ?>" title="Dashboard Overview">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>
        </li>

        <!-- Registration Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Register Certificate</li>
        <li>
            <a href="../public/certificate_of_live_birth.php" class="<?php echo $current_page == 'certificate_of_live_birth.php' ? 'active' : ''; ?>" title="Register Birth Certificate">
                <i data-lucide="baby"></i> <span>Birth Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_marriage.php" class="<?php echo $current_page == 'certificate_of_marriage.php' ? 'active' : ''; ?>" title="Register Marriage Certificate">
                <i data-lucide="heart"></i> <span>Marriage Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_death.php" class="<?php echo $current_page == 'certificate_of_death.php' ? 'active' : ''; ?>" title="Register Death Certificate">
                <i data-lucide="cross"></i> <span>Death Certificate</span>
            </a>
        </li>
        <li>
            <a href="../public/application_for_marriage_license.php" class="<?php echo $current_page == 'application_for_marriage_license.php' ? 'active' : ''; ?>" title="Marriage License Application">
                <i data-lucide="clipboard-check"></i> <span>Marriage License</span>
            </a>
        </li>

        <!-- Records Management Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Manage Records</li>
        <li>
            <a href="../public/birth_records.php" class="<?php echo $current_page == 'birth_records.php' ? 'active' : ''; ?>" title="View & Manage Birth Records">
                <i data-lucide="folder-open"></i> <span>Birth Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_records.php" class="<?php echo $current_page == 'marriage_records.php' ? 'active' : ''; ?>" title="View & Manage Marriage Records">
                <i data-lucide="folder-heart"></i> <span>Marriage Records</span>
            </a>
        </li>
        <li>
            <a href="../public/death_records.php" class="<?php echo $current_page == 'death_records.php' ? 'active' : ''; ?>" title="View & Manage Death Records">
                <i data-lucide="folder-minus"></i> <span>Death Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_license_records.php" class="<?php echo $current_page == 'marriage_license_records.php' ? 'active' : ''; ?>" title="View & Manage Marriage License Applications">
                <i data-lucide="folder-check"></i> <span>License Applications</span>
            </a>
        </li>

        <!-- Reports & Analytics Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Analytics</li>
        <li>
            <a href="../admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" title="Generate & View Reports">
                <i data-lucide="chart-bar"></i> <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="../admin/archives.php" class="<?php echo $current_page == 'archives.php' ? 'active' : ''; ?>" title="Archived Records">
                <i data-lucide="archive"></i> <span>Archives</span>
            </a>
        </li>

        <!-- Administration Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">System</li>
        <li>
            <a href="../admin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>" title="Manage System Users">
                <i data-lucide="users"></i> <span>Users</span>
            </a>
        </li>
        <li>
            <a href="../admin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" title="System Configuration">
                <i data-lucide="settings"></i> <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>
