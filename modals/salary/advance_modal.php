<!-- Salary Advance Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="salaryAdvanceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-plus-circle mr-2"></i><?= __('record_advance') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="salaryAdvanceForm">
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
                                    <label><?= __('advance_amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="amount" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('description') ?></label>
                                    <input type="text" class="form-control" name="description" placeholder="Optional note.">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('give_advance') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
