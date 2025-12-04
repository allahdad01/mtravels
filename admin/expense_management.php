<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Get branch_id from session
$branch_id = $_SESSION['branch_id'];

// Fetch tenant's allowed features
$allowed_features = [];
$query = "
    SELECT p.features
    FROM tenant_subscriptions ts
    JOIN plans p ON ts.plan_id = p.id
    WHERE ts.tenant_id = ? AND ts.status = 'active'
";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['tenant_id'] ?? 1]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allowed_features = json_decode($row['features'], true) ?? [];
}

// Fetch main accounts with tenant and branch filtering
$mainAccountsQuery = "SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
$mainAccountsStmt = $pdo->prepare($mainAccountsQuery);
$mainAccountsStmt->execute([$_SESSION['tenant_id'] ?? 1, $branch_id]);
$internal = $mainAccountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories and expenses first with tenant and branch filtering
$categoriesQuery = "SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name";
$categoriesStmt = $pdo->prepare($categoriesQuery);
$categoriesStmt->execute([$_SESSION['tenant_id'] ?? 1, $branch_id]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    .card-body {
        min-height: 300px;
    }
    canvas {
        min-height: 250px;
    }
    .expense-list {
        display: none;
    }
    .category-header {
        cursor: pointer;
    }
    .card {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 8px;
    }
    .spinner {
        display: inline-block;
        animation: spinner 1s linear infinite;
    }
    @keyframes spinner {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .card-header {
        border-radius: 8px 8px 0 0;
    }

    .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
    }

    .form-control {
        border-left: none;
    }

    .form-control:focus {
        box-shadow: none;
        border-color: #ced4da;
    }

    .btn-group {
        width: 100%;
        justify-content: center;
    }

    .btn-group .btn {
        flex: 1;
        max-width: 150px;
    }

    .btn-outline-primary:hover {
        background-color: #4099ff;
        border-color: #4099ff;
    }

    .btn-outline-primary.active {
        background-color: #4099ff;
        border-color: #4099ff;
    }

    .text-muted {
        font-size: 0.875rem;
    }

    .mt-4 {
        margin-top: 2rem !important;
    }

    .totals-container {
        display: flex;
        justify-content: stretch;
        margin-bottom: 40px;
        gap: 20px;
        width: 100%;
    }

    .modern-ui {
        display: flex;
        justify-content: stretch;
        margin-bottom: 40px;
        gap: 20px;
        width: 100%;
    }

    .total-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        padding: 24px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.2);
        flex: 1;
        min-width: 0;
    }

    .total-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    }

    .total-card.income-card {
        background: linear-gradient(135deg, #ffffff 0%, #e8f5e8 100%);
        border-left: 4px solid #28a745;
    }

    .total-card.expense-card {
        background: linear-gradient(135deg, #ffffff 0%, #ffeaea 100%);
        border-left: 4px solid #dc3545;
    }

    .total-card.profit-loss-card {
        background: linear-gradient(135deg, #ffffff 0%, #fff3cd 100%);
        border-left: 4px solid #ffc107;
        transition: all 0.4s ease;
    }

    .total-card.profit-loss-card.profit {
        background: linear-gradient(135deg, #ffffff 0%, #e8f5e8 100%);
        border-left: 4px solid #28a745;
    }

    .total-card.profit-loss-card.loss {
        background: linear-gradient(135deg, #ffffff 0%, #ffeaea 100%);
        border-left: 4px solid #dc3545;
    }

    .card-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 24px;
        position: relative;
        z-index: 2;
    }

    .income-card .card-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 8px 24px rgba(40, 167, 69, 0.3);
    }

    .expense-card .card-icon {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
        box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
    }

    .profit-loss-card .card-icon {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
        box-shadow: 0 8px 24px rgba(255, 193, 7, 0.3);
        transition: all 0.4s ease;
    }

    .profit-loss-card.profit .card-icon {
        background: linear-gradient(135deg, #28a745, #20c997);
        box-shadow: 0 8px 24px rgba(40, 167, 69, 0.3);
    }

    .profit-loss-card.loss .card-icon {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
    }

    .card-content {
        position: relative;
        z-index: 2;
    }

    .card-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 16px;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
    }

    .amount-display {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .amount-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 80px;
    }

    .currency-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .amount-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        transition: all 0.3s ease;
        position: relative;
    }

    .income-card .amount-value {
        color: #28a745;
    }

    .expense-card .amount-value {
        color: #dc3545;
    }

    .profit-loss-card .amount-value {
        color: #ffc107;
    }

    .profit-loss-card.profit .amount-value {
        color: #28a745;
    }

    .profit-loss-card.loss .amount-value {
        color: #dc3545;
    }

    .card-accent {
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
        border-radius: 0 16px 0 100px;
        z-index: 1;
    }

    /* Animation for number updates */
    @keyframes numberPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .amount-value.updating {
        animation: numberPulse 0.6s ease-in-out;
    }

    /* Unique Export Button Styling */
    .export-section {
        display: flex;
        justify-content: center;
        margin-top: 30px;
        margin-bottom: 40px;
    }

    .export-button-container {
        position: relative;
        display: inline-block;
    }

    .export-button-container::before {
        content: '';
        position: absolute;
        top: -10px;
        left: -10px;
        right: -10px;
        bottom: -10px;
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 50%, #ffc107 100%);
        border-radius: 50px;
        z-index: -1;
        opacity: 0.3;
        transition: all 0.4s ease;
    }

    .export-button-container:hover::before {
        opacity: 0.6;
        transform: scale(1.05);
    }

    #exportComprehensiveReport {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 15px 40px;
        font-size: 1.1rem;
        font-weight: 600;
        color: white;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 8px 25px rgba(64, 153, 255, 0.3);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    #exportComprehensiveReport::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease;
    }

    #exportComprehensiveReport:hover::before {
        left: 100%;
    }

    #exportComprehensiveReport:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(64, 153, 255, 0.4);
        background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
    }

    #exportComprehensiveReport:active {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(64, 153, 255, 0.3);
    }

    #exportComprehensiveReport i {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    #exportComprehensiveReport:hover i {
        transform: scale(1.1);
    }

    /* Floating particles effect */
    .export-button-container::after {
        content: '';
        position: absolute;
        top: -5px;
        left: -5px;
        right: -5px;
        bottom: -5px;
        background: radial-gradient(circle, rgba(64, 153, 255, 0.1) 1px, transparent 1px);
        background-size: 20px 20px;
        border-radius: 50px;
        opacity: 0;
        transition: opacity 0.4s ease;
        z-index: -2;
    }

    .export-button-container:hover::after {
        opacity: 1;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .totals-container {
            flex-direction: column;
            gap: 15px;
        }

        .total-card {
            min-width: auto;
            max-width: none;
        }

        .amount-display {
            gap: 15px;
        }

        .amount-item {
            min-width: 70px;
        }

        .amount-value {
            font-size: 1.3rem;
        }

        .export-section {
            margin-top: 20px;
            margin-bottom: 30px;
        }

        #exportComprehensiveReport {
            padding: 12px 30px;
            font-size: 1rem;
        }
    }

    @media (max-width: 480px) {
        .total-card {
            padding: 20px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }

        .amount-display {
            flex-direction: column;
            gap: 10px;
        }

        .amount-item {
            flex-direction: row;
            justify-content: center;
            gap: 8px;
        }

        .currency-label {
            margin-bottom: 0;
        }

        #exportComprehensiveReport {
            padding: 10px 25px;
            font-size: 0.9rem;
        }
    }
</style>



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
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?= __('expense_management') ?></h5>
                                        <div class="float-right">
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#categoryModal">
                                                <i class="feather icon-plus"></i> <?= __('add_category') ?>
                                            </button>
                                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#expenseModal">
                                                <i class="feather icon-plus"></i> <?= __('add_expense') ?>
                                            </button>
                                            <a href="budget_allocations.php" class="btn btn-info">
                                                <i class="feather icon-credit-card"></i> <?= __('budget_allocations') ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Date Filter -->
                                        <div class="expense-filter mb-4">
                                            <div class="card shadow-sm">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <h6 class="m-0"><i class="feather icon-calendar mr-2"></i><?= __('date_filter') ?></h6>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="toggleExpenseFilter">
                                                        <i class="feather icon-chevron-down"></i>
                                                    </button>
                                                </div>
                                                <div class="card-body" id="expenseFilterBody">
                                                    <form id="expenseFilterForm">
                                                        <div class="row">
                                                            <div class="col-md-4 mb-3">
                                                                <label><?= __('from_date') ?></label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text">
                                                                            <i class="feather icon-calendar"></i>
                                                                        </span>
                                                                    </div>
                                                                    <input type="date" class="form-control" id="filterStartDate" name="filterStartDate">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <label><?= __('to_date') ?></label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend">
                                                                        <span class="input-group-text">
                                                                            <i class="feather icon-calendar"></i>
                                                                        </span>
                                                                    </div>
                                                                    <input type="date" class="form-control" id="filterEndDate" name="filterEndDate">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4 mb-3">
                                                                <label><?= __('quick_date_range') ?></label>
                                                                <select class="form-control" id="filterQuickDate">
                                                                    <option value=""><?= __('custom_range') ?></option>
                                                                    <option value="today"><?= __('today') ?></option>
                                                                    <option value="yesterday"><?= __('yesterday') ?></option>
                                                                    <option value="week"><?= __('this_week') ?></option>
                                                                    <option value="month"><?= __('this_month') ?></option>
                                                                    <option value="last_month"><?= __('last_month') ?></option>
                                                                    <option value="year"><?= __('this_year') ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="feather icon-search mr-1"></i><?= __('apply_filter') ?>
                                                                </button>
                                                                <button type="button" id="resetExpenseFilter" class="btn btn-secondary ml-2">
                                                                    <i class="feather icon-refresh-ccw mr-1"></i><?= __('reset') ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="expense-categories">
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<div class="category-section mb-4" data-category="' . $category['id'] . '">';
                                                echo '<div class="category-header d-flex justify-content-between align-items-center bg-light p-3 rounded">';
                                echo '<h6 class="mb-0">' . htmlspecialchars($category['name']) . '</h6>';
                                echo '<div class="category-actions">';
                                echo '<button class="btn btn-sm btn-success mr-2 print-category" data-id="' . $category['id'] . '" title="Print Category Report"><i class="feather icon-printer"></i></button>';
                                echo '<button class="btn btn-sm btn-info mr-2 edit-category" data-id="' . $category['id'] . '" data-name="' . htmlspecialchars($category['name']) . '"><i class="feather icon-edit"></i></button>';
                                echo '<button class="btn btn-sm btn-danger delete-category" data-id="' . $category['id'] . '"><i class="feather icon-trash-2"></i></button>';
                                echo '</div>';
                                                echo '</div>';
                                                
                                                // By default, show only current month expenses
                                                $currentMonth = date('Y-m-01'); // First day of current month
                                                $nextMonth = date('Y-m-d', strtotime($currentMonth . ' +1 month')); // First day of next month
                                                
                                                // Check if date filter is being applied from URL parameters
                                                $isFilterActive = isset($_GET['startDate']) && isset($_GET['endDate']);
                                                
                                                if ($isFilterActive) {
                                                    // If filter is active, use the filter dates
                                                    $startDate = $_GET['startDate'];
                                                    $endDate = $_GET['endDate'];
                                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date <= ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $_SESSION['tenant_id'] ?? 1, $branch_id]);
                                                } else {
                                                    // Default to current month only
                                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date < ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                                    $expenseStmt->execute([$category['id'], $currentMonth, $nextMonth, $_SESSION['tenant_id'] ?? 1, $branch_id]);
                                                }
                                                
                                                echo '<div class="expense-list mt-3" style="display: none;">';
                                                echo '<div class="table-responsive">';
                                                echo '<table class="table table-bordered">';
                                                echo '<thead><tr><th>'.__('date').'</th><th>'.__('description').'</th><th>'.__('amount').'</th><th>'.__('currency').'</th><th>'.__('actions').'</th></tr></thead>';
                                                echo '<tbody>';
                                                
                                                while($expense = $expenseStmt->fetch(PDO::FETCH_ASSOC)) {
                                                    // Add created_at data attribute for date filtering
                                                    $createdAt = isset($expense['created_at']) ? $expense['created_at'] : $expense['date'];
                                                    echo '<tr data-created="' . $createdAt . '">';
                                                    echo '<td>' . date('d/m/Y', strtotime($expense['date'])) . '</td>';
                                                    echo '<td style="max-width: 300px; word-wrap: break-word; white-space: normal;">' . htmlspecialchars($expense['description']) . '</td>';
                                                    echo '<td>' . number_format($expense['amount'], 2) . '</td>';
                                                    echo '<td>' . ($expense['currency'] ?? 'USD') . '</td>';
                                                    echo '<td>';
                                                    echo '<button class="btn btn-sm btn-info mr-2 edit-expense" data-id="' . $expense['id'] . '" data-category="' . $category['id'] . '" data-date="' . $expense['date'] . '" data-description="' . htmlspecialchars($expense['description']) . '" data-amount="' . $expense['amount'] . '" data-currency="' . ($expense['currency'] ?? 'USD') . '" data-main-account="' . ($expense['main_account_id'] ?? '') . '"><i class="feather icon-edit"></i></button>';
                                                    echo '<button class="btn btn-sm btn-danger delete-expense" data-id="' . $expense['id'] . '"><i class="feather icon-trash-2"></i></button>';
                                                    echo '<a href="expense_detail.php?id=' . $expense['id'] . '" class="btn btn-sm btn-primary"><i class="feather icon-eye"></i></a>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                                
                                                echo '</tbody></table>';
                                                echo '</div>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="feather icon-calendar mr-2"></i><?= __('date_range_filter') ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="dateRangeForm">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="text-muted font-weight-bold"><?= __('from_date') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">
                                                                    <i class="feather icon-calendar"></i>
                                                                </span>
                                                            </div>
                                                            <input type="date" class="form-control" id="startDate" name="startDate">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="text-muted font-weight-bold"><?= __('to_date') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text">
                                                                    <i class="feather icon-calendar"></i>
                                                                </span>
                                                            </div>
                                                            <input type="date" class="form-control" id="endDate" name="endDate">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mt-4">
                                                        <button type="submit" class="btn btn-primary btn-block">
                                                            <i class="feather icon-filter mr-2"></i><?= __('apply_filter') ?>
                                                        </button>
                                                        <button type="button" class="btn btn-secondary btn-block mt-2" id="resetFilter">
                                                            <i class="feather icon-refresh-ccw mr-2"></i><?= __('reset') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" data-range="today"><?= __('today') ?></button>
                                                        <button type="button" class="btn btn-outline-primary" data-range="week"><?= __('this_week') ?></button>
                                                        <button type="button" class="btn btn-outline-primary" data-range="month"><?= __('this_month') ?></button>
                                                        <button type="button" class="btn btn-outline-primary" data-range="quarter"><?= __('this_quarter') ?></button>
                                                        <button type="button" class="btn btn-outline-primary" data-range="year"><?= __('this_year') ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <!-- Income Graph -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><?= __('income_overview') ?></h5>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="exportChart('incomeChart', 'Income_Overview')">
                                                <i class="feather icon-download"></i> <?= __('export') ?>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="exportToExcel('income')">
                                                <i class="feather icon-file"></i> <?= __('excel') ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="incomeChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Expense Graph -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><?= __('expense_overview') ?></h5>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="exportChart('expenseChart', 'Expense_Overview')">
                                                <i class="feather icon-download"></i> <?= __('export') ?>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="exportToExcel('expenses')">
                                                <i class="feather icon-file"></i> <?= __('excel') ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expenseChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profit/Loss Graph -->
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><?= __('profit_loss_overview') ?></h5>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="exportChart('profitLossChart', 'Profit_Loss_Overview')">
                                                <i class="feather icon-download"></i> <?= __('export') ?>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="exportToExcel('profitLoss')">
                                                <i class="feather icon-file"></i> <?= __('excel') ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="profitLossChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="totals-container modern-ui">
                                <div class="total-card income-card">
                                    <div class="card-icon">
                                        <i class="feather icon-trending-up"></i>
                                    </div>
                                    <div class="card-content">
                                        <h5 class="card-title"><?= __('total_income') ?></h5>
                                        <div class="amount-display">
                                            <div class="amount-item usd">
                                                <span class="currency-label">USD</span>
                                                <span class="amount-value" id="totalIncomeUSD">0</span>
                                            </div>
                                            <div class="amount-item afs">
                                                <span class="currency-label">AFS</span>
                                                <span class="amount-value" id="totalIncomeAFS">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-accent"></div>
                                </div>
                                <div class="total-card expense-card">
                                    <div class="card-icon">
                                        <i class="feather icon-trending-down"></i>
                                    </div>
                                    <div class="card-content">
                                        <h5 class="card-title"><?= __('total_expenses') ?></h5>
                                        <div class="amount-display">
                                            <div class="amount-item usd">
                                                <span class="currency-label">USD</span>
                                                <span class="amount-value" id="totalExpensesUSD">0</span>
                                            </div>
                                            <div class="amount-item afs">
                                                <span class="currency-label">AFS</span>
                                                <span class="amount-value" id="totalExpensesAFS">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-accent"></div>
                                </div>
                                <div class="total-card profit-loss-card" id="profitLossCard">
                                    <div class="card-icon">
                                        <i class="feather icon-bar-chart-2" id="profitLossIcon"></i>
                                    </div>
                                    <div class="card-content">
                                        <h5 class="card-title" id="profitLossTitle"><?= __('profit_loss') ?></h5>
                                        <div class="amount-display">
                                            <div class="amount-item usd">
                                                <span class="currency-label">USD</span>
                                                <span class="amount-value" id="totalProfitLossUSD">0</span>
                                            </div>
                                            <div class="amount-item afs">
                                                <span class="currency-label">AFS</span>
                                                <span class="amount-value" id="totalProfitLossAFS">0</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-accent"></div>
                                </div>
                            </div>
                            
                            <!-- Add Comprehensive Export Button -->
                            <div class="export-section">
                                <div class="export-button-container">
                                    <button id="exportComprehensiveReport">
                                        <i class="feather icon-file-text"></i>
                                        <span><?= __('export_financial_report') ?></span>
                                        <i class="feather icon-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/expense/category_modal.php'; ?>
<?php include '../modals/expense/expense_modal.php'; ?>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- Expense Management Scripts -->
    <script>
        // Set allowedFeatures before loading scripts
        var allowedFeatures = <?= json_encode($allowed_features); ?>;
    </script>
    <script src="../js/expense/file_input_handler.js"></script>
    <script src="../js/expense/button_protection.js"></script>
    <script src="../js/expense/expense_management.js"></script>
    <script src="../js/expense/event_handlers.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>