<!-- Withdraw Supplier Modal -->
<div class="modal fade modern-modal" id="withdrawSupplierModal" tabindex="-1" aria-labelledby="withdrawSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="withdrawSupplierModalLabel"><?= __('withdraw_supplier_account') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="withdrawSupplierForm">
                    <!-- Select Main Account -->
                    <div class="mb-3">
                        <label for="withdrawMainAccount" class="form-label"><?= __('select_main_account') ?></label>
                        <select id="withdrawMainAccount" name="main_account" class="form-control" required>
                            <!-- Populated dynamically with main accounts -->
                        </select>
                    </div>
                    
                    <!-- Supplier Information -->
                    <div class="mb-3">
                        <label for="withdrawSupplierName" class="form-label"><?= __('supplier') ?></label>
                        <input type="text" id="withdrawSupplierName" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="withdrawSupplierCurrency" class="form-label"><?= __('currency') ?></label>
                        <input type="text" id="withdrawSupplierCurrency" class="form-control" readonly>
                    </div>
                    
                    <!-- Payment Currency -->
                    <div class="mb-3">
                        <label for="withdrawPaymentCurrency" class="form-label"><?= __('payment_currency') ?></label>
                        <select id="withdrawPaymentCurrency" name="payment_currency" class="form-control" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                    </div>
                    
                    <!-- Exchange Rate -->
                    <div class="mb-3 d-none" id="withdrawExchangeRateGroup">
                        <label for="withdrawExchangeRate" class="form-label" id="withdrawExchangeRateLabel"><?= __('exchange_rate_usd_to_afs') ?></label>
                        <input type="number" id="withdrawExchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                        <small class="form-text text-muted" id="withdrawExchangeHint"><?= __('exchange_rate_hint') ?></small>
                    </div>
                    
                    <!-- Withdrawal Amount -->
                    <div class="mb-3">
                        <label for="withdrawAmount" class="form-label"><?= __('amount_to_withdraw') ?></label>
                        <input type="number" id="withdrawAmount" name="amount" class="form-control" step="0.01" placeholder="<?= __('enter_amount') ?>" required>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label for="withdrawRemarks" class="form-label"><?= __('remarks') ?></label>
                        <textarea id="withdrawRemarks" name="remarks" class="form-control" rows="3" placeholder="<?= __('enter_remarks') ?>"></textarea>
                    </div>
                    
                    <!-- Receipt Number -->
                    <div class="mb-3">
                        <label for="withdrawReceiptNumber" class="form-label"><?= __('receipt_number') ?></label>
                        <input type="text" id="withdrawReceiptNumber" name="receipt_number" class="form-control" placeholder="<?= __('enter_receipt_number') ?>" required>
                    </div>
                    
                    <input type="hidden" id="withdrawSupplierId" name="supplier_id">
                    <button type="submit" class="btn btn-danger w-100"><?= __('withdraw_account') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>