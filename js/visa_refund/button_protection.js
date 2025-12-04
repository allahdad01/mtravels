// Button Protection System for Visa Refunds
class VisaRefundsButtonProtection {
    constructor() {
        this.init();
    }

    init() {
        this.protectProcessRefundButtons();
        this.protectPrintAgreementButtons();
        this.protectDeleteRefundButtons();
        this.protectAddTransactionButton();
        this.protectSaveChangesButton();
    }

    protectButton(button, loadingText = 'Processing...', duration = 2000) {
        if (button && !button.disabled) {
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${loadingText}`;

            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            }, duration);
        }
    }

    protectProcessRefundButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="processRefundTransaction"]')) {
                const button = e.target.closest('button');
                if (button) {
                    this.protectButton(button, 'Processing...', 3000);
                }
            }
        });
    }

    protectPrintAgreementButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="printRefundAgreement"]')) {
                const button = e.target.closest('button');
                if (button) {
                    this.protectButton(button, 'Printing...', 2000);
                }
            }
        });
    }

    protectDeleteRefundButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="deleteRefund"]')) {
                const button = e.target.closest('button');
                if (button) {
                    this.protectButton(button, 'Deleting...', 2000);
                }
            }
        });
    }

    protectAddTransactionButton() {
        // Remove this method - it conflicts with form submission
        // The transaction manager handles button protection properly
    }

    protectSaveChangesButton() {

    }
}

// Initialize button protection
const visaRefundsButtonProtection = new VisaRefundsButtonProtection();