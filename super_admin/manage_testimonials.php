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
    error_log("Unauthorized access attempt to super admin testimonials: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Handle POST requests for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_testimonial':
                // Handle file upload for photo
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    }

                    $filename = uniqid('testimonial_') . '.' . $file_extension;
                    $photo_path = $upload_dir . $filename;

                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                        throw new Exception('Failed to upload photo');
                    }

                    // Store relative path
                    $photo_path = 'uploads/testimonials/' . $filename;
                }

                $stmt = $pdo->prepare("INSERT INTO testimonials (tenant_id, name, photo, testimonial, destination, rating, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$_POST['tenant_id'], $_POST['name'], $photo_path, $_POST['testimonial'], $_POST['destination'], $_POST['rating']]);

                // Log audit
                logAudit($pdo, $_SESSION['user_id'], 'create_testimonial', 'testimonials', $pdo->lastInsertId(), 'Created new testimonial for ' . $_POST['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial added successfully']);
                break;

            case 'update_testimonial':
                $testimonial_id = $_POST['testimonial_id'];

                // Handle photo update
                $photo_path = $_POST['existing_photo'] ?? null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    }

                    $filename = uniqid('testimonial_') . '.' . $file_extension;
                    $new_photo_path = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $new_photo_path)) {
                        // Delete old photo if exists
                        if ($photo_path && file_exists('../' . $photo_path)) {
                            unlink('../' . $photo_path);
                        }
                        $photo_path = 'uploads/testimonials/' . $filename;
                    }
                }

                $stmt = $pdo->prepare("UPDATE testimonials SET name = ?, photo = ?, testimonial = ?, destination = ?, rating = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$_POST['name'], $photo_path, $_POST['testimonial'], $_POST['destination'], $_POST['rating'], $testimonial_id]);

                // Log audit
                logAudit($pdo, $_SESSION['user_id'], 'update_testimonial', 'testimonials', $testimonial_id, 'Updated testimonial for ' . $_POST['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial updated successfully']);
                break;

            case 'delete_testimonial':
                $testimonial_id = $_POST['testimonial_id'];

                // Get testimonial data for cleanup
                $stmt = $pdo->prepare("SELECT name, photo FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonial_id]);
                $testimonial = $stmt->fetch();

                // Delete photo file if exists
                if ($testimonial['photo'] && file_exists('../' . $testimonial['photo'])) {
                    unlink('../' . $testimonial['photo']);
                }

                // Delete testimonial
                $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonial_id]);

                // Log audit
                logAudit($pdo, $_SESSION['user_id'], 'delete_testimonial', 'testimonials', $testimonial_id, 'Deleted testimonial from ' . $testimonial['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial deleted successfully']);
                break;

            case 'toggle_status':
                $testimonial_id = $_POST['testimonial_id'];
                $active = $_POST['active'];

                $stmt = $pdo->prepare("UPDATE testimonials SET active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$active, $testimonial_id]);

                // Log audit
                logAudit($pdo, $_SESSION['user_id'], 'toggle_testimonial_status', 'testimonials', $testimonial_id, 'Changed testimonial status to ' . ($active ? 'active' : 'inactive'));

                echo json_encode(['success' => true, 'message' => 'Testimonial status updated successfully']);
                break;
        }
    } catch (Exception $e) {
        error_log("Testimonial operation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

// Count total items
$count_query = "SELECT COUNT(*) as total FROM testimonials t WHERE 1=1";
$filter_params = [];
if (!empty($search_query)) {
    $count_query .= " AND (t.name LIKE ? OR t.destination LIKE ? OR t.testimonial LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated testimonials
$query = "
    SELECT t.*, tn.name as tenant_name
    FROM testimonials t
    LEFT JOIN tenants tn ON t.tenant_id = tn.id
    WHERE 1=1";
if (!empty($search_query)) {
    $query .= " AND (t.name LIKE ? OR t.destination LIKE ? OR t.testimonial LIKE ?)";
}
$query .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
$params = $filter_params;
$params[] = $items_per_page;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

// Fetch tenants for dropdown
$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$stmt->execute();
$tenants = $stmt->fetchAll();

// Audit logging function
function logAudit($pdo, $user_id, $action, $entity_type, $entity_id, $details) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $entity_type, $entity_id, $details]);
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container dark:bg-gray-900">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10 text-2xl font-semibold text-gray-800 dark:text-gray-100">Manage Testimonials</h5>
                                </div>
                                <ul class="breadcrumb flex space-x-2 text-sm text-gray-500 dark:text-gray-400">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Super Admin</a></li>
                                    <li class="breadcrumb-item"><a href="#!">Testimonials</a></li>
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
                            <div class="col-sm-12">
                                <!-- Action Buttons -->
                                <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6">
                                    <div class="flex justify-between items-center">
                                        <h6 class="text-lg font-semibold text-gray-800 dark:text-white">Testimonials Management</h6>
                                        <button class="btn btn-primary flex items-center px-4 py-2 rounded-lg" data-toggle="modal" data-target="#addTestimonialModal">
                                            <i class="feather icon-plus mr-2"></i>Add New Testimonial
                                        </button>
                                    </div>
                                </div>

                                <!-- Testimonials Table -->
                                 <div class="card bg-white dark:bg-gray-800 shadow-md rounded-lg">
                                     <div class="card-header border-b pb-4">
                                         <h5 class="text-lg font-semibold text-gray-800 dark:text-white">All Testimonials <span class="badge badge-info"><?= $total_items ?> total</span></h5>
                                     </div>
                                     <div class="card-body">
                                         <div class="mb-3">
                                             <form method="GET" action="manage_testimonials.php" class="form-inline">
                                                 <input type="text" class="form-control mr-2" name="search" placeholder="Name, destination, testimonial..." value="<?= htmlspecialchars($search_query) ?>" style="width: 300px;">
                                                 <button type="submit" class="btn btn-primary mr-2">Search</button>
                                                 <?php if (!empty($search_query)): ?>
                                                 <a href="manage_testimonials.php" class="btn btn-secondary">Clear</a>
                                                 <?php endif; ?>
                                             </form>
                                         </div>
                                         <div class="table-responsive">
                                            <table id="testimonialsTable" class="table table-striped table-bordered">
                                                <thead class="bg-gray-50 dark:bg-gray-700">
                                                    <tr>
                                                        <th>S.N.</th>
                                                        <th>Customer Name</th>
                                                        <th>Tenant</th>
                                                        <th>Testimonial</th>
                                                        <th>Rating</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($testimonials as $index => $testimonial): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <div class="flex items-center">
                                                                <?php if ($testimonial['photo']): ?>
                                                                    <img src="../<?php echo htmlspecialchars($testimonial['photo']); ?>" alt="Photo" class="w-8 h-8 rounded-full mr-3">
                                                                <?php else: ?>
                                                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                                        <span class="text-gray-600 font-semibold"><?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?></span>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($testimonial['name']); ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($testimonial['tenant_name'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($testimonial['testimonial']); ?>">
                                                                <?php echo htmlspecialchars(substr($testimonial['testimonial'], 0, 50)) . (strlen($testimonial['testimonial']) > 50 ? '...' : ''); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="flex">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $testimonial['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> rounded-full px-3 py-1 text-xs font-medium">
                                                                <?php echo $testimonial['active'] ? 'Active' : 'Inactive'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></td>
                                                        <td>
                                                            <div class="flex space-x-2">
                                                                <button class="btn btn-sm btn-outline-primary edit-testimonial" data-id="<?php echo $testimonial['id']; ?>" title="Edit">
                                                                    <i class="feather icon-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm <?php echo $testimonial['active'] ? 'btn-outline-warning' : 'btn-outline-success'; ?> toggle-status"
                                                                        data-id="<?php echo $testimonial['id']; ?>"
                                                                        data-active="<?php echo $testimonial['active']; ?>"
                                                                        title="<?php echo $testimonial['active'] ? 'Deactivate' : 'Activate'; ?>">
                                                                    <i class="feather <?php echo $testimonial['active'] ? 'icon-eye-off' : 'icon-eye'; ?>"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-testimonial" data-id="<?php echo $testimonial['id']; ?>" data-name="<?php echo htmlspecialchars($testimonial['name']); ?>" title="Delete">
                                                                    <i class="feather icon-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                </table>
                                                </div>
                                                
                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                <nav aria-label="Page navigation" class="mt-3">
                                                <ul class="pagination justify-content-center">
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Previous</a>
                                                </li>
                                                <?php 
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                if ($start_page > 1): ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                                </li>
                                                <?php endfor; ?>
                                                <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                                </li>
                                                <?php endif; ?>
                                                <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Next</a>
                                                </li>
                                                </ul>
                                                </nav>
                                                <div class="text-center text-muted small mt-2">
                                                Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($testimonials) ?> of <?= $total_items ?> testimonials
                                                </div>
                                                <?php endif; ?>
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

<!-- Add Testimonial Modal -->
<div class="modal fade" id="addTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="addTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="add_testimonial">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div class="modal-header bg-blue-500 text-white border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-plus mr-2"></i>Add New Testimonial
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Tenant *</label>
                            <select name="tenant_id" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Customer Name *</label>
                            <input type="text" name="name" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Destination</label>
                            <input type="text" name="destination" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g., Dubai, Tokyo">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Photo</label>
                            <input type="file" name="photo" class="form-control w-full h-12 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" accept="image/*">
                            <small class="text-gray-500 dark:text-gray-400">Optional. JPG, PNG, GIF only. Max 5MB.</small>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Rating *</label>
                            <select name="rating" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Testimonial *</label>
                            <textarea name="testimonial" rows="4" class="form-control w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Testimonial</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Testimonial Modal -->
<div class="modal fade" id="editTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="editTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="update_testimonial">
            <input type="hidden" name="testimonial_id" id="edit_testimonial_id">
            <input type="hidden" name="existing_photo" id="edit_existing_photo">
            <div class="modal-content bg-white dark:bg-gray-800 shadow-lg rounded-lg">
                <div class="modal-header bg-blue-500 text-white border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-edit mr-2"></i>Edit Testimonial
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Tenant *</label>
                            <select name="tenant_id" id="edit_tenant_id" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Customer Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Destination</label>
                            <input type="text" name="destination" id="edit_destination" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g., Dubai, Tokyo">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Photo</label>
                            <input type="file" name="photo" class="form-control w-full h-12 px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" accept="image/*">
                            <small class="text-gray-500 dark:text-gray-400">Leave empty to keep current photo. JPG, PNG, GIF only. Max 5MB.</small>
                            <div id="current_photo_container" class="mt-2 hidden">
                                <img id="current_photo" src="" alt="Current Photo" class="w-16 h-16 rounded-full object-cover">
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Rating *</label>
                            <select name="rating" id="edit_rating" class="form-control w-full h-12 px-3 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Testimonial *</label>
                            <textarea name="testimonial" id="edit_testimonial" rows="4" class="form-control w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-gray-100 dark:bg-gray-700 border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Testimonial</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#testimonialsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "language": {
            "search": "Search testimonials:",
            "lengthMenu": "Show _MENU_ testimonials per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ testimonials"
        }
    });
});

// Handle form submissions
$('#addTestimonialForm, #editTestimonialForm').on('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    $.ajax({
        url: 'manage_testimonials.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: result.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while processing your request.'
            });
        }
    });
});

// Edit testimonial
$('.edit-testimonial').on('click', function() {
    const testimonialId = $(this).data('id');

    // Fetch testimonial data
    $.ajax({
        url: 'get_testimonial_details.php',
        type: 'GET',
        data: { id: testimonialId },
        success: function(response) {
            const testimonial = JSON.parse(response);

            $('#edit_testimonial_id').val(testimonial.id);
            $('#edit_tenant_id').val(testimonial.tenant_id);
            $('#edit_name').val(testimonial.name);
            $('#edit_destination').val(testimonial.destination || '');
            $('#edit_rating').val(testimonial.rating);
            $('#edit_testimonial').val(testimonial.testimonial);
            $('#edit_existing_photo').val(testimonial.photo || '');

            if (testimonial.photo) {
                $('#current_photo').attr('src', '../' + testimonial.photo);
                $('#current_photo_container').removeClass('hidden');
            } else {
                $('#current_photo_container').addClass('hidden');
            }

            $('#editTestimonialModal').modal('show');
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load testimonial data.'
            });
        }
    });
});

// Toggle testimonial status
$('.toggle-status').on('click', function() {
    const testimonialId = $(this).data('id');
    const currentActive = $(this).data('active');
    const newActive = currentActive ? 0 : 1;
    const actionText = newActive ? 'activate' : 'deactivate';

    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to ${actionText} this testimonial?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: newActive ? '#28a745' : '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Yes, ${actionText} it!`
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'manage_testimonials.php',
                type: 'POST',
                data: {
                    action: 'toggle_status',
                    testimonial_id: testimonialId,
                    active: newActive,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while updating the testimonial status.'
                    });
                }
            });
        }
    });
});

// Delete testimonial
$('.delete-testimonial').on('click', function() {
    const testimonialId = $(this).data('id');
    const customerName = $(this).data('name');

    Swal.fire({
        title: 'Are you sure?',
        text: `Do you want to delete the testimonial from ${customerName}? This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'manage_testimonials.php',
                type: 'POST',
                data: {
                    action: 'delete_testimonial',
                    testimonial_id: testimonialId,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: result.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while deleting the testimonial.'
                    });
                }
            });
        }
    });
});
</script>

</body>
</html>
