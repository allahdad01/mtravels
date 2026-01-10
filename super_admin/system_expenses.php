<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Fetch all expense categories
$categoriesQuery = "SELECT id, name, description FROM system_expense_categories ORDER BY name";
$categoriesStmt = $pdo->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-t');
$categoryFilter = $_GET['category'] ?? null;

// Build query
$expenseQuery = "SELECT se.*, sec.name as category_name, u.name as created_by_name 
                 FROM system_expenses se
                 LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
                 LEFT JOIN users u ON se.created_by = u.id
                 WHERE se.date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($categoryFilter) {
    $expenseQuery .= " AND se.category_id = ?";
    $params[] = $categoryFilter;
}

$expenseQuery .= " ORDER BY se.date DESC";
$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute($params);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary by currency
$totalAmount = 0;
$totalAmountByUSD = 0;
$totalAmountByAFS = 0;
$categoryTotals = [];
$currencies = [];

foreach ($expenses as $expense) {
    $totalAmount += $expense['amount'];
    
    // Track by currency
    if (!isset($currencies[$expense['currency']])) {
        $currencies[$expense['currency']] = 0;
    }
    $currencies[$expense['currency']] += $expense['amount'];
    
    if ($expense['currency'] === 'USD') {
        $totalAmountByUSD += $expense['amount'];
    } else {
        $totalAmountByAFS += $expense['amount'];
    }
    
    $catId = $expense['category_id'];
    if (!isset($categoryTotals[$catId])) {
        $categoryTotals[$catId] = ['name' => $expense['category_name'], 'total' => 0, 'count' => 0, 'currencies' => []];
    }
    $categoryTotals[$catId]['total'] += $expense['amount'];
    $categoryTotals[$catId]['count']++;
    
    if (!isset($categoryTotals[$catId]['currencies'][$expense['currency']])) {
        $categoryTotals[$catId]['currencies'][$expense['currency']] = 0;
    }
    $categoryTotals[$catId]['currencies'][$expense['currency']] += $expense['amount'];
}

// Calculate averages per currency
$usdCount = 0;
$afsCount = 0;
foreach ($expenses as $expense) {
    if ($expense['currency'] === 'USD') {
        $usdCount++;
    } else {
        $afsCount++;
    }
}
$avgUSD = $usdCount > 0 ? $totalAmountByUSD / $usdCount : 0;
$avgAFS = $afsCount > 0 ? $totalAmountByAFS / $afsCount : 0;
?>

<?php include '../includes/header_super_admin.php'; ?>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <!-- Page Header with Gradient -->
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-credit-card mr-2"></i><?php echo __('system_expenses'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_system_expenses'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#addExpenseModal">
                                                    <i class="feather icon-plus mr-1"></i><?php echo __('add_expense'); ?>
                                                </button>
                                                <button type="button" class="btn btn-outline-light btn-sm" data-toggle="modal" data-target="#manageCategories">
                                                    <i class="feather icon-settings mr-1"></i><?php echo __('manage_categories'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filter Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-filter mr-2"></i><?php echo __('filters'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <form id="filterForm" method="GET" class="form-row">
                                                    <div class="col-md-3">
                                                        <label class="small font-weight-bold"><?php echo __('from_date'); ?></label>
                                                        <input type="date" name="startDate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($startDate); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="small font-weight-bold"><?php echo __('to_date'); ?></label>
                                                        <input type="date" name="endDate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($endDate); ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="small font-weight-bold"><?php echo __('category'); ?></label>
                                                        <select name="category" class="form-control form-control-sm">
                                                            <option value=""><?php echo __('all_categories'); ?></option>
                                                            <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($cat['name']); ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary btn-sm mr-2">
                                                            <i class="feather icon-search mr-1"></i><?php echo __('apply_filter'); ?>
                                                        </button>
                                                        <a href="system_expenses.php" class="btn btn-secondary btn-sm">
                                                            <i class="feather icon-refresh-ccw mr-1"></i><?php echo __('reset'); ?>
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted mb-3"><?php echo __('total_expenses'); ?></h6>
                                                <div class="h4 font-weight-bold text-primary mb-2">
                                                    <?php if ($totalAmountByUSD > 0): ?>
                                                    $ <?php echo number_format($totalAmountByUSD, 2); ?>
                                                    <?php endif; ?>
                                                    <?php if ($totalAmountByAFS > 0): ?>
                                                    <br> ؋ <?php echo number_format($totalAmountByAFS, 2); ?>
                                                    <?php endif; ?>
                                                    <?php if ($totalAmountByUSD == 0 && $totalAmountByAFS == 0): ?>
                                                    -
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge badge-primary badge-pill"><?php echo count($expenses); ?> <?php echo __('expenses'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted mb-3"><?php echo __('number_of_expenses'); ?></h6>
                                                <div class="h2 font-weight-bold text-success"><?php echo count($expenses); ?></div>
                                                <span class="badge badge-success badge-pill"><?php echo $usdCount; ?> USD | <?php echo $afsCount; ?> AFS</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted mb-3"><?php echo __('average_expense'); ?></h6>
                                                <div class="h4 font-weight-bold text-info mb-2">
                                                    <?php if ($avgUSD > 0): ?>
                                                    $ <?php echo number_format($avgUSD, 2); ?>
                                                    <?php endif; ?>
                                                    <?php if ($avgAFS > 0): ?>
                                                    <br> ؋ <?php echo number_format($avgAFS, 2); ?>
                                                    <?php endif; ?>
                                                    <?php if ($avgUSD == 0 && $avgAFS == 0): ?>
                                                    -
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge badge-info badge-pill"><?php echo __('per_expense'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted mb-3"><?php echo __('categories_used'); ?></h6>
                                                <div class="h2 font-weight-bold text-warning"><?php echo count($categoryTotals); ?></div>
                                                <span class="badge badge-warning badge-pill"><?php echo __('categories'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Expenses Table -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-list mr-2"></i><?php echo __('expense_list'); ?>
                                                    <span class="badge badge-primary badge-pill ml-2"><?php echo count($expenses); ?></span>
                                                </h5>
                                            </div>
                                            <div class="card-body table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><i class="feather icon-calendar mr-1"></i><?php echo __('date'); ?></th>
                                                            <th><i class="feather icon-tag mr-1"></i><?php echo __('category'); ?></th>
                                                            <th><i class="feather icon-file-text mr-1"></i><?php echo __('description'); ?></th>
                                                            <th class="text-right"><i class="feather icon-dollar-sign mr-1"></i><?php echo __('amount'); ?></th>
                                                            <th><i class="feather icon-credit-card mr-1"></i><?php echo __('currency'); ?></th>
                                                            <th><i class="feather icon-user mr-1"></i><?php echo __('created_by'); ?></th>
                                                            <th><i class="feather icon-settings mr-1"></i><?php echo __('actions'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($expenses)): ?>
                                                            <?php foreach ($expenses as $expense): ?>
                                                            <tr>
                                                                <td class="font-weight-medium"><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                                                <td>
                                                                    <span class="badge badge-primary badge-pill">
                                                                        <?php echo htmlspecialchars($expense['category_name']); ?>
                                                                    </span>
                                                                </td>
                                                                <td style="max-width: 250px; white-space: normal;">
                                                                    <?php echo htmlspecialchars(substr($expense['description'], 0, 50)); ?>
                                                                    <?php echo strlen($expense['description']) > 50 ? '...' : ''; ?>
                                                                </td>
                                                                <td class="text-right font-weight-bold text-success">
                                                                    <?php echo formatCurrency($expense['amount'], $expense['currency']); ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?php echo $expense['currency'] === 'USD' ? 'badge-info' : 'badge-success'; ?> badge-pill">
                                                                        <?php echo htmlspecialchars($expense['currency']); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-muted"><?php echo htmlspecialchars($expense['created_by_name'] ?? 'System'); ?></td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-info edit-expense" data-id="<?php echo $expense['id']; ?>" data-toggle="modal" data-target="#editExpenseModal" title="<?php echo __('edit'); ?>">
                                                                        <i class="feather icon-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-danger delete-expense" data-id="<?php echo $expense['id']; ?>" title="<?php echo __('delete'); ?>">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="7" class="text-center text-muted py-5">
                                                                    <i class="feather icon-inbox mr-2"></i><?php echo __('no_expenses_found'); ?>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Category Summary -->
                                <?php if (!empty($categoryTotals)): ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-bar-chart-2 mr-2"></i><?php echo __('expense_by_category'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <?php foreach ($categoryTotals as $catId => $catData): ?>
                                                    <?php 
                                                    $catTotal = 0;
                                                    foreach ($catData['currencies'] as $amount) {
                                                        $catTotal += $amount;
                                                    }
                                                    ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="border rounded p-3 h-100">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <h6 class="font-weight-bold mb-0"><?php echo htmlspecialchars($catData['name']); ?></h6>
                                                                <span class="badge badge-primary badge-pill"><?php echo $catData['count']; ?></span>
                                                            </div>
                                                            <div class="h5 font-weight-bold text-success mb-2">
                                                                <?php if (count($catData['currencies']) === 1): ?>
                                                                    <?php foreach ($catData['currencies'] as $curr => $amount): ?>
                                                                        <?php echo formatCurrency($catTotal, $curr); ?>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    Mixed
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo $totalAmount > 0 ? number_format(($catTotal / $totalAmount) * 100, 1) : 0; ?>% of total
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title"><i class="feather icon-plus mr-2"></i><?php echo __('add_expense'); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="addExpenseForm" method="POST" action="handlers/create_system_expense.php" enctype="multipart/form-data" onsubmit="handleExpenseSubmit(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body p-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('category'); ?> *</label>
                                    <select name="category_id" class="form-control" required>
                                        <option value=""><?php echo __('select_category'); ?></option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('date'); ?> *</label>
                                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('description'); ?> *</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('amount'); ?> *</label>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('currency'); ?></label>
                                    <select name="currency" class="form-control">
                                        <option value="USD">USD</option>
                                        <option value="AFS">AFS</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('payment_method'); ?></label>
                                    <input type="text" name="payment_method" class="form-control" placeholder="e.g., Bank Transfer, Cash">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('reference_number'); ?></label>
                                    <input type="text" name="reference_number" class="form-control" placeholder="Invoice, Check, Transaction ID">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('receipt_file'); ?></label>
                            <input type="file" name="receipt_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple="false">
                            <small class="text-muted">Max 5MB, PDF/JPG/PNG only</small>
                        </div>
                        <div class="form-group mb-0">
                            <label><?php echo __('notes'); ?></label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes (optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-2"></i><?php echo __('save_expense'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal (loaded via AJAX) -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div id="editExpenseContent"><!-- Loaded via AJAX --></div>
            </div>
        </div>
    </div>

    <!-- Manage Categories Modal -->
    <div class="modal fade" id="manageCategories" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title"><i class="feather icon-settings mr-2"></i><?php echo __('expense_categories'); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-6">
                    <button type="button" class="btn btn-success mb-4" data-toggle="modal" data-target="#addCategoryModal" data-dismiss="modal">
                        <i class="feather icon-plus mr-2"></i><?php echo __('add_category'); ?>
                    </button>
                    <div class="row">
                        <?php foreach ($categories as $cat): ?>
                        <div class="col-md-6 mb-3">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="font-weight-bold mb-1"><?php echo htmlspecialchars($cat['name']); ?></h6>
                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-info edit-category" data-id="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars($cat['name']); ?>" data-toggle="modal" data-target="#editCategoryModal" data-dismiss="modal" title="<?php echo __('edit'); ?>">
                                            <i class="feather icon-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-category" data-id="<?php echo $cat['id']; ?>" title="<?php echo __('delete'); ?>">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title"><?php echo __('add_category'); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="addCategoryForm" method="POST" action="handlers/create_system_expense_category.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body p-6">
                        <div class="form-group">
                            <label><?php echo __('category_name'); ?> *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group mb-0">
                            <label><?php echo __('description'); ?></label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-success"><i class="feather icon-save mr-2"></i><?php echo __('save_category'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal (loaded via AJAX) -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div id="editCategoryContent"><!-- Loaded via AJAX --></div>
            </div>
        </div>
    </div>

    <style>
    /* Page Header Styles */
    .page-header.card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    /* Card Styles */
    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0 !important;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .card-header.bg-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .card-header.bg-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    }

    /* Badge Styles */
    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-primary {
        background-color: #667eea;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    /* Table Styles */
    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    /* Form Control Styles */
    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    /* Button Styles */
    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-success:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .btn-danger {
        border-radius: 5px;
    }

    .btn-info {
        border-radius: 5px;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 15px;
        border: none;
    }

    .modal-header {
        border-radius: 14px 14px 0 0;
    }

    /* Alert Styles */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    /* Text Colors */
    .text-primary { color: #667eea !important; }
    .text-success { color: #28a745 !important; }
    .text-info { color: #17a2b8 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-muted { color: #6c757d !important; }

    /* Font Sizes */
    .h2 { font-size: 2.5rem; }
    .h4 { font-size: 1.5rem; }
    .h5 { font-size: 1.25rem; }
    .h6 { font-size: 1rem; }
    .small { font-size: 0.875em; }

    /* Border utilities */
    .border { border: 1px solid #dee2e6 !important; }
    </style>

    <script>
    // Handle form submission
    function handleExpenseSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('<?php echo __("expense_created_successfully"); ?>');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }

    // Delete expense
    document.querySelectorAll('.delete-expense').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('<?php echo __('are_you_sure'); ?>')) {
                const id = this.dataset.id;
                fetch('handlers/delete_system_expense.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        alert('<?php echo __("deleted_successfully"); ?>');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                }).catch(err => alert('Error: ' + err.message));
            }
        });
    });

    // Delete category
    document.querySelectorAll('.delete-category').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('<?php echo __('are_you_sure'); ?>')) {
                const id = this.dataset.id;
                fetch('handlers/delete_system_expense_category.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    });

    // Edit expense
    document.querySelectorAll('.edit-expense').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch('handlers/get_system_expense.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    const html = `
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title"><i class="feather icon-edit mr-2"></i><?php echo __('edit_expense'); ?></h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <form method="POST" action="handlers/update_system_expense.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="modal-body p-6">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('category'); ?> *</label>
                                            <select name="category_id" class="form-control" required>
                                                <?php foreach ($categories as $cat): ?>
                                                <option value="${data.category_id}">${data.category_name}</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('date'); ?> *</label>
                                            <input type="date" name="date" class="form-control" value="${data.date}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><?php echo __('description'); ?> *</label>
                                    <textarea name="description" class="form-control" rows="3" required>${data.description}</textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('amount'); ?> *</label>
                                            <input type="number" name="amount" class="form-control" step="0.01" value="${data.amount}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('currency'); ?></label>
                                            <select name="currency" class="form-control">
                                                <option value="USD" ${data.currency === 'USD' ? 'selected' : ''}>USD</option>
                                                <option value="AFS" ${data.currency === 'AFS' ? 'selected' : ''}>AFS</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('payment_method'); ?></label>
                                            <input type="text" name="payment_method" class="form-control" value="${data.payment_method || ''}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo __('reference_number'); ?></label>
                                            <input type="text" name="reference_number" class="form-control" value="${data.reference_number || ''}">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label><?php echo __('notes'); ?></label>
                                    <textarea name="notes" class="form-control" rows="2">${data.notes || ''}</textarea>
                                </div>
                            </div>
                            <div class="modal-footer bg-light border-0">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                                <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-2"></i><?php echo __('update'); ?></button>
                            </div>
                        </form>
                    `;
                    document.getElementById('editExpenseContent').innerHTML = html;
                });
        });
    });
    </script>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
