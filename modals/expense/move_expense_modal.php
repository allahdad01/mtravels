<!-- Move Expense Modal -->
<div class="modal fade" id="moveExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-move"></i>
                    <?= __('move_expense') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="moveExpenseForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" id="moveExpenseId" name="expenseId">
                <div class="modal-body">
                    <!-- Current Info -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-info"></i>
                            <span><?= __('current_info') ?></span>
                        </div>
                        <div class="move-current-info">
                            <div class="move-info-row">
                                <span class="move-info-label"><?= __('description') ?>:</span>
                                <span class="move-info-value" id="moveExpenseDesc">-</span>
                            </div>
                            <div class="move-info-row">
                                <span class="move-info-label"><?= __('amount') ?>:</span>
                                <span class="move-info-value" id="moveExpenseAmount">-</span>
                            </div>
                            <div class="move-info-row">
                                <span class="move-info-label"><?= __('from_category') ?>:</span>
                                <span class="move-info-value move-info-from" id="moveExpenseFrom">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Target Category -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-arrow-right"></i>
                            <span><?= __('move_to_category') ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label"><?= __('category') ?> <span class="text-danger">*</span></label>
                                    <select class="form-control" id="moveExpenseCategory" name="newCategoryId" required>
                                        <option value=""><?= __('select_category') ?></option>
                                        <?php foreach($categories as $category): ?>
                                            <?php if (empty($category['parent_id'])): ?>
                                            <option value="<?php echo h($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label"><?= __('sub_category') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></label>
                                    <select class="form-control" id="moveExpenseSubCategory" name="newSubCategoryId">
                                        <option value=""><?= __('no_sub_category') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x"></i> <?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-move"></i> <?= __('move_expense') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
