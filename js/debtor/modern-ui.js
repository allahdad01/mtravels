/**
 * Modern UI - Enhanced UI interactions and modal functionality
 * This file replaces the debtors-modal-fix.js with modern UI interactions
 */

// Add this at the beginning of the file
console.log('modern-ui.js loaded');

// Toast notification configuration
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    customClass: {
        popup: 'swal2-toast'
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

// Modal enhancements
class ModernModal {
    constructor() {
        this.initModalHandlers();
        this.fixModalBackdropIssues();
        this.enhanceModals();
    }

    initModalHandlers() {
        // Add icons to modal titles
        document.querySelectorAll('.modal').forEach(modal => {
            const titleElement = modal.querySelector('.modal-title');
            if (titleElement && !titleElement.querySelector('i')) {
                const modalId = modal.id;
                let icon = 'feather icon-box';
                
                // Set appropriate icon based on modal type
                if (modalId.includes('payment')) {
                    icon = 'feather icon-credit-card';
                } else if (modalId.includes('edit')) {
                    icon = 'feather icon-edit-2';
                } else if (modalId.includes('add')) {
                    icon = 'feather icon-plus-circle';
                } else if (modalId.includes('transaction')) {
                    icon = 'feather icon-list';
                } else if (modalId.includes('profile')) {
                    icon = 'feather icon-user';
                } else if (modalId.includes('settings')) {
                    icon = 'feather icon-settings';
                }
                
                const iconElement = document.createElement('i');
                iconElement.className = icon;
                titleElement.insertBefore(iconElement, titleElement.firstChild);
            }
        });

        // Add animation classes to modals
        $(document).on('show.bs.modal', '.modal', function (e) {
            $(this).addClass('zoom-in');
            
            // Add form-floating class to form groups
            $(this).find('.form-group').each(function() {
                const input = $(this).find('input, textarea, select');
                const label = $(this).find('label');
                
                if (input.length && label.length && !$(this).hasClass('form-check') && !$(this).hasClass('form-floating')) {
                    $(this).addClass('form-floating');
                    input.attr('placeholder', ' ');
                    
                    // Move label after input for floating label effect
                    $(this).append(label);
                }
            });
        });
        
        // Clean up after modal is hidden
        $(document).on('hidden.bs.modal', '.modal', function (e) {
            // Remove any stuck backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            const visibleModals = document.querySelectorAll('.modal.show');
            
            if (backdrops.length > visibleModals.length) {
                for (let i = visibleModals.length; i < backdrops.length; i++) {
                    backdrops[i].remove();
                }
                
                if (visibleModals.length === 0) {
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                }
            }
        });
    }

    fixModalBackdropIssues() {
        // Fix for multiple backdrops issue
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    const visibleModals = document.querySelectorAll('.modal.show');
                    
                    // If there are backdrops but no visible modals, remove the backdrops
                    if (backdrops.length > 0 && visibleModals.length === 0) {
                        backdrops.forEach(backdrop => {
                            backdrop.remove();
                        });
                        document.body.classList.remove('modal-open');
                        document.body.style.paddingRight = '';
                        document.body.style.overflow = '';
                    }
                    
                    // If there are multiple backdrops but fewer modals, clean up extra backdrops
                    if (backdrops.length > visibleModals.length && visibleModals.length > 0) {
                        for (let i = visibleModals.length; i < backdrops.length; i++) {
                            backdrops[i].remove();
                        }
                    }
                }
            });
        });
        
        // Start observing the document body for added/removed nodes
        observer.observe(document.body, { childList: true, subtree: true });
    }

    enhanceModals() {
        // Override Bootstrap's modal show method
        const originalShow = $.fn.modal.Constructor.prototype.show;
        $.fn.modal.Constructor.prototype.show = function() {
            // Call the original show method
            originalShow.apply(this, arguments);
            
            // Get the modal element
            const modalElement = this._element;
            
            // Fix scrolling after modal is shown
            setTimeout(() => {
                if (modalElement) {
                    // Make sure modal appears over everything else
                    modalElement.style.zIndex = '1050';
                    
                    // Ensure the modal body is scrollable
                    const modalBody = modalElement.querySelector('.modal-body');
                    if (modalBody) {
                        modalBody.style.overflowY = 'auto';
                        modalBody.style.maxHeight = 'calc(100vh - 200px)';
                    }
                    
                    // Fix backdrop position
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => {
                        backdrop.style.zIndex = '1040';
                    });
                }
            }, 100);
        };
        
        // Override Bootstrap's modal hide method
        const originalHide = $.fn.modal.Constructor.prototype.hide;
        $.fn.modal.Constructor.prototype.hide = function() {
            // Call the original hide method
            originalHide.apply(this, arguments);
            
            // After modal is hidden, ensure backdrop is cleared if necessary
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
    }
}

// UI enhancements
class ModernUI {
    constructor() {
        this.initAnimations();
        this.enhanceFormElements();
        this.addRippleEffect();
    }

    initAnimations() {
        // Add fade-in animation to cards
        document.querySelectorAll('.card').forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('fade-in');
            }, index * 100);
        });
        
        // Add animation to table rows
        document.querySelectorAll('tbody tr').forEach((row, index) => {
            row.style.opacity = '0';
            setTimeout(() => {
                row.style.opacity = '1';
                row.classList.add('slide-in');
            }, 100 + (index * 50));
        });
        
        // Add animation to buttons
        document.querySelectorAll('.btn').forEach((btn) => {
            btn.addEventListener('mousedown', function(e) {
                const x = e.clientX - this.getBoundingClientRect().left;
                const y = e.clientY - this.getBoundingClientRect().top;
                
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    enhanceFormElements() {
        // Add icons to form labels
        document.querySelectorAll('.form-label').forEach(label => {
            if (!label.querySelector('i')) {
                const input = label.parentElement.querySelector('input, select, textarea');
                if (input) {
                    let icon = 'feather icon-type';
                    
                    // Set appropriate icon based on input type or name
                    if (input.type === 'email' || input.name?.includes('email')) {
                        icon = 'feather icon-mail';
                    } else if (input.type === 'password' || input.name?.includes('password')) {
                        icon = 'feather icon-lock';
                    } else if (input.type === 'tel' || input.name?.includes('phone')) {
                        icon = 'feather icon-phone';
                    } else if (input.type === 'date' || input.name?.includes('date')) {
                        icon = 'feather icon-calendar';
                    } else if (input.name?.includes('name')) {
                        icon = 'feather icon-user';
                    } else if (input.name?.includes('address')) {
                        icon = 'feather icon-map-pin';
                    } else if (input.name?.includes('amount') || input.name?.includes('balance')) {
                        icon = 'feather icon-dollar-sign';
                    } else if (input.name?.includes('currency')) {
                        icon = 'feather icon-globe';
                    } else if (input.name?.includes('description')) {
                        icon = 'feather icon-file-text';
                    }
                    
                    const iconElement = document.createElement('i');
                    iconElement.className = icon;
                    label.insertBefore(iconElement, label.firstChild);
                }
            }
        });
        
        // Enhance select elements
        document.querySelectorAll('select.form-control').forEach(select => {
            select.classList.add('custom-select');
        });
    }

    addRippleEffect() {
        // Add CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Show success/error messages with toast if they exist in the DOM
function showToastMessages() {
    if (document.querySelector('.alert-success')) {
        const message = document.querySelector('.alert-success').textContent;
        document.querySelector('.alert-success').remove();
        Toast.fire({
            icon: 'success',
            title: message
        });
    }
    
    if (document.querySelector('.alert-danger')) {
        const message = document.querySelector('.alert-danger').textContent;
        document.querySelector('.alert-danger').remove();
        Toast.fire({
            icon: 'error',
            title: message
        });
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modern UI components
    const modernModal = new ModernModal();
    const modernUI = new ModernUI();
    
    // Show toast messages
    showToastMessages();
    
    // Fix for DataTables search input
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            initComplete: function() {
                // Add animation to table rows
                this.api().rows().nodes().each(function(row, index) {
                    $(row).css('opacity', '0');
                    setTimeout(() => {
                        $(row).css('opacity', '1').addClass('slide-in');
                    }, 100 + (index * 50));
                });
            }
        });
    }
    
    // Add click handler for confirmation dialogs
    $(document).on('submit', 'form[name="delete_transaction_form"]', function(e) {
        e.preventDefault();
        const form = this;
        
        Swal.fire({
            title: confirmDeleteTransactionText || 'Delete Transaction?',
            text: deleteTransactionWarningText || 'This will reverse the payment. Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5370',
            cancelButtonColor: '#6c757d',
            confirmButtonText: yesDeleteText || 'Yes, delete it',
            cancelButtonText: cancelText || 'Cancel',
            customClass: {
                popup: 'zoom-in'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
    
    // Add click handler for debtor status forms
    $(document).on('submit', 'form[name="debtor_status_form"]', function(e) {
        e.preventDefault();
        const form = this;
        const isDeactivate = form.querySelector('button[name="deactivate_debtor"]') !== null;
        
        Swal.fire({
            title: isDeactivate ? (confirmDeactivateText || 'Deactivate Debtor?') : (confirmReactivateText || 'Reactivate Debtor?'),
            text: isDeactivate ? (deactivateWarningText || 'This debtor will be moved to inactive list') : (reactivateInfoText || 'This debtor will be moved back to active list'),
            icon: isDeactivate ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: isDeactivate ? '#FF5370' : '#2ed8b6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: isDeactivate ? (yesDeactivateText || 'Yes, deactivate') : (yesReactivateText || 'Yes, reactivate'),
            cancelButtonText: cancelText || 'Cancel',
            customClass: {
                popup: 'zoom-in'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
}); 

// Debug modal functionality
$(document).ready(function() {
    console.log('Document ready in modern-ui.js');
    
    // Log all modals found in the page
    const modals = $('.modal');
    console.log(`Found ${modals.length} modals on the page:`);
    modals.each(function(index) {
        console.log(`Modal ${index + 1}: #${$(this).attr('id')}`);
    });
    
    // Add global modal event listeners for debugging
    $(document).on('show.bs.modal', '.modal', function(e) {
        console.log(`Modal #${$(this).attr('id')} is about to be shown`);
    });
    
    $(document).on('shown.bs.modal', '.modal', function(e) {
        console.log(`Modal #${$(this).attr('id')} is now visible`);
    });
    
    $(document).on('hide.bs.modal', '.modal', function(e) {
        console.log(`Modal #${$(this).attr('id')} is about to be hidden`);
    });
    
    $(document).on('hidden.bs.modal', '.modal', function(e) {
        console.log(`Modal #${$(this).attr('id')} is now hidden`);
    });
    
    // Add specific listener for edit transaction button
    $(document).on('click', '.edit-transaction-btn', function() {
        console.log('Edit transaction button clicked (from modern-ui.js)');
    });
}); 