<!-- Edit Main Account Modal -->
<div class="modal fade modern-modal" id="editMainAccountModal" tabindex="-1" aria-labelledby="editMainAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editMainAccountModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_main_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMainAccountForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_account_id" name="account_id">
                    
                    <div class="mb-3">
                        <label for="edit_account_name" class="form-label"><?= __('account_name') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-briefcase"></i></span>
                    </div>
                            <input type="text" id="edit_account_name" name="account_name" class="form-control" placeholder="<?= __('enter_account_name') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_account_type" class="form-label"><?= __('account_type') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-tag"></i></span>
                            </div>
                        <select id="edit_account_type" name="account_type" class="form-control" required>
                            <option value="internal"><?= __('internal_account') ?></option>
                            <option value="bank"><?= __('bank_account') ?></option>
                        </select>
                    </div>
                    </div>
                    
                    <!-- Bank account specific fields - shown/hidden based on account type -->
                    <div id="edit_bankFields" style="display: none;">
                        <div class="mb-3">
                            <label for="edit_bank_account_number" class="form-label"><?= __('bank_account_usd_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="edit_bank_account_number" name="bank_account_number" class="form-control" placeholder="<?= __('enter_bank_account_number') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bank_account_afs_number" class="form-label"><?= __('bank_account_afs_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="bank_account_afs_number" name="bank_account_afs_number" class="form-control" placeholder="<?= __('enter_bank_account_afs_number') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label"><?= __('status') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-toggle-right"></i></span>
                            </div>
                                <select id="edit_status" name="status" class="form-control" required>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                    
                        <div class="alert alert-warning small mb-0">
                            <div class="d-flex">
                                <i class="feather icon-alert-circle mr-2 mt-1"></i>
                                <div>
                                    <strong><?= __('note') ?>:</strong> <?= __('editing_an_account_doesnt_affect_its_transaction_history') ?>. <?= __('this_will_only_update_the_account_information') ?>.
                                </div>
                            </div>
                        </div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveEditMainAccountBtn">
                        <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>