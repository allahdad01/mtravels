<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once '../includes/conn.php';

// Pagination and search setup
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$searchCondition = " WHERE tenant_id = ?";
$params = [$tenant_id];
$types  = "i"; // assuming tenant_id is integer

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
$searchCondition = " WHERE va.tenant_id = ?";
$params = [$tenant_id];
$types  = "i"; // assuming tenant_id is integer

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

$stmt = $conn->prepare($totalRecordsQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$totalRecords = $result->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);
$stmt->close();

/* ---------- MAIN VISA QUERY ---------- */
$visaQuery = "SELECT va.*, u.name as created_by 
              FROM visa_applications va 
              LEFT JOIN users u ON va.created_by = u.id 
              $searchCondition
              ORDER BY va.id DESC 
              LIMIT ? OFFSET ?";

// Add limit + offset params
$paramsWithLimit = $params;
$typesWithLimit  = $types . "ii"; 
$paramsWithLimit[] = $recordsPerPage;
$paramsWithLimit[] = $offset;

$stmt = $conn->prepare($visaQuery);
$stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$visaResult = $stmt->get_result();
$visas = $visaResult->fetch_all(MYSQLI_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name 
                   FROM suppliers 
                   WHERE status = 'active' AND tenant_id = ?";

$stmt = $conn->prepare($suppliersQuery);
$stmt->bind_param("i", $tenant_id); // assuming tenant_id is integer
$stmt->execute();
$suppliersResult = $stmt->get_result();
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// Fetch Clients
$clientsQuery = "SELECT id, name 
                 FROM clients 
                 WHERE status = 'active' AND tenant_id = ?";

$stmt = $conn->prepare($clientsQuery);
$stmt->bind_param("i", $tenant_id); // assuming tenant_id is integer
$stmt->execute();
$clientsResult = $stmt->get_result();
$clients = $clientsResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Internal Accounts
$internalQuery = "SELECT id, name 
                  FROM main_account 
                  WHERE status = 'active' AND tenant_id = ?";

$stmt = $conn->prepare($internalQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$internalResult = $stmt->get_result();
$internal = $internalResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/ticket_styles.css">
    <link rel="stylesheet" href="css/ticket-components.css">
    <link rel="stylesheet" href="css/ticket-form.css">
    <link rel="stylesheet" href="css/modal-styles.css">
    <link rel="stylesheet" href="css/visa.css">

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
                                                                $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id And name = '".$visa['sold_name']."'");
                                                                if ($clientQuery && $clientQuery->num_rows > 0) {
                                                                    $clientRow = $clientQuery->fetch_assoc();
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
                                                    $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id AND name = '".$visa['sold_name']."'");
                                                    if ($clientQuery && $clientQuery->num_rows > 0) {
                                                        $clientRow = $clientQuery->fetch_assoc();
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
                                                        $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
                                                            transaction_of = 'visa_sale'
                                                            AND reference_id = '$visaId'");

                                                        if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                            while ($transaction = $transactionQuery->fetch_assoc()) {
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
                                                                    <span class="badge badge-<?= getStatusBadgeClass($visa['status']) ?>">
                                                                        <?= htmlspecialchars($visa['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="passenger-info">
                                                                        <div class="passenger-info__avatar">
                                                                            <?= strtoupper(substr($visa['applicant_name'], 0, 2)) ?>
                                                                        </div>
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
                                <!-- Add Refund Visa Modal -->
                                <div class="modal fade" id="refundVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-refresh-cw mr-2"></i><?= __('refund_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <span><?= __('refunding_a_visa_will_create_a_refund_record_and_allow_processing_a_refund_transaction_to_the_customer') ?></span>
                                                </div>
                                                
                                                <form id="refundVisaForm">
                                                    <input type="hidden" id="refundVisaId" name="visa_id">
                                                    <input type="hidden" id="refundTotalAmount" name="total_amount">
                                                    <input type="hidden" id="refundProfitAmount" name="profit_amount">
                                                    <input type="hidden" id="refundCurrency" name="currency">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('visa_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="refundCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="text" class="form-control" id="refundVisaAmount" readonly>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('profit_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="refundProfitCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="text" class="form-control" id="refundVisaProfit" readonly>
                                                        </div>
                                                    </div>
                                                    
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('refund_type') ?>:</label>
                                                        <div class="custom-control custom-radio mb-2">
                                                            <input type="radio" id="fullRefund" name="refund_type" value="full" class="custom-control-input" checked>
                                                            <label class="custom-control-label" for="fullRefund"><?= __('full_refund') ?> (<?= __('sets_profit_to_0') ?>)</label>
                                                        </div>
                                                        <div class="custom-control custom-radio">
                                                            <input type="radio" id="partialRefund" name="refund_type" value="partial" class="custom-control-input">
                                                            <label class="custom-control-label" for="partialRefund"><?= __('partial_refund') ?></label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div id="partialRefundAmountGroup" class="form-group" style="display: none;">
                                                        <label for="partialRefundAmount"><?= __('refund_amount') ?>:</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text" id="partialRefundCurrencyLabel">$</span>
                                                            </div>
                                                            <input type="number" class="form-control" id="partialRefundAmount" name="refund_amount">
                                                        </div>
                                                        <small class="form-text text-muted"><?= __('enter_the_amount_to_refund_to_the_customer') ?></small>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="refundReason"><?= __('reason_for_refund') ?>:</label>
                                                        <textarea class="form-control" id="refundReason" name="refund_reason" rows="3" required></textarea>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                <button type="button" class="btn btn-warning" id="processRefundBtn"><?= __('process_refund') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add Cancellation Visa Modal -->
                                <div class="modal fade" id="cancelVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-x-circle mr-2"></i><?= __('cancel_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="feather icon-alert-triangle mr-2"></i>
                                                    <span><?= __('cancelling_a_visa_will_change_its_status_and_prevent_further_processing') ?></span>
                                                </div>
                                                
                                                <form id="cancelVisaForm">
                                                    <input type="hidden" id="cancelVisaId" name="visa_id">
                                                    <input type="hidden" id="currentStatus" name="current_status">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('applicant_name') ?>:</label>
                                                        <input type="text" class="form-control" id="cancelApplicantName" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('current_status') ?>:</label>
                                                        <input type="text" class="form-control" id="cancelCurrentStatus" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('new_status') ?>:</label>
                                                        <select class="form-control" id="cancelNewStatus" name="new_status" required>
                                                            <option value="Cancelled"><?= __('cancelled') ?></option>
                                                            <option value="Rejected"><?= __('rejected') ?></option>
                                                            <option value="Withdrawn"><?= __('withdrawn') ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="cancellationReason"><?= __('reason_for_cancellation') ?>:</label>
                                                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" rows="3"></textarea>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="confirmCancellation" required>
                                                            <label class="custom-control-label" for="confirmCancellation">
                                                                <?= __('i_confirm_that_i_want_to_cancel_this_visa_application') ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                <button type="button" class="btn btn-danger" id="processCancellationBtn" disabled><?= __('cancel_visa') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add Re-apply Visa Modal -->
                                <div class="modal fade" id="reapplyVisaModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('re_apply_visa') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <span><?= __('re_applying_a_visa_will_restore_its_original_profit_and_reverse_cancellation_balance_changes') ?></span>
                                                </div>
                                                
                                                <form id="reapplyVisaForm">
                                                    <input type="hidden" id="reapplyVisaId" name="visa_id">
                                                    <input type="hidden" id="reapplyOriginalProfit" name="original_profit">
                                                    <input type="hidden" id="reapplyBaseAmount" name="base_amount">
                                                    <input type="hidden" id="reapplySoldAmount" name="sold_amount">
                                                    <input type="hidden" id="reapplyCurrency" name="currency">
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('applicant_name') ?>:</label>
                                                        <input type="text" class="form-control" id="reapplyApplicantName" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('current_status') ?>:</label>
                                                        <input type="text" class="form-control" id="reapplyCurrentStatus" readonly>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="font-weight-bold"><?= __('new_status') ?>:</label>
                                                        <select class="form-control" id="reapplyNewStatus" name="new_status" required>
                                                            <option value="Pending"><?= __('pending') ?></option>
                                                            <option value="Approved"><?= __('approved') ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label for="reapplyReason"><?= __('reason_for_re_application') ?>:</label>
                                                        <textarea class="form-control" id="reapplyReason" name="reapply_reason" rows="3" required></textarea>
                                                    </div>
                                                    
                                                    
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox">
                                                            <input type="checkbox" class="custom-control-input" id="confirmReapply" required>
                                                            <label class="custom-control-label" for="confirmReapply">
                                                                <?= __('i_confirm_that_i_want_to_re_apply_this_visa_application') ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                <button type="button" class="btn btn-success" id="processReapplyBtn" disabled><?= __('re_apply_visa') ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php include '../includes/admin_footer.php'; ?>
                                <!-- Visa Details Modal -->
                                <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-file-text mr-2"></i><?= __('visa_details') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="nav nav-pills nav-fill mb-3" id="detailsTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary">
                                                            <i class="feather icon-info mr-1"></i><?= __('summary') ?>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description">
                                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                                        </a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content p-3 border rounded">
                                                    <div class="tab-pane fade show active" id="details-summary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card border-primary mb-3">
                                                                    <div class="card-header bg-primary text-white">
                                                                        <i class="feather icon-user mr-1"></i><?= __('personal_details') ?>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong><?= __('paid_to') ?>:</strong> <span id="paid-to"></span></p>
                                                                        <p class="mb-2"><strong><?= __('country') ?>:</strong> <span id="country"></span></p>
                                                                        <p class="mb-2"><strong><?= __('visa_type') ?>:</strong> <span id="visa-type"></span></p>
                                                                        <p class="mb-2"><strong><?= __('created_by') ?>:</strong> <span id="created-by"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-success mb-3">
                                                                    <div class="card-header bg-success text-white">
                                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('financial_details') ?>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong><?= __('currency') ?>:</strong> <span id="currency"></span></p>
                                                                        <p class="mb-2"><strong><?= __('base_price') ?>:</strong> <span id="base-price"></span></p>
                                                                        <p class="mb-2"><strong><?= __('sold_price') ?>:</strong> <span id="sold-price"></span></p>
                                                                        <p class="mb-2"><strong><?= __('profit') ?>:</strong> <span id="profit" class="text-success"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border-info">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="feather icon-calendar mr-1"></i><?= __('dates') ?>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('receive_date') ?>:</strong> <span id="receive-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('applied_date') ?>:</strong> <span id="applied-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong><?= __('issued_date') ?>:</strong> <span id="issued-date"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="tab-pane fade" id="details-description">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <p id="description" class="mb-0"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
            <!-- Multiple Ticket Invoice Modal -->
<div class="modal fade" id="multiTicketInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_invoice') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="feather icon-info mr-2"></i><?= __('select_multiple_tickets_to_generate_a_combined_invoice') ?>
                </div>
                
                <form id="multiTicketInvoiceForm">
                    <div class="form-group">
                        <label for="clientForInvoice"><?= __('client') ?></label>
                        
                        <input type="text" class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment"><?= __('comments_notes') ?></label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="ticketSelectionTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAllTickets">
                                            <label class="custom-control-label" for="selectAllTickets"></label>
                                        </div>
                                    </th>
                                    <th><?= __('applicant_name') ?></th>
                                    <th><?= __('passport') ?></th>
                                    <th><?= __('visa_type') ?></th>
                                    <th><?= __('country') ?></th>
                                    <th><?= __('applied_date') ?></th>
                                    <th><?= __('issued_date') ?></th>
                                    <th><?= __('amount') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="6" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                    <td id="invoiceTotal" class="font-weight-bold">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="invoiceCurrency"><?= __('currency') ?></label>
                        <select class="form-control" id="invoiceCurrency" name="invoiceCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <button type="button" class="btn btn-primary" id="generateCombinedInvoice">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>

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
                                       <!-- Add Visa Modal -->
                                        <div class="modal fade" id="addVisaModal" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5><?= __('add_visa') ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <form id="addVisaForm">
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <h5 class="mb-4 text-primary">
                                                                        <i class="feather icon-file-text mr-2"></i><?= __('visa_application_details') ?>
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-primary">
                                                                        <div class="card-header bg-primary text-white">
                                                                            <?= __('supplier_and_client_info') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="form-group">
                                                                                <label for="supplier"><?= __('supplier') ?></label>
                                                                                <select class="form-control bootstrap-select" id="supplier" name="supplier" required>
                                                                                    <option value=""><?= __('select_supplier') ?></option>
                                                                                    <?php foreach ($suppliers as $supplier): ?>
                                                                                    <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="soldto"><?= __('sold_to') ?></label>
                                                                                <select class="form-control bootstrap-select" id="soldTo" name="soldto" required>
                                                                                    <option value=""><?= __('select_client') ?></option>
                                                                                    <?php foreach ($clients as $client): ?>
                                                                                    <option value="<?= $client['id'] ?>"><?= $client['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                    <div class="form-group">
                                                                                        <label for="paidto"><?= __('paid_via') ?></label>
                                                                                        <select class="form-control" id="paidto" name="paidto" required>
                                                                                            <option value=""><?= __('select_main_account') ?></option>
                                                                                            <?php foreach ($internal as $int): ?>
                                                                                            <option value="<?= $int['id'] ?>"><?= $int['name'] ?></option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            <div class="form-group">
                                                                                <label for="phone"><?= __('phone') ?></label>
                                                                                <input type="text" class="form-control" id="phone" name="phone" required>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-info">
                                                                        <div class="card-header bg-info text-white">
                                                                            <?= __('applicant_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="title"><?= __('title') ?></label>
                                                                                        <select class="form-control" id="title" name="title" required>
                                                                                            <option value="Mr">Mr</option>
                                                                                            <option value="Mrs">Mrs</option>
                                                                                            <option value="Child">Child</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="gender"><?= __('gender') ?></label>
                                                                                        <select class="form-control" id="gender" name="gender" required>
                                                                                            <option value="Male"><?= __('male') ?></option>
                                                                                            <option value="Female"><?= __('female') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="passengerName"><?= __('passenger_name') ?></label>
                                                                                        <input type="text" class="form-control" id="passengerName" name="passengerName" required>
                                                                                    </div>
                                                                                </div>
                                                                            
                                                                            
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="passNum"><?= __('passport_number') ?></label>
                                                                                        <input type="text" class="form-control" id="passNum" name="passNum" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="country"><?= __('country') ?></label>
                                                                                        <select class="form-control" id="country" name="country" required>
                                                                                            <option value=""><?= __('select_country') ?></option>
                                                                                            <option value="Pakistan">Pakistan</option>
                                                                                            <option value="India">India</option>
                                                                                            <option value="Turkey">Turkey</option>
                                                                                            <option value="Iran">Iran</option>
                                                                                            <option value="Saudi Arabia">Saudi Arabia</option>
                                                                                            <option value="United Arab Emirates">United Arab Emirates</option>
                                                                                            <option value="Uzbekistan">Uzbekistan</option>
                                                                                            <option value="Kazakhstan">Kazakhstan</option>
                                                                                            <option value="Qatar">Qatar</option>
                                                                                            <option value="Kuwait">Kuwait</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="visaType"><?= __('visa_type') ?></label>
                                                                                        <select class="form-control" id="visaType" name="visaType" required>
                                                                                            <option value=""><?= __('select_visa_type') ?></option>
                                                                                            <option value="Tourist">Tourist</option>
                                                                                            <option value="Business">Business</option>
                                                                                            <option value="Work">Work</option>
                                                                                            <option value="Study">Study</option>
                                                                                            <option value="Family">Family</option>
                                                                                            <option value="Medical">Medical</option>
                                                                                            <option value="Religious">Religious</option>
                                                                                            <option value="Transit">Transit</option>
                                                                                            <option value="Diplomatic">Diplomatic</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                             </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-success">
                                                                        <div class="card-header bg-success text-white">
                                                                            <?= __('dates') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="receiveDate"><?= __('received_date') ?></label>
                                                                                        <input type="date" class="form-control" id="receiveDate" name="receiveDate" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="appliedDate"><?= __('applied_date') ?></label>
                                                                                        <input type="date" class="form-control" id="appliedDate" name="appliedDate" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="issuedDate"><?= __('issued_date') ?></label>
                                                                                        <input type="date" class="form-control" id="issuedDate" name="issuedDate">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-warning">
                                                                        <div class="card-header bg-warning text-white">
                                                                            <?= __('financial_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="base"><?= __('base') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="base" name="base" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="sold"><?= __('sold') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="sold" name="sold" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="pro"><?= __('profit') ?></label>
                                                                                        <input type="number" step="0.01" class="form-control" id="pro" name="pro" required readonly>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                               
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="curr"><?= __('currency') ?></label>
                                                                                        <input type="text" class="form-control" id="curr" name="curr" required readonly>
                                                                                    </div>
                                                                                </div>
                                                                                
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label for="description"><?= __('description') ?></label>
                                                                        <input type="text" class="form-control" id="description" name="description" required>
                                                                    </div>
                                                                </div>
                                                              </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                            <button type="submit" class="btn btn-primary" data-no-protection><?= __('add_visa') ?></button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                       <!-- Edit Visa Modal -->
                                        <div class="modal fade" id="editVisaModal" tabindex="-1" role="dialog" aria-labelledby="editVisaModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <form id="editVisaForm">
                                                    <input type="hidden" id="editVisaId" name="visa_id">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editVisaModalLabel"><?= __('edit_visa') ?></h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <h5 class="mb-4 text-primary">
                                                                        <i class="feather icon-file-text mr-2"></i><?= __('visa_application_details') ?>
                                                                    </h5>
                                                                </div>
                                                             </div>
 
                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-primary">
                                                                        <div class="card-header bg-primary text-white">
                                                                            <?= __('supplier_and_client_info') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="form-group">
                                                                                <label for="editSupplier"><?= __('supplier') ?></label>
                                                                                <select class="form-control bootstrap-select" id="editSupplier" name="supplier" required>
                                                                                    <option value=""><?= __('select_supplier') ?></option>
                                                                                    <?php foreach ($suppliers as $supplier): ?>
                                                                                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editSoldTo"><?= __('sold_to') ?></label>
                                                                                <select class="form-control bootstrap-select" id="editSoldTo" name="sold_to" required>
                                                                                    <option value=""><?= __('select_client') ?></option>
                                                                                    <?php foreach ($clients as $client): ?>
                                                                                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editPhone"><?= __('phone') ?></label>
                                                                                <input type="text" class="form-control" id="editPhone" name="phone" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editPaidTo"><?= __('paid_via') ?></label>
                                                                                <select class="form-control" id="editPaidTo" name="paid_to" required>
                                                                                    <?php 
                                                                                    // Fetch the current visa's paid_to value if available
                                                                                    $currentPaidTo = isset($visa['paid_to']) ? $visa['paid_to'] : null;
                                                                                    
                                                                                    foreach ($internal as $int): 
                                                                                        $selected = ($currentPaidTo == $int['id']) ? 'selected' : '';
                                                                                    ?>
                                                                                        <option value="<?= $int['id'] ?>" <?= $selected ?>><?= $int['name'] ?></option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-info">
                                                                        <div class="card-header bg-info text-white">
                                                                            <?= __('applicant_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editTitle"><?= __('title') ?></label>
                                                                                        <select class="form-control" id="editTitle" name="title" required>
                                                                                            <option value="Mr"><?= __('mr') ?></option>
                                                                                            <option value="Mrs"><?= __('mrs') ?></option>
                                                                                            <option value="Child"><?= __('child') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editGender"><?= __('gender') ?></label>
                                                                                        <select class="form-control" id="editGender" name="gender" required>
                                                                                            <option value="Male"><?= __('male') ?></option>
                                                                                            <option value="Female"><?= __('female') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editApplicantName"><?= __('applicant_name') ?></label>
                                                                                        <input type="text" class="form-control" id="editApplicantName" name="applicant_name" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editPassportNumber"><?= __('passport_number') ?></label>
                                                                                        <input type="text" class="form-control" id="editPassportNumber" name="passport_number" required>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editCountry"><?= __('country') ?></label>
                                                                                        <select class="form-control" id="editCountry" name="country" required>
                                                                                            <option value=""><?= __('select_country') ?></option>
                                                                                            <option value="Pakistan">Pakistan</option>
                                                                                            <option value="India">India</option>
                                                                                            <option value="Iran">Iran</option>
                                                                                            <option value="Turkey">Turkey</option>
                                                                                            <option value="United Arab Emirates">United Arab Emirates</option>
                                                                                            <option value="Uzbekistan">Uzbekistan</option>
                                                                                            <option value="Tajikistan">Tajikistan</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editVisaType"><?= __('visa_type') ?></label>
                                                                                        <select class="form-control" id="editVisaType" name="visa_type" required>
                                                                                            <option value=""><?= __('select_visa_type') ?></option>
                                                                                            <option value="Tourist">Tourist</option>
                                                                                            <option value="Business">Business</option>
                                                                                            <option value="Work">Work</option>
                                                                                            <option value="Study">Study</option>
                                                                                            <option value="Family">Family</option>
                                                                                            <option value="Medical">Medical</option>
                                                                                            <option value="Religious">Religious</option>
                                                                                            <option value="Transit">Transit</option>
                                                                                            <option value="Diplomatic">Diplomatic</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-5">
                                                                    <div class="card mb-4 border-success">
                                                                        <div class="card-header bg-success text-white">
                                                                            <?= __('dates') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="form-group">
                                                                                <label for="editReceiveDate"><?= __('receive_date') ?></label>
                                                                                <input type="date" class="form-control" id="editReceiveDate" name="receive_date" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editAppliedDate"><?= __('applied_date') ?></label>
                                                                                <input type="date" class="form-control" id="editAppliedDate" name="applied_date" required>
                                                                            </div>
                                                                            <div class="form-group">
                                                                                <label for="editIssuedDate"><?= __('issued_date') ?></label>
                                                                                <input type="date" class="form-control" id="editIssuedDate" name="issued_date">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-7">
                                                                    <div class="card mb-4 border-warning">
                                                                        <div class="card-header bg-warning text-white">
                                                                            <?= __('financial_details') ?>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editBase"><?= __('base_price') ?></label>
                                                                                        <input type="number" class="form-control" id="editBase" name="base" step="0.01" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editSold"><?= __('sold_price') ?></label>
                                                                                        <input type="number" class="form-control" id="editSold" name="sold" step="0.01" required>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="editPro"><?= __('profit') ?></label>
                                                                                        <input type="number" class="form-control" id="editPro" name="profit" step="0.01" readonly>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="row">
                                                                                
                                                                                <div class="col-md-6">
                                                                                    <div class="form-group">
                                                                                        <label for="editCurrency"><?= __('currency') ?></label>
                                                                                        <select class="form-control" id="editCurrency" name="currency" required>
                                                                                            <option value="USD"><?= __('usd') ?></option>
                                                                                            <option value="EUR"><?= __('eur') ?></option>
                                                                                            <option value="DARHAM"><?= __('darham') ?></option>
                                                                                            <option value="AFS"><?= __('afs') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-12">
                                                                                    <div class="form-group">
                                                                                        <label for="editStatus"><?= __('status') ?></label>
                                                                                        <select class="form-control" id="editStatus" name="status">
                                                                                            <option value="Pending"><?= __('pending') ?></option>
                                                                                            <option value="Approved"><?= __('approved') ?></option>
                                                                                            <option value="Rejected"><?= __('rejected') ?></option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <div class="form-group">
                                                                        <label for="editRemarks"><?= __('remarks') ?></label>
                                                                        <input type="text" class="form-control" id="editRemarks" name="remarks">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                         </div>
                                                         <div class="modal-footer">
                                                             <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                             <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                                         </div>
                                                     </div>
                                                 </form>
                                             </div>
                                         </div>

                                        
                                        
                                        
                                         <!-- transaction modals -->
                                        <div class="modal fade" id="transactionModal" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-primary text-white">
                                                        <h5 class="modal-title">
                                                            <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                                                        </h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                                                        <!-- Visa Info Card -->
                                                        <div class="card mb-4 border-primary">
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <h6 class="text-muted mb-2"><?= __('visa_application_details') ?></h6>
                                                                        <p class="mb-1"><strong><?= __('visa_id') ?>:</strong> <span id="transactionVisaId"></span></p>
                                                                        <input type="hidden" id="transactionVisaIdInput" name="visa_id">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                    <div class="alert alert-info mb-0">
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <span><?= __('total_amount') ?>:</span>
                                                                            <strong id="totalAmount"></strong>
                                                                        </div>
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <span><?= __('exchange_rate') ?>:</span>
                                                                            <strong id="exchangeRateDisplay"></strong>
                                                                        </div>
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <span><?= __('exchanged_amount') ?>:</span>
                                                                            <strong id="exchangedAmount"></strong>
                                                                        </div>
                                                                        <div id="usdSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('paid_amount_usd') ?>:</span>
                                                                                <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('remaining_amount_usd') ?>:</span>
                                                                                <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="afsSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('paid_amount_afs') ?>:</span>
                                                                                <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('remaining_amount_afs') ?>:</span>
                                                                                <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="eurSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('paid_amount_eur') ?>:</span>
                                                                                <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('remaining_amount_eur') ?>:</span>
                                                                                <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="aedSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('paid_amount_aed') ?>:</span>
                                                                                <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span><?= __('remaining_amount_aed') ?>:</span>
                                                                                <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Transactions Table -->
                                                        <div class="card mb-4">
                                                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                                                                <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                                                                    <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                                                                </button>
                                                            </div>
                                                            <div class="card-body p-0">
                                                                <div class="table-responsive">
                                                                    <table class="table table-hover mb-0">
                                                                        <thead class="thead-light">
                                                                            <tr>
                                                                                <th><?= __('date') ?></th>
                                                                                <th><?= __('description') ?></th>
                                                                                <th><?= __('payment') ?></th>
                                                                                <th><?= __('amount') ?></th>
                                                                                <th><?= __('exchange_rate') ?></th>
                                                                                <th class="text-center"><?= __('actions') ?></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="transactionTableBody">
                                                                            <!-- Transactions will be loaded here -->
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Add Transaction Form -->
                                                        <div id="addTransactionForm" class="collapse">
                                                            <div class="card border-primary">
                                                                <div class="card-header bg-primary text-white">
                                                                    <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                                                                </div>
                                                                <div class="card-body">
                                                                    <form id="visaTransactionForm">
                                                                        <input type="hidden" id="visa_id" name="visa_id">
                                                                        <div class="row">
                                                                            <div class="col-md-6">
                                                                                <div class="form-group">
                                                                                    <label for="paymentDate">
                                                                                        <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                                                                    </label>
                                                                                    <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <div class="form-group">
                                                                                    <label for="paymentTime">
                                                                                        <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                                                                    </label>
                                                                                    <input type="time" class="form-control" id="paymentTime" name="payment_time" step="1" required>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <div class="form-group">
                                                                                    <label for="paymentAmount">
                                                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                                                    </label>
                                                                                    <input type="number" class="form-control" id="paymentAmount" 
                                                                                           name="payment_amount" step="0.01" min="0.01" required 
                                                                                           placeholder="Enter amount">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                            <div class="form-group">
                                                                                <label for="paymentCurrency">
                                                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                                                                </label>
                                                                                <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                                                                    <option value=""><?= __('select_currency') ?></option>
                                                                                    <option value="USD"><?= __('usd') ?></option>
                                                                                    <option value="AFS"><?= __('afs') ?></option>
                                                                                    <option value="EUR"><?= __('eur') ?></option>
                                                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <div class="form-group" id="exchangeRateField" style="display: none;">
                                                                                <label for="transactionExchangeRate">
                                                                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                                                </label>
                                                                                <input type="number" class="form-control" id="transactionExchangeRate"
                                                                                       name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                                                            </div>
                                                                        </div>
                                                                        </div>

                                                                        <div class="form-group">
                                                                            <label for="paymentDescription">
                                                                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                                                            </label>
                                                                            <textarea class="form-control" id="paymentDescription" 
                                                                                      name="payment_description" rows="2" required
                                                                                      placeholder="Enter payment description"></textarea>
                                                                        </div>

                                                                        <div class="text-right mt-3">
                                                                            <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                                                                    data-target="#addTransactionForm">
                                                                                <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                                                            </button>
                                                                            <button type="submit" class="btn btn-primary">
                                                                                <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                            <i class="feather icon-x mr-1"></i><?= __('close') ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Edit Transaction Modal -->
                                        <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editTransactionModalLabel"><?= __('edit_transaction') ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form id="editTransactionForm">
                                                            <!-- Hidden fields for IDs -->
                                                            <input type="hidden" id="editTransactionId" name="transaction_id">
                                                            <input type="hidden" id="editVisaId" name="visa_id">
                                                            <input type="hidden" id="originalAmount" name="original_amount">
                                                            
                                                            <!-- Date and Time -->
                                                            <div class="form-group">
                                                                <label for="editPaymentDate"><?= __('payment_date') ?></label>
                                                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="editPaymentTime"><?= __('payment_time') ?></label>
                                                                <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                                                       pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                                                       placeholder="HH:MM:SS" required>
                                                                <small class="form-text text-muted"><?= __('enter_time_in_24_hour_format') ?></small>
                                                            </div>
                                                            
                                                            <!-- Amount -->
                                                            <div class="form-group">
                                                                <label for="editPaymentAmount"><?= __('amount') ?></label>
                                                                <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                                                                <small class="form-text text-muted"><?= __('changing_this_amount_will_update_all_subsequent_balances') ?></small>
                                                            </div>

                                                            <!-- Currency -->
                                                            <div class="form-group">
                                                                <label for="editPaymentCurrency"><?= __('currency') ?></label>
                                                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required>
                                                                    <option value=""><?= __('select_currency') ?></option>
                                                                    <option value="USD"><?= __('usd') ?></option>
                                                                    <option value="AFS"><?= __('afs') ?></option>
                                                                    <option value="EUR"><?= __('eur') ?></option>
                                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                                </select>
                                                            </div>

                                                            <!-- Exchange Rate -->
                                                            <div class="form-group" id="editExchangeRateField" style="display: none;">
                                                                <label for="editTransactionExchangeRate">
                                                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                                </label>
                                                                <input type="number" class="form-control" id="editTransactionExchangeRate"
                                                                       name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                                            </div>

                                                            <!-- Description -->
                                                            <div class="form-group">
                                                                <label for="editPaymentDescription"><?= __('description') ?></label>
                                                                <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="3"></textarea>
                                                            </div>
                                                            
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                                <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        


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

         <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
         <script src="js/visa/select2.js"></script>
         <script src="js/visa/visa_details.js"></script>       
         <script src="js/visa/supplier_currency.js"></script>
         <script src="js/visa/profit_calc.js"></script>
         <script src="js/visa/add_visa.js"></script>
         <script src="js/visa/edit_visa.js"></script>
         <script src="js/visa/invoice.js"></script>
         <script src="js/visa/visa_refund.js"></script>
         <script src="js/visa/transaction_manager.js"></script>
         <script src="js/visa/search.js"></script>


   
<script>
// Toast notification system
const toastConfig = {
    duration: 4000,      // Display duration in ms
    animationDuration: 300,  // Animation duration in ms
    maxToasts: 3        // Maximum number of toasts to show at once
};

// Collection to track active toasts
let activeToasts = [];

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 * @param {object} options - Optional configuration overrides
 */
function showToast(message, type = 'success', options = {}) {
    const config = { ...toastConfig, ...options };

    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    // Set icon based on type
    let icon = 'check-circle';
    switch(type) {
        case 'error':
            icon = 'alert-circle';
            break;
        case 'warning':
            icon = 'alert-triangle';
            break;
        case 'info':
            icon = 'info';
            break;
    }

    // Set toast content
    toast.innerHTML = `
        <div class="toast-title">
            <i class="feather icon-${icon} mr-2"></i>
            ${type.charAt(0).toUpperCase() + type.slice(1)}
        </div>
        <div class="toast-message">${message}</div>
    `;

    // Manage toast collection
    if (activeToasts.length >= toastConfig.maxToasts) {
        const oldestToast = activeToasts.shift();
        if (oldestToast && oldestToast.parentNode) {
            oldestToast.classList.add('toast-removing');
            setTimeout(() => oldestToast.remove(), config.animationDuration);
        }
    }

    // Add toast to container
    const container = document.querySelector('.toast-container');
    container.appendChild(toast);
    activeToasts.push(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('toast-showing'));

    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => {
            toast.remove();
            activeToasts = activeToasts.filter(t => t !== toast);
        }, config.animationDuration);
    }, config.duration);

    return toast;
}

// Convert all alerts to toasts
document.addEventListener('DOMContentLoaded', function() {
    // Success alerts
    document.querySelectorAll('.alert-success').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'success');
        alert.remove();
    });

    // Error alerts
    document.querySelectorAll('.alert-danger').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'error');
        alert.remove();
    });

    // Warning alerts
    document.querySelectorAll('.alert-warning').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'warning');
        alert.remove();
    });
});

// Replace all existing alert() calls with toast notifications
window.oldAlert = window.alert;
window.alert = function(message) {
    showToast(message, 'info');
};

// Cancellation functionality
function openCancellationModal(visaId, applicantName, currentStatus) {
    document.getElementById('cancelVisaId').value = visaId;
    document.getElementById('cancelApplicantName').value = applicantName;
    document.getElementById('cancelCurrentStatus').value = currentStatus;
    document.getElementById('currentStatus').value = currentStatus;
    document.getElementById('cancellationReason').value = '';
    document.getElementById('confirmCancellation').checked = false;
    document.getElementById('processCancellationBtn').disabled = true;
    $('#cancelVisaModal').modal('show');
}

// Enable/disable process button based on confirmation
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmCancellation');
    const processBtn = document.getElementById('processCancellationBtn');
    
    if (confirmCheckbox && processBtn) {
        confirmCheckbox.addEventListener('change', function() {
            processBtn.disabled = !this.checked;
        });
    }
});

// Process cancellation
document.getElementById('processCancellationBtn').addEventListener('click', function() {
    const form = document.getElementById('cancelVisaForm');
    const formData = new FormData(form);
    
    
    // Disable button and show loading
    this.disabled = true;
    this.innerHTML = '<i class="feather icon-loader mr-1"></i>Processing...';
    
    // Prepare data for submission
    const data = {
        action: 'cancel_visa',
        visa_id: formData.get('visa_id'),
        current_status: formData.get('current_status'),
        new_status: formData.get('new_status'),
        cancellation_reason: formData.get('cancellation_reason')
    };
    
    // Send AJAX request
    fetch('../api/visa_cancellation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Visa cancelled successfully', 'success');
            $('#cancelVisaModal').modal('hide');
            // Reload page after short delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to cancel visa', 'error');
            // Re-enable button
            this.disabled = false;
            this.innerHTML = '<?= __("cancel_visa") ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while cancelling the visa', 'error');
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<?= __("cancel_visa") ?>';
    });
});

// Re-apply functionality
function openReapplyModal(visaId, applicantName, originalProfit, baseAmount, soldAmount, currency) {
    document.getElementById('reapplyVisaId').value = visaId;
    document.getElementById('reapplyApplicantName').value = applicantName;
    document.getElementById('reapplyCurrentStatus').value = 'Cancelled/Rejected/Withdrawn';
    document.getElementById('reapplyOriginalProfit').value = originalProfit;
    document.getElementById('reapplyBaseAmount').value = baseAmount;
    document.getElementById('reapplySoldAmount').value = soldAmount;
    document.getElementById('reapplyCurrency').value = currency;
    document.getElementById('reapplyReason').value = '';
    document.getElementById('confirmReapply').checked = false;
    document.getElementById('processReapplyBtn').disabled = true;
    
    $('#reapplyVisaModal').modal('show');
}

// Enable/disable process button based on confirmation
document.addEventListener('DOMContentLoaded', function() {
    const confirmReapplyCheckbox = document.getElementById('confirmReapply');
    const processReapplyBtn = document.getElementById('processReapplyBtn');
    
    if (confirmReapplyCheckbox && processReapplyBtn) {
        confirmReapplyCheckbox.addEventListener('change', function() {
            processReapplyBtn.disabled = !this.checked;
        });
    }
});

// Process re-apply
document.getElementById('processReapplyBtn').addEventListener('click', function() {
    const form = document.getElementById('reapplyVisaForm');
    const formData = new FormData(form);
    
    
    // Disable button and show loading
    this.disabled = true;
    this.innerHTML = '<i class="feather icon-loader mr-1"></i>Processing...';
    
    // Prepare data for submission
    const data = {
        action: 'reapply_visa',
        visa_id: formData.get('visa_id'),
        new_status: formData.get('new_status'),
        reapply_reason: formData.get('reapply_reason'),
        original_profit: formData.get('original_profit'),
        base_amount: formData.get('base_amount'),
        sold_amount: formData.get('sold_amount'),
        currency: formData.get('currency')
    };
    
    // Send AJAX request
    fetch('../api/visa_reapply.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('Visa re-applied successfully', 'success');
            $('#reapplyVisaModal').modal('hide');
            // Reload page after short delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast(result.message || 'Failed to re-apply visa', 'error');
            // Re-enable button
            this.disabled = false;
            this.innerHTML = '<?= __("re_apply_visa") ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while re-applying the visa', 'error');
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<?= __("re_apply_visa") ?>';
    });
});
</script>
         
</body>
</html>
