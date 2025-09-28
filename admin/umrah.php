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
require_once('../includes/conn.php');


?>


<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">>
<link rel="stylesheet" href="css/ticket-form.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
.services-table input,
.services-table select {
    min-width: 120px; /* Makes sure inputs aren’t too tiny */
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
                                        <h5 class="m-b-10"><?= __('umrah_management') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('umrah') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- body -->
                                        <?php
                                            // Search and Pagination setup
                                            $resultsPerPage = 10; // Number of families per page
                                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                            $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
                                            $visaStatus = isset($_GET['visa_status']) ? $conn->real_escape_string($_GET['visa_status']) : '';
                                            $offset = ($page - 1) * $resultsPerPage;

                                            // ---------- COUNT QUERY ----------
                                            $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                                        FROM families f
                                                        LEFT JOIN users u ON f.created_by = u.id
                                                        WHERE 1=1 AND f.tenant_id = $tenant_id";

                                            // Add filters for count
                                            if (!empty($visaStatus)) {
                                                $countSql .= " AND f.visa_status = '$visaStatus'";
                                            }

                                            if (!empty($search)) {
                                                $countSql .= " AND (
                                                    f.head_of_family LIKE '%$search%' OR 
                                                    f.contact LIKE '%$search%' OR 
                                                    f.address LIKE '%$search%' OR 
                                                    f.package_type LIKE '%$search%' OR 
                                                    f.location LIKE '%$search%' OR 
                                                    u.name LIKE '%$search%'
                                                )";
                                            }

                                            $countResult = $conn->query($countSql);
                                            $totalFamilies = $countResult->fetch_assoc()['total'];
                                            $totalPages = ceil($totalFamilies / $resultsPerPage);

                                            // ---------- MAIN QUERY ----------
                                            $sqlFamilies = "SELECT 
                                                                f.*, 
                                                                u.name as created_by,
                                                                COUNT(ub.booking_id) AS total_members,
                                                                SUM(CASE WHEN ub.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_members
                                                            FROM families f
                                                            
                                                            LEFT JOIN users u ON f.created_by = u.id
                                                            LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                                            WHERE 1=1 AND f.tenant_id = $tenant_id";

                                            // Add filters for main query
                                            if (!empty($visaStatus)) {
                                                $sqlFamilies .= " AND f.visa_status = '$visaStatus'";
                                            }

                                            if (!empty($search)) {
                                                $sqlFamilies .= " AND (
                                                    f.head_of_family LIKE '%$search%' OR 
                                                    f.contact LIKE '%$search%' OR 
                                                    f.address LIKE '%$search%' OR 
                                                    f.package_type LIKE '%$search%' OR 
                                                    f.location LIKE '%$search%' OR 
                                                    u.name LIKE '%$search%'
                                                )";
                                            }

                                            // Group by family and order newest first
                                            $sqlFamilies .= " GROUP BY f.family_id
                                            ORDER BY f.created_at DESC
                                            LIMIT $resultsPerPage OFFSET $offset";

                                            $resultFamilies = $conn->query($sqlFamilies);

                                            // For dropdown
                                            $resultFamiliesForDropdown = $conn->query("SELECT * FROM families WHERE tenant_id = $tenant_id");
                                        ?>
                                <!-- Display Families and Bookings -->
                                <div class="container-fluid px-4">
                                    <div class="card umrah-card shadow-lg border-0 mb-4">
                                        <div class="card-header bg-primary text-white py-3">
                                            <div class="container-fluid px-0">
                                                <div class="row align-items-center">
                                                    <!-- Title Section -->
                                                    <div class="col-md-3 mb-3 mb-md-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-users mr-3 h4 mb-0"></i>
                                                            <h4 class="mb-0 font-weight-bold"><?= __('family_list') ?></h4>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Filter Tabs -->
                                                    <div class="col-md-5 mb-3 mb-md-0">
                                                        <div class="bg-opacity-10 rounded-pill p-1">
                                                            <ul class="nav nav-pills nav-fill">
                                                                <li class="nav-item">
                                                                    <a class="nav-link py-1 px-3<?= empty($visaStatus) ? ' active text-primary' : ' text-black' ?>" 
                                                                       href="?visa_status=" 
                                                                       style="border-radius: 50px;">
                                                                        <?= __('all') ?>
                                                                    </a>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <a class="nav-link py-1 px-3<?= $visaStatus === 'Not Applied' ? ' active text-primary' : ' text-black' ?>" 
                                                                       href="?visa_status=Not Applied" 
                                                                       style="border-radius: 50px;">
                                                                        <?= __('not_applied') ?>
                                                                    </a>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <a class="nav-link py-1 px-3<?= $visaStatus === 'Applied' ? ' active text-primary' : ' text-black' ?>" 
                                                                       href="?visa_status=Applied" 
                                                                       style="border-radius: 50px;">
                                                                        <?= __('applied') ?>
                                                                    </a>
                                                                </li>
                                                                <li class="nav-item">
                                                                    <a class="nav-link py-1 px-3<?= $visaStatus === 'Issued' ? ' active text-primary' : ' text-black' ?>" 
                                                                       href="?visa_status=Issued" 
                                                                       style="border-radius: 50px;">
                                                                        <?= __('issued') ?>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Action Buttons -->
                                                    <div class="col-md-4">
                                                        <div class="d-flex align-items-center justify-content-end">
                                                            <form class="flex-grow-1 me-3" id="familySearchForm" method="GET">
                                                                <div class="input-group input-group-sm">
                                                                    <input type="search" 
                                                                           class="form-control form-control-sm" 
                                                                           placeholder="<?= __('search_families') ?>" 
                                                                           name="search" 
                                                                           value="<?= htmlspecialchars($search) ?>"
                                                                           aria-label="Search families">
                                                                    <button class="btn btn-light" type="submit">
                                                                        <i class="feather icon-search"></i>
                                                                    </button>
                                                                </div>
                                                            
                                                                <div class="row" id="exchangeRateRow" style="display: none;">
                                                                    <div class="col-md-6">
                                                                        <div class="form-group">
                                                                            <label for="exchangeRate">
                                                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('exchange_rate') ?>
                                                                            </label>
                                                                            <input type="number" class="form-control" id="exchangeRate"
                                                                                   name="exchange_rate" step="0.01" min="0.01"
                                                                                   placeholder="Enter exchange rate">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            
                                                            </form>
                                                            <div class="btn-group ms-2">
                                                                <a href="umrah_refunds.php" class="btn btn-sm btn-outline-light" title="Refunds">
                                                                    <i class="feather icon-refresh-cw"></i>
                                                                </a>
                                                                <a href="umrah_date_changes.php" class="btn btn-sm btn-outline-light" title="Date Changes">
                                                                    <i class="feather icon-calendar"></i>
                                                                </a>
                                                                <button class="btn btn-sm btn-light" data-toggle="modal" data-target="#createFamilyModal" title="Add Family">
                                                                    <i class="feather icon-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover umrah-table mb-0" id="familyTable">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-left pl-4">
                                                                <i class="feather icon-user mr-2"></i><?= __('family_info') ?>
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-package mr-2"></i><?= __('package_details') ?>
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-dollar-sign mr-2"></i><?= __('financial') ?>
                                                            </th>
                                                            <th class="text-center">
                                                                <i class="feather icon-settings mr-2"></i><?= __('actions') ?>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($resultFamilies->num_rows > 0) {
                                                            while ($row = $resultFamilies->fetch_assoc()) {
                                                                $familyId = $row['family_id']; ?>
                                                                <?php
                                                                    $isFullyRefunded = ($row['total_members'] > 0 && $row['total_members'] == $row['refunded_members']);
                                                                    $rowClass = $isFullyRefunded ? 'table-danger' : '';
                                                                    ?>
                                                                <tr class="family-row <?= $rowClass ?>">
                                                                    <td class="pl-4">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="family-avatar bg-primary text-white rounded-circle mr-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                                <?= strtoupper(substr($row['head_of_family'], 0, 2)) ?>
                                                                            </div>
                                                                            <div>
                                                                                <h6 class="mb-1 font-weight-bold"><?= htmlspecialchars($row['head_of_family']) ?></h6>
                                                                                <div class="text-muted small">
                                                                                    <i class="feather icon-phone mr-1"></i><?= htmlspecialchars($row['contact']) ?>
                                                                                </div>
                                                                                <div class="text-muted small">
                                                                                    <i class="feather icon-map-pin mr-1"></i><?= htmlspecialchars($row['address']) ?>
                                                                                </div>
                                                                                <div class="text-muted small">
                                                                                    <i class="feather icon-map-pin mr-1"></i><?= htmlspecialchars($row['province']) ?>
                                                                                </div>
                                                                                <div class="text-muted small">
                                                                                    <i class="feather icon-map-pin mr-1"></i><?= htmlspecialchars($row['district']) ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-soft-info mb-2"><?= htmlspecialchars($row['package_type']) ?></span>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-map mr-1"></i><?= htmlspecialchars($row['location']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-users mr-1"></i><?= __('members') ?>: 
                                                                            <span class="badge badge-soft-primary"><?= htmlspecialchars($row['total_members']) ?></span>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-users mr-1"></i><?= __('refunded_members') ?>: 
                                                                            <span class="badge badge-soft-danger"><?= htmlspecialchars($row['refunded_members']) ?></span>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-check-circle mr-1"></i><?= __('visa') ?>: 
                                                                            <span class="badge badge-soft-<?= $row['visa_status'] == 'Approved' ? 'success' : 'warning' ?>">
                                                                                <?= htmlspecialchars($row['visa_status']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="financial-summary">
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted"><?= __('total_price') ?>:</span>
                                                                                <strong><?= htmlspecialchars($row['total_price']) ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-success"><?= __('paid') ?>:</span>
                                                                                <strong class="text-success"><?= htmlspecialchars($row['total_paid']) ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-warning"><?= __('bank') ?>:</span>
                                                                                <strong class="text-warning"><?= htmlspecialchars($row['total_paid_to_bank']) ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="text-danger"><?= __('due') ?>:</span>
                                                                                <strong class="text-danger"><?= htmlspecialchars($row['total_due']) ?></strong>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="actionMenu<?= $familyId ?>" data-toggle="dropdown">
                                                                                <?= __('actions') ?>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right shadow">
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                                                    <i class="feather icon-user-plus text-primary mr-2"></i><?= __('add_member') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="toggleMembers(<?= $familyId ?>)">
                                                                                    <i class="feather icon-list text-info mr-2"></i><?= __('view_members') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openEditFamilyModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>', 
                                                                                '<?= htmlspecialchars($row['contact']) ?>', '<?= htmlspecialchars($row['address']) ?>', 
                                                                                '<?= htmlspecialchars($row['package_type']) ?>', '<?= htmlspecialchars($row['location']) ?>', 
                                                                                '<?= htmlspecialchars($row['tazmin']) ?>', '<?= htmlspecialchars($row['visa_status']) ?>', 
                                                                                '<?= htmlspecialchars($row['province']) ?>', '<?= htmlspecialchars($row['district']) ?>')">
                                                                                    <i class="feather icon-edit text-warning mr-2"></i><?= __('edit') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyTazmin(<?= $familyId ?>)">
                                                                                    <i class="feather icon-shield text-success mr-2"></i><?= __('generate_family_tazmin') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyAgreement(<?= $familyId ?>)">
                                                                                    <i class="feather icon-file-text text-primary mr-2"></i><?= __('generate_family_agreement') ?>
                                                                                </a>
                                                                              
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCompletion(<?= $familyId ?>)">
                                                                                    <i class="feather icon-check-circle text-success mr-2"></i><?= __('generate_family_completion') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCancellation(<?= $familyId ?>)">
                                                                                    <i class="feather icon-x-circle text-warning mr-2"></i><?= __('generate_family_cancellation') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showBankLetterModal(<?= $familyId ?>)">
                                                                                    <i class="feather icon-user-x mr-2"></i><?= __("bank_receipt") ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showUmrahPresidencyModal(<?= $familyId ?>)">
                                                                                    <i class="feather icon-credit-card mr-2"></i><?= __("umrah_presidency") ?>
                                                                                </a>
                                                                            
                                                                                <div class="dropdown-divider"></div>
                                                                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteFamily(<?= $familyId ?>)">
                                                                                    <i class="feather icon-trash-2 mr-2"></i><?= __('delete') ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <!-- Members Details Row -->
                                                                <tr id="family-members-<?= $familyId ?>" style="display: none;">
                                                                    <td colspan="4" class="p-0">
                                                                        <div class="card m-2 border-primary">
                                                                            <div class="card-header bg-light">
                                                                                <h6 class="mb-0"><i class="feather icon-users mr-2"></i><?= __('family_members') ?></h6>
                                                                            </div>
                                                                            <div class="card-body p-0">
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm mb-0">
                                                                                        <thead class="thead-light">
                                                                                            <tr>
                                                                                                <th><?= __('account_info') ?></th>
                                                                                                <th><?= __('personal_details') ?></th>
                                                                                                <th><?= __('travel_info') ?></th>
                                                                                                <th><?= __('financial') ?></th>
                                                                                                <th><?= __('actions') ?></th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <?php
                                                                                            $sqlMembers = "SELECT um.*, c.name as client_name, ma.name as main_account_name, u.name as created_by,
                                                                                                GROUP_CONCAT(CONCAT(
                                                                                                    CASE ubs.service_type
                                                                                                        WHEN 'all' THEN 'All Services'
                                                                                                        WHEN 'ticket' THEN 'Ticket'
                                                                                                        WHEN 'visa' THEN 'Visa'
                                                                                                        WHEN 'hotel' THEN 'Hotel'
                                                                                                        WHEN 'transport' THEN 'Transport'
                                                                                                        ELSE ubs.service_type
                                                                                                    END,
                                                                                                    ': ', s.name, ' (Base: ', ubs.base_price, ' ', ubs.currency, ', Sold: ', ubs.sold_price, ', Profit: ', ubs.profit, ')') SEPARATOR '<br>') as services_info
                                                                                            FROM umrah_bookings um
                                                                                            LEFT JOIN clients c ON um.sold_to = c.id
                                                                                            LEFT JOIN main_account ma ON um.paid_to = ma.id
                                                                                            LEFT JOIN umrah_booking_services ubs ON um.booking_id = ubs.booking_id
                                                                                            LEFT JOIN suppliers s ON ubs.supplier_id = s.id
                                                                                            LEFT JOIN users u ON um.created_by = u.id
                                                                                            WHERE um.family_id = $familyId AND um.tenant_id = $tenant_id
                                                                                            GROUP BY um.booking_id";
                                                                                            $resultMembers = $conn->query($sqlMembers);
                                                                                            if ($resultMembers->num_rows > 0) {
                                                                                                while ($member = $resultMembers->fetch_assoc()) { ?>
                                                                                                    <tr class="<?= isset($member['status']) && $member['status'] === 'refunded' ? 'table-danger' : '' ?>">
                                                                                                        <td>
                                                                                                            <div><?= __('sold_to') ?>: <?= htmlspecialchars($member['client_name']) ?></div>
                                                                                                            <div><?= __('paid_to') ?>: <?= htmlspecialchars($member['main_account_name']) ?></div>
                                                                                                            <div><strong><?= __('services') ?>:</strong><br><?= $member['services_info'] ?: 'No services' ?></div>
                                                                                                            <div><?= __('created_by') ?>: <?= htmlspecialchars($member['created_by']) ?></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><strong><?= htmlspecialchars($member['name']) ?></strong></div>
                                                                                                            <div><?= __('dob') ?>: <?= htmlspecialchars($member['dob']) ?></div>
                                                                                                            <div><?= __('passport') ?>: <?= htmlspecialchars($member['passport_number']) ?></div>
                                                                                                            <div><?= __('id') ?>: <span class="badge badge-info"><?= htmlspecialchars($member['id_type']) ?></span></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><?= __('flight') ?>: <?= htmlspecialchars($member['flight_date']) ?></div>
                                                                                                            <div><?= __('return') ?>: <?= htmlspecialchars($member['return_date']) ?></div>
                                                                                                            <div><?= __('room') ?>: <?= htmlspecialchars($member['room_type']) ?></div>
                                                                                                            <div><?= __('duration') ?>: <?= htmlspecialchars($member['duration']) ?></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><?= __('base') ?>: <?= htmlspecialchars($member['price']) ?></div>
                                                                                                            <div><?= __('discount') ?>: <?= htmlspecialchars($member['discount']) ?></div>
                                                                                                            <div><?= __('sold') ?>: <?= htmlspecialchars($member['sold_price']) ?></div>
                                                                                                            <div class="text-success"><?= __('paid') ?>: <?= htmlspecialchars($member['paid']) ?></div>
                                                                                                            <?php
                                                                                                                                                                             // Fetch main account transactions for this booking
                                                                                                                                                                             $transactionSql = "SELECT SUM(payment_amount / COALESCE(exchange_rate, 1)) as main_account_total
                                                                                                                                                                                             FROM umrah_transactions
                                                                                                                                                                                             WHERE umrah_booking_id = {$member['booking_id']}
                                                                                                                                                                                             AND transaction_to = 'Internal Account'";
                                                                                                                                                                             $transResult = $conn->query($transactionSql);
                                                                                                                                                                             $mainAccountTotal = 0;
                                                                                                                                                                             if ($transResult && $transRow = $transResult->fetch_assoc()) {
                                                                                                                                                                                 $mainAccountTotal = $transRow['main_account_total'] ?: 0;
                                                                                                                                                                             }
                                                                                                                                                                             ?>
                                                                                                            <div class="text-primary"><?= __('internal_account') ?>: <?= htmlspecialchars($mainAccountTotal) ?></div>
                                                                                                            <div><?= __('bank') ?>: <?= htmlspecialchars($member['received_bank_payment']) ?></div>
                                                                                                            <div><?= __('receipt') ?>: <?= htmlspecialchars($member['bank_receipt_number']) ?></div>
                                                                                                            <div class="text-danger"><?= __('due') ?>: <?= htmlspecialchars($member['due']) ?></div>
                                                                                                            <div class="text-success"><?= __('profit') ?>: <?= htmlspecialchars($member['profit']) ?></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div class="dropdown">
                                                                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="actionDropdown<?= $member['booking_id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                                                    Actions
                                                                                                                </button>
                                                                                                                <div class="dropdown-menu dropdown-menu-right custom-scrollable-dropdown" aria-labelledby="actionDropdown<?= $member['booking_id'] ?>">
                                                                                                                    <!-- Primary Actions -->
                                                                                                                    <h6 class="dropdown-header"><?= __('primary_actions') ?></h6>
                                                                                                                    <a class="dropdown-item" href="#" onclick="viewMemberDetails(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-eye mr-2 text-info"></i><?= __('view_details') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="openEditMemberModal(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-edit-2 mr-2 text-warning"></i><?= __('edit') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="openTransactionTab(<?= $member['booking_id'] ?>, <?= $member['sold_price'] ?>); return false;">
                                                                                                                        <i class="feather icon-credit-card mr-2 text-primary"></i><?= __('transaction') ?>
                                                                                                                    </a>

                                                                                                                    <div class="dropdown-divider"></div>

                                                                                                                    <!-- Document Generation -->
                                                                                                                    <h6 class="dropdown-header"><?= __('documents') ?></h6>
                                                                                                                    <a class="dropdown-item" href="#" onclick="generateTazminAgreement(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-shield mr-2 text-success"></i><?= __('generate_tazmin') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="generateAgreement(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-file-text mr-2 text-primary"></i><?= __('generate_agreement') ?>
                                                                                                                    </a>
                                                                                                                   
                                                                                                                    <a class="dropdown-item" href="#" onclick="generateCompletionForm(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-check-circle mr-2 text-success"></i><?= __('generate_completion_form') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="selectForIdCard(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                                                                                                        <i class="feather icon-credit-card mr-2 text-primary"></i><?= __('select_for_id_card') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" 
                                                                                                                        onclick="selectForGroupTicket(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                                                                                                        <i class="feather icon-users mr-2 text-primary"></i><?= __('select_for_group_ticket') ?>
                                                                                                                    </a>


                                                                                                                    <div class="dropdown-divider"></div>

                                                                                                                    <!-- Advanced Actions -->
                                                                                                                    <h6 class="dropdown-header"><?= __('advanced_actions') ?></h6>
                                                                                                                    <a class="dropdown-item" href="#" onclick="openRefundModal(<?= $member['booking_id'] ?>, <?= $member['sold_price'] ?>, <?= $member['profit'] ?>, '<?= $member['currency'] ?>'); return false;">
                                                                                                                        <i class="feather icon-refresh-ccw mr-2 text-warning"></i><?= __('process_refund') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="openDateChangeModal(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>', '<?= htmlspecialchars($member['flight_date']) ?>', '<?= htmlspecialchars($member['return_date']) ?>', '<?= htmlspecialchars($member['duration']) ?>', <?= $member['price'] ?>, '<?= $member['currency'] ?>'); return false;">
                                                                                                                        <i class="feather icon-calendar mr-2 text-info"></i><?= __('request_date_change') ?>
                                                                                                                    </a>
                                                                                                                    <a class="dropdown-item" href="#" onclick="generateCancellationForm(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-x-circle mr-2 text-danger"></i><?= __('generate_cancellation_form') ?>
                                                                                                                    </a>

                                                                                                                    <div class="dropdown-divider"></div>

                                                                                                                    <!-- Danger Zone -->
                                                                                                                    <h6 class="dropdown-header text-danger"><?= __('danger_zone') ?></h6>
                                                                                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteBooking(<?= $member['booking_id'] ?>); return false;">
                                                                                                                        <i class="feather icon-trash-2 mr-2"></i><?= __('delete') ?>
                                                                                                                    </a>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                <?php }
                                                                                            } else { ?>
                                                                                                <tr>
                                                                                                    <td colspan="5" class="text-center text-muted"><?= __('no_members_found') ?></td>
                                                                                                </tr>
                                                                                            <?php } ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr>
                                                                <td colspan="4" class="text-center py-5">
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <i class="feather icon-search text-muted" style="font-size: 4rem;"></i>
                                                                        <h5 class="text-muted mt-3">
                                                                            <?= !empty($search) 
                                                                                ? sprintf(__('no_families_found_for_search'), htmlspecialchars($search)) 
                                                                                : __('no_families_available') 
                                                                            ?>
                                                                        </h5>
                                                                        <?php if (!empty($search)): ?>
                                                                            <a href="umrah.php" class="btn btn-primary mt-3">
                                                                                <i class="feather icon-x-circle mr-2"></i><?= __('clear_search') ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <p class="text-muted"><?= __('start_by_adding_a_new_family') ?></p>
                                                                            <button class="btn btn-primary mt-3" data-toggle="modal" data-target="#createFamilyModal">
                                                                                <i class="feather icon-plus mr-2"></i><?= __('add_new_family') ?>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <!-- Pagination -->
                                            <nav aria-label="Family list pagination" class="p-3">
                                                <ul class="pagination justify-content-center mb-0">
                                                    <?php 
                                                    // Preserve search parameter in pagination links
                                                    $searchParam = !empty($search) ? "&search=" . urlencode($search) : "";
                                                    
                                                    if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page - 1 . $searchParam ?>" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                                <span class="sr-only"><?= __('previous') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php 
                                                    // Show page numbers
                                                    $startPage = max(1, $page - 2);
                                                    $endPage = min($totalPages, $page + 2);
                                                    
                                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i . $searchParam ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php if ($page < $totalPages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page + 1 . $searchParam ?>" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                                <span class="sr-only"><?= __('next') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                                <div class="text-center text-muted mt-2">
                                                    <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?>
                                                    <span class="ml-2"><?= sprintf(__('total_families_x'), $totalFamilies) ?></span>
                                                </div>
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

<!-- Bootstrap Modal for Editing Umrah Booking -->
<div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMemberModalLabel"><?= __('edit_member') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <form id="editMemberForm" method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                    <input type="hidden" name="booking_id" id="editBookingId">

                    <!-- Common Fields: Sold To, Paid To -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-settings mr-2"></i><?= __('common_information') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="editSoldTo"><?= __('sold_to') ?></label>
                                    <select class="form-control" id="editSoldTo" name="soldTo" required>
                                        <option value=""><?= __('select_client') ?></option>
                                        <?php
                                        // Fetch clients from the database
                                        if ($conn->connect_error) {
                                            echo "<option value=''>Database connection failed</option>";
                                        } else {
                                            $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='{$row['id']}' data-usd='{$row['usd_balance']}' data-afs='{$row['afs_balance']}'>
                                                        {$row['name']}
                                                      </option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="editPaidTo"><?= __('paid_to') ?></label>
                                    <select class="form-control" id="editPaidTo" name="paidTo" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php
                                        // Fetch main accounts from the database
                                        if ($conn->connect_error) {
                                            echo "<option value=''>Database connection failed</option>";
                                        } else {
                                            $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='{$row['id']}' data-usd='{$row['usd_balance']}' data-afs='{$row['afs_balance']}'>
                                                        {$row['name']}
                                                      </option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Services Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('services') ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="editAddServiceBtn">
                                <i class="feather icon-plus"></i> Add Service
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered services-table" id="editServicesTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="18%"><?= __('service_type') ?></th>
                                            <th width="22%"><?= __('supplier') ?></th>
                                            <th width="10%"><?= __('currency') ?></th>
                                            <th width="15%"><?= __('base_price') ?></th>
                                            <th width="15%"><?= __('sold_price') ?></th>
                                            <th width="15%"><?= __('profit') ?></th>
                                            <th width="5%"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="editServicesTableBody">
                                        <!-- Service rows will be added here -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                            <td><input type="number" class="form-control form-control-sm" id="editTotalBasePrice" readonly value="0"></td>
                                            <td><input type="number" class="form-control form-control-sm" id="editTotalSoldPrice" readonly value="0"></td>
                                            <td><input type="number" class="form-control form-control-sm" id="editTotalProfit" readonly value="0"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>


                    <!-- Second Row: Entry Date, Name, Date of Birth -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editEntry_date"><?= __('entry_date') ?></label>
                            <input type="date" class="form-control" id="editEntry_date" name="entry_date" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editName"><?= __('name') ?></label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editDob"><?= __('date_of_birth') ?></label>
                            <input type="date" class="form-control" id="editDob" name="dob" required>
                        </div>
                    </div>

                    <!-- Additional Row: Gender and Nationality -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editGender"><?= __('gender') ?></label>
                            <select class="form-control" id="editGender" name="gender" required>
                                <option value="Male"><?= __('male') ?></option>
                                <option value="Female"><?= __('female') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editFather_name"><?= __('father_name') ?></label>
                            <input type="text" class="form-control" id="editFather_name" name="father_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editG_name"><?= __('g_name') ?></label>
                            <input type="text" class="form-control" id="editG_name" name="g_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editRelation"><?= __('relation') ?></label>
                            <select class="form-control" id="editRelation" name="relation" required>
                                <option value=""><?= __('select_relation') ?></option>
                                <option value="Ownself"><?= __('ownself') ?></option>
                                <option value="Friend"><?= __('friend') ?></option>
                                <option value="Father"><?= __('father') ?></option>
                                <option value="Mother"><?= __('mother') ?></option>
                                <option value="Brother"><?= __('brother') ?></option>
                                <option value="Sister"><?= __('sister') ?></option>
                                <option value="Son"><?= __('son') ?></option>
                                <option value="Daughter"><?= __('daughter') ?></option>
                                <option value="Wife"><?= __('wife') ?></option>
                                <option value="Husband"><?= __('husband') ?></option>
                                <option value="Grandfather"><?= __('grand_father') ?></option>
                                <option value="Grandmother"><?= __('grand_mother') ?></option>
                                <option value="Uncle"><?= __('uncle') ?></option>
                                <option value="Aunt"><?= __('aunt') ?></option>
                                <option value="Cousin"><?= __('cousin') ?></option>
                                <option value="Nephew"><?= __('nephew') ?></option>
                                <option value="Niece"><?= __('niece') ?></option>
                                <option value="Son-in-law"><?= __('son_in_law') ?></option>
                                <option value="Daughter-in-law"><?= __('daughter_in_law') ?></option>
                                <option value="Brother-in-law"><?= __('brother_in_law') ?></option>
                                <option value="Sister-in-law"><?= __('sister_in_law') ?></option>
                                <option value="Grandson"><?= __('grandson') ?></option>
                                <option value="Granddaughter"><?= __('granddaughter') ?></option>
                                <option value="Father-in-law"><?= __('father_in_law') ?></option>
                                <option value="Mother-in-law"><?= __('mother_in_law') ?></option>
                            </select>
                        </div>

                    </div>

                    <!-- Third Row: Passport Number, ID Type, Flight Date -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editPassport_number"><?= __('passport_number') ?></label>
                            <input type="text" class="form-control" id="editPassport_number" name="passport_number" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editPassport_expiry"><?= __('passport_expiry') ?></label>
                            <input type="date" class="form-control" id="editPassport_expiry" name="passport_expiry" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editId_type"><?= __('id_type') ?></label>
                            <select class="form-control" id="editId_type" name="id_type" required>
                            <option value="ID Original + Passport Original"><?= __('ID Original + Passport Original') ?></option>
                            <option value="ID Original + Passport Copy"><?= __('ID Original + Passport Copy') ?></option>
                            <option value="ID Copy + Passport Original"><?= __('ID Copy + Passport Original') ?></option>
                            <option value="ID Copy + Passport Copy"><?= __('ID Copy + Passport Copy') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Fourth Row: Return Date, Duration, Room Type -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="editFlight_date"><?= __('flight_date') ?></label>
                            <input type="date" class="form-control" id="editFlight_date" name="flight_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editReturn_date"><?= __('return_date') ?></label>
                            <input type="date" class="form-control" id="editReturn_date" name="return_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="editDuration"><?= __('duration') ?></label>
                            <select class="form-control" id="editDuration" name="duration" required>
                                <option value="5 Days"><?= __('5_days') ?></option>
                                <option value="6 Days"><?= __('6_days') ?></option>
                                <option value="7 Days"><?= __('7_days') ?></option>
                                <option value="8 Days"><?= __('8_days') ?></option>
                                <option value="9 Days"><?= __('9_days') ?></option>
                                <option value="10 Days"><?= __('10_days') ?></option>
                                <option value="11 Days"><?= __('11_days') ?></option>
                                <option value="12 Days"><?= __('12_days') ?></option>
                                <option value="13 Days"><?= __('13_days') ?></option>
                                <option value="14 Days"><?= __('14_days') ?></option>
                                <option value="15 Days"><?= __('15_days') ?></option>
                                <option value="16 Days"><?= __('16_days') ?></option>
                                <option value="17 Days"><?= __('17_days') ?></option>
                                <option value="18 Days"><?= __('18_days') ?></option>
                                <option value="19 Days"><?= __('19_days') ?></option>
                                <option value="20 Days"><?= __('20_days') ?></option>
                                <option value="21 Days"><?= __('21_days') ?></option>
                                <option value="22 Days"><?= __('22_days') ?></option>
                                <option value="23 Days"><?= __('23_days') ?></option>
                                <option value="24 Days"><?= __('24_days') ?></option>
                                <option value="25 Days"><?= __('25_days') ?></option>
                                <option value="26 Days"><?= __('26_days') ?></option>
                                <option value="27 Days"><?= __('27_days') ?></option>
                                <option value="28 Days"><?= __('28_days') ?></option>
                                <option value="29 Days"><?= __('29_days') ?></option>
                                <option value="30 Days"><?= __('30_days') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Room Type -->
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="editRoom_type"><?= __('room_type') ?></label>
                            <select class="form-control" id="editRoom_type" name="room_type" required>
                                <option value="1 Bed"><?= __('1_bed') ?></option>
                                <option value="2 Beds"><?= __('2_beds') ?></option>
                                <option value="3 Beds"><?= __('3_beds') ?></option>
                                <option value="Shared"><?= __('shared') ?></option>
                                <option value="No Room"><?= __('no_room') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Discount (applied to total sold price) -->
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="editDiscount"><?= __('discount') ?> (<?= __('applied_to_total_sold_price') ?>)</label>
                            <input type="number" class="form-control" id="editDiscount" name="discount" value="0" min="0" step="0.01">
                        </div>
                    </div>



                    <!-- Eighth Row: Due Amount and Additional Fields -->
                    <div class="row">
                            <input type="hidden" class="form-control" id="editDue" name="due" readonly>

                        <div class="form-group col-md-12">
                            <label for="editRemarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="editRemarks" name="remarks"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= __('update_booking') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal for Adding Umrah Booking -->
<div class="modal fade" id="umrahModal" tabindex="-1" aria-labelledby="umrahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="umrahModalLabel"><?= __('add_new_members') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <form id="umrahForm" method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

                    <input type="hidden" name="family_id" id="familyId">

                    <!-- Common Fields: Sold To, Paid To -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-settings mr-2"></i><?= __('common_information') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="soldTo"><?= __('sold_to') ?></label>
                                    <select class="form-control" id="soldTo" name="soldTo" required>
                                        <option value=""><?= __('select_client') ?></option>
                                        <?php
                                        // Fetch clients from the database
                                        if ($conn->connect_error) {
                                            echo "<option value=''>Database connection failed</option>";
                                        } else {
                                            $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='{$row['id']}'>
                                                        {$row['name']}
                                                      </option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="paidTo"><?= __('paid_to') ?></label>
                                    <select class="form-control" id="paidTo" name="paidTo" required>
                                        <option value=""><?= __('select_main_account') ?></option>
                                        <?php
                                        // Fetch main accounts from the database
                                        if ($conn->connect_error) {
                                            echo "<option value=''>Database connection failed</option>";
                                        } else {
                                            $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='{$row['id']}'>
                                                        {$row['name']}
                                                      </option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Services Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="feather icon-package mr-2"></i><?= __('services') ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addServiceBtn">
                                <i class="feather icon-plus"></i> Add Service
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered services-table" id="servicesTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="18%"><?= __('service_type') ?></th>
                                            <th width="22%"><?= __('supplier') ?></th>
                                            <th width="10%"><?= __('currency') ?></th>
                                            <th width="15%"><?= __('base_price') ?></th>
                                            <th width="15%"><?= __('sold_price') ?></th>
                                            <th width="15%"><?= __('profit') ?></th>
                                            <th width="5%"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="servicesTableBody">
                                        <!-- Service rows will be added here -->
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                            <td><input type="number" class="form-control form-control-sm" id="totalBasePrice" readonly value="0"></td>
                                            <td><input type="number" class="form-control form-control-sm" id="totalSoldPrice" readonly value="0"></td>
                                            <td><input type="number" class="form-control form-control-sm" id="totalProfit" readonly value="0"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>


                    <!-- Second Row: Entry Date, Name, Date of Birth -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="entry_date"><?= __('entry_date') ?></label>
                            <input type="date" class="form-control" id="entry_date" name="entry_date" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="name"><?= __('name') ?></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="dob"><?= __('date_of_birth') ?></label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                    </div>

                    <!-- Additional Row: Gender and Nationality -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="gender"><?= __('gender') ?></label>
                            <select class="form-control" id="gender" name="gender" required>
                                <option value="Male"><?= __('male') ?></option>
                                <option value="Female"><?= __('female') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="father_name"><?= __('father_name') ?></label>
                            <input type="text" class="form-control" id="father_name" name="father_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="g_name"><?= __('g_name') ?></label>
                            <input type="text" class="form-control" id="g_name" name="g_name" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="relation"><?= __('relation') ?></label>
                            <select class="form-control" id="relation" name="relation" required>
                                <option value=""><?= __('select_relation') ?></option>
                                <option value="Ownself"><?= __('ownself') ?></option>
                                <option value="Friend"><?= __('friend') ?></option>
                                <option value="Father"><?= __('father') ?></option>
                                <option value="Mother"><?= __('mother') ?></option>
                                <option value="Brother"><?= __('brother') ?></option>
                                <option value="Sister"><?= __('sister') ?></option>
                                <option value="Son"><?= __('son') ?></option>
                                <option value="Daughter"><?= __('daughter') ?></option>
                                <option value="Wife"><?= __('wife') ?></option>
                                <option value="Husband"><?= __('husband') ?></option>
                                <option value="Grandfather"><?= __('grand_father') ?></option>
                                <option value="Grandmother"><?= __('grand_mother') ?></option>
                                <option value="Uncle"><?= __('uncle') ?></option>
                                <option value="Aunt"><?= __('aunt') ?></option>
                                <option value="Cousin"><?= __('cousin') ?></option>
                                <option value="Nephew"><?= __('nephew') ?></option>
                                <option value="Niece"><?= __('niece') ?></option>
                                <option value="Son-in-law"><?= __('son_in_law') ?></option>
                                <option value="Daughter-in-law"><?= __('daughter_in_law') ?></option>
                                <option value="Brother-in-law"><?= __('brother_in_law') ?></option>
                                <option value="Sister-in-law"><?= __('sister_in_law') ?></option>
                                <option value="Grandson"><?= __('grandson') ?></option>
                                <option value="Granddaughter"><?= __('granddaughter') ?></option>
                                <option value="Father-in-law"><?= __('father_in_law') ?></option>
                                <option value="Mother-in-law"><?= __('mother_in_law') ?></option>
                            </select>
                        </div>
                        
                    </div>

                    <!-- Third Row: Passport Number, ID Type, Flight Date -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="passport_number"><?= __('passport_number') ?></label>
                            <input type="text" class="form-control" id="passport_number" name="passport_number" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="passport_expiry"><?= __('passport_expiry') ?></label>
                            <input type="date" class="form-control" id="passport_expiry" name="passport_expiry" required>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="id_type"><?= __('id_type') ?></label>
                            <select class="form-control" id="id_type" name="id_type" required>
                            <option value="ID Original + Passport Original"><?= __('ID Original + Passport Original') ?></option>
                            <option value="ID Original + Passport Copy"><?= __('ID Original + Passport Copy') ?></option>
                            <option value="ID Copy + Passport Original"><?= __('ID Copy + Passport Original') ?></option>
                            <option value="ID Copy + Passport Copy"><?= __('ID Copy + Passport Copy') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Fourth Row: Return Date, Duration, Room Type -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label for="flight_date"><?= __('flight_date') ?></label>
                            <input type="date" class="form-control" id="flight_date" name="flight_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="return_date"><?= __('return_date') ?></label>
                            <input type="date" class="form-control" id="return_date" name="return_date">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="duration"><?= __('duration') ?></label>
                            <select class="form-control" id="duration" name="duration" required>
                                <option value="5 Days"><?= __('5_days') ?></option>
                                <option value="6 Days"><?= __('6_days') ?></option>
                                <option value="7 Days"><?= __('7_days') ?></option>
                                <option value="8 Days"><?= __('8_days') ?></option>
                                <option value="9 Days"><?= __('9_days') ?></option>
                                <option value="10 Days"><?= __('10_days') ?></option>
                                <option value="11 Days"><?= __('11_days') ?></option>
                                <option value="12 Days"><?= __('12_days') ?></option>
                                <option value="13 Days"><?= __('13_days') ?></option>
                                <option value="14 Days"><?= __('14_days') ?></option>
                                <option value="15 Days"><?= __('15_days') ?></option>
                                <option value="16 Days"><?= __('16_days') ?></option>
                                <option value="17 Days"><?= __('17_days') ?></option>
                                <option value="18 Days"><?= __('18_days') ?></option>
                                <option value="19 Days"><?= __('19_days') ?></option>
                                <option value="20 Days"><?= __('20_days') ?></option>
                                <option value="21 Days"><?= __('21_days') ?></option>
                                <option value="22 Days"><?= __('22_days') ?></option>
                                <option value="23 Days"><?= __('23_days') ?></option>
                                <option value="24 Days"><?= __('24_days') ?></option>
                                <option value="25 Days"><?= __('25_days') ?></option>
                                <option value="26 Days"><?= __('26_days') ?></option>
                                <option value="27 Days"><?= __('27_days') ?></option>
                                <option value="28 Days"><?= __('28_days') ?></option>
                                <option value="29 Days"><?= __('29_days') ?></option>
                                <option value="30 Days"><?= __('30_days') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Room Type -->
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="room_type"><?= __('room_type') ?></label>
                            <select class="form-control" id="room_type" name="room_type" required>
                                <option value="1 Bed"><?= __('1_bed') ?></option>
                                <option value="2 Beds"><?= __('2_beds') ?></option>
                                <option value="3 Beds"><?= __('3_beds') ?></option>
                                <option value="Shared"><?= __('shared') ?></option>
                                <option value="No Room"><?= __('no_room') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Discount (applied to total sold price) -->
                    <div class="row">
                        <div class="form-group col-md-12">
                            <label for="discount"><?= __('discount') ?> (<?= __('applied_to_total_sold_price') ?>)</label>
                            <input type="number" class="form-control" id="discount" name="discount" value="0" min="0" step="0.01">
                        </div>
                    </div>

                  
                        
                            <input type="hidden" class="form-control" id="received_bank_payment" name="received_bank_payment">
                        
                        
                            <input type="hidden" class="form-control" id="bank_receipt_number" name="bank_receipt_number">
                      
                            <input type="hidden" class="form-control" id="paid" name="paid">
                       
                   

                    <!-- Eighth Row: Due Amount and Additional Fields -->
                    <div class="row">
                            <input type="hidden" class="form-control" id="due" name="due" readonly>
                    
                        <div class="form-group col-md-12">
                            <label for="remarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="remarks" name="remarks"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= __('add_booking') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal to Create a New Family -->
<div class="modal umrah-modal fade" id="createFamilyModal" tabindex="-1" aria-labelledby="createFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createFamilyModalLabel"><?= __('create_new_family') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createFamilyForm" method="POST" onsubmit="return submitCreateFamilyForm();">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        
                    <div class="form-group">
                        <label for="head_of_family"><?= __('family_head') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="head_of_family" name="head_of_family" required>
                    </div>
                    <div class="form-group">
                        <label for="contact"><?= __('contact_number') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="contact" name="contact" required>
                    </div>
                    <div class="form-group">
                        <label for="address"><?= __('address') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="address" name="address" required>
                    </div>
                    <div class="form-group">
                        <label for="package_type"><?= __('package_type') ?></label>
                        <select class="form-control umrah-form-control" id="package_type" name="package_type" required>
                            <option value="Full Package"><?= __('full_package') ?></option>
                            <option value="Visa"><?= __('visa') ?></option>
                            <option value="Services"><?= __('services') ?></option>
                            <option value="Ticket+Visa"><?= __('ticket_visa') ?></option>
                            <option value="Visa+Services"><?= __('visa_services') ?></option>
                            <option value="Visa+Transport"><?= __('visa_transport') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location"><?= __('location') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="location" name="location" required>
                    </div>
                    <div class="form-group">
                            <label for="tazmin"><?= __('tazmin') ?></label>
                        <select class="form-control umrah-form-control" id="tazmin" name="tazmin" required>
                            <option value="Done"><?= __('done') ?></option>
                            <option value="Not Done"><?= __('not_done') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                            <label for="visa_status"><?= __('visa_status') ?></label>
                            <select class="form-control umrah-form-control" id="visa_status" name="visa_status" required>
                                <option value="Not Applied"><?= __('not_applied') ?></option>
                                <option value="Applied"><?= __('applied') ?></option>
                                <option value="Issued"><?= __('issued') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                        <label for="province"><?= __('province') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="province" name="province" required>
                    </div>
                    <div class="form-group">
                        <label for="district"><?= __('district') ?></label>
                        <input type="text" class="form-control umrah-form-control" id="district" name="district" required>
                    </div>
                    <button type="submit" class="btn umrah-btn umrah-btn-primary"><?= __('create_family') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Modal -->
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
                <!-- Umrah Info Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('umrah_details') ?></h6>
                                <p class="mb-1"><strong><?= __('umrah_id') ?>:</strong> <span id="transactionUmrahId"></span></p>
                                <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                <p class="mb-1"><strong><?= __('package') ?>:</strong> <span id="trans-package-name"></span></p>
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
                                        <th><?= __('payment_type') ?></th>
                                        <th><?= __('transaction_to') ?></th>
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
                            <form id="umrahTransactionForm">
                                <input type="hidden" id="transactionUmrahIdInput" name="umrah_id">
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
                                            <label for="transaction_to">
                                                <i class="feather icon-user mr-1"></i><?= __('transaction_to') ?>
                                            </label>
                                            <select class="form-control" id="transaction_to" name="transaction_to" required>
                                                <option value="Internal Account"><?= __('internal_account') ?></option>
                                                <option value="Bank"><?= __('bank') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
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
                                                <option value="USD">USD</option>
                                                <option value="AFS">AFS</option>
                                                <option value="EUR">EUR</option>
                                                <option value="DARHAM">DARHAM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group" id="receiptNumberField" style="display: none;">
                                            <label for="receiptNumber">
                                                <i class="feather icon-file-text mr-1"></i><?= __('receipt_number') ?>
                                            </label>
                                            <input type="text" class="form-control" id="receiptNumber" 
                                                   name="receipt_number" placeholder="Enter receipt number">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
        <div class="form-group" style="display: none;">
            <label for="transactionExchangeRate">
                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
            </label>
            <input type="number" class="form-control" id="transactionExchangeRate"
                   name="exchange_rate" step="0.01" min="0.01" placeholder="Enter exchange rate">
        </div>
    </div>
                                <div class="form-group">
                                    <label for="paymentDescription">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="paymentDescription" 
                                              name="payment_description" rows="2" 
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

<script>
// Show/Hide Receipt Number field based on Transaction To selection
$(document).ready(function() {
    $('#transaction_to').change(function() {
        if ($(this).val() === 'Bank') {
            $('#receiptNumberField').slideDown();
        } else {
            $('#receiptNumberField').slideUp();
        }
    });
});
</script>

<!-- Edit Family Modal -->
<div class="modal fade" id="editFamilyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('edit_family_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editFamilyForm">
                    <input type="hidden" id="editFamilyId" name="family_id">
                    <div class="form-group">
                        <label><?= __('family_head') ?></label>
                        <input type="text" class="form-control" id="editHeadOfFamily" name="head_of_family">
                    </div>
                    <div class="form-group">
                        <label><?= __('contact') ?></label>
                        <input type="text" class="form-control" id="editContact" name="contact">
                    </div>
                    <div class="form-group">
                        <label><?= __('address') ?></label>
                        <input type="text" class="form-control" id="editAddress" name="address">
                    </div>
                    <div class="form-group">
                        <label for="editPackageType">Package Type:</label>
                        <select class="form-control" id="editPackageType" name="package_type" required>
                            <option value="Full Package"><?= __('full_package') ?></option>
                            <option value="Visa"><?= __('visa') ?></option>
                            <option value="Services"><?= __('services') ?></option>
                            <option value="Ticket+Visa"><?= __('ticket_visa') ?></option>
                            <option value="Visa+Services"><?= __('visa_services') ?></option>
                            <option value="Visa+Transport"><?= __('visa_transport') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('location') ?></label>
                        <input type="text" class="form-control" id="editLocation" name="location">
                    </div>
                    <div class="form-group">
                        <label for="editTazmin"><?= __('tazmin') ?></label>
                        <select class="form-control" id="editTazmin" name="tazmin" required>
                            <option value="Done"><?= __('done') ?></option>
                            <option value="Not Done"><?= __('not_done') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                            <label for="editStatus"><?= __('visa_status') ?></label>
                            <select class="form-control" id="editStatus" name="visa_status" required>
                                <option value="Not Applied"><?= __('not_applied') ?></option>
                                <option value="Applied"><?= __('applied') ?></option>
                                <option value="Issued"><?= __('issued') ?></option>
                            </select>
                        </div>
                    <div class="form-group">
                        <label for="editProvince"><?= __('province') ?></label>
                        <input type="text" class="form-control" id="editProvince" name="province">
                    </div>
                        <div class="form-group">
                        <label for="editDistrict"><?= __('district') ?></label>
                        <input type="text" class="form-control" id="editDistrict" name="district">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>


                               <!-- Profile Modal -->
                               <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>



                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
    <!-- Required Js -->
    <script src="../assets/plugins/jquery/js/jquery.min.js"></script>
    <script src="../assets/js/vendor-all.min.js"></script>
 <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- Custom Scripts -->
    <script>
        // Toast notification function
        function showToast(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            
            Toast.fire({
                icon: type,
                title: message
            });
        }

        // Ensure jQuery and other dependencies are loaded
        $(document).ready(function() {
            // Refund handling is now managed by js/umrah/refund.js
        });
    </script>

    <script src="js/umrah/transaction_manager.js"></script>
    <script src="js/umrah/bookings.js"></script>
    <script src="js/umrah/edit_bookings.js"></script>
    <script src="js/umrah/refund.js?v=1"></script>
    <script src="js/umrah/idcard.js"></script>
    <script src="js/umrah/groupTickets.js"></script>
    <script src="js/umrah/profile.js"></script>
    <script src="js/umrah/family.js"></script>
    <script src="js/umrah/generations.js"></script>
    <script src="js/umrah/generations_received_form.js"></script>
    <script src="js/umrah/generate_completion.js"></script>
    <script src="js/umrah/generate_cancelation.js"></script>
    <script src="js/umrah-forms.js"></script>
    <script src="js/umrah/family_documents.js"></script>
    <script src="js/umrah/generate_bankandumrah.js"></script>
    

<script>
document.getElementById('editSupplier').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const currencyMatch = selectedOption.text.match(/\((.*?)\s*(USD|AFS)\)/);
    if (currencyMatch) {
        document.getElementById('editSupplierCurrency').value = currencyMatch[2];
    } else {
        document.getElementById('editSupplierCurrency').value = '';
    }
});
</script>


<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel"><?= __('edit_transaction') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editUmrahId" name="umrah_id">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    
                    <div class="form-group">
                        <label for="editPaymentDate"><?= __('payment_date') ?></label>
                        <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentTime"><?= __('payment_time') ?></label>
                        <input type="time" class="form-control" id="editPaymentTime" name="payment_time" required step="any">
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentAmount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                    </div>
                    <div class="form-group">
                        <label for="editExchangeRate"><?= __('exchange_rate') ?></label>
                        <input type="number" step="0.01" class="form-control" id="editExchangeRate" name="exchange_rate" required>
                    </div>
                    <div class="form-group">
                        <label for="editPaymentDescription"><?= __('description') ?></label>
                        <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="3"></textarea>
                    </div>
                    
                    <div class="text-right">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                    </div>
                </form>
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
                                    <th><?= __('passenger') ?></th>
                                    <th><?= __('passport') ?></th>
                                    <th><?= __('package') ?></th>
                                    <th><?= __('duration') ?></th>
                                    <th><?= __('flight_date') ?></th>
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


<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="refundForm" onsubmit="return false;">
                <div class="modal-body">
                    <input type="hidden" id="refund_booking_id" name="booking_id">
                    <input type="hidden" id="refund_original_amount" name="original_amount">
                    <input type="hidden" id="refund_original_profit" name="original_profit">
                    <input type="hidden" id="refund_currency" name="currency">
                    
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= __('original_amount') ?>:</span>
                            <strong id="displayOriginalAmount">-</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= __('original_profit') ?>:</span>
                            <strong id="displayOriginalProfit">-</strong>
                        </div>
                        
                    </div>

                    <div class="form-group">
                        <label for="refund_type"><?=__('refund_type')?></label>
                        <select class="form-control" id="refund_type" name="refund_type" required onchange="toggleRefundAmount()">
                            <option value=""><?=__('select_refund_type')?></option>
                            <option value="full"><?=__('full_refund')?></option>
                            <option value="partial"><?=__('partial_refund')?></option>
                        </select>
                    </div>

                    <div class="form-group" id="refundAmountGroup" style="display: none;">
                        <label for="refund_amount"><?= __('refund_amount') ?></label>
                        <input type="number" class="form-control" id="refund_amount" name="refund_amount">
                    </div>

                    <div class="form-group">
                        <label for="refund_reason"><?= __('reason_for_refund') ?></label>
                        <textarea class="form-control" id="refund_reason" name="reason" 
                                  rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="button" class="btn btn-primary" id="processRefundBtn" onclick="console.log('Button clicked directly');">
                        <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<!-- Include Umrah Forms JS -->
<script src="js/umrah-forms.js"></script>



<script>
// Toggle between direct and indirect flight forms
document.addEventListener('DOMContentLoaded', function() {
    const directRadio = document.getElementById('directFlight');
    const indirectRadio = document.getElementById('indirectFlight');
    const directFields = document.getElementById('directFlightFields');
    const indirectFields = document.getElementById('indirectFlightFields');
    const directDates = document.getElementById('directFlightDates');

    function toggleFlightType() {
        if (directRadio.checked) {
            directFields.style.display = 'block';
            directDates.style.display = 'block';
            indirectFields.style.display = 'none';
            
            // Make direct flight fields required
            directFields.querySelectorAll('input').forEach(input => input.required = true);
            directDates.querySelectorAll('input').forEach(input => input.required = true);
            
            // Remove required from indirect fields
            indirectFields.querySelectorAll('input').forEach(input => input.required = false);
        } else {
            directFields.style.display = 'none';
            directDates.style.display = 'none';
            indirectFields.style.display = 'block';
            
            // Make indirect flight fields required
            indirectFields.querySelectorAll('input').forEach(input => input.required = true);
            
            // Remove required from direct fields
            directFields.querySelectorAll('input').forEach(input => input.required = false);
            directDates.querySelectorAll('input').forEach(input => input.required = false);
        }
    }

    directRadio.addEventListener('change', toggleFlightType);
    indirectRadio.addEventListener('change', toggleFlightType);

    // Calculate stopover duration
    function calculateStopover() {
        const leg1Arrival = document.getElementById('leg1ArrivalDate').value + ' ' + document.getElementById('leg1ArrivalTime').value;
        const leg2Departure = document.getElementById('leg2DepartureDate').value + ' ' + document.getElementById('leg2DepartureTime').value;
        
        if (leg1Arrival && leg2Departure) {
            const arrival = new Date(leg1Arrival);
            const departure = new Date(leg2Departure);
            const diffMs = departure - arrival;
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('stopoverDuration').textContent = `${diffHours}h ${diffMins}m`;
        }
    }

    // Add event listeners for stopover calculation
    ['leg1ArrivalDate', 'leg1ArrivalTime', 'leg2DepartureDate', 'leg2DepartureTime'].forEach(id => {
        document.getElementById(id).addEventListener('change', calculateStopover);
    });

    // Initialize
    toggleFlightType();
});
</script>

<!-- Floating action button -->
<div id="groupTicketFloatingButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050; display: none;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="showGroupTicketModal" title="<?= __('generate_group_ticket') ?>">
        <i class="feather icon-airplay"></i>
        <span class="badge badge-light badge-pill position-absolute" style="top: -5px; right: -5px;" id="groupTicketSelectionCount">0</span>
    </button>
</div>


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>





<script>
var suppliersData = [];

function loadSuppliers() {
    return $.getJSON('ajax/get_suppliers.php').then(data => {
        suppliersData = data.success ? data.suppliers : [];
        console.log('Suppliers loaded:', suppliersData.length);
    }).catch(() => { suppliersData = []; });
}

let serviceRowCounter = 0;

function addServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0) {
    serviceRowCounter++;
    const rowId = 'serviceRow_' + serviceRowCounter;

    const suppliersOptions = suppliersData.map(s => `<option value="${s.id}" data-currency="${s.currency}">${s.name}</option>`).join('');

    const rowHtml = `
        <tr id="${rowId}">
            <td>
                <select class="form-control service-type" name="services[${serviceRowCounter}][service_type]" required>
                    <option value="">Select Service Type</option>
                    <option value="all" ${serviceType==='all'?'selected':''}>All Services</option>
                    <option value="ticket" ${serviceType==='ticket'?'selected':''}>Ticket</option>
                    <option value="visa" ${serviceType==='visa'?'selected':''}>Visa</option>
                    <option value="hotel" ${serviceType==='hotel'?'selected':''}>Hotel</option>
                    <option value="transport" ${serviceType==='transport'?'selected':''}>Transport</option>
                </select>
            </td>
            <td>
                <select class="form-control service-supplier" name="services[${serviceRowCounter}][supplier_id]" required>
                    <option value="">Select Supplier</option>
                    ${suppliersOptions}
                </select>
            </td>
            <td><input type="text" class="form-control service-currency" name="services[${serviceRowCounter}][currency]" readonly></td>
            <td><input type="number" class="form-control service-base-price" name="services[${serviceRowCounter}][base_price]" value="${basePrice}" min="0" step="0.01" required></td>
            <td><input type="number" class="form-control service-sold-price" name="services[${serviceRowCounter}][sold_price]" value="${soldPrice}" min="0" step="0.01" required></td>
            <td><input type="number" class="form-control service-profit" name="services[${serviceRowCounter}][profit]" readonly></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeServiceRow('${rowId}')">
                    <i class="feather icon-trash-2"></i>
                </button>
            </td>
        </tr>
    `;

    $('#servicesTableBody').append(rowHtml);
    if(supplierId) $(`#${rowId} .service-supplier`).val(supplierId).trigger('change');
    updateTotals();
}

function removeServiceRow(rowId) { $('#' + rowId).remove(); updateTotals(); }

function updateTotals() {
    let totalBase=0, totalSold=0, totalProfit=0;
    const discount = parseFloat($('#discount').val()) || 0;
    $('#servicesTableBody tr').each(function() {
        const base = parseFloat($(this).find('.service-base-price').val()) || 0;
        const sold = parseFloat($(this).find('.service-sold-price').val()) || 0;
        const profit = sold - base;
        $(this).find('.service-profit').val(profit.toFixed(2));
        totalBase += base; totalSold += sold; totalProfit += profit;
    });
    const discountedSold = totalSold - discount;
    $('#totalBasePrice').val(totalBase.toFixed(2));
    $('#totalSoldPrice').val(discountedSold.toFixed(2));
    $('#totalProfit').val((discountedSold - totalBase).toFixed(2));
}

// Event bindings
$(document).on('click', '#addServiceBtn', () => addServiceRow());
$(document).on('change', '.service-supplier', function() {
    const currency = $(this).find('option:selected').data('currency') || '';
    $(this).closest('tr').find('.service-currency').val(currency);
});
$(document).on('input', '.service-base-price, .service-sold-price, #discount', updateTotals);

// Ensure at least one service row when modal opens
$('#umrahModal').on('shown.bs.modal', function() {
    if ($('#servicesTableBody tr').length === 0) {
        loadSuppliers().then(() => addServiceRow());
    }
});
</script>
<script>
// Edit modal service management functions
let editServiceRowCounter = 0;

function addEditServiceRow(serviceType = '', supplierId = '', basePrice = 0, soldPrice = 0, serviceId = null) {
    editServiceRowCounter++;
    const rowId = 'editServiceRow_' + editServiceRowCounter;

    const suppliersOptions = suppliersData.map(supplier =>
        `<option value="${supplier.id}" data-currency="${supplier.currency}" ${supplierId == supplier.id ? 'selected' : ''}>${supplier.name}</option>`
    ).join('');

    const rowHtml = `
        <tr id="${rowId}" data-service-id="${serviceId || ''}">
            <td>
                <select class="form-control edit-service-type" name="edit_services[${editServiceRowCounter}][service_type]" required>
                    <option value="">Select Service Type</option>
                    <option value="all" ${serviceType === 'all' ? 'selected' : ''}>All Services</option>
                    <option value="ticket" ${serviceType === 'ticket' ? 'selected' : ''}>Ticket</option>
                    <option value="visa" ${serviceType === 'visa' ? 'selected' : ''}>Visa</option>
                    <option value="hotel" ${serviceType === 'hotel' ? 'selected' : ''}>Hotel</option>
                    <option value="transport" ${serviceType === 'transport' ? 'selected' : ''}>Transport</option>
                </select>
            </td>
            <td>
                <select class="form-control edit-service-supplier" name="edit_services[${editServiceRowCounter}][supplier_id]" required>
                    <option value="">Select Supplier</option>
                    ${suppliersOptions}
                </select>
            </td>
            <td>
                <input type="text" class="form-control edit-service-currency" name="edit_services[${editServiceRowCounter}][currency]" readonly>
            </td>
            <td>
                <input type="number" class="form-control edit-service-base-price" name="edit_services[${editServiceRowCounter}][base_price]"
                       value="${basePrice}" min="0" step="0.01" required>
            </td>
            <td>
                <input type="number" class="form-control edit-service-sold-price" name="edit_services[${editServiceRowCounter}][sold_price]"
                       value="${soldPrice}" min="0" step="0.01" required>
            </td>
            <td>
                <input type="number" class="form-control edit-service-profit" name="edit_services[${editServiceRowCounter}][profit]" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-edit-service-btn" onclick="removeEditServiceRow('${rowId}')">
                    <i class="feather icon-trash-2"></i>
                </button>
            </td>
        </tr>
    `;

    $('#editServicesTableBody').append(rowHtml);

    // Set currency if supplier is selected
    if (supplierId) {
        const selectedSupplier = suppliersData.find(s => s.id == supplierId);
        if (selectedSupplier) {
            $(`#${rowId} .edit-service-currency`).val(selectedSupplier.currency);
        }
    }

    updateEditTotals();
}

function removeEditServiceRow(rowId) {
    $('#' + rowId).remove();
    updateEditTotals();
}

function updateEditTotals() {
    let totalBase = 0;
    let totalSold = 0;
    let totalProfit = 0;
    const discount = parseFloat($('#editDiscount').val()) || 0;

    $('#editServicesTableBody tr').each(function() {
        const basePrice = parseFloat($(this).find('.edit-service-base-price').val()) || 0;
        const soldPrice = parseFloat($(this).find('.edit-service-sold-price').val()) || 0;
        const profit = soldPrice - basePrice;

        $(this).find('.edit-service-profit').val(profit.toFixed(2));

        totalBase += basePrice;
        totalSold += soldPrice;
        totalProfit += profit;
    });

    // Apply discount to sold price
    const discountedSold = totalSold - discount;
    const finalProfit = discountedSold - totalBase;

    // Update visible totals
    $('#editTotalBasePrice').val(totalBase.toFixed(2));
    $('#editTotalSoldPrice').val(discountedSold.toFixed(2));
    $('#editTotalProfit').val(finalProfit.toFixed(2));

    // Update hidden fields to ensure they are sent in the form
    if ($('#editTotalBasePriceHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalBasePriceHidden',
            name: 'total_base_price',
            value: totalBase.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalBasePriceHidden').val(totalBase.toFixed(2));
    }

    if ($('#editTotalSoldPriceHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalSoldPriceHidden',
            name: 'total_sold_price',
            value: discountedSold.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalSoldPriceHidden').val(discountedSold.toFixed(2));
    }

    if ($('#editTotalProfitHidden').length === 0) {
        $('<input>').attr({
            type: 'hidden',
            id: 'editTotalProfitHidden',
            name: 'total_profit',
            value: finalProfit.toFixed(2)
        }).appendTo('#editMemberForm');
    } else {
        $('#editTotalProfitHidden').val(finalProfit.toFixed(2));
    }

    // Update due
    const paid = parseFloat($('#editPaidAmount')?.val() || 0); // if you have a paid field
    const due = discountedSold - paid;
    $('#editDue').val(due.toFixed(2));
}


// Event handlers for edit modal
$(document).on('change', '.edit-service-supplier', function() {
    const selectedOption = $(this).find('option:selected');
    const currency = selectedOption.data('currency') || '';
    $(this).closest('tr').find('.edit-service-currency').val(currency);
});

$(document).on('input', '.edit-service-base-price, .edit-service-sold-price, #editDiscount', function() {
    updateEditTotals();
});

$(document).on('click', '#editAddServiceBtn', function() {
    addEditServiceRow();
});

// Edit form submission
$(document).on('submit', '#editMemberForm', function(event) {
    event.preventDefault();
    console.log("Edit form submitted!");

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="feather icon-loader"></i> updating...';

    let formData = new FormData(event.target);

    fetch("update_umrah_member.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        console.log("Server Response:", data);
        if (data.success) {
            alert("umrah_member_updated_successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "failed_to_update_member"));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("an_error_occurred");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});

// Initialize with one empty row
$(document).ready(function() {
    loadSuppliers().then(() => {
        addServiceRow();
    });
});

function openEditMemberModal(bookingId) {
    console.log('Opening edit modal for booking:', bookingId);

    // Show loading state
    Swal.fire({
        title: '<?= __("loading") ?>',
        text: '<?= __("please_wait") ?>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch member details
    fetch(`ajax/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                // Populate form fields
                document.getElementById('editBookingId').value = member.booking_id;
                document.getElementById('editSoldTo').value = member.sold_to;
                document.getElementById('editPaidTo').value = member.paid_to;
                document.getElementById('editEntry_date').value = member.entry_date;
                document.getElementById('editName').value = member.name;
                document.getElementById('editDob').value = member.dob;
                document.getElementById('editGender').value = member.gender;
                document.getElementById('editFather_name').value = member.fname;
                document.getElementById('editG_name').value = member.gfname;
                document.getElementById('editRelation').value = member.relation;
                document.getElementById('editPassport_number').value = member.passport_number;
                document.getElementById('editPassport_expiry').value = member.passport_expiry;
                document.getElementById('editId_type').value = member.id_type;
                document.getElementById('editFlight_date').value = member.flight_date;
                document.getElementById('editReturn_date').value = member.return_date;
                document.getElementById('editDuration').value = member.duration;
                document.getElementById('editRoom_type').value = member.room_type;
                document.getElementById('editDiscount').value = member.discount || 0;
                document.getElementById('editRemarks').value = member.remarks || '';

                // Clear existing services
                $('#editServicesTableBody').empty();

                // Ensure suppliers are loaded before adding rows
                var addRows = () => {
                    if (member.services && member.services.length > 0) {
                        member.services.forEach(service => {
                            addEditServiceRow(service.service_type, service.supplier_id, service.base_price, service.sold_price, service.service_id);
                        });
                    } else {
                        // Add one empty row if no services
                        addEditServiceRow();
                    }
                };

                if (suppliersData.length === 0) {
                    loadSuppliers().then(addRows);
                } else {
                    addRows();
                }

                // Close loading and show modal
                Swal.close();
                $('#editMemberModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?= __("error") ?>',
                    text: data.message || '<?= __("failed_to_load_member_details") ?>'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("failed_to_load_member_details") ?>'
            });
        });
}

function viewMemberDetails(bookingId) {
    // Show loading state
    Swal.fire({
        title: '<?= __("loading") ?>',
        text: '<?= __("please_wait") ?>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch member details
    fetch(`ajax/get_member_details.php?booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.member) {
                const member = data.member;

                // Update modal fields
                document.getElementById('memberName').textContent = member.name;
                document.getElementById('memberGender').textContent = member.gender;
                document.getElementById('memberDob').textContent = member.dob;
                document.getElementById('memberPassport').textContent = member.passport_number;
                document.getElementById('memberPassportExpiry').textContent = member.passport_expiry;
                document.getElementById('memberId').textContent = member.id_type;
                document.getElementById('memberRemarks').textContent = member.remarks || '-';

                document.getElementById('memberEntryDate').textContent = member.entry_date;
                document.getElementById('memberFlightDate').textContent = member.flight_date;
                document.getElementById('memberReturnDate').textContent = member.return_date;
                document.getElementById('memberDuration').textContent = member.duration;
                document.getElementById('memberRoomType').textContent = member.room_type;
                document.getElementById('memberDiscount').textContent = `${member.discount} ${member.currency}`;
                document.getElementById('memberPrice').textContent = `${member.price} ${member.currency}`;
                document.getElementById('memberSoldPrice').textContent = `${member.sold_price} ${member.currency}`;

                document.getElementById('memberProfit').textContent = `${member.profit} ${member.currency}`;
                document.getElementById('memberPaid').textContent = `${member.paid} ${member.currency}`;
                document.getElementById('memberBankPayment').textContent = `${member.received_bank_payment} ${member.currency}`;
                document.getElementById('memberReceiptNumber').textContent = member.bank_receipt_number || '-';
                document.getElementById('memberDue').textContent = `${member.due} ${member.currency}`;

                // Load date change history
                loadDateChangeHistory(bookingId);

                // Close loading and show modal
                Swal.close();
                $('#memberDetailsModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?= __("error") ?>',
                    text: data.message || '<?= __("failed_to_load_member_details") ?>'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: '<?= __("error") ?>',
                text: '<?= __("failed_to_load_member_details") ?>'
            });
        });
}

// Load date change history for a booking
function loadDateChangeHistory(bookingId) {
    $.ajax({
        url: 'ajax/get_booking_date_changes.php',
        type: 'GET',
        data: { booking_id: bookingId },
        success: function(response) {
            if (response.success && response.history && response.history.length > 0) {
                let historyHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
                historyHtml += '<thead><tr><th>Date</th><th>Changes</th><th>Status</th><th>Penalty</th></tr></thead><tbody>';

                response.history.forEach(function(item) {
                    const date = new Date(item.created_at).toLocaleDateString();
                    const changes = [];

                    if (item.old_flight_date !== item.new_flight_date) {
                        changes.push(`Flight: ${item.old_flight_date || 'N/A'} → ${item.new_flight_date}`);
                    }
                    if (item.old_return_date !== item.new_return_date) {
                        changes.push(`Return: ${item.old_return_date || 'N/A'} → ${item.new_return_date}`);
                    }
                    if (item.old_duration !== item.new_duration) {
                        changes.push(`Duration: ${item.old_duration || 'N/A'} → ${item.new_duration}`);
                    }

                    const changesText = changes.length > 0 ? changes.join('<br>') : 'Price change only';
                    const penaltyText = item.total_penalty > 0 ? `$${item.total_penalty}` : '-';

                    let statusBadge = '';
                    switch(item.status) {
                        case 'Pending': statusBadge = '<span class="badge badge-warning">Pending</span>'; break;
                        case 'Approved': statusBadge = '<span class="badge badge-info">Approved</span>'; break;
                        case 'Rejected': statusBadge = '<span class="badge badge-danger">Rejected</span>'; break;
                        case 'Completed': statusBadge = '<span class="badge badge-success">Completed</span>'; break;
                    }

                    historyHtml += `<tr>
                        <td>${date}</td>
                        <td>${changesText}</td>
                        <td>${statusBadge}</td>
                        <td>${penaltyText}</td>
                    </tr>`;
                });

                historyHtml += '</tbody></table></div>';
                $('#dateChangeHistoryContent').html(historyHtml);
                $('#dateChangeHistorySection').show();
            } else {
                $('#dateChangeHistorySection').hide();
            }
        },
        error: function() {
            $('#dateChangeHistorySection').hide();
        }
    });
}
</script>

<!-- Add this before </body> tag -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js"></script>
<!-- Language Selection Modal -->
<div class="modal fade" id="languageModal" tabindex="-1" role="dialog" aria-labelledby="languageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="languageModalLabel"><?= __('select_language') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('please_select_the_language_for_the_document') ?></p>
                <div class="d-flex justify-content-around">
                    <button type="button" class="btn btn-primary" onclick="generateIndividualDocumentWithLanguage('en')">English</button>
                    <button type="button" class="btn btn-info" onclick="generateIndividualDocumentWithLanguage('fa')">Dari (دری)</button>
                    <button type="button" class="btn btn-success" onclick="generateIndividualDocumentWithLanguage('ps')">Pashto (پښتو)</button>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ID Card Generation Modal -->
<div class="modal fade" id="idCardModal" tabindex="-1" role="dialog" aria-labelledby="idCardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="idCardModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('generate_id_cards') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="feather icon-info mr-2"></i><?= __('select_up_to_8_pilgrims_for_id_cards') ?>
                </div>
                
                <div class="selected-pilgrims mb-3">
                    <h6><?= __('selected_pilgrims') ?>: <span id="selectedCount">0</span>/8</h6>
                    <div id="selectedPilgrimsList" class="row">
                        <!-- Selected pilgrims will be displayed here -->
                    </div>
                </div>
                
                <form id="idCardForm" action="generate_id_cards.php" method="post" target="_blank" enctype="multipart/form-data">
                    <input type="hidden" name="selected_pilgrims" id="selectedPilgrimsInput">
                    
                    <div class="form-group">
                        <label for="idCardTitle"><?= __('id_card_title') ?></label>
                        <input type="text" class="form-control" id="idCardTitle" name="id_card_title" 
                               value="<?= htmlspecialchars($settings['agency_name']) ?> - Umrah Pilgrim ID" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="idCardValidityDays"><?= __('id_card_validity_days') ?></label>
                        <input type="number" class="form-control" id="idCardValidityDays" name="id_card_validity_days" 
                               value="45" min="1" max="90" required>
                        <small class="form-text text-muted"><?= __('number_of_days_id_card_is_valid_from_today') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="idCardColor"><?= __('id_card_color') ?></label>
                        <select class="form-control" id="idCardColor" name="id_card_color">
                            <option value="primary"><?= __('blue') ?></option>
                            <option value="success"><?= __('green') ?></option>
                            <option value="danger"><?= __('red') ?></option>
                            <option value="warning"><?= __('yellow') ?></option>
                            <option value="info"><?= __('light_blue') ?></option>
                            <option value="dark"><?= __('black') ?></option>
                        </select>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="feather icon-phone mr-2"></i><?= __('guide_contact_information') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMakkahName"><?= __('guide_makkah_name') ?></label>
                                        <input type="text" class="form-control" id="guideMakkahName" name="guide_makkah_name" placeholder="<?= __('enter_guide_name') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMakkahPhone"><?= __('guide_makkah_phone_number') ?></label>
                                        <input type="text" class="form-control" id="guideMakkahPhone" name="guide_makkah_phone" placeholder="<?= __('enter_guide_phone_number') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="groupName"><?= __('group_name') ?></label>
                                <input type="text" class="form-control" id="groupName" name="group_name" placeholder="<?= __('enter_group_name') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMadinaName"><?= __('guide_madina_name') ?></label>
                                        <input type="text" class="form-control" id="guideMadinaName" name="guide_madina_name" placeholder="<?= __('enter_guide_madina_name') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guideMadinaPhone"><?= __('guide_madina_phone_number') ?></label>
                                        <input type="text" class="form-control" id="guideMadinaPhone" name="guide_madina_phone" placeholder="<?= __('enter_guide_madina_phone_number') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="photo-upload-section mt-4">
                        <h5 class="mb-3"><?= __('pilgrim_photos') ?></h5>
                        <div class="alert alert-info">
                            <i class="feather icon-camera mr-2"></i> <?= __('upload_photos_for_id_cards') ?>
                            <ul class="mb-0 mt-2">
                                <li><?= __('passport_style_photos_recommended') ?></li>
                                <li><?= __('square_photos_work_best') ?></li>
                                <li><?= __('photos_will_be_cropped_to_fit') ?></li>
                            </ul>
                        </div>
                        
                        <div id="photoUploadContainer" class="row">
                            <!-- Photo upload fields will be added here dynamically -->
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="generateIdCardsBtn" disabled>
                    <i class="feather icon-printer mr-2"></i><?= __('generate_id_cards') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating action button for ID card generation -->
<div id="idCardFloatingButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050; display: none;">
    <button type="button" class="btn btn-dark btn-lg shadow" id="showIdCardModal" title="<?= __('generate_id_cards') ?>">
        <i class="feather icon-credit-card"></i>
        <span class="badge badge-light badge-pill position-absolute" style="top: -5px; right: -5px;" id="idCardSelectionCount">0</span>
    </button>
</div>

<!-- Group Ticket Generation Modal -->
<div class="modal fade" id="groupTicketModal" tabindex="-1" role="dialog" aria-labelledby="groupTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="groupTicketModalLabel">
                    <i class="feather icon-airplay mr-2"></i><?= __('generate_group_ticket') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="feather icon-info mr-2"></i><?= __('select_members_and_enter_flight_details') ?>
                </div>

                <!-- Selected Members -->
                <div class="selected-members mb-3">
                    <h6><?= __('selected_members') ?>: <span id="selectedGroupCount">0</span></h6>
                    <div id="selectedGroupMembersList" class="row">
                        <!-- Members will appear here -->
                    </div>
                </div>

                <form id="groupTicketForm" action="generate_group_ticket.php" method="post" target="_blank">
                    <input type="hidden" name="selected_members" id="selectedGroupMembersInput">

                    <!-- Airline & PNR -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="airlineName"><?= __('airline_name') ?></label>
                            <input type="text" class="form-control" id="airlineName" name="airline_name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="pnr"><?= __('pnr_number') ?></label>
                            <input type="text" class="form-control" id="pnr" name="pnr" required>
                        </div>
                    </div>

                    <!-- Flight Type Selection -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label"><?= __('flight_type') ?></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="flight_type" id="directFlight" value="direct" checked>
                                <label class="form-check-label" for="directFlight">
                                    <i class="feather icon-arrow-right mr-1"></i><?= __('direct_flight') ?>
                                </label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="flight_type" id="indirectFlight" value="indirect">
                                <label class="form-check-label" for="indirectFlight">
                                    <i class="feather icon-shuffle mr-1"></i><?= __('connecting_flight') ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary mb-3"><i class="feather icon-calendar mr-2"></i><?= __('outbound_journey') ?></h6>

                    <!-- Direct Flight Fields (Default) -->
                    <div id="directFlightFields">
                        <!-- Flight Routes -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="departureCity"><?= __('departure_city') ?></label>
                                <input type="text" class="form-control" id="departureCity" name="departure_city" value="Kabul" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="arrivalCity"><?= __('arrival_city') ?></label>
                                <input type="text" class="form-control" id="arrivalCity" name="arrival_city" value="Jeddah" required>
                            </div>
                        </div>

                        <!-- Flight Numbers -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="flightNumber1"><?= __('outbound_flight_number') ?></label>
                                <input type="text" class="form-control" id="flightNumber1" name="flight_number_1" value="RQ993" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="flightNumber2"><?= __('return_flight_number') ?></label>
                                <input type="text" class="form-control" id="flightNumber2" name="flight_number_2" value="RQ994" required>
                            </div>
                        </div>
                    </div>

                    <!-- Indirect/Connecting Flight Fields (Hidden by default) -->
                    <div id="indirectFlightFields" style="display: none;">
                        <!-- First Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-right mr-2"></i><?= __('first_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="leg1DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="leg1DepartureCity" name="leg1_departure_city" value="Kabul">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg1ArrivalCity"><?= __('stopover_city') ?></label>
                                        <input type="text" class="form-control" id="leg1ArrivalCity" name="leg1_arrival_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg1FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="leg1FlightNumber" name="leg1_flight_number" value="FZ341">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="leg1DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="leg1DepartureDate" name="leg1_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="leg1DepartureTime" name="leg1_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="leg1ArrivalDate" name="leg1_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg1ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="leg1ArrivalTime" name="leg1_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stopover Duration -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <i class="feather icon-clock mr-2"></i>
                                    <strong><?= __('stopover_duration') ?>:</strong> 
                                    <span id="stopoverDuration"><?= __('calculating') ?>...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Second Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-right mr-2"></i><?= __('second_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="leg2DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="leg2DepartureCity" name="leg2_departure_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg2ArrivalCity"><?= __('final_destination') ?></label>
                                        <input type="text" class="form-control" id="leg2ArrivalCity" name="leg2_arrival_city" value="Jeddah">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="leg2FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="leg2FlightNumber" name="leg2_flight_number" value="FZ415">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="leg2DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="leg2DepartureDate" name="leg2_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="leg2DepartureTime" name="leg2_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="leg2ArrivalDate" name="leg2_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="leg2ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="leg2ArrivalTime" name="leg2_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return Journey -->
                        <hr>
                        <h6 class="text-success mb-3"><i class="feather icon-corner-up-left mr-2"></i><?= __('return_journey') ?></h6>
                        
                        <!-- Return First Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-left mr-2"></i><?= __('return_first_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1DepartureCity" name="return_leg1_departure_city" value="Jeddah">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1ArrivalCity"><?= __('stopover_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1ArrivalCity" name="return_leg1_arrival_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg1FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="returnLeg1FlightNumber" name="return_leg1_flight_number" value="FZ416">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg1DepartureDate" name="return_leg1_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg1DepartureTime" name="return_leg1_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg1ArrivalDate" name="return_leg1_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg1ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg1ArrivalTime" name="return_leg1_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return Second Leg -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="feather icon-arrow-left mr-2"></i><?= __('return_second_leg') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2DepartureCity"><?= __('departure_city') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2DepartureCity" name="return_leg2_departure_city" value="Dubai">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2ArrivalCity"><?= __('final_destination') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2ArrivalCity" name="return_leg2_arrival_city" value="Kabul">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="returnLeg2FlightNumber"><?= __('flight_number') ?></label>
                                        <input type="text" class="form-control" id="returnLeg2FlightNumber" name="return_leg2_flight_number" value="FZ342">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2DepartureDate"><?= __('departure_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg2DepartureDate" name="return_leg2_departure_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2DepartureTime"><?= __('departure_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg2DepartureTime" name="return_leg2_departure_time">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2ArrivalDate"><?= __('arrival_date') ?></label>
                                        <input type="date" class="form-control" id="returnLeg2ArrivalDate" name="return_leg2_arrival_date">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="returnLeg2ArrivalTime"><?= __('arrival_time') ?></label>
                                        <input type="time" class="form-control" id="returnLeg2ArrivalTime" name="return_leg2_arrival_time">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Direct Flight Dates (Hidden when indirect is selected) -->
                    <div id="directFlightDates">
                        <!-- Departure -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="departureDate"><?= __('departure_date') ?></label>
                                <input type="date" class="form-control" id="departureDate" name="departure_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="departureTime"><?= __('departure_time') ?></label>
                                <input type="time" class="form-control" id="departureTime" name="departure_time" required>
                            </div>
                        </div>

                        <!-- Arrival -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="arrivalDate"><?= __('arrival_date') ?></label>
                                <input type="date" class="form-control" id="arrivalDate" name="arrival_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="arrivalTime"><?= __('arrival_time') ?></label>
                                <input type="time" class="form-control" id="arrivalTime" name="arrival_time" required>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-primary mb-3"><i class="feather icon-corner-up-left mr-2"></i><?= __('return_flight_details') ?></h6>

                        <!-- Return Departure -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="returnDate"><?= __('return_departure_date') ?></label>
                                <input type="date" class="form-control" id="returnDate" name="return_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="returnTime"><?= __('return_departure_time') ?></label>
                                <input type="time" class="form-control" id="returnTime" name="return_time" required>
                            </div>
                        </div>

                        <!-- Return Arrival -->
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="retArrivalDate"><?= __('return_arrival_date') ?></label>
                                <input type="date" class="form-control" id="retArrivalDate" name="ret_arrival_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="retArrivalTime"><?= __('return_arrival_time') ?></label>
                                <input type="time" class="form-control" id="retArrivalTime" name="return_arrival_time" required>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label for="remarks"><?= __('remarks') ?></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="<?= __('additional_notes_or_special_instructions') ?>"></textarea>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="generateGroupTicketBtn" disabled>
                    <i class="feather icon-printer mr-2"></i><?= __('generate_group_ticket') ?>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Umrah Service Completion Modal -->
<div class="modal fade" id="completionDetailsModal" tabindex="-1" role="dialog" aria-labelledby="completionDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completionDetailsModalLabel">
                    <i class="feather icon-check-circle mr-2"></i><?= __('umrah_completion_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="completionDetailsForm">
                    <input type="hidden" id="completionBookingId" name="booking_id">
                    
                    <div class="alert alert-info">
                        <?= __('please_specify_the_documents_and_items_being_returned_to_the_pilgrim') ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= __('document_item') ?></th>
                                    <th class="text-center"><?= __('returned') ?></th>
                                </tr>
                            </thead>
                            <tbody id="completionDetailsTableBody">
                                <!-- Document rows will be generated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group mt-3">
                        <label for="completionAdditionalNotes"><?= __('additional_notes') ?></label>
                        <textarea class="form-control" id="completionAdditionalNotes" name="additional_notes" rows="3" 
                                 placeholder="<?= __('enter_any_additional_notes_about_the_completion') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="completionConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="completionConfirmation">
                                <?= __('i_confirm_all_documents_and_items_have_been_properly_returned') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-success" id="generateCompletionFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_completion_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancellation Details Modal -->
<div class="modal fade" id="cancellationDetailsModal" tabindex="-1" role="dialog" aria-labelledby="cancellationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancellationDetailsModalLabel">
                    <i class="feather icon-x-circle mr-2"></i><?= __('umrah_cancellation_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="cancellationDetailsForm">
                    <input type="hidden" id="cancellationBookingId" name="booking_id">
                    
                    <div class="alert alert-warning">
                        <?= __('please_specify_the_cancellation_details_and_fees') ?>
                    </div>

                    <div class="section-header"><?= __('document_return') ?></div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th width="30%"><?= __('document_type') ?></th>
                                    <th width="20%"><?= __('returned') ?></th>
                                    <th width="20%"><?= __('condition') ?></th>
                                    <th width="30%"><?= __('notes') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= __('passport') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_passport" name="returned_items[passport]" value="1">
                                            <label class="custom-control-label" for="return_passport"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[passport]" 
                                                id="condition_passport">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[passport]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('id_card') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_id_card" name="returned_items[id_card]" value="1">
                                            <label class="custom-control-label" for="return_id_card"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[id_card]" 
                                                id="condition_id_card">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[id_card]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('photos') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_photos" name="returned_items[photos]" value="1">
                                            <label class="custom-control-label" for="return_photos"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[photos]" 
                                                id="condition_photos">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[photos]">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?= __('other_documents') ?></td>
                                    <td class="text-center">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="return_other_docs" name="returned_items[other_docs]" value="1">
                                            <label class="custom-control-label" for="return_other_docs"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm" name="item_condition[other_docs]" 
                                                id="condition_other_docs">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="good"><?= __('good') ?></option>
                                            <option value="fair"><?= __('fair') ?></option>
                                            <option value="poor"><?= __('poor') ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="item_notes[other_docs]" placeholder="<?= __('specify_documents') ?>">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group">
                        <label for="cancellationReason"><?= __('reason_for_cancellation') ?></label>
                        <textarea class="form-control" id="cancellationReason" name="cancellation_reason" 
                                  rows="3" required></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="cancellationConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="cancellationConfirmation">
                                <?= __('i_confirm_all_cancellation_details_are_correct') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-danger" id="generateCancellationFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_cancellation_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Family Language Selection Modal -->
<div class="modal fade" id="familyLanguageModal" tabindex="-1" role="dialog" aria-labelledby="familyLanguageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyLanguageModalLabel"><?= __('select_language') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('please_select_the_language_for_the_document') ?></p>
                <div class="d-flex justify-content-around">
                    <button type="button" class="btn btn-primary" onclick="generateDocumentWithLanguage('en')">English</button>
                    <button type="button" class="btn btn-info" onclick="generateDocumentWithLanguage('fa')">Dari (دری)</button>
                    <button type="button" class="btn btn-success" onclick="generateDocumentWithLanguage('ps')">Pashto (پښتو)</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Family Service Completion Modal -->
<div class="modal fade" id="familyCompletionDetailsModal" tabindex="-1" role="dialog" aria-labelledby="familyCompletionDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="familyCompletionDetailsModalLabel">
                    <i class="feather icon-check-circle mr-2"></i><?= __('family_completion_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="familyCompletionDetailsForm">
                    <input type="hidden" id="familyCompletionBookingId" name="booking_id">
                    
                    <div class="alert alert-info">
                        <?= __('please_specify_the_documents_and_items_being_returned_to_the_family') ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= __('document_item') ?></th>
                                    <th class="text-center"><?= __('returned') ?></th>
                                </tr>
                            </thead>
                            <tbody id="familyCompletionDetailsTableBody">
                                <!-- Document rows will be generated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group mt-3">
                        <label for="familyCompletionAdditionalNotes"><?= __('additional_notes') ?></label>
                        <textarea class="form-control" id="familyCompletionAdditionalNotes" name="additional_notes" rows="3" 
                                 placeholder="<?= __('enter_any_additional_notes_about_the_completion') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="familyCompletionConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="familyCompletionConfirmation">
                                <?= __('i_confirm_all_documents_and_items_have_been_properly_returned') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-success" id="familyGenerateCompletionFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_completion_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Family Cancellation Details Modal -->
<div class="modal fade" id="familyCancellationDetailsModal" tabindex="-1" role="dialog" aria-labelledby="familyCancellationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="familyCancellationDetailsModalLabel">
                    <i class="feather icon-x-circle mr-2"></i><?= __('family_cancellation_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="familyCancellationDetailsForm">
                    <input type="hidden" id="familyCancellationFamilyId" name="family_id">
                    <input type="hidden" id="familyCancellationBookingId" name="booking_id">
                    
                    <div class="alert alert-warning">
                        <i class="feather icon-alert-triangle mr-2"></i>
                        <?= __('please_specify_the_cancellation_details_for_all_family_members') ?>
                    </div>

                    <!-- Family Summary -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="feather icon-users mr-2"></i><?= __('family_information') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong><?= __('family_name') ?>:</strong> 
                                    <span id="familyNameDisplay"></span>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= __('total_members') ?>:</strong> 
                                    <span id="totalMembersDisplay"></span>
                                </div>
                                <div class="col-md-4">
                                    <strong><?= __('package_type') ?>:</strong> 
                                    <span id="packageTypeDisplay"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Return Section for Each Family Member -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="feather icon-file-text mr-2"></i><?= __('document_return_by_member') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="familyMembersDocuments">
                                <!-- Family member document sections will be populated here -->
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="familyCancellationReason"><?= __('reason_for_cancellation') ?> *</label>
                        <textarea class="form-control" id="familyCancellationReason" name="cancellation_reason" 
                                  rows="4" required placeholder="<?= __('please_provide_detailed_reason_for_family_cancellation') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="familyCancellationConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="familyCancellationConfirmation">
                                <strong><?= __('i_confirm_all_family_cancellation_details_are_correct') ?></strong>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-danger" id="familyGenerateCancellationFormBtn">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_family_cancellation_form') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden template for family member document section -->
<div id="memberDocumentTemplate" style="display: none;">
    <div class="member-document-section mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="feather icon-user mr-2"></i>
                    <span class="member-name"></span> 
                    <small class="ml-2">(<span class="member-passport"></span> - ID: <span class="member-booking-id"></span>)</small>
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th width="30%"><?= __('document_type') ?></th>
                                <th width="15%"><?= __('returned') ?></th>
                                <th width="20%"><?= __('condition') ?></th>
                                <th width="35%"><?= __('notes') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?= __('passport') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="passport" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="passport">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="passport" 
                                           placeholder="<?= __('passport_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('id_card') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="id_card" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="id_card">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="id_card" 
                                           placeholder="<?= __('id_card_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('photos') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="photos" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="photos">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="photos" 
                                           placeholder="<?= __('photos_notes') ?>">
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?= __('other_documents') ?></strong></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input member-return-checkbox" 
                                               data-member-id="" data-doc-type="other_docs" value="1">
                                        <label class="custom-control-label"></label>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-control form-control-sm member-condition-select" 
                                            data-member-id="" data-doc-type="other_docs">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="good"><?= __('good') ?></option>
                                        <option value="fair"><?= __('fair') ?></option>
                                        <option value="damaged"><?= __('damaged') ?></option>
                                        <option value="missing"><?= __('missing') ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm member-notes-input" 
                                           data-member-id="" data-doc-type="other_docs" 
                                           placeholder="<?= __('specify_other_documents') ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .member-document-section {
        border-left: 4px solid #007bff;
        margin-bottom: 1rem;
    }

    .member-document-section .card-header {
        font-size: 0.95rem;
    }

    .table-sm td, .table-sm th {
        padding: 0.5rem;
    }

    .custom-control-input:checked ~ .custom-control-label::before {
        background-color: #28a745;
        border-color: #28a745;
    }

    .alert-warning {
        border-left: 4px solid #ffc107;
    }
</style>

<!-- Member Details Modal -->
<div class="modal fade" id="memberDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i><?= __('member_details') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6">
                        <div class="card border-primary mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="feather icon-user mr-2"></i><?= __('personal_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted"><?= __('name') ?>:</td>
                                        <td class="font-weight-bold" id="memberName"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('gender') ?>:</td>
                                        <td id="memberGender"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('date_of_birth') ?>:</td>
                                        <td id="memberDob"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('passport_number') ?>:</td>
                                        <td id="memberPassport"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('passport_expiry') ?>:</td>
                                        <td id="memberPassportExpiry"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('id_type') ?>:</td>
                                        <td id="memberId"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('remarks') ?>:</td>
                                        <td id="memberRemarks"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Travel Information -->
                    <div class="col-md-6">
                        <div class="card border-info mb-3">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="feather icon-map mr-2"></i><?= __('travel_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td class="text-muted"><?= __('entry_date') ?>:</td>
                                        <td id="memberEntryDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('flight_date') ?>:</td>
                                        <td id="memberFlightDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('return_date') ?>:</td>
                                        <td id="memberReturnDate"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('duration') ?>:</td>
                                        <td id="memberDuration"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted"><?= __('room_type') ?>:</td>
                                        <td id="memberRoomType"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="col-md-12">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="feather icon-dollar-sign mr-2"></i><?= __('financial_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td class="text-muted"><?= __('base') ?>:</td>
                                                <td class="font-weight-bold" id="memberPrice"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('sold_price') ?>:</td>
                                                <td class="font-weight-bold" id="memberSoldPrice"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('discount') ?>:</td>
                                                <td class="font-weight-bold" id="memberDiscount"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('profit') ?>:</td>
                                                <td class="text-success font-weight-bold" id="memberProfit"></td>
                                            </tr>
                                           
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            <tr>
                                                <td class="text-muted"><?= __('paid') ?>:</td>
                                                <td class="text-success" id="memberPaid"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('bank_payment') ?>:</td>
                                                <td id="memberBankPayment"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('receipt_number') ?>:</td>
                                                <td id="memberReceiptNumber"></td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted"><?= __('due') ?>:</td>
                                                <td class="text-danger" id="memberDue"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Change History -->
                    <div class="col-md-12" id="dateChangeHistorySection" style="display: none;">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="feather icon-calendar mr-2"></i><?= __('date_change_history') ?></h6>
                            </div>
                            <div class="card-body">
                                <div id="dateChangeHistoryContent">
                                    <!-- History will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Date Change Request Modal -->
<div class="modal fade" id="dateChangeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="feather icon-calendar mr-2"></i><?= __('request_date_change') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="dateChangeForm">
                    <input type="hidden" id="dateChangeBookingId" name="booking_id">

                    <!-- Current Details -->
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-info mr-2"></i><?= __('current_booking_details') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><?= __('passenger_name') ?>:</strong> <span id="currentPassengerName"></span></p>
                                    <p><strong><?= __('current_flight_date') ?>:</strong> <span id="currentFlightDate"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><?= __('current_return_date') ?>:</strong> <span id="currentReturnDate"></span></p>
                                    <p><strong><?= __('current_duration') ?>:</strong> <span id="currentDuration"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New Date Details -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="feather icon-edit mr-2"></i><?= __('new_date_details') ?></h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="newFlightDate"><?= __('new_flight_date') ?> *</label>
                                    <input type="date" class="form-control" id="newFlightDate" name="new_flight_date" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="newReturnDate"><?= __('new_return_date') ?> *</label>
                                    <input type="date" class="form-control" id="newReturnDate" name="new_return_date" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="newDuration"><?= __('new_duration') ?> *</label>
                                    <select class="form-control" id="newDuration" name="new_duration" required>
                                        <option value="5 Days">5 Days</option>
                                        <option value="6 Days">6 Days</option>
                                        <option value="7 Days">7 Days</option>
                                        <option value="8 Days">8 Days</option>
                                        <option value="9 Days">9 Days</option>
                                        <option value="10 Days">10 Days</option>
                                        <option value="11 Days">11 Days</option>
                                        <option value="12 Days">12 Days</option>
                                        <option value="13 Days">13 Days</option>
                                        <option value="14 Days">14 Days</option>
                                        <option value="15 Days">15 Days</option>
                                        <option value="16 Days">16 Days</option>
                                        <option value="17 Days">17 Days</option>
                                        <option value="18 Days">18 Days</option>
                                        <option value="19 Days">19 Days</option>
                                        <option value="20 Days">20 Days</option>
                                        <option value="21 Days">21 Days</option>
                                        <option value="22 Days">22 Days</option>
                                        <option value="23 Days">23 Days</option>
                                        <option value="24 Days">24 Days</option>
                                        <option value="25 Days">25 Days</option>
                                        <option value="26 Days">26 Days</option>
                                        <option value="27 Days">27 Days</option>
                                        <option value="28 Days">28 Days</option>
                                        <option value="29 Days">29 Days</option>
                                        <option value="30 Days">30 Days</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="newPrice"><?= __('new_sold_price') ?> (<?= __('optional') ?>)</label>
                                    <input type="number" class="form-control" id="newPrice" name="new_price" step="0.01" min="0">
                                    <small class="form-text text-muted"><?= __('leave_empty_if_no_price_change') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-group">
                        <label for="changeReason"><?= __('reason_for_change') ?> *</label>
                        <textarea class="form-control" id="changeReason" name="change_reason" rows="3" required
                                  placeholder="<?= __('please_provide_detailed_reason_for_date_change') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="additionalRemarks"><?= __('additional_remarks') ?></label>
                        <textarea class="form-control" id="additionalRemarks" name="additional_remarks" rows="2"
                                  placeholder="<?= __('any_additional_notes_or_special_requests') ?>"></textarea>
                    </div>

                    <!-- Confirmation -->
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="dateChangeConfirmation" name="confirmation" required>
                            <label class="custom-control-label" for="dateChangeConfirmation">
                                <strong><?= __('i_confirm_date_change_request_details_are_correct') ?></strong>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-info" id="submitDateChangeRequest">
                    <i class="feather icon-send mr-2"></i><?= __('submit_request') ?>
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Bank Recipt Language Selection Modal -->
    <div class="modal fade" id="bankReciptModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_bank_recipt_language") ?></h5>
                </div>
                <div class="modal-body">
                    <form id="bankReciptForm" onsubmit="generateBankRecipt(event)">
                    <div class="form-group">
                    <label for="bank_name"><?= __("bank_name") ?></label>
                    <input type="text" class="form-control" id="bank_name" placeholder="<?= __("bank_name") ?>">
                    </div>
                    <div class="form-group">
                    <label for="bank_account_number"><?= __("bank_account_number") ?></label>
                    <input type="text" class="form-control" id="bank_account_number" placeholder="<?= __("bank_account_number") ?>">
                    </div>
                    <div class="form-group">
                    <label for="account_name"><?= __("account_name") ?></label>
                    <input type="text" class="form-control" id="account_name" placeholder="<?= __("account_name") ?>">
                    </div>
                    <div class="form-group">
                    <label for="payment"><?= __("payment") ?></label>
                    <input type="text" class="form-control" id="payment" placeholder="<?= __("payment") ?>">
                    </div>
                    </form>
                    <div class="mt-3">
                        <label class="font-weight-bold mb-2"><?= __("select_members_to_include") ?></label>
                        <div id="bankReciptMembers" class="border rounded p-2" style="max-height: 220px; overflow:auto;">
                            <div class="text-muted small"><?= __("members_will_load_here") ?></div>
                        </div>
                    </div>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateBankRecipt(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateBankRecipt(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Umrah Letter Language Selection Modal -->
<div class="modal fade" id="umrahPresidencyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __("umrah_presedency_lettter") ?></h5>
            </div>
            <div class="modal-body">
                <form id="umrahPresidencyForm" onsubmit="generateUmrah(event)">
                    
                    <!-- Family Head Info -->
                    <h6 class="mb-3 mt-2 text-primary"><?= __("family_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="family_head_father_name"><?= __("family_head_father_name") ?></label>
                            <input type="text" class="form-control" id="family_head_father_name" placeholder="<?= __("family_head_father_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="family_head_id_number"><?= __("family_head_id_number") ?></label>
                            <input type="text" class="form-control" id="family_head_id_number" placeholder="<?= __("family_head_id_number") ?>">
                        </div>
                    </div>

                    <!-- Visa & Ticket -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("visa_ticket_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="umrah_visa_amount"><?= __("umrah_visa_amount") ?></label>
                            <input type="text" class="form-control" id="umrah_visa_amount" placeholder="<?= __("umrah_visa_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="ticket_amount"><?= __("ticket_amount") ?></label>
                            <input type="text" class="form-control" id="ticket_amount" placeholder="<?= __("ticket_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="airline_name"><?= __("airline_name") ?></label>
                            <input type="text" class="form-control" id="airline_name" placeholder="<?= __("airline_name") ?>">
                        </div>
                    </div>

                    <!-- Duration -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("stay_duration") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="makkah_day_number"><?= __("makkah_day_number") ?></label>
                            <input type="text" class="form-control" id="makkah_day_number" placeholder="<?= __("makkah_day_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_night_number"><?= __("makkah_night_number") ?></label>
                            <input type="text" class="form-control" id="makkah_night_number" placeholder="<?= __("makkah_night_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_day_number"><?= __("madina_day_number") ?></label>
                            <input type="text" class="form-control" id="madina_day_number" placeholder="<?= __("madina_day_number") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_night_number"><?= __("madina_night_number") ?></label>
                            <input type="text" class="form-control" id="madina_night_number" placeholder="<?= __("madina_night_number") ?>">
                        </div>
                    </div>

                    <!-- Transport -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("transport_services") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="amount_airport_hotel"><?= __("amount_airport_hotel") ?></label>
                            <input type="text" class="form-control" id="amount_airport_hotel" placeholder="<?= __("amount_airport_hotel") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="amount_hotel_airport"><?= __("amount_hotel_airport") ?></label>
                            <input type="text" class="form-control" id="amount_hotel_airport" placeholder="<?= __("amount_hotel_airport") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="visiting_ziarats_amount"><?= __("visiting_ziarats_amount") ?></label>
                            <input type="text" class="form-control" id="visiting_ziarats_amount" placeholder="<?= __("visiting_ziarats_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="halaqat_darsi_amount"><?= __("halaqat_darsi_amount") ?></label>
                            <input type="text" class="form-control" id="halaqat_darsi_amount" placeholder="<?= __("halaqat_darsi_amount") ?>">
                        </div>
                    </div>

                    <!-- Hotels -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("hotel_information") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_name"><?= __("makkah_hotel_name") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_name" placeholder="<?= __("makkah_hotel_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_degree"><?= __("makkah_hotel_degree") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_degree" placeholder="<?= __("makkah_hotel_degree") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_distance"><?= __("makkah_hotel_distance") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_distance" placeholder="<?= __("makkah_hotel_distance") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="makkah_hotel_amount"><?= __("makkah_hotel_amount") ?></label>
                            <input type="text" class="form-control" id="makkah_hotel_amount" placeholder="<?= __("makkah_hotel_amount") ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_name"><?= __("madina_hotel_name") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_name" placeholder="<?= __("madina_hotel_name") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_degree"><?= __("madina_hotel_degree") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_degree" placeholder="<?= __("madina_hotel_degree") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_distance"><?= __("madina_hotel_distance") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_distance" placeholder="<?= __("madina_hotel_distance") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="madina_hotel_amount"><?= __("madina_hotel_amount") ?></label>
                            <input type="text" class="form-control" id="madina_hotel_amount" placeholder="<?= __("madina_hotel_amount") ?>">
                        </div>
                    </div>

                    <!-- Financial -->
                    <h6 class="mb-3 mt-4 text-primary"><?= __("financials") ?></h6>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="commission_amount"><?= __("commission_amount") ?></label>
                            <input type="text" class="form-control" id="commission_amount" placeholder="<?= __("commission_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="child_services_amount"><?= __("child_services_amount") ?></label>
                            <input type="text" class="form-control" id="child_services_amount" placeholder="<?= __("child_services_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="child_commission_amount"><?= __("child_commission_amount") ?></label>
                            <input type="text" class="form-control" id="child_commission_amount" placeholder="<?= __("child_commission_amount") ?>">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="total_amount"><?= __("total_amount") ?></label>
                            <input type="text" class="form-control" id="total_amount" placeholder="<?= __("total_amount") ?>">
                        </div>
                    </div>
                </form>

                <!-- Language Selection -->
                <div class="list-group mt-3">
                    <a href="#" class="list-group-item list-group-item-action" onclick="generateUmrah(event, 'fa')">
                        <i class="feather icon-globe mr-2"></i> Dari
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="generateUmrah(event, 'ps')">
                        <i class="feather icon-globe mr-2"></i> Pashto
                    </a>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <?= __("cancel") ?>
                </button>
            </div>
        </div>
    </div>
</div>





<script>
    // Family Cancellation Modal Functions
    var familyMembersData = [];

    // Function to open family cancellation modal
    function openFamilyCancellationModal(familyId, bookingId = null) {
        console.log('Opening family cancellation modal with familyId:', familyId, 'bookingId:', bookingId);
        
        // Validate familyId
        if (!familyId || familyId === '' || familyId === 'undefined') {
            console.error('Invalid family ID provided:', familyId);
            Swal.fire({
                icon: 'error',
                title: 'Invalid Family ID',
                text: 'Please provide a valid family ID.'
            });
            return;
        }
        
        // Set the family ID and booking ID
        $('#familyCancellationFamilyId').val(familyId);
        $('#familyCancellationBookingId').val(bookingId || '');
        
        // Clear previous data
        $('#familyMembersDocuments').html('');
        $('#familyNameDisplay').text('');
        $('#totalMembersDisplay').text('');
        $('#packageTypeDisplay').text('');
        
        // Show the modal first
        $('#familyCancellationDetailsModal').modal('show');
        
        // Load family members data after modal is shown
        setTimeout(function() {
            loadFamilyMembersForCancellation(familyId);
        }, 300);
    }

    // Function to load family members data
    function loadFamilyMembersForCancellation(familyId) {
        console.group('Load Family Members Debug');
        console.log('Function called with familyId:', familyId);
        console.log('Current page URL:', window.location.href);
        console.log('jQuery version:', $.fn.jquery);
        console.log('Modal exists:', $('#familyCancellationDetailsModal').length > 0);
        console.log('Family ID input exists:', $('#familyCancellationFamilyId').length > 0);
        
        // Validate inputs
        if (!familyId) {
            console.error('Invalid familyId provided');
            $('#familyMembersDocuments').html('<div class="alert alert-danger">Invalid Family ID</div>');
            console.groupEnd();
            return;
        }
        
        // Show loading state
        $('#familyMembersDocuments').html('<div class="text-center p-4"><i class="feather icon-loader spinning"></i> Loading family members...</div>');
        
        // Determine the correct AJAX URL with more logging
        var possiblePaths = [
            'ajax/get_family_members1.php',
            'admin/ajax/get_family_members1.php',
            '../ajax/get_family_members1.php',
            'get_family_members1.php'
        ];
        
        // Try to find the correct path dynamically
        function findValidPath(paths) {
            for (var i = 0; i < paths.length; i++) {
                var testPath = paths[i];
                console.log('Attempting path:', testPath);
                
                try {
                    var xhr = $.ajax({
                        url: testPath,
                        type: 'HEAD',
                        async: false
                    });
                    
                    if (xhr.status === 200) {
                        console.log('Valid path found:', testPath);
                        return testPath;
                    }
                } catch (e) {
                    console.warn('Path test failed:', testPath, e);
                }
            }
            console.warn('No valid path found, defaulting to first path');
            return paths[0]; // Default to first path if no valid path found
        }
        
        var ajaxUrl = findValidPath(possiblePaths);
        
        console.log('Final AJAX URL:', ajaxUrl);
        
        // Perform AJAX request with extensive logging
        $.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: { 
                family_id: familyId,
                action: 'get_family_members' 
            },
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            beforeSend: function(xhr) {
                console.log('AJAX request started for family ID:', familyId);
                console.log('Request headers:', xhr.getAllResponseHeaders());
            },
            success: function(response, textStatus, xhr) {
                console.log('AJAX Success Response:', response);
                console.log('Response Status:', textStatus);
                console.log('XHR Object:', xhr);
                
                if (response && response.success && response.data) {
                    // Store family members data globally
                    window.familyMembersData = response.data.members || [];
                    
                    console.log('Family members loaded:', window.familyMembersData.length);
                    
                    // Update family information display
                    $('#familyNameDisplay').text(response.data.family_name || 'N/A');
                    $('#totalMembersDisplay').text(window.familyMembersData.length);
                    $('#packageTypeDisplay').text(response.data.package_type || 'N/A');
                    
                    // Set the booking ID to the first member's booking ID
                    if (window.familyMembersData.length > 0) {
                        var firstMemberBookingId = window.familyMembersData[0].booking_id;
                        $('#familyCancellationBookingId').val(firstMemberBookingId);
                        console.log('Set booking ID to first member:', firstMemberBookingId);
                    }
                    
                    // Generate member document sections
                    generateFamilyMemberDocumentSections();
                } else {
                    console.error('Invalid response structure:', response);
                    var errorMsg = 'Error loading family members: ' + (response.message || 'Invalid response structure');
                    $('#familyMembersDocuments').html('<div class="alert alert-danger">' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                console.error('Request URL:', ajaxUrl);
                console.error('Request Parameters:', { 
                    family_id: familyId, 
                    action: 'get_family_members' 
                });
                
                var errorMessage = 'Failed to load family members.';
                
                if (xhr.status === 404) {
                    errorMessage = 'AJAX endpoint not found. Please check the file path: ' + ajaxUrl;
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please check your permissions.';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.responseText) {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        errorMessage = 'Server returned: ' + xhr.responseText.substring(0, 100) + '...';
                    }
                }
                
                $('#familyMembersDocuments').html(
                    '<div class="alert alert-danger">' + 
                    '<strong>Error:</strong> ' + errorMessage + 
                    '<br><small>Status Code: ' + xhr.status + ' | Status: ' + status + 
                    '<br>URL: ' + ajaxUrl + '</small>' +
                    '</div>'
                );
                
                // Optional: Show a more user-friendly error toast
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Loading Error',
                        text: errorMessage,
                        confirmButtonColor: '#dc3545'
                    });
                } else {
                    alert(errorMessage);
                }
            },
            complete: function(xhr, status) {
                console.log('AJAX request completed with status:', status);
                console.groupEnd();
            }
        });
    }

    // Function to generate document sections for each family member
    function generateFamilyMemberDocumentSections() {
        console.log('Generating document sections for', familyMembersData.length, 'members');
        
        var sectionsHtml = '';
        
        if (familyMembersData.length === 0) {
            $('#familyMembersDocuments').html('<div class="alert alert-warning">No family members found.</div>');
            return;
        }
        
        familyMembersData.forEach(function(member, index) {
            console.log('Processing member:', member.name, 'ID:', member.booking_id);
            
            var template = $('#memberDocumentTemplate').html();
            if (!template) {
                console.error('Member document template not found!');
                $('#familyMembersDocuments').html('<div class="alert alert-danger">Template not found. Please refresh the page.</div>');
                return;
            }
            
            var memberSection = $(template);
            
            // Update member information
            memberSection.find('.member-name').text(member.name || 'Unknown');
            memberSection.find('.member-passport').text(member.passport_number || 'N/A');
            memberSection.find('.member-booking-id').text(member.booking_id || 'N/A');
            
            // Update input IDs and names for this member
            var memberId = member.booking_id || index;
            
            // Ensure unique IDs for each input
            memberSection.find('.member-return-checkbox').each(function() {
                var docType = $(this).data('doc-type');
                var uniqueId = 'member_' + memberId + '_return_' + docType;
                
                // Set unique ID and data attributes
                $(this)
                    .attr('id', uniqueId)
                    .attr('name', uniqueId)
                    .attr('data-member-id', memberId)
                    .attr('data-doc-type', docType);
                
                // Update corresponding label
                $(this).next('label').attr('for', uniqueId);
            });
            
            memberSection.find('.member-condition-select').each(function() {
                var docType = $(this).data('doc-type');
                var uniqueId = 'member_' + memberId + '_condition_' + docType;
                
                // Set unique ID and data attributes
                $(this)
                    .attr('id', uniqueId)
                    .attr('name', uniqueId)
                    .attr('data-member-id', memberId)
                    .attr('data-doc-type', docType);
            });
            
            memberSection.find('.member-notes-input').each(function() {
                var docType = $(this).data('doc-type');
                var uniqueId = 'member_' + memberId + '_notes_' + docType;
                
                // Set unique ID and data attributes
                $(this)
                    .attr('id', uniqueId)
                    .attr('name', uniqueId)
                    .attr('data-member-id', memberId)
                    .attr('data-doc-type', docType);
            });
            
            sectionsHtml += memberSection.prop('outerHTML');
        });
        
        $('#familyMembersDocuments').html(sectionsHtml);
        console.log('Document sections generated successfully');
    }

    // Main cancellation form generation handler
    $(document).on('click', '#familyGenerateCancellationFormBtn', function() {
        console.log('Generate cancellation form button clicked');
        
        // Validate form
        var form = $('#familyCancellationDetailsForm');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        // Check if at least one document is marked as returned for at least one member
        var hasReturnedDocuments = false;
        $('.member-return-checkbox:checked').each(function() {
            hasReturnedDocuments = true;
            return false; // Break the loop
        });
        
        if (!hasReturnedDocuments) {
            Swal.fire({
                icon: 'warning',
                title: 'No Documents Returned',
                text: 'Please mark at least one document as returned for at least one family member.',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        
        // Validate cancellation reason
        var cancellationReason = $('#familyCancellationReason').val().trim();
        if (!cancellationReason) {
            Swal.fire({
                icon: 'warning',
                title: 'Cancellation Reason Required',
                text: 'Please provide a detailed reason for the family cancellation.',
                confirmButtonColor: '#dc3545'
            });
            $('#familyCancellationReason').focus();
            return;
        }
        
        // Show confirmation dialog
        Swal.fire({
            title: 'Generate Family Cancellation Form',
            text: 'This will generate a cancellation form for all ' + familyMembersData.length + ' family members. Continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Generate Form',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                generateFamilyCancellationForm();
            }
        });
    });

    // Function to generate the actual cancellation form
    function generateFamilyCancellationForm() {
        console.group('Family Cancellation Form Generation');
        console.log('Starting form generation process');
        
        try {
            // Show loading state
            $('#familyGenerateCancellationFormBtn')
                .html('<i class="feather icon-loader spinning"></i> Generating...')
                .prop('disabled', true);
            
            // Collect all form data
            var familyId = $('#familyCancellationFamilyId').val();
            var bookingId = $('#familyCancellationBookingId').val();
            var cancellationReason = $('#familyCancellationReason').val().trim();
            
            console.log('Form data:', { familyId, bookingId, cancellationReason });
            
            // Validate inputs
            if (!familyId) {
                throw new Error('Family ID is required');
            }
            
            if (!bookingId) {
                throw new Error('Booking ID is required');
            }
            
            if (!cancellationReason) {
                throw new Error('Cancellation reason is required');
            }
            
            // Use window.familyMembersData instead of local variable
            var familyMembersData = window.familyMembersData || [];
            
            console.log('Family Members Data:', familyMembersData);
            
            // Collect returned items and their conditions for each member
            var returnedItems = {};
            var itemConditions = {};
            var itemNotes = {};
            
            console.group('Returned Items Collection');
            
            familyMembersData.forEach(function(member) {
                var memberId = member.booking_id;
                var memberPrefix = 'member_' + memberId + '_';
                
                console.log('Processing member:', member);
                console.log('Member Prefix:', memberPrefix);
                
                // Document types
                var docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
                
                docTypes.forEach(function(docType) {
                    var returnCheckbox = $('#member_' + memberId + '_return_' + docType);
                    var conditionSelect = $('#member_' + memberId + '_condition_' + docType);
                    var notesInput = $('#member_' + memberId + '_notes_' + docType);
                    
                    console.log('Checking document type:', docType);
                    console.log('Return Checkbox:', returnCheckbox.length, returnCheckbox.is(':checked'));
                    console.log('Condition Select:', conditionSelect.length, conditionSelect.val());
                    console.log('Notes Input:', notesInput.length, notesInput.val());
                    
                    if (returnCheckbox.length) {
                        returnedItems[memberPrefix + docType] = returnCheckbox.is(':checked') ? '1' : '0';
                    }
                    if (conditionSelect.length) {
                        itemConditions[memberPrefix + docType] = conditionSelect.val() || '';
                    }
                    if (notesInput.length) {
                        itemNotes[memberPrefix + docType] = notesInput.val() || '';
                    }
                });
            });
            
            console.log('Collected Returned Items:', returnedItems);
            console.log('Collected Item Conditions:', itemConditions);
            console.log('Collected Item Notes:', itemNotes);
            console.groupEnd();
            
            // Determine current language, default to 'en' if not set
            var currentLang = typeof currentLang !== 'undefined' ? currentLang : 'en';
            
            // Build URL with parameters
            var url = 'generate_family_cancellation.php?family_id=' + encodeURIComponent(familyId);
            url += '&booking_id=' + encodeURIComponent(bookingId);
            url += '&cancellation_reason=' + encodeURIComponent(cancellationReason);
            url += '&returned_items=' + encodeURIComponent(JSON.stringify(returnedItems));
            url += '&item_condition=' + encodeURIComponent(JSON.stringify(itemConditions));
            url += '&item_notes=' + encodeURIComponent(JSON.stringify(itemNotes));
            url += '&lang=' + currentLang;
            
            console.log('Generation URL:', url);
            
            // AJAX request to generate cancellation form
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                timeout: 60000, // 60 second timeout for PDF generation
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.responseType = 'text'; // Receive as text first
                    return xhr;
                },
                success: function(response, textStatus, xhr) {
                    console.group('Cancellation Form Generation Response');
                    console.log('Raw Response:', xhr.responseText);
                    
                    try {
                        // If response is not already an object (e.g., from dataType: 'json'), parse it
                        if (typeof response === 'string') {
                            response = JSON.parse(xhr.responseText);
                        }
                        
                        console.log('Parsed Response:', response);
                        
                        // Reset button state
                        $('#familyGenerateCancellationFormBtn')
                            .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                            .prop('disabled', false);
                        
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Family Cancellation Form Generated',
                                html: response.message + '<br><small>Family Members: ' + (response.family_members_count || familyMembersData.length) + '</small>',
                                showCancelButton: true,
                                confirmButtonText: 'Download PDF',
                                cancelButtonText: 'Close',
                                confirmButtonColor: '#28a745'
                            }).then((result) => {
                                if (result.isConfirmed && response.file_url) {
                                    // Verify file exists before attempting download
                                    $.ajax({
                                        url: response.file_url,
                                        type: 'HEAD',
                                        success: function() {
                                            // File exists, open in new window
                                            window.open(response.file_url, '_blank');
                                        },
                                        error: function() {
                                            // File not found, show error message
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Download Error',
                                                text: 'The PDF file could not be found. Please contact support.',
                                                confirmButtonColor: '#dc3545'
                                            });

                                            // Log the error for debugging
                                            console.error('PDF File Not Found:', response.file_url);
                                        }
                                    });
                                }
                            });
                            
                            // Close the modal
                            $('#familyCancellationDetailsModal').modal('hide');
                            
                            // Refresh the page or update the UI as needed
                            if (typeof refreshBookingsTable === 'function') {
                                refreshBookingsTable();
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Generation Failed',
                                text: response.message || 'Failed to generate family cancellation form',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    } catch (parseError) {
                        console.error('JSON Parsing Error:', parseError);
                        
                        // Log the raw response for debugging
                        console.error('Raw Response Text:', xhr.responseText);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Response Error',
                            html: 'Failed to parse server response. Please contact support.<br>' +
                                '<small>Error: ' + parseError.message + '</small><br>' +
                                '<small>Response: ' + xhr.responseText.substring(0, 200) + '...</small>',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                    
                    console.groupEnd();
                },
                error: function(xhr, status, error) {
                    console.group('Cancellation Form Generation Error');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    console.groupEnd();
                    
                    // Reset button state
                    $('#familyGenerateCancellationFormBtn')
                        .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                        .prop('disabled', false);
                    
                    var errorMessage = 'Failed to generate family cancellation form.';
                    
                    if (xhr.status === 404) {
                        errorMessage = 'Generation endpoint not found.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Check server logs.';
                    } else if (status === 'parsererror') {
                        errorMessage = 'Invalid response from server. Response could not be parsed.';
                        
                        // Try to extract meaningful error message
                        try {
                            var responseText = xhr.responseText;
                            console.error('Unparseable Response:', responseText);
                            
                            // If it looks like HTML, extract the body
                            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                                var bodyMatch = responseText.match(/<body[^>]*>([\s\S]*)<\/body>/i);
                                if (bodyMatch) {
                                    responseText = bodyMatch[1];
                                }
                            }
                            
                            errorMessage += ' Server returned: ' + responseText.substring(0, 200) + '...';
                        } catch (e) {
                            console.error('Error extracting error message:', e);
                        }
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Generation Error',
                        html: errorMessage,
                        confirmButtonColor: '#dc3545'
                    });
                },
                complete: function() {
                    console.groupEnd(); // Close the main generation group
                }
            });
        } catch (error) {
            console.error('Form Generation Error:', error);
            
            // Reset button state
            $('#familyGenerateCancellationFormBtn')
                .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                .prop('disabled', false);
            
            Swal.fire({
                icon: 'error',
                title: 'Generation Error',
                text: error.message,
                confirmButtonColor: '#dc3545'
            });
            
            console.groupEnd(); // Close the main generation group
        }
    }
</script>


<div class="modal fade" id="familyCancellationLanguageModal" tabindex="-1" role="dialog" aria-labelledby="familyCancellationLanguageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyCancellationLanguageModalLabel">
                    <i class="feather icon-globe mr-2"></i><?= __('select_language_for_cancellation_form') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="en">
                            <i class="feather icon-flag mr-2"></i><?= __('english') ?>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="ps">
                            <i class="feather icon-flag mr-2"></i><?= __('pashto') ?>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-outline-primary btn-block language-select" data-lang="fa">
                            <i class="feather icon-flag mr-2"></i><?= __('dari') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Language selection for family cancellation form
$(document).on('click', '#familyGenerateCancellationFormBtn', function() {
    // Validate form first
    var form = $('#familyCancellationDetailsForm');
    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }

    // Check if at least one document is marked as returned for at least one member
    var hasReturnedDocuments = false;
    $('.member-return-checkbox:checked').each(function() {
        hasReturnedDocuments = true;
        return false; // Break the loop
    });
    
    if (!hasReturnedDocuments) {
        Swal.fire({
            icon: 'warning',
            title: 'No Documents Returned',
            text: 'Please mark at least one document as returned for at least one family member.',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    // Open language selection modal
    $('#familyCancellationLanguageModal').modal('show');
});

// Handle language selection
$(document).on('click', '#familyCancellationLanguageModal .language-select', function() {
    var selectedLang = $(this).data('lang');
    
    // Close language selection modal
    $('#familyCancellationLanguageModal').modal('hide');
    
    // Proceed with form generation
    generateFamilyCancellationForm(selectedLang);
});

// Modify generateFamilyCancellationForm to accept language parameter
function generateFamilyCancellationForm(lang) {
    console.group('Family Cancellation Form Generation');
    console.log('Starting form generation process with language:', lang);
    
    try {
        // Show loading state
        $('#familyGenerateCancellationFormBtn')
            .html('<i class="feather icon-loader spinning"></i> Generating...')
            .prop('disabled', true);
        
        // Collect all form data
        var familyId = $('#familyCancellationFamilyId').val();
        var bookingId = $('#familyCancellationBookingId').val();
        var cancellationReason = $('#familyCancellationReason').val().trim();
        
        console.log('Form data:', { familyId, bookingId, cancellationReason, lang });
        
        // Validate inputs
        if (!familyId) {
            throw new Error('Family ID is required');
        }
        
        if (!bookingId) {
            throw new Error('Booking ID is required');
        }
        
        if (!cancellationReason) {
            throw new Error('Cancellation reason is required');
        }
        
        // Use window.familyMembersData instead of local variable
        var familyMembersData = window.familyMembersData || [];
        
        console.log('Family Members Data:', familyMembersData);
        
        // Collect returned items and their conditions for each member
        var returnedItems = {};
        var itemConditions = {};
        var itemNotes = {};
        
        console.group('Returned Items Collection');
        
        familyMembersData.forEach(function(member) {
            var memberId = member.booking_id;
            var memberPrefix = 'member_' + memberId + '_';
            
            console.log('Processing member:', member);
            console.log('Member Prefix:', memberPrefix);
            
            // Document types
            var docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
            
            docTypes.forEach(function(docType) {
                var returnCheckbox = $('#member_' + memberId + '_return_' + docType);
                var conditionSelect = $('#member_' + memberId + '_condition_' + docType);
                var notesInput = $('#member_' + memberId + '_notes_' + docType);
                
                console.log('Checking document type:', docType);
                console.log('Return Checkbox:', returnCheckbox.length, returnCheckbox.is(':checked'));
                console.log('Condition Select:', conditionSelect.length, conditionSelect.val());
                console.log('Notes Input:', notesInput.length, notesInput.val());
                
                if (returnCheckbox.length) {
                    returnedItems[memberPrefix + docType] = returnCheckbox.is(':checked') ? '1' : '0';
                }
                if (conditionSelect.length) {
                    itemConditions[memberPrefix + docType] = conditionSelect.val() || '';
                }
                if (notesInput.length) {
                    itemNotes[memberPrefix + docType] = notesInput.val() || '';
                }
            });
        });
        
        console.log('Collected Returned Items:', returnedItems);
        console.log('Collected Item Conditions:', itemConditions);
        console.log('Collected Item Notes:', itemNotes);
        console.groupEnd();
        
        // Build URL with parameters
        var url = 'generate_family_cancellation.php?family_id=' + encodeURIComponent(familyId);
        url += '&booking_id=' + encodeURIComponent(bookingId);
        url += '&cancellation_reason=' + encodeURIComponent(cancellationReason);
        url += '&returned_items=' + encodeURIComponent(JSON.stringify(returnedItems));
        url += '&item_condition=' + encodeURIComponent(JSON.stringify(itemConditions));
        url += '&item_notes=' + encodeURIComponent(JSON.stringify(itemNotes));
        url += '&lang=' + encodeURIComponent(lang);
        
        console.log('Generation URL:', url);
        
        // AJAX request to generate cancellation form
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            timeout: 60000, // 60 second timeout for PDF generation
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.responseType = 'text'; // Receive as text first
                return xhr;
            },
            success: function(response, textStatus, xhr) {
                console.group('Cancellation Form Generation Response');
                console.log('Raw Response:', xhr.responseText);
                
                try {
                    // If response is not already an object (e.g., from dataType: 'json'), parse it
                    if (typeof response === 'string') {
                        response = JSON.parse(xhr.responseText);
                    }
                    
                    console.log('Parsed Response:', response);
                    
                    // Reset button state
                    $('#familyGenerateCancellationFormBtn')
                        .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                        .prop('disabled', false);
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Family Cancellation Form Generated',
                            html: response.message + '<br><small>Family Members: ' + (response.family_members_count || familyMembersData.length) + '</small>',
                            showCancelButton: true,
                            confirmButtonText: 'Download PDF',
                            cancelButtonText: 'Close',
                            confirmButtonColor: '#28a745'
                        }).then((result) => {
                            if (result.isConfirmed && response.file_url) {
                                // Verify file exists before attempting download
                                $.ajax({
                                    url: response.file_url,
                                    type: 'HEAD',
                                    success: function() {
                                        // File exists, open in new window
                                        window.open(response.file_url, '_blank');
                                    },
                                    error: function() {
                                        // File not found, show error message
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Download Error',
                                            text: 'The PDF file could not be found. Please contact support.',
                                            confirmButtonColor: '#dc3545'
                                        });

                                        // Log the error for debugging
                                        console.error('PDF File Not Found:', response.file_url);
                                    }
                                });
                            }
                        });
                        
                        // Close the modal
                        $('#familyCancellationDetailsModal').modal('hide');
                        
                        // Refresh the page or update the UI as needed
                        if (typeof refreshBookingsTable === 'function') {
                            refreshBookingsTable();
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Generation Failed',
                            text: response.message || 'Failed to generate family cancellation form',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                } catch (parseError) {
                    console.error('JSON Parsing Error:', parseError);
                    
                    // Log the raw response for debugging
                    console.error('Raw Response Text:', xhr.responseText);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Response Error',
                        html: 'Failed to parse server response. Please contact support.<br>' +
                              '<small>Error: ' + parseError.message + '</small><br>' +
                              '<small>Response: ' + xhr.responseText.substring(0, 200) + '...</small>',
                        confirmButtonColor: '#dc3545'
                    });
                }
                
                console.groupEnd();
            },
            error: function(xhr, status, error) {
                console.group('Cancellation Form Generation Error');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                console.groupEnd();
                
                // Reset button state
                $('#familyGenerateCancellationFormBtn')
                    .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
                    .prop('disabled', false);
                
                var errorMessage = 'Failed to generate family cancellation form.';
                
                if (xhr.status === 404) {
                    errorMessage = 'Generation endpoint not found.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Check server logs.';
                } else if (status === 'parsererror') {
                    errorMessage = 'Invalid response from server. Response could not be parsed.';
                    
                    // Try to extract meaningful error message
                    try {
                        var responseText = xhr.responseText;
                        console.error('Unparseable Response:', responseText);
                        
                        // If it looks like HTML, extract the body
                        if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                            var bodyMatch = responseText.match(/<body[^>]*>([\s\S]*)<\/body>/i);
                            if (bodyMatch) {
                                responseText = bodyMatch[1];
                            }
                        }
                        
                        errorMessage += ' Server returned: ' + responseText.substring(0, 200) + '...';
                    } catch (e) {
                        console.error('Error extracting error message:', e);
                    }
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Error',
                    html: errorMessage,
                    confirmButtonColor: '#dc3545'
                });
            },
            complete: function() {
                console.groupEnd(); // Close the main generation group
            }
        });
    } catch (error) {
        console.error('Form Generation Error:', error);
        
        // Reset button state
        $('#familyGenerateCancellationFormBtn')
            .html('<i class="feather icon-file-text mr-2"></i>Generate Family Cancellation Form')
            .prop('disabled', false);
        
        Swal.fire({
            icon: 'error',
            title: 'Generation Error',
            text: error.message,
            confirmButtonColor: '#dc3545'
        });
        
        console.groupEnd(); // Close the main generation group
    }
}

// Date Change Modal Functions
function openDateChangeModal(bookingId, passengerName, currentFlightDate, currentReturnDate, currentDuration, currentPrice, currency) {
    console.log('Opening date change modal for booking:', bookingId);

    // Set modal data
    document.getElementById('dateChangeBookingId').value = bookingId;
    document.getElementById('currentPassengerName').textContent = passengerName;
    document.getElementById('currentFlightDate').textContent = currentFlightDate || 'Not set';
    document.getElementById('currentReturnDate').textContent = currentReturnDate || 'Not set';
    document.getElementById('currentDuration').textContent = currentDuration || 'Not set';

    // Set current values as defaults for new fields
    document.getElementById('newFlightDate').value = currentFlightDate || '';
    document.getElementById('newReturnDate').value = currentReturnDate || '';
    document.getElementById('newDuration').value = currentDuration || '';
    document.getElementById('newPrice').value = currentPrice || '';

    // Reset form
    document.getElementById('dateChangeForm').reset();
    document.getElementById('dateChangeConfirmation').checked = false;

    // Show modal
    $('#dateChangeModal').modal('show');
}

// Submit Date Change Request
$(document).on('click', '#submitDateChangeRequest', function() {
    console.log('Submit date change request clicked');

    // Validate form
    var form = document.getElementById('dateChangeForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Check confirmation
    if (!document.getElementById('dateChangeConfirmation').checked) {
        Swal.fire({
            icon: 'warning',
            title: 'Confirmation Required',
            text: 'Please confirm that the date change request details are correct.',
            confirmButtonColor: '#17a2b8'
        });
        return;
    }

    // Show loading state
    var submitBtn = $(this);
    var originalHtml = submitBtn.html();
    submitBtn.html('<i class="feather icon-loader spinning"></i> Submitting...').prop('disabled', true);

    // Collect form data
    var formData = new FormData(form);

    // Submit request
    $.ajax({
        url: 'ajax/submit_date_change_request.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('Date change request response:', response);

            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Request Submitted',
                    text: response.message || 'Date change request has been submitted successfully.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    $('#dateChangeModal').modal('hide');
                    // Refresh the page to show updated data
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: response.message || 'Failed to submit date change request.',
                    confirmButtonColor: '#dc3545'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Date change request error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Request Error',
                text: 'An error occurred while submitting the request. Please try again.',
                confirmButtonColor: '#dc3545'
            });
        },
        complete: function() {
            // Reset button state
            submitBtn.html(originalHtml).prop('disabled', false);
        }
    });
});

// Auto-calculate duration when dates change
$(document).on('change', '#newFlightDate, #newReturnDate', function() {
    var flightDate = new Date($('#newFlightDate').val());
    var returnDate = new Date($('#newReturnDate').val());

    if (flightDate && returnDate && returnDate > flightDate) {
        var timeDiff = returnDate.getTime() - flightDate.getTime();
        var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

        // Set duration based on days difference
        var durationSelect = $('#newDuration');
        var durationValue = daysDiff + ' Days';

        // Check if the calculated duration exists in options
        if (durationSelect.find('option[value="' + durationValue + '"]').length > 0) {
            durationSelect.val(durationValue);
        }
    }
});
</script>

</body>
</html>

