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
    
    selectedPilgrims.forEach(pilgrim => {
        const pilgrimCard = document.createElement('div');
        pilgrimCard.className = 'col-md-3 mb-2';
        pilgrimCard.innerHTML = `
            <div class="card border-primary">
                <div class="card-body p-2 text-center">
                    <small class="text-primary font-weight-bold">${pilgrim.name}</small>
                    <button type="button" class="btn btn-sm btn-outline-danger ml-2" 
                            onclick="removeFromIdCardSelection(${pilgrim.id})" title="Remove">
                        <i class="feather icon-x"></i>
                    </button>
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
            visaStatus = `<div class="alert alert-warning alert-sm mb-2">
                <i class="feather icon-alert-triangle mr-1"></i>No visa document
            </div>`;
        }
        
        photoDiv.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">${pilgrim.name}</h6>
                    ${photoStatus}
                    ${visaStatus}
                    <div class="form-group">
                        <label for="photo_${pilgrim.id}" class="btn btn-outline-primary btn-sm btn-block">
                            <i class="feather icon-camera mr-1"></i>Upload Photo (Optional)
                        </label>
                        <input type="file" 
                               id="photo_${pilgrim.id}" 
                               name="photo_${pilgrim.id}" 
                               accept="image/*" 
                               style="display: none;"
                               onchange="previewPhoto(this, ${pilgrim.id})">
                    </div>
                    <div id="photoPreview_${pilgrim.id}" class="mt-2">
                        <!-- Photo preview will appear here -->
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
            
            // Check if any photos are missing
            let missingPhotos = [];
            selectedPilgrims.forEach(pilgrim => {
                const photoInput = document.getElementById(`photo_${pilgrim.id}`);
                if (!photoInput || !photoInput.files || !photoInput.files.length) {
                    missingPhotos.push(pilgrim.name);
                }
            });
            
            if (missingPhotos.length > 0) {
                Swal.fire({
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
    window.previewPhoto = previewPhoto;
    
    // Initialize UI
    updateIdCardSelection();
});

// Debug function for testing
window.debugIdCard = function() {






};
