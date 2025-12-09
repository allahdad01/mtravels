    <!-- Language Selection Modal -->
    <div class="modal fade" id="languageSelectionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_agreement_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                <div>
                    <form id="agreementForm" onsubmit="generateAgreement(event)">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="form-group">
                            <label for="rule"><?php echo __('rule'); ?></label>
                            <textarea type="text" class="form-control" id="rule" placeholder="<?php echo __('rule'); ?>"></textarea>
                        </div>
                    </form>
                </div>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('en')">
                            <i class="feather icon-globe mr-2"></i> English
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>