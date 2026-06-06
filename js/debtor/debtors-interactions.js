// Print debtor receipt function
function printDebtorReceipt(transactionId) {
    window.open(`../api/debtor/print_debtor_receipt.php?id=${transactionId}`, '_blank');
}

// This script will ensure the edit transaction button works
document.addEventListener('DOMContentLoaded', function() {

    // Direct event handler for all edit transaction buttons
    const editButtons = document.querySelectorAll('.edit-transaction-btn');


    editButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();


            // Get data attributes
             const transactionId = this.getAttribute('data-transaction-id');
             const debtorId = this.getAttribute('data-debtor-id');
             const amount = this.getAttribute('data-amount');
             const currency = this.getAttribute('data-currency');
             const description = this.getAttribute('data-description');
             const paymentDate = this.getAttribute('data-payment-date');
             const referenceNumber = this.getAttribute('data-reference-number');

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
                document.getElementById('edit_reference_number').value = referenceNumber || '';

                // Show the modal
                $('#editTransactionModal').modal('show');
            }, 300);
        });
    });

    // Direct event handler for the save button
     const saveButton = document.getElementById('saveTransactionBtn');
     if (saveButton) {

         saveButton.addEventListener('click', function(e) {
             e.preventDefault();

             const form = document.getElementById('editTransactionForm');

             // Enhanced validation
             const amount = document.getElementById('edit_amount').value;
             const description = document.getElementById('edit_description').value;
             const paymentDate = document.getElementById('edit_payment_date').value;

             // Validate required fields with visual feedback
             let isValid = true;

             if (!amount || amount <= 0) {

                 const field = document.getElementById('edit_amount');
                 field.classList.add('is-invalid');
                 isValid = false;
             }

             if (!description.trim()) {

                 const field = document.getElementById('edit_description');
                 field.classList.add('is-invalid');
                 isValid = false;
             }

             if (!paymentDate) {

                 const field = document.getElementById('edit_payment_date');
                 field.classList.add('is-invalid');
                 isValid = false;
             }

             if (!isValid) {

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

             // Submit form as standard POST
             form.method = 'POST';
             form.action = '../api/debtor/update_debtor_transaction.php';
             form.submit();
         });
     } else {

     }

    // Direct event handler for delete transaction buttons
    const deleteButtons = document.querySelectorAll('.delete-transaction-btn');


    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();


            // Get data attributes
            const transactionId = this.getAttribute('data-transaction-id');
            const debtorId = this.getAttribute('data-debtor-id');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');

            console.log({
                transactionId,
                debtorId,
                amount,
                currency
            });

            // Show confirmation using native JavaScript alert
            const confirmMessage = `Delete Transaction?\n\nAmount: ${amount} ${currency}\n\nThis transaction will be deleted and the payment will be reversed.`;
            
            if (confirm(confirmMessage)) {
                // Close the edit transaction modal first
                $('#editTransactionModal').modal('hide');
                
                // Create a hidden form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../api/debtor/delete_debtor_transaction.php';
                
                // Add form fields
                const fields = {
                    'transaction_id': transactionId,
                    'debtor_id': debtorId,
                    'amount': amount,
                    'currency': currency,
                    'delete_transaction': 'true'
                };
                
                for (const [key, value] of Object.entries(fields)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // Direct event handler for delete debtor buttons
    const deleteDebtorButtons = document.querySelectorAll('.delete-debtor-btn');


    deleteDebtorButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();


            // Get data attributes
            const debtorId = this.getAttribute('data-debtor-id');
            const debtorName = this.getAttribute('data-debtor-name');

            console.log({
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

                        return response.json();
                    })
                    .then(data => {

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
