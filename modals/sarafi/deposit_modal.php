<!-- Sarafi Deposit Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="sarafiDepositModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-plus-circle mr-2"></i><?= __('new_deposit') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="sarafiDepositForm" enctype="multipart/form-data">
                <input type="hidden" name="add_deposit" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('customer_information') ?></div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label><?= __('customer') ?></label>
                                    <select class="form-control" name="customer_id" required>
                                        <option value=""><?= __('select_customer') ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('currency') ?></label>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                        <option value="SAR"><?= __('sar') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?= __('main_account') ?></label>
                                    <select class="form-control" name="main_account_id" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
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
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('details') ?></div>
                        <div class="form-group">
                            <label><?= __('reference_number') ?></label>
                            <input type="text" class="form-control" name="reference" value="<?= uniqid('DEP') ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __('notes') ?></label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label><?= __('receipt_optional') ?></label>
                            <div class="custom-file">
                                <input type="file" class="form-control" name="receipt" accept="image/*,.pdf">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
