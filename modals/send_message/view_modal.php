<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-message-circle mr-2"></i>
                    <span id="messageSubject"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="message-info mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="feather icon-user mr-1"></i> <?= __("from") ?>:</strong> <span id="messageSender"></span></p>
                            <p><strong><i class="feather icon-users mr-1"></i> <?= __("to") ?>:</strong> <span id="messageRecipient"></span></p>
                        </div>
                        <div class="col-md-6 text-right">
                            <p><strong><i class="feather icon-calendar mr-1"></i> <?= __("date") ?>:</strong> <span id="messageDate"></span></p>
                            <p><strong><i class="feather icon-flag mr-1"></i> <?= __("status") ?>:</strong> <span id="messageStatus"></span></p>
                        </div>
                    </div>
                    <hr>
                </div>
                <div class="message-content">
                    <p id="messageBody"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("close") ?></button>
                <button type="button" class="btn btn-primary reply-message">
                    <i class="feather icon-corner-up-left mr-1"></i> <?= __("reply") ?>
                </button>
            </div>
        </div>
    </div>
</div>
