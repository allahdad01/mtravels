<!-- Edit Client Modal -->
<div class="modal fade" id="editClientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><?= __('edit_client') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editClientForm">
                <div class="modal-body">
                    <input type="hidden" id="editClientId">
                    <div class="mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('email') ?></label>
                        <input type="email" class="form-control" id="editEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" class="form-control" id="editPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" id="editAddress" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('client_type') ?></label>
                        <select class="form-control" id="editType" required>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-control" id="editStatus" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="inactive"><?= __('inactive') ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-save mr-2"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>