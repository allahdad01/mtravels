<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel"><?= __('edit_transaction') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editUmrahId" name="umrah_id">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    
                    <div class="form-group">
                        <label for="editPaymentDate"><?= __('payment_date') ?></label>
                        <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentTime"><?= __('payment_time') ?></label>
                        <input type="time" class="form-control" id="editPaymentTime" name="payment_time" required step="any">
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentAmount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                    </div>
                    <div class="form-group">
                        <label for="editExchangeRate"><?= __('exchange_rate') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editExchangeRate" name="exchange_rate" required>
                    </div>
                    <div class="form-group">
                        <label for="editPaymentDescription"><?= __('description') ?></label>
                        <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="3"></textarea>
                    </div>
                    
                    <div class="text-right">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>