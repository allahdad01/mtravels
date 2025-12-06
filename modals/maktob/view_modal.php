<!-- View Maktob Modal -->
<div class="modal fade" id="viewMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>
                    <span id="maktobSubject"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="maktob-info mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?= __('letter_number') ?>:</strong> <span id="maktobNumber"></span></p>
                            <p><strong><?= __('company_name') ?>:</strong> <span id="maktobCompany"></span></p>
                            <p><strong><?= __('language') ?>:</strong> <span id="maktobLanguage"></span></p>
                        </div>
                        <div class="col-md-6 text-right">
                            <p><strong><?= __('date') ?>:</strong> <span id="maktobDate"></span></p>
                            <p><strong><?= __('status') ?>:</strong> <span id="maktobStatus"></span></p>
                            <p id="fileLinks"></p>
                        </div>
                    </div>
                    <hr>
                </div>
                <div class="maktob-content">
                    <p id="maktobContent"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
            </div>
        </div>
    </div>
</div>
