<!-- Cancellation Details Modal -->
<div class="modal fade" id="cancellationDetailsModal" tabindex="-1" role="dialog" aria-labelledby="cancellationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancellationDetailsModalLabel">
                    <i class="feather icon-x-circle mr-2"></i><?= __('umrah_cancellation_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="cancellationDetailsForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="cancellationBookingId" name="booking_id">
                    
                    <div class="alert alert-warning">
                        <?= __('please_specify_the_cancellation_details_and_fees') ?>
                    </div>

                    <div class="form-group">
                        <label for="cancellationReason"><?= __('reason_for_cancellation') ?></label>
                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" 
                                  rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="cancellationConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="cancellationConfirmation">
                                <?= __('i_confirm_all_cancellation_details_are_correct') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-danger" id="generateCancellationFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_cancellation_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>