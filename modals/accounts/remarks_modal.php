<!-- Remarks Modal -->
<div class="modal fade modern-modal" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="remarksModalLabel">
                    <i class="feather icon-message-square mr-2"></i><?= __('enter_your_remarks') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-group mb-3">
                        <label for="user-remarks"><?= __('remarks') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-message-circle"></i></span>
                            </div>
                            <textarea id="user-remarks" class="form-control" rows="4" placeholder="<?= __('add_remarks_regarding_this_funding') ?>..."></textarea>
                        </div>
                    </div>
                    </div>

                    <div class="form-group mb-0">
                        <label for="modalReceiptNumber"><?= __('receipt_number') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                            </div>
                            <input type="text" class="form-control" id="modalReceiptNumber" name="receiptNumber" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" id="submit-remarks-btn">
                        <i class="feather icon-check-circle mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>