// Toast notification system
const toastConfig = {
    duration: 4000,      // Display duration in ms
    animationDuration: 300,  // Animation duration in ms
    maxToasts: 3        // Maximum number of toasts to show at once
};

// Collection to track active toasts
let activeToasts = [];

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 * @param {object} options - Optional configuration overrides
 */
function showToast(message, type = 'success', options = {}) {
    const config = { ...toastConfig, ...options };

    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    // Set icon based on type
    let icon = 'check-circle';
    switch(type) {
        case 'error':
            icon = 'alert-circle';
            break;
        case 'warning':
            icon = 'alert-triangle';
            break;
        case 'info':
            icon = 'info';
            break;
    }

    // Set toast content
    toast.innerHTML = `
        <div class="toast-title">
            <i class="feather icon-${icon} mr-2"></i>
            ${type.charAt(0).toUpperCase() + type.slice(1)}
        </div>
        <div class="toast-message">${message}</div>
    `;

    // Manage toast collection
    if (activeToasts.length >= toastConfig.maxToasts) {
        const oldestToast = activeToasts.shift();
        if (oldestToast && oldestToast.parentNode) {
            oldestToast.classList.add('toast-removing');
            setTimeout(() => oldestToast.remove(), config.animationDuration);
        }
    }

    // Add toast to container
    const container = document.querySelector('.toast-container');
    container.appendChild(toast);
    activeToasts.push(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('toast-showing'));

    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => {
            toast.remove();
            activeToasts = activeToasts.filter(t => t !== toast);
        }, config.animationDuration);
    }, config.duration);

    return toast;
}

// Convert all alerts to toasts
document.addEventListener('DOMContentLoaded', function() {
    // Success alerts
    document.querySelectorAll('.alert-success').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'success');
        alert.remove();
    });

    // Error alerts
    document.querySelectorAll('.alert-danger').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'error');
        alert.remove();
    });

    // Warning alerts
    document.querySelectorAll('.alert-warning').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'warning');
        alert.remove();
    });
});

// Optional: Replace alert() calls with toast notifications (commented out to avoid loops)
// window.oldAlert = window.alert;
// window.alert = function(message) {
//     showToast(message, 'info');
// };
