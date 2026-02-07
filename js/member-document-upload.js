/**
 * Member Document Upload Handler
 * Handles photo and passport uploads for umrah members
 */

// Upload file for a member
function uploadMemberDocument(bookingId, documentType) {
    const fileInput = document.getElementById(`${documentType}-file-${bookingId}`);
    
    if (!fileInput || !fileInput.files.length) {
        Swal.fire({
            icon: 'warning',
            title: 'No File',
            text: 'Please select a file first'
        });
        return;
    }

    const file = fileInput.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB

    // Client-side validation
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Allowed formats: JPG, PNG, GIF, PDF'
        });
        return;
    }

    if (file.size > maxSize) {
        Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: 'Maximum file size is 5MB'
        });
        return;
    }

    // Show loading
    const uploadBtn = document.getElementById(`${documentType}-upload-btn-${bookingId}`);
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="feather icon-loader mr-1"></i>Uploading...';

    // Prepare FormData
    const formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('document_type', documentType);
    formData.append('file', file);

    // Upload
    fetch('../api/upload_member_documents.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message,
                timer: 2000
            }).then(() => {
                // Refresh the member details
                viewMemberDetails(bookingId);
                fileInput.value = '';
            });
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: error.message || 'An error occurred during upload'
        });
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
    });
}

// Delete a member document
function deleteMemberDocument(bookingId, documentType) {
    Swal.fire({
        title: 'Delete ' + documentType.charAt(0).toUpperCase() + documentType.slice(1),
        text: 'Are you sure you want to delete this file?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('booking_id', bookingId);
            formData.append('document_type', documentType);

            fetch('../api/delete_member_document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', 'File has been deleted.', 'success');
                    viewMemberDetails(bookingId);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to delete file', 'error');
            });
        }
    });
}

// View/Download document
function viewMemberDocument(filePath, documentType) {
    if (!filePath) {
        Swal.fire('Error', 'No file path available', 'error');
        return;
    }

    // Check file type and open accordingly
    if (filePath.endsWith('.pdf')) {
        // Open PDF in new tab
        window.open(filePath, '_blank');
    } else if (filePath.match(/\.(jpg|jpeg|png|gif)$/i)) {
        // Show image in modal
        Swal.fire({
            title: documentType.charAt(0).toUpperCase() + documentType.slice(1),
            imageUrl: filePath,
            imageAlt: documentType,
            width: '80%',
            showConfirmButton: false,
            html: `<a href="${filePath}" download class="btn btn-primary mt-3">Download</a>`
        });
    } else {
        // Download other files
        window.location.href = filePath;
    }
}

// Initialize document upload UI for a specific booking
function initializeDocumentUpload(bookingId, photoPath = null, passportPath = null) {
    const photoInput = document.getElementById(`photo-file-${bookingId}`);
    const passportInput = document.getElementById(`passport-file-${bookingId}`);

    if (photoInput) {
        photoInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            const label = document.querySelector(`label[for="photo-file-${bookingId}"]`);
            if (label) {
                label.textContent = fileName;
            }
        });
    }

    if (passportInput) {
        passportInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            const label = document.querySelector(`label[for="passport-file-${bookingId}"]`);
            if (label) {
                label.textContent = fileName;
            }
        });
    }
}

// Load and display member details with documents
function loadMemberDocuments(bookingId) {
    fetch(`../api/get_member_documents.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const photoPath = data.photo_path;
                const passportPath = data.passport_path;
                const photoBtn = document.getElementById(`view-photo-${bookingId}`);
                const passportBtn = document.getElementById(`view-passport-${bookingId}`);

                if (photoBtn && photoPath) {
                    photoBtn.innerHTML = '<i class="feather icon-eye mr-1"></i>View Photo';
                    photoBtn.disabled = false;
                    photoBtn.onclick = () => viewMemberDocument(photoPath, 'photo');
                }

                if (passportBtn && passportPath) {
                    passportBtn.innerHTML = '<i class="feather icon-eye mr-1"></i>View Passport';
                    passportBtn.disabled = false;
                    passportBtn.onclick = () => viewMemberDocument(passportPath, 'passport');
                }
            }
        })
        .catch(error => console.error('Error loading documents:', error));
}
