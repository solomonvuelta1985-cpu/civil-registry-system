<!-- Mobile Header (shows only on mobile) -->
<?php require_once __DIR__ . '/branding.php'; ?>
<div class="mobile-header">
    <div class="mobile-header-content">
        <div class="mobile-header-brand">
            <div class="mobile-header-logo">
                <img src="<?= htmlspecialchars(branding_logo_url('app'), ENT_QUOTES, 'UTF-8') ?>" alt="CRDMS Logo">
            </div>
            <div class="mobile-header-brand-copy">
                <h4>CRDMS</h4>
                <span class="mobile-header-brand-divider" aria-hidden="true">|</span>
                <small class="mobile-header-subtitle">Civil Registry &amp; Document Management System</small>
            </div>
        </div>
        <button type="button" id="mobileSidebarToggle" title="Open Menu">
            <i data-lucide="menu"></i>
        </button>
    </div>
</div>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
