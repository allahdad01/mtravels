/**
 * Multi-Member Addition System for Umrah Modal
 * Handles dynamic member row creation, multi-file uploads, and form submission
 */

var suppliersData = [];
var memberRowCounter = 0;
var uploadedDocuments = {}; // Store uploaded file data by index

// ============================================
// SECTION 1: SUPPLIERS LOADING
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

// ============================================
// SECTION 2: SERVICE ROW MANAGEMENT
// ============================================

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
                        <option value="ticket+visa" ${serviceType==='ticket+visa'?'selected':''}>Ticket + Visa</option>
                        <option value="ticket+hotel" ${serviceType==='ticket+hotel'?'selected':''}>Ticket + Hotel</option>
                        <option value="ticket+transport" ${serviceType==='ticket+transport'?'selected':''}>Ticket + Transport</option>
                        <option value="visa+services" ${serviceType==='visa+services'?'selected':''}>Visa + Services</option>
                        <option value="visa+hotel" ${serviceType==='visa+hotel'?'selected':''}>Visa + Hotel</option>
                        <option value="visa+transport" ${serviceType==='visa+transport'?'selected':''}>Visa + Transport</option>
                        <option value="hotel+transport" ${serviceType==='hotel+transport'?'selected':''}>Hotel + Transport</option>
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
                        <button type="button" class="btn btn-sm btn-info btn-block" onclick="autoFillMember('${rowId}')">
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
// SECTION 4: MULTI-FILE DOCUMENT UPLOAD
// ============================================

function setupMultiDocumentUpload() {
    const uploadZone = document.getElementById('passportUploadZone');
    const fileInput = document.getElementById('passportDocumentFile');
    
    if (!uploadZone || !fileInput) return;
    
    // Click to select files
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
        const files = e.dataTransfer.files;
        processMultipleDocuments(files);
    }, false);
    
    // Handle file input change
    fileInput.addEventListener('change', function(e) {
        processMultipleDocuments(e.target.files);
    });
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function processMultipleDocuments(files) {
    const fileList = document.getElementById('uploadedFilesList');
    fileList.innerHTML = '<p style="font-size: 12px; color: #666; margin-top: 10px;"><i class="feather icon-info mr-1"></i>Processing files...</p>';
    
    let processedCount = 0;
    const totalFiles = files.length;
    
    Array.from(files).forEach((file, index) => {
        processDocument(file, index).then(() => {
            processedCount++;
            if (processedCount === totalFiles) {
                displayUploadedFiles();
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

async function processDocument(file, fileIndex) {
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    
    if (!allowedTypes.includes(file.type)) {
        showToast('error', `File ${fileIndex + 1}: Invalid file type. Please upload PDF or image.`);
        return;
    }
    
    // Try server-side OCR first
    let extractedData = await tryServerOCR(file);
    
    if (!extractedData) {
        // Fallback to client-side Tesseract.js
        const ocrText = await tryClientOCR(file);
        
        if (ocrText) {
            // Send extracted text to server for MRZ parsing
            extractedData = await extractMRZOnServer(ocrText);
            
            // Fallback to client-side pattern matching if MRZ fails
            if (!extractedData || Object.keys(extractedData).length === 0) {
                extractedData = parseMRZData(ocrText);
            }
        }
    }
    
    if (extractedData) {
        uploadedDocuments[fileIndex] = extractedData;
    }
}

async function tryServerOCR(file) {
    try {
        const formData = new FormData();
        formData.append('document_file', file);
        formData.append('document_type', 'passport');
        
        const response = await fetch('../api/umrah/extract_text.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Server OCR failed');
        
        const data = await response.json();
        if (data.success && data.data) {
            return data.data;
        }
    } catch (err) {
        console.log('Server OCR failed, trying client-side...');
    }
    return null;
}

async function tryClientOCR(file) {
    let fileUrl = null;
    try {
        // Use Tesseract for client-side OCR
        if (typeof Tesseract === 'undefined') {
            console.warn('Tesseract not loaded');
            return null;
        }
        
        // Create file URL for Tesseract
        fileUrl = URL.createObjectURL(file);
        
        // Initialize worker
        const worker = await Tesseract.createWorker();
        await worker.loadLanguage('eng');
        await worker.initialize('eng');
        
        const result = await worker.recognize(fileUrl);
        const text = result.data.text;
        
        // Clean up worker
        await worker.terminate();
        
        return text;
    } catch (err) {
        console.error('Client OCR error:', err);
    } finally {
        // Clean up file URL
        if (fileUrl) {
            URL.revokeObjectURL(fileUrl);
        }
    }
    return null;
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
        
        if (result.success && result.data) {
            return result.data;
        }
        
        return null;
    } catch (error) {
        console.warn('Server MRZ extraction error:', error);
        return null;
    }
}

function parseMRZData(text) {
    const lines = text.split('\n');
    const result = {};
    
    // Simple MRZ parsing (enhance as needed)
    for (let line of lines) {
        if (line.includes('PASSPORT')) {
            result.passport_type = 'PASSPORT';
        }
        // Extract numbers for passport number
        const passportMatch = line.match(/[A-Z0-9]{6,9}/);
        if (passportMatch && !result.passport_number) {
            result.passport_number = passportMatch[0];
        }
        // Extract dates (format: YYMMDD)
        const dateMatch = line.match(/\d{6}/g);
        if (dateMatch && !result.dob && dateMatch[0]) {
            result.dob = convertPassportDate(dateMatch[0]);
        }
        if (dateMatch && !result.passport_expiry && dateMatch.length > 1) {
            result.passport_expiry = convertPassportDate(dateMatch[1]);
        }
    }
    
    return Object.keys(result).length > 0 ? result : null;
}

function convertPassportDate(yymmdd) {
    if (!yymmdd || yymmdd.length !== 6) return '';
    const yy = parseInt(yymmdd.substring(0, 2));
    const mm = yymmdd.substring(2, 4);
    const dd = yymmdd.substring(4, 6);
    const yyyy = (yy > 50) ? 1900 + yy : 2000 + yy;
    return `${yyyy}-${mm}-${dd}`;
}

function displayUploadedFiles() {
    const fileList = document.getElementById('uploadedFilesList');
    
    if (Object.keys(uploadedDocuments).length === 0) {
        fileList.innerHTML = '';
        return;
    }
    
    let html = '<div style="margin-top: 10px;"><small style="color: #666;"><strong>Extracted Data:</strong></small><ul style="font-size: 11px; margin: 5px 0; padding-left: 20px;">';
    
    Object.keys(uploadedDocuments).forEach(index => {
        const data = uploadedDocuments[index];
        const name = data.name || `File ${parseInt(index) + 1}`;
        html += `<li>${name} - Passport: ${data.passport_number || 'N/A'}, DOB: ${data.dob || 'N/A'}</li>`;
    });
    
    html += '</ul></div>';
    fileList.innerHTML = html;
}

function autoFillMember(rowId) {
    // Open file picker for this specific member
    const memberIndex = parseInt(rowId.split('_')[1]);
    const fileInput = document.getElementById('passportDocumentFile');
    fileInput.onchange = function() {
        if (this.files[0]) {
            processDocumentForMember(this.files[0], memberIndex);
        }
    };
    fileInput.click();
}

async function processDocumentForMember(file, memberIndex) {
    showToast('info', 'Extracting passport data...');
    
    let extractedData = await tryServerOCR(file);
    if (!extractedData) {
        extractedData = await tryClientOCR(file);
    }
    
    if (extractedData) {
        populateMemberForm(memberIndex, extractedData);
        showToast('success', 'Passport data extracted and filled');
    } else {
        showToast('warning', 'Could not extract passport data. Please fill manually.');
    }
}

function populateMemberForm(memberIndex, data) {
    if (data.name) $(`#members_${memberIndex}_name`).val(data.name);
    if (data.dob) $(`#members_${memberIndex}_dob`).val(data.dob);
    if (data.passport_number) $(`#members_${memberIndex}_passport_number`).val(data.passport_number);
    if (data.passport_expiry) $(`#members_${memberIndex}_passport_expiry`).val(data.passport_expiry);
    
    updateMembersSummary();
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
        const dob = $(this).find('input[name*="[dob]"]').val() || 'N/A';
        summaryHtml += `<li>${name} (DOB: ${dob})</li>`;
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
                showToast('error', `Member ${index + 1}: Missing required field: ${$(this).prev('label').text()}`);
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
    
    // Initialize document upload
    setupMultiDocumentUpload();
});

// Reset form on modal close
$('#umrahModal').on('hidden.bs.modal', function() {
    memberRowCounter = 0;
    uploadedDocuments = {};
});
