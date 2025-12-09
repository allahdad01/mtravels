<!-- Date Change Request Modal -->
<div class="modal fade" id="dateChangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="feather icon-calendar mr-2"></i><?= __('request_date_change') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="dateChangeForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="dateChangeBookingId" name="booking_id">

                    <!-- Current Details -->
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-info mr-2"></i><?= __('current_booking_details') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><?= __('passenger_name') ?>:</strong> <span id="currentPassengerName"></span></p>
                                    <p><strong><?= __('current_flight_date') ?>:</strong> <span id="currentFlightDate"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><?= __('current_return_date') ?>:</strong> <span id="currentReturnDate"></span></p>
                                    <p><strong><?= __('current_duration') ?>:</strong> <span id="currentDuration"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Date Details -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-edit mr-2"></i><?= __('new_date_details') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="newFlightDate"><?= __('new_flight_date') ?> *</label>
                                    <input type="date" class="form-control" id="newFlightDate" name="new_flight_date" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="newReturnDate"><?= __('new_return_date') ?> *</label>
                                    <input type="date" class="form-control" id="newReturnDate" name="new_return_date" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="newDuration"><?= __('new_duration') ?> *</label>
                                    <select class="form-control" id="newDuration" name="new_duration" required>
                                        <option value="5 Days">5 Days</option>
                                        <option value="6 Days">6 Days</option>
                                        <option value="7 Days">7 Days</option>
                                        <option value="8 Days">8 Days</option>
                                        <option value="9 Days">9 Days</option>
                                        <option value="10 Days">10 Days</option>
                                        <option value="11 Days">11 Days</option>
                                        <option value="12 Days">12 Days</option>
                                        <option value="13 Days">13 Days</option>
                                        <option value="14 Days">14 Days</option>
                                        <option value="15 Days">15 Days</option>
                                        <option value="16 Days">16 Days</option>
                                        <option value="17 Days">17 Days</option>
                                        <option value="18 Days">18 Days</option>
                                        <option value="19 Days">19 Days</option>
                                        <option value="20 Days">20 Days</option>
                                        <option value="21 Days">21 Days</option>
                                        <option value="22 Days">22 Days</option>
                                        <option value="23 Days">23 Days</option>
                                        <option value="24 Days">24 Days</option>
                                        <option value="25 Days">25 Days</option>
                                        <option value="26 Days">26 Days</option>
                                        <option value="27 Days">27 Days</option>
                                        <option value="28 Days">28 Days</option>
                                        <option value="29 Days">29 Days</option>
                                        <option value="30 Days">30 Days</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="newPrice"><?= __('new_sold_price') ?> (<?= __('optional') ?>)</label>
                                    <input type="number" class="form-control" id="newPrice" name="new_price" step="0.01" min="0">
                                    <small class="form-text text-muted"><?= __('leave_empty_if_no_price_change') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-group">
                        <label for="changeReason"><?= __('reason_for_change') ?> *</label>
                        <textarea class="form-control" id="changeReason" name="change_reason" rows="3" required
                                  placeholder="<?= __('please_provide_detailed_reason_for_date_change') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="additionalRemarks"><?= __('additional_remarks') ?></label>
                        <textarea class="form-control" id="additionalRemarks" name="additional_remarks" rows="2"
                                  placeholder="<?= __('any_additional_notes_or_special_requests') ?>"></textarea>
                    </div>

                    <!-- Confirmation -->
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="dateChangeConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="dateChangeConfirmation">
                                <strong><?= __('i_confirm_date_change_request_details_are_correct') ?></strong>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-info" id="submitDateChangeRequest">
                    <i class="feather icon-send mr-2"></i><?= __('submit_request') ?>
                </button>
            </div>
        </div>
    </div>
</div>