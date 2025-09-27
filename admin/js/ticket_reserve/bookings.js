document.getElementById('supplier').addEventListener('change', function () {
    const supplierId = this.value;

    console.log('Selected Supplier ID:', supplierId);

    if (supplierId) {
        fetch(`get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => {
                console.log('Response status:', response.status); // Log status
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // Log full response
                const currInput = document.getElementById('curr');
                if (data.currency) {
                    currInput.value = data.currency;

                    console.log('Currency input updated to:', data.currency);
                } else {
                    currInput.value = '';
                    console.warn('No currency found in response!');
                }
            })
            .catch(error => {
                console.error('Error fetching supplier currency:', error);
            });
    } else {
        console.log('No supplier selected, clearing input.');
        document.getElementById('curr').value = '';
    }
});
document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission
    const formData = new FormData(this); // Collect form data

    fetch('save_ticket_reserve.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        if (data.status === 'success') { // Check for status
            alert(data.message); // Show success message
            location.reload(); // Reload page
        } else {
            alert('Error: ' + data.message); // Display specific error message
        }
    })
    .catch(error => {
        console.error('Error:', error); // Log error
        alert('An unexpected error occurred.');
    });
});
function deleteTicket(id) {
    if (confirm('are_you_sure_you_want_to_delete_this_ticket')) {
        fetch('delete_ticket_reserve.php', {
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
        .catch(error => console.error('error_deleting_ticket', error));
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
                 console.error('Missing required elements for payment calculation');
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

                                            console.log("Base: ", base);
                                            console.log("Sold: ", sold);
                                            console.log('Profit Calculated:', pro);

                                            // Update the profit field and make sure it's also visible
                                            proInput.value = pro.toFixed(2);  // Update to two decimal points
                                            console.log('Updated Profit Input Value: ', proInput.value); // Check updated value
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

                                            console.log("editBase: ", editBase);
                                            console.log("editSold: ", editSold);
                                            console.log('Profit Calculated:', editPro);

                                            // Update the profit field and make sure it's also visible
                                            editProInput.value = editPro.toFixed(2);  // Update to two decimal points
                                            console.log('Updated Profit Input Value: ', editProInput.value); // Check updated value
                                        }

                                        // Add event listeners for real-time calculation
                                        editBaseInput.addEventListener('input', calculateEditPro);
                                        editSoldInput.addEventListener('input', calculateEditPro);
  });