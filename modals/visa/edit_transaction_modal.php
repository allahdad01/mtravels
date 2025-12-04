                                        <!-- Edit Transaction Modal -->
                                        <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editTransactionModalLabel"><?= __('edit_transaction') ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form id="editTransactionForm">
                                                            <!-- Hidden fields for IDs -->
                                                            <input type="hidden" id="editTransactionId" name="transaction_id">
                                                            <input type="hidden" id="editVisaId" name="visa_id">
                                                            <input type="hidden" id="originalAmount" name="original_amount">
                                                            
                                                            <!-- Date and Time -->
                                                            <div class="form-group">
                                                                <label for="editPaymentDate"><?= __('payment_date') ?></label>
                                                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="editPaymentTime"><?= __('payment_time') ?></label>
                                                                <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                                                       pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                                                       placeholder="HH:MM:SS" required>
                                                                <small class="form-text text-muted"><?= __('enter_time_in_24_hour_format') ?></small>
                                                            </div>
                                                            
                                                            <!-- Amount -->
                                                            <div class="form-group">
                                                                <label for="editPaymentAmount"><?= __('amount') ?></label>
                                                                <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                                                                <small class="form-text text-muted"><?= __('changing_this_amount_will_update_all_subsequent_balances') ?></small>
                                                            </div>

                                                            <!-- Currency -->
                                                            <div class="form-group">
                                                                <label for="editPaymentCurrency"><?= __('currency') ?></label>
                                                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required>
                                                                    <option value=""><?= __('select_currency') ?></option>
                                                                    <option value="USD"><?= __('usd') ?></option>
                                                                    <option value="AFS"><?= __('afs') ?></option>
                                                                    <option value="EUR"><?= __('eur') ?></option>
                                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                                </select>
                                                            </div>

                                                            <!-- Exchange Rate -->
                                                            <div class="form-group" id="editExchangeRateField" style="display: none;">
                                                                <label for="editTransactionExchangeRate">
                                                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                                </label>
                                                                <input type="number" class="form-control" id="editTransactionExchangeRate"
                                                                       name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                                            </div>

                                                            <!-- Description -->
                                                            <div class="form-group">
                                                                <label for="editPaymentDescription"><?= __('description') ?></label>
                                                                <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="3"></textarea>
                                                            </div>
                                                            
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                                <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>