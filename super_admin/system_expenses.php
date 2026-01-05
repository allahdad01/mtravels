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
<div class="pcoded-main-container dark:bg-gray-900">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10 text-2xl font-semibold text-gray-800 dark:text-gray-100"><?= __('system_expenses') ?></h5>
                                </div>
                                <ul class="breadcrumb flex space-x-2 text-sm text-gray-500 dark:text-gray-400">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="dashboard.php"><?= __('dashboard') ?></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('system_expenses') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Action Buttons -->
                        <div class="row mb-6">
                            <div class="col-md-12">
                                <div class="flex justify-between items-center gap-3 flex-wrap">
                                    <button type="button" class="btn btn-primary flex items-center px-4 py-2 rounded-lg" data-toggle="modal" data-target="#addExpenseModal">
                                        <i class="feather icon-plus mr-2"></i><?= __('add_expense') ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary flex items-center px-4 py-2 rounded-lg" data-toggle="modal" data-target="#manageCategories">
                                        <i class="feather icon-settings mr-2"></i><?= __('manage_categories') ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Section -->
                        <div class="row mb-6">
                            <div class="col-md-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <h6 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">
                                        <i class="feather icon-filter mr-2"></i><?= __('filters') ?>
                                    </h6>
                                    <form id="filterForm" method="GET" class="space-y-4">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('from_date') ?></label>
                                                <input type="date" name="startDate" class="form-control mt-2" value="<?= htmlspecialchars($startDate) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('to_date') ?></label>
                                                <input type="date" name="endDate" class="form-control mt-2" value="<?= htmlspecialchars($endDate) ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('category') ?></label>
                                                <select name="category" class="form-control mt-2">
                                                    <option value=""><?= __('all_categories') ?></option>
                                                    <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-search mr-1"></i><?= __('apply_filter') ?>
                                                </button>
                                                <a href="system_expenses.php" class="btn btn-secondary ml-2">
                                                    <i class="feather icon-refresh-ccw mr-1"></i><?= __('reset') ?>
                                                </a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row mb-6 gap-y-4">
                            <div class="col-md-3">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 border-l-4 border-blue-500">
                                    <div class="text-center">
                                        <h6 class="text-gray-600 dark:text-gray-400 text-sm"><?= __('total_expenses') ?></h6>
                                        <div class="mt-2">
                                            <?php if ($totalAmountByUSD > 0): ?>
                                            <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                                $ <?= number_format($totalAmountByUSD, 2) ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($totalAmountByAFS > 0): ?>
                                            <div class="text-2xl font-bold text-gray-800 dark:text-white">
                                                ؋ <?= number_format($totalAmountByAFS, 2) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 border-l-4 border-green-500">
                                    <div class="text-center">
                                        <h6 class="text-gray-600 dark:text-gray-400 text-sm"><?= __('number_of_expenses') ?></h6>
                                        <h3 class="text-3xl font-bold text-gray-800 dark:text-white mt-2">
                                            <?= count($expenses) ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 border-l-4 border-purple-500">
                                    <div class="text-center">
                                        <h6 class="text-gray-600 dark:text-gray-400 text-sm"><?= __('average_expense') ?></h6>
                                        <div class="mt-2">
                                            <?php if ($avgUSD > 0): ?>
                                            <div class="text-xl font-bold text-gray-800 dark:text-white mb-1">
                                                $ <?= number_format($avgUSD, 2) ?>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $usdCount ?> USD <?= __('expenses') ?></p>
                                            <?php endif; ?>
                                            <?php if ($avgAFS > 0): ?>
                                            <div class="text-xl font-bold text-gray-800 dark:text-white mt-2">
                                                ؋ <?= number_format($avgAFS, 2) ?>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $afsCount ?> AFS <?= __('expenses') ?></p>
                                            <?php endif; ?>
                                            <?php if ($avgUSD == 0 && $avgAFS == 0): ?>
                                            <p class="text-gray-600 dark:text-gray-400">-</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 border-l-4 border-yellow-500">
                                    <div class="text-center">
                                        <h6 class="text-gray-600 dark:text-gray-400 text-sm"><?= __('categories_used') ?></h6>
                                        <h3 class="text-3xl font-bold text-gray-800 dark:text-white mt-2">
                                            <?= count($categoryTotals) ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Expenses Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4 flex justify-between items-center">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('expense_list') ?></h5>
                                        <span class="badge bg-blue-100 text-blue-800 px-3 py-1 text-xs font-medium rounded-full"><?= count($expenses) ?> <?= __('records') ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr class="bg-gray-100 dark:bg-gray-700">
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('date') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('category') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('description') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold text-right"><?= __('amount') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('currency') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('created_by') ?></th>
                                                        <th class="text-gray-800 dark:text-white font-semibold"><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($expenses)): ?>
                                                        <?php foreach ($expenses as $expense): ?>
                                                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                            <td class="text-gray-800 dark:text-gray-200">
                                                                <?= date('M d, Y', strtotime($expense['date'])) ?>
                                                            </td>
                                                            <td class="text-gray-800 dark:text-gray-200">
                                                                <span class="badge bg-purple-100 text-purple-800 px-2 py-1 text-xs font-medium rounded">
                                                                    <?= htmlspecialchars($expense['category_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-gray-800 dark:text-gray-200" style="max-width: 300px; white-space: normal;">
                                                                <?= htmlspecialchars(substr($expense['description'], 0, 50)) ?>
                                                                <?= strlen($expense['description']) > 50 ? '...' : '' ?>
                                                            </td>
                                                            <td class="text-right font-semibold text-gray-800 dark:text-gray-200">
                                                                <?= formatCurrency($expense['amount'], $expense['currency']) ?>
                                                            </td>
                                                            <td class="text-gray-800 dark:text-gray-200 font-medium">
                                                                <span class="badge <?= $expense['currency'] === 'USD' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?> px-2 py-1 text-xs font-semibold rounded">
                                                                    <?= htmlspecialchars($expense['currency']) ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-gray-800 dark:text-gray-200 text-sm">
                                                                <?= htmlspecialchars($expense['created_by_name'] ?? 'System') ?>
                                                            </td>
                                                            <td class="space-x-2">
                                                                <button class="btn btn-sm btn-info edit-expense" data-id="<?= $expense['id'] ?>" data-toggle="modal" data-target="#editExpenseModal">
                                                                    <i class="feather icon-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger delete-expense" data-id="<?= $expense['id'] ?>">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center text-gray-600 dark:text-gray-400 py-6">
                                                            <?= __('no_expenses_found') ?>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category Summary -->
                        <?php if (!empty($categoryTotals)): ?>
                        <div class="row mt-6">
                            <div class="col-md-12">
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="card-header border-b pb-4">
                                        <h5 class="text-lg font-semibold text-gray-800 dark:text-white"><?= __('expense_by_category') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="space-y-4">
                                            <?php foreach ($categoryTotals as $catId => $catData): ?>
                                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                                <div>
                                                    <h6 class="text-gray-800 dark:text-white font-semibold mb-2"><?= htmlspecialchars($catData['name']) ?></h6>

                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-gray-800 dark:text-white mb-1">
                                                        <?php 
                                                        // Calculate total amount for this category
                                                        $catTotal = 0;
                                                        foreach ($catData['currencies'] as $amount) {
                                                            $catTotal += $amount;
                                                        }
                                                        ?>
                                                        <?php if (count($catData['currencies']) === 1): ?>
                                                            <?php foreach ($catData['currencies'] as $curr => $amount): ?>
                                                                <?= formatCurrency($catTotal, $curr) ?>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            Mixed
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                                        <?= $catData['count'] ?> <?= __('expenses') ?>
                                                    </p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                                        <?= $totalAmount > 0 ? number_format(($catTotal / $totalAmount) * 100, 1) : 0 ?>% of total
                                                    </p>
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

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
            <div class="modal-header bg-blue-500 text-white border-0">
                <h5 class="modal-title"><i class="feather icon-plus mr-2"></i><?= __('add_expense') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="addExpenseForm" method="POST" action="handlers/create_system_expense.php" enctype="multipart/form-data" onsubmit="handleExpenseSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-body p-6 space-y-4">
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('category') ?> *</label>
                        <select name="category_id" class="form-control mt-2" required>
                            <option value=""><?= __('select_category') ?></option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('date') ?> *</label>
                        <input type="date" name="date" class="form-control mt-2" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('description') ?> *</label>
                        <textarea name="description" class="form-control mt-2" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('amount') ?> *</label>
                                <input type="number" name="amount" class="form-control mt-2" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('currency') ?></label>
                                <select name="currency" class="form-control mt-2">
                                    <option value="USD">USD</option>
                                    <option value="AFS">AFS</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('payment_method') ?></label>
                        <input type="text" name="payment_method" class="form-control mt-2" placeholder="e.g., Bank Transfer, Cash">
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('reference_number') ?></label>
                        <input type="text" name="reference_number" class="form-control mt-2" placeholder="Invoice, Check, Transaction ID">
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('receipt_file') ?></label>
                        <input type="file" name="receipt_file" class="form-control mt-2" accept=".pdf,.jpg,.jpeg,.png" multiple="false">
                        <small class="text-gray-500 dark:text-gray-400 block mt-2">Max 5MB, PDF/JPG/PNG only</small>
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('notes') ?></label>
                        <textarea name="notes" class="form-control mt-2" rows="2" placeholder="Additional notes (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-2"></i><?= __('save_expense') ?></button>
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
            <div class="modal-header bg-green-500 text-white border-0">
                <h5 class="modal-title"><i class="feather icon-settings mr-2"></i><?= __('expense_categories') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-6">
                <button type="button" class="btn btn-success mb-4" data-toggle="modal" data-target="#addCategoryModal" data-dismiss="modal">
                    <i class="feather icon-plus mr-2"></i><?= __('add_category') ?>
                </button>
                <div class="space-y-3">
                    <?php foreach ($categories as $cat): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <h6 class="text-gray-800 dark:text-white font-semibold"><?= htmlspecialchars($cat['name']) ?></h6>
                            <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($cat['description'] ?? '') ?></p>
                        </div>
                        <div class="space-x-2">
                            <button class="btn btn-sm btn-info edit-category" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-toggle="modal" data-target="#editCategoryModal" data-dismiss="modal">
                                <i class="feather icon-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-category" data-id="<?= $cat['id'] ?>">
                                <i class="feather icon-trash-2"></i>
                            </button>
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
            <div class="modal-header bg-green-500 text-white border-0">
                <h5 class="modal-title"><?= __('add_category') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="addCategoryForm" method="POST" action="handlers/create_system_expense_category.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-body p-6 space-y-4">
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('category_name') ?> *</label>
                        <input type="text" name="name" class="form-control mt-2" required>
                    </div>
                    <div class="form-group">
                        <label class="text-gray-700 dark:text-gray-300 font-medium"><?= __('description') ?></label>
                        <textarea name="description" class="form-control mt-2" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><i class="feather icon-save mr-2"></i><?= __('save_category') ?></button>
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
    .currency {
        font-size: 0.8em;
        color: #10B981;
        margin-right: 0.3em;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(59, 130, 246, 0.05);
    }
    
    .dark .table-hover tbody tr:hover {
        background-color: rgba(59, 130, 246, 0.1);
    }
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

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
            alert('<?= __("expense_created_successfully") ?>');
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
        if (confirm('<?= __('are_you_sure') ?>')) {
            const id = this.dataset.id;
            fetch('handlers/delete_system_expense.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&csrf_token=<?= $_SESSION['csrf_token'] ?>'
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('<?= __("deleted_successfully") ?>');
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
        if (confirm('<?= __('are_you_sure') ?>')) {
            const id = this.dataset.id;
            fetch('handlers/delete_system_expense_category.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&csrf_token=<?= $_SESSION['csrf_token'] ?>'
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
                    <div class="modal-header bg-blue-500 text-white border-0">
                        <h5 class="modal-title"><i class="feather icon-edit mr-2"></i><?= __('edit_expense') ?></h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <form method="POST" action="handlers/update_system_expense.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id" value="${data.id}">
                        <div class="modal-body p-6 space-y-4">
                            <div class="form-group">
                                <label><?= __('category') ?> *</label>
                                <select name="category_id" class="form-control mt-2" required>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="${data.category_id}">${data.category_name}</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?= __('date') ?> *</label>
                                <input type="date" name="date" class="form-control mt-2" value="${data.date}" required>
                            </div>
                            <div class="form-group">
                                <label><?= __('description') ?> *</label>
                                <textarea name="description" class="form-control mt-2" rows="3" required>${data.description}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <label><?= __('amount') ?> *</label>
                                    <input type="number" name="amount" class="form-control mt-2" step="0.01" value="${data.amount}" required>
                                </div>
                                <div class="col-md-6">
                                    <label><?= __('currency') ?></label>
                                    <select name="currency" class="form-control mt-2">
                                        <option value="USD" ${data.currency === 'USD' ? 'selected' : ''}>USD</option>
                                        <option value="AFS" ${data.currency === 'AFS' ? 'selected' : ''}>AFS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><?= __('payment_method') ?></label>
                                <input type="text" name="payment_method" class="form-control mt-2" value="${data.payment_method || ''}">
                            </div>
                            <div class="form-group">
                                <label><?= __('reference_number') ?></label>
                                <input type="text" name="reference_number" class="form-control mt-2" value="${data.reference_number || ''}">
                            </div>
                            <div class="form-group">
                                <label><?= __('notes') ?></label>
                                <textarea name="notes" class="form-control mt-2" rows="2">${data.notes || ''}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                            <button type="submit" class="btn btn-primary"><i class="feather icon-save mr-2"></i><?= __('update') ?></button>
                        </div>
                    </form>
                `;
                document.getElementById('editExpenseContent').innerHTML = html;
            });
    });
});
</script>

</body>
</html>
