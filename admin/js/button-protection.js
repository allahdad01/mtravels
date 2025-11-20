/**
 * Button Protection System
 * Prevents double-clicking and multiple form submissions
 */
class ButtonProtection {
    constructor() {
        this.protectedButtons = new Set();
        this.init();
    }

    init() {
        // Auto-protect all form submit buttons
        document.addEventListener('DOMContentLoaded', () => {
            this.protectFormButtons();
            this.protectDeleteButtons();
            this.protectModalButtons();
        });
    }

    /**
     * Protect form submit buttons
     */
    protectFormButtons() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"], button[data-submit]');
            submitButtons.forEach(button => {
                if (!button.hasAttribute('data-no-protection')) {
                    button.addEventListener('click', (e) => this.handleFormSubmit(e, button));
                }
            });
        });
    }

    /**
     * Protect delete buttons
     */
    protectDeleteButtons() {
        const deleteButtons = document.querySelectorAll('[data-delete], .delete-btn, .btn-danger');
        deleteButtons.forEach(button => {
            if (!button.hasAttribute('data-no-protection') && button.type !== 'submit') {
                button.addEventListener('click', (e) => this.handleDeleteClick(e, button));
            }
        });
    }

    /**
     * Protect modal trigger buttons
     */
    protectModalButtons() {
        const modalButtons = document.querySelectorAll('[data-toggle="modal"], [data-target]');
        modalButtons.forEach(button => {
            if (!button.hasAttribute('data-no-protection')) {
                button.addEventListener('click', (e) => this.handleModalClick(e, button));
            }
        });
    }

    /**
     * Handle form submission
     */
    handleFormSubmit(event, button) {
        if (this.isButtonProtected(button)) {
            event.preventDefault();
            return false;
        }

        this.protectButton(button, 'Submitting...');
        return true;
    }

    /**
     * Handle delete button click
     */
    handleDeleteClick(event, button) {
        if (this.isButtonProtected(button)) {
            event.preventDefault();
            return false;
        }

        this.protectButton(button, 'Deleting...', 3000); // Longer protection for delete operations
        return true;
    }

    /**
     * Handle modal button click
     */
    handleModalClick(event, button) {
        if (this.isButtonProtected(button)) {
            event.preventDefault();
            return false;
        }

        this.protectButton(button, 'Loading...', 1000, false); // Short protection without disabling
        return true;
    }

    /**
     * Check if button is already protected
     */
    isButtonProtected(button) {
        return button.disabled || button.classList.contains('btn-protected') || this.protectedButtons.has(button);
    }

    /**
     * Protect a button
     */
    protectButton(button, loadingText = 'Processing...', duration = 2000, disableButton = true) {
        const originalText = button.innerHTML;
        const originalDisabled = button.disabled;

        // Mark as protected
        this.protectedButtons.add(button);
        if (disableButton) {
            button.disabled = true;
        }
        button.classList.add('btn-protected');

        // Update button text
        if (loadingText) {
            button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${loadingText}`;
        }

        // Auto-unprotect after duration
        setTimeout(() => {
            this.unprotectButton(button, originalText, originalDisabled, disableButton);
        }, duration);
    }

    /**
     * Unprotect a button
     */
    unprotectButton(button, originalText = null, originalDisabled = false, restoreDisabledState = true) {
        this.protectedButtons.delete(button);
        button.classList.remove('btn-protected');
        if (restoreDisabledState) {
            button.disabled = originalDisabled;
        }

        if (originalText !== null) {
            button.innerHTML = originalText;
        }
    }

    /**
     * Manually protect a specific button
     */
    protect(button, loadingText = 'Processing...', duration = 2000) {
        return this.protectButton(button, loadingText, duration);
    }

    /**
     * Manually unprotect a specific button
     */
    unprotect(button) {
        return this.unprotectButton(button);
    }

    /**
     * Protect all buttons in a container
     */
    protectContainer(container, loadingText = 'Processing...') {
        const buttons = container.querySelectorAll('button, input[type="submit"], input[type="button"]');
        buttons.forEach(button => {
            if (!button.hasAttribute('data-no-protection')) {
                this.protectButton(button, loadingText);
            }
        });
    }

    /**
     * Unprotect all buttons in a container
     */
    unprotectContainer(container) {
        const buttons = container.querySelectorAll('button, input[type="submit"], input[type="button"]');
        buttons.forEach(button => {
            this.unprotectButton(button);
        });
    }
}

// Global instance
const buttonProtection = new ButtonProtection();

// Make it globally available
window.ButtonProtection = ButtonProtection;
window.buttonProtection = buttonProtection;