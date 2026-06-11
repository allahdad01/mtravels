// View transaction function
function viewTransaction(transactionId) {
    // Implement view transaction functionality
    alert('View transaction functionality to be implemented');
}

// Process refund transaction function
function processRefundTransaction(refundId) {
    // Show loading state
    $('#refundTransactionModal .modal-content').addClass('loading');
    
    // Fetch refund details
    $.ajax({
        url: '../api/umrah/get_umrah_refund_details.php',
        type: 'GET',
        data: { id: refundId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const refund = response.refund;

                // Store refund currency globally for exchange rate field logic
                window.refundCurrency = refund.currency || 'USD';

                // Set form values
                $('#refund_id').val(refundId);
                $('#transactionBookingId').text(refund.booking_id);
                $('#refundType').text(refund.refund_type === 'full' ? 'full_refund' : 'partial_refund');
                $('#refundReason').text(refund.reason || 'N/A');
                $('#refundGuest').text(refund.name || 'N/A');
                $('#refundUmrah').text(refund.package_type || 'N/A');
                
                // Calculate exchanged amount
                const amount = parseFloat(refund.refund_amount);
                const exchangeRate = parseFloat(refund.exchange_rate || 1);
                const exchangedAmount = refund.currency === 'USD' ? 
                    amount * exchangeRate : 
                    amount / exchangeRate;
                
                // Update total amount with currency
                const totalAmountElement = $('#totalAmount');
                totalAmountElement.text(parseFloat(amount).toFixed(2));
                totalAmountElement.closest('.financial-summary-value').html(`${refund.currency} ${totalAmountElement.text()}`);

                $('#exchangeRateDisplay').text(parseFloat(exchangeRate).toFixed(5));
                $('#exchangedAmount').text(
                    `${refund.currency === 'USD' ? 'AFS' : 'USD'} ${exchangedAmount.toFixed(2)}`
                );
                
                // Store original amounts for currency conversion
                $('#paymentAmount')
                    .data('usd-amount', refund.currency === 'USD' ? amount : exchangedAmount)
                    .data('afs-amount', refund.currency === 'USD' ? exchangedAmount : amount)
                    .val(amount.toFixed(2));
                
                // Set default currency
                $('#paymentCurrency').val(refund.currency);
                
                // Generate default description
                const description = `Refund payment for Umrah Booking #${refund.booking_id} - ${refund.name}`;
                $('#paymentDescription').val(description);
                
                // Load transaction history
                transactionManager.loadTransactionHistory(refundId);
                
                // Remove loading state and show modal
                $('#refundTransactionModal .modal-content').removeClass('loading');
                $('#refundTransactionModal').modal('show');
            } else {
                alert('Error fetching refund details: ' + (response.message || 'Unknown error'));
                $('#refundTransactionModal .modal-content').removeClass('loading');
            }
        },
        error: function(xhr, status, error) {

            alert('Error fetching refund details');
            $('#refundTransactionModal .modal-content').removeClass('loading');
        }
    });
}

function printRefundAgreement(refundId) {
    // Open the printable agreement page in a new window
    window.open('../api/umrah/print_umrah_refund.php?id=' + refundId, '_blank');
}


    // Enhanced delete refund with confirmation
    function deleteRefund(refundId) {
        Swal.fire({
            title: 'are_you_sure',
            text: 'you_cannot_revert_this_action',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'yes_delete_it',
            cancelButtonText: 'cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Actual delete logic
                fetch('../api/umrah/delete_umrah_refund.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: refundId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'deleted',
                            'refund_deleted_successfully',
                            'success'
                        ).then(() => location.reload());
                    } else {
                        Swal.fire(
                            'error',
                            data.message || 'failed_to_delete_refund',
                            'error'
                        );
                    }
                })
                .catch(error => {

                    Swal.fire(
                        'error',
                        'network_error_occurred',
                        'error'
                    );
                });
            }
        });
    }

function editUmrahRefund(id) {
    if (!id) {
        Swal.fire('Error', 'Refund ID is missing', 'error');
        return;
    }

    Swal.fire({
        title: 'Loading...',
        html: 'Fetching refund details...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../api/umrah/get_umrah_refund_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.refund) {
                Swal.fire('Error', data.message || 'Failed to fetch refund details', 'error');
                return;
            }

            const r = data.refund;
            const currency = r.currency || 'USD';

            var modalHtml = '' +
                '<style>' +
                    '.refund-edit-form { font-family:inherit; }' +
                    '.refund-info-card { background:#f8f9fc; border:1px solid #e9ecf2; border-radius:8px; padding:12px 14px; margin-bottom:16px; }' +
                    '.refund-info-row { display:flex; align-items:center; padding:3px 0; font-size:13px; }' +
                    '.refund-info-row + .refund-info-row { border-top:1px solid #f0f1f4; }' +
                    '.refund-info-label { min-width:80px; font-weight:600; color:#6b7a8f; font-size:11px; text-transform:uppercase; letter-spacing:0.3px; }' +
                    '.refund-info-value { color:#1a2332; }' +
                    '.refund-section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#8a94a6; margin:14px 0 6px; }' +
                    '.refund-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }' +
                    '.refund-field { margin-bottom:6px; }' +
                    '.refund-field label { display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:4px; }' +
                    '.refund-field .swal2-input, .refund-field .swal2-textarea { margin:0; width:100%; box-sizing:border-box; }' +
                    '.refund-field .refund-ro { background:#f3f4f6 !important; color:#4a5568; cursor:default; }' +
                    '.refund-field .swal2-textarea { min-height:70px; resize:vertical; }' +
                '</style>' +
                '<div class="refund-edit-form">' +
                    '<div class="refund-info-card">' +
                        '<div class="refund-info-row">' +
                            '<span class="refund-info-label">Refund #' + id + '</span>' +
                            '<span class="refund-info-value">' + escapeHtml(r.name || '') + '</span>' +
                        '</div>' +
                        '<div class="refund-info-row">' +
                            '<span class="refund-info-label">Package</span>' +
                            '<span class="refund-info-value">' + escapeHtml(r.package_type || '') + '</span>' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Pricing</div>' +
                    '<div class="refund-grid-2">' +
                        '<div class="refund-field">' +
                            '<label>Base (' + currency + ')</label>' +
                            '<input class="swal2-input refund-ro" id="edit-base" value="' + parseFloat(r.base || 0).toFixed(2) + '" readonly>' +
                        '</div>' +
                        '<div class="refund-field">' +
                            '<label>Sold (' + currency + ')</label>' +
                            '<input class="swal2-input refund-ro" id="edit-sold" value="' + parseFloat(r.sold || 0).toFixed(2) + '" readonly>' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Penalties</div>' +
                    '<div class="refund-grid-2">' +
                        '<div class="refund-field">' +
                            '<label>Supplier (' + currency + ')</label>' +
                            '<input class="swal2-input" id="edit-supplier-penalty" type="number" step="0.01" value="' + parseFloat(r.supplier_penalty || 0).toFixed(2) + '">' +
                        '</div>' +
                        '<div class="refund-field">' +
                            '<label>Service (' + currency + ')</label>' +
                            '<input class="swal2-input" id="edit-service-penalty" type="number" step="0.01" value="' + parseFloat(r.service_penalty || 0).toFixed(2) + '">' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Result</div>' +
                    '<div class="refund-field">' +
                        '<label>Refund Amount (' + currency + ')</label>' +
                        '<input class="swal2-input refund-ro" id="edit-refund-amount" type="number" step="0.01" value="' + parseFloat(r.refund_amount || 0).toFixed(2) + '" readonly>' +
                    '</div>' +

                    '<div class="refund-section-label">Reason</div>' +
                    '<div class="refund-field">' +
                        '<textarea class="swal2-textarea" id="edit-reason" rows="3">' + escapeHtml(r.reason || '') + '</textarea>' +
                    '</div>' +
                '</div>';

            Swal.fire({
                title: 'Edit Umrah Refund',
                html: modalHtml,
                showCancelButton: true,
                confirmButtonText: 'Update',
                confirmButtonColor: '#185FA5',
                cancelButtonText: 'Cancel',
                width: 520,
                didOpen: () => {
                    const sold = parseFloat(document.getElementById('edit-sold').value) || 0;

                    function calcRefund() {
                        const sp = parseFloat(document.getElementById('edit-supplier-penalty').value) || 0;
                        const sv = parseFloat(document.getElementById('edit-service-penalty').value) || 0;
                        const total = sp + sv;
                        const amt = sold - total;
                        document.getElementById('edit-refund-amount').value = amt > 0 ? amt.toFixed(2) : '0.00';
                    }

                    document.getElementById('edit-supplier-penalty').addEventListener('input', calcRefund);
                    document.getElementById('edit-service-penalty').addEventListener('input', calcRefund);
                },
                preConfirm: () => {
                    const supplierPenalty = parseFloat(document.getElementById('edit-supplier-penalty').value) || 0;
                    const servicePenalty = parseFloat(document.getElementById('edit-service-penalty').value) || 0;
                    const refundAmount = parseFloat(document.getElementById('edit-refund-amount').value) || 0;
                    const reason = document.getElementById('edit-reason').value.trim();

                    if (refundAmount <= 0) {
                        Swal.showValidationMessage('Refund amount must be greater than zero');
                        return false;
                    }

                    return {
                        id: id,
                        supplier_penalty: supplierPenalty,
                        service_penalty: servicePenalty,
                        refund_amount: refundAmount,
                        reason: reason
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../api/umrah/update_umrah_refund.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(result.value)
                    })
                    .then(response => response.json())
                    .then(res => {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated',
                                text: res.message || 'Refund updated successfully'
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message || 'Failed to update refund', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'An unexpected error occurred', 'error');
                    });
                }
            });
        })
        .catch(() => {
            Swal.fire('Error', 'Failed to fetch refund details', 'error');
        });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
