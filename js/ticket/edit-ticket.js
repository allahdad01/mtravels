// Function to populate airline options
function populateAirlineOptions(selectElement, selectedAirline) {
    const airlines = [
          // Middle East & Central Asia (Expanded)
    { code: 'KM', name: 'KamAir (KM)' },
    { code: 'IR', name: 'Iran Air (IR)' },
    { code: 'W5', name: 'Mahan Airlines (W5)' },
    { code: 'EP', name: 'Iran Aseman Airlines (EP)' },
    { code: 'QB', name: 'Qeshm Airlines (QB)' },
    { code: 'Y9', name: 'Kish Air (Y9)' },
    { code: 'NV', name: 'Iranian Naft Airlines (NV)' },
    { code: 'EK', name: 'Emirates (EK)' },
    { code: 'QR', name: 'Qatar Airways (QR)' },
    { code: 'GF', name: 'Gulf Air (GF)' },
    { code: 'SV', name: 'Saudia (SV)' },
    { code: 'ME', name: 'Middle East Airlines (ME)' },
    { code: 'RJ', name: 'Royal Jordanian (RJ)' },
    { code: 'IY', name: 'Yemenia (IY)' },
    { code: 'KC', name: 'Air Astana (KC)' },
    { code: 'HY', name: 'Uzbekistan Airways (HY)' },
    { code: 'J2', name: 'Azerbaijan Airlines (J2)' },
    { code: 'Z9', name: 'Tajik Air (Z9)' },
    { code: 'R2', name: 'ORENAIR (R2)' },
    { code: 'PS', name: 'Ukraine International Airlines (PS)' },
    { code: 'FG', name: 'Ariana Afghan Airlines (FG)' },
    { code: 'RB', name: 'Syrian Arab Airlines (RB)' },
    { code: 'XY', name: 'Nas Air (XY)' },
    { code: 'J9', name: 'Jazeera Airways (J9)' },

    // South Asia (Expanded)
    { code: 'AI', name: 'Air India (AI)' },
    { code: 'PK', name: 'Pakistan International Airlines (PK)' },
    { code: '6E', name: 'IndiGo (6E)' },
    { code: 'SG', name: 'SpiceJet (SG)' },
    { code: 'G8', name: 'GoAir (G8)' },
    { code: 'UK', name: 'Vistara (UK)' },
    { code: 'BG', name: 'Biman Bangladesh Airlines (BG)' },
    { code: 'UL', name: 'SriLankan Airlines (UL)' },
    { code: 'RA', name: 'Nepal Airlines (RA)' },
    { code: 'KB', name: 'Druk Air (KB)' },
    { code: 'QZ', name: 'AirAsia Indonesia (QZ)' },
    { code: 'IT', name: 'Air Italy (IT)' },
    { code: 'S2', name: 'SpiceJet (S2)' },
    { code: 'I5', name: 'AirAsia India (I5)' },
    { code: 'D7', name: 'AirAsia X (D7)' },
    { code: 'U4', name: 'Buddha Air (U4)' },
    { code: 'H9', name: 'Himalaya Airlines (H9)' },
    { code: 'GS', name: 'GoAir (GS)' },

    // Southeast Asia (Expanded)
    { code: 'SQ', name: 'Singapore Airlines (SQ)' },
    { code: 'MH', name: 'Malaysia Airlines (MH)' },
    { code: 'GA', name: 'Garuda Indonesia (GA)' },
    { code: 'TG', name: 'Thai Airways (TG)' },
    { code: 'VN', name: 'Vietnam Airlines (VN)' },
    { code: 'PG', name: 'Bangkok Airways (PG)' },
    { code: 'TR', name: 'Scoot (TR)' },
    { code: 'AK', name: 'AirAsia (AK)' },
    { code: 'JQ', name: 'Jetstar Airways (JQ)' },
    { code: 'Z2', name: 'Philippine Airlines (Z2)' },
    { code: 'CX', name: 'Cathay Pacific (CX)' },
    { code: 'PR', name: 'Philippine Airlines (PR)' },
    { code: 'MI', name: 'SilkAir (MI)' },
    { code: 'QV', name: 'Lao Airlines (QV)' },
    { code: 'BK', name: 'Bangkok Airways (BK)' },
    { code: 'XJ', name: 'Thai AirAsia X (XJ)' },
    { code: 'OD', name: 'Batik Air (OD)' },
    { code: 'ID', name: 'Batik Air Malaysia (ID)' },

    // East Asia (Expanded)
    { code: 'CA', name: 'Air China (CA)' },
    { code: 'CZ', name: 'China Southern Airlines (CZ)' },
    { code: 'MU', name: 'China Eastern Airlines (MU)' },
    { code: 'HU', name: 'Hainan Airlines (HU)' },
    { code: 'KE', name: 'Korean Air (KE)' },
    { code: 'OZ', name: 'Asiana Airlines (OZ)' },
    { code: 'JL', name: 'Japan Airlines (JL)' },
    { code: 'NH', name: 'All Nippon Airways (NH)' },
    { code: 'BR', name: 'EVA Air (BR)' },
    { code: 'CI', name: 'China Airlines (CI)' },
    { code: 'MF', name: 'Xiamen Airlines (MF)' },
    { code: 'ZH', name: 'Shenzhen Airlines (ZH)' },
    { code: 'HO', name: 'Juneyao Airlines (HO)' },
    { code: 'GS', name: 'Tianjin Airlines (GS)' },
    { code: 'SC', name: 'Shandong Airlines (SC)' },
    { code: 'FM', name: 'Shanghai Airlines (FM)' },
    { code: 'CJ', name: 'North China Airlines (CJ)' },
    { code: 'TV', name: 'Tibet Airlines (TV)' },

    // Europe (Expanded)
    { code: 'BA', name: 'British Airways (BA)' },
    { code: 'LH', name: 'Lufthansa (LH)' },
    { code: 'AF', name: 'Air France (AF)' },
    { code: 'KL', name: 'KLM Royal Dutch Airlines (KL)' },
    { code: 'AY', name: 'Finnair (AY)' },
    { code: 'SK', name: 'Scandinavian Airlines (SK)' },
    { code: 'IB', name: 'Iberia (IB)' },
    { code: 'LX', name: 'SWISS International Air Lines (LX)' },
    { code: 'OS', name: 'Austrian Airlines (OS)' },
    { code: 'TK', name: 'Turkish Airlines (TK)' },
    { code: 'SU', name: 'Aeroflot (SU)' },
    { code: 'U2', name: 'easyJet (U2)' },
    { code: 'FR', name: 'Ryanair (FR)' },
    { code: 'U6', name: 'Ural Airlines (U6)' },
    { code: 'B2', name: 'Belavia (B2)' },
    { code: 'FB', name: 'Bulgaria Air (FB)' },
    { code: 'LO', name: 'LOT Polish Airlines (LO)' },
    { code: 'A3', name: 'Aegean Airlines (A3)' },
    { code: 'JU', name: 'Air Serbia (JU)' },
    { code: 'TP', name: 'TAP Air Portugal (TP)' },
    { code: 'RO', name: 'TAROM (RO)' },
    { code: 'EI', name: 'Aer Lingus (EI)' },
    { code: 'BE', name: 'Flybe (BE)' },
    { code: 'HV', name: 'Transavia (HV)' },
    { code: 'VY', name: 'Vueling (VY)' },
    { code: 'ZB', name: 'Monarch Airlines (ZB)' },

    // North America (Expanded)
    { code: 'AA', name: 'American Airlines (AA)' },
    { code: 'UA', name: 'United Airlines (UA)' },
    { code: 'DL', name: 'Delta Air Lines (DL)' },
    { code: 'AC', name: 'Air Canada (AC)' },
    { code: 'WS', name: 'WestJet (WS)' },
    { code: 'AS', name: 'Alaska Airlines (AS)' },
    { code: 'B6', name: 'JetBlue Airways (B6)' },
    { code: 'F9', name: 'Frontier Airlines (F9)' },
    { code: 'NK', name: 'Spirit Airlines (NK)' },
    { code: 'WN', name: 'Southwest Airlines (WN)' },
    { code: 'HA', name: 'Hawaiian Airlines (HA)' },
    { code: 'VX', name: 'Virgin America (VX)' },
    { code: 'PD', name: 'Porter Airlines (PD)' },
    { code: 'C6', name: 'Canjet (C6)' },
    { code: 'MX', name: 'Mexicana Airlines (MX)' },
    { code: 'QX', name: 'Horizon Air (QX)' },
    { code: 'YV', name: 'Mesa Airlines (YV)' },
    { code: 'EV', name: 'ExpressJet Airlines (EV)' },
    { code: 'OO', name: 'SkyWest Airlines (OO)' },

    // South America (Expanded)
    { code: 'LA', name: 'LATAM Airlines (LA)' },
    { code: 'G3', name: 'GOL Linhas Aéreas (G3)' },
    { code: 'AD', name: 'Azul Brazilian Airlines (AD)' },
    { code: 'AR', name: 'Aerolineas Argentinas (AR)' },
    { code: 'CM', name: 'Copa Airlines (CM)' },
    { code: 'AV', name: 'Avianca (AV)' },
    { code: 'JJ', name: 'LATAM Airlines Brasil (JJ)' },
    { code: 'Y8', name: 'Volaris Costa Rica (Y8)' },
    { code: 'P5', name: 'Aerorepública (P5)' },
    { code: '4M', name: 'Líneas Aéreas Privadas Argentinas (4M)' },
    { code: 'LP', name: 'LATAM Peru (LP)' },
    { code: 'XL', name: 'LATAM Chile (XL)' },
    { code: 'O6', name: 'Oceanair (O6)' },
    { code: 'H2', name: 'SKY Airline (H2)' },

    // Oceania (Expanded)
    { code: 'QF', name: 'Qantas Airways (QF)' },
    { code: 'NZ', name: 'Air New Zealand (NZ)' },
    { code: 'VA', name: 'Virgin Australia (VA)' },
    { code: 'DJ', name: 'Virgin Australia Regional Airlines (DJ)' },
    { code: 'TT', name: 'Tigerair Australia (TT)' },
    { code: 'NF', name: 'Air Vanuatu (NF)' },
    { code: 'PF', name: 'Air Tahiti (PF)' },
    { code: 'FJ', name: 'Fiji Airways (FJ)' },
    { code: 'SB', name: 'Air Calin (SB)' },

    // Africa (Expanded)
    { code: 'ET', name: 'Ethiopian Airlines (ET)' },
    { code: 'SA', name: 'South African Airways (SA)' },
    { code: 'MS', name: 'EgyptAir (MS)' },
    { code: 'RW', name: 'RwandAir (RW)' },
    { code: 'KQ', name: 'Kenya Airways (KQ)' },
    { code: 'DZ', name: 'Air Algerie (DZ)' },
    { code: 'MK', name: 'Air Mauritius (MK)' },
    { code: 'KM', name: 'Air Comoros (KM)' },
    { code: 'AT', name: 'Royal Air Maroc (AT)' },
    { code: 'TU', name: 'Tunisair (TU)' },
    { code: 'AH', name: 'Air Algérie (AH)' },
    { code: 'VT', name: 'Air Tahiti (VT)' },
    { code: 'WB', name: 'RwandAir (WB)' },
    { code: 'MN', name: 'Mauritania Airlines (MN)' },
    { code: 'QU', name: 'Uganda Airlines (QU)' },

    // Additional Airlines
    { code: 'EY', name: 'Etihad Airways (EY)' },
    { code: 'VS', name: 'Virgin Atlantic (VS)' },
    { code: 'BI', name: 'Royal Brunei Airlines (BI)' },
    { code: 'G9', name: 'Air Arabia (G9)' },
    { code: 'FZ', name: 'Flydubai (FZ)' },
    { code: 'PC', name: 'Pegasus Airlines (PC)' },
    { code: 'W6', name: 'Wizz Air (W6)' },
    { code: 'U4', name: 'Buddha Air (U4)' },
    { code: 'H9', name: 'Himalaya Airlines (H9)' },
    { code: 'WY', name: 'Oman Air (WY)' },
    { code: 'PW', name: 'Precision Air (PW)' },
    { code: 'MR', name: 'Mauritius Air (MR)' }
    ];

    // Clear existing options
    $(selectElement).empty();

    // Add default option
    const defaultOption = new Option('Select Airline', '');
    $(selectElement).append(defaultOption);

    // Add airline options
    airlines.forEach(airline => {
        const option = new Option(airline.name, airline.code);
        $(option).attr('data-tokens', `${airline.name} ${airline.code}`);
        $(selectElement).append(option);
    });

    // If we have a selected airline, try to find it in our list
    if (selectedAirline) {
        // Try to find by code first
        let found = airlines.find(a => a.code === selectedAirline);
        if (found) {
            $(selectElement).val(found.code);
        } else {
            // Try to find by name
            found = airlines.find(a => a.name === selectedAirline);
            if (found) {
                $(selectElement).val(found.code);
            } else {
                // If not found, add it as a custom option
                const option = new Option(selectedAirline, selectedAirline);
                $(option).attr('data-tokens', selectedAirline);
                $(selectElement).append(option);
                $(selectElement).val(selectedAirline);
            }
        }
    }

    // Refresh the Bootstrap Select
    $(selectElement).selectpicker('refresh');
}

// Function to edit ticket
function editTicket(ticketId) {
    // Show loader
    document.getElementById('editLoader').style.display = 'block';

    // Fetch ticket data
    fetch(`fetch_ticket_by_id.php?id=${ticketId}`)
        .then(response => response.json())
        .then(response => {
            if (!response.success) {
                throw new Error(response.message || 'Failed to fetch ticket data');
            }

            const data = response.ticket;

            // Populate airline dropdown with selected airline
            const editAirlineSelect = document.getElementById('editAirline');
            // Initialize Bootstrap Select on the airline dropdown
            $(editAirlineSelect)
                .addClass('selectpicker')
                .attr('data-live-search', 'true')
                .attr('data-style', 'btn-light');
            
            // Ensure we have a valid airline value
            const airlineValue = data.airline || '';
            console.log('Setting airline value:', airlineValue); // Debug log
            populateAirlineOptions(editAirlineSelect, airlineValue);
            
            // Refresh the Bootstrap Select
            $(editAirlineSelect).selectpicker('refresh');
            
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
            document.getElementById('editBase').value = data.price;
            document.getElementById('editSold').value = data.sold;
            document.getElementById('editDiscount').value = data.discount || 0;
            document.getElementById('editPro').value = data.profit;
            document.getElementById('editCurr').value = data.currency;
            document.getElementById('editPaidTo').value = data.paid_to;
            document.getElementById('editDescription').value = data.description || '';

            // Handle round trip fields
            if (data.trip_type === 'round_trip') {
                document.getElementById('editReturnJourneyFields').style.display = 'block';
                document.getElementById('editReturnDateField').style.display = 'block';
                document.getElementById('editReturnOrigin').value = data.return_origin || '';
                document.getElementById('editReturnDestination').value = data.return_destination || '';
                document.getElementById('editReturnDate').value = data.return_date !== '0000-00-00' ? data.return_date : '';
            } else {
                document.getElementById('editReturnJourneyFields').style.display = 'none';
                document.getElementById('editReturnDateField').style.display = 'none';
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

            // Store original values for comparison
            window.originalBase = data.price;
            window.originalSold = data.sold;

            // Hide loader and show modal
            document.getElementById('editLoader').style.display = 'none';
            $('#editTicketModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('editLoader').style.display = 'none';
            showToast('Error fetching ticket data: ' + error.message, 'error');
        });
}

// Add event listeners to update balances in real-time when editing base and sold prices
document.addEventListener('DOMContentLoaded', function() {
    const editBaseInput = document.getElementById('editBase');
    const editSoldInput = document.getElementById('editSold');
    const editTripTypeSelect = document.getElementById('editTripType');
    
    // Store original values when the modal opens
    let originalBase = 0;
    let originalSold = 0;
    
    // When the edit modal is shown, store the original values
    $('#editTicketModal').on('shown.bs.modal', function() {
        originalBase = parseFloat(editBaseInput.value) || 0;
        originalSold = parseFloat(editSoldInput.value) || 0;
        
        console.log('Original values stored - Base:', originalBase, 'Sold:', originalSold);
        
        // Show/hide return fields based on trip type
        toggleReturnFields();
    });
    
    // Toggle return fields visibility based on trip type
    editTripTypeSelect.addEventListener('change', toggleReturnFields);
    
    function toggleReturnFields() {
        const isRoundTrip = editTripTypeSelect.value === 'round_trip';
        document.getElementById('editReturnJourneyFields').style.display = isRoundTrip ? 'block' : 'none';
        document.getElementById('editReturnDateField').style.display = isRoundTrip ? 'block' : 'none';
        
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
                        const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
                        
                        // Update the option text with the new balance preview
                        selectedOption.text = `${data.supplier_name} (Balance: ${newBalance.toFixed(2)})`;
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
        const currency = document.getElementById('editCurr').value;
        
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
                        const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                        
                        // Update the option text with the new balance preview
                        selectedOption.text = `${data.client_name} (${currency}: ${newBalance.toFixed(2)})`;
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
    document.getElementById('editTicketForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        // Show loader
        document.getElementById('editLoader').style.display = 'block';
        
        const formData = new FormData(this);
        
        // Add the original values to the form data for server-side comparison
        formData.append('originalBase', originalBase);
        formData.append('originalSold', originalSold);
        
        // Validate required fields for round trip
        if (editTripTypeSelect.value === 'round_trip') {
            const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
            for (const fieldId of returnFields) {
                const field = document.getElementById(fieldId);
                if (field && !field.value) {
                    showToast(`Please fill in the ${field.previousElementSibling.textContent} field.`, 'error');
                    document.getElementById('editLoader').style.display = 'none';
                    return;
                }
            }
        }
        
        fetch('update_ticket.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            // Hide loader
            document.getElementById('editLoader').style.display = 'none';
            
            if (data.success) {
                showToast('Ticket updated successfully', 'success');
                $('#editTicketModal').modal('hide');
                location.reload(); // Refresh to see updated balances
            } else {
                showToast('Error updating ticket: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error updating ticket:', error);
            document.getElementById('editLoader').style.display = 'none';
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
        
        // Set return journey details if applicable
        if (ticketData.trip_type === 'round_trip') {
            document.getElementById('editReturnDestination').value = ticketData.return_destination || '';
            document.getElementById('editReturnDate').value = ticketData.return_date || '';
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