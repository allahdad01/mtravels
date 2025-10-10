<!-- Family Language Selection Modal -->
<div class="modal fade" id="familyLanguageModal" tabindex="-1" role="dialog" aria-labelledby="familyLanguageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyLanguageModalLabel"><?= __('select_language') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('please_select_the_language_for_the_document') ?></p>
                <div class="d-flex justify-content-around">
                    <button type="button" class="btn btn-primary" onclick="generateDocumentWithLanguage('en')">English</button>
                    <button type="button" class="btn btn-info" onclick="generateDocumentWithLanguage('fa')">Dari (دری)</button>
                    <button type="button" class="btn btn-success" onclick="generateDocumentWithLanguage('ps')">Pashto (پښتو)</button>
                </div>
            </div>
        </div>
    </div>
</div>