// Enhanced button protection for all forms
document.addEventListener('DOMContentLoaded', function() {

    // Print debtor receipt function
    function printDebtorReceipt(transactionId) {
        window.open(`../api/debtor/print_debtor_receipt.php?id=${transactionId}`, '_blank');
    }

    // Function to protect form submission
    function protectFormSubmission(form, buttonName, loadingText) {
        form.addEventListener('submit', function(e) {


            const submitBtn = this.querySelector(`button[name="${buttonName}"]`);
            if (submitBtn && !submitBtn.disabled) {
                // Disable button and show loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-loading');

                // Use Feather icons instead of FontAwesome
                const loadingHtml = `<i class="feather icon-refresh-cw mr-1" style="animation: spin 1s linear infinite;"></i>${loadingText}`;
                submitBtn.innerHTML = loadingHtml;

                // Add CSS for spinner animation if not exists
                if (!document.querySelector('#spinner-styles')) {
                    const style = document.createElement('style');
                    style.id = 'spinner-styles';
                    style.textContent = `
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        .btn-loading {
                            pointer-events: none;
                            opacity: 0.7;
                        }
                    `;
                    document.head.appendChild(style);
                }


            }
        });
    }

    // Protect the main Add Debtor form (in modal)
    const addDebtorModal = document.getElementById('addDebtorModal');
    if (addDebtorModal) {
        const addDebtorForm = addDebtorModal.querySelector('form');
        if (addDebtorForm) {
            protectFormSubmission(addDebtorForm, 'add_debtor', 'Adding Debtor...');
        }
    }

    // Protect all Payment forms (in modals)
    const paymentModals = document.querySelectorAll('[id^="paymentModal"]');
    paymentModals.forEach(modal => {
        const paymentForm = modal.querySelector('form');
        if (paymentForm) {
            protectFormSubmission(paymentForm, 'pay', 'Processing Payment...');
        }
    });

    // Protect all Edit Debtor forms (in modals)
    const editModals = document.querySelectorAll('[id^="editDebtorModal"]');
    editModals.forEach(modal => {
        const editForm = modal.querySelector('form');
        if (editForm) {
            protectFormSubmission(editForm, 'edit_debtor', 'Updating Debtor...');
        }
    });

    // Protect inline deactivate debtor forms
    const deactivateForms = document.querySelectorAll('form');
    deactivateForms.forEach(form => {
        const deactivateButton = form.querySelector('button[name="deactivate_debtor"]');
        if (deactivateButton) {
            protectFormSubmission(form, 'deactivate_debtor', 'Deactivating...');
        }
    });

    // Protect inline reactivate debtor forms
    const reactivateForms = document.querySelectorAll('form');
    reactivateForms.forEach(form => {
        const reactivateButton = form.querySelector('button[name="reactivate_debtor"]');
        if (reactivateButton) {
            protectFormSubmission(form, 'reactivate_debtor', 'Reactivating...');
        }
    });

    // Enhanced click protection for all submit buttons
    const allSubmitButtons = document.querySelectorAll('button[type="submit"]');
    allSubmitButtons.forEach(button => {
        // Add single click protection
        button.addEventListener('click', function(e) {
            // Check if already processing
            if (this.disabled || this.classList.contains('processing') || this.classList.contains('btn-loading')) {

                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Mark as processing immediately
            this.classList.add('processing');

            // Remove processing class after a short delay (in case form doesn't submit)
            setTimeout(() => {
                this.classList.remove('processing');
            }, 3000);
        }, true);
    });

    // Additional protection for form submission events
    const allForms = document.querySelectorAll('form');
    allForms.forEach(form => {
        // Remove processing classes when form is actually submitted
        form.addEventListener('submit', function() {

        });
    });


});

// Function to check currency and show/hide exchange rate field
function checkCurrency(selectElement, debtorCurrency, debtorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv' + debtorId);
    const baseSpan = document.getElementById('selectedCurrency' + debtorId);
    const targetSpan = document.getElementById('debtorCurrency' + debtorId);
    const exchangeRateInput = document.getElementById('exchangeRate' + debtorId);
    const helpText = document.getElementById('exchangeRateHelp' + debtorId);

    const sampleRates = {
        'USD->AFS': 72.5, 'AFS->USD': 72.5,
        'USD->EUR': 0.92, 'EUR->USD': 1.09,
        'USD->DARHAM': 3.67, 'DARHAM->USD': 3.67,
        'AFS->EUR': 78.8, 'EUR->AFS': 78.8,
        'AFS->DARHAM': 19.75, 'DARHAM->AFS': 19.75,
        'EUR->DARHAM': 3.99, 'DARHAM->EUR': 3.99,
    };

    if (selectedCurrency !== debtorCurrency) {
        exchangeRateDiv.style.display = 'block';
        exchangeRateInput.required = true;

        let base, target;
        if (debtorCurrency === 'AFS') {
            base = selectedCurrency;
            target = 'AFS';
        } else if (selectedCurrency === 'AFS') {
            base = debtorCurrency;
            target = 'AFS';
        } else {
            base = debtorCurrency;
            target = selectedCurrency;
        }
        baseSpan.textContent = base;
        targetSpan.textContent = target;
        const rate = sampleRates[base + '->' + target];
        helpText.textContent = rate
            ? 'e.g. 1 ' + base + ' = ' + rate + ' ' + target + ' → enter ' + rate
            : 'Enter the rate for 1 ' + base + ' = ? ' + target;
    } else {
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
