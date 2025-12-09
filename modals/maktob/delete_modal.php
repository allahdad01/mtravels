<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-alert-triangle text-danger mr-2"></i>
                    <?= __('confirm_delete') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('delete_confirmation') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <form id="deleteMaktobForm" method="POST" action="../../api/maktob/delete_maktob.php">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="delete_maktob_id" name="maktob_id">
                    <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>