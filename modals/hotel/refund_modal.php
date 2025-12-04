<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="refundForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="refund_booking_id" name="booking_id">
                    <input type="hidden" id="refund_original_amount" name="original_amount">
                    <input type="hidden" id="refund_original_profit" name="original_profit">
                    <input type="hidden" id="refund_currency" name="currency">
                    
                    <!-- Booking Summary Card -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted"><?= __('original_amount') ?></span>
                                <strong id="displayOriginalAmount" class="text-primary">-</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><?= __('original_profit') ?></span>
                                <strong id="displayOriginalProfit" class="text-success">-</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Refund Type -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="feather icon-tag mr-1"></i><?= __('refund_type') ?>
                        </label>
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-primary active">
                                <input type="radio" name="refund_type" value="full" checked 
                                       onchange="toggleRefundAmount()"> <?= __('full_refund') ?>
                            </label>
                            <label class="btn btn-outline-primary">
                                <input type="radio" name="refund_type" value="partial" 
                                       onchange="toggleRefundAmount()"> <?= __('partial_refund') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Refund Amount (Hidden by default) -->
                    <div class="form-group" id="refundAmountGroup" style="display: none;">
                        <label class="form-label">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('refund_amount') ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="refundCurrencySymbol">$</span>
                            </div>
                            <input type="number" class="form-control" id="refund_amount" name="refund_amount" 
                                   step="0.01" min="0.01">
                            <div class="invalid-feedback">
                                <?= __('please_enter_valid_refund_amount') ?>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            <?= __('maximum_refund_amount') ?>: <span id="maxRefundAmount">-</span>
                        </small>
                    </div>

                    <!-- Reason -->
                    <div class="form-group mb-0">
                        <label class="form-label">
                            <i class="feather icon-file-text mr-1"></i><?= __('reason_for_refund') ?>
                        </label>
                        <textarea class="form-control" id="refund_reason" name="reason" 
                                  rows="3" required placeholder="<?= __('enter_refund_reason') ?>"></textarea>
                        <div class="invalid-feedback">
                            <?= __('please_enter_refund_reason') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('process_refund') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>