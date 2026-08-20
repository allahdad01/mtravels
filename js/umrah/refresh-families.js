// Function to refresh the families table without page reload
function refreshFamiliesTable(page = 1) {
    const familyGrid = document.querySelector('.families-grid') || document.getElementById('familiesContainer');
    if (!familyGrid) return;

    fetch(`../api/umrah/fetch_families.php?page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Get the container
                let container = familyGrid;
                
                // Clear existing cards (but keep structure)
                const existingCards = container.querySelectorAll('.family-card');
                existingCards.forEach(card => card.remove());
                
                // Render new cards
                data.families.forEach(family => {
                    const card = createFamilyCard(family);
                    container.appendChild(card);
                });

                // Reinitialize any event listeners
                attachFamilyCardListeners();
            }
        })
        .catch(error => {
            console.error('Error refreshing families table:', error);
        });
}

// Function to create a family card element
function createFamilyCard(family) {
    const card = document.createElement('div');
    card.className = 'family-card';
    card.dataset.familyId = family.family_id;
    
    const statusBadge = family.status === 'active' ? 'success' : 'warning';
    const visaBadge = family.visa_status ? family.visa_status : 'pending';

    const canEditFamily = typeof window.UMRAH_CAN_EDIT_FAMILY === 'undefined' || window.UMRAH_CAN_EDIT_FAMILY;
    const canDeleteFamily = typeof window.UMRAH_CAN_DELETE_FAMILY === 'undefined' || window.UMRAH_CAN_DELETE_FAMILY;
    
    card.innerHTML = `
        <div class="card family-card" style="height: 100%;">
            <div class="card-header bg-gradient">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">${escapeHtml(family.head_of_family)}</h6>
                        <small class="text-muted">Package: ${escapeHtml(family.package_type)}</small>
                    </div>
                    <span class="badge badge-${statusBadge}">${family.status}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label">Contact:</span>
                    <span class="value">${escapeHtml(family.contact)}</span>
                </div>
                <div class="info-row">
                    <span class="label">Location:</span>
                    <span class="value">${escapeHtml(family.location)}</span>
                </div>
                <div class="info-row">
                    <span class="label">Members:</span>
                    <span class="value">${family.member_count} (${family.approved_count} approved)</span>
                </div>
                <div class="info-row">
                    <span class="label">Visa Status:</span>
                    <span class="badge badge-info">${escapeHtml(family.visa_status)}</span>
                </div>
            </div>
            <div class="card-footer bg-light">
                <div class="btn-group btn-group-sm w-100" role="group">
                    <button type="button" class="btn btn-outline-primary view-members-btn" data-family-id="${family.family_id}">
                        <i class="fas fa-users mr-1"></i> Members
                    </button>
                    ${canEditFamily ? `<button type="button" class="btn btn-outline-secondary edit-family-btn" data-family-id="${family.family_id}">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </button>` : ''}
                    ${canDeleteFamily ? `<button type="button" class="btn btn-outline-danger delete-family-btn" data-family-id="${family.family_id}">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>` : ''}
                </div>
            </div>
        </div>
    `;
    
    return card;
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

// Function to reattach event listeners to new cards
function attachFamilyCardListeners() {
    // Reattach view members listeners
    document.querySelectorAll('.view-members-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const familyId = this.getAttribute('data-family-id');
            if (typeof viewFamilyMembers === 'function') {
                viewFamilyMembers(familyId);
            }
        });
    });
    
    // Reattach edit family listeners
    document.querySelectorAll('.edit-family-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const familyId = this.getAttribute('data-family-id');
            if (typeof editFamily === 'function') {
                editFamily(familyId);
            }
        });
    });
    
    // Reattach delete family listeners
    document.querySelectorAll('.delete-family-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const familyId = this.getAttribute('data-family-id');
            if (typeof deleteFamily === 'function') {
                deleteFamily(familyId);
            }
        });
    });
}
