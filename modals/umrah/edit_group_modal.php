<!-- Bootstrap Modal to Edit a Group -->
<div class="modal umrah-modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel"><?= __('edit_group') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editGroupForm" method="POST" onsubmit="return submitEditGroupForm();">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="group_id" id="editGroupId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editGroupNumber"><?= __('group_number') ?></label>
                                <input type="text" class="form-control" id="editGroupNumber" name="group_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editGroupName"><?= __('group_name') ?></label>
                                <input type="text" class="form-control" id="editGroupName" name="group_name" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('save_changes') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>