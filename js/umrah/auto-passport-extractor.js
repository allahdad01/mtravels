/**
 * Automatic Afghan Passport Photo Extractor
 * Automatically detects and extracts photo from passport without user interaction
 */

class AutoPassportExtractor {
    constructor() {
        this.extracting = false;
    }

    /**
     * Extract photo from passport image automatically
     * @param {File|Blob} file - The passport image file
     * @param {Number} bookingId - Optional booking ID to save to database
     * @param {Number} familyId - Optional family ID for proper folder structure
     * @returns {Promise} Resolves with extraction result
     */
    async extract(file, bookingId = null, familyId = null) {
        if (this.extracting) {
            return Promise.reject(new Error('Extraction already in progress'));
        }

        if (!file) {
            return Promise.reject(new Error('No file provided'));
        }

        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        if (!allowedTypes.includes(file.type)) {
            return Promise.reject(new Error('Only PNG and JPG images supported'));
        }

        if (file.size > 10 * 1024 * 1024) {
            return Promise.reject(new Error('File too large (max 10MB)'));
        }

        this.extracting = true;

        try {
            // Read file as data URL
            const imageData = await this.readFile(file);

            // Call auto-extraction API with absolute path
            const response = await fetch('/almoqadas/mtravels/api/umrah/auto_extract_passport_photo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_data: imageData,
                    booking_id: bookingId,
                    family_id: familyId
                }),
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Extraction failed');
            }

            return {
                success: true,
                photoPath: result.photo_path,
                filename: result.filename,
                width: result.width,
                height: result.height,
                detectedArea: result.detected_area,
                message: result.message
            };

        } finally {
            this.extracting = false;
        }
    }

    /**
     * Read file as data URL
     */
    readFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * Show progress toast
     */
    showProgress(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Processing...',
                html: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    }

    /**
     * Show success message
     */
    showSuccess(message, title = 'Success') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                timer: 3000
            });
        }
    }

    /**
     * Show error message
     */
    showError(message, title = 'Error') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: title,
                text: message
            });
        }
    }

    /**
     * Extract from file input element
     */
    async extractFromInput(fileInputId, bookingId = null) {
        const input = document.getElementById(fileInputId);
        if (!input || !input.files || input.files.length === 0) {
            this.showError('Please select a file');
            return null;
        }

        this.showProgress('Detecting photo in passport...');

        try {
            const result = await this.extract(input.files[0], bookingId);
            this.showSuccess(
                `Photo extracted successfully!\nSize: ${result.width}x${result.height}px`,
                'Photo Extracted'
            );
            return result;
        } catch (error) {
            this.showError(error.message);
            return null;
        }
    }

    /**
     * Extract from dropped file
     */
    async extractFromDrop(files, bookingId = null) {
        if (!files || files.length === 0) {
            this.showError('No files provided');
            return null;
        }

        this.showProgress('Detecting photo in passport...');

        try {
            const result = await this.extract(files[0], bookingId);
            this.showSuccess(
                `Photo extracted successfully!\nSize: ${result.width}x${result.height}px`,
                'Photo Extracted'
            );
            return result;
        } catch (error) {
            this.showError(error.message);
            return null;
        }
    }

    /**
     * Setup drag-drop zone
     */
    setupDropZone(dropZoneId, bookingId = null) {
        const zone = document.getElementById(dropZoneId);
        if (!zone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        zone.addEventListener('dragover', () => {
            zone.style.backgroundColor = '#e8f4f8';
            zone.style.borderColor = '#2ed8b6';
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, () => {
                zone.style.backgroundColor = '';
                zone.style.borderColor = '';
            });
        });

        zone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length > 0) {
                this.extractFromDrop(e.dataTransfer.files, bookingId);
            }
        });

        zone.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/png,image/jpeg,image/jpg';
            input.onchange = () => {
                if (input.files.length > 0) {
                    this.extractFromDrop(input.files, bookingId);
                }
            };
            input.click();
        });
    }
}

// Initialize globally
let autoPassportExtractor = new AutoPassportExtractor();
window.autoPassportExtractor = autoPassportExtractor;
