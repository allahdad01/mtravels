// ================== Group Ticket System ==================

// Global variable
let selectedGroupMembers = [];

// Select member for group ticket
function selectForGroupTicket(bookingId, memberName) {
    const existingIndex = selectedGroupMembers.findIndex(m => m.id == bookingId);

    if (existingIndex > -1) {
        selectedGroupMembers.splice(existingIndex, 1);
        showToast('Member removed from group ticket selection', 'info');
    } else {
        selectedGroupMembers.push({ id: bookingId, name: memberName });
        showToast('Member added for group ticket', 'success');
    }

    updateGroupTicketSelection();
}

// Update selection UI
function updateGroupTicketSelection() {
    const floatingButton = document.getElementById('groupTicketFloatingButton');
    const selectionCount = document.getElementById('groupTicketSelectionCount');
    const modalSelectedCount = document.getElementById('groupSelectedCount');
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

    selectedGroupMembers.forEach(member => {
        const memberCard = document.createElement('div');
        memberCard.className = 'col-md-3 mb-2';
        memberCard.innerHTML = `
            <div class="card border-primary">
                <div class="card-body p-2 text-center">
                    <small class="text-primary font-weight-bold">${member.name}</small>
                    <button type="button" class="btn btn-sm btn-outline-danger ml-2" 
                            onclick="removeFromGroupTicket(${member.id})" title="Remove">
                        <i class="feather icon-x"></i>
                    </button>
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
        showToast('Member removed from selection', 'info');
    }
}

// Submit form
function submitGroupTicketForm() {
    const form = document.getElementById('groupTicketForm');
    if (!form) return;

    const hiddenInput = document.getElementById('selectedGroupMembersInput');
    hiddenInput.value = JSON.stringify(selectedGroupMembers);

    form.submit();

    // Reset
    selectedGroupMembers = [];
    updateGroupTicketSelection();
    $('#groupTicketModal').modal('hide');
    Swal.fire({
        icon: 'success',
        title: 'Group Ticket Generated',
        text: 'Your group ticket has been generated successfully!',
        timer: 3000,
        showConfirmButton: false
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('showGroupTicketModal')?.addEventListener('click', () => {
        $('#groupTicketModal').modal('show');
    });

    document.getElementById('generateGroupTicketBtn')?.addEventListener('click', () => {
        if (selectedGroupMembers.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No Members', text: 'Please select members first.' });
            return;
        }
        submitGroupTicketForm();
    });

    updateGroupTicketSelection();
});

// Toast helper
function showToast(message, type = 'info') {
    console.log(`Toast [${type}]: ${message}`);
}
