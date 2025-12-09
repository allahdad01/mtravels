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
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        
                        <input type="hidden" id="bonusSupplierId" name="supplier_id">
                        <input type="hidden" id="bonusSupplierName" name="supplier_name">
                        <input type="hidden" id="bonusSupplierCurrency" name="supplier_currency">
                        
                        <div class="form-section">
                            <div class="supplier-info alert alert-success mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="feather icon-user mr-2"></i>
                                    <h6 class="mb-0" id="bonusSupplierNameDisplay"></h6>
                                </div>
                                <p class="mb-0 small" id="bonusSupplierCurrencyDisplay"></p>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="form-section-title"><?= __('bonus_details') ?></div>
                            
                            <div class="form-group mb-3">
                                <label for="bonusAmount"><?= __('amount') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light" id="bonusCurrencySymbol">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="bonusAmount" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="bonusReceipt"><?= __('receipt_number') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="bonusReceipt" name="receipt_number">
                                </div>
                            </div>
                            
                            <div class="form-group mb-0">
                                <label for="bonusRemarks"><?= __('remarks') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                    </div>
                                    <textarea class="form-control" id="bonusRemarks" name="remarks" rows="3" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                                </div>
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