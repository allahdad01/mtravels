<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_category') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="categoryForm">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="categoryId">
                    <div class="form-group">
                        <label><?= __('category_name') ?></label>
                        <input type="text" class="form-control" id="categoryName" name="categoryName" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('parent_category') ?></label>
                        <select class="form-control" id="categoryParent" name="categoryParent">
                            <option value=""><?= __('top_level_category') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?php echo h($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted"><?= __('leave_empty_for_top_level') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>