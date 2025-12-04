<!-- Add Partial Payment Modal -->
<div class="modal fade modern-modal" id="partialPaymentModal" tabindex="-1" role="dialog" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="partialPaymentModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('make_payment') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="partialPaymentForm">
                    <input type="hidden" id="clientId" name="client_id">
                    <input type="hidden" id="clientName" name="client_name">
                    
                    <!-- Client Info Section -->
                    <div class="form-section">
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="feather icon-info-circle mr-2" style="font-size: 1.5rem;"></i>
                                <h6 class="mb-0"><?= __('current_balances') ?></h6>
                            </div>
                        <div class="row">
                            <div class="col-md-6">
                                    <div class="balance-item d-flex align-items-center">
                                        <div class="currency-icon bg-success-light mr-2">
                                            <i class="fas fa-dollar-sign text-success"></i>
                                        </div>
                                        <div>
                                            <div class="balance-label small"><?= __('usd_balance') ?></div>
                                            <div class="balance-value text-success" id="currentUsdBalance">$0.00</div>
                                        </div>
                                    </div>
                            </div>
                            <div class="col-md-6">
                                    <div class="balance-item d-flex align-items-center">
                                        <div class="currency-icon bg-info-light mr-2">
                                            <i class="fas fa-money-bill-wave text-info"></i>
                                        </div>
                                        <div>
                                            <div class="balance-label small"><?= __('afs_balance') ?></div>
                                            <div class="balance-value text-info" id="currentAfsBalance">؋0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('payment_details') ?></div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="paymentCurrency"><?= __('select_currency_to_update') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                        <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                                    </div>
                        <small class="text-muted"><?= __('select_the_currency_balance_you_want_to_update') ?></small>
                    </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="totalAmount"><?= __('amount_to_pay_in_selected_currency') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                            <span class="input-group-text bg-light" id="totalAmountCurrency">$</span>
                            </div>
                            <input type="number" class="form-control" id="totalAmount" name="total_amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                        </div>
                    </div>
                    
                        <div class="form-group mb-3">
                        <label for="exchangeRate"><?= __('exchange_rate') ?> (<?= __('afs_per_usd') ?>)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                    <span class="input-group-text bg-light">1 <?= __('usd') ?> =</span>
                            </div>
                            <input type="number" class="form-control" id="exchangeRate" name="exchange_rate" step="0.01" min="0" required>
                            <div class="input-group-append">
                                    <span class="input-group-text bg-light"><?= __('afs') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Amounts Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('payment_amounts') ?></div>
                        <div class="row mb-3">
                        <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="usdAmount"><?= __('payment_in_usd') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="usdAmount" name="usd_amount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="afsAmount"><?= __('payment_in_afs') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">؋</span>
                                    </div>
                                    <input type="number" class="form-control" id="afsAmount" name="afs_amount" step="0.01" min="0" required>
                                </div>
                                    <small class="text-info" id="afsEquivalent"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="clientMainAccount"><?= __('main_account') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                        </div>
                                        <select class="form-control" id="clientMainAccount" name="main_account" required>
                        <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="receiptNumber"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="receiptNumber" name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                </div>
                            </div>
                    </div>
                    
                        <div class="form-group mb-0">
                        <label for="remarks"><?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="<?= __('enter_payment_details') ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary btn-confirm" id="processPaymentBtn">
                    <i class="feather icon-check-circle mr-1"></i><?= __('process_payment') ?>
                </button>
            </div>
        </div>
    </div>
</div>