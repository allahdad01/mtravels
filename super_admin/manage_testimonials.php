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
require_once 'includes/file_upload_security.php';

// Handle POST requests for CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token - use hash_equals to prevent timing attacks
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_testimonial':
                // Handle file upload for photo with MIME validation
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Validate file using FileUploadSecurity
                    $validation = FileUploadSecurity::validateUpload($_FILES['photo'], 'image', 5242880);
                    
                    if (!$validation['success']) {
                        throw new Exception($validation['error']);
                    }

                    // Move file using secure method
                    $moveResult = FileUploadSecurity::moveUploadedFile(
                        $_FILES['photo']['tmp_name'],
                        $upload_dir,
                        $validation['safe_name']
                    );

                    if (!$moveResult['success']) {
                        throw new Exception($moveResult['error']);
                    }

                    // Store relative path
                    $photo_path = 'uploads/testimonials/' . $validation['safe_name'];
                }

                $stmt = $pdo->prepare("INSERT INTO testimonials (tenant_id, name, photo, testimonial, position, rating, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$_POST['tenant_id'], $_POST['name'], $photo_path, $_POST['testimonial'], $_POST['position'], $_POST['rating']]);

                // Log audit
                logAudit($pdo, $_SESSION['user_id'], 'create_testimonial', 'testimonials', $pdo->lastInsertId(), 'Created new testimonial for ' . $_POST['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial added successfully']);
                break;

            case 'update_testimonial':
                $testimonial_id = $_POST['testimonial_id'];

                // Handle photo update with MIME validation
                $photo_path = $_POST['existing_photo'] ?? null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Validate file using FileUploadSecurity
                    $validation = FileUploadSecurity::validateUpload($_FILES['photo'], 'image', 5242880);
                    
                    if (!$validation['success']) {
                        throw new Exception($validation['error']);
                    }

                    // Move file using secure method
                    $moveResult = FileUploadSecurity::moveUploadedFile(
                        $_FILES['photo']['tmp_name'],
                        $upload_dir,
                        $validation['safe_name']
                    );

                    if ($moveResult['success']) {
                        // Delete old photo if exists
                        if ($photo_path && file_exists('../' . $photo_path)) {
                            @unlink('../' . $photo_path);
                        }
                        $photo_path = 'uploads/testimonials/' . $validation['safe_name'];
                    } else {
                        throw new Exception($moveResult['error']);
                    }
                }

                $stmt = $pdo->prepare("UPDATE testimonials SET name = ?, photo = ?, testimonial = ?, position = ?, rating = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$_POST['name'], $photo_path, $_POST['testimonial'], $_POST['position'], $_POST['rating'], $testimonial_id]);

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
    $count_query .= " AND (t.name LIKE ? OR t.position LIKE ? OR t.testimonial LIKE ?)";
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
    $query .= " AND (t.name LIKE ? OR t.position LIKE ? OR t.testimonial LIKE ?)";
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $entity_type, $entity_id, $details, $ip]);
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:       #f5f3ff;
  --surface:  #ffffff;
  --surface2: #ede9fe;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --primary:  #7C3AED;
  --primary-light: #a78bfa;
  --cta:      #f97316;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --radius:   12px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Fira Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }
.sa-content { padding: 24px 28px; display: flex; flex-direction: column; gap: 20px; }

.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
.stat-card {
  background: var(--surface);
  border-radius: var(--radius);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  border: 1px solid var(--border);
  transition: all .2s;
}
.stat-card:hover {
  box-shadow: 0 4px 12px rgba(124,58,237,0.08);
  border-color: var(--primary-light);
}
.stat-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}
.stat-icon.total { background: rgba(124,58,237,0.12); color: var(--primary); }
.stat-icon.active-count { background: rgba(16,185,129,0.12); color: var(--green); }
.stat-icon.inactive-count { background: rgba(245,158,11,0.12); color: var(--amber); }
.stat-info h4 {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text);
  line-height: 1.2;
}
.stat-info p {
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 200px;
  max-width: 400px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0 12px;
  transition: all .2s;
}
.search-box:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
}
.search-box i {
  color: var(--muted);
  font-size: 0.9rem;
}
.search-box input {
  border: none;
  background: transparent;
  padding: 10px 0;
  font-size: 0.85rem;
  font-family: 'Fira Sans', sans-serif;
  color: var(--text);
  outline: none;
  width: 100%;
}
.search-box input::placeholder { color: var(--muted); }

.btn {
  font-size: .8rem; font-weight: 600; font-family: 'Fira Sans', sans-serif;
  padding: 10px 18px; border-radius: 8px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.btn:active { transform: scale(0.97); }
.btn-primary {
  background: var(--primary); color: #fff;
}
.btn-primary:hover { background: #6d28d9; }
.btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.btn-ghost:hover { color: var(--text); border-color: var(--primary-light); }
.btn-sm { padding: 6px 12px; font-size: .75rem; }
.btn-success { background: var(--green); color: white; }
.btn-warning { background: var(--amber); color: white; }
.btn-danger { background: var(--red); color: white; }
.btn-cta {
  background: linear-gradient(135deg, var(--primary), #6d28d9);
  color: #fff;
  box-shadow: 0 2px 8px rgba(124,58,237,0.25);
}
.btn-cta:hover { box-shadow: 0 4px 14px rgba(124,58,237,0.35); transform: translateY(-1px); }

.testimonial-entry {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px;
  margin-bottom: 14px;
  display: flex;
  gap: 18px;
  transition: all .2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}
.testimonial-entry:last-child { margin-bottom: 0; }
.testimonial-entry:hover {
  border-color: var(--primary-light);
  box-shadow: 0 4px 16px rgba(124,58,237,0.08);
}
.testimonial-entry.inactive {
  border-left: 3px solid var(--amber);
}
.testimonial-entry.active {
  border-left: 3px solid var(--green);
}

.testimonial-avatar {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 600; font-size: 1.1rem;
  flex-shrink: 0; overflow: hidden;
}
.testimonial-avatar img {
  width: 100%; height: 100%; object-fit: cover;
}

.testimonial-body { flex: 1; min-width: 0; }

.testimonial-row {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; flex-wrap: wrap; margin-bottom: 8px;
}
.testimonial-person {
  display: flex; flex-direction: column; gap: 2px;
}
.testimonial-name {
  font-size: 0.95rem; font-weight: 600; color: var(--text);
}
.testimonial-position {
  font-size: 0.78rem; color: var(--muted);
}
.testimonial-meta {
  display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
  font-size: 0.73rem; color: var(--muted);
}
.testimonial-meta span {
  display: flex; align-items: center; gap: 4px;
}
.testimonial-rating {
  color: var(--amber);
  font-size: 0.8rem;
  letter-spacing: 2px;
}
.testimonial-text {
  font-size: 0.83rem;
  color: #4b5563;
  line-height: 1.6;
  margin: 8px 0 12px;
  font-style: italic;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.testimonial-entry:hover .testimonial-text {
  -webkit-line-clamp: unset;
}

.testimonial-actions {
  display: flex; gap: 6px;
}
.action-btn {
  width: 32px; height: 32px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all .15s;
  color: var(--muted); font-size: 0.85rem;
}
.action-btn:hover {
  border-color: var(--primary-light);
  color: var(--primary);
  background: var(--surface2);
}
.action-btn.delete:hover {
  border-color: var(--red);
  color: var(--red);
  background: #fef2f2;
}

.status-badge {
  font-size: 0.65rem; font-weight: 600;
  padding: 3px 10px; border-radius: 20px;
  text-transform: uppercase; letter-spacing: 0.04em;
}
.status-badge.active { background: rgba(16,185,129,0.12); color: #059669; }
.status-badge.inactive { background: rgba(245,158,11,0.12); color: #d97706; }

.pagination-wrap {
  display: flex; flex-direction: column; align-items: center;
  gap: 12px; margin-top: 24px; padding-top: 20px;
  border-top: 1px solid var(--border);
}
.pagination {
  display: flex; gap: 4px; list-style: none;
  flex-wrap: wrap; justify-content: center;
}
.page-link {
  padding: 8px 13px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 500;
  font-family: 'Fira Code', monospace;
  transition: all .15s;
}
.page-link:hover {
  border-color: var(--primary-light);
  color: var(--primary);
  background: var(--surface2);
}
.page-item.active .page-link {
  background: var(--primary);
  border-color: var(--primary);
  color: white;
}
.page-item.disabled .page-link {
  opacity: 0.4;
  cursor: not-allowed;
  pointer-events: none;
}
.pagination-info {
  font-size: 0.73rem;
  color: var(--muted);
}

.empty-state {
  text-align: center; padding: 56px 24px; color: var(--muted);
}
.empty-state-icon {
  font-size: 3rem; margin-bottom: 12px; opacity: 0.4;
}
.empty-state-text { font-size: 0.9rem; }

.form-group { position: relative; }
.form-label {
  display: block; font-weight: 600;
  color: var(--text); margin-bottom: 6px; font-size: 0.8rem;
}
.form-control {
  width: 100%; padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 8px; font-size: 0.85rem;
  transition: all .15s ease;
  background: #f9fafb;
  color: var(--text);
  font-family: 'Fira Sans', sans-serif;
}
.form-control:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(124,58,237,0.12);
  background: var(--surface);
}
select.form-control {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 36px;
}

.modal-glass .modal-content {
  border: none;
  border-radius: 16px;
  overflow: hidden;
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 8px 32px rgba(124,58,237,0.12);
}
.modal-glass .modal-header {
  background: linear-gradient(135deg, var(--primary), #6d28d9);
  border: none; padding: 20px 24px;
}
.modal-glass .modal-header .close { opacity: 0.8; }
.modal-glass .modal-header .close span { color: white; font-size: 1.5rem; }
.modal-glass .modal-title { color: white; font-weight: 600; }
.modal-glass .modal-body { padding: 24px; }
.modal-glass .modal-footer {
  border-top: 1px solid var(--border);
  padding: 16px 24px;
}

::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #ddd6fe; border-radius: 10px; }

body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }

@media (max-width: 768px) {
  .sa-content { padding: 16px; }
  .stats-row { grid-template-columns: 1fr; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .search-box { max-width: 100%; }
  .testimonial-entry { flex-direction: column; }
  .testimonial-avatar { width: 44px; height: 44px; font-size: 0.95rem; }
  .testimonial-row { flex-direction: column; }
  .testimonial-actions { width: 100%; justify-content: flex-end; }
  .page-link { padding: 6px 10px; font-size: 0.7rem; }
}
</style>

                <!-- [ Main Content ] start -->
                <div class="pcoded-main-container">
                    <div class="pcoded-wrapper">
                        <div class="pcoded-content">
                            <div class="pcoded-inner-content">
                                <div class="page-header">
                                    <div class="page-block">
                                        <div class="row align-items-center">
                                            <div class="col-md-12">
                                                <div class="page-header-title">
                                                    <h5 class="m-b-10">Manage Testimonials</h5>
                                                </div>
                                                <ul class="breadcrumb">
                                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                                    <li class="breadcrumb-item"><a href="#!">Super Admin</a></li>
                                                    <li class="breadcrumb-item"><a href="#!">Testimonials</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-wrap">
                                    <div class="sa-content">

                                        <?php
                                        $active_count = 0;
                                        $inactive_count = 0;
                                        foreach ($testimonials as $t) {
                                            if ($t['active']) $active_count++; else $inactive_count++;
                                        }
                                        ?>
                                        <div class="stats-row">
                                            <div class="stat-card">
                                                <div class="stat-icon total"><i class="feather icon-message-square"></i></div>
                                                <div class="stat-info">
                                                    <h4><?= $total_items ?></h4>
                                                    <p>Total Testimonials</p>
                                                </div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-icon active-count"><i class="feather icon-check-circle"></i></div>
                                                <div class="stat-info">
                                                    <h4><?= $active_count ?></h4>
                                                    <p>Active</p>
                                                </div>
                                            </div>
                                            <div class="stat-card">
                                                <div class="stat-icon inactive-count"><i class="feather icon-x-circle"></i></div>
                                                <div class="stat-info">
                                                    <h4><?= $inactive_count ?></h4>
                                                    <p>Inactive</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="toolbar">
                                            <form method="GET" action="manage_testimonials.php" style="display:flex;align-items:center;gap:8px;flex:1;flex-wrap:wrap">
                                                <div class="search-box">
                                                    <i class="feather icon-search"></i>
                                                    <input type="text" name="search" placeholder="Search name, position, testimonial..." value="<?= htmlspecialchars($search_query) ?>">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm"><i class="feather icon-search"></i> Search</button>
                                                <?php if (!empty($search_query)): ?>
                                                <a href="manage_testimonials.php" class="btn btn-ghost btn-sm"><i class="feather icon-x"></i> Clear</a>
                                                <?php endif; ?>
                                            </form>
                                            <button class="btn btn-cta" data-toggle="modal" data-target="#addTestimonialModal">
                                                <i class="feather icon-plus"></i> Add Testimonial
                                            </button>
                                        </div>

                                        <?php if (!empty($testimonials)): ?>
                                            <?php foreach ($testimonials as $testimonial): 
                                                $initial = strtoupper(substr($testimonial['name'], 0, 1));
                                                $stars = '';
                                                for($i = 1; $i <= 5; $i++) {
                                                    $stars .= $i <= $testimonial['rating'] ? '★' : '☆';
                                                }
                                            ?>
                                            <div class="testimonial-entry <?= $testimonial['active'] ? 'active' : 'inactive' ?>">
                                                <div class="testimonial-avatar">
                                                    <?php if ($testimonial['photo']): ?>
                                                        <img src="../<?= htmlspecialchars($testimonial['photo']) ?>" alt="<?= htmlspecialchars($testimonial['name']) ?>">
                                                    <?php else: ?>
                                                        <?= $initial ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="testimonial-body">
                                                    <div class="testimonial-row">
                                                        <div class="testimonial-person">
                                                            <div class="testimonial-name"><?= htmlspecialchars($testimonial['name']) ?></div>
                                                            <div class="testimonial-position">
                                                                <i class="feather icon-briefcase" style="margin-right:3px"></i><?= htmlspecialchars($testimonial['position'] ?: 'No position') ?>
                                                            </div>
                                                        </div>
                                                        <div style="display:flex;align-items:center;gap:10px">
                                                            <span class="testimonial-rating"><?= $stars ?></span>
                                                            <span class="status-badge <?= $testimonial['active'] ? 'active' : 'inactive' ?>"><?= $testimonial['active'] ? 'Active' : 'Inactive' ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="testimonial-meta">
                                                        <span><i class="feather icon-building"></i> <?= htmlspecialchars($testimonial['tenant_name'] ?? 'N/A') ?></span>
                                                        <span><i class="feather icon-calendar"></i> <?= date('M d, Y', strtotime($testimonial['created_at'])) ?></span>
                                                    </div>
                                                    <p class="testimonial-text">"<?= htmlspecialchars($testimonial['testimonial']) ?>"</p>
                                                    <div class="testimonial-actions">
                                                        <button class="action-btn edit-testimonial" data-id="<?= $testimonial['id'] ?>" title="Edit">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <button class="action-btn toggle-status"
                                                                data-id="<?= $testimonial['id'] ?>"
                                                                data-active="<?= $testimonial['active'] ?>"
                                                                title="<?= $testimonial['active'] ? 'Deactivate' : 'Activate' ?>">
                                                            <i class="feather <?= $testimonial['active'] ? 'icon-eye-off' : 'icon-eye' ?>"></i>
                                                        </button>
                                                        <button class="action-btn delete delete-testimonial" data-id="<?= $testimonial['id'] ?>" data-name="<?= htmlspecialchars($testimonial['name']) ?>" title="Delete">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <div class="empty-state-icon"><i class="feather icon-message-square"></i></div>
                                                <div class="empty-state-text">No testimonials found</div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($total_pages > 1): ?>
                                        <div class="pagination-wrap">
                                            <ul class="pagination">
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        <i class="feather icon-chevron-left"></i>
                                                    </a>
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
                                                    <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                        <i class="feather icon-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                            <div class="pagination-info">
                                                Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($testimonials) ?> of <?= $total_items ?> testimonials
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                    </div><!-- /sa-content -->
                                </div><!-- /sa-wrap -->
                            </div><!-- /.pcoded-inner-content -->
                        </div><!-- /.pcoded-content -->
                    </div><!-- /.pcoded-wrapper -->
                </div><!-- /.pcoded-main-container -->

<!-- Add Testimonial Modal -->
<div class="modal fade modal-glass" id="addTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="addTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_testimonial">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="feather icon-plus" style="margin-right:8px"></i>Add New Testimonial</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Tenant *</label>
                            <select name="tenant_id" class="form-control" required>
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., CEO, Travel Manager">
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <small style="color:var(--muted);font-size:0.75rem">Optional. JPG, PNG, GIF only. Max 5MB.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rating *</label>
                            <select name="rating" class="form-control" required>
                                <option value="5">5 Stars ★★★★★</option>
                                <option value="4">4 Stars ★★★★☆</option>
                                <option value="3">3 Stars ★★★☆☆</option>
                                <option value="2">2 Stars ★★☆☆☆</option>
                                <option value="1">1 Star ★☆☆☆☆</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Testimonial *</label>
                            <textarea name="testimonial" rows="4" class="form-control" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Testimonial</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Testimonial Modal -->
<div class="modal fade modal-glass" id="editTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="editTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="update_testimonial">
            <input type="hidden" name="testimonial_id" id="edit_testimonial_id">
            <input type="hidden" name="existing_photo" id="edit_existing_photo">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,var(--amber),#d97706)">
                    <h5 class="modal-title"><i class="feather icon-edit" style="margin-right:8px"></i>Edit Testimonial</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Tenant *</label>
                            <select name="tenant_id" id="edit_tenant_id" class="form-control" required>
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Customer Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" id="edit_position" class="form-control" placeholder="e.g., CEO, Travel Manager">
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/*">
                            <small style="color:var(--muted);font-size:0.75rem">Leave empty to keep current photo.</small>
                            <div id="current_photo_container" class="mt-2" style="display:none">
                                <img id="current_photo" src="" alt="Current Photo" style="width:60px;height:60px;border-radius:50%;object-fit:cover">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rating *</label>
                            <select name="rating" id="edit_rating" class="form-control" required>
                                <option value="5">5 Stars ★★★★★</option>
                                <option value="4">4 Stars ★★★★☆</option>
                                <option value="3">3 Stars ★★★☆☆</option>
                                <option value="2">2 Stars ★★☆☆☆</option>
                                <option value="1">1 Star ★☆☆☆☆</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column:span 2">
                            <label class="form-label">Testimonial *</label>
                            <textarea name="testimonial" id="edit_testimonial" rows="4" class="form-control" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Testimonial</button>
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

<script>
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

    $.ajax({
        url: 'get_testimonial_details.php',
        type: 'GET',
        data: { id: testimonialId },
        success: function(response) {
            const testimonial = JSON.parse(response);

            $('#edit_testimonial_id').val(testimonial.id);
            $('#edit_tenant_id').val(testimonial.tenant_id);
            $('#edit_name').val(testimonial.name);
            $('#edit_position').val(testimonial.position || '');
            $('#edit_rating').val(testimonial.rating);
            $('#edit_testimonial').val(testimonial.testimonial);
            $('#edit_existing_photo').val(testimonial.photo || '');

            if (testimonial.photo) {
                $('#current_photo').attr('src', '../' + testimonial.photo);
                $('#current_photo_container').show();
            } else {
                $('#current_photo_container').hide();
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
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
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
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
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
