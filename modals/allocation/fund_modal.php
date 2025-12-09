<!-- Add Fund Modal -->
<div class="modal fade" id="fundAllocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_funds_to_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="fundAllocationForm">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                
                <input type="hidden" id="fundAllocationId" name="fundAllocationId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="feather icon-info mr-2"></i>
                        <?= __('adding_funds_will_increase_both_the_total_allocation_amount_and_the_remaining_amount') ?>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?= __('additional_funds') ?></label>
                                <input type="number" step="0.01" class="form-control" id="additionalAmount" name="additionalAmount" required min="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <input type="text" class="form-control" id="fundCurrency" name="fundCurrency" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('note') ?></label>
                        <textarea class="form-control" id="fundNote" name="fundNote" rows="2" placeholder="<?= __('reason_for_adding_funds_optional') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><?= __('add_funds') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>