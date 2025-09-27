// Penalties Management
function editPenalties(ticketId, sold, supplierPenalty, servicePenalty, currency) {
    // Set values in the modal
    $('#editTicketIdPenalties').val(ticketId);
    $('#editSoldAmount').val(sold);
    $('#editSupplierPenalty').val(supplierPenalty);
    $('#editServicePenalty').val(servicePenalty);
    $('#penaltyCurrency, #penaltyCurrency2, #penaltyCurrency3, #penaltyCurrency4').text(currency);
    
    // Calculate initial refund
    calculateEditPenalties();
    
    // Show modal
    $('#editPenaltiesModal').modal('show');
}

function calculateEditPenalties() {
    const sold = parseFloat($('#editSoldAmount').val()) || 0;
    const supplierPenalty = parseFloat($('#editSupplierPenalty').val()) || 0;
    const servicePenalty = parseFloat($('#editServicePenalty').val()) || 0;
    
    // Calculate total penalty
    const totalPenalty = supplierPenalty + servicePenalty;
    
    // Update total penalty field
    $('#editTotalPenalty').val(totalPenalty.toFixed(2));
    
    // Calculate refund amount
    const refund = sold - totalPenalty;
    $('#calculatedRefund').val(refund.toFixed(2));
}

function updatePenalties() {
    const formData = {
        ticket_id: $('#editTicketIdPenalties').val(),
        supplier_penalty: $('#editSupplierPenalty').val(),
        service_penalty: $('#editServicePenalty').val(),
        refund_amount: $('#calculatedRefund').val()
    };

    $.ajax({
        url: 'update_refund_penalties.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    alert('Penalties updated successfully');
                    $('#editPenaltiesModal').modal('hide');
                    location.reload(); // Reload to show updated values
                } else {
                    alert('Error updating penalties: ' + (result.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                alert('Error processing the request');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Error updating penalties');
        }
    });
} 