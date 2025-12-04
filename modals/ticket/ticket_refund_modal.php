    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('refund') ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="refundForm">
                    <div class="modal-body">
                        <input type="hidden" id="refundTicketId" name="ticketId">
                        <input type="hidden" name="status" value="Refunded">

                        <div class="form-group">
                            <label for="refundSold"><?= __('sold_price') ?></label>
                            <input type="number" step="any" class="form-control" id="refundSold" name="sold" required>
                        </div>

                        <div class="form-group">
                            <label for="refundBase"><?= __('base_price') ?></label>
                            <input type="number" step="any" class="form-control" id="refundBase" name="base" required>
                        </div>

                        <div class="form-group">
                            <label><?= __('calculation_method') ?></label>
                            <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                <label class="btn btn-outline-primary active">
                                    <input type="radio" name="calculationMethod" id="calcFromBase" value="base" checked> Calculate from Base
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input type="radio" name="calculationMethod" id="calcFromSold" value="sold"> Calculate from Sold
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplierPenalty"><?= __('supplier_penalty') ?></label>
                            <input type="number" step="any" class="form-control" id="supplierRefundPenalty" name="supplier_penalty" required>
                            <small class="form-text text-muted">
                                <?= __('penalty_charged_by_the_supplier_deducted_from_the_base_price') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="servicePenalty"><?= __('our_service_penalty') ?></label>
                            <input type="number" step="any" class="form-control" id="serviceRefundPenalty" name="service_penalty" required>
                            <small class="form-text text-muted">
                                <?= __('penalty_charged_by_us_independent_of_supplier_penalties') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="refundAmount"><?= __('refund_amount') ?></label>
                            <input type="number" step="any" class="form-control" id="refundAmount" name="refund" readonly>
                            <small class="form-text text-muted">
                                <?= __('the_amount_the_passenger_will_be_refunded_calculated_automatically') ?>
                            </small>
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