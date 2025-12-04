                                <!-- Add Re-apply Visa Modal -->
                                <div class="modal fade" id="reapplyVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('re_apply_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <span><?= __('re_applying_a_visa_will_restore_its_original_profit_and_reverse_cancellation_balance_changes') ?></span>
                                                </div>
                                                
                                                <form id="reapplyVisaForm">
                                                    <input type="hidden" id="reapplyVisaId" name="visa_id">
                                                    <input type="hidden" id="reapplyOriginalProfit" name="original_profit">
                                                    <input type="hidden" id="reapplyBaseAmount" name="base_amount">
                                                    <input type="hidden" id="reapplySoldAmount" name="sold_amount">
                                                    <input type="hidden" id="reapplyCurrency" name="currency">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('applicant_name') ?>:</label>
                                                        <input type="text" class="form-control" id="reapplyApplicantName" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('current_status') ?>:</label>
                                                        <input type="text" class="form-control" id="reapplyCurrentStatus" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('new_status') ?>:</label>
                                                        <select class="form-control" id="reapplyNewStatus" name="new_status" required>
                                                            <option value="Pending"><?= __('pending') ?></option>
                                                            <option value="Approved"><?= __('approved') ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="reapplyReason"><?= __('reason_for_re_application') ?>:</label>
                                                        <textarea class="form-control" id="reapplyReason" name="reapply_reason" rows="3" required></textarea>
                                                    </div>
                                                    
                                                    
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="confirmReapply" required>
                                                            <label class="custom-control-label" for="confirmReapply">
                                                                <?= __('i_confirm_that_i_want_to_re_apply_this_visa_application') ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                <button type="button" class="btn btn-success" id="processReapplyBtn" disabled><?= __('re_apply_visa') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>