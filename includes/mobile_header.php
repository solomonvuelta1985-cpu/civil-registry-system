<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mobile-header-content">
        <div>
            <h4><i data-lucide="file-badge"></i> Civil Registry</h4>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <!-- User Profile Dropdown (Mobile) -->
            <div class="user-profile-dropdown">
                <button class="user-profile-btn" id="mobileUserProfileBtn" type="button">
                    <div class="user-avatar">AU</div>
                </button>

                <div class="user-dropdown-menu" id="mobileUserDropdownMenu">
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
            <button type="button" id="mobileSidebarToggle">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
