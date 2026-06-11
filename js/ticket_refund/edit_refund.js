function editRefundTicket(id) {
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

    fetch('../api/ticket_refund/get_refund_ticket_bookings.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.booking) {
                Swal.fire('Error', data.message || 'Failed to fetch refund details', 'error');
                return;
            }

            const r = data.booking;
            const currency = r.currency || 'USD';
            const calcMethod = r.calculation_method || 'sold';
            const soldSelected = calcMethod === 'sold' ? 'selected' : '';
            const baseSelected = calcMethod === 'base' ? 'selected' : '';

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
                            '<span class="refund-info-value" style="color:#185FA5">' + escapeHtml(r.pnr) + '</span>' +
                        '</div>' +
                        '<div class="refund-info-row">' +
                            '<span class="refund-info-label">Passenger</span>' +
                            '<span class="refund-info-value">' + escapeHtml(r.title) + ' ' + escapeHtml(r.passenger_name) + '</span>' +
                        '</div>' +
                        '<div class="refund-info-row">' +
                            '<span class="refund-info-label">Route</span>' +
                            '<span class="refund-info-value">' + escapeHtml(r.origin) + ' &rarr; ' + escapeHtml(r.destination) + '</span>' +
                        '</div>' +
                        '<div class="refund-info-row">' +
                            '<span class="refund-info-label">Airline</span>' +
                            '<span class="refund-info-value">' + escapeHtml(r.airline) + '</span>' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Pricing</div>' +
                    '<div class="refund-grid-2">' +
                        '<div class="refund-field">' +
                            '<label>Base (' + currency + ')</label>' +
                            '<input class="swal2-input refund-ro" id="edit-base" value="' + parseFloat(r.base).toFixed(3) + '" readonly>' +
                        '</div>' +
                        '<div class="refund-field">' +
                            '<label>Sold (' + currency + ')</label>' +
                            '<input class="swal2-input refund-ro" id="edit-sold" value="' + parseFloat(r.sold).toFixed(3) + '" readonly>' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Calculation</div>' +
                    '<div class="refund-field">' +
                        '<label>Method</label>' +
                        '<select class="swal2-input" id="edit-method">' +
                            '<option value="sold" ' + soldSelected + '>Sold - Penalties</option>' +
                            '<option value="base" ' + baseSelected + '>Base - Penalties</option>' +
                        '</select>' +
                    '</div>' +

                    '<div class="refund-section-label">Penalties</div>' +
                    '<div class="refund-grid-2">' +
                        '<div class="refund-field">' +
                            '<label>Supplier (' + currency + ')</label>' +
                            '<input class="swal2-input" id="edit-supplier-penalty" type="number" step="0.001" value="' + parseFloat(r.supplier_penalty).toFixed(3) + '">' +
                        '</div>' +
                        '<div class="refund-field">' +
                            '<label>Service (' + currency + ')</label>' +
                            '<input class="swal2-input" id="edit-service-penalty" type="number" step="0.001" value="' + parseFloat(r.service_penalty).toFixed(3) + '">' +
                        '</div>' +
                    '</div>' +

                    '<div class="refund-section-label">Result</div>' +
                    '<div class="refund-field">' +
                        '<label>Refund to Passenger (' + currency + ')</label>' +
                        '<input class="swal2-input refund-ro" id="edit-refund-amount" type="number" step="0.001" value="' + parseFloat(r.refund_to_passenger).toFixed(3) + '" readonly>' +
                    '</div>' +

                    '<div class="refund-section-label">Remarks</div>' +
                    '<div class="refund-field">' +
                        '<textarea class="swal2-textarea" id="edit-remarks" rows="3">' + escapeHtml(r.remarks || '') + '</textarea>' +
                    '</div>' +
                '</div>';

            Swal.fire({
                title: 'Edit Refund #' + id,
                html: modalHtml,
                showCancelButton: true,
                confirmButtonText: 'Update',
                confirmButtonColor: '#185FA5',
                cancelButtonText: 'Cancel',
                width: 520,
                didOpen: () => {
                    const base = parseFloat(document.getElementById('edit-base').value) || 0;
                    const sold = parseFloat(document.getElementById('edit-sold').value) || 0;

                    function calcRefund() {
                        const method = document.getElementById('edit-method').value;
                        const sp = parseFloat(document.getElementById('edit-supplier-penalty').value) || 0;
                        const sv = parseFloat(document.getElementById('edit-service-penalty').value) || 0;
                        const total = sp + sv;
                        const amt = method === 'sold' ? sold - total : base - total;
                        document.getElementById('edit-refund-amount').value = amt > 0 ? amt.toFixed(3) : '0.000';
                    }

                    document.getElementById('edit-supplier-penalty').addEventListener('input', calcRefund);
                    document.getElementById('edit-service-penalty').addEventListener('input', calcRefund);
                    document.getElementById('edit-method').addEventListener('change', calcRefund);
                },
                preConfirm: () => {
                    const supplierPenalty = parseFloat(document.getElementById('edit-supplier-penalty').value) || 0;
                    const servicePenalty = parseFloat(document.getElementById('edit-service-penalty').value) || 0;
                    const refundAmount = parseFloat(document.getElementById('edit-refund-amount').value) || 0;
                    const remarks = document.getElementById('edit-remarks').value.trim();

                    if (refundAmount <= 0) {
                        Swal.showValidationMessage('Refund amount must be greater than zero');
                        return false;
                    }

                    const calculationMethod = document.getElementById('edit-method').value;

                    return {
                        ticket_id: id,
                        supplier_penalty: supplierPenalty,
                        service_penalty: servicePenalty,
                        refund_amount: refundAmount,
                        remarks: remarks,
                        calculation_method: calculationMethod
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../api/ticket_refund/update_refund_penalties.php', {
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
