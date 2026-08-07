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
                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Weight Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('weight_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-passenger-name"></span></p>
                                    <p class="mb-1"><strong><?= __('pnr') ?>:</strong> <span id="trans-pnr"></span></p>
                                    <p class="mb-0"><strong><?= __('weight') ?>:</strong> <span id="trans-weight"></span></p>
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
                                    <div class="d-flex justify-content-between align-items-center mb-2">
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
                                    <div id="darhamSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_aed') ?>:</span>
                                            <strong id="paidAmountDARHAM" class="text-success">AED 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_aed') ?>:</span>
                                            <strong id="remainingAmountDARHAM" class="text-danger">AED 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="sarSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_sar') ?>:</span>
                                            <strong id="paidAmountSAR" class="text-success">SAR 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_sar') ?>:</span>
                                            <strong id="remainingAmountSAR" class="text-danger">SAR 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form (collapsed by default) -->
                    <div id="weightAddTransactionForm" class="collapse">
                        <div class="card mb-4 shadow-sm border-primary">
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
                                                    <option value="SAR"><?= __('sar') ?></option>
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
                    </div>

                    <!-- Transactions Table -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="feather icon-list mr-2"></i>
                                <?= __('transaction_history') ?>
                            </h6>
                            <button type="button" class="btn btn-sm btn-light" data-toggle="collapse" data-target="#weightAddTransactionForm">
                                <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" id="transactionsTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="border-0">
                                                <i class="feather icon-calendar mr-1"></i>
                                                <?= __('date') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-file-text mr-1"></i>
                                                <?= __('remarks') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-hash mr-1"></i>
                                                <?= __('receipt') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-dollar-sign mr-1"></i>
                                                <?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i>
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
