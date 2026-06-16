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

<!-- Withdraw Main Account Modal -->
<div class="modal fade modern-modal" id="withdrawMainModal" tabindex="-1" aria-labelledby="withdrawMainModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="withdrawMainModalLabel">
                    <i class="feather icon-arrow-down mr-2"></i><?= __('withdraw_from_main_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="withdrawMainForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">

                    <!-- Account Information -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-info"></i><?= __('account_information') ?></div>
                        <div class="row">
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label><?= __('account_name') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                        </div>
                                        <input type="text" id="withdrawMainAccountName" class="form-control" readonly>
                                        <input type="hidden" id="withdrawMainAccountId" name="main_account_id">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="withdrawMainCurrency"><?= __('currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <select id="withdrawMainCurrency" name="currency" class="form-control" required>
                                            <option value="USD"><?= __('usd') ?></option>
                                            <option value="AFS"><?= __('afs') ?></option>
                                            <option value="EUR"><?= __('eur') ?></option>
                                            <option value="DARHAM"><?= __('darham') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Amount & Receipt -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('transaction_details') ?></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawMainAmount"><?= __('amount_to_withdraw') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                        </div>
                                        <input type="number" id="withdrawMainAmount" name="amount" class="form-control" step="0.01" placeholder="<?= __('enter_amount') ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="withdrawMainReceipt"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                        </div>
                                        <input type="text" id="withdrawMainReceipt" name="receipt_number" class="form-control" placeholder="<?= __('enter_receipt_number') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-message-square"></i><?= __('remarks') ?></div>
                        <div class="form-group mb-0">
                            <textarea id="withdrawMainRemarks" name="remarks" class="form-control" rows="2" placeholder="<?= __('enter_remarks') ?>"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="withdrawMainForm" class="btn btn-danger" id="withdrawMainBtn">
                    <i class="feather icon-arrow-down mr-1"></i><?= __('withdraw') ?>
                </button>
            </div>
        </div>
    </div>
</div>
