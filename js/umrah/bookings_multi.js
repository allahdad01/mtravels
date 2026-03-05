/**
 * Multi-Member Form Submission Handler
 * Handles form validation and submission for multiple members
 */

$(document).off('submit', '#umrahForm').on('submit', '#umrahForm', function(event) {
    event.preventDefault();

    // Validate form before submission
    if (!validateForm()) {
        return false;
    }

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Members...';

    // Get member count for UI feedback
    const memberCount = $('#membersContainer .card').length;

    let formData = new FormData(event.target);

    fetch("../api/umrah/add_umrah_multi.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const successMsg = `${memberCount} member(s) added successfully`;
            showToast('success', successMsg);
            
            const familyId = $('#familyId').val();
            event.target.reset();
            $('#umrahModal').modal('hide');
            
            setTimeout(() => {
                // Reload the family members section
                if (familyId && typeof loadFamilyMembers === 'function') {
                    loadFamilyMembers(familyId);
                }
                // Also refresh the main families table for updated counts
                if (typeof refreshFamiliesTable === 'function') {
                    refreshFamiliesTable();
                }
            }, 500);
        } else {
            showToast('error', data.message || 'Failed to add members');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while adding members');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});
