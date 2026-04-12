<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
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

<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
<link href="../css/expenses/style.css" rel="stylesheet">
<?php include '../includes/header.php'; ?>


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

    <script src="../js/expense/file_input_handler.js"></script>
    <script src="../js/expense/button_protection.js"></script>
    <script src="../js/expense/expense_management.js"></script>
    <script src="../js/expense/event_handlers.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>