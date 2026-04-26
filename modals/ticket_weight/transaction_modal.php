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
                <div class="modal-body" style="max-height: 80vh; overflow-y: auto; padding: 1.5rem;">
                    <!-- Weight Info Card -->
                    <div class="card mb-4 border-primary shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-info mr-2"></i><?= __('weight_details') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="media">
                                        <div class="media-body">
                                            <h6 class="mt-0 mb-1 text-muted"><?= __('passenger_information') ?></h6>
                                            <p class="mb-1"><strong class="text-dark"><?= __('passenger') ?>:</strong> <span id="trans-passenger-name" class="text-primary">Loading...</span></p>
                                            <p class="mb-1"><strong class="text-dark"><?= __('pnr') ?>:</strong> <span id="trans-pnr" class="text-primary">Loading...</span></p>
                                            <p class="mb-0"><strong class="text-dark"><?= __('weight') ?>:</strong> <span id="trans-weight" class="text-primary">Loading...</span></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title text-muted mb-2">
                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('total_amount') ?>
                                                    </h6>
                                                    <h4 class="mb-0 text-primary" id="totalAmount">Loading...</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title text-muted mb-2">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                    </h6>
                                                    <h5 class="mb-0 text-info" id="exchangeRateDisplay">Loading...</h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6 class="text-muted mb-2">
                                            <i class="feather icon-trending-up mr-1"></i><?= __('exchanged_amount') ?>
                                        </h6>
                                        <p class="mb-0 text-success font-weight-bold" id="exchangedAmount">Loading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status Cards -->
                    <div class="row mb-4" id="paymentStatusContainer" style="display: none;">
                        <div class="col-12">
                            <h6 class="mb-3 text-muted">
                                <i class="feather icon-bar-chart-2 mr-2"></i><?= __('payment_status') ?>
                            </h6>
                        </div>
                        <div class="col-md-3" id="usdSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">USD</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="afsSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">AFS</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="eurSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">EUR</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="darhamSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">AED</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountDARHAM" class="text-success">DARHAM 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountDARHAM" class="text-danger">DARHAM 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_transaction') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="weightTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" id="weightId" name="weight_id">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionAmount" class="font-weight-bold">
                                                <i class="feather icon-dollar-sign mr-1"></i>
                                                <?= __('amount') ?> <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="feather icon-hash"></i></span>
                                                </div>
                                                <input type="number" class="form-control form-control-lg" id="transactionAmount" name="amount" step="0.01" placeholder="0.00" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionCurrency" class="font-weight-bold">
                                                <i class="feather icon-globe mr-1"></i>
                                                <?= __('currency') ?> <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-lg" id="transactionCurrency" name="currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD">USD - US Dollar</option>
                                                <option value="AFS">AFS - Afghan Afghani</option>
                                                <option value="EUR">EUR - Euro</option>
                                                <option value="DARHAM">DARHAM - AED</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exchange Rate Section -->
                                 <div class="row mb-3">
                                     <div class="col-12">
                                         <div class="form-group" id="exchangeRateField" style="display: none;">
                                             <label id="exchangeRateLabel" for="transactionExchangeRate" class="font-weight-bold">
                                                 <i class="feather icon-refresh-cw mr-1"></i>
                                                 <?= __('exchange_rate') ?> <span class="text-danger">*</span>
                                             </label>
                                             <div class="input-group">
                                                 <div class="input-group-prepend">
                                                     <span class="input-group-text"><i class="feather icon-trending-up"></i></span>
                                                 </div>
                                                 <input type="number" class="form-control form-control-lg" id="transactionExchangeRate"
                                                     name="exchange_rate" step="0.01" placeholder="0.00" required>
                                             </div>
                                             <small class="form-text text-muted d-block mt-1">
                                                 Enter how many <span id="exchangeRateTarget"></span> equals 1 <span id="exchangeRateBase"></span>
                                                 <span id="exchangeRateExample" class="d-block mt-1" style="color: #666;"></span>
                                             </small>
                                         </div>
                                     </div>
                                 </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionDate" class="font-weight-bold">
                                                <i class="feather icon-calendar mr-1"></i>
                                                <?= __('date') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control form-control-lg" id="transactionDate" name="transaction_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionTime" class="font-weight-bold">
                                                <i class="feather icon-clock mr-1"></i>
                                                <?= __('time') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="time" class="form-control form-control-lg" id="transactionTime" name="transaction_time" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="transactionRemarks" class="font-weight-bold">
                                        <i class="feather icon-message-square mr-1"></i>
                                        <?= __('remarks') ?>
                                    </label>
                                    <textarea class="form-control" id="transactionRemarks" name="remarks" rows="3" placeholder="Optional remarks..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="receiptNumber" class="font-weight-bold">
                                        <i class="feather icon-hash mr-1"></i>
                                        <?= __('receipt_number') ?>
                                    </label>
                                    <input type="text" class="form-control" id="receiptNumber"
                                        name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                                </div>

                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary btn-lg px-4">
                                        <i class="feather icon-save mr-2"></i>
                                        <?= __('save_transaction') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="feather icon-list mr-2"></i>
                                <?= __('transaction_history') ?>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="transactionsTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="border-0">
                                                <i class="feather icon-calendar mr-1"></i>
                                                <?= __('date') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-dollar-sign mr-1"></i>
                                                <?= __('remarks') ?>
                                                
                                            </th>
                                            
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i>
                                                <?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-message-square mr-1"></i>
                                                <?= __('exchange_rate') ?>
                                            </th>
                                            <th class="text-center border-0">
                                                <i class="feather icon-settings mr-1"></i>
                                                <?= __('actions') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionsTableBody">
                                        <!-- Transactions will be loaded here dynamically -->
                                        <tr id="noTransactionsRow">
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="feather icon-inbox display-4 mb-3"></i>
                                                <h5 class="mb-2"><?= __('no_transactions_found') ?></h5>
                                                <p class="mb-0"><?= __('add_first_transaction_above') ?></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>