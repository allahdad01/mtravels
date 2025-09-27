<!-- Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTransactionModalLabel">Add Transaction</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <input type="hidden" id="transaction_payment_id" name="payment_id">
                    <input type="hidden" id="transaction_payment_type" name="payment_type">
                    <input type="hidden" id="transaction_currency" name="currency">
                    <input type="hidden" id="transaction_main_account_id" name="main_account_id">
                    <input type="hidden" id="transaction_id" name="transaction_id">
                    
                    <div class="form-group">
                        <label for="payment_amount">Amount</label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" required step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_description">Description</label>
                        <textarea class="form-control" id="payment_description" name="payment_description" rows="3"></textarea>
                    </div>
                </form>
                
                <!-- Existing Transactions Table -->
                <div class="mt-4">
                    <h6>Existing Transactions</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsTableBody">
                                <!-- Transactions will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveTransaction">Add Transaction</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set today's date as default for payment date
    $('#payment_date').val(new Date().toISOString().split('T')[0]);

    // Load transactions when modal is shown
    $('#addTransactionModal').on('show.bs.modal', function() {
        var paymentId = $('#transaction_payment_id').val();
        loadTransactions(paymentId);
    });

    // Save transaction
    $('#saveTransaction').click(function() {
        var formData = {
            payment_id: $('#transaction_payment_id').val(),
            payment_type: $('#transaction_payment_type').val(),
            currency: $('#transaction_currency').val(),
            main_account_id: $('#transaction_main_account_id').val(),
            payment_amount: $('#payment_amount').val(),
            payment_date: $('#payment_date').val(),
            payment_description: $('#payment_description').val()
        };

        var url = 'add_additional_payment_transaction.php';
        var transactionId = $('#transaction_id').val();
        if (transactionId) {
            url = 'update_additional_payment_transaction.php';
            formData.transaction_id = transactionId;
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            success: function(response) {
                try {
                    // Handle both string and parsed JSON responses
                    var result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        alert('Transaction saved successfully');
                        $('#addTransactionModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error occurred'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('Error: Invalid response from server');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving transaction:', error);
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    alert('Error: ' + (errorResponse.message || 'Failed to save transaction'));
                } catch (e) {
                    alert('Error: Failed to save transaction. Please try again.');
                }
            }
        });
    });

    // Load transactions for a payment
    function loadTransactions(paymentId) {
        $.ajax({
            url: 'get_transactions.php',
            type: 'GET',
            data: { payment_id: paymentId },
            success: function(response) {
                var transactions = JSON.parse(response);
                var tbody = $('#transactionsTableBody');
                tbody.empty();
                
                transactions.forEach(function(transaction) {
                    tbody.append(`
                        <tr>
                            <td>${transaction.created_at}</td>
                            <td>${transaction.currency} ${transaction.amount}</td>
                            <td>${transaction.description}</td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-transaction" 
                                        data-id="${transaction.id}"
                                        data-amount="${transaction.amount}"
                                        data-date="${transaction.created_at}"
                                        data-description="${transaction.description}">
                                    <i class="feather icon-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-transaction" 
                                        data-id="${transaction.id}">
                                    <i class="feather icon-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            }
        });
    }

    // Edit transaction
    $(document).on('click', '.edit-transaction', function() {
        var id = $(this).data('id');
        var amount = $(this).data('amount');
        var date = $(this).data('date');
        var description = $(this).data('description');

        $('#payment_amount').val(amount);
        $('#payment_date').val(date);
        $('#payment_description').val(description);
        $('#transaction_id').val(id);
    });

    // Delete transaction
    $(document).on('click', '.delete-transaction', function() {
        if (confirm('Are you sure you want to delete this transaction?')) {
            var id = $(this).data('id');
            var paymentId = $('#transaction_payment_id').val();
            
            $.ajax({
                url: 'delete_additional_payment_transaction.php',
                type: 'POST',
                data: { 
                    transaction_id: id,
                    payment_id: paymentId
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Transaction deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function() {
                    alert('Error deleting transaction');
                }
            });
        }
    });
});
</script> 