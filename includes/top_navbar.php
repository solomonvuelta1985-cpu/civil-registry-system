<?php
require_once __DIR__ . '/security.php';

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

    <div class="top-navbar-actions">
        <?php include __DIR__ . '/notification_bell.php'; ?>

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
                <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
                <a href="<?= htmlspecialchars(rtrim(BASE_URL, '/') . '/public/mfa_management.php', ENT_QUOTES, 'UTF-8') ?>" class="dropdown-item">
                    <i data-lucide="shield-check" aria-hidden="true"></i>
                    <span>My MFA</span>
                </a>
                <?php endif; ?>
                <button type="button" class="dropdown-item change-password-item" id="changePasswordOpen" data-change-password-open aria-haspopup="dialog" aria-expanded="false" aria-controls="changePasswordModal">
                    <i data-lucide="key-round" aria-hidden="true"></i>
                    <span>Change Password</span>
                </button>
                <div class="dropdown-divider"></div>
                <a href="../public/logout.php" class="dropdown-item logout-item">
                    <i data-lucide="log-out"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</div>

    <section class="notification-panel" id="notificationPanel" hidden aria-label="Notifications" aria-describedby="notificationAnnouncement">
        <div class="notification-panel-heading">
            <div>
                <h2>Notifications</h2>
                <p id="notificationAnnouncement" data-notification-announcement aria-live="polite">Loading notifications...</p>
                <p class="notification-encoding-stats" data-notification-encoding-stats>
                    <span>Today encoded <strong data-encoded-today>—</strong></span>
                    <span class="notification-encoding-divider" aria-hidden="true">·</span>
                    <span>All-time encoded <strong data-encoded-all-time>—</strong></span>
                </p>
            </div>
            <button type="button" class="notification-close" data-notifications-close aria-label="Close notifications">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="notification-panel-actions">
            <span class="notification-panel-caption">Latest updates</span>
            <button type="button" class="notification-mark-all" data-notification-mark-all hidden>Mark all as read</button>
        </div>
        <div class="notification-list" data-notification-list role="list"></div>
        <p class="notification-empty" data-notification-empty hidden>You are all caught up.</p>
        <p class="notification-error" data-notification-error role="status" hidden></p>
    </section>
<?php require_once __DIR__ . '/profile_password_dialog.php'; ?>
<script defer src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>assets/js/notifications.js?v=3"></script>
