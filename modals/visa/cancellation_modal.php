                                <!-- Add Cancellation Visa Modal -->
                                <div class="modal fade" id="cancelVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-x-circle mr-2"></i><?= __('cancel_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="feather icon-alert-triangle mr-2"></i>
                                                    <span><?= __('cancelling_a_visa_will_change_its_status_and_prevent_further_processing') ?></span>
                                                </div>
                                                
                                                <form id="cancelVisaForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                                                    <input type="hidden" id="cancelVisaId" name="visa_id">
                                                    <input type="hidden" id="currentStatus" name="current_status">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('applicant_name') ?>:</label>
                                                        <input type="text" class="form-control" id="cancelApplicantName" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('current_status') ?>:</label>
                                                        <input type="text" class="form-control" id="cancelCurrentStatus" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('new_status') ?>:</label>
                                                        <select class="form-control" id="cancelNewStatus" name="new_status" required>
                                                            <option value="Cancelled"><?= __('cancelled') ?></option>
                                                            <option value="Rejected"><?= __('rejected') ?></option>
                                                            <option value="Withdrawn"><?= __('withdrawn') ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="cancellationReason"><?= __('reason_for_cancellation') ?>:</label>
                                                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" rows="3"></textarea>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="confirmCancellation" required>
                                                            <label class="custom-control-label" for="confirmCancellation">
                                                                <?= __('i_confirm_that_i_want_to_cancel_this_visa_application') ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                <button type="button" class="btn btn-danger" id="processCancellationBtn" disabled><?= __('cancel_visa') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>