 // Print category button click handler
 $('.print-category').on('click', function() {
    const categoryId = $(this).data('id');
    // Open the PDF in a new window/tab
    window.open('../api/expense/generate_category_pdf.php?category_id=' + categoryId, '_blank');
});

// Helper function to get CSRF token
function getCsrfToken() {
    // First try to get from categoryForm
    let csrfInput = document.querySelector('#categoryForm input[name="csrf_token"]');
    if (csrfInput && csrfInput.value) {
        console.log('CSRF from categoryForm:', csrfInput.value);
        return csrfInput.value;
    }
    
    // Then try expenseForm
    csrfInput = document.querySelector('#expenseForm input[name="csrf_token"]');
    if (csrfInput && csrfInput.value) {
        console.log('CSRF from expenseForm:', csrfInput.value);
        return csrfInput.value;
    }
    
    // Then try any form
    csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput && csrfInput.value) {
        console.log('CSRF from any form:', csrfInput.value);
        return csrfInput.value;
    }
    
    // Try meta tag
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        const token = metaTag.getAttribute('content');
        if (token) {
            console.log('CSRF from meta tag:', token);
            return token;
        }
    }
    
    console.log('WARNING: No CSRF token found!');
    return '';
}

// Category form submission
$('#categoryForm').on('submit', function(e) {
    e.preventDefault();
    const categoryId = $('#categoryId').val();
    const categoryName = $('#categoryName').val();
    
    $.ajax({
        url: '../api/expense/expense_actions.php',
        type: 'POST',
        data: {
            action: 'save_category',
            categoryId: categoryId,
            categoryName: categoryName,
            csrf_token: getCsrfToken()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#categoryModal').modal('hide');
                alert('category_saved_successfully');
                location.reload();
            } else {
                alert('error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {

            alert('an_error_occurred_while_saving_the_category');
        }
    });
});

// Expense form submission
$('#expenseForm').on('submit', function(e) {
    e.preventDefault();
    
    // Create FormData object to handle file uploads
    const formData = new FormData(this);
    
    // FormData from form will already include csrf_token from hidden input
    // But explicitly add 'action' field
    formData.append('action', 'save_expense');
    
    // Get allocation info if present
    const selectedAllocation = $('#expenseAllocation').find('option:selected');
    if (selectedAllocation.val()) {
        const allocationCurrency = selectedAllocation.data('currency');
        // Ensure the currency matches the allocation
        formData.set('expenseCurrency', allocationCurrency);

    }
    
    // Re-enable any disabled fields to ensure their values are included in the form
    $('#expenseCurrency').prop('disabled', false);
    $('#expenseCategory').prop('disabled', false);
    $('#expenseMainAccount').prop('disabled', false);
    
    // Show loading indicator
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="feather icon-loader spinner"></i> Processing...');
    submitBtn.prop('disabled', true);
    
    $.ajax({
        url: '../api/expense/expense_actions.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        contentType: false, // Required for FormData
        processData: false, // Required for FormData
        success: function(response) {
            if (response.success) {
                $('#expenseModal').modal('hide');
                alert('expense_saved_successfully');
                location.reload();
            } else {
                alert('error: ' + response.message);
                // Reset button
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {

            alert('an_error_occurred_while_saving_the_expense');
            // Reset button
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        }
    });
});

// Edit category button click handler
$('.edit-category').on('click', function() {
    const categoryId = $(this).data('id');
    const categoryName = $(this).data('name');
    
    $('#categoryId').val(categoryId);
    $('#categoryName').val(categoryName);
    $('#categoryModal').modal('show');
});

// Delete category button click handler
$('.delete-category').on('click', function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        if (confirm('are_you_sure_you_want_to_delete_this_category')) {
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin"></i>');
        const categoryId = $btn.data('id');
        
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'delete_category',
                categoryId: categoryId,
                csrf_token: getCsrfToken()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('category_deleted_successfully');
                    location.reload();
                } else {
                    alert('error: ' + response.message);
                }
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                alert('an_error_occurred_while_deleting_the_category');
            }
        });
    }
});

// Edit expense button click handler
$('.edit-expense').on('click', function() {
    const expenseId = $(this).data('id');
    const categoryId = $(this).data('category');
    const date = $(this).data('date');
    const description = $(this).data('description');
    const amount = $(this).data('amount');
    const currency = $(this).data('currency');
    const mainAccountId = $(this).data('main-account');
    
    $('#expenseId').val(expenseId);
    $('#expenseCategory').val(categoryId);
    $('#expenseDate').val(date);
    $('#expenseDescription').val(description);
    $('#expenseAmount').val(amount);
    $('#expenseCurrency').val(currency);
    $('#expenseMainAccount').val(mainAccountId);
    
    // Reset receipt fields
    $('#expenseReceiptNumber').val('');
    $('.custom-file-label').text('choose_file');
    
    // Fetch additional expense details like receipt number and file
    $.ajax({
        url: '../api/expense/expense_actions.php',
        type: 'POST',
        data: {
            action: 'get_expense_details',
            expenseId: expenseId,
            csrf_token: getCsrfToken()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.expense) {
                // Set main account if available
                if (response.expense.main_account_id) {
                    $('#expenseMainAccount').val(response.expense.main_account_id);
                }
                
                // Set allocation if available
                if (response.expense.allocation_id) {
                    $('#expenseAllocation').val(response.expense.allocation_id);
                    // Trigger the change event to update related fields
                    $('#expenseAllocation').trigger('change');
                }
                
                // Set receipt number if available
                if (response.expense.receipt_number) {
                    $('#expenseReceiptNumber').val(response.expense.receipt_number);
                }
                
                // Display existing receipt file information if available
                if (response.expense.receipt_file) {
                    $('.custom-file-label').text(response.expense.receipt_file);
                    // Remove any existing view button first
                    $('#receiptFileViewBtn').remove();
                    $('<div id="receiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + response.expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> view_receipt</a></div>')
                        .insertAfter('#expenseReceiptFile').parent();
                }
            }
        },
        error: function(xhr, status, error) {

        }
    });
    
    $('#expenseModal').modal('show');
});

// Delete expense button click handler
$('.delete-expense').on('click', function() {
    var $btn = $(this);
    var originalHtml = $btn.html();
    if (confirm('are_you_sure_you_want_to_delete_this_expense')) {
        $btn.prop('disabled', true);
        $btn.html('<i class="fas fa-spinner fa-spin"></i>');
        const expenseId = $btn.data('id');
        
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'delete_expense',
                expenseId: expenseId,
                csrf_token: getCsrfToken()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('expense_deleted_successfully');
                    location.reload();
                } else {
                    alert('error: ' + response.message);
                }
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                alert('an_error_occurred_while_deleting_the_expense');
            }
        });
    }
});

 // Reset form when opening the Add Expense modal via the Add Expense button
 $('[data-target="#expenseModal"]').on('click', function() {
    $('#expenseForm')[0].reset();
    $('#expenseId').val('');
    $('#expenseMainAccount').prop('disabled', false);
    $('#expenseCategory').prop('disabled', false);
    $('#expenseCurrency').prop('disabled', false);
    $('.custom-file-label').text('choose_file');
    $('#receiptFileViewBtn').remove();
});

// Handle allocation selection
$('#expenseAllocation').on('change', function() {
    const selectedOption = $(this).find('option:selected');
    if (selectedOption.val()) {
        // Set currency to match allocation currency
        const currency = selectedOption.data('currency');
        $('#expenseCurrency').val(currency);
        $('#expenseCurrency').prop('disabled', true);
        
        // Set max amount to remaining amount
        const remaining = selectedOption.data('remaining');
        $('#expenseAmount').attr('max', remaining);
        
        // If category is selected, update the category selection
        const category = selectedOption.data('category');
        const categoryOption = $('#expenseCategory option').filter(function() {
            return $(this).text().trim() === category;
        });
        
        if (categoryOption.length) {
            $('#expenseCategory').val(categoryOption.val());
            $('#expenseCategory').prop('disabled', true);
        }
        
        // When using allocation, the main account should be disabled
        $('#expenseMainAccount').val('');
        $('#expenseMainAccount').prop('disabled', true);


    } else {
        // Reset fields
        $('#expenseCurrency').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseMainAccount').prop('disabled', false);
        $('#expenseAmount').removeAttr('max');
    }
});

// Make sure we reset everything properly when the modal is hidden
$('#expenseModal').on('hidden.bs.modal', function() {
    // Re-enable all fields that might have been disabled
    $('#expenseCurrency').prop('disabled', false);
    $('#expenseCategory').prop('disabled', false);
    $('#expenseMainAccount').prop('disabled', false);
});

// Check URL parameters for allocation references
const searchParams = new URLSearchParams(window.location.search);
const allocationId = searchParams.get('allocation_id');
const currency = searchParams.get('currency');
const categoryId = searchParams.get('category_id');

if (allocationId) {

    
    // First, set the expense form to defaults
    $('#expenseForm')[0].reset();
    $('#expenseId').val('');
    
    // Then set the allocation dropdown
    $('#expenseAllocation').val(allocationId);
    
    // Manually set fields based on allocation data
    const selectedOption = $('#expenseAllocation').find('option:selected');
    if (selectedOption.val()) {
        // Get currency from the allocation data
        const allocationCurrency = selectedOption.data('currency');

        
        // Set and lock currency field
        $('#expenseCurrency').val(allocationCurrency);
        $('#expenseCurrency').prop('disabled', true);
        
        // Set and lock category field
        const category = selectedOption.data('category');
        const categoryOption = $('#expenseCategory option').filter(function() {
            return $(this).text().trim() === category;
        });
        
        if (categoryOption.length) {
            $('#expenseCategory').val(categoryOption.val());
            $('#expenseCategory').prop('disabled', true);
        }
        
        // Disable main account field
        $('#expenseMainAccount').val('');
        $('#expenseMainAccount').prop('disabled', true);
    }
    
    // Open the expense modal automatically
    $('#expenseModal').modal('show');
}

// Check for edit_expense parameter
const editExpenseId = searchParams.get('edit_expense');
if (editExpenseId) {
    // Fetch expense details and open the modal
    $.ajax({
        url: '../api/expense/expense_actions.php',
        type: 'POST',
        data: {
            action: 'get_expense',
            expenseId: editExpenseId,
            csrf_token: getCsrfToken()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const expense = response.expense;
                
                // Fill the form with expense data
                $('#expenseId').val(expense.id);
                $('#expenseCategory').val(expense.category_id);
                
                // Just use the date portion
                const datetime = new Date(expense.date);
                const dateString = datetime.toISOString().split('T')[0];
                
                $('#expenseDate').val(dateString);
                $('#expenseDescription').val(expense.description);
                $('#expenseAmount').val(expense.amount);
                
                // Set currency but don't trigger change events yet
                $('#expenseCurrency').val(expense.currency);
                
                // Ensure we display the main account correctly
                if (expense.main_account_id) {
                    $('#expenseMainAccount').val(expense.main_account_id);
                }
                
                // Handle receipt details
                if (expense.receipt) {
                    $('#expenseReceiptNumber').val(expense.receipt);
                }
                
                if (expense.receipt_file) {
                    $('.custom-file-label').text(expense.receipt_file);
                    // Remove any existing view button first
                    $('#receiptFileViewBtn').remove();
                    $('<div id="receiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> view_receipt</a></div>')
                        .insertAfter('#expenseReceiptFile').parent();
                }
                
                // Handle allocation last as it may disable other fields
                if (expense.allocation_id) {
                    // First select the allocation
                    $('#expenseAllocation').val(expense.allocation_id);
                    
                    // Then manually update the fields based on the allocation data
                    const selectedOption = $('#expenseAllocation').find('option:selected');
                    if (selectedOption.val()) {
                        // Get the currency from the allocation data
                        const currency = selectedOption.data('currency');

                        
                        // Ensure currency matches the allocation
                        $('#expenseCurrency').val(currency);
                        $('#expenseCurrency').prop('disabled', true);
                        
                        // Disable the category field
                        $('#expenseCategory').prop('disabled', true);
                        
                        // Disable main account as we're using allocation
                        $('#expenseMainAccount').val('');
                        $('#expenseMainAccount').prop('disabled', true);
                    }
                }
                
                // Update modal title
                $('.modal-title').text('Edit Expense');
                
                // Open the modal
                $('#expenseModal').modal('show');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {

            alert('an_error_occurred_while_fetching_expense_details');
        }
    });
}
