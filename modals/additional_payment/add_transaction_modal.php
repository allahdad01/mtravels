<!-- Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addTransactionModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Payment Info Summary Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('payment_details') ?></h6>
                                <p class="mb-1"><strong><?= __('payment_type') ?>:</strong> <span id="trans-payment-type"></span></p>
                                <p class="mb-1"><strong><?= __('description') ?>:</strong> <span id="trans-description"></span></p>
                                <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="trans-account"></span></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('total_amount') ?>:</span>
                                        <strong id="totalAmount"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Exchange Rate:</span>
                                        <strong id="exchangeRateDisplay"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Exchanged Amount:</span>
                                        <strong id="exchangedAmount"></strong>
                                    </div>
                                    <div id="usdSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount USD:</span>
                                            <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Remaining Amount USD:</span>
                                            <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount AFS:</span>
                                            <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Remaining Amount AFS:</span>
                                            <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount EUR:</span>
                                            <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Remaining Amount EUR:</span>
                                            <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount AED:</span>
                                            <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Remaining Amount AED:</span>
                                            <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Transaction Form Card -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#transactionFormContainer">
                            <i class="feather icon-plus"></i> <?= __('show_hide_form') ?>
                        </button>
                    </div>
                    <div id="transactionFormContainer" class="collapse show">
                        <div class="card-body">
                            <form id="transactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" id="transaction_payment_id" name="payment_id">
                                <input type="hidden" id="transaction_payment_type" name="payment_type">
                                <input type="hidden" id="original_payment_currency" name="original_payment_currency">
                                <input type="hidden" id="transaction_main_account_id" name="main_account_id">
                                <input type="hidden" id="transaction_id" name="transaction_id">
                    
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_date">
                                                <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                            </label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_time">
                                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                            </label>
                                            <input type="time" class="form-control" id="payment_time" name="payment_time" step="1" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_amount">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </label>
                                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" required step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transaction_currency">
                                                <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                            </label>
                                            <select class="form-control" id="transaction_currency" name="currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD"><?= __('usd') ?></option>
                                                <option value="AFS"><?= __('afs') ?></option>
                                                <option value="EUR"><?= __('eur') ?></option>
                                                <option value="DARHAM"><?= __('darham') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group" id="exchange_rate_group" style="display: none;">
                                    <label for="exchange_rate">
                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                    </label>
                                    <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" step="0.01" placeholder="0.00">
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_description">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="payment_description" name="payment_description" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="receipt_number">
                                        <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                                    </label>
                                    <input type="text" class="form-control" id="receipt_number" name="receipt_number"
                                           placeholder="<?= __('enter_receipt_number') ?>">
                                </div>
                                
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-primary" id="AddTransaction">
                                        <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Existing Transactions Table Card -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="transactionsTable">
                                    <thead class="thead-light">
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('description') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('exchange_rate') ?></th>
                                        <th class="text-center"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTableBody">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
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
