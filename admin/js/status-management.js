console.log('Status Management JS loaded successfully');

document.addEventListener('DOMContentLoaded', function() {
    // Function to create and show a confirmation modal
    function showConfirmationModal(title, message, confirmCallback) {
        // Remove any existing confirmation modal
        const existingModal = document.getElementById('statusConfirmationModal');
        if (existingModal) {
            existingModal.parentNode.removeChild(existingModal);
        }
        
        // Create modal HTML
        const modalHTML = `
        <div class="modal fade" id="statusConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="statusConfirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="statusConfirmationModalLabel">
                            <i class="feather icon-alert-triangle mr-2"></i>${title}
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" id="confirmStatusChangeBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        // Append modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Show the modal
        $('#statusConfirmationModal').modal('show');
        
        // Add event listener to the confirm button
        document.getElementById('confirmStatusChangeBtn').addEventListener('click', function() {
            // Hide the modal
            $('#statusConfirmationModal').modal('hide');
            
            // Execute the callback after modal is hidden
            $('#statusConfirmationModal').on('hidden.bs.modal', function() {
                confirmCallback();
            });
        });
    }

    // Toggle main account status
    document.querySelectorAll('.toggle-status-btn').forEach(button => {
        button.addEventListener('click', function() {
            const accountId = this.dataset.accountId;
            const currentStatus = this.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const accountName = this.closest('.account-card').querySelector('.text-primary').textContent.trim();
            
            const title = `${currentStatus === 'active' ? 'Deactivate' : 'Activate'} Account`;
            const message = `Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} the account "${accountName}"?`;
            
            showConfirmationModal(title, message, function() {
                // Send request to toggle status
                fetch('toggle_account_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        account_id: accountId,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessToast(`Account ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully!`);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showErrorToast('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorToast('An error occurred while updating the account status. Please try again.');
                });
            });
        });
    });
    
    // Toggle client status
    document.querySelectorAll('.toggle-client-status-btn').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.dataset.clientId;
            const currentStatus = this.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const clientName = this.closest('.client-card').querySelector('.text-success').textContent.trim();
            
            const title = `${currentStatus === 'active' ? 'Deactivate' : 'Activate'} Client`;
            const message = `Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} the client "${clientName}"?`;
            
            showConfirmationModal(title, message, function() {
                // Send request to toggle status
                fetch('toggle_client_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        client_id: clientId,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccessToast(`Client ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully!`);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showErrorToast('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorToast('An error occurred while updating the client status. Please try again.');
                });
            });
        });
    });
    
    // Toggle supplier status
    document.querySelectorAll('.toggle-supplier-status-btn').forEach(button => {
        button.addEventListener('click', function() {
            const supplierId = this.dataset.supplierId;
            const currentStatus = this.dataset.currentStatus;
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const supplierRow = this.closest('tr');
            const supplierName = supplierRow ? supplierRow.querySelector('.fw-medium').textContent.trim() : 'this supplier';
            
            const title = `${currentStatus === 'active' ? 'Deactivate' : 'Activate'} Supplier`;
            const message = `Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} the supplier "${supplierName}"?`;
            
            showConfirmationModal(title, message, function() {
                // Send request to toggle status
                fetch('toggle_supplier_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        supplier_id: supplierId,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())  
                .then(data => {
                    if (data.success) {
                        showSuccessToast(`Supplier ${newStatus === 'active' ? 'activated' : 'deactivated'} successfully!`);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showErrorToast('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorToast('An error occurred while updating the supplier status. Please try again.');
                });
            });
        });
    });
}); 