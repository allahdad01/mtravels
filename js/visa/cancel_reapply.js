// Cancellation functionality
function openCancellationModal(visaId, applicantName, currentStatus) {
    document.getElementById('cancelVisaId').value = visaId;
    document.getElementById('cancelApplicantName').value = applicantName;
    document.getElementById('cancelCurrentStatus').value = currentStatus;
    document.getElementById('currentStatus').value = currentStatus;
    document.getElementById('cancellationReason').value = '';
    document.getElementById('confirmCancellation').checked = false;
    document.getElementById('processCancellationBtn').disabled = true;
    $('#cancelVisaModal').modal('show');
}

// Enable/disable process button based on confirmation
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmCancellation');
    const processBtn = document.getElementById('processCancellationBtn');
    
    if (confirmCheckbox && processBtn) {
        confirmCheckbox.addEventListener('change', function() {
            processBtn.disabled = !this.checked;
        });
    }
});

// Process cancellation
document.getElementById('processCancellationBtn').addEventListener('click', function() {
    const form = document.getElementById('cancelVisaForm');
    const formData = new FormData(form);
    
    
    // Disable button and show loading
    this.disabled = true;
    this.innerHTML = '<i class="feather icon-loader mr-1"></i>Processing...';
    
    // Prepare data for submission
    const data = {
        action: 'cancel_visa',
        visa_id: formData.get('visa_id'),
        current_status: formData.get('current_status'),
        new_status: formData.get('new_status'),
        cancellation_reason: formData.get('cancellation_reason')
    };
    
    // Send AJAX request
    fetch('../api/visa/visa_cancellation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Visa cancelled successfully', 'success');
            $('#cancelVisaModal').modal('hide');
            // Reload page after short delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to cancel visa', 'error');
            // Re-enable button
            this.disabled = false;
            this.innerHTML = '<?= __("cancel_visa") ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while cancelling the visa', 'error');
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<?= __("cancel_visa") ?>';
    });
});

// Re-apply functionality
function openReapplyModal(visaId, applicantName, originalProfit, baseAmount, soldAmount, currency) {
    document.getElementById('reapplyVisaId').value = visaId;
    document.getElementById('reapplyApplicantName').value = applicantName;
    document.getElementById('reapplyCurrentStatus').value = 'Cancelled/Rejected/Withdrawn';
    document.getElementById('reapplyOriginalProfit').value = originalProfit;
    document.getElementById('reapplyBaseAmount').value = baseAmount;
    document.getElementById('reapplySoldAmount').value = soldAmount;
    document.getElementById('reapplyCurrency').value = currency;
    document.getElementById('reapplyReason').value = '';
    document.getElementById('confirmReapply').checked = false;
    document.getElementById('processReapplyBtn').disabled = true;
    
    $('#reapplyVisaModal').modal('show');
}

// Enable/disable process button based on confirmation
document.addEventListener('DOMContentLoaded', function() {
    const confirmReapplyCheckbox = document.getElementById('confirmReapply');
    const processReapplyBtn = document.getElementById('processReapplyBtn');
    
    if (confirmReapplyCheckbox && processReapplyBtn) {
        confirmReapplyCheckbox.addEventListener('change', function() {
            processReapplyBtn.disabled = !this.checked;
        });
    }
});

// Process re-apply
document.getElementById('processReapplyBtn').addEventListener('click', function() {
    const form = document.getElementById('reapplyVisaForm');
    const formData = new FormData(form);
    
    
    // Disable button and show loading
    this.disabled = true;
    this.innerHTML = '<i class="feather icon-loader mr-1"></i>Processing...';
    
    // Prepare data for submission
    const data = {
        action: 'reapply_visa',
        visa_id: formData.get('visa_id'),
        new_status: formData.get('new_status'),
        reapply_reason: formData.get('reapply_reason'),
        original_profit: formData.get('original_profit'),
        base_amount: formData.get('base_amount'),
        sold_amount: formData.get('sold_amount'),
        currency: formData.get('currency')
    };
    
    // Send AJAX request
    fetch('../api/visa/visa_reapply.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Visa re-applied successfully', 'success');
            $('#reapplyVisaModal').modal('hide');
            // Reload page after short delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to re-apply visa', 'error');
            // Re-enable button
            this.disabled = false;
            this.innerHTML = '<?= __("re_apply_visa") ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while re-applying the visa', 'error');
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<?= __("re_apply_visa") ?>';
    });
});