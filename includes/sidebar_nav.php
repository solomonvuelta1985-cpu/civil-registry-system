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
        <!-- Main Section -->
        <li class="sidebar-heading">Overview</li>
        <li>
            <a href="../admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php' || $current_page == 'dashboard_modern.php') ? 'active' : ''; ?>" title="Dashboard">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>
        </li>

        <!-- Certificates Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Certificates</li>
        <li>
            <a href="../public/certificate_of_live_birth.php" class="<?php echo $current_page == 'certificate_of_live_birth.php' ? 'active' : ''; ?>" title="Birth Certificates">
                <i data-lucide="baby"></i> <span>Birth Certificates</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_marriage.php" class="<?php echo $current_page == 'certificate_of_marriage.php' ? 'active' : ''; ?>" title="Marriage Certificates">
                <i data-lucide="heart"></i> <span>Marriage Certificates</span>
            </a>
        </li>
        <li>
            <a href="../public/certificate_of_death.php" class="<?php echo $current_page == 'certificate_of_death.php' ? 'active' : ''; ?>" title="Death Certificates">
                <i data-lucide="file-minus"></i> <span>Death Certificates</span>
            </a>
        </li>

        <!-- Records Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">Management</li>
        <li>
            <a href="../public/birth_records.php" class="<?php echo $current_page == 'birth_records.php' ? 'active' : ''; ?>" title="Birth Records">
                <i data-lucide="baby"></i> <span>Birth Records</span>
            </a>
        </li>
        <li>
            <a href="../public/marriage_records.php" class="<?php echo $current_page == 'marriage_records.php' ? 'active' : ''; ?>" title="Marriage Records">
                <i data-lucide="heart"></i> <span>Marriage Records</span>
            </a>
        </li>
        <li>
            <a href="../admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" title="Reports">
                <i data-lucide="bar-chart-3"></i> <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="../admin/archives.php" class="<?php echo $current_page == 'archives.php' ? 'active' : ''; ?>" title="Archives">
                <i data-lucide="archive"></i> <span>Archives</span>
            </a>
        </li>

        <!-- System Section -->
        <li class="sidebar-divider"></li>
        <li class="sidebar-heading">System</li>
        <li>
            <a href="../admin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>" title="Users">
                <i data-lucide="users"></i> <span>Users</span>
            </a>
        </li>
        <li>
            <a href="../admin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>" title="Settings">
                <i data-lucide="settings"></i> <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>
