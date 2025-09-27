  // Function to handle edit ticket button click
  function editTicket(ticketId) {
    // Show loader
    document.getElementById('editLoader').style.display = 'block';
    
    // Function to populate edit form with ticket data
    window.populateEditForm = function(ticketData) {
        try {
            // Set ticket ID
            const editTicketIdEl = document.getElementById('editTicketId');
            if (editTicketIdEl) editTicketIdEl.value = ticketData.id;

            // Set basic ticket information
            const editSupplierEl = document.getElementById('editSupplier');
            if (editSupplierEl) editSupplierEl.value = ticketData.supplier;
            const editSoldToEl = document.getElementById('editSoldTo');
            if (editSoldToEl) editSoldToEl.value = ticketData.sold_to;
            const editTripTypeEl = document.getElementById('editTripType');
            if (editTripTypeEl) editTripTypeEl.value = ticketData.trip_type;
            const editTitleEl = document.getElementById('editTitle');
            if (editTitleEl) editTitleEl.value = ticketData.title;
            const editGenderEl = document.getElementById('editGender');
            if (editGenderEl) editGenderEl.value = ticketData.gender;
            const editPassengerNameEl = document.getElementById('editPassengerName');
            if (editPassengerNameEl) editPassengerNameEl.value = ticketData.passenger_name;
            const editPnrEl = document.getElementById('editPnr');
            if (editPnrEl) editPnrEl.value = ticketData.pnr;
            const editPhoneEl = document.getElementById('editPhone');
            if (editPhoneEl) editPhoneEl.value = ticketData.phone;

            // Set journey details
            const editOriginEl = document.getElementById('editOrigin');
            if (editOriginEl) editOriginEl.value = ticketData.origin;
            const editDestinationEl = document.getElementById('editDestination');
            if (editDestinationEl) editDestinationEl.value = ticketData.destination;

            // Set airline with selectpicker refresh
            const editAirlineSelect = document.getElementById('editAirline');
            if (editAirlineSelect) {
                editAirlineSelect.value = ticketData.airline;
                $(editAirlineSelect).selectpicker('refresh');
            }
            const editIssueDateEl = document.getElementById('editIssueDate');
            if (editIssueDateEl) editIssueDateEl.value = ticketData.issue_date;
            const editDepartureDateEl = document.getElementById('editDepartureDate');
            if (editDepartureDateEl) editDepartureDateEl.value = ticketData.departure_date;

            // Set return journey details if applicable
            if (ticketData.trip_type === 'round_trip') {
                const editReturnDestinationEl = document.getElementById('editReturnDestination');
                if (editReturnDestinationEl) editReturnDestinationEl.value = ticketData.return_destination || '';
                const editReturnDateEl = document.getElementById('editReturnDate');
                if (editReturnDateEl) editReturnDateEl.value = ticketData.return_date || '';
            }

            // Set financial details
            const editBaseEl = document.getElementById('editBase');
            if (editBaseEl) editBaseEl.value = ticketData.price;
            const editSoldEl = document.getElementById('editSold');
            if (editSoldEl) editSoldEl.value = ticketData.sold;
            const editProEl = document.getElementById('editPro');
            if (editProEl) editProEl.value = ticketData.profit;

            const editCurrEl = document.getElementById('editCurr');
            if (editCurrEl) editCurrEl.value = ticketData.currency;
            const editDescriptionEl = document.getElementById('editDescription');
            if (editDescriptionEl) editDescriptionEl.value = ticketData.description || '';
            const editPaidToEl = document.getElementById('editPaidTo');
            if (editPaidToEl) editPaidToEl.value = ticketData.paid_to || '';
            
            // Handle return fields visibility directly
            const isRoundTrip = ticketData.trip_type === 'round_trip';
            const returnJourneyFields = document.getElementById('editReturnJourneyFields');
            const returnDateField = document.getElementById('editReturnDateField');
            
            if (returnJourneyFields) {
                returnJourneyFields.style.display = isRoundTrip ? 'block' : 'none';
            }
            if (returnDateField) {
                returnDateField.style.display = isRoundTrip ? 'block' : 'none';
            }
            
            // Update required status of return fields
            const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
            returnFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.required = isRoundTrip;
                }
            });
            
            console.log('Form populated successfully with ticket data');
        } catch (error) {
            console.error('Error populating form:', error);
            alert('Error loading form data. Please check the console for details.');
        }
    };
    
    // Fetch ticket data
    fetch(`fetch_ticket_reserve_by_id.php?id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            // Hide loader
            document.getElementById('editLoader').style.display = 'none';
            
            if (data.success) {
                // Populate the form with ticket data
                window.populateEditForm(data.ticket);
                
                // Show the modal
                $('#editTicketModal').modal('show');
            } else {
                alert('Error loading ticket data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching ticket data:', error);
            document.getElementById('editLoader').style.display = 'none';
            alert('An error occurred while loading ticket data. Please try again.');
        });
}

// Add event listeners to update balances in real-time when editing base and sold prices
document.addEventListener('DOMContentLoaded', function() {
    const editBaseInput = document.getElementById('editBase');
    const editSoldInput = document.getElementById('editSold');
    const editTripTypeSelect = document.getElementById('editTripType');
    const editDiscountInput = document.getElementById('editDiscount');
    const editProInput = document.getElementById('editPro');
    const editTicketModal = document.getElementById('editTicketModal');
    
    // Skip initialization if elements are not present on the page
    if (!editBaseInput || !editSoldInput || !editTripTypeSelect) {
        console.log('Edit form elements not found, skipping initialization');
        return;
    }
    
    // Store original values when the modal opens
    let originalBase = 0;
    let originalSold = 0;
    
    // When the edit modal is shown, store the original values
    if (editTicketModal) {
        $(editTicketModal).on('shown.bs.modal', function() {
            originalBase = parseFloat(editBaseInput.value) || 0;
            originalSold = parseFloat(editSoldInput.value) || 0;
            
            console.log('Original values stored - Base:', originalBase, 'Sold:', originalSold);
            
            // Show/hide return fields based on trip type
            toggleReturnFields();
        });
    }
    
    // Toggle return fields visibility based on trip type
    if (editTripTypeSelect) {
        editTripTypeSelect.addEventListener('change', toggleReturnFields);
    }
    
    function toggleReturnFields() {
        if (!editTripTypeSelect) return;
        
        const isRoundTrip = editTripTypeSelect.value === 'round_trip';
        const returnJourneyFields = document.getElementById('editReturnJourneyFields');
        const returnDateField = document.getElementById('editReturnDateField');
        
        if (returnJourneyFields) returnJourneyFields.style.display = isRoundTrip ? 'block' : 'none';
        if (returnDateField) returnDateField.style.display = isRoundTrip ? 'block' : 'none';
        
        // Make return fields required if round trip is selected
        const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
        returnFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.required = isRoundTrip;
            }
        });
    }
    
    // Calculate profit automatically
    function calculateProfit() {
        if (!editBaseInput || !editSoldInput || !editDiscountInput || !editProInput) return;
        
        const base = parseFloat(editBaseInput.value) || 0;
        const sold = parseFloat(editSoldInput.value) || 0;
        const discount = parseFloat(editDiscountInput.value) || 0;
        const profit = sold - discount - base;
        editProInput.value = profit.toFixed(2);
    }
    
    // Recalculate profit when base or sold changes
    if (editBaseInput) editBaseInput.addEventListener('input', calculateProfit);
    if (editSoldInput) editSoldInput.addEventListener('input', calculateProfit);
    if (editDiscountInput) editDiscountInput.addEventListener('input', calculateProfit);
    
    // Update supplier balance when base price changes
    if (editBaseInput) {
        editBaseInput.addEventListener('input', function() {
            const editSupplierEl = document.getElementById('editSupplier');
            if (!editSupplierEl) return;
            
            const supplierId = editSupplierEl.value;
            if (!supplierId) return;
            
            const newBase = parseFloat(this.value) || 0;
            const baseDifference = originalBase - newBase; // Positive if base decreased, negative if increased
            
            // Only proceed if there's an actual change
            if (baseDifference !== 0) {
                updateSupplierBalance(supplierId, baseDifference);
            }
        });
    }
    
    // Update client balance when sold price changes
    if (editSoldInput) {
        editSoldInput.addEventListener('input', function() {
            const editSoldToEl = document.getElementById('editSoldTo');
            if (!editSoldToEl) return;
            
            const clientId = editSoldToEl.value;
            if (!clientId) return;
            
            const newSold = parseFloat(this.value) || 0;
            const soldDifference = originalSold - newSold; // Positive if sold decreased, negative if increased
            
            // Only proceed if there's an actual change
            if (soldDifference !== 0) {
                updateClientBalance(clientId, soldDifference);
            }
        });
    }
    
    // Function to update supplier balance preview
    function updateSupplierBalance(supplierId, difference) {
        // Get the currency
        const currencyElement = document.getElementById('editCurr');
        const currency = currencyElement ? currencyElement.value : 'USD';
        
        // Make AJAX call to get current supplier balance
        fetch(`get_supplier_balance.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Only update preview if supplier is External
                    if (data.is_external) {
                        // Calculate new balance
                        const currentBalance = parseFloat(data.balance) || 0;
                        const newBalance = currentBalance + difference;
                        
                        console.log(`Supplier balance update preview: ${currentBalance} + ${difference} = ${newBalance}`);
                        
                        // Update the supplier dropdown to show the new balance preview
                        const supplierSelect = document.getElementById('editSupplier');
                        if (supplierSelect) {
                            const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
                            if (selectedOption) {
                                // Update the option text with the new balance preview
                                selectedOption.text = `${data.supplier_name} (Balance: ${newBalance.toFixed(2)})`;
                            }
                        }
                    } else {
                        console.log('Supplier is not External, no balance update needed');
                    }
                } else {
                    console.error('Error fetching supplier balance:', data.message);
                }
            })
            .catch(error => {
                console.error('Error in supplier balance update:', error);
            });
    }
    
    // Function to update client balance preview
    function updateClientBalance(clientId, difference) {
        // Get the currency
        const currencyElement = document.getElementById('editCurr');
        const currency = currencyElement ? currencyElement.value : 'USD';
        
        // Make AJAX call to get current client balance
        fetch(`get_client_balance.php?client_id=${clientId}&currency=${currency}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Only update preview if client is Regular
                    if (data.is_regular) {
                        // Calculate new balance
                        const currentBalance = parseFloat(data.balance) || 0;
                        const newBalance = currentBalance + difference;
                        
                        console.log(`Client balance update preview: ${currentBalance} + ${difference} = ${newBalance} ${currency}`);
                        
                        // Update the client dropdown to show the new balance preview
                        const clientSelect = document.getElementById('editSoldTo');
                        if (clientSelect) {
                            const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                            if (selectedOption) {
                                // Update the option text with the new balance preview
                                selectedOption.text = `${data.client_name} (${currency}: ${newBalance.toFixed(2)})`;
                            }
                        }
                    } else {
                        console.log('Client is not Regular, no balance update needed');
                    }
                } else {
                    console.error('Error fetching client balance:', data.message);
                }
            })
            .catch(error => {
                console.error('Error in client balance update:', error);
            });
    }
    
    // Update the form submission to include all fields and balance changes
    const editTicketForm = document.getElementById('editTicketForm');
    if (editTicketForm) {
        editTicketForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Show loader
            const editLoader = document.getElementById('editLoader');
            if (editLoader) {
                editLoader.style.display = 'block';
            }
            
            const formData = new FormData(this);
            
            // Add the original values to the form data for server-side comparison
            formData.append('originalBase', originalBase);
            formData.append('originalSold', originalSold);
            
            // Validate required fields for round trip
            if (editTripTypeSelect && editTripTypeSelect.value === 'round_trip') {
                const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
                for (const fieldId of returnFields) {
                    const field = document.getElementById(fieldId);
                    if (field && !field.value) {
                        const label = field.previousElementSibling ? field.previousElementSibling.textContent : 'Required';
                        alert(`Please fill in the ${label} field.`);
                        if (editLoader) {
                            editLoader.style.display = 'none';
                        }
                        return;
                    }
                }
            }
            
            fetch('update_ticket_reserve.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                // Hide loader
                if (editLoader) {
                    editLoader.style.display = 'none';
                }
                
                if (data.success) {
                    alert('ticket_updated_successfully');
                    $('#editTicketModal').modal('hide');
                    location.reload(); // Refresh to see updated balances
                } else {
                    alert('error_updating_ticket: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error updating ticket:', error);
                if (editLoader) {
                    editLoader.style.display = 'none';
                }
                alert('error_occurred_while_updating_the_ticket');
            });
        });
    }
});