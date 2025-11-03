<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

require_once('../includes/db.php');
include '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

// Get client info for pre-selection
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Client fetch error: " . $e->getMessage());
}
?>

<?php include '../includes/header_client.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                
                                        <!-- body -->
                                    <div class="container-fluid px-4">
                                        <div class="card custom-card">
                                            <div class="card-header bg-gradient">
                                                <h5 class="mb-0 text-white">
                                                    <i class="feather icon-file-text me-2"></i><?= __('generate_report') ?>
                                                </h5>
                                            </div>
                                            <div class="card-body p-4">
                                                <form id="reportForm" class="row g-4">
                                                    <!-- Report Type Selection -->
                                                    <div class="col-lg-6">
                                                        <div class="form-group custom-form-group">
                                                            <label class="floating-label" for="reportType"><?= __('report_type') ?></label>
                                                            <select id="reportType" class="form-select form-select-lg" onchange="loadOptions()">
                                                                <option value="client" selected>👥 <?= __('client') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Dynamic Dropdown for Selecting Entity -->
                                                    <div class="col-lg-6" id="entitySelection" style="display: none;">
                                                        <div class="form-group custom-form-group">
                                                            <label class="floating-label" for="entity"><?= __('select_entity') ?></label>
                                                            <select id="entity" class="form-select form-select-lg"></select>
                                                        </div>
                                                    </div>

                                                    <!-- Report Category Selection -->
                                                    <div class="col-lg-6" id="reportCategorySelection" style="display: none;">
                                                        <div class="form-group custom-form-group">
                                                            <label class="floating-label" for="reportCategory"><?= __('report_category') ?></label>
                                                            <select id="reportCategory" class="form-select form-select-lg">
                                                                <option value="statement" selected>📊 <?= __('statement') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Statement Currency Selection -->
                                                    <div class="col-lg-6" id="statementFields" style="display: none;">
                                                        <div class="form-group custom-form-group">
                                                            <label class="floating-label" for="statementCurrency"><?= __('currency') ?></label>
                                                            <select id="statementCurrency" class="form-select form-select-lg">
                                                                <option value="USD" selected>💵 <?= __('usd') ?></option>
                                                                <option value="AFS">🪙 <?= __('afs') ?></option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Date Range Selection -->
                                                    <div class="col-lg-6">
                                                        <div class="form-group custom-form-group">
                                                            <label class="floating-label" for="dateRange"><?= __('date_range') ?></label>
                                                            <input type="text" id="dateRange" class="form-control form-control-lg" readonly>
                                                            <input type="hidden" id="startDate">
                                                            <input type="hidden" id="endDate">
                                                            <i class="feather icon-calendar input-icon"></i>
                                                        </div>
                                                    </div>

                                                    <!-- Generate Button -->
                                                    <div class="col-12 text-end mt-4">
                                                        <button type="button" class="btn btn-primary btn-lg px-5 custom-btn" onclick="filterResults()">
                                                            <i class="feather icon-filter me-2"></i><?= __('generate_report') ?>
                                                            <div class="btn-hover-effect"></div>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Results Container -->
                                        <div id="resultsContainer" class="mt-4 custom-results" style="display: none;">
                                            <!-- Table will be inserted here -->
                                        </div>

                                        <!-- Export Buttons -->
                                        <div id="exportButtons" class="mt-4 text-end" style="display: none;">
                                            <button type="button" class="btn btn-danger btn-lg me-2 custom-btn" onclick="exportReport('pdf')">
                                                <i class="feather icon-file-text me-2"></i><?= __('pdf') ?>
                                                <div class="btn-hover-effect"></div>
                                            </button>
                                            <button type="button" class="btn btn-success btn-lg me-2 custom-btn" onclick="exportReport('excel')">
                                                <i class="feather icon-file me-2"></i><?= __('excel') ?>
                                                <div class="btn-hover-effect"></div>
                                            </button>
                                            <button type="button" class="btn btn-primary btn-lg custom-btn" onclick="exportReport('word')">
                                                <i class="feather icon-file me-2"></i><?= __('word') ?>
                                                <div class="btn-hover-effect"></div>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



<style>
#statementFields {
    margin-top: 15px;
    padding: 15px;
    border-radius: 4px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
}

#statementFields .form-group {
    margin-bottom: 0;
}

#statementFields label {
    font-weight: 500;
    color: #495057;
}

#statementFields select {
    border-color: #ced4da;
}

#statementFields select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Add these styles to your CSS */
.form-floating > .form-select,
.form-floating > .form-control {
    height: calc(3.5rem + 2px);
    line-height: 1.25;
}

.form-floating > label {
    padding: 1rem 0.75rem;
}

.form-floating > .form-select:focus,
.form-floating > .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.card {
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem 1.5rem;
}

.btn {
    border-radius: 0.35rem;
    padding: 0.5rem 1rem;
    font-weight: 500;
}

.btn-primary {
    background-color: #5E72E4;
    border-color: #5E72E4;
}

.btn-primary:hover {
    background-color: #324cdd;
    border-color: #324cdd;
}

/* Modern Card Styling */
.custom-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.custom-card:hover {
    transform: translateY(-5px);
}

.card-header.bg-gradient {
    background: linear-gradient(45deg, #4776E6, #8E54E9);
    border-radius: 15px 15px 0 0;
    padding: 1.5rem;
}

/* Form Controls Styling */
.custom-form-group {
    position: relative;
    margin-bottom: 1rem;
}

.form-select-lg, .form-control-lg {
    height: 60px;
    border-radius: 12px;
    border: 2px solid #e0e6ed;
    padding: 0.75rem 1.25rem;
    font-size: 1rem;
    background-color: #f8fafc;
    transition: all 0.3s ease;
}

.form-select-lg:focus, .form-control-lg:focus {
    border-color: #4776E6;
    box-shadow: 0 0 0 0.25rem rgba(71, 118, 230, 0.1);
    background-color: #fff;
}

.floating-label {
    position: absolute;
    top: -10px;
    left: 15px;
    background: #fff;
    padding: 0 8px;
    color: #4776E6;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.input-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #8E54E9;
}

/* Custom Button Styling */
.custom-btn {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    font-weight: 500;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-hover-effect {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.custom-btn:hover .btn-hover-effect {
    transform: translateX(100%);
}

/* Results Container Styling */
.custom-results {
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    padding: 1.5rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .form-select-lg, .form-control-lg {
        height: 50px;
    }
    
    .card-header.bg-gradient {
        padding: 1rem;
    }
}

/* Animation for Form Elements */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-group {
    animation: fadeIn 0.5s ease forwards;
}
</style>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Add these scripts at the bottom of the file, before closing body tag -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

                        <script>
function loadOptions() {
    var reportType = document.getElementById("reportType").value;
    var entitySelection = document.getElementById("entitySelection");
    var entityDropdown = document.getElementById("entity");
    var reportCategorySelection = document.getElementById("reportCategorySelection");
    var statementFields = document.getElementById("statementFields");

    // Hide all optional fields initially
    entitySelection.style.display = "none";
    entityDropdown.innerHTML = "";
    reportCategorySelection.style.display = "none";
    statementFields.style.display = "none";

    if (reportType === "general" || reportType === "main_account") {
        // Show all options for general and main account
        reportCategorySelection.style.display = "block";
        
        // Reset or populate report category options for general report
        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = `
            <option value=""><?= __('select_report_category') ?></option>
            <option value="statement">📊 <?= __('statement') ?></option>
        `;
    } else if (reportType === "supplier" || reportType === "client") {
        // Show limited options for suppliers and clients
        reportCategorySelection.style.display = "block";
        
        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = `
            <option value=""><?= __('select_report_category') ?></option>
            <option value="statement">📊 <?= __('statement') ?></option>
        `;
    }

    if (reportType === "supplier" || reportType === "main_account" || reportType === "client") {
        entitySelection.style.display = "block";

        if (reportType === "client") {
            // For client reports, pre-select the logged-in client
            entityDropdown.innerHTML = `<option value="<?= $_SESSION['user_id'] ?>" selected><?= htmlspecialchars($client['name'] ?? 'Client') ?></option>`;
        } else {
            $.ajax({
                url: "load_entities.php",
                type: "POST",
                data: { type: reportType },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        entityDropdown.innerHTML = '<option value=""><?= __('select_an_entity') ?></option>';
                        response.data.forEach(function(entity) {
                            entityDropdown.innerHTML += `<option value="${entity.id}">${entity.name}</option>`;
                        });
                    } else {
                        entityDropdown.innerHTML = '<option value=""><?= __('no_entities_found') ?></option>';
                    }
                },
                error: function() {
                    entityDropdown.innerHTML = '<option value=""><?= __('error_loading_entities') ?></option>';
                }
            });
        }
    }

    if (reportType !== "") {
        reportCategorySelection.style.display = "block";
        
        // Add event listener for report category changes
        document.getElementById("reportCategory").addEventListener("change", function() {
            if (this.value === "statement") {
                statementFields.style.display = "block";
            } else {
                statementFields.style.display = "none";
            }
        });
    }
}

function filterResults() {
    var reportType = document.getElementById("reportType").value;
    var entity = "<?= $_SESSION['user_id'] ?>"; // Always use logged-in client ID
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var resultsContainer = document.getElementById("resultsContainer");

    if (!reportType || !startDate || !endDate) {
        alert("<?= __('please_select_all_required_fields') ?>");
        return;
    }

    // For client reports, entity is pre-selected, so only check other required fields
    if (!reportCategory || !startDate || !endDate) {
        alert("<?= __('please_select_all_required_fields') ?>");
        return;
    }

    // Show loading indicator
    resultsContainer.style.display = "block";
    resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only"><?= __('loading') ?>...</span></div></div>';

    // Check if statement report is selected
    if (reportCategory === "statement") {
        // Get the selected currency
        var currency = document.getElementById("statementCurrency").value;
        
        // Handle statement generation
        $.ajax({
            url: "generateStatement.php",
            type: "POST",
            data: {
                reportType: reportType,
                entityId: entity,
                startDate: startDate,
                endDate: endDate,
                currency: currency
            },
            dataType: "json",
            success: function(response) {
                if (response.status === 'success' && response.data.transactions) {
                    // Hide the results container and show only export buttons
                    resultsContainer.style.display = "none";
                    document.getElementById("exportButtons").style.display = "block";
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('success') ?>',
                        text: '<?= __('report_generated_successfully_you_can_now_export_it_in_your_preferred_format') ?>'
                    });
                } else {
                    resultsContainer.innerHTML = '<div class="alert alert-warning"><?= __('no_statement_data_found') ?></div>';
                    document.getElementById("exportButtons").style.display = "none";
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                resultsContainer.innerHTML = '<div class="alert alert-danger"><?= __('error_generating_statement_please_try_again') ?></div>';
                document.getElementById("exportButtons").style.display = "none";
            }
        });
    } else {
        // Original report generation code
        $.ajax({
            url: "fetch_report_data.php",
            type: "POST",
            data: {
                reportType: reportType,
                entity: entity,
                reportCategory: reportCategory,
                startDate: startDate,
                endDate: endDate
            },
            dataType: "json",
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    // Hide the results container and show only export buttons
                    resultsContainer.style.display = "none";
                    document.getElementById("exportButtons").style.display = "block";
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('success') ?>',
                        text: '<?= __('report_generated_successfully_you_can_now_export_it_in_your_preferred_format') ?>'
                    });
                } else {
                    resultsContainer.innerHTML = '<div class="alert alert-warning"><?= __('no_data_found_for_the_selected_criteria') ?></div>';
                    document.getElementById("exportButtons").style.display = "none";
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                resultsContainer.innerHTML = '<div class="alert alert-danger"><?= __('error_fetching_data_please_try_again') ?></div>';
                document.getElementById("exportButtons").style.display = "none";
            }
        });
    }
}

function exportReport(format) {
    var reportType = document.getElementById("reportType").value;
    var entity = "<?= $_SESSION['user_id'] ?>"; // Always use logged-in client ID
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var currency = document.getElementById("statementCurrency").value;

    if (!reportCategory || !startDate || !endDate) {
        Swal.fire({
            icon: 'error',
            title: '<?= __('error') ?>',
            text: '<?= __('please_select_all_fields_and_filter_the_results_first') ?>'
        });
        return;
    }

    // If statement is selected, redirect to export_statement.php
    if (reportCategory === 'statement') {
        // Show loading message
        Swal.fire({
            title: '<?= __('generating_statement') ?>',
            text: '<?= __('please_wait') ?>...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Create a temporary form to handle the download
        var form = document.createElement('form');
        form.method = 'GET';
        form.action = 'export_statement.php';
        form.style.display = 'none';

        // Add parameters including format
        var params = {
            reportType: reportType,
            entity: entity,
            startDate: startDate,
            endDate: endDate,
            currency: currency,
            format: format // Add format parameter
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
                title: '<?= __('success') ?>',
                text: '<?= __('statement_has_been_generated_successfully_in') ?> ' + format.toUpperCase() + ' <?= __('format') ?>!'
            });
        }, 2000);

        document.body.removeChild(form);
    } else {
        // For other report types, use the original export functionality
        Swal.fire({
            title: '<?= __('generating_report') ?>',
            text: '<?= __('please_wait') ?>...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        window.location.href = "export_report.php?format=" + format + 
                              "&reportType=" + reportType + 
                              "&entity=" + entity + 
                              "&reportCategory=" + reportCategory + 
                              "&startDate=" + startDate + 
                              "&endDate=" + endDate;

        // Close loading after a short delay
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: '<?= __('success') ?>',
                text: '<?= __('report_has_been_generated_successfully') ?>'
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

// Add this at the start of your script to handle URL parameters
$(document).ready(function() {
    // Check for error parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        Swal.fire({
            icon: 'error',
            title: '<?= __('error') ?>',
            text: decodeURIComponent(error)
        });
    }

    if (success) {
        Swal.fire({
            icon: 'success',
                title: '<?= __('success') ?>',
            text: decodeURIComponent(success)
        });
    }
});

$(document).ready(function() {
    // Pre-select client and trigger loadOptions
    loadOptions();

    $('#dateRange').daterangepicker({
        startDate: moment('<?= $client['created_at'] ?? date('Y-m-d') ?>'),
        endDate: moment(),
        ranges: {
           '<?= __('today') ?>': [moment(), moment()],
           '<?= __('yesterday') ?>': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           '<?= __('last_7_days') ?>': [moment().subtract(6, 'days'), moment()],
           '<?= __('last_30_days') ?>': [moment().subtract(29, 'days'), moment()],
           '<?= __('this_month') ?>': [moment().startOf('month'), moment().endOf('month')],
           '<?= __('last_month') ?>': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
           '<?= __('this_year') ?>': [moment().startOf('year'), moment().endOf('year')],
           '<?= __('last_year') ?>': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
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

    // Set initial values for hidden inputs from client creation date to today
    $('#startDate').val(moment('<?= $client['created_at'] ?? date('Y-m-d') ?>').format('YYYY-MM-DD'));
    $('#endDate').val(moment().format('YYYY-MM-DD'));
});
</script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
