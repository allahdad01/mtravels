<!-- Salary Payment Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="salaryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-dollar-sign mr-2"></i><?= __('record_salary') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="salaryForm">
                <input type="hidden" name="payment_type" value="regular">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('employee_information') ?></div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= __('employee') ?></label>
                                    <select class="form-control" name="user_id" required>
                                        <option value=""><?= __('select_employee') ?></option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-credit-card"></i><?= __('salary_details') ?></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('main_account') ?></label>
                                    <select class="form-control" name="main_account_id" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('payment_for_month') ?></label>
                                    <input type="month" class="form-control" name="payment_for_month" value="<?= date('Y-m') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="amount" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('months') ?></label>
                                    <input type="number" min="1" step="1" value="1" class="form-control" name="months_to_pay">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label><?= __('description') ?></label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('pay_salary') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
