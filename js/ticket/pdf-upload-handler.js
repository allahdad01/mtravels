/**
 * PDF Upload Handler for Ticket Booking Modal
 * Handles drag-and-drop and file selection for PDF uploads
 * Uses server-side pattern matching for ticket extraction
 * 
 * Supports:
 * - Kam Air tickets
 * - Ariana Afghan Airlines tickets
 * - Standard IATA format tickets
 * - Drag and drop file upload
 * - Real-time validation
 * - Auto-form population
 */

document.addEventListener('DOMContentLoaded', function() {
    setupPdfUploadZone();
    setupPdfFileInput();
});

/**
 * Setup drag-and-drop upload zone
 */
function setupPdfUploadZone() {
    const uploadZone = document.getElementById('pdfUploadZone');
    const fileInput = document.getElementById('ticketPdfFile');
    
    if (!uploadZone || !fileInput) return;
    
    // Click to select file
    uploadZone.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, preventDefaults, false);
    });
    
    // Highlight zone on drag
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadZone.addEventListener(eventName, function() {
            uploadZone.style.backgroundColor = '#e8f4f8';
            uploadZone.style.borderColor = '#2ed8b6';
            uploadZone.style.borderWidth = '2px';
            uploadZone.style.borderStyle = 'dashed';
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, function() {
            uploadZone.style.backgroundColor = '';
            uploadZone.style.borderColor = '';
            uploadZone.style.borderWidth = '';
            uploadZone.style.borderStyle = '';
        }, false);
    });
    
    // Handle drop
    uploadZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            const file = files[0];
            
            // Validate file type
            if (!file.type.match('application/pdf')) {
                showPdfStatus('❌ Please upload a PDF file', 'error');
                return;
            }
            
            // Validate file size (10MB)
            if (file.size > 10 * 1024 * 1024) {
                showPdfStatus('❌ File too large. Maximum 10MB allowed', 'error');
                return;
            }
            
            // Process file
            processAndExtractPdf(file);
        }
    }, false);
}

/**
 * Setup file input change handler
 */
function setupPdfFileInput() {
    const fileInput = document.getElementById('ticketPdfFile');
    
    if (!fileInput) return;
    
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (!file) return;
        
        // Validate file type
        if (!file.type.match('application/pdf')) {
            showPdfStatus('❌ Please upload a PDF file', 'error');
            fileInput.value = '';
            return;
        }
        
        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            showPdfStatus('❌ File too large. Maximum 10MB allowed', 'error');
            fileInput.value = '';
            return;
        }
        
        // Process file
        processAndExtractPdf(file);
    });
}

/**
 * Process and extract PDF using server-side patterns
 */
async function processAndExtractPdf(file) {
    showPdfStatus('⏳ Reading PDF and extracting ticket...', 'info');
    
    const formData = new FormData();
    formData.append('pdf_file', file);
    
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
            // Fill form with extracted data
            fillTicketForm(data.data);
            
            // Show success with details
            const confidence = data.confidence ? Math.round(data.confidence * 100) : 0;
            const format = data.format_detected || 'unknown';
            
            showPdfStatus(
                `✅ Ticket extracted successfully! (Format: <strong>${format}</strong>, Confidence: <strong>${confidence}%</strong>)`,
                'success'
            );
            
            // Log for debugging
            console.log('Extraction Details:', {
                format: format,
                confidence: confidence,
                passengers: data.data.passengers ? data.data.passengers.length : 1,
                data: data.data
            });
        } else {
            showPdfStatus(`❌ Extraction failed: ${data.message}`, 'error');
        }
    } catch (error) {
        console.error('PDF Extraction Error:', error);
        showPdfStatus(`❌ Error: ${error.message}`, 'error');
    }
}

/**
 * Fill booking form with extracted ticket data
 */
function fillTicketForm(data) {
    // Handle group booking - use first passenger's data for common fields
    const flightData = data.passengers ? data.passengers[0] : data;
    
    // Map extracted data to modal form field IDs
    // Must match IDs in modals/ticket/book_ticket_modal.php
    const flightFields = {
        'pnr': flightData.pnr,
        'airline': flightData.airline,
        'origin': flightData.origin,
        'destination': flightData.destination,
        'departureDate': flightData.departure_date,
        'departureTime': flightData.departure_time,
        'issueDate': flightData.issue_date
    };
    
    console.log('Filling form with data:', flightFields);
    
    // Fill each flight field
    for (const [fieldId, value] of Object.entries(flightFields)) {
        const field = document.getElementById(fieldId);
        if (field && value) {
            // Format date/time values properly
            let formattedValue = value;
            
            // Convert date format if needed (YYYY-MM-DD is HTML5 date format)
            if ((fieldId === 'departureDate' || fieldId === 'departureTime' || fieldId === 'issueDate') && value) {
                formattedValue = value; // Already in correct format
            }
            
            field.value = formattedValue;
            triggerFieldChange(field);
            console.log(`Filled ${fieldId}: ${formattedValue}`);
        } else if (!field) {
            console.warn(`Field not found: ${fieldId}`);
        }
    }
    
    // Set passenger count if we have multiple passengers
    if (data.passengers && Array.isArray(data.passengers)) {
        const passengerCount = data.passengers.length;
        console.log(`Setting adult count to ${passengerCount}`);
        
        // Set adult count
        const adultCountField = document.getElementById('adultCount');
        if (adultCountField) {
            adultCountField.value = passengerCount;
            triggerFieldChange(adultCountField);
            
            // Wait for passenger rows to be created, then fill them
            // Use setTimeout to allow DOM to update
            setTimeout(() => {
                console.log('Filling passenger data after DOM update');
                fillPassengerDataMultiple(data.passengers);
            }, 500);
        }
    } else if (flightData.passenger_name) {
        console.log('Filling single passenger:', flightData.passenger_name);
        fillPassengerDataSingle(flightData);
    }
}

/**
 * Fill single passenger data
 */
function fillPassengerDataSingle(data) {
    const passengerField = document.getElementById('passengerName') || 
                          document.querySelector('[name="passenger_name"]');
    
    if (passengerField && data.passenger_name) {
        passengerField.value = data.passenger_name;
        triggerFieldChange(passengerField);
    }
}

/**
 * Fill multiple passenger data from group booking
 */
function fillPassengerDataMultiple(passengers) {
    const container = document.getElementById('passengersContainer');
    if (!container) return;
    
    console.log(`Attempting to fill ${passengers.length} passengers`);
    
    passengers.forEach((passenger, index) => {
        // Passenger indices in form are 1-based, not 0-based
        const passengerIndex = index + 1;
        console.log(`Processing passenger ${passengerIndex}: ${passenger.passenger_name}`);
        
        // Find passenger row using data-passenger attribute (1-based indexing)
        const passengerRow = container.querySelector(`[data-passenger="${passengerIndex}"]`);
        
        if (passengerRow) {
            // Use the correct field IDs as defined in passenger_info.js and passenger-count.js
            const nameField = document.getElementById(`passengerName_${passengerIndex}`);
            
            if (nameField && passenger.passenger_name) {
                nameField.value = passenger.passenger_name;
                triggerFieldChange(nameField);
                console.log(`✓ Filled passenger ${passengerIndex} name: ${passenger.passenger_name}`);
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
 * Show status message with auto-hide for success
 */
function showPdfStatus(message, type) {
    const uploadZone = document.getElementById('pdfUploadZone');
    if (!uploadZone) return;
    
    // Remove existing status
    const existingStatus = uploadZone.nextElementSibling;
    if (existingStatus && existingStatus.classList.contains('pdf-status')) {
        existingStatus.remove();
    }
    
    // Create new status element
    const statusDiv = document.createElement('div');
    statusDiv.className = `alert pdf-status`;
    statusDiv.style.marginTop = '15px';
    statusDiv.innerHTML = message;
    
    if (type === 'success') {
        statusDiv.classList.add('alert-success');
    } else if (type === 'error') {
        statusDiv.classList.add('alert-danger');
    } else {
        statusDiv.classList.add('alert-info');
    }
    
    // Insert after upload zone
    uploadZone.parentElement.insertBefore(statusDiv, uploadZone.nextElementSibling);
    
    // Auto-remove success messages after 6 seconds
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.remove();
        }, 6000);
    }
}

/**
 * Trigger change event on form field
 */
function triggerFieldChange(field) {
    if (field) {
        const event = new Event('change', { bubbles: true });
        field.dispatchEvent(event);
    }
}

/**
 * Prevent default drag behaviors
 */
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}
