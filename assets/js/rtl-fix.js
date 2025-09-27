/**
 * RTL Sidebar Fixes
 * This script applies additional fixes to sidebar layout in RTL mode
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in RTL mode
    if (document.documentElement.getAttribute('dir') === 'rtl' || 
        document.body.classList.contains('rtl')) {
        
        console.log("Applying standard RTL fixes");
        
        // Fix sidebar menu layout using flexbox
        const pcodedItems = document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar .pcoded-item li a, .pcoded-navbar .nav-item a');
        pcodedItems.forEach(function(item) {
            // Apply flexbox layout
            item.style.display = 'flex';
            item.style.flexDirection = 'row'; // Using standard row direction
            item.style.justifyContent = 'flex-start';
            item.style.alignItems = 'center';
            item.style.textAlign = 'right';
            item.style.width = '100%';
            
            // Ensure icon is positioned correctly
            const micon = item.querySelector('.pcoded-micon, i.feather, i.fas');
            if (micon) {
                micon.style.float = 'right';
                micon.style.marginRight = '0';
                micon.style.marginLeft = '15px';
                micon.style.order = '2'; // Icon comes second
            }
            
            // Ensure text is aligned properly
            const mtext = item.querySelector('.pcoded-mtext');
            if (mtext) {
                mtext.style.textAlign = 'right';
                mtext.style.float = 'right';
                mtext.style.order = '1'; // Text comes first
                // Fix width issue
                mtext.style.flexGrow = '1';
            }
        });
        
        // Fix submenu indicators
        const hasMenuItems = document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar .pcoded-hasmenu > a');
        hasMenuItems.forEach(function(item) {
            // Replace the :after pseudo element's position using a real element
            let hasIndicator = item.querySelector('.pcoded-hasmenu-indicator');
            if (!hasIndicator) {
                const indicator = document.createElement('span');
                indicator.className = 'pcoded-hasmenu-indicator';
                indicator.style.position = 'absolute';
                indicator.style.left = '20px';
                indicator.style.right = 'auto';
                indicator.style.top = '50%';
                indicator.style.transform = 'translateY(-50%) rotate(0deg)'; // Don't rotate
                indicator.innerHTML = '<i class="feather icon-chevron-left"></i>'; // Use left chevron
                item.appendChild(indicator);
            }
        });
        
        // Fix menu caption alignment
        document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar .pcoded-menu-caption').forEach(function(caption) {
            caption.style.textAlign = 'right';
            caption.style.paddingRight = '20px';
            caption.style.paddingLeft = '0';
        });
        
        // Force RTL direction on all sidebar elements
        document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar *').forEach(function(el) {
            el.style.direction = 'rtl';
        });
        
        // Fix submenu padding
        document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar .pcoded-item .pcoded-hasmenu .pcoded-submenu').forEach(function(submenu) {
            submenu.style.paddingRight = '20px';
            submenu.style.paddingLeft = '0';
            
            // Also fix submenu items
            const submenuItems = submenu.querySelectorAll('li a');
            submenuItems.forEach(function(item) {
                item.style.display = 'flex';
                item.style.flexDirection = 'row'; // Standard row direction
                item.style.justifyContent = 'flex-start';
                item.style.textAlign = 'right';
                
                // Fix icon in submenu
                const micon = item.querySelector('.pcoded-micon, i.feather, i.fas');
                if (micon) {
                    micon.style.float = 'right';
                    micon.style.marginRight = '0';
                    micon.style.marginLeft = '15px';
                    micon.style.order = '2'; // Icon second
                }
                
                // Fix text in submenu
                const mtext = item.querySelector('.pcoded-mtext');
                if (mtext) {
                    mtext.style.textAlign = 'right';
                    mtext.style.float = 'right';
                    mtext.style.order = '1'; // Text first
                }
            });
        });
        
        // Fix list items (remove any float)
        document.querySelectorAll('.pcoded-navbar .pcoded-inner-navbar .pcoded-item li').forEach(function(item) {
            item.style.float = 'none';
            item.style.width = '100%';
            item.style.position = 'relative';
        });

        // Fix submenu arrows direction
        var submenuArrows = document.querySelectorAll('.pcoded-hasmenu > a:after');
        submenuArrows.forEach(function(arrow) {
            arrow.style.transform = 'rotate(180deg)';
        });

        // Fix RTL for sidebar
        setTimeout(function() {
            console.log('Applying delayed RTL fixes');
            
            // Force sidebar to right side
            var navbar = document.querySelector('.pcoded-navbar');
            if (navbar) {
                navbar.style.right = '0';
                navbar.style.left = 'auto';
                
                // Set initial positions for mobile view
                if (window.innerWidth <= 991) {
                    if (!navbar.classList.contains('mob-open')) {
                        navbar.style.right = '-100%';
                    }
                }
            }
        }, 100);
        
        // Fix header dropdowns in RTL mode
        setTimeout(function() {
            // Fix dropdown positioning
            var headerDropdowns = document.querySelectorAll('.pcoded-header .dropdown');
            
            headerDropdowns.forEach(function(dropdown) {
                // Get dropdown menu
                var menu = dropdown.querySelector('.dropdown-menu');
                if (!menu) return;
                
                // Apply RTL styles
                menu.style.textAlign = 'right';
                menu.style.left = '0';
                menu.style.right = 'auto';
                
                // Get and modify dropdown toggle behavior
                var toggle = dropdown.querySelector('.dropdown-toggle');
                if (toggle) {
                    // Listen for click to adjust position
                    toggle.addEventListener('click', function(e) {
                        // Use timeout to let Bootstrap show the dropdown first
                        setTimeout(function() {
                            if (menu.classList.contains('show')) {
                                // Get toggle and menu dimensions
                                var toggleRect = toggle.getBoundingClientRect();
                                var menuRect = menu.getBoundingClientRect();
                                
                                // Fix language dropdown
                                if (toggle.querySelector('.icon-globe')) {
                                    menu.style.minWidth = '160px';
                                }
                                
                                // Fix position - keep in viewport
                                var viewportWidth = window.innerWidth;
                                if (menuRect.right > viewportWidth) {
                                    var overflow = menuRect.right - viewportWidth;
                                    menu.style.left = Math.max(0, (toggleRect.left - overflow - 10)) + 'px';
                                }
                            }
                        }, 10);
                    });
                }
                
                // Fix dropdown items alignment
                var items = menu.querySelectorAll('.dropdown-item');
                items.forEach(function(item) {
                    item.style.textAlign = 'right';
                    item.style.direction = 'rtl';
                    
                    // Handle items with icons
                    var icon = item.querySelector('i');
                    if (icon) {
                        item.style.display = 'flex';
                        item.style.flexDirection = 'row-reverse';
                        item.style.alignItems = 'center';
                        icon.style.marginRight = '0';
                        icon.style.marginLeft = '10px';
                    }
                });
            });
        }, 200);
    }
}); 