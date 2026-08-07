<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit"></i>
                    <?= __('edit_expense') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editExpenseForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="editExpenseId" name="expenseId">
                    <input type="hidden" id="editExpenseCategory" name="expenseCategory">
                    <input type="hidden" id="editExpenseCurrency" name="expenseCurrency">
                    <input type="hidden" id="editExpenseMainAccount" name="expenseMainAccount">

                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-info"></i>
                            <span><?= __('edit_expense_details') ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('date') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-calendar"></i></span>
                                        </div>
                                        <input type="date" class="form-control" id="editExpenseDate" name="expenseDate" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="editExpenseAmount" name="expenseAmount" placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= __('description') ?></label>
                            <input type="text" class="form-control" id="editExpenseDescription" name="expenseDescription" placeholder="<?= __('enter_expense_description') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= __('sub_category') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></label>
                            <select class="form-control" id="editExpenseSubCategory" name="expenseSubCategory">
                                <option value=""><?= __('no_sub_category') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Receipt -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-file-text"></i>
                            <span><?= __('receipt_information') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('receipt_number') ?></label>
                                    <input type="text" class="form-control" id="editExpenseReceiptNumber" name="expenseReceiptNumber" placeholder="<?= __('enter_receipt_number') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('receipt_file') ?></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="editExpenseReceiptFile" name="expenseReceiptFile">
                                        <label class="custom-file-label" for="editExpenseReceiptFile"><?= __('choose_file') ?></label>
                                    </div>
                                    <small class="form-text text-muted"><?= __('supported_formats') ?>: PDF, JPG, PNG</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x"></i> <?= __('close') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check"></i> <?= __('save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
