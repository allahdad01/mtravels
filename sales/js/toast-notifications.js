/**
 * Toast Notification System
 * Modern, animated toast notifications for the admin panel
 */

// Store toast configuration
const toastConfig = {
    duration: 4000,      // Display duration in ms
    animationDuration: 300,  // Animation duration in ms
    position: 'top-right', // Default position
    maxToasts: 3,       // Maximum number of toasts to show at once
    container: '.toast-container'
};

// Collection to track active toasts
let activeToasts = [];

/**
 * Create and show a success toast notification
 * @param {string} message - The message to display
 * @param {object} options - Optional configuration overrides
 */
function showSuccessToast(message, options = {}) {
    const config = { ...toastConfig, ...options };
    const title = options.title || 'Success';
    
    return showToast({
        type: 'success',
        title: title,
        message: message,
        icon: 'check-circle',
        ...config
    });
}

/**
 * Create and show an error toast notification
 * @param {string} message - The message to display
 * @param {object} options - Optional configuration overrides
 */
function showErrorToast(message, options = {}) {
    const config = { ...toastConfig, ...options };
    const title = options.title || 'Error';
    
    return showToast({
        type: 'error',
        title: title,
        message: message,
        icon: 'alert-circle',
        ...config
    });
}

/**
 * Create and show an info toast notification
 * @param {string} message - The message to display
 * @param {object} options - Optional configuration overrides
 */
function showInfoToast(message, options = {}) {
    const config = { ...toastConfig, ...options };
    const title = options.title || 'Information';
    
    return showToast({
        type: 'info',
        title: title,
        message: message,
        icon: 'info',
        ...config
    });
}

/**
 * Create and show a warning toast notification
 * @param {string} message - The message to display
 * @param {object} options - Optional configuration overrides
 */
function showWarningToast(message, options = {}) {
    const config = { ...toastConfig, ...options };
    const title = options.title || 'Warning';
    
    return showToast({
        type: 'warning',
        title: title,
        message: message,
        icon: 'alert-triangle',
        ...config
    });
}

/**
 * Core toast creation and management function
 * @param {object} config - Toast configuration
 */
function showToast(config) {
    // Make sure toast container exists
    ensureContainer(config.container);
    
    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${config.type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // Set toast content
    toast.innerHTML = `
        <div class="toast-title">
            <i class="feather icon-${config.icon} mr-2"></i>
            ${config.title}
        </div>
        <div class="toast-message">${config.message}</div>
    `;
    
    // Manage toast collection
    if (activeToasts.length >= toastConfig.maxToasts) {
        // Remove oldest toast if we're at the limit
        const oldestToast = activeToasts.shift();
        if (oldestToast && oldestToast.parentNode) {
            oldestToast.classList.add('toast-removing');
            setTimeout(() => {
                if (oldestToast.parentNode) {
                    oldestToast.parentNode.removeChild(oldestToast);
                }
            }, 300);
        }
    }
    
    // Add toast to the container
    const container = document.querySelector(config.container);
    container.appendChild(toast);
    activeToasts.push(toast);
    
    // Trigger animation by setting opacity after a small delay
    requestAnimationFrame(() => {
        toast.classList.add('toast-showing');
    });
    
    // Set auto-dismiss timer
    const dismissTimeout = setTimeout(() => {
        dismissToast(toast);
    }, config.duration);
    
    // Allow manual dismissal
    toast.addEventListener('click', () => {
        clearTimeout(dismissTimeout);
        dismissToast(toast);
    });
    
    // Return the toast element in case caller needs it
    return toast;
}

/**
 * Ensure the toast container exists
 * @param {string} containerSelector - CSS selector for the container
 */
function ensureContainer(containerSelector) {
    if (!document.querySelector(containerSelector)) {
        const container = document.createElement('div');
        container.className = containerSelector.replace('.', '');
        document.body.appendChild(container);
        
        // Add custom styles for positioning
        Object.assign(container.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '9999',
            maxWidth: '350px'
        });
    }
}

/**
 * Dismiss a toast with animation
 * @param {HTMLElement} toast - The toast element to dismiss
 */
function dismissToast(toast) {
    // Remove from active toasts collection
    activeToasts = activeToasts.filter(t => t !== toast);
    
    // Animate out
    toast.classList.remove('toast-showing');
    toast.classList.add('toast-removing');
    
    // Remove from DOM after animation
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, toastConfig.animationDuration);
}

// Add necessary CSS if not already present
function injectToastStyles() {
    if (document.getElementById('toast-notification-styles')) {
        return; // Styles already added
    }
    
    const styleElement = document.createElement('style');
    styleElement.id = 'toast-notification-styles';
    styleElement.textContent = `
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .toast {
            position: relative;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            overflow: hidden;
            opacity: 0;
            transform: translateX(40px);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            padding: 15px;
        }
        
        .toast-showing {
            opacity: 1;
            transform: translateX(0);
        }
        
        .toast-removing {
            opacity: 0;
            transform: translateY(-20px);
        }
        
        .toast-success {
            border-left-color: #10b981;
        }
        
        .toast-error {
            border-left-color: #ef4444;
        }
        
        .toast-warning {
            border-left-color: #f59e0b;
        }
        
        .toast-info {
            border-left-color: #3b82f6;
        }
        
        .toast-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .toast-message {
            word-break: break-word;
            line-height: 1.5;
            color: #64748b;
        }
    `;
    
    document.head.appendChild(styleElement);
}

// Initialize the toast system
document.addEventListener('DOMContentLoaded', function() {
    injectToastStyles();
}); 