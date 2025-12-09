<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="refundForm" onsubmit="return false;">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="refund_booking_id" name="booking_id">
                    <input type="hidden" id="refund_original_amount" name="original_amount">
                    <input type="hidden" id="refund_original_profit" name="original_profit">
                    <input type="hidden" id="refund_currency" name="currency">
                    
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= __('original_amount') ?>:</span>
                            <strong id="displayOriginalAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= __('original_profit') ?>:</span>
                            <strong id="displayOriginalProfit">-</strong>
                        </div>
                        
                    </div>

                    <div class="form-group">
                        <label for="refund_type"><?=__('refund_type')?></label>
                        <select class="form-control" id="refund_type" name="refund_type" required onchange="toggleRefundAmount()">
                            <option value=""><?=__('select_refund_type')?></option>
                            <option value="full"><?=__('full_refund')?></option>
                            <option value="partial"><?=__('partial_refund')?></option>
                        </select>
                    </div>

                    <div class="form-group" id="refundAmountGroup" style="display: none;">
                        <label for="refund_amount"><?= __('refund_amount') ?></label>
                        <input type="number" class="form-control" id="refund_amount" name="refund_amount">
                    </div>

                    <div class="form-group">
                        <label for="refund_reason"><?= __('reason_for_refund') ?></label>
                        <textarea class="form-control" id="refund_reason" name="reason" 
                                  rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="processRefundBtn" onclick="console.log('Button clicked directly');">
                        <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
