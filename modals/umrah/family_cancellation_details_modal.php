<!-- Family Cancellation Details Modal -->
<div class="modal fade" id="familyCancellationDetailsModal" tabindex="-1" role="dialog" aria-labelledby="familyCancellationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="familyCancellationDetailsModalLabel">
                    <i class="feather icon-x-circle mr-2"></i><?= __('family_cancellation_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="familyCancellationDetailsForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="familyCancellationFamilyId" name="family_id">
                    <input type="hidden" id="familyCancellationBookingId" name="booking_id">
                    
                    <div class="alert alert-warning">
                        <i class="feather icon-alert-triangle mr-2"></i>
                        <?= __('please_specify_the_cancellation_details_for_all_family_members') ?>
                    </div>

                    <!-- Family Summary -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="feather icon-users mr-2"></i><?= __('family_information') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong><?= __('family_name') ?>:</strong> 
                                    <span id="familyNameDisplay"></span>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= __('total_members') ?>:</strong> 
                                    <span id="totalMembersDisplay"></span>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= __('package_type') ?>:</strong> 
                                    <span id="packageTypeDisplay"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Return Section for Each Family Member -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="feather icon-file-text mr-2"></i><?= __('document_return_by_member') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="familyMembersDocuments">
                                <!-- Family member document sections will be populated here -->
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="familyCancellationReason"><?= __('reason_for_cancellation') ?> *</label>
                        <textarea class="form-control" id="familyCancellationReason" name="cancellation_reason" 
                                  rows="4" required placeholder="<?= __('please_provide_detailed_reason_for_family_cancellation') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="familyCancellationConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="familyCancellationConfirmation">
                                <strong><?= __('i_confirm_all_family_cancellation_details_are_correct') ?></strong>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-danger" id="familyGenerateCancellationFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_family_cancellation_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>