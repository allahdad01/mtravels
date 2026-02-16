  // Add this to your existing scripts
  
  // Function to load transactions - defined globally so it can be accessed from event handlers
  function loadTransactions(accountType, accountId, accountName) {
        let tableBody, loader, noTransactionsMessage, modal;
        
        // Set the appropriate elements based on account type
        if (accountType === 'main') {
            tableBody = document.getElementById('transactionsTableBody');
            loader = document.getElementById('transactionsLoader');
            noTransactionsMessage = document.getElementById('noTransactionsMessage');
            document.getElementById('accountNameDisplay').textContent = accountName;
            modal = new bootstrap.Modal(document.getElementById('transactionHistoryModal'));
        } else if (accountType === 'supplier') {
            tableBody = document.getElementById('supplierTransactionsTableBody');
            loader = document.getElementById('supplierTransactionsLoader');
            noTransactionsMessage = document.getElementById('noSupplierTransactionsMessage');
            document.getElementById('supplierNameDisplay').textContent = accountName;
            modal = new bootstrap.Modal(document.getElementById('supplierTransactionHistoryModal'));
        } else if (accountType === 'client') {
            tableBody = document.getElementById('clientTransactionsTableBody');
            loader = document.getElementById('clientTransactionsLoader');
            noTransactionsMessage = document.getElementById('noClientTransactionsMessage');
            document.getElementById('clientNameDisplay').textContent = accountName;
            modal = new bootstrap.Modal(document.getElementById('clientTransactionHistoryModal'));
        }
        
        // Show loader, hide no transactions message
        loader.classList.remove('d-none');
        noTransactionsMessage.classList.add('d-none');
        tableBody.innerHTML = '';
        
        // Add row number variable
        let rowNumber = 1;
        
        // Show the modal
        modal.show();
        
        // Determine endpoint based on account type
        let endpoint;
        if (accountType === 'main') {
            endpoint = '../api/accounts/get_main_account_transactions.php?account_id=' + accountId;
        } else if (accountType === 'supplier') {
            endpoint = '../api/accounts/get_supplier_transactions_main.php?supplier_id=' + accountId;
        } else if (accountType === 'client') {
            endpoint = '../api/accounts/get_client_transactions.php?client_id=' + accountId;
        }
        
        // Fetch transactions from the server
        fetch(endpoint)
            .then(response => response.json())
            .then(data => {
                // Hide loader
                loader.classList.add('d-none');
                
                if (data.length === 0) {
                    // Show no transactions message
                    noTransactionsMessage.classList.remove('d-none');
                } else {
                                // Initialize row counter
        let rowNumber = 1;
        
        // Populate table with transactions
        data.forEach(transaction => {
            const row = document.createElement('tr');
                        
                        // Format the date (handle both transaction_date and created_at fields)
                        const dateField = transaction.transaction_date || transaction.created_at;
                        const date = dateField ? new Date(dateField) : new Date();
                        const formattedDate = date.toLocaleString();
                        
                        // Format the amount with proper sign
                        const amount = parseFloat(transaction.amount || 0);
                        const amountClass = transaction.type === 'credit' || transaction.transaction_type === 'credit' ? 'text-success' : 'text-danger';
                        const formattedAmount = Math.abs(amount).toFixed(3);
                        
                        // Get transaction sign for display
                        const amountSign = transaction.type === 'credit' || transaction.transaction_type === 'credit' ? '+' : '-';
                        
                        // Get currency symbol
                        let currencySymbol = '';
                        
                        if (transaction.currency === 'USD') currencySymbol = '$';
                        else if (transaction.currency === 'AFS') currencySymbol = '؋';
                        else if (transaction.currency === 'EUR') currencySymbol = '€';
                        else if (transaction.currency === 'DARHAM') currencySymbol = 'AED';
                        
                        // Check if this transaction should show delete button based on account type
                        let showDeleteButton = false;
                        let showEditButton = false;
                        let showEditReceiptButton = false;

                        if (accountType === 'main') {
                            // For main accounts, show delete for fund, transfer, and supplier_bonus, but NOT client_fund
                            showDeleteButton = (transaction.transaction_of && (
                                transaction.transaction_of.toLowerCase() === 'fund' ||
                                transaction.transaction_of.toLowerCase() === 'transfer' ||
                                transaction.transaction_of.toLowerCase() === 'supplier_bonus'
                            ));
                            // Show edit button only for fund transactions in main accounts
                            showEditButton = (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'fund');
                            // Show edit receipt button for all main account transactions
                            showEditReceiptButton = true;
                        } else if (accountType === 'supplier') {
                            // For suppliers, show delete for supplier_bonus, fund, and fund_withdrawal transactions
                            showDeleteButton = (transaction.transaction_of && (
                                transaction.transaction_of.toLowerCase() === 'supplier_bonus' ||
                                transaction.transaction_of.toLowerCase() === 'fund' ||
                                transaction.transaction_of.toLowerCase() === 'fund_withdrawal'
                            ));
                        } else if (accountType === 'client') {
                            // For clients, show delete for fund transactions
                            showDeleteButton = (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'fund');
                        }

                        let actionsHtml = '';
                        if (showDeleteButton || showEditButton || showEditReceiptButton) {
                            actionsHtml += '<td class="text-center">';
                            if (showDeleteButton) {
                                actionsHtml += `<button class="btn btn-danger btn-sm delete-transaction-btn mr-1"
                                        data-transaction-id="${transaction.id}"
                                        data-transaction-type="${accountType}"
                                        title="Delete Transaction">
                                    <i class="feather icon-trash-2"></i>
                                </button>`;
                            }
                            if (showEditButton) {
                                actionsHtml += `<button class="btn btn-primary btn-sm edit-transaction-btn mr-1"
                                        data-transaction-id="${transaction.id}"
                                        data-transaction-type="${accountType}"
                                        data-amount="${Math.abs(amount).toFixed(3)}"
                                        data-transaction-date="${dateField || ''}"
                                        data-description="${transaction.description || ''}"
                                        data-currency="${transaction.currency || ''}"
                                        data-remarks="${transaction.remarks || ''}"
                                        data-receipt="${transaction.receipt || ''}"
                                        data-type="${transaction.type || transaction.transaction_type || ''}"
                                        title="Edit Transaction">
                                    <i class="feather icon-edit"></i>
                                </button>`;
                            }
                            if (showEditReceiptButton) {
                                actionsHtml += `<button class="btn btn-info btn-sm edit-receipt-btn"
                                        data-transaction-id="${transaction.id}"
                                        data-transaction-type="${accountType}"
                                        data-receipt="${transaction.receipt || ''}"
                                        data-transaction-date="${dateField || ''}"
                                        title="Edit Receipt">
                                    <i class="feather icon-file-text"></i>
                                </button>`;
                            }
                            actionsHtml += '</td>';
                        } else {
                            actionsHtml = `<td class="text-center">
                                    <span class="text-muted"><?= __('no_actions') ?></span>
                            </td>`;
                        }

                        const actionsCell = actionsHtml;
                        
                                        if (accountType === 'main') {
                            // Main account row format
                            const creditAmount = transaction.type === 'credit' || transaction.transaction_type === 'credit' ? 
                                `${currencySymbol}${formattedAmount}` : '-';
                            const debitAmount = transaction.type === 'debit' || transaction.transaction_type === 'debit' ? 
                                `${currencySymbol}${formattedAmount}` : '-';
                            
                            row.innerHTML = `
                                <td>${rowNumber++}</td>
                                <td>${formattedDate}</td>
                                <td style="max-width: 300px; white-space: pre-wrap; word-break: break-word;">${transaction.description || '-'}</td>
                                <td>${transaction.receipt || '-'}</td>
                                <td class="text-danger">${debitAmount}</td>
                                <td class="text-success">${creditAmount}</td>
                                <td>${transaction.balance || '-'}</td>
                                <td>${transaction.currency || '-'}</td>
                                
                                ${actionsCell}
                            `;
                                        } else if (accountType === 'supplier') {
                // Supplier row format
                let referenceText = transaction.reference_name || transaction.reference_id || '-';
                            // Format transaction_of with proper capitalization and spacing
                            let transactionOf = transaction.transaction_of || '-';
                            transactionOf = transactionOf.split('_')
                                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                                .join(' ');

                            const creditAmount = transaction.type === 'Credit' || transaction.transaction_type === 'Credit' ? 
                                `${currencySymbol}${formattedAmount}` : '-';
                            const debitAmount = transaction.type === 'Debit' || transaction.transaction_type === 'Debit' ? 
                                `${currencySymbol}${formattedAmount}` : '-';
                            
                            // Determine status badge color
                            let statusBadgeClass = 'secondary';
                            
                            
                            if (transaction.status) {
                                const status = transaction.status.toUpperCase();
                                if (status === 'COMPLETED') statusBadgeClass = 'success';
                                else if (status === 'PENDING') statusBadgeClass = 'warning';
                                else if (status === 'CANCELLED' || status === 'FAILED') statusBadgeClass = 'danger';
                                else if (status === 'PROCESSING') statusBadgeClass = 'info';
                            }
                            
                            row.innerHTML = `
                                <td>${rowNumber++}</td>
                                <td>${formattedDate}</td>
                                <td style="max-width: 300px; white-space: pre-wrap; word-break: break-word;">${transaction.remarks || '-'}</td>
                                <td>${transaction.receipt || '-'}</td>
                                <td>${transactionOf}</td>
                                <td>${referenceText}</td>
                                <td class="text-danger">${debitAmount}</td>
                                <td class="text-success">${creditAmount}</td>
                                <td>${currencySymbol}${parseFloat(transaction.balance || 0)}</td>
                                
                                
                                
                                ${actionsCell}
                            `;
                                } else if (accountType === 'client') {
                        // Client row format
                        let referenceText = transaction.reference_name || transaction.reference_id || '-';
                        // Format transaction_of with proper capitalization and spacing
                        let transactionOf = transaction.transaction_of || '-';
                        transactionOf = transactionOf.split('_')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                            .join(' ');
                        
                        const creditAmount = transaction.type === 'credit' || transaction.transaction_type === 'Credit' ? 
                            `${currencySymbol}${formattedAmount}` : '-';
                        const debitAmount = transaction.type === 'debit' || transaction.transaction_type === 'Debit' ? 
                            `${currencySymbol}${formattedAmount}` : '-';
                        
                        // Determine status badge color
                        let statusBadgeClass = 'secondary';
                        if (transaction.status) {
                            const status = transaction.status.toUpperCase();
                            if (status === 'COMPLETED') statusBadgeClass = 'success';
                            else if (status === 'PENDING') statusBadgeClass = 'warning';
                            else if (status === 'CANCELLED' || status === 'FAILED') statusBadgeClass = 'danger';
                            else if (status === 'PROCESSING') statusBadgeClass = 'info';
                        }
                        
                        row.innerHTML = `
                            <td>${rowNumber++}</td>
                            <td>${formattedDate}</td>
                            <td style="max-width: 300px; white-space: pre-wrap; word-break: break-word;">${transaction.description || '-'}</td>
                            <td>${transaction.receipt || transaction.receipt_number || '-'}</td>
                            <td>${transactionOf}</td>
                            <td>${referenceText}</td>
                            <td class="text-danger">${debitAmount}</td>
                            <td class="text-success">${creditAmount}</td>
                            <td>${transaction.balance || '-'}</td>
                            <td>${transaction.currency || '-'}</td>
                            ${actionsCell}
                        `;
                    }
                        
                        tableBody.appendChild(row);
                    });
                    
                    // Add event listeners to delete buttons
                    attachDeleteButtonListeners();

                    // Add event listeners for edit buttons (only for main account fund transactions)
                    document.querySelectorAll('.edit-transaction-btn').forEach(button => {
                        // Clone the button to remove all event listeners
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);

                        // Add click event listener to the new button
                        newButton.addEventListener('click', function(e) {
                            e.stopPropagation();

                            // Get transaction data from data attributes
                            const transactionId = this.dataset.transactionId;
                            const transactionType = this.dataset.transactionType;
                            const amount = this.dataset.amount;
                            const transactionDate = this.dataset.transactionDate;
                            const description = this.dataset.description;
                            const remarks = this.dataset.remarks;
                            const receipt = this.dataset.receipt;
                            const type = this.dataset.type;
                            const currency = this.dataset.currency;

                            // Populate the edit form
                            const editTransactionId = document.getElementById('editTransactionId');
                            const editTransactionType = document.getElementById('editTransactionType');
                            const originalAmount = document.getElementById('originalAmount');
                            const originalType = document.getElementById('originalType');
                            const editTransactionDate = document.getElementById('editTransactionDate');
                            const editTransactionAmount = document.getElementById('editTransactionAmount');
                            const editTransactionTypeSelect = document.getElementById('editTransactionTypeSelect');
                            const editTransactionCurrency = document.getElementById('editTransactionCurrency');
                            const editTransactionDescription = document.getElementById('editTransactionDescription');
                            const editTransactionReceipt = document.getElementById('editTransactionReceipt');

                            if (editTransactionId && editTransactionType && originalAmount && originalType &&
                                editTransactionDate && editTransactionAmount && editTransactionTypeSelect &&
                                editTransactionCurrency && editTransactionDescription && editTransactionReceipt) {

                                editTransactionId.value = transactionId;
                                editTransactionType.value = transactionType;
                                originalAmount.value = amount;
                                originalType.value = type;

                                // Format date for datetime-local input
                                if (transactionDate) {
                                    const date = new Date(transactionDate);
                                    const formattedDate = date.toISOString().slice(0, 16); // Format: YYYY-MM-DDTHH:MM
                                    editTransactionDate.value = formattedDate;
                                }

                                editTransactionAmount.value = amount;
                                editTransactionTypeSelect.value = type.toLowerCase();

                                // For main accounts, use description
                                editTransactionCurrency.value = currency;
                                editTransactionDescription.value = description;

                                editTransactionReceipt.value = receipt;

                                // Hide the current transaction history modal
                                $('#transactionHistoryModal').modal('hide');

                                // Show the edit modal after a short delay
                                setTimeout(() => {
                                    const editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
                                    editModal.show();
                                }, 500);
                            }
                        });
                    });

                    // Add event listeners for edit receipt buttons (main accounts only)
                    document.querySelectorAll('.edit-receipt-btn').forEach(button => {
                        // Clone the button to remove all event listeners
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);

                        // Add click event listener to the new button
                        newButton.addEventListener('click', function(e) {
                            e.stopPropagation();

                            // Get transaction data from data attributes
                            const transactionId = this.dataset.transactionId;
                            const transactionType = this.dataset.transactionType;
                            const receipt = this.dataset.receipt;
                            const transactionDate = this.dataset.transactionDate;

                            // Populate the edit receipt form
                            const editReceiptTransactionId = document.getElementById('editReceiptTransactionId');
                            const editReceiptTransactionType = document.getElementById('editReceiptTransactionType');
                            const editReceiptNumber = document.getElementById('editReceiptNumber');

                            if (editReceiptTransactionId && editReceiptTransactionType && editReceiptNumber) {
                                editReceiptTransactionId.value = transactionId;
                                editReceiptTransactionType.value = transactionType;
                                editReceiptNumber.value = receipt;

                                // Hide the current transaction history modal
                                $('#transactionHistoryModal').modal('hide');

                                // Show the edit receipt modal after a short delay
                                setTimeout(() => {
                                    const editReceiptModal = new bootstrap.Modal(document.getElementById('editReceiptModal'));
                                    editReceiptModal.show();
                                }, 500);
                            }
                        });
                    });
                }
            })
            .catch(error => {
                showErrorToast('error_fetching_transactions: ' + error);
                loader.classList.add('d-none');
                noTransactionsMessage.classList.remove('d-none');
                noTransactionsMessage.innerHTML = `
                    <i class="feather icon-alert-circle text-danger mb-2" style="font-size: 2rem;"></i>
                    <p class="text-danger">error_loading_transactions: ${error.message}</p>
                `;
            });
    }
    
    // Move the delete button event listeners to a separate function to avoid duplication
    function attachDeleteButtonListeners() {
        // First, remove any existing event listeners to prevent duplicates
        document.querySelectorAll('.delete-transaction-btn').forEach(button => {
            // Clone the button to remove all event listeners
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
        });
        
        // Now add the event listeners to the fresh buttons
         document.querySelectorAll('.delete-transaction-btn').forEach(button => {
             button.addEventListener('click', function(e) {
                 // Prevent event bubbling
                 e.stopPropagation();
                 
                 const transactionId = this.dataset.transactionId;
                 const transactionType = this.dataset.transactionType;
                 
                 // Set values in hidden form
                 document.getElementById('deleteTransactionId').value = transactionId;
                 document.getElementById('deleteTransactionType').value = transactionType;
                 
                 // Hide the current transaction history modal
                 if (transactionType === 'main') {
                     $('#transactionHistoryModal').modal('hide');
                 } else if (transactionType === 'supplier') {
                     $('#supplierTransactionHistoryModal').modal('hide');
                 } else if (transactionType === 'client') {
                     $('#clientTransactionHistoryModal').modal('hide');
                 }
                 
                 // Delete transaction directly without confirmation
                 setTimeout(() => {
                     deleteTransaction(transactionId, transactionType);
                 }, 300);
             });
         });
         
        }
        
        // DOMContentLoaded event handlers
        document.addEventListener('DOMContentLoaded', function() {
        // Handle View Transactions button clicks
        document.querySelectorAll('.view-transactions-btn').forEach(button => {
        button.addEventListener('click', function() {
            const accountId = this.dataset.accountId;
            const accountName = this.dataset.accountName;
            loadTransactions('main', accountId, accountName);
        });
        });

        // Handle Supplier Transactions button clicks
        document.querySelectorAll('.view-supplier-transactions-btn').forEach(button => {
        button.addEventListener('click', function() {
            const supplierId = this.dataset.supplierId;
            const supplierName = this.dataset.supplierName;
            loadTransactions('supplier', supplierId, supplierName);
        });
        });

        // Handle Client Transactions button clicks
        document.querySelectorAll('.view-client-transactions-btn').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.dataset.clientId;
            const clientName = this.dataset.clientName;
            loadTransactions('client', clientId, clientName);
        });
        });
        });

        // Add event listener for the save edit button (only for main account fund transactions)
if (document.getElementById('saveEditTransactionBtn')) {
document.getElementById('saveEditTransactionBtn').addEventListener('click', function() {
    // Get form data
    const form = document.getElementById('editTransactionForm');
    const formData = new FormData(form);

    // Show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> saving...';

    // Send AJAX request to update the transaction
    fetch('../api/accounts/update_transaction.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        this.disabled = false;
        this.innerHTML = 'save_changes';

        if (data.success) {
            // Close the modal
            $('#editTransactionModal').modal('hide');

            // Show success message
            showSuccessToast('transaction_updated_successfully');
            showSuccessToast('balances_have_been_recalculated');

            // Reload the transactions to show updated data
            const accountType = document.getElementById('editTransactionType').value;
            const accountId = data.account_id;
            const accountName = data.account_name;

            // Reload transactions
            loadTransactions(accountType, accountId, accountName);
        } else {
            // Show error message
            showErrorToast('error: ' + data.message);
        }
    })
    .catch(error => {
        showErrorToast('error_updating_transaction: ' + error);
        this.disabled = false;
        this.innerHTML = 'save_changes';
        showErrorToast('an_error_occurred_while_updating_the_transaction');
        showErrorToast('please_try_again');
    });
});
}

// Add event listener for the save edit receipt button (main accounts only)
if (document.getElementById('saveEditReceiptBtn')) {
document.getElementById('saveEditReceiptBtn').addEventListener('click', function() {
    // Get form data
    const transactionId = document.getElementById('editReceiptTransactionId').value;
    const transactionType = document.getElementById('editReceiptTransactionType').value;
    const receipt = document.getElementById('editReceiptNumber').value;

    // Validate receipt number
    if (!receipt.trim()) {
        showErrorToast('please_enter_a_receipt_number');
        return;
    }

    // Show loading state
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> saving...';

    // Send AJAX request to update the receipt
    fetch('../api/accounts/update_receipt.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            transaction_id: transactionId,
            transaction_type: transactionType,
            receipt: receipt
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        this.disabled = false;
        this.innerHTML = '<i class="feather icon-save mr-1"></i>Save Receipt';

        if (data.success) {
            // Close the modal
            $('#editReceiptModal').modal('hide');

            // Show success message
            showSuccessToast('receipt_updated_successfully');

            // Reload the transactions to show updated data
            const accountId = data.account_id;
            const accountName = data.account_name;

            // Reload transactions
            loadTransactions('main', accountId, accountName);
        } else {
            // Show error message
            showErrorToast('error: ' + data.message);
        }
    })
    .catch(error => {
        showErrorToast('error_updating_receipt: ' + error);
        this.disabled = false;
        this.innerHTML = '<i class="feather icon-save mr-1"></i>Save Receipt';
        showErrorToast('an_error_occurred_while_updating_the_receipt');
        showErrorToast('please_try_again');
    });
});
}

// Function to delete transaction directly
function deleteTransaction(transactionId, transactionType) {
    // Determine which endpoint to use based on transaction type
    let endpoint = '';
    switch(transactionType) {
        case 'main':
            endpoint = '../api/accounts/delete_main_account_transaction.php';
            break;
        case 'supplier':
            endpoint = '../api/accounts/delete_supplier_transaction.php';
            break;
        case 'client':
            endpoint = '../api/accounts/delete_client_transaction.php';
            break;
        default:
            showErrorToast('invalid_transaction_type');
            return;
    }
    
    // Send AJAX request to delete the transaction
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            transaction_id: transactionId,
            transaction_type: transactionType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showSuccessToast('transaction_deleted_successfully and balances have been recalculated');
            
            // Reload the entire page instead of just the transactions
            location.reload();
        } else {
            // Show error message
            showErrorToast('error: ' + data.message);
        }
    })
    .catch(error => {
        showErrorToast('error_deleting_transaction: ' + error);
        showErrorToast('an_error_occurred_while_deleting_the_transaction and please try again');
    });
}
    // Fix for modal stacking issues
    $(document).on('show.bs.modal', '.modal', function () {
        const zIndex = 1040 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(function() {
            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
        }, 0);
    });
    
