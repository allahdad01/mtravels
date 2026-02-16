 // Button Protection System for Hotel Refunds
 class HotelRefundsButtonProtection {
    constructor() {
        this.init();
    }

    init() {
        this.protectViewBookingButtons();
        this.protectViewTransactionButtons();
        this.protectProcessPaymentButtons();
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

    protectViewBookingButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('a[href*="hotel.php?id="]')) {
                const link = e.target.closest('a[href*="hotel.php?id="]');
                if (link) {
                    this.protectButton(link, 'Loading...', 1000);
                }
            }
        });
    }

    protectViewTransactionButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="viewTransaction"]')) {
                const button = e.target.closest('a');
                if (button) {
                    this.protectButton(button, 'Loading...', 1000);
                }
            }
        });
    }

    protectProcessPaymentButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="processRefundTransaction"]')) {
                const button = e.target.closest('a');
                if (button) {
                    this.protectButton(button, 'Processing...', 3000);
                }
            }
        });
    }

    protectPrintAgreementButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="printRefundAgreement"]')) {
                const button = e.target.closest('a');
                if (button) {
                    this.protectButton(button, 'Printing...', 2000);
                }
            }
        });
    }

    protectDeleteRefundButtons() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('[onclick*="deleteRefund"]')) {
                const button = e.target.closest('a');
                if (button) {
                    this.protectButton(button, 'Deleting...', 2000);
                }
            }
        });
    }

    protectAddTransactionButton() {
        const button = document.querySelector('#hotelTransactionForm button[type="submit"]');
        if (button) {
            button.addEventListener('click', () => {
                this.protectButton(button, 'Adding Transaction...', 3000);
            });
        }
    }

    protectSaveChangesButton() {
        const button = document.querySelector('#editTransactionForm button[type="submit"]');
        if (button) {
            button.addEventListener('click', () => {
                this.protectButton(button, 'Saving Changes...', 3000);
            });
        }
    }
}

// Initialize button protection
const hotelRefundsButtonProtection = new HotelRefundsButtonProtection();

// DataTables removed - using server-side PHP filtering instead
