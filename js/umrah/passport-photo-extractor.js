/**
 * Passport Photo Extractor
 * Extracts and crops photos from Afghan passport documents
 * Provides interactive crop UI for precise photo selection
 */

class PassportPhotoExtractor {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.image = null;
        this.imageData = null;
        this.isDragging = false;
        this.startX = 0;
        this.startY = 0;
        this.cropBox = {
            x: 0,
            y: 0,
            width: 0,
            height: 0,
            minWidth: 100,
            minHeight: 150 // Passport photos typically taller than wide
        };
    }

    /**
     * Initialize photo extractor modal
     */
    init() {
        try {
        const html = `
            <div id="photoExtractorModal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Extract Photo from Passport</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div id="photoExtractorSteps" class="steps-container">
                                <!-- Step 1: Upload -->
                                <div id="step1Upload" class="step-section">
                                    <h6>Step 1: Upload Passport Image</h6>
                                    <div class="upload-zone" id="photoUploadZone" style="
                                        border: 2px dashed #ccc;
                                        border-radius: 8px;
                                        padding: 30px;
                                        text-align: center;
                                        cursor: pointer;
                                        background: #f9f9f9;
                                        transition: all 0.3s;
                                    ">
                                        <i class="fas fa-image" style="font-size: 40px; color: #999; margin-bottom: 10px;"></i>
                                        <p>Click or drag passport image here</p>
                                        <small style="color: #999;">Supports PNG, JPG, PDF</small>
                                    </div>
                                    <input type="file" id="photoUploadInput" style="display: none;" accept="image/png,image/jpeg,application/pdf">
                                </div>

                                <!-- Step 2: Crop -->
                                <div id="step2Crop" class="step-section" style="display: none;">
                                    <h6>Step 2: Crop Photo Area</h6>
                                    <div style="position: relative; display: inline-block; width: 100%; margin: 10px 0;">
                                        <canvas id="photoCropCanvas" style="
                                            max-width: 100%;
                                            border: 1px solid #ccc;
                                            cursor: crosshair;
                                            display: block;
                                            background: #f0f0f0;
                                        "></canvas>
                                    </div>
                                    <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
                                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                                            <strong>Instructions:</strong> Click and drag to select the photo area. The highlighted box is the photo region.
                                        </p>
                                        <div style="display: flex; gap: 10px;">
                                            <button type="button" class="btn btn-sm btn-secondary" id="resetCropBtn">
                                                <i class="fas fa-redo"></i> Reset
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" id="autoCropBtn">
                                                <i class="fas fa-magic"></i> Auto Detect
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3: Preview -->
                                <div id="step3Preview" class="step-section" style="display: none;">
                                    <h6>Step 3: Preview</h6>
                                    <div style="text-align: center;">
                                        <img id="photoPreview" style="
                                            max-width: 300px;
                                            max-height: 400px;
                                            border: 1px solid #ddd;
                                            border-radius: 4px;
                                            margin: 10px auto;
                                            display: block;
                                        ">
                                        <p id="photoPreviewInfo" style="color: #666; font-size: 12px; margin-top: 10px;"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="extractPhotoBtn" style="display: none;">
                                <i class="fas fa-save"></i> Save Photo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Add modal to page
            if (!document.getElementById('photoExtractorModal')) {
                document.body.insertAdjacentHTML('beforeend', html);
            }

            this.attachEventListeners();
        } catch (error) {
            console.error('Error initializing photo extractor:', error);
        }
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        const uploadZone = document.getElementById('photoUploadZone');
        const uploadInput = document.getElementById('photoUploadInput');
        const canvas = document.getElementById('photoCropCanvas');
        const resetBtn = document.getElementById('resetCropBtn');
        const autoBtn = document.getElementById('autoCropBtn');
        const extractBtn = document.getElementById('extractPhotoBtn');

        // Upload zone
        uploadZone?.addEventListener('click', () => uploadInput?.click());
        uploadZone?.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.style.backgroundColor = '#e8f4f8';
            uploadZone.style.borderColor = '#2ed8b6';
        });
        uploadZone?.addEventListener('dragleave', () => {
            uploadZone.style.backgroundColor = '';
            uploadZone.style.borderColor = '';
        });
        uploadZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.backgroundColor = '';
            uploadZone.style.borderColor = '';
            if (e.dataTransfer.files.length > 0) {
                uploadInput.files = e.dataTransfer.files;
                this.handleFileUpload(e.dataTransfer.files[0]);
            }
        });

        uploadInput?.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFileUpload(e.target.files[0]);
            }
        });

        // Canvas crop
        canvas?.addEventListener('mousedown', (e) => this.startCrop(e, canvas));
        canvas?.addEventListener('mousemove', (e) => this.drawCrop(e, canvas));
        canvas?.addEventListener('mouseup', () => this.stopCrop());
        canvas?.addEventListener('mouseout', () => this.stopCrop());

        // Reset button
        resetBtn?.addEventListener('click', () => this.resetCrop());

        // Auto detect
        autoBtn?.addEventListener('click', () => this.autoCropPhoto());

        // Extract button
        extractBtn?.addEventListener('click', () => this.extractAndSaveToServer());
    }

    /**
     * Handle file upload
     */
    async handleFileUpload(file) {
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'application/pdf'];
        
        if (!allowedTypes.includes(file.type)) {
            Swal.fire('Error', 'Please upload a valid image or PDF file', 'error');
            return;
        }

        try {
            let imageData;

            if (file.type === 'application/pdf') {
                imageData = await this.extractImageFromPDF(file);
            } else {
                imageData = await this.readImageFile(file);
            }

            if (!imageData) {
                throw new Error('Failed to read image');
            }

            this.loadImage(imageData);
            this.showStep(2); // Move to crop step
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    /**
     * Read image file
     */
    readImageFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * Extract image from PDF using PDF.js (basic approach)
     * Note: Requires pdf.js library loaded
     */
    async extractImageFromPDF(file) {
        // For now, show a message that user should upload image directly
        throw new Error('Please upload the passport image directly instead of PDF. Alternatively, use a PDF to image converter first.');
    }

    /**
     * Load image to canvas
     */
    loadImage(imageData) {
        const canvas = document.getElementById('photoCropCanvas');
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');

        const img = new Image();
        img.onload = () => {
            this.image = img;

            // Set canvas size
            canvas.width = img.width;
            canvas.height = img.height;

            // Scale canvas to fit container (max 600px width)
            const maxWidth = 600;
            if (img.width > maxWidth) {
                const scale = maxWidth / img.width;
                canvas.style.width = maxWidth + 'px';
                canvas.style.height = (img.height * scale) + 'px';
                this.scale = scale;
            } else {
                this.scale = 1;
            }

            // Draw image
            this.ctx.drawImage(img, 0, 0);

            // Initialize crop box (center of image, 150x200 typical passport photo)
            this.cropBox.width = Math.min(150, img.width);
            this.cropBox.height = Math.min(200, img.height);
            this.cropBox.x = (img.width - this.cropBox.width) / 2;
            this.cropBox.y = (img.height - this.cropBox.height) / 2;

            this.drawCropOverlay();
        };
        img.src = imageData;
    }

    /**
     * Start crop selection
     */
    startCrop(e, canvas) {
        const rect = canvas.getBoundingClientRect();
        this.startX = (e.clientX - rect.left) / this.scale;
        this.startY = (e.clientY - rect.top) / this.scale;
        this.isDragging = true;
    }

    /**
     * Draw crop during drag
     */
    drawCrop(e, canvas) {
        if (!this.isDragging || !this.image) return;

        const rect = canvas.getBoundingClientRect();
        const currentX = (e.clientX - rect.left) / this.scale;
        const currentY = (e.clientY - rect.top) / this.scale;

        this.cropBox.x = Math.min(this.startX, currentX);
        this.cropBox.y = Math.min(this.startY, currentY);
        this.cropBox.width = Math.abs(currentX - this.startX);
        this.cropBox.height = Math.abs(currentY - this.startY);

        // Enforce minimum size
        if (this.cropBox.width < this.cropBox.minWidth) {
            this.cropBox.width = this.cropBox.minWidth;
        }
        if (this.cropBox.height < this.cropBox.minHeight) {
            this.cropBox.height = this.cropBox.minHeight;
        }

        this.drawCropOverlay();
    }

    /**
     * Stop crop
     */
    stopCrop() {
        this.isDragging = false;
    }

    /**
     * Draw crop overlay
     */
    drawCropOverlay() {
        if (!this.ctx || !this.image) return;

        // Redraw image
        this.ctx.drawImage(this.image, 0, 0);

        // Draw dark overlay outside crop area
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        this.ctx.fillRect(0, 0, this.image.width, this.cropBox.y);
        this.ctx.fillRect(0, this.cropBox.y, this.cropBox.x, this.cropBox.height);
        this.ctx.fillRect(
            this.cropBox.x + this.cropBox.width,
            this.cropBox.y,
            this.image.width - (this.cropBox.x + this.cropBox.width),
            this.cropBox.height
        );
        this.ctx.fillRect(
            0,
            this.cropBox.y + this.cropBox.height,
            this.image.width,
            this.image.height - (this.cropBox.y + this.cropBox.height)
        );

        // Draw crop box border
        this.ctx.strokeStyle = '#2ed8b6';
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(
            this.cropBox.x,
            this.cropBox.y,
            this.cropBox.width,
            this.cropBox.height
        );

        // Draw corner handles
        const handleSize = 8;
        const corners = [
            [this.cropBox.x, this.cropBox.y],
            [this.cropBox.x + this.cropBox.width, this.cropBox.y],
            [this.cropBox.x, this.cropBox.y + this.cropBox.height],
            [this.cropBox.x + this.cropBox.width, this.cropBox.y + this.cropBox.height]
        ];

        this.ctx.fillStyle = '#2ed8b6';
        corners.forEach(([x, y]) => {
            this.ctx.fillRect(x - handleSize / 2, y - handleSize / 2, handleSize, handleSize);
        });
    }

    /**
     * Auto-crop to detect photo area
     */
    autoCropPhoto() {
        if (!this.image) return;

        // Simple auto-crop: detect face area or use standard passport photo dimensions
        // For now, use a center-based approach with typical passport dimensions
        const img = this.image;
        const targetWidth = 150;
        const targetHeight = 200;

        // Center the crop box
        this.cropBox.width = Math.min(targetWidth, img.width);
        this.cropBox.height = Math.min(targetHeight, img.height);
        this.cropBox.x = (img.width - this.cropBox.width) / 2;
        this.cropBox.y = Math.max(0, (img.height - this.cropBox.height) / 2 - 30); // Slight top bias

        this.drawCropOverlay();
        Swal.fire('Auto-crop', 'Crop area adjusted to standard passport photo size', 'info');
    }

    /**
     * Reset crop to full image
     */
    resetCrop() {
        if (!this.image) return;
        this.cropBox.x = 0;
        this.cropBox.y = 0;
        this.cropBox.width = this.image.width;
        this.cropBox.height = this.image.height;
        this.drawCropOverlay();
    }

    /**
     * Extract cropped photo and preview
     */
    extractAndSave() {
        if (!this.canvas || !this.ctx) return;

        // Create temporary canvas for cropped image
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = this.cropBox.width;
        tempCanvas.height = this.cropBox.height;
        const tempCtx = tempCanvas.getContext('2d');

        tempCtx.drawImage(
            this.image,
            this.cropBox.x, this.cropBox.y,
            this.cropBox.width, this.cropBox.height,
            0, 0,
            this.cropBox.width, this.cropBox.height
        );

        // Store as data for upload
        this.croppedPhotoData = tempCanvas.toDataURL('image/jpeg', 0.9);
        this.croppedPhotoBlob = dataURLtoBlob(this.croppedPhotoData);

        // Show preview and save button
        const preview = document.getElementById('photoPreview');
        const info = document.getElementById('photoPreviewInfo');
        if (preview) preview.src = this.croppedPhotoData;
        if (info) info.textContent = `Dimensions: ${this.cropBox.width}x${this.cropBox.height}px`;

        const extractBtn = document.getElementById('extractPhotoBtn');
        if (extractBtn) extractBtn.style.display = 'inline-block';

        this.showStep(3); // Show preview step
    }

    /**
     * Save extracted photo to server
     */
    async extractAndSaveToServer() {
        if (!this.croppedPhotoData) {
            Swal.fire('Error', 'Please crop a photo first', 'error');
            return;
        }

        const bookingId = this.currentBookingId;

        try {
            const response = await fetch('/api/umrah/extract_passport_photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_data: this.croppedPhotoData,
                    booking_id: bookingId,
                    crop_data: this.cropBox
                }),
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire('Success', 'Photo extracted and saved successfully', 'success').then(() => {
                    // Close modal and refresh
                    $('#photoExtractorModal').modal('hide');
                    if (window.viewMemberDetails) {
                        window.viewMemberDetails(bookingId);
                    }
                });
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    }

    /**
     * Show step
     */
    showStep(stepNum) {
        for (let i = 1; i <= 3; i++) {
            const step = document.getElementById(`step${i}Upload`) || document.getElementById(`step${i}Crop`) || document.getElementById(`step${i}Preview`);
            if (step) step.style.display = 'none';
        }

        if (stepNum === 1) {
            const el = document.getElementById('step1Upload');
            if (el) el.style.display = 'block';
        } else if (stepNum === 2) {
            const el = document.getElementById('step2Crop');
            if (el) el.style.display = 'block';
        } else if (stepNum === 3) {
            const el = document.getElementById('step3Preview');
            if (el) el.style.display = 'block';
        }
    }

    /**
     * Open extractor for specific booking
     */
    openFor(bookingId) {
        this.currentBookingId = bookingId;
        this.resetState();
        this.showStep(1);
        $('#photoExtractorModal').modal('show');
    }

    /**
     * Reset state
     */
    resetState() {
        this.canvas = null;
        this.ctx = null;
        this.image = null;
        this.imageData = null;
        this.croppedPhotoData = null;
        this.croppedPhotoBlob = null;
        this.isDragging = false;
        document.getElementById('photoUploadInput').value = '';
    }
}

/**
 * Convert data URL to blob
 */
function dataURLtoBlob(dataurl) {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
}

// Initialize on page load
let passportPhotoExtractor = null;

function initializePhotoExtractor() {
    try {
        if (!passportPhotoExtractor) {
            passportPhotoExtractor = new PassportPhotoExtractor();
            passportPhotoExtractor.init();
            console.log('✓ PassportPhotoExtractor initialized successfully');
            window.passportPhotoExtractor = passportPhotoExtractor; // Expose globally
        }
    } catch (error) {
        console.error('✗ Failed to initialize PassportPhotoExtractor:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePhotoExtractor);
} else {
    // DOM already loaded
    initializePhotoExtractor();
}

// Also initialize after a slight delay to ensure all dependencies are ready
setTimeout(initializePhotoExtractor, 100);
