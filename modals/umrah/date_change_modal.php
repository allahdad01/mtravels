<!-- Date Change Modal — single-step, fulfillment-style -->
<div class="modal fade" id="dateChangeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #eef2f7;">
                <h5 class="modal-title">
                    <i class="feather icon-calendar mr-2" style="color: #0e7490;"></i><?= __('request_date_change') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto; background: #f8fafc;">
                <form id="dateChangeForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="dateChangeBookingId" name="booking_id">

                    <!-- Passenger + Current Dates -->
                    <div class="card mb-3" style="border-left: 3px solid #0e7490;">
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center mb-1">
                                <strong id="currentPassengerName">-</strong>
                                <span class="fulfillment-chip ml-2"><?= __('current_booking_details') ?></span>
                            </div>
                            <div class="row small text-muted mt-1">
                                <div class="col-md-4">
                                    <span class="d-block"><?= __('current_flight_date') ?></span>
                                    <strong class="text-dark" id="currentFlightDate">-</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="d-block"><?= __('current_return_date') ?></span>
                                    <strong class="text-dark" id="currentReturnDate">-</strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="d-block"><?= __('current_duration') ?></span>
                                    <strong class="text-dark" id="currentDuration">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Dates -->
                    <div class="card mb-3" style="border-left: 3px solid #10b981;">
                        <div class="card-body">
                            <h6 class="mb-2" style="font-size: .9rem; font-weight: 600; color: #334155;">
                                <i class="feather icon-edit-2 mr-1" style="color: #10b981;"></i><?= __('new_date_details') ?>
                            </h6>
                            <div class="row">
                                <div class="form-group col-md-6 mb-0">
                                    <label class="small mb-1 text-muted"><?= __('new_flight_date') ?> *</label>
                                    <input type="date" class="form-control form-control-sm" id="newFlightDate" name="new_flight_date" required>
                                </div>
                                <div class="form-group col-md-6 mb-0">
                                    <label class="small mb-1 text-muted"><?= __('new_return_date') ?> *</label>
                                    <input type="date" class="form-control form-control-sm" id="newReturnDate" name="new_return_date" required>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <label class="small mb-1 text-muted"><?= __('new_duration') ?></label>
                                    <input type="text" class="form-control form-control-sm" id="newDuration" name="new_duration" readonly style="background:#eef2f7; color:#334155; font-weight:600;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Penalties -->
                    <div class="card mb-3" style="border-left: 3px solid #f59e0b;">
                        <div class="card-body">
                            <h6 class="mb-2" style="font-size: .9rem; font-weight: 600; color: #334155;">
                                <i class="feather icon-dollar-sign mr-1" style="color: #f59e0b;"></i><?= __('supplier_and_service_penalty_amounts') ?>
                            </h6>
                            <div class="row">
                                <div class="form-group col-md-6 mb-0">
                                    <label class="small mb-1 text-muted" for="supplierPenalty"><?= __('supplier_penalty') ?></label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="supplierPenalty" name="supplier_penalty" step="0.01" min="0" value="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text" id="penaltyCurrency">USD</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-6 mb-0">
                                    <label class="small mb-1 text-muted" for="servicePenalty"><?= __('service_penalty') ?></label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="servicePenalty" name="service_penalty" step="0.01" min="0" value="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text"><?= __('optional') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mt-3 p-2 rounded" style="background: #fffbeb; border: 1px solid #fde68a;">
                                <i class="feather icon-alert-circle mr-2" style="color: #f59e0b;"></i>
                                <span class="small text-muted mr-2"><?= __('total_penalty') ?>:</span>
                                <strong id="totalPenaltyDisplay" style="color: #b45309;">0.00 <span id="totalPenaltyCurrency">USD</span></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="form-group mb-0">
                        <label for="changeReason"><?= __('reason_for_change') ?> *</label>
                        <textarea class="form-control" id="changeReason" name="change_reason" rows="3" required
                                  placeholder="<?= __('please_provide_detailed_reason_for_date_change') ?>"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #eef2f7;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="submitDateChangeRequest">
                    <i class="feather icon-check-circle mr-2"></i><?= __('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>