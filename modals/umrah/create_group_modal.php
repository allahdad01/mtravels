<!-- Bootstrap Modal to Create a New Group -->
<div class="modal umrah-modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createGroupModalLabel"><?= __('create_new_group') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createGroupForm" method="POST" onsubmit="return submitCreateGroupForm();">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="group_number"><?= __('group_number') ?></label>
                                <input type="text" class="form-control" id="group_number" name="group_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="group_name"><?= __('group_name') ?></label>
                                <input type="text" class="form-control" id="group_name" name="group_name" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('create_group') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>