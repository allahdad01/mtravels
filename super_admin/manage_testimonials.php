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

                $stmt = $pdo->prepare("INSERT INTO testimonials (tenant_id, name, photo, testimonial, destination, rating, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$_POST['tenant_id'], $_POST['name'], $photo_path, $_POST['testimonial'], $_POST['destination'], $_POST['rating']]);

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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --orange:   #f97316;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 24px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* Card colors */
.sa-card:nth-child(1) { border-left-color: #6366f1; }
.sa-card:nth-child(2) { border-left-color: #10b981; }
.sa-card:nth-child(3) { border-left-color: #f59e0b; }

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.sa-btn-sm { padding: 6px 12px; font-size: .75rem; }
.sa-btn-success { background: linear-gradient(135deg, var(--green), #34d399); color: white; }
.sa-btn-warning { background: linear-gradient(135deg, var(--amber), #fbbf24); color: white; }
.sa-btn-danger { background: linear-gradient(135deg, var(--red), #f87171); color: white; }

/* ─── BADGE ─────────────────────────────────────────────── */
.badge-num {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px; border-radius: 20px;
  font-size: .7rem; font-weight: 700;
  background: rgba(108,99,255,.2); color: var(--accent);
  font-family: 'JetBrains Mono', monospace;
}

/* ─── FORM STYLES ────────────────────────────────────────── */
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
}

.form-group { position: relative; }

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
    background: var(--surface);
}

/* ─── TESTIMONIAL CARD ───────────────────────────────────── */
.testimonial-entry {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--muted);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all .2s;
    display: flex;
    gap: 20px;
}
.testimonial-entry:last-child { margin-bottom: 0; }
.testimonial-entry:hover {
    border-left-color: var(--accent);
    background: rgba(108,99,255,.02);
}
.testimonial-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
    flex-shrink: 0;
    overflow: hidden;
}
.testimonial-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.testimonial-content {
    flex: 1;
    min-width: 0;
}
.testimonial-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 8px;
}
.testimonial-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}
.testimonial-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--muted);
}
.testimonial-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.testimonial-rating {
    color: var(--amber);
    font-size: 0.85rem;
    letter-spacing: 2px;
}
.testimonial-text {
    font-size: 0.85rem;
    color: var(--muted);
    line-height: 1.6;
    margin-bottom: 12px;
    font-style: italic;
}
.testimonial-actions {
    display: flex;
    gap: 8px;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s;
    color: var(--muted);
}
.action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.action-btn.delete:hover {
    border-color: var(--red);
    color: var(--red);
    background: rgba(239,68,68,.05);
}
.action-btn.toggle-active {
    border-color: var(--green);
    color: var(--green);
}
.action-btn.toggle-active:hover {
    background: rgba(16,185,129,.1);
}
.action-btn.toggle-inactive {
    border-color: var(--amber);
    color: var(--amber);
}
.action-btn.toggle-inactive:hover {
    background: rgba(245,158,11,.1);
}

/* Status badge */
.status-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.status-badge.active { background: rgba(16,185,129,.15); color: var(--green); }
.status-badge.inactive { background: rgba(245,158,11,.15); color: var(--amber); }

/* ─── PAGINATION ─────────────────────────────────────────── */
.pagination-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}
.pagination {
    display: flex;
    gap: 6px;
    list-style: none;
    flex-wrap: wrap;
    justify-content: center;
}
.page-item { display: flex; }
.page-link {
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all .15s;
}
.page-link:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.page-item.active .page-link {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-color: var(--accent);
    color: white;
}
.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.pagination-info {
    font-size: 0.75rem;
    color: var(--muted);
}

/* ─── EMPTY STATE ───────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-state-text {
    font-size: 0.9rem;
}

/* ─── SCROLLBAR ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 10px; }

/* ─── PCODED LAYOUT INTEGRATION ──────────────────────────── */
body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .sa-content { padding: 16px; }
    .filter-grid { grid-template-columns: 1fr; }
    .testimonial-entry { flex-direction: column; }
    .testimonial-avatar { width: 50px; height: 50px; }
    .testimonial-header { flex-direction: column; align-items: flex-start; }
    .testimonial-actions { width: 100%; justify-content: flex-end; }
    .pagination { gap: 4px; }
    .page-link { padding: 6px 10px; font-size: 0.75rem; }
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
                <!-- [ breadcrumb ] end -->

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Filter Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-filter" style="margin-right:8px"></i>Filters</h3>
                                <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#addTestimonialModal">
                                    <i class="feather icon-plus"></i>Add Testimonial
                                </button>
                            </div>
                            <div class="sa-card-body">
                                <form method="GET" action="manage_testimonials.php">
                                    <div class="filter-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="search">Search</label>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Name, destination, testimonial..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="sa-btn sa-btn-primary" style="width:100%; justify-content:center;">
                                                <i class="feather icon-search"></i> Search
                                            </button>
                                        </div>
                                        <?php if (!empty($search_query)): ?>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <a href="manage_testimonials.php" class="sa-btn sa-btn-ghost" style="width:100%; justify-content:center;">
                                                <i class="feather icon-x"></i> Clear
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Testimonials List Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-message-square" style="margin-right:8px"></i>Testimonials</h3>
                                <span class="badge-num"><?= $total_items ?> total</span>
                            </div>
                            <div class="sa-card-body">
                                <?php if (!empty($testimonials)): ?>
                                    <?php foreach ($testimonials as $testimonial): 
                                        $initial = strtoupper(substr($testimonial['name'], 0, 1));
                                        $stars = '';
                                        for($i = 1; $i <= 5; $i++) {
                                            $stars .= $i <= $testimonial['rating'] ? '★' : '☆';
                                        }
                                    ?>
                                    <div class="testimonial-entry" style="border-left-color: <?= $testimonial['active'] ? 'var(--green)' : 'var(--amber)' ?>">
                                        <div class="testimonial-avatar">
                                            <?php if ($testimonial['photo']): ?>
                                                <img src="../<?= htmlspecialchars($testimonial['photo']) ?>" alt="<?= htmlspecialchars($testimonial['name']) ?>">
                                            <?php else: ?>
                                                <?= $initial ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="testimonial-content">
                                            <div class="testimonial-header">
                                                <div>
                                                    <h4 class="testimonial-name"><?= htmlspecialchars($testimonial['name']) ?></h4>
                                                    <div class="testimonial-meta">
                                                        <span><i class="feather icon-building"></i> <?= htmlspecialchars($testimonial['tenant_name'] ?? 'N/A') ?></span>
                                                        <span><i class="feather icon-map-pin"></i> <?= htmlspecialchars($testimonial['destination'] ?: 'N/A') ?></span>
                                                        <span><i class="feather icon-calendar"></i> <?= date('M d, Y', strtotime($testimonial['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                                <div style="display:flex;align-items:center;gap:8px">
                                                    <span class="testimonial-rating"><?= $stars ?></span>
                                                    <span class="status-badge <?= $testimonial['active'] ? 'active' : 'inactive' ?>">
                                                        <?= $testimonial['active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <p class="testimonial-text">"<?= htmlspecialchars($testimonial['testimonial']) ?>"</p>
                                            <div class="testimonial-actions">
                                                <button class="action-btn edit-testimonial" data-id="<?= $testimonial['id'] ?>" title="Edit">
                                                    <i class="feather icon-edit-2"></i>
                                                </button>
                                                <button class="action-btn <?= $testimonial['active'] ? 'toggle-inactive' : 'toggle-active' ?> toggle-status"
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

                                <!-- Pagination -->
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
                            </div>
                        </div>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->

<!-- Add Testimonial Modal -->
<div class="modal fade" id="addTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="addTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_testimonial">
            <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #2ed8b6);border:none;padding:20px 24px">
                    <h5 class="modal-title" style="color:white;font-weight:600">
                        <i class="feather icon-plus" style="margin-right:8px"></i>Add New Testimonial
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity:0.8">
                        <span style="color:white">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding:24px">
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
                            <label class="form-label">Destination</label>
                            <input type="text" name="destination" class="form-control" placeholder="e.g., Dubai, Tokyo">
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
                <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 24px">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">Add Testimonial</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Testimonial Modal -->
<div class="modal fade" id="editTestimonialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="editTestimonialForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="update_testimonial">
            <input type="hidden" name="testimonial_id" id="edit_testimonial_id">
            <input type="hidden" name="existing_photo" id="edit_existing_photo">
            <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);border:none;padding:20px 24px">
                    <h5 class="modal-title" style="color:white;font-weight:600">
                        <i class="feather icon-edit" style="margin-right:8px"></i>Edit Testimonial
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity:0.8">
                        <span style="color:white">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding:24px">
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
                            <label class="form-label">Destination</label>
                            <input type="text" name="destination" id="edit_destination" class="form-control" placeholder="e.g., Dubai, Tokyo">
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
                <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 24px">
                    <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-warning">Update Testimonial</button>
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
            $('#edit_destination').val(testimonial.destination || '');
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
