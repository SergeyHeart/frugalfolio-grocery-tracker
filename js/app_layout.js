// js/app_layout.js - 

document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    const mobileBreakpoint = 768;
    const collapseBreakpoint = 992;

    // --- Add preload class immediately ---
    body.classList.add('preload'); // Add this class to prevent transitions on initial load

    // --- Function to Apply Layout State on Load and Resize ---
    function applyLayoutState() {
        const screenWidth = window.innerWidth;

        // --- Reset state classes first ---
        const wasMobile = body.classList.contains('mobile-view');
        body.classList.remove('mobile-view', 'sidebar-collapsed');
        // Keep sidebar-overlay-visible untouched unless moving out of mobile view
        if (wasMobile && screenWidth >= mobileBreakpoint) {
            body.classList.remove('sidebar-overlay-visible');
        }

        // --- Determine and apply state based on width ---
        if (screenWidth < mobileBreakpoint) {
            body.classList.add('mobile-view');
             // Sidebar starts hidden via CSS transform in mobile-view
        } else if (screenWidth < collapseBreakpoint) {
            body.classList.add('sidebar-collapsed');
        }
        // No else needed, desktop state is the absence of these classes
    }

    // --- Function to CLOSE the mobile overlay sidebar ---
    function closeMobileSidebar() {
        body.classList.remove('sidebar-overlay-visible');
    }

    // --- Function for Toggle Button Click ---
    function handleToggleClick() {
        // Remove preload on first interaction if it's still there
        if (body.classList.contains('preload')) {
             body.classList.remove('preload');
        }

        const screenWidth = window.innerWidth;

        if (screenWidth < mobileBreakpoint) {
            body.classList.toggle('sidebar-overlay-visible');
            // Ensure sidebar is expanded when shown as overlay
            if(body.classList.contains('sidebar-overlay-visible')) {
                 body.classList.remove('sidebar-collapsed');
            }
        } else {
            body.classList.toggle('sidebar-collapsed');
        }
    }

    // --- Event Listeners ---
    if (sidebarToggle) { sidebarToggle.addEventListener('click', handleToggleClick); } else { console.error("Sidebar toggle button (#sidebarToggle) not found."); }
    if (sidebarCloseBtn) { sidebarCloseBtn.addEventListener('click', closeMobileSidebar); }
    if (sidebarOverlay) {
	  sidebarOverlay.addEventListener('click', function() {
		closeMobileSidebar(); // Function that removes 'sidebar-overlay-visible' etc.
	  });
	}

    // --- Initial Setup ---
    applyLayoutState();

    requestAnimationFrame(() => {
        setTimeout(() => {
            body.classList.remove('preload');
            // console.log("Preload class removed, transitions enabled.");
        }, 50); // Shorter delay might be okay now
    });


    // Apply state on window resize (debounced)
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyLayoutState, 150); // Just apply state on resize
    });

	// --- Notification Dropdown Logic ---
    const notificationBellBtn = document.getElementById('notificationBellBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.querySelector('.notification-badge');

    if (notificationBellBtn && notificationDropdown) {
        notificationBellBtn.addEventListener('click', function(event) {
            event.stopPropagation();
            const isVisible = notificationDropdown.style.display === 'block';
            notificationDropdown.style.display = isVisible ? 'none' : 'block';
            if (!isVisible && notificationBadge) {
                 notificationBadge.style.display = 'none';
            }
        });
        document.addEventListener('click', function(event) {
            if (notificationDropdown.style.display === 'block' &&
                !notificationBellBtn.contains(event.target) &&
                !notificationDropdown.contains(event.target))
            {
                notificationDropdown.style.display = 'none';
            }
        });
        notificationDropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    } else {
        if (!notificationBellBtn) console.warn("Notification bell button (#notificationBellBtn) not found.");
        if (!notificationDropdown) console.warn("Notification dropdown (#notificationDropdown) not found.");
    }
    // --- End Notification Dropdown Logic ---
	
	const userProfileTrigger = document.getElementById('userProfileTrigger');
    const userDropdownMenu = document.getElementById('userDropdownMenu');

    if (userProfileTrigger && userDropdownMenu) {
        userProfileTrigger.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent click from bubbling up to document
            userDropdownMenu.style.display = userDropdownMenu.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown if clicking outside
        document.addEventListener('click', function(event) {
            if (userDropdownMenu.style.display === 'block' && !userProfileTrigger.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                userDropdownMenu.style.display = 'none';
            }
        });

        // Optional: Close with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && userDropdownMenu.style.display === 'block') {
                userDropdownMenu.style.display = 'none';
            }
        });
    }

    if (window.feather) {
        feather.replace();
    }
}); // End DOMContentLoaded