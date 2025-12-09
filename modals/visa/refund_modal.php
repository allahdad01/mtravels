                                <!-- Add Refund Visa Modal -->
                                <div class="modal fade" id="refundVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-refresh-cw mr-2"></i><?= __('refund_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <span><?= __('refunding_a_visa_will_create_a_refund_record_and_allow_processing_a_refund_transaction_to_the_customer') ?></span>
                                                </div>
                                                
                                                <form id="refundVisaForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <input type="hidden" id="refundVisaId" name="visa_id">
                                                    <input type="hidden" id="refundTotalAmount" name="total_amount">
                                                    <input type="hidden" id="refundProfitAmount" name="profit_amount">
                                                    <input type="hidden" id="refundCurrency" name="currency">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('visa_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="refundCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="text" class="form-control" id="refundVisaAmount" readonly>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('profit_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="refundProfitCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="text" class="form-control" id="refundVisaProfit" readonly>
                                                        </div>
                                                    </div>
                                                    
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('refund_type') ?>:</label>
                                                        <div class="custom-control custom-radio mb-2">
                                                            <input type="radio" id="fullRefund" name="refund_type" value="full" class="custom-control-input" checked>
                                                            <label class="custom-control-label" for="fullRefund"><?= __('full_refund') ?> (<?= __('sets_profit_to_0') ?>)</label>
                                                        </div>
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="partialRefund" name="refund_type" value="partial" class="custom-control-input">
                                                            <label class="custom-control-label" for="partialRefund"><?= __('partial_refund') ?></label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div id="partialRefundAmountGroup" class="form-group" style="display: none;">
                                                        <label for="partialRefundAmount"><?= __('refund_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="partialRefundCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="number" class="form-control" id="partialRefundAmount" name="refund_amount">
                                                        </div>
                                                        <small class="form-text text-muted"><?= __('enter_the_amount_to_refund_to_the_customer') ?></small>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="refundReason"><?= __('reason_for_refund') ?>:</label>
                                                        <textarea class="form-control" id="refundReason" name="refund_reason" rows="3" required></textarea>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                <button type="button" class="btn btn-warning" id="processRefundBtn"><?= __('process_refund') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>