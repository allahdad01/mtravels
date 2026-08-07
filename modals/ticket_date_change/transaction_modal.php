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
                    <!-- Date Change Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('date_change_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-passenger-name"></span></p>
                                    <p class="mb-1"><strong><?= __('pnr') ?>:</strong> <span id="trans-pnr"></span></p>
                                    <p class="mb-0"><strong><?= __('new_departure') ?>:</strong> <span id="trans-departure-date"></span></p>
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
                    <div id="addTransactionForm" class="collapse">
                        <div class="card mb-4 shadow-sm border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0 text-white">
                                    <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_transaction') ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="dateChangeTransactionForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                    <input type="hidden" id="booking_id" name="booking_id">

                                    <!-- Transaction Details Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary mb-3 border-bottom pb-2">
                                                <i class="feather icon-calendar mr-2"></i><?= __('transaction_details') ?>
                                            </h6>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentDate" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="date" class="form-control border" id="paymentDate" name="payment_date" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentTime" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control border" id="paymentTime" name="payment_time"
                                                    placeholder="14:30:00" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]"
                                                    title="Format: HH:MM:SS (24-hour format)" required>
                                                <small class="form-text text-muted">
                                                    <i class="feather icon-help-circle mr-1"></i><?= __('format_hours_minutes_seconds_24_hour') ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Information Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary mb-3 border-bottom pb-2">
                                                <i class="feather icon-credit-card mr-2"></i><?= __('payment_information') ?>
                                            </h6>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentAmount" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" step="0.01" min="0" class="form-control border"
                                                    id="paymentAmount" name="payment_amount"
                                                    placeholder="0.00" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentCurrency" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control border" id="paymentCurrency" name="payment_currency" required>
                                                    <option value=""><?= __('select_currency') ?></option>
                                                    <option value="USD">USD - <?= __('us_dollar') ?></option>
                                                    <option value="AFS">AFS - <?= __('afghan_afghani') ?></option>
                                                    <option value="EUR">EUR - <?= __('euro') ?></option>
                                                    <option value="DARHAM">AED - <?= __('uae_dirham') ?></option>
                                                    <option value="SAR"><?= __('sar') ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Exchange Rate Section -->
                                     <div class="row mb-4">
                                         <div class="col-12">
                                             <div class="form-group" id="exchangeRateField" style="display: none;">
                                                 <label id="exchangeRateLabel" for="transactionExchangeRate" class="font-weight-semibold text-dark">
                                                     <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                     <span class="text-danger">*</span>
                                                 </label>
                                                 <input type="number" class="form-control border" id="transactionExchangeRate"
                                                     name="exchange_rate" step="0.01" placeholder="0.00" required>
                                                 <small class="form-text text-muted d-block mt-1">
                                                     Enter how many <span id="exchangeRateTarget"></span> equals 1 <span id="exchangeRateBase"></span>
                                                     <span id="exchangeRateExample" class="d-block mt-1" style="color: #666;"></span>
                                                 </small>
                                             </div>
                                         </div>
                                     </div>

                                    <!-- Description Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary mb-3 border-bottom pb-2">
                                                <i class="feather icon-edit-3 mr-2"></i><?= __('additional_information') ?>
                                            </h6>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="paymentDescription" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                                </label>
                                                <textarea class="form-control border" id="paymentDescription" name="payment_description"
                                                        rows="3" placeholder="<?= __('enter_transaction_description') ?>"></textarea>
                                                <small class="form-text text-muted"><?= __('optional_field') ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="receiptNumber" class="font-weight-semibold text-dark">
                                                    <i class="feather icon-hash mr-1"></i><?= __('receipt_number') ?>
                                                </label>
                                                <input type="text" class="form-control border" id="receiptNumber"
                                                    name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                                <small class="text-muted">
                                                    <i class="feather icon-info mr-1"></i>
                                                    <span class="text-danger">*</span> <?= __('required_fields') ?>
                                                </small>

                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-secondary" onclick="transactionManager.resetForm()">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('reset') ?>
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="feather icon-save mr-1"></i><?= __('save_transaction') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-list mr-2"></i><?= __('transaction_history') ?>
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                                    <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                                </button>
                                <small class="text-muted ml-2" id="transactionCount">0 <?= __('transactions') ?></small>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="border-0">
                                                <i class="feather icon-calendar mr-1"></i><?= __('date_time') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-hash mr-1"></i><?= __('receipt') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                            </th>
                                            <th class="text-center border-0">
                                                <i class="feather icon-settings mr-1"></i><?= __('actions') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="feather icon-loader fa-spin fa-2x mb-3 d-block"></i>
                                                    <p class="mb-0"><?= __('loading_transactions') ?>...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light text-center" id="transactionFooter" style="display: none;">
                            <small class="text-muted">
                                <i class="feather icon-info mr-1"></i>
                                <?= __('showing_all_transactions_for_this_ticket') ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <div class="d-flex justify-content-between w-100">
                        <small class="text-muted align-self-center">
                            <i class="feather icon-info mr-1"></i>
                            <?= __('manage_all_transactions_for_this_date_change') ?>
                        </small>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('close') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
