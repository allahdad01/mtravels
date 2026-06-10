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
    WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trial')
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
                        <!-- Page Header -->
                        <div class="expense-page-header">
                            <div class="header-left">
                                <h4><?= __('expense_management') ?></h4>
                            </div>
                            <div class="header-actions">
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

                        <!-- Date Filter -->
                        <div class="expense-filter">
                            <div class="filter-card">
                                <div class="filter-card-header" id="toggleExpenseFilter">
                                    <h6>
                                        <i class="feather icon-calendar"></i>
                                        <?= __('date_filter') ?>
                                        <span class="filter-badge" id="filterBadge"><?= __('filtered') ?></span>
                                    </h6>
                                    <i class="feather icon-chevron-down expand-icon" id="filterChevron"></i>
                                </div>
                                <div class="filter-card-body" id="expenseFilterBody" style="display: none;">
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
                                        <div class="filter-actions">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather icon-search"></i><?= __('apply_filter') ?>
                                            </button>
                                            <button type="button" id="resetExpenseFilter" class="btn btn-secondary">
                                                <i class="feather icon-refresh-ccw"></i><?= __('reset') ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Category Expenses -->
                        <div class="expense-categories">
                            <?php
                            foreach ($categories as $category) {
                                echo '<div class="category-card" data-category="' . $category['id'] . '">';
                                echo '<div class="category-card-header">';
                                echo '<div class="category-info">';
                                echo '<div class="category-icon icon-default"><i class="feather icon-folder"></i></div>';
                                echo '<h6>' . htmlspecialchars($category['name']) . '</h6>';
                                echo '</div>';
                                echo '<div class="category-meta">';
                                
                                // By default, show only current month expenses
                                $currentMonth = date('Y-m-01');
                                $nextMonth = date('Y-m-d', strtotime($currentMonth . ' +1 month'));
                                
                                $isFilterActive = isset($_GET['startDate']) && isset($_GET['endDate']);
                                
                                if ($isFilterActive) {
                                    $startDate = $_GET['startDate'];
                                    $endDate = $_GET['endDate'];
                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date <= ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $_SESSION['tenant_id'] ?? 1, $branch_id]);
                                } else {
                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date < ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                    $expenseStmt->execute([$category['id'], $currentMonth, $nextMonth, $_SESSION['tenant_id'] ?? 1, $branch_id]);
                                }
                                
                                $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
                                $count = count($expenses);
                                $currencyTotals = [];
                                foreach ($expenses as $exp) {
                                    $cur = $exp['currency'] ?? 'USD';
                                    $currencyTotals[$cur] = ($currencyTotals[$cur] ?? 0) + $exp['amount'];
                                }
                                
                                echo '<span class="expense-count">' . $count . ' ' . __('entries') . '</span>';
                                $totalParts = [];
                                foreach ($currencyTotals as $cur => $amt) {
                                    $totalParts[] = number_format($amt, 2) . ' ' . htmlspecialchars($cur);
                                }
                                echo '<span class="category-total">' . implode(' | ', $totalParts) . '</span>';
                                echo '<div class="category-card-actions">';
                                echo '<button class="btn-print print-category" data-id="' . $category['id'] . '" title="Print Category Report"><i class="feather icon-printer"></i></button>';
                                echo '<button class="btn-edit edit-category" data-id="' . $category['id'] . '" data-name="' . htmlspecialchars($category['name']) . '"><i class="feather icon-edit-2"></i></button>';
                                echo '<button class="btn-delete delete-category" data-id="' . $category['id'] . '"><i class="feather icon-trash-2"></i></button>';
                                echo '</div>';
                                echo '<i class="feather icon-chevron-down expand-icon"></i>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="expense-list">';
                                if ($count > 0) {
                                    echo '<div class="table-wrap">';
                                    echo '<table class="table">';
                                    echo '<thead><tr><th>'.__('date').'</th><th>'.__('description').'</th><th>'.__('amount').'</th><th>'.__('currency').'</th><th>'.__('actions').'</th></tr></thead>';
                                    echo '<tbody>';
                                    
                                    foreach ($expenses as $expense) {
                                        $createdAt = isset($expense['created_at']) ? $expense['created_at'] : $expense['date'];
                                        $isGlobal = !empty($expense['global_allocation_id']);
                                        $isBudgetAlloc = !empty($expense['allocation_id']);
                                        echo '<tr data-created="' . $createdAt . '">';
                                        echo '<td class="date-col">' . date('d/m/Y', strtotime($expense['date'])) . '</td>';
                                        echo '<td class="desc-col">' . htmlspecialchars($expense['description']) . '</td>';
                                        echo '<td class="amount-col">' . number_format($expense['amount'], 2);
                                        if ($isGlobal) {
                                            echo ' <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:50px;background:#eef5fb;color:#185FA5;border:1px solid #c6ddf0;vertical-align:middle;"><i class="feather icon-globe" style="font-size:9px;"></i>Global</span>';
                                        } elseif ($isBudgetAlloc) {
                                            echo ' <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:50px;background:#e8f8ef;color:#1a7a4a;border:1px solid #b8e6cc;vertical-align:middle;"><i class="feather icon-pie-chart" style="font-size:9px;"></i>Budget</span>';
                                        }
                                        echo '</td>';
                                        echo '<td class="ccy-col">' . ($expense['currency'] ?? 'USD') . '</td>';
                                        echo '<td class="actions-col">';
                                        if ($isGlobal) {
                                            echo '<span class="allocation-link">Managed from <a href="global_budget_allocation.php">Global Allocation</a></span>';
                                        } elseif ($isBudgetAlloc) {
                                            echo '<span class="allocation-link">Managed from <a href="budget_allocations.php">Budget Allocation</a></span>';
                                        } else {
                                            echo '<div class="btn-group-wrap">';
                                            echo '<button class="btn-action-edit edit-expense" data-id="' . $expense['id'] . '" data-category="' . $category['id'] . '" data-date="' . $expense['date'] . '" data-description="' . htmlspecialchars($expense['description']) . '" data-amount="' . $expense['amount'] . '" data-currency="' . ($expense['currency'] ?? 'USD') . '" data-main-account="' . ($expense['main_account_id'] ?? '') . '" title="Edit"><i class="feather icon-edit-2"></i></button>';
                                            echo '<a href="expense_detail.php?id=' . $expense['id'] . '" class="btn-action-view" title="View"><i class="feather icon-eye"></i></a>';
                                            echo '<button class="btn-action-delete delete-expense" data-id="' . $expense['id'] . '" title="Delete"><i class="feather icon-trash-2"></i></button>';
                                            echo '</div>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    
                                    echo '</tbody></table>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="empty-expenses">';
                                    echo '<i class="feather icon-inbox"></i>';
                                    echo '<p>' . __('no_expenses_found') . '</p>';
                                    echo '</div>';
                                }
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

<?php include '../modals/expense/category_modal.php'; ?>
<?php include '../modals/expense/expense_modal.php'; ?>
<?php include '../modals/expense/edit_expense_modal.php'; ?>

    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- Expense Management Scripts -->

    <script src="../js/expense/file_input_handler.js"></script>
    <script src="../js/expense/expense_management.js"></script>
    <script src="../js/expense/event_handlers.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>