<?php
$_nav_fullname  = function_exists('getUserFullName') ? getUserFullName() : 'Admin User';
$_nav_role      = function_exists('getUserRole')     ? getUserRole()     : 'Administrator';
$_nav_initial   = strtoupper(substr($_nav_fullname, 0, 1)) ?: 'A';
$_nav_username  = function_exists('getUsername')     ? getUsername()     : '';
?>
<!-- Top Navigation Bar (Desktop) -->
<div class="top-navbar" id="topNavbar">
    <button type="button" id="sidebarCollapse" title="Toggle Sidebar">
        <i data-lucide="menu"></i>
    </button>
    <div class="top-navbar-info">
        <span class="welcome-text">Welcome, <?= htmlspecialchars($_nav_fullname) ?></span>
    </div>

    <!-- User Profile Dropdown -->
    <div class="user-profile-dropdown">
        <button class="user-profile-btn" id="userProfileBtn" type="button">
            <div class="user-avatar"><?= htmlspecialchars($_nav_initial) ?></div>
            <div class="user-profile-info">
                <span class="user-name"><?= htmlspecialchars($_nav_fullname) ?></span>
                <span class="user-role"><?= htmlspecialchars($_nav_role) ?></span>
            </div>
            <i data-lucide="chevron-down" class="dropdown-arrow"></i>
        </button>

        <div class="user-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-header">
                <div class="dropdown-user-info">
                    <div class="user-avatar large"><?= htmlspecialchars($_nav_initial) ?></div>
                    <div>
                        <div class="dropdown-user-name"><?= htmlspecialchars($_nav_fullname) ?></div>
                        <div class="dropdown-user-email"><?= htmlspecialchars($_nav_username) ?></div>
                        <span class="dropdown-user-badge"><?= htmlspecialchars(strtoupper($_nav_role)) ?></span>
                    </div>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="../public/logout.php" class="dropdown-item logout-item">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
