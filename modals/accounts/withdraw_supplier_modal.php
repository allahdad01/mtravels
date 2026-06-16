<style>
.modal-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    border: 1px solid #e9ecef;
}
.modal-section-title {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-section-title i {
    font-size: 16px;
}
</style>

<!-- Withdraw Supplier Modal -->
<div class="modal fade modern-modal" id="withdrawSupplierModal" tabindex="-1" aria-labelledby="withdrawSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="withdrawSupplierModalLabel">
                    <i class="feather icon-arrow-down mr-2"></i><?= __('withdraw_supplier_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="withdrawSupplierForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="withdrawSupplierId" name="supplier_id">

                    <!-- Supplier Information -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('supplier_information') ?></div>
                        <div class="row">
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label><?= __('supplier') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-user"></i></span>
                                        </div>
                                        <input type="text" id="withdrawSupplierName" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="text" id="withdrawSupplierCurrency" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction Details -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('transaction_details') ?></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawMainAccount"><?= __('main_account') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                        </div>
                                        <select id="withdrawMainAccount" name="main_account" class="form-control" required>
                                            <option value=""><?= __('select_account') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawPaymentCurrency"><?= __('payment_currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <select id="withdrawPaymentCurrency" name="payment_currency" class="form-control" required>
                                            <option value="USD"><?= __('usd') ?></option>
                                            <option value="AFS"><?= __('afs') ?></option>
                                            <option value="EUR"><?= __('eur') ?></option>
                                            <option value="DARHAM"><?= __('darham') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 d-none" id="withdrawExchangeRateGroup">
                            <label for="withdrawExchangeRate" class="form-label" id="withdrawExchangeRateLabel"><?= __('exchange_rate') ?></label>
                            <div class="input-group">
                                <input type="number" id="withdrawExchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-light" id="withdrawFormulaBadge" style="font-weight:700;font-size:16px;">×</span>
                                </div>
                            </div>
                            <small class="form-text text-muted" id="withdrawExchangeHint" style="font-size:11px;margin-top:4px;"></small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawAmount"><?= __('amount_to_withdraw') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                        </div>
                                        <input type="number" id="withdrawAmount" name="amount" class="form-control" step="0.01" placeholder="<?= __('enter_amount') ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawReceiptNumber"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                        </div>
                                        <input type="text" id="withdrawReceiptNumber" name="receipt_number" class="form-control" placeholder="<?= __('enter_receipt_number') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-message-square"></i><?= __('remarks') ?></div>
                        <div class="form-group mb-0">
                            <textarea id="withdrawRemarks" name="remarks" class="form-control" rows="2" placeholder="<?= __('enter_remarks') ?>"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="withdrawSupplierForm" class="btn btn-danger" id="withdrawSupplierBtn">
                    <i class="feather icon-arrow-down mr-1"></i><?= __('withdraw') ?>
                </button>
            </div>
        </div>
    </div>
</div>
