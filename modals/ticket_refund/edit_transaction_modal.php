<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
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
                    <input type="hidden" id="editTicketId" name="ticket_id">
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
                            <small class="form-text text-muted"><?= __('format_hours_minutes_seconds_24_hour') ?></small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentAmount">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                        </label>
                        <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                    </div>

                    <div class="form-group" id="editExchangeRateField" style="display: none;">
                        <label for="editExchangeRate">
                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                        </label>
                        <input type="number" class="form-control" id="editExchangeRate"
                            name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                    </div>

                    <div class="form-group">
                        <label for="editPaymentDescription">
                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                        </label>
                        <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" id="updateTransactionBtn" class="btn btn-primary">
                    <i class="feather icon-save mr-1"></i><?= __('update_transaction') ?>
                </button>
            </div>
        </div>
    </div>
</div>