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

require_permission('finance.expenses');

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
                                <button type="button" class="btn btn-primary" id="addCategoryBtn" data-toggle="modal" data-target="#categoryModal">
                                    <i class="feather icon-plus"></i> <?= __('add_category') ?>
                                </button>
                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#expenseModal">
                                    <i class="feather icon-plus"></i> <?= __('add_expense') ?>
                                </button>
                                <a href="agency_settlements.php" class="btn btn-warning" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
                                    <i class="feather icon-building"></i> Agency Settlements
                                </a>
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
                            // Build parent -> children map
                            $childrenByParent = [];
                            foreach ($categories as $cat) {
                                if (!empty($cat['parent_id'])) {
                                    $childrenByParent[$cat['parent_id']][] = $cat;
                                }
                            }

                            // Render one category card (parent or child) with its expense list
                            function render_expense_category_card($category, $pdo, $tenantId, $branchId, $children = [], $isChild = false, $parentName = '') {
                                $currentMonth = date('Y-m-01');
                                $nextMonth = date('Y-m-d', strtotime($currentMonth . ' +1 month'));

                                $isFilterActive = isset($_GET['startDate']) && isset($_GET['endDate']);

                                if ($isFilterActive) {
                                    $startDate = $_GET['startDate'];
                                    $endDate = $_GET['endDate'];
                                    $dateOp = '<='; // filter end date is inclusive
                                } else {
                                    $startDate = $currentMonth;
                                    $endDate = $nextMonth;
                                    $dateOp = '<'; // current-month view: end is exclusive
                                }

                                // Expense list for this card: child cards show only their own
                                // sub-category expenses; a parent with children shows only its
                                // direct expenses (children are listed in their own cards).
                                if ($isChild) {
                                    $expenseQuery = "SELECT * FROM expenses WHERE sub_category_id = ? AND date >= ? AND date $dateOp ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $tenantId, $branchId]);
                                } elseif ($children) {
                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND sub_category_id IS NULL AND date >= ? AND date $dateOp ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $tenantId, $branchId]);
                                } else {
                                    $expenseQuery = "SELECT * FROM expenses WHERE category_id = ? AND date >= ? AND date $dateOp ? AND tenant_id = ? AND branch_id = ? ORDER BY date DESC";
                                    $expenseStmt = $pdo->prepare($expenseQuery);
                                    $expenseStmt->execute([$category['id'], $startDate, $endDate, $tenantId, $branchId]);
                                }

                                $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
                                $count = count($expenses);
                                $currencyTotals = [];
                                foreach ($expenses as $exp) {
                                    $cur = $exp['currency'] ?? 'USD';
                                    $currencyTotals[$cur] = ($currencyTotals[$cur] ?? 0) + $exp['amount'];
                                }

                                // Grand total for a parent: include all children's expenses
                                $grandCount = $count;
                                $grandTotals = $currencyTotals;
                                if ($children) {
                                    foreach ($children as $child) {
                                        $childQuery = "SELECT amount, currency FROM expenses WHERE sub_category_id = ? AND date >= ? AND date $dateOp ? AND tenant_id = ? AND branch_id = ?";
                                        $childStmt = $pdo->prepare($childQuery);
                                        $childStmt->execute([$child['id'], $startDate, $endDate, $tenantId, $branchId]);
                                        foreach ($childStmt->fetchAll(PDO::FETCH_ASSOC) as $cexp) {
                                            $ccur = $cexp['currency'] ?? 'USD';
                                            $grandTotals[$ccur] = ($grandTotals[$ccur] ?? 0) + $cexp['amount'];
                                            $grandCount++;
                                        }
                                    }
                                }

                                // Fetch settlement data for expenses in this category
                                $settlementQuery = "SELECT aes.expense_id, aes.amount_owed, aes.status AS settlement_status, aes.currency AS settlement_currency,
                                    COALESCE(br.name, aes.agency_name) AS agency_name
                                    FROM agency_expense_settlements aes
                                    LEFT JOIN branches br ON br.id = aes.agency_branch_id
                                    WHERE aes.tenant_id = ? AND aes.branch_id = ?";
                                $settlementParams = [$tenantId, $branchId];

                                // Build expense IDs for this category to filter settlements
                                $expenseIds = array_column($expenses, 'id');
                                if ($children) {
                                    foreach ($children as $child) {
                                        $childExpQuery = "SELECT id FROM expenses WHERE sub_category_id = ? AND date >= ? AND date $dateOp ? AND tenant_id = ? AND branch_id = ?";
                                        $childExpStmt = $pdo->prepare($childExpQuery);
                                        $childExpStmt->execute([$child['id'], $startDate, $endDate, $tenantId, $branchId]);
                                        while ($ce = $childExpStmt->fetch(PDO::FETCH_ASSOC)) {
                                            $expenseIds[] = $ce['id'];
                                        }
                                    }
                                }

                                $settlementMap = [];
                                if (!empty($expenseIds)) {
                                    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
                                    $settlementSql = "SELECT aes.expense_id, aes.amount_owed, aes.status AS settlement_status, aes.currency AS settlement_currency,
                                        COALESCE(br.name, aes.agency_name) AS agency_name
                                        FROM agency_expense_settlements aes
                                        LEFT JOIN branches br ON br.id = aes.agency_branch_id
                                        WHERE aes.tenant_id = ? AND aes.branch_id = ? AND aes.expense_id IN ($placeholders)";
                                    $settlementParams2 = array_merge([$tenantId, $branchId], $expenseIds);
                                    $settlementStmt = $pdo->prepare($settlementSql);
                                    $settlementStmt->execute($settlementParams2);
                                    while ($sr = $settlementStmt->fetch(PDO::FETCH_ASSOC)) {
                                        $settlementMap[$sr['expense_id']] = $sr;
                                    }
                                }

                                // Calculate settlement totals for this category
                                $settledTotal = 0;
                                $pendingTotal = 0;
                                foreach ($settlementMap as $sid => $sm) {
                                    if ($sm['settlement_status'] === 'settled') {
                                        $settledTotal++;
                                    } else {
                                        $pendingTotal += (float)$sm['amount_owed'];
                                    }
                                }

                                $cardClass = $isChild ? 'category-card category-card--child' : 'category-card';
                                echo '<div class="' . $cardClass . '" data-category="' . $category['id'] . '">';
                                echo '<div class="category-card-header">';
                                echo '<div class="category-info">';
                                if ($isChild) {
                                    echo '<div class="category-icon icon-sub"><i class="feather icon-corner-down-right"></i></div>';
                                } else {
                                    echo '<div class="category-icon icon-default"><i class="feather icon-folder"></i></div>';
                                }
                                echo '<h6>';
                                if ($isChild && $parentName !== '') {
                                    echo '<span class="sub-parent-crumb">' . htmlspecialchars($parentName) . '</span>';
                                    echo '<i class="feather icon-chevrons-right sub-crumb-sep"></i>';
                                }
                                echo htmlspecialchars($category['name']);
                                if ($isChild) {
                                    echo ' <span class="sub-category-badge">' . __('sub_category') . '</span>';
                                }
                                echo '</h6>';
                                echo '</div>';
                                echo '<div class="category-meta">';

                                if ($children) {
                                    echo '<span class="sub-count-chip"><i class="feather icon-layers"></i> ' . count($children) . ' ' . __('sub_categories') . '</span>';
                                }
                                echo '<span class="expense-count">' . ($children ? $grandCount : $count) . ' ' . __('entries') . '</span>';
                                $totalParts = [];
                                foreach (($children ? $grandTotals : $currencyTotals) as $cur => $amt) {
                                    $totalParts[] = number_format($amt, 2) . ' ' . htmlspecialchars($cur);
                                }
                                $totalClass = $children ? 'category-total category-total--grand' : 'category-total';
                                if ($totalParts) {
                                    $totalTitle = $children ? __('grand_total_includes_sub') : '';
                                    echo '<span class="' . $totalClass . '"' . ($totalTitle ? ' title="' . $totalTitle . '"' : '') . '>' . implode(' | ', $totalParts) . '</span>';
                                } else {
                                    echo '<span class="' . $totalClass . ' category-total--zero">0.00</span>';
                                }

                                // Settlement summary badge
                                if (!empty($settlementMap)) {
                                    $settlementCount = count($settlementMap);
                                    $settledCount = 0;
                                    foreach ($settlementMap as $sm) {
                                        if ($sm['settlement_status'] === 'settled') $settledCount++;
                                    }
                                    $settlePct = $settlementCount > 0 ? round(($settledCount / $settlementCount) * 100) : 0;
                                    $settleColor = $settlePct === 100 ? '#16a34a' : ($settlePct > 0 ? '#d97706' : '#dc2626');
                                    echo '<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:' . $settleColor . '15;color:' . $settleColor . ';border:1px solid ' . $settleColor . '30;margin-left:6px;" title="' . $settledCount . ' settled, ' . $settlementCount . ' total agency expenses">';
                                    echo '<i class="feather icon-building" style="font-size:10px;"></i>';
                                    echo $settlePct . '% Settled';
                                    echo '</span>';
                                }
                                echo '<div class="category-card-actions">';
                                echo '<button class="btn-print print-category" data-id="' . $category['id'] . '" title="Print Category Report"><i class="feather icon-printer"></i></button>';
                                echo '<button class="btn-edit edit-category" data-id="' . $category['id'] . '" data-name="' . htmlspecialchars($category['name']) . '" data-parent="' . ($category['parent_id'] ?? '') . '" data-has-children="' . ($children ? 1 : 0) . '"><i class="feather icon-edit-2"></i></button>';
                                echo '<button class="btn-delete delete-category" data-id="' . $category['id'] . '"><i class="feather icon-trash-2"></i></button>';
                                echo '</div>';
                                echo '<i class="feather icon-chevron-down expand-icon"></i>';
                                echo '</div>';
                                echo '</div>';

                                echo '<div class="expense-list">';

                                // Settlement summary bar
                                if (!empty($settlementMap)) {
                                    $sumPending = 0;
                                    $sumSettled = 0;
                                    $sumPartial = 0;
                                    $sumRemaining = 0;
                                    foreach ($settlementMap as $sm) {
                                        if ($sm['settlement_status'] === 'settled') $sumSettled++;
                                        elseif ($sm['settlement_status'] === 'partial') { $sumPartial++; $sumRemaining += (float)$sm['amount_owed']; }
                                        else { $sumPending++; $sumRemaining += (float)$sm['amount_owed']; }
                                    }
                                    $sumTotal = count($settlementMap);
                                    echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:8px;">';
                                    echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">';
                                    echo '<span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#475569;"><i class="feather icon-building" style="font-size:13px;color:#d97706;"></i> Agency Expenses</span>';
                                    echo '<span style="font-size:11px;padding:3px 8px;border-radius:20px;background:#f0fdf4;color:#15803d;font-weight:600;">' . $sumSettled . ' settled</span>';
                                    if ($sumPartial > 0) echo '<span style="font-size:11px;padding:3px 8px;border-radius:20px;background:#fffbeb;color:#b45309;font-weight:600;">' . $sumPartial . ' partial</span>';
                                    if ($sumPending > 0) echo '<span style="font-size:11px;padding:3px 8px;border-radius:20px;background:#fef2f2;color:#b91c1c;font-weight:600;">' . $sumPending . ' pending</span>';
                                    echo '</div>';
                                    echo '<div style="display:flex;align-items:center;gap:10px;">';
                                    if ($sumRemaining > 0) {
                                        echo '<span style="font-size:12px;font-weight:700;color:#dc2626;">Remaining: ' . number_format($sumRemaining, 2) . '</span>';
                                    } else {
                                        echo '<span style="font-size:12px;font-weight:700;color:#16a34a;"><i class="feather icon-check-circle" style="font-size:13px;"></i> Fully Settled</span>';
                                    }
                                    echo '<a href="agency_settlements.php" style="font-size:11px;font-weight:600;color:#2563eb;text-decoration:none;">View Details</a>';
                                    echo '</div>';
                                    echo '</div>';
                                }
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
                                        // Settlement badge
                                        $expId = $expense['id'];
                                        if (isset($settlementMap[$expId])) {
                                            $sm = $settlementMap[$expId];
                                            $sColor = $sm['settlement_status'] === 'settled' ? '#16a34a' : ($sm['settlement_status'] === 'partial' ? '#d97706' : '#dc2626');
                                            $sBg = $sm['settlement_status'] === 'settled' ? '#f0fdf4' : ($sm['settlement_status'] === 'partial' ? '#fffbeb' : '#fef2f2');
                                            $sLabel = $sm['settlement_status'] === 'settled' ? 'Settled' : ($sm['settlement_status'] === 'partial' ? number_format((float)$sm['amount_owed'], 2) . ' left' : 'Pending');
                                             echo ' <span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:50px;background:' . $sBg . ';color:' . $sColor . ';border:1px solid ' . $sColor . '30;vertical-align:middle;" title="Agency: ' . htmlspecialchars($sm['agency_name']) . '">';
                                            echo '<i class="feather icon-link" style="font-size:9px;"></i>' . $sLabel;
                                            echo '</span>';
                                        }
                                        echo '</td>';
                                        echo '<td class="ccy-col">' . ($expense['currency'] ?? 'USD') . '</td>';
                                        echo '<td class="actions-col">';
                                        if ($isGlobal) {
                                            echo '<span class="allocation-link">Managed from <a href="global_budget_allocation.php">Global Allocation</a></span>';
                                        } elseif ($isBudgetAlloc) {
                                            echo '<span class="allocation-link">Managed from <a href="budget_allocations.php">Budget Allocation</a></span>';
                                        } else {
                                            // Check if already linked to agency
                                            $isLinked = isset($settlementMap[$expense['id']]);
                                            echo '<div class="btn-group-wrap">';
                                            $expenseCategoryId = $isChild ? ($category['parent_id'] ?? $category['id']) : $category['id'];
                                            if (!$isLinked) {
                                                echo '<button class="btn-action-edit link-agency" data-id="' . $expense['id'] . '" data-amount="' . $expense['amount'] . '" data-currency="' . ($expense['currency'] ?? 'USD') . '" title="Link to Agency" style="color:#d97706;"><i class="feather icon-link"></i></button>';
                                            }
                                            echo '<button class="btn-action-edit edit-expense" data-id="' . $expense['id'] . '" data-category="' . $expenseCategoryId . '" data-sub-category="' . ($expense['sub_category_id'] ?? '') . '" data-date="' . $expense['date'] . '" data-description="' . htmlspecialchars($expense['description']) . '" data-amount="' . $expense['amount'] . '" data-currency="' . ($expense['currency'] ?? 'USD') . '" data-main-account="' . ($expense['main_account_id'] ?? '') . '" title="Edit"><i class="feather icon-edit-2"></i></button>';
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

                            // Render top-level categories with their sub-categories nested underneath
                            foreach ($categories as $category) {
                                if (!empty($category['parent_id'])) {
                                    continue; // rendered under its parent
                                }
                                $children = $childrenByParent[$category['id']] ?? [];
                                echo '<div class="category-group">';
                                render_expense_category_card($category, $pdo, $_SESSION['tenant_id'] ?? 1, $branch_id, $children);
                                if ($children) {
                                    echo '<div class="category-children">';
                                    foreach ($children as $child) {
                                        render_expense_category_card($child, $pdo, $_SESSION['tenant_id'] ?? 1, $branch_id, [], true, $category['name']);
                                    }
                                    echo '</div>';
                                }
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
<?php include '../modals/expense/agency_settlement_modal.php'; ?>

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