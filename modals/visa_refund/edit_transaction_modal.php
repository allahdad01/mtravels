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
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editRefundId" name="visa_id">
                        <input type="hidden" id="editOriginalAmount" name="original_amount">
                        <input type="hidden" id="originalAmount" name="original_amount">
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editPaymentDate">
                                    <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                                </label>
                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editPaymentTime">
                                    <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                </label>
                                <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                    placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                    title="Format: HH:MM:SS" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editPaymentAmount">
                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                </label>
                                <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                            </div>
                            <div class="form-group col-md-6">
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