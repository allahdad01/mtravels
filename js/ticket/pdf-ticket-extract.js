/**
 * PDF Ticket Extraction Module
 * Extracts ticket information from PDF using server-side pattern matching
 * Supports: Kam Air, Ariana Afghan Airlines, IATA formats
 */

document.addEventListener('DOMContentLoaded', function() {
    setupPdfTicketExtraction();
});

/**
 * Setup PDF ticket extraction handlers
 */
function setupPdfTicketExtraction() {
    const uploadInput = document.getElementById('pdfUpload');
    if (!uploadInput) return;
    
    uploadInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file
        if (!file.type.match('application/pdf')) {
            showExtractionStatus('❌ Please upload a PDF file', 'error');
            uploadInput.value = '';
            return;
        }
        
        if (file.size > 10 * 1024 * 1024) {
            showExtractionStatus('❌ File exceeds 10MB limit', 'error');
            uploadInput.value = '';
            return;
        }
        
        extractTicketFromPdf(file);
    });
}

/**
 * Extract ticket data from PDF
 */
async function extractTicketFromPdf(file) {
    const formData = new FormData();
    formData.append('pdf_file', file);
    
    showExtractionStatus('⏳ Processing PDF...', 'info');
    
    try {
        const response = await fetch('../api/ticket/extract_ticket.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Extraction failed');
        }
        
        if (data.success) {
            // Auto-fill form with extracted data
            populateTicketForm(data.data);
            
            // Show success message
            const confidence = data.confidence ? Math.round(data.confidence * 100) : 0;
            const format = data.format_detected || 'unknown';
            showExtractionStatus(
                `✅ Extracted successfully! (Format: ${format}, Confidence: ${confidence}%)`,
                'success'
            );
            
            // Log extraction details
            console.log('Extraction Details:', {
                format: format,
                confidence: confidence,
                data: data.data
            });
        } else {
            showExtractionStatus(`❌ ${data.message}`, 'error');
        }
    } catch (error) {
        console.error('Extraction Error:', error);
        showExtractionStatus(`❌ Error: ${error.message}`, 'error');
    }
}

/**
 * Populate ticket form with extracted data
 */
function populateTicketForm(data) {
    console.log('Populating form with data:', data);
    
    // Handle group booking - use first passenger for flight info
    const flightData = data.passengers ? data.passengers[0] : data;
    
    // Fill flight information first
    fillFlightDetails(flightData);
    
    // Set passenger count if we have multiple passengers
    if (data.passengers && Array.isArray(data.passengers)) {
        const passengerCount = data.passengers.length;
        console.log(`Setting adult count to ${passengerCount} and filling ${passengerCount} passengers`);
        
        // Set adult count
        const adultCountField = document.getElementById('adultCount');
        if (adultCountField) {
            adultCountField.value = passengerCount;
            triggerChange(adultCountField);
            
            // Wait for passenger rows to be created, then fill them
            // Use setTimeout to allow DOM to update
            setTimeout(() => {
                console.log('Filling passenger data after DOM update');
                fillGroupPassengerForm(data.passengers);
            }, 500);
        }
    } else if (data.passenger_name) {
        console.log('Filling single passenger');
        fillSinglePassengerForm(data);
    }
}

/**
 * Fill single passenger form
 */
function fillSinglePassengerForm(data) {
    const fields = {
        'pnr': data.pnr,
        'airline': data.airline,
        'origin': data.origin,
        'destination': data.destination,
        'departureDate': data.departure_date,
        'departureTime': data.departure_time,
        'issueDate': data.issue_date
    };
    
    Object.entries(fields).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element && value) {
            element.value = value;
            triggerChange(element);
            console.log(`Filled ${id}: ${value}`);
        }
    });
}

/**
 * Fill group passenger form
 */
function fillGroupPassengerForm(data) {
    const container = document.getElementById('passengersContainer');
    if (!container) return;
    
    const passengers = data;
    console.log(`Filling ${passengers.length} passengers in container`);
    
    passengers.forEach((passenger, index) => {
        // Passenger indices in form are 1-based, not 0-based
        const passengerIndex = index + 1;
        console.log(`Processing passenger ${passengerIndex}: ${passenger.passenger_name}`);
        
        // Find passenger row using data-passenger attribute (1-based indexing)
        const passengerRow = container.querySelector(`[data-passenger="${passengerIndex}"]`);
        
        if (passengerRow) {
            // Use the correct field IDs as defined in passenger_info.js and passenger-count.js
            const nameField = document.getElementById(`passengerName_${passengerIndex}`);
            
            if (nameField) {
                nameField.value = passenger.passenger_name || passenger.name || '';
                triggerChange(nameField);
                console.log(`✓ Filled passenger ${passengerIndex} name: ${nameField.value}`);
            } else {
                console.warn(`Could not find name field for passenger ${passengerIndex}`);
            }
            
            // Fill ticket number if available
            // Note: ticket fields are not in the standard form structure,
            // so we skip this for now or add it if needed
        } else {
            console.warn(`Could not find passenger row for index ${passengerIndex}`);
        }
    });
}

/**
 * Fill flight details common to all passengers
 */
function fillFlightDetails(data) {
    // Map to modal field IDs
    const flightFields = {
        'pnr': data.pnr,
        'airline': data.airline,
        'origin': data.origin,
        'destination': data.destination,
        'departureDate': data.departure_date,
        'departureTime': data.departure_time,
        'issueDate': data.issue_date
    };
    
    console.log('Flight details fields:', flightFields);
    
    Object.entries(flightFields).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element && value) {
            element.value = value;
            triggerChange(element);
            console.log(`Filled flight field ${id}: ${value}`);
        } else if (!element && value) {
            console.warn(`Flight field not found: ${id}`);
        }
    });
}

/**
 * Show extraction status message
 */
function showExtractionStatus(message, type) {
    let statusDiv = document.getElementById('extractionStatus');
    
    if (!statusDiv) {
        statusDiv = document.createElement('div');
        statusDiv.id = 'extractionStatus';
        statusDiv.style.marginTop = '15px';
        
        const uploadInput = document.getElementById('pdfUpload');
        if (uploadInput && uploadInput.parentElement) {
            uploadInput.parentElement.insertBefore(statusDiv, uploadInput.nextElementSibling);
        }
    }
    
    statusDiv.innerHTML = message;
    statusDiv.style.display = 'block';
    statusDiv.style.padding = '12px 15px';
    statusDiv.style.borderRadius = '4px';
    statusDiv.style.marginBottom = '15px';
    
    // Remove previous classes
    statusDiv.className = 'alert';
    
    if (type === 'success') {
        statusDiv.classList.add('alert-success');
        // Auto-hide success messages
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    } else if (type === 'error') {
        statusDiv.classList.add('alert-danger');
    } else {
        statusDiv.classList.add('alert-info');
    }
}

/**
 * Trigger change event on element
 */
function triggerChange(element) {
    if (element) {
        const event = new Event('change', { bubbles: true });
        element.dispatchEvent(event);
    }
}
