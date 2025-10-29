/**
 * Fixed Elements Visibility Controller
 * 
 * This script ensures that fixed elements like floating buttons and footer
 * remain visible regardless of modal state or other DOM changes.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Force redraw of fixed elements periodically to ensure visibility
    setInterval(function() {
        const floatingButton = document.getElementById('floatingActionButton');
        const footerWrapper = document.querySelector('.footer-wrapper');
        
        if (floatingButton) {
            // Toggle a class to force redraw
            floatingButton.classList.add('force-visible');
            setTimeout(() => floatingButton.classList.remove('force-visible'), 10);
        }
        
        if (footerWrapper) {
            // Toggle a class to force redraw
            footerWrapper.classList.add('force-visible');
            setTimeout(() => footerWrapper.classList.remove('force-visible'), 10);
        }
    }, 500);
    
    // Fix z-index issues when modals are opened
    const modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function() {
            document.getElementById('floatingActionButton').style.zIndex = '1999';
            
            const footerWrapper = document.querySelector('.footer-wrapper');
            if (footerWrapper) {
                footerWrapper.style.zIndex = '1060';
            }
        });
    });
}); 