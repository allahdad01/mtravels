<!-- Bootstrap Modal to Create a New Family -->
<div class="modal umrah-modal fade" id="createFamilyModal" tabindex="-1" aria-labelledby="createFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createFamilyModalLabel"><?= __('create_new_family') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createFamilyForm" method="POST" onsubmit="return submitCreateFamilyForm();">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="head_of_family"><?= __('family_head') ?></label>
                                <input type="text" class="form-control" id="head_of_family" name="head_of_family" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="contact"><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="contact" inputmode="tel" name="contact" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="address"><?= __('address') ?></label>
                                <input type="text" class="form-control" id="address" name="address" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="group_id"><?= __('group') ?></label>
                                <select class="form-control" id="group_id" name="group_id" required>
                                    <option value=""><?= __('select_group') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tazmin"><?= __('tazmin') ?></label>
                                <select class="form-control" id="tazmin" name="tazmin" required>
                                    <option value="Done"><?= __('done') ?></option>
                                    <option value="Not Done"><?= __('not_done') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('create_family') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>