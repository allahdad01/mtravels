<!-- Cancellation/Re-Apply Modal -->
<div class="modal fade" id="cancellationReapplyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalTitle">
                    <i class="feather icon-settings mr-2"></i>Manage Booking Status
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="cancellationReapplyForm" onsubmit="return false;">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="cr_booking_id" name="booking_id">
                    <input type="hidden" id="cr_action" name="action">
                    <input type="hidden" id="cr_base_price" name="base_price">
                    <input type="hidden" id="cr_sold_price" name="sold_price">
                    <input type="hidden" id="cr_current_profit" name="current_profit">
                    
                    <!-- Action Selection -->
                    <div class="form-group">
                        <label><?= __('select_action') ?></label>
                        <div class="row">
                            <div class="col-md-6" id="cancelActionCard">
                                <div class="card border-warning">
                                    <div class="card-body text-center p-3">
                                        <i class="feather icon-x-circle text-warning mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title"><?= __('cancel_booking') ?></h6>
                                        <p class="card-text small text-muted"><?= __('cancel_booking_desc') ?></p>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="selectAction('cancel');">
                                            <?= __('select') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" id="reapplyActionCard">
                                <div class="card border-success">
                                    <div class="card-body text-center p-3">
                                        <i class="feather icon-refresh-cw text-success mb-2" style="font-size: 2rem;"></i>
                                        <h6 class="card-title"><?= __('reapply_booking') ?></h6>
                                        <p class="card-text small text-muted"><?= __('reapply_booking_desc') ?></p>
                                        <button type="button" class="btn btn-success btn-sm" onclick="selectAction('reapply')">
                                            <?= __('select') ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Action Display -->
                    <div id="selectedActionDisplay" class="alert alert-info" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><?= __('selected_action') ?>:</strong></span>
                            <span id="actionText" class="badge-info">-</span>
                        </div>
                    </div>

                    <!-- Current Values Display -->
                    <div class="alert alert-light border">
                        <h6 class="alert-heading"><?= __('current_values') ?></h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= __('base_price') ?>:</span>
                                    <strong id="displayBasePrice">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= __('sold_price') ?>:</span>
                                    <strong id="displaySoldPrice">-</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= __('current_profit') ?>:</span>
                                    <strong id="displayCurrentProfit">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= __('new_profit') ?>:</span>
                                    <strong id="displayNewProfit">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cr_reason"><?= __('reason') ?></label>
                        <textarea class="form-control" id="cr_reason" name="reason" 
                                  rows="3" placeholder="<?= __('enter_reason') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="processCancellationReapplyBtn">
                        <i class="feather icon-check mr-2"></i><?= __('confirm_action') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>