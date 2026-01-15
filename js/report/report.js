function loadOptions() {
    var reportType = document.getElementById("reportType").value;
    var entitySection = document.getElementById("entitySection");
    var entitySelection = document.getElementById("entitySelection");
    var entityDropdown = document.getElementById("entity");
    var reportConfigSection = document.getElementById("reportConfigSection");
    var reportCategorySelection = document.getElementById("reportCategorySelection");
    var statementFields = document.getElementById("statementFields");
    var expenseCategoryFields = document.getElementById("expenseCategoryFields");

    // Hide all optional sections initially
    entitySection.style.display = "none";
    entitySelection.style.display = "none";
    entityDropdown.innerHTML = "";
    reportConfigSection.style.display = "none";
    reportCategorySelection.style.display = "none";
    statementFields.style.display = "none";
    expenseCategoryFields.style.display = "none";

    // Get allowed features from the page (similar to header.php)
    var allowedFeatures = allowedFeaturesData;

    // Function to check if a feature is allowed
    function hasFeature(feature) {
        return allowedFeatures.includes(feature);
    }

    if (reportType === "general" || reportType === "main_account") {
        // Show report configuration section for general and main account
        reportConfigSection.style.display = "block";
        reportCategorySelection.style.display = "block";

        // Reset or populate report category options for general report
        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = '';

        // Dynamically add report categories based on allowed features
        var reportCategories = [
            { value: 'ticket', label: '🎫 Ticket', feature: 'ticket_bookings' },
            { value: 'ticket_reservation', label: '🎫 Ticket Reservation', feature: 'ticket_reservations' },
            { value: 'ticket_weight', label: '🎫 Ticket Weight', feature: 'ticket_weights' },
            { value: 'refund_ticket', label: '↩️ Refund Ticket', feature: 'refunded_tickets' },
            { value: 'date_change_ticket', label: '📅 Date Change Ticket', feature: 'date_change_tickets' },
            { value: 'visa', label: '🛂 Visa', feature: 'visa_applications' },
            { value: 'visa_refund', label: '🛂 Visa Refund', feature: 'visa_refunds' },
            { value: 'umrah', label: '🕌 Umrah', feature: 'umrah_bookings' },
            { value: 'umrah_refund', label: '🕌 Umrah Refund', feature: 'umrah_refunds' },
            { value: 'hotel', label: '🏨 Hotel', feature: 'hotel_bookings' },
            { value: 'hotel_refund', label: '🏨 Hotel Refund', feature: 'hotel_refunds' },
            { value: 'expense', label: '💸 Expense', feature: 'expense_management' },
            { value: 'creditor', label: '💼 Creditor', feature: 'creditors' },
            { value: 'debtor', label: '📝 Debtor', feature: 'debtors' },
            { value: 'additional_payment', label: '💵 Additional Payments', feature: 'additional_payments' },
            { value: 'statement', label: '📊 Statement', feature: 'financial_statements' }
        ];

        reportCategories.forEach(function(category) {
            if (hasFeature(category.feature)) {
                reportCategoryDropdown.innerHTML += `<option value="${category.value}">${category.label}</option>`;
            }
        });
    } else if (reportType === "supplier" || reportType === "client") {
        // Show entity and report configuration sections for suppliers and clients
        entitySection.style.display = "block";
        reportConfigSection.style.display = "block";
        reportCategorySelection.style.display = "block";

        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = '';

        var reportCategories = [
            { value: 'ticket', label: '🎫 Ticket', feature: 'ticket_bookings' },
            { value: 'ticket_reservation', label: '🎫 Ticket Reservation', feature: 'ticket_reservations' },
            { value: 'ticket_weight', label: '🎫 Ticket Weight', feature: 'ticket_weights' },
            { value: 'refund_ticket', label: '↩️ Refund Ticket', feature: 'refunded_tickets' },
            { value: 'date_change_ticket', label: '📅 Date Change Ticket', feature: 'date_change_tickets' },
            { value: 'visa', label: '🛂 Visa', feature: 'visa_applications' },
            { value: 'visa_refund', label: '🛂 Visa Refund', feature: 'visa_refunds' },
            { value: 'umrah', label: '🕌 Umrah', feature: 'umrah_bookings' },
            { value: 'umrah_refund', label: '🕌 Umrah Refund', feature: 'umrah_refunds' },
            { value: 'hotel', label: '🏨 Hotel', feature: 'hotel_bookings' },
            { value: 'hotel_refund', label: '🏨 Hotel Refund', feature: 'hotel_refunds' },
            { value: 'statement', label: '📊 Statement', feature: 'financial_statements' }
        ];

        reportCategories.forEach(function(category) {
            if (hasFeature(category.feature)) {
                reportCategoryDropdown.innerHTML += `<option value="${category.value}">${category.label}</option>`;
            }
        });
    }

    if (reportType === "supplier" || reportType === "main_account" || reportType === "client") {
        entitySelection.style.display = "block";

        $.ajax({
            url: "../api/report/load_entities.php",
            type: "POST",
            data: { type: reportType, tenant_id: tenantId, branch_id: branchId },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    entityDropdown.innerHTML = '<option value="">Select an Entity</option>';
                    response.data.forEach(function(entity) {
                        entityDropdown.innerHTML += `<option value="${entity.id}">${entity.name}</option>`;
                    });
                } else {
                    entityDropdown.innerHTML = '<option value="">No entities found</option>';
                }
            },
            error: function() {
                entityDropdown.innerHTML = '<option value="">Error loading entities</option>';
            }
        });

        // Add event listener for entity changes
        document.getElementById("entity").addEventListener("change", updateStartDateForStatement);
    }

    // Show report configuration section when report type is selected
    if (reportType !== "") {
        reportConfigSection.style.display = "block";
        reportCategorySelection.style.display = "block";
    }

    // Add event listener for report category changes
    document.getElementById("reportCategory").addEventListener("change", function() {
        // Clean up all dynamic fields first
        cleanupDynamicFields();

        if (this.value === "statement") {
            statementFields.style.display = "block";
            expenseCategoryFields.style.display = "none";
            umrahFamilyFields.style.display = "none";
            specificFamilySelection.style.display = "none";
            updateStartDateForStatement(); // Update start date if entity is selected
        } else if (this.value === "expense") {
            expenseCategoryFields.style.display = "block";
            statementFields.style.display = "none";
            umrahFamilyFields.style.display = "none";
            specificFamilySelection.style.display = "none";
            loadExpenseCategories(); // Load expense categories from the database
        } else if (this.value === "umrah") {
            umrahFamilyFields.style.display = "block";
            statementFields.style.display = "none";
            expenseCategoryFields.style.display = "none";
            specificFamilySelection.style.display = "none";
            // Reset family type to default
            document.getElementById("umrahFamilyType").value = "all";
            // Reset call counter for new session
            loadFamiliesCallCount = 0;
            console.log('Switched to umrah category, reset call counter');
            // Load families if specific is selected
            toggleFamilySelection();
        } else {
            statementFields.style.display = "none";
            expenseCategoryFields.style.display = "none";
            umrahFamilyFields.style.display = "none";
            specificFamilySelection.style.display = "none";
            // Reset family type when switching away
            document.getElementById("umrahFamilyType").value = "all";
            // Reset loading flag and counter
            isLoadingFamilies = false;
            loadFamiliesCallCount = 0;
            console.log('Switched away from umrah category, reset flags and counter');
        }
    });
}

// Function to load expense categories from the database
function loadExpenseCategories() {
    var expenseCategoryDropdown = document.getElementById("expenseCategory");
    
    // Show loading state
    expenseCategoryDropdown.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch categories from the server
    $.ajax({
        url: "../api/report/load_expense_categories.php",
        type: "GET",
        dataType: "json",
        success: function(response) {
            if (response.success && response.data.length > 0) {
                // Clear dropdown
                expenseCategoryDropdown.innerHTML = '';
                
                // Add options from response
                response.data.forEach(function(category) {
                    // Get appropriate emoji for the category (you can customize this)
                    let emoji = getEmojiForCategory(category.id);
                    expenseCategoryDropdown.innerHTML += `<option value="${category.id}">${emoji} ${category.name}</option>`;
                });
            } else {
                // If no categories or error, show default option
                expenseCategoryDropdown.innerHTML = '<option value="all">🔍 All Categories</option>';
            }
        },
        error: function() {
            // On error, show error message
            expenseCategoryDropdown.innerHTML = '<option value="all">🔍 All Categories</option>';
            console.error("Failed to load expense categories");
        }
    });
}

// Helper function to get emoji for category (customize as needed)
function getEmojiForCategory(categoryId) {
    const emojiMap = {
        'all': '🔍',
        'rent': '🏢',
        'utilities': '💡',
        'salaries': '👨‍💼',
        'office_supplies': '📎',
        'marketing': '📣',
        'travel': '✈️',
        'maintenance': '🔧',
        'other': '📌'
    };

    return emojiMap[categoryId] || '📋'; // Default emoji if not found
}

// Global flag to prevent multiple simultaneous calls
var isLoadingFamilies = false;
var loadFamiliesCallCount = 0;


// Function to clean up all dynamic fields and Bootstrap Select instances
function cleanupDynamicFields() {
    console.log('Cleaning up dynamic fields...');

    // Clean up expense category Bootstrap Select
    // Note: Bootstrap Select removed for specificFamily only

    // Reset family dropdown to original state
    var familyDropdown = document.getElementById("specificFamily");
    if (familyDropdown) {
        // Destroy Select2 if it exists
        if ($('#specificFamily').hasClass('select2-hidden-accessible')) {
            $('#specificFamily').select2('destroy');
        }
        $('#specificFamily').html('<option value="">Select Family</option>');
        familyDropdown.className = 'form-select form-select-lg';
    }

    console.log('Dynamic fields cleanup completed');
}

// Function to toggle family selection visibility
function toggleFamilySelection() {
    var type = document.getElementById("umrahFamilyType").value;
    var specificFamilySelection = document.getElementById("specificFamilySelection");

    if (type === "specific") {
        specificFamilySelection.style.display = "block";
        if (!isLoadingFamilies) {
            loadFamilies();
        }
    } else {
        specificFamilySelection.style.display = "none";
        cleanupFamilySelection();
    }
}

// Function to clean up family selection when switching away
function cleanupFamilySelection() {
    console.log('Cleaning up family selection...');

    // Set flag to prevent new loading
    isLoadingFamilies = false;


    // Reset the original select element
    var familyDropdown = document.getElementById("specificFamily");
    if (familyDropdown) {
        // Destroy Select2 if it exists
        if ($('#specificFamily').hasClass('select2-hidden-accessible')) {
            $('#specificFamily').select2('destroy');
        }
        $('#specificFamily').html('<option value="">Select Family</option>');
        familyDropdown.className = 'form-select form-select-lg';
    }

    console.log('Family selection cleanup completed');
}

// Function to load families from the database
function loadFamilies() {
    loadFamiliesCallCount++;
    console.log('loadFamilies function called (call #' + loadFamiliesCallCount + ')');

    // Prevent multiple simultaneous calls
    if (isLoadingFamilies) {
        console.log('Family loading already in progress, skipping...');
        return;
    }

    isLoadingFamilies = true;

    var familyDropdown = document.getElementById("specificFamily");
    if (!familyDropdown) {
        console.error('Family dropdown element not found');
        isLoadingFamilies = false;
        return;
    }


    // Show loading state
    familyDropdown.innerHTML = '<option value="">Loading...</option>';

    // Fetch families from the server
    $.ajax({
        url: "../api/report/load_families.php",
        type: "GET",
        dataType: "json",
        timeout: 10000, // Add timeout to prevent hanging requests
        success: function (response) {
            console.log('Ajax response received:', response);

            // Clear dropdown completely
            $('#specificFamily').empty();

            if (response.success && response.data.length > 0) {
                // Add default option
                $('#specificFamily').append($('<option>').val('').text('Select Family'));

                // Add families without duplicates
                var existingValues = new Set();
                response.data.forEach(function (family) {
                    if (!existingValues.has(family.family_id)) {
                        existingValues.add(family.family_id);
                        $('#specificFamily').append($('<option>').val(family.family_id).text(family.head_of_family));
                    }
                });

                console.log('Options added');

                // Initialize Select2
                $('#specificFamily').select2({
                    placeholder: 'Select Family',
                    allowClear: true,
                    width: '100%',
                    theme: 'bootstrap-5',
                    minimumResultsForSearch: 1 // Always show search box
                });

                isLoadingFamilies = false;
            } else {
                $('#specificFamily').html('<option value="">No families found</option>');
                console.log('No families found');
                isLoadingFamilies = false;
            }
        },
        error: function (xhr, status, error) {
            $('#specificFamily').html('<option value="">Error loading families</option>');
            console.error("Failed to load families:", error);
            console.error("XHR status:", status);
            console.error("XHR response:", xhr.responseText);
            isLoadingFamilies = false;
        }
    });
}



// Function to update start date for statement reports
function updateStartDateForStatement() {
    var entityId = document.getElementById("entity").value;
    var reportType = document.getElementById("reportType").value;
    var reportCategory = document.getElementById("reportCategory").value;

    if (entityId && reportCategory === "statement") {
        $.ajax({
            url: "../api/report/get_entity_created_date.php",
            type: "POST",
            data: { entityId: entityId, reportType: reportType, tenant_id: tenantId, branch_id: branchId },
            dataType: "json",
            success: function(response) {
                if (response.success && response.created_date) {
                    $('#startDate').val(response.created_date);
                    var endDate = $('#endDate').val();
                    $('#dateRange').val(moment(response.created_date).format('DD MMM YYYY') + ' - ' + moment(endDate).format('DD MMM YYYY'));
                }
            },
            error: function() {
                console.error("Failed to fetch entity created date");
            }
        });
    }
}

function filterResults() {
    var reportType = document.getElementById("reportType").value;
    var entity = document.getElementById("entity") ? document.getElementById("entity").value : "";
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var expenseCategory = reportCategory === "expense" ? document.getElementById("expenseCategory").value : "";
    var umrahFamilyType = reportCategory === "umrah" ? document.getElementById("umrahFamilyType").value : "";
    var specificFamily = reportCategory === "umrah" && umrahFamilyType === "specific" ? document.getElementById("specificFamily").value : "";

    if (!reportType || !startDate || !endDate) {
        alert("Please select all required fields");
        return;
    }

    // Special handling for general report type - don't require entity selection
    if (reportType === "general" && (!reportCategory || !startDate || !endDate)) {
        alert("Please select report category and date range");
        return;
    } else if (reportType !== "general" && ((!entity && (reportType === "supplier" || reportType === "main_account" || reportType === "client")) || !reportCategory || !startDate || !endDate)) {
        alert("Please select all required fields");
        return;
    }

    // Validation for umrah specific family selection
    if (reportCategory === "umrah" && umrahFamilyType === "specific" && !specificFamily) {
        alert("Please select a family");
        return;
    }

    // Show loading indicator
    var resultsSection = document.getElementById("resultsSection");
    var exportSection = document.getElementById("exportSection");
    resultsSection.style.display = "block";
    exportSection.style.display = "none";

    // Check if statement report is selected
    if (reportCategory === "statement") {
        // Get the selected currency
        var currency = document.getElementById("statementCurrency").value;
        
        // Handle statement generation
        $.ajax({
            url: "../api/report/generateStatement.php",
            type: "POST",
            data: {
                reportType: reportType,
                entityId: entity,
                startDate: startDate,
                endDate: endDate,
                currency: currency,
                tenant_id: tenantId,
                branch_id: branchId
            },
            dataType: "json",
            success: function(response) {
                if (response.status === 'success' && response.data.transactions) {
                    // Show export section
                    exportSection.style.display = "block";

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Report generated successfully. You can now export it in your preferred format',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    exportSection.style.display = "none";
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
               exportSection.style.display = "none";
            }
        });
    } else {
        // Original report generation code
        $.ajax({
            url: "../api/report/fetch_report_data.php",
            type: "POST",
            data: {
                reportType: reportType,
                entity: entity,
                reportCategory: reportCategory,
                startDate: startDate,
                endDate: endDate,
                expenseCategory: expenseCategory,
                umrahFamilyType: umrahFamilyType,
                specificFamily: specificFamily,
                tenant_id: tenantId,
                branch_id: branchId
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Show export section
                    exportSection.style.display = "block";

                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Report generated successfully. You can now export it in your preferred format',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    exportSection.style.display = "none";
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                exportSection.style.display = "none";
            }
        });
    }
}

function exportReport(format) {
    var reportType = document.getElementById("reportType").value;
    var entity = document.getElementById("entity") ? document.getElementById("entity").value : "";
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var currency = document.getElementById("statementCurrency").value;
    var expenseCategory = reportCategory === "expense" ? document.getElementById("expenseCategory").value : "";
    var umrahFamilyType = reportCategory === "umrah" ? document.getElementById("umrahFamilyType").value : "";
    var specificFamily = reportCategory === "umrah" && umrahFamilyType === "specific" ? document.getElementById("specificFamily").value : "";
    
    if (!reportType || !reportCategory || !startDate || !endDate) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please select all fields and filter the results first'
        });
        return;
    }

    // If statement is selected, redirect to export_statement.php
    if (reportCategory === 'statement') {
        // Show loading message
        Swal.fire({
            title: 'Generating Statement',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Create a temporary form to handle the download
        var form = document.createElement('form');
        form.method = 'GET';
        form.action = '../api/report/export_statement.php';
        form.style.display = 'none';

        // Add parameters including format
        var params = {
            reportType: reportType,
            entity: entity,
            startDate: startDate,
            endDate: endDate,
            currency: currency,
            format: format, // Add format parameter
            tenant_id: tenantId,
            branch_id: branchId
        };

        for (var key in params) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        
        // Submit form and handle response
        form.submit();
        
        // Close loading after a short delay
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Statement has been generated successfully in ' + format.toUpperCase() + ' format!'
            });
        }, 2000);

        document.body.removeChild(form);
    } else {
        // For other report types, use the original export functionality
        Swal.fire({
            title: 'Generating Report',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        window.location.href = "../api/report/export_report.php?format=" + format +
                              "&reportType=" + reportType +
                              "&entity=" + entity +
                              "&reportCategory=" + reportCategory +
                              "&startDate=" + startDate +
                              "&endDate=" + endDate +
                              "&tenant_id=" + tenantId +
                              "&branch_id=" + branchId +
                              (expenseCategory ? "&expenseCategory=" + expenseCategory : "") +
                              (umrahFamilyType ? "&umrahFamilyType=" + umrahFamilyType : "") +
                              (specificFamily ? "&specificFamily=" + specificFamily : "");

        // Close loading after a short delay
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Report has been generated successfully'
            });
        }, 2000);
    }
}

// Utility functions for statement formatting
function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatAmount(amount) {
    if (!amount) return '0.00';
    return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    // Convert to string if it's not already a string
    str = String(str);
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Add this function for debugging
function debugDate(dateString) {
    console.log('Original date:', dateString);
    const date = new Date(dateString);
    console.log('Parsed date:', date);
    return formatDate(dateString);
}

// Initialize the report functionality when the document is ready
$(document).ready(function() {
    // Global variables to store tenant and branch IDs
    window.tenantId = document.querySelector('meta[name="tenant-id"]') ? 
        document.querySelector('meta[name="tenant-id"]').getAttribute('content') : '';
    window.branchId = document.querySelector('meta[name="branch-id"]') ? 
        document.querySelector('meta[name="branch-id"]').getAttribute('content') : '';
    window.allowedFeaturesData = document.querySelector('meta[name="allowed-features"]') ? 
        JSON.parse(document.querySelector('meta[name="allowed-features"]').getAttribute('content')) : [];

    // Check for error parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: decodeURIComponent(error)
        });
    }

    if (success) {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: decodeURIComponent(success)
        });
    }

    // Test family selection functionality
    console.log('Testing family selection elements...');
    var umrahFamilyType = document.getElementById('umrahFamilyType');
    var specificFamilySelection = document.getElementById('specificFamilySelection');
    var specificFamily = document.getElementById('specificFamily');

    if (umrahFamilyType) {
        console.log('umrahFamilyType element found');
        // Remove any existing event listeners to prevent duplicates
        var newUmrahFamilyType = umrahFamilyType.cloneNode(true);
        umrahFamilyType.parentNode.replaceChild(newUmrahFamilyType, umrahFamilyType);
        // Add event listener to the new element
        newUmrahFamilyType.addEventListener('change', toggleFamilySelection);
    } else {
        console.error('umrahFamilyType element NOT found');
    }

    if (specificFamilySelection) {
        console.log('specificFamilySelection element found');
    } else {
        console.error('specificFamilySelection element NOT found');
    }

    if (specificFamily) {
        console.log('specificFamily element found');
    } else {
        console.error('specificFamily element NOT found');
    }

    $('#dateRange').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
           'This Year': [moment().startOf('year'), moment().endOf('year')],
           'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
        },
        locale: {
            format: 'DD MMM YYYY'
        }
    }, function(start, end) {
        // Update hidden inputs with formatted dates
        $('#startDate').val(start.format('YYYY-MM-DD'));
        $('#endDate').val(end.format('YYYY-MM-DD'));

        // If you have any function that needs to run when dates change
        if (typeof updateReport === 'function') {
            updateReport();
        }
    });

    // Set initial values for hidden inputs
    $('#startDate').val(moment().startOf('month').format('YYYY-MM-DD'));
    $('#endDate').val(moment().endOf('month').format('YYYY-MM-DD'));
});