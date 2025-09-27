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


// Fetch main accounts with tenant filtering
$mainAccountsQuery = "SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ?";
$mainAccountsStmt = $pdo->prepare($mainAccountsQuery);
$mainAccountsStmt->execute([$_SESSION['tenant_id'] ?? 1]);
$internal = $mainAccountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories and expenses first with tenant filtering
$categoriesQuery = "SELECT * FROM expense_categories WHERE tenant_id = ? ORDER BY name";
$categoriesStmt = $pdo->prepare($categoriesQuery);
$categoriesStmt->execute([$_SESSION['tenant_id'] ?? 1]);
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
                                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date <= ? AND tenant_id = ? ORDER BY date DESC";
                                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $_SESSION['tenant_id'] ?? 1]);
                                                } else {
                                                    // Default to current month only
                                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date < ? AND tenant_id = ? ORDER BY date DESC";
                                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                                    $expenseStmt->execute([$category['id'], $currentMonth, $nextMonth, $_SESSION['tenant_id'] ?? 1]);
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

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_category') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="categoryForm">
                <div class="modal-body">
                    <input type="hidden" id="categoryId" name="categoryId">
                    <div class="form-group">
                        <label><?= __('category_name') ?></label>
                        <input type="text" class="form-control" id="categoryName" name="categoryName" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_expense') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="expenseForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="expenseId" name="expenseId">
                    <div class="form-group">
                        <label><?= __('category') ?></label>
                        <select class="form-control" id="expenseCategory" name="expenseCategory" required>
                            <?php foreach($categories as $category): ?>
                                <option value="<?php echo h($category['id']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('date') ?></label>
                        <div class="row">
                            <div class="col-md-12">
                                <input type="date" class="form-control" id="expenseDate" name="expenseDate" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <input type="text" class="form-control" id="expenseDescription" name="expenseDescription" required>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="expenseAmount" name="expenseAmount" required>
                            </div>
                        </div>
                        <!-- Main Account -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('main_account') ?></label>
                                <select class="form-control" id="expenseMainAccount" name="expenseMainAccount" required>
                                    <option value=""><?= __('select_main_account') ?></option>
                                    <?php foreach ($internal as $int): ?>
                                    <option value="<?= $int['id'] ?>"><?= $int['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Currency -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="expenseCurrency" name="expenseCurrency" required>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label><?= __('budget_allocation') ?></label>
                                <select class="form-control" id="expenseAllocation" name="expenseAllocation">
                                    <option value=""><?= __('select_budget_allocation') ?></option>
                                    <?php 
                                    // Fetch available allocations with tenant filtering
                                    $allocationsQuery = "
                                        SELECT ba.id, ba.remaining_amount, ba.currency,
                                               ec.name as category_name,
                                               ma.name as account_name
                                        FROM budget_allocations ba
                                        JOIN expense_categories ec ON ba.category_id = ec.id
                                        JOIN main_account ma ON ba.main_account_id = ma.id
                                        WHERE ba.tenant_id = ? AND ec.tenant_id = ? AND ma.tenant_id = ?
                                        ORDER BY ec.name, ba.allocation_date DESC
                                    ";
                                    $allocationsStmt = $pdo->prepare($allocationsQuery);
                                    $tenantId = $_SESSION['tenant_id'] ?? 1;
                                    $allocationsStmt->execute([$tenantId, $tenantId, $tenantId]);
                                    $allocations = $allocationsStmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach($allocations as $allocation): 
                                    ?>
                                    <option value="<?= $allocation['id'] ?>" 
                                            data-currency="<?= $allocation['currency'] ?>" 
                                            data-category="<?= $allocation['category_name'] ?>"
                                            data-remaining="<?= $allocation['remaining_amount'] ?>">
                                        <?= htmlspecialchars($allocation['category_name']) ?> - 
                                        <?= number_format($allocation['remaining_amount'], 2) ?> <?= $allocation['currency'] ?> 
                                        (<?= htmlspecialchars($allocation['account_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted"><?= __('if_selected_expense_will_deduct_from_this_allocation_instead_of_the_main_account') ?></small>
                            </div>
                        </div>
                    </div>
                    <!-- Receipt Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('receipt_number') ?> (<?= __('optional') ?>)</label>
                                <input type="text" class="form-control" id="expenseReceiptNumber" name="expenseReceiptNumber">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('receipt_file') ?> (<?= __('optional') ?>)</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="expenseReceiptFile" name="expenseReceiptFile">
                                    <label class="custom-file-label" for="expenseReceiptFile"><?= __('choose_file') ?></label>
                                </div>
                                <small class="form-text text-muted"><?= __('supported_formats') ?>: PDF, JPG, PNG</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>



                                    </form>
                                </div>
                            </div>



                               <!-- Profile Modal -->
                               <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


    <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                            alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Update custom file input label with selected filename
$(document).on('change', '.custom-file-input', function() {
    let fileName = $(this).val().split('\\').pop();
    if (fileName) {
        $(this).next('.custom-file-label').html(fileName);
    } else {
        $(this).next('.custom-file-label').html('<?= __('choose_file') ?>');
    }
});
</script>


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
<script>
// Wait for document and jQuery to be ready
$(document).ready(function() {
    // Check if we have filter parameters in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlStartDate = urlParams.get('startDate');
    const urlEndDate = urlParams.get('endDate');
    
    if (urlStartDate && urlEndDate) {
        // If we have filter dates in URL, use those
        $('#filterStartDate').val(urlStartDate);
        $('#filterEndDate').val(urlEndDate);
    } else {
        // Otherwise set default date range to current month
        const currentDate = new Date();
        const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        
        // Format dates properly for inputs
        $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
        $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
    }
    
    // If filter is active, show a reset button at the top
    if (urlStartDate && urlEndDate) {
        // Add a visible indicator that a filter is active
        $('.card-header h5').append(' <span class="badge badge-primary">Filtered</span>');
    }
    
    // Expense Filter Section Toggle
    $('#toggleExpenseFilter').on('click', function() {
        $('#expenseFilterBody').slideToggle();
        const icon = $(this).find('i');
        if (icon.hasClass('icon-chevron-down')) {
            icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');
        } else {
            icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
        }
    });
    
    // Expense Filter Form Submission
    $('#expenseFilterForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get filter dates
        const startDate = $('#filterStartDate').val();
        const endDate = $('#filterEndDate').val();
        
        if (startDate && endDate) {
            // Reload the page with date parameters to fetch filtered data from server
            window.location.href = window.location.pathname + '?startDate=' + startDate + '&endDate=' + endDate;
        } else {
            alert('Please select both start and end dates');
        }
    });
    
    // Quick date range selection
    $('#filterQuickDate').on('change', function() {
        const range = $(this).val();
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'yesterday':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            case 'month':
                // First day of current month
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'last_month':
                // First day of last month
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                // Last day of last month
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Current day
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                break;
            default:
                // Keep current values
                return;
        }
        
        // Format dates as YYYY-MM-DD
        $('#filterStartDate').val(formatDateISO(startDate));
        $('#filterEndDate').val(formatDateISO(endDate));
    });
    
    // Reset Date Filter - go back to current month view
    $('#resetExpenseFilter').on('click', function() {
        // If we have URL parameters, reload without them to show default view
        if (window.location.search) {
            window.location.href = window.location.pathname;
        } else {
            // Just reset the form fields
            $('#expenseFilterForm')[0].reset();
            
            // Set default date range to current month
            const currentDate = new Date();
            const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            
            $('#filterStartDate').val(formatDateISO(firstDayOfMonth));
            $('#filterEndDate').val(formatDateISO(lastDayOfMonth));
        }
    });
    
    // Print category button click handler
    $('.print-category').on('click', function() {
        const categoryId = $(this).data('id');
        // Open the PDF in a new window/tab
        window.open('generate_category_pdf.php?category_id=' + categoryId, '_blank');
    });

    // Category form submission
    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        const categoryId = $('#categoryId').val();
        const categoryName = $('#categoryName').val();
        
        $.ajax({
            url: 'expense_actions.php',
            type: 'POST',
            data: {
                action: 'save_category',
                categoryId: categoryId,
                categoryName: categoryName
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#categoryModal').modal('hide');
                    alert('<?= __('category_saved_successfully') ?>');
                    location.reload();
                } else {
                    alert('<?= __('error') ?>: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_saving_the_category') ?>');
            }
        });
    });
    
    // Expense form submission
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        
        // Create FormData object to handle file uploads
        const formData = new FormData(this);
        
        // Add all form fields to FormData
        formData.append('action', 'save_expense');
        
        // Get allocation info if present
        const selectedAllocation = $('#expenseAllocation').find('option:selected');
        if (selectedAllocation.val()) {
            const allocationCurrency = selectedAllocation.data('currency');
            // Ensure the currency matches the allocation
            formData.set('expenseCurrency', allocationCurrency);
            console.log('Form submission - ensuring currency matches allocation:', allocationCurrency);
        }
        
        // Re-enable any disabled fields to ensure their values are included in the form
        $('#expenseCurrency').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseMainAccount').prop('disabled', false);
        
        // Show loading indicator
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="feather icon-loader spinner"></i> Processing...');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'expense_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false, // Required for FormData
            processData: false, // Required for FormData
            success: function(response) {
                if (response.success) {
                    $('#expenseModal').modal('hide');
                    alert('<?= __('expense_saved_successfully') ?>');
                    location.reload();
                } else {
                    alert('<?= __('error') ?>: ' + response.message);
                    // Reset button
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_saving_the_expense') ?>');
                // Reset button
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        });
    });
    
    // Edit category button click handler
    $('.edit-category').on('click', function() {
        const categoryId = $(this).data('id');
        const categoryName = $(this).data('name');
        
        $('#categoryId').val(categoryId);
        $('#categoryName').val(categoryName);
        $('#categoryModal').modal('show');
    });
    
    // Delete category button click handler
    $('.delete-category').on('click', function() {
        if (confirm('<?= __('are_you_sure_you_want_to_delete_this_category') ?>')) {
            const categoryId = $(this).data('id');
            
            $.ajax({
                url: 'expense_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_category',
                    categoryId: categoryId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('<?= __('category_deleted_successfully') ?>');
                        location.reload();
                    } else {
                        alert('<?= __('error') ?>: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('<?= __('an_error_occurred_while_deleting_the_category') ?>');
                }
            });
        }
    });
    
    // Edit expense button click handler
    $('.edit-expense').on('click', function() {
        const expenseId = $(this).data('id');
        const categoryId = $(this).data('category');
        const date = $(this).data('date');
        const description = $(this).data('description');
        const amount = $(this).data('amount');
        const currency = $(this).data('currency');
        const mainAccountId = $(this).data('main-account');
        
        $('#expenseId').val(expenseId);
        $('#expenseCategory').val(categoryId);
        $('#expenseDate').val(date);
        $('#expenseDescription').val(description);
        $('#expenseAmount').val(amount);
        $('#expenseCurrency').val(currency);
        $('#expenseMainAccount').val(mainAccountId);
        
        // Reset receipt fields
        $('#expenseReceiptNumber').val('');
        $('.custom-file-label').text('<?= __('choose_file') ?>');
        
        // Fetch additional expense details like receipt number and file
        $.ajax({
            url: 'expense_actions.php',
            type: 'POST',
            data: {
                action: 'get_expense_details',
                expenseId: expenseId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.expense) {
                    // Set main account if available
                    if (response.expense.main_account_id) {
                        $('#expenseMainAccount').val(response.expense.main_account_id);
                    }
                    
                    // Set allocation if available
                    if (response.expense.allocation_id) {
                        $('#expenseAllocation').val(response.expense.allocation_id);
                        // Trigger the change event to update related fields
                        $('#expenseAllocation').trigger('change');
                    }
                    
                    // Set receipt number if available
                    if (response.expense.receipt_number) {
                        $('#expenseReceiptNumber').val(response.expense.receipt_number);
                    }
                    
                    // Display existing receipt file information if available
                    if (response.expense.receipt_file) {
                        $('.custom-file-label').text(response.expense.receipt_file);
                        // Remove any existing view button first
                        $('#receiptFileViewBtn').remove();
                        $('<div id="receiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + response.expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> <?= __('view_receipt') ?></a></div>')
                            .insertAfter('#expenseReceiptFile').parent();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching expense details:', error);
            }
        });
        
        $('#expenseModal').modal('show');
    });
    
    // Delete expense button click handler
    $('.delete-expense').on('click', function() {
        if (confirm('<?= __('are_you_sure_you_want_to_delete_this_expense') ?>')) {
            const expenseId = $(this).data('id');
            
            $.ajax({
                url: 'expense_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_expense',
                    expenseId: expenseId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('<?= __('expense_deleted_successfully') ?>');
                        location.reload();
                    } else {
                        alert('<?= __('error') ?>: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('<?= __('an_error_occurred_while_deleting_the_expense') ?>');
                }
            });
        }
    });

    // Function to format date as YYYY-MM-DD
    function formatDateISO(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Add leading zero if needed
        const day = String(date.getDate()).padStart(2, '0'); // Add leading zero if needed
        return `${year}-${month}-${day}`;
    }
    
    // Set default date range (current month)
    const today = new Date();
    // First day of current month (always the 1st)
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    // Last day of current month
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    // Format dates properly
    $('#startDate').val(formatDateISO(firstDay));
    $('#endDate').val(formatDateISO(lastDay));
    
    console.log('Initial date range:', {
        startDate: formatDateISO(firstDay),
        endDate: formatDateISO(lastDay),
        startDay: firstDay.getDate(),
        endDay: lastDay.getDate()
    });
    
    // Debug function to validate date format
    function validateDateRange() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        // Check if the start date is the first day of the month
        const startDateObj = new Date(startDate);
        const isFirstDay = startDateObj.getDate() === 1;
        
        console.log('Current date range:', {
            startDate,
            endDate,
            isFirstDayOfMonth: isFirstDay,
            startDateObj
        });
        
        return isFirstDay;
    }
    
    // Validate initial date range
    validateDateRange();

    // Date range form submission
    $('#dateRangeForm').on('submit', function(e) {
        e.preventDefault();
        // Validate date range before loading data
        validateDateRange();
        loadFinancialData();
    });

    // Quick date range buttons
    $('.btn-group .btn').click(function() {
        const range = $(this).data('range');
        const today = new Date();
        let startDate, endDate;

        switch(range) {
            case 'today':
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                endDate = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
                break;
            case 'week':
                // Get first day of week (Sunday)
                startDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                startDate.setDate(startDate.getDate() - startDate.getDay());
                endDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
                endDate.setDate(endDate.getDate() + 6);
                break;
            case 'month':
                // First day of current month - always the 1st
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                // Last day of current month
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                console.log('Month date range:', {
                    startDate: formatDateISO(startDate),
                    endDate: formatDateISO(endDate),
                    startDay: startDate.getDate(),
                    endDay: endDate.getDate()
                });
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                // First day of current quarter
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                // Last day of current quarter
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                break;
            case 'year':
                // First day of current year
                startDate = new Date(today.getFullYear(), 0, 1);
                // Last day of current year
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        // Use our custom formatting function
        $('#startDate').val(formatDateISO(startDate));
        $('#endDate').val(formatDateISO(endDate));
        
        console.log('Selected date range:', {
            range: range,
            startDate: formatDateISO(startDate),
            endDate: formatDateISO(endDate),
            startDay: startDate.getDate(),
            endDay: endDate.getDate()
        });
        
        // Make the current selection button active
        $('.btn-group .btn').removeClass('active');
        $(this).addClass('active');
        
        // Submit the form to update data
        $('#dateRangeForm').submit();
    });

    // Highlight active range button
    function updateActiveButton() {
        $('.btn-group .btn').removeClass('active');
        // Add logic to determine which button should be active based on current date range
    }

    // Update active button when date inputs change
    $('#startDate, #endDate').change(updateActiveButton);
    
    // Reset form when opening the Add Expense modal via the Add Expense button
    $('[data-target="#expenseModal"]').on('click', function() {
        $('#expenseForm')[0].reset();
        $('#expenseId').val('');
        $('#expenseMainAccount').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseCurrency').prop('disabled', false);
        $('.custom-file-label').text('<?= __('choose_file') ?>');
        $('#receiptFileViewBtn').remove();
    });

    // Handle allocation selection
    $('#expenseAllocation').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            // Set currency to match allocation currency
            const currency = selectedOption.data('currency');
            $('#expenseCurrency').val(currency);
            $('#expenseCurrency').prop('disabled', true);
            
            // Set max amount to remaining amount
            const remaining = selectedOption.data('remaining');
            $('#expenseAmount').attr('max', remaining);
            
            // If category is selected, update the category selection
            const category = selectedOption.data('category');
            const categoryOption = $('#expenseCategory option').filter(function() {
                return $(this).text().trim() === category;
            });
            
            if (categoryOption.length) {
                $('#expenseCategory').val(categoryOption.val());
                $('#expenseCategory').prop('disabled', true);
            }
            
            // When using allocation, the main account should be disabled
            $('#expenseMainAccount').val('');
            $('#expenseMainAccount').prop('disabled', true);

            console.log('Allocation selected. Currency set to:', currency);
        } else {
            // Reset fields
            $('#expenseCurrency').prop('disabled', false);
            $('#expenseCategory').prop('disabled', false);
            $('#expenseMainAccount').prop('disabled', false);
            $('#expenseAmount').removeAttr('max');
        }
    });

    // Make sure we reset everything properly when the modal is hidden
    $('#expenseModal').on('hidden.bs.modal', function() {
        // Re-enable all fields that might have been disabled
        $('#expenseCurrency').prop('disabled', false);
        $('#expenseCategory').prop('disabled', false);
        $('#expenseMainAccount').prop('disabled', false);
    });
    
    // Check URL parameters for allocation references
    const searchParams = new URLSearchParams(window.location.search);
    const allocationId = searchParams.get('allocation_id');
    const currency = searchParams.get('currency');
    const categoryId = searchParams.get('category_id');
    
    if (allocationId) {
        console.log('Allocation ID from URL:', allocationId);
        
        // First, set the expense form to defaults
        $('#expenseForm')[0].reset();
        $('#expenseId').val('');
        
        // Then set the allocation dropdown
        $('#expenseAllocation').val(allocationId);
        
        // Manually set fields based on allocation data
        const selectedOption = $('#expenseAllocation').find('option:selected');
        if (selectedOption.val()) {
            // Get currency from the allocation data
            const allocationCurrency = selectedOption.data('currency');
            console.log('Setting currency from URL allocation:', allocationCurrency);
            
            // Set and lock currency field
            $('#expenseCurrency').val(allocationCurrency);
            $('#expenseCurrency').prop('disabled', true);
            
            // Set and lock category field
            const category = selectedOption.data('category');
            const categoryOption = $('#expenseCategory option').filter(function() {
                return $(this).text().trim() === category;
            });
            
            if (categoryOption.length) {
                $('#expenseCategory').val(categoryOption.val());
                $('#expenseCategory').prop('disabled', true);
            }
            
            // Disable main account field
            $('#expenseMainAccount').val('');
            $('#expenseMainAccount').prop('disabled', true);
        }
        
        // Open the expense modal automatically
        $('#expenseModal').modal('show');
    }
    
    // Check for edit_expense parameter
    const editExpenseId = searchParams.get('edit_expense');
    if (editExpenseId) {
        // Fetch expense details and open the modal
        $.ajax({
            url: 'expense_actions.php',
            type: 'POST',
            data: {
                action: 'get_expense',
                expenseId: editExpenseId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const expense = response.expense;
                    
                    // Fill the form with expense data
                    $('#expenseId').val(expense.id);
                    $('#expenseCategory').val(expense.category_id);
                    
                    // Just use the date portion
                    const datetime = new Date(expense.date);
                    const dateString = datetime.toISOString().split('T')[0];
                    
                    $('#expenseDate').val(dateString);
                    $('#expenseDescription').val(expense.description);
                    $('#expenseAmount').val(expense.amount);
                    
                    // Set currency but don't trigger change events yet
                    $('#expenseCurrency').val(expense.currency);
                    
                    // Ensure we display the main account correctly
                    if (expense.main_account_id) {
                        $('#expenseMainAccount').val(expense.main_account_id);
                    }
                    
                    // Handle receipt details
                    if (expense.receipt) {
                        $('#expenseReceiptNumber').val(expense.receipt);
                    }
                    
                    if (expense.receipt_file) {
                        $('.custom-file-label').text(expense.receipt_file);
                        // Remove any existing view button first
                        $('#receiptFileViewBtn').remove();
                        $('<div id="receiptFileViewBtn" class="mt-2"><a href="../uploads/expense_receipt/' + expense.receipt_file + '" target="_blank" class="btn btn-sm btn-info"><i class="feather icon-eye"></i> <?= __('view_receipt') ?></a></div>')
                            .insertAfter('#expenseReceiptFile').parent();
                    }
                    
                    // Handle allocation last as it may disable other fields
                    if (expense.allocation_id) {
                        // First select the allocation
                        $('#expenseAllocation').val(expense.allocation_id);
                        
                        // Then manually update the fields based on the allocation data
                        const selectedOption = $('#expenseAllocation').find('option:selected');
                        if (selectedOption.val()) {
                            // Get the currency from the allocation data
                            const currency = selectedOption.data('currency');
                            console.log('Setting currency from allocation:', currency);
                            
                            // Ensure currency matches the allocation
                            $('#expenseCurrency').val(currency);
                            $('#expenseCurrency').prop('disabled', true);
                            
                            // Disable the category field
                            $('#expenseCategory').prop('disabled', true);
                            
                            // Disable main account as we're using allocation
                            $('#expenseMainAccount').val('');
                            $('#expenseMainAccount').prop('disabled', true);
                        }
                    }
                    
                    // Update modal title
                    $('.modal-title').text('Edit Expense');
                    
                    // Open the modal
                    $('#expenseModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_fetching_expense_details') ?>');
            }
        });
    }

    // Reset filter
    $('#resetFilter').click(function() {
        // Reset to current month from 1st day to last day
        const today = new Date();
        // First day of current month (always the 1st)
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        // Last day of current month
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        // Use our custom formatting function
        $('#startDate').val(formatDateISO(firstDay));
        $('#endDate').val(formatDateISO(lastDay));
        
        console.log('Reset date range:', {
            startDate: formatDateISO(firstDay),
            endDate: formatDateISO(lastDay),
            startDay: firstDay.getDate(),
            endDay: lastDay.getDate()
        });
        
        // Clear any active button state
        $('.btn-group .btn').removeClass('active');
        
        // Load data with these date settings
        loadFinancialData();
    });

    // Initial load
    loadFinancialData();

    // Make sure category headers expand when clicked
    $(document).on('click', '.category-header', function() {
        $(this).closest('.category-section').find('.expense-list').slideToggle();
    });
    
    // Attach click handler to the comprehensive export button
    $('#exportComprehensiveReport').click(function() {
        exportComprehensiveReport();
    });
});

// Declare chart variables at a higher scope
let incomeChart, expenseChart, profitLossChart;

function destroyExistingCharts() {
    if (incomeChart) {
        incomeChart.destroy();
        incomeChart = null;
    }
    if (expenseChart) {
        expenseChart.destroy();
        expenseChart = null;
    }
    if (profitLossChart) {
        profitLossChart.destroy();
        profitLossChart = null;
    }
}

function createIncomeChart(data) {
    const ctx = document.getElementById('incomeChart');
    if (!ctx) {
        console.error('Income chart canvas not found');
        return;
    }

    incomeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['<?= __('tickets') ?>', '<?= __('ticket_weights') ?>', '<?= __('reservations') ?>', '<?= __('refunds') ?>', '<?= __('date_changes') ?>', '<?= __('visa') ?>', '<?= __('umrah') ?>', '<?= __('hotel') ?>', '<?= __('additional_payments') ?>'],
            datasets: [
                {
                    label: '<?= __('total_income') ?> (USD)',
                    data: [
                        data.tickets.USD || 0,
                        data.ticket_weights.USD || 0,
                        data.reservations.USD || 0,
                        data.refunds.USD || 0,
                        data.dateChanges.USD || 0,
                        data.visa.USD || 0,
                        data.umrah.USD || 0,
                        data.hotel.USD || 0,
                        data.additionalPayments.USD || 0
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: '<?= __('total_income') ?> (AFS)',
                    data: [
                        data.tickets.AFS || 0,
                        data.ticket_weights.AFS || 0,
                        data.reservations.AFS || 0,
                        data.refunds.AFS || 0,
                        data.dateChanges.AFS || 0,
                        data.visa.AFS || 0,
                        data.umrah.AFS || 0,
                        data.hotel.AFS || 0,
                        data.additionalPayments.AFS || 0
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '<?= __('total_income') ?>'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            return `${label}: ${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });
}

function createExpenseChart(data) {
    const ctx = document.getElementById('expenseChart');
    if (!ctx) {
        console.error('Expense chart canvas not found');
        return;
    }

    const labels = [];
    const usdData = [];
    const afsData = [];

    data.USD.categories.forEach((category, index) => {
        labels.push(category);
        usdData.push(data.USD.amounts[index]);
        afsData.push(0);
    });

    data.AFS.categories.forEach((category, index) => {
        labels.push(category + ' (AFS)');
        usdData.push(0);
        afsData.push(data.AFS.amounts[index]);
    });

    expenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '<?= __('total_expenses') ?> (USD)',
                    data: usdData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: '<?= __('total_expenses') ?> (AFS)',
                    data: afsData,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '<?= __('total_expenses') ?>'
                    }
                }
            }
        }
    });
}

function createProfitLossChart(data) {
    const ctx = document.getElementById('profitLossChart');
    if (!ctx) {
        console.error('Profit/Loss chart canvas not found');
        return;
    }

    profitLossChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['<?= __('profit') ?>', '<?= __('loss') ?>'],
            datasets: [
                {
                    label: '<?= __('total') ?> (USD)',
                    data: [data.USD.profit, -data.USD.loss],
                    backgroundColor: ['rgba(75, 192, 192, 0.6)', 'rgba(255, 99, 132, 0.6)'],
                    borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 99, 132, 1)'],
                    borderWidth: 1
                },
                {
                    label: '<?= __('total') ?> (AFS)',
                    data: [data.AFS.profit, -data.AFS.loss],
                    backgroundColor: ['rgba(54, 162, 235, 0.6)', 'rgba(255, 159, 64, 0.6)'],
                    borderColor: ['rgba(54, 162, 235, 1)', 'rgba(255, 159, 64, 1)'],
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '<?= __('total_profit_loss') ?>'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            const category = context.label;
                            return `${label} ${category}: ${Math.abs(value).toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });
}

// Function to export chart as image
function exportChart(chartId, filename) {
    const canvas = document.getElementById(chartId);
    const link = document.createElement('a');
    link.download = `${filename}_${formatDate(new Date())}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// Function to export comprehensive financial report
function exportComprehensiveReport() {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    $.ajax({
        url: 'export_comprehensive_report.php',
        type: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        success: function(response) {
            if(response.success) {
                // Convert base64 to blob
                const binary = atob(response.file);
                const array = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    array[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([array], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});

                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Financial_Report_${startDate}_to_${endDate}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('<?= __('error') ?>: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('<?= __('error') ?>: ' + response.message);
        }
    });
}

// Function to export data to Excel
function exportToExcel(type) {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    let url = 'export_financial_data.php';
    let data = {
        type: type,
        startDate: startDate,
        endDate: endDate
    };

    // If exporting expenses, use a different endpoint
    if (type === 'expenses') {
        url = 'export_expenses.php';
        data = {
            startDate: startDate,
            endDate: endDate
        };
    }
    
    $.ajax({
        url: url,
        type: 'GET',
        data: data,
        success: function(response) {
            if(response.success) {
                // Convert base64 to blob
                const binary = atob(response.file);
                const array = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    array[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([array], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});

                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${type}_report_${formatDate(new Date())}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('<?= __('error') ?>: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('<?= __('error') ?>: ' + response.message);
        }
    });
}

// Helper function to format date for filenames
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

// Function to filter expenses based on created_at date
function filterExpenses() {
    // Get the selected date range from the filter
    const filterStartDate = $('#filterStartDate').val() ? new Date($('#filterStartDate').val() + 'T00:00:00') : null;
    const filterEndDate = $('#filterEndDate').val() ? new Date($('#filterEndDate').val() + 'T23:59:59') : null;
    
    console.log('Filtering expenses with date range:', {
        filterStartDate: filterStartDate ? filterStartDate.toISOString() : 'none',
        filterEndDate: filterEndDate ? filterEndDate.toISOString() : 'none'
    });
    
    // Make sure all categories are visible
    $('.category-section').show();
    $('.expense-list').show();
    
    // Remove any previous "no matches" messages
    $('.no-matches-row').remove();
    
    // No date filter selected, show all expenses
    if (!filterStartDate && !filterEndDate) {
        $('.expense-list tbody tr').show();
        return;
    }
    
    // Initially show all rows, then hide non-matching ones
    $('.expense-list tbody tr:not(.no-matches-row)').show();
    
    // Filter each row based on created_at date
    $('.expense-list tbody tr').each(function() {
        const $row = $(this);
        
        // Get the created_at date from data attribute
        const createdAtStr = $row.data('created');
        
        if (!createdAtStr) {
            console.error('No created_at date found');
            $row.show(); // Show row with no date
            return;
        }
        
        console.log('Row created_at:', createdAtStr);
        
        try {
            // Parse the created_at date
            const rowDate = new Date(createdAtStr);
            
            console.log('Comparing dates:', {
                rowCreatedAt: rowDate.toISOString(),
                filterStartDate: filterStartDate ? filterStartDate.toISOString() : 'none',
                filterEndDate: filterEndDate ? filterEndDate.toISOString() : 'none'
            });
            
            // Check date range against created_at date
            const dateMatch = (!filterStartDate || rowDate >= filterStartDate) && (!filterEndDate || rowDate <= filterEndDate);
            
            // Show/hide based on date match
            if (dateMatch) {
                $row.show();
            } else {
                $row.hide();
            }
        } catch (e) {
            console.error('Error parsing created_at date:', e);
            $row.show(); // Show row with invalid date format
        }
    });
    
    // Always show all categories, even if they have no matching expenses
    $('.category-section').each(function() {
        const $section = $(this);
        const $visibleRows = $section.find('tbody tr:visible');
        
        console.log('Category visible rows:', {
            category: $section.find('.category-header h6').text(),
            visibleRows: $visibleRows.length
        });
        
        // Always show the category, but show a message if no matching expenses
        if ($visibleRows.length === 0) {
            // Get the expense list table body
            const $tbody = $section.find('.expense-list tbody');
            
            // Check if we already added a "no matches" message
            if ($tbody.find('.no-matches-row').length === 0) {
                // Add a row indicating no matching expenses
                $tbody.append('<tr class="no-matches-row text-muted"><td colspan="5" class="text-center"><?= __("no_expenses_match_the_selected_date_range") ?></td></tr>');
            }
        } else {
            // Remove any "no matches" message if we have visible rows
            $section.find('.no-matches-row').remove();
        }
    });
}
function convertDateFormat(dateStr) {
    const parts = dateStr.split('/');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return dateStr;
}

function loadFinancialData() {
    // Get dates from the main date range picker, not the expense filter
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();

    $.ajax({
        url: 'get_financial_data.php',
        type: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        dataType: 'json',
        success: function(response) {
            console.log('Financial data received:', response); // Debug log
            if(response.success) {
                destroyExistingCharts(); // Destroy existing charts

                // Calculate totals for USD
                const totalIncomeUSD = response.income.tickets.USD + response.income.ticket_weights.USD + response.income.reservations.USD + response.income.refunds.USD + 
                    response.income.dateChanges.USD + response.income.visa.USD + 
                    response.income.umrah.USD + response.income.hotel.USD + 
                    response.income.additionalPayments.USD;
                const totalExpensesUSD = response.expenses.USD.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossUSD = response.profitLoss.USD.profit - response.profitLoss.USD.loss;

                // Calculate totals for AFS
                const totalIncomeAFS = response.income.tickets.AFS + response.income.ticket_weights.AFS + response.income.reservations.AFS + response.income.refunds.AFS + 
                    response.income.dateChanges.AFS + response.income.visa.AFS + 
                    response.income.umrah.AFS + response.income.hotel.AFS + 
                    response.income.additionalPayments.AFS;
                const totalExpensesAFS = response.expenses.AFS.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossAFS = response.profitLoss.AFS.profit - response.profitLoss.AFS.loss;

                // Update HTML elements for USD with animation
                updateAmountWithAnimation('totalIncomeUSD', totalIncomeUSD);
                updateAmountWithAnimation('totalExpensesUSD', totalExpensesUSD);
                updateAmountWithAnimation('totalProfitLossUSD', totalProfitLossUSD);

                // Update HTML elements for AFS with animation
                updateAmountWithAnimation('totalIncomeAFS', totalIncomeAFS);
                updateAmountWithAnimation('totalExpensesAFS', totalExpensesAFS);
                updateAmountWithAnimation('totalProfitLossAFS', totalProfitLossAFS);

                // Update profit/loss card styling based on values
                updateProfitLossCardStyling(totalProfitLossUSD, totalProfitLossAFS);

                // Create charts
                createIncomeChart(response.income);
                createExpenseChart(response.expenses);
                createProfitLossChart(response.profitLoss);
            } else {
                console.error('Error loading financial data:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Ajax error:', error);
        }
    });
}

// Function to update amount with animation
function updateAmountWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (element) {
        // Add updating class for animation
        element.classList.add('updating');

        // Update the value
        element.textContent = newValue.toLocaleString();

        // Remove animation class after animation completes
        setTimeout(() => {
            element.classList.remove('updating');
        }, 600);
    }
}

// Function to update profit/loss card styling based on values
function updateProfitLossCardStyling(usdValue, afsValue) {
    const profitLossCard = document.getElementById('profitLossCard');
    const profitLossIcon = document.getElementById('profitLossIcon');
    const profitLossTitle = document.getElementById('profitLossTitle');

    if (profitLossCard && profitLossIcon && profitLossTitle) {
        // Determine if it's profit or loss based on USD value (primary currency)
        const isProfit = usdValue >= 0;
        const isLoss = usdValue < 0;

        // Remove existing classes
        profitLossCard.classList.remove('profit', 'loss');

        if (isProfit) {
            profitLossCard.classList.add('profit');
            profitLossIcon.className = 'feather icon-trending-up';
            profitLossTitle.textContent = usdValue === 0 ? 'Break Even' : 'Profit';
        } else {
            profitLossCard.classList.add('loss');
            profitLossIcon.className = 'feather icon-trending-down';
            profitLossTitle.textContent = 'Loss';
        }

        // Update USD value (show negative for loss)
        const usdElement = document.getElementById('totalProfitLossUSD');
        if (usdElement) {
            usdElement.textContent = usdValue.toLocaleString();
        }

        // Update AFS value (show negative for loss)
        const afsElement = document.getElementById('totalProfitLossAFS');
        if (afsElement) {
            afsElement.textContent = afsValue.toLocaleString();
        }
    }
}
</script>