/**
 * RTL Header Dropdown Fixes
 * This script fixes the positioning of dropdown menus in the header for RTL layouts
 */
document.addEventListener('DOMContentLoaded', function() {
    // Only run in RTL mode
    if (document.documentElement.dir === 'rtl' || document.body.classList.contains('rtl')) {
        console.log('RTL Header Fix loaded');
        
        // Fix bootstrap dropdown positioning
        var fixHeaderDropdowns = function() {
            // Target all dropdowns in the header
            var headerDropdowns = document.querySelectorAll('.pcoded-header .dropdown');
            
            headerDropdowns.forEach(function(dropdown) {
                // Get the dropdown menu
                var menu = dropdown.querySelector('.dropdown-menu');
                if (!menu) return;
                
                // Override bootstrap's dropdown positioning
                menu.style.right = 'auto';
                menu.style.left = '0';
                menu.style.textAlign = 'right';
                menu.style.transformOrigin = 'top left';
                
                // Add event listener to the dropdown toggle
                var toggle = dropdown.querySelector('.dropdown-toggle');
                if (!toggle) return;
                
                // Remove any existing click listener
                var newToggle = toggle.cloneNode(true);
                if (toggle.parentNode) {
                    toggle.parentNode.replaceChild(newToggle, toggle);
                }
                toggle = newToggle;
                
                // Get the toggle button
                toggle.addEventListener('click', function() {
                    // Apply RTL fixes after Bootstrap shows the dropdown
                    setTimeout(function() {
                        if (menu.classList.contains('show')) {
                            // Get the toggle and dropdown dimensions
                            var toggleRect = toggle.getBoundingClientRect();
                            var menuRect = menu.getBoundingClientRect();
                            
                            // Language dropdown specific fixes (Globe icon)
                            if (toggle.querySelector('.icon-globe')) {
                                menu.style.minWidth = '160px';
                                menu.style.position = 'absolute';
                                menu.style.zIndex = '1000';
                                menu.style.left = '0';
                                menu.style.top = '100%';
                                menu.classList.add('language-dropdown-rtl-fix');
                                
                                // Calculate position to ensure dropdown stays within viewport
                                var viewportWidth = window.innerWidth;
                                if (menuRect.right > viewportWidth) {
                                    var overflow = menuRect.right - viewportWidth + 10;
                                    var newLeft = Math.max(0, toggleRect.left - overflow);
                                    menu.style.left = newLeft + 'px';
                                }
                            }
                            
                            // Settings dropdown specific fixes (Settings icon)
                            if (toggle.querySelector('.icon-settings')) {
                                menu.style.position = 'absolute';
                                menu.style.left = '0';
                                menu.style.top = '100%';
                                menu.classList.add('profile-notification');
                                
                                // Fix profile notification header
                                var proHead = menu.querySelector('.pro-head');
                                if (proHead) {
                                    proHead.style.display = 'flex';
                                    proHead.style.flexDirection = 'row-reverse';
                                    proHead.style.textAlign = 'right';
                                    
                                    // Fix logout icon position
                                    var logoutIcon = proHead.querySelector('.dud-logout');
                                    if (logoutIcon) {
                                        proHead.style.position = 'relative';
                                        logoutIcon.style.position = 'absolute';
                                        logoutIcon.style.left = '10px';
                                        logoutIcon.style.right = 'auto';
                                    }
                                }
                                
                                // Fix profile notification items
                                var proBodyItems = menu.querySelectorAll('.pro-body li a');
                                proBodyItems.forEach(function(item) {
                                    item.style.display = 'flex';
                                    item.style.flexDirection = 'row-reverse';
                                    item.style.textAlign = 'right';
                                    
                                    // Fix icons
                                    var icon = item.querySelector('i');
                                    if (icon) {
                                        icon.style.marginRight = '0';
                                        icon.style.marginLeft = '10px';
                                    }
                                });
                                
                                // Ensure dropdown stays in view
                                var viewportWidth = window.innerWidth;
                                if (menuRect.right > viewportWidth) {
                                    var overflow = menuRect.right - viewportWidth + 10;
                                    var newLeft = Math.max(0, toggleRect.left - overflow);
                                    menu.style.left = newLeft + 'px';
                                    // If mobile, ensure dropdown is not cut off
                                    if (window.innerWidth < 992) {
                                        menu.style.maxWidth = (viewportWidth - 20) + 'px';
                                    }
                                }
                            }
                        }
                    }, 10);
                });
                
                // Fix dropdown items alignment
                var items = menu.querySelectorAll('.dropdown-item');
                items.forEach(function(item) {
                    item.style.textAlign = 'right';
                    item.style.direction = 'rtl';
                    
                    // Fix items with icons
                    var icon = item.querySelector('i');
                    if (icon) {
                        item.style.display = 'flex';
                        item.style.alignItems = 'center';
                        item.style.flexDirection = 'row-reverse';
                        icon.style.marginRight = '0';
                        icon.style.marginLeft = '10px';
                    }
                });
            });
        };
        
        // Add global click event to close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            // Find all open dropdowns
            var openDropdowns = document.querySelectorAll('.pcoded-header .dropdown.show');
            
            openDropdowns.forEach(function(dropdown) {
                // Check if click is outside the dropdown
                if (!dropdown.contains(e.target)) {
                    // Find the toggle
                    var toggle = dropdown.querySelector('.dropdown-toggle');
                    if (toggle) {
                        // Close dropdown manually
                        var menu = dropdown.querySelector('.dropdown-menu');
                        if (menu) {
                            menu.classList.remove('show');
                        }
                        dropdown.classList.remove('show');
                    }
                }
            });
        });
        
        // Run immediately
        fixHeaderDropdowns();
        
        // Also run after a short delay to ensure all elements are loaded
        setTimeout(fixHeaderDropdowns, 500);
        
        // Run on window resize to adjust positions
        window.addEventListener('resize', fixHeaderDropdowns);
        
        // Additional script to specifically fix language and settings dropdowns
        var languageToggle = document.querySelector('.pcoded-header .icon.feather.icon-globe');
        var settingsToggle = document.querySelector('.pcoded-header .icon.feather.icon-settings');
        
        if (languageToggle) {
            var languageDropdown = languageToggle.closest('.dropdown');
            if (languageDropdown) {
                languageDropdown.addEventListener('show.bs.dropdown', function() {
                    setTimeout(function() {
                        var menu = languageDropdown.querySelector('.dropdown-menu');
                        if (menu) {
                            menu.style.left = '0';
                            menu.style.right = 'auto';
                        }
                    }, 0);
                });
            }
        }
        
        if (settingsToggle) {
            var settingsDropdown = settingsToggle.closest('.dropdown');
            if (settingsDropdown) {
                settingsDropdown.addEventListener('show.bs.dropdown', function() {
                    setTimeout(function() {
                        var menu = settingsDropdown.querySelector('.dropdown-menu');
                        if (menu) {
                            menu.style.left = '0';
                            menu.style.right = 'auto';
                        }
                    }, 0);
                });
            }
        }
    }
}); 