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

/**
 * Clean airline value by removing extra whitespace and newlines
 * Extracts the actual airline name from text like "Stop Baggage\nFLY DUBAI"
 * But preserves valid airline names like "Ariana Afghan Airlines"
 */
function cleanAirlineValue(value) {
    if (!value) return value;
    
    // Remove newlines and extra whitespace
    let cleaned = value.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
    
    // Check if the cleaned value might be a valid airline name already
    // Known airline name patterns: 1-4 words, starting with capital letter
    const words = cleaned.split(' ');
    
    // If it's a reasonable length (2-4 words, typical airline name), return it as-is
    if (words.length >= 2 && words.length <= 4) {
        // Check if it looks like a real airline name (no suspicious words before it)
        const suspiciousWords = ['STOP', 'BAGGAGE', 'TICKET', 'BOOKING', 'RESERVATION', 'FLIGHT', 'DATE', 'TIME', 'DEPART', 'ARRIVE'];
        const hasSuspiciousWord = suspiciousWords.some(word => cleaned.toUpperCase().includes(word));
        
        if (!hasSuspiciousWord) {
            return cleaned; // Likely a valid airline name
        }
    }
    
    // Only try to extract if there are suspicious words or too many words
    if (words.length > 4 || true) {
        // Look for airline keywords (usually in uppercase)
        const airlineKeywords = ['DUBAI', 'EMIRATES', 'AIRWAYS', 'AIR', 'AIRLINES', 'QATAR', 'TURKISH', 'KAM', 'ARIANA', 'ARABIA'];
        
        // Find the first substantial airline keyword (not just "AIR" or "AIRLINES" alone)
        for (let i = 0; i < words.length; i++) {
            const word = words[i].toUpperCase();
            // Look for multi-letter keywords or combinations
            if (airlineKeywords.some(keyword => word.includes(keyword) && keyword.length > 3)) {
                // Found a good keyword, take from this position
                cleaned = words.slice(Math.max(0, i - 1)).join(' ');
                break;
            }
        }
        
        // Drop leading junk words (STOP, BAGGAGE, etc.) before the airline name
        const junkWords = ['STOP', 'BAGGAGE', 'TICKET', 'BOOKING', 'RESERVATION', 'FLIGHT', 'DATE', 'TIME', 'DEPART', 'ARRIVE'];
        let parts = cleaned.split(' ');
        while (parts.length > 0 && junkWords.some(word => parts[0].toUpperCase().includes(word))) {
            parts.shift();
        }
        cleaned = parts.join(' ') || cleaned;
        
        // Fallback: just take the last few words
        if (parts.length === 0) {
            cleaned = words.slice(-3).join(' ');
        }
    }
    
    return cleaned;
}

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
            console.log({
                format: format,
                confidence: confidence,
                passengers: data.data.passengers ? data.data.passengers.length : 1,
                data: data.data
            });
        } else {
            showPdfStatus(`❌ Extraction failed: ${data.message}`, 'error');
        }
    } catch (error) {

        showPdfStatus(`❌ Error: ${error.message}`, 'error');
    }
}

/**
 * Fill booking form with extracted ticket data
 */
function fillTicketForm(data) {
    // Handle group booking - use first passenger's data for common fields
    const flightData = data.passengers && data.passengers.length > 0 ? data.passengers[0] : data;
    
    // Multi-segment flights: populate each leg row from segments
    const segments = (flightData.segments && Array.isArray(flightData.segments) && flightData.segments.length > 0) ? flightData.segments : null;
    
    if (segments) {
        // Common fields
        setFormField('pnr', flightData.pnr);
        setFormField('issueDate', flightData.issue_date || flightData.booked_date);
        
        const fallbackAirline = cleanAirlineValue(flightData.airline);
        const isRoundTrip = flightData.trip_type === 'Round Trip' || flightData.trip_type === 'round_trip';
        
        // Set trip type dropdown and toggle return group for round trip
        if (isRoundTrip) {
            const tripTypeSelect = document.getElementById('tripType');
            if (tripTypeSelect) {
                tripTypeSelect.value = 'round_trip';
                tripTypeSelect.dispatchEvent(new Event('change'));
            }
            // Directly show return group in case trip-type.js listener isn't loaded
            const returnGroup = document.getElementById('returnFlightSegmentsGroup');
            if (returnGroup) returnGroup.style.display = '';
        }
        
        if (isRoundTrip && segments.length > 1) {
            // Round trip: first segment = outbound leg 1, remaining segments = return legs
            const outSeg = segments[0];
            const outTime = outSeg.dep_time || outSeg.time || outSeg.departure_time;
            const outArrTime = outSeg.arr_time || outSeg.arrival_time;
            const outDate = outSeg.date || outSeg.departure_date;
            const outArrDate = outSeg.arrival_date || outSeg.date || outSeg.departure_date;
            const outAirline = cleanAirlineValue(outSeg.airline) || fallbackAirline;
            setFormField('origin', outSeg.origin);
            setFormField('destination', outSeg.destination);
            setFormField('airline', outAirline);
            setFormField('flightNumber', outSeg.flight_number);
            setFormField('departureDate', outDate);
            setFormField('departureTime', outTime);
            setFormField('arrivalDate', outArrDate);
            setFormField('arrivalTime', outArrTime);
            
            // Return segments go into the return flight group
            // Return Leg 1 already exists as static HTML, only add new rows for legs 2+
            for (let i = 1; i < segments.length; i++) {
                const seg = segments[i];
                const segTime = seg.dep_time || seg.time || seg.departure_time;
                const segArrTime = seg.arr_time || seg.arrival_time;
                const segDate = seg.date || seg.departure_date;
                const segArrDate = seg.arrival_date || seg.date || seg.departure_date;
                const segAirline = cleanAirlineValue(seg.airline) || fallbackAirline;
                // Only click add button for legs 2+ (leg 1 is already in the DOM)
                if (i > 1) {
                    const addReturnBtn = document.getElementById('addReturnFlightLegBtn');
                    if (addReturnBtn) addReturnBtn.click();
                }
                const rows = document.querySelectorAll('#returnFlightLegsContainer .flight-leg-row');
                const row = rows[i - 1];
                if (!row) continue;
                setLegRowValue(row, 'leg-origin', seg.origin);
                setLegRowValue(row, 'leg-destination', seg.destination);
                setLegRowValue(row, 'leg-airline', segAirline);
                setLegRowValue(row, 'leg-flight-number', seg.flight_number);
                setLegRowValue(row, 'leg-date', segDate);
                setLegRowValue(row, 'leg-time', segTime);
                setLegRowValue(row, 'leg-arrival-date', segArrDate);
                setLegRowValue(row, 'leg-arrival-time', segArrTime);
            }
        } else {
            // Multi-segment or single: all segments go into outbound legs
            segments.forEach((seg, index) => {
            const segTime = seg.dep_time || seg.time || seg.departure_time;
            const segArrTime = seg.arr_time || seg.arrival_time;
            const segDate = seg.date || seg.departure_date;
            const segArrDate = seg.arrival_date || seg.date || seg.departure_date;
            const segAirline = cleanAirlineValue(seg.airline) || fallbackAirline;
            if (index === 0) {
                // Static leg 1 (filled by ID for PDF compat)
                setFormField('origin', seg.origin);
                setFormField('destination', seg.destination);
                setFormField('airline', segAirline);
                setFormField('flightNumber', seg.flight_number);
                setFormField('departureDate', segDate);
                setFormField('departureTime', segTime);
                setFormField('arrivalDate', segArrDate);
                setFormField('arrivalTime', segArrTime);
            } else {
                const addBtn = document.getElementById('addFlightLegBtn');
                if (addBtn) addBtn.click();
                const rows = document.querySelectorAll('#flightLegsContainer .flight-leg-row');
                const row = rows[index];
                if (!row) return;
                setLegRowValue(row, 'leg-origin', seg.origin);
                setLegRowValue(row, 'leg-destination', seg.destination);
                setLegRowValue(row, 'leg-airline', segAirline);
                setLegRowValue(row, 'leg-flight-number', seg.flight_number);
                setLegRowValue(row, 'leg-date', segDate);
                setLegRowValue(row, 'leg-time', segTime);
                setLegRowValue(row, 'leg-arrival-date', segArrDate);
                setLegRowValue(row, 'leg-arrival-time', segArrTime);
            }
        });
        }
    } else {
        // Map extracted data to modal form field IDs
        // Must match IDs in modals/ticket/book_ticket_modal.php
        const flightFields = {
            'pnr': flightData.pnr,
            'airline': cleanAirlineValue(flightData.airline),
            'origin': flightData.origin,
            'destination': flightData.destination,
            'departureDate': flightData.departure_date,
            'departureTime': flightData.departure_time,
            'arrivalDate': flightData.arrival_date,
            'arrivalTime': flightData.arrival_time,
            'flightNumber': flightData.flight_number,
            'issueDate': flightData.issue_date
        };
        
        // Fill each flight field
        for (const [fieldId, value] of Object.entries(flightFields)) {
            const field = document.getElementById(fieldId);
            if (field && value) {
                // Format date/time values properly
                let formattedValue = value;
                
                // Convert date format if needed (YYYY-MM-DD is HTML5 date format)
                if ((fieldId === 'departureDate' || fieldId === 'departureTime' || fieldId === 'arrivalDate' || fieldId === 'arrivalTime' || fieldId === 'issueDate') && value) {
                    formattedValue = value; // Already in correct format
                }
                
                // Handle select elements (like airline dropdown)
                if (field.tagName === 'SELECT') {
                    // Try to find option by exact value match
                    let option = Array.from(field.options).find(opt => opt.value === value || opt.text === value);
                    if (option) {
                        field.value = option.value;
    
                    } else {
                        // Try fuzzy matching for airline names
                        if (fieldId === 'airline') {
                            // Try to match against airline database
                            let matchedAirline = null;
                            
                            if (typeof AIRLINES !== 'undefined') {
                                const searchTerm = value.toLowerCase().trim();
                                const words = searchTerm.split(' ');
                                
                                // Step 1: Try exact match (full name or base name)
                                matchedAirline = AIRLINES.find(airline => 
                                    airline.name === value || 
                                    airline.name.split(' (')[0] === value ||
                                    value.toLowerCase() === airline.name.toLowerCase() ||
                                    value.toLowerCase() === airline.name.split(' (')[0].toLowerCase()
                                );
                                if (matchedAirline) {
    
                                }
                                
                                // Step 2: Try matching by first 2-3 words (e.g., "Ariana Afghan" or "Ariana Afghan Airlines")
                                if (!matchedAirline && words.length >= 2) {
                                    // Try progressively longer matches
                                    for (let wordCount = Math.min(words.length, 3); wordCount >= 2; wordCount--) {
                                        const partialTerm = words.slice(0, wordCount).join(' ').toLowerCase();
                                        matchedAirline = AIRLINES.find(airline => 
                                            airline.name.split(' (')[0].toLowerCase().startsWith(partialTerm) ||
                                            partialTerm.startsWith(airline.name.split(' (')[0].toLowerCase().split(' ')[0])
                                        );
                                        if (matchedAirline) {
    
                                            break;
                                        }
                                    }
                                }
                                
                                // Step 3: Try matching by first word + contains second word
                                if (!matchedAirline && words.length >= 2) {
                                    matchedAirline = AIRLINES.find(airline => {
                                        const airlineName = airline.name.split(' (')[0].toLowerCase();
                                        return airlineName.startsWith(words[0]) && airlineName.includes(words[1]);
                                    });
                                    if (matchedAirline) {
    
                                    }
                                }
                                
                                // Step 4: Last resort - find airline containing most words from search term
                                if (!matchedAirline) {
                                    let bestMatch = null;
                                    let bestMatchCount = 0;
                                    AIRLINES.forEach(airline => {
                                        const airlineName = airline.name.toLowerCase();
                                        const matchCount = words.filter(word => airlineName.includes(word)).length;
                                        if (matchCount > bestMatchCount) {
                                            bestMatchCount = matchCount;
                                            bestMatch = airline;
                                        }
                                    });
                                    if (bestMatch && bestMatchCount >= Math.ceil(words.length / 2)) {
                                        matchedAirline = bestMatch;
    
                                    }
                                }
                            }
                            
                            // If found in database, use the airline code to set the select value
                            if (matchedAirline) {
                                field.value = matchedAirline.code;
                                option = Array.from(field.options).find(opt => opt.value === matchedAirline.code);
                                if (option) {
    
                                }
                            } else {
    
                            }
                        } else {
    
                        }
                    }
                } else {
                    // Regular input field
                    field.value = formattedValue;
    
                }
                
                triggerFieldChange(field);
            } else if (!field) {
    
            }
        }
    }
    
    // Set passenger count if we have multiple passengers
    if (data.passengers && Array.isArray(data.passengers) && data.passengers.length > 0) {
        // Count adults (M/F) vs children (C/Child) based on gender
        let adultCount = 0;
        let childCount = 0;
        data.passengers.forEach(p => {
            const g = (p.gender || '').toUpperCase();
            if (g === 'C' || g === 'CHILD') {
                childCount++;
            } else {
                adultCount++;
            }
        });
        // Fallback: if no gender data, treat all as adults
        if (adultCount === 0 && childCount === 0) {
            adultCount = data.total_passengers || data.passengers.length;
        }

        
        // Set adult and child counts (use jQuery to ensure passenger_info.js change listener fires)
        const adultCountField = document.getElementById('adultCount');
        const childCountField = document.getElementById('childCount');
        if (adultCountField) {
            adultCountField.value = adultCount;
            // Fire both native and jQuery change events for compatibility
            adultCountField.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof $ !== 'undefined') $(adultCountField).trigger('change');
        }
        if (childCountField && childCount > 0) {
            childCountField.value = childCount;
            childCountField.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof $ !== 'undefined') $(childCountField).trigger('change');
        }
        
        // Wait for passenger rows to be created, then fill them
        // Use setTimeout to allow DOM to update
        setTimeout(() => {

            fillPassengerDataMultiple(data.passengers);
        }, 1000);
    } else if (flightData.passenger_name) {

        fillPassengerDataSingle(flightData);
    }
}

/**
 * Set a form field by ID and trigger change (used by multi-segment fill)
 */
function setFormField(id, value) {
    if (!value) return;
    const field = document.getElementById(id);
    if (field) {
        field.value = value;
        triggerFieldChange(field);
    }
}

/**
 * Set a leg row field by class and trigger change (used by multi-segment fill)
 */
function setLegRowValue(row, className, value) {
    if (!value) return;
    const field = row.querySelector('.' + className);
    if (field) {
        field.value = value;
        triggerFieldChange(field);
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
 * Sorts passengers: adults (M/F) first, then children (C) to match form row order
 */
function fillPassengerDataMultiple(passengers) {
    const container = document.getElementById('passengersContainer');
    if (!container) return;
    

    
    // Sort: adults (M/F) first, then children (C/Child)
    const sorted = [...passengers].sort((a, b) => {
        const aIsChild = ((a.gender || '').toUpperCase() === 'C' || (a.gender || '').toUpperCase() === 'CHILD') ? 1 : 0;
        const bIsChild = ((b.gender || '').toUpperCase() === 'C' || (b.gender || '').toUpperCase() === 'CHILD') ? 1 : 0;
        return aIsChild - bIsChild;
    });
    
    sorted.forEach((passenger, index) => {
        // Passenger indices in form are 1-based, not 0-based
        const passengerIndex = index + 1;

        
        // Find passenger row using data-passenger attribute (1-based indexing)
        const passengerRow = container.querySelector(`[data-passenger="${passengerIndex}"]`);
        
        if (passengerRow) {
            // Use the correct field IDs as defined in passenger_info.js and passenger-count.js
            const nameField = document.getElementById(`passengerName_${passengerIndex}`);
            
            if (nameField && passenger.passenger_name) {
                nameField.value = passenger.passenger_name;
                triggerFieldChange(nameField);

            } else {

            }
            
            // Set gender if available
            const gender = (passenger.gender || '').toUpperCase();
            if (gender) {
                const genderField = document.getElementById(`gender_${passengerIndex}`);
                if (genderField) {
                    if (gender === 'M' || gender === 'MALE') {
                        genderField.value = 'Male';
                    } else if (gender === 'F' || gender === 'FEMALE') {
                        genderField.value = 'Female';
                    }
                    triggerFieldChange(genderField);
                }
            }
            
            // Set title based on gender (Child for C/Child, Mr/Mrs for M/F)
            const titleField = document.getElementById(`title_${passengerIndex}`);
            if (titleField) {
                if (gender === 'C' || gender === 'CHILD') {
                    titleField.value = 'Child';
                } else if (gender === 'F') {
                    titleField.value = 'Mrs';
                } else {
                    titleField.value = 'Mr';
                }
                triggerFieldChange(titleField);
            }
        } else {

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
