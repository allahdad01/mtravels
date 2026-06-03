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
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $validation = FileUploadSecurity::validateUpload($_FILES['photo'], 'image', 5242880);
                    if (!$validation['success']) {
                        throw new Exception($validation['error']);
                    }
                    $moveResult = FileUploadSecurity::moveUploadedFile(
                        $_FILES['photo']['tmp_name'],
                        $upload_dir,
                        $validation['safe_name']
                    );
                    if (!$moveResult['success']) {
                        throw new Exception($moveResult['error']);
                    }
                    $photo_path = 'uploads/testimonials/' . $validation['safe_name'];
                }

                $stmt = $pdo->prepare("INSERT INTO testimonials (tenant_id, name, photo, testimonial, position, rating, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
                $stmt->execute([$_POST['tenant_id'], $_POST['name'], $photo_path, $_POST['testimonial'], $_POST['position'], $_POST['rating']]);

                logAudit($pdo, $_SESSION['user_id'], 'create_testimonial', 'testimonials', $pdo->lastInsertId(), 'Created new testimonial for ' . $_POST['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial added successfully']);
                break;

            case 'update_testimonial':
                $testimonial_id = $_POST['testimonial_id'];
                $photo_path = $_POST['existing_photo'] ?? null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/testimonials/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $validation = FileUploadSecurity::validateUpload($_FILES['photo'], 'image', 5242880);
                    if (!$validation['success']) {
                        throw new Exception($validation['error']);
                    }
                    $moveResult = FileUploadSecurity::moveUploadedFile(
                        $_FILES['photo']['tmp_name'],
                        $upload_dir,
                        $validation['safe_name']
                    );
                    if ($moveResult['success']) {
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

                logAudit($pdo, $_SESSION['user_id'], 'update_testimonial', 'testimonials', $testimonial_id, 'Updated testimonial for ' . $_POST['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial updated successfully']);
                break;

            case 'delete_testimonial':
                $testimonial_id = $_POST['testimonial_id'];
                $stmt = $pdo->prepare("SELECT name, photo FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonial_id]);
                $testimonial = $stmt->fetch();

                if ($testimonial['photo'] && file_exists('../' . $testimonial['photo'])) {
                    unlink('../' . $testimonial['photo']);
                }

                $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
                $stmt->execute([$testimonial_id]);

                logAudit($pdo, $_SESSION['user_id'], 'delete_testimonial', 'testimonials', $testimonial_id, 'Deleted testimonial from ' . $testimonial['name']);

                echo json_encode(['success' => true, 'message' => 'Testimonial deleted successfully']);
                break;

            case 'toggle_status':
                $testimonial_id = $_POST['testimonial_id'];
                $active = $_POST['active'];

                $stmt = $pdo->prepare("UPDATE testimonials SET active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$active, $testimonial_id]);

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

$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$stmt->execute();
$tenants = $stmt->fetchAll();

function logAudit($pdo, $user_id, $action, $entity_type, $entity_id, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $entity_type, $entity_id, $details, $ip]);
}

$active_count = 0;
$inactive_count = 0;
foreach ($testimonials as $t) {
    if ($t['active']) $active_count++; else $inactive_count++;
}
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
    :root {
        --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --muted: #888;
        --surface: #fff;
        --surface2: #f5f6fa;
        --border: #e0e0e0;
        --text: #333;
        --radius: 10px;
        --green: #10b981;
        --red: #ef4444;
        --amber: #f59e0b;
        --blue: #3b82f6;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif; background:#f0f2f5; color:var(--text); }

    /* ─── PAGE HEADER ────────────────────────────────────────── */
    .page-header.card {
        background: var(--grad) !important; color: #fff; border: none !important;
        margin-bottom: 20px; padding: 22px 28px !important;
        box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
        position: relative; overflow: hidden;
    }
    .page-header.card::after {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }
    .page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
    .page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
    .page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
    .page-header.card .sa-btn:hover { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; transform: translateY(-1px); }

    /* ─── SECTION LABEL ───────────────────────────────────────── */
    .section-label {
        font-size:0.82rem; font-weight:700; color:var(--muted); text-transform:uppercase;
        letter-spacing:0.05em; margin-bottom:10px;
    }

    /* ─── METRIC CARDS ────────────────────────────────────────── */
    .metric-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:16px; }
    .metric-card {
        background:var(--surface); border-radius:12px; padding:20px; border:1px solid var(--border);
        border-left:4px solid var(--blue); box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:all 0.2s;
    }
    .metric-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.1); }
    .metric-card.green { border-left-color:var(--green); }
    .metric-card.amber { border-left-color:var(--amber); }
    .metric-card.red { border-left-color:var(--red); }
    .metric-value { font-size:1.5rem; font-weight:700; margin-bottom:6px; }
    .metric-label { font-size:0.78rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em; }

    /* ─── TOOLBAR ─────────────────────────────────────────────── */
    .sa-toolbar {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        padding:16px; margin-bottom:16px;
    }
    .sa-toolbar-form { display:flex; }
    .sa-toolbar-group { display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%; }
    .sa-search-wrap { position:relative; flex:1; min-width:200px; }
    .sa-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
    .sa-toolbar-input {
        padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.85rem;
        background:var(--surface); color:var(--text); min-width:140px; flex:1;
    }
    .sa-toolbar-input:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    .sa-search-input { padding-left:36px !important; }

    /* ─── DATA TABLE ──────────────────────────────────────────── */
    .sa-table-wrap {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        overflow-x:auto; margin-bottom:16px;
    }
    .sa-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    .sa-table thead { background:#f8f9fc; }
    .sa-table th {
        padding:12px 14px; text-align:left; font-weight:600; color:#555;
        border-bottom:2px solid var(--border); white-space:nowrap;
    }
    .sa-table td { padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .sa-table tbody tr:hover { background:#f8f9fc; }
    .sa-table tbody tr:last-child td { border-bottom:none; }
    .sa-th-actions { text-align:right; width:80px; }
    .sa-td-actions { text-align:right; white-space:nowrap; }

    /* ─── AVATAR CELL ─────────────────────────────────────────── */
    .sa-avatar-cell { display:flex; align-items:center; gap:10px; }
    .sa-avatar {
        width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:700; font-size:0.85rem; flex-shrink:0; overflow:hidden;
    }
    .sa-avatar img { width:100%; height:100%; object-fit:cover; }

    /* ─── PILLS ───────────────────────────────────────────────── */
    .pill {
        font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:20px;
        text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap; display:inline-block;
    }
    .pill-green { background:rgba(16,185,129,0.12); color:#10b981; }
    .pill-amber { background:rgba(245,158,11,0.12); color:#d97706; }

    /* ─── EMPTY STATE ─────────────────────────────────────────── */
    .sa-empty {
        text-align:center; padding:48px 20px; color:var(--muted);
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:16px;
    }
    .sa-empty p { margin-top:12px; font-size:0.9rem; }

    /* ─── BUTTONS ─────────────────────────────────────────────── */
    .sa-btn {
        display:inline-flex; align-items:center; padding:9px 18px; border-radius:8px;
        font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        border:none; text-decoration:none; gap:4px;
    }
    .sa-btn-sm { padding:6px 12px; font-size:0.8rem; }
    .sa-btn-primary { background:var(--grad); color:#fff; }
    .sa-btn-primary:hover { box-shadow:0 4px 12px rgba(64,153,255,0.35); transform:translateY(-1px); }
    .sa-btn-ghost { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
    .sa-btn-ghost:hover { background:#e8e8e8; }

    /* ─── ICON BUTTONS ─────────────────────────────────────────── */
    .sa-btn-icon {
        display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
        border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;
        background:transparent; color:#666;
    }
    .sa-btn-icon:hover { background:#e8ecf1; color:var(--blue); }
    .sa-btn-icon-danger:hover { background:#fef2f2; color:var(--red); }

    /* ─── PAGINATION ──────────────────────────────────────────── */
    .sa-pagination { margin-top:16px; }
    .sa-pagination-btns { display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; }
    .sa-page-btn {
        display:inline-flex; align-items:center; justify-content:center;
        width:36px; height:36px; border-radius:8px; border:1px solid var(--border);
        background:var(--surface); cursor:pointer; transition:all 0.2s; color:#555;
    }
    .sa-page-btn:hover:not(:disabled) { border-color:var(--blue); color:var(--blue); background:#f0f4ff; }
    .sa-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
    .sa-pagination-info { font-size:0.8rem; color:var(--muted); margin:0 8px; white-space:nowrap; }

    /* ─── MODAL ───────────────────────────────────────────────── */
    .sa-modal-overlay {
        display:none; position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,0.45); backdrop-filter:blur(2px);
        align-items:center; justify-content:center;
    }
    .sa-modal {
        background:var(--surface); border-radius:14px; width:100%; max-width:520px;
        max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);
        animation:modalIn 0.25s ease-out;
    }
    .sa-modal-wide { max-width:680px; }
    @keyframes modalIn { from { opacity:0; transform:scale(0.95) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
    .sa-modal-header {
        display:flex; align-items:center; justify-content:space-between;
        padding:18px 22px; border-bottom:1px solid var(--border);
    }
    .sa-modal-header h5 { font-size:1.05rem; font-weight:700; display:flex; align-items:center; margin:0; }
    .sa-modal-close {
        background:none; border:none; cursor:pointer; color:#999; padding:4px; border-radius:6px;
        display:flex; align-items:center; justify-content:center;
    }
    .sa-modal-close:hover { background:var(--surface2); color:var(--text); }
    .sa-modal-body { padding:20px 22px; }
    .sa-modal-footer {
        display:flex; justify-content:flex-end; gap:10px;
        padding:16px 22px; border-top:1px solid var(--border); background:var(--surface2);
    }

    /* ─── FORM ELEMENTS ───────────────────────────────────────── */
    .sa-form-group { margin-bottom:14px; }
    .sa-form-label { display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:5px; }
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
    textarea.sa-form-control { resize:vertical; }
    .sa-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    @media (max-width:768px) {
        .sa-form-row { grid-template-columns:1fr; }
        .sa-toolbar-group { flex-direction:column; }
        .sa-search-wrap { width:100%; }
        .sa-toolbar-input { width:100%; }
        .metric-grid { grid-template-columns:1fr 1fr; }
    }
    </style>
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header card">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="10" x2="9" y2="10.01"/><line x1="15" y1="10" x2="15" y2="10.01"/></svg>
                                    <?php echo __('manage_testimonials'); ?>
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?php echo __('manage_customer_testimonials'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="sa-btn" onclick="showModal('addTestimonialModal')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php echo __('add_testimonial'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->

                            <!-- Stats -->
                            <p class="section-label">Summary</p>
                            <div class="metric-grid">
                                <div class="metric-card blue">
                                    <div class="metric-value" style="font-size:1.8rem;"><?= $total_items ?></div>
                                    <div class="metric-label"><?php echo __('total_testimonials'); ?></div>
                                </div>
                                <div class="metric-card green">
                                    <div class="metric-value" style="font-size:1.8rem;"><?= $active_count ?></div>
                                    <div class="metric-label"><?php echo __('active'); ?></div>
                                </div>
                                <div class="metric-card amber">
                                    <div class="metric-value" style="font-size:1.8rem;"><?= $inactive_count ?></div>
                                    <div class="metric-label"><?php echo __('inactive'); ?></div>
                                </div>
                            </div>

                            <!-- Toolbar -->
                            <div class="sa-toolbar">
                                <form method="GET" class="sa-toolbar-form">
                                    <div class="sa-toolbar-group">
                                        <div class="sa-search-wrap">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" name="search" class="sa-toolbar-input sa-search-input" placeholder="<?php echo __('search_name_position'); ?>" value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <?php echo __('search'); ?>
                                        </button>
                                        <?php if (!empty($search_query)): ?>
                                        <a href="manage_testimonials.php" class="sa-btn sa-btn-ghost sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            <?php echo __('clear'); ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Testimonials Table -->
                            <?php if (!empty($testimonials)): ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('customer'); ?></th>
                                            <th><?php echo __('tenant'); ?></th>
                                            <th><?php echo __('position'); ?></th>
                                            <th><?php echo __('rating'); ?></th>
                                            <th><?php echo __('status'); ?></th>
                                            <th><?php echo __('created'); ?></th>
                                            <th class="sa-th-actions"><?php echo __('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testimonials as $testimonial):
                                            $initial = strtoupper(substr($testimonial['name'], 0, 1));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="sa-avatar-cell">
                                                    <div class="sa-avatar" style="background:linear-gradient(135deg,#4099ff,#2ed8b6);">
                                                        <?php if ($testimonial['photo']): ?>
                                                        <img src="../<?= htmlspecialchars($testimonial['photo']) ?>" alt="<?= htmlspecialchars($testimonial['name']) ?>">
                                                        <?php else: ?>
                                                        <?= $initial ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($testimonial['name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($testimonial['tenant_name'] ?? 'N/A') ?></td>
                                            <td style="color:var(--muted);"><?= htmlspecialchars($testimonial['position'] ?: '-') ?></td>
                                            <td>
                                                <span style="color:var(--amber);white-space:nowrap;">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="<?= $i <= $testimonial['rating'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><?php if ($i <= $testimonial['rating']): ?><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/><?php else: ?><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/><?php endif; ?></svg>
                                                    <?php endfor; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="pill <?= $testimonial['active'] ? 'pill-green' : 'pill-amber' ?>"><?= $testimonial['active'] ? __('active') : __('inactive') ?></span>
                                            </td>
                                            <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($testimonial['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <button type="button" class="sa-btn-icon" onclick="editTestimonial(<?= $testimonial['id'] ?>)" title="<?php echo __('edit'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button type="button" class="sa-btn-icon" onclick="toggleStatus(<?= $testimonial['id'] ?>, <?= $testimonial['active'] ?>)" title="<?= $testimonial['active'] ? __('deactivate') : __('activate') ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php if ($testimonial['active']): ?><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/><?php else: ?><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><?php endif; ?></svg>
                                                </button>
                                                <button type="button" class="sa-btn-icon sa-btn-icon-danger" onclick="deleteTestimonial(<?= $testimonial['id'] ?>, '<?= htmlspecialchars($testimonial['name'], ENT_QUOTES) ?>')" title="<?php echo __('delete'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="sa-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <p><?php echo __('no_testimonials_found'); ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="sa-pagination">
                                <div class="sa-pagination-btns">
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                    </button>
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                    </button>
                                    <span class="sa-pagination-info"><?= __('page') . ' ' . $current_page . ' ' . __('of') . ' ' . $total_pages . ' | ' . count($testimonials) . ' ' . __('of') . ' ' . $total_items . ' ' . __('testimonials') ?></span>
                                    <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    </button>
                                    <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 18 12 11 7"/><polyline points="6 17 13 12 6 7"/></svg>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Testimonial Modal -->
    <div class="sa-modal-overlay" id="addTestimonialModal">
        <div class="sa-modal sa-modal-wide">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo __('add_testimonial'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('addTestimonialModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form id="addTestimonialForm" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="add_testimonial">
                <div class="sa-modal-body">
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('tenant'); ?> *</label>
                            <select name="tenant_id" class="sa-form-control" required>
                                <option value=""><?php echo __('select_tenant'); ?></option>
                                <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('customer_name'); ?> *</label>
                            <input type="text" name="name" class="sa-form-control" required>
                        </div>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('position'); ?></label>
                            <input type="text" name="position" class="sa-form-control" placeholder="e.g., CEO, Travel Manager">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('rating'); ?> *</label>
                            <select name="rating" class="sa-form-control" required>
                                <option value="5">5 <?php echo __('stars'); ?></option>
                                <option value="4">4 <?php echo __('stars'); ?></option>
                                <option value="3">3 <?php echo __('stars'); ?></option>
                                <option value="2">2 <?php echo __('stars'); ?></option>
                                <option value="1">1 <?php echo __('star'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('photo'); ?></label>
                        <input type="file" name="photo" class="sa-form-control" accept="image/*">
                        <small style="color:var(--muted);font-size:0.75rem;"><?php echo __('photo_optional_hint'); ?></small>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('testimonial'); ?> *</label>
                        <textarea name="testimonial" rows="4" class="sa-form-control" required></textarea>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('addTestimonialModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?php echo __('add_testimonial'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Testimonial Modal -->
    <div class="sa-modal-overlay" id="editTestimonialModal">
        <div class="sa-modal sa-modal-wide">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <?php echo __('edit_testimonial'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('editTestimonialModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form id="editTestimonialForm" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_testimonial">
                <input type="hidden" name="testimonial_id" id="edit_testimonial_id">
                <input type="hidden" name="existing_photo" id="edit_existing_photo">
                <div class="sa-modal-body">
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('tenant'); ?> *</label>
                            <select name="tenant_id" id="edit_tenant_id" class="sa-form-control" required>
                                <option value=""><?php echo __('select_tenant'); ?></option>
                                <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('customer_name'); ?> *</label>
                            <input type="text" name="name" id="edit_name" class="sa-form-control" required>
                        </div>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('position'); ?></label>
                            <input type="text" name="position" id="edit_position" class="sa-form-control" placeholder="e.g., CEO, Travel Manager">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('rating'); ?> *</label>
                            <select name="rating" id="edit_rating" class="sa-form-control" required>
                                <option value="5">5 <?php echo __('stars'); ?></option>
                                <option value="4">4 <?php echo __('stars'); ?></option>
                                <option value="3">3 <?php echo __('stars'); ?></option>
                                <option value="2">2 <?php echo __('stars'); ?></option>
                                <option value="1">1 <?php echo __('star'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('photo'); ?></label>
                        <input type="file" name="photo" class="sa-form-control" accept="image/*">
                        <small style="color:var(--muted);font-size:0.75rem;"><?php echo __('photo_update_hint'); ?></small>
                        <div id="current_photo_container" style="display:none;margin-top:8px;">
                            <img id="current_photo" src="" alt="Current Photo" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('testimonial'); ?> *</label>
                        <textarea name="testimonial" id="edit_testimonial" rows="4" class="sa-form-control" required></textarea>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('editTestimonialModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php echo __('update_testimonial'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>


<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
    <script>
    function showModal(id) {
        document.getElementById(id).style.display = 'flex';
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Form submission handler
    function handleFormSubmit(formId) {
        const form = document.getElementById(formId);
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('manage_testimonials.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('An error occurred while processing your request.'));
        });
    }
    handleFormSubmit('addTestimonialForm');
    handleFormSubmit('editTestimonialForm');

    // Edit testimonial (AJAX load)
    function editTestimonial(id) {
        fetch('get_testimonial_details.php?id=' + id)
            .then(r => r.json())
            .then(t => {
                document.getElementById('edit_testimonial_id').value = t.id;
                document.getElementById('edit_tenant_id').value = t.tenant_id;
                document.getElementById('edit_name').value = t.name;
                document.getElementById('edit_position').value = t.position || '';
                document.getElementById('edit_rating').value = t.rating;
                document.getElementById('edit_testimonial').value = t.testimonial;
                document.getElementById('edit_existing_photo').value = t.photo || '';
                if (t.photo) {
                    document.getElementById('current_photo').src = '../' + t.photo;
                    document.getElementById('current_photo_container').style.display = 'block';
                } else {
                    document.getElementById('current_photo_container').style.display = 'none';
                }
                showModal('editTestimonialModal');
            })
            .catch(() => alert('Failed to load testimonial data.'));
    }

    // Toggle status
    function toggleStatus(id, currentActive) {
        const newActive = currentActive ? 0 : 1;
        const actionText = newActive ? 'activate' : 'deactivate';
        if (confirm('Do you want to ' + actionText + ' this testimonial?')) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('testimonial_id', id);
            formData.append('active', newActive);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            fetch('manage_testimonials.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('An error occurred.'));
        }
    }

    // Delete testimonial
    function deleteTestimonial(id, name) {
        if (confirm('Delete testimonial from ' + name + '? This cannot be undone.')) {
            const formData = new FormData();
            formData.append('action', 'delete_testimonial');
            formData.append('testimonial_id', id);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            fetch('manage_testimonials.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('An error occurred.'));
        }
    }
    </script>
<?php include '../includes/admin_footer.php'; ?>
