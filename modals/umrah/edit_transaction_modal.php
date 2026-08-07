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
                    <input type="hidden" id="editPaymentCurrencyHidden" name="payment_currency">
                    
                    <div class="form-group">
                        <label for="editPaymentCurrency"><?= __('currency') ?></label>
                        <select class="form-control" id="editPaymentCurrency" disabled>
                            <option value="USD">USD</option>
                            <option value="AFS">AFS</option>
                            <option value="EUR">EUR</option>
                            <option value="DARHAM">DARHAM</option>
                            <option value="SAR"><?= __('sar') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentAmount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                    </div>
                    
                    <div class="form-group" id="editExchangeRateField">
                        <label for="editExchangeRate"><?= __('exchange_rate') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editExchangeRate" name="exchange_rate">
                        <small class="form-text text-muted"><?= __('enter_exchange_rate_if_different') ?></small>
                    </div>
                    
                    <div class="form-group" id="editReceiptField">
                        <label for="editReceipt"><?= __('receipt') ?></label>
                        <input type="text" class="form-control" id="editReceipt" name="receipt" placeholder="<?= __('enter_receipt_number') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="editTransactionTo"><?= __('transaction_to') ?></label>
                        <input type="text" class="form-control" id="editTransactionTo" name="transaction_to" readonly>
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
