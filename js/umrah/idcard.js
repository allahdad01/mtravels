// Complete ID Card Generation System - Fixed Version

// Global variable to store selected pilgrims
let selectedPilgrims = [];

// Function to select pilgrim for ID card (called from dropdown)
function selectForIdCard(bookingId, pilgrName) {
    
    // Check if pilgrim is already selected
    const existingIndex = selectedPilgrims.findIndex(p => p.id == bookingId);
    
    if (existingIndex > -1) {
        // Remove if already selected
        selectedPilgrims.splice(existingIndex, 1);
        showToast('info', 'Pilgrim removed from ID card selection');
    } else {
        // Add if not selected and under limit
         if (selectedPilgrims.length >= 8) {
             showToast('warning', 'You can only select up to 8 pilgrims for ID cards.');
             return;
         }
        
        selectedPilgrims.push({
            id: bookingId,
            name: pilgrName,
            photoPath: null,
            visaPath: null
        });
        
        // Fetch member documents (photo and visa)
        fetchMemberDocuments(bookingId);
        
        showToast('success', 'Pilgrim selected for ID card generation');
    }
    
    // Update UI
    updateIdCardSelection();
}

// Bulk select all members from a family for ID cards (up to 8 limit)
function selectAllFamilyForIdCard(familyId) {
    const membersSection = document.getElementById('members-grid-' + familyId);
    if (!membersSection) return;

    const btn = document.querySelector('.selectAllIdCardBtn[data-family-id="' + familyId + '"]');
    const checkboxes = membersSection.querySelectorAll('.member-checkbox');
    const activeCheckboxes = Array.from(checkboxes).filter(cb => cb.getAttribute('data-status') === 'active');
    
    // Check if all active members are already selected
    const allSelected = activeCheckboxes.every(cb => {
        const bookingId = cb.getAttribute('data-booking-id');
        return selectedPilgrims.findIndex(p => p.id == bookingId) !== -1;
    });

    if (allSelected) {
        // Deselect all
        activeCheckboxes.forEach(checkbox => {
            const bookingId = checkbox.getAttribute('data-booking-id');
            const index = selectedPilgrims.findIndex(p => p.id == bookingId);
            if (index !== -1) {
                selectedPilgrims.splice(index, 1);
            }
            checkbox.checked = false;
        });
        if (btn) btn.innerHTML = '<i class="fas fa-id-card mr-1"></i>Select All for ID Cards';
        showToast('info', 'Deselected all members from ID cards');
    } else {
        // Select all active members
        let addedCount = 0;
        let skippedCount = 0;

        activeCheckboxes.forEach(checkbox => {
            // Stop if limit reached
            if (selectedPilgrims.length >= 8) {
                skippedCount++;
                return;
            }

            const bookingId = checkbox.getAttribute('data-booking-id');
            const memberName = checkbox.closest('.member-card')?.querySelector('.member-name')?.textContent || 'Member';
            
            const existingIndex = selectedPilgrims.findIndex(p => p.id == bookingId);
            if (existingIndex === -1) {
                selectedPilgrims.push({
                    id: bookingId,
                    name: memberName,
                    photoPath: null,
                    visaPath: null
                });
                // Fetch member documents
                fetchMemberDocuments(bookingId);
                addedCount++;
            }
            checkbox.checked = true;
        });

        let message = `Added ${addedCount} pilgrim(s) for ID cards`;
        if (skippedCount > 0) {
            message += ` (${skippedCount} skipped - limit of 8 reached)`;
        }
        if (addedCount > 0) {
            showToast('success', message);
        }
        if (btn) btn.innerHTML = '<i class="fas fa-id-card mr-1"></i>Deselect All for ID Cards';
    }
    updateIdCardSelection();
}

// Fetch member documents from the API
function fetchMemberDocuments(bookingId) {
    fetch(`../api/get_member_documents.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Find the pilgrim and update their document paths
                const pilgrim = selectedPilgrims.find(p => p.id == bookingId);
                if (pilgrim) {
                    pilgrim.photoPath = data.photo_path || null;
                    pilgrim.visaPath = data.visa_path || null;
                }
                // Update UI to show document status
                updatePhotoUploadContainer();
            }
        })
        .catch(error => console.error('Error fetching member documents:', error));
}

// Function to update ID card selection UI
function updateIdCardSelection() {
    const floatingButton = document.getElementById('idCardFloatingButton');
    const selectionCount = document.getElementById('idCardSelectionCount');
    const modalSelectedCount = document.getElementById('selectedCount');
    const generateBtn = document.getElementById('generateIdCardsBtn');
    
    // Update count displays
    if (selectionCount) {
        selectionCount.textContent = selectedPilgrims.length;
    }
    if (modalSelectedCount) {
        modalSelectedCount.textContent = selectedPilgrims.length;
    }
    
    // Show/hide floating button
    if (floatingButton) {
        floatingButton.style.display = selectedPilgrims.length > 0 ? 'block' : 'none';
    }
    
    // Enable/disable generate button
    if (generateBtn) {
        generateBtn.disabled = selectedPilgrims.length === 0;
    }
    
    // Update selected pilgrims list in modal
    updateSelectedPilgrimsList();
    
    // Update photo upload container
    updatePhotoUploadContainer();
    

}

// Function to update selected pilgrims list display
function updateSelectedPilgrimsList() {
    const listContainer = document.getElementById('selectedPilgrimsList');
    if (!listContainer) return;
    
    listContainer.innerHTML = '';
    
    if (selectedPilgrims.length === 0) {
        listContainer.innerHTML = '<div class="col-12 text-center text-muted py-3"><i class="fas fa-info-circle mr-2"></i>No pilgrims selected yet</div>';
        return;
    }
    
    selectedPilgrims.forEach((pilgrim, index) => {
        const pilgrimCard = document.createElement('div');
        pilgrimCard.className = 'col-md-4 col-sm-6 mb-2';
        pilgrimCard.innerHTML = `
            <div class="card border-info shadow-sm">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <small class="text-info font-weight-bold d-block">${pilgrim.name}</small>
                            <small class="text-muted">#${index + 1}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="removeFromIdCardSelection(${pilgrim.id})" title="Remove">
                            <i class="feather icon-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        listContainer.appendChild(pilgrimCard);
    });
}

// Function to remove pilgrim from selection
function removeFromIdCardSelection(bookingId) {
    const index = selectedPilgrims.findIndex(p => p.id == bookingId);
    if (index > -1) {
        selectedPilgrims.splice(index, 1);
        updateIdCardSelection();
        showToast('Pilgrim removed from selection', 'info');
    }
}

// Function to update photo upload container
function updatePhotoUploadContainer() {
    const container = document.getElementById('photoUploadContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (selectedPilgrims.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted">No pilgrims selected</div>';
        return;
    }
    
    selectedPilgrims.forEach(pilgrim => {
        const photoDiv = document.createElement('div');
        photoDiv.className = 'col-md-6 mb-3';
        
        // Check if photo exists from member documents
        const hasPhoto = pilgrim.photoPath !== null && pilgrim.photoPath !== undefined;
        const hasVisa = pilgrim.visaPath !== null && pilgrim.visaPath !== undefined;
        
        let photoStatus = '';
        if (hasPhoto) {
            photoStatus = `<div class="alert alert-success alert-sm mb-2">
                <i class="feather icon-check-circle mr-1"></i>Photo from member documents
            </div>`;
        }
        
        let visaStatus = '';
        if (hasVisa) {
            visaStatus = `<div class="alert alert-success alert-sm mb-2">
                <i class="feather icon-check-circle mr-1"></i>Visa from member documents
            </div>`;
        } else {
            visaStatus = `
                <div class="mb-2">
                    <div class="custom-file mb-1">
                        <input type="file" class="custom-file-input" id="visa_file_${pilgrim.id}" accept="image/*,.pdf" onchange="previewVisaForIdCard(this, ${pilgrim.id})">
                        <label class="custom-file-label" for="visa_file_${pilgrim.id}">Choose visa file</label>
                    </div>
                    <small class="text-muted d-block mb-1">Supported: JPG, PNG, PDF (Max 5MB)</small>
                    <button type="button" class="btn btn-success btn-sm w-100" onclick="uploadVisaForIdCard(${pilgrim.id})">
                        <i class="feather icon-upload mr-1"></i>Upload Visa
                    </button>
                    <div id="visaPreview_${pilgrim.id}" class="mt-1"></div>
                </div>
            `;
        }
        
        photoDiv.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">${pilgrim.name}</h6>
                    ${photoStatus}
                    ${visaStatus}
                    <div class="mb-2">
                        <div class="custom-file mb-1">
                            <input type="file" class="custom-file-input" id="photo_file_${pilgrim.id}" accept="image/*" onchange="previewPhotoForIdCard(this, ${pilgrim.id})">
                            <label class="custom-file-label" for="photo_file_${pilgrim.id}">Choose photo file</label>
                        </div>
                        <small class="text-muted d-block mb-1">Supported: JPG, PNG (Max 5MB)</small>
                        <button type="button" class="btn btn-primary btn-sm w-100" onclick="uploadPhotoForIdCard(${pilgrim.id})">
                            <i class="feather icon-upload mr-1"></i>Upload Photo
                        </button>
                        <div id="photoPreview_${pilgrim.id}" class="mt-1"></div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(photoDiv);
    });
}

// Function to preview uploaded photos
function previewPhoto(input, pilgrimId) {
    const previewDiv = document.getElementById(`photoPreview_${pilgrimId}`);
    if (!previewDiv) return;
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewDiv.innerHTML = `
                <img src="${e.target.result}" 
                     alt="Photo Preview" 
                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; border: 2px solid #28a745;">
                <div class="mt-1">
                    <small class="text-success">
                        <i class="feather icon-check mr-1"></i>Photo uploaded
                    </small>
                </div>
            `;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Function to preview selected photo file
function previewPhotoForIdCard(input, pilgrimId) {
    const previewDiv = document.getElementById(`photoPreview_${pilgrimId}`);
    if (!previewDiv) return;
    if (!input.files || !input.files[0]) { previewDiv.innerHTML = ''; return; }
    const file = input.files[0];
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewDiv.innerHTML = `<img src="${e.target.result}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:2px solid #28a745;">`;
        };
        reader.readAsDataURL(file);
    }
}

// Function to upload photo for ID card pilgrim
function uploadPhotoForIdCard(pilgrimId) {
    const pilgrim = selectedPilgrims.find(p => p.id == pilgrimId);
    if (!pilgrim) return;
    const fileInput = document.getElementById(`photo_file_${pilgrimId}`);
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        showToast('warning', 'Please select a photo file first.');
        return;
    }
    const file = fileInput.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    const maxSize = 5 * 1024 * 1024;
    if (!allowedTypes.includes(file.type)) {
        showToast('error', 'Invalid file type. Allowed: JPG, PNG, GIF.');
        return;
    }
    if (file.size > maxSize) {
        showToast('error', 'File too large. Maximum 5MB.');
        return;
    }
    const uploadBtn = document.querySelector(`button[onclick="uploadPhotoForIdCard(${pilgrimId})"]`);
    if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="feather icon-loader mr-1"></i>Uploading...'; }
    const formData = new FormData();
    formData.append('booking_id', pilgrimId);
    formData.append('document_type', 'photo');
    formData.append('file', file);
    fetch('../api/upload_member_documents.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                pilgrim.photoPath = data.file_path;
                updatePhotoUploadContainer();
                showToast('success', 'Photo uploaded successfully!');
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        })
        .catch(err => showToast('error', 'Error uploading photo: ' + err.message))
        .finally(() => {
            if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="feather icon-upload mr-1"></i>Upload Photo'; }
        });
}

// Function to preview selected visa file
function previewVisaForIdCard(input, pilgrimId) {
    const previewDiv = document.getElementById(`visaPreview_${pilgrimId}`);
    if (!previewDiv) return;
    if (!input.files || !input.files[0]) { previewDiv.innerHTML = ''; return; }
    const file = input.files[0];
    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
    const isPdf = file.name.toLowerCase().endsWith('.pdf');
    if (isPdf) {
        previewDiv.innerHTML = `<div class="alert alert-info py-1 px-2 mb-0" style="font-size:12px"><i class="feather icon-file-text mr-1"></i>PDF: ${file.name} (${fileSize})</div>`;
    } else {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewDiv.innerHTML = `<img src="${e.target.result}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;border:2px solid #28a745;">`;
        };
        reader.readAsDataURL(file);
    }
}

// Function to upload visa for ID card pilgrim
function uploadVisaForIdCard(pilgrimId) {
    const pilgrim = selectedPilgrims.find(p => p.id == pilgrimId);
    if (!pilgrim) return;
    const fileInput = document.getElementById(`visa_file_${pilgrimId}`);
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        showToast('warning', 'Please select a visa file first.');
        return;
    }
    const file = fileInput.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    const maxSize = 5 * 1024 * 1024;
    if (!allowedTypes.includes(file.type)) {
        showToast('error', 'Invalid file type. Allowed: JPG, PNG, GIF, PDF.');
        return;
    }
    if (file.size > maxSize) {
        showToast('error', 'File too large. Maximum 5MB.');
        return;
    }
    const uploadBtn = document.querySelector(`button[onclick="uploadVisaForIdCard(${pilgrimId})"]`);
    if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="feather icon-loader mr-1"></i>Uploading...'; }
    const formData = new FormData();
    formData.append('booking_id', pilgrimId);
    formData.append('document_type', 'visa');
    formData.append('file', file);
    fetch('../api/upload_member_documents.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                pilgrim.visaPath = data.file_path;
                updatePhotoUploadContainer();
                showToast('success', 'Visa uploaded successfully!');
            } else {
                throw new Error(data.message || 'Upload failed');
            }
        })
        .catch(err => showToast('error', 'Error uploading visa: ' + err.message))
        .finally(() => {
            if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="feather icon-upload mr-1"></i>Upload Visa'; }
        });
}

// Event listener for floating button to show modal
document.addEventListener('DOMContentLoaded', function() {
    const showModalBtn = document.getElementById('showIdCardModal');
    if (showModalBtn) {
        showModalBtn.addEventListener('click', function() {

            $('#idCardModal').modal('show');
        });
    }
});

// Event listener for generate ID cards button
document.addEventListener('DOMContentLoaded', function() {
    const generateBtn = document.getElementById('generateIdCardsBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {


            
            // Check if pilgrims are selected
            if (!selectedPilgrims || selectedPilgrims.length === 0) {
                showToast('warning', 'Please select at least one pilgrim for ID cards.');
                return;
            }
            
            // Update the hidden input with selected pilgrim data
            const selectedPilgrimsInput = document.getElementById('selectedPilgrimsInput');
            if (selectedPilgrimsInput) {
                selectedPilgrimsInput.value = JSON.stringify(selectedPilgrims);

            } else {

                showToast('error', 'Form element not found. Please refresh the page.');
                return;
            }
            
            // Validate required fields
            const title = document.getElementById('idCardTitle').value.trim();
            const validityDays = document.getElementById('idCardValidityDays').value;
            
            if (!title) {
                showToast('warning', 'Please enter ID card title.');
                return;
            }
            
            if (!validityDays || validityDays < 1 || validityDays > 90) {
                showToast('warning', 'Please enter a valid number of days (1-90).');
                return;
            }
            
            // Check if any photos are missing from server documents
            let missingPhotos = [];
            selectedPilgrims.forEach(pilgrim => {
                const hasPhoto = pilgrim.photoPath !== null && pilgrim.photoPath !== undefined;
                if (!hasPhoto) {
                    missingPhotos.push(pilgrim.name);
                }
            });
            
            if (missingPhotos.length > 0) {
                Swal.fire({
                    target: document.getElementById('idCardModal'),
                    title: 'Missing Photos',
                    html: `The following pilgrims don't have photos:<br><strong>${missingPhotos.join(', ')}</strong><br><br>Continue with default photos?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Continue',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitIdCardForm();
                    }
                });
            } else {
                // All photos are provided, submit the form
                submitIdCardForm();
            }
        });
    }
});

// Function to submit the ID card form
function submitIdCardForm() {
    const form = document.getElementById('idCardForm');
    if (!form) {

        showToast('error', 'Form not found. Please refresh the page and try again.');
        return;
    }
    
    try {
        // Show loading indicator
        showToast('info', 'Generating ID Cards... Please wait.');
        

        
        // Submit the form
        form.submit();
        
        // Close the modal and show success after a short delay
        setTimeout(() => {
            // Close the modal
            $('#idCardModal').modal('hide');
            
            // Clear selection
            selectedPilgrims = [];
            updateIdCardSelection();
            
            // Show success message
            showToast('success', 'ID cards have been generated successfully!');
        }, 2000);
        
    } catch (error) {

        showToast('error', 'An error occurred while generating ID cards. Please try again.');
    }
}

// Helper function to show toast messages
function showToast(message, type = 'info') {
    // You can replace this with your preferred toast notification system

    
    // If you have a toast system, use it here
    // For now, we'll use a simple alert for critical messages
    if (type === 'error') {
        alert(message);
    }
}

// Initialize the system when page loads
document.addEventListener('DOMContentLoaded', function() {

    
    // Make sure selectedPilgrims is available globally
    window.selectedPilgrims = selectedPilgrims;
    window.selectForIdCard = selectForIdCard;
    window.removeFromIdCardSelection = removeFromIdCardSelection;
    window.previewPhotoForIdCard = previewPhotoForIdCard;
    window.uploadPhotoForIdCard = uploadPhotoForIdCard;
    window.uploadVisaForIdCard = uploadVisaForIdCard;
    
    // Initialize UI
    updateIdCardSelection();
});

// Debug function for testing
window.debugIdCard = function() {






};
