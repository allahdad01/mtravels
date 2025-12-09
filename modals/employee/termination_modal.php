    <!-- Termination Letter Language Selection Modal -->
    <div class="modal fade" id="terminationLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_termination_letter_language'); ?></h5>
                </div>
                <div class="modal-body">
                    <form id="terminationLetterForm" onsubmit="generateTerminationLetter(event)">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title_termination" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="termination_date"><?php echo __('termination_date'); ?></label>
                    <input type="date" class="form-control" id="termination_date" placeholder="<?php echo __('termination_date'); ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'ps')">
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