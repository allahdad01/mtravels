$(document).ready(function () {

    function calculateRefundAmount() {
        const soldPrice = parseFloat($('#refundSold').val()) || 0;
        const supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0;
        const servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;
        return Math.max(0, soldPrice - supplierPenalty - servicePenalty);
    }

    $(document).on('input change', '#supplierRefundPenalty, #serviceRefundPenalty', function () {
        $('#refundAmount').val(calculateRefundAmount().toFixed(2));
    });

    window.openRefundModal = function (bookingId, soldPrice, basePrice, currency) {
        $('#refundBookingId').val(bookingId);
        $('#refundSold').val(soldPrice);
        $('#refundBase').val(basePrice);
        $('#displaySoldPrice').text(currency + ' ' + parseFloat(soldPrice).toFixed(2));

        $('#supplierRefundPenalty').val(0);
        $('#serviceRefundPenalty').val(0);
        $('#refundDescription').val('');
        $('#refundAmount').val(calculateRefundAmount().toFixed(2));

        $('#refundModal').modal('show');
    };

    $('#refundForm').submit(function (e) {
        e.preventDefault();

        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="feather icon-loader mr-2"></i>Processing...');

        const formData = $(this).serialize();

        $.ajax({
            url: '../api/hotel/process_hotel_refund.php',
            method: 'POST',
            data: formData,
            success: function (response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success || result.status === 'success') {
                        showToast(result.message || 'Refund processed successfully', 'success');
                        $('#refundModal').modal('hide');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showToast(result.message || 'Error processing refund', 'error');
                    }
                } catch (e) {
                    if ($.trim(response) === 'success') {
                        showToast('Refund processed successfully', 'success');
                        $('#refundModal').modal('hide');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showToast('Error processing refund', 'error');
                    }
                }
            },
            error: function () {
                showToast('An error occurred', 'error');
            },
            complete: function () {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

});
