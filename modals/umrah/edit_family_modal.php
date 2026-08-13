<!-- Edit Family Modal -->
<div class="modal umrah-modal fade" id="editFamilyModal" tabindex="-1" aria-labelledby="editFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFamilyModalLabel"><?= __('edit_family_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editFamilyForm" method="POST" onsubmit="return submitEditFamilyForm();">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        <input type="hidden" id="editFamilyId" name="family_id">
                        <input type="hidden" id="editFamilyGroupId">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editHeadOfFamily"><?= __('family_head') ?></label>
                                <input type="text" class="form-control" id="editHeadOfFamily" name="head_of_family" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editContact"><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="editContact" inputmode="tel" name="contact" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editAddress"><?= __('address') ?></label>
                                <input type="text" class="form-control" id="editAddress" name="address" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editGroup"><?= __('group') ?></label>
                                <select class="form-control" id="editGroup" name="group_id" required>
                                    <option value=""><?= __('select_group') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editTazmin"><?= __('tazmin') ?></label>
                                <select class="form-control" id="editTazmin" name="tazmin" required>
                                    <option value="Done"><?= __('done') ?></option>
                                    <option value="Not Done"><?= __('not_done') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('save_changes') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>