<!-- Edit Fund Transaction Modal -->
<div class="modal fade" id="editFundTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('edit_fund_transaction') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editFundTransactionForm">
                <input type="hidden" id="editFundTransactionId" name="transaction_id">
                <input type="hidden" id="editFundAllocationId" name="allocation_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editFundAmount" name="amount" required min="0.01">
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <textarea class="form-control" id="editFundDescription" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Fund Transactions Modal -->
<div class="modal fade" id="viewFundsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('fund_transactions_for_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="allocation-funds-details mb-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 id="funds-allocation-category" class="mb-2"></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="funds-allocation-account"></span></p>
                                    <p class="mb-1"><strong><?= __('date') ?>:</strong> <span id="funds-allocation-date"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="funds-allocation-amount"></span></p>
                                    <p class="mb-1"><strong><?= __('remaining') ?>:</strong> <span id="funds-allocation-remaining"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funds-list">
                    <h6 class="mb-3"><?= __('fund_transactions') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('description') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('type') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="funds-table-body">
                                <!-- Fund transactions will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="no-funds-message" class="text-center py-4" style="display: none;">
                    <i class="feather icon-inbox text-muted" style="font-size: 36px;"></i>
                    <p class="mt-3 mb-0"><?= __('no_fund_transactions_found_for_this_allocation') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>