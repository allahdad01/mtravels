// Function to open refund modal
function openRefundModal(bookingId, amount, profit, currency) {

    
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
        const csrfToken = $('#refundForm input[name="csrf_token"]').val();
        
        
        // Validate required fields
         if (!bookingId || !refundType || !reason) {
             showToast('error', 'Please fill in all required fields');
             return;
         }
        
        // Validate refund amount for partial refunds
        if (refundType === 'partial') {
            if (!refundAmount || refundAmount <= 0 || refundAmount > originalAmount) {
                showToast('error', 'Please enter a valid refund amount (between 0 and ' + originalAmount + ')');
                return;
            }
        }
        
        
        // Show loading state
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="feather icon-refresh-cw spinner"></i> Processing...');
        

        
        // Send AJAX request
        $.ajax({
            url: '../api/umrah/process_umrah_refund.php',
            type: 'POST',
            data: {
                booking_id: bookingId,
                refund_type: refundType,
                refund_amount: refundAmount,
                reason: reason,
                currency: currency,
                original_profit: originalProfit,
                csrf_token: csrfToken
            },
            success: function(response) {

                try {
                    // Try to parse the response if it's a string
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (result.status === 'success' || result.success) {
                         showToast('success', result.message || 'Refund processed successfully');
                         setTimeout(() => {
                             $('#refundModal').modal('hide');
                             refreshFamiliesTable();
                         }, 1500);
                     } else {
                         showToast('error', result.message || 'Failed to process refund');
                     }
                } catch (e) {

                     // If response is HTML or plain text, show it directly
                     if (typeof response === 'string') {
                         showToast('error', 'Error processing refund');
                     } else {
                         showToast('error', 'Error processing the refund request');
                     }
                 }
            },
            error: function(xhr, status, error) {



                
                // Try to get message from xhr response
                let errorMessage = 'Error processing refund';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage = response.message || errorMessage;
                } catch (e) {
                    errorMessage = xhr.responseText || errorMessage;
                }
                
                showToast('error', errorMessage);
            },
            complete: function() {
                // Reset button state
                btn.prop('disabled', false).html(originalText);

            }
        });
    });
    
    // Also bind to form submit as backup
    $('#refundForm').on('submit', function(e) {
        e.preventDefault();

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

function generateCancellationForm(bookingId) {
    openLanguageModal(bookingId, 'cancellation');
}

let currentBookingId = null;
let currentFormType = null;
const formEndpoints = {
    agreement: '../api/umrah/generate_umrah_agreement.php',
    completion: '../api/umrah/generate_umrah_completion.php',
    cancellation: '../api/umrah/generate_umrah_cancellation.php',
    receipt: '../api/umrah/generate_umrah_document_receipt.php'
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

    // Directly open in new window, similar to family agreements
    window.open(url, '_blank');
}

// Expose to global scope for inline onclick handlers
if (typeof window !== 'undefined') {
    window.generateIndividualDocumentWithLanguage = generateIndividualDocumentWithLanguage;
}
