    <!-- Fine Letter Language Selection Modal -->
    <div class="modal fade" id="fineLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_fine_letter_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="fineLetterForm" onsubmit="generateFineLetter(event)">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title_fine" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="takhaluf"><?php echo __('takhaluf'); ?></label>
                    <input type="text" class="form-control" id="takhaluf_fine" placeholder="<?php echo __('takhaluf'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="fine_amount"><?php echo __('fine_amount'); ?></label>
                    <input type="text" class="form-control" id="fine_amount" placeholder="<?php echo __('fine_amount'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="currency"><?php echo __('currency'); ?></label>
                    <select class="form-control" id="currency">
                            <option value="AFS">AFS</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'ps')">
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