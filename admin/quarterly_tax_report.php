<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role - admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../includes/db.php');

// Fetch suppliers
$suppliers_query = "SELECT id, name FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name ASC";
$stmt = $pdo->prepare($suppliers_query);
$stmt->execute([$tenant_id]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expense categories
$categories_query = "SELECT id, name FROM expense_categories WHERE tenant_id = ? ORDER BY name ASC";
$stmt = $pdo->prepare($categories_query);
$stmt->execute([$tenant_id]);
$expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<style>
    .card-header {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
        color: #ffffff !important;
        border-bottom: none !important;
    }

    .card-header h5 {
        color: #ffffff !important;
        margin-bottom: 0 !important;
    }

    .tab-content {
        border: 1px solid #ddd;
        border-top: none;
        padding: 25px;
        background: #f9f9f9;
    }

    .nav-tabs .nav-link {
        color: #666;
        border: 1px solid transparent;
        border-bottom: 3px solid transparent;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        color: #4099ff;
        border-bottom-color: #4099ff;
    }

    .nav-tabs .nav-link.active {
        color: #4099ff;
        border-bottom-color: #4099ff;
        background: white;
        cursor: pointer;
    }

    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .section-header i {
        font-size: 20px;
        color: #4099ff;
        margin-right: 10px;
    }

    .section-header h6 {
        margin: 0;
        color: #2c3e50;
    }

    .supplier-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .supplier-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .supplier-card .form-check-label {
        cursor: pointer;
        font-weight: 500;
        margin-bottom: 10px;
    }

    .supplier-options {
        background: #f8f9fa;
        padding: 12px;
        border-radius: 6px;
        margin-top: 10px;
    }

    .supplier-options .form-check {
        margin-bottom: 8px;
    }

    .expense-item {
        background: white;
        border-left: 4px solid #4099ff;
        padding: 12px;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    .expense-item .form-check {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .expense-item .form-check-label {
        margin-left: 8px;
        cursor: pointer;
        flex: 1;
    }

    .expense-item input[type="number"] {
        width: 120px;
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .btn-generate {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(64, 153, 255, 0.3);
    }

    .quarters-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .quarter-btn {
        padding: 10px;
        border: 2px solid #ddd;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        text-align: center;
    }

    .quarter-btn:hover {
        border-color: #4099ff;
        color: #4099ff;
    }

    .quarter-btn.active {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
        border-color: #4099ff;
    }

    .alert {
        border-radius: 8px;
        border: none;
    }

    .alert-info {
        background: #e7f3ff;
        color: #004085;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .summary-table th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        padding: 10px;
        text-align: left;
        font-weight: 600;
    }

    .summary-table td {
        border-bottom: 1px solid #dee2e6;
        padding: 10px;
    }

    .summary-table tr:hover {
        background: #f8f9fa;
    }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card custom-card shadow-lg">
                                    <div class="card-header overflow-hidden">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <div class="icon-wrapper me-3">
                                                    <i class="feather icon-file-text text-white"></i>
                                                </div>
                                                <div>
                                                    <h5 class="mb-0 text-white fw-bold">Quarterly Tax Report Generator</h5>
                                                    <small class="text-white-50">Generate individual supplier or general quarterly tax reports</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                         <!-- Report Type Selector (Global for both tabs) -->
                                         <div class="alert alert-info mb-3">
                                             <label class="mb-0"><strong>Select Report Type:</strong></label>
                                             <div class="mt-2">
                                                 <div class="form-check form-check-inline">
                                                     <input class="form-check-input" type="radio" name="reportType" id="type_ticket" value="ticket" checked>
                                                     <label class="form-check-label" for="type_ticket">Ticket</label>
                                                 </div>
                                                 <div class="form-check form-check-inline">
                                                     <input class="form-check-input" type="radio" name="reportType" id="type_visa" value="visa">
                                                     <label class="form-check-label" for="type_visa">Visa</label>
                                                 </div>
                                                 <div class="form-check form-check-inline">
                                                     <input class="form-check-input" type="radio" name="reportType" id="type_umrah" value="umrah">
                                                     <label class="form-check-label" for="type_umrah">Umrah</label>
                                                 </div>
                                                 <div class="form-check form-check-inline">
                                                     <input class="form-check-input" type="radio" name="reportType" id="type_hotel" value="hotel">
                                                     <label class="form-check-label" for="type_hotel">Hotel</label>
                                                 </div>
                                                 <div class="form-check form-check-inline">
                                                     <input class="form-check-input" type="radio" name="reportType" id="type_all" value="all">
                                                     <label class="form-check-label" for="type_all">All Types</label>
                                                 </div>
                                             </div>
                                         </div>

                                         <!-- Tab Navigation -->
                                         <ul class="nav nav-tabs" role="tablist">
                                              <li class="nav-item" role="presentation">
                                                  <a class="nav-link active" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier-report" role="tab" aria-controls="supplier-report" aria-selected="true">
                                                      <i class="feather icon-building me-2"></i>Individual Supplier Report
                                                  </a>
                                              </li>
                                             <li class="nav-item" role="presentation">
                                                 <a class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-report" role="tab" aria-controls="general-report" aria-selected="false">
                                                     <i class="feather icon-bar-chart me-2"></i>General Tax Report
                                                 </a>
                                             </li>
                                             <li class="nav-item" role="presentation">
                                                 <a class="nav-link" id="saved-reports-tab" data-bs-toggle="tab" data-bs-target="#saved-reports" role="tab" aria-controls="saved-reports" aria-selected="false">
                                                     <i class="feather icon-archive me-2"></i>Saved Reports
                                                 </a>
                                             </li>
                                         </ul>

                                         <div class="tab-content">
                                             <!-- SUPPLIER REPORT TAB -->
                                             <div class="tab-pane fade show active" id="supplier-report" role="tabpanel" aria-labelledby="supplier-tab">
                                                <form id="supplierReportForm">
                                                    <!-- Quarter Selection -->
                                                    <div class="section-header">
                                                        <i class="feather icon-calendar"></i>
                                                        <h6>Select Quarter Period</h6>
                                                    </div>

                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Year</label>
                                                            <select id="supplierYear" class="form-select" required>
                                                                <option value="">Select Year</option>
                                                                <?php
                                                                $currentYear = date('Y');
                                                                for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                                    echo "<option value=\"$y\">$y</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Quarter</label>
                                                            <div class="quarters-selector" id="supplierQuarters">
                                                                <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                            </div>
                                                            <input type="hidden" id="supplierQuarter" required>
                                                        </div>
                                                    </div>

                                                    <!-- Custom Date Range (Optional) -->
                                                    <div class="alert alert-info small mb-3">
                                                        <i class="feather icon-info me-2"></i>
                                                        Or specify custom date range for the quarter period:
                                                    </div>
                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold small">Quarter Start Date</label>
                                                            <input type="date" id="supplierQuarterStart" class="form-control" placeholder="From">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold small">Quarter End Date</label>
                                                            <input type="date" id="supplierQuarterEnd" class="form-control" placeholder="To">
                                                        </div>
                                                    </div>

                                                    <!-- Exchange Rate -->
                                                    <div class="section-header">
                                                        <i class="feather icon-dollar-sign"></i>
                                                        <h6>Tax Configuration</h6>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="exchangeRate" class="form-label">Exchange Rate</label>
                                                        <input type="number" id="exchangeRate" class="form-control" placeholder="Enter exchange rate (e.g., 1.25)" step="0.01" min="0" value="1">
                                                        <small class="text-muted">Amount will be multiplied by this rate, then 4% tax will be extracted</small>
                                                    </div>

                                                    <!-- Supplier Selection -->
                                                    <div class="section-header">
                                                        <i class="feather icon-users"></i>
                                                        <h6>Select Suppliers</h6>
                                                    </div>

                                                    <div id="suppliersContainer" class="mb-4">
                                                        <?php foreach ($suppliers as $supplier): ?>
                                                            <div class="supplier-card">
                                                                <div class="form-check">
                                                                    <input class="form-check-input supplier-checkbox" type="checkbox" id="supplier<?= $supplier['id'] ?>" value="<?= $supplier['id'] ?>" data-supplier-name="<?= htmlspecialchars($supplier['name']) ?>">
                                                                    <label class="form-check-label" for="supplier<?= $supplier['id'] ?>">
                                                                        <?= htmlspecialchars($supplier['name']) ?>
                                                                    </label>
                                                                </div>

                                                                <div class="supplier-options" style="display: none;">
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-semibold small">Data Type</label>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input data-type-radio" type="radio" name="dataType<?= $supplier['id'] ?>" id="actual<?= $supplier['id'] ?>" value="actual" checked>
                                                                            <label class="form-check-label" for="actual<?= $supplier['id'] ?>">
                                                                                Use Actual Data
                                                                            </label>
                                                                        </div>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input data-type-radio" type="radio" name="dataType<?= $supplier['id'] ?>" id="random<?= $supplier['id'] ?>" value="random">
                                                                            <label class="form-check-label" for="random<?= $supplier['id'] ?>">
                                                                                Generate Random Data
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <div class="random-options" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                                                                        <label class="form-label fw-semibold small">Profit Range</label>
                                                                        <div class="row g-2">
                                                                            <div class="col-6">
                                                                                <input type="number" class="form-control form-control-sm profit-min" placeholder="Min Profit" min="0">
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <input type="number" class="form-control form-control-sm profit-max" placeholder="Max Profit" min="0">
                                                                            </div>
                                                                        </div>

                                                                        <label class="form-label fw-semibold small mt-2">Number of Items</label>
                                                                        <div class="row g-2">
                                                                            <div class="col-6">
                                                                                <input type="number" class="form-control form-control-sm items-count" placeholder="Item Count" min="1" value="5">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="d-flex gap-2 mb-3">
                                                        <button type="button" class="btn btn-generate" id="generateSupplierReport">
                                                            <i class="feather icon-download me-2"></i>Generate Report
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" id="exportSupplierExcel">
                                                            <i class="feather icon-file-text me-2"></i>Export as Excel
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" id="exportSupplierPDF">
                                                            <i class="feather icon-download me-2"></i>Export as PDF
                                                        </button>
                                                    </div>

                                                    <!-- Preview Area -->
                                                    <div id="supplierReportPreview" style="display: none; margin-top: 20px;">
                                                        <div class="alert alert-info">
                                                            <i class="feather icon-info me-2"></i>Report Preview
                                                        </div>
                                                        <div id="supplierReportContent"></div>
                                                    </div>
                                                </form>
                                            </div>

                                            <!-- GENERAL REPORT TAB -->
                                            <div class="tab-pane fade" id="general-report" role="tabpanel" aria-labelledby="general-tab">
                                                <form id="generalReportForm">
                                                    <!-- Quarter Selection -->
                                                    <div class="section-header">
                                                        <i class="feather icon-calendar"></i>
                                                        <h6>Select Quarter Period</h6>
                                                    </div>

                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Year</label>
                                                            <select id="generalYear" class="form-select" required>
                                                                <option value="">Select Year</option>
                                                                <?php
                                                                $currentYear = date('Y');
                                                                for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                                    echo "<option value=\"$y\">$y</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold">Quarter</label>
                                                            <div class="quarters-selector" id="generalQuarters">
                                                                <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                                <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                            </div>
                                                            <input type="hidden" id="generalQuarter" required>
                                                        </div>
                                                    </div>

                                                    <!-- Custom Date Range (Optional) -->
                                                    <div class="alert alert-info small mb-3">
                                                        <i class="feather icon-info me-2"></i>
                                                        Or specify custom date range for the quarter period:
                                                    </div>
                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold small">Quarter Start Date</label>
                                                            <input type="date" id="generalQuarterStart" class="form-control" placeholder="From">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label fw-semibold small">Quarter End Date</label>
                                                            <input type="date" id="generalQuarterEnd" class="form-control" placeholder="To">
                                                        </div>
                                                    </div>

                                                    <!-- Expense Configuration -->
                                                    <div class="section-header">
                                                        <i class="feather icon-list"></i>
                                                        <h6>Configure Expenses</h6>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Expense Categories</label>
                                                        <div id="expenseCategoriesCheckboxes">
                                                            <?php foreach ($expense_categories as $cat): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input expense-category-checkbox" type="checkbox" value="<?= htmlspecialchars($cat['name']) ?>" id="cat<?= $cat['id'] ?>">
                                                                    <label class="form-check-label" for="cat<?= $cat['id'] ?>">
                                                                        <?= htmlspecialchars($cat['name']) ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Expenses Items -->
                                                    <div id="expenseItemsContainer" style="margin-top: 20px;">
                                                        <div class="section-header">
                                                            <i class="feather icon-dollar-sign"></i>
                                                            <h6>Expense Amounts</h6>
                                                        </div>
                                                        <!-- Expense items will be dynamically added here -->
                                                    </div>

                                                    <!-- Action Buttons -->
                                                    <div class="d-flex gap-2 mb-3">
                                                        <button type="button" class="btn btn-generate" id="generateGeneralReport">
                                                            <i class="feather icon-download me-2"></i>Generate Report
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" id="exportGeneralExcel">
                                                            <i class="feather icon-file-text me-2"></i>Export as Excel
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" id="exportGeneralPDF">
                                                            <i class="feather icon-download me-2"></i>Export as PDF
                                                        </button>
                                                    </div>

                                                    <!-- Preview Area -->
                                                    <div id="generalReportPreview" style="display: none; margin-top: 20px;">
                                                        <div class="alert alert-info">
                                                            <i class="feather icon-info me-2"></i>Report Preview
                                                        </div>
                                                        <div id="generalReportContent"></div>
                                                    </div>
                                                </form>
                                            </div>

                                            <!-- SAVED REPORTS TAB -->
                                            <div class="tab-pane fade" id="saved-reports" role="tabpanel" aria-labelledby="saved-reports-tab">
                                                <div class="section-header">
                                                    <i class="feather icon-calendar"></i>
                                                    <h6>Filter Saved Reports</h6>
                                                </div>

                                                <div class="row mb-4">
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Year</label>
                                                        <select id="savedReportsYear" class="form-select" required>
                                                            <option value="">Select Year</option>
                                                            <?php
                                                            $currentYear = date('Y');
                                                            for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                                echo "<option value=\"$y\">$y</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">Quarter</label>
                                                        <div class="quarters-selector" id="savedReportsQuarters">
                                                            <button type="button" class="quarter-btn" data-quarter="Q1">Q1</button>
                                                            <button type="button" class="quarter-btn" data-quarter="Q2">Q2</button>
                                                            <button type="button" class="quarter-btn" data-quarter="Q3">Q3</button>
                                                            <button type="button" class="quarter-btn" data-quarter="Q4">Q4</button>
                                                            <button type="button" class="quarter-btn" data-quarter="">All</button>
                                                        </div>
                                                        <input type="hidden" id="savedReportsQuarter">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label fw-semibold">&nbsp;</label>
                                                        <button type="button" class="btn btn-primary w-100" id="loadSavedReportsBtn">
                                                            <i class="feather icon-download me-2"></i>Load Reports
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Supplier Reports Section -->
                                                <div class="section-header mt-4">
                                                    <i class="feather icon-building me-2"></i>
                                                    <h6>Saved Supplier Reports</h6>
                                                </div>
                                                <div id="savedSupplierReportsContainer" class="mb-4">
                                                    <div class="alert alert-info">
                                                        <i class="feather icon-info me-2"></i>
                                                        Select a year to view saved supplier reports
                                                    </div>
                                                </div>

                                                <!-- General Reports Section -->
                                                <div class="section-header mt-4">
                                                    <i class="feather icon-bar-chart me-2"></i>
                                                    <h6>Saved General Reports</h6>
                                                </div>
                                                <div id="savedGeneralReportsContainer" class="mb-4">
                                                    <div class="alert alert-info">
                                                        <i class="feather icon-info me-2"></i>
                                                        Select a year to view saved general reports
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Excel Export Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.min.js"></script>

<!-- PDF Export Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    console.log('Quarterly Tax Report page loaded');
    
    // Global variables from PHP
    const PHP_TENANT_ID = <?php echo json_encode($tenant_id); ?>;
    const PHP_BRANCH_ID = <?php echo json_encode($branch_id); ?>;
    
    // Store for supplier report data
    let supplierReportData = {};
    let generalReportData = {};
    let tempExpenses = [];  // Store temporary expenses added during report generation

    // Quarter button handlers
    function setupQuarterButtons() {
        document.querySelectorAll('.quarters-selector').forEach(container => {
            container.querySelectorAll('.quarter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const quarter = btn.getAttribute('data-quarter');
                    const input = container.nextElementSibling;
                    
                    container.querySelectorAll('.quarter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    input.value = quarter;
                });
            });
        });
    }

    // Supplier checkbox handlers
    function setupSupplierCheckboxes() {
        document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const card = checkbox.closest('.supplier-card');
                const options = card.querySelector('.supplier-options');
                options.style.display = checkbox.checked ? 'block' : 'none';

                if (checkbox.checked) {
                    setupDataTypeRadios(checkbox.value);
                }
            });
        });
    }

    function setupDataTypeRadios(supplierId) {
        const radios = document.querySelectorAll(`input[name="dataType${supplierId}"]`);
        radios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                const card = document.getElementById(`supplier${supplierId}`).closest('.supplier-card');
                const randomOptions = card.querySelector('.random-options');
                randomOptions.style.display = e.target.value === 'random' ? 'block' : 'none';
            });
        });
    }

    // Category selection handlers
    function setupCategoryCheckboxes() {
        document.querySelectorAll('.expense-category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const selected = Array.from(document.querySelectorAll('.expense-category-checkbox:checked'))
                    .map(cb => cb.value);
                updateExpenseItems(selected);
            });
        });
    }

    function updateExpenseItems(categories) {
         const container = document.getElementById('expenseItemsContainer');
         
         if (categories.length === 0) {
             container.innerHTML = `
                 <div class="section-header">
                     <i class="feather icon-dollar-sign"></i>
                     <h6>Expense Items</h6>
                 </div>
                 <div class="alert alert-info small">
                     <i class="feather icon-info me-2"></i>
                     Select expense categories above to view and select individual expenses
                 </div>
             `;
             return;
         }

         // Get the selected period for filtering expenses
         const year = document.getElementById('generalYear')?.value || new Date().getFullYear();
         const quarter = document.getElementById('generalQuarter')?.value || null;
         const quarterStart = document.getElementById('generalQuarterStart')?.value || null;
         const quarterEnd = document.getElementById('generalQuarterEnd')?.value || null;

         let html = `
             <div class="section-header">
                 <i class="feather icon-dollar-sign"></i>
                 <h6>Expense Items by Category</h6>
             </div>
         `;

         container.innerHTML = html + '<div class="text-center text-muted"><i class="feather icon-loader"></i> Loading expenses...</div>';

         // Currency symbol map
         const currencySymbols = {
             'USD': '$',
             'AFS': '؋',
             'AFN': '؋',
             'EUR': '€',
             'GBP': '£',
             'JPY': '¥',
             'INR': '₹',
             'AED': 'د.إ',
             'SAR': 'ر.س',
             'QAR': 'ق.ر',
             'KWD': 'د.ك',
             'BHD': 'د.ب',
             'OMR': 'ر.ع.',
             'PKR': '₨',
             'TRY': '₺'
         };

         // Fetch expenses for all selected categories
         Promise.all(categories.map(category => {
             return fetch('handlers/quarterly_tax_handler.php?action=get_expenses', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/json'
                 },
                 body: JSON.stringify({
                     quarter: quarter,
                     year: year,
                     date_from: quarterStart,
                     date_to: quarterEnd,
                     categories: [category]
                 })
             })
             .then(res => res.json())
             .then(data => {
                 // Include temporary expenses for this category
                 const allExpenses = (data.data || []).concat(
                     tempExpenses.filter(exp => exp.category === category)
                 );
                 return {
                     category: category,
                     expenses: allExpenses
                 };
             });
         }))
         .then(results => {
             let html = `
                 <div class="section-header">
                     <i class="feather icon-dollar-sign"></i>
                     <h6>Expense Items by Category</h6>
                 </div>
             `;

             let totalAmount = 0;
             let totalCurrency = 'USD'; // default

             results.forEach(result => {
                 const category = result.category;
                 const expenses = result.expenses;

                 html += `
                     <div class="expense-category-items mb-4">
                         <div class="alert alert-light border border-1" style="background: #f8f9fa;">
                             <h6 class="mb-3">
                                 <i class="feather icon-folder me-2"></i>
                                 <strong>${category}</strong>
                                 <span class="bg-info ms-2">${expenses.length} ${expenses.length === 1 ? 'item' : 'items'}</span>
                             </h6>
                 `;

                 if (expenses.length === 0) {
                     html += `
                         <div class="text-muted small">
                             <i class="feather icon-alert-circle me-1"></i>
                             No expenses found for this category in the selected period
                         </div>
                     `;
                 } else {
                     html += `
                         <table class="table table-sm table-bordered mb-3">
                             <thead class="bg-light">
                                 <tr>
                                     <th style="width: 40px;"><input type="checkbox" class="form-check-input category-select-all" data-category="${category}"></th>
                                     <th>Type</th>
                                     <th style="width: 120px; text-align: right;">Amount</th>
                                     <th style="width: 80px; text-align: center;">Include</th>
                                 </tr>
                             </thead>
                             <tbody>
                     `;

                     let categoryAmount = 0;
                     let expenseCurrency = 'USD'; // default

                     expenses.forEach((expense, idx) => {
                         const amount = parseFloat(expense.total_amount || 0);
                         expenseCurrency = expense.currency || 'USD';
                         totalCurrency = expenseCurrency; // Track currency for total
                         categoryAmount += amount;
                         
                         const currencySymbol = currencySymbols[expenseCurrency] || expenseCurrency;
                         
                         html += `
                             <tr>
                                 <td></td>
                                 <td><strong>${expense.category}</strong></td>
                                 <td style="text-align: right;">${currencySymbol}${amount.toFixed(2)}</td>
                                 <td style="text-align: center;">
                                     <input type="checkbox" class="form-check-input expense-item-checkbox" 
                                            data-category="${category}" data-amount="${amount}" checked>
                                 </td>
                             </tr>
                         `;
                     });

                     totalAmount += categoryAmount;
                     const currencySymbol = currencySymbols[expenseCurrency] || expenseCurrency;

                     html += `
                             </tbody>
                         </table>
                         <div style="text-align: right; padding: 10px 0; border-top: 1px solid #dee2e6;">
                             <strong>Category Total:</strong> ${currencySymbol}${categoryAmount.toFixed(2)}
                         </div>
                     `;
                 }

                 html += `
                         </div>
                     </div>
                 `;
             });

             const totalCurrencySymbol = currencySymbols[totalCurrency] || totalCurrency;
             html += `
                 <div style="background: #f0f0f0; padding: 15px; border-radius: 8px; margin-top: 20px;">
                     <h6><strong>Total Selected Expenses:</strong> ${totalCurrencySymbol}<span id="totalExpensesAmount">${totalAmount.toFixed(2)}</span></h6>
                 </div>
                 
                 <div style="margin-top: 20px;">
                     <div class="section-header">
                         <i class="feather icon-plus-circle"></i>
                         <h6>Add New Expense</h6>
                     </div>
                     
                     <div class="alert alert-light border">
                         <div class="row g-3">
                             <div class="col-md-4">
                                 <label class="form-label small fw-semibold">Category</label>
                                 <input type="text" id="newExpenseCategory" class="form-control form-control-sm" placeholder="Select or type category">
                                 <small class="text-muted">Start typing to create new or select existing</small>
                                 <div id="categoryDropdown" style="position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; display: none; max-height: 200px; overflow-y: auto; z-index: 1000; width: 300px;"></div>
                             </div>
                             <div class="col-md-4">
                                 <label class="form-label small fw-semibold">Description</label>
                                 <input type="text" id="newExpenseDescription" class="form-control form-control-sm" placeholder="Expense description">
                             </div>
                             <div class="col-md-2">
                                 <label class="form-label small fw-semibold">Amount (USD)</label>
                                 <input type="number" id="newExpenseAmount" class="form-control form-control-sm" placeholder="0.00" min="0" step="0.01">
                             </div>
                             <div class="col-md-2">
                                 <label class="form-label small fw-semibold">&nbsp;</label>
                                 <button type="button" class="btn btn-sm btn-primary w-100" id="addNewExpenseBtn">
                                     <i class="feather icon-plus me-1"></i>Add
                                 </button>
                             </div>
                         </div>
                     </div>
                 </div>
             `;

             container.innerHTML = html;

             // Set up event listeners for checkboxes
             setupExpenseCheckboxes();
             setupNewExpenseForm();
         })
         .catch(error => {
             console.error('Error loading expenses:', error);
             container.innerHTML = `
                 <div class="alert alert-danger">
                     <i class="feather icon-alert-circle me-2"></i>
                     Failed to load expenses. Please try again.
                 </div>
             `;
         });
     }

     function setupExpenseCheckboxes() {
         // Select all checkbox for each category
         document.querySelectorAll('.category-select-all').forEach(checkbox => {
             checkbox.addEventListener('change', (e) => {
                 const category = e.target.getAttribute('data-category');
                 document.querySelectorAll(`.expense-item-checkbox[data-category="${category}"]`).forEach(item => {
                     item.checked = e.target.checked;
                 });
                 updateTotalExpensesAmount();
             });
         });

         // Individual expense checkboxes
         document.querySelectorAll('.expense-item-checkbox').forEach(checkbox => {
             checkbox.addEventListener('change', () => {
                 updateTotalExpensesAmount();
             });
         });
     }

     function updateTotalExpensesAmount() {
         const total = Array.from(document.querySelectorAll('.expense-item-checkbox:checked'))
             .reduce((sum, checkbox) => sum + parseFloat(checkbox.getAttribute('data-amount')), 0);
         
         const totalElement = document.getElementById('totalExpensesAmount');
         if (totalElement) {
             totalElement.textContent = total.toFixed(2);
         }
     }

     function setupNewExpenseForm() {
         const categoryInput = document.getElementById('newExpenseCategory');
         const descriptionInput = document.getElementById('newExpenseDescription');
         const amountInput = document.getElementById('newExpenseAmount');
         const addBtn = document.getElementById('addNewExpenseBtn');
         const dropdown = document.getElementById('categoryDropdown');
         let allCategories = [];

         // Fetch all existing categories
         const existingCheckboxes = document.querySelectorAll('.expense-category-checkbox');
         existingCheckboxes.forEach(cb => {
             allCategories.push(cb.value);
         });

         // Category autocomplete
         categoryInput?.addEventListener('input', (e) => {
             const value = e.target.value.toLowerCase();
             
             if (value.length === 0) {
                 dropdown.style.display = 'none';
                 return;
             }

             const filtered = allCategories.filter(cat => cat.toLowerCase().includes(value));
             
             if (filtered.length === 0 && value.length > 0) {
                 dropdown.innerHTML = `
                     <div style="padding: 10px;">
                         <button type="button" class="btn btn-sm btn-outline-primary w-100" data-new-category="${value}">
                             <i class="feather icon-plus me-1"></i>Create "${value}"
                         </button>
                     </div>
                 `;
             } else {
                 dropdown.innerHTML = filtered.map(cat => 
                     `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; hover: background: #f9f9f9;">${cat}</div>`
                 ).join('');
             }
             
             dropdown.style.display = 'block';
         });

         // Dropdown item selection
         dropdown?.addEventListener('click', (e) => {
             const catItem = e.target.closest('div[style*="padding"]');
             if (catItem && !catItem.querySelector('button')) {
                 categoryInput.value = catItem.textContent;
                 dropdown.style.display = 'none';
             } else if (e.target.closest('button')) {
                 const newCat = e.target.closest('button').getAttribute('data-new-category');
                 categoryInput.value = newCat;
                 dropdown.style.display = 'none';
             }
         });

         // Add button click
         addBtn?.addEventListener('click', () => {
             const category = categoryInput.value.trim();
             const description = descriptionInput.value.trim();
             const amount = parseFloat(amountInput.value) || 0;

             if (!category) {
                 Swal.fire('Error', 'Please enter a category', 'error');
                 return;
             }

             if (amount <= 0) {
                 Swal.fire('Error', 'Amount must be greater than 0', 'error');
                 return;
             }

             // Create new expense
             createNewExpense(category, description, amount);
         });

         // Close dropdown when clicking outside
         document.addEventListener('click', (e) => {
             if (!e.target.closest('#newExpenseCategory') && !e.target.closest('#categoryDropdown')) {
                 dropdown.style.display = 'none';
             }
         });
     }

     function createNewExpense(category, description, amount) {
         // Add to temporary expenses (not saved to database)
         const tempExpense = {
             id: 'temp_' + Date.now(),
             category: category,
             description: description || category,
             total_amount: amount,
             isTemporary: true
         };

         tempExpenses.push(tempExpense);

         Swal.fire('Success', 'Expense added to this report', 'success');
         
         // Reset form
         document.getElementById('newExpenseCategory').value = '';
         document.getElementById('newExpenseDescription').value = '';
         document.getElementById('newExpenseAmount').value = '';
         
         // Reload expense items
         const categories = Array.from(document.querySelectorAll('.expense-category-checkbox:checked'))
             .map(cb => cb.value);
         updateExpenseItems(categories);
     }

    // Report generation handlers
    document.getElementById('generateSupplierReport')?.addEventListener('click', generateSupplierReport);
    document.getElementById('generateGeneralReport')?.addEventListener('click', generateGeneralReport);

    function generateSupplierReport() {
        const year = document.getElementById('supplierYear').value;
        const quarter = document.getElementById('supplierQuarter').value;
        const quarterStart = document.getElementById('supplierQuarterStart').value;
        const quarterEnd = document.getElementById('supplierQuarterEnd').value;
        const selectedSuppliers = Array.from(document.querySelectorAll('.supplier-checkbox:checked')).map(cb => ({
            id: cb.value,
            name: cb.getAttribute('data-supplier-name')
        }));

        if (selectedSuppliers.length === 0) {
            Swal.fire('Error', 'Please select at least one supplier', 'error');
            return;
        }

        // Validate either quarter or custom dates are provided
        if (!quarter && (!quarterStart || !quarterEnd)) {
            Swal.fire('Error', 'Please select a quarter or specify custom date range', 'error');
            return;
        }

        // Validate custom dates if provided
        if (quarterStart && quarterEnd && new Date(quarterStart) > new Date(quarterEnd)) {
            Swal.fire('Error', 'Quarter start date must be before end date', 'error');
            return;
        }

        // Collect supplier data
        const suppliers = selectedSuppliers.map(supplier => {
            const card = document.getElementById(`supplier${supplier.id}`).closest('.supplier-card');
            const dataType = document.querySelector(`input[name="dataType${supplier.id}"]:checked`).value;
            
            const data = {
                id: supplier.id,
                name: supplier.name,
                dataType: dataType
            };

            if (dataType === 'random') {
                data.profitMin = parseInt(card.querySelector('.profit-min').value) || 1000;
                data.profitMax = parseInt(card.querySelector('.profit-max').value) || 10000;
                data.itemCount = parseInt(card.querySelector('.items-count').value) || 5;
            }

            return data;
        });

        const exchangeRate = parseFloat(document.getElementById('exchangeRate').value) || 1;

        supplierReportData = {
            year: year || new Date().getFullYear(),
            quarter: quarter || null,
            quarterStart: quarterStart || null,
            quarterEnd: quarterEnd || null,
            exchangeRate: exchangeRate,
            suppliers,
            generatedAt: new Date().toLocaleString()
        };

        // Show preview
        displaySupplierReportPreview();
    }

    function displaySupplierReportPreview() {
        const preview = document.getElementById('supplierReportPreview');
        const content = document.getElementById('supplierReportContent');

        let periodDisplay = supplierReportData.quarterStart && supplierReportData.quarterEnd 
            ? `${supplierReportData.quarterStart} to ${supplierReportData.quarterEnd}`
            : (supplierReportData.quarter && supplierReportData.year ? `${supplierReportData.quarter} ${supplierReportData.year}` : 'Custom Period');

        let html = `
            <div class="alert alert-info mb-3">
                <strong>Report Period:</strong> ${periodDisplay}
            </div>
        `;

        // Fetch actual ticket data for each supplier
        supplierReportData.suppliers.forEach(supplier => {
            html += `
                <div class="supplier-section mb-4">
                    <h6 class="mb-3">
                        <i class="feather icon-building me-2"></i>
                        <strong>${supplier.name}</strong>
                        <small class="text-muted">(${supplier.dataType === 'actual' ? 'Actual Data' : 'Random Data'})</small>
                    </h6>
                    <div id="supplier-${supplier.id}-loading" class="text-center text-muted">
                        <i class="feather icon-loader"></i> Loading ticket data...
                    </div>
                    <div id="supplier-${supplier.id}-data" style="display:none;"></div>
                </div>
            `;

            // Fetch data via AJAX
            const reportType = document.querySelector('input[name="reportType"]:checked').value;
            const payload = {
                action: 'generate_supplier_report',
                tenant_id: PHP_TENANT_ID,
                branch_id: PHP_BRANCH_ID,
                supplier_id: supplier.id,
                supplier_name: supplier.name,
                quarter: supplierReportData.quarter,
                year: supplierReportData.year,
                date_from: supplierReportData.quarterStart,
                date_to: supplierReportData.quarterEnd,
                data_type: supplier.dataType,
                report_type: reportType,
                exchangeRate: supplierReportData.exchangeRate
            };
            
            // Store report type in the data object for export
            supplierReportData.reportType = reportType;

            // Only add profit parameters for random data
            if (supplier.dataType === 'random') {
                payload.profit_min = supplier.profitMin || 1000;
                payload.profit_max = supplier.profitMax || 10000;
                payload.item_count = supplier.itemCount || 5;
            }

            fetch('handlers/quarterly_tax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data) {
                    displaySupplierTickets(supplier.id, response.data);
                } else {
                    document.getElementById(`supplier-${supplier.id}-loading`).innerHTML = 
                        '<div class="alert alert-warning">No data found for this supplier.</div>';
                }
            })
            .catch(error => {
                document.getElementById(`supplier-${supplier.id}-loading`).innerHTML = 
                    '<div class="alert alert-danger">Error loading data: ' + error.message + '</div>';
            });
        });

        content.innerHTML = html;
        preview.style.display = 'block';
    }

    function displaySupplierTickets(supplierId, tickets) {
        const loadingEl = document.getElementById(`supplier-${supplierId}-loading`);
        const dataEl = document.getElementById(`supplier-${supplierId}-data`);

        if (!tickets || tickets.length === 0) {
            loadingEl.innerHTML = '<div class="alert alert-warning">No tickets found for this supplier in the selected period.</div>';
            return;
        }

        loadingEl.style.display = 'none';

        const exchangeRate = supplierReportData.exchangeRate || 1;

        let html = `
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Issue Date</th>
                        <th>Passenger</th>
                        <th>Sector</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>PNR</th>
                        <th>Base Price</th>
                        <th>Sold Price</th>
                        <th>Profit (USD)</th>
                    </tr>
                </thead>
                <tbody>
        `;

        let totalProfit = 0;
        let totalSold = 0;

        tickets.forEach(ticket => {
            const profit = ticket.details.profit || 0;
            const soldPrice = ticket.details.sold_price || 0;
            const ticketType = ticket.details.ticket_type || 'ticket';
            
            // Map ticket type to badge label
            let typeBadgeClass = 'bg-primary';
            let typeLabel = 'Ticket';
            
            if (ticketType === 'ticket_refund') {
                typeBadgeClass = 'bg-warning';
                typeLabel = 'Ticket Refund';
            } else if (ticketType === 'ticket_date_change') {
                typeBadgeClass = 'bg-info';
                typeLabel = 'Date Change';
            } else if (ticketType === 'visa') {
                typeBadgeClass = 'bg-success';
                typeLabel = 'Visa';
            } else if (ticketType === 'umrah') {
                typeBadgeClass = 'bg-danger';
                typeLabel = 'Umrah';
            } else if (ticketType === 'hotel') {
                typeBadgeClass = 'bg-secondary';
                typeLabel = 'Hotel';
            }
            
            totalProfit += profit;
            totalSold += soldPrice;

            html += `
                <tr>
                    <td>${ticket.issue_date}</td>
                    <td>${ticket.full_name}</td>
                    <td><small>${ticket.sector}</small></td>
                    <td><span class="${typeBadgeClass}">${typeLabel}</span></td>
                    <td><span class="bg-secondary">${ticket.details.status}</span></td>
                    <td><code>${ticket.details.pnr}</code></td>
                    <td class="text-end">$${parseFloat(ticket.details.base_price).toFixed(2)}</td>
                    <td class="text-end fw-bold">$${parseFloat(soldPrice).toFixed(2)}</td>
                    <td class="text-end text-success fw-bold">$${parseFloat(profit).toFixed(2)}</td>
                </tr>
            `;
        });

        const totalExchanged = totalProfit * exchangeRate;
        const totalTax = totalExchanged * 0.04;

        html += `
                <tr class="table-light fw-bold">
                    <td colspan="6">TOTAL (USD)</td>
                    <td class="text-end">-</td>
                    <td class="text-end">$${totalSold.toFixed(2)}</td>
                    <td class="text-end text-success">$${totalProfit.toFixed(2)}</td>
                </tr>
                <tr class="table-warning fw-bold">
                    <td colspan="6">EXCHANGE TO AFN (@ ${exchangeRate})</td>
                    <td class="text-end">-</td>
                    <td class="text-end">-</td>
                    <td class="text-end text-info">${totalExchanged.toFixed(2)} AFN</td>
                </tr>
                <tr class="table-danger fw-bold">
                    <td colspan="6">TAX (4% OF EXCHANGED AMOUNT)</td>
                    <td class="text-end">-</td>
                    <td class="text-end">-</td>
                    <td class="text-end text-danger">${totalTax.toFixed(2)} AFN</td>
                </tr>
                </tbody>
            </table>
        `;

        dataEl.innerHTML = html;
        dataEl.style.display = 'block';
    }

    function generateGeneralReport() {
         const year = document.getElementById('generalYear').value;
         const quarter = document.getElementById('generalQuarter').value;
         const quarterStart = document.getElementById('generalQuarterStart').value;
         const quarterEnd = document.getElementById('generalQuarterEnd').value;

         // Validate year selection
         if (!year) {
             Swal.fire('Error', 'Please select a year', 'error');
             return;
         }

         // Validate either quarter or custom dates are provided
         if (!quarter && (!quarterStart || !quarterEnd)) {
             Swal.fire('Error', 'Please select a quarter or specify custom date range', 'error');
             return;
         }

         // Validate custom dates if provided
         if (quarterStart && quarterEnd && new Date(quarterStart) > new Date(quarterEnd)) {
             Swal.fire('Error', 'Quarter start date must be before end date', 'error');
             return;
         }

         // Collect selected expense items from checkboxes
         const selectedExpenses = Array.from(document.querySelectorAll('.expense-item-checkbox:checked'));
         
         if (selectedExpenses.length === 0) {
             Swal.fire('Warning', 'No expenses selected. Please select at least one expense item.', 'warning');
             return;
         }

         // Group selected expenses by category
         const expensesByCategory = {};
         selectedExpenses.forEach(checkbox => {
             const category = checkbox.getAttribute('data-category');
             const amount = parseFloat(checkbox.getAttribute('data-amount'));
             
             if (!expensesByCategory[category]) {
                 expensesByCategory[category] = {
                     category: category,
                     amount: 0,
                     items: []
                 };
             }
             expensesByCategory[category].amount += amount;
             expensesByCategory[category].items.push({ amount });
         });

         const includedExpenses = Object.values(expensesByCategory);

         // Fetch saved supplier reports for this quarter
         const quarterToUse = quarter || null;
         
         const fetchUrl = `handlers/quarterly_tax_handler.php?action=get_saved_reports&quarter=${quarterToUse}&year=${year}`;
         console.log('Fetching supplier reports from:', fetchUrl);
         
         // First fetch supplier reports
         fetch(fetchUrl)
             .then(res => {
                 console.log('Response status:', res.status);
                 return res.json();
             })
             .then(response => {
                 console.log('Supplier reports response:', response);
                 
                 // Now fetch actual expenses for each selected category
                 const expensePromises = includedExpenses.map(expense => {
                     return fetch('handlers/quarterly_tax_handler.php?action=get_expenses', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json'
                         },
                         body: JSON.stringify({
                             quarter: quarterToUse,
                             year: year,
                             date_from: quarterStart,
                             date_to: quarterEnd,
                             categories: [expense.category]
                         })
                     })
                     .then(res => res.json())
                     .then(data => ({
                         category: expense.category,
                         amount: expense.amount,
                         items: data.data || []
                     }));
                 });
                 
                 return Promise.all(expensePromises).then(enrichedExpenses => {
                     return {
                         suppliers: response.data || [],
                         expenses: enrichedExpenses
                     };
                 });
             })
             .then(data => {
                 generalReportData = {
                     year,
                     quarter: quarterToUse,
                     quarterStart: quarterStart || null,
                     quarterEnd: quarterEnd || null,
                     expenses: data.expenses,
                     suppliers: data.suppliers,
                     generatedAt: new Date().toLocaleString()
                 };

                 console.log('Loaded ' + data.suppliers.length + ' supplier reports and ' + data.expenses.length + ' expense categories');
                 displayGeneralReportPreview();
             })
             .catch(error => {
                 console.error('Error fetching data:', error);
                 // Continue without data
                 generalReportData = {
                     year,
                     quarter: quarterToUse,
                     quarterStart: quarterStart || null,
                     quarterEnd: quarterEnd || null,
                     expenses: includedExpenses.map(e => ({category: e.category, amount: e.amount, items: []})),
                     suppliers: [],
                     generatedAt: new Date().toLocaleString()
                 };
                 displayGeneralReportPreview();
             });
     }

    function displayGeneralReportPreview() {
        const preview = document.getElementById('generalReportPreview');
        const content = document.getElementById('generalReportContent');

        let html = '<div>';
        
        // SUPPLIER INCOME AND TAX TABLE
        if (generalReportData.suppliers && generalReportData.suppliers.length > 0) {
            let totalIncome = 0;
            let totalTax = 0;
            
            html += `
                <div class="mb-4">
                    <h6 class="mb-3"><strong>Supplier Income & Tax</strong></h6>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Supplier Name</th>
                                <th>Income (USD)</th>
                                <th>Exchange Rate</th>
                                <th>Income (AFN)</th>
                                <th>Tax (4%)</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            generalReportData.suppliers.forEach(supplier => {
                const reportData = supplier.data;
                if (reportData && reportData.data) {
                    // Calculate total profit from the report
                    let profit = 0;
                    reportData.data.forEach(item => {
                        profit += (item.details.profit || 0);
                    });
                    
                    // Get exchange rate from the individual supplier report (if available) or use 1
                    const exchangeRate = reportData.exchange_rate || 1;
                    const exchanged = profit * exchangeRate;
                    const tax = exchanged * 0.04;
                    
                    totalIncome += exchanged;
                    totalTax += tax;
                    
                    html += `
                        <tr>
                            <td>${reportData.supplier_name || 'Unknown'}</td>
                            <td>$${profit.toFixed(2)}</td>
                            <td>${exchangeRate}</td>
                            <td>${exchanged.toFixed(2)} AFN</td>
                            <td>${tax.toFixed(2)} AFN</td>
                        </tr>
                    `;
                }
            });
            
            html += `
                        <tr style="font-weight: bold; background: #f0f0f0;">
                            <td colspan="3">TOTAL</td>
                            <td>${totalIncome.toFixed(2)} AFN</td>
                            <td>${totalTax.toFixed(2)} AFN</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            html += `
                <div class="alert alert-info mb-4">
                    <i class="feather icon-info me-2"></i>
                    No supplier reports found for this quarter. Make sure to generate individual supplier reports first.
                </div>
            `;
        }

        // EXPENSES TABLE
        let totalExpense = 0;
        html += `
            <div class="mb-4">
                <h6 class="mb-3"><strong>Expenses</strong></h6>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        generalReportData.expenses.forEach(expense => {
            totalExpense += expense.amount;
            html += `
                <tr>
                    <td>${expense.category}</td>
                    <td>$${expense.amount.toFixed(2)}</td>
                </tr>
            `;
        });

        html += `
                    <tr style="font-weight: bold; background: #f0f0f0;">
                        <td>Total</td>
                        <td>$${totalExpense.toFixed(2)}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 d-flex gap-2">
                <button type="button" class="btn btn-success" id="saveGeneralReport">
                    <i class="feather icon-save me-2"></i>Save Report
                </button>
                <button type="button" class="btn btn-outline-secondary" id="discardGeneralReport">
                    <i class="feather icon-x me-2"></i>Discard
                </button>
            </div>
            
            <small class="d-block mt-3 text-muted">Generated: ${generalReportData.generatedAt}</small>
        </div>
        `;

        content.innerHTML = html;
        preview.style.display = 'block';
        
        // Set up save/discard buttons
        document.getElementById('saveGeneralReport')?.addEventListener('click', saveGeneralReport);
        document.getElementById('discardGeneralReport')?.addEventListener('click', () => {
            preview.style.display = 'none';
        });
        }

        function saveGeneralReport() {
        const payload = {
            action: 'save_general_report',
            quarter: generalReportData.quarter,
            year: generalReportData.year,
            quarterStart: generalReportData.quarterStart,
            quarterEnd: generalReportData.quarterEnd,
            expenses: generalReportData.expenses,
            suppliers: generalReportData.suppliers,
            generatedAt: generalReportData.generatedAt
        };

        fetch('handlers/quarterly_tax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                Swal.fire('Success', 'General report saved successfully', 'success');
                // Store the report ID for future reference
                generalReportData.id = response.report_id;
            } else {
                Swal.fire('Error', response.message || 'Failed to save report', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to save report: ' + error.message, 'error');
        });
        }

    // Export handlers
    document.getElementById('exportSupplierExcel')?.addEventListener('click', () => {
        if (Object.keys(supplierReportData).length === 0) {
            Swal.fire('Error', 'Generate a report first', 'warning');
            return;
        }
        serverExport('supplier', 'xlsx');
    });

    document.getElementById('exportGeneralExcel')?.addEventListener('click', () => {
        if (Object.keys(generalReportData).length === 0) {
            Swal.fire('Error', 'Generate a report first', 'warning');
            return;
        }
        serverExport('general', 'xlsx');
    });

    document.getElementById('exportSupplierPDF')?.addEventListener('click', () => {
        if (Object.keys(supplierReportData).length === 0) {
            Swal.fire('Error', 'Generate a report first', 'warning');
            return;
        }
        serverExport('supplier', 'pdf');
    });

    document.getElementById('exportGeneralPDF')?.addEventListener('click', () => {
        if (Object.keys(generalReportData).length === 0) {
            Swal.fire('Error', 'Generate a report first', 'warning');
            return;
        }
        serverExport('general', 'pdf');
    });

    function serverExport(reportType, format) {
        const data = reportType === 'supplier' ? supplierReportData : generalReportData;
        
        fetch('handlers/quarterly_tax_export.php?report_type=' + reportType + '&format=' + format, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            if (!response.ok) throw new Error('Export failed');
            return response.blob();
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = data[reportType === 'supplier' ? 'supplier_name' : 'quarter'] + '.' + format;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            a.remove();
            Swal.fire('Success', `Report exported to ${format.toUpperCase()}`, 'success');
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Failed to export report', 'error');
        });
    }



    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        console.log('DOMContentLoaded event fired');
        
        setupQuarterButtons();
        setupSupplierCheckboxes();
        setupCategoryCheckboxes();
        
        // Initialize Bootstrap tabs manually
        const tabElements = document.querySelectorAll('[data-bs-toggle="tab"]');
        console.log('Found ' + tabElements.length + ' tab buttons');
        
        tabElements.forEach(tab => {
            console.log('Setting up tab:', tab.id);
            
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Tab clicked:', tab.id);
                
                // Remove active class from all tabs
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                    link.setAttribute('aria-selected', 'false');
                });
                
                // Remove active class from all tab panes
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active class to clicked tab
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                
                // Add active class to corresponding pane
                const targetId = tab.getAttribute('data-bs-target');
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    targetPane.classList.add('show', 'active');
                    console.log('Activated pane:', targetId);
                }
                });
                });
                });

                // Saved Reports Tab Functionality
                setupSavedReportsQuarterButtons();
                
                function setupSavedReportsQuarterButtons() {
                document.querySelectorAll('#savedReportsQuarters .quarter-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                e.preventDefault();
                const quarter = btn.getAttribute('data-quarter');
                const container = document.getElementById('savedReportsQuarters');
                
                container.querySelectorAll('.quarter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('savedReportsQuarter').value = quarter;
                });
                });
                }

                document.getElementById('loadSavedReportsBtn')?.addEventListener('click', () => {
                const year = document.getElementById('savedReportsYear').value;
                const quarter = document.getElementById('savedReportsQuarter').value;

                if (!year) {
                Swal.fire('Error', 'Please select a year', 'warning');
                return;
                }

                loadSavedReports(year, quarter);
                });

                function loadSavedReports(year, quarter) {
                const supplierContainer = document.getElementById('savedSupplierReportsContainer');
                const generalContainer = document.getElementById('savedGeneralReportsContainer');

                // Show loading state
                supplierContainer.innerHTML = '<div class="alert alert-info"><i class="feather icon-loader me-2"></i>Loading supplier reports...</div>';
                generalContainer.innerHTML = '<div class="alert alert-info"><i class="feather icon-loader me-2"></i>Loading general reports...</div>';

                // Fetch saved reports
                const url = `handlers/quarterly_tax_handler.php?action=get_all_saved_reports&year=${year}${quarter ? '&quarter=' + quarter : ''}`;
                
                fetch(url)
                .then(res => {
                if (!res.ok) throw new Error('Network response was not ok');
                return res.json();
                })
                .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to fetch reports');
                }

                const supplierReports = data.data.filter(r => r.report_type === 'supplier');
                const generalReports = data.data.filter(r => r.report_type === 'general');

                displaySupplierReports(supplierReports);
                displayGeneralReports(generalReports);
                })
                .catch(error => {
                console.error('Error:', error);
                supplierContainer.innerHTML = `<div class="alert alert-danger"><i class="feather icon-alert-circle me-2"></i>${error.message}</div>`;
                generalContainer.innerHTML = `<div class="alert alert-danger"><i class="feather icon-alert-circle me-2"></i>${error.message}</div>`;
                });
                }

                function displaySupplierReports(reports) {
                const container = document.getElementById('savedSupplierReportsContainer');

                if (reports.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="feather icon-info me-2"></i>No supplier reports found</div>';
                return;
                }

                let html = '<div class="table-responsive"><table class="table table-bordered table-hover"><thead class="bg-light"><tr><th>Supplier</th><th>Quarter</th><th>Year</th><th>Created</th><th>Actions</th></tr></thead><tbody>';

                reports.forEach(report => {
                const reportData = report.data || {};
                const supplierName = reportData.supplier_name || 'Unknown';
                const createdAt = new Date(report.created_at).toLocaleDateString();

                html += `<tr>
                <td><strong>${supplierName}</strong></td>
                <td>${report.quarter}</td>
                <td>${report.year}</td>
                <td>${createdAt}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewSupplierReport(${report.id})">
                        <i class="feather icon-eye me-1"></i>View
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="exportSavedSupplierReport(${report.id}, 'xlsx')">
                        <i class="feather icon-download me-1"></i>Excel
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSavedReport(${report.id}, 'supplier')">
                        <i class="feather icon-trash me-1"></i>Delete
                    </button>
                </td>
                </tr>`;
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
                }

                function displayGeneralReports(reports) {
                const container = document.getElementById('savedGeneralReportsContainer');

                if (reports.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="feather icon-info me-2"></i>No general reports found</div>';
                return;
                }

                let html = '<div class="table-responsive"><table class="table table-bordered table-hover"><thead class="bg-light"><tr><th>Quarter</th><th>Year</th><th>Suppliers</th><th>Expenses</th><th>Created</th><th>Actions</th></tr></thead><tbody>';

                reports.forEach(report => {
                const reportData = report.data || {};
                const supplierCount = (reportData.suppliers || []).length;
                const expenseCount = (reportData.expenses || []).length;
                const createdAt = new Date(report.created_at).toLocaleDateString();

                html += `<tr>
                <td><strong>${report.quarter}</strong></td>
                <td>${report.year}</td>
                <td><span class="bg-primary">${supplierCount} supplier(s)</span></td>
                <td><span class="bg-warning">${expenseCount} category(ies)</span></td>
                <td>${createdAt}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="viewGeneralReport(${report.id})">
                        <i class="feather icon-eye me-1"></i>View
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="exportSavedGeneralReport(${report.id}, 'xlsx')">
                        <i class="feather icon-download me-1"></i>Excel
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteSavedReport(${report.id}, 'general')">
                        <i class="feather icon-trash me-1"></i>Delete
                    </button>
                </td>
                </tr>`;
                });

                html += '</tbody></table></div>';
                container.innerHTML = html;
                }

                function viewSupplierReport(reportId) {
                // Fetch and display report details
                fetch(`handlers/quarterly_tax_handler.php?action=get_report&id=${reportId}`)
                .then(res => res.json())
                .then(data => {
                if (data.success && data.data) {
                    const reportData = data.data.data || {};
                    Swal.fire({
                        title: 'Supplier Report: ' + (reportData.supplier_name || 'Unknown'),
                        html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">
                            <p><strong>Quarter:</strong> ${reportData.quarter || 'N/A'}</p>
                            <p><strong>Year:</strong> ${reportData.year || 'N/A'}</p>
                            <p><strong>Created:</strong> ${reportData.created_at || 'N/A'}</p>
                        </div>`,
                        confirmButtonText: 'Close',
                        width: 600
                    });
                }
                })
                .catch(error => {
                Swal.fire('Error', 'Failed to view report', 'error');
                });
                }

                function viewGeneralReport(reportId) {
                // Fetch and display report details
                fetch(`handlers/quarterly_tax_handler.php?action=get_report&id=${reportId}`)
                .then(res => res.json())
                .then(data => {
                if (data.success && data.data) {
                    const reportData = data.data.data || {};
                    const suppliers = reportData.suppliers || [];
                    const expenses = reportData.expenses || [];
                    
                    Swal.fire({
                        title: `General Report - ${reportData.quarter} ${reportData.year}`,
                        html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">
                            <p><strong>Suppliers:</strong> ${suppliers.length}</p>
                            <p><strong>Expense Categories:</strong> ${expenses.length}</p>
                            <p><strong>Created:</strong> ${reportData.created_at || 'N/A'}</p>
                        </div>`,
                        confirmButtonText: 'Close',
                        width: 600
                    });
                }
                })
                .catch(error => {
                Swal.fire('Error', 'Failed to view report', 'error');
                });
                }

                function deleteSavedReport(reportId, reportType) {
                Swal.fire({
                title: 'Delete Report?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Delete'
                }).then(result => {
                if (result.isConfirmed) {
                fetch('handlers/quarterly_tax_handler.php?action=delete_report', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: reportId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Deleted!', 'Report has been deleted', 'success');
                        // Reload saved reports
                        const year = document.getElementById('savedReportsYear').value;
                        const quarter = document.getElementById('savedReportsQuarter').value;
                        loadSavedReports(year, quarter);
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete report', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Failed to delete report', 'error');
                });
                }
                });
                }

                function exportSavedSupplierReport(reportId, format) {
                // Redirect to export with report ID
                window.location.href = `handlers/quarterly_tax_export.php?action=export_saved&id=${reportId}&format=${format}&type=supplier`;
                }

                function exportSavedGeneralReport(reportId, format) {
                // Redirect to export with report ID
                window.location.href = `handlers/quarterly_tax_export.php?action=export_saved&id=${reportId}&format=${format}&type=general`;
                }
                </script>
