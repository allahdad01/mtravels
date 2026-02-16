<?php
include 'header.php';

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

// Build query for refunded tickets
$query = "SELECT
    rt.*,
    u.name as created_by_name,
    b.name as branch_name,
    tb.passenger_name,
    tb.pnr,
    tb.airline,
    tb.origin,
    tb.destination,
    tb.title,
    tb.trip_type,
    tb.return_date,
    tb.return_destination
FROM refunded_tickets rt
LEFT JOIN users u ON rt.created_by = u.id
LEFT JOIN branches b ON rt.branch_id = b.id
LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
WHERE rt.tenant_id = ?";

// Add branch filter
if ($branch_filter !== 'all') {
    $query .= " AND rt.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (tb.passenger_name LIKE ? OR tb.pnr LIKE ? OR tb.airline LIKE ? OR tb.origin LIKE ? OR tb.destination LIKE ?)";
}

// Add ordering and pagination
$query .= " ORDER BY rt.created_at DESC LIMIT ? OFFSET ?";

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
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM refunded_tickets rt
                LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
                WHERE rt.tenant_id = ?";
$count_params = [$tenant_id];

if ($branch_filter !== 'all') {
    $count_query .= " AND rt.branch_id = ?";
    $count_params[] = $branch_filter;
}

if (!empty($search)) {
    $count_query .= " AND (tb.passenger_name LIKE ? OR tb.pnr LIKE ? OR tb.airline LIKE ? OR tb.origin LIKE ? OR tb.destination LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_tickets = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_tickets / $results_per_page);

// Get branches for filter dropdown
$branches_query = "SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name";
$branches_stmt = $pdo->prepare($branches_query);
$branches_stmt->execute([$tenant_id]);
$branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-12">
                                        <h5 class="mb-0"><i class="feather icon-refresh-ccw mr-2"></i>Refunded Tickets</h5>
                                    </div>
                                </div>
                            </div>



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
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by passenger name, PNR, airline, or route" value="<?= htmlspecialchars($search) ?>">
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

                                <!-- Tickets Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Passenger Info</th>
                                                        <th>Flight Details</th>
                                                        <th>Refund Info</th>
                                                        <th>Branch</th>
                                                        <th class="text-right">Refund Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="ticketTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($tickets as $ticket):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="passenger-info">
                                                                <div class="passenger-info__details">
                                                                    <div class="passenger-info__name">
                                                                        <?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?>
                                                                    </div>
                                                                    <div class="passenger-info__pnr">
                                                                        PNR: <?= htmlspecialchars($ticket['pnr']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="flight-info">
                                                                <div class="flight-info__segment">
                                                                    <div class="flight-info__city">
                                                                        <?= htmlspecialchars($ticket['origin']) ?> - <?= htmlspecialchars($ticket['destination']) ?>
                                                                    </div>
                                                                    <?php if ($ticket['trip_type'] === 'round_trip'): ?>
                                                                        <div class="flight-info__city mt-2">
                                                                            <?= htmlspecialchars($ticket['destination']) ?> - <?= htmlspecialchars($ticket['return_destination']) ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="flight-info__airline">
                                                                        <?= htmlspecialchars($ticket['airline']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="refund-info">
                                                                <div class="refund-info__date">
                                                                    <i class="feather icon-calendar text-muted mr-1"></i>
                                                                    <?= htmlspecialchars($ticket['created_at']) ?>
                                                                </div>
                                                                <div class="refund-info__status">
                                                                    <span class="badge badge-<?= $ticket['status'] === 'Refunded' ? 'success' : 'warning' ?>">
                                                                        <?= htmlspecialchars($ticket['status']) ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-info">
                                                                <?= htmlspecialchars($ticket['branch_name'] ?: 'No Branch') ?>
                                                            </span>
                                                        </td>

                                                        <td class="text-right">
                                                            <div class="refund-amount">
                                                                <div class="refund-amount__value text-danger">
                                                                    <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['refund_to_passenger'], 2) ?>
                                                                </div>
                                                                <div class="refund-amount__penalty text-warning">
                                                                    <small>
                                                                        Penalty: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?>
                                                                    </small>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_tickets) ?> to <?= min($page * $results_per_page, $total_tickets) ?> of <?= $total_tickets ?> refunded tickets
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
</div>

<!-- Ticket Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-refresh-ccw mr-2"></i>Refunded Ticket Details
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
                            <div class="small text-muted mb-1">Refund Amount</div>
                            <h4 class="mb-0 text-danger" id="refund-amount">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Total Penalties</div>
                            <h4 class="mb-0 text-warning" id="total-penalties">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Original Price</div>
                            <h4 class="mb-0 text-info" id="original-price">-</h4>
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
                        <a class="nav-link" id="details-refund-tab" data-toggle="tab" href="#details-refund" role="tab">
                            <i class="feather icon-refresh-ccw mr-2"></i>Refund Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Client Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Passenger Name</span>
                                            <strong id="passenger-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">PNR</span>
                                            <strong id="pnr">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Supplier</span>
                                            <strong id="supplier-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Sold To</span>
                                            <strong id="sold-to">-</strong>
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
                                        <h6 class="card-subtitle mb-3 text-muted">Flight Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Airline</span>
                                            <strong id="airline">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Route</span>
                                            <strong id="route">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Departure Date</span>
                                            <strong id="departure-date">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Issue Date</span>
                                            <strong id="issue-date">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refund Details Tab -->
                    <div class="tab-pane fade" id="details-refund" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Refund Breakdown</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Original Sold Price</span>
                                    <strong id="original-sold">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Supplier Penalty</span>
                                    <strong class="text-danger" id="supplier-penalty">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Service Penalty</span>
                                    <strong class="text-danger" id="service-penalty">-</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted"><strong>Refund to Passenger</strong></span>
                                    <strong class="text-success" id="refund-to-passenger">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Status</span>
                                    <strong id="refund-status">-</strong>
                                </div>
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

<?php include 'footer.php'; ?>

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
        const ticketData = JSON.parse(this.getAttribute('data-ticket'));

        // Populate modal with ticket data
        const refundAmount = parseFloat(ticketData.refund_to_passenger || 0);
        const supplierPenalty = parseFloat(ticketData.supplier_penalty || 0);
        const servicePenalty = parseFloat(ticketData.service_penalty || 0);
        const totalPenalties = supplierPenalty + servicePenalty;
        const originalPrice = refundAmount + totalPenalties;

        document.getElementById('refund-amount').textContent = ticketData.currency + ' ' + refundAmount.toFixed(2);
        document.getElementById('total-penalties').textContent = ticketData.currency + ' ' + totalPenalties.toFixed(2);
        document.getElementById('original-price').textContent = ticketData.currency + ' ' + originalPrice.toFixed(2);

        document.getElementById('passenger-name').textContent = ticketData.title + ' ' + ticketData.passenger_name;
        document.getElementById('pnr').textContent = ticketData.pnr;
        document.getElementById('supplier-name').textContent = ticketData.supplier || 'N/A';
        document.getElementById('sold-to').textContent = ticketData.sold_to || 'N/A';
        document.getElementById('branch-name').textContent = ticketData.branch_name || 'No Branch';
        document.getElementById('created-by').textContent = ticketData.created_by_name || 'N/A';

        document.getElementById('airline').textContent = ticketData.airline || 'N/A';
        document.getElementById('route').textContent = (ticketData.origin || '') + ' - ' + (ticketData.destination || '');
        document.getElementById('departure-date').textContent = ticketData.departure_date || 'N/A';
        document.getElementById('issue-date').textContent = ticketData.issue_date || 'N/A';

        document.getElementById('original-sold').textContent = ticketData.currency + ' ' + originalPrice.toFixed(2);
        document.getElementById('supplier-penalty').textContent = ticketData.currency + ' ' + supplierPenalty.toFixed(2);
        document.getElementById('service-penalty').textContent = ticketData.currency + ' ' + servicePenalty.toFixed(2);
        document.getElementById('refund-to-passenger').textContent = ticketData.currency + ' ' + refundAmount.toFixed(2);
        document.getElementById('refund-status').textContent = ticketData.status || 'N/A';
        document.getElementById('remarks').textContent = ticketData.remarks || 'No remarks';

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>