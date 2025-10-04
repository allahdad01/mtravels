<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to manage_demo_requests.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/conn.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_demo_requests.php?error=invalid_csrf');
        exit();
    }

    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'];

    $valid_statuses = ['pending', 'contacted', 'scheduled', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        header('Location: manage_demo_requests.php?error=invalid_status');
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE demo_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $new_status, $request_id);
        $stmt->execute();
        $stmt->close();

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_demo_request_status', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['new_status' => $new_status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $user_id, $request_id, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_demo_requests.php?success=status_updated');
        exit();
    } catch (Exception $e) {
        error_log("Error updating demo request status: " . $e->getMessage());
        header('Location: manage_demo_requests.php?error=update_failed');
        exit();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_demo_requests.php?error=invalid_csrf');
        exit();
    }

    $request_id = intval($_POST['request_id']);

    try {
        $stmt = $conn->prepare("DELETE FROM demo_requests WHERE id = ?");
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $stmt->close();

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'delete_demo_request', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['action' => 'deleted']);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $user_id, $request_id, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_demo_requests.php?success=request_deleted');
        exit();
    } catch (Exception $e) {
        error_log("Error deleting demo request: " . $e->getMessage());
        header('Location: manage_demo_requests.php?error=delete_failed');
        exit();
    }
}

// Fetch demo requests with optional filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM demo_requests WHERE 1=1";
$params = [];
$types = '';

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$demo_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status counts for summary
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM demo_requests GROUP BY status");
$stmt->execute();
$status_counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$status_summary = array_column($status_counts, 'count', 'status');
?>

<?php include '../includes/header_super_admin.php'; ?>

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
                                    <h5 class="m-b-10">Demo Requests Management</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Demo Requests</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->

                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="feather icon-check-circle"></i>
                                <?php
                                switch ($_GET['success']) {
                                    case 'status_updated': echo 'Demo request status updated successfully!'; break;
                                    case 'request_deleted': echo 'Demo request deleted successfully!'; break;
                                    default: echo 'Operation completed successfully!';
                                }
                                ?>
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="feather icon-alert-circle"></i>
                                <?php
                                switch ($_GET['error']) {
                                    case 'invalid_csrf': echo 'Security validation failed. Please try again.'; break;
                                    case 'invalid_status': echo 'Invalid status selected.'; break;
                                    case 'update_failed': echo 'Failed to update request status.'; break;
                                    case 'delete_failed': echo 'Failed to delete request.'; break;
                                    default: echo 'An error occurred. Please try again.';
                                }
                                ?>
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        <?php endif; ?>

                        <!-- Status Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-blue-500">
                                    <div class="flex items-center">
                                        <div class="bg-blue-100 text-blue-600 rounded-full p-2">
                                            <i class="feather icon-clock text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= $status_summary['pending'] ?? 0 ?></h5>
                                            <span class="text-gray-600 text-sm">Pending</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-yellow-500">
                                    <div class="flex items-center">
                                        <div class="bg-yellow-100 text-yellow-600 rounded-full p-2">
                                            <i class="feather icon-phone text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= $status_summary['contacted'] ?? 0 ?></h5>
                                            <span class="text-gray-600 text-sm">Contacted</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-purple-500">
                                    <div class="flex items-center">
                                        <div class="bg-purple-100 text-purple-600 rounded-full p-2">
                                            <i class="feather icon-calendar text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= $status_summary['scheduled'] ?? 0 ?></h5>
                                            <span class="text-gray-600 text-sm">Scheduled</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-green-500">
                                    <div class="flex items-center">
                                        <div class="bg-green-100 text-green-600 rounded-full p-2">
                                            <i class="feather icon-check-circle text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= $status_summary['completed'] ?? 0 ?></h5>
                                            <span class="text-gray-600 text-sm">Completed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-red-500">
                                    <div class="flex items-center">
                                        <div class="bg-red-100 text-red-600 rounded-full p-2">
                                            <i class="feather icon-x-circle text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= $status_summary['cancelled'] ?? 0 ?></h5>
                                            <span class="text-gray-600 text-sm">Cancelled</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <div class="card statustic-card bg-white shadow-md rounded-lg p-4 border-l-4 border-gray-500">
                                    <div class="flex items-center">
                                        <div class="bg-gray-100 text-gray-600 rounded-full p-2">
                                            <i class="feather icon-bar-chart text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-xl font-semibold text-gray-800"><?= array_sum($status_summary) ?></h5>
                                            <span class="text-gray-600 text-sm">Total</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters and Search -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <form method="GET" action="manage_demo_requests.php" class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="status">Filter by Status</label>
                                                    <select class="form-control" id="status" name="status">
                                                        <option value="">All Statuses</option>
                                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="contacted" <?= $status_filter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                                                        <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="search">Search</label>
                                                    <input type="text" class="form-control" id="search" name="search"
                                                           value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, or company">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>&nbsp;</label>
                                                    <div>
                                                        <button type="submit" class="btn btn-primary btn-block">
                                                            <i class="feather icon-search mr-2"></i>Filter
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Demo Requests Table -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card shadow-lg border-0">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0"><i class="feather icon-users mr-2"></i>Demo Requests</h4>
                                        <span class="badge badge-pill badge-info"><?= count($demo_requests) ?> requests</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Contact Info</th>
                                                        <th>Company</th>
                                                        <th>Preferred Schedule</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($demo_requests)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                                            <p class="text-muted">No demo requests found</p>
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($demo_requests as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($request['name']) ?></h6>
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-mail mr-1"></i><?= htmlspecialchars($request['email']) ?>
                                                                    </small>
                                                                    <?php if ($request['phone']): ?>
                                                                    <br><small class="text-muted">
                                                                        <i class="feather icon-phone mr-1"></i><?= htmlspecialchars($request['phone']) ?>
                                                                    </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong><?= htmlspecialchars($request['company']) ?></strong>
                                                                <?php if ($request['company_size']): ?>
                                                                <br><small class="text-muted">Size: <?= htmlspecialchars($request['company_size']) ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($request['preferred_date']): ?>
                                                            <div>
                                                                <i class="feather icon-calendar mr-1"></i>
                                                                <?= date('M d, Y', strtotime($request['preferred_date'])) ?>
                                                                <?php if ($request['preferred_time']): ?>
                                                                <br><small class="text-muted">
                                                                    <i class="feather icon-clock mr-1"></i><?= date('H:i', strtotime($request['preferred_time'])) ?>
                                                                </small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php else: ?>
                                                            <span class="text-muted">No preference</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?= getStatusBadgeClass($request['status']) ?>">
                                                                <?= ucfirst(htmlspecialchars($request['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <?= date('M d, Y', strtotime($request['created_at'])) ?>
                                                                <br><small class="text-muted">
                                                                    <?= date('H:i A', strtotime($request['created_at'])) ?>
                                                                </small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-sm btn-outline-primary" onclick="viewRequestDetails(<?= $request['id'] ?>)">
                                                                    <i class="feather icon-eye"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-secondary" onclick="updateStatus(<?= $request['id'] ?>, '<?= $request['status'] ?>')">
                                                                    <i class="feather icon-edit-2"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRequest(<?= $request['id'] ?>)">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" role="dialog" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewRequestModalLabel">
                    <i class="feather icon-eye mr-2"></i>Demo Request Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="updateStatusModalLabel">
                    <i class="feather icon-edit-2 mr-2"></i>Update Request Status
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="request_id" id="updateRequestId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="statusSelect">New Status</label>
                        <select class="form-control" id="statusSelect" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="contacted">Contacted</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="feather icon-check-circle mr-1"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Request Modal -->
<div class="modal fade" id="deleteRequestModal" tabindex="-1" role="dialog" aria-labelledby="deleteRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteRequestModalLabel">
                    <i class="feather icon-trash-2 mr-2"></i>Delete Demo Request
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="delete_request" value="1">
                <input type="hidden" name="request_id" id="deleteRequestId">
                <div class="modal-body">
                    <p>Are you sure you want to delete this demo request? This action cannot be undone.</p>
                    <div id="deleteRequestInfo"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="feather icon-trash-2 mr-1"></i>Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'warning',
        'contacted': 'info',
        'scheduled': 'primary',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return classes[status] || 'secondary';
}

function viewRequestDetails(requestId) {
    // Load request details
    fetch(`get_demo_request_details.php?id=${requestId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('requestDetailsContent').innerHTML = data;
            $('#viewRequestModal').modal('show');
        })
        .catch(error => {
            console.error('Error loading request details:', error);
            alert('Error loading request details. Please try again.');
        });
}

function updateStatus(requestId, currentStatus) {
    document.getElementById('updateRequestId').value = requestId;
    document.getElementById('statusSelect').value = currentStatus;
    $('#updateStatusModal').modal('show');
}

function deleteRequest(requestId) {
    // Load basic request info for confirmation
    fetch(`get_demo_request_details.php?id=${requestId}&basic=1`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('deleteRequestId').value = requestId;
            document.getElementById('deleteRequestInfo').innerHTML = data;
            $('#deleteRequestModal').modal('show');
        })
        .catch(error => {
            console.error('Error loading request info:', error);
            document.getElementById('deleteRequestId').value = requestId;
            document.getElementById('deleteRequestInfo').innerHTML = '<p class="text-muted">Unable to load request details.</p>';
            $('#deleteRequestModal').modal('show');
        });
}
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>

<?php
function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'warning',
        'contacted' => 'info',
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}
?>