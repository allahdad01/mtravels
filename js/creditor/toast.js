class Toast {
    constructor() {
        this.container = document.querySelector('.toast-container');
    }

    show(message, type = 'success', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        let icon = '';
        switch(type) {
            case 'success':
                icon = 'check-circle';
                break;
            case 'error':
                icon = 'alert-circle';
                break;
            case 'warning':
                icon = 'alert-triangle';
                break;
            default:
                icon = 'info';
        }

        toast.innerHTML = `
            <div class="toast-content">
                <i class="feather icon-${icon} toast-icon"></i>
                <p class="toast-message">${message}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="feather icon-x"></i>
            </button>
        `;

        this.container.appendChild(toast);

        // Auto remove after duration
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);

        // Remove on click
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        });
    }
}

// Initialize toast
const toast = new Toast();

// Remove the old alert divs
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => alert.remove());
});