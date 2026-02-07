/**
 * Open member documents modal for photo and passport upload
 */
function openMemberDocumentsModal(bookingId, memberName) {
    // Set the modal title
    const modal = document.getElementById('memberDocumentsModal');
    
    // Load documents and show modal
    loadMemberDocumentsModal(bookingId);
    
    // Show the modal
    $('#memberDocumentsModal').modal('show');
}
