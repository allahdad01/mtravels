<!-- Transaction Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Hotel Info Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('ticket_booking_details') ?></h6>
                                <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                <p class="mb-1"><strong><?= __('pnr') ?>:</strong> <span id="trans-order-id"></span></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('total_amount') ?>:</span>
                                        <strong id="totalAmount"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('exchange_rate') ?>:</span>
                                        <strong id="exchangeRateDisplay"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= __('exchanged_amount') ?>:</span>
                                        <strong id="exchangedAmount"></strong>
                                    </div>
                                    <div id="usdSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_usd') ?>:</span>
                                            <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_usd') ?>:</span>
                                            <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_afs') ?>:</span>
                                            <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_afs') ?>:</span>
                                            <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_eur') ?>:</span>
                                            <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_eur') ?>:</span>
                                            <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_aed') ?>:</span>
                                            <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_aed') ?>:</span>
                                            <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                            <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('description') ?></th>
                                        <th><?= __('payment') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('exchange_rate') ?></th>
                                        <th class="text-center"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="transactionTableBody">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add Transaction Form -->
                <div id="addTransactionForm" class="collapse">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                        </div>
                        <div class="card-body">
                            <form id="hotelTransactionForm">
                                <input type="hidden" id="booking_id" name="booking_id">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentDate">
                                                <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                            </label>
                                            <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentTime">
                                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                            </label>
                                            <input type="text" class="form-control" id="paymentTime" name="payment_time" 
                                                placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                                title="Format: HH:MM:SS" required>
                                            <small class="form-text text-muted"><?= __('format_hours_minutes_seconds_24_hour') ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentAmount">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </label>
                                            <input type="number" class="form-control" id="paymentAmount" 
                                                   name="payment_amount" step="0.01" min="0.01" required 
                                                   placeholder="<?= __('enter_amount') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentCurrency">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                            </label>
                                            <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD"><?= __('usd') ?></option>
                                                <option value="AFS"><?= __('afs') ?></option>
                                                <option value="EUR"><?= __('eur') ?></option>
                                                <option value="DARHAM"><?= __('darham') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" id="exchangeRateField" style="display: none;">
                                    <label for="transactionExchangeRate">
                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                    </label>
                                    <input type="number" class="form-control" id="transactionExchangeRate"
                                        name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                </div>

                                <div class="form-group">
                                    <label for="paymentDescription">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="paymentDescription" 
                                              name="payment_description" rows="2" required
                                              placeholder="<?= __('enter_payment_description') ?>"></textarea>
                                </div>

                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                            data-target="#addTransactionForm">
                                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>