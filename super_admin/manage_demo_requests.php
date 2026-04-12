<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
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
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

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
        $stmt = $pdo->prepare("UPDATE demo_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $request_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_demo_request_status', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['new_status' => $new_status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $request_id, $details, $ip_address]);
        header('Location: manage_demo_requests.php?success=status_updated');
        exit();
    } catch (Exception $e) {
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
        $stmt = $pdo->prepare("DELETE FROM demo_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'delete_demo_request', 'demo_request', ?, ?, ?, NOW())");
        $details = json_encode(['action' => 'deleted']);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $request_id, $details, $ip_address]);
        header('Location: manage_demo_requests.php?success=request_deleted');
        exit();
    } catch (Exception $e) {
        header('Location: manage_demo_requests.php?error=delete_failed');
        exit();
    }
}

// Pagination and filters
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Count total
$count_query = "SELECT COUNT(*) as total FROM demo_requests WHERE 1=1";
$filter_params = [];
if ($status_filter) {
    $count_query .= " AND status = ?";
    $filter_params[] = $status_filter;
}
if ($search) {
    $count_query .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
    $search_param = "%$search%";
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
}
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated requests
$query = "SELECT * FROM demo_requests WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$demo_requests = $stmt->fetchAll();

// Get status counts for summary
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM demo_requests GROUP BY status");
$stmt->execute();
$status_counts = $stmt->fetchAll();
$status_summary = array_column($status_counts, 'count', 'status');
$total_requests = array_sum($status_summary);
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">

                            <!-- Page Header -->
                            <div class="sa-page-header">
                                <div class="sph-content">
                                    <div class="sph-icon">
                                        <i class="feather icon-monitor"></i>
                                    </div>
                                    <div class="sph-text">
                                        <h1><?php echo __('demo_requests'); ?></h1>
                                        <p><?php echo __('manage_demo_requests'); ?></p>
                                    </div>
                                </div>
                                <div class="sph-actions">
                                    <span class="sa-header-stats">
                                        <i class="feather icon-users"></i>
                                        <strong><?php echo $total_requests; ?></strong> <?php echo __('total_requests'); ?>
                                    </span>
                                </div>
                            </div>

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
                                <div class="col-md-2">
                                    <div class="sa-summary-card sa-summary-card-primary">
                                        <div class="ssc-icon">
                                            <i class="feather icon-clock"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Pending</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $status_summary['pending'] ?? 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="sa-summary-card" style="border-left: 4px solid #f59e0b;">
                                        <div class="ssc-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                                            <i class="feather icon-phone"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Contacted</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $status_summary['contacted'] ?? 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="sa-summary-card" style="border-left: 4px solid #8b5cf6;">
                                        <div class="ssc-icon" style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                                            <i class="feather icon-calendar"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Scheduled</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $status_summary['scheduled'] ?? 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="sa-summary-card sa-summary-card-success">
                                        <div class="ssc-icon">
                                            <i class="feather icon-check-circle"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Completed</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $status_summary['completed'] ?? 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="sa-summary-card" style="border-left: 4px solid #ef4444;">
                                        <div class="ssc-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                                            <i class="feather icon-x-circle"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Cancelled</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $status_summary['cancelled'] ?? 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="sa-summary-card sa-summary-card-warning">
                                        <div class="ssc-icon">
                                            <i class="feather icon-bar-chart"></i>
                                        </div>
                                        <div class="ssc-content">
                                            <span class="ssc-label">Total</span>
                                            <div class="ssc-value ssc-value-large"><?php echo $total_requests; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filters and Search -->
                            <div class="sa-card" style="margin-bottom: 20px;">
                                <div class="sa-card-body">
                                    <form method="GET" action="manage_demo_requests.php" class="sa-search-filter">
                                        <div class="sa-search-group">
                                            <div>
                                                <label class="sa-search-label">Search</label>
                                                <input type="text" class="sa-search-input" name="search" 
                                                       value="<?php echo htmlspecialchars($search); ?>" 
                                                       placeholder="Name, email, or company">
                                            </div>
                                            <div>
                                                <label class="sa-search-label">Status</label>
                                                <select class="sa-search-input" name="status">
                                                    <option value="">All Statuses</option>
                                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="contacted" <?php echo $status_filter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                            <div class="sa-filter-actions">
                                                <button type="submit" class="sa-btn sa-btn-primary">
                                                    <i class="feather icon-search"></i> Filter
                                                </button>
                                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                                <a href="manage_demo_requests.php" class="sa-btn sa-btn-ghost">
                                                    <i class="feather icon-refresh-ccw"></i> Reset
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Demo Requests Cards -->
                            <div class="sa-shdr" style="margin-bottom: 16px;">
                                <div>
                                    <h2>Demo Requests</h2>
                                    <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);"><?php echo count($demo_requests); ?> of <?php echo $total_items; ?> requests</p>
                                </div>
                            </div>

                            <?php if (empty($demo_requests)): ?>
                            <div class="sa-card">
                                <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                    <div style="font-size: 2rem; margin-bottom: 12px;">📋</div>
                                    <div style="font-weight: 600; margin-bottom: 4px;">No demo requests found</div>
                                    <div style="font-size: 0.8rem;">Try adjusting your search or filter criteria.</div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="sa-request-list">
                                <?php foreach ($demo_requests as $request): ?>
                                <div class="sa-request-card">
                                    <div class="src-header">
                                        <div class="src-info">
                                            <h4><?php echo htmlspecialchars($request['name']); ?></h4>
                                            <p class="src-company">
                                                <i class="feather icon-briefcase"></i>
                                                <?php echo htmlspecialchars($request['company']); ?>
                                                <?php if ($request['company_size']): ?>
                                                <span class="src-size">(<?php echo htmlspecialchars($request['company_size']); ?>)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="src-status">
                                            <span class="pill <?php echo getStatusPillClass($request['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="src-details">
                                        <div class="src-detail-item">
                                            <span class="src-detail-label">Email</span>
                                            <span class="src-detail-value">
                                                <i class="feather icon-mail"></i>
                                                <?php echo htmlspecialchars($request['email']); ?>
                                            </span>
                                        </div>
                                        <?php if ($request['phone']): ?>
                                        <div class="src-detail-item">
                                            <span class="src-detail-label">Phone</span>
                                            <span class="src-detail-value">
                                                <i class="feather icon-phone"></i>
                                                <?php echo htmlspecialchars($request['phone']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <div class="src-detail-item">
                                            <span class="src-detail-label">Schedule</span>
                                            <span class="src-detail-value">
                                                <i class="feather icon-calendar"></i>
                                                <?php if ($request['preferred_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($request['preferred_date'])); ?>
                                                    <?php if ($request['preferred_time']): ?>
                                                        at <?php echo date('H:i', strtotime($request['preferred_time'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    No preference
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="src-detail-item">
                                            <span class="src-detail-label">Created</span>
                                            <span class="src-detail-value">
                                                <i class="feather icon-clock"></i>
                                                <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="src-actions">
                                        <button class="sa-btn sa-btn-small sa-btn-info" onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                            <i class="feather icon-eye"></i> View
                                        </button>
                                        <button class="sa-btn sa-btn-small" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: white;" onclick="updateStatus(<?php echo $request['id']; ?>, '<?php echo $request['status']; ?>')">
                                            <i class="feather icon-edit-2"></i> Update
                                        </button>
                                        <button class="sa-btn sa-btn-small sa-btn-danger" onclick="deleteRequest(<?php echo $request['id']; ?>)">
                                            <i class="feather icon-trash-2"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="p-3">
                                <nav aria-label="Page navigation" class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $current_page === 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Previous</a>
                                    </li>
                                    <?php 
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    if ($start_page > 1): ?>
                                    <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                    <?php endif; ?>
                                    <li class="page-item <?php echo $current_page === $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Next</a>
                                    </li>
                                </ul>
                                </nav>
                                <div class="text-center text-muted small mt-2">
                                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> | Showing <?php echo count($demo_requests); ?> of <?php echo $total_items; ?> requests
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Details Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
            <div class="sa-modal-header">
                <div class="sa-modal-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="feather icon-eye"></i>
                </div>
                <div class="sa-modal-title">
                    <h3>Demo Request Details</h3>
                    <p>View complete request information</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body sa-modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
            <div class="sa-modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);">
                <div class="sa-modal-icon" style="background: rgba(255,255,255,0.2);">
                    <i class="feather icon-edit-2"></i>
                </div>
                <div class="sa-modal-title">
                    <h3>Update Request Status</h3>
                    <p>Change the status of this request</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="request_id" id="updateRequestId">
                <div class="modal-body sa-modal-body">
                    <div class="sa-form-group">
                        <label class="sa-form-label">New Status</label>
                        <select class="sa-form-input" id="statusSelect" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="contacted">Contacted</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-warning">
                        <i class="feather icon-check-circle"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Request Modal -->
<div class="modal fade" id="deleteRequestModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
            <div class="sa-modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);">
                <div class="sa-modal-icon" style="background: rgba(255,255,255,0.2);">
                    <i class="feather icon-trash-2"></i>
                </div>
                <div class="sa-modal-title">
                    <h3>Delete Demo Request</h3>
                    <p>This action cannot be undone</p>
                </div>
                <button type="button" class="sa-modal-close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="delete_request" value="1">
                <input type="hidden" name="request_id" id="deleteRequestId">
                <div class="modal-body sa-modal-body">
                    <div class="sa-alert sa-alert-warning">
                        <i class="feather icon-alert-triangle"></i>
                        <div>
                            <strong>Warning!</strong>
                            <p>Are you sure you want to delete this demo request? This action cannot be undone.</p>
                        </div>
                    </div>
                    <div id="deleteRequestInfo"></div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-danger">
                        <i class="feather icon-trash-2"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* ─── ROOT VARIABLES ─────────────────────────────────── */
    :root {
        --muted: #999;
        --surface: #ffffff;
        --surface2: #f5f5f5;
        --border: #e0e0e0;
        --text: #333333;
        --green: #28a745;
        --red: #dc3545;
    }

    /* ─── PAGE HEADER ─────────────────────────────────── */
    .sa-page-header {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-radius: 16px;
        padding: 24px 28px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 20px rgba(64, 153, 255, 0.35);
    }

    .sa-page-header .sph-content {
        display: flex;
        align-items: center;
        gap: 18px;
    }

    .sa-page-header .sph-icon {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
        backdrop-filter: blur(4px);
    }

    .sa-page-header .sph-text h1 {
        margin: 0 0 4px 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: white;
        letter-spacing: -0.02em;
    }

    .sa-page-header .sph-text p {
        margin: 0;
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.85);
    }

    .sa-page-header .sph-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sa-header-stats {
        background: rgba(255, 255, 255, 0.2);
        padding: 10px 18px;
        border-radius: 10px;
        color: white;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sa-header-stats i {
        font-size: 1.1rem;
    }

    .sa-header-stats strong {
        font-size: 1.2rem;
    }

    @media (max-width: 768px) {
        .sa-page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }
        
        .sa-page-header .sph-actions {
            justify-content: center;
        }
    }

    /* ─── SUMMARY CARDS ──────────────────────────────── */
    .sa-summary-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #eee;
        height: 100%;
    }

    .sa-summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .sa-summary-card .ssc-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .sa-summary-card .ssc-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sa-summary-card .ssc-label {
        font-size: 0.75rem;
        color: #888;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .sa-summary-card .ssc-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #333;
    }

    .sa-summary-card .ssc-value-large {
        font-size: 2rem;
    }

    .sa-summary-card-primary {
        border-left: 4px solid #667eea;
    }

    .sa-summary-card-primary .ssc-icon {
        background: rgba(102, 126, 234, 0.12);
        color: #667eea;
    }

    .sa-summary-card-success {
        border-left: 4px solid #28a745;
    }

    .sa-summary-card-success .ssc-icon {
        background: rgba(40, 167, 69, 0.12);
        color: #28a745;
    }

    .sa-summary-card-warning {
        border-left: 4px solid #ffc107;
    }

    .sa-summary-card-warning .ssc-icon {
        background: rgba(255, 193, 7, 0.12);
        color: #d99e00;
    }

    /* ─── CARDS ──────────────────────────────────────── */
    .sa-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .sa-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .sa-card-body {
        padding: 1.5rem;
    }

    /* ─── SECTION HEADER ───────────────────────────────── */
    .sa-shdr {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .sa-shdr h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
        color: #333;
    }

    .sa-shdr p {
        margin: 4px 0 0 0;
        font-size: 0.75rem;
        color: #999;
    }

    /* ─── BUTTONS ──────────────────────────────────── */
    .sa-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        font-size: 0.9rem;
    }

    .sa-btn-small {
        padding: 6px 12px;
        font-size: 0.75rem;
    }

    .sa-btn-info {
        background: linear-gradient(135deg, #11cdef 0%, #2dd4bf 100%);
        color: white;
    }

    .sa-btn-info:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(17, 207, 239, 0.3);
    }

    .sa-btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
        color: white;
    }

    .sa-btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .d-flex {
        display: flex;
    }

    .gap-2 {
        gap: 8px;
    }

    /* ─── SEARCH & FILTER ────────────────────────────── */
    .sa-search-filter {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .sa-search-group {
        display: flex;
        gap: 1rem;
        align-items: flex-end;
        flex: 1;
        flex-wrap: wrap;
    }

    .sa-search-group > div {
        flex: 1;
        min-width: 120px;
    }

    .sa-search-label {
        font-size: 0.75rem;
        color: #666;
        font-weight: 600;
        margin-bottom: 4px;
        display: block;
    }

    .sa-search-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .sa-search-input:focus {
        outline: none;
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .sa-filter-actions {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .sa-btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
    }

    .sa-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .sa-btn-ghost {
        background: #f0f0f0;
        color: #333;
        border: 1px solid #e0e0e0;
    }

    .sa-btn-ghost:hover {
        background: #e8e8e8;
        border-color: #d0d0d0;
    }

    /* ─── PILLS ────────────────────────────────────────── */
    .pill {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 20px;
        text-transform: capitalize;
    }

    .pill-pending {
        background: rgba(245, 158, 11, 0.12);
        color: #f59e0b;
    }

    .pill-contacted {
        background: rgba(59, 130, 246, 0.12);
        color: #3b82f6;
    }

    .pill-scheduled {
        background: rgba(139, 92, 246, 0.12);
        color: #8b5cf6;
    }

    .pill-completed {
        background: rgba(16, 185, 129, 0.12);
        color: #10b981;
    }

    .pill-cancelled {
        background: rgba(239, 68, 68, 0.12);
        color: #ef4444;
    }

    /* ─── TABLE STYLES ─────────────────────────────────── */
    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table td {
        vertical-align: middle;
    }

    /* ─── REQUEST CARDS ──────────────────────────────── */
    .sa-request-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .sa-request-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.2s ease;
    }

    .sa-request-card:hover {
        border-color: rgba(64, 153, 255, 0.3);
        box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
    }

    .src-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e0e0e0;
    }

    .src-info h4 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 6px 0;
        color: #333;
    }

    .src-company {
        font-size: 0.9rem;
        color: #666;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .src-company i {
        font-size: 0.85rem;
    }

    .src-size {
        color: #999;
        font-size: 0.8rem;
    }

    .src-status {
        flex-shrink: 0;
    }

    .src-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .src-detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .src-detail-label {
        font-size: 0.7rem;
        color: #999;
        font-weight: 600;
        text-transform: uppercase;
    }

    .src-detail-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: #444;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .src-detail-value i {
        font-size: 0.85rem;
        color: #888;
    }

    .src-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    /* ─── MODAL STYLES ───────────────────────────────── */
    .sa-modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        position: relative;
    }

    .sa-modal-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        flex-shrink: 0;
    }

    .sa-modal-title {
        flex: 1;
    }

    .sa-modal-title h3 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: white;
    }

    .sa-modal-title p {
        margin: 4px 0 0 0;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .sa-modal-close {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .sa-modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .sa-modal-body {
        padding: 24px;
    }

    .sa-modal-footer {
        padding: 16px 24px;
        background: #f8f9fa;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    .sa-form-group {
        margin-bottom: 16px;
    }

    .sa-form-label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .sa-form-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }

    .sa-form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .sa-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border-radius: 10px;
        margin-bottom: 16px;
    }

    .sa-alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .sa-alert-warning i {
        color: #f59e0b;
        font-size: 1.2rem;
        margin-top: 2px;
    }

    .sa-alert-warning strong {
        display: block;
        color: #b45309;
        margin-bottom: 4px;
    }

    .sa-alert-warning p {
        margin: 0;
        color: #92400e;
        font-size: 0.9rem;
    }

    .sa-btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
        color: white;
    }

    .sa-btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    /* ─── RESPONSIVE ────────────────────────────────── */
    @media (max-width: 768px) {
        .sa-summary-card {
            flex-direction: column;
            align-items: stretch;
        }
        
        .sa-summary-card .ssc-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
        
        .sa-summary-card .ssc-value {
            font-size: 1.1rem;
        }
        
        .sa-summary-card .ssc-value-large {
            font-size: 1.5rem;
        }
        
        .src-header {
            flex-direction: column;
            gap: 12px;
        }
        
        .src-details {
            grid-template-columns: 1fr;
        }
        
        .src-actions {
            width: 100%;
            justify-content: stretch;
        }
        
        .src-actions .sa-btn {
            flex: 1;
            text-align: center;
        }
    }
</style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function getStatusPillClass(status) {
    const classes = {
        'pending': 'pill-pending',
        'contacted': 'pill-contacted',
        'scheduled': 'pill-scheduled',
        'completed': 'pill-completed',
        'cancelled': 'pill-cancelled'
    };
    return classes[status] || 'pill-pending';
}

function viewRequestDetails(requestId) {
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

<?php
function getStatusPillClass($status) {
    $classes = [
        'pending' => 'pill-pending',
        'contacted' => 'pill-contacted',
        'scheduled' => 'pill-scheduled',
        'completed' => 'pill-completed',
        'cancelled' => 'pill-cancelled'
    ];
    return $classes[$status] ?? 'pill-pending';
}
?>
