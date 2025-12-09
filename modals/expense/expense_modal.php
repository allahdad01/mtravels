<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_expense') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="expenseForm" enctype="multipart/form-data">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="expenseId" name="expenseId">
                    <div class="form-group">
                        <label><?= __('category') ?></label>
                        <select class="form-control" id="expenseCategory" name="expenseCategory" required>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo h($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('date') ?></label>
                        <div class="row">
                            <div class="col-md-12">
                                <input type="date" class="form-control" id="expenseDate" name="expenseDate" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <input type="text" class="form-control" id="expenseDescription" name="expenseDescription" required>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="expenseAmount" name="expenseAmount" required>
                            </div>
                        </div>
                        <!-- Main Account -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('main_account') ?></label>
                                <select class="form-control" id="expenseMainAccount" name="expenseMainAccount" required>
                                    <option value=""><?= __('select_main_account') ?></option>
                                    <?php foreach ($internal as $int): ?>
                                    <option value="<?= $int['id'] ?>"><?= $int['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Currency -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="expenseCurrency" name="expenseCurrency" required>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label><?= __('budget_allocation') ?></label>
                                <select class="form-control" id="expenseAllocation" name="expenseAllocation">
                                    <option value=""><?= __('select_budget_allocation') ?></option>
                                    <?php 
                                    // Fetch available allocations with tenant and branch filtering
                                    $allocationsQuery = "
                                        SELECT ba.id, ba.remaining_amount, ba.currency,
                                               ec.name as category_name,
                                               ma.name as account_name
                                        FROM budget_allocations ba
                                        JOIN expense_categories ec ON ba.category_id = ec.id
                                        JOIN main_account ma ON ba.main_account_id = ma.id
                                        WHERE ba.tenant_id = ? AND ba.branch_id = ? AND ec.tenant_id = ? AND ec.branch_id = ? AND ma.tenant_id = ? AND ma.branch_id = ?
                                        ORDER BY ec.name, ba.allocation_date DESC
                                    ";
                                    $allocationsStmt = $pdo->prepare($allocationsQuery);
                                    $tenantId = $_SESSION['tenant_id'] ?? 1;
                                    $allocationsStmt->execute([$tenantId, $branch_id, $tenantId, $branch_id, $tenantId, $branch_id]);
                                    $allocations = $allocationsStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach($allocations as $allocation): 
                                    ?>
                                    <option value="<?= $allocation['id'] ?>" 
                                            data-currency="<?= $allocation['currency'] ?>" 
                                            data-category="<?= $allocation['category_name'] ?>"
                                            data-remaining="<?= $allocation['remaining_amount'] ?>">
                                        <?= htmlspecialchars($allocation['category_name']) ?> - 
                                        <?= number_format($allocation['remaining_amount'], 2) ?> <?= $allocation['currency'] ?> 
                                        (<?= htmlspecialchars($allocation['account_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><?= __('if_selected_expense_will_deduct_from_this_allocation_instead_of_the_main_account') ?></small>
                            </div>
                        </div>
                    </div>
                    <!-- Receipt Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('receipt_number') ?> (<?= __('optional') ?>)</label>
                                <input type="text" class="form-control" id="expenseReceiptNumber" name="expenseReceiptNumber">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('receipt_file') ?> (<?= __('optional') ?>)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="expenseReceiptFile" name="expenseReceiptFile">
                                    <label class="custom-file-label" for="expenseReceiptFile"><?= __('choose_file') ?></label>
                                </div>
                                <small class="form-text text-muted"><?= __('supported_formats') ?>: PDF, JPG, PNG</small>
                            </div>
                        </div>
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