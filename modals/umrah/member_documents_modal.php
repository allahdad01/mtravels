<!-- Member Documents Modal - Photo and Passport Upload/View -->
<div class="modal fade" id="memberDocumentsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>Member Documents
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="documentBookingId" value="">

                <div class="row">
                    <!-- Photo Section -->
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="feather icon-image mr-2"></i>Photo</h6>
                            </div>
                            <div class="card-body">
                                <!-- Photo Preview -->
                                <div id="photoPreview" class="mb-3" style="display: none;">
                                    <img id="photoImage" src="" alt="Photo" class="img-fluid rounded" style="max-height: 250px;">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-info mr-2" id="viewPhotoBtn" onclick="viewMemberDocument(document.getElementById('photoImage').src, 'photo')">
                                            <i class="feather icon-eye mr-1"></i>View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" id="deletePhotoBtn" onclick="deleteMemberDocument(document.getElementById('documentBookingId').value, 'photo')">
                                            <i class="feather icon-trash-2 mr-1"></i>Delete
                                        </button>
                                    </div>
                                </div>

                                <!-- Photo Upload -->
                                <div id="photoUploadSection">
                                    <div id="photoPreviewBeforeUpload" class="mb-3" style="display: none;">
                                        <img id="photoPreviewImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">
                                        <div class="mt-2 small text-muted">
                                            <strong>File:</strong> <span id="photoFileName"></span><br>
                                            <strong>Size:</strong> <span id="photoFileSize"></span>
                                        </div>
                                    </div>
                                    <div class="custom-file mb-2">
                                        <input type="file" class="custom-file-input" id="photo-file-document" accept="image/*" onchange="previewPhotoBeforeUpload(event)">
                                        <label class="custom-file-label" for="photo-file-document">Choose file</label>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        Supported: JPG, PNG, GIF (Max 5MB)
                                    </small>
                                    <button type="button" class="btn btn-primary btn-sm w-100" id="uploadPhotoBtn" onclick="uploadMemberDocumentModal('photo')">
                                        <i class="feather icon-upload mr-1"></i>Upload Photo
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passport Section -->
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="feather icon-file mr-2"></i>Passport</h6>
                            </div>
                            <div class="card-body">
                                <!-- Passport Preview -->
                                <div id="passportPreview" class="mb-3" style="display: none;">
                                    <div id="passportContent"></div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-info mr-2" id="viewPassportBtn" onclick="viewMemberDocument(document.getElementById('passportPath').value, 'passport')">
                                            <i class="feather icon-eye mr-1"></i>View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" id="deletePassportBtn" onclick="deleteMemberDocument(document.getElementById('documentBookingId').value, 'passport')">
                                            <i class="feather icon-trash-2 mr-1"></i>Delete
                                        </button>
                                    </div>
                                    <input type="hidden" id="passportPath">
                                </div>

                                <!-- Passport Upload -->
                                <div id="passportUploadSection">
                                    <div id="passportPreviewBeforeUpload" class="mb-3" style="display: none;">
                                        <div id="passportPreviewContent"></div>
                                        <div class="mt-2 small text-muted">
                                            <strong>File:</strong> <span id="passportFileName"></span><br>
                                            <strong>Size:</strong> <span id="passportFileSize"></span>
                                        </div>
                                    </div>
                                    <div class="custom-file mb-2">
                                        <input type="file" class="custom-file-input" id="passport-file-document" accept="image/*,.pdf" onchange="previewPassportBeforeUpload(event)">
                                        <label class="custom-file-label" for="passport-file-document">Choose file</label>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        Supported: JPG, PNG, GIF, PDF (Max 5MB)
                                    </small>
                                    <button type="button" class="btn btn-primary btn-sm w-100" id="uploadPassportBtn" onclick="uploadMemberDocumentModal('passport')">
                                        <i class="feather icon-upload mr-1"></i>Upload Passport
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visa Section -->
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="feather icon-shield mr-2"></i>Visa</h6>
                            </div>
                            <div class="card-body">
                                <!-- Visa Preview -->
                                <div id="visaPreview" class="mb-3" style="display: none;">
                                    <div id="visaContent"></div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-info mr-2" id="viewVisaBtn" onclick="viewMemberDocument(document.getElementById('visaPath').value, 'visa')">
                                            <i class="feather icon-eye mr-1"></i>View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" id="deleteVisaBtn" onclick="deleteMemberDocument(document.getElementById('documentBookingId').value, 'visa')">
                                            <i class="feather icon-trash-2 mr-1"></i>Delete
                                        </button>
                                    </div>
                                    <input type="hidden" id="visaPath">
                                </div>

                                <!-- Visa Upload -->
                                <div id="visaUploadSection">
                                    <div id="visaPreviewBeforeUpload" class="mb-3" style="display: none;">
                                        <div id="visaPreviewContent"></div>
                                        <div class="mt-2 small text-muted">
                                            <strong>File:</strong> <span id="visaFileName"></span><br>
                                            <strong>Size:</strong> <span id="visaFileSize"></span>
                                        </div>
                                    </div>
                                    <div class="custom-file mb-2">
                                        <input type="file" class="custom-file-input" id="visa-file-document" accept="image/*,.pdf" onchange="previewVisaBeforeUpload(event)">
                                        <label class="custom-file-label" for="visa-file-document">Choose file</label>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        Supported: JPG, PNG, GIF, PDF (Max 5MB)
                                    </small>
                                    <button type="button" class="btn btn-primary btn-sm w-100" id="uploadVisaBtn" onclick="uploadMemberDocumentModal('visa')">
                                        <i class="feather icon-upload mr-1"></i>Upload Visa
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info Alert -->
                <div class="alert alert-info mt-3" role="alert">
                    <i class="feather icon-info mr-2"></i>
                    <strong>Storage Information:</strong> Documents are stored securely in organized folders (Tenant/Branch/Umrah/Family structure) and are viewable to members.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Upload document via modal
function uploadMemberDocumentModal(documentType) {
    const bookingId = document.getElementById('documentBookingId').value;
    const fileInput = document.getElementById(`${documentType}-file-document`);
    
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

    const uploadBtn = document.getElementById(`upload${documentType.charAt(0).toUpperCase() + documentType.slice(1)}Btn`);
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="feather icon-loader mr-1"></i>Uploading...';

    const formData = new FormData();
    formData.append('booking_id', bookingId);
    formData.append('document_type', documentType);
    formData.append('file', file);

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
                loadMemberDocumentsModal(bookingId);
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

// Preview photo before upload
function previewPhotoBeforeUpload(event) {
    const file = event.target.files[0];
    if (!file) {
        document.getElementById('photoPreviewBeforeUpload').style.display = 'none';
        return;
    }

    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    const reader = new FileReader();

    reader.onload = function(e) {
        document.getElementById('photoPreviewImage').src = e.target.result;
        document.getElementById('photoFileName').textContent = file.name;
        document.getElementById('photoFileSize').textContent = fileSize;
        document.getElementById('photoPreviewBeforeUpload').style.display = 'block';
    };

    reader.readAsDataURL(file);
}

// Preview passport before upload
function previewPassportBeforeUpload(event) {
    const file = event.target.files[0];
    if (!file) {
        document.getElementById('passportPreviewBeforeUpload').style.display = 'none';
        return;
    }

    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    const fileExt = file.name.split('.').pop().toLowerCase();

    if (fileExt === 'pdf') {
        // Show PDF indicator
        document.getElementById('passportPreviewContent').innerHTML = `
            <div class="alert alert-info" role="alert">
                <i class="feather icon-file-text mr-2" style="font-size: 48px;"></i>
                <p class="mt-2"><strong>PDF Document</strong></p>
            </div>
        `;
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
        // Show image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('passportPreviewContent').innerHTML = `
                <img src="${e.target.result}" alt="Preview" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">
            `;
        };
        reader.readAsDataURL(file);
    }

    document.getElementById('passportFileName').textContent = file.name;
    document.getElementById('passportFileSize').textContent = fileSize;
    document.getElementById('passportPreviewBeforeUpload').style.display = 'block';
}

// Preview visa before upload
function previewVisaBeforeUpload(event) {
    const file = event.target.files[0];
    if (!file) {
        document.getElementById('visaPreviewBeforeUpload').style.display = 'none';
        return;
    }

    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    const fileExt = file.name.split('.').pop().toLowerCase();

    if (fileExt === 'pdf') {
        // Show PDF indicator
        document.getElementById('visaPreviewContent').innerHTML = `
            <div class="alert alert-info" role="alert">
                <i class="feather icon-file-text mr-2" style="font-size: 48px;"></i>
                <p class="mt-2"><strong>PDF Document</strong></p>
            </div>
        `;
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
        // Show image preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('visaPreviewContent').innerHTML = `
                <img src="${e.target.result}" alt="Preview" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">
            `;
        };
        reader.readAsDataURL(file);
    }

    document.getElementById('visaFileName').textContent = file.name;
    document.getElementById('visaFileSize').textContent = fileSize;
    document.getElementById('visaPreviewBeforeUpload').style.display = 'block';
}

// Load documents in modal
function loadMemberDocumentsModal(bookingId) {
    document.getElementById('documentBookingId').value = bookingId;

    fetch(`../api/get_member_documents.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show photo
                if (data.photo_path) {
                    document.getElementById('photoPreview').style.display = 'block';
                    document.getElementById('photoImage').src = data.photo_path;
                    document.getElementById('photoUploadSection').style.display = 'none';
                } else {
                    document.getElementById('photoPreview').style.display = 'none';
                    document.getElementById('photoUploadSection').style.display = 'block';
                }

                // Show passport
                if (data.passport_path) {
                    document.getElementById('passportPreview').style.display = 'block';
                    document.getElementById('passportPath').value = data.passport_path;
                    
                    let content = '';
                    if (data.passport_path.endsWith('.pdf')) {
                        content = '<p><i class="feather icon-file-text mr-2"></i>PDF Document</p>';
                    } else {
                        content = `<img src="${data.passport_path}" alt="Passport" class="img-fluid rounded" style="max-height: 250px;">`;
                    }
                    document.getElementById('passportContent').innerHTML = content;
                    document.getElementById('passportUploadSection').style.display = 'none';
                } else {
                    document.getElementById('passportPreview').style.display = 'none';
                    document.getElementById('passportUploadSection').style.display = 'block';
                }

                // Show visa
                if (data.visa_path) {
                    document.getElementById('visaPreview').style.display = 'block';
                    document.getElementById('visaPath').value = data.visa_path;
                    
                    let content = '';
                    if (data.visa_path.endsWith('.pdf')) {
                        content = '<p><i class="feather icon-file-text mr-2"></i>PDF Document</p>';
                    } else {
                        content = `<img src="${data.visa_path}" alt="Visa" class="img-fluid rounded" style="max-height: 250px;">`;
                    }
                    document.getElementById('visaContent').innerHTML = content;
                    document.getElementById('visaUploadSection').style.display = 'none';
                } else {
                    document.getElementById('visaPreview').style.display = 'none';
                    document.getElementById('visaUploadSection').style.display = 'block';
                }
            }
        })
        .catch(error => console.error('Error loading documents:', error));
}
</script>
