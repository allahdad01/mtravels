/**
 * Modal Override Script
 * This script ensures that the proper modal is used across the system
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in the finance section but using admin modals
    const isFinanceSection = window.location.pathname.includes('/finance/');
    const hasPartialPaymentModal = document.getElementById('partialPaymentModal');
    
    if (isFinanceSection && hasPartialPaymentModal) {
        console.log('Overriding finance payment modal with admin version');
        
        // Override the make-payment button click handlers
        document.querySelectorAll('.make-payment-btn').forEach(button => {
            // Remove existing event listeners (not perfect but helps)
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Add our own event listener
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const clientId = this.dataset.clientId;
                const clientName = this.dataset.clientName;
                const usdBalance = parseFloat(this.dataset.usdBalance);
                const afsBalance = parseFloat(this.dataset.afsBalance);
                
                // Set form values
                document.getElementById('clientId').value = clientId;
                document.getElementById('clientName').value = clientName;
                
                // Set current balances display
                document.getElementById('currentUsdBalance').textContent = '$' + usdBalance.toFixed(2);
                document.getElementById('currentAfsBalance').textContent = '؋' + afsBalance.toFixed(2);
                
                // Reset form fields
                const paymentCurrency = document.getElementById('paymentCurrency');
                const totalAmount = document.getElementById('totalAmount');
                const exchangeRate = document.getElementById('exchangeRate');
                const usdAmount = document.getElementById('usdAmount');
                const afsAmount = document.getElementById('afsAmount');
                
                if (paymentCurrency) paymentCurrency.value = '';
                if (totalAmount) totalAmount.value = '';
                if (exchangeRate) exchangeRate.value = '';
                if (usdAmount) usdAmount.value = '';
                if (afsAmount) afsAmount.value = '';
                
                // Show our modal
                $('#partialPaymentModal').modal('show');
                
                return false;
            });
        });
        
        // Override the process payment button
        const processPaymentBtn = document.getElementById('processPaymentBtn');
        if (processPaymentBtn) {
            const newProcessBtn = processPaymentBtn.cloneNode(true);
            processPaymentBtn.parentNode.replaceChild(newProcessBtn, processPaymentBtn);
            
            newProcessBtn.addEventListener('click', function() {
                const form = document.getElementById('partialPaymentForm');
                const formData = new FormData(form);
                
                // Validate amounts
                const selectedCurrency = formData.get('payment_currency');
                const totalAmount = parseFloat(formData.get('total_amount')) || 0;
                const exchangeRate = parseFloat(formData.get('exchange_rate')) || 0;
                const usdAmount = parseFloat(formData.get('usd_amount')) || 0;
                const afsAmount = parseFloat(formData.get('afs_amount')) || 0;
                
                if (!selectedCurrency) {
                    showWarningToast('Please select a payment currency');
                    return;
                }
                
                if (totalAmount <= 0) {
                    showWarningToast('Please enter a valid total amount');
                    return;
                }
                
                if (exchangeRate <= 0) {
                    showWarningToast('Please enter a valid exchange rate');
                    return;
                }
                
                if (usdAmount === 0 && afsAmount === 0) {
                    showWarningToast('Please enter at least one payment amount');
                    return;
                }
                
                // Calculate total payment in selected currency
                let totalPaymentInSelectedCurrency = 0;
                if (selectedCurrency === 'USD') {
                    const afsInUsd = afsAmount / exchangeRate;
                    totalPaymentInSelectedCurrency = usdAmount + afsInUsd;
                } else {
                    const usdInAfs = usdAmount * exchangeRate;
                    totalPaymentInSelectedCurrency = usdInAfs + afsAmount;
                }
                
                // Validate total payment matches the amount to pay
                if (Math.abs(totalAmount - totalPaymentInSelectedCurrency) > 0.01) {
                    showWarningToast('The sum of USD and AFS payments must equal the total amount to pay');
                    return;
                }
                
                // Create confirmation toast with action buttons
                const confirmToast = showToast(
                    `Payment for ${document.getElementById('clientName').value}: ${selectedCurrency === 'USD' ? '$' : '؋'}${totalAmount.toFixed(2)}`,
                    'warning',
                    'Confirm Payment',
                    0 // Don't auto-close
                );
                
                // Add custom content to the toast body
                const toastBody = confirmToast.querySelector('.toast-body');
                toastBody.innerHTML = `
                    <p>Client: ${document.getElementById('clientName').value}</p>
                    <p>Selected Currency: ${selectedCurrency}</p>
                    <p>Total Amount: ${selectedCurrency === 'USD' ? '$' : '؋'}${totalAmount.toFixed(2)}</p>
                    <p><strong>Payment Breakdown:</strong></p>
                    <ul>
                        <li>USD Payment: $${usdAmount.toFixed(2)}</li>
                        <li>AFS Payment: ؋${afsAmount.toFixed(2)}</li>
                    </ul>
                    <div class="mt-2 text-right">
                        <button class="btn btn-sm btn-outline-secondary mr-2 cancel-payment">Cancel</button>
                        <button class="btn btn-sm btn-success confirm-payment">Confirm</button>
                    </div>
                `;
                
                // Add event listeners to the buttons
                const cancelBtn = toastBody.querySelector('.cancel-payment');
                const confirmBtn = toastBody.querySelector('.confirm-payment');
                
                cancelBtn.addEventListener('click', () => {
                    removeToast(confirmToast);
                });
                
                confirmBtn.addEventListener('click', () => {
                    removeToast(confirmToast);
                    
                    // Send payment request
                    fetch('../api/fundClient.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showSuccessToast('Payment processed successfully');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showErrorToast('Payment failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showErrorToast('An error occurred while processing the payment');
                    });
                });
            });
        }
    }
}); 