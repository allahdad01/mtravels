// Add button protection for main forms
document.addEventListener('DOMContentLoaded', function() {
    // Protect Add Payment form
    const addPaymentForm = document.querySelector('form[action*="add_additional_payment.php"]');
    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[name="add_payment"]') || document.getElementById('savePayment');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Payment...';
            }
        });
    }

    // Protect Save Payment button click handler
    const savePaymentBtn = document.getElementById('savePayment');
    if (savePaymentBtn) {
        savePaymentBtn.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving Payment...';
            }
        });
    }

    // Protect Update Payment button
    const updatePaymentBtn = document.getElementById('updatePayment');
    if (updatePaymentBtn) {
        updatePaymentBtn.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating Payment...';
            }
        });
    }

    // Protect Add Transaction button
    const addTransactionBtn = document.getElementById('AddTransaction');
    if (addTransactionBtn) {
        addTransactionBtn.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Transaction...';
            }
        });
    }

    // Protect Update Transaction button
    const updateTransactionBtn = document.getElementById('updateTransaction');
    if (updateTransactionBtn) {
        updateTransactionBtn.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Updating Transaction...';
            }
        });
    }

    // Protect Delete Payment buttons
    const deletePaymentButtons = document.querySelectorAll('.delete-payment');
    deletePaymentButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
            }
        });
    });

    // Protect Delete Transaction buttons
    const deleteTransactionButtons = document.querySelectorAll('.delete-transaction');
    deleteTransactionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!this.disabled) {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
            }
        });
    });

    // Re-enable buttons on AJAX errors
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        // Re-enable all disabled buttons on any AJAX error
        const disabledButtons = document.querySelectorAll('button:disabled');
        disabledButtons.forEach(button => {
            if (button.innerHTML.includes('spinner')) {
                // Restore original button text
                const originalText = button.innerHTML.replace(/<i class="fas fa-spinner fa-spin mr-1"><\/i>/, '').replace(/^\w+/, '');
                if (button.id === 'savePayment') {
                    button.innerHTML = 'Save Payment';
                } else if (button.id === 'updatePayment') {
                    button.innerHTML = 'Update Payment';
                } else if (button.id === 'AddTransaction') {
                    button.innerHTML = 'Add Transaction';
                } else if (button.id === 'updateTransaction') {
                    button.innerHTML = 'Update Transaction';
                } else if (button.classList.contains('delete-payment') || button.classList.contains('delete-transaction')) {
                    button.innerHTML = '<i class="feather icon-trash"></i>';
                }
                button.disabled = false;
            }
        });
    });
});
