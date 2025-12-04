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
                    <input type="hidden" id="supplierId" name="supplier_id">
                    <input type="hidden" id="supplierName" name="supplier_name">
                    
                    
                    <!-- Supplier Info Section -->
                    <div class="form-section">
                        <div class="supplier-info alert alert-info mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="feather icon-user mr-2"></i>
                                <h6 class="mb-0" id="supplierNameDisplay"></h6>
                            </div>
                            <p class="mb-0 small" id="supplierCurrencyDisplay"></p>
                        </div>
                    </div>
                    
                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>
                        
                        <div class="form-group mb-3">
                            <label for="mainAccount"><?= __('select_main_account') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                </div>
                                <select class="form-control" id="mainAccount" name="main_account" required>
                                    <option value=""><?= __('select_account') ?></option>
                                    <!-- Options will be loaded dynamically -->
                                </select>
                            </div>
                        </div>
                        
                        
                        
                        <!-- Payment Currency -->
                    <div class="mb-3">
                        <label for="supplierCurrency" class="form-label">Supplier Currency</label>
                        <input class="form-control" type="text" id="supplierCurrency" name="supplier_currency" readonly>
                        <label for="paymentCurrency" class="form-label">Payment Currency</label>
                        <select id="paymentCurrency" name="payment_currency" class="form-control" required>
                            <option value="USD">USD</option>
                            <option value="AFS">AFS</option>
                        </select>
                    </div>
                    <!-- Exchange Rate -->
                    <div class="mb-3 d-none" id="exchangeRateGroup">
                        <label for="exchangeRate" class="form-label" id="exchangeRateLabel">Exchange rate (USD → AFS)</label>
                        <input type="number" id="exchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                        <small class="form-text text-muted" id="exchangeHint">Provide USD → AFS rate only when payment currency differs from supplier currency.</small>
                    </div>
                        <div class="form-group mb-3">
                            <label for="amount"><?= __('amount') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light" id="currencySymbol">$</span>
                                </div>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="receipt"><?= __('receipt_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                </div>
                                <input type="text" class="form-control" id="receipt" name="receipt_number" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-0">
                            <label for="remarks"><?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="fundSupplierForm" class="btn btn-info">
                    <i class="feather icon-check-circle mr-1"></i><?= __('fund_account') ?>
                </button>
            </div>
        </div>
    </div>
</div>