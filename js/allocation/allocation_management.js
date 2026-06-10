// Budget Allocation Management Core Functions

// Create budget allocation
function createBudgetAllocation(categoryId, mainAccountId, amount, currency, date, description) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'create_allocation',
            category_id: categoryId,
            main_account_id: mainAccountId,
            amount: amount,
            currency: currency,
            date: date,
            description: description,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Add funds to allocation
function addFundsToAllocation(allocationId, amount, note) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'add_funds',
            allocation_id: allocationId,
            amount: amount,
            note: note,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Get fund transactions for an allocation
function getFundTransactions(allocationId) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'get_fund_transactions',
            allocation_id: allocationId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Get allocation details and expenses
function getAllocationDetails(allocationId) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'get_allocation_details',
            allocation_id: allocationId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Delete allocation
function deleteAllocation(allocationId) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'delete_allocation',
            allocation_id: allocationId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Delete fund transaction
function deleteFundTransaction(transactionId, allocationId) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'delete_fund_transaction',
            transaction_id: transactionId,
            allocation_id: allocationId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Update fund transaction
function updateFundTransaction(transactionId, allocationId, amount, description) {
    const csrfToken = $('input[name="csrf_token"]').val();
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'update_fund_transaction',
            transaction_id: transactionId,
            allocation_id: allocationId,
            amount: amount,
            description: description,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Delete expense
function deleteExpense(expenseId) {
    // Get CSRF token from meta tag or hidden input
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                     document.querySelector('input[name="csrf_token"]')?.value;
    
    return $.ajax({
        url: '../api/expense/expense_actions.php',
        type: 'POST',
        data: {
            action: 'delete_expense',
            expenseId: expenseId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Add expense to allocation
function addAllocationExpense(allocationId, categoryId, date, description, amount, currency) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                     document.querySelector('input[name="csrf_token"]')?.value;
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'add_allocation_expense',
            allocation_id: allocationId,
            category_id: categoryId,
            date: date,
            description: description,
            amount: amount,
            currency: currency,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Update expense in allocation
function updateAllocationExpense(expenseId, allocationId, date, description, amount, currency) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                     document.querySelector('input[name="csrf_token"]')?.value;
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'update_allocation_expense',
            expense_id: expenseId,
            allocation_id: allocationId,
            date: date,
            description: description,
            amount: amount,
            currency: currency,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Delete expense from allocation
function deleteAllocationExpense(expenseId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                     document.querySelector('input[name="csrf_token"]')?.value;
    return $.ajax({
        url: '../api/allocation/allocation_actions.php',
        type: 'POST',
        data: {
            action: 'delete_allocation_expense',
            expense_id: expenseId,
            csrf_token: csrfToken
        },
        dataType: 'json'
    });
}

// Auto-submit filter form
function setupFilterAutoSubmit() {
    $('#monthFilter, #yearFilter').on('change', function() {
        $(this).closest('form').submit();
    });
}

// Initialize allocation management
function initializeAllocationManagement() {
    setupFilterAutoSubmit();
}
