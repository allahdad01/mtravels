/**
 * Modal RTL Fix
 * This file contains fixes for Bootstrap modals when using RTL languages
 */
document.addEventListener('DOMContentLoaded', function() {
    // Apply fixes for all languages, not just RTL
    // This ensures modals work correctly in all contexts
    
    console.log("Applying Modal fixes for debtors.php");
    
    // Fix for Bootstrap modals to ensure they display within the main content
    const fixModalOnShow = function(modalElement) {
        // Force modal dialog to center position
        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (modalDialog) {
            // Reset positioning to ensure proper display within main content
            modalDialog.style.right = 'auto';
            modalDialog.style.left = 'auto';
            modalDialog.style.marginRight = 'auto';
            modalDialog.style.marginLeft = 'auto';
            // Use flexbox centering for the modal
            modalElement.style.display = 'flex';
            modalElement.style.alignItems = 'center';
            modalElement.style.justifyContent = 'center';
        }
        
        // Ensure modals appear within the main content by adjusting z-index
        modalElement.style.zIndex = '1050';
        
        // Fix scrolling issues
        modalElement.style.overflow = 'auto';
        
        // Fix modal scrollable body if it exists
        const modalBody = modalElement.querySelector('.modal-body');
        if (modalBody) {
            modalBody.style.overflowY = 'auto';
            modalBody.style.maxHeight = 'calc(100vh - 200px)'; // Adjust height to ensure it fits in viewport
        }
    };
    
    // Fix for stuck backdrop issue
    const fixBackdropIssue = function() {
        // Monitor for any stuck backdrops
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    // Look for any backdrop that might be stuck
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    
                    // If there are backdrops but no visible modals, remove the backdrops
                    const activeModals = document.querySelectorAll('.modal.show');
                    if (backdrops.length > 0 && activeModals.length === 0) {
                        backdrops.forEach(backdrop => {
                            backdrop.remove();
                        });
                        document.body.classList.remove('modal-open');
                        document.body.style.paddingRight = '';
                        document.body.style.overflow = '';
                    }
                    
                    // If there are multiple backdrops but fewer modals, clean up extra backdrops
                    if (backdrops.length > activeModals.length && activeModals.length > 0) {
                        // Keep only the number of backdrops equal to active modals
                        for (let i = activeModals.length; i < backdrops.length; i++) {
                            backdrops[i].remove();
                        }
                    }
                }
            });
        });
        
        // Start observing the document body for added/removed nodes
        observer.observe(document.body, { childList: true, subtree: true });
    };
    
    // Fix modals on ESC key to ensure proper backdrop removal
    const fixEscKey = function() {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Find visible modals
                const visibleModals = document.querySelectorAll('.modal.show');
                if (visibleModals.length > 0) {
                    // Force hide all modals
                    visibleModals.forEach(modal => {
                        $(modal).modal('hide');
                    });
                    
                    // Clean up any stuck backdrops
                    setTimeout(() => {
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        const activeModals = document.querySelectorAll('.modal.show');
                        
                        if (backdrops.length > 0 && activeModals.length === 0) {
                            backdrops.forEach(backdrop => {
                                backdrop.remove();
                            });
                            document.body.classList.remove('modal-open');
                            document.body.style.paddingRight = '';
                            document.body.style.overflow = '';
                        }
                    }, 300);
                }
            }
        });
    };
    
    // Override Bootstrap's modal hide method to ensure backdrops are properly removed
    const fixModalClosing = function() {
        // Save the original Bootstrap modal hide method
        const originalHide = $.fn.modal.Constructor.prototype.hide;
        
        // Override the hide method
        $.fn.modal.Constructor.prototype.hide = function() {
            // Call the original hide method
            originalHide.apply(this, arguments);
            
            // After modal is hidden, ensure backdrop is cleared if necessary
            const self = this;
            setTimeout(() => {
                // If there are no more visible modals, remove any stuck backdrops
                const visibleModals = document.querySelectorAll('.modal.show');
                if (visibleModals.length === 0) {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.remove();
                    });
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                }
            }, 300);
        };
    };
    
    // Fix scrolling issues with modals
    const fixModalScrolling = function() {
        // Apply CSS fix for modals and backdrops
        const style = document.createElement('style');
        style.textContent = `
            /* Modal Scrolling Fixes */
            .modal {
                overflow-y: auto !important;
                z-index: 1050 !important;
            }
            .modal-dialog {
                overflow-y: initial !important;
                margin: 1.75rem auto !important; /* Force center alignment */
                position: relative !important;
            }
            .modal-body {
                overflow-y: auto !important;
                max-height: calc(100vh - 200px);
                margin-right: 0 !important;
            }
            /* Ensure modal is visible */
            .modal.show {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            /* Fix for backdrop */
            .modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1040;
                width: 100vw;
                height: 100vh;
            }
            /* Prevent body scrolling when modal is open */
            body.modal-open {
                overflow: hidden;
                padding-right: 0 !important;
            }
            /* Fix modal positioning */
            .modal-dialog {
                transform: none !important;
                left: auto !important;
                right: auto !important;
                margin: 1.75rem auto !important;
            }
            /* Ensure modals stay within the pcoded-main-container */
            .pcoded-main-container .modal {
                position: fixed;
                z-index: 1050 !important;
            }
            /* Fix modal backdrop to be properly positioned */
            .modal-backdrop {
                position: fixed !important;
            }
            @media (min-width: 576px) {
                .modal-dialog {
                    max-width: 500px;
                    margin: 1.75rem auto !important;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Override Bootstrap's modal show method to fix scrolling
        const originalShow = $.fn.modal.Constructor.prototype.show;
        $.fn.modal.Constructor.prototype.show = function() {
            // Call the original show method
            originalShow.apply(this, arguments);
            
            // Get the modal element
            const modalElement = this._element;
            
            // Fix scrolling after modal is shown
            setTimeout(() => {
                if (modalElement) {
                    // Center the modal
                    modalElement.style.display = 'flex';
                    modalElement.style.alignItems = 'center';
                    modalElement.style.justifyContent = 'center';
                    
                    // Make sure modal appears over everything else
                    modalElement.style.zIndex = '1050';
                    
                    // Fix modal dialog position
                    const modalDialog = modalElement.querySelector('.modal-dialog');
                    if (modalDialog) {
                        modalDialog.style.margin = '1.75rem auto';
                        modalDialog.style.left = 'auto';
                        modalDialog.style.right = 'auto';
                        modalDialog.style.transform = 'none';
                    }
                    
                    // Ensure the modal body is scrollable
                    const modalBody = modalElement.querySelector('.modal-body');
                    if (modalBody) {
                        modalBody.style.overflowY = 'auto';
                        modalBody.style.maxHeight = 'calc(100vh - 200px)';
                    }
                    
                    // Ensure the modal itself is scrollable if needed
                    modalElement.style.overflowY = 'auto';
                    
                    // Fix backdrop position
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.style.zIndex = '1040';
                    });
                }
            }, 100);
        };
        
        // Fix modal backdrop placement (ensure it's positioned relative to viewport)
        document.addEventListener('show.bs.modal', function() {
            // Ensure backdrop is appended to body, not to main-content
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => {
                    if (backdrop.parentElement !== document.body) {
                        document.body.appendChild(backdrop);
                    }
                });
            }, 0);
        }, true);
    };
    
    // Ensure all modals are appended to body for proper stacking
    const fixModalAppendTarget = function() {
        // Override Bootstrap's modal constructor to ensure modals are appended to body
        const originalModal = $.fn.modal;
        $.fn.modal = function(option) {
            const result = originalModal.apply(this, arguments);
            
            if (typeof option === 'object') {
                // Ensure modals are appended to body
                this.each(function() {
                    const $this = $(this);
                    if (!$this.parent().is('body')) {
                        $this.detach().appendTo('body');
                    }
                });
            }
            
            return result;
        };
        $.fn.modal.Constructor = originalModal.Constructor;
        $.fn.modal.Constructor.prototype = originalModal.Constructor.prototype;
    };
    
    // Fix for modals that might be nested in pcoded-main-container
    const fixNestedModals = function() {
        // Move all modals to body if they are nested in pcoded-main-container
        document.querySelectorAll('.pcoded-main-container .modal').forEach(modal => {
            // Only move if not already a direct child of body
            if (modal.parentElement !== document.body) {
                modal.remove();
                document.body.appendChild(modal);
            }
        });
    };
    
    // Attach to all modals
    document.querySelectorAll('.modal').forEach(function(modal) {
        // Listen for modal show event
        modal.addEventListener('show.bs.modal', function() {
            setTimeout(() => fixModalOnShow(modal), 10);
        });
        
        // Apply fix on click of modal triggers
        const modalId = modal.id;
        if (modalId) {
            const modalTriggers = document.querySelectorAll('[data-target="#' + modalId + '"], [href="#' + modalId + '"]');
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', function() {
                    setTimeout(() => fixModalOnShow(modal), 10);
                });
            });
        }
        
        // Fix backdrop removal on modal hide
        $(modal).on('hidden.bs.modal', function() {
            // Check if there are no more visible modals
            const visibleModals = document.querySelectorAll('.modal.show');
            if (visibleModals.length === 0) {
                // Remove any remaining backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length > 0) {
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                }
            }
        });
    });
    
    // Fix for direct modal showing (for modals already in DOM)
    const fixExistingModals = function() {
        document.querySelectorAll('.modal.show').forEach(fixModalOnShow);
    };
    
    // Apply all fixes
    fixBackdropIssue();
    fixEscKey();
    fixModalClosing();
    fixModalScrolling();
    fixModalAppendTarget();
    fixNestedModals();
    
    // Run initial fix
    setTimeout(fixExistingModals, 500);
    
    // Apply additional fix for modals already in the page
    setTimeout(() => {
        document.querySelectorAll('.modal').forEach(modal => {
            // Center the modal dialog
            const modalDialog = modal.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.style.margin = '1.75rem auto';
                modalDialog.style.left = 'auto';
                modalDialog.style.right = 'auto';
            }
            
            // Ensure modal is in the body element for proper stacking
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }, 1000);
}); 