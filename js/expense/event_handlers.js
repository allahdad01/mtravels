// Expense Management Event Handlers and Form Functionality

$(document).ready(function() {
    // Check if we have filter parameters in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlStartDate = urlParams.get('startDate');
    const urlEndDate = urlParams.get('endDate');
    
    if (urlStartDate && urlEndDate) {
        // If we have filter dates in URL, use those
        $('#filterStartDate').val(urlStartDate);
        $('#filterEndDate').val(urlEndDate);
    } else {
        // Otherwise set default date range to current month
        const currentDate = new Date();
        const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        
        // Format dates properly for inputs
        $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
        $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
    }
    
    // If filter is active, show filter badge
    if (urlStartDate && urlEndDate) {
        $('#filterBadge').addClass('active');
    }
    
    // Expense Filter Section Toggle
    $('#toggleExpenseFilter').on('click', function() {
        $('#expenseFilterBody').slideToggle();
        $('#filterChevron').toggleClass('icon-chevron-down icon-chevron-up');
    });
    
    // Expense Filter Form Submission
    $('#expenseFilterForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get filter dates
        const startDate = $('#filterStartDate').val();
        const endDate = $('#filterEndDate').val();
        
        if (startDate && endDate) {
            // Reload the page with date parameters to fetch filtered data from server
            window.location.href = window.location.pathname + '?startDate=' + startDate + '&endDate=' + endDate;
        } else {
            alert('Please select both start and end dates');
        }
    });
    
    // Quick date range selection
    $('#filterQuickDate').on('change', function() {
        const range = $(this).val();
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'yesterday':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            case 'month':
                // First day of current month
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'last_month':
                // First day of last month
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                // Last day of last month
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Current day
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            default:
                // Keep current values
                return;
        }
        
        // Format dates as YYYY-MM-DD
        $('#filterStartDate').val(formatDateISO(startDate));
        $('#filterEndDate').val(formatDateISO(endDate));
    });
    
    // Reset Date Filter - go back to current month view
    $('#resetExpenseFilter').on('click', function() {
        // If we have URL parameters, reload without them to show default view
        if (window.location.search) {
            window.location.href = window.location.pathname;
        } else {
            // Just reset the form fields
            $('#expenseFilterForm')[0].reset();
            
            // Set default date range to current month
            const currentDate = new Date();
            const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            
            $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
            $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
        }
    });
    
    // Print category button click handler
    $('.print-category').on('click', function() {
        const categoryId = $(this).data('id');
        // Open the PDF in a new window/tab
        window.open('../api/expense/generate_category_pdf.php?category_id=' + categoryId, '_blank');
    });

    // Helper function to get CSRF token
    function getCsrfToken() {
        const input = document.querySelector('input[name="csrf_token"]');
        return input ? input.value : '';
    }

    // Category form submission
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        const categoryId = $('#categoryId').val();
        const categoryName = $('#categoryName').val();
        const csrfToken = $('input[name="csrf_token"]').val();
        
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'save_category',
                categoryId: categoryId,
                categoryName: categoryName,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#categoryModal').modal('hide');
                    alert('Category saved successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {

                alert('An error occurred while saving the category');
            }
        });
    });
    
    // Expense form submission
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        
        // Create FormData object to handle file uploads
        const formData = new FormData(this);
        
        // FormData will already include csrf_token from hidden input
        // Add action field
        formData.append('action', 'save_expense');
        
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
                    alert('Expense saved successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    // Reset button
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {

                alert('An error occurred while saving the expense');
                // Reset button
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        });
    });
    
    // Edit expense form submission
    $('#editExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'save_expense');
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="feather icon-loader spinner"></i> Processing...');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    $('#editExpenseModal').modal('hide');
                    alert('Expense updated successfully');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred while updating the expense');
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
        if (confirm('Are you sure you want to delete this category?')) {
            const categoryId = $(this).data('id');
            
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
                        alert('Category deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {

                    alert('An error occurred while deleting the category');
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
        
        $('#editExpenseId').val(expenseId);
        $('#editExpenseCategory').val(categoryId);
        $('#editExpenseCurrency').val(currency);
        $('#editExpenseMainAccount').val(mainAccountId);
        $('#editExpenseDate').val(date);
        $('#editExpenseDescription').val(description);
        $('#editExpenseAmount').val(amount);
        
        // Reset receipt fields
        $('#editExpenseReceiptNumber').val('');
        $('#editExpenseReceiptFile').siblings('.custom-file-label').text('Choose File');
        $('#editReceiptFileViewBtn').remove();
        
        // Fetch additional expense details like receipt number and file
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'get_expense_details',
                expenseId: expenseId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.expense) {
                    if (response.expense.receipt_number) {
                        $('#editExpenseReceiptNumber').val(response.expense.receipt_number);
                    }
                    if (response.expense.receipt_file) {
                        $('#editExpenseReceiptFile').siblings('.custom-file-label').text(response.expense.receipt_file);
                        $('<div id="editReceiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + response.expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> View Receipt</a></div>')
                            .insertAfter('#editExpenseReceiptFile').parent();
                    }
                }
            },
            error: function(xhr, status, error) {

            }
        });
        
        $('#editExpenseModal').modal('show');
    });
    
    // Delete expense button click handler
    $('.delete-expense').on('click', function() {
        if (confirm('Are you sure you want to delete this expense?')) {
            const expenseId = $(this).data('id');

            // Get CSRF token from meta tag or hidden input
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="csrf_token"]')?.value;
            
            $.ajax({
                url: '../api/expense/expense_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_expense',
                    expenseId: expenseId,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Expense deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {

                    alert('An error occurred while deleting the expense');
                }
            });
        }
    });

    // Function to format date as YYYY-MM-DD
    function formatDateISO(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Add leading zero if needed
        const day = String(date.getDate()).padStart(2, '0'); // Add leading zero if needed
        return `${year}-${month}-${day}`;
    }
    
    // Set default date range (current month)
    const today = new Date();
    // First day of current month (always the 1st)
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    // Last day of current month
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    // Format dates properly
    $('#startDate').val(formatDateISO(firstDay));
    $('#endDate').val(formatDateISO(lastDay));
    
    // Debug function to validate date format
    function validateDateRange() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        // Check if the start date is the first day of the month
        const startDateObj = new Date(startDate);
        const isFirstDay = startDateObj.getDate() === 1;
        
        return isFirstDay;
    }
    
    // Validate initial date range
    validateDateRange();

    // Date range form submission
    $('#dateRangeForm').on('submit', function(e) {
        e.preventDefault();
        // Validate date range before loading data
        validateDateRange();
        loadFinancialData();
    });

    // Quick date range buttons
    $('.btn-group .btn').click(function() {
        const range = $(this).data('range');
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                endDate.setDate(endDate.getDate() + 6);
                break;
            case 'month':
                // First day of current month - always the 1st
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                // First day of current quarter
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                // Last day of current quarter
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Last day of current year
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        // Use our custom formatting function
        $('#startDate').val(formatDateISO(startDate));
        $('#endDate').val(formatDateISO(endDate));
        
        // Make the current selection button active
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        
        // Submit the form to update data
        $('#dateRangeForm').submit();
    });

    // Highlight active range button
    function updateActiveButton() {
        $('.btn-group .btn').removeClass('active');
        // Add logic to determine which button should be active based on current date range
    }

    // Update active button when date inputs change
    $('#startDate, #endDate').change(updateActiveButton);
    
    // Reset form when opening the Add Expense modal via the Add Expense button
    $('[data-target="#expenseModal"]').on('click', function() {
        $('#expenseForm')[0].reset();
        $('#expenseId').val('');
        $('#expenseMainAccount').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseCurrency').prop('disabled', false);
        $('.custom-file-label').text('Choose File');
        $('#receiptFileViewBtn').remove();
    });

    // Make sure we reset everything properly when the modal is hidden
    $('#expenseModal').on('hidden.bs.modal', function() {
        // Re-enable all fields that might have been disabled
        $('#expenseCurrency').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseMainAccount').prop('disabled', false);
    });
    
    // Check for edit_expense parameter
    const searchParams = new URLSearchParams(window.location.search);
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
                        $('<div id="receiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> View Receipt</a></div>')
                            .insertAfter('#expenseReceiptFile').parent();
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

                alert('An error occurred while fetching expense details');
            }
        });
    }

    // Reset filter
    $('#resetFilter').click(function() {
        // Reset to current month from 1st day to last day
        const today = new Date();
        // First day of current month (always the 1st)
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        // Last day of current month
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        // Use our custom formatting function
        $('#startDate').val(formatDateISO(firstDay));
        $('#endDate').val(formatDateISO(lastDay));
        
        // Clear any active button state
        $('.btn-group .btn').removeClass('active');
        
        // Load data with these date settings
        loadFinancialData();
    });

    // Initial load
    loadFinancialData();

    // Make sure category headers expand when clicked
    $(document).on('click', '.category-card-header', function() {
        const $card = $(this).closest('.category-card');
        const $list = $card.find('.expense-list');
        const $icon = $card.find('.expand-icon');
        $list.slideToggle();
        $icon.toggleClass('icon-chevron-down icon-chevron-up');
    });

    // Attach click handler to the comprehensive export button
    $('#exportComprehensiveReport').click(function() {
        exportComprehensiveReport();
    });
});
