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

<!-- Add Supplier Bonus Modal -->
<div class="modal fade modern-modal" id="addBonusModal" tabindex="-1" role="dialog" aria-labelledby="addBonusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addBonusModalLabel">
                    <i class="feather icon-gift mr-2"></i><?= __('add_supplier_bonus') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addBonusForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="bonusSupplierId" name="supplier_id">
                    <input type="hidden" id="bonusSupplierName" name="supplier_name">
                    <input type="hidden" id="bonusSupplierCurrency" name="supplier_currency">

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
                                        <input type="text" class="form-control" id="bonusSupplierNameDisplay" readonly>
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
                                        <input type="text" class="form-control" id="bonusSupplierCurrencyDisplay" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bonus Details -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-gift"></i><?= __('bonus_details') ?></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bonusAmount"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light" id="bonusCurrencySymbol">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="bonusAmount" name="amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bonusReceipt"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="bonusReceipt" name="receipt_number">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-message-square"></i><?= __('remarks') ?></div>
                        <div class="form-group mb-0">
                            <textarea class="form-control" id="bonusRemarks" name="remarks" rows="2" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="addBonusForm" class="btn btn-success">
                    <i class="feather icon-check-circle mr-1"></i><?= __('add_bonus') ?>
                </button>
            </div>
        </div>
    </div>
</div>
