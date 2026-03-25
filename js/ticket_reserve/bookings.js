document.getElementById('supplier').addEventListener('change', function () {
    const supplierId = this.value;



    if (supplierId) {
        fetch(`../api/ticket_reserve/get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {

                return response.json();
            })
            .then(data => {

                const currInput = document.getElementById('curr');
                if (data.currency) {
                    currInput.value = data.currency;


                } else {
                    currInput.value = '';

                }
            })
            .catch(error => {

            });
    } else {

        document.getElementById('curr').value = '';
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const bookForm = document.getElementById('bookTicketForm');
    if (bookForm) {
        bookForm.addEventListener('submit', function (event) {
            event.preventDefault(); // Prevent default form submission
            const submitBtn = this.querySelector('input[type="submit"], button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true; // Disable button to prevent multiple clicks
                submitBtn.dataset.originalText = submitBtn.textContent || submitBtn.value; // Store original text
                submitBtn.textContent = submitBtn.value = 'Loading...'; // Show loading state
            }

            const formData = new FormData(this); // Collect form data

            // Create a timeout promise
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Request timeout')), 30000); // 30 second timeout
            });

            Promise.race([
                fetch('../api/ticket_reserve/save_ticket_reserve.php', {
                    method: 'POST',
                    body: formData
                }).then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error, status = ' + response.status);
                    }
                    return response.json();
                }),
                timeoutPromise
            ])
            .then(data => {
                if (data.status === 'success') { // Check for status
                    showToast(data.message, 'success');
                    $('#bookTicketModal').modal('hide');
                    location.reload(); // Reload page
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), 'error'); // Display specific error message
                }
            })
            .catch(error => {
                console.error('Booking error:', error);
                showToast('An unexpected error occurred: ' + error.message, 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false; // Re-enable button
                    submitBtn.textContent = submitBtn.value = submitBtn.dataset.originalText; // Restore original text
                }
            });
        });
    }
});
function deleteTicket(id) {
    if (confirm('are_you_sure_you_want_to_delete_this_ticket')) {
        fetch('../api/ticket_reserve/delete_ticket_reserve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('ticket_deleted_successfully');
                location.reload();
            } else {
                alert('error: ' + data.message);
            }
        })

    }
}
   // Trip type toggle for new booking form
   const tripTypeElement = document.getElementById('tripType');
   if (tripTypeElement) {
       tripTypeElement.addEventListener('change', function() {
           const tripType = this.value;
           const returnJourneyFields = document.getElementById('returnJourneyFields');
           const returnDateField = document.getElementById('returnDateField');
           
           if (tripType === 'round_trip') {
               if (returnJourneyFields) returnJourneyFields.style.display = 'block';
               if (returnDateField) returnDateField.style.display = 'block';
               // Make return fields required when visible
               const returnOrigin = document.getElementById('returnOrigin');
               const returnDate = document.getElementById('returnDate');
               if (returnOrigin) returnOrigin.required = true;
               if (returnDate) returnDate.required = true;
           } else {
               if (returnJourneyFields) returnJourneyFields.style.display = 'none';
               if (returnDateField) returnDateField.style.display = 'none';
               // Remove required attribute when hidden
               const returnOrigin = document.getElementById('returnOrigin');
               const returnDate = document.getElementById('returnDate');
               if (returnOrigin) returnOrigin.required = false;
               if (returnDate) returnDate.required = false;
           }
       });
   }
     // Payment currency handling for new booking form
     const paymentCurrencyElement = document.getElementById('paymentCurrency');
     if (paymentCurrencyElement) {
         paymentCurrencyElement.addEventListener('change', function() {
             const supplierCurrency = document.getElementById('curr')?.value || '';
             const paymentCurrency = this.value;
             
             const exchangeRateElement = document.getElementById('exchangeRate');
             const paymentAmountElement = document.getElementById('paymentAmount');
             const soldElement = document.getElementById('sold');
             
             if (supplierCurrency !== paymentCurrency) {
                 if (exchangeRateElement) exchangeRateElement.required = true;
             } else {
                 if (exchangeRateElement) exchangeRateElement.required = false;
                 if (paymentAmountElement && soldElement) {
                     paymentAmountElement.value = soldElement.value;
                 }
             }
         });
     }
     
     // Calculate payment amount when the calculate button is clicked
     const calculatePaymentElement = document.getElementById('calculatePayment');
     if (calculatePaymentElement) {
         calculatePaymentElement.addEventListener('click', function() {
             const currElement = document.getElementById('curr');
             const paymentCurrencyElement = document.getElementById('paymentCurrency');
             const soldElement = document.getElementById('sold');
             const exchangeRateElement = document.getElementById('exchangeRate');
             const paymentAmountElement = document.getElementById('paymentAmount');
             
             if (!currElement || !paymentCurrencyElement || !soldElement || !exchangeRateElement || !paymentAmountElement) {

                 return;
             }
             
             const supplierCurrency = currElement.value;
             const paymentCurrency = paymentCurrencyElement.value;
             const sold = parseFloat(soldElement.value) || 0;
             const exchangeRate = parseFloat(exchangeRateElement.value) || 1;
             let paymentAmount;
             
             if (supplierCurrency !== paymentCurrency) {
                 paymentAmount = sold * exchangeRate;
             } else {
                 paymentAmount = sold;
             }
             
             paymentAmountElement.value = paymentAmount.toFixed(2);
         });
     }
     
     // Set supplier currency when supplier changes
     const supplierElement = document.getElementById('supplier');
     if (supplierElement) {
         supplierElement.addEventListener('change', function() {
             // This function is already handled by the existing get_supplier_currency.php call
             // Additionally update payment calculation when supplier or currency changes
             setTimeout(() => {
                 const paymentCurrencyElement = document.getElementById('paymentCurrency');
                 const currElement = document.getElementById('curr');
                 const soldElement = document.getElementById('sold');
                 const paymentAmountElement = document.getElementById('paymentAmount');
                 
                 if (!paymentCurrencyElement || !currElement || !paymentAmountElement) {
                     return;
                 }
                 
                 const paymentCurrency = paymentCurrencyElement.value;
                 const supplierCurrency = currElement.value;
                 
                 if (paymentCurrency === supplierCurrency) {
                     if (soldElement) {
                         paymentAmountElement.value = soldElement.value;
                     }
                 } else {
                     // Clear payment amount to require recalculation
                     paymentAmountElement.value = '';
                 }
             }, 500); // Small timeout to wait for the supplier currency to be set
         });
     }
     
     // Update payment amount when sold amount changes
     const soldElement = document.getElementById('sold');
     if (soldElement) {
         soldElement.addEventListener('input', function() {
             const paymentCurrencyElement = document.getElementById('paymentCurrency');
             const currElement = document.getElementById('curr');
             const paymentAmountElement = document.getElementById('paymentAmount');
             const calculatePaymentElement = document.getElementById('calculatePayment');
             
             if (!paymentCurrencyElement || !currElement || !paymentAmountElement) {
                 return;
             }
             
             const paymentCurrency = paymentCurrencyElement.value;
             const supplierCurrency = currElement.value;
             
             if (paymentCurrency === supplierCurrency) {
                 paymentAmountElement.value = this.value;
             } else {
                 // If currencies differ, don't auto-update but indicate recalculation is needed
                 const currentPaymentAmount = paymentAmountElement.value;
                 if (currentPaymentAmount && calculatePaymentElement) {
                     // Trigger calculation if there was already a value
                     calculatePaymentElement.click();
                 }
             }
         });
     }
     
     // Trip type toggle for edit form
     const editTripTypeEl = document.getElementById('editTripType');
     if (editTripTypeEl) {
         editTripTypeEl.addEventListener('change', function() {
             const tripType = this.value;
             const returnJourneyFields = document.getElementById('editReturnJourneyFields');
             const returnDateField = document.getElementById('editReturnDateField');
             
             if (tripType === 'round_trip') {
                 if (returnJourneyFields) returnJourneyFields.style.display = 'block';
                 if (returnDateField) returnDateField.style.display = 'block';
                 // Make return fields required when visible
                 const editReturnOrigin = document.getElementById('editReturnOrigin');
                 const editReturnDate = document.getElementById('editReturnDate');
                 if (editReturnOrigin) editReturnOrigin.required = true;
                 if (editReturnDate) editReturnDate.required = true;
             } else {
                 if (returnJourneyFields) returnJourneyFields.style.display = 'none';
                 if (returnDateField) returnDateField.style.display = 'none';
                 // Remove required attribute when hidden
                 const editReturnOrigin = document.getElementById('editReturnOrigin');
                 const editReturnDate = document.getElementById('editReturnDate');
                 if (editReturnOrigin) editReturnOrigin.required = false;
                 if (editReturnDate) editReturnDate.required = false;
             }
         });
     }

     document.addEventListener('DOMContentLoaded', () => {
        const baseInput = document.getElementById('base');
       const soldInput = document.getElementById('sold');
                      const proInput = document.getElementById('pro');

                                        // Function to calculate and update the profit field
                                        function calculatePro() {
                                            const base = parseFloat(baseInput.value) || 0; // Default to 0 if not valid
                                            const sold = parseFloat(soldInput.value) || 0; // Default to 0 if not valid
                                            const pro = sold - base; // Calculate profit





                                            // Update the profit field and make sure it's also visible
                                            proInput.value = pro.toFixed(2);  // Update to two decimal points

                                        }

                                        // Add event listeners for real-time calculation
                                        baseInput.addEventListener('input', calculatePro);
                                        soldInput.addEventListener('input', calculatePro);
   });

      document.addEventListener('DOMContentLoaded', () => {
                                        const editBaseInput = document.getElementById('editBase');
                                        const editSoldInput = document.getElementById('editSold');
                                        const editProInput = document.getElementById('editPro');

                                        // Function to calculate and update the profit field
                                        function calculateEditPro() {
                                            const editBase = parseFloat(editBaseInput.value) || 0; // Default to 0 if not valid
                                            const editSold = parseFloat(editSoldInput.value) || 0; // Default to 0 if not valid
                                            const editPro = editSold - editBase; // Calculate profit





                                            // Update the profit field and make sure it's also visible
                                            editProInput.value = editPro.toFixed(2);  // Update to two decimal points

                                        }

                                        // Add event listeners for real-time calculation
                                        editBaseInput.addEventListener('input', calculateEditPro);
                                        editSoldInput.addEventListener('input', calculateEditPro);
  });
