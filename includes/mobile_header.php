<!-- Mobile Header (shows only on mobile) -->
<?php require_once __DIR__ . '/branding.php'; ?>
<div class="mobile-header">
    <div class="mobile-header-content">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="mobile-header-logo">
                <img src="<?= htmlspecialchars(branding_logo_url('app'), ENT_QUOTES, 'UTF-8') ?>" alt="CRDMS Logo">
            </div>
            <h4>CRDMS</h4>
        </div>
        <button type="button" id="mobileSidebarToggle" title="Open Menu">
            <i data-lucide="menu"></i>
        </button>
    </div>
</div>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
