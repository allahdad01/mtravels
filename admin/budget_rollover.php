<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
// Database connection
require_once('../includes/db.php');

// Include database security module for input validation
require_once 'includes/db_security.php';
require_once 'includes/logger.php';

// Get current month and previous month
$currentMonth = date('m');
$currentYear = date('Y');
$previousMonth = date('m', strtotime('-1 month'));
$previousYear = date('Y', strtotime('-1 month'));

// If manual month/year are specified, use those instead
$manualRollover = false;
if (isset($_POST['rollover_month']) && isset($_POST['rollover_year'])) {
    $previousMonth = $_POST['rollover_month'];
    $previousYear = $_POST['rollover_year'];
    $manualRollover = true;
}

// Create date range for previous month
$previousMonthStart = $previousYear . '-' . $previousMonth . '-01';
$previousMonthEnd = date('Y-m-t', strtotime($previousMonthStart));

// Current month date
$currentMonthDate = date('Y-m-d');

// Response array
$response = [
    'success' => false,
    'message' => '',
    'rollovers' => []
];

// Process rollover if requested
if (isset($_POST['process_rollover'])) {
    // Begin transaction for safety
    $pdo->beginTransaction();
    
    try {
        // Get all allocations from previous month with remaining funds
        $stmt = $pdo->prepare("
            SELECT * FROM budget_allocations 
            WHERE allocation_date BETWEEN ? AND ? 
            AND remaining_amount > 0
            AND tenant_id = ?
        ");
        $stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($allocations)) {
            $response['message'] = "No remaining budget found from " . date('F Y', strtotime($previousMonthStart)) . " to roll over.";
        } else {
            // Process each allocation with remaining funds
            foreach ($allocations as $allocation) {
                // Create a new allocation for the current month
                $stmt = $pdo->prepare("
                    INSERT INTO budget_allocations 
                    (main_account_id, category_id, allocated_amount, remaining_amount, currency, allocation_date, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $description = "Rollover from " . date('F Y', strtotime($previousMonthStart)) . 
                               " - " . $allocation['description'];
                
                $stmt->execute([
                    $allocation['main_account_id'],
                    $allocation['category_id'],
                    $allocation['remaining_amount'], // Only transfer the remaining amount
                    $allocation['remaining_amount'],
                    $allocation['currency'],
                    $currentMonthDate,
                    $description
                ]);
                
                $newAllocationId = $pdo->lastInsertId();
                
                // Set the remaining amount of the previous allocation to zero
                $stmt = $pdo->prepare("
                    UPDATE budget_allocations 
                    SET remaining_amount = 0, 
                        description = CONCAT(description, ' (Rolled over to allocation #$newAllocationId)')
                    WHERE id = ?
                ");
                $stmt->execute([$allocation['id']]);
                
                // Log this rollover
                $response['rollovers'][] = [
                    'from_id' => $allocation['id'],
                    'to_id' => $newAllocationId,
                    'amount' => $allocation['remaining_amount'],
                    'currency' => $allocation['currency'],
                    'category_id' => $allocation['category_id']
                ];
                
                // Log in system activity log
                $old_values = json_encode([
                    'id' => $allocation['id'],
                    'remaining_amount' => $allocation['remaining_amount']
                ]);
                
                $new_values = json_encode([
                    'id' => $newAllocationId,
                    'allocated_amount' => $allocation['remaining_amount'],
                    'remaining_amount' => $allocation['remaining_amount'],
                    'description' => $description
                ]);
                
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $activityStmt = $pdo->prepare("
                    INSERT INTO activity_log 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
                    VALUES (?, 'budget_rollover', 'budget_allocations', ?, ?, ?, ?, ?, NOW())
                ");
                $activityStmt->execute([$user_id, $newAllocationId, $old_values, $new_values, $ip_address, $user_agent]);
            }
            
            // Commit the transaction
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = "Successfully rolled over " . count($response['rollovers']) . 
                                  " budget allocations from " . date('F Y', strtotime($previousMonthStart)) . 
                                  " to " . date('F Y');
        }
    } catch (PDOException $e) {
        // Roll back the transaction on error
        $pdo->rollBack();
        $response['message'] = "Error: " . $e->getMessage();
    }
}

// Check if we should display the notification about pending rollovers
$pendingRollover = false;
$pendingCount = 0;

// Only check for pending rollovers if we're at the start of a new month and not doing a manual rollover
if (!$manualRollover && intval($currentMonth) !== intval($previousMonth)) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM budget_allocations 
        WHERE allocation_date BETWEEN ? AND ? 
        AND remaining_amount > 0
        AND tenant_id = ?
    ");
    $stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id]);
    $pendingCount = $stmt->fetchColumn();
    
    if ($pendingCount > 0) {
        $pendingRollover = true;
    }
}

// Database connection is closed automatically when script ends

$categoriesQuery = "SELECT * FROM expense_categories WHERE tenant_id = ? ORDER BY name";
$stmt = $pdo->prepare($categoriesQuery);
$stmt->execute([$tenant_id]); // bind the tenant_id
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesById = [];
foreach ($categories as $category) {
    $categoriesById[$category['id']] = $category['name'];
}

// Check if automatic rollover has already been done for this month
$autoRolloverDone = false;
$currentMonthStart = date('Y-m-01');
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM activity_log 
    WHERE action = 'budget_rollover' 
    AND created_at >= ?
    AND tenant_id = ?
");
$stmt->execute([$currentMonthStart, $tenant_id]);
$rolloverCount = $stmt->fetchColumn();

if ($rolloverCount > 0) {
    $autoRolloverDone = true;
}
?>

    <style>
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 8px 8px 0 0;
            background-color: #f8f9fa;
        }
        .rollover-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .rollover-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
    </style>
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
    <?php include '../includes/header.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            
                            <!-- [ Page Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Budget Rollover Management</h5>
                                            <div class="float-right">
                                                <a href="budget_allocations.php" class="btn btn-outline-primary">
                                                    <i class="feather icon-arrow-left"></i> Back to Budget Allocations
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($pendingRollover && !$autoRolloverDone): ?>
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="feather icon-alert-circle mr-2"></i> 
                                                <strong>Pending Budget Rollover:</strong> There are <?= $pendingCount ?> allocations with remaining funds from <?= date('F Y', strtotime($previousMonthStart)) ?> that need to be rolled over to the current month.
                                            </div>
                                            <div>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="process_rollover" value="1">
                                                    <input type="hidden" name="rollover_month" value="<?= $previousMonth ?>">
                                                    <input type="hidden" name="rollover_year" value="<?= $previousYear ?>">
                                                    <button type="submit" class="btn btn-warning">
                                                        <i class="feather icon-refresh-cw mr-1"></i> Process Automatic Rollover
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($response['success']): ?>
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-success">
                                        <i class="feather icon-check-circle mr-2"></i> <?= $response['message'] ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Manual Budget Rollover</h5>
                                        </div>
                                        <div class="card-body">
                                            <p>Use this form to manually roll over remaining budget from a specific month to the current month (<?= date('F Y') ?>).</p>
                                            <form method="post" id="manualRolloverForm">
                                                <div class="form-group">
                                                    <label>Select Month to Roll Over From:</label>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <select name="rollover_month" class="form-control">
                                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                                    <option value="<?= sprintf('%02d', $m) ?>" <?= $previousMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <select name="rollover_year" class="form-control">
                                                                <?php for ($y = $currentYear - 2; $y <= $currentYear; $y++): ?>
                                                                    <option value="<?= $y ?>" <?= $previousYear == $y ? 'selected' : '' ?>>
                                                                        <?= $y ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <button type="submit" name="process_rollover" value="1" class="btn btn-primary">
                                                        <i class="feather icon-refresh-cw mr-1"></i> Roll Over Remaining Budget
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>How Budget Rollover Works</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info mb-3">
                                                <h6><i class="feather icon-info mr-2"></i>Automatic Monthly Rollover</h6>
                                                <p class="mb-0">At the beginning of each month, the system checks for any budget allocations from the previous month that have remaining funds. These funds are automatically transferred to new budget allocations for the current month.</p>
                                            </div>
                                            
                                            <ul class="list-group">
                                                <li class="list-group-item">
                                                    <i class="feather icon-check text-success mr-2"></i>
                                                    Remaining funds are carried forward to the new month
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="feather icon-check text-success mr-2"></i>
                                                    Original category and account assignments are preserved
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="feather icon-check text-success mr-2"></i>
                                                    Previous month's allocations are marked as "rolled over"
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="feather icon-check text-success mr-2"></i>
                                                    All transactions are traceable through the activity log
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($response['rollovers'])): ?>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Rollover Results</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>From Allocation</th>
                                                            <th>To Allocation</th>
                                                            <th>Category</th>
                                                            <th>Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($response['rollovers'] as $rollover): ?>
                                                        <tr>
                                                            <td>#<?= $rollover['from_id'] ?></td>
                                                            <td>#<?= $rollover['to_id'] ?></td>
                                                            <td>
                                                                <?= isset($categoriesById[$rollover['category_id']]) ? htmlspecialchars($categoriesById[$rollover['category_id']]) : 'Unknown' ?>
                                                            </td>
                                                            <td>
                                                                <?= number_format($rollover['amount'], 2) ?> <?= $rollover['currency'] ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- [ Page Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

</body>
</html> 