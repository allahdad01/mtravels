<!-- Refund Modal (mirrors umrah/visa refund flow) -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('process_refund') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="refundForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="refundBookingId" name="booking_id">
                    <input type="hidden" id="refundSold" name="sold">
                    <input type="hidden" id="refundBase" name="base">

                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= __('sold_price') ?>:</span>
                            <strong id="displaySoldPrice">-</strong>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="supplierRefundPenalty"><?= __('supplier_penalty') ?></label>
                        <input type="number" step="any" class="form-control" id="supplierRefundPenalty" name="supplier_penalty" required value="0">
                    </div>

                    <div class="form-group">
                        <label for="serviceRefundPenalty"><?= __('our_service_penalty') ?></label>
                        <input type="number" step="any" class="form-control" id="serviceRefundPenalty" name="service_penalty" required value="0">
                    </div>

                    <div class="form-group">
                        <label for="refundAmount"><?= __('refund_amount') ?></label>
                        <input type="number" step="any" class="form-control" id="refundAmount" name="refund" readonly>
                    </div>

                    <div class="form-group">
                        <label for="refundDescription"><?= __('description') ?></label>
                        <textarea class="form-control" id="refundDescription" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>