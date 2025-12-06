
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="feather icon-alert-triangle mr-2"></i>
                    <?= __("confirm_delete") ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="feather icon-trash-2 text-danger" style="font-size: 4rem;"></i>
                </div>
                <p class="text-center lead"><?= __("are_you_sure_you_want_to_delete_this_message") ?></p>
                <p class="text-center text-muted"><?= __("this_action_cannot_be_undone") ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i> <?= __("cancel") ?>
                </button>
                <form id="deleteMessageForm" method="POST" action="delete_message.php">
                    <input type="hidden" id="delete_message_id" name="message_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="feather icon-trash-2 mr-1"></i> <?= __("delete") ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>