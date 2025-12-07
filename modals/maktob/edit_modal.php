<!-- Edit Maktob Modal -->
<div class="modal fade" id="editMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i>
                    <?= __('edit_letter') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMaktobForm" method="POST" action="../../api/maktob/update_maktob.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_maktob_id" name="maktob_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_maktob_number"><?= __('letter_number') ?></label>
                                <input type="text" class="form-control" id="edit_maktob_number" name="maktob_number" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_maktob_date"><?= __('letter_date') ?></label>
                                <input type="date" class="form-control" id="edit_maktob_date" name="maktob_date" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_company_name"><?= __('company_name') ?></label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_language"><?= __('language') ?></label>
                                <select class="form-control" id="edit_language" name="language" required>
                                    <option value="english"><?= __('english') ?></option>
                                    <option value="dari"><?= __('dari') ?></option>
                                    <option value="pashto"><?= __('pashto') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_subject"><?= __('subject') ?></label>
                                <input type="text" class="form-control" id="edit_subject" name="subject" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_content"><?= __('content') ?></label>
                                <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>