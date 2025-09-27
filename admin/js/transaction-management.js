  // Add this to your existing scripts
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

    // Function to load transactions
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
            endpoint = 'get_main_account_transactions.php?account_id=' + accountId;
        } else if (accountType === 'supplier') {
            endpoint = 'get_supplier_transactions_main.php?supplier_id=' + accountId;
        } else if (accountType === 'client') {
            endpoint = 'get_client_transactions.php?client_id=' + accountId;
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
                        else if (transaction.currency === 'DARHAM') currencySymbol = 'د.أ.ف.س';
                        
                        // Check if this is a fund transaction - only show actions for fund transactions
                        const isFundTransaction = 
                            (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'fund') ||
                            (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'transfer')||
                            (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'supplier_bonus')||
                            (transaction.transaction_of && transaction.transaction_of.toLowerCase() === 'client_fund');
                        
                        const actionsCell = isFundTransaction ? 
                            `<td class="text-center">
                                <button class="btn btn-danger btn-sm delete-transaction-btn mr-1" 
                                        data-transaction-id="${transaction.id}"
                                        data-transaction-type="${accountType}"
                                        title="Delete Transaction">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                <button class="btn btn-primary btn-sm edit-transaction-btn" 
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
                                </button>
                            </td>` : 
                            `<td class="text-center">
                                    <span class="text-muted"><?= __('no_actions') ?></span>
                            </td>`;
                        
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
        
        // Add event listeners for edit buttons
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
                document.getElementById('editTransactionId').value = transactionId;
                document.getElementById('editTransactionType').value = transactionType;
                document.getElementById('originalAmount').value = amount;
                document.getElementById('originalType').value = type;
                
                // Format date for datetime-local input
                if (transactionDate) {
                    const date = new Date(transactionDate);
                    const formattedDate = date.toISOString().slice(0, 16); // Format: YYYY-MM-DDTHH:MM
                    document.getElementById('editTransactionDate').value = formattedDate;
                }
                
                document.getElementById('editTransactionAmount').value = amount;
                document.getElementById('editTransactionTypeSelect').value = type.toLowerCase();
                
                // For suppliers, use remarks instead of description and determine currency from symbol
                if (transactionType === 'supplier') {
                    // Get currency from the currency symbol in the amount column
                    const currencySymbol = this.closest('tr').querySelector('td:nth-child(5)').textContent.trim().charAt(0);
                    let currencyValue = 'USD';
                    if (currencySymbol === '؋') currencyValue = 'AFS';
                    else if (currencySymbol === '€') currencyValue = 'EUR';
                    else if (currencySymbol === 'د') currencyValue = 'DARHAM';
                    
                    document.getElementById('editTransactionCurrency').value = currencyValue;
                    document.getElementById('editTransactionDescription').value = remarks || '';
                } else {
                    document.getElementById('editTransactionCurrency').value = currency;
                    document.getElementById('editTransactionDescription').value = description;
                }
                
                document.getElementById('editTransactionReceipt').value = receipt;
                
                // Hide the current transaction history modal
                if (transactionType === 'main') {
                    $('#transactionHistoryModal').modal('hide');
                } else if (transactionType === 'supplier') {
                    $('#supplierTransactionHistoryModal').modal('hide');
                } else if (transactionType === 'client') {
                    $('#clientTransactionHistoryModal').modal('hide');
                }
                
                // Show the edit modal after a short delay
                setTimeout(() => {
                    const editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
                    editModal.show();
                }, 500);
            });
        });
    }
    
    // Add event listener for the save edit button
    document.getElementById('saveEditTransactionBtn').addEventListener('click', function() {
        // Get form data
        const form = document.getElementById('editTransactionForm');
        const formData = new FormData(form);
        
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> saving...';
        
        // Send AJAX request to update the transaction
        fetch('update_transaction.php', {
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

    // Fix for edit modal appearing behind transaction history modal
    document.addEventListener('DOMContentLoaded', function() {
        // This ensures the script runs after all elements are loaded
        $(document).on('click', '.edit-transaction-btn', function(e) {
            e.preventDefault();
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
            document.getElementById('editTransactionId').value = transactionId;
            document.getElementById('editTransactionType').value = transactionType;
            document.getElementById('originalAmount').value = amount;
            document.getElementById('originalType').value = type;
            
            // Format date for datetime-local input
            if (transactionDate) {
                const date = new Date(transactionDate);
                const formattedDate = date.toISOString().slice(0, 16); // Format: YYYY-MM-DDTHH:MM
                document.getElementById('editTransactionDate').value = formattedDate;
            }
            
            document.getElementById('editTransactionAmount').value = amount;
            document.getElementById('editTransactionTypeSelect').value = type.toLowerCase();
            
            // For suppliers, use remarks instead of description and determine currency from symbol
            if (transactionType === 'supplier') {
                // Get currency from the currency symbol in the amount column
                const currencySymbol = this.closest('tr').querySelector('td:nth-child(5)').textContent.trim().charAt(0);
                let currencyValue = 'USD';
                if (currencySymbol === '؋') currencyValue = 'AFS';
                else if (currencySymbol === '€') currencyValue = 'EUR';
                else if (currencySymbol === 'د') currencyValue = 'DARHAM';
                
                document.getElementById('editTransactionCurrency').value = currencyValue;
                document.getElementById('editTransactionDescription').value = remarks || '';
            } else {
                document.getElementById('editTransactionCurrency').value = currency;
                document.getElementById('editTransactionDescription').value = description;
            }
            
            document.getElementById('editTransactionReceipt').value = receipt;
            
            // Hide all transaction history modals first
            $('#transactionHistoryModal').modal('hide');
            $('#supplierTransactionHistoryModal').modal('hide');
            $('#clientTransactionHistoryModal').modal('hide');
            
            // Wait for modals to be fully hidden before showing the edit modal
            setTimeout(function() {
                $('#editTransactionModal').modal('show');
            }, 500);
        });
    });  
});

// Function to delete transaction directly
function deleteTransaction(transactionId, transactionType) {
    // Determine which endpoint to use based on transaction type
    let endpoint = '';
    switch(transactionType) {
        case 'main':
            endpoint = 'delete_main_account_transaction.php';
            break;
        case 'supplier':
            endpoint = 'delete_supplier_transaction.php';
            break;
        case 'client':
            endpoint = 'delete_client_transaction.php';
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
    
    // Ensure edit modal is properly displayed when opened from another modal
    $(document).on('click', '.edit-transaction-btn', function(e) {
        e.preventDefault();
        
        // If needed, close the current modal first
        // $('#transactionHistoryModal, #clientTransactionHistoryModal, #supplierTransactionHistoryModal').modal('hide');
        
        // Show the edit modal with a slight delay to ensure proper stacking
        setTimeout(function() {
            $('#editTransactionModal').modal('show');
        }, 150);
    });