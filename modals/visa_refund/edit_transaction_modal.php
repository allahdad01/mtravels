    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_transaction') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editRefundId" name="visa_id">
                        <input type="hidden" id="editOriginalAmount" name="original_amount">
                        <input type="hidden" id="originalAmount" name="original_amount">

                        <div class="form-group">
                            <label for="editPaymentAmount">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                            </label>
                            <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                        </div>

                        <div class="form-group">
                            <label for="editPaymentDescription">
                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                            </label>
                            <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                        </div>

                        <div class="form-group" id="editExchangeRateField" style="display: none;">
                            <label for="editExchangeRate">
                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                            </label>
                            <input type="number" class="form-control" id="editExchangeRate"
                                   name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                        </div>

                        <div class="form-group">
                            <label for="editReceiptNumber">
                                <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                            </label>
                            <input type="text" class="form-control" id="editReceiptNumber"
                                   name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
