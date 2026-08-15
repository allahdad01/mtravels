<!-- Expenses Modal (self-contained: add, edit, delete) -->
<div class="modal fade" id="expensesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('expenses_for_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">

                <!-- Allocation Details -->
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

                <!-- Add Expense Form (collapsible) -->
                <div class="card border mb-3" id="addExpenseSection" style="display:none;">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong style="font-size:13px;"><?= __('add_expense') ?></strong>
                        <button type="button" class="btn btn-sm btn-link" id="cancelAddExpense"><?= __('cancel') ?></button>
                    </div>
                    <div class="card-body">
                        <form id="inlineAddExpenseForm">
                            <input type="hidden" id="inlineAllocationId" name="allocation_id">
                            <input type="hidden" id="inlineCategoryId" name="category_id">
                            <input type="hidden" id="inlineCurrency" name="currency">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('sub_category') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></label>
                                        <select class="form-control form-control-sm" id="inlineExpenseSubCategory" name="sub_category_id">
                                            <option value=""><?= __('no_sub_category') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('date') ?></label>
                                        <input type="date" class="form-control form-control-sm" id="inlineExpenseDate" name="date" required>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('description') ?></label>
                                        <input type="text" class="form-control form-control-sm" id="inlineExpenseDescription" name="description" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('amount') ?></label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="inlineExpenseAmount" name="amount" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-success"><?= __('save') ?></button>
                        </form>
                    </div>
                </div>

                <!-- Edit Expense Form (collapsible) -->
                <div class="card border mb-3" id="editExpenseSection" style="display:none;">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong style="font-size:13px;"><?= __('edit_expense') ?></strong>
                        <button type="button" class="btn btn-sm btn-link" id="cancelEditExpense"><?= __('cancel') ?></button>
                    </div>
                    <div class="card-body">
                        <form id="inlineEditExpenseForm">
                            <input type="hidden" id="inlineEditExpenseId" name="expense_id">
                            <input type="hidden" id="inlineEditAllocationId" name="allocation_id">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('date') ?></label>
                                        <input type="date" class="form-control form-control-sm" id="inlineEditDate" name="date" required>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('description') ?></label>
                                        <input type="text" class="form-control form-control-sm" id="inlineEditDescription" name="description" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label style="font-size:12px;"><?= __('amount') ?></label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" id="inlineEditAmount" name="amount" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary"><?= __('update') ?></button>
                        </form>
                    </div>
                </div>

                <!-- Expenses Table -->
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
                                <!-- Expenses loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="no-expenses-message" class="text-center py-4" style="display:none;">
                    <i class="feather icon-inbox text-muted" style="font-size:36px;"></i>
                    <p class="mt-3 mb-0"><?= __('no_expenses_found_for_this_allocation') ?></p>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="showAddExpenseBtn"><?= __('add_expense') ?></button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>