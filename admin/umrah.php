<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}
// Database connection
require_once('../includes/db.php');

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/umrah/umrah-enhanced.css">
<link rel="stylesheet" href="../css/document-upload.css">
<link rel="stylesheet" href="../css/passport-photo-extractor.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-kaaba page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_management') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_families_and_bookings') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">

                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus-circle mr-2"></i><?= __('add_family') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                    // Search and Pagination setup
                    $resultsPerPage = 12;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                    $visaStatus = isset($_GET['visa_status']) ? trim($_GET['visa_status']) : '';
                    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
                    $offset = ($page - 1) * $resultsPerPage;

                    // COUNT QUERY
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";
                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

                        if (!empty($search)) {
                            $countSql .= " AND (
                                f.head_of_family LIKE ? OR
                                f.contact LIKE ? OR
                                f.address LIKE ? OR
                                f.package_type LIKE ? OR
                                f.location LIKE ? OR
                                u.name LIKE ? OR
                                EXISTS (SELECT 1 FROM umrah_bookings ub2 WHERE ub2.family_id = f.family_id AND ub2.tenant_id = ? AND ub2.branch_id = ? AND (
                                    ub2.name LIKE ? OR
                                    ub2.passport_number LIKE ?
                                ))
                            )";
                            $searchTerm = "%$search%";
                            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                            $countTypes .= "ssssssiiiss";
                        }

                        $countSql .= " GROUP BY f.family_id
                                    HAVING SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END) > 0";
                    } else {
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

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
                    }

                    $countStmt = $pdo->prepare($countSql);
                    $countStmt->execute($countParams);
                    $totalFamilies = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                    $totalPages = ceil($totalFamilies / $resultsPerPage);

                    // MAIN QUERY
                    $sqlFamilies = "SELECT
                                        f.*,
                                        u.name as created_by,
                                        COUNT(ub.booking_id) AS total_members,
                                        SUM(CASE WHEN ub.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_members,
                                        SUM(CASE WHEN ub.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_members,
                                        (SELECT COUNT(*) FROM group_tickets gt WHERE gt.tenant_id = f.tenant_id AND gt.branch_id = f.branch_id AND JSON_CONTAINS(gt.member_ids, JSON_ARRAY((SELECT booking_id FROM umrah_bookings ub2 WHERE ub2.family_id = f.family_id LIMIT 1))) AND gt.status = 'active') AS has_group_tickets
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                    $familiesParams = [$tenant_id, $branch_id];
                    $familiesTypes = "ii";

                    if (($filter !== 'refunded' && $filter !== 'cancelled') && !empty($visaStatus)) {
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

                    $sqlFamilies .= " GROUP BY f.family_id";
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $sqlFamilies .= " HAVING SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END) > 0";
                    }
                    $sqlFamilies .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
                    $familiesParams[] = $resultsPerPage;
                    $familiesParams[] = $offset;
                    $familiesTypes .= "ii";

                    $familiesStmt = $pdo->prepare($sqlFamilies);
                    $familiesStmt->execute($familiesParams);
                    $resultFamilies = $familiesStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Calculate statistics
                    $totalRevenue = 0;
                    $totalCollected = 0;
                    $totalOutstanding = 0;
                    foreach ($resultFamilies as $family) {
                        $totalRevenue += floatval($family['total_price'] ?? 0);
                        $totalCollected += floatval($family['total_paid'] ?? 0);
                        $totalOutstanding += floatval($family['total_due'] ?? 0);
                    }
                ?>

                <!-- Filters and Search -->
                <div class="container-fluid px-4 mb-4">
                    <div class="filters-wrapper">
                        <!-- Filter Pills -->
                        <div class="filter-pills">
                            <a href="?visa_status=" class="filter-pill <?= empty($filter) && empty($visaStatus) ? 'active' : '' ?>">
                                <i class="fas fa-layer-group"></i>
                                <span><?= __('all') ?></span>
                                <span class="pill-badge"><?= $totalFamilies ?></span>
                            </a>
                            <a href="?visa_status=Not Applied" class="filter-pill <?= empty($filter) && $visaStatus === 'Not Applied' ? 'active' : '' ?>">
                                <i class="fas fa-clock"></i>
                                <span><?= __('not_applied') ?></span>
                            </a>
                            <a href="?visa_status=Applied" class="filter-pill <?= empty($filter) && $visaStatus === 'Applied' ? 'active' : '' ?>">
                                <i class="fas fa-hourglass-half"></i>
                                <span><?= __('applied') ?></span>
                            </a>
                            <a href="?visa_status=Issued" class="filter-pill <?= empty($filter) && $visaStatus === 'Issued' ? 'active' : '' ?>">
                                <i class="fas fa-check-circle"></i>
                                <span><?= __('issued') ?></span>
                            </a>
                            <a href="?filter=refunded" class="filter-pill <?= $filter === 'refunded' ? 'active' : '' ?>">
                                <i class="fas fa-undo"></i>
                                <span><?= __('refunded') ?></span>
                            </a>
                            <a href="?filter=cancelled" class="filter-pill <?= $filter === 'cancelled' ? 'active' : '' ?>">
                                <i class="fas fa-times-circle"></i>
                                <span><?= __('cancelled') ?></span>
                            </a>
                        </div>

                        <!-- Enhanced Search -->
                        <div class="search-wrapper">
                            <form method="GET" class="search-form">
                                <div class="search-input-group">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="search" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="<?= __('search_families_members_passports') ?>"
                                           class="search-input">
                                    <input type="hidden" name="visa_status" value="<?= htmlspecialchars($visaStatus) ?>">
                                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                                    <?php if (!empty($search)): ?>
                                        <a href="?" class="clear-search">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="search-button">
                                        <?= __('search') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Family Cards Grid -->
                <div class="container-fluid px-4">
                    <?php if (!empty($resultFamilies)): ?>
                        <div class="family-cards-grid">
                            <?php foreach ($resultFamilies as $row): 
                                $familyId = $row['family_id'];
                                $isFullyRefunded = ($row['total_members'] > 0 && $row['total_members'] == $row['refunded_members']);
                                
                                // Calculate payment percentage
                                $totalPrice = floatval($row['total_price'] ?? 0);
                                $totalPaid = floatval($row['total_paid'] ?? 0);
                                $paymentPercentage = $totalPrice > 0 ? ($totalPaid / $totalPrice) * 100 : 0;
                                
                                // Get visa status color
                                $visaStatusClass = 'default';
                                switch ($row['visa_status']) {
                                    case 'Not Applied':
                                        $visaStatusClass = 'warning';
                                        break;
                                    case 'Applied':
                                        $visaStatusClass = 'info';
                                        break;
                                    case 'Issued':
                                        $visaStatusClass = 'success';
                                        break;
                                }
                            ?>
                                <div class="family-card <?= $isFullyRefunded ? 'refunded-family' : '' ?>" data-family-id="<?= $familyId ?>">
                                    <!-- Card Header -->
                                    <div class="card-header-section">
                                        <div class="family-avatar">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="family-main-info">
                                            <h3 class="family-name"><?= htmlspecialchars($row['head_of_family']) ?></h3>
                                            <div class="family-meta">
                                                    <span class="meta-item">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?= htmlspecialchars($row['location']) ?>
                                                    </span>
                                                    <span class="meta-item">
                                                        <i class="fas fa-users"></i>
                                                        <?= $row['total_members'] ?> <?= __('members') ?>
                                                    </span>
                                                    <?php if ($row['refunded_members'] > 0): ?>
                                                    <span class="meta-item text-warning" title="<?= __('refunded_members') ?>">
                                                        <i class="fas fa-undo"></i>
                                                        <?= $row['refunded_members'] ?> <?= __('refunded') ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($row['cancelled_members'] > 0): ?>
                                                    <span class="meta-item text-danger" title="<?= __('cancelled_members') ?>">
                                                        <i class="fas fa-ban"></i>
                                                        <?= $row['cancelled_members'] ?> <?= __('cancelled') ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                        </div>
                                        <div class="card-actions">
                                             <button class="btn-icon view-members-btn" data-family-id="<?= $familyId ?>" type="button" title="<?= __('view_members') ?>">
                                                 <i class="fas fa-eye"></i>
                                             </button>
                                            <div class="dropdown">
                                                <button class="btn-icon" type="button" data-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                         <i class="fas fa-user-plus"></i><?= __('add_member') ?>
                                                     </a>
                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="openEditFamilyModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>',
                                                     '<?= htmlspecialchars($row['contact']) ?>', '<?= htmlspecialchars($row['address']) ?>',
                                                     '<?= htmlspecialchars($row['package_type']) ?>', '<?= htmlspecialchars($row['location']) ?>',
                                                     '<?= htmlspecialchars($row['tazmin']) ?>', '<?= htmlspecialchars($row['visa_status']) ?>',
                                                     '<?= htmlspecialchars($row['province']) ?>', '<?= htmlspecialchars($row['district']) ?>')">
                                                         <i class="fas fa-edit"></i><?= __('edit') ?>
                                                     </a>
                                                     <?php if ($canEdit): ?>
                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="openFamilyTransactionModal(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>', '<?= htmlspecialchars($row['package_type']) ?>', <?= $row['total_members'] ?>)">
                                                         <i class="fas fa-credit-card"></i><?= __('family_transaction') ?>
                                                     </a>
                                                     <?php endif; ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyTazmin(<?= $familyId ?>)">
                                                        <i class="fas fa-shield-alt"></i><?= __('generate_family_tazmin') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyAgreement(<?= $familyId ?>)">
                                                        <i class="fas fa-file-contract"></i><?= __('generate_family_agreement') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCompletion(<?= $familyId ?>)">
                                                        <i class="fas fa-check-circle"></i><?= __('generate_family_completion') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCancellation(<?= $familyId ?>)">
                                                        <i class="fas fa-times-circle"></i><?= __('generate_family_cancellation') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showBankLetterModal(<?= $familyId ?>)">
                                                        <i class="fas fa-file-invoice"></i><?= __("bank_receipt") ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showUmrahPresidencyModal(<?= $familyId ?>)">
                                                        <i class="fas fa-landmark"></i><?= __("umrah_presidency") ?>
                                                    </a>
                                                    <?php if ($canEdit): ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteFamily(<?= $familyId ?>)">
                                                        <i class="fas fa-trash"></i><?= __('delete') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="card-body-section">
                                        <!-- Contact Information -->
                                        <div class="info-row">
                                            <i class="fas fa-phone"></i>
                                            <span><?= htmlspecialchars($row['contact']) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?= htmlspecialchars($row['address']) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-globe"></i>
                                            <span><?= htmlspecialchars($row['province']) ?> - <?= htmlspecialchars($row['district']) ?></span>
                                        </div>

                                        <!-- Package Info -->
                                         <div class="package-info">
                                             <div class="package-badge">
                                                 <i class="fas fa-box"></i>
                                                 <?= htmlspecialchars($row['package_type']) ?>
                                             </div>
                                             <div class="visa-badge visa-<?= $visaStatusClass ?>">
                                                 <i class="fas fa-passport"></i>
                                                 <?= htmlspecialchars($row['visa_status']) ?>
                                             </div>
                                         </div>

                                         <!-- Flight Status Badge -->
                                         <div class="flight-badge" id="flight-status-<?= $familyId ?>">
                                             <i class="fas fa-plane"></i>
                                             <span id="flight-status-text-<?= $familyId ?>">Loading...</span>
                                         </div>

                                         <!-- Flight Details Button (if group ticket exists) -->
                                         <?php if ($row['has_group_tickets'] > 0): ?>
                                         <button class="btn btn-sm btn-info" 
                                                 onclick="viewFamilyFlightDetails(<?= $familyId ?>, '<?= htmlspecialchars($row['head_of_family']) ?>')"
                                                 title="<?= __('view_flight_details') ?>"
                                                 style="margin-top: 8px;">
                                             <i class="fas fa-ticket-alt"></i> <?= __('flight_details') ?>
                                         </button>
                                         <?php endif; ?>

                                        <!-- Financial Summary -->
                                        <div class="financial-summary">
                                            <?php 
                                                // Check if any family member has regular client type
                                                $hasRegularClient = false;
                                                $checkClientTypeStmt = $pdo->prepare("
                                                    SELECT COUNT(*) as regular_count 
                                                    FROM umrah_bookings ub
                                                    JOIN clients c ON ub.sold_to = c.id
                                                    WHERE ub.family_id = ? AND c.client_type = 'regular' 
                                                    AND ub.tenant_id = ? AND ub.branch_id = ?
                                                ");
                                                $checkClientTypeStmt->bindParam(1, $familyId, PDO::PARAM_INT);
                                                $checkClientTypeStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                $checkClientTypeStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                $checkClientTypeStmt->execute();
                                                $clientTypeResult = $checkClientTypeStmt->fetch(PDO::FETCH_ASSOC);
                                                $hasRegularClient = $clientTypeResult && $clientTypeResult['regular_count'] > 0;
                                            ?>
                                            <?php if (!$hasRegularClient): ?>
                                            <div class="financial-header">
                                                <span><?= __('payment_status') ?></span>
                                                <span class="percentage"><?= number_format($paymentPercentage, 1) ?>%</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= $paymentPercentage ?>%"></div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="financial-details">
                                                <div class="financial-item">
                                                    <span class="label"><?= __('total_price') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_price'] ?? '0') ?></span>
                                                </div>
                                                <?php if (!$hasRegularClient): ?>
                                                <div class="financial-item success">
                                                    <span class="label"><?= __('paid') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_paid'] ?? '0') ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <div class="financial-item warning">
                                                    <span class="label"><?= __('bank') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_paid_to_bank'] ?? '0') ?></span>
                                                </div>
                                                <?php if (!$hasRegularClient): ?>
                                                <div class="financial-item danger">
                                                    <span class="label"><?= __('due') ?></span>
                                                    <span class="value"><?= htmlspecialchars($row['total_due'] ?? '0') ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($hasRegularClient): ?>
                                            <div class="alert alert-info mt-3" style="margin: 10px 0 0 0; padding: 8px 12px; font-size: 12px;">
                                                <i class="fas fa-info-circle"></i>
                                                <strong><?= __('note') ?>:</strong> <?= __('add_only_bank_transaction_for_outsider_client') ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Members Section (Initially Hidden) -->
                                    <div class="members-section" id="members-<?= $familyId ?>" style="display: none;">
                                        <div class="members-header">
                                            <h4><?= __('family_members') ?></h4>
                                            <button class="btn-sm btn-primary" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                <i class="fas fa-plus"></i> <?= __('add_member') ?>
                                            </button>
                                        </div>
                                        <div class="members-grid" id="members-grid-<?= $familyId ?>">
                                            <!-- Members will be loaded via AJAX -->
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <?= __('loading_members') ?>...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Enhanced Pagination -->
                        <nav class="pagination-wrapper" aria-label="Family list pagination">
                            <ul class="pagination-list">
                                <?php
                                $queryString = "";
                                if (!empty($search)) {
                                    $queryString .= "&search=" . urlencode($search);
                                }
                                if (!empty($visaStatus)) {
                                    $queryString .= "&visa_status=" . urlencode($visaStatus);
                                }
                                if (!empty($filter)) {
                                    $queryString .= "&filter=" . urlencode($filter);
                                }

                                if ($page > 1): ?>
                                    <li>
                                        <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li>
                                        <a href="?page=<?= $i . $queryString ?>" 
                                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="pagination-info">
                                <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?> 
                                (<?= $totalFamilies ?> <?= __('total_families') ?>)
                            </div>
                        </nav>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3><?= !empty($search) ? sprintf(__('no_families_found_for_search'), htmlspecialchars($search)) : __('no_families_available') ?></h3>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
                                </a>
                            <?php else: ?>
                                <p><?= __('start_by_adding_a_new_family') ?></p>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus mr-2"></i><?= __('add_new_family') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
<?php include '../modals/umrah/member_documents_modal.php'; ?>
<?php include '../modals/umrah/date_change_modal.php'; ?>
<?php include '../modals/umrah/bank_receipt_modal.php'; ?>
<?php include '../modals/umrah/umrah_presidency_modal.php'; ?>
<?php include '../modals/umrah/group_ticket_modal.php'; ?>
<?php include '../modals/umrah/id_card_modal.php'; ?>
<?php include '../modals/umrah/family_transaction_modal.php'; ?>
<?php include '../modals/umrah/flight_details_modal.php'; ?>

<!-- Floating action buttons -->
<div id="groupTicketFloatingButton" class="floating-action-btn" style="display: none; bottom: 220px; right: 23px;">
    <button type="button" class="fab-button" id="showGroupTicketModal">
        <i class="fas fa-plane"></i>
        <span class="fab-badge" id="groupTicketSelectionCount">0</span>
    </button>
</div>

<div id="idCardFloatingButton" class="floating-action-btn" style="display: none; bottom: 85px; right: 23px;">
    <button type="button" class="fab-button fab-dark" id="showIdCardModal">
        <i class="fas fa-id-card"></i>
        <span class="fab-badge" id="idCardSelectionCount">0</span>
    </button>
</div>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<!-- Document Upload Script -->
<script src="../js/member-document-upload.js"></script>
<script src="../js/umrah/refresh-families.js"></script>

<script src="../js/umrah/transaction_manager.js"></script>
<script src="../js/umrah/approve_booking.js"></script>
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
<script src="../js/umrah/edit_member.js"></script>
<script src="../js/umrah/family_cancellation.js"></script>
<script src="../js/umrah/view_member_details.js"></script>
<script src="../js/umrah/family_transaction_manager.js"></script>
<script src="../js/umrah/umrah-forms.js"></script>
<script src="../js/umrah/flight_details.js"></script>
<script src="../js/umrah/bookings.js"></script>

<!-- Tesseract.js for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.0/dist/tesseract.min.js"></script>

<!-- Multi-Member Umrah Modal (NEW - replaces single-member) -->
<script src="../js/umrah/add_member_multi_refactored.js?v=<?php echo time(); ?>"></script>
<!-- Multi-Member Form Submission (NEW) -->
<script src="../js/umrah/bookings_multi.js"></script>

<!-- Single-Member Document Handler (fallback if needed) -->
<script src="../js/umrah/open_documents_modal.js"></script>
<script src="../js/umrah/passport-photo-extractor.js"></script>
<script src="../js/umrah/auto-passport-extractor.js"></script>
<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    // Toast notification
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

    // View family members with AJAX loading
    window.viewFamilyMembers = function(familyId) {
        try {
            const sectionId = 'members-' + familyId;
            const gridId = 'members-grid-' + familyId;
            const section = document.getElementById(sectionId);
            const grid = document.getElementById(gridId);
            const card = document.querySelector('[data-family-id="' + familyId + '"]');
            
            console.log('VIEW: familyId=' + familyId + ', section=' + (section ? 'FOUND' : 'NOT FOUND') + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
            
            if (!section || !grid) {
                console.error('ERROR: Could not find section or grid');
                return false;
            }
            
            const isHidden = section.style.display === 'none';
            section.style.display = isHidden ? 'block' : 'none';
            
            // Add/remove members-visible class to the card
            if (card) {
                if (isHidden) {
                    card.classList.add('members-visible');
                } else {
                    card.classList.remove('members-visible');
                }
            }
            
            console.log('VIEW: Display changed to ' + section.style.display);
            
            if (isHidden && grid.innerHTML.includes('loading-spinner')) {
                console.log('VIEW: Loading members...');
                window.loadFamilyMembers(familyId);
            }
            return false;
        } catch(err) {
            console.error('VIEW ERROR:', err);
            console.error('Stack:', err.stack);
        }
    };

    // Load family members via AJAX
    window.loadFamilyMembers = function(familyId) {
        const gridId = 'members-grid-' + familyId;
        const grid = document.getElementById(gridId);
        
        console.log('LOAD: familyId=' + familyId + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
        
        if (!grid) {
            alert('ERROR: Grid element not found: ' + gridId);
            return;
        }
        
        grid.innerHTML = '<div style="padding: 20px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Loading members...</div>';
        
        // Get filter parameter from current URL
        const urlParams = new URLSearchParams(window.location.search);
        const filter = urlParams.get('filter') || '';
        let url = '../api/umrah/load_family_members.php?family_id=' + familyId;
        if (filter) {
            url += '&filter=' + encodeURIComponent(filter);
        }
        console.log('LOAD: Fetching from ' + url);
        
        fetch(url)
            .then(function(response) {
                console.log('LOAD: Response status ' + response.status);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                console.log('LOAD: Data received', data);
                if (data.success) {
                    grid.innerHTML = data.html;
                    console.log('LOAD: Success - members displayed');
                } else {
                    grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + (data.message || 'Unknown error') + '</div>';
                    console.error('LOAD: API error - ' + data.message);
                }
            })
            .catch(function(error) {
                console.error('LOAD: Fetch error', error);
                grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + error.message + '</div>';
                alert('Error loading members: ' + error.message);
            });
    };

    // Auto-expand members when searching
    if (window.location.search.includes('search=')) {
        document.querySelectorAll('.members-section').forEach(section => {
            section.style.display = 'block';
            const familyId = section.id.replace('members-', '');
            loadFamilyMembers(familyId);
        });
    }

    // Add event listener to view members buttons (run immediately, don't wait for DOMContentLoaded)
    function attachMembersButtonListeners() {
        console.log('Attaching members button listeners...');
        const buttons = document.querySelectorAll('.view-members-btn');
        console.log('Found ' + buttons.length + ' buttons');
        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const familyId = this.getAttribute('data-family-id');
                console.log('Button clicked for family ' + familyId);
                try {
                    var result = window.viewFamilyMembers(familyId);
                    console.log('viewFamilyMembers returned:', result);
                } catch(ex) {
                    console.error('Exception:', ex);
                }
            });
        });
    }
    
    // Attach listeners immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachMembersButtonListeners);
    } else {
        attachMembersButtonListeners();
    }

    // Load flight status for each family card
    function loadFlightStatusForFamilies() {
        const familyCards = document.querySelectorAll('[data-family-id]');
        familyCards.forEach(card => {
            const familyId = card.getAttribute('data-family-id');
            loadFlightStatus(familyId);
        });
    }

    function loadFlightStatus(familyId) {
        const statusBadge = document.getElementById('flight-status-' + familyId);
        const statusText = document.getElementById('flight-status-text-' + familyId);
        if (!statusBadge || !statusText) return;

        fetch(`../api/umrah/get_group_ticket_info.php?family_id=${familyId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const totalMembers = data.members_total;
                    const flightDone = data.members_with_flight;
                    
                    // Reset classes
                    statusBadge.classList.remove('flight-complete', 'flight-partial', 'flight-pending');
                    
                    if (flightDone === totalMembers && totalMembers > 0) {
                        statusBadge.classList.add('flight-complete');
                        statusText.textContent = `✓ Flight Done (${flightDone}/${totalMembers})`;
                    } else if (flightDone > 0) {
                        statusBadge.classList.add('flight-partial');
                        statusText.textContent = `⚠ Flight Done (${flightDone}/${totalMembers})`;
                    } else {
                        statusBadge.classList.add('flight-pending');
                        statusText.textContent = `Flight Pending (0/${totalMembers})`;
                    }
                } else {
                    statusBadge.classList.add('flight-pending');
                    statusText.textContent = '-';
                }
            })
            .catch(error => {
                console.error('Error loading flight status:', error);
                statusBadge.classList.add('flight-pending');
                statusText.textContent = '-';
            });
    }

    // Load flight status after page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadFlightStatusForFamilies);
    } else {
        loadFlightStatusForFamilies();
    }
</script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>