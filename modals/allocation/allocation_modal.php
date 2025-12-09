<!-- Allocation Modal -->
<div class="modal fade" id="allocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('create_budget_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="allocationForm">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('expense_category') ?></label>
                        <select class="form-control" id="categoryId" name="categoryId" required>
                            <option value=""><?= __('select_category') ?></option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('main_account') ?></label>
                        <select class="form-control" id="mainAccountId" name="mainAccountId" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('allocation_date') ?></label>
                        <input type="date" class="form-control" id="allocationDate" name="allocationDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('create_allocation') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>