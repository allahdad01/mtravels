<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <form id="editTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="edit_transaction_id" name="transaction_id">
                    <input type="hidden" id="edit_transaction_payment_id" name="payment_id">
                    <input type="hidden" id="edit_original_payment_currency" name="original_payment_currency">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_payment_amount">
                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                </label>
                                <input type="number" class="form-control" id="edit_payment_amount" name="payment_amount" required step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_payment_description">
                                    <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                </label>
                                <textarea class="form-control" id="edit_payment_description" name="payment_description" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="edit_receipt">
                                    <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                                </label>
                                <input type="text" class="form-control" id="edit_receipt" name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_transaction_currency">
                                    <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                </label>
                                <select class="form-control" id="edit_transaction_currency" name="currency" required disabled>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                    <option value="SAR"><?= __('sar') ?></option>
                                </select>
                                <input type="hidden" name="currency" id="edit_currency_hidden">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="edit_exchange_rate_group" style="display: none;">
                                <label for="edit_exchange_rate">
                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                </label>
                                <input type="number" class="form-control" id="edit_exchange_rate" name="exchange_rate" step="0.0001" min="0.0001">
                                <small class="form-text text-muted"><?= __('enter_the_exchange_rate_from_transaction_currency_to_payment_currency') ?></small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
                <button type="button" class="btn btn-primary" id="updateTransaction">
                    <i class="feather icon-save mr-1"></i><?= __('update_transaction') ?>
                </button>
            </div>
        </div>
    </div>
</div>
