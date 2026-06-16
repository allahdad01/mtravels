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

<!-- Fund Supplier Modal -->
<div class="modal fade modern-modal" id="fundSupplierModal" tabindex="-1" role="dialog" aria-labelledby="fundSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="fundSupplierModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('fund_supplier_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="fundSupplierForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="supplierId" name="supplier_id">
                    <input type="hidden" id="supplierName" name="supplier_name">

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
                                        <input type="text" class="form-control" id="supplierNameDisplay" readonly>
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
                                        <input type="text" class="form-control" id="supplierCurrencyDisplay" readonly>
                                        <input type="hidden" id="supplierCurrency" name="supplier_currency">
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
                                    <label for="mainAccount"><?= __('main_account') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                        </div>
                                        <select class="form-control" id="mainAccount" name="main_account" required>
                                            <option value=""><?= __('select_account') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="paymentCurrency"><?= __('payment_currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <select id="paymentCurrency" name="payment_currency" class="form-control" required>
                                            <option value="USD"><?= __('usd') ?></option>
                                            <option value="AFS"><?= __('afs') ?></option>
                                            <option value="EUR"><?= __('eur') ?></option>
                                            <option value="DARHAM"><?= __('darham') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 d-none" id="exchangeRateGroup">
                            <label for="exchangeRate" class="form-label" id="exchangeRateLabel"><?= __('exchange_rate') ?></label>
                            <div class="input-group">
                                <input type="number" id="exchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-light" id="fundFormulaBadge" style="font-weight:700;font-size:16px;">×</span>
                                </div>
                            </div>
                            <small class="form-text text-muted" id="exchangeHint" style="font-size:11px;margin-top:4px;"></small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="amount"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light" id="currencySymbol">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receipt"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="receipt" name="receipt_number" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-message-square"></i><?= __('remarks') ?></div>
                        <div class="form-group mb-0">
                            <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="fundSupplierForm" class="btn btn-info" id="fundSupplierBtn">
                    <i class="feather icon-check-circle mr-1"></i><?= __('fund_account') ?>
                </button>
            </div>
        </div>
    </div>
</div>
