/**
 * FileUploadProgress - Handle file upload with progress bar and feedback
 */
class FileUploadProgress {
    constructor(uiUtilities) {
        this.ui = uiUtilities;
        this.activeUploads = new Map();
    }

    /**
     * Create progress bar for file
     */
    createProgressBar(fileName) {
        const uploadId = `upload-${Date.now()}-${Math.random()}`;

        const container = document.createElement('div');
        container.id = uploadId;
        container.style.cssText = `
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        `;

        container.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                    <i class="fas fa-file" style="color: #6b7280;"></i>
                    <span style="color: #374151; font-size: 0.9rem; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${this.ui.escapeHtml(fileName)}
                    </span>
                </div>
                <button type="button" class="cancel-upload" style="
                    background: none;
                    border: none;
                    color: #ef4444;
                    cursor: pointer;
                    padding: 4px 8px;
                    font-size: 0.85rem;
                ">
                    Cancel
                </button>
            </div>

            <div style="
                background: #e5e7eb;
                border-radius: 4px;
                height: 6px;
                overflow: hidden;
            ">
                <div class="progress-fill" style="
                    height: 100%;
                    background: linear-gradient(90deg, #4099ff, #2ed8b6);
                    width: 0%;
                    transition: width 0.3s ease;
                "></div>
            </div>

            <div style="display: flex; justify-content: space-between; gap: 8px;">
                <span class="upload-status" style="font-size: 0.8rem; color: #6b7280;">
                    Initializing...
                </span>
                <span class="upload-speed" style="font-size: 0.8rem; color: #9ca3af;">
                    --
                </span>
            </div>
        `;

        this.activeUploads.set(uploadId, {
            fileName,
            container,
            startTime: Date.now(),
            uploadedBytes: 0,
            totalBytes: 0
        });

        return { uploadId, container };
    }

    /**
     * Update progress
     */
    updateProgress(uploadId, uploadedBytes, totalBytes) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        upload.uploadedBytes = uploadedBytes;
        upload.totalBytes = totalBytes;

        const percentage = totalBytes > 0 ? Math.round((uploadedBytes / totalBytes) * 100) : 0;
        const progressBar = upload.container.querySelector('.progress-fill');
        const statusSpan = upload.container.querySelector('.upload-status');
        const speedSpan = upload.container.querySelector('.upload-speed');

        // Update progress bar
        progressBar.style.width = percentage + '%';

        // Calculate speed
        const elapsedSeconds = (Date.now() - upload.startTime) / 1000;
        const speed = uploadedBytes / elapsedSeconds / (1024 * 1024); // MB/s

        // Update status and speed
        statusSpan.textContent = `${percentage}% (${this.ui.formatFileSize(uploadedBytes)} / ${this.ui.formatFileSize(totalBytes)})`;
        speedSpan.textContent = speed > 0 ? `${speed.toFixed(1)} MB/s` : '--';
    }

    /**
     * Complete upload
     */
    completeUpload(uploadId, success = true) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        const container = upload.container;

        if (success) {
            container.style.background = '#d1fae5';
            container.style.borderColor = '#10b981';
            container.querySelector('.progress-fill').style.background = '#10b981';
            container.querySelector('.upload-status').textContent = '✓ Upload complete';
            container.querySelector('.upload-status').style.color = '#10b981';
            container.querySelector('.cancel-upload').style.display = 'none';

            // Auto-remove after 2 seconds
            setTimeout(() => {
                container.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    container.remove();
                    this.activeUploads.delete(uploadId);
                }, 300);
            }, 2000);
        } else {
            container.style.background = '#fee2e2';
            container.style.borderColor = '#ef4444';
            container.querySelector('.progress-fill').style.background = '#ef4444';
            container.querySelector('.upload-status').textContent = '✗ Upload failed';
            container.querySelector('.upload-status').style.color = '#ef4444';
        }
    }

    /**
     * Cancel upload
     */
    cancelUpload(uploadId) {
        const upload = this.activeUploads.get(uploadId);
        if (!upload) return;

        const container = upload.container;
        container.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => {
            container.remove();
            this.activeUploads.delete(uploadId);
        }, 300);
    }

    /**
     * Handle file input change
     */
    handleFileSelect(files, onProgressCallback, onCompleteCallback) {
        if (!files || files.length === 0) return;

        Array.from(files).forEach(file => {
            // Validate file
            const maxSize = 25 * 1024 * 1024; // 25MB
            if (file.size > maxSize) {
                this.ui.showError(`File "${file.name}" exceeds 25MB limit`);
                return;
            }

            const { uploadId, container } = this.createProgressBar(file.name);
            const messagesContainer = document.querySelector('.messages-container');

            if (messagesContainer) {
                messagesContainer.insertBefore(container, messagesContainer.firstChild);
            }

            // Setup cancel button
            container.querySelector('.cancel-upload').addEventListener('click', () => {
                this.cancelUpload(uploadId);
                // Could also abort the XMLHttpRequest if stored
            });

            // Call progress callback
            if (onProgressCallback) {
                onProgressCallback(uploadId, file);
            }

            // Simulate file upload with XHR for better progress tracking
            this.uploadFileWithProgress(file, uploadId, onCompleteCallback);
        });
    }

    /**
     * Upload file with XMLHttpRequest for progress tracking
     */
    uploadFileWithProgress(file, uploadId, onComplete) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', window.csrfToken);

        const xhr = new XMLHttpRequest();

        // Progress event
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                this.updateProgress(uploadId, e.loaded, e.total);
            }
        });

        // Success
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    this.completeUpload(uploadId, true);
                    this.ui.showSuccess(`File "${file.name}" uploaded successfully`);

                    if (onComplete) {
                        onComplete(response, uploadId);
                    }
                } catch (e) {
                    this.completeUpload(uploadId, false);
                    this.ui.showError('Invalid response from server');
                }
            } else {
                this.completeUpload(uploadId, false);
                this.ui.showError(`Upload failed with status ${xhr.status}`);
            }
        });

        // Error
        xhr.addEventListener('error', () => {
            this.completeUpload(uploadId, false);
            this.ui.showError(`Failed to upload "${file.name}"`);
        });

        // Abort
        xhr.addEventListener('abort', () => {
            this.ui.showWarning(`Upload cancelled: ${file.name}`);
        });

        // Start upload
        xhr.open('POST', 'api/chat_upload_file.php');
        xhr.setRequestHeader('X-CSRF-Token', window.csrfToken);
        xhr.send(formData);

        // Store XHR for potential cancellation
        this.activeUploads.get(uploadId).xhr = xhr;
    }

    /**
     * Create drag-and-drop zone styling
     */
    setupDragDrop(dropZone, onFilesSelected) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = '#f0f9ff';
            dropZone.style.borderColor = '#4099ff';
            dropZone.style.borderWidth = '2px';
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = '';
            dropZone.style.borderColor = '';
            dropZone.style.borderWidth = '';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.style.background = '';
            dropZone.style.borderColor = '';
            dropZone.style.borderWidth = '';

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                onFilesSelected(files);
            }
        });
    }
}

// Export
window.FileUploadProgress = FileUploadProgress;
