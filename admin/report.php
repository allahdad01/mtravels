<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Database connection
require_once('../includes/db.php');

// Fetch tenant's active subscription and plan features
$tenant_id = $_SESSION['tenant_id'] ?? null; // Ensure tenant_id is set in session
$allowed_features = [];

if ($tenant_id) {
    $query = "
        SELECT p.features
        FROM tenant_subscriptions ts
        JOIN plans p ON ts.plan_id = p.id
        WHERE ts.tenant_id = ? AND ts.status = 'active'
        ORDER BY ts.start_date DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $allowed_features = json_decode($result['features'], true) ?? [];
    }
}
?>

<?php include '../includes/header.php'; ?>


<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
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

                                      
                                        <div class="card custom-card shadow-lg">
                                            <div class="card-header overflow-hidden">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <div class="icon-wrapper me-3">
                                                            <i class="feather icon-file-text text-white"></i>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-0 text-white fw-bold">
                                                                <?= __('generate_report') ?>
                                                            </h5>
                                                            <small class="text-white-50 mb-0">
                                                                <?= __('select_criteria_and_generate_reports') ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="header-decoration">
                                                        <i class="feather icon-bar-chart-2 text-white opacity-25"></i>
                                                    </div>
                                                </div>
                                                <div class="header-pattern"></div>
                                            </div>
                                            <div class="card-body p-4 p-lg-5">
                                                <form id="reportForm">
                                                    <!-- Basic Configuration Section -->
                                                    <div class="form-section mb-4">
                                                        <div class="section-header mb-3">
                                                            <h6 class="text-primary mb-0 fw-bold">
                                                                <i class="feather icon-settings me-2"></i>
                                                                <?= __('basic_configuration') ?>
                                                            </h6>
                                                            <div class="section-divider"></div>
                                                        </div>
                                                        <div class="row g-3">
                                                            <!-- Report Type Selection -->
                                                            <div class="col-lg-6">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-bar-chart me-1"></i>
                                                                        <?= __('report_type') ?>
                                                                    </label>
                                                                    <select id="reportType" class="form-select form-select-lg" onchange="loadOptions()">
                                                                        <option value=""><?= __('select_report_type') ?></option>
                                                                        <option value="general">📊 <?= __('general') ?> (<?= __('all_types') ?>)</option>
                                                                        <option value="supplier">🏢 <?= __('supplier') ?></option>
                                                                        <option value="main_account">💰 <?= __('main_account') ?></option>
                                                                        <option value="client">👥 <?= __('client') ?></option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <!-- Date Range Selection -->
                                                            <div class="col-lg-6">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-calendar me-1"></i>
                                                                        <?= __('date_range') ?>
                                                                    </label>
                                                                    <div class="position-relative">
                                                                        <input type="text" id="dateRange" class="form-control form-control-lg" readonly placeholder="<?= __('select_date_range') ?>">
                                                                        <input type="hidden" id="startDate">
                                                                        <input type="hidden" id="endDate">
                                                                        
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Entity Selection Section -->
                                                    <div class="form-section mb-4" id="entitySection" style="display: none;">
                                                        <div class="section-header mb-3">
                                                            <h6 class="text-primary mb-0 fw-bold">
                                                                <i class="feather icon-users me-2"></i>
                                                                <?= __('entity_selection') ?>
                                                            </h6>
                                                            <div class="section-divider"></div>
                                                        </div>
                                                        <div class="row g-3">
                                                            <!-- Dynamic Dropdown for Selecting Entity -->
                                                            <div class="col-lg-12" id="entitySelection" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-building me-1"></i>
                                                                        <?= __('select_entity') ?>
                                                                    </label>
                                                                    <select id="entity" class="form-select form-select-lg"></select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Report Configuration Section -->
                                                    <div class="form-section mb-4" id="reportConfigSection" style="display: none;">
                                                        <div class="section-header mb-3">
                                                            <h6 class="text-primary mb-0 fw-bold">
                                                                <i class="feather icon-filter me-2"></i>
                                                                <?= __('report_configuration') ?>
                                                            </h6>
                                                            <div class="section-divider"></div>
                                                        </div>
                                                        <div class="row g-3">
                                                            <!-- Report Category Selection -->
                                                            <div class="col-lg-6" id="reportCategorySelection" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-tag me-1"></i>
                                                                        <?= __('report_category') ?>
                                                                    </label>
                                                                    <select id="reportCategory" class="form-select form-select-lg">
                                                                        <option value="ticket">🎫 <?= __('ticket') ?></option>
                                                                        <option value="ticket_reservation">🎫 <?= __('ticket_reservation') ?></option>
                                                                        <option value="refund_ticket">↩️ <?= __('refund_ticket') ?></option>
                                                                        <option value="date_change_ticket">📅 <?= __('date_change_ticket') ?></option>
                                                                        <option value="visa">🛂 <?= __('visa') ?></option>
                                                                        <option value="umrah">🕌 <?= __('umrah') ?></option>
                                                                        <option value="hotel">🏨 <?= __('hotel') ?></option>
                                                                        <option value="expense">💸 <?= __('expense') ?></option>
                                                                        <option value="creditor">💼 <?= __('creditor') ?></option>
                                                                        <option value="debtor">📝 <?= __('debtor') ?></option>
                                                                        <option value="additional_payment">💵 <?= __('additional_payment') ?></option>
                                                                        <option value="statement">📊 <?= __('statement') ?></option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <!-- Statement Currency Selection -->
                                                            <div class="col-lg-6" id="statementFields" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-dollar-sign me-1"></i>
                                                                        <?= __('currency') ?>
                                                                    </label>
                                                                    <select id="statementCurrency" class="form-select form-select-lg">
                                                                        <option value="USD">💵 <?= __('usd') ?></option>
                                                                        <option value="AFS">🪙 <?= __('afs') ?></option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <!-- Expense Categories Selection -->
                                                            <div class="col-lg-6" id="expenseCategoryFields" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-list me-1"></i>
                                                                        <?= __('expense_category') ?>
                                                                    </label>
                                                                    <select id="expenseCategory" class="form-select form-select-lg">
                                                                        <option value="all">🔍 <?= __('all_categories') ?></option>
                                                                        <!-- Categories will be loaded dynamically from the database -->
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <!-- Umrah Family Selection -->
                                                            <div class="col-lg-6" id="umrahFamilyFields" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-users me-1"></i>
                                                                        <?= __('family_type') ?>
                                                                    </label>
                                                                    <select id="umrahFamilyType" class="form-select form-select-lg" onchange="toggleFamilySelection()">
                                                                        <option value="all">🕌 <?= __('all_families') ?></option>
                                                                        <option value="specific">👨‍👩‍👧‍👦 <?= __('specific_family') ?></option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <!-- Specific Family Selection -->
                                                            <div class="col-lg-12" id="specificFamilySelection" style="display: none;">
                                                                <div class="form-group custom-form-group">
                                                                    <label class="form-label fw-semibold text-muted mb-2">
                                                                        <i class="feather icon-user-check me-1"></i>
                                                                        <?= __('select_family') ?>
                                                                    </label>
                                                                    <select id="specificFamily" class="form-select form-select-lg">
                                                                        <!-- Families will be loaded dynamically -->
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Action Section -->
                                                    <div class="form-section">
                                                        <div class="d-flex justify-content-end align-items-center">
                                                            <div class="action-info me-3">
                                                                <small class="text-muted">
                                                                    <i class="feather icon-info me-1"></i>
                                                                    <?= __('fill_required_fields_and_click_generate') ?>
                                                                </small>
                                                            </div>
                                                            <button type="button" class="btn btn-primary btn-lg px-4 py-3 custom-btn" onclick="filterResults()">
                                                                <i class="feather icon-filter me-2"></i>
                                                                <span class="fw-bold"><?= __('generate_report') ?></span>
                                                                <div class="btn-hover-effect"></div>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Results Section -->
                                        <div id="resultsSection" class="mt-5" style="display: none;">
                                            <div class="form-section">
                                                <div class="section-header mb-4">
                                                    <h6 class="text-primary mb-0 fw-bold">
                                                        <i class="feather icon-bar-chart-2 me-2"></i>
                                                        <?= __('report_results') ?>
                                                    </h6>
                                                    <div class="section-divider"></div>
                                                </div>

                                                

                                                <!-- Export Section -->
                                                <div id="exportSection" class="mt-4 pt-4 border-top" style="display: none;">
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                                        <div class="export-info mb-3 mb-lg-0">
                                                            <h6 class="text-success mb-1">
                                                                <i class="feather icon-check-circle me-2"></i>
                                                                <?= __('report_generated_successfully') ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?= __('choose_export_format_below') ?>
                                                            </small>
                                                        </div>
                                                        <div class="export-buttons d-flex gap-2 flex-wrap">
                                                            <button type="button" class="btn btn-outline-danger btn-lg px-3 export-btn" onclick="exportReport('pdf')">
                                                                <i class="feather icon-file-text me-2"></i>
                                                                <span class="d-none d-sm-inline"><?= __('pdf') ?></span>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-success btn-lg px-3 export-btn" onclick="exportReport('excel')">
                                                                <i class="feather icon-file me-2"></i>
                                                                <span class="d-none d-sm-inline"><?= __('excel') ?></span>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary btn-lg px-3 export-btn" onclick="exportReport('word')">
                                                                <i class="feather icon-file me-2"></i>
                                                                <span class="d-none d-sm-inline"><?= __('word') ?></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
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

    /* Enhanced Card Header Styling */
    .card-header.bg-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border-radius: 15px 15px 0 0 !important;
        padding: 2rem 2rem !important;
        position: relative;
        overflow: hidden;
    }

    .card-header.bg-gradient::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
    }

    .icon-wrapper {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
    }

    .icon-wrapper i {
        font-size: 1.5rem;
    }

    .header-decoration {
        opacity: 0.6;
        transform: rotate(15deg);
    }

    .header-decoration i {
        font-size: 3rem;
    }

    /* Form Sections */
    .form-section {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .form-section:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transform: translateY(-1px);
    }

    .section-header {
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 0.75rem;
    }

    .section-header h6 {
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-divider {
        height: 3px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 2px;
        margin-top: 0.5rem;
        width: 60px;
    }

    /* Enhanced Form Labels */
    .form-label {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }

    .form-label i {
        font-size: 0.8rem;
        margin-right: 0.25rem;
    }

    /* Enhanced Form Controls */
    .form-select-lg, .form-control-lg {
        border-radius: 10px !important;
        border: 2px solid #e0e6ed !important;
        font-size: 1rem !important;
        padding: 0.875rem 1.25rem !important;
        transition: all 0.3s ease !important;
        background-color: #fff !important;
    }

    .form-select-lg:focus, .form-control-lg:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15) !important;
        transform: translateY(-1px);
    }

    /* Input Group Styling */
    .position-relative .form-control {
        padding-right: 3rem;
    }

    .position-relative .feather {
        color: #6c757d;
        transition: color 0.3s ease;
    }

    .position-relative .form-control:focus + .feather {
        color: #667eea;
    }

    /* Enhanced Button */
    .custom-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: none !important;
        border-radius: 12px !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        position: relative !important;
        overflow: hidden !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
    }

    .custom-btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
    }

    .btn-hover-effect {
        position: absolute !important;
        top: 0 !important;
        left: -100% !important;
        width: 100% !important;
        height: 100% !important;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent) !important;
        transition: left 0.5s ease !important;
    }

    .custom-btn:hover .btn-hover-effect {
        left: 100% !important;
    }

    /* Action Section */
    .action-info {
        background: #e8f4f8;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        border-left: 4px solid #667eea;
    }

    .action-info small {
        font-size: 0.85rem;
        color: #495057;
    }

    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection {
        height: 60px !important;
        border-radius: 10px !important;
        border: 2px solid #e0e6ed !important;
        background-color: #fff !important;
        padding: 0.875rem 1.25rem !important;
        font-size: 1rem !important;
        transition: all 0.3s ease !important;
    }

    .select2-container--bootstrap-5 .select2-selection:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15) !important;
        background-color: #fff !important;
        transform: translateY(-1px);
    }

    .select2-container--bootstrap-5 .select2-selection__rendered {
        color: #495057 !important;
        line-height: 1.5 !important;
        padding: 0 !important;
    }

    .select2-container--bootstrap-5 .select2-selection__placeholder {
        color: #6c757d !important;
    }

    .select2-container--bootstrap-5 .select2-selection__arrow {
        height: 100% !important;
        right: 15px !important;
        top: 0 !important;
    }

    .select2-container--bootstrap-5 .select2-selection__clear {
        color: #6c757d !important;
        cursor: pointer !important;
        margin-right: 10px !important;
    }

    .select2-dropdown {
        border-radius: 10px !important;
        border: 2px solid #e0e6ed !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }

    .select2-container--bootstrap-5 .select2-results__option {
        padding: 12px 15px !important;
        font-size: 1rem !important;
        color: #495057 !important;
        transition: all 0.2s ease !important;
    }

    .select2-container--bootstrap-5 .select2-results__option--highlighted {
        background-color: #667eea !important;
        color: white !important;
    }

    .select2-container--bootstrap-5 .select2-results__option--selected {
        background-color: #e8f4f8 !important;
        color: #495057 !important;
    }

    .select2-search--dropdown .select2-search__field {
        border-radius: 8px !important;
        border: 1px solid #dee2e6 !important;
        padding: 10px 12px !important;
        font-size: 1rem !important;
        margin: 10px !important;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
    }

    /* Responsive Enhancements */
    @media (max-width: 768px) {
        .card-header.bg-gradient {
            padding: 1.5rem 1rem !important;
        }

        .form-section {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .action-info {
            display: none;
        }

        .custom-btn {
            width: 100%;
            margin-top: 1rem;
        }
    }

    /* Loading Animation */
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }

    .form-section.loading {
        animation: pulse 1.5s ease-in-out infinite;
    }

    /* Success Animation */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-section.success {
        animation: slideInUp 0.5s ease-out;
    }

    /* Export Section Styling */
    .export-info h6 {
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .export-buttons .btn {
        border-radius: 8px !important;
        border-width: 2px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        min-width: 100px;
    }

    .export-buttons .btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    }

    .export-btn i {
        font-size: 1.1rem;
    }

    /* Enhanced Alerts */
    .alert {
        border-radius: 10px !important;
        border: none !important;
        font-size: 0.95rem !important;
    }

    .alert i {
        font-size: 1rem;
    }

    /* Loading Spinner Enhancement */
    .spinner-border {
        border-width: 0.3em !important;
    }

    /* Form Section Transitions */
    .form-section {
        transition: all 0.3s ease;
    }

    /* Focus States */
    .form-select:focus,
    .form-control:focus {
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15) !important;
    }

    /* Custom Scrollbar for Select2 */
    .select2-container--bootstrap-5 .select2-results__options {
        max-height: 200px;
    }

    /* Mobile Optimizations */
    @media (max-width: 576px) {
        .export-buttons {
            justify-content: center !important;
        }

        .export-buttons .btn {
            flex: 1;
            min-width: auto;
        }

        .action-info {
            text-align: center;
            margin-bottom: 1rem;
        }
    }
</style>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Add these scripts at the bottom of the file, before closing body tag -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <!-- Add meta tags for JavaScript variables -->
    <meta name="tenant-id" content="<?= $tenant_id ?>">
    <meta name="branch-id" content="<?= $branch_id ?>">
    <meta name="allowed-features" content='<?= json_encode($allowed_features) ?>'>

    <!-- Report JavaScript -->
    <script src="../js/report/report.js"></script>
  

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>

