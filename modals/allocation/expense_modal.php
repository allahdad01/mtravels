<!-- Expenses Modal -->
<div class="modal fade" id="expensesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('expenses_for_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="allocation-details mb-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 id="allocation-category" class="mb-2"></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="allocation-account"></span></p>
                                    <p class="mb-1"><strong><?= __('date') ?>:</strong> <span id="allocation-date"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="allocation-amount"></span></p>
                                    <p class="mb-1"><strong><?= __('remaining') ?>:</strong> <span id="allocation-remaining"></span></p>
                                </div>
                            </div>
                            <p class="mt-2 mb-0"><strong><?= __('description') ?>:</strong> <span id="allocation-description"></span></p>
                        </div>
                    </div>
                </div>
                <div class="expenses-list">
                    <h6 class="mb-3"><?= __('related_expenses') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('description') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="expenses-table-body">
                                <!-- Expenses will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="no-expenses-message" class="text-center py-4" style="display: none;">
                    <i class="feather icon-inbox text-muted" style="font-size: 36px;"></i>
                    <p class="mt-3 mb-0"><?= __('no_expenses_found_for_this_allocation') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="addExpenseBtn"><?= __('add_expense') ?></button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>