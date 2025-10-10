<div class="modal fade" id="familyCancellationLanguageModal" tabindex="-1" role="dialog" aria-labelledby="familyCancellationLanguageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyCancellationLanguageModalLabel">
                    <i class="feather icon-globe mr-2"></i><?= __('select_language_for_cancellation_form') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="en">
                            <i class="feather icon-flag mr-2"></i><?= __('english') ?>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="ps">
                            <i class="feather icon-flag mr-2"></i><?= __('pashto') ?>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="fa">
                            <i class="feather icon-flag mr-2"></i><?= __('dari') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>