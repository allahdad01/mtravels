// Print debtor receipt function
function printDebtorReceipt(transactionId) {
    window.open(`../api/debtor/print_debtor_receipt.php?id=${transactionId}`, '_blank');
}

// This script will ensure the edit transaction button works
document.addEventListener('DOMContentLoaded', function() {

    // Direct event handler for all edit transaction buttons
    const editButtons = document.querySelectorAll('.edit-transaction-btn');
    console.log('Found ' + editButtons.length + ' edit transaction buttons');

    editButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Edit button clicked directly');

            // Get data attributes
            const transactionId = this.getAttribute('data-transaction-id');
            const debtorId = this.getAttribute('data-debtor-id');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');
            const description = this.getAttribute('data-description');
            const paymentDate = this.getAttribute('data-payment-date');
            const createdAt = this.getAttribute('data-created-at');

            console.log('Transaction data:', {
                transactionId,
                debtorId,
                amount,
                currency,
                description,
                paymentDate,
                createdAt
            });

            // Close any open modals first
            $('.modal').modal('hide');

            // Wait a moment for any previous modal to close
            setTimeout(function() {
                // Set form values
                document.getElementById('edit_transaction_id').value = transactionId;
                document.getElementById('edit_debtor_id').value = debtorId;
                document.getElementById('edit_original_amount').value = amount;
                document.getElementById('edit_currency').value = currency;
                document.getElementById('edit_amount').value = amount;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_payment_date').value = paymentDate;

                // Handle created_at datetime
                if (createdAt) {
                    const createdAtObj = new Date(createdAt);
                    // Format time as HH:MM
                    const hours = createdAtObj.getHours().toString().padStart(2, '0');
                    const minutes = createdAtObj.getMinutes().toString().padStart(2, '0');
                    document.getElementById('edit_created_at_time').value = `${hours}:${minutes}`;
                    document.getElementById('edit_created_at_date').value = paymentDate;
                }

                // Show the modal
                $('#editTransactionModal').modal('show');
            }, 300);
        });
    });

    // Direct event handler for the save button
    const saveButton = document.getElementById('saveTransactionBtn');
    if (saveButton) {
        console.log('Save button found, attaching direct handler');
        saveButton.addEventListener('click', function(e) {
            console.log('Save button clicked directly');

            const form = document.getElementById('editTransactionForm');

            // Enhanced validation
            const amount = document.getElementById('edit_amount').value;
            const description = document.getElementById('edit_description').value;
            const paymentDate = document.getElementById('edit_payment_date').value;

            // Validate required fields with visual feedback
            let isValid = true;

            if (!amount || amount <= 0) {
                console.log('Amount validation failed');
                const field = document.getElementById('edit_amount');
                field.classList.add('is-invalid');
                isValid = false;
            }

            if (!description.trim()) {
                console.log('Description validation failed');
                const field = document.getElementById('edit_description');
                field.classList.add('is-invalid');
                isValid = false;
            }

            if (!paymentDate) {
                console.log('Payment date validation failed');
                const field = document.getElementById('edit_payment_date');
                field.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                console.log('Form validation failed');
                Swal.fire({
                    icon: 'warning',
                    title: 'Please complete all required fields',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }

            // Collect form data
            const formData = new FormData();
            formData.append('transaction_id', document.getElementById('edit_transaction_id').value);
            formData.append('debtor_id', document.getElementById('edit_debtor_id').value);
            formData.append('original_amount', document.getElementById('edit_original_amount').value);
            formData.append('amount', document.getElementById('edit_amount').value);
            formData.append('currency', document.getElementById('edit_currency').value);
            formData.append('description', document.getElementById('edit_description').value);
            formData.append('payment_date', document.getElementById('edit_payment_date').value);
            formData.append('created_at_time', document.getElementById('edit_created_at_time').value);
            formData.append('created_at_date', document.getElementById('edit_created_at_date').value);

            // Show loading indicator
            Swal.fire({
                title: 'Updating transaction...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form data via fetch API
            fetch('../api/debtor/update_debtor_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                Swal.close();
                if (data.success) {
                    // Close modal
                    $('#editTransactionModal').modal('hide');

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: data.message || 'Transaction updated successfully',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });

                    // Reload the page to refresh the transaction list
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: data.message || 'Failed to update transaction',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'An error occurred while updating the transaction',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
        });
    } else {
        console.error('Save button not found!');
    }

    // Direct event handler for delete transaction buttons
    const deleteButtons = document.querySelectorAll('.delete-transaction-btn');
    console.log('Found ' + deleteButtons.length + ' delete transaction buttons');

    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Delete button clicked');

            // Get data attributes
            const transactionId = this.getAttribute('data-transaction-id');
            const debtorId = this.getAttribute('data-debtor-id');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');

            console.log('Transaction data for deletion:', {
                transactionId,
                debtorId,
                amount,
                currency
            });

            // Show toast notification instead of confirmation dialog
            Swal.fire({
                icon: 'info',
                title: 'Deleting transaction...',
                text: 'Transaction will be deleted and payment will be reversed.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });

            // Create form data for the delete request
            const formData = new FormData();
            formData.append('transaction_id', transactionId);
            formData.append('debtor_id', debtorId);
            formData.append('amount', amount);
            formData.append('currency', currency);
            formData.append('delete_transaction', 'true');

            // Submit form data via fetch API after a short delay
            setTimeout(() => {
                fetch('../api/debtor/delete_debtor_transaction.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response received:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success) {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: data.message || 'Transaction deleted successfully',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });

                        // Reload the page to refresh the transaction list
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: data.message || 'Failed to delete transaction',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'An error occurred while deleting the transaction',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                });
            }, 2000);
        });
    });

    // Direct event handler for delete debtor buttons
    const deleteDebtorButtons = document.querySelectorAll('.delete-debtor-btn');
    console.log('Found ' + deleteDebtorButtons.length + ' delete debtor buttons');

    deleteDebtorButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Delete debtor button clicked');

            // Get data attributes
            const debtorId = this.getAttribute('data-debtor-id');
            const debtorName = this.getAttribute('data-debtor-name');

            console.log('Debtor data for deletion:', {
                debtorId,
                debtorName
            });

            // Show confirmation dialog with conditional message
            Swal.fire({
                title: 'Confirm Deletion',
                text: 'Are you sure you want to delete this debtor? All transactions will be deleted and balances will be reversed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete debtor',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading indicator
                    Swal.fire({
                        title: 'Deleting debtor...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Create form data for the delete request
                    const formData = new FormData();
                    formData.append('debtor_id', debtorId);
                    formData.append('delete_debtor', 'true');

                    // Submit form data via fetch API
                    fetch('../api/debtor/delete_debtor.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response received:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data);
                        Swal.close();
                        if (data.success) {
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: data.message || 'Debtor deleted successfully',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });

                            // Reload the page to refresh the debtor list
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            // Show error message
                            Swal.fire({
                                icon: 'error',
                                title: data.message || 'Failed to delete debtor',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'An error occurred while deleting the debtor',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    });
                }
            });
        });
    });
});