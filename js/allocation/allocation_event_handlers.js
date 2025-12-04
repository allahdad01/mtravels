// Budget Allocation Event Handlers

$(document).ready(function() {
    console.log('Setting up allocation event handlers');
    
    // Create budget allocation form submission
    $('#allocationForm').on('submit', function(e) {
        e.preventDefault();
        
        const categoryId = $('#categoryId').val();
        const mainAccountId = $('#mainAccountId').val();
        const amount = $('#amount').val();
        const currency = $('#currency').val();
        const date = $('#allocationDate').val();
        const description = $('#description').val();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.text('Creating...');
        submitBtn.prop('disabled', true);
        
        createBudgetAllocation(categoryId, mainAccountId, amount, currency, date, description)
            .done(function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while creating the allocation');
            })
            .always(function() {
                // Restore button state
                submitBtn.text(originalText);
                submitBtn.prop('disabled', false);
            });
    });
    
    // Add funds to allocation button click
    $(document).on('click', '.fund-allocation', function(e) {
        e.preventDefault();
        console.log('Fund button clicked');
        
        const allocationId = $(this).data('id');
        const currency = $(this).data('currency');
        console.log('Allocation ID:', allocationId, 'Currency:', currency);
        
        // Set values in modal
        $('#fundAllocationId').val(allocationId);
        $('#fundCurrency').val(currency);
        
        // Show modal
        $('#fundAllocationModal').modal('show');
    });
    
    // Handle fund allocation form submission
    $('#fundAllocationForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Fund form submitted');
        
        const allocationId = $('#fundAllocationId').val();
        const amount = $('#additionalAmount').val();
        const note = $('#fundNote').val();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.text('Adding Funds...');
        submitBtn.prop('disabled', true);
        
        addFundsToAllocation(allocationId, amount, note)
            .done(function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while adding funds to the allocation');
            })
            .always(function() {
                // Restore button state
                submitBtn.text(originalText);
                submitBtn.prop('disabled', false);
            });
    });
    
    // Delete allocation button click
    $('.delete-allocation').on('click', function() {
        if (confirm('Are you sure you want to delete this allocation? Any remaining funds will be returned to the main account')) {
            const allocationId = $(this).data('id');
            
            // Show loading state
            const button = $(this);
            const originalText = button.html();
            button.html('<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...');
            button.prop('disabled', true);
            
            deleteAllocation(allocationId)
                .done(function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the allocation');
                })
                .always(function() {
                    // Restore button state
                    button.html(originalText);
                    button.prop('disabled', false);
                });
        }
    });
    
    // View expenses for an allocation button click
    $('.view-expenses').on('click', function() {
        const allocationId = $(this).data('id');
        
        // Show loading state
        const button = $(this);
        const originalText = button.html();
        button.html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        button.prop('disabled', true);
        
        getAllocationDetails(allocationId)
            .done(function(response) {
                if (response.success) {
                    const allocation = response.allocation;
                    const expenses = response.expenses;
                    
                    // Update allocation details
                    $('#allocation-category').text(allocation.category_name);
                    $('#allocation-account').text(allocation.account_name);
                    $('#allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                    $('#allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                    $('#allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                    $('#allocation-description').text(allocation.description || 'No description');
                    
                    // Add allocation ID to Add Expense button for later use
                    $('#addExpenseBtn').data('allocation-id', allocation.id);
                    $('#addExpenseBtn').data('currency', allocation.currency);
                    $('#addExpenseBtn').data('category-id', allocation.category_id);
                    
                    // Clear and populate expenses table
                    const tbody = $('#expenses-table-body');
                    tbody.empty();
                    
                    if (expenses.length > 0) {
                        expenses.forEach(expense => {
                            const row = `
                                <tr>
                                    <td>${new Date(expense.date).toLocaleDateString()}</td>
                                    <td style="max-width: 300px; word-wrap: break-word; white-space: normal;">${expense.description}</td>
                                    <td>${expense.amount} ${expense.currency}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-expense" data-id="${expense.id}">
                                            <i class="feather icon-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-expense" data-id="${expense.id}">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        $('.expenses-list').show();
                        $('#no-expenses-message').hide();
                    } else {
                        $('.expenses-list').hide();
                        $('#no-expenses-message').show();
                    }
                    
                    // Show modal
                    $('#expensesModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while fetching allocation details');
            })
            .always(function() {
                // Restore button state
                button.html(originalText);
                button.prop('disabled', false);
            });
    });
    
    // Add expense from allocation button click
    $('#addExpenseBtn').on('click', function() {
        const allocationId = $(this).data('allocation-id');
        const currency = $(this).data('currency');
        const categoryId = $(this).data('category-id');
        
        // Close current modal
        $('#expensesModal').modal('hide');
        
        // Open expense modal from the main expense page with allocation data
        window.location.href = 'expense_management.php?allocation_id=' + allocationId + 
                               '&currency=' + currency + 
                               '&category_id=' + categoryId;
    });
    
    // Edit expense from allocation view button click
    $(document).on('click', '.edit-expense', function() {
        const expenseId = $(this).data('id');
        // Redirect to expense edit page with the ID
        window.location.href = 'expense_management.php?edit_expense=' + expenseId;
    });
    
    // Delete expense from allocation view button click
    $(document).on('click', '.delete-expense', function() {
        if (confirm('Are you sure you want to delete this expense? The amount will be returned to the allocation')) {
            const expenseId = $(this).data('id');
            
            // Show loading state
            const button = $(this);
            const originalHtml = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i>');
            button.prop('disabled', true);
            
            deleteExpense(expenseId)
                .done(function(response) {
                    if (response.success) {
                        alert(response.message);
                        // Close modal and refresh page to see updated allocation
                        $('#expensesModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the expense');
                })
                .always(function() {
                    // Restore button state
                    button.html(originalHtml);
                    button.prop('disabled', false);
                });
        }
    });
});