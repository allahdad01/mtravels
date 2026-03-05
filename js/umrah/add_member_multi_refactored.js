/**
 * Multi-Member Addition System for Umrah Modal
 * Uses IDENTICAL document handling as document-upload-handler.js
 * - Drag-and-drop setup (same patterns)
 * - File validation (same rules)
 * - OCR extraction (same process)
 * - Form population (adapted for multiple members)
 */

var suppliersData = [];
var memberRowCounter = 0;
var uploadedDocuments = {}; // Store uploaded file data by member index

// ============================================
// SECTION 1: INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Pre-load Tesseract worker in background (same as single-member)
    setTimeout(() => {
        initializeTesseractWorker().catch(err => {
            console.warn('Tesseract initialization deferred');
        });
    }, 1000); // Load after page settles
});

// ============================================
// SECTION 2: SUPPLIERS & SERVICES
// ============================================

function loadSuppliers() {
    return $.ajax({
        url: '../api/umrah/get_suppliers.php',
        type: 'GET',
        dataType: 'json'
    }).then(data => {
        suppliersData = data.suppliers || [];
    }).catch(error => {
        console.error('Error loading suppliers:', error);
        suppliersData = [];
    });
}

let serviceRowCounter = 0;

function addServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0) {
    serviceRowCounter++;
    const rowId = 'serviceRow_' + serviceRowCounter;

    const suppliersOptions = suppliersData.map(s => 
        `<option value="${s.id}" data-currency="${s.currency}">${s.name}</option>`
    ).join('');

    const rowHtml = `
        <div id="${rowId}" class="service-row-grid">
            <div class="grid-column-1">
                <div class="form-group">
                    <label>Service Type</label>
                    <select class="form-control service-type" name="services[${serviceRowCounter}][service_type]" required>
                        <option value="">Select Service Type</option>
                        <option value="all" ${serviceType==='all'?'selected':''}>All Services</option>
                        <option value="ticket" ${serviceType==='ticket'?'selected':''}>Ticket</option>
                        <option value="visa" ${serviceType==='visa'?'selected':''}>Visa</option>
                        <option value="hotel" ${serviceType==='hotel'?'selected':''}>Hotel</option>
                        <option value="transport" ${serviceType==='transport'?'selected':''}>Transport</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select class="form-control service-supplier" name="services[${serviceRowCounter}][supplier_id]" required>
                        <option value="">Select Supplier</option>
                        ${suppliersOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <input type="text" class="form-control service-currency" name="services[${serviceRowCounter}][currency]" readonly>
                </div>
            </div>
            <div class="grid-column-2">
                <div class="form-group">
                    <label>Base Price</label>
                    <input type="number" class="form-control service-base-price" name="services[${serviceRowCounter}][base_price]" value="${basePrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Sold Price</label>
                    <input type="number" class="form-control service-sold-price" name="services[${serviceRowCounter}][sold_price]" value="${soldPrice}" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Profit</label>
                    <input type="number" class="form-control service-profit" name="services[${serviceRowCounter}][profit]" readonly>
                </div>
            </div>
            <div class="grid-column-3">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-danger btn-block" onclick="removeServiceRow('${rowId}')">
                        <i class="feather icon-trash-2"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    `;

    $('.services-grid-body').append(rowHtml);
    if(supplierId) $(`#${rowId} .service-supplier`).val(supplierId).trigger('change');
    updateTotals();
}

function removeServiceRow(rowId) {
    $('#' + rowId).remove();
    updateTotals();
}

function updateTotals() {
    let totalBase = 0, totalSold = 0, totalProfit = 0;
    const discount = parseFloat($('#discount').val()) || 0;
    
    $('.services-grid-body .service-row-grid').each(function() {
        const base = parseFloat($(this).find('.service-base-price').val()) || 0;
        const sold = parseFloat($(this).find('.service-sold-price').val()) || 0;
        const profit = sold - base;
        $(this).find('.service-profit').val(profit.toFixed(2));
        totalBase += base;
        totalSold += sold;
        totalProfit += profit;
    });
    
    const discountedSold = totalSold - discount;
    $('#totalBasePrice').val(totalBase.toFixed(2));
    $('#totalSoldPrice').val(discountedSold.toFixed(2));
    $('#totalProfit').val((discountedSold - totalBase).toFixed(2));
}

// Service event bindings
$(document).on('click', '#addServiceBtn', () => addServiceRow());
$(document).on('change', '.service-supplier', function() {
    const currency = $(this).find('option:selected').data('currency') || '';
    $(this).closest('.service-row-grid').find('.service-currency').val(currency);
});
$(document).on('input', '.service-base-price, .service-sold-price, #discount', updateTotals);

// ============================================
// SECTION 3: MEMBER ROW MANAGEMENT
// ============================================

function addMemberRow(name = '', dob = '', gender = 'Male', passport_number = '', passport_expiry = '', 
                     father_name = '', g_name = '', relation = '', id_type = '', photo_path = '', passport_path = '') {
    memberRowCounter++;
    const rowId = 'memberRow_' + memberRowCounter;
    console.log(`✨ addMemberRow called: memberRowCounter=${memberRowCounter}, rowId=${rowId}`);

    const memberHtml = `
        <div id="${rowId}" class="card mb-3 border-left-primary" style="border-left: 4px solid #007bff;">
            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="padding: 10px 15px;">
                <h6 class="mb-0">
                    <i class="feather icon-user mr-2"></i>
                    Member <span class="member-number">${memberRowCounter}</span> 
                    <span class="member-name-badge" style="color: #666; font-size: 12px;"></span>
                </h6>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeMemberRow('${rowId}')">
                    <i class="feather icon-trash-2"></i> Remove
                </button>
            </div>
            <div class="card-body" style="padding: 15px;">
                <!-- Member Personal Information -->
                <div class="row">
                    <div class="form-group col-md-3">
                        <label for="members_${memberRowCounter}_name">Name *</label>
                        <input type="text" class="form-control member-name" id="members_${memberRowCounter}_name" 
                               name="members[${memberRowCounter}][name]" value="${name}" required 
                               placeholder="Full Name">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="members_${memberRowCounter}_dob">Date of Birth *</label>
                        <input type="date" class="form-control" id="members_${memberRowCounter}_dob" 
                               name="members[${memberRowCounter}][dob]" value="${dob}" required>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="members_${memberRowCounter}_gender">Gender *</label>
                        <select class="form-control" id="members_${memberRowCounter}_gender" 
                                name="members[${memberRowCounter}][gender]" required>
                            <option value="Male" ${gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${gender === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="members_${memberRowCounter}_relation">Relation *</label>
                        <select class="form-control" id="members_${memberRowCounter}_relation" 
                                name="members[${memberRowCounter}][relation]" value="${relation}" required>
                            <option value="">Select Relation</option>
                            <option value="Ownself" ${relation === 'Ownself' ? 'selected' : ''}>Ownself</option>
                            <option value="Friend" ${relation === 'Friend' ? 'selected' : ''}>Friend</option>
                            <option value="Father" ${relation === 'Father' ? 'selected' : ''}>Father</option>
                            <option value="Mother" ${relation === 'Mother' ? 'selected' : ''}>Mother</option>
                            <option value="Brother" ${relation === 'Brother' ? 'selected' : ''}>Brother</option>
                            <option value="Sister" ${relation === 'Sister' ? 'selected' : ''}>Sister</option>
                            <option value="Son" ${relation === 'Son' ? 'selected' : ''}>Son</option>
                            <option value="Daughter" ${relation === 'Daughter' ? 'selected' : ''}>Daughter</option>
                            <option value="Wife" ${relation === 'Wife' ? 'selected' : ''}>Wife</option>
                            <option value="Husband" ${relation === 'Husband' ? 'selected' : ''}>Husband</option>
                            <option value="Grandfather" ${relation === 'Grandfather' ? 'selected' : ''}>Grandfather</option>
                            <option value="Grandmother" ${relation === 'Grandmother' ? 'selected' : ''}>Grandmother</option>
                            <option value="Uncle" ${relation === 'Uncle' ? 'selected' : ''}>Uncle</option>
                            <option value="Aunt" ${relation === 'Aunt' ? 'selected' : ''}>Aunt</option>
                            <option value="Cousin" ${relation === 'Cousin' ? 'selected' : ''}>Cousin</option>
                            <option value="Nephew" ${relation === 'Nephew' ? 'selected' : ''}>Nephew</option>
                            <option value="Niece" ${relation === 'Niece' ? 'selected' : ''}>Niece</option>
                            <option value="Son-in-law" ${relation === 'Son-in-law' ? 'selected' : ''}>Son-in-law</option>
                            <option value="Daughter-in-law" ${relation === 'Daughter-in-law' ? 'selected' : ''}>Daughter-in-law</option>
                            <option value="Brother-in-law" ${relation === 'Brother-in-law' ? 'selected' : ''}>Brother-in-law</option>
                            <option value="Sister-in-law" ${relation === 'Sister-in-law' ? 'selected' : ''}>Sister-in-law</option>
                            <option value="Grandson" ${relation === 'Grandson' ? 'selected' : ''}>Grandson</option>
                            <option value="Granddaughter" ${relation === 'Granddaughter' ? 'selected' : ''}>Granddaughter</option>
                            <option value="Father-in-law" ${relation === 'Father-in-law' ? 'selected' : ''}>Father-in-law</option>
                            <option value="Mother-in-law" ${relation === 'Mother-in-law' ? 'selected' : ''}>Mother-in-law</option>
                        </select>
                    </div>
                </div>

                <!-- Father Name and Grandfather Name -->
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="members_${memberRowCounter}_father_name">Father Name *</label>
                        <input type="text" class="form-control" id="members_${memberRowCounter}_father_name" 
                               name="members[${memberRowCounter}][father_name]" value="${father_name}" required 
                               placeholder="Father's Full Name">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="members_${memberRowCounter}_g_name">Grandfather Name *</label>
                        <input type="text" class="form-control" id="members_${memberRowCounter}_g_name" 
                               name="members[${memberRowCounter}][g_name]" value="${g_name}" required 
                               placeholder="Grandfather's Full Name">
                    </div>
                    <div class="form-group col-md-4">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-info btn-block" onclick="autoFillMemberDocument('${rowId}', ${memberRowCounter})">
                            <i class="feather icon-upload-cloud mr-1"></i>Upload Passport
                        </button>
                    </div>
                </div>

                <!-- Passport Information -->
                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="members_${memberRowCounter}_passport_number">Passport Number *</label>
                        <input type="text" class="form-control" id="members_${memberRowCounter}_passport_number" 
                               name="members[${memberRowCounter}][passport_number]" value="${passport_number}" required 
                               placeholder="e.g., AB123456">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="members_${memberRowCounter}_passport_expiry">Passport Expiry *</label>
                        <input type="date" class="form-control" id="members_${memberRowCounter}_passport_expiry" 
                               name="members[${memberRowCounter}][passport_expiry]" value="${passport_expiry}" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="members_${memberRowCounter}_id_type">ID Type *</label>
                        <select class="form-control" id="members_${memberRowCounter}_id_type" 
                                name="members[${memberRowCounter}][id_type]" required>
                            <option value="">Select ID Type</option>
                            <option value="ID Original + Passport Original" ${id_type === 'ID Original + Passport Original' ? 'selected' : ''}>ID Original + Passport Original</option>
                            <option value="ID Original + Passport Copy" ${id_type === 'ID Original + Passport Copy' ? 'selected' : ''}>ID Original + Passport Copy</option>
                            <option value="ID Copy + Passport Original" ${id_type === 'ID Copy + Passport Original' ? 'selected' : ''}>ID Copy + Passport Original</option>
                            <option value="ID Copy + Passport Copy" ${id_type === 'ID Copy + Passport Copy' ? 'selected' : ''}>ID Copy + Passport Copy</option>
                        </select>
                    </div>
                </div>

                <!-- Hidden fields for document paths -->
                <input type="hidden" name="members[${memberRowCounter}][photo_path]" class="member-photo-path" value="${photo_path}">
                <input type="hidden" name="members[${memberRowCounter}][passport_path]" class="member-passport-path" value="${passport_path}">
            </div>
        </div>
    `;

    $('#membersContainer').append(memberHtml);
    updateMembersSummary();
}

function removeMemberRow(rowId) {
    $(`#${rowId}`).remove();
    updateMembersSummary();
}

// Add member on button click
$(document).on('click', '#addMemberBtn', function() {
    addMemberRow();
});

// Update member name in header when typing
$(document).on('input', '.member-name', function() {
    const badge = $(this).closest('.card').find('.member-name-badge');
    const name = $(this).val() || '[Name]';
    badge.text(`- ${name}`);
});

// ============================================
// SECTION 4: DOCUMENT UPLOAD - IDENTICAL TO document-upload-handler.js
// ============================================

/**
 * Setup drag-and-drop upload zone (IDENTICAL to single-member)
 */
function setupMultiDocumentUpload() {
    const uploadZone = document.getElementById('passportUploadZone');
    const fileInput = document.getElementById('passportDocumentFile');
    
    if (!uploadZone || !fileInput) return;
    
    // Click to select file
    uploadZone.addEventListener('click', function() {
        fileInput.click();
    });
    
    // Prevent default drag behaviors (IDENTICAL)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadZone.addEventListener(eventName, preventDefaults, false);
    });
    
    // Highlight zone on drag (IDENTICAL)
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
    
    // Handle drop - FOR MULTIPLE FILES
    uploadZone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            processMultipleDocumentsIdentical(files);
        }
    }, false);
    
    // Handle file input change - FOR MULTIPLE FILES
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            processMultipleDocumentsIdentical(e.target.files);
        }
    });
}

/**
 * Process multiple documents using IDENTICAL validation & extraction
 */
async function processMultipleDocumentsIdentical(files) {
    showDocumentStatus('passport', '⏳ Processing files...', 'info');
    
    const totalFiles = files.length;
    console.log(`📄 Processing ${totalFiles} file(s)`);
    
    // Auto-create member rows if needed
    // currentMemberCount = number of existing member cards
    const currentMemberCount = $('#membersContainer .card').length;
    const neededMembers = totalFiles - currentMemberCount;
    
    if (neededMembers > 0) {
        console.log(`➕ Auto-creating ${neededMembers} additional member row(s)`);
        for (let i = 0; i < neededMembers; i++) {
            addMemberRow();
        }
    }
    
    let processedCount = 0;
    
    Array.from(files).forEach((file, index) => {
        // Use IDENTICAL processDocument function
        processDocumentIdentical(file, 'passport', index).then(() => {
            processedCount++;
            if (processedCount === totalFiles) {
                displayUploadedFiles();
                showDocumentStatus('passport', `✅ ${totalFiles} file(s) processed`, 'success');
            }
        }).catch(err => {
            console.error(`Error processing file ${index}:`, err);
            processedCount++;
            if (processedCount === totalFiles) {
                displayUploadedFiles();
            }
        });
    });
}

/**
 * Process document with IDENTICAL validation as single-member
 */
async function processDocumentIdentical(file, documentType, fileIndex) {
    // IDENTICAL validation
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
        showDocumentStatus(documentType, `❌ Please upload a PDF or image file`, 'error');
        return;
    }
    
    // IDENTICAL size validation
    if (file.size > 10 * 1024 * 1024) {
        showDocumentStatus(documentType, `❌ File too large. Maximum 10MB allowed`, 'error');
        return;
    }
    
    // Save document file and get paths
    const docPaths = await saveDocumentFileIdentical(file, documentType);
    
    // Extract using IDENTICAL process (will get OCR data + apply document paths)
    await performClientSideOCRIdentical(file, documentType, fileIndex, docPaths?.passportPath, docPaths?.photoPath);
}

/**
 * Save document file to server (IDENTICAL)
 */
async function saveDocumentFileIdentical(file, documentType) {
    try {
        const familyId = document.getElementById('familyId')?.value || null;
        const formData = new FormData();
        formData.append('passport_file', file);
        if (familyId) {
            formData.append('family_id', familyId);
        }
        
        const response = await fetch('/almoqadas/mtravels/api/umrah/save_passport_document.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (result.success && result.document_path) {
            console.log('✓ Document saved:', result.document_path);
            
            // Store document path
            const fieldId = documentType === 'passport' ? 'passportPath' : null;
            if (fieldId) {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = result.document_path;
                }
            }
            
            let photoPath = null;
            
            // Auto-extract photo (IDENTICAL)
            if (documentType === 'passport') {
                photoPath = await extractPhotoFromPassportIdentical(file, familyId);
            }
            
            // Return both paths so they can be stored in member data
            return {
                passportPath: result.document_path,
                photoPath: photoPath
            };
        } else {
            console.warn('Document save failed:', result.message);
            return null;
        }
    } catch (error) {
        console.warn('Document save error:', error.message);
        return null;
    }
}

/**
 * Extract photo from passport (IDENTICAL) - Returns photo path
 */
async function extractPhotoFromPassportIdentical(file, familyId) {
    return new Promise((resolve) => {
        try {
            const reader = new FileReader();
            reader.onload = async function(e) {
                try {
                    const imageData = e.target.result;
                    
                    const response = await fetch('/almoqadas/mtravels/api/umrah/auto_extract_passport_photo.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            image_data: imageData,
                            family_id: familyId
                        }),
                        credentials: 'same-origin'
                    });
                    
                    const result = await response.json();
                    
                    if (result.success && result.photo_path) {
                        const photoField = document.getElementById('photoPath');
                        if (photoField) {
                            photoField.value = result.photo_path;
                        }
                        console.log('✓ Photo extracted:', result.photo_path);
                        resolve(result.photo_path);
                    } else {
                        console.warn('Photo extraction failed:', result.message);
                        resolve(null);
                    }
                } catch (error) {
                    console.warn('Photo extraction error:', error);
                    resolve(null);
                }
            };
            reader.readAsDataURL(file);
            
        } catch (error) {
            console.warn('Photo extraction failed:', error.message);
            resolve(null);
        }
    });
}

/**
 * Perform client-side OCR (IDENTICAL to single-member)
 */
async function performClientSideOCRIdentical(file, documentType, fileIndex, passportPath = null, photoPath = null) {
    let fileUrl = null;
    try {
        const worker = await initializeTesseractWorker();
        
        // Create file URL for Tesseract
        fileUrl = URL.createObjectURL(file);
        
        // Recognize image
        const result = await worker.recognize(fileUrl);
        const extractedText = result.data.text;
        
        if (extractedText) {
            // Send to server for MRZ parsing (primary method - most accurate)
            let data = await extractMRZOnServer(extractedText);
            
            // Fallback to client-side pattern matching if MRZ fails
            if (!data || Object.keys(data).length === 0) {
                data = parsePassportDataIdentical(extractedText);
            }
            
            if (data && Object.keys(data).length > 0) {
                // Map fileIndex (0-based) to memberRowCounter (1-based)
                // First file (fileIndex=0) goes to member 1
                const memberIndex = fileIndex + 1;
                console.log(`✅ Storing data for memberIndex=${memberIndex} from fileIndex=${fileIndex}`);
                
                // Add document paths if available
                if (passportPath) {
                    data.passport_path = passportPath;
                    console.log(`✅ Added passport_path:`, passportPath);
                }
                if (photoPath) {
                    data.photo_path = photoPath;
                    console.log(`✅ Added photo_path:`, photoPath);
                }
                
                uploadedDocuments[memberIndex] = data;
                
                // Fill the member form with extracted data
                try {
                    fillMemberFormIdentical(memberIndex, data);
                    console.log(`✅ Member form ${memberIndex} filled successfully`);
                } catch (err) {
                    console.error(`❌ Error filling member form ${memberIndex}:`, err);
                }
                
                // Update the summary display
                displayUploadedFiles();
            }
        }
    } catch (error) {
        console.warn('Client-side OCR error:', error);
    } finally {
        // Clean up file URL
        if (fileUrl) {
            URL.revokeObjectURL(fileUrl);
        }
    }
}

/**
 * Send extracted text to server for MRZ parsing (primary method)
 * MRZ is the machine-readable zone at the bottom of passports - most accurate
 */
async function extractMRZOnServer(text) {
    try {
        const response = await fetch('../api/umrah/extract_text.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                text: text,
                document_type: 'passport'
            })
        });
        
        if (!response.ok) {
            console.warn('Server MRZ extraction failed');
            return null;
        }
        
        const result = await response.json();
        
        console.log('extractMRZOnServer response:', result);
        
        if (result.success && result.data) {
            // Data is nested inside result.data
            const data = result.data;
            console.log('Extracted data from API:', data);
            
            return {
                full_name: data.full_name,
                passport_number: data.passport_number,
                date_of_birth: data.date_of_birth,
                expiry_date: data.expiry_date,
                father_name: data.father_name,
                gender: data.gender,
                nationality: data.nationality,
                mrz_valid: result.mrz_valid,
                extraction_method: result.extraction_method || 'mrz'
            };
        }
        
        return null;
    } catch (error) {
        console.warn('Server MRZ extraction error:', error);
        return null;
    }
}

/**
 * Parse passport data from text (IDENTICAL to document-upload-handler.js extractDocumentFromText)
 * Fallback method if MRZ extraction fails
 */
function parsePassportDataIdentical(text) {
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
 * Format OCR date (IDENTICAL)
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
 * Upload passport for specific member
 */
function autoFillMemberDocument(rowId, memberIndex) {
    console.log(`autoFillMemberDocument called with memberIndex=${memberIndex}, rowId=${rowId}`);
    
    const fileInput = document.getElementById('passportDocumentFile');
    
    // Use a one-time event listener to avoid closure issues
    const changeHandler = function(e) {
        console.log(`File input changed, memberIndex=${memberIndex}`);
        if (this.files[0]) {
            console.log(`Processing file for memberIndex=${memberIndex}`);
            processDocumentForMemberIdentical(this.files[0], memberIndex);
        }
        // Remove listener after use
        fileInput.removeEventListener('change', changeHandler);
    };
    
    fileInput.addEventListener('change', changeHandler, { once: true });
    fileInput.click();
}

/**
 * Process document for specific member using IDENTICAL functions
 */
async function processDocumentForMemberIdentical(file, memberIndex) {
    let fileUrl = null;
    showDocumentStatus('passport', 'Extracting passport data...', 'info');
    
    // IDENTICAL validation & extraction
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    if (!allowedTypes.includes(file.type)) {
        showDocumentStatus('passport', `❌ Invalid file type`, 'error');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) {
        showDocumentStatus('passport', `❌ File too large`, 'error');
        return;
    }
    
    await saveDocumentFileIdentical(file, 'passport');
    
    try {
        // Create file URL for Tesseract
        fileUrl = URL.createObjectURL(file);
        
        const worker = await initializeTesseractWorker();
        const result = await worker.recognize(fileUrl);
        const extractedText = result.data.text;
        
        console.log('🔍 Tesseract extracted text, now calling extractMRZOnServer...');
        
        // Send to server for MRZ parsing (primary method - most accurate)
        let data = await extractMRZOnServer(extractedText);
        
        console.log('✅ extractMRZOnServer returned');
        console.log('🔎 Received data type:', typeof data);
        console.log('🔎 Received data is null?:', data === null);
        console.log('🔎 Received data is undefined?:', data === undefined);
        console.log('🔎 Data content:', data);
        
        // Fallback to client-side pattern matching if MRZ fails
        if (!data || Object.keys(data).length === 0) {
            console.log('⚠️ MRZ failed, trying parsePassportDataIdentical...');
            data = parsePassportDataIdentical(extractedText);
        }
        
        console.log('📋 Data received after all attempts:', data);
        console.log('About to fill form with memberIndex:', memberIndex);
        
        try {
            if (data && Object.keys(data).length > 0) {
                console.log('Data is valid, filling form...');
                // Store extracted data for display
                uploadedDocuments[memberIndex] = data;
                console.log('uploadedDocuments updated:', uploadedDocuments);
                
                // Fill the member form fields
                console.log('Calling fillMemberFormIdentical with memberIndex:', memberIndex);
                fillMemberFormIdentical(memberIndex, data);
                console.log('fillMemberFormIdentical returned successfully');
                
                // Update display
                displayUploadedFiles();
                console.log('displayUploadedFiles called successfully');
                
                showDocumentStatus('passport', '✅ Passport data extracted and filled', 'success');
            } else {
                console.log('Data is empty or null');
                showDocumentStatus('passport', '⚠️ Could not extract data. Please fill manually.', 'warning');
            }
        } catch (innerError) {
            console.error('❌ Error during form filling:', innerError);
            console.error('Error stack:', innerError.stack);
            showDocumentStatus('passport', '⚠️ Error filling form: ' + innerError.message, 'warning');
        }
    } catch (error) {
        console.warn('Document extraction error:', error);
        console.warn('Error stack:', error.stack);
        showDocumentStatus('passport', '⚠️ Extraction failed. Please fill manually.', 'warning');
    } finally {
        // Clean up file URL
        if (fileUrl) {
            URL.revokeObjectURL(fileUrl);
        }
    }
}

/**
 * Fill member form with extracted data
 */
function fillMemberFormIdentical(memberIndex, data) {
    console.log(`fillMemberFormIdentical called: memberIndex=${memberIndex}, data=`, data);
    console.log(`Looking for form fields with ID: #members_${memberIndex}_*`);
    
    // Debug: Check what elements exist in the container
    const memberCard = $(`#memberRow_${memberIndex}`);
    console.log(`Member card found: ${memberCard.length} (looking for #memberRow_${memberIndex})`);
    
    const nameField = $(`#members_${memberIndex}_name`);
    const dobField = $(`#members_${memberIndex}_dob`);
    const passportExpiryField = $(`#members_${memberIndex}_passport_expiry`);
    const passportNumberField = $(`#members_${memberIndex}_passport_number`);
    const fatherNameField = $(`#members_${memberIndex}_father_name`);
    const genderField = $(`#members_${memberIndex}_gender`);
    
    console.log(`Field checks: name=${nameField.length}, dob=${dobField.length}, expiry=${passportExpiryField.length}, passport=${passportNumberField.length}`);
    
    // If no fields found, list all available card IDs
    if (nameField.length === 0) {
        console.error('❌ No form fields found! Available member cards:');
        $('#membersContainer .card').each(function(i) {
            console.log(`  Card ${i}: id="${$(this).attr('id')}"`);
        });
    }
    
    if (data.full_name && nameField.length) {
        nameField.val(data.full_name).change();
        console.log('✓ Set full_name:', data.full_name);
    }
    if (data.date_of_birth && dobField.length) {
        dobField.val(formatDate(data.date_of_birth)).change();
        console.log('✓ Set date_of_birth:', data.date_of_birth);
    }
    if (data.expiry_date && passportExpiryField.length) {
        passportExpiryField.val(formatDate(data.expiry_date)).change();
        console.log('✓ Set expiry_date:', data.expiry_date);
    }
    if (data.passport_number && passportNumberField.length) {
        passportNumberField.val(data.passport_number).change();
        console.log('✓ Set passport_number:', data.passport_number);
    }
    if (data.father_name && fatherNameField.length) {
        fatherNameField.val(data.father_name).change();
        console.log('✓ Set father_name:', data.father_name);
    }
    if (data.gender && genderField.length) {
        const genderValue = data.gender.toLowerCase() === 'm' || data.gender.toLowerCase() === 'male' ? 'Male' : 'Female';
        genderField.val(genderValue).change();
        console.log('✓ Set gender:', genderValue);
    }
    
    // Set hidden fields for document paths if available
    const photoPathField = $(`#memberRow_${memberIndex} input[name*="[photo_path]"]`);
    const passportPathField = $(`#memberRow_${memberIndex} input[name*="[passport_path]"]`);
    
    if (data.photo_path && photoPathField.length) {
        photoPathField.val(data.photo_path);
        console.log('✓ Set photo_path:', data.photo_path);
    }
    
    if (data.passport_path && passportPathField.length) {
        passportPathField.val(data.passport_path);
        console.log('✓ Set passport_path:', data.passport_path);
    }
    
    // Trigger input event for name field to update summary
    nameField.trigger('input');
    
    updateMembersSummary();
}

/**
 * Format date to YYYY-MM-DD (IDENTICAL)
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    // Already YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}/.test(dateString)) {
        return dateString.substring(0, 10);
    }
    
    // DD/MM/YYYY or DD-MM-YYYY
    if (/^\d{1,2}[/-]\d{1,2}[/-]\d{4}/.test(dateString)) {
        const parts = dateString.split(/[/-]/);
        if (parts.length === 3) {
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const year = parts[2];
            return `${year}-${month}-${day}`;
        }
    }
    
    return dateString;
}

/**
 * Display uploaded files list
 */
function displayUploadedFiles() {
    const fileList = document.getElementById('uploadedFilesList');
    
    if (!fileList) {
        console.warn('uploadedFilesList element not found');
        return;
    }
    
    if (Object.keys(uploadedDocuments).length === 0) {
        fileList.innerHTML = '';
        return;
    }
    
    let html = '<div style="margin-top: 10px;"><small style="color: #666;"><strong>Extracted Data:</strong></small><ul style="font-size: 11px; margin: 5px 0; padding-left: 20px;">';
    
    Object.keys(uploadedDocuments).sort((a, b) => parseInt(a) - parseInt(b)).forEach(memberIndex => {
        const data = uploadedDocuments[memberIndex];
        const fileNumber = memberIndex; // memberIndex already matches member number (1-based)
        const name = data.full_name || `File ${fileNumber}`;
        html += `<li>Member ${fileNumber}: ${name} - Passport: ${data.passport_number || 'N/A'}, DOB: ${data.date_of_birth || 'N/A'}</li>`;
    });
    
    html += '</ul></div>';
    fileList.innerHTML = html;
    console.log('✓ displayUploadedFiles updated:', uploadedDocuments);
}

// ============================================
// SECTION 5: SUMMARY & VALIDATION
// ============================================

function updateMembersSummary() {
    const memberCount = $('#membersContainer .card').length;
    const summaryCard = $('#membersSummaryCard');
    
    if (memberCount === 0) {
        summaryCard.hide();
        return;
    }
    
    $('#memberCount').text(memberCount);
    
    let summaryHtml = '<ol style="margin: 0; padding-left: 20px; font-size: 12px;">';
    
    $('#membersContainer .card').each(function(index) {
        const name = $(this).find('.member-name').val() || `Member ${index + 1}`;
        const passportNumber = $(this).find('input[id*="_passport_number"]').val() || 'N/A';
        const dob = $(this).find('input[id*="_dob"]').val() || 'N/A';
        summaryHtml += `<li>File ${index + 1} - Passport: ${passportNumber}, DOB: ${dob}</li>`;
    });
    
    summaryHtml += '</ol>';
    $('#membersSummaryList').html(summaryHtml);
    summaryCard.show();
}

function validateForm() {
    const memberCount = $('#membersContainer .card').length;
    
    if (memberCount === 0) {
        showToast('error', 'Please add at least one member');
        return false;
    }
    
    let hasError = false;
    
    $('#membersContainer .card').each(function(index) {
        const form = $(this).find('input, select');
        form.each(function() {
            if ($(this).prop('required') && !$(this).val()) {
                showToast('error', `Member ${index + 1}: Missing required field`);
                hasError = true;
                return false;
            }
        });
    });
    
    if (hasError) return false;
    
    // Validate passport expiry (6 months from today)
    const today = new Date();
    const sixMonthsLater = new Date();
    sixMonthsLater.setMonth(sixMonthsLater.getMonth() + 6);
    
    let expiryError = false;
    $('#membersContainer [name*="[passport_expiry]"]').each(function(index) {
        const expiryDate = new Date($(this).val());
        if (expiryDate < sixMonthsLater) {
            showToast('error', `Member ${index + 1}: Passport must be valid for at least 6 months`);
            expiryError = true;
        }
    });
    
    return !expiryError;
}

// ============================================
// SECTION 6: MODAL INITIALIZATION
// ============================================

$('#umrahModal').on('shown.bs.modal', function() {
    // Load suppliers if not already loaded
    if (suppliersData.length === 0) {
        loadSuppliers().then(() => {
            // Add first service row
            if ($('.services-grid-body .service-row-grid').length === 0) {
                addServiceRow();
            }
        });
    }
    
    // Add first member row if none exist
    if ($('#membersContainer .card').length === 0) {
        addMemberRow();
    }
    
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    if (!$('#entry_date').val()) {
        $('#entry_date').val(today);
    }
    
    // Initialize document upload (IDENTICAL setup)
    setupMultiDocumentUpload();
});

// Reset form on modal close
$('#umrahModal').on('hidden.bs.modal', function() {
    memberRowCounter = 0;
    uploadedDocuments = {};
});

// ============================================
// GLOBAL HELPER FUNCTIONS (IDENTICAL)
// ============================================

/**
 * Global Tesseract worker (IDENTICAL)
 */
let globalTesseractWorker = null;

async function initializeTesseractWorker() {
    if (globalTesseractWorker) {
        return globalTesseractWorker;
    }
    
    try {
        if (typeof Tesseract === 'undefined') {
            throw new Error('Tesseract.js library not loaded');
        }
        
        globalTesseractWorker = await Tesseract.createWorker();
        
        await globalTesseractWorker.loadLanguage('eng');
        await globalTesseractWorker.initialize('eng');
        
        return globalTesseractWorker;
    } catch (error) {
        globalTesseractWorker = null;
        throw error;
    }
}

/**
 * Prevent default drag behaviors (IDENTICAL)
 */
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

/**
 * Show document status message (IDENTICAL)
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
