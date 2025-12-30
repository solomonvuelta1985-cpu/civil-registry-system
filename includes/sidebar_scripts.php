<script>
// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log('Lucide icons initialized');
    } else {
        console.warn('Lucide library not loaded');
    }
});

// Sidebar functionality
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const sidebarCollapse = document.getElementById('sidebarCollapse');
const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
const body = document.body;

// Desktop: Toggle sidebar collapse/expand
if (sidebarCollapse) {
    sidebarCollapse.addEventListener('click', function() {
        body.classList.toggle('sidebar-collapsed');

        // Save state to localStorage
        const isCollapsed = body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
}

// Mobile: Toggle sidebar visibility
if (mobileSidebarToggle) {
    mobileSidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        body.classList.toggle('sidebar-open');
    });
}

// Close sidebar when clicking overlay
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.classList.remove('sidebar-open');
    });
}

// Close mobile sidebar when clicking a menu link
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', function(e) {
        // Add click animation
        this.style.transition = 'all 0.15s ease';
        this.style.transform = 'scale(0.96) translateX(3px)';

        setTimeout(() => {
            this.style.transform = '';
        }, 150);

        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            body.classList.remove('sidebar-open');
        }
    });
});

// Restore sidebar state on page load
const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
if (isCollapsed && window.innerWidth > 768) {
    body.classList.add('sidebar-collapsed');
}

// Animate active menu item on page load
const activeMenuItem = document.querySelector('.sidebar-menu a.active');
if (activeMenuItem) {
    activeMenuItem.style.animation = 'none';
    setTimeout(() => {
        activeMenuItem.style.animation = '';
    }, 10);
}

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        body.classList.remove('sidebar-open');
    }
});

// User Profile Dropdown Functionality
const userProfileBtn = document.getElementById('userProfileBtn');
const userDropdownMenu = document.getElementById('userDropdownMenu');
const mobileUserProfileBtn = document.getElementById('mobileUserProfileBtn');
const mobileUserDropdownMenu = document.getElementById('mobileUserDropdownMenu');

// Desktop dropdown
if (userProfileBtn && userDropdownMenu) {
    userProfileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        userProfileBtn.classList.toggle('active');
        userDropdownMenu.classList.toggle('show');

        // Close mobile dropdown if open
        if (mobileUserProfileBtn && mobileUserDropdownMenu) {
            mobileUserProfileBtn.classList.remove('active');
            mobileUserDropdownMenu.classList.remove('show');
        }
    });
}

// Mobile dropdown
if (mobileUserProfileBtn && mobileUserDropdownMenu) {
    mobileUserProfileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        mobileUserProfileBtn.classList.toggle('active');
        mobileUserDropdownMenu.classList.toggle('show');

        // Close desktop dropdown if open
        if (userProfileBtn && userDropdownMenu) {
            userProfileBtn.classList.remove('active');
            userDropdownMenu.classList.remove('show');
        }
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (userProfileBtn && userDropdownMenu) {
        if (!userProfileBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
            userProfileBtn.classList.remove('active');
            userDropdownMenu.classList.remove('show');
        }
    }

    if (mobileUserProfileBtn && mobileUserDropdownMenu) {
        if (!mobileUserProfileBtn.contains(e.target) && !mobileUserDropdownMenu.contains(e.target)) {
            mobileUserProfileBtn.classList.remove('active');
            mobileUserDropdownMenu.classList.remove('show');
        }
    }
});

// Close dropdown when pressing Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (userProfileBtn && userDropdownMenu) {
            userProfileBtn.classList.remove('active');
            userDropdownMenu.classList.remove('show');
        }
        if (mobileUserProfileBtn && mobileUserDropdownMenu) {
            mobileUserProfileBtn.classList.remove('active');
            mobileUserDropdownMenu.classList.remove('show');
        }
    }
});
</script>
