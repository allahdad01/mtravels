// Handle adding main accounts
document.addEventListener('DOMContentLoaded', function () {
    // Open modal when the Add Account button is clicked
    const addMainAccountBtn = document.getElementById('addMainAccountBtn');
    if (addMainAccountBtn) {
        addMainAccountBtn.addEventListener('click', function () {
            // Use jQuery modal instead of bootstrap.Modal
            $('#addMainAccountModal').modal('show');
        });
    }

    // Handle form submission for adding a main account
    const addMainAccountForm = document.getElementById('addMainAccountForm');
    if (addMainAccountForm) {
        addMainAccountForm.addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent form from submitting normally

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

            const formData = new FormData(e.target);

            fetch('../api/accounts/add_main_account.php', { // PHP file to handle adding accounts
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessToast('Main account added successfully!');
                    location.reload(); // Reload to refresh accounts
                } else {
                    showErrorToast('Error: ' + data.message);
                }
            })
            .catch(error => {

                showErrorToast('An unexpected error occurred while adding the account.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    }

    // Show/hide bank fields based on account type selection for add form
    const accountTypeSelect = document.getElementById('account_type');
    const bankFields = document.getElementById('bankFields');
    
    if (accountTypeSelect && bankFields) {
        accountTypeSelect.addEventListener('change', function() {
            if (this.value === 'bank') {
                bankFields.style.display = 'block';
                document.getElementById('bank_account_number').setAttribute('required', 'required');
            } else {
                bankFields.style.display = 'none';
                document.getElementById('bank_account_number').removeAttribute('required');
            }
        });
    }
    
    // Show/hide bank fields based on account type selection for edit form
    const editAccountTypeSelect = document.getElementById('edit_account_type');
    const editBankFields = document.getElementById('edit_bankFields');
    
    if (editAccountTypeSelect && editBankFields) {
        editAccountTypeSelect.addEventListener('change', function() {
            if (this.value === 'bank') {
                editBankFields.style.display = 'block';
                document.getElementById('edit_bank_account_number').setAttribute('required', 'required');
            } else {
                editBankFields.style.display = 'none';
                document.getElementById('edit_bank_account_number').removeAttribute('required');
            }
        });
    }

    // Attach event listeners to all Edit Account buttons
    document.querySelectorAll('.edit-main-account-btn').forEach(button => {
        button.addEventListener('click', function() {
            const accountId = this.dataset.accountId;
            
            // Fetch account details including new fields
            fetch(`../api/accounts/get_main_account.php?id=${accountId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const account = data.account;
                        
                        // Populate the edit form with account data
                        document.getElementById('edit_account_id').value = account.id;
                        document.getElementById('edit_account_name').value = account.name;
                        
                        // Set account type and toggle bank fields visibility
                        const accountTypeSelect = document.getElementById('edit_account_type');
                        accountTypeSelect.value = account.account_type || 'internal';
                        
                        // Set account status
                        const statusSelect = document.getElementById('edit_status');
                        statusSelect.value = account.status || 'active';
                        
                        // Trigger change event to show/hide bank fields
                        const event = new Event('change');
                        accountTypeSelect.dispatchEvent(event);
                        
                        // Populate bank fields if they exist
                        if (account.account_details) {
                            document.getElementById('edit_bank_account_number').value = account.account_details;
                        }                       
                        // Show the edit modal
                        $('#editMainAccountModal').modal('show');
                    } else {
                        showErrorToast('Error loading account details: ' + data.message);
                    }
                })
                .catch(error => {

                    showErrorToast('Failed to load account details. Please try again.');
                });
        });
    });
    
    // Handle form submission for editing a main account
    const editMainAccountForm = document.getElementById('editMainAccountForm');
    if (editMainAccountForm) {
        editMainAccountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            // Disable the save button and show loading state
            const saveButton = document.getElementById('saveEditMainAccountBtn');
            saveButton.disabled = true;
            saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
            
            // Send the data to the backend
            fetch('../api/accounts/edit_main_account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable the button
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="feather icon-save mr-1"></i> Save Changes';
                
                if (data.success) {
                    // Close the modal
                    $('#editMainAccountModal').modal('hide');
                    
                    // Show success message
                    showSuccessToast('Main account updated successfully!');
                    
                    // Reload the page to reflect the changes
                    location.reload();
                } else {
                    showErrorToast('Error: ' + data.message);
                }
            })
            .catch(error => {
                // Re-enable the button on error
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="feather icon-save mr-1"></i> Save Changes';
                

                showErrorToast('An error occurred while updating the account. Please try again.');
            });
        });
    }

    // Delete main account
    document.querySelectorAll('.delete-main-account-btn').forEach(button => {
        button.addEventListener('click', function() {
            const accountId = this.dataset.accountId;
            const accountName = this.dataset.accountName;

            const existingModal = document.getElementById('deleteMainAccountModal');
            if (existingModal) {
                existingModal.parentNode.removeChild(existingModal);
            }

            const modalHTML = `
            <div class="modal fade" id="deleteMainAccountModal" tabindex="-1" role="dialog" aria-labelledby="deleteMainAccountModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteMainAccountModalLabel">
                                <i class="feather icon-trash-2 mr-2"></i>Delete Account
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to permanently delete the account <strong>"${accountName}"</strong>?</p>
                            <p class="text-muted small mb-0">This action cannot be undone. Accounts with existing transactions cannot be deleted.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteMainAccountBtn">Delete Account</button>
                        </div>
                    </div>
                </div>
            </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHTML);
            $('#deleteMainAccountModal').modal('show');

            document.getElementById('confirmDeleteMainAccountBtn').addEventListener('click', function() {
                const confirmBtn = this;
                const originalHtml = confirmBtn.innerHTML;
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                fetch('../api/accounts/delete_main_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: accountId,
                        csrf_token: csrfToken
                    })
                })
                .then(response => response.json())
                .then(data => {
                    $('#deleteMainAccountModal').modal('hide');
                    if (data.success) {
                        showSuccessToast('Main account deleted successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showErrorToast('Error: ' + data.message);
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = originalHtml;
                    }
                })
                .catch(() => {
                    $('#deleteMainAccountModal').modal('hide');
                    showErrorToast('An error occurred while deleting the account. Please try again.');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalHtml;
                });
            });
        });
    });

    // NOTE: Payment modal handler now in client_payment_modal.php (openPaymentModal)
    // This old code is deprecated and replaced by the new modal's built-in submission
    /*
    // Handle client payment processing
    const processPaymentBtn = document.getElementById('processPaymentBtn');
    if (processPaymentBtn) {
        processPaymentBtn.addEventListener('click', function() {
            const form = document.getElementById('partialPaymentForm');
            const formData = new FormData(form);
            
            // Validate amounts
            const selectedCurrency = formData.get('payment_currency');
            const totalAmount = parseFloat(formData.get('total_amount')) || 0;
            const exchangeRate = parseFloat(formData.get('exchange_rate')) || 0;
            const usdAmount = parseFloat(formData.get('usd_amount')) || 0;
            const afsAmount = parseFloat(formData.get('afs_amount')) || 0;
            
            // New modal (openPaymentModal) handles validation client-side
            // Proceed with submission - backend will validate
            
            // Instead of using toast, show a confirmation modal
            // Create a confirmation modal dynamically
            const modalHtml = `
                <div class="modal fade" id="paymentConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="paymentConfirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-white">
                                <h5 class="modal-title" id="paymentConfirmationModalLabel">
                                    <i class="feather icon-alert-triangle mr-2"></i>Confirm Payment
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p>Client: ${document.getElementById('clientName').value}</p>
                                <p>Selected Currency: ${selectedCurrency}</p>
                                <p>Total Amount: ${selectedCurrency === 'USD' ? '$' : '؋'}${totalAmount.toFixed(2)}</p>
                                <p><strong>Payment Breakdown:</strong></p>
                                <ul>
                                    <li>USD Payment: $${usdAmount.toFixed(2)}</li>
                                    <li>AFS Payment: ؋${afsAmount.toFixed(2)}</li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-success" id="confirmPaymentBtn">Confirm Payment</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal with the same ID
            const existingModal = document.getElementById('paymentConfirmationModal');
            if (existingModal) {
                existingModal.parentNode.removeChild(existingModal);
            }
            
            // Append the modal to the body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show the modal
            $('#paymentConfirmationModal').modal('show');
            
            // Add event listener to the confirm button
            document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
                // Hide the modal
                $('#paymentConfirmationModal').modal('hide');
                
                // Wait for modal to close before proceeding
                $('#paymentConfirmationModal').on('hidden.bs.modal', function() {
                    // Send payment request
                    fetch('../api/accounts/fundClient.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessToast('Payment processed successfully');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showErrorToast('Payment failed: ' + data.message);
                        }
                    })
                    .catch(error => {

                        showErrorToast('An error occurred while processing the payment');
                    });
                });
            });
        });
    }
    */
});

// Client payment calculation
document.addEventListener('DOMContentLoaded', function() {
    const paymentCurrency = document.getElementById('paymentCurrency');
    const totalAmount = document.getElementById('totalAmount');
    const exchangeRate = document.getElementById('exchangeRate');
    const usdAmount = document.getElementById('usdAmount');
    const afsAmount = document.getElementById('afsAmount');
    
    // Add event listener for make-payment buttons
    document.querySelectorAll('.make-payment-btn').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.dataset.clientId;
            const clientName = this.dataset.clientName;
            const usdBalance = parseFloat(this.dataset.usdBalance);
            const afsBalance = parseFloat(this.dataset.afsBalance);
            
            // Use the new modal's API
            window.openPaymentModal(clientId, clientName, usdBalance, afsBalance);
        });
    });
    
    if (paymentCurrency) {
        paymentCurrency.addEventListener('change', function() {
            const selectedCurrency = this.value;
            const currencySymbol = selectedCurrency === 'USD' ? '$' : '؋';
            const curEl = document.getElementById('totalAmountCurrency');
            if (curEl) curEl.textContent = currencySymbol;
            updatePaymentSummary();
        });
    }
    
    // Calculate and update payment summary
    function updatePaymentSummary() {
        if (!paymentCurrency || !totalAmount || !exchangeRate || !usdAmount || !afsAmount) return;
        
        const selectedCurrency = paymentCurrency.value;
        const total = parseFloat(totalAmount.value) || 0;
        const rate = parseFloat(exchangeRate.value) || 0;
        const usd = parseFloat(usdAmount.value) || 0;
        
        if (selectedCurrency === 'USD' && rate > 0) {
            // Calculate remaining USD amount
            const remainingUsd = total - usd;
            
            // Calculate AFS amount based on remaining USD
            const calculatedAfsAmount = remainingUsd * rate;
            
            // Update AFS input field
            afsAmount.value = calculatedAfsAmount.toFixed(2);
        }
        
        const afs = parseFloat(afsAmount.value) || 0;
        
        // Calculate amounts in selected currency
        let totalPaymentInSelectedCurrency = 0;
        let remainingAmount = 0;
        
        if (selectedCurrency === 'USD') {
            // Convert AFS to USD
            const afsInUsd = rate > 0 ? afs / rate : 0;
            totalPaymentInSelectedCurrency = usd + afsInUsd;
            remainingAmount = total - totalPaymentInSelectedCurrency;
            
            // Update AFS equivalent display
            document.getElementById('afsEquivalent').textContent = rate > 0 ? 
                `Equivalent to $${afsInUsd.toFixed(2)}` : '';
        } else if (selectedCurrency === 'AFS') {
            // Convert USD to AFS
            const usdInAfs = usd * rate;
            totalPaymentInSelectedCurrency = usdInAfs + afs;
            remainingAmount = total - totalPaymentInSelectedCurrency;
            
            // Update AFS equivalent display
            document.getElementById('afsEquivalent').textContent = rate > 0 ? 
                `Equivalent to $${usd.toFixed(2)}` : '';
        }
    }
    
    // Add event listeners for amount inputs
    if (totalAmount) totalAmount.addEventListener('input', updatePaymentSummary);
    if (exchangeRate) exchangeRate.addEventListener('input', updatePaymentSummary);
    if (usdAmount) usdAmount.addEventListener('input', updatePaymentSummary);
});

// Enhanced Modal Functionality
$(document).ready(function() {
    // Ensure all modals are properly initialized with scrolling behavior
    $('.modal').modal({
        show: false,
        backdrop: true,
        keyboard: true
    }).on('shown.bs.modal', function() {
        // Reset scroll position when modal is opened
        $(this).find('.modal-body').scrollTop(0);
        
        // Adjust modal height based on screen size
        adjustModalMaxHeight($(this));
    });
    
    // Initialize date range pickers for transaction history modals
    if ($.fn.daterangepicker) {
        $('#dateRangeFilter, #clientDateRangeFilter, #supplierDateRangeFilter').daterangepicker({
            opens: 'left',
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        // Apply selected date range to the input field when user selects a date range
        $('#dateRangeFilter, #clientDateRangeFilter, #supplierDateRangeFilter').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            // Trigger filter function based on which modal is active
            if ($(this).attr('id') === 'dateRangeFilter') {
                filterMainAccountTransactions();
            } else if ($(this).attr('id') === 'clientDateRangeFilter') {
                filterClientTransactions();
            } else if ($(this).attr('id') === 'supplierDateRangeFilter') {
                filterSupplierTransactions();
            }
        });

        // Clear the input field when user clicks "Clear"
        $('#dateRangeFilter, #clientDateRangeFilter, #supplierDateRangeFilter').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            // Trigger filter function based on which modal is active
            if ($(this).attr('id') === 'dateRangeFilter') {
                filterMainAccountTransactions();
            } else if ($(this).attr('id') === 'clientDateRangeFilter') {
                filterClientTransactions();
            } else if ($(this).attr('id') === 'supplierDateRangeFilter') {
                filterSupplierTransactions();
            }
        });
    }

    // Make sure all modals are properly initialized
    $('.modal').each(function() {
        $(this).on('shown.bs.modal', function() {
            $(this).find('[autofocus]').focus();
        });
    });

    // Initialize transaction detail displays in delete modal
    $('#deleteTransactionModal').on('show.bs.modal', function (e) {
        const button = $(e.relatedTarget);
        const transactionId = button.data('transaction-id');
        const transactionType = button.data('transaction-type');
        const transactionAmount = button.data('transaction-amount');
        const transactionCurrency = button.data('transaction-currency');
        
        // Set hidden form values
        $('#deleteTransactionId').val(transactionId);
        $('#deleteTransactionType').val(transactionType);
        
        // Update display elements
        $('#deleteTransactionIdDisplay').text(transactionId);
        $('#deleteTransactionTypeDisplay').text(transactionType);
        $('#deleteTransactionAmountDisplay').text(
            (transactionCurrency === 'USD' ? '$' : transactionCurrency === 'AFS' ? '؋' : '') + 
            parseFloat(transactionAmount).toFixed(2)
        );
    });

    // Enable floating labels for modal inputs
    $('.form-control').on('focus blur', function (e) {
        $(this).parents('.form-group').toggleClass('focused', (e.type === 'focus' || this.value.length > 0));
    }).trigger('blur');
    
    // Add responsive behavior to modals
    function adjustModalMaxHeight(modal) {
        const modalBody = modal.find('.modal-body');
        const modalHeight = modal.height();
        const windowHeight = $(window).height();
        const maxHeight = windowHeight * 0.7;
        
        if (modalBody.height() > maxHeight) {
            modalBody.css('max-height', maxHeight);
        }
    }
});

// Debug function to log filter values
function logFilterValues(type, currency, receipt, dateRange) {
    console.log({
        type: type,
        currency: currency,
        receipt: receipt,
        dateRange: dateRange
    });
}

function filterMainAccountTransactions() {
    const currency = $('#mainAccountCurrencyFilter').val();
    const receipt = $('#receiptSearch').val().toLowerCase().trim();
    const dateRange = $('#dateRangeFilter').val();
    
    // Log filter values for debugging
    logFilterValues('main account', currency, receipt, dateRange);
    
    // Reload transactions with filters - get account ID and name from the modal
    const accountId = document.getElementById('mainAccountTransactionId')?.value;
    const accountName = document.getElementById('accountNameDisplay')?.textContent;
    
    if (accountId && accountName) {
        // Reload transactions with new filters
        loadTransactions('main', accountId, accountName);
    }
}

function filterClientTransactions() {
    const currency = $('#clientCurrencyFilter').val();
    const receipt = $('#clientReceiptSearch').val().toLowerCase().trim();
    const dateRange = $('#clientDateRangeFilter').val();
    
    // Log filter values for debugging
    logFilterValues('client', currency, receipt, dateRange);
    
    // Reload transactions with filters - get client ID and name from the modal
    const clientId = document.getElementById('clientTransactionId')?.value;
    const clientName = document.getElementById('clientNameDisplay')?.textContent;
    
    if (clientId && clientName) {
        // Reload transactions with new filters
        loadTransactions('client', clientId, clientName);
    }
}

function filterSupplierTransactions() {
    const receipt = $('#supplierReceiptSearch').val().toLowerCase().trim();
    const dateRange = $('#supplierDateRangeFilter').val();
    
    // Log filter values for debugging
    logFilterValues('supplier', 'N/A', receipt, dateRange);
    
    // Reload transactions with filters - get supplier ID and name from the modal
    const supplierId = document.getElementById('supplierTransactionId')?.value;
    const supplierName = document.getElementById('supplierTransNameDisplay')?.textContent;
    
    if (supplierId && supplierName) {
        // Reload transactions with new filters
        loadTransactions('supplier', supplierId, supplierName);
    }
}

// Add event listeners for filters
$(document).ready(function() {
    // Main account transaction filters
    $('#mainAccountCurrencyFilter').on('change', filterMainAccountTransactions);
    $('#receiptSearch').on('keyup input change', filterMainAccountTransactions);
    
    // Client transaction filters
    $('#clientCurrencyFilter').on('change', filterClientTransactions);
    $('#clientReceiptSearch').on('keyup input change', filterClientTransactions);
    
    // Supplier transaction filters
    $('#supplierReceiptSearch').on('keyup input change', filterSupplierTransactions);
    
    // Initialize daterangepicker when transaction modals are opened
    $('#transactionHistoryModal').on('shown.bs.modal', function() {
        initializeDateRangePicker('#dateRangeFilter', filterMainAccountTransactions);
    });
    
    $('#clientTransactionHistoryModal').on('shown.bs.modal', function() {
        initializeDateRangePicker('#clientDateRangeFilter', filterClientTransactions);
    });
    
    $('#supplierTransactionHistoryModal').on('shown.bs.modal', function() {
        initializeDateRangePicker('#supplierDateRangeFilter', filterSupplierTransactions);
    });
});

// Function to initialize daterangepicker
function initializeDateRangePicker(selector, filterFunction) {
    if ($.fn.daterangepicker) {
        $(selector).daterangepicker({
            opens: 'left',
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            },
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        // Apply selected date range to the input field when user selects a date range
        $(selector).on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            filterFunction();
        });

        // Clear the input field when user clicks "Clear"
        $(selector).on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            filterFunction();
        });
    } else {

    }
} // Function to setup and show the bonus modal
function setupBonusModal(supplierId, supplierName, supplierCurrency) {
    $('#bonusSupplierId').val(supplierId);
    $('#bonusSupplierName').val(supplierName);
    $('#bonusSupplierCurrency').val(supplierCurrency);

    $('#bonusSupplierNameDisplay').val(supplierName);
    $('#bonusSupplierCurrencyDisplay').val(supplierCurrency);

    if (supplierCurrency === 'USD') {
        $('#bonusCurrencySymbol').text('$');
    } else if (supplierCurrency === 'AFS') {
        $('#bonusCurrencySymbol').text('؋');
    } else {
        $('#bonusCurrencySymbol').text(supplierCurrency);
    }
    
    $('#addBonusModal').modal('show');
}

$(document).ready(function() {
    // Add event listener for bonus buttons
    $(document).on('click', '.add-bonus-btn', function() {
        const supplierId = $(this).data('supplier-id');
        const supplierName = $(this).data('supplier-name');
        const supplierCurrency = $(this).data('supplier-currency');
        setupBonusModal(supplierId, supplierName, supplierCurrency);
    });

    $('#addBonusForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            supplier_id: $('#bonusSupplierId').val(),
            amount: $('#bonusAmount').val(),
            receipt_number: $('#bonusReceipt').val(),
            remarks: $('#bonusRemarks').val(),
            supplier_currency: $('#bonusSupplierCurrency').val(),
            csrf_token: $('input[name="csrf_token"]').val()
        };

        const $submitButton = $(this).find('button[type="submit"]');
        const originalButtonText = $submitButton.html();
        $submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...');

        $.ajax({
            url: '../api/accounts/add_supplier_bonus.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                setTimeout(() => {
                if (response.success) {
                    showSuccessToast(response.message);
                    $('#addBonusModal').modal('hide');
                    window.location.reload();
                    } else {
                        showErrorToast(response.message);
                    }
                }, 2000);
            },
            error: function() {
                showErrorToast('An unexpected error occurred. Please try again.');
            },
            complete: function() {
                $submitButton.prop('disabled', false).html(originalButtonText);
                $('#addBonusForm')[0].reset();
            }
        });
    });
});
