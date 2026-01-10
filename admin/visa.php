<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Pagination and search setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$searchCondition = " WHERE tenant_id = ? AND branch_id = ?";
$params = [$tenant_id, $branch_id];
$types  = "ii"; // assuming tenant_id and branch_id are integers

if (!empty($search)) {
    $searchCondition .= " AND (
        applicant_name LIKE ? OR 
        passport_number LIKE ? OR 
        title LIKE ? OR 
        country LIKE ? OR 
        visa_type LIKE ?
    )";

    // Add search param 5 times (for each LIKE)
    for ($i = 0; $i < 5; $i++) {
        $params[] = "%$search%";
        $types   .= "s";
    }
}

// Pagination setup
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Build search + tenant condition
$searchCondition = " WHERE va.tenant_id = ? AND va.branch_id = ?";
$params = [$tenant_id, $branch_id];
$types  = "ii"; // assuming tenant_id and branch_id are integers

if (!empty($search)) {
    $searchCondition .= " AND (
        va.applicant_name LIKE ? OR 
        va.passport_number LIKE ? OR 
        va.title LIKE ? OR 
        va.country LIKE ? OR 
        va.visa_type LIKE ?
    )";

    // Add search param 5 times (for each LIKE)
    for ($i = 0; $i < 5; $i++) {
        $params[] = "%$search%";
        $types   .= "s";
    }
}

/* ---------- COUNT QUERY ---------- */
$totalRecordsQuery = "SELECT COUNT(*) as total
                      FROM visa_applications va
                      $searchCondition";

$stmt = $pdo->prepare($totalRecordsQuery);
foreach ($params as $index => $param) {
    $stmt->bindParam($index + 1, $params[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

/* ---------- MAIN VISA QUERY ---------- */
$visaQuery = "SELECT va.*, u.name as created_by
              FROM visa_applications va
              LEFT JOIN users u ON va.created_by = u.id
              $searchCondition
              AND (u.id IS NULL OR u.branch_id = ?)
              ORDER BY va.id DESC
              LIMIT ? OFFSET ?";

// Add limit + offset params
$paramsWithLimit = $params;
$typesWithLimit  = $types . "iii";
$paramsWithLimit[] = $branch_id; // for users table branch_id
$paramsWithLimit[] = $recordsPerPage;
$paramsWithLimit[] = $offset;

$stmt = $pdo->prepare($visaQuery);
foreach ($paramsWithLimit as $index => $param) {
    $stmt->bindParam($index + 1, $paramsWithLimit[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$visas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name
                   FROM suppliers
                   WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";

$stmt = $pdo->prepare($suppliersQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Fetch Clients
$clientsQuery = "SELECT id, name
                 FROM clients
                 WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";

$stmt = $pdo->prepare($clientsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Internal Accounts
$internalQuery = "SELECT id, name
                  FROM main_account
                  WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";

$stmt = $pdo->prepare($internalQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$internal = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}

// Create an associative array of client id to client name for easy lookup
$client_names = [];
foreach ($clients as $client) {
    $client_names[$client['id']] = $client['name'];
}

// Create an associative array of internal account id to account name for easy lookup
$internal_names = [];
foreach ($internal as $account) {
    $internal_names[$account['id']] = $account['name'];
}

// Now, for each visa, add the supplier's name and client's name based on their IDs
foreach ($visas as $key => $visa) {
    $supplier_id = $visa['supplier'];
    $sold_to_id = $visa['sold_to'];
    $paid_to_id = $visa['paid_to'] ?? null;
    
    $visas[$key]['supplier_name'] = isset($supplier_names[$supplier_id]) ? $supplier_names[$supplier_id] : 'Unknown';
    $visas[$key]['sold_name'] = isset($client_names[$sold_to_id]) ? $client_names[$sold_to_id] : 'Unknown';
    $visas[$key]['paid_name'] = isset($internal_names[$paid_to_id]) ? $internal_names[$paid_to_id] : 'Unknown';
}

?>

    <link rel="stylesheet" href="../css/ticket/ticket_styles.css">
    <link rel="stylesheet" href="../css/ticket/ticket-components.css">
    <link rel="stylesheet" href="../css/ticket/ticket-form.css">
    <link rel="stylesheet" href="../css/general/modal-styles.css">
    <link rel="stylesheet" href="../css/visa/visa.css">
    <!-- Add Bootstrap-select CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
 

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
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('visa') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('visa_management') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->

                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <!-- Toast Container -->
                            <div class="toast-container"></div>

                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="search-box">
                                                        <div class="input-group">
                                                            <input type="text" id="searchInput" class="form-control" placeholder="<?= __('search_by_passport_number_applicant_name_or_phone') ?>" value="<?= htmlspecialchars($search) ?>">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-primary" type="button" id="searchBtn">
                                                                    <i class="feather icon-search"></i> <?= __('search') ?>
                                                                </button>
                                                                <?php if (!empty($search)): ?>
                                                                <a href="visa.php" class="btn btn-secondary">
                                                                    <i class="feather icon-x"></i> <?= __('clear') ?>
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <button class="btn btn-primary btn-lg shadow-md" data-toggle="modal" data-target="#addVisaModal">
                                                        <i class="feather icon-plus-circle mr-2"></i><?= __('new_visa_application') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Visa Management Section -->
                                    <div class="container-fluid px-4">
                                        <div class="card shadow-sm mb-4">
                                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i><?= __('visa_applications') ?></h5>
                                                <div>
                                                    <a href="visa_refunds.php" class="btn btn-light btn-sm mr-2">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('visa_refunds') ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center" width="50">#</th>
                                                                <th width="100"><?= __('actions') ?></th>
                                                                <th width="60" class="text-center"><?= __('payment') ?></th>
                                                                <th width="80" class="text-center"><?= __('status') ?></th>
                                                                <th><?= __('applicant_info') ?></th>
                                                                <th><?= __('visa_details') ?></th>
                                                                <th><?= __('application_info') ?></th>
                                                                <th class="text-right"><?= __('amount') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="visaTable">
                                                            <?php 
                                                            $counter = 1;
                                                            foreach ($visas as $visa): 
                                                                $isAgencyClient = false;
                                                                $soldTo = $visa['sold_to'];
                                                                $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                                                                $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                                $clientStmt->bindParam(3, $visa['sold_name'], PDO::PARAM_STR);
                                                                $clientStmt->execute();
                                                                if ($clientStmt->rowCount() > 0) {
                                                                    $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                }
                                                            ?>
                                                            <tr>
                                                                <td class="text-center"><?= $counter++ ?></td>
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-icon btn-secondary btn-sm dropdown-toggle" type="button" id="actionDropdown<?= $visa['id'] ?>" data-toggle="dropdown">
                                                                            <i class="feather icon-more-vertical"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu dropdown-menu-right">
                                                                            <button class="dropdown-item view-details" data-visa='<?= htmlspecialchars(json_encode($visa)) ?>'>
                                                                                <i class="feather icon-eye text-primary mr-2"></i> <?= __('view_details') ?>
                                                                            </button>
                                                                            <button class="dropdown-item" onclick="editVisa(<?= $visa['id'] ?>)">
                                                                                <i class="feather icon-edit-2 text-warning mr-2"></i> <?= __('edit') ?>
                                                                            </button>
                                                                            <?php if ($isAgencyClient): ?>
                                                                            <button class="dropdown-item" onclick="openTransactionTab(<?= $visa['id'] ?>, <?= htmlspecialchars($visa['sold']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')">
                                                                                <i class="fas fa-dollar-sign text-success mr-2"></i> <?= __('transactions') ?>
                                                                            </button>
                                                                            <?php endif; ?>
                                                                            <button class="dropdown-item" 
                                                                                    onclick="openRefundModal(<?= $visa['id'] ?>, <?= htmlspecialchars($visa['sold']) ?>, <?= htmlspecialchars($visa['profit']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')">
                                                                                <i class="feather icon-refresh-cw text-warning mr-2"></i> <?= __('refund_visa') ?>
                                                                            </button>
                                                                            <button class="dropdown-item" 
                                                                                    onclick="openCancellationModal(<?= $visa['id'] ?>, '<?= htmlspecialchars($visa['applicant_name']) ?>', '<?= htmlspecialchars($visa['status']) ?>')">
                                                                                <i class="feather icon-x-circle text-danger mr-2"></i> <?= __('cancel_visa') ?>
                                                                            </button>
                                                                            <?php if (in_array(strtolower($visa['status']), ['cancelled', 'rejected', 'withdrawn'])): ?>
                                                                            <button class="dropdown-item" 
                                                                                    onclick="openReapplyModal(<?= $visa['id'] ?>, '<?= htmlspecialchars($visa['applicant_name']) ?>', <?= htmlspecialchars($visa['profit']) ?>, <?= htmlspecialchars($visa['base']) ?>, <?= htmlspecialchars($visa['sold']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')">
                                                                                <i class="feather icon-refresh-ccw text-success mr-2"></i> <?= __('re_apply_visa') ?>
                                                                            </button>
                                                                            <?php endif; ?>
                                                                            <div class="dropdown-divider"></div>
                                                                            <button class="dropdown-item text-danger" onclick="deleteVisa(<?= $visa['id'] ?>)">
                                                                                <i class="feather icon-trash-2 mr-2"></i> <?= __('delete') ?>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-center">
                                                                <?php
                                                                    // Get client type from clients table
                                                                    $soldTo = $visa['sold_to'];
                                                                    $isAgencyClient = false; // Default to not agency client

                                                                    // Fix: We need to query the clients table using the client name from sold_to
                                                                    $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE tenant_id = ? AND branch_id = ? AND name = ?");
                                                                    $clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                                    $clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                                    $clientStmt->bindParam(3, $visa['sold_name'], PDO::PARAM_STR);
                                                                    $clientStmt->execute();
                                                                    if ($clientStmt->rowCount() > 0) {
                                                                        $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                                        // Only show payment status for agency clients
                                                                        $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                    }

                                                                    // Only show payment status for agency clients
                                                                    if ($isAgencyClient) {
                                                                        // Calculate payment status using transaction-specific exchange rates
                                                                        $baseCurrency = $visa['currency'];
                                                                        $soldAmount = floatval($visa['sold']);
                                                                        $totalPaidInBase = 0.0;

                                                                        // Get visa ID
                                                                        $visaId = $visa['id'];

                                                                        // Query transactions from main_account_transactions table
                                                                        $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                                            transaction_of = 'visa_sale'
                                                                            AND reference_id = ?
                                                                            AND tenant_id = ?
                                                                            AND branch_id = ?");
                                                                        $transactionStmt->bindParam(1, $visaId, PDO::PARAM_INT);
                                                                        $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                        $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                        $transactionStmt->execute();
                                                                        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                                                        if ($transactions && count($transactions) > 0) {
                                                                            foreach ($transactions as $transaction) {
                                                                                $amount = floatval($transaction['amount']);
                                                                                $transCurrency = $transaction['currency'];
                                                                                $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : 1.0;

                                                                                $convertedAmount = 0.0;

                                                                                // Conversion logic
                                                                                if ($transCurrency === $baseCurrency) {
                                                                                    $convertedAmount = $amount;
                                                                                } else {
                                                                                    if ($baseCurrency === 'AFS') {
                                                                                        $convertedAmount = $amount * $transExchangeRate;
                                                                                    } else {
                                                                                        $convertedAmount = $amount / $transExchangeRate;
                                                                                    }
                                                                                }

                                                                                $totalPaidInBase += $convertedAmount;
                                                                            }
                                                                        }

                                                                        // Status icon based on payment status
                                                                        if ($totalPaidInBase <= 0) {
                                                                            // No transactions
                                                                            echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                        } elseif ($totalPaidInBase < $soldAmount) {
                                                                            // Partial payment
                                                                            $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                            echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                                title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . $baseCurrency . ' ' .
                                                                                number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                                        } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                            // Fully paid (with a small tolerance for floating-point comparison)
                                                                            echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                                        } else {
                                                                            // Overpaid
                                                                            echo '<i class="fas fa-circle text-success"
                                                                                title="Fully paid (overpaid by ' . $baseCurrency . ' ' .
                                                                                number_format($totalPaidInBase - $soldAmount, 2) . ')"></i>';
                                                                        }
                                                                    } else {
                                                                        // Not an agency client - show neutral icon
                                                                        echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                                    }
                                                                ?>
                                                                </td>
                                                                <td class="text-center">
                                                                    <span class="<?= getStatusBadgeClass($visa['status']) ?>">
                                                                        <?= htmlspecialchars($visa['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="passenger-info">

                                                                        <div class="passenger-info__details">
                                                                            <div class="passenger-info__name">
                                                                                <?= htmlspecialchars($visa['title']) ?> <?= htmlspecialchars($visa['applicant_name']) ?>
                                                                            </div>
                                                                            <div class="passenger-info__passport">
                                                                                <?= __('passport') ?>: <?= htmlspecialchars($visa['passport_number']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="flight-info">
                                                                        <div class="flight-info__segment">
                                                                            <div class="flight-info__country">
                                                                                <?= htmlspecialchars($visa['country']) ?>
                                                                            </div>
                                                                            <div class="flight-info__visa-type">
                                                                                <?= htmlspecialchars($visa['visa_type']) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="booking-info">
                                                                        <div class="booking-info__date">
                                                                            <i class="feather icon-calendar text-muted mr-1"></i>
                                                                            <?= htmlspecialchars($visa['receive_date']) ?>
                                                                        </div>
                                                                        <div class="booking-info__applied-date">
                                                                            <i class="feather icon-file-text text-muted mr-1"></i>
                                                                            <?= htmlspecialchars($visa['applied_date']) ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="text-right">
                                                                    <div class="ticket-amount">
                                                                        <div class="ticket-amount__value">
                                                                            <?= htmlspecialchars($visa['currency']) ?> <?= number_format($visa['sold'], 2) ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    
                                                    <!-- Pagination Controls -->
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div class="dataTables_info">
                                                            <?= __('showing') ?> 
                                                            <?= (($page - 1) * $recordsPerPage) + 1 ?> 
                                                            <?= __('to') ?> 
                                                            <?= min($page * $recordsPerPage, $totalRecords) ?> 
                                                            <?= __('of') ?> 
                                                            <?= $totalRecords ?> <?= __('entries') ?>
                                                        </div>
                                                        <nav aria-label="Visa table pagination">
                                                            <ul class="pagination mb-0">
                                                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                                    <a class="page-link" href="?page=<?= max(1, $page - 1) ?>" aria-label="<?= __('previous') ?>">
                                                                        <span aria-hidden="true">&laquo;</span>
                                                                    </a>
                                                                </li>
                                                                <?php 
                                                                // Show up to 5 page numbers around the current page
                                                                $startPage = max(1, $page - 2);
                                                                $endPage = min($totalPages, $page + 2);
                                                                
                                                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                                    </li>
                                                                <?php endfor; ?>
                                                                
                                                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                                    <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>" aria-label="<?= __('next') ?>">
                                                                        <span aria-hidden="true">&raquo;</span>
                                                                    </a>
                                                                </li>
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
    <?php include '../includes/admin_footer.php'; ?>
    <?php include '../modals/visa/details_modal.php'; ?>
    <?php include '../modals/visa/add_visa_modal.php'; ?>
    <?php include '../modals/visa/edit_visa_modal.php'; ?>
    <?php include '../modals/visa/refund_modal.php'; ?>
    <?php include '../modals/visa/cancellation_modal.php'; ?>
    <?php include '../modals/visa/reapply_modal.php'; ?>
    <?php include '../modals/visa/multi_visa_modal.php'; ?>
    <?php include '../modals/visa/transaction_modal.php'; ?>
    <?php include '../modals/visa/edit_transaction_modal.php'; ?>
    
    


    



<style>
    #floatingActionButton {
        right: 30px;
    }
    
    /* RTL support - position on left side instead */
    html[dir="rtl"] #floatingActionButton {
        right: auto;
        left: 30px;
    }
</style>

                                <?php
                                function getStatusBadgeClass($status) {
                                    switch (strtolower($status)) {
                                        case 'approved':
                                        case 'issued':
                                            return 'success';
                                        case 'pending':
                                            return 'warning';
                                        case 'rejected':
                                        case 'refunded':
                                        case 'cancelled':
                                        case 'withdrawn':
                                            return 'danger';
                                        default:
                                            return 'secondary';
                                    }
                                }
                                ?>


    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }

        .toast {
            position: relative;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            overflow: hidden;
            opacity: 0;
            transform: translateX(40px);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            padding: 15px;
        }

        .toast-showing {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-removing {
            opacity: 0;
            transform: translateY(-20px);
        }

        .toast-success {
            border-left-color: #10b981;
        }

        .toast-error {
            border-left-color: #ef4444;
        }

        .toast-warning {
            border-left-color: #f59e0b;
        }

        .toast-info {
            border-left-color: #3b82f6;
        }

        .toast-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .toast-message {
            word-break: break-word;
            line-height: 1.5;
            color: #64748b;
        }
</style>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

         <!-- Add Bootstrap-select JavaScript -->
         <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
         <script src="../js/visa/select2.js"></script>
         <script src="../js/visa/visa_details.js"></script>       
         <script src="../js/visa/supplier_currency.js"></script>
         <script src="../js/visa/profit_calc.js"></script>
         <script src="../js/visa/add_visa.js"></script>
         <script src="../js/visa/edit_visa.js"></script>
         <script src="../js/visa/invoice.js"></script>
         <script src="../js/visa/visa_refund.js"></script>
         <script src="../js/visa/transaction_manager.js"></script>
         <script src="../js/visa/search.js"></script>
         <script src="../js/visa/cancel_reapply.js"></script>
         <script src="../js/visa/toast.js"></script>


</body>
</html>
