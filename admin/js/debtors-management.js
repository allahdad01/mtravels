// Toast notification configuration
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

// Event delegation for edit transaction buttons
$(document).on('click', '.edit-transaction-btn', function(e) {
    e.preventDefault(); // Prevent any default action
    console.log('Edit transaction button clicked');
    
    // Get data attributes
    const transactionId = $(this).data('transaction-id');
    const debtorId = $(this).data('debtor-id');
    const amount = $(this).data('amount');
    const currency = $(this).data('currency');
    const description = $(this).data('description');
    const paymentDate = $(this).data('payment-date');
    const createdAt = $(this).data('created-at');
    
    console.log('Transaction data:', {
        transactionId,
        debtorId,
        amount,
        currency,
        description,
        paymentDate,
        createdAt
    });
    
    // First, hide any open transaction modal
    $('.modal').modal('hide');
    
    // Wait for the first modal to close before opening the edit modal
    setTimeout(function() {
        try {
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
            
            console.log('Form values set successfully');
            
            // Ensure proper z-index
            $('.modal-backdrop').remove(); // Remove any existing backdrops
            $('body').removeClass('modal-open');
            
            // Open the edit modal with animation
            $('#editTransactionModal').modal('show');
            console.log('Modal should be visible now');
            
            // Add animation to modal elements
            setTimeout(() => {
                $('#editTransactionModal .form-group').each(function(index) {
                    $(this).addClass('fade-in').css('animation-delay', `${index * 0.1}s`);
                });
                $('#editTransactionModal .alert').addClass('slide-in');
            }, 300);
        } catch (error) {
            console.error('Error in edit transaction modal:', error);
            Toast.fire({
                icon: 'error',
                title: 'Error opening edit form'
            });
        }
    }, 300); // 300ms delay should be enough
});

// Function to handle edit transaction button click directly
function editTransaction(button) {
    console.log('editTransaction function called directly');
    
    // Get data attributes
    const transactionId = $(button).data('transaction-id');
    const debtorId = $(button).data('debtor-id');
    const amount = $(button).data('amount');
    const currency = $(button).data('currency');
    const description = $(button).data('description');
    const paymentDate = $(button).data('payment-date');
    const createdAt = $(button).data('created-at');
    
    console.log('Transaction data (direct function):', {
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
    setTimeout(() => {
        try {
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
            
            // Add animation to modal elements
            setTimeout(() => {
                $('#editTransactionModal .form-group').each(function(index) {
                    $(this).addClass('fade-in').css('animation-delay', `${index * 0.1}s`);
                });
                $('#editTransactionModal .alert').addClass('slide-in');
            }, 300);
        } catch (error) {
            console.error('Error in editTransaction function:', error);
            Toast.fire({
                icon: 'error',
                title: 'Error opening edit form'
            });
        }
    }, 300);
}

// Function to initialize event handlers for dynamic content
function initDynamicHandlers() {
    console.log('Initializing dynamic handlers');
    
    // Ensure edit transaction buttons work with event delegation
    $(document).off('click', '.edit-transaction-btn').on('click', '.edit-transaction-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Edit transaction button clicked (delegated)');
        
        // Get the parent modal and close it first
        const parentModal = $(this).closest('.modal');
        if (parentModal.length) {
            parentModal.modal('hide');
        }
        
        // Get data attributes
        const transactionId = $(this).data('transaction-id');
        const debtorId = $(this).data('debtor-id');
        const amount = $(this).data('amount');
        const currency = $(this).data('currency');
        const description = $(this).data('description');
        const paymentDate = $(this).data('payment-date');
        const createdAt = $(this).data('created-at');
        
        console.log('Transaction data (delegated):', {
            transactionId,
            debtorId,
            amount,
            currency,
            description,
            paymentDate,
            createdAt
        });
        
        // Wait for any open modals to close
        setTimeout(() => {
            try {
                // Set form values
                $('#edit_transaction_id').val(transactionId);
                $('#edit_debtor_id').val(debtorId);
                $('#edit_original_amount').val(amount);
                $('#edit_currency').val(currency);
                $('#edit_amount').val(amount);
                $('#edit_description').val(description);
                $('#edit_payment_date').val(paymentDate);
                
                // Handle created_at datetime
                if (createdAt) {
                    const createdAtObj = new Date(createdAt);
                    // Format time as HH:MM
                    const hours = createdAtObj.getHours().toString().padStart(2, '0');
                    const minutes = createdAtObj.getMinutes().toString().padStart(2, '0');
                    $('#edit_created_at_time').val(`${hours}:${minutes}`);
                    $('#edit_created_at_date').val(paymentDate);
                }
                
                // Show the modal
                $('#editTransactionModal').modal('show');
                
                // Add animation to modal elements
                setTimeout(() => {
                    $('#editTransactionModal .form-group').each(function(index) {
                        $(this).addClass('fade-in').css('animation-delay', `${index * 0.1}s`);
                    });
                    $('#editTransactionModal .alert').addClass('slide-in');
                }, 300);
            } catch (error) {
                console.error('Error showing edit transaction modal:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Error opening edit form'
                });
            }
        }, 300);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    $('#debtorsTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: searchDebtorsPlaceholder,
            lengthMenu: showText + " _MENU_ " + entriesText,
            info: showingText + " _START_ " + toText + " _END_ " + ofText + " _TOTAL_ " + entriesText,
            paginate: {
                first: firstText,
                last: lastText,
                next: '<i class="feather icon-chevron-right"></i>',
                previous: '<i class="feather icon-chevron-left"></i>'
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        columnDefs: [
            { orderable: false, targets: 6 } // Disable sorting on actions column
        ],
        drawCallback: function() {
            // Re-initialize handlers when table is redrawn (pagination, search, etc.)
            initDynamicHandlers();
        }
    });
    
    // Initialize handlers for dynamic content
    initDynamicHandlers();
    
    // Ensure the edit transaction modal is properly initialized
    if ($('#editTransactionModal').length) {
        console.log('Edit transaction modal found in DOM');
        
        // Reinitialize the modal to ensure it works properly
        $('#editTransactionModal').modal({
            backdrop: 'static',
            keyboard: false,
            show: false
        });
        
        // Log when the modal is shown
        $('#editTransactionModal').on('shown.bs.modal', function() {
            console.log('Edit transaction modal is now visible');
        });
        
        // Log when the modal is hidden
        $('#editTransactionModal').on('hidden.bs.modal', function() {
            console.log('Edit transaction modal is now hidden');
        });
    } else {
        console.error('Edit transaction modal not found in DOM!');
    }

    // Listen for form submission (using submit event)
    document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const currentPassword = document.getElementById('currentPassword').value;

        // If any password field is filled, all password fields must be filled
        if (newPassword || confirmPassword || currentPassword) {
            if (!currentPassword) {
                Toast.fire({
                    icon: 'warning',
                    title: enterCurrentPasswordText
                });
                return;
            }
            if (!newPassword) {
                Toast.fire({
                    icon: 'warning',
                    title: enterNewPasswordText
                });
                return;
            }
            if (!confirmPassword) {
                Toast.fire({
                    icon: 'warning',
                    title: confirmNewPasswordText
                });
                return;
            }
            if (newPassword !== confirmPassword) {
                Toast.fire({
                    icon: 'error',
                    title: passwordsDoNotMatchText
                });
                return;
            }
            if (newPassword.length < 6) {
                Toast.fire({
                    icon: 'warning',
                    title: passwordLengthText
                });
                return;
            }
        }
        
        const formData = new FormData(this);
        
        // Show loading indicator
        Swal.fire({
            title: loadingText,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('update_client_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: data.message
                });
                // Clear password fields
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                
                // Reload with a slight delay
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || failedToUpdateProfileText
                });
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: errorOccurredText
            });
        });
    });
    
    // Event listener for saving transaction edits
    document.getElementById('saveTransactionBtn').addEventListener('click', function() {
        const form = document.getElementById('editTransactionForm');
        
        // Enhanced validation
        const amount = document.getElementById('edit_amount').value;
        const description = document.getElementById('edit_description').value;
        const paymentDate = document.getElementById('edit_payment_date').value;
        
        // Validate required fields with visual feedback
        let isValid = true;
        
        if (!amount || amount <= 0) {
            highlightInvalidField('edit_amount');
            isValid = false;
        }
        
        if (!description.trim()) {
            highlightInvalidField('edit_description');
            isValid = false;
        }
        
        if (!paymentDate) {
            highlightInvalidField('edit_payment_date');
            isValid = false;
        }
        
        if (!isValid) {
            Toast.fire({
                icon: 'warning',
                title: pleaseCompleteAllFieldsText
            });
            return;
        }
        
        const formData = new FormData(form);
        
        // Show loading indicator with animation
        Swal.fire({
            title: loadingTransactionText,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit form data via fetch API
        fetch('update_debtor_transaction.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                // Close modal with animation
                $('#editTransactionModal .modal-content').addClass('zoom-out');
                
                setTimeout(() => {
                    $('#editTransactionModal').modal('hide');
                    
                    Toast.fire({
                        icon: 'success',
                        title: data.message
                    });
                    
                    // Reload the page to refresh the transaction list
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }, 300);
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.message || failedToUpdateTransactionText
                });
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: errorOccurredTransactionText
            });
        });
    });
    
    // Add input event listeners to clear validation errors
    document.querySelectorAll('#editTransactionForm .form-control').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const formGroup = this.closest('.form-group');
            if (formGroup) {
                const feedbackElement = formGroup.querySelector('.invalid-feedback');
                if (feedbackElement) {
                    feedbackElement.remove();
                }
            }
        });
    });
});

// Function to preview profile image
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Function to check currency and show/hide exchange rate field
function checkCurrency(selectElement, debtorCurrency, debtorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv' + debtorId);
    const selectedCurrencySpan = document.getElementById('selectedCurrency' + debtorId);
    const debtorCurrencySpan = document.getElementById('debtorCurrency' + debtorId);
    const exchangeRateInput = document.getElementById('exchangeRate' + debtorId);
    
    if (selectedCurrency !== debtorCurrency) {
        // Show exchange rate field with animation
        exchangeRateDiv.style.display = 'block';
        exchangeRateDiv.classList.add('fade-in');
        selectedCurrencySpan.textContent = selectedCurrency;
        debtorCurrencySpan.textContent = debtorCurrency;
        exchangeRateInput.required = true;
    } else {
        // Hide exchange rate field
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}

// Function to highlight invalid fields
function highlightInvalidField(fieldId) {
    const field = document.getElementById(fieldId);
    field.classList.add('is-invalid');
    
    // Add validation message if not exists
    const formGroup = field.closest('.form-group');
    if (formGroup && !formGroup.querySelector('.invalid-feedback')) {
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = fieldRequiredText || 'This field is required';
        formGroup.appendChild(feedback);
    }
    
    // Scroll to the invalid field
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
} 