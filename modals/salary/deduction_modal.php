<!-- Salary Deduction Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="salaryDeductionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-minus-circle mr-2"></i><?= __('record_deduction') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="salaryDeductionForm">
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
                                        <?php foreach ($users_with_salary as $employee): ?>
                                        <option value="<?= $employee['id'] ?>"><?= htmlspecialchars($employee['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-credit-card"></i><?= __('deduction_for') ?></div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('deduction_type') ?></label>
                                    <select class="form-control" name="type" required>
                                        <option value="absence"><?= __('absence') ?></option>
                                        <option value="penalty"><?= __('penalty') ?></option>
                                        <option value="tax"><?= __('tax') ?></option>
                                        <option value="other"><?= __('other') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('deduction_amount') ?></label>
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
                                    <label><?= __('deduction_date') ?></label>
                                    <input type="date" class="form-control" name="deduction_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label><?= __('description') ?></label>
                            <textarea class="form-control" name="description" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('save_deduction') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
