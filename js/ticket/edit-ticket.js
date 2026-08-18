
// Function to edit ticket
function editTicket(ticketId) {
    // Show loader
    document.getElementById('editLoader').style.display = 'block';

    // Fetch ticket data
    fetch(`../api/ticket/fetch_ticket_by_id.php?id=${ticketId}`)
        .then(response => response.json())
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to fetch ticket data');
            }

            const data = response.ticket;

            // Set form values
            document.getElementById('editTicketId').value = data.id;
            document.getElementById('editSupplier').value = data.supplier;
            document.getElementById('editSoldTo').value = data.sold_to;
            document.getElementById('editTripType').value = data.trip_type;
            document.getElementById('editTitle').value = data.title;
            document.getElementById('editGender').value = data.gender;
            document.getElementById('editPassengerName').value = data.passenger_name;
            document.getElementById('editPnr').value = data.pnr;
            document.getElementById('editPhone').value = data.phone;
            document.getElementById('editOrigin').value = data.origin;
            document.getElementById('editDestination').value = data.destination;
            document.getElementById('editIssueDate').value = data.issue_date;
            document.getElementById('editDepartureDate').value = data.departure_date;
            document.getElementById('editDepartureTime').value = data.departure_time || '';
            document.getElementById('editBase').value = data.price;
            document.getElementById('editSold').value = data.sold;
            document.getElementById('editDiscount').value = data.discount || 0;
            document.getElementById('editPro').value = data.profit;
            document.getElementById('editCurr').value = data.currency;
            document.getElementById('editPaidTo').value = data.paid_to;
            document.getElementById('editDescription').value = data.description || '';

            // Populate return flight segments (round trip)
            if (typeof window.populateEditReturnFlightLegs === 'function') {
                window.populateEditReturnFlightLegs(
                    data.return_flight_legs || null,
                    data.return_origin,
                    data.return_destination,
                    data.return_date !== '0000-00-00' ? data.return_date : '',
                    data.return_departure_time || ''
                );
            }

            // Add helper text to show full names for reference
            if (data.supplier_name) {
                const supplierText = document.createElement('small');
                supplierText.className = 'form-text text-muted';
                supplierText.textContent = data.supplier_name;
                document.getElementById('editSupplier').after(supplierText);
            }
            
            if (data.client_name) {
                const clientText = document.createElement('small');
                clientText.className = 'form-text text-muted';
                clientText.textContent = data.client_name;
                document.getElementById('editSoldTo').after(clientText);
            }
            
            if (data.paid_to_name) {
                const paidToText = document.createElement('small');
                paidToText.className = 'form-text text-muted';
                paidToText.textContent = data.paid_to_name;
                document.getElementById('editPaidTo').after(paidToText);
            }

            // Populate multi-leg flight data if available
            if (typeof window.populateEditFlightLegs === 'function') {
                window.populateEditFlightLegs(
                    data.flight_legs || null,
                    data.origin,
                    data.destination,
                    data.airline,
                    data.departure_date,
                    data.departure_time || ''
                );
            }

            // Store original values for comparison
            window.originalBase = data.price;
            window.originalSold = data.sold;

            // Disable supplier and client fields if ticket has refund, date change, or weight records
            var hasRestrictions = data.has_refund || data.has_date_change || data.has_weight;
            var supplierSelect = document.getElementById('editSupplier');
            var clientSelect = document.getElementById('editSoldTo');
            
            if (hasRestrictions) {
                supplierSelect.disabled = true;
                clientSelect.disabled = true;
                $(supplierSelect).selectpicker('refresh');
                $(clientSelect).selectpicker('refresh');
            } else {
                supplierSelect.disabled = false;
                clientSelect.disabled = false;
                $(supplierSelect).selectpicker('refresh');
                $(clientSelect).selectpicker('refresh');
            }

            // Hide loader and show modal
            document.getElementById('editLoader').style.display = 'none';
            $('#editTicketModal').modal('show');
        })
        .catch(error => {

            document.getElementById('editLoader').style.display = 'none';
            showToast('Error fetching ticket data: ' + error.message, 'error');
        });
}

// Function to fetch and set supplier currency
function fetchEditSupplierCurrency(supplierId) {
    if (supplierId) {
        fetch(`../api/ticket/get_supplier_currency.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                const currInput = document.getElementById('editCurr');
                if (data.currency) {
                    currInput.value = data.currency;
                } else {
                    currInput.value = '';
                }
            })
            .catch(error => {
                console.error('Error fetching currency:', error);
            });
    } else {
        document.getElementById('editCurr').value = '';
    }
}

// Add event listeners to update balances in real-time when editing base and sold prices
document.addEventListener('DOMContentLoaded', function() {
    const editBaseInput = document.getElementById('editBase');
    const editSoldInput = document.getElementById('editSold');
    const editTripTypeSelect = document.getElementById('editTripType');
    const editSupplierSelect = document.getElementById('editSupplier');
    
    // Store original values when the modal opens
    let originalBase = 0;
    let originalSold = 0;
    
    // When the edit modal is shown, store the original values
    $('#editTicketModal').on('shown.bs.modal', function() {
        originalBase = parseFloat(editBaseInput.value) || 0;
        originalSold = parseFloat(editSoldInput.value) || 0;
        

        
        // Show/hide return fields based on trip type
        toggleReturnFields();
    });
    
    // Handle supplier change to fetch and update currency
    editSupplierSelect.addEventListener('change', function() {
        const supplierId = this.value;
        fetchEditSupplierCurrency(supplierId);
    });
    
    // Toggle return fields visibility based on trip type
    editTripTypeSelect.addEventListener('change', toggleReturnFields);
    
    function toggleReturnFields() {
        const isRoundTrip = editTripTypeSelect.value === 'round_trip';
        const returnGroup = document.getElementById('editReturnFlightSegmentsGroup');
        const returnContainer = document.getElementById('editReturnFlightLegsContainer');
        if (returnGroup) returnGroup.style.display = isRoundTrip ? 'block' : 'none';
        if (returnContainer) {
            returnContainer.querySelectorAll('.flight-leg-row .leg-origin, .flight-leg-row .leg-destination').forEach(function (field) {
                field.required = isRoundTrip;
            });
        }
    }
    
    // Calculate profit automatically
    function calculateProfit() {
        const base = parseFloat(editBaseInput.value) || 0;
        const sold = parseFloat(editSoldInput.value) || 0;
        const discount = parseFloat(document.getElementById('editDiscount').value) || 0;
        const profit = sold - discount - base;
        document.getElementById('editPro').value = profit.toFixed(2);
    }
    
    // Recalculate profit when base or sold changes
    editBaseInput.addEventListener('input', calculateProfit);
    editSoldInput.addEventListener('input', calculateProfit);
    document.getElementById('editDiscount').addEventListener('input', calculateProfit);
    
    // Update supplier balance when base price changes
    editBaseInput.addEventListener('input', function() {
        const supplierId = document.getElementById('editSupplier').value;
        if (!supplierId) return;
        
        const newBase = parseFloat(this.value) || 0;
        const baseDifference = originalBase - newBase; // Positive if base decreased, negative if increased
        
        // Only proceed if there's an actual change
        if (baseDifference !== 0) {
            updateSupplierBalance(supplierId, baseDifference);
        }
    });
    
    // Update client balance when sold price changes
    editSoldInput.addEventListener('input', function() {
        const clientId = document.getElementById('editSoldTo').value;
        if (!clientId) return;
        
        const newSold = parseFloat(this.value) || 0;
        const soldDifference = originalSold - newSold; // Positive if sold decreased, negative if increased
        
        // Only proceed if there's an actual change
        if (soldDifference !== 0) {
            updateClientBalance(clientId, soldDifference);
        }
    });
    
    // Function to update supplier balance preview
    function updateSupplierBalance(supplierId, difference) {
        // Get the currency
        const currency = document.getElementById('editCurr').value;
        
        // Make AJAX call to get current supplier balance
        fetch(`../api/ticket/get_supplier_balance.php?supplier_id=${supplierId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Only update preview if supplier is External
                    if (data.is_external) {
                        // Calculate new balance
                        const currentBalance = parseFloat(data.balance) || 0;
                        const newBalance = currentBalance + difference;
                        

                        
                        // Update the supplier dropdown to show the new balance preview
                        const supplierSelect = document.getElementById('editSupplier');
                        const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
                        
                        // Update the option text with the new balance preview
                        selectedOption.text = `${data.supplier_name} (Balance: ${newBalance.toFixed(2)})`;
                    } else {

                    }
                } else {

                }
            })
            .catch(error => {

            });
    }
    
    // Function to update client balance preview
    function updateClientBalance(clientId, difference) {
        // Get the currency
        const currency = document.getElementById('editCurr').value;
        
        // Make AJAX call to get current client balance
        fetch(`../api/ticket/get_client_balance.php?client_id=${clientId}&currency=${currency}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Only update preview if client is Regular
                    if (data.is_regular) {
                        // Calculate new balance
                        const currentBalance = parseFloat(data.balance) || 0;
                        const newBalance = currentBalance + difference;
                        

                        
                        // Update the client dropdown to show the new balance preview
                        const clientSelect = document.getElementById('editSoldTo');
                        const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                        
                        // Update the option text with the new balance preview
                        selectedOption.text = `${data.client_name} (${currency}: ${newBalance.toFixed(2)})`;
                    } else {

                    }
                } else {

                }
            })
            .catch(error => {

            });
    }
    
    // Update the form submission to include all fields and balance changes
    document.getElementById('editTicketForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        // Show loader and disable submit button
        document.getElementById('editLoader').style.display = 'block';
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalButtonText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="feather icon-loader mr-2"></i>Processing...';
        
        const formData = new FormData(this);
        
        // Multi-leg flights: append legs JSON and use the last leg as final destination
        if (typeof window.collectEditFlightLegs === 'function') {
            const legsJson = window.collectEditFlightLegs();
            if (legsJson) {
                formData.append('flight_legs', legsJson);
                const finalDestination = window.getEditFlightLegsFinalDestination();
                if (finalDestination) formData.set('destination', finalDestination);
            }
        }

        // Round trip: append return flight segments JSON and derive return destination
        if (editTripTypeSelect.value === 'round_trip' && typeof window.collectEditReturnFlightLegs === 'function') {
            const returnLegsJson = window.collectEditReturnFlightLegs();
            if (returnLegsJson) {
                formData.append('return_flight_legs', returnLegsJson);
                const returnFinalDestination = window.getEditReturnFlightLegsFinalDestination();
                if (returnFinalDestination) formData.set('returnDestination', returnFinalDestination);
            } else {
                showToast('Please add at least one return flight segment for a round trip.', 'error');
                document.getElementById('editLoader').style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalButtonText;
                return;
            }
        }

        // Add the original values to the form data for server-side comparison
        formData.append('originalBase', originalBase);
        formData.append('originalSold', originalSold);
        
        fetch('../api/ticket/update_ticket.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            // Hide loader and restore button
            document.getElementById('editLoader').style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalButtonText;
            
            if (data.success) {
                showToast('Ticket updated successfully', 'success');
                $('#editTicketModal').modal('hide');
                setTimeout(() => {
                    refreshTicketTable();
                }, 1000);
            } else {
                showToast('Error updating ticket: ' + data.message, 'error');
            }
        })
        .catch(error => {
            document.getElementById('editLoader').style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalButtonText;
            showToast('An error occurred while updating the ticket', 'error');
        });
    });
    
    // Function to populate edit form with ticket data
    window.populateEditForm = function(ticketData) {
        // Set ticket ID
        document.getElementById('editTicketId').value = ticketData.id;
        
        // Set basic ticket information
        document.getElementById('editSupplier').value = ticketData.supplier;
        document.getElementById('editSoldTo').value = ticketData.sold_to;
        document.getElementById('editTripType').value = ticketData.trip_type;
        document.getElementById('editTitle').value = ticketData.title;
        document.getElementById('editGender').value = ticketData.gender;
        document.getElementById('editPassengerName').value = ticketData.passenger_name;
        document.getElementById('editPnr').value = ticketData.pnr;
        document.getElementById('editPhone').value = ticketData.phone;
        
        // Set journey details
        document.getElementById('editOrigin').value = ticketData.origin;
        document.getElementById('editDestination').value = ticketData.destination;
        document.getElementById('editAirline').value = ticketData.airline;
        document.getElementById('editIssueDate').value = ticketData.issue_date;
        document.getElementById('editDepartureDate').value = ticketData.departure_date;
        document.getElementById('editDepartureTime').value = ticketData.departure_time || '';
        
        // Set return journey details if applicable
        if (ticketData.trip_type === 'round_trip' && typeof window.populateEditReturnFlightLegs === 'function') {
            window.populateEditReturnFlightLegs(
                ticketData.return_flight_legs || null,
                ticketData.return_origin,
                ticketData.return_destination,
                ticketData.return_date !== '0000-00-00' ? ticketData.return_date : '',
                ticketData.return_departure_time || ''
            );
        }
        
        // Set financial details
        document.getElementById('editBase').value = ticketData.price;
        document.getElementById('editSold').value = ticketData.sold;
        document.getElementById('editDiscount').value = ticketData.discount;
        document.getElementById('editPro').value = ticketData.profit;
        document.getElementById('editCurr').value = ticketData.currency;
        document.getElementById('editDescription').value = ticketData.description || '';
        document.getElementById('editPaidTo').value = ticketData.paid_to || '';
        
        // Toggle return fields based on trip type
        toggleReturnFields();
    };
}); 
