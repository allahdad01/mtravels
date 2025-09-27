/**
 * EXTREME RTL FIX - Last resort measures
 * This script forcibly realigns the sidebar elements for RTL languages
 */

// Execute immediately
(function() {
    // Ensure this only runs in RTL mode
    if (document.documentElement.dir !== 'rtl' && !document.body.classList.contains('rtl')) {
        return;
    }

    // Function to apply direct DOM fixes for RTL sidebar
    function applyRTLSidebarFix() {
        console.log("Applying extreme RTL fixes");
        // Target sidebar navigation
        const sidebar = document.querySelector('.pcoded-navbar');
        if (!sidebar) return;
        
        // Apply RTL settings to sidebar
        Object.assign(sidebar.style, {
            right: '0',
            left: 'auto',
            direction: 'rtl'
        });
        
        // Target inner navigation
        const innerNav = sidebar.querySelector('.pcoded-inner-navbar');
        if (innerNav) {
            Object.assign(innerNav.style, {
                direction: 'rtl',
                textAlign: 'right'
            });
        }
        
        // Add a global style to force proper horizontal alignment
        if (!document.getElementById('rtl-fix-style')) {
            const styleEl = document.createElement('style');
            styleEl.id = 'rtl-fix-style';
            styleEl.textContent = `
                .pcoded-navbar a, .pcoded-navbar .nav-item a {
                    display: flex !important;
                    flex-direction: row !important;
                    flex-wrap: nowrap !important;
                    align-items: center !important;
                }
                .pcoded-navbar .pcoded-micon, .pcoded-navbar a i {
                    margin-left: 15px !important;
                    order: 2 !important;
                    flex: 0 0 auto !important;
                }
                .pcoded-navbar .pcoded-mtext {
                    order: 1 !important;
                    flex: 1 1 auto !important;
                }
                
                /* Mobile sidebar fixes for RTL */
                @media (max-width: 991px) {
                    body[dir="rtl"] .pcoded-navbar,
                    html[dir="rtl"] .pcoded-navbar {
                        right: -100% !important;
                        left: auto !important;
                        transform: translateX(0) !important;
                        transition: all 0.3s ease-in-out !important;
                    }
                    
                    body[dir="rtl"] .pcoded-navbar.mob-open,
                    html[dir="rtl"] .pcoded-navbar.mob-open {
                        right: 0 !important;
                        left: auto !important;
                        display: block !important;
                    }
                }
            `;
            document.head.appendChild(styleEl);
        }
        
        // Target all menu items - this is the key fix for the issue
        const menuItems = sidebar.querySelectorAll('.nav-item a, .pcoded-item li a');
        menuItems.forEach(item => {
            // Remove any existing inline styles that might interfere
            item.removeAttribute('style');
            
            // Apply new styles
            Object.assign(item.style, {
                display: 'flex',
                flexDirection: 'row',
                flexWrap: 'nowrap',
                justifyContent: 'flex-start',
                alignItems: 'center',
                textAlign: 'right',
                padding: '10px 20px 10px 35px',
                direction: 'rtl',
                width: '100%'
            });
            
            // IMPORTANT: Move icon and text elements to proper order in DOM
            // This is critical for proper horizontal layout
            const icon = item.querySelector('.pcoded-micon, i.feather, i.fas');
            const text = item.querySelector('.pcoded-mtext');
            
            if (icon && text) {
                // Actually reorder the DOM elements to ensure proper layout
                // First, store original parent
                const parent = icon.parentNode;
                
                // Create a wrapper div to hold both elements in proper order
                const wrapper = document.createElement('div');
                wrapper.style.display = 'flex';
                wrapper.style.flexDirection = 'row';
                wrapper.style.alignItems = 'center';
                wrapper.style.width = '100%';
                
                // Remove the elements from their current position
                if (icon.parentNode) icon.parentNode.removeChild(icon);
                if (text.parentNode) text.parentNode.removeChild(text);
                
                // First add text, then icon (for RTL display)
                wrapper.appendChild(text);
                wrapper.appendChild(icon);
                
                // Style the elements
                Object.assign(text.style, {
                    order: '1',
                    textAlign: 'right',
                    marginLeft: 'auto',
                    marginRight: '0',
                    flex: '1'
                });
                
                Object.assign(icon.style, {
                    order: '2',
                    marginLeft: '15px',
                    marginRight: '0'
                });
                
                // Add the wrapper back to the parent
                parent.appendChild(wrapper);
            }
        });
        
        // Fix menu captions
        const menuCaptions = sidebar.querySelectorAll('.pcoded-menu-caption');
        menuCaptions.forEach(caption => {
            Object.assign(caption.style, {
                textAlign: 'right',
                paddingRight: '20px',
                paddingLeft: '0',
                direction: 'rtl'
            });
        });
        
        // Fix submenu indicators
        const hasMenuItems = sidebar.querySelectorAll('.pcoded-hasmenu > a');
        hasMenuItems.forEach(item => {
            // Check if the item already has our custom indicator
            if (!item.querySelector('.rtl-submenu-indicator')) {
                // Create a new indicator element
                const indicator = document.createElement('span');
                indicator.className = 'rtl-submenu-indicator';
                indicator.innerHTML = '<i class="feather icon-chevron-left"></i>';
                Object.assign(indicator.style, {
                    position: 'absolute',
                    left: '20px',
                    right: 'auto',
                    top: '50%',
                    transform: 'translateY(-50%)'
                });
                item.appendChild(indicator);
            }
        });
        
        // Fix submenus
        const submenus = sidebar.querySelectorAll('.pcoded-submenu');
        submenus.forEach(submenu => {
            Object.assign(submenu.style, {
                paddingRight: '40px',
                paddingLeft: '0',
                textAlign: 'right'
            });
            
            // Fix submenu items
            const submenuItems = submenu.querySelectorAll('li a');
            submenuItems.forEach(item => {
                Object.assign(item.style, {
                    display: 'flex',
                    flexDirection: 'row',
                    flexWrap: 'nowrap',
                    justifyContent: 'flex-start',
                    alignItems: 'center',
                    textAlign: 'right',
                    padding: '7px 45px 7px 15px',
                    direction: 'rtl'
                });
                
                // IMPORTANT: Move icon and text elements to proper order in DOM for submenus
                const icon = item.querySelector('.pcoded-micon, i.feather, i.fas');
                const text = item.querySelector('.pcoded-mtext');
                
                if (icon && text) {
                    // Create a wrapper div
                    const wrapper = document.createElement('div');
                    wrapper.style.display = 'flex';
                    wrapper.style.flexDirection = 'row';
                    wrapper.style.alignItems = 'center';
                    wrapper.style.width = '100%';
                    
                    // Remove the elements from current position
                    if (icon.parentNode) icon.parentNode.removeChild(icon);
                    if (text.parentNode) text.parentNode.removeChild(text);
                    
                    // Add in the correct order for RTL
                    wrapper.appendChild(text);
                    wrapper.appendChild(icon);
                    
                    // Style them
                    Object.assign(text.style, {
                        order: '1',
                        textAlign: 'right',
                        marginLeft: 'auto',
                        marginRight: '0',
                        flex: '1'
                    });
                    
                    Object.assign(icon.style, {
                        order: '2',
                        marginLeft: '15px',
                        marginRight: '0'
                    });
                    
                    // Add the wrapper to the parent
                    item.appendChild(wrapper);
                }
            });
        });
    }

    // Apply fixes immediately
    applyRTLSidebarFix();
    
    // Apply fixes again after a short delay to handle template scripts
    setTimeout(applyRTLSidebarFix, 500);
    
    // Also apply after full page load
    window.addEventListener('load', function() {
        applyRTLSidebarFix();
        // Apply one more time after a delay to be extra safe
        setTimeout(applyRTLSidebarFix, 1000);
    });
    
    // Fix mobile sidebar toggle functionality
    function fixMobileSidebarToggle() {
        // Find all menu toggle buttons
        const toggleButtons = document.querySelectorAll('.mobile-menu, #mobile-collapse1, #mobile-header, .navbar-toggler');
        
        // Remove any existing event listeners by cloning and replacing
        toggleButtons.forEach(button => {
            const newButton = button.cloneNode(true);
            if (button.parentNode) {
                button.parentNode.replaceChild(newButton, button);
            }
            
            // Add our custom click handler
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle the sidebar
                const sidebar = document.querySelector('.pcoded-navbar');
                if (!sidebar) return;
                
                // Toggle mob-open class
                if (sidebar.classList.contains('mob-open')) {
                    sidebar.classList.remove('mob-open');
                    sidebar.style.right = '-100%';
                } else {
                    sidebar.classList.add('mob-open');
                    sidebar.style.right = '0';
                    sidebar.style.display = 'block';
                }
                
                // Create or toggle overlay
                let overlay = document.querySelector('.mobile-menu-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.className = 'mobile-menu-overlay';
                    
                    // Style the overlay
                    Object.assign(overlay.style, {
                        position: 'fixed',
                        top: '0',
                        left: '0',
                        right: '0',
                        bottom: '0',
                        background: 'rgba(0,0,0,0.5)',
                        zIndex: '999',
                        display: 'none'
                    });
                    
                    // Add click handler to close sidebar when overlay is clicked
                    overlay.addEventListener('click', function() {
                        const sidebar = document.querySelector('.pcoded-navbar');
                        if (sidebar) {
                            sidebar.classList.remove('mob-open');
                            sidebar.style.right = '-100%';
                        }
                        overlay.style.display = 'none';
                    });
                    
                    document.body.appendChild(overlay);
                }
                
                // Show/hide overlay based on sidebar state
                overlay.style.display = sidebar.classList.contains('mob-open') ? 'block' : 'none';
                
                // Apply RTL fixes after toggle
                setTimeout(applyRTLSidebarFix, 300);
            });
        });
        
        // Make sure the sidebar is in the correct initial position
        const sidebar = document.querySelector('.pcoded-navbar');
        if (sidebar && window.innerWidth <= 991 && !sidebar.classList.contains('mob-open')) {
            sidebar.style.right = '-100%';
            sidebar.style.left = 'auto';
        }
    }
    
    // Run mobile fix immediately and after DOM load
    fixMobileSidebarToggle();
    window.addEventListener('load', fixMobileSidebarToggle);
    
    // Also handle window resize
    window.addEventListener('resize', function() {
        const sidebar = document.querySelector('.pcoded-navbar');
        if (sidebar) {
            if (window.innerWidth <= 991) {
                // Mobile view
                if (!sidebar.classList.contains('mob-open')) {
                    sidebar.style.right = '-100%';
                    sidebar.style.left = 'auto';
                }
            } else {
                // Desktop view
                sidebar.style.right = '0';
                sidebar.style.left = 'auto';
                sidebar.style.display = 'block';
            }
        }
    });
    
    // Handle clicks outside the sidebar to close it
    document.addEventListener('click', function(e) {
        // If we're on mobile and the sidebar is open
        if (window.innerWidth <= 991) {
            const sidebar = document.querySelector('.pcoded-navbar');
            if (sidebar && sidebar.classList.contains('mob-open')) {
                // Check if click was outside the sidebar and not on a toggle button
                const isToggleButton = e.target.classList.contains('mobile-menu') || 
                                     e.target.closest('.mobile-menu') || 
                                     e.target.classList.contains('navbar-toggler');
                
                if (!sidebar.contains(e.target) && !isToggleButton) {
                    sidebar.classList.remove('mob-open');
                    sidebar.style.right = '-100%';
                    
                    // Hide overlay
                    const overlay = document.querySelector('.mobile-menu-overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                    }
                }
            }
        }
    });
})(); 