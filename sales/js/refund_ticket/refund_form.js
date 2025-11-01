$(document).ready(function() {

    // Calculate refund amount
    function calculateRefund() {
        const calculationMethod = $('#calculationMethod').val();
        const sold = parseFloat($('#sold').val()) || 0;
        const base = parseFloat($('#base').val()) || 0;
        const supplierPenalty = parseFloat($('#supplier_penalty').val()) || 0;
        const servicePenalty = parseFloat($('#service_penalty').val()) || 0;
        const totalPenalty = supplierPenalty + servicePenalty;
        
        let refundAmount = 0;
        if (calculationMethod === 'sold') {
            refundAmount = sold - totalPenalty;
        } else {
            refundAmount = base - supplierPenalty;
        }

        $('#totalPenalty').val(totalPenalty.toFixed(2));
        $('#refundPassengerAmount').val(Math.max(0, refundAmount).toFixed(2));
    }

    // Event listeners for penalty inputs
    $('#supplier_penalty, #service_penalty').on('input', calculateRefund);
    $('#calculationMethod').on('change', calculateRefund);

    // Form submission handler
    $('#refundTicketForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: 'handlers/save_refund_ticket.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showToast(result.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(result.message, 'error');
                    }
                } catch (error) {
                    showToast('An error occurred while processing the request', 'error');
                }
            },
            error: function() {
                showToast('Failed to communicate with the server', 'error');
            }
        });
    });

    // Edit penalties handler
    window.editPenalties = function(ticketId, sold, supplierPenalty, servicePenalty, currency) {
        $('#editTicketIdPenalties').val(ticketId);
        $('#editSoldAmount').val(sold);
        $('#editSupplierPenalty').val(supplierPenalty);
        $('#editServicePenalty').val(servicePenalty);
        $('#penaltyCurrency, #penaltyCurrency2, #penaltyCurrency3, #penaltyCurrency4').text(currency);
        calculateEditPenalties();
        $('#editPenaltiesModal').modal('show');
    };

    // Calculate edit penalties
    window.calculateEditPenalties = function() {
        const sold = parseFloat($('#editSoldAmount').val()) || 0;
        const supplierPenalty = parseFloat($('#editSupplierPenalty').val()) || 0;
        const servicePenalty = parseFloat($('#editServicePenalty').val()) || 0;
        const totalPenalty = supplierPenalty + servicePenalty;
        const refundAmount = Math.max(0, sold - totalPenalty);
        
        $('#editTotalPenalty').val(totalPenalty.toFixed(2));
        $('#calculatedRefund').val(refundAmount.toFixed(2));
    };

    // Update penalties handler
    window.updatePenalties = function() {
        const data = {
            ticket_id: $('#editTicketIdPenalties').val(),
            supplier_penalty: $('#editSupplierPenalty').val(),
            service_penalty: $('#editServicePenalty').val(),
            refund_amount: $('#calculatedRefund').val()
        };

        $.ajax({
            url: 'handlers/update_refund_penalties.php',
            type: 'POST',
            data: data,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showToast(result.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(result.message, 'error');
                    }
                } catch (error) {
                    showToast('An error occurred while updating penalties', 'error');
                }
            },
            error: function() {
                showToast('Failed to communicate with the server', 'error');
            }
        });
    };

    // Delete ticket handler
    window.deleteTicket = function(ticketId) {
        if (confirm(window.translations.are_you_sure_you_want_to_delete_this_ticket)) {
            $.ajax({
                url: 'handlers/delete_refund_ticket.php',
                type: 'POST',
                data: { ticket_id: ticketId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            showToast(result.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showToast(result.message, 'error');
                        }
                    } catch (error) {
                        showToast('An error occurred while deleting the ticket', 'error');
                    }
                },
                error: function() {
                    showToast('Failed to communicate with the server', 'error');
                }
            });
        }
    };

    // Generate invoice handler
    window.generateInvoice = function(ticketId) {
        window.open(`generate_refund_invoice.php?ticket_id=${ticketId}`, '_blank');
    };

    // Print refund agreement handler
    window.printRefundAgreement = function(ticketId) {
        if (!ticketId) {
            showToast(window.translations.ticket_id_is_missing, 'error');
            return;
        }

        $.ajax({
            url: 'generate_refund_agreement.php',
            type: 'POST',
            data: { ticket_id: ticketId },
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        window.open(result.file_url, '_blank');
                    } else {
                        showToast(window.translations.failed_to_generate_agreement, 'error');
                    }
                } catch (error) {
                    showToast(window.translations.error_generating_agreement, 'error');
                }
            },
            error: function() {
                showToast(window.translations.error_generating_agreement, 'error');
            }
        });
    };
}); 