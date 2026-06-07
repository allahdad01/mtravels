<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card"></i>
                    <?= __('add_expense') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="expenseForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <input type="hidden" id="expenseId" name="expenseId">

                    <!-- Basic Information -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-info"></i>
                            <span><?= __('basic_information') ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('category') ?></label>
                                    <select class="form-control" id="expenseCategory" name="expenseCategory" required>
                                        <?php foreach($categories as $category): ?>
                                            <option value="<?php echo h($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('date') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-calendar"></i></span>
                                        </div>
                                        <input type="date" class="form-control" id="expenseDate" name="expenseDate" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= __('description') ?></label>
                            <input type="text" class="form-control" id="expenseDescription" name="expenseDescription" placeholder="<?= __('enter_expense_description') ?>" required>
                        </div>
                    </div>

                    <!-- Financial Details -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-credit-card"></i>
                            <span><?= __('financial_details') ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="expenseAmount" name="expenseAmount" placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label class="form-label"><?= __('main_account') ?></label>
                                    <select class="form-control" id="expenseMainAccount" name="expenseMainAccount" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($internal as $int): ?>
                                        <option value="<?= $int['id'] ?>"><?= $int['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label"><?= __('currency') ?></label>
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
                    </div>

                    <!-- Budget Allocation -->
                    <div class="modal-section">
                        <div class="modal-section-title">
                            <i class="feather icon-pie-chart"></i>
                            <span><?= __('budget_allocation') ?> <span class="text-muted font-weight-normal">(<?= __('optional') ?>)</span></span>
                        </div>
                        <div class="form-group">
                            <select class="form-control" id="expenseAllocation" name="expenseAllocation">
                                <option value=""><?= __('select_budget_allocation') ?></option>
                                <?php
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
                            <small class="form-text text-muted">
                                <i class="feather icon-alert-circle mr-1"></i>
                                <?= __('if_selected_expense_will_deduct_from_this_allocation_instead_of_the_main_account') ?>
                            </small>
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
                                    <input type="text" class="form-control" id="expenseReceiptNumber" name="expenseReceiptNumber" placeholder="<?= __('enter_receipt_number') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><?= __('receipt_file') ?></label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="expenseReceiptFile" name="expenseReceiptFile">
                                        <label class="custom-file-label" for="expenseReceiptFile"><?= __('choose_file') ?></label>
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
