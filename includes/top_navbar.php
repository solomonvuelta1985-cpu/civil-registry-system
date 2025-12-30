<!-- Top Navigation Bar (Desktop) -->
<div class="top-navbar" id="topNavbar">
    <button type="button" id="sidebarCollapse" title="Toggle Sidebar">
        <i data-lucide="menu"></i>
    </button>
    <div class="top-navbar-info">
        <span class="welcome-text">Welcome, Admin User</span>
    </div>

    <!-- User Profile Dropdown -->
    <div class="user-profile-dropdown">
        <button class="user-profile-btn" id="userProfileBtn" type="button">
            <div class="user-avatar">AU</div>
            <div class="user-profile-info">
                <span class="user-name">Admin User</span>
                <span class="user-role">Administrator</span>
            </div>
            <i data-lucide="chevron-down" class="dropdown-arrow"></i>
        </button>

        <div class="user-dropdown-menu" id="userDropdownMenu">
            <div class="dropdown-header">
                <div class="dropdown-user-info">
                    <div class="user-avatar large">AU</div>
                    <div>
                        <div class="dropdown-user-name">Admin User</div>
                        <div class="dropdown-user-email">admin</div>
                        <span class="dropdown-user-badge">ADMIN</span>
                    </div>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item logout-item">
                <i data-lucide="log-out"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>
