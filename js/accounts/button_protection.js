    // Enhanced button protection for all accounts forms
    document.addEventListener('DOMContentLoaded', function() {
        
        // Function to protect form submission
        function protectFormSubmission(form, buttonName, loadingText) {
            form.addEventListener('submit', function(e) {
                console.log(`Accounts form submitted with button: ${buttonName}`);
                
                const submitBtn = this.querySelector(`button[name="${buttonName}"]`) || 
                                this.querySelector(`#${buttonName}`) ||
                                this.querySelector(`button[type="submit"]`);
                
                if (submitBtn && !submitBtn.disabled) {
                    // Disable button and show loading state
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-loading');
                    
                    // Use Feather icons with proper spinning animation
                    const loadingHtml = `<i class="feather icon-refresh-cw mr-1" style="animation: spin 1s linear infinite;"></i>${loadingText}`;
                    submitBtn.innerHTML = loadingHtml;
                    
                    // Add CSS for spinner animation if not exists
                    if (!document.querySelector('#accounts-spinner-styles')) {
                        const style = document.createElement('style');
                        style.id = 'accounts-spinner-styles';
                        style.textContent = `
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            .btn-loading {
                                pointer-events: none;
                                opacity: 0.7;
                            }
                            .spinner {
                                animation: spin 1s linear infinite;
                            }
                        `;
                        document.head.appendChild(style);
                    }
                    
                    console.log(`Button ${buttonName} disabled and loading state shown`);
                }
            });
        }

        // Protect the Transfer Balance form
        const transferModal = document.getElementById('transferModal');
        if (transferModal) {
            const transferForm = document.getElementById('transferForm');
            if (transferForm) {
                protectFormSubmission(transferForm, 'transfer', 'Transferring...');
            }
        }

        // Protect the Add Main Account form
        const addMainAccountModal = document.getElementById('addMainAccountModal');
        if (addMainAccountModal) {
            const addMainAccountForm = document.getElementById('addMainAccountForm');
            if (addMainAccountForm) {
                protectFormSubmission(addMainAccountForm, 'add_main_account', 'Adding Account...');
            }
        }

        // Protect the Edit Main Account form
        const editMainAccountModal = document.getElementById('editMainAccountModal');
        if (editMainAccountModal) {
            const editMainAccountForm = document.getElementById('editMainAccountForm');
            if (editMainAccountForm) {
                protectFormSubmission(editMainAccountForm, 'edit_main_account', 'Updating Account...');
            }
        }

        // Protect the Fund Supplier form
        const fundSupplierModal = document.getElementById('fundSupplierModal');
        if (fundSupplierModal) {
            const fundSupplierForm = document.getElementById('fundSupplierForm');
            if (fundSupplierForm) {
                protectFormSubmission(fundSupplierForm, 'fund_supplier', 'Funding Account...');
            }
        }

        // Protect the Withdraw Supplier form
        const withdrawSupplierModal = document.getElementById('withdrawSupplierModal');
        if (withdrawSupplierModal) {
            const withdrawSupplierForm = document.getElementById('withdrawSupplierForm');
            if (withdrawSupplierForm) {
                protectFormSubmission(withdrawSupplierForm, 'withdraw_supplier', 'Processing Withdrawal...');
            }
        }

        // Protect the Add Bonus form
        const addBonusModal = document.getElementById('addBonusModal');
        if (addBonusModal) {
            const addBonusForm = document.getElementById('addBonusForm');
            if (addBonusForm) {
                protectFormSubmission(addBonusForm, 'add_bonus', 'Adding Bonus...');
            }
        }

        // Protect the Partial Payment form
        const partialPaymentModal = document.getElementById('partialPaymentModal');
        if (partialPaymentModal) {
            const partialPaymentForm = document.getElementById('partialPaymentForm');
            if (partialPaymentForm) {
                // This form uses a button with ID instead of name
                const submitBtn = partialPaymentForm.querySelector('#processPaymentBtn');
                if (submitBtn) {
                    submitBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (this.disabled || this.classList.contains('processing')) {
                            console.log('Payment button already processing, preventing double click');
                            return false;
                        }
                        
                        this.disabled = true;
                        this.classList.add('btn-loading');
                        this.innerHTML = '<i class="feather icon-refresh-cw mr-1" style="animation: spin 1s linear infinite;"></i>Processing Payment...';
                        
                        // Form validation and submission logic
                        if (validatePartialPaymentForm()) {
                            // Add your form submission logic here
                            console.log('Payment form being processed');
                        } else {
                            // Re-enable button if validation fails
                            setTimeout(() => {
                                this.disabled = false;
                                this.classList.remove('btn-loading');
                                this.innerHTML = '<i class="feather icon-check-circle mr-1"></i>Process Payment';
                            }, 1000);
                        }
                    });
                }
            }
        }

        // Function to validate partial payment form
        function validatePartialPaymentForm() {
            const requiredFields = ['paymentCurrency', 'totalAmount', 'exchangeRate', 'usdAmount', 'afsAmount', 'clientMainAccount'];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field || !field.value) {
                    field?.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                // Show validation message
                console.log('Partial payment form validation failed');
            }
            
            return isValid;
        }

        // Enhanced click protection for all submit buttons in accounts page
        const allSubmitButtons = document.querySelectorAll('button[type="submit"], .btn-confirm, #transferBtn, #processPaymentBtn, #saveEditMainAccountBtn, #submit-remarks-btn');
        allSubmitButtons.forEach(button => {
            // Add single click protection
            button.addEventListener('click', function(e) {
                // Check if already processing
                if (this.disabled || this.classList.contains('processing') || this.classList.contains('btn-loading')) {
                    console.log('Accounts button already processing, preventing double click');
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

        // Special handling for fund account buttons (inline buttons in cards)
        const fundAccountButtons = document.querySelectorAll('.fund-account-btn');
        fundAccountButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (this.disabled || this.classList.contains('processing')) {
                    console.log('Fund account button already processing');
                    return false;
                }
                
                const accountId = this.getAttribute('data-account-id');
                const amount = document.getElementById(`amount-${accountId}`)?.value;
                const currency = document.getElementById(`currency-${accountId}`)?.value;
                
                if (!amount || !currency) {
                    console.log('Amount and currency are required');
                    return false;
                }
                
                // Show loading state
                this.disabled = true;
                this.classList.add('processing');
                this.innerHTML = '<i class="feather icon-refresh-cw mr-1" style="animation: spin 1s linear infinite;"></i>Funding...';
                
                // Your funding logic here
                console.log(`Funding account ${accountId} with ${amount} ${currency}`);
            });
        });

        console.log('Button protection initialized for all accounts forms');
    });