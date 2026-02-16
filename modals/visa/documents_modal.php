<!-- Visa Documents Modal -->
<div class="modal fade" id="documentsModal" tabindex="-1" role="dialog" aria-labelledby="documentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="documentsModalLabel">
                    <i class="feather icon-file-text mr-2"></i>
                    <?= __('visa_documents') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="documentsTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="upload-tab" data-toggle="tab" href="#upload-panel" role="tab">
                                    <i class="feather icon-upload mr-2"></i><?= __('upload_documents') ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents-panel" role="tab">
                                    <i class="feather icon-list mr-2"></i><?= __('uploaded_documents') ?>
                                    <span class="badge badge-primary ml-2" id="docCount">0</span>
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="documentsTabContent">
                            <!-- Upload Tab -->
                            <div class="tab-pane fade show active" id="upload-panel" role="tabpanel">
                                <form id="visaDocumentForm" enctype="multipart/form-data">
                                    <input type="hidden" id="visaIdInput" name="visa_id">
                                    
                                    <div class="form-group">
                                        <label for="docType" class="form-label">
                                            <?= __('document_type') ?>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <select class="form-control selectpicker" id="docType" name="doc_type" required data-live-search="true">
                                                <option value="">-- <?= __('select_or_type_custom') ?> --</option>
                                            </select>
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="toggleCustomDoc" title="<?= __('enter_custom_document_type') ?>">
                                                    <i class="feather icon-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">
                                            <?= __('select_known_type_or_enter_custom') ?>
                                        </small>
                                    </div>

                                    <!-- Custom Document Type Input (Hidden by default) -->
                                    <div class="form-group" id="customDocTypeGroup" style="display: none;">
                                        <label for="customDocType" class="form-label">
                                            <?= __('custom_document_name') ?>
                                        </label>
                                        <input type="text" class="form-control" id="customDocType" name="custom_doc_type" placeholder="<?= __('e_g_bank_statement_marriage_certificate') ?>">
                                        <small class="form-text text-muted">
                                            <?= __('enter_any_document_name') ?>
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-check">
                                            <input type="checkbox" class="form-check-input" id="isRequired" name="is_required">
                                            <span class="form-check-label">
                                                <?= __('mark_as_required') ?>
                                            </span>
                                        </label>
                                        <small class="form-text text-muted">
                                            <?= __('indicate_if_this_is_mandatory_document') ?>
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="fileUpload" class="form-label">
                                            <?= __('select_files') ?>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <!-- Hidden file input -->
                                        <input type="file" id="fileUpload" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="display: none !important; visibility: hidden;">
                                        
                                        <!-- Upload area (separate from file input) -->
                                        <div class="upload-area card border-2 border-dashed p-4 text-center" id="uploadArea" style="cursor: pointer; transition: all 0.3s;" role="button" tabindex="0">
                                            <i class="feather icon-upload-cloud display-4 text-muted mb-2"></i>
                                            <p class="mb-0 font-weight-bold"><?= __('drag_and_drop_files_here') ?></p>
                                            <p class="text-muted small"><?= __('or_click_to_browse') ?></p>
                                        </div>
                                        <small class="form-text text-muted d-block mt-2">
                                            <?= __('allowed_formats_pdf_images_office') ?> | <?= __('max_size') ?>: 10MB
                                        </small>
                                    </div>

                                    <!-- File Preview -->
                                    <div id="filePreview" class="mt-4" style="max-height: 300px; overflow-y: auto;"></div>

                                    <div class="form-group">
                                        <label for="remarks" class="form-label"><?= __('remarks') ?></label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="<?= __('add_any_remarks') ?>"></textarea>
                                    </div>

                                    <div class="alert alert-info" role="alert">
                                        <i class="feather icon-info mr-2"></i>
                                        <strong><?= __('note') ?>:</strong> <?= __('you_can_upload_multiple_files_at_once') ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Documents List Tab -->
                            <div class="tab-pane fade" id="documents-panel" role="tabpanel">
                                <div class="row">
                                    <!-- Documents List -->
                                    <div class="col-md-6">
                                        <div id="documentsList" class="list-group">
                                            <div class="text-center text-muted py-5">
                                                <i class="feather icon-inbox display-4 mb-3"></i>
                                                <p><?= __('no_documents_uploaded_yet') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Document Preview -->
                                    <div class="col-md-6">
                                        <div id="documentPreviewContainer" class="border rounded p-3" style="background: #f9fafb; min-height: 400px;">
                                            <div class="text-center text-muted py-5">
                                                <i class="feather icon-eye display-4 mb-3"></i>
                                                <p><?= __('select_document_to_preview') ?? 'Select a document to preview' ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= __('close') ?>
                </button>
                <button type="button" class="btn btn-primary" id="uploadSubmitBtn">
                    <i class="feather icon-upload mr-2"></i><?= __('upload_documents') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .upload-area {
        border-color: #e5e7eb !important;
        background-color: #f9fafb;
    }

    .upload-area:hover {
        border-color: #4099ff !important;
        background-color: #f0f7ff;
    }

    .upload-area.dragover {
        border-color: #2ed8b6 !important;
        background-color: #e0f7f4;
    }

    .file-preview-item {
        display: flex;
        align-items: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 8px;
        border-left: 4px solid #4099ff;
    }

    .file-preview-item.error {
        border-left-color: #ef4444;
        background: #fef2f2;
    }

    .file-preview-item.success {
        border-left-color: #10b981;
        background: #f0fdf4;
    }

    .document-item {
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        margin-bottom: 8px;
        transition: all 0.2s;
    }

    .document-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .document-item.required::before {
        content: "● ";
        color: #ef4444;
        font-weight: bold;
    }

    .document-status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-approved {
        background: #dcfce7;
        color: #166534;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .border-dashed {
        border-style: dashed !important;
    }

    #documentPreviewContainer {
        position: relative;
        overflow: auto;
    }

    .document-preview-wrapper {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
    }

    .document-preview-image {
        max-width: 100%;
        max-height: 350px;
        border-radius: 4px;
    }

    .document-preview-pdf {
        width: 100%;
        height: 400px;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
    }

    .document-preview-info {
        background: #f0f7ff;
        border-left: 4px solid #4099ff;
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 12px;
        font-size: 13px;
    }

    .document-preview-info strong {
        display: block;
        margin-bottom: 4px;
        color: #1e293b;
    }

    .document-preview-info small {
        color: #64748b;
        display: block;
        line-height: 1.5;
    }

    .document-item.preview-active {
        background: #eff6ff;
        border-color: #4099ff;
        box-shadow: 0 0 8px rgba(64, 153, 255, 0.2);
    }

    .document-list-group .document-item {
        cursor: pointer;
        user-select: none;
    }
</style>
