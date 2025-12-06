<!-- Edit Message Modal -->
<div class="modal fade" id="editMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i>
                    <?= __("edit_message") ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMessageForm" method="POST" action="update_message.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_message_id" name="message_id">
                    <div class="form-group">
                        <label for="edit_subject"><?= __("subject") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-bookmark"></i></span>
                            </div>
                            <input type="text" class="form-control" id="edit_subject" name="subject" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_message"><?= __("message") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-message-circle"></i></span>
                            </div>
                            <textarea class="form-control" id="edit_message" name="message" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_recipient_type"><?= __("send_to") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-users"></i></span>
                            </div>
                            <select class="form-control" id="edit_recipient_type" name="recipient_type" required onchange="toggleEditRecipientSelect()">
                                <option value="clients"><?= __("all_clients") ?></option>
                                <option value="individual"><?= __("individual_client") ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="edit_recipient_select_group" style="display: none;">
                        <label for="edit_recipient_id"><?= __("select_recipient") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-user"></i></span>
                            </div>
                            <select class="form-control select2" id="edit_recipient_id" name="recipient_id">
                                <?php if (!empty($clients)): ?>
                                <optgroup label="Clients">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-1"></i> <?= __("save_changes") ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>