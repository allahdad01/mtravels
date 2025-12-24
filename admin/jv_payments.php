<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include the security module
require_once('security.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication for this page
enforce_auth(['admin', 'finance']);



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Get all clients
$clientsQuery = "SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name";
$clientsStmt = $pdo->prepare($clientsQuery);
$clientsStmt->execute([$tenant_id, $branch_id]);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all suppliers
$suppliersQuery = "SELECT id, name, balance, currency FROM suppliers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY name";
$suppliersStmt = $pdo->prepare($suppliersQuery);
$suppliersStmt->execute([$tenant_id, $branch_id]);
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " AND (
        jp.jv_name LIKE ? OR
        c.name LIKE ? OR
        s.name LIKE ? OR
        jp.receipt LIKE ?
    )";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM jv_payments jp
              LEFT JOIN clients c ON jp.client_id = c.id
              LEFT JOIN suppliers s ON jp.supplier_id = s.id
              WHERE jp.tenant_id = ? AND jp.branch_id = ?" . $search_condition;
$countParams = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $countParams = array_merge($countParams, array_fill(0, 4, $search_param));
}
try {
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total_records = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $items_per_page);
} catch (PDOException $e) {
    error_log("Error counting JV payments: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get all JV payments with pagination
$jvPaymentsQuery = "SELECT jp.*, u.name as created_by_name
                    FROM jv_payments jp
                    LEFT JOIN users u ON jp.created_by = u.id
                    WHERE jp.tenant_id = ? AND jp.branch_id = ?
                    ORDER BY jp.created_at DESC";
try {
    $jvPaymentsStmt = $pdo->prepare($jvPaymentsQuery);
    $jvPaymentsStmt->execute([$tenant_id, $branch_id]);
    $jvPayments = $jvPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching JV payments: " . $e->getMessage());
    $jvPayments = [];
}
    
?>




    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">
    <link rel="stylesheet" href="../css/jv_payment/styles.css">

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
                                            <h5><?= __('jv_payments_management') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($success_message): ?>
                                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                    <?php echo $success_message; ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($error_message): ?>
                                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                    <?php echo $error_message; ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Modern Header Section -->
                                            <div class="modern-header mb-4">
                                                <div class="header-content">
                                                    <div class="header-text">
                                                        <h4 class="mb-2">
                                                            <i class="feather icon-credit-card mr-2"></i>
                                                            <?= __('client_to_supplier_payment_management') ?>
                                                        </h4>
                                                        <p class="text-muted mb-0">Manage direct payments between clients and suppliers</p>
                                                    </div>
                                                    <div class="header-actions">
                                                        <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addClientSupplierModal">
                                                            <i class="feather icon-plus-circle mr-2"></i>
                                                            <span class="btn-text"><?= __('add_new_payment') ?></span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="header-stats">
                                                    <div class="stat-card">
                                                        <div class="stat-icon">
                                                            <i class="feather icon-users"></i>
                                                        </div>
                                                        <div class="stat-info">
                                                            <span class="stat-number"><?php echo count($clients); ?></span>
                                                            <span class="stat-label">Active Clients</span>
                                                        </div>
                                                    </div>
                                                    <div class="stat-card">
                                                        <div class="stat-icon">
                                                            <i class="feather icon-shopping-bag"></i>
                                                        </div>
                                                        <div class="stat-info">
                                                            <span class="stat-number"><?php echo count($suppliers); ?></span>
                                                            <span class="stat-label">Active Suppliers</span>
                                                        </div>
                                                    </div>
                                                    <div class="stat-card">
                                                        <div class="stat-icon">
                                                            <i class="feather icon-activity"></i>
                                                        </div>
                                                        <div class="stat-info">
                                                            <span class="stat-number"><?php echo isset($csPayments) ? count($csPayments) : 0; ?></span>
                                                            <span class="stat-label">Total Payments</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Client to Supplier Payment Content -->
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?= __('client_supplier_jv_guide') ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="alert alert-info">
                                                        <h6><?= __('client_to_supplier_jv_payment') ?></h6>
                                                        <p><?= __('this_process_allows_clients_to_pay_suppliers_directly_from_their_account_balance_without_using_a_main_account') ?></p>
                                                        <ul>
                                                            <li><?= __('client_balance_will_be_reduced_by_the_specified_amount') ?></li>
                                                            <li><?= __('supplier_balance_will_be_increased_by_the_equivalent_amount') ?></li>
                                                            <li><?= __('if_currencies_differ_the_exchange_rate_will_be_used_for_conversion') ?></li>
                                                            <li><?= __('transactions_will_be_recorded_for_both_client_and_supplier') ?></li>
                                                        </ul>
                                                    </div>
                                                    <div class="alert alert-warning">
                                                        <h6><?= __('important_notes') ?></h6>
                                                        <ul>
                                                            <li><?= __('the_exchange_rate_is_critical_when_the_client_and_supplier_use_different_currencies') ?></li>
                                                            <li><?= __('always_verify_client_and_supplier_details_before_confirming_the_transaction') ?></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Modern Payment History Table -->
                                             <div class="card">
                                                 <div class="card-header">
                                                     <div class="d-flex justify-content-between align-items-center">
                                                         <div>
                                                             <h5 class="mb-0">
                                                                 <i class="feather icon-list mr-2"></i>
                                                                 <?= __('payment_history') ?>
                                                             </h5>
                                                             <small class="text-muted">View and manage all client-supplier payments</small>
                                                         </div>
                                                         <div class="table-actions">
                                                             <button class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">
                                                                 <i class="feather icon-refresh-cw mr-1"></i> Refresh
                                                             </button>
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Search Bar -->
                                                 <div class="card-body border-bottom pb-3">
                                                     <form method="GET" class="form-inline">
                                                         <div class="form-group mb-0 flex-grow-1">
                                                             <input 
                                                                 type="text" 
                                                                 name="search" 
                                                                 class="form-control w-100" 
                                                                 placeholder="Search by JV name, client, supplier, receipt..." 
                                                                 value="<?= htmlspecialchars($search_query) ?>"
                                                             >
                                                         </div>
                                                         <button type="submit" class="btn btn-info ml-2">
                                                             <i class="feather icon-search"></i> Search
                                                         </button>
                                                         <?php if (!empty($search_query)): ?>
                                                             <a href="jv_payments.php" class="btn btn-secondary ml-2">
                                                                 <i class="feather icon-x"></i> Clear
                                                             </a>
                                                         <?php endif; ?>
                                                     </form>
                                                 </div>

                                                 <div class="card-body p-0">
                                                     <!-- Pagination Info -->
                                                     <div class="row mb-3 p-3">
                                                         <div class="col-md-6">
                                                             <small class="text-muted">
                                                                 Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                             </small>
                                                         </div>
                                                     </div>
                                                    <div class="table-responsive">
                                                        <table class="table table-hover" id="clientSupplierTable">
                                                            <thead>
                                                                <tr>
                                                                    <th>
                                                                        <i class="feather icon-calendar mr-1"></i>
                                                                        <?= __('date') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-file-text mr-1"></i>
                                                                        <?= __('jv_name') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-user mr-1"></i>
                                                                        <?= __('client') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-shopping-bag mr-1"></i>
                                                                        <?= __('supplier') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-dollar-sign mr-1"></i>
                                                                        <?= __('amount') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-globe mr-1"></i>
                                                                        <?= __('currency') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-hash mr-1"></i>
                                                                        <?= __('receipt') ?>
                                                                    </th>
                                                                    <th>
                                                                        <i class="feather icon-settings mr-1"></i>
                                                                        <?= __('actions') ?>
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                 // Get client-supplier JV payments with pagination and search
                                                                 $csQuery = "SELECT jp.*, c.name as client_name, s.name as supplier_name
                                                                             FROM jv_payments jp
                                                                             LEFT JOIN clients c ON jp.client_id = c.id
                                                                             LEFT JOIN suppliers s ON jp.supplier_id = s.id
                                                                             WHERE jp.tenant_id = ? AND jp.branch_id = ?" . $search_condition . "
                                                                             ORDER BY jp.created_at DESC
                                                                             LIMIT ? OFFSET ?";
                                                                 try {
                                                                     $csStmt = $pdo->prepare($csQuery);
                                                                     $params = [$tenant_id, $branch_id];
                                                                     if (!empty($search_query)) {
                                                                         $search_param = '%' . $search_query . '%';
                                                                         $params = array_merge($params, array_fill(0, 4, $search_param));
                                                                     }
                                                                     $params[] = $items_per_page;
                                                                     $params[] = $offset;
                                                                     $csStmt->execute($params);
                                                                     $csPayments = $csStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                 } catch (PDOException $e) {
                                                                     error_log("Error fetching client-supplier payments: " . $e->getMessage());
                                                                     $csPayments = [];
                                                                 }

                                                                if (empty($csPayments)): ?>
                                                                    <tr>
                                                                        <td colspan="8" class="text-center py-5">
                                                                            <div class="empty-state">
                                                                                <i class="feather icon-inbox display-4 text-muted mb-3"></i>
                                                                                <h6 class="text-muted">No payments found</h6>
                                                                                <p class="text-muted small">Start by adding your first client-supplier payment</p>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php else:
                                                                    foreach ($csPayments as $payment): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="date-cell">
                                                                                <strong><?= date('M d, Y', strtotime($payment['created_at'])) ?></strong>
                                                                                <br>
                                                                                <small class="text-muted"><?= date('H:i', strtotime($payment['created_at'])) ?></small>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="jv-name-cell">
                                                                                <span class="mb-1">JV</span>
                                                                                <br>
                                                                                <strong><?= htmlspecialchars($payment['jv_name']) ?></strong>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="client-cell">
                                                                                <i class="feather icon-user text-primary mr-1"></i>
                                                                                <?= htmlspecialchars($payment['client_name'] ?? 'N/A') ?>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="supplier-cell">
                                                                                <i class="feather icon-shopping-bag text-success mr-1"></i>
                                                                                <?= htmlspecialchars($payment['supplier_name'] ?? 'N/A') ?>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="amount-cell">
                                                                                <strong class="text-success">
                                                                                    <?= number_format($payment['total_amount'], 2) ?>
                                                                                </strong>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <span class="<?= $payment['currency'] === 'USD' ? 'info' : 'warning' ?>">
                                                                                <?= htmlspecialchars($payment['currency']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <code class="receipt-code">
                                                                                <?= htmlspecialchars($payment['receipt']) ?>
                                                                            </code>
                                                                        </td>
                                                                        <td>
                                                                             <div class="dropdown">
                                                                                 <button class="btn btn-icon btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                                     <i class="feather icon-more-horizontal"></i>
                                                                                 </button>
                                                                                 <div class="dropdown-menu dropdown-menu-right">
                                                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="document.querySelector('.view-cs-btn[data-id=\'' + '<?= htmlspecialchars($payment['id']) ?>' + '\']')?.click();">
                                                                                         <i class="feather icon-eye mr-2"></i>View Details
                                                                                     </a>
                                                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="document.querySelector('.edit-cs-btn[data-id=\'' + '<?= htmlspecialchars($payment['id']) ?>' + '\']')?.click();">
                                                                                         <i class="feather icon-edit-2 mr-2"></i>Edit
                                                                                     </a>
                                                                                     <div class="dropdown-divider"></div>
                                                                                     <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="document.querySelector('.delete-cs-btn[data-id=\'' + '<?= htmlspecialchars($payment['id']) ?>' + '\']')?.click();">
                                                                                         <i class="feather icon-trash-2 mr-2"></i>Delete
                                                                                     </a>
                                                                                 </div>
                                                                             </div>
                                                                             <!-- Hidden buttons to maintain existing functionality -->
                                                                             <button style="display:none;" type="button" class="btn btn-info btn-sm view-cs-btn"
                                                                                     data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                 <i class="feather icon-eye"></i>
                                                                             </button>
                                                                             <button style="display:none;" type="button" class="btn btn-warning btn-sm edit-cs-btn"
                                                                                     data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                 <i class="feather icon-edit-2"></i>
                                                                             </button>
                                                                             <button style="display:none;" type="button" class="btn btn-danger btn-sm delete-cs-btn"
                                                                                     data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                 <i class="feather icon-trash-2"></i>
                                                                             </button>
                                                                         </td>
                                                                    </tr>
                                                                <?php endforeach;
                                                                endif; ?>
                                                            </tbody>
                                                        </table>
                                                        </div>
                                                        <!-- Pagination Controls -->
                                                        <div class="row mt-4 p-3">
                                                        <div class="col-md-12">
                                                            <nav aria-label="Page navigation">
                                                                <ul class="pagination justify-content-center">
                                                                    <?php
                                                                    // Helper function to build pagination links with search parameter
                                                                    $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                                                                    ?>
                                                                    <?php if ($current_page > 1): ?>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=1<?= $search_param ?>">First</a>
                                                                        </li>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $search_param ?>">Previous</a>
                                                                        </li>
                                                                    <?php else: ?>
                                                                        <li class="page-item disabled">
                                                                            <span class="page-link">First</span>
                                                                        </li>
                                                                        <li class="page-item disabled">
                                                                            <span class="page-link">Previous</span>
                                                                        </li>
                                                                    <?php endif; ?>

                                                                    <?php
                                                                    // Show page numbers
                                                                    $start_page = max(1, $current_page - 2);
                                                                    $end_page = min($total_pages, $current_page + 2);

                                                                    if ($start_page > 1):
                                                                    ?>
                                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                                    <?php endif; ?>

                                                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                                        <?php if ($i == $current_page): ?>
                                                                            <li class="page-item active"><span class="page-link"><?= $i ?></span></li>
                                                                        <?php else: ?>
                                                                            <li class="page-item"><a class="page-link" href="?page=<?= $i ?><?= $search_param ?>"><?= $i ?></a></li>
                                                                        <?php endif; ?>
                                                                    <?php endfor; ?>

                                                                    <?php if ($end_page < $total_pages): ?>
                                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                                    <?php endif; ?>

                                                                    <?php if ($current_page < $total_pages): ?>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $search_param ?>">Next</a>
                                                                        </li>
                                                                        <li class="page-item">
                                                                            <a class="page-link" href="?page=<?= $total_pages ?><?= $search_param ?>">Last</a>
                                                                        </li>
                                                                    <?php else: ?>
                                                                        <li class="page-item disabled">
                                                                            <span class="page-link">Next</span>
                                                                        </li>
                                                                        <li class="page-item disabled">
                                                                            <span class="page-link">Last</span>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </nav>
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
            </div>
        </div>
    </div>

    <!-- Delete JV Payment Modal -->
    <div class="modal fade" id="deleteJvModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('delete_jv_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="<?= $redirect_url ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p><?= __('are_you_sure_you_want_to_delete_this_jv_payment') ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <!-- Modern Add Client-Supplier Payment Modal -->
    <div class="modal fade" id="addClientSupplierModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-gradient-primary text-white border-0">
                    <div class="modal-title-section">
                        <h5 class="modal-title mb-1">
                            <i class="feather icon-plus-circle mr-2"></i>
                            <?= __('add_client_to_supplier_payment') ?>
                        </h5>
                        <p class="modal-subtitle mb-0 small opacity-75">Create a direct payment between client and supplier</p>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv.php" id="clientSupplierForm">
                    <div class="modal-body p-4">
                        <!-- JV Name Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title">
                                <i class="feather icon-file-text mr-2"></i>
                                Payment Information
                            </h6>
                            <div class="form-group">
                                <label for="jv_name" class="form-label">
                                    <i class="feather icon-tag mr-1"></i>
                                    <?= __('jv_name') ?>
                                </label>
                                <input type="text" class="form-control form-control-lg" id="jv_name" name="jv_name"
                                       value="Client-Supplier Payment" required
                                       placeholder="Enter payment name">
                                <small class="form-text text-muted">A descriptive name for this payment transaction</small>
                            </div>
                        </div>

                        <!-- Parties Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title">
                                <i class="feather icon-users mr-2"></i>
                                Transaction Parties
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="client_id" class="form-label">
                                            <i class="feather icon-user mr-1"></i>
                                            <?= __('client') ?>
                                        </label>
                                        <select class="form-control form-control-lg" id="client_id" name="client_id" required>
                                            <option value=""><?= __('select_client') ?></option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= $client['id'] ?>" data-usd-balance="<?= $client['usd_balance'] ?>" data-afs-balance="<?= $client['afs_balance'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($client['name']) ?></span>
                                                        <small class="text-muted">
                                                            USD: <?= number_format($client['usd_balance'], 2) ?>,
                                                            AFS: <?= number_format($client['afs_balance'], 2) ?>
                                                        </small>
                                                    </div>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Client account to debit from</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_id" class="form-label">
                                            <i class="feather icon-shopping-bag mr-1"></i>
                                            <?= __('supplier') ?>
                                        </label>
                                        <select class="form-control form-control-lg" id="supplier_id" name="supplier_id" required>
                                            <option value=""><?= __('select_supplier') ?></option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?= $supplier['id'] ?>" data-currency="<?= $supplier['currency'] ?>" data-balance="<?= $supplier['balance'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><?= htmlspecialchars($supplier['name']) ?></span>
                                                        <small class="text-muted">
                                                            <?= number_format($supplier['balance'], 2) ?> <?= $supplier['currency'] ?>
                                                        </small>
                                                    </div>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">Supplier account to credit to</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Amount & Currency Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title">
                                <i class="feather icon-dollar-sign mr-2"></i>
                                Amount & Currency
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="currency" class="form-label">
                                            <i class="feather icon-globe mr-1"></i>
                                            <?= __('currency') ?>
                                        </label>
                                        <select class="form-control form-control-lg" id="currency" name="currency" required>
                                            <option value="USD">USD - US Dollar</option>
                                            <option value="AFS">AFS - Afghani</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="total_amount" class="form-label">
                                            <i class="feather icon-credit-card mr-1"></i>
                                            <?= __('amount') ?>
                                        </label>
                                        <input type="number" step="0.01" class="form-control form-control-lg"
                                               id="total_amount" name="total_amount" required
                                               placeholder="0.00">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="exchange_rate" class="form-label">
                                            <i class="feather icon-trending-up mr-1"></i>
                                            <?= __('exchange_rate') ?>
                                        </label>
                                        <input type="number" step="0.00001" class="form-control form-control-lg"
                                               id="exchange_rate" name="exchange_rate"
                                               placeholder="1.00000">
                                        <small class="form-text text-muted">Required if currencies differ</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Details Section -->
                        <div class="form-section mb-4">
                            <h6 class="section-title">
                                <i class="feather icon-info mr-2"></i>
                                Additional Details
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="receipt" class="form-label">
                                            <i class="feather icon-hash mr-1"></i>
                                            <?= __('receipt_number') ?>
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="receipt" name="receipt" required
                                               placeholder="Enter receipt number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="remarks" class="form-label">
                                            <i class="feather icon-message-square mr-1"></i>
                                            <?= __('remarks') ?>
                                        </label>
                                        <textarea class="form-control form-control-lg" id="remarks" name="remarks"
                                                  rows="2" placeholder="Optional notes or remarks"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Preview -->
                        <div class="balance-preview bg-light rounded p-3 mb-3" id="balancePreview" style="display: none;">
                            <h6 class="mb-3">
                                <i class="feather icon-eye mr-2"></i>
                                Transaction Preview
                            </h6>
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <div class="balance-item">
                                        <small class="text-muted d-block">Client Balance After</small>
                                        <span class="client-balance-preview font-weight-bold text-primary">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="balance-item">
                                        <small class="text-muted d-block">Supplier Balance After</small>
                                        <span class="supplier-balance-preview font-weight-bold text-success">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-lg" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i>
                            <?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="feather icon-check-circle mr-2"></i>
                            <?= __('process_payment') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Client-Supplier Payment Modal -->
    <div class="modal fade" id="deleteCsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('delete_client_supplier_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv_delete.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_cs_id">
                        <p><?= __('are_you_sure_you_want_to_delete_this_payment') ?> <?= __('this_action_will') ?>:</p>
                        <ul>
                            <li><?= __('return_funds_to_the_client_account') ?></li>
                            <li><?= __('deduct_the_amount_from_the_supplier_balance') ?></li>
                            <li><?= __('delete_all_associated_transaction_records') ?></li>
                        </ul>
                        <p class="text-danger"><strong><?= __('this_action_cannot_be_undone') ?></strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    

                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>


    <script>
    // Button Protection System for JV Payments
    class JvButtonProtection {
        constructor() {
            this.init();
        }

        init() {
            this.protectFormButtons();
            this.protectDeleteButtons();
        }

        protectButton(button, loadingText = 'Processing...', duration = 2000) {
            if (button && !button.disabled) {
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${loadingText}`;

                setTimeout(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }, duration);
            }
        }

        protectFormButtons() {
            // Protect form submit buttons
            const formButtons = [
                { formId: '#clientSupplierForm', buttonSelector: 'button[type="submit"]', text: 'Processing Payment...' },
                { formId: '#editClientSupplierForm', buttonSelector: 'button[type="submit"]', text: 'Updating Payment...' }
            ];

            formButtons.forEach(({ formId, buttonSelector, text }) => {
                const form = document.querySelector(formId);
                if (form) {
                    const button = form.querySelector(buttonSelector);
                    if (button) {
                        form.addEventListener('submit', () => {
                            this.protectButton(button, text, 3000);
                        });
                    }
                }
            });
        }

        protectDeleteButtons() {
            // Protect delete buttons
            document.addEventListener('click', (e) => {
                if (e.target.closest('.delete-cs-btn')) {
                    const button = e.target.closest('button');
                    if (button) {
                        this.protectButton(button, 'Deleting...', 2000);
                    }
                }
            });
        }
    }

    // Initialize button protection
    const jvButtonProtection = new JvButtonProtection();

    $(document).ready(function() {
        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#clientSupplierTable').DataTable({
                responsive: true,
                order: [[0, 'desc']],
                autoWidth: false,
                scrollX: true,
                columnDefs: [
                    { responsivePriority: 1, targets: [1, 2, 3] }, // JV Name, Client, Supplier
                    { responsivePriority: 2, targets: [4, 7] },    // Amount, Actions
                    { responsivePriority: 3, targets: [0, 5, 6] }  // Date, Currency, Receipt
                ]
            });
            
            // Add click handler for table rows on small screens
            $('#clientSupplierTable tbody').on('click', 'tr', function(e) {
                // Only trigger if we're in responsive mode and not clicking on a button
                if ($(window).width() < 768 && !$(e.target).closest('button').length) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Find the ID from the row's view button
                    const viewBtn = $(this).find('.view-cs-btn');
                    if (viewBtn.length) {
                        const paymentId = viewBtn.data('id');
                        // Trigger the view button click
                        $('.view-cs-btn[data-id="' + paymentId + '"]').trigger('click');
                    }
                }
            });
        }

        // Enhance View Client-Supplier Payment
        $('.view-cs-btn').off('click').on('click', function() {
            const jvId = $(this).data('id');
            // Show modal with loading spinner
            $('#viewCsModal').modal('show');

            $.ajax({
                url: 'get_jv_payment.php',
                type: 'GET',
                data: { id: jvId, type: 'client_supplier' },
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        $('#viewCsModal .modal-body').html(
                            '<div class="alert alert-danger m-3">' +
                            '<i class="feather icon-alert-triangle mr-2"></i>' +
                            'Error: ' + response.message +
                            '</div>'
                        );
                        return;
                    }
                    
                    const p = response.payment;
                    
                    // Format numbers and dates
                    const formattedAmount = parseFloat(p.total_amount).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    const createdDate = new Date(p.created_at);
                    const formattedDate = createdDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Calculate time elapsed
                    const now = new Date();
                    const diffTime = Math.abs(now - createdDate);
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                    const timeElapsed = diffDays === 0 ? 'Today' : 
                                        diffDays === 1 ? 'Yesterday' : 
                                        diffDays + ' days ago';
                    
                    // Build new clean modal content
                    let html = `
                    <div class="payment-details">
                        <!-- Header with payment summary -->
                        <div class="payment-header bg-light p-4">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <span class="mb-2">ID: ${p.id}</span>
                                    <h4 class="mb-1">${$('<div>').text(p.jv_name).html()}</h4>
                                    <div class="text-muted">
                                        <i class="feather icon-calendar mr-1"></i> ${formattedDate}
                                        <span class="ml-2">${timeElapsed}</span>
                                    </div>
                                </div>
                                <div class="col-md-5 text-md-right mt-3 mt-md-0">
                                    <h3 class="text-success mb-0">${formattedAmount} ${p.currency}</h3>
                                    <div class="text-muted small">
                                        <i class="feather icon-file-text mr-1"></i> ${p.receipt || 'No receipt'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Main content -->
                        <div class="p-4">
                            <!-- Transaction parties -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="icon-box bg-primary text-white rounded-circle p-2 mr-3">
                                                    <i class="feather icon-user"></i>
                                                </div>
                                                <h6 class="mb-0"><?= __('client') ?></h6>
                                            </div>
                                            <h5>${$('<div>').text(p.client_name || 'N/A').html()}</h5>
                                            <div class="text-muted small"><?= __('paid_from') ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="icon-box bg-success text-white rounded-circle p-2 mr-3">
                                                    <i class="feather icon-shopping-bag"></i>
                                                </div>
                                                <h6 class="mb-0"><?= __('supplier') ?></h6>
                                            </div>
                                            <h5>${$('<div>').text(p.supplier_name || 'N/A').html()}</h5>
                                            <div class="text-muted small"><?= __('paid_to') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment details -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-light border-0">
                                    <h6 class="mb-0"><i class="feather icon-info mr-2"></i><?= __('payment_details') ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('exchange_rate') ?></div>
                                            <div class="font-weight-bold">${p.exchange_rate || 'N/A'}</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('created_by') ?></div>
                                            <div class="font-weight-bold">${p.created_by_name || 'System'}</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('updated_at') ?></div>
                                            <div class="font-weight-bold">${p.updated_at ? new Date(p.updated_at).toLocaleString() : 'N/A'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Remarks -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-0">
                                    <h6 class="mb-0"><i class="feather icon-message-square mr-2"></i><?= __('remarks') ?></h6>
                                </div>
                                <div class="card-body">
                                    ${p.remarks ? 
                                      `<p class="mb-0">${$('<div>').text(p.remarks).html()}</p>` : 
                                      `<p class="text-muted font-italic mb-0"><?= __('no_remarks_provided') ?></p>`
                                    }
                                </div>
                            </div>
                        </div>
                    </div>`;
                    
                    // Update modal content
                    $('#viewCsModal .modal-body').html(html);
                    
                    // Add payment ID to modal title
                    $('#viewCsModal .modal-title').html(
                        `<i class="feather icon-credit-card mr-2"></i><?= __('payment_details') ?> <span class="badge-light ml-2">ID: ${p.id}</span>`
                    );
                    
                    // Show action buttons and set their click handlers
                    $('.edit-payment-btn')
                        .removeClass('d-none')
                        .off('click')
                        .on('click', function() {
                            $('#viewCsModal').modal('hide');
                            $('.edit-cs-btn[data-id="' + p.id + '"]').trigger('click');
                        });
                        
                    $('.delete-payment-btn')
                        .removeClass('d-none')
                        .off('click')
                        .on('click', function() {
                            $('#viewCsModal').modal('hide');
                            $('.delete-cs-btn[data-id="' + p.id + '"]').trigger('click');
                        });
                },
                error: function(xhr, status, error) {
                    $('#viewCsModal .modal-body').html(
                        '<div class="alert alert-danger m-3">' +
                        '<i class="feather icon-alert-triangle mr-2"></i>' +
                        '<?= __('failed_to_load_details') ?>: ' + $('<div>').text(error).html() +
                        '<br><small><?= __('please_try_again_or_contact_support_if_the_issue_persists') ?></small>' +
                        '</div>'
                    );
                }
            });
        });

        // Edit Client-Supplier Payment
        $('.edit-cs-btn').click(function() {
            const jvId = $(this).data('id');
            
            // AJAX call to get JV payment details
            $.ajax({
                url: 'get_jv_payment.php',
                type: 'GET',
                data: { id: jvId, type: 'client_supplier' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const payment = response.payment;
                        console.log("Payment data received:", payment); // Debug log
                        
                        // Check if this is a valid client-supplier payment
                        if (!payment.client_id || !payment.supplier_id) {
                            alert("<?= __('this_record_is_missing_client_or_supplier_information_and_cannot_be_edited_as_a_client_supplier_payment') ?>");
                            return;
                        }
                        
                        // Populate form fields for editing
                        $('#edit_id').val(payment.id);
                        $('#edit_jv_name').val(payment.jv_name);
                        
                        // Set client dropdown
                        if (payment.client_id) {
                            console.log("Client dropdown before:", $('#edit_client_id').val());
                            
                            // Check if the option exists, if not add it
                            if ($('#edit_client_id option[value="' + payment.client_id + '"]').length === 0) {
                                console.log("Adding client option for ID:", payment.client_id);
                                // Add the current client as an option if it doesn't exist
                                $('#edit_client_id').append(
                                    $('<option>', {
                                        value: payment.client_id,
                                        text: payment.client_name || '<?= __('client') ?> #' + payment.client_id
                                    })
                                );
                            }
                            
                            // Set the value after ensuring option exists
                            $('#edit_client_id').val(payment.client_id);
                            console.log("Client dropdown after:", $('#edit_client_id').val());
                        }
                        
                        // Set supplier dropdown
                        if (payment.supplier_id) {
                            console.log("Supplier dropdown before:", $('#edit_supplier_id').val());
                            
                            // Check if the option exists, if not add it
                            if ($('#edit_supplier_id option[value="' + payment.supplier_id + '"]').length === 0) {
                                console.log("Adding supplier option for ID:", payment.supplier_id);
                                // Add the current supplier as an option if it doesn't exist
                                $('#edit_supplier_id').append(
                                    $('<option>', {
                                        value: payment.supplier_id,
                                        text: payment.supplier_name || '<?= __('supplier') ?> #' + payment.supplier_id,
                                        'data-currency': payment.currency // Use payment currency as fallback
                                    })
                                );
                            }
                            
                            // Set the value after ensuring option exists
                            $('#edit_supplier_id').val(payment.supplier_id);
                            console.log("Supplier dropdown after:", $('#edit_supplier_id').val());
                        }
                        
                        // Set other fields
                        console.log("Setting currency to:", payment.currency);
                        $('#edit_currency').val(payment.currency);
                        
                        console.log("Setting total_amount to:", payment.total_amount);
                        $('#edit_total_amount').val(payment.total_amount);
                        
                        console.log("Setting exchange_rate to:", payment.exchange_rate);
                        $('#edit_exchange_rate').val(payment.exchange_rate);
                        
                        console.log("Setting receipt to:", payment.receipt);
                        $('#edit_receipt').val(payment.receipt);
                        
                        console.log("Setting remarks to:", payment.remarks);
                        $('#edit_remarks').val(payment.remarks);
                        
                        // Verify all fields are set
                        console.log("Final field values:");
                        console.log("- client_id:", $('#edit_client_id').val());
                        console.log("- supplier_id:", $('#edit_supplier_id').val());
                        console.log("- currency:", $('#edit_currency').val());
                        console.log("- total_amount:", $('#edit_total_amount').val());
                        console.log("- exchange_rate:", $('#edit_exchange_rate').val());
                        console.log("- receipt:", $('#edit_receipt').val());
                        console.log("- remarks:", $('#edit_remarks').val());
                        
                        // Force currency change event to ensure exchange rate visibility is correct
                        $('#edit_currency').trigger('change');
                        
                        // Update exchange rate visibility
                        updateEditExchangeRateVisibility();
                        
                        // Show the modal
                        $('#editClientSupplierModal').modal('show');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.error("Response:", xhr.responseText);
                    alert('<?= __('error') ?>: <?= __('failed_to_load_payment_details_for_editing') ?>');
                }
            });
        });

        // Delete Client-Supplier Payment
        $('.delete-cs-btn').click(function() {
            const jvId = $(this).data('id');
            $('#delete_cs_id').val(jvId);
            $('#deleteCsModal').modal('show');
        });

        // Client-Supplier form validation
        $('#clientSupplierForm').submit(function(e) {
            const clientId = $('#client_id').val();
            const supplierId = $('#supplier_id').val();
            const amount = parseFloat($('#total_amount').val());
            const currency = $('#currency').val();
            const exchangeRate = parseFloat($('#exchange_rate').val());
            
            if (!clientId || !supplierId) {
                alert('<?= __('please_select_both_client_and_supplier') ?>');
                e.preventDefault();
                return false;
            }
            
            if (isNaN(amount) || amount <= 0) {
                alert('<?= __('please_enter_a_valid_amount_greater_than_zero') ?>');
                e.preventDefault();
                return false;
            }
            
            // Check if exchange rate is needed and provided
            const selectedSupplier = $('#supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            
            if (supplierCurrency !== currency && (isNaN(exchangeRate) || exchangeRate <= 0)) {
                alert('<?= __('please_enter_a_valid_exchange_rate_for_currency_conversion') ?>');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Edit Client-Supplier form validation
        $('#editClientSupplierForm').submit(function(e) {
            const clientId = $('#edit_client_id').val();
            const supplierId = $('#edit_supplier_id').val();
            const amount = parseFloat($('#edit_total_amount').val());
            const currency = $('#edit_currency').val();
            const exchangeRate = parseFloat($('#edit_exchange_rate').val());
            
            console.log("Form validation checking:");
            console.log("- clientId:", clientId);
            console.log("- supplierId:", supplierId);
            console.log("- amount:", amount);
            console.log("- currency:", currency);
            console.log("- exchangeRate:", exchangeRate);
            
            if (!clientId || !supplierId) {
                alert('<?= __('please_select_both_client_and_supplier') ?>');
                e.preventDefault();
                return false;
            }
            
            if (isNaN(amount) || amount <= 0) {
                alert('<?= __('please_enter_a_valid_amount_greater_than_zero') ?>');
                e.preventDefault();
                return false;
            }
            
            // Check if exchange rate is needed and provided
            const selectedSupplier = $('#edit_supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            
            console.log("- supplierCurrency:", supplierCurrency);
            
            if (supplierCurrency && supplierCurrency !== currency && (isNaN(exchangeRate) || exchangeRate <= 0)) {
                alert('<?= __('please_enter_a_valid_exchange_rate_for_currency_conversion') ?>');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Handle supplier and currency change events
        $('#supplier_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const supplierCurrency = selectedOption.data('currency');
            
            // Display the supplier currency in the form
            $('#supplier_currency_info').text(supplierCurrency || '');
            
            updateExchangeRateVisibility();
        });
        
        $('#cs_currency').change(function() {
            updateExchangeRateVisibility();
        });
        
        function updateExchangeRateVisibility() {
            const selectedSupplier = $('#supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            const selectedCurrency = $('#cs_currency').val();
            
            // Show exchange rate field only if currencies are different
            if (supplierCurrency && selectedCurrency && supplierCurrency !== selectedCurrency) {
                $('#cs_exchange_rate').closest('.form-group').show();
            } else {
                $('#cs_exchange_rate').closest('.form-group').hide();
            }
        }
        
        // Handle edit form supplier and currency change events
        $('#edit_supplier_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const supplierCurrency = selectedOption.data('currency');
            
            updateEditExchangeRateVisibility();
        });
        
        $('#edit_currency').change(function() {
            updateEditExchangeRateVisibility();
        });
        
        function updateEditExchangeRateVisibility() {
            const selectedSupplier = $('#edit_supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            const selectedCurrency = $('#edit_currency').val();
            
            // Show exchange rate field only if currencies are different
            if (supplierCurrency && selectedCurrency && supplierCurrency !== selectedCurrency) {
                $('#edit_exchange_rate').closest('.form-group').show();
            } else {
                $('#edit_exchange_rate').closest('.form-group').hide();
            }
        }
    });
    </script>

    <!-- View Client-Supplier Payment Modal -->
    <div class="modal fade" id="viewCsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="feather icon-credit-card mr-2"></i><?= __('payment_details') ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <!-- Content will be loaded dynamically via AJAX -->
                    <div class="text-center p-4">
                        <i class="fas fa-spinner fa-pulse fa-2x mb-2"></i>
                        <p><?= __('loading_details') ?>...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('close') ?>
                    </button>
                    <button type="button" class="btn btn-warning edit-payment-btn d-none">
                        <i class="feather icon-edit-2 mr-1"></i><?= __('edit') ?>
                    </button>
                    <button type="button" class="btn btn-danger delete-payment-btn d-none">
                        <i class="feather icon-trash-2 mr-1"></i><?= __('delete') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Client-Supplier Payment Modal -->
    <div class="modal fade" id="editClientSupplierModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('edit_client_supplier_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv_update.php" id="editClientSupplierForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="description" value="Client-Supplier Payment">
                        <div class="form-group">
                            <label for="edit_jv_name"><?= __('jv_name') ?></label>
                            <input type="text" class="form-control" id="edit_jv_name" name="jv_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_client_id"><?= __('client') ?></label>
                            <select class="form-control" id="edit_client_id" name="client_id" required>
                                <option value=""><?= __('select_client') ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['name']) ?> 
                                        (USD: <?= number_format($client['usd_balance'], 2) ?>, 
                                        AFS: <?= number_format($client['afs_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_supplier_id"><?= __('supplier') ?></label>
                            <select class="form-control" id="edit_supplier_id" name="supplier_id" required>
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" data-currency="<?= $supplier['currency'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?> 
                                        (<?= number_format($supplier['balance'], 2) ?> <?= $supplier['currency'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_currency"><?= __('currency') ?></label>
                            <select class="form-control" id="edit_currency" name="currency" required>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_total_amount"><?= __('amount') ?></label>
                            <input type="number" step="0.01" class="form-control" id="edit_total_amount" name="total_amount" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_exchange_rate"><?= __('exchange_rate') ?></label>
                            <input type="number" step="0.00001" class="form-control" id="edit_exchange_rate" name="exchange_rate">
                        </div>
                        <div class="form-group">
                            <label for="edit_receipt"><?= __('receipt_number') ?></label>
                            <input type="text" class="form-control" id="edit_receipt" name="receipt" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_remarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('update_payment') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Custom dropdown positioning to avoid table overflow issues
    document.addEventListener('show.bs.dropdown', function(e) {
        const toggle = e.relatedTarget;
        const menu = toggle.nextElementSibling;
        
        if (menu && menu.classList.contains('dropdown-menu')) {
            const rect = toggle.getBoundingClientRect();
            
            menu.style.position = 'fixed';
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = (rect.right - 200) + 'px';
            menu.style.right = 'auto';
            
            // Adjust if goes off-screen
            setTimeout(() => {
                const menuRect = menu.getBoundingClientRect();
                if (menuRect.right > window.innerWidth) {
                    menu.style.left = (window.innerWidth - 210) + 'px';
                }
                if (menuRect.bottom > window.innerHeight) {
                    menu.style.top = (rect.top - menuRect.height - 5) + 'px';
                }
            }, 0);
        }
    });
    </script>
    
    </body>
    </html>
    <!-- Delete Client-Supplier Payment Modal -->