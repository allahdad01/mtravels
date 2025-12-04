<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editTransactionForm">
                <div class="modal-body">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editBookingId" name="booking_id">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPaymentDate">
                                    <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                </label>
                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPaymentTime">
                                    <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                </label>
                                <input type="time" class="form-control" id="editPaymentTime" name="payment_time" step="1" required>
                            </div>
                        </div>
                    </div>
                    
                   
                    
                    <div class="form-group">
                        <label for="editPaymentAmount">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                        </label>
                        <input type="number" class="form-control" id="editPaymentAmount" name="payment_amount" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
                        <label for="editPaymentCurrency">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                        </label>
                        <select class="form-control" id="editPaymentCurrency" name="payment_currency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>

                    <div class="form-group" id="editExchangeRateField" style="display: none;">
                        <label for="editTransactionExchangeRate">
                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                        </label>
                        <input type="number" class="form-control" id="editTransactionExchangeRate"
                            name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                    </div>

                    <div class="form-group">
                        <label for="editPaymentDescription">
                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                        </label>
                        <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>