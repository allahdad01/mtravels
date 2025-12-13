/**
 * UIUtilities - Toast notifications, loading spinners, and confirmation dialogs
 */
class UIUtilities {
    constructor() {
        this.initToastContainer();
    }

    /**
     * Initialize toast container if it doesn't exist
     */
    initToastContainer() {
        if (!document.getElementById('toastContainer')) {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    /**
     * Show toast notification
     * @param {string} message - Message to display
     * @param {string} type - Type: success, error, info, warning
     * @param {number} duration - Duration in ms (0 = no auto-dismiss)
     * @returns {string} Toast ID
     */
    showToast(message, type = 'info', duration = 3000) {
        const toastId = `toast-${Date.now()}-${Math.random()}`;
        const container = document.getElementById('toastContainer');

        // Color schemes
        const colors = {
            success: { bg: '#10b981', icon: 'fa-check-circle', light: '#d1fae5' },
            error: { bg: '#ef4444', icon: 'fa-exclamation-circle', light: '#fee2e2' },
            info: { bg: '#3b82f6', icon: 'fa-info-circle', light: '#dbeafe' },
            warning: { bg: '#f59e0b', icon: 'fa-warning', light: '#fef3c7' }
        };

        const color = colors[type] || colors.info;

        const toast = document.createElement('div');
        toast.id = toastId;
        toast.style.cssText = `
            background: white;
            border-left: 4px solid ${color.bg};
            border-radius: 6px;
            padding: 12px 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            min-width: 300px;
        `;

        toast.innerHTML = `
            <i class="fas ${color.icon}" style="color: ${color.bg}; font-size: 1.2rem; flex-shrink: 0;"></i>
            <span style="flex: 1; color: #1f2937; font-size: 0.95rem;">${this.escapeHtml(message)}</span>
            <button class="toast-close" style="background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add close button handler
        toast.querySelector('.toast-close').addEventListener('click', () => {
            this.dismissToast(toastId);
        });

        container.appendChild(toast);

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                this.dismissToast(toastId);
            }, duration);
        }

        // Add animation styles if not already added
        if (!document.getElementById('toastAnimations')) {
            const style = document.createElement('style');
            style.id = 'toastAnimations';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOutRight {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        return toastId;
    }

    /**
     * Dismiss toast notification
     */
    dismissToast(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }

    /**
     * Show success toast
     */
    showSuccess(message, duration = 3000) {
        return this.showToast(message, 'success', duration);
    }

    /**
     * Show error toast
     */
    showError(message, duration = 4000) {
        return this.showToast(message, 'error', duration);
    }

    /**
     * Show warning toast
     */
    showWarning(message, duration = 3500) {
        return this.showToast(message, 'warning', duration);
    }

    /**
     * Show info toast
     */
    showInfo(message, duration = 3000) {
        return this.showToast(message, 'info', duration);
    }

    /**
     * Show loading spinner overlay
     */
    showLoadingSpinner(selector, message = 'Loading...') {
        const element = document.querySelector(selector);
        if (!element) return;

        const spinnerId = `spinner-${Date.now()}`;
        const spinner = document.createElement('div');
        spinner.id = spinnerId;
        spinner.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            z-index: 1000;
            border-radius: inherit;
        `;

        spinner.innerHTML = `
            <div class="spinner-border" role="status" style="color: #4099ff;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <small style="color: #6b7280;">${this.escapeHtml(message)}</small>
        `;

        element.style.position = 'relative';
        element.appendChild(spinner);

        return spinnerId;
    }

    /**
     * Stop loading spinner
     */
    stopLoadingSpinner(spinnerId) {
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                spinner.remove();
            }, 300);
        }
    }

    /**
     * Show confirmation dialog
     */
    showConfirm(title, message, onConfirm, onCancel = null) {
        const modalId = `confirm-${Date.now()}`;
        const modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal fade show';
        modal.style.cssText = 'display: block; background: rgba(0, 0, 0, 0.5);';
        modal.setAttribute('aria-labelledby', `${modalId}-title`);
        modal.setAttribute('aria-hidden', 'false');

        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-bottom">
                        <h5 class="modal-title" id="${modalId}-title">${this.escapeHtml(title)}</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p style="color: #374151; margin: 0;">${this.escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm btn-confirm">Confirm</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Event handlers
        const closeHandler = () => {
            modal.remove();
            if (onCancel) onCancel();
        };

        const confirmHandler = () => {
            modal.remove();
            onConfirm();
        };

        modal.querySelector('.btn-close').addEventListener('click', closeHandler);
        modal.querySelector('[data-dismiss="modal"]').addEventListener('click', closeHandler);
        modal.querySelector('.btn-confirm').addEventListener('click', confirmHandler);

        // Click outside to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeHandler();
            }
        });

        return modalId;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Show skeleton loader
     */
    showSkeletonLoader(container, count = 5) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton-loader';
        skeleton.style.cssText = 'padding: 1rem;';

        for (let i = 0; i < count; i++) {
            const item = document.createElement('div');
            item.style.cssText = `
                display: flex;
                gap: 0.75rem;
                margin-bottom: 1rem;
                align-items: center;
            `;
            item.innerHTML = `
                <div style="width: 44px; height: 44px; background: #e5e7eb; border-radius: 50%; animation: pulse 2s infinite;"></div>
                <div style="flex: 1;">
                    <div style="height: 12px; background: #e5e7eb; border-radius: 4px; margin-bottom: 8px; animation: pulse 2s infinite;"></div>
                    <div style="height: 10px; background: #f3f4f6; border-radius: 4px; width: 70%; animation: pulse 2s infinite;"></div>
                </div>
            `;
            skeleton.appendChild(item);
        }

        container.innerHTML = '';
        container.appendChild(skeleton);
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Format time duration (MM:SS)
     */
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Export for use
window.UIUtilities = UIUtilities;
