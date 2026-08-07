<!-- Sarafi Hawala Modal (standalone, embedded by Payments Journal) -->
<div class="modal fade" id="sarafiHawalaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-send mr-2"></i><?= __('new_hawala_transfer') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="sarafiHawalaForm">
                <input type="hidden" name="add_hawala" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                <div class="modal-body">
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-user"></i><?= __('sender_information') ?></div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label><?= __('sender') ?></label>
                                    <select class="form-control" name="sender_id" required>
                                        <option value=""><?= __('select_sender') ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?= __('send_currency') ?></label>
                                    <select class="form-control" name="send_currency" required>
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
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?= __('amount_to_send') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" name="send_amount" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?= __('secret_code') ?></label>
                                    <input type="text" class="form-control" name="secret_code" required>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block"><?= __('this_code_will_be_used_by_the_receiver_to_claim_the_transfer') ?></small>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-percent"></i><?= __('commission') ?></div>
                        <div class="form-group mb-0">
                            <label><?= __('commission_amount') ?> <small class="text-muted">(<?= __('in_same_currency_as_send') ?>)</small></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                </div>
                                <input type="number" step="0.01" class="form-control" id="hawalaCommissionAmount" name="commission_amount" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-section" style="background:#e8f5e9;border-color:#c8e6c9;">
                        <div class="modal-section-title" style="color:#2e7d32;"><i class="feather icon-info"></i><?= __('summary') ?></div>
                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:14px;">
                            <span><?= __('customer_pays') ?></span>
                            <span id="hawalaBreakdownSend" class="font-weight-bold">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:14px;color:#e65100;">
                            <span><?= __('commission_deducted') ?></span>
                            <span id="hawalaBreakdownCommission" class="font-weight-bold">− 0.00</span>
                        </div>
                        <hr style="margin:6px 0;border-color:#c8e6c9;">
                        <div class="d-flex justify-content-between align-items-center" style="font-size:15px;">
                            <span class="font-weight-bold"><?= __('receiver_gets') ?></span>
                            <span id="hawalaBreakdownNet" class="font-weight-bold" style="color:#2e7d32;font-size:16px;">0.00</span>
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-title"><i class="feather icon-file-text"></i><?= __('notes') ?></div>
                        <div class="form-group mb-0">
                            <textarea class="form-control" name="notes" rows="2" placeholder="<?= __('optional_notes') ?>"></textarea>
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
