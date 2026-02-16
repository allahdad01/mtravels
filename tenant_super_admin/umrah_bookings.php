<?php
/**
 * Umrah Bookings - Tenant Interface
 *
 * Displays list of umrah bookings with search and filter functionality.
 */

require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get branch filter from URL or session
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : 'all';

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

// Build query for umrah bookings
$query = "SELECT
    ub.*,
    u.name as created_by_name,
    b.name as branch_name,
    f.head_of_family,
    f.contact,
    f.province,
    f.district
FROM umrah_bookings ub
LEFT JOIN users u ON ub.created_by = u.id
LEFT JOIN branches b ON ub.branch_id = b.id
LEFT JOIN families f ON ub.family_id = f.family_id
WHERE ub.tenant_id = ?";

// Add branch filter
if ($branch_filter !== 'all') {
    $query .= " AND ub.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (ub.name LIKE ? OR ub.fname LIKE ? OR ub.passport_number LIKE ? OR f.head_of_family LIKE ?)";
}

// Add ordering and pagination
$query .= " ORDER BY ub.created_at DESC LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

if ($branch_filter !== 'all') {
    $params[] = $branch_filter;
}

if (!empty($search)) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM umrah_bookings ub WHERE ub.tenant_id = ?";
$count_params = [$tenant_id];

if ($branch_filter !== 'all') {
    $count_query .= " AND ub.branch_id = ?";
    $count_params[] = $branch_filter;
}

if (!empty($search)) {
    $count_query .= " AND (ub.name LIKE ? OR ub.fname LIKE ? OR ub.passport_number LIKE ? OR f.head_of_family LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_bookings = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_bookings / $results_per_page);

// Get branches for filter dropdown
$branches_query = "SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name";
$branches_stmt = $pdo->prepare($branches_query);
$branches_stmt->execute([$tenant_id]);
$branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('umrah_bookings');
include 'header.php';
?>

    <style>
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
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
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .progress {
        border-radius: 15px;
        
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;
        
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

    #estimated_cost {
        color: #28a745;
        font-weight: bold;
    }

    .h2 {
        font-size: 2.5rem;
    }

    .h4 {
        font-size: 1.5rem;
    }

    .h5 {
        font-size: 1.25rem;
    }

    .h6 {
        font-size: 1rem;
    }
    </style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-map-pin mr-2"></i><?php echo __('umrah_bookings'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_umrah_bookings'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-sm-12">
                                <!-- Search and Filter Section -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="search-box">
                                                    <div class="input-group">
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by pilgrim name, passport, or head of family" value="<?= htmlspecialchars($search) ?>">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-primary" type="button" id="searchBtn">
                                                                <i class="feather icon-search"></i> Search
                                                            </button>
                                                            <?php if (!empty($search)): ?>
                                                            <a href="?branch=<?= $branch_filter ?>" class="btn btn-secondary">
                                                                <i class="feather icon-x"></i> Clear
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="branchFilter" class="sr-only">Filter by Branch</label>
                                                    <select class="form-control" id="branchFilter">
                                                        <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>All Branches</option>
                                                        <?php foreach ($branches as $branch): ?>
                                                        <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($branch['name']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bookings Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Pilgrim Info</th>
                                                        <th>Family Info</th>
                                                        <th>Booking Details</th>
                                                        <th>Branch</th>
                                                        <th class="text-right">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="bookingTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($bookings as $booking):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-booking='<?= htmlspecialchars(json_encode($booking)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="pilgrim-info">
                                                                <div class="pilgrim-info__details">
                                                                    <div class="pilgrim-info__name">
                                                                        <?= htmlspecialchars($booking['name']) ?> <?= htmlspecialchars($booking['fname']) ?> <?= htmlspecialchars($booking['gfname']) ?>
                                                                    </div>
                                                                    <div class="pilgrim-info__passport">
                                                                        Passport: <?= htmlspecialchars($booking['passport_number']) ?>
                                                                    </div>
                                                                    <div class="pilgrim-info__relation">
                                                                        Relation: <?= htmlspecialchars($booking['relation']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="family-info">
                                                                <div class="family-info__head">
                                                                    <div class="family-info__name">
                                                                        Head: <?= htmlspecialchars($booking['head_of_family'] ?: 'Individual') ?>
                                                                    </div>
                                                                    <div class="family-info__location">
                                                                        <?= htmlspecialchars($booking['province']) ?>, <?= htmlspecialchars($booking['district']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="booking-details">
                                                                <div class="booking-details__dates">
                                                                    <i class="feather icon-calendar text-muted mr-1"></i>
                                                                    Entry: <?= htmlspecialchars($booking['entry_date']) ?>
                                                                    <?php if ($booking['flight_date']): ?>
                                                                        <br>
                                                                        <i class="feather icon-plane text-muted mr-1"></i>
                                                                        Flight: <?= htmlspecialchars($booking['flight_date']) ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="booking-details__status">
                                                                    <span class="badge badge-<?= $booking['status'] === 'active' ? 'success' : ($booking['status'] === 'refunded' ? 'danger' : 'secondary') ?>">
                                                                        <?= ucfirst(htmlspecialchars($booking['status'])) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-info">
                                                                <?= htmlspecialchars($booking['branch_name'] ?: 'No Branch') ?>
                                                            </span>
                                                        </td>

                                                        <td class="text-right">
                                                            <div class="booking-amount">
                                                                <div class="booking-amount__sold">
                                                                    <?= htmlspecialchars($booking['currency']) ?> <?= number_format($booking['sold_price'], 2) ?>
                                                                </div>
                                                                <div class="booking-amount__profit text-success">
                                                                    <small>Profit: <?= htmlspecialchars($booking['currency']) ?> <?= number_format($booking['profit'], 2) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <div class="card-footer bg-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-muted">
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_bookings) ?> to <?= min($page * $results_per_page, $total_bookings) ?> of <?= $total_bookings ?> umrah bookings
                                                </div>
                                                <nav aria-label="Page navigation">
                                                    <ul class="pagination mb-0">
                                                        <?php if ($page > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=1&branch=<?= $branch_filter ?>&search=<?= urlencode($search) ?>">
                                                                    <i class="feather icon-chevrons-left"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page - 1 ?>&branch=<?= $branch_filter ?>&search=<?= urlencode($search) ?>">
                                                                    <i class="feather icon-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start_page = max(1, $page - 2);
                                                        $end_page = min($total_pages, $page + 2);

                                                        if ($start_page > 1) {
                                                            echo '<li class="page-item"><a class="page-link" href="?page=1&branch=' . $branch_filter . '&search=' . urlencode($search) . '">1</a></li>';
                                                            if ($start_page > 2) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                        }

                                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $i . '&branch=' . $branch_filter . '&search=' . urlencode($search) . '">' . $i . '</a>
                                                            </li>';
                                                        }

                                                        if ($end_page < $total_pages) {
                                                            if ($end_page < $total_pages - 1) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&branch=' . $branch_filter . '&search=' . urlencode($search) . '">' . $total_pages . '</a></li>';
                                                        }
                                                        ?>

                                                        <?php if ($page < $total_pages): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page + 1 ?>&branch=<?= $branch_filter ?>&search=<?= urlencode($search) ?>">
                                                                    <i class="feather icon-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $total_pages ?>&branch=<?= $branch_filter ?>&search=<?= urlencode($search) ?>">
                                                                    <i class="feather icon-chevrons-right"></i>
                                                                </a>
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

<!-- Booking Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-map-pin mr-2"></i>Umrah Booking Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Sold Price</div>
                            <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Base Price</div>
                            <h4 class="mb-0 text-info" id="base-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Profit</div>
                            <h4 class="mb-0 text-success" id="profit">-</h4>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i>Summary
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-pilgrim-tab" data-toggle="tab" href="#details-pilgrim" role="tab">
                            <i class="feather icon-user mr-2"></i>Pilgrim Details
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Pilgrim Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Full Name</span>
                                            <strong id="pilgrim-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Passport Number</span>
                                            <strong id="passport-number">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Relation</span>
                                            <strong id="relation">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Gender</span>
                                            <strong id="gender">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Branch</span>
                                            <strong id="branch-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Created By</span>
                                            <strong id="created-by">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Booking Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Entry Date</span>
                                            <strong id="entry-date">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Flight Date</span>
                                            <strong id="flight-date">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Return Date</span>
                                            <strong id="return-date">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Duration</span>
                                            <strong id="duration">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Sold To</span>
                                            <strong id="sold-to">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Status</span>
                                            <strong id="booking-status">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pilgrim Details Tab -->
                    <div class="tab-pane fade" id="details-pilgrim" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Family & Additional Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Head of Family</span>
                                            <strong id="head-of-family">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Province</span>
                                            <strong id="province">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">District</span>
                                            <strong id="district">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Contact</span>
                                            <strong id="contact">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Room Type</span>
                                            <strong id="room-type">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Paid Amount</span>
                                            <strong id="paid-amount">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Due Amount</span>
                                            <strong id="due-amount">-</strong>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Remarks</span>
                                    <strong id="remarks">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>

<script>
// Handle search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchValue = document.getElementById('searchInput').value.trim();
    const branchValue = document.getElementById('branchFilter').value;

    let url = '?branch=' + branchValue;
    if (searchValue) {
        url += '&search=' + encodeURIComponent(searchValue);
    }

    window.location.href = url;
});

// Handle branch filter change
document.getElementById('branchFilter').addEventListener('change', function() {
    const branchValue = this.value;
    const searchValue = document.getElementById('searchInput').value.trim();

    let url = '?branch=' + branchValue;
    if (searchValue) {
        url += '&search=' + encodeURIComponent(searchValue);
    }

    window.location.href = url;
});

// Handle enter key in search input
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchBtn').click();
    }
});

// Handle view details modal
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const bookingData = JSON.parse(this.getAttribute('data-booking'));

        // Populate modal with booking data
        document.getElementById('sold-price').textContent = bookingData.currency + ' ' + parseFloat(bookingData.sold_price || 0).toFixed(2);
        document.getElementById('base-price').textContent = bookingData.currency + ' ' + parseFloat(bookingData.price || 0).toFixed(2);
        document.getElementById('profit').textContent = bookingData.currency + ' ' + parseFloat(bookingData.profit || 0).toFixed(2);

        document.getElementById('pilgrim-name').textContent = bookingData.name + ' ' + bookingData.fname + ' ' + bookingData.gfname;
        document.getElementById('passport-number').textContent = bookingData.passport_number || 'N/A';
        document.getElementById('relation').textContent = bookingData.relation || 'N/A';
        document.getElementById('gender').textContent = bookingData.gender || 'N/A';
        document.getElementById('branch-name').textContent = bookingData.branch_name || 'No Branch';
        document.getElementById('created-by').textContent = bookingData.created_by_name || 'N/A';

        document.getElementById('entry-date').textContent = bookingData.entry_date || 'N/A';
        document.getElementById('flight-date').textContent = bookingData.flight_date || 'N/A';
        document.getElementById('return-date').textContent = bookingData.return_date || 'N/A';
        document.getElementById('duration').textContent = bookingData.duration || 'N/A';
        document.getElementById('sold-to').textContent = bookingData.sold_to || 'N/A';
        document.getElementById('booking-status').textContent = bookingData.status || 'N/A';

        document.getElementById('head-of-family').textContent = bookingData.head_of_family || 'Individual';
        document.getElementById('province').textContent = bookingData.province || 'N/A';
        document.getElementById('district').textContent = bookingData.district || 'N/A';
        document.getElementById('contact').textContent = bookingData.contact || 'N/A';
        document.getElementById('room-type').textContent = bookingData.room_type || 'N/A';
        document.getElementById('currency').textContent = bookingData.currency || 'N/A';
        document.getElementById('paid-amount').textContent = bookingData.currency + ' ' + parseFloat(bookingData.paid || 0).toFixed(2);
        document.getElementById('due-amount').textContent = bookingData.currency + ' ' + parseFloat(bookingData.due || 0).toFixed(2);
        document.getElementById('remarks').textContent = bookingData.remarks || 'No remarks';

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>