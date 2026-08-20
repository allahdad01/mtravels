/**
 * Visa Document Manager
 * Handles document uploads, deletions, and management
 */

class VisaDocumentManager {
    constructor() {
        this.currentVisaId = null;
        this.selectedFiles = [];
        this.documentTypes = [];
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDocumentTypeSelect();
    }

    setupEventListeners() {
        // Open modal
        $(document).on('click', '[data-action="upload-docs"]', (e) => {
            const visaId = $(e.currentTarget).data('visa-id');
            this.openModal(visaId);
        });

        // Upload area events
        const uploadArea = $('#uploadArea');
        const fileInput = $('#fileUpload');

        // Click to open file browser - use preventDefault to avoid recursion
        uploadArea.on('click', function (e) {
            if ($(e.target).closest('#fileUpload').length === 0) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.click();
            }
        });

        // Keyboard support for accessibility
        uploadArea.on('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                fileInput.click();
            }
        });

        uploadArea.on('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.addClass('dragover');
        });

        uploadArea.on('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.removeClass('dragover');
        });

        uploadArea.on('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            this.handleFiles(files);
        });

        // File input change
        fileInput.on('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                this.handleFiles(e.target.files);
            }
        });

        // Custom document type toggle
        $('#toggleCustomDoc').on('click', () => {
            const group = $('#customDocTypeGroup');
            group.toggle();
            if (group.is(':visible')) {
                $('#customDocType').focus();
            }
        });

        // Submit upload
        $('#uploadSubmitBtn').on('click', () => this.submitUpload());

        // Modal events
        $('#documentsModal').on('show.bs.modal', () => {
            this.loadDocuments();
        });
    }

    setupDocumentTypeSelect() {
        const select = $('#docType');

        // Fetch document types
        $.ajax({
            url: '../api/visa_document_types.php',
            type: 'GET',
            dataType: 'json',
            success: (response) => {
                if (response.success && response.types) {
                    this.documentTypes = response.types;
                    this.populateDocTypeSelect(response.types);
                    // Reinitialize bootstrap-select
                    select.selectpicker('refresh');
                }
            },
            error: (err) => {
                console.error('Failed to load document types:', err);
            }
        });
    }

    populateDocTypeSelect(types) {
        const select = $('#docType');
        select.find('option:not(:first)').remove();

        types.forEach(type => {
            const label = type.name + (type.is_required ? ' *' : '');
            select.append($('<option>').val(type.name).text(label));
        });
    }

    handleFiles(files) {
        this.selectedFiles = Array.from(files);
        this.displayFilePreview();
    }

    displayFilePreview() {
        const preview = $('#filePreview');
        preview.empty();

        if (this.selectedFiles.length === 0) {
            return;
        }

        const container = $('<div class="mt-3"></div>');

        this.selectedFiles.forEach((file, index) => {
            const validation = this.validateFile(file);
            const className = validation.valid ? 'success' : 'error';
            const icon = this.getFileIcon(file.type);

            const item = $(`
                <div class="file-preview-item ${className}" style="display: flex; align-items: center;">
                    <div style="font-size: 24px; margin-right: 12px; min-width: 30px;">
                        <i class="${icon}"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold;">${this.escapeHtml(file.name)}</div>
                        <small style="color: #999;">${this.formatFileSize(file.size)}</small>
                        ${validation.valid ? '' : '<div style="color: #dc3545; font-size: 12px;">' + validation.message + '</div>'}
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-danger" data-file-index="${index}" style="margin-left: 10px;">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </div>
            `);

            container.append(item);
        });

        preview.append(container);

        // Bind remove button handlers
        preview.find('.btn-link').on('click', (e) => {
            const fileIndex = $(e.currentTarget).data('file-index');
            this.removeFile(fileIndex);
        });
    }

    validateFile(file) {
        const allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpg'
        ];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (!allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: 'File type not allowed'
            };
        }

        if (file.size > maxSize) {
            return {
                valid: false,
                message: 'File exceeds 10MB limit'
            };
        }

        return { valid: true };
    }

    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.displayFilePreview();
    }

    getFileIcon(mimeType) {
        const iconMap = {
            'application/pdf': 'feather icon-file-pdf text-danger',
            'image/jpeg': 'feather icon-image text-primary',
            'image/png': 'feather icon-image text-primary',
            'image/jpg': 'feather icon-image text-primary',
            'application/msword': 'feather icon-file-text text-primary',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'feather icon-file-text text-primary',
            'application/vnd.ms-excel': 'feather icon-file text-success',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'feather icon-file text-success'
        };
        return iconMap[mimeType] || 'feather icon-file text-muted';
    }

    formatFileSize(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return size.toFixed(2) + ' ' + units[unitIndex];
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    submitUpload() {
        const docType = $('#docType').val();
        const customDocType = $('#customDocType').val();
        const isRequired = $('#isRequired').is(':checked');

        // Validate
        const finalDocType = customDocType.trim() || docType;
        if (!finalDocType) {
            showToast('Please select or enter a document type', 'warning');
            return;
        }

        if (this.selectedFiles.length === 0) {
            showToast('Please select at least one file', 'warning');
            return;
        }

        // Create FormData
        const formData = new FormData();
        formData.append('visa_id', this.currentVisaId);
        formData.append('doc_type', docType);
        formData.append('custom_doc_type', customDocType);
        formData.append('is_required', isRequired ? 1 : 0);

        this.selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });

        // Show loading state
        const btn = $('#uploadSubmitBtn');
        btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm mr-2"></i>Uploading...');

        // Upload
        $.ajax({
            url: '../api/visa_documents_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    showToast(response.message, 'success');
                    this.resetForm();
                    this.loadDocuments();

                    // Switch to documents tab
                    setTimeout(() => {
                        $('#documents-tab').tab('show');
                    }, 500);
                } else {
                    showToast(response.message || 'Upload failed', 'error');
                }

                if (response.errors && response.errors.length > 0) {
                    const errorMsg = response.errors.map(e => `${e.file}: ${e.error}`).join('\n');
                    showToast('Some files failed: ' + errorMsg, 'warning');
                }
            },
            error: (err) => {
                console.error('Upload error:', err);
                showToast('Upload failed. Please try again.', 'error');
            },
            complete: () => {
                btn.prop('disabled', false).html('<i class="feather icon-upload mr-2"></i>Upload Documents');
            }
        });
    }

    resetForm() {
        $('#visaDocumentForm')[0].reset();
        $('#fileUpload').val('');
        $('#customDocTypeGroup').hide();
        this.selectedFiles = [];
        this.displayFilePreview();
    }

    openModal(visaId) {
        this.currentVisaId = visaId;
        this.resetForm();
        $('#visaIdInput').val(visaId);

        // Ensure modal exists and show it
        const modal = $('#documentsModal');
        if (modal.length) {
            modal.modal('show');
            // loadDocuments is triggered by show.bs.modal event
        } else {
            console.error('Documents modal not found');
            return;
        }
    }

    loadDocuments() {
        if (!this.currentVisaId) return;

        const listContainer = $('#documentsList');
        listContainer.html('<div class="text-center"><i class="spinner-border"></i></div>');

        $.ajax({
            url: '../api/visa_documents_upload.php',
            type: 'GET',
            data: { visa_id: this.currentVisaId },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    this.displayDocuments(response.documents);
                }
            },
            error: (err) => {
                console.error('Failed to load documents:', err);
                listContainer.html('<div class="alert alert-danger">Failed to load documents</div>');
            }
        });
    }

    displayDocuments(documents) {
        const listContainer = $('#documentsList');
        $('#docCount').text(documents.length);

        if (documents.length === 0) {
            listContainer.html(`
                <div class="text-center text-muted py-5">
                    <i class="feather icon-inbox display-4 mb-3"></i>
                    <p>No documents uploaded yet</p>
                </div>
            `);
            return;
        }

        const list = $('<div class="document-list-group"></div>');

        documents.forEach(doc => {
            const statusBadge = this.getStatusBadge(doc.status);
            const requiredClass = doc.is_required ? 'required' : '';
            const icon = this.getFileIcon(doc.mime_type);

            const item = $(`
                <div class="document-item ${requiredClass}" data-doc-id="${doc.id}" data-doc-path="../${doc.file_path}" data-doc-type="${doc.mime_type}" data-doc-name="${this.escapeHtml(doc.original_filename)}" data-doc-size="${doc.file_size}" data-doc-uploaded="${doc.uploaded_at}" data-doc-uploaded-by="${doc.uploaded_by_name || ''}">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <i class="${icon}" style="font-size: 24px;"></i>
                        </div>
                        <div class="col">
                            <div class="font-weight-bold">${this.escapeHtml(doc.doc_type)}</div>
                            <small class="text-muted">
                                ${this.escapeHtml(doc.original_filename)} • ${this.formatFileSize(doc.file_size)}
                            </small>
                            <div class="small text-muted mt-1">
                                Uploaded: ${new Date(doc.uploaded_at).toLocaleDateString()}
                                ${doc.uploaded_by_name ? ' by ' + doc.uploaded_by_name : ''}
                            </div>
                        </div>
                        <div class="col-auto text-right">
                            <div class="mb-2">
                                ${statusBadge}
                            </div>
                            <div>
                                <a href="../${doc.file_path}" class="btn btn-sm btn-outline-primary" target="_blank" title="Download">
                                    <i class="feather icon-download"></i>
                                </a>
                                ${typeof window.VISA_CAN_DELETE_DOC === 'undefined' || window.VISA_CAN_DELETE_DOC ? `
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="visaDocManager.deleteDocument(${doc.id})">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    ${doc.rejection_reason ? '<div class="alert alert-warning mt-2 mb-0 py-1"><small><strong>Rejection Reason:</strong> ' + this.escapeHtml(doc.rejection_reason) + '</small></div>' : ''}
                </div>
            `);

            list.append(item);
        });

        listContainer.html(list);

        // Bind preview click handlers
        list.find('.document-item').on('click', (e) => {
            const docItem = $(e.currentTarget);
            this.previewDocument(docItem);
        });
    }

    getStatusBadge(status) {
        const badges = {
            'pending': '<span class="document-status-badge status-pending">Pending</span>',
            'approved': '<span class="document-status-badge status-approved">Approved</span>',
            'rejected': '<span class="document-status-badge status-rejected">Rejected</span>'
        };
        return badges[status] || badges['pending'];
    }

    deleteDocument(documentId) {
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }

        $.ajax({
            url: '../api/visa_documents_upload.php',
            type: 'DELETE',
            contentType: 'application/json',
            data: JSON.stringify({ id: documentId }),
            success: (response) => {
                if (response.success) {
                    showToast('Document deleted successfully', 'success');
                    this.loadDocuments();
                } else {
                    showToast('Failed to delete document', 'error');
                }
            },
            error: (err) => {
                console.error('Delete error:', err);
                showToast('Failed to delete document', 'error');
            }
        });
    }

    previewDocument(docItem) {
        const previewContainer = $('#documentPreviewContainer');

        // Remove active class from all items
        $('.document-item').removeClass('preview-active');

        // Add active class to clicked item
        docItem.addClass('preview-active');

        const docPath = docItem.data('doc-path');
        const docType = docItem.data('doc-type');
        const docName = docItem.data('doc-name');
        const docSize = docItem.data('doc-size');
        const docUploaded = docItem.data('doc-uploaded');
        const docUploadedBy = docItem.data('doc-uploaded-by');

        // Create info section
        const infoHtml = `
            <div class="document-preview-info">
                <strong>${this.escapeHtml(docName)}</strong>
                <small>
                    Size: ${this.formatFileSize(docSize)}<br>
                    Uploaded: ${new Date(docUploaded).toLocaleString()}
                    ${docUploadedBy ? '<br>By: ' + this.escapeHtml(docUploadedBy) : ''}
                </small>
            </div>
        `;

        let previewHtml = infoHtml;

        // Generate preview based on file type
        if (docType.includes('image')) {
            // Image preview
            previewHtml += `
                <div class="text-center">
                    <img src="${docPath}" alt="${this.escapeHtml(docName)}" class="document-preview-image">
                </div>
            `;
        } else if (docType === 'application/pdf') {
            // PDF preview
            previewHtml += `
                <embed src="${docPath}" type="application/pdf" class="document-preview-pdf">
            `;
        } else {
            // Generic preview for other file types
            previewHtml += `
                <div class="text-center py-4">
                    <i class="feather icon-file-text display-4 text-muted mb-3"></i>
                    <p class="text-muted">
                        <strong>${this.getMimeTypeLabel(docType)}</strong><br>
                        <small>File preview not available for this file type</small>
                    </p>
                    <a href="${docPath}" class="btn btn-primary btn-sm" target="_blank">
                        <i class="feather icon-download mr-2"></i>Download File
                    </a>
                </div>
            `;
        }

        previewContainer.html(previewHtml);
    }

    getMimeTypeLabel(mimeType) {
        const labels = {
            'application/pdf': 'PDF Document',
            'application/msword': 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Document',
            'application/vnd.ms-excel': 'Excel Spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel Spreadsheet'
        };
        return labels[mimeType] || 'Document';
    }
}

// Initialize on document ready
let visaDocManager;
$(document).ready(function () {
    visaDocManager = new VisaDocumentManager();
});
