
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
                                <!-- Page Header -->
                                <div class="sa-page-header">
                                    <div class="sph-content">
                                        <div class="sph-icon">
                                            <i class="feather icon-credit-card"></i>
                                        </div>
                                        <div class="sph-text">
                                            <h1><?php echo __('system_expenses'); ?></h1>
                                            <p><?php echo __('manage_system_expenses'); ?></p>
                                        </div>
                                    </div>
                                    <div class="sph-actions">
                                        <button type="button" class="sa-btn-header sa-btn-header-primary" data-toggle="modal" data-target="#addExpenseModal">
                                            <i class="feather icon-plus"></i>
                                            <span><?php echo __('add_expense'); ?></span>
                                        </button>
                                        <button type="button" class="sa-btn-header sa-btn-header-secondary" data-toggle="modal" data-target="#manageCategories">
                                            <i class="feather icon-settings"></i>
                                            <span><?php echo __('manage_categories'); ?></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Filter Section -->
                                <div class="sa-card" style="margin-bottom: 20px;">
                                    <div class="sa-card-body">
                                        <form id="filterForm" method="GET" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <div>
                                                    <label class="sa-search-label"><?php echo __('from_date'); ?></label>
                                                    <input type="date" name="startDate" class="sa-search-input" value="<?php echo htmlspecialchars($startDate); ?>">
                                                </div>
                                                <div>
                                                    <label class="sa-search-label"><?php echo __('to_date'); ?></label>
                                                    <input type="date" name="endDate" class="sa-search-input" value="<?php echo htmlspecialchars($endDate); ?>">
                                                </div>
                                                <div>
                                                    <label class="sa-search-label"><?php echo __('category'); ?></label>
                                                    <select name="category" class="sa-search-input">
                                                        <option value=""><?php echo __('all_categories'); ?></option>
                                                        <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="sa-filter-actions">
                                                    <button type="submit" class="sa-btn sa-btn-primary">
                                                        <i class="feather icon-search"></i> <?php echo __('apply_filter'); ?>
                                                    </button>
                                                    <a href="system_expenses.php" class="sa-btn sa-btn-ghost">
                                                        <i class="feather icon-refresh-ccw"></i> <?php echo __('reset'); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Summary Cards -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="sa-summary-card sa-summary-card-primary">
                                            <div class="ssc-icon">
                                                <i class="feather icon-credit-card"></i>
                                            </div>
                                            <div class="ssc-content">
                                                <span class="ssc-label"><?php echo __('total_expenses'); ?></span>
                                                <div class="ssc-value">
                                                    <?php if ($totalAmountByUSD > 0): ?>
                                                    <span class="currency-usd">$ <?php echo number_format($totalAmountByUSD, 2); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($totalAmountByAFS > 0): ?>
                                                    <span class="currency-afs">؋ <?php echo number_format($totalAmountByAFS, 2); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($totalAmountByUSD == 0 && $totalAmountByAFS == 0): ?>
                                                    <span class="currency-na">-</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="ssc-badge"><?php echo count($expenses); ?> <?php echo __('expenses'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sa-summary-card sa-summary-card-success">
                                            <div class="ssc-icon">
                                                <i class="feather icon-list"></i>
                                            </div>
                                            <div class="ssc-content">
                                                <span class="ssc-label"><?php echo __('number_of_expenses'); ?></span>
                                                <div class="ssc-value ssc-value-large"><?php echo count($expenses); ?></div>
                                                <span class="ssc-badge"><?php echo $usdCount; ?> USD | <?php echo $afsCount; ?> AFS</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sa-summary-card sa-summary-card-info">
                                            <div class="ssc-icon">
                                                <i class="feather icon-trending-up"></i>
                                            </div>
                                            <div class="ssc-content">
                                                <span class="ssc-label"><?php echo __('average_expense'); ?></span>
                                                <div class="ssc-value">
                                                    <?php if ($avgUSD > 0): ?>
                                                    <span class="currency-usd">$ <?php echo number_format($avgUSD, 2); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($avgAFS > 0): ?>
                                                    <span class="currency-afs">؋ <?php echo number_format($avgAFS, 2); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($avgUSD == 0 && $avgAFS == 0): ?>
                                                    <span class="currency-na">-</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="ssc-badge"><?php echo __('per_expense'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sa-summary-card sa-summary-card-warning">
                                            <div class="ssc-icon">
                                                <i class="feather icon-tag"></i>
                                            </div>
                                            <div class="ssc-content">
                                                <span class="ssc-label"><?php echo __('categories_used'); ?></span>
                                                <div class="ssc-value ssc-value-large"><?php echo count($categoryTotals); ?></div>
                                                <span class="ssc-badge"><?php echo __('categories'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Expenses Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2><?php echo __('expense_list'); ?></h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);"><?php echo count($expenses); ?> expenses found</p>
                                    </div>
                                </div>

                                <!-- Expenses Cards -->
                                <?php if (empty($expenses)): ?>
                                <div class="sa-card">
                                    <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                        <div style="font-size: 2rem; margin-bottom: 12px;">💰</div>
                                        <div style="font-weight: 600; margin-bottom: 4px;"><?php echo __('no_expenses_found'); ?></div>
                                        <div style="font-size: 0.8rem;">No expenses found for the selected period.</div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="sa-expense-list">
                                    <?php foreach ($expenses as $expense): ?>
                                    <div class="sa-expense-card">
                                        <div class="sec-header">
                                            <div class="sec-info">
                                                <h4><?php echo date('M d, Y', strtotime($expense['date'])); ?></h4>
                                                <p class="sec-category">
                                                    <i class="feather icon-tag"></i>
                                                    <?php echo htmlspecialchars($expense['category_name']); ?>
                                                </p>
                                            </div>
                                            <div class="sec-amount">
                                                <span class="amount-value"><?php echo formatCurrency($expense['amount'], $expense['currency']); ?></span>
                                                <span class="pill <?php echo $expense['currency'] === 'USD' ? 'pill-blue' : 'pill-green'; ?>">
                                                    <?php echo htmlspecialchars($expense['currency']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="sec-details">
                                            <div class="sec-detail-item">
                                                <span class="sec-detail-label">Description</span>
                                                <span class="sec-detail-value"><?php echo htmlspecialchars($expense['description']); ?></span>
                                            </div>
                                            <div class="sec-detail-item">
                                                <span class="sec-detail-label">Created By</span>
                                                <span class="sec-detail-value"><?php echo htmlspecialchars($expense['created_by_name'] ?? 'System'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="sec-actions">
                                            <button class="sa-btn sa-btn-small sa-btn-info edit-expense" data-id="<?php echo $expense['id']; ?>" data-toggle="modal" data-target="#editExpenseModal">
                                                <i class="feather icon-edit"></i> Edit
                                            </button>
                                            <button class="sa-btn sa-btn-small sa-btn-danger delete-expense" data-id="<?php echo $expense['id']; ?>">
                                                <i class="feather icon-trash-2"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Category Summary -->
                                <?php if (!empty($categoryTotals)): ?>
                                <div class="sa-shdr" style="margin-top: 30px; margin-bottom: 16px;">
                                    <div>
                                        <h2><?php echo __('expense_by_category'); ?></h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Expense breakdown by category</p>
                                    </div>
                                </div>
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

    /* ─── ROOT VARIABLES ─────────────────────────────────── */
    :root {
        --muted: #999;
        --surface: #ffffff;
        --surface2: #f5f5f5;
        --border: #e0e0e0;
        --text: #333333;
        --green: #28a745;
        --red: #dc3545;
    }

    /* ─── EXPENSE CARD STYLES ───────────────────────────── */
    .sa-expense-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .sa-expense-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.2s ease;
    }

    .sa-expense-card:hover {
        border-color: rgba(64, 153, 255, 0.3);
        box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
    }

    .sec-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e0e0e0;
    }

    .sec-info h4 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 6px 0;
        color: #333;
    }

    .sec-category {
        font-size: 0.85rem;
        color: #999;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .sec-amount {
        text-align: right;
    }

    .amount-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #2ed8b6;
        display: block;
    }

    .sec-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .sec-detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .sec-detail-label {
        font-size: 0.75rem;
        color: #999;
        font-weight: 600;
        text-transform: uppercase;
    }

    .sec-detail-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: #444;
    }

    .sec-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    /* ─── PILLS ────────────────────────────────────────── */
    .pill {
        font-size: 0.62rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    .pill-green {
        background: rgba(16,185,129,0.12);
        color: #10b981;
    }

    .pill-blue {
        background: rgba(59,130,246,0.12);
        color: #3b82f6;
    }

    /* ─── SECTION HEADER ───────────────────────────────── */
    .sa-shdr {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .sa-shdr h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
        color: #333;
    }

    .sa-shdr p {
        margin: 4px 0 0 0;
        font-size: 0.75rem;
        color: #999;
    }

    /* ─── CARDS ──────────────────────────────────────── */
    .sa-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .sa-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .sa-card-body {
        padding: 1.5rem;
    }

    /* ─── BUTTONS ──────────────────────────────────── */
    .sa-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        font-size: 0.9rem;
    }

    .sa-btn-small {
        padding: 6px 12px;
        font-size: 0.75rem;
    }

    .sa-btn-info {
        background: linear-gradient(135deg, #11cdef 0%, #2dd4bf 100%);
        color: white;
    }

    .sa-btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(17, 207, 239, 0.3);
    }

    .sa-btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
        color: white;
    }

    .sa-btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    /* ─── RESPONSIVE ────────────────────────────────── */
    @media (max-width: 768px) {
        .sec-header {
            flex-direction: column;
        }
        
        .sec-amount {
            text-align: left;
            margin-top: 12px;
        }
        
        .sec-details {
            grid-template-columns: 1fr;
        }
        
        .sec-actions {
            width: 100%;
        }
    }

    /* ─── SEARCH & FILTER ────────────────────────────── */
    .sa-search-filter {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .sa-search-group {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex: 1;
        flex-wrap: wrap;
    }

    .sa-search-group > div {
        flex: 1;
        min-width: 120px;
    }

    .sa-search-label {
        font-size: 0.75rem;
        color: #666;
        font-weight: 600;
        margin-bottom: 4px;
        display: block;
    }

    .sa-search-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .sa-search-input:focus {
        outline: none;
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .sa-filter-actions {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .sa-btn-ghost {
        background: #f0f0f0;
        color: #333;
        border: 1px solid #e0e0e0;
    }

    .sa-btn-ghost:hover {
        background: #e8e8e8;
        border-color: #d0d0d0;
    }

    /* ─── PAGE HEADER ─────────────────────────────────── */
    .sa-page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 24px 28px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.35);
    }

    .sa-page-header .sph-content {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .sa-page-header .sph-icon {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
        backdrop-filter: blur(4px);
    }

    .sa-page-header .sph-text h1 {
        margin: 0 0 4px 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        letter-spacing: -0.02em;
    }

    .sa-page-header .sph-text p {
        margin: 0;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.85);
    }

    .sa-page-header .sph-actions {
        display: flex;
        gap: 12px;
    }

    .sa-page-header .sa-btn-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
    }

    .sa-page-header .sa-btn-header-primary {
        background: white;
        color: #667eea;
    }

    .sa-page-header .sa-btn-header-primary:hover {
        background: rgba(255, 255, 255, 0.9);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .sa-page-header .sa-btn-header-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .sa-page-header .sa-btn-header-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }

    @media (max-width: 768px) {
        .sa-page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }
        
        .sa-page-header .sph-actions {
            flex-direction: column;
        }
        
        .sa-page-header .sa-btn-header {
            justify-content: center;
        }
    }

    /* ─── SUMMARY CARDS ──────────────────────────────── */
    .sa-summary-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #eee;
        height: 100%;
    }

    .sa-summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .sa-summary-card .ssc-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .sa-summary-card .ssc-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sa-summary-card .ssc-label {
        font-size: 0.75rem;
        color: #888;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .sa-summary-card .ssc-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #333;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .sa-summary-card .ssc-value-large {
        font-size: 2rem;
    }

    .sa-summary-card .ssc-badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        display: inline-block;
        width: fit-content;
    }

    .currency-usd {
        color: #3b82f6;
    }

    .currency-afs {
        color: #10b981;
    }

    .currency-na {
        color: #999;
    }

    /* Primary Card */
    .sa-summary-card-primary {
        border-left: 4px solid #667eea;
    }

    .sa-summary-card-primary .ssc-icon {
        background: rgba(102, 126, 234, 0.12);
        color: #667eea;
    }

    .sa-summary-card-primary .ssc-badge {
        background: rgba(102, 126, 234, 0.12);
        color: #667eea;
    }

    /* Success Card */
    .sa-summary-card-success {
        border-left: 4px solid #28a745;
    }

    .sa-summary-card-success .ssc-icon {
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
    }

    .sa-summary-card-success .ssc-badge {
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
    }

    /* Info Card */
    .sa-summary-card-info {
        border-left: 4px solid #17a2b8;
    }

    .sa-summary-card-info .ssc-icon {
        background: rgba(23, 162, 184, 0.12);
        color: #17a2b8;
    }

    .sa-summary-card-info .ssc-badge {
        background: rgba(23, 162, 184, 0.12);
        color: #17a2b8;
    }

    /* Warning Card */
    .sa-summary-card-warning {
        border-left: 4px solid #ffc107;
    }

    .sa-summary-card-warning .ssc-icon {
        background: rgba(255, 193, 7, 0.12);
        color: #d99e00;
    }

    .sa-summary-card-warning .ssc-badge {
        background: rgba(255, 193, 7, 0.12);
        color: #d99e00;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sa-summary-card {
            flex-direction: column;
            align-items: stretch;
        }
        
        .sa-summary-card .ssc-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        
        .sa-summary-card .ssc-value {
            font-size: 1.1rem;
        }
        
        .sa-summary-card .ssc-value-large {
            font-size: 1.5rem;
        }
    }
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
