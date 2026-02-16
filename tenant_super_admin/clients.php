<?php
include 'header.php';

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

// Build query for clients
$query = "SELECT
    c.*,
    COUNT(ct.id) as transaction_count,
    COALESCE(SUM(CASE WHEN ct.type = 'credit' THEN ct.amount ELSE 0 END), 0) as total_credits,
    COALESCE(SUM(CASE WHEN ct.type = 'debit' THEN ct.amount ELSE 0 END), 0) as total_debits
FROM clients c
LEFT JOIN client_transactions ct ON c.id = ct.client_id
WHERE c.tenant_id = ? AND c.status = 'active'";

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
}

// Group by client and add ordering and pagination
$query .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

if (!empty($search)) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM clients WHERE tenant_id = ? AND status = 'active'";
$count_params = [$tenant_id];

if (!empty($search)) {
    $count_query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_clients = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_clients / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_clients,
    SUM(usd_balance) as total_usd_balance,
    SUM(afs_balance) as total_afs_balance,
    SUM(CASE WHEN usd_balance > 0 THEN usd_balance ELSE 0 END) as positive_usd_balance,
    SUM(CASE WHEN afs_balance > 0 THEN afs_balance ELSE 0 END) as positive_afs_balance,
    SUM(CASE WHEN usd_balance < 0 THEN ABS(usd_balance) ELSE 0 END) as negative_usd_balance,
    SUM(CASE WHEN afs_balance < 0 THEN ABS(afs_balance) ELSE 0 END) as negative_afs_balance
FROM clients
WHERE tenant_id = ? AND status = 'active'";

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$tenant_id]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <!-- Page Header Card -->
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="feather icon-users mr-2"></i>Clients</h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage and view all your clients</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i>Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Summary Cards Column -->
                                <div class="col-md-3">
                                    <div class="card mb-3">
                                        <div class="card-body text-center">
                                            <div class="h2 font-weight-bold text-primary">
                                                <i class="feather icon-users mr-2"></i><?php echo number_format($summary['total_clients'] ?? 0); ?>
                                            </div>
                                            <p class="text-muted mb-0">Total Clients</p>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-body text-center">
                                            <div class="h4 mb-1 font-weight-bold text-success">
                                                $<?php echo number_format($summary['positive_usd_balance'] ?? 0, 2); ?>
                                            </div>
                                            <small class="text-muted"><i class="feather icon-dollar-sign mr-1"></i>Positive USD Balance</small>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-body text-center">
                                            <div class="h4 mb-1 font-weight-bold text-info">
                                                ؋<?php echo number_format($summary['positive_afs_balance'] ?? 0, 2); ?>
                                            </div>
                                            <small class="text-muted"><i class="feather icon-money-bill-wave mr-1"></i>Positive AFS Balance</small>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-body text-center">
                                            <div class="h4 mb-1 font-weight-bold text-danger">
                                                $<?php echo number_format(($summary['negative_usd_balance'] ?? 0) + ($summary['negative_afs_balance'] ?? 0), 2); ?>
                                            </div>
                                            <small class="text-muted"><i class="feather icon-alert-triangle mr-1"></i>Outstanding Balances</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Main Content Column -->
                                <div class="col-md-9">
                                    <!-- Search Section -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="search-box">
                                                <div class="input-group">
                                                    <input type="text" id="searchInput" class="form-control" placeholder="Search by client name, email, or phone" value="<?php echo htmlspecialchars($search); ?>">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-primary" type="button" id="searchBtn">
                                                            <i class="feather icon-search"></i> Search
                                                        </button>
                                                        <?php if (!empty($search)): ?>
                                                        <a href="?" class="btn btn-secondary">
                                                            <i class="feather icon-x"></i> Clear
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Clients Table Section -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-list mr-2"></i>Clients List</h5>
                                        </div>
                                        <div class="card-body table-responsive p-0">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Client Info</th>
                                                        <th>Contact Details</th>
                                                        <th>USD Balance</th>
                                                        <th>AFS Balance</th>
                                                        <th>Activity</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="clientTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($clients as $client):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?php echo $counter++; ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-client='<?php echo htmlspecialchars(json_encode($client)); ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                    <button class="dropdown-item view-transactions" data-client-id="<?php echo $client['id']; ?>" data-client-name="<?php echo htmlspecialchars($client['name']); ?>">
                                                                        <i class="feather icon-list text-info mr-2"></i> View Transactions
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="client-info">
                                                                <div class="client-info__details">
                                                                    <div class="client-info__name">
                                                                        <strong><?php echo htmlspecialchars($client['name']); ?></strong>
                                                                    </div>
                                                                    <div class="client-info__type">
                                                                        <span class="badge badge-<?php echo $client['client_type'] === 'agency' ? 'primary' : 'secondary'; ?>">
                                                                            <?php echo ucfirst(htmlspecialchars($client['client_type'])); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="contact-info">
                                                                <div class="contact-info__details">
                                                                    <?php if (!empty($client['phone'])): ?>
                                                                    <div class="contact-info__phone">
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?php echo htmlspecialchars($client['phone']); ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($client['email'])): ?>
                                                                    <div class="contact-info__email">
                                                                        <i class="feather icon-mail mr-1"></i>
                                                                        <?php echo htmlspecialchars($client['email']); ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (empty($client['phone']) && empty($client['email'])): ?>
                                                                    <div class="text-muted">
                                                                        <em>No contact info</em>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="balance-info">
                                                                <div class="balance-info__amount">
                                                                    <span class="text-<?php echo $client['usd_balance'] >= 0 ? 'success' : 'danger'; ?>">
                                                                        <strong>
                                                                            $<?php echo number_format($client['usd_balance'], 2); ?>
                                                                        </strong>
                                                                    </span>
                                                                </div>
                                                                <div class="balance-info__status">
                                                                    <small class="text-muted">
                                                                        <?php echo $client['usd_balance'] >= 0 ? 'Credit' : 'Debit'; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="balance-info">
                                                                <div class="balance-info__amount">
                                                                    <span class="text-<?php echo $client['afs_balance'] >= 0 ? 'info' : 'danger'; ?>">
                                                                        <strong>
                                                                            ؋<?php echo number_format($client['afs_balance'], 2); ?>
                                                                        </strong>
                                                                    </span>
                                                                </div>
                                                                <div class="balance-info__status">
                                                                    <small class="text-muted">
                                                                        <?php echo $client['afs_balance'] >= 0 ? 'Credit' : 'Debit'; ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="activity-info">
                                                                <div class="activity-info__transactions">
                                                                    <i class="feather icon-activity text-muted mr-1"></i>
                                                                    <?php echo number_format($client['transaction_count']); ?> transactions
                                                                </div>
                                                                <div class="activity-info__summary">
                                                                    <small class="text-muted">
                                                                        Credits: $<?php echo number_format($client['total_credits'], 2); ?><br>
                                                                        Debits: $<?php echo number_format($client['total_debits'], 2); ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-<?php echo $client['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo ucfirst(htmlspecialchars($client['status'])); ?>
                                                            </span>
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
                                                    Showing <?php echo min(($page - 1) * $results_per_page + 1, $total_clients); ?> to <?php echo min($page * $results_per_page, $total_clients); ?> of <?php echo $total_clients; ?> clients
                                                </div>
                                                <nav aria-label="Page navigation">
                                                    <ul class="pagination mb-0">
                                                        <?php if ($page > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">
                                                                    <i class="feather icon-chevrons-left"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                                                    <i class="feather icon-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start_page = max(1, $page - 2);
                                                        $end_page = min($total_pages, $page + 2);

                                                        if ($start_page > 1) {
                                                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '">1</a></li>';
                                                            if ($start_page > 2) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                        }

                                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '">' . $i . '</a>
                                                            </li>';
                                                        }

                                                        if ($end_page < $total_pages) {
                                                            if ($end_page < $total_pages - 1) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '">' . $total_pages . '</a></li>';
                                                        }
                                                        ?>

                                                        <?php if ($page < $total_pages): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                                                    <i class="feather icon-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>">
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

<!-- Client Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i>Client Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="balance-summary">
                                <div class="balance-summary__label">USD Balance</div>
                                <div class="balance-summary__amount" id="usd-balance">-</div>
                                <div class="balance-summary__status" id="usd-balance-status">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="balance-summary">
                                <div class="balance-summary__label">AFS Balance</div>
                                <div class="balance-summary__amount" id="afs-balance">-</div>
                                <div class="balance-summary__status" id="afs-balance-status">-</div>
                            </div>
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
                        <a class="nav-link" id="details-contact-tab" data-toggle="tab" href="#details-contact" role="tab">
                            <i class="feather icon-phone mr-2"></i>Contact Info
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
                                            <span class="text-muted">Client Name</span>
                                            <strong id="client-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Type</span>
                                            <strong id="client-type">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Status</span>
                                            <strong id="client-status">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Created At</span>
                                            <strong id="created-at">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Financial Summary</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Credits</span>
                                            <strong class="text-success" id="total-credits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Debits</span>
                                            <strong class="text-danger" id="total-debits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Net Position</span>
                                            <strong id="net-position">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info Tab -->
                    <div class="tab-pane fade" id="details-contact" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Contact Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Phone</span>
                                    <strong id="contact-phone">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Email</span>
                                    <strong id="contact-email">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Address</span>
                                    <strong id="contact-address">-</strong>
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

<!-- Transactions Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-list mr-2"></i>Client Transactions - <span id="client-name-header"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="transactionsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading transactions...</p>
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

<style>
/* Enhanced custom styles matching request_user_addon.php */
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

.badge-primary {
    background-color: #007bff;
}

.badge-secondary {
    background-color: #6c757d;
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

.pagination .page-link {
    border-radius: 50%;
    margin: 0 2px;
    border: none;
    color: #495057;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.pagination .page-link:hover {
    background-color: #e9ecef;
}

/* Text colors */
.text-primary { color: #007bff !important; }
.text-success { color: #28a745 !important; }
.text-info { color: #17a2b8 !important; }
.text-danger { color: #dc3545 !important; }
.text-muted { color: #6c757d !important; }
</style>

<script>
// Handle search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchValue = document.getElementById('searchInput').value.trim();

    let url = '?';
    if (searchValue) {
        url += 'search=' + encodeURIComponent(searchValue);
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
        const clientData = JSON.parse(this.getAttribute('data-client'));

        // Populate modal with client data
        document.getElementById('usd-balance').textContent = '$' + parseFloat(clientData.usd_balance || 0).toFixed(2);
        document.getElementById('usd-balance-status').textContent = (clientData.usd_balance >= 0 ? 'Credit balance' : 'Debit balance');
        document.getElementById('afs-balance').textContent = '؋' + parseFloat(clientData.afs_balance || 0).toFixed(2);
        document.getElementById('afs-balance-status').textContent = (clientData.afs_balance >= 0 ? 'Credit balance' : 'Debit balance');

        document.getElementById('client-name').textContent = clientData.name;
        document.getElementById('client-type').textContent = clientData.client_type.charAt(0).toUpperCase() + clientData.client_type.slice(1);
        document.getElementById('client-status').textContent = clientData.status.charAt(0).toUpperCase() + clientData.status.slice(1);
        document.getElementById('created-at').textContent = clientData.created_at || 'N/A';

        document.getElementById('total-credits').textContent = '$' + parseFloat(clientData.total_credits || 0).toFixed(2);
        document.getElementById('total-debits').textContent = '$' + parseFloat(clientData.total_debits || 0).toFixed(2);
        const netPosition = parseFloat(clientData.total_credits || 0) - parseFloat(clientData.total_debits || 0);
        document.getElementById('net-position').innerHTML = (netPosition >= 0 ? '<span class="text-success">+$' : '<span class="text-danger">-$') + Math.abs(netPosition).toFixed(2) + '</span>';

        document.getElementById('contact-phone').textContent = clientData.phone || 'N/A';
        document.getElementById('contact-email').textContent = clientData.email || 'N/A';
        document.getElementById('contact-address').textContent = clientData.address || 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view transactions modal
document.querySelectorAll('.view-transactions').forEach(button => {
    button.addEventListener('click', function() {
        const clientId = this.getAttribute('data-client-id');
        const clientName = this.getAttribute('data-client-name');

        document.getElementById('client-name-header').textContent = clientName;

        // Load transactions via AJAX
        fetch('get_client_transactions.php?client_id=' + clientId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('transactionsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('transactionsContent').innerHTML = '<div class="alert alert-danger">Error loading transactions: ' + error.message + '</div>';
            });

        // Show modal
        $('#transactionsModal').modal('show');
    });
});
</script>

<?php include 'footer.php'; ?>
