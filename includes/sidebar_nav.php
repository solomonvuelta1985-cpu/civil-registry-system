<?php
// Get current page to highlight active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i data-lucide="file-badge"></i> <span>Civil Registry</span></h4>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard Section -->
        <li class="sidebar-heading">Main</li>
        <li>
            <a href="../admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php' || $current_page == 'dashboard_modern.php') ? 'active' : ''; ?>" title="Dashboard">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>
        </li>

        <!-- Registration Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">New Registration</li>
        <li>
            <a href="../public/certificate_of_live_birth.php" class="<?php echo $current_page == 'certificate_of_live_birth.php' ? 'active' : ''; ?>" title="Register Birth">
                <i data-lucide="baby"></i> <span>Birth</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_marriage.php" class="<?php echo $current_page == 'certificate_of_marriage.php' ? 'active' : ''; ?>" title="Register Marriage">
                <i data-lucide="heart"></i> <span>Marriage</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_death.php" class="<?php echo $current_page == 'certificate_of_death.php' ? 'active' : ''; ?>" title="Register Death">
                <i data-lucide="cross"></i> <span>Death</span>
            </a>
        </li>
        <li>
            <a href="../public/application_for_marriage_license.php" class="<?php echo $current_page == 'application_for_marriage_license.php' ? 'active' : ''; ?>" title="Marriage License Application">
                <i data-lucide="file-heart"></i> <span>Marriage License</span>
            </a>
        </li>

        <!-- Records Management Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Records Management</li>
        <li>
            <a href="../public/birth_records.php" class="<?php echo $current_page == 'birth_records.php' ? 'active' : ''; ?>" title="Manage Birth Records">
                <i data-lucide="file-text"></i> <span>Birth Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_records.php" class="<?php echo $current_page == 'marriage_records.php' ? 'active' : ''; ?>" title="Manage Marriage Records">
                <i data-lucide="file-heart"></i> <span>Marriage Records</span>
            </a>
        </li>
        <li>
            <a href="../public/death_records.php" class="<?php echo $current_page == 'death_records.php' ? 'active' : ''; ?>" title="Manage Death Records">
                <i data-lucide="file-minus"></i> <span>Death Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_license_records.php" class="<?php echo $current_page == 'marriage_license_records.php' ? 'active' : ''; ?>" title="Manage Marriage License Records">
                <i data-lucide="files"></i> <span>License Records</span>
            </a>
        </li>

        <!-- Reports & Analytics Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Reports & Analytics</li>
        <li>
            <a href="../admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" title="View Reports">
                <i data-lucide="bar-chart-3"></i> <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="../admin/archives.php" class="<?php echo $current_page == 'archives.php' ? 'active' : ''; ?>" title="View Archives">
                <i data-lucide="archive"></i> <span>Archives</span>
            </a>
        </li>

        <!-- Administration Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Administration</li>
        <li>
            <a href="../admin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>" title="Manage Users">
                <i data-lucide="users"></i> <span>User Management</span>
            </a>
        </li>
        <li>
            <a href="../admin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" title="System Settings">
                <i data-lucide="settings"></i> <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>
