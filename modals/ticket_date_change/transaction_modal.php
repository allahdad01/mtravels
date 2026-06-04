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
                <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                    <!-- Ticket Info Card -->
                    <div class="card mb-4 border-primary shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-info mr-2"></i><?= __('ticket_information') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="feather icon-user mr-2"></i><?= __('passenger_details') ?>
                                    </h6>
                                    <div class="pl-3">
                                        <p class="mb-2">
                                            <strong class="text-dark"><?= __('passenger') ?>:</strong>
                                            <span id="trans-passenger-name" class="text-primary">Loading...</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong class="text-dark"><?= __('pnr') ?>:</strong>
                                            <span id="trans-pnr" class="text-primary">Loading...</span>
                                        </p>
                                        <p class="mb-0">
                                            <strong class="text-dark"><?= __('new_departure') ?>:</strong>
                                            <span id="trans-departure-date" class="text-primary">Loading...</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="feather icon-dollar-sign mr-2"></i><?= __('payment_summary') ?>
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted d-block"><?= __('total_amount') ?></small>
                                                <strong id="totalAmount" class="text-dark h6">0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block"><?= __('exchange_rate') ?></small>
                                                <strong id="exchangeRateDisplay" class="text-dark h6">0.00</strong>
                                            </div>
                                        </div>
                                        <hr class="my-2">
                                        <div class="row">
                                            <div class="col-12">
                                                <small class="text-muted d-block"><?= __('exchanged_amount') ?></small>
                                                <strong id="exchangedAmount" class="text-success h6">0.00</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Currency-specific sections -->
                                    <div id="usdSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_usd') ?>
                                                </small>
                                                <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_usd') ?>
                                                </small>
                                                <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_afs') ?>
                                                </small>
                                                <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_afs') ?>
                                                </small>
                                                <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_eur') ?>
                                                </small>
                                                <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_eur') ?>
                                                </small>
                                                <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_aed') ?>
                                                </small>
                                                <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_aed') ?>
                                                </small>
                                                <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div class="card mb-4 shadow-sm">
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



                    <!-- Transaction History -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-list mr-2"></i><?= __('transaction_history') ?>
                            </h6>
                            <small class="text-muted" id="transactionCount">0 <?= __('transactions') ?></small>
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