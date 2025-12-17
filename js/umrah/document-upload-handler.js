/**
 * Document Upload Handler for Umrah Booking Modal
 * Handles drag-and-drop and file selection for Passport uploads
 * Uses same process as test_document_extractor.php:
 * 1. Try server-side PaddleOCR first
 * 2. If fails, use client-side Tesseract.js (browser)
 * 3. Send OCR text to server for MRZ parsing
 * 
 * Supports:
 * - Passport document uploads (PDF/Image)
 * - Real-time validation
 * - Auto-form population
 */

document.addEventListener('DOMContentLoaded', function() {
    setupDocumentUploadZones();
});

/**
 * Setup drag-and-drop upload zones for passport
 */
function setupDocumentUploadZones() {
    const documentTypes = ['passport'];
    
    documentTypes.forEach(docType => {
        const uploadZone = document.getElementById(`${docType}UploadZone`);
        const fileInput = document.getElementById(`${docType}DocumentFile`);
        
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
                processDocument(file, docType);
            }
        }, false);
        
        // Handle file input change
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                processDocument(file, docType);
            }
        });
    });
}

/**
 * Process and extract document using Tesseract.js (like test file)
 * Skip file upload - go straight to browser OCR for all files
 */
async function processDocument(file, documentType) {
    // Validate file type
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
        showDocumentStatus(documentType, `❌ Please upload a PDF or image file`, 'error');
        return;
    }
    
    // Validate file size (10MB)
    if (file.size > 10 * 1024 * 1024) {
        showDocumentStatus(documentType, `❌ File too large. Maximum 10MB allowed`, 'error');
        return;
    }
    
    // Same as test file: Use Tesseract.js for all documents
    console.log(`Starting Tesseract.js OCR for ${documentType}...`);
    await performClientSideOCR(file, documentType);
}

/**
 * Global Tesseract worker instance (singleton pattern)
 */
let globalTesseractWorker = null;

/**
 * Initialize Tesseract worker (reuse same instance)
 */
async function initializeTesseractWorker() {
    if (globalTesseractWorker) {
        return globalTesseractWorker;
    }
    
    try {
        if (typeof Tesseract === 'undefined') {
            throw new Error('Tesseract.js library not loaded');
        }
        
        console.log('Creating Tesseract worker...');
        globalTesseractWorker = await Tesseract.createWorker();
        
        // Load English language
        console.log('Loading English language data...');
        await globalTesseractWorker.loadLanguage('eng');
        console.log('Initializing OCR engine...');
        await globalTesseractWorker.initialize('eng');
        
        console.log('Tesseract worker ready');
        return globalTesseractWorker;
    } catch (error) {
        console.error('Failed to initialize Tesseract:', error);
        globalTesseractWorker = null;
        throw error;
    }
}

/**
 * Perform client-side OCR using Tesseract.js
 */
async function performClientSideOCR(file, documentType) {
    let fileUrl = null;
    
    try {
        showDocumentStatus(documentType, `⏳ Starting browser-based OCR (Tesseract.js)...`, 'info');
        console.log('Using Tesseract.js for OCR...');
        
        // Create image URL for Tesseract
        fileUrl = URL.createObjectURL(file);
        
        // Initialize Tesseract worker (loads language data on first use)
        showDocumentStatus(documentType, `⏳ Loading Tesseract worker and language data...`, 'info');
        const worker = await initializeTesseractWorker();
        
        // Process image with timeout
        showDocumentStatus(documentType, `⏳ Tesseract.js processing image...`, 'info');
        
        const result = await Promise.race([
            worker.recognize(fileUrl),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('OCR processing timeout')), 120000)
            )
        ]);
        
        const text = result.data.text;
        
        if (!text || text.trim().length === 0) {
            throw new Error('No text detected in image');
        }
        
        console.log('OCR completed. Text length:', text.length);
        console.log('Sending OCR text to server for MRZ parsing...');
        
        // Send OCR text to server for MRZ parsing
        showDocumentStatus(documentType, `⏳ Extracting data with server-side MRZ parsing...`, 'info');
        
        const response = await fetch('../api/umrah/extract_text.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                text: text,
                document_type: documentType
            }),
            credentials: 'same-origin'
        });
        
        const serverData = await response.json();
        
        if (response.ok && serverData.success) {
            fillUmrahForm(serverData.data, documentType);
            
            const confidence = serverData.confidence ? Math.round(serverData.confidence * 100) : 0;
            const mrzValid = serverData.mrz_valid ? 'MRZ' : 'Pattern';
            
            showDocumentStatus(documentType, 
                `✅ Extracted via Tesseract.js + ${mrzValid} (Confidence: ${confidence}%)`, 
                'success'
            );
            
            console.log(`${documentType} Extraction (${mrzValid}):`, serverData.data);
        } else {
            throw new Error(serverData.message || 'Server extraction failed');
        }
        
    } catch (error) {
        console.error(`Client-side OCR Error (${documentType}):`, error);
        
        // Try fallback: client-side pattern matching
        console.log('Attempting client-side fallback extraction...');
        try {
            if (!fileUrl) {
                fileUrl = URL.createObjectURL(file);
            }
            
            const worker = await initializeTesseractWorker();
            const result = await Promise.race([
                worker.recognize(fileUrl),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('OCR timeout')), 120000)
                )
            ]);
            
            const text = result.data.text;
            if (text && text.trim().length > 0) {
                const documentData = extractDocumentFromText(text, documentType);
                fillUmrahForm(documentData, documentType);
                
                showDocumentStatus(documentType, 
                    `✅ Extracted via Tesseract.js (Client-side pattern matching)`, 
                    'success'
                );
                
                console.log(`${documentType} Extraction (fallback):`, documentData);
            } else {
                throw new Error('No text detected in fallback');
            }
        } catch (fallbackError) {
            showDocumentStatus(documentType, 
                `❌ OCR Error: ${error.message}`, 
                'error'
            );
            console.error('Fallback also failed:', fallbackError);
        }
    } finally {
        if (fileUrl) {
            URL.revokeObjectURL(fileUrl);
        }
    }
}

/**
 * Extract document data from OCR text using pattern matching
 * (Simulates server-side extraction on client)
 */
function extractDocumentFromText(text, documentType) {
    const data = {};
    
    // Extract full name - usually after "Given Names" or "Name"
    let nameMatch = text.match(/Given\s+Names[:\s]*([A-Z][A-Za-z\s]+?)(?:\n|AFGHAN|Nationality|Father)/i) ||
                   text.match(/(?:Name|Surname)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|Nationality|Father)/i) ||
                   text.match(/^([A-Z][A-Za-z\s]+?)(?:\n|Date of Birth|Dato of Birth|AFGHAN)/m);
    if (nameMatch) {
        data.full_name = nameMatch[1].trim();
    }
    
    // Extract passport number - patterns like "A12345678", "AFG9205026M", or "098220721AFG"
    let passportMatch = text.match(/P<AFG[A-Z0-9]*?(\d{9}[A-Z]{3})/i) ||
                       text.match(/(\d{9}[A-Z]{3})/i) ||
                       text.match(/PASSPORT\s*(?:NO|NUMBER|O)[:\s]*([A-Z0-9]{5,20})/i);
    if (passportMatch) {
        data.passport_number = passportMatch[1].trim();
    }
    
    // Extract date of birth
    let dobMatch = text.match(/Date\s+of\s+(?:Birth|birth)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i) ||
                  text.match(/DOB[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i) ||
                  text.match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i);
    if (dobMatch) {
        data.date_of_birth = formatOCRDate(dobMatch[1], dobMatch[2], dobMatch[3]);
    }
    
    // Extract issue date
    let issueMatch = text.match(/Date\s+of\s+(?:Issue|Issu)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i) ||
                    text.match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})\s+(?:Issue|Issu)/i);
    if (issueMatch) {
        data.issue_date = formatOCRDate(issueMatch[1], issueMatch[2], issueMatch[3]);
    }
    
    // Extract expiry date
    let expiryMatch = text.match(/(?:Date\s+of\s+)?(?:Expiry|Expires|Valid\s+Until|Expir)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i) ||
                     text.match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})\s+(?:Expiry|Expires|Valid)/i);
    if (expiryMatch) {
        data.expiry_date = formatOCRDate(expiryMatch[1], expiryMatch[2], expiryMatch[3]);
    }
    
    // Extract place of birth
    let placeMatch = text.match(/Place\s+of\s+(?:Birth|birth)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|Sex|Gender|Date)/i);
    if (placeMatch) {
        data.place_of_birth = placeMatch[1].trim();
    }
    
    // Extract nationality
    let natMatch = text.match(/Nationality[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|Father)/i) ||
                  text.match(/AFGHAN/i);
    if (natMatch) {
        data.nationality = natMatch[1] ? natMatch[1].trim() : 'Afghan';
    }
    
    // Extract father's name
    let fatherMatch = text.match(/(?:Father|Father\'s|Parent)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|Nationality)/i);
    if (fatherMatch) {
        data.father_name = fatherMatch[1].trim();
    }
    
    // Extract gender
    let genderMatch = text.match(/(?:Gender|Sex)[:\s]+(M|F|Male|Female)/i);
    if (genderMatch) {
        const gender = genderMatch[1].toUpperCase();
        data.gender = (gender === 'M' || gender === 'MALE') ? 'Male' : 
                     (gender === 'F' || gender === 'FEMALE') ? 'Female' : genderMatch[1];
    }
    
    return data;
}

/**
 * Format date from OCR text to YYYY-MM-DD
 */
function formatOCRDate(day, month, year) {
    const months = {
        'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
        'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
        'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
    };
    
    let monthStr = month;
    if (!month.match(/^\d+$/)) {
        monthStr = months[month.toUpperCase()] || month;
    }
    
    day = String(day).padStart(2, '0');
    monthStr = String(monthStr).padStart(2, '0');
    
    return `${year}-${monthStr}-${day}`;
}

/**
 * Fill umrah form with extracted document data
 */
function fillUmrahForm(data, documentType) {
    // Map extracted data to form field IDs
    const fieldMappings = {
        'passport': {
            'name': data.full_name,
            'dob': data.date_of_birth,
            'passport_number': data.passport_number,
            'passport_expiry': data.expiry_date,
            'father_name': data.father_name || data.parent_name,
            'gender': data.gender
        }
    };
    
    const fields = fieldMappings[documentType] || {};
    console.log(`Filling form with passport data:`, fields);
    
    // Fill each field that has a value
    for (const [fieldId, value] of Object.entries(fields)) {
        if (!value) continue;
        
        const field = document.getElementById(fieldId);
        if (!field) {
            console.warn(`Field not found: ${fieldId}`);
            continue;
        }
        
        // Handle date fields
        if ((fieldId === 'dob' || fieldId === 'passport_expiry') && value) {
            field.value = formatDate(value);
        } else if (fieldId === 'gender' && value) {
            // Handle select dropdown for gender
            const genderValue = value.toLowerCase() === 'm' || value.toLowerCase() === 'male' ? 'Male' : 
                               value.toLowerCase() === 'f' || value.toLowerCase() === 'female' ? 'Female' : 
                               value;
            field.value = genderValue;
        } else {
            field.value = value;
        }
        
        triggerFieldChange(field);
        console.log(`✓ Filled ${fieldId}: ${value}`);
    }
}

/**
 * Format date to YYYY-MM-DD format
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    // Try to parse various date formats
    let date;
    
    // Try ISO format first
    if (/^\d{4}-\d{2}-\d{2}/.test(dateString)) {
        return dateString.substring(0, 10);
    }
    
    // Try DD/MM/YYYY or DD-MM-YYYY
    if (/^\d{1,2}[/-]\d{1,2}[/-]\d{4}/.test(dateString)) {
        const parts = dateString.split(/[/-]/);
        if (parts.length === 3) {
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const year = parts[2];
            return `${year}-${month}-${day}`;
        }
    }
    
    // Try DD Month YYYY
    if (/^\d{1,2}\s+[A-Za-z]+\s+\d{4}/.test(dateString)) {
        date = new Date(dateString);
        if (!isNaN(date.getTime())) {
            return date.toISOString().split('T')[0];
        }
    }
    
    return dateString;
}

/**
 * Show status message
 */
function showDocumentStatus(documentType, message, type) {
    const uploadZone = document.getElementById(`${documentType}UploadZone`);
    if (!uploadZone) return;
    
    // Remove existing status
    const existingStatus = uploadZone.nextElementSibling;
    if (existingStatus && existingStatus.classList.contains('document-status')) {
        existingStatus.remove();
    }
    
    // Create new status element
    const statusDiv = document.createElement('div');
    statusDiv.className = `alert document-status`;
    statusDiv.style.marginTop = '10px';
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
