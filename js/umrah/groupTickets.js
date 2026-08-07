// ================== Group Ticket System ==================

// Global variable
let selectedGroupMembers = [];

// Select member for group ticket
function selectForGroupTicket(bookingId, memberName) {
    const existingIndex = selectedGroupMembers.findIndex(m => m.id == bookingId);

    if (existingIndex > -1) {
        selectedGroupMembers.splice(existingIndex, 1);
        showToast('info', 'Member removed from group ticket selection');
    } else {
        selectedGroupMembers.push({ id: bookingId, name: memberName });
        showToast('success', 'Member added for group ticket');
    }

    updateGroupTicketSelection();
}

// Bulk select all members from a family for group ticket
function selectAllFamilyForGroupTicket(familyId) {
    const membersSection = document.getElementById('members-grid-' + familyId);
    if (!membersSection) return;

    const btn = document.querySelector('.selectAllGroupTicketBtn[data-family-id="' + familyId + '"]');
    const checkboxes = membersSection.querySelectorAll('.member-checkbox');
    const activeCheckboxes = Array.from(checkboxes).filter(cb => cb.getAttribute('data-status') === 'active');
    
    // Check if all active members are already selected
    const allSelected = activeCheckboxes.every(cb => {
        const bookingId = cb.getAttribute('data-booking-id');
        return selectedGroupMembers.findIndex(m => m.id == bookingId) !== -1;
    });

    if (allSelected) {
        // Deselect all
        activeCheckboxes.forEach(checkbox => {
            const bookingId = checkbox.getAttribute('data-booking-id');
            const index = selectedGroupMembers.findIndex(m => m.id == bookingId);
            if (index !== -1) {
                selectedGroupMembers.splice(index, 1);
            }
            checkbox.checked = false;
        });
        if (btn) btn.innerHTML = '<i class="fas fa-plane mr-1"></i>Select All for Group Ticket';
        showToast('info', 'Deselected all members from group ticket');
    } else {
        // Select all active members
        let addedCount = 0;
        activeCheckboxes.forEach(checkbox => {
            const bookingId = checkbox.getAttribute('data-booking-id');
            const memberName = checkbox.closest('.member-card')?.querySelector('.member-name')?.textContent || 'Member';
            
            const existingIndex = selectedGroupMembers.findIndex(m => m.id == bookingId);
            if (existingIndex === -1) {
                selectedGroupMembers.push({ id: bookingId, name: memberName });
                addedCount++;
            }
            checkbox.checked = true;
        });

        if (addedCount > 0) {
            showToast('success', `Added ${addedCount} member(s) to group ticket`);
        }
        if (btn) btn.innerHTML = '<i class="fas fa-plane mr-1"></i>Deselect All for Group Ticket';
    }
    updateGroupTicketSelection();
}

// Update selection UI
function updateGroupTicketSelection() {
    const floatingButton = document.getElementById('groupTicketFloatingButton');
    const selectionCount = document.getElementById('groupTicketSelectionCount');
    const modalSelectedCount = document.getElementById('selectedGroupCount');
    const generateBtn = document.getElementById('generateGroupTicketBtn');

    if (selectionCount) selectionCount.textContent = selectedGroupMembers.length;
    if (modalSelectedCount) modalSelectedCount.textContent = selectedGroupMembers.length;
    if (floatingButton) floatingButton.style.display = selectedGroupMembers.length > 0 ? 'block' : 'none';
    if (generateBtn) generateBtn.disabled = selectedGroupMembers.length === 0;

    updateSelectedGroupMembersList();
}

// Display selected members
function updateSelectedGroupMembersList() {
    const listContainer = document.getElementById('selectedGroupMembersList');
    if (!listContainer) return;

    listContainer.innerHTML = '';

    if (selectedGroupMembers.length === 0) {
        listContainer.innerHTML = '<div class="col-12 text-center text-muted py-3"><i class="fas fa-info-circle mr-2"></i>No members selected yet</div>';
        return;
    }

    selectedGroupMembers.forEach(member => {
        const memberCard = document.createElement('div');
        memberCard.className = 'col-md-4 col-sm-6 mb-2';
        memberCard.innerHTML = `
            <div class="card border-success shadow-sm">
                <div class="card-body p-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <small class="text-success font-weight-bold d-block">${member.name}</small>
                            <small class="text-muted">ID: ${member.id}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="removeFromGroupTicket(${member.id})" title="Remove">
                            <i class="feather icon-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        listContainer.appendChild(memberCard);
    });
}

// Remove member
function removeFromGroupTicket(bookingId) {
    const index = selectedGroupMembers.findIndex(m => m.id == bookingId);
    if (index > -1) {
        selectedGroupMembers.splice(index, 1);
        updateGroupTicketSelection();
        showToast('info', 'Member removed from selection');
    }
}

// Submit form - save group ticket to database and generate PDF
function submitGroupTicketForm() {
    const form = document.getElementById('groupTicketForm');
    if (!form) return;

    // Get flight dates
    const flightDate = document.getElementById('groupFlightDate').value;
    const returnDate = document.getElementById('groupReturnDate').value;

    if (!flightDate || !returnDate) {
        showToast('error', 'Please fill in both flight date and return date');
        return;
    }

    // Validate dates
    const flightDateTime = new Date(flightDate);
    const returnDateTime = new Date(returnDate);

    if (returnDateTime <= flightDateTime) {
        showToast('error', 'Return date must be after flight date');
        return;
    }

    if (selectedGroupMembers.length === 0) {
        showToast('error', 'Please select members first');
        return;
    }

    // Prepare form data
    const formData = new FormData(form);
    formData.append('flight_date', flightDate);
    formData.append('return_date', returnDate);
    formData.append('selected_members', JSON.stringify(selectedGroupMembers));

    // Get button to show loading state
    const btn = document.getElementById('generateGroupTicketBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

    // Save group ticket to database
    fetch('../api/umrah/save_group_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Group ticket saved successfully!');
            
            // Now generate PDF with the saved ticket
            setTimeout(() => {
                generateGroupTicketPDF(data.ticket_id);
                
                // Reset form
                selectedGroupMembers = [];
                updateGroupTicketSelection();
                document.getElementById('groupTicketForm').reset();
                $('#groupTicketModal').modal('hide');
                
                // Reload page after 2 seconds
                setTimeout(() => location.reload(), 2000);
            }, 500);
        } else {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            Swal.fire({
                target: document.getElementById('groupTicketModal'),
                icon: 'error',
                title: 'Unable to save group ticket',
                html: (data.message || 'Failed to save group ticket').replace(/\n/g, '<br>'),
                confirmButtonText: 'OK'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while saving group ticket');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

// Generate PDF from the saved group ticket
function generateGroupTicketPDF(ticketId) {
    // Open PDF generation in new window/tab
    window.open(`../api/umrah/generate_group_ticket.php?ticket_id=${ticketId}`, '_blank');
}

// Event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Show modal button
    const showGroupTicketModal = document.getElementById('showGroupTicketModal');
    if (showGroupTicketModal) {
        showGroupTicketModal.addEventListener('click', () => {
            $('#groupTicketModal').modal('show');
        });
    }

    // Generate/Save button
    const generateBtn = document.getElementById('generateGroupTicketBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (selectedGroupMembers.length === 0) {
                showToast('error', 'Please select members first');
                return;
            }
            submitGroupTicketForm();
        });
    }

    updateGroupTicketSelection();
});

// Toast helper
function showToast(type = 'info', message = '') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}
