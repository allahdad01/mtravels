// Function to open refund modal
function openRefundModal(bookingId, amount, profit, currency) {
    console.log('Opening refund modal:', { bookingId, amount, profit, currency });
    
    // Set hidden fields
    $('#refund_booking_id').val(bookingId);
    $('#refund_original_amount').val(amount);
    $('#refund_original_profit').val(profit);
    $('#refund_currency').val(currency);
    
    // Display values in the modal
    $('#displayOriginalAmount').text(currency + ' ' + amount.toFixed(2));
    $('#displayOriginalProfit').text(currency + ' ' + profit.toFixed(2));
    

    
    // Reset form
    $('#refundForm')[0].reset();
    $('#refundAmountGroup').hide();
    
    // Re-set the hidden fields after form reset
    $('#refund_booking_id').val(bookingId);
    $('#refund_original_amount').val(amount);
    $('#refund_original_profit').val(profit);
    $('#refund_currency').val(currency);
    
    // Show modal
    $('#refundModal').modal('show');
}

// Function to toggle refund amount field
function toggleRefundAmount() {
    const refundType = $('#refund_type').val();
    const amountGroup = $('#refundAmountGroup');
    const amountInput = $('#refund_amount');
    
    if (refundType === 'partial') {
        amountGroup.show();
        amountInput.prop('required', true);
        const maxAmount = parseFloat($('#refund_original_amount').val());
        amountInput.attr('max', maxAmount);
    } else {
        amountGroup.hide();
        amountInput.prop('required', false);
    }
}

// Handle refund processing
$(document).ready(function() {
    
    // Direct click handler
    $(document).on('click', '#processRefundBtn', function() {
        
        // Get form data
        const bookingId = $('#refund_booking_id').val();
        const refundType = $('#refund_type').val();
        const originalAmount = parseFloat($('#refund_original_amount').val());
        const refundAmount = refundType === 'partial' ? parseFloat($('#refund_amount').val()) : originalAmount;
        const reason = $('#refund_reason').val();
        const currency = $('#refund_currency').val();
        const originalProfit = parseFloat($('#refund_original_profit').val());
        
        
        // Validate required fields
        if (!bookingId || !refundType || !reason) {
            Swal.fire({
                icon: 'error',
                title: 'Missing Information',
                text: 'Please fill in all required fields'
            });
            return;
        }
        
        // Validate refund amount for partial refunds
        if (refundType === 'partial') {
            if (!refundAmount || refundAmount <= 0 || refundAmount > originalAmount) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Amount',
                    text: 'Please enter a valid refund amount (between 0 and ' + originalAmount + ')'
                });
                return;
            }
        }
        
        
        // Show loading state
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="feather icon-refresh-cw spinner"></i> Processing...');
        
        console.log('Sending AJAX request to process_umrah_refund.php');
        
        // Send AJAX request
        $.ajax({
            url: 'process_umrah_refund.php',
            type: 'POST',
            data: {
                booking_id: bookingId,
                refund_type: refundType,
                refund_amount: refundAmount,
                reason: reason,
                currency: currency,
                original_profit: originalProfit
            },
            success: function(response) {
                console.log('AJAX success response:', response);
                try {
                    // Try to parse the response if it's a string
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.status === 'success' || result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.message || 'Refund processed successfully',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $('#refundModal').modal('hide');
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message || 'Failed to process refund'
                        });
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    // If response is HTML or plain text, show it directly
                    if (typeof response === 'string') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: response // Use html instead of text to properly render HTML content
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error processing the refund request'
                        });
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                // Try to get message from xhr response
                let errorMessage = 'Error processing refund';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = xhr.responseText || errorMessage;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    html: errorMessage // Use html to properly render any HTML in the error message
                });
            },
            complete: function() {
                // Reset button state
                btn.prop('disabled', false).html(originalText);
                console.log('AJAX request completed');
            }
        });
    });
    
    // Also bind to form submit as backup
    $('#refundForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted, triggering button click');
        $('#processRefundBtn').click();
    });
});

// Function to generate umrah agreement
function generateAgreement(bookingId) {
    openLanguageModal(bookingId, 'agreement');
}

function generateCompletionForm(bookingId) {
    openLanguageModal(bookingId, 'completion');
}

function generateDocumentReceipt(bookingId) {
    openLanguageModal(bookingId, 'receipt');
}

let currentBookingId = null;
let currentFormType = null;
const formEndpoints = {
    agreement: 'generate_umrah_agreement.php',
    completion: 'generate_umrah_completion.php',
    receipt: 'generate_umrah_document_receipt.php'
};
const formTitles = {
    agreement: 'generating_agreement',
    completion: 'generating_completion_form',
    receipt: 'generating_document_receipt'
};

function openLanguageModal(bookingId, formType) {
    currentBookingId = bookingId;
    currentFormType = formType;
    $('#languageModal').modal('show');
}

function generateIndividualDocumentWithLanguage(lang) {
    $('#languageModal').modal('hide');
    if (!currentBookingId || !currentFormType) return;

    const bookingId = currentBookingId;
    const url = `${formEndpoints[currentFormType]}?booking_id=${bookingId}&lang=${lang}`;
    const title = formTitles[currentFormType];

    Swal.fire({
        title: title,
        text: 'please_wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'success',
                text: data.message,
                confirmButtonText: 'view_document',
                showCancelButton: true,
                cancelButtonText: 'close'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('../' + data.file_url, '_blank');
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'error',
                text: data.message || 'failed_to_generate_document'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'error',
            text: 'an_error_occurred'
        });
    });
}   

// Expose to global scope for inline onclick handlers
if (typeof window !== 'undefined') {
    window.generateIndividualDocumentWithLanguage = generateIndividualDocumentWithLanguage;
}