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


?>


<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">
<link rel="stylesheet" href="../css/umrah/umrah-management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                                            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                            $visaStatus = isset($_GET['visa_status']) ? trim($_GET['visa_status']) : '';
                                            $offset = ($page - 1) * $resultsPerPage;

                                            // ---------- COUNT QUERY ----------
                                            $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                                        FROM families f
                                                        LEFT JOIN users u ON f.created_by = u.id
                                                        WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                                            $countParams = [$tenant_id, $branch_id];
                                            $countTypes = "ii";

                                            // Add filters for count
                                            if (!empty($visaStatus)) {
                                                $countSql .= " AND f.visa_status = ?";
                                                $countParams[] = $visaStatus;
                                                $countTypes .= "s";
                                            }

                                            if (!empty($search)) {
                                                $countSql .= " AND (
                                                    f.head_of_family LIKE ? OR
                                                    f.contact LIKE ? OR
                                                    f.address LIKE ? OR
                                                    f.package_type LIKE ? OR
                                                    f.location LIKE ? OR
                                                    u.name LIKE ? OR
                                                    EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                                        ub.name LIKE ? OR
                                                        ub.passport_number LIKE ?
                                                    ))
                                                )";
                                                $searchTerm = "%$search%";
                                                $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                                                $countTypes .= "ssssssiiiss";
                                            }

                                            $countStmt = $pdo->prepare($countSql);
                                            $countStmt->execute($countParams);
                                            $totalFamilies = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
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
                                                            WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                                            $familiesParams = [$tenant_id, $branch_id];
                                            $familiesTypes = "ii";

                                            // Add filters for main query
                                            if (!empty($visaStatus)) {
                                                $sqlFamilies .= " AND f.visa_status = ?";
                                                $familiesParams[] = $visaStatus;
                                                $familiesTypes .= "s";
                                            }

                                            if (!empty($search)) {
                                                $sqlFamilies .= " AND (
                                                    f.head_of_family LIKE ? OR
                                                    f.contact LIKE ? OR
                                                    f.address LIKE ? OR
                                                    f.package_type LIKE ? OR
                                                    f.location LIKE ? OR
                                                    u.name LIKE ? OR
                                                    EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                                        ub.name LIKE ? OR
                                                        ub.passport_number LIKE ?
                                                    ))
                                                )";
                                                $searchTerm = "%$search%";
                                                $familiesParams = array_merge($familiesParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                                                $familiesTypes .= "ssssssiiiss";
                                            }

                                            // Group by family and order newest first
                                            $sqlFamilies .= " GROUP BY f.family_id
                                            ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
                                            $familiesParams[] = $resultsPerPage;
                                            $familiesParams[] = $offset;
                                            $familiesTypes .= "ii";

                                            $familiesStmt = $pdo->prepare($sqlFamilies);
                                            $familiesStmt->execute($familiesParams);
                                            $resultFamilies = $familiesStmt->fetchAll(PDO::FETCH_ASSOC);

                                            // For dropdown
                                            $dropdownStmt = $pdo->prepare("SELECT * FROM families WHERE tenant_id = ? AND branch_id = ?");
                                            $dropdownStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                            $dropdownStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                            $dropdownStmt->execute();
                                            $resultFamiliesForDropdown = $dropdownStmt->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                <!-- Display Families and Bookings -->
                                <div class="container-fluid px-4">
                                    <div class="card umrah-card shadow-lg border-0 mb-4">
                                        <div class="card-header bg-primary text-white py-3">
                                            <div class="container-fluid px-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h4 class="mb-0 font-weight-bold"><?= __('family_list') ?></h4>
                                                    <button class="btn btn-light btn-sm" data-toggle="modal" data-target="#createFamilyModal" title="Add Family">
                                                        <i class="feather icon-plus"></i> <?= __('add_family') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <!-- Filters and Search Section -->
                                            <div class="p-3 border-bottom">
                                                <div class="container-fluid px-0">
                                                    <div class="row align-items-center">
                                                        <!-- Filter Tabs -->
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <div class="bg-light rounded-pill p-1">
                                                                <ul class="nav nav-pills nav-fill">
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= empty($visaStatus) ? ' active' : '' ?>"
                                                                           href="?visa_status="
                                                                           style="border-radius: 50px;">
                                                                            <?= __('all') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $visaStatus === 'Not Applied' ? ' active' : '' ?>"
                                                                           href="?visa_status=Not Applied"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('not_applied') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $visaStatus === 'Applied' ? ' active' : '' ?>"
                                                                           href="?visa_status=Applied"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('applied') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $visaStatus === 'Issued' ? ' active' : '' ?>"
                                                                           href="?visa_status=Issued"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('issued') ?>
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Search -->
                                                        <div class="col-md-6">
                                                            <div class="d-flex align-items-center justify-content-end">
                                                                <form id="familySearchForm" method="GET" class="d-flex">
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="search"
                                                                               class="form-control form-control-sm"
                                                                               placeholder="<?= __('search_families') ?>"
                                                                               name="search"
                                                                               value="<?= htmlspecialchars($search) ?>"
                                                                               aria-label="Search families">
                                                                        <div class="input-group-append">
                                                                            <button class="btn btn-outline-secondary" type="submit">
                                                                                <i class="feather icon-search"></i>
                                                                            </button>
                                                                        </div>
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
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover umrah-table mb-0" id="familyTable">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-left pl-4">
                                                                <?= __('family_info') ?>
                                                            </th>
                                                            <th>
                                                                <?= __('package_details') ?>
                                                            </th>
                                                            <th>
                                                                <?= __('financial') ?>
                                                            </th>
                                                            <th class="text-center">
                                                                <?= __('actions') ?>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($resultFamilies)) {
                                                            foreach ($resultFamilies as $row) {
                                                                $familyId = $row['family_id']; ?>
                                                                <?php
                                                                    $isFullyRefunded = ($row['total_members'] > 0 && $row['total_members'] == $row['refunded_members']);
                                                                    $rowClass = $isFullyRefunded ? 'table-danger' : '';
                                                                    ?>
                                                                <tr class="family-row <?= $rowClass ?>">
                                                                    <td class="pl-4">
                                                                        <div class="d-flex align-items-center">
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
                                                                        <span class=""><?= htmlspecialchars($row['package_type']) ?></span>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-map mr-1"></i><?= htmlspecialchars($row['location']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-users mr-1"></i><?= __('members') ?>: 
                                                                            <span class=""><?= htmlspecialchars($row['total_members']) ?></span>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-users mr-1"></i><?= __('refunded_members') ?>: 
                                                                            <span><?= htmlspecialchars($row['refunded_members']) ?></span>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-check-circle mr-1"></i><?= __('visa') ?>: 
                                                                            <span class="<?= $row['visa_status'] == 'Approved' ? 'success' : 'warning' ?>">
                                                                                <?= htmlspecialchars($row['visa_status']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="financial-summary">
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted"><?= __('total_price') ?>:</span>
                                                                                <strong><?= htmlspecialchars($row['total_price'] ?? '') ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-success"><?= __('paid') ?>:</span>
                                                                                <strong class="text-success"><?= htmlspecialchars($row['total_paid'] ?? '') ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-warning"><?= __('bank') ?>:</span>
                                                                                <strong class="text-warning"><?= htmlspecialchars($row['total_paid_to_bank'] ?? '') ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="text-danger"><?= __('due') ?>:</span>
                                                                                <strong class="text-danger"><?= htmlspecialchars($row['total_due'] ?? '') ?></strong>
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
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="openFamilyTransactionModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>', '<?= htmlspecialchars($row['package_type']) ?>', <?= $row['total_members'] ?>)">
                                                                                    <i class="feather icon-credit-card text-success mr-2"></i><?= __('family_transaction') ?>
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
                                                                                                <th>
                                                                                                    <input type="checkbox" id="selectAllMembers" onchange="toggleAllMembers()">
                                                                                                </th>
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
                                                                                                    ': ', s.name) SEPARATOR '|') as services_info
                                                                                            FROM umrah_bookings um
                                                                                            LEFT JOIN clients c ON um.sold_to = c.id
                                                                                            LEFT JOIN main_account ma ON um.paid_to = ma.id
                                                                                            LEFT JOIN umrah_booking_services ubs ON um.booking_id = ubs.booking_id
                                                                                            LEFT JOIN suppliers s ON ubs.supplier_id = s.id
                                                                                            LEFT JOIN users u ON um.created_by = u.id
                                                                                            WHERE um.family_id = ? AND um.tenant_id = ? AND um.branch_id = ?
                                                                                            GROUP BY um.booking_id";
                                                                                            $membersStmt = $pdo->prepare($sqlMembers);
                                                                                            $membersStmt->bindParam(1, $familyId, PDO::PARAM_INT);
                                                                                            $membersStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                                            $membersStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                                            $membersStmt->execute();
                                                                                            $resultMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                                            if (!empty($resultMembers)) {
                                                                                                foreach ($resultMembers as $member) { ?>
                                                                                                    <tr class="<?= isset($member['status']) && $member['status'] === 'refunded' ? 'table-danger' : '' ?>">
                                                                                                        <td>
                                                                                                            <input type="checkbox" class="member-checkbox" value="<?= $member['booking_id'] ?>"
                                                                                                                   data-booking-id="<?= $member['booking_id'] ?>"
                                                                                                                   data-base-price="<?= $member['price'] ?>"
                                                                                                                   data-sold-price="<?= $member['sold_price'] ?>"
                                                                                                                   data-current-profit="<?= $member['profit'] ?>"
                                                                                                                   data-status="<?= $member['status'] ?>"
                                                                                                                   data-currency="<?= $member['currency'] ?>">
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><?= __('sold_to') ?>: <?= htmlspecialchars($member['client_name']) ?></div>
                                                                                                            <div><?= __('paid_to') ?>: <?= htmlspecialchars($member['main_account_name']) ?></div>
                                                                                                            <div><strong><?= __('services') ?>:</strong><br>
                                                                                                                <?php
                                                                                                                $services = explode('|', $member['services_info']);
                                                                                                                if (!empty($services) && $services[0] !== '') {
                                                                                                                    echo '<ul class="list-unstyled mb-0">';
                                                                                                                    foreach ($services as $service) {
                                                                                                                        echo '<li>' . htmlspecialchars($service) . '</li>';
                                                                                                                    }
                                                                                                                    echo '</ul>';
                                                                                                                } else {
                                                                                                                    echo 'No services';
                                                                                                                }
                                                                                                                ?>
                                                                                                            </div>
                                                                                                            <div><?= __('created_by') ?>: <?= htmlspecialchars($member['created_by']) ?></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><strong><?= htmlspecialchars($member['name'] ?? '') ?></strong></div>
                                                                                                            <div><?= __('dob') ?>: <?= htmlspecialchars($member['dob'] ?? '') ?></div>
                                                                                                            <div><?= __('passport') ?>: <?= htmlspecialchars($member['passport_number'] ?? '') ?></div>
                                                                                                            <div><?= __('id') ?>: <span class=""><?= htmlspecialchars($member['id_type'] ?? '') ?></span></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><?= __('flight') ?>: <?= htmlspecialchars($member['flight_date'] ?? '') ?></div>
                                                                                                            <div><?= __('return') ?>: <?= htmlspecialchars($member['return_date'] ?? '') ?></div>
                                                                                                            <div><?= __('room') ?>: <?= htmlspecialchars($member['room_type'] ?? '') ?></div>
                                                                                                            <div><?= __('duration') ?>: <?= htmlspecialchars($member['duration'] ?? '') ?></div>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <div><?= __('base') ?>: <?= htmlspecialchars($member['price'] ?? '') ?></div>
                                                                                                            <div><?= __('discount') ?>: <?= htmlspecialchars($member['discount'] ?? '') ?></div>
                                                                                                            <div><?= __('sold') ?>: <?= htmlspecialchars($member['sold_price'] ?? '') ?></div>
                                                                                                            <div class="text-success"><?= __('paid') ?>: <?= htmlspecialchars($member['paid'] ?? '') ?></div>
                                                                                                            <?php
                                                                                                            // Fetch main account transactions for this booking
                                                                                                            $transactionSql = "SELECT SUM(payment_amount / COALESCE(exchange_rate, 1)) as main_account_total
                                                                                                                            FROM umrah_transactions
                                                                                                                            WHERE umrah_booking_id = ?
                                                                                                                            AND transaction_to = 'Internal Account'
                                                                                                                            AND tenant_id = ? AND branch_id = ?";
                                                                                                            $transStmt = $pdo->prepare($transactionSql);
                                                                                                            $transStmt->bindParam(1, $member['booking_id'], PDO::PARAM_INT);
                                                                                                            $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                                                            $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                                                            $transStmt->execute();
                                                                                                            $transResult = $transStmt->fetch(PDO::FETCH_ASSOC);
                                                                                                            $mainAccountTotal = $transResult ? ($transResult['main_account_total'] ?: 0) : 0;
                                                                                                            ?>
                                                                                                            <div class="text-primary"><?= __('internal_account') ?>: <?= htmlspecialchars($mainAccountTotal) ?></div>
                                                                                                            <div><?= __('bank') ?>: <?= htmlspecialchars($member['received_bank_payment'] ?? '') ?></div>
                                                                                                            <div><?= __('receipt') ?>: <?= htmlspecialchars($member['bank_receipt_number'] ?? '') ?></div>
                                                                                                            <div class="text-danger"><?= __('due') ?>: <?= htmlspecialchars($member['due'] ?? '') ?></div>
                                                                                                            <div class="text-success"><?= __('profit') ?>: <?= htmlspecialchars($member['profit'] ?? '') ?></div>
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
                                                                                                                    <a class="dropdown-item" href="#" onclick="openCancellationReapplyModal(<?= $member['booking_id'] ?>, <?= $member['price'] ?>, <?= $member['sold_price'] ?>, <?= $member['profit'] ?>, '<?= $member['currency'] ?>', '<?= $member['status'] ?>'); return false;">
                                                                                                                        <i class="feather icon-settings mr-2 text-primary"></i>Manage Booking Status
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
                                                                                                    <td colspan="6" class="text-center text-muted"><?= __('no_members_found') ?></td>
                                                                                                </tr>
                                                                                            <?php } ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                    
                                                                                    <!-- Bulk Action Buttons -->
                                                                                    <div class="mt-3">
                                                                                        <div class="row">
                                                                                            <div class="col-md-12">
                                                                                                <button type="button" class="btn btn-warning btn-sm mr-2" onclick="bulkCancelSelected()">
                                                                                                    <i class="feather icon-x-circle mr-1"></i>Cancel Selected
                                                                                                </button>
                                                                                                <button type="button" class="btn btn-success btn-sm" onclick="bulkReapplySelected()">
                                                                                                    <i class="feather icon-refresh-cw mr-1"></i>Re-apply Selected
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
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


    <?php include '../modals/umrah/edit_transaction_modal.php'; ?>
    <?php include '../modals/umrah/language_modal.php'; ?>
    <?php include '../modals/umrah/edit_member_modal.php'; ?>
    <?php include '../modals/umrah/umrah_modal.php'; ?>
    <?php include '../modals/umrah/create_family_modal.php'; ?>
    <?php include '../modals/umrah/transaction_modal.php'; ?>
    <?php include '../modals/umrah/edit_family_modal.php'; ?>
    <?php include '../modals/umrah/refund_modal.php'; ?>
    <?php include '../modals/umrah/cancellation_reapply_modal.php'; ?>
    <?php include '../modals/umrah/multi_ticket_invoice_modal.php'; ?>
    <?php include '../modals/umrah/completion_details_modal.php'; ?>
    <?php include '../modals/umrah/cancellation_details_modal.php'; ?>
    <?php include '../modals/umrah/family_language_modal.php'; ?>
    <?php include '../modals/umrah/family_completion_details_modal.php'; ?>
    <?php include '../modals/umrah/family_cancellation_details_modal.php'; ?>
    <?php include '../modals/umrah/member_document_template.php'; ?>
    <?php include '../modals/umrah/member_details_modal.php'; ?>
    <?php include '../modals/umrah/date_change_modal.php'; ?>
    <?php include '../modals/umrah/bank_receipt_modal.php'; ?>
    <?php include '../modals/umrah/umrah_presidency_modal.php'; ?>
    <?php include '../modals/umrah/group_ticket_modal.php'; ?>
    <?php include '../modals/umrah/id_card_modal.php'; ?>
    <?php include '../modals/umrah/family_transaction_modal.php'; ?>

    <!-- Floating action button -->
<div id="groupTicketFloatingButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050; display: none;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="showGroupTicketModal" title="<?= __('generate_group_ticket') ?>">
        <i class="feather icon-airplay"></i>
        <span class="badge-light badge-pill position-absolute" style="top: -5px; right: -5px;" id="groupTicketSelectionCount">0</span>
    </button>
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

    <script src="../js/umrah/transaction_manager.js"></script>
    <script src="../js/umrah/bookings.js"></script>
    <script src="../js/umrah/refund.js?v=1"></script>
    <script src="../js/umrah/cancellation_reapply.js"></script>
    <script src="../js/umrah/idcard.js"></script>
    <script src="../js/umrah/groupTickets.js"></script>
    <script src="../js/umrah/family.js"></script>
    <script src="../js/umrah/generations.js"></script>
    <script src="../js/umrah/generations_received_form.js"></script>
    <script src="../js/umrah/generate_completion.js"></script>
    <script src="../js/umrah/generate_cancelation.js"></script>
    <script src="../js/umrah/family_documents.js"></script>
    <script src="../js/umrah/generate_bankandumrah.js"></script>
    <script src="../js/umrah/date_change_request.js"></script>
    <script src="../js/umrah/multi_ticket.js"></script>
    <script src="../js/umrah/add_member.js"></script>
    <script src="../js/umrah/edit_member.js"></script>
    <script src="../js/umrah/family_cancellation.js"></script>
    <script src="../js/umrah/view_member_details.js"></script>
    <script src="../js/umrah/family_transaction_manager.js"></script>
    <!-- Include SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Include Umrah Forms JS -->
    <script src="../js/umrah/umrah-forms.js"></script>

    <script>
        // Auto-expand family members when searching
        if (window.location.search.includes('search=')) {
            document.querySelectorAll('[id^="family-members-"]').forEach(row => {
                row.style.display = 'table-row';
            });
        }


        // Edit supplier currency handler
        var editSupplierElement = document.getElementById('editSupplier');
        if (editSupplierElement) {
            editSupplierElement.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const currencyMatch = selectedOption.text.match(/\((.*?)\s*(USD|AFS)\)/);
                if (currencyMatch) {
                    document.getElementById('editSupplierCurrency').value = currencyMatch[2];
                } else {
                    document.getElementById('editSupplierCurrency').value = '';
                }
            });
        }
    </script>
     

    




<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<!-- Tesseract.js v5 with proper worker setup -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.0/dist/tesseract.min.js"></script>

<!-- Document Upload Handler for Umrah -->
<script src="../js/umrah/document-upload-handler.js"></script>

<!-- Floating action button for ID card generation -->
<div id="idCardFloatingButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050; display: none;">
    <button type="button" class="btn btn-dark btn-lg shadow" id="showIdCardModal" title="<?= __('generate_id_cards') ?>">
        <i class="feather icon-credit-card"></i>
        <span class="badge-light badge-pill position-absolute" style="top: -5px; right: -5px;" id="idCardSelectionCount">0</span>
    </button>
</div>

</body>
</html>

