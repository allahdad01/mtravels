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
                        <input type="hidden" id="editRefundId" name="booking_id">
                        <input type="hidden" id="editOriginalAmount" name="original_amount">
                        <input type="hidden" id="originalAmount" name="original_amount">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editPaymentAmount">
                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editPaymentCurrency">
                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                    </label>
                                    <select class="form-control" id="editPaymentCurrency" name="payment_currency" required disabled>
                                        <option value=""><?= __('select_currency') ?></option>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                    <input type="hidden" id="editPaymentCurrencyHidden" name="payment_currency_actual">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="editPaymentDescription">
                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                            </label>
                            <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="editReceiptNumber">
                                <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                            </label>
                            <input type="text" class="form-control" id="editReceiptNumber"
                                   name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                        </div>

                        <div class="form-group" id="editExchangeRateField" style="display: none;">
                            <label for="editTransactionExchangeRate">
                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                            </label>
                            <input type="number" class="form-control" id="editTransactionExchangeRate"
                                   name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                            <small class="form-text text-muted d-block mt-1">
                                Enter how many <span id="editExchangeRateTarget"></span> equals 1 <span id="editExchangeRateBase"></span>
                                <span id="editExchangeRateExample" class="d-block mt-1" style="color: #666;"></span>
                            </small>
                        </div>

                        <div class="modal-footer px-0 pb-0">
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
