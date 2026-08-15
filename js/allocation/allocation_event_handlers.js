// Budget Allocation Event Handlers

$(document).ready(function() {

    
    // Create budget allocation form submission
    $('#allocationForm').on('submit', function(e) {
        e.preventDefault();
        
        const categoryId = $('#categoryId').val();
        const subCategoryId = $('#allocationSubCategory').val();
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
        
        createBudgetAllocation(categoryId, subCategoryId, mainAccountId, amount, currency, date, description)
            .done(function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {

                alert('An error occurred while creating the allocation');
            })
            .always(function() {
                // Restore button state
                submitBtn.text(originalText);
                submitBtn.prop('disabled', false);
            });
    });

    // Load sub-categories for the selected category into the allocation modal
    function loadAllocationSubCategories(categoryId, selectedValue) {
        const csrfToken = $('input[name="csrf_token"]').val();
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'get_sub_categories',
                categoryId: categoryId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                const $select = $('#allocationSubCategory');
                $select.empty().append('<option value="">No Sub-Category</option>');
                if (response.success) {
                    $.each(response.sub_categories, function(i, sub) {
                        $select.append($('<option>', { value: sub.id, text: sub.name }));
                    });
                }
                if (selectedValue) {
                    $select.val(String(selectedValue));
                }
            }
        });
    }

    // Category change in allocation modal -> load its sub-categories
    $('#categoryId').on('change', function() {
        const categoryId = $(this).val();
        if (categoryId) {
            loadAllocationSubCategories(categoryId, '');
        } else {
            $('#allocationSubCategory').empty().append('<option value="">No Sub-Category</option>');
        }
    });

    // Reset sub-category dropdown when allocation modal opens
    $('#allocationModal').on('show.bs.modal', function() {
        $('#allocationSubCategory').empty().append('<option value="">No Sub-Category</option>');
    });
    
    // View funds for allocation button click
    $(document).on('click', '.view-funds', function(e) {
        e.preventDefault();
        
        const allocationId = $(this).data('id');
        
        // Show loading state
        const button = $(this);
        const originalText = button.html();
        button.html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        button.prop('disabled', true);
        
        getFundTransactions(allocationId)
            .done(function(response) {
                if (response.success) {
                    const allocation = response.allocation;
                    const transactions = response.transactions || [];
                    
                    // Update allocation details
                    $('#funds-allocation-category').text(allocation.category_name);
                    $('#funds-allocation-account').text(allocation.account_name);
                    $('#funds-allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                    $('#funds-allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                    $('#funds-allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                    
                    // Clear and populate funds table
                    const tbody = $('#funds-table-body');
                    tbody.empty();
                    
                    if (transactions.length > 0) {
                        transactions.forEach(transaction => {
                            const row = `
                                <tr>
                                    <td>${new Date(transaction.created_at).toLocaleDateString()}</td>
                                    <td>${transaction.description}</td>
                                    <td>${transaction.amount} ${transaction.currency}</td>
                                    <td><span class="ba-badge ${transaction.type}">${transaction.type}</span></td>
                                    <td>
                                        <button class="ba-action-btn edit-fund-transaction"
                                                data-id="${transaction.id}" data-allocation-id="${allocationId}"
                                                data-amount="${transaction.amount}" data-description="${(transaction.description||'').replace(/'/g,"\\'")}">
                                            <i class="feather icon-edit-2"></i>
                                        </button>
                                        <button class="ba-action-btn danger delete-fund-transaction" data-id="${transaction.id}" data-allocation-id="${allocationId}">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        $('.funds-list').show();
                        $('#no-funds-message').hide();
                    } else {
                        $('.funds-list').hide();
                        $('#no-funds-message').show();
                    }
                    
                    // Show modal
                    $('#viewFundsModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {
                alert('An error occurred while fetching fund transactions');
            })
            .always(function() {
                // Restore button state
                button.html(originalText);
                button.prop('disabled', false);
            });
    });
    
    // Add funds to allocation button click
    $(document).on('click', '.fund-allocation', function(e) {
        e.preventDefault();

        
        const allocationId = $(this).data('id');
        const currency = $(this).data('currency');

        
        // Set values in modal
        $('#fundAllocationId').val(allocationId);
        $('#fundCurrency').val(currency);
        
        // Show modal
        $('#fundAllocationModal').modal('show');
    });
    
    // Handle fund allocation form submission
    $('#fundAllocationForm').on('submit', function(e) {
        e.preventDefault();

        
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

                    alert('An error occurred while deleting the allocation');
                })
                .always(function() {
                    // Restore button state
                    button.html(originalText);
                    button.prop('disabled', false);
                });
        }
    });
    
    // ── View expenses for an allocation button click ──────────────────────
    $('.view-expenses').on('click', function() {
        const allocationId = $(this).data('id');
        
        const button = $(this);
        const originalText = button.html();
        button.html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        button.prop('disabled', true);
        
        // Reset inline forms
        $('#addExpenseSection').hide();
        $('#editExpenseSection').hide();
        
        getAllocationDetails(allocationId)
            .done(function(response) {
                if (response.success) {
                    const allocation = response.allocation;
                    const expenses = response.expenses;
                    
                    $('#allocation-category').text(allocation.category_name);
                    $('#allocation-account').text(allocation.account_name);
                    $('#allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                    $('#allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                    $('#allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                    $('#allocation-description').text(allocation.description || 'No description');
                    
                    // Store allocation info for inline forms
                    $('#inlineAllocationId').val(allocation.id);
                    $('#inlineCategoryId').val(allocation.category_id);
                    $('#inlineCurrency').val(allocation.currency);
                    $('#inlineEditAllocationId').val(allocation.id);
                    
                    // Load sub-categories of the allocation's category (pre-select allocation's sub if any)
                    loadInlineSubCategories(allocation.category_id, allocation.sub_category_id || '');
                    
                    // Set default date for add form
                    $('#inlineExpenseDate').val(new Date().toISOString().split('T')[0]);
                    
                    // Populate expenses table
                    const tbody = $('#expenses-table-body');
                    tbody.empty();
                    
                    if (expenses.length > 0) {
                        expenses.forEach(expense => {
                            const subBadge = expense.sub_category_name
                                ? ` <span class="ba-badge sub" style="font-size:.7rem;">${expense.sub_category_name}</span>`
                                : '';
                            const row = `
                                <tr>
                                    <td>${new Date(expense.date).toLocaleDateString()}</td>
                                    <td style="max-width:300px;word-wrap:break-word;white-space:normal;">${expense.description}${subBadge}</td>
                                    <td>${expense.amount} ${expense.currency}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-expense" data-id="${expense.id}" data-date="${expense.date}" data-description="${expense.description.replace(/"/g, '&quot;')}" data-amount="${expense.amount}">
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
                    
                    $('#expensesModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function(xhr, status, error) {
                alert('An error occurred while fetching allocation details');
            })
            .always(function() {
                button.html(originalText);
                button.prop('disabled', false);
            });
    });
    
    // ── Load sub-categories into the inline add-expense form ───────────────
    function loadInlineSubCategories(categoryId, selectedValue) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                         document.querySelector('input[name="csrf_token"]')?.value;
        $.ajax({
            url: '../api/expense/expense_actions.php',
            type: 'POST',
            data: {
                action: 'get_sub_categories',
                categoryId: categoryId,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                const $select = $('#inlineExpenseSubCategory');
                $select.empty().append('<option value="">No Sub-Category</option>');
                if (response.success) {
                    $.each(response.sub_categories, function(i, sub) {
                        $select.append($('<option>', { value: sub.id, text: sub.name }));
                    });
                }
                if (selectedValue) {
                    $select.val(String(selectedValue));
                }
            }
        });
    }
    
    // ── Show/hide inline add expense form ─────────────────────────────────
    $('#showAddExpenseBtn').on('click', function() {
        $('#editExpenseSection').hide();
        $('#addExpenseSection').show();
        $('#inlineExpenseDescription').val('').focus();
        $('#inlineExpenseAmount').val('');
    });
    
    $('#cancelAddExpense').on('click', function() {
        $('#addExpenseSection').hide();
    });
    
    // ── Inline add expense form submit ─────────────────────────────────────
    $('#inlineAddExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        const allocationId = $('#inlineAllocationId').val();
        const categoryId = $('#inlineCategoryId').val();
        const subCategoryId = $('#inlineExpenseSubCategory').val();
        const date = $('#inlineExpenseDate').val();
        const description = $('#inlineExpenseDescription').val();
        const amount = $('#inlineExpenseAmount').val();
        const currency = $('#inlineCurrency').val();
        
        const $btn = $(this).find('button[type="submit"]');
        const orig = $btn.html();
        $btn.html('<i class="feather icon-loader spinner"></i> Saving...').prop('disabled', true);
        
        addAllocationExpense(allocationId, categoryId, subCategoryId, date, description, amount, currency)
            .done(function(response) {
                if (response.success) {
                    $('#addExpenseSection').hide();
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function() {
                alert('An error occurred while adding the expense');
            })
            .always(function() {
                $btn.html(orig).prop('disabled', false);
            });
    });
    
    // ── Edit expense button click ─────────────────────────────────────────
    $(document).on('click', '.edit-expense', function() {
        const expenseId = $(this).data('id');
        const date = $(this).data('date');
        const description = $(this).data('description');
        const amount = $(this).data('amount');
        
        $('#inlineEditExpenseId').val(expenseId);
        $('#inlineEditDate').val(date);
        $('#inlineEditDescription').val(description);
        $('#inlineEditAmount').val(amount);
        
        $('#addExpenseSection').hide();
        $('#editExpenseSection').show();
    });
    
    $('#cancelEditExpense').on('click', function() {
        $('#editExpenseSection').hide();
    });
    
    // ── Inline edit expense form submit ───────────────────────────────────
    $('#inlineEditExpenseForm').on('submit', function(e) {
        e.preventDefault();
        
        const expenseId = $('#inlineEditExpenseId').val();
        const allocationId = $('#inlineEditAllocationId').val();
        const date = $('#inlineEditDate').val();
        const description = $('#inlineEditDescription').val();
        const amount = $('#inlineEditAmount').val();
        const currency = $('#inlineCurrency').val();
        
        const $btn = $(this).find('button[type="submit"]');
        const orig = $btn.html();
        $btn.html('<i class="feather icon-loader spinner"></i> Updating...').prop('disabled', true);
        
        updateAllocationExpense(expenseId, allocationId, date, description, amount, currency)
            .done(function(response) {
                if (response.success) {
                    $('#editExpenseSection').hide();
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            })
            .fail(function() {
                alert('An error occurred while updating the expense');
            })
            .always(function() {
                $btn.html(orig).prop('disabled', false);
            });
    });
    
    // ── Delete expense button click ───────────────────────────────────────
    $(document).on('click', '.delete-expense', function() {
        if (confirm('Are you sure you want to delete this expense? The amount will be returned to the allocation')) {
            const expenseId = $(this).data('id');
            
            const button = $(this);
            const originalHtml = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i>');
            button.prop('disabled', true);
            
            deleteAllocationExpense(expenseId)
                .done(function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    alert('An error occurred while deleting the expense');
                })
                .always(function() {
                    button.html(originalHtml);
                    button.prop('disabled', false);
                });
        }
    });
    
    // ── Reset top-level add expense modal on open ────────────────────────
    $('#addAllocationExpenseModal').on('shown.bs.modal', function() {
        $('#addAllocationExpenseForm')[0].reset();
        $('#topExpenseDate').val(new Date().toISOString().split('T')[0]);
    });

    // ── Top-level add expense form submit ─────────────────────────────────
    $('#addAllocationExpenseForm').on('submit', function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const orig = $btn.html();
        $btn.html('<i class="feather icon-loader spinner"></i> Adding...').prop('disabled', true);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                         document.querySelector('input[name="csrf_token"]')?.value;

        const $catSel = $('#topExpenseCategory');
        const categoryId = $catSel.val();
        const subCategoryId = $catSel.find('option:selected').data('sub') || '';

        $.ajax({
            url: '../api/allocation/allocation_actions.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'add_auto_allocation_expense',
                categoryId: categoryId,
                subCategoryId: subCategoryId,
                date: $('#topExpenseDate').val(),
                description: $('#topExpenseDescription').val(),
                amount: $('#topExpenseAmount').val(),
                currency: $('#topExpenseCurrency').val(),
                csrf_token: csrfToken
            },
            success: function(r) {
                r.success ? (alert(r.message), location.reload()) : alert('Error: ' + r.message);
            },
            error: function() { alert('An error occurred while adding the expense'); },
            complete: function() { $btn.html(orig).prop('disabled', false); }
        });
    });

    // Delete fund transaction from view funds modal
    $(document).on('click', '.delete-fund-transaction', function() {
        if (confirm('Are you sure you want to delete this fund transaction? The amount will be returned to the main account')) {
            const transactionId = $(this).data('id');
            const allocationId = $(this).data('allocation-id');
            
            // Show loading state
            const button = $(this);
            const originalHtml = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i>');
            button.prop('disabled', true);
            
            deleteFundTransaction(transactionId, allocationId)
                .done(function(response) {
                    if (response.success) {
                        alert(response.message);
                        // Refresh the funds modal
                        getFundTransactions(allocationId)
                            .done(function(response) {
                                if (response.success) {
                                    const allocation = response.allocation;
                                    const transactions = response.transactions || [];
                                    
                                    // Update allocation details
                                    $('#funds-allocation-category').text(allocation.category_name);
                                    $('#funds-allocation-account').text(allocation.account_name);
                                    $('#funds-allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                                    $('#funds-allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                                    $('#funds-allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                                    
                                    // Clear and populate funds table
                                    const tbody = $('#funds-table-body');
                                    tbody.empty();
                                    
                                    if (transactions.length > 0) {
                                        transactions.forEach(transaction => {
                                            const row = `
                                                <tr>
                                                    <td>${new Date(transaction.created_at).toLocaleDateString()}</td>
                                                    <td>${transaction.description}</td>
                                                    <td>${transaction.amount} ${transaction.currency}</td>
                                                    <td><span class="ba-badge ${transaction.type}">${transaction.type}</span></td>
                                                    <td>
                                                        <button class="ba-action-btn edit-fund-transaction"
                                                                data-id="${transaction.id}" data-allocation-id="${allocationId}"
                                                                data-amount="${transaction.amount}" data-description="${(transaction.description||'').replace(/'/g,"\\'")}">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <button class="ba-action-btn danger delete-fund-transaction" data-id="${transaction.id}" data-allocation-id="${allocationId}">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            `;
                                            tbody.append(row);
                                        });
                                        
                                        $('.funds-list').show();
                                        $('#no-funds-message').hide();
                                    } else {
                                        $('.funds-list').hide();
                                        $('#no-funds-message').show();
                                    }
                                }
                            });
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    alert('An error occurred while deleting the fund transaction');
                })
                .always(function() {
                    // Restore button state
                    button.html(originalHtml);
                    button.prop('disabled', false);
                });
        }
    });
    
    // Edit fund transaction
    $(document).on('click', '.edit-fund-transaction', function() {
        const $btn = $(this);
        $('#editFundTransactionId').val($btn.data('id'));
        $('#editFundAllocationId').val($btn.data('allocation-id'));
        $('#editFundAmount').val($btn.data('amount'));
        $('#editFundDescription').val($btn.data('description'));
        $('#viewFundsModal').modal('hide');
        $('#editFundTransactionModal').modal('show');
    });

    $('#editFundTransactionModal').on('hidden.bs.modal', function() {
        const aid = $('#editFundAllocationId').val();
        if (aid) $('.view-funds[data-id="' + aid + '"]').trigger('click');
    });

    $('#editFundTransactionForm').on('submit', function(e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const originalHtml = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...').prop('disabled', true);

        const transactionId = $('#editFundTransactionId').val();
        const allocationId = $('#editFundAllocationId').val();
        const amount = $('#editFundAmount').val();
        const description = $('#editFundDescription').val();

        updateFundTransaction(transactionId, allocationId, amount, description)
            .done(function(response) {
                if (response.success) {
                    $('#editFundTransactionModal').modal('hide');
                    alert(response.message);
                    $('.view-funds[data-id="' + allocationId + '"]').trigger('click');
                } else {
                    $('#editFundTransactionModal').modal('hide');
                    alert('Error: ' + response.message);
                }
            })
            .fail(function() {
                alert('An error occurred while updating the fund transaction');
            })
            .always(function() {
                $btn.html(originalHtml).prop('disabled', false);
            });
    });
});
