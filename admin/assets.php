<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Include secure file upload class
require_once '../includes/SecureFileUpload.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Build redirect URL
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

require_once '../includes/InputValidator.php';

// ── CSRF helper ──────────────────────────────────────────────────────────────
function validateCsrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
    }
}

// ── ADD ASSET ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    validateCsrf();

    $name           = InputValidator::getString($_POST['name']           ?? '', 255);
    $category       = InputValidator::getString($_POST['category']       ?? '', 100);
    $purchase_date  = InputValidator::getDate($_POST['purchase_date']    ?? '', 'Y-m-d', '');
    $purchase_value = InputValidator::getString($_POST['purchase_value'] ?? '', 20);
    $current_value  = InputValidator::getString($_POST['current_value']  ?? '', 20);
    $currency       = InputValidator::getEnum($_POST['currency'] ?? '', ['USD','EUR','AFS','DARHAM','PKR','INR'], 'USD');
    $description    = InputValidator::getString($_POST['description']    ?? '', 1000);
    $location       = InputValidator::getString($_POST['location']       ?? '', 255);
    $serial_number  = InputValidator::getString($_POST['serial_number']  ?? '', 100);
    $warranty_expiry = InputValidator::getDate($_POST['warranty_expiry'] ?? '', 'Y-m-d', null);
    $status         = InputValidator::getEnum($_POST['status'] ?? '', ['active','inactive','sold','disposed'], 'active');
    $assigned_to    = InputValidator::getString($_POST['assigned_to']    ?? '', 255);
    $condition_state = InputValidator::getEnum($_POST['condition_state'] ?? '', ['New','Excellent','Good','Fair','Poor'], 'Good');

    $document = '';
    if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
        $result   = $uploader->upload('document', 'assets');
        if ($result['success']) {
            $document = $result['data']['filename'];
        } else {
            $_SESSION['error_message'] = "Document upload failed: " . $result['error'];
            header('Location: ' . $redirect_url); exit();
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO assets (name,category,purchase_date,purchase_value,current_value,currency,description,location,serial_number,warranty_expiry,status,assigned_to,condition_state,document,tenant_id,branch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$name,$category,$purchase_date,$purchase_value,$current_value,$currency,$description,$location,$serial_number,$warranty_expiry,$status,$assigned_to,$condition_state,$document,$tenant_id,$branch_id]);
        $_SESSION['success_message'] = "Asset added successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error adding asset: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── EDIT ASSET ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_asset'])) {
    validateCsrf();

    $asset_id       = (int)$_POST['asset_id'];
    $name           = InputValidator::getString($_POST['name']           ?? '', 255);
    $category       = InputValidator::getString($_POST['category']       ?? '', 100);
    $purchase_date  = InputValidator::getDate($_POST['purchase_date']    ?? '', 'Y-m-d', '');
    $purchase_value = InputValidator::getString($_POST['purchase_value'] ?? '', 20);
    $current_value  = InputValidator::getString($_POST['current_value']  ?? '', 20);
    $currency       = InputValidator::getEnum($_POST['currency'] ?? '', ['USD','EUR','AFS','DARHAM','PKR','INR'], 'USD');
    $description    = InputValidator::getString($_POST['description']    ?? '', 1000);
    $location       = InputValidator::getString($_POST['location']       ?? '', 255);
    $serial_number  = InputValidator::getString($_POST['serial_number']  ?? '', 100);
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? InputValidator::getDate($_POST['warranty_expiry'], 'Y-m-d', null) : null;
    $status         = InputValidator::getEnum($_POST['status'] ?? '', ['active','inactive','maintenance','sold','disposed'], 'active');
    $assigned_to    = InputValidator::getString($_POST['assigned_to']    ?? '', 255);
    $condition_state = InputValidator::getEnum($_POST['condition_state'] ?? '', ['New','Excellent','Good','Fair','Poor'], 'Good');

    // Get current document
    $stmt = $pdo->prepare("SELECT document FROM assets WHERE id=? AND tenant_id=? AND branch_id=?");
    $stmt->execute([$asset_id, $tenant_id, $branch_id]);
    $asset            = $stmt->fetch();
    $current_document = $asset['document'] ?? '';
    $document         = $current_document;

    if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
        $result   = $uploader->upload('document', 'assets');
        if ($result['success']) {
            $document = $result['data']['filename'];
            if (!empty($current_document)) {
                $old_file = '../uploads/assets/' . $current_document;
                if (file_exists($old_file) && strpos(realpath($old_file), realpath('../uploads/assets/')) === 0) {
                    @unlink($old_file);
                }
            }
        } else {
            $_SESSION['error_message'] = "Document upload failed: " . $result['error'];
            header('Location: ' . $redirect_url); exit();
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE assets SET name=?,category=?,purchase_date=?,purchase_value=?,current_value=?,currency=?,description=?,location=?,serial_number=?,warranty_expiry=?,status=?,assigned_to=?,condition_state=?,document=? WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$name,$category,$purchase_date,$purchase_value,$current_value,$currency,$description,$location,$serial_number,$warranty_expiry,$status,$assigned_to,$condition_state,$document,$asset_id,$tenant_id,$branch_id]);
        $_SESSION['success_message'] = "Asset updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating asset: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── CHANGE STATUS ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    validateCsrf();
    $asset_id   = (int)$_POST['asset_id'];
    $new_status = $_POST['new_status'];
    $valid_statuses = ['active','inactive','maintenance','sold','disposed'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error_message'] = "Invalid status value!";
        header('Location: ' . $redirect_url); exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE assets SET status=? WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$new_status, $asset_id, $tenant_id, $branch_id]);
        $_SESSION['success_message'] = "Asset marked as " . ucfirst($new_status) . " successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error changing asset status: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── DEACTIVATE ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_asset'])) {
    validateCsrf();
    $asset_id = (int)$_POST['asset_id'];
    try {
        $stmt = $pdo->prepare("UPDATE assets SET status='inactive' WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$asset_id, $tenant_id, $branch_id]);
        $_SESSION['success_message'] = "Asset deactivated successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deactivating asset: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── REACTIVATE ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_asset'])) {
    validateCsrf();
    $asset_id = (int)$_POST['asset_id'];
    try {
        $stmt = $pdo->prepare("UPDATE assets SET status='active' WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$asset_id, $tenant_id, $branch_id]);
        $_SESSION['success_message'] = "Asset reactivated successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error reactivating asset: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── DELETE ASSET ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset'])) {
    validateCsrf();
    $asset_id = (int)$_POST['asset_id'];
    try {
        $stmt = $pdo->prepare("SELECT document FROM assets WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$asset_id, $tenant_id, $branch_id]);
        $asset = $stmt->fetch();
        $stmt  = $pdo->prepare("DELETE FROM assets WHERE id=? AND tenant_id=? AND branch_id=?");
        $stmt->execute([$asset_id, $tenant_id, $branch_id]);
        if (!empty($asset['document'])) {
            $file_path = '../uploads/assets/' . $asset['document'];
            if (file_exists($file_path)) unlink($file_path);
        }
        $_SESSION['success_message'] = "Asset deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting asset: " . $e->getMessage();
    }
    header('Location: ' . $redirect_url); exit();
}

// ── FETCH ASSETS ──────────────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'active';
$valid_filters = ['active','inactive','maintenance','sold','disposed','all'];
if (!in_array($status_filter, $valid_filters)) $status_filter = 'active';

$sql = "SELECT * FROM assets WHERE tenant_id=? AND branch_id=?";
$params = [$tenant_id, $branch_id];
if ($status_filter !== 'all') { $sql .= " AND status=?"; $params[] = $status_filter; }
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Status counts for tabs
$count_stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM assets WHERE tenant_id=? AND branch_id=? GROUP BY status");
$count_stmt->execute([$tenant_id, $branch_id]);
$status_counts_raw = $count_stmt->fetchAll(PDO::FETCH_ASSOC);
$status_counts = ['all'=>0,'active'=>0,'inactive'=>0,'maintenance'=>0,'sold'=>0,'disposed'=>0];
foreach ($status_counts_raw as $row) {
    $status_counts[$row['status']] = (int)$row['cnt'];
    $status_counts['all'] += (int)$row['cnt'];
}

// Currency totals
$currency_totals = [];
$categories      = [];
foreach ($assets as $a) {
    $currency_totals[$a['currency']] = ($currency_totals[$a['currency']] ?? 0) + $a['current_value'];
    $categories[$a['category']]      = ($categories[$a['category']] ?? 0) + 1;
}

// Status distribution for chart (always all assets, not filtered)
$all_stmt = $pdo->prepare("SELECT status FROM assets WHERE tenant_id=? AND branch_id=?");
$all_stmt->execute([$tenant_id, $branch_id]);
$all_assets_status = $all_stmt->fetchAll(PDO::FETCH_COLUMN);
$status_chart_data = ['active'=>0,'inactive'=>0,'maintenance'=>0,'sold'=>0,'disposed'=>0];
foreach ($all_assets_status as $s) { if (isset($status_chart_data[$s])) $status_chart_data[$s]++; }

// Category chart (all assets)
$cat_stmt = $pdo->prepare("SELECT category, COUNT(*) as cnt FROM assets WHERE tenant_id=? AND branch_id=? GROUP BY category");
$cat_stmt->execute([$tenant_id, $branch_id]);
$all_categories = [];
foreach ($cat_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $all_categories[$row['category']] = (int)$row['cnt'];
?>
<?php include '../includes/header.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ── TOKENS ── */
:root {
  --brand-start: #4099ff;
  --brand-end:   #2ed8b6;
  --brand-mid:   #38b2e8;
  --surface:        #f7f9fc;
  --surface-raised: #ffffff;
  --surface2:       #f0f4f8;
  --surface3:       #e8ecf2;
  --border:         #e4eaf3;
  --border2:        #d0dbe7;
  --text-primary:   #1a2540;
  --text-secondary: #5a6a85;
  --text-muted:     #96a4b8;
  --text:           #1a2540;
  --text-2:         #5a6a85;
  --text-3:         #96a4b8;
  --accent:      #4099ff;
  --accent-dim:  rgba(64,153,255,.12);
  --accent-glow: rgba(64,153,255,.28);
  --green:  #10b981;
  --yellow: #f59e0b;
  --red:    #ef4444;
  --cyan:   #38b2e8;
  --purple: #8b5cf6;
  --orange: #f97b4a;
  --radius: 12px;
  --radius-sm: 8px;
  --radius-lg: 18px;
  --font-d: 'DM Sans', sans-serif;
  --font-b: 'DM Sans', sans-serif;
}

/* ── SCOPE: wrap everything so we don't fight your existing theme ── */
.assets-page * { box-sizing: border-box; }
.assets-page { font-family: var(--font-b); color: var(--text-primary); background: var(--surface); min-height: 100vh; padding: 28px; }

/* ── PAGE HEADER ── */
.ap-page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 28px; gap: 16px; flex-wrap: wrap;
}
.ap-page-header h1 { font-family: var(--font-d); font-size: 26px; font-weight: 700; letter-spacing: -.4px; line-height: 1.2; }
.ap-page-header h1 span { color: var(--accent); }
.ap-page-header .subtitle { color: var(--text-secondary); font-size: 13.5px; margin-top: 4px; }
.ap-breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-muted); margin-bottom: 6px; }
.ap-breadcrumb a { color: var(--text-muted); text-decoration: none; }
.ap-breadcrumb a:hover { color: var(--accent); }
.ap-breadcrumb .sep { opacity: .4; }

/* ── ALERTS ── */
.ap-alert {
  display: flex; align-items: center; gap: 10px;
  padding: 13px 16px; border-radius: var(--radius-sm);
  font-size: 13.5px; margin-bottom: 20px;
  animation: apFadeUp .3s ease;
}
.ap-alert.success { background: rgba(45,212,170,.1); border: 1px solid rgba(45,212,170,.25); color: var(--green); }
.ap-alert.error   { background: rgba(240,90,106,.1);  border: 1px solid rgba(240,90,106,.25); color: var(--red); }
.ap-alert-close { margin-left: auto; background: none; border: none; color: inherit; cursor: pointer; opacity: .6; font-size: 15px; }
.ap-alert-close:hover { opacity: 1; }

/* ── BUTTONS ── */
.ap-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 18px; border-radius: var(--radius-sm);
  font-family: var(--font-b); font-size: 13.5px; font-weight: 500;
  cursor: pointer; border: none; transition: all .18s; white-space: nowrap; text-decoration: none;
}
.ap-btn-primary { background: linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%); color: #fff; box-shadow: 0 4px 16px var(--accent-glow); }
.ap-btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
.ap-btn-ghost { background: var(--surface-raised); color: var(--text-secondary); border: 1px solid var(--border-strong); }
.ap-btn-ghost:hover { border-color: var(--brand-start); color: var(--brand-start); }
.ap-btn-danger { background: rgba(240,90,106,.12); color: var(--red); border: 1px solid rgba(240,90,106,.2); }
.ap-btn-danger:hover { background: rgba(240,90,106,.2); }
.ap-btn-sm { padding: 7px 13px; font-size: 12.5px; }
.ap-btn-icon { width: 32px; height: 32px; padding: 0; display: grid; place-items: center; border-radius: 8px; }

/* ── KPI GRID ── */
.ap-kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
.ap-kpi-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 20px 22px; position: relative; overflow: hidden; transition: border-color .2s, transform .2s;
}
.ap-kpi-card:hover { border-color: var(--border2); transform: translateY(-2px); }
.ap-kpi-card::before { content:''; position:absolute; top:0;left:0;right:0;height:2px; background:var(--kpi-color,var(--accent)); border-radius:var(--radius) var(--radius) 0 0; }
.ap-kpi-top { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px; }
.ap-kpi-icon { width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:15px; background:var(--kpi-bg,var(--accent-dim));color:var(--kpi-color,var(--accent)); }
.ap-kpi-value { font-family:var(--font-d);font-size:28px;font-weight:700;letter-spacing:-.5px;line-height:1;margin-bottom:4px; }
.ap-kpi-label { font-size:12.5px;color:var(--text-2); }

/* ── MIDDLE ROW ── */
.ap-middle-row { display:grid;grid-template-columns:1fr 320px;gap:16px;margin-bottom:28px; }

/* ── CARD ── */
.ap-card { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden; }
.ap-card-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border); }
.ap-card-title { font-family:var(--font-d);font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px; }
.ap-card-title .dot { width:8px;height:8px;border-radius:50%;background:var(--accent);flex-shrink:0; }
.ap-card-body { padding:20px; }

/* ── CHARTS ── */
.ap-charts-body { padding:16px 20px 20px;display:grid;grid-template-columns:1fr 1fr;gap:20px; }
.ap-chart-label { font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px; }
.ap-chart-wrap { height:160px;position:relative; }

/* ── FILTERS ── */
.ap-filter-body { padding:16px 18px;display:flex;flex-direction:column;gap:14px; }
.ap-field label { font-size:11.5px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.7px;display:block;margin-bottom:6px; }
.ap-field select, .ap-field input[type=text], .ap-field input[type=date] {
  width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);
  color:var(--text);font-family:var(--font-b);font-size:13px;padding:9px 12px;
  appearance:none;outline:none;transition:border-color .15s;
}
.ap-field select:focus, .ap-field input:focus { border-color:var(--accent); }
.ap-date-range { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.ap-date-range label { grid-column:1/-1; }

/* ── STATUS TABS ── */
.ap-tabs-row { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:14px 20px 0; }
.ap-tabs { display:flex;gap:3px;background:var(--surface);padding:3px;border-radius:var(--radius-sm);border:1px solid var(--border); }
.ap-tab {
  padding:6px 13px;border-radius:6px;font-size:12.5px;font-weight:500;
  cursor:pointer;color:var(--text-2);transition:all .15s;border:none;background:none;
  font-family:var(--font-b);display:flex;align-items:center;gap:6px;
}
.ap-tab:hover { color:var(--text);background:var(--surface2); }
.ap-tab.active { background:var(--accent);color:#fff; }
.ap-tab .cnt { font-size:10.5px;opacity:.75;background:rgba(255,255,255,.18);padding:1px 6px;border-radius:99px; }

/* ── TABLE SEARCH ── */
.ap-search-wrap { display:flex;align-items:center;gap:0;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;transition:border-color .15s; }
.ap-search-wrap:focus-within { border-color:var(--accent); }
.ap-search-wrap i { padding:0 11px;color:var(--text-3);font-size:13px; }
.ap-search-wrap input { background:none;border:none;outline:none;color:var(--text);font-family:var(--font-b);font-size:13px;padding:9px 12px 9px 0;width:220px; }
.ap-search-wrap input::placeholder { color:var(--text-3); }

/* ── TABLE ── */
.ap-table-wrap { overflow-x:auto;padding:16px 20px 20px; }
table.ap-table { width:100%;border-collapse:collapse; }
.ap-table thead th { font-size:11px;font-weight:600;letter-spacing:.9px;text-transform:uppercase;color:var(--text-3);padding:10px 14px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap; }
.ap-table tbody tr { border-bottom:1px solid var(--border);transition:background .12s; }
.ap-table tbody tr:last-child { border-bottom:none; }
.ap-table tbody tr:hover { background:var(--surface2); }
.ap-table tbody td { padding:13px 14px;vertical-align:middle;font-size:13.5px; }

/* Asset name cell */
.ap-asset-cell { display:flex;align-items:center;gap:11px; }
.ap-thumb { width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:14px;flex-shrink:0; }
.ap-asset-name { font-weight:500;color:var(--text);font-size:13.5px; }
.ap-asset-serial { font-size:11.5px;color:var(--text-3);margin-top:1px; }

/* Value cell */
.ap-val-amount { font-weight:600;font-family:var(--font-d);font-size:14px; }
.ap-val-currency { font-size:11px;color:var(--text-3);margin-left:3px; }
.ap-depr-bar { height:3px;background:var(--surface3);border-radius:99px;margin-top:6px;overflow:hidden; }
.ap-depr-fill { height:100%;border-radius:99px; }

/* Chips */
.ap-chip { display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:500;background:var(--surface2);color:var(--text-2);border:1px solid var(--border); }

/* Status badge */
.ap-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:99px;font-size:11.5px;font-weight:500; }
.ap-badge .dot { width:5px;height:5px;border-radius:50;border-radius:50%; }
.ap-badge.active      { background:rgba(45,212,170,.12);color:var(--green); }
.ap-badge.active .dot { background:var(--green);box-shadow:0 0 4px var(--green); }
.ap-badge.inactive      { background:rgba(139,147,168,.1);color:var(--text-2); }
.ap-badge.inactive .dot { background:var(--text-3); }
.ap-badge.maintenance      { background:rgba(245,197,66,.12);color:var(--yellow); }
.ap-badge.maintenance .dot { background:var(--yellow); }
.ap-badge.sold      { background:rgba(56,199,232,.12);color:var(--cyan); }
.ap-badge.sold .dot { background:var(--cyan); }
.ap-badge.disposed      { background:rgba(240,90,106,.12);color:var(--red); }
.ap-badge.disposed .dot { background:var(--red); }

/* Condition dots */
.ap-cond-dots { display:flex;gap:3px; }
.ap-cond-dot { width:6px;height:6px;border-radius:50%;background:var(--border2); }
.ap-cond-dot.on { background:var(--green); }

/* Row actions */
.ap-row-actions { display:flex;align-items:center;gap:5px; }
.ap-row-btn { width:30px;height:30px;border-radius:7px;display:grid;place-items:center;background:var(--surface2);color:var(--text-3);cursor:pointer;font-size:12px;transition:all .15s;border:1px solid var(--border); }
.ap-row-btn:hover { border-color:var(--border2);color:var(--text); }
.ap-row-btn.edit:hover  { background:rgba(245,197,66,.12);color:var(--yellow);border-color:rgba(245,197,66,.2); }
.ap-row-btn.view:hover  { background:rgba(79,127,255,.12);color:var(--accent);border-color:rgba(79,127,255,.2); }

/* Dropdown */
.ap-dd-wrap { position:relative; }
.ap-dd-menu { display:none;position:absolute;right:0;top:calc(100% + 5px);background:var(--surface2);border:1px solid var(--border2);border-radius:var(--radius-sm);min-width:175px;z-index:200;box-shadow:0 8px 24px rgba(0,0,0,.45);overflow:hidden; }
.ap-dd-menu.open { display:block; }
.ap-dd-menu button, .ap-dd-menu a { display:flex;align-items:center;gap:9px;padding:10px 14px;width:100%;background:none;border:none;text-align:left;font-family:var(--font-b);font-size:13px;color:var(--text-2);cursor:pointer;text-decoration:none;transition:background .12s,color .12s; }
.ap-dd-menu button:hover, .ap-dd-menu a:hover { background:var(--surface3);color:var(--text); }
.ap-dd-menu .ap-dd-divider { border-top:1px solid var(--border);margin:4px 0; }
.ap-dd-menu .ap-danger { color:var(--red) !important; }
.ap-dd-menu .ap-danger:hover { background:rgba(240,90,106,.1) !important; }

/* Empty state */
.ap-empty { text-align:center;padding:60px 20px;color:var(--text-3); }
.ap-empty i { font-size:36px;margin-bottom:12px;display:block;opacity:.35; }

/* ── MODAL ── */
.ap-overlay { display:none;position:fixed;inset:0;z-index:1050;background:rgba(0,0,0,.7);backdrop-filter:blur(5px);align-items:center;justify-content:center; }
.ap-overlay.open { display:flex; }
.ap-modal { background:var(--surface);border:1px solid var(--border2);border-radius:var(--radius-lg);width:660px;max-width:96vw;max-height:92vh;overflow-y:auto;animation:apModalIn .2s ease; }
@keyframes apModalIn { from{opacity:0;transform:translateY(14px) scale(.97)} to{opacity:1;transform:none} }
.ap-modal-head { display:flex;align-items:center;justify-content:space-between;padding:22px 26px 16px;border-bottom:1px solid var(--border);position:sticky;top:0;background:var(--surface);z-index:2; }
.ap-modal-title { font-family:var(--font-d);font-size:17px;font-weight:700; }
.ap-modal-close { background:none;border:none;color:var(--text-2);cursor:pointer;font-size:18px;line-height:1;transition:color .15s; }
.ap-modal-close:hover { color:var(--text); }
.ap-modal-body { padding:22px 26px; }
.ap-modal-footer { padding:16px 26px 22px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border); }

/* Form */
.ap-form-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px; }
.ap-form-grid.c3 { grid-template-columns:1fr 1fr 1fr; }
.ap-form-grid.c1 { grid-template-columns:1fr; }
.ap-form-group { display:flex;flex-direction:column;gap:6px; }
.ap-label { font-size:11.5px;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:.7px; }
.ap-label .req { color:var(--accent);margin-left:2px; }
.ap-input, .ap-select, .ap-textarea {
  background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);
  color:var(--text);font-family:var(--font-b);font-size:13.5px;padding:10px 13px;outline:none;
  transition:border-color .15s,box-shadow .15s;width:100%;
}
.ap-input:focus, .ap-select:focus, .ap-textarea:focus { border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-dim); }
.ap-select { appearance:none;cursor:pointer; }
.ap-textarea { resize:vertical;min-height:80px; }

/* File drop */
.ap-file-drop { border:1.5px dashed var(--border2);border-radius:var(--radius-sm);padding:18px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s; }
.ap-file-drop:hover { border-color:var(--accent);background:var(--accent-dim); }
.ap-file-drop i { font-size:22px;color:var(--text-3);margin-bottom:6px;display:block; }
.ap-file-drop p { font-size:12.5px;color:var(--text-2); }
.ap-file-drop span { color:var(--accent); }

/* View detail grid */
.ap-view-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px; }
.ap-view-field .ap-vf-label { font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.7px;margin-bottom:4px; }
.ap-view-field .ap-vf-val { font-size:13.5px; }
.ap-depr-block { background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px; }
.ap-depr-block-label { font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px; }
.ap-depr-block-row { display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:13px; }
.ap-depr-track { height:6px;background:var(--surface3);border-radius:99px;overflow:hidden; }
.ap-depr-progress { height:100%;border-radius:99px; }

/* Doc link */
.ap-doc-link { display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:var(--radius-sm);background:var(--accent-dim);color:var(--accent);border:1px solid rgba(79,127,255,.2);font-size:13px;text-decoration:none;transition:background .15s; }
.ap-doc-link:hover { background:rgba(79,127,255,.2); }

/* Toast */
.ap-toasts { position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none; }
.ap-toast { background:var(--surface2);border:1px solid var(--border2);border-radius:var(--radius-sm);padding:13px 16px;display:flex;align-items:center;gap:10px;font-size:13.5px;box-shadow:0 8px 24px rgba(0,0,0,.4);animation:apFadeUp .25s ease;min-width:270px;pointer-events:all; }
.ap-toast.success { border-left:3px solid var(--green); }
.ap-toast.error   { border-left:3px solid var(--red); }
.ap-toast.success i { color:var(--green); }
.ap-toast.error   i { color:var(--red); }

/* Currency summary pills */
.ap-currency-pills { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px; }
.ap-currency-pill { display:flex;align-items:center;gap:10px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 16px; }
.ap-currency-pill .label { font-size:11.5px;color:var(--text-3); }
.ap-currency-pill .val { font-family:var(--font-d);font-weight:700;font-size:17px; }
.ap-currency-pill .cur { font-size:11.5px;color:var(--text-2); }

/* Animations */
@keyframes apFadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.ap-anim  { animation:apFadeUp .3s ease both; }
.ap-a1 { animation-delay:.04s; }
.ap-a2 { animation-delay:.09s; }
.ap-a3 { animation-delay:.14s; }
.ap-a4 { animation-delay:.19s; }
.ap-a5 { animation-delay:.24s; }

/* Responsive */
@media(max-width:1100px){ .ap-kpi-grid{grid-template-columns:1fr 1fr;} .ap-middle-row{grid-template-columns:1fr;} }
@media(max-width:700px){ .ap-kpi-grid{grid-template-columns:1fr 1fr;} .ap-charts-body{grid-template-columns:1fr;} .ap-form-grid{grid-template-columns:1fr;} .ap-form-grid.c3{grid-template-columns:1fr 1fr;} }
</style>

<!-- pcoded wrapper so header.php layout works -->
<div class="pcoded-main-container">
<div class="main-body">
<div class="page-wrapper">

<div class="assets-page">

  <!-- Breadcrumb -->
  <div class="ap-breadcrumb">
    <a href="dashboard.php"><i class="fa-solid fa-house" style="font-size:11px;"></i></a>
    <span class="sep">›</span>
    <span style="color:var(--text-2);"><?= __('assets') ?></span>
  </div>

  <!-- Page header -->
  <div class="ap-page-header ap-anim">
    <div>
      <h1><?= __('company') ?> <span><?= __('assets') ?></span></h1>
      <p class="subtitle"><?= __('manage_and_track_company_assets') ?></p>
    </div>
    <button class="ap-btn ap-btn-primary" onclick="apOpenModal('apAddModal')">
      <i class="fa-solid fa-plus"></i> <?= __('add_new_asset') ?>
    </button>
  </div>

  <!-- Alerts -->
  <?php if ($success_message): ?>
  <div class="ap-alert success ap-anim" id="apAlertSuccess">
    <i class="fa-solid fa-circle-check"></i>
    <?= h($success_message) ?>
    <button class="ap-alert-close" onclick="document.getElementById('apAlertSuccess').remove()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <?php endif; ?>
  <?php if ($error_message): ?>
  <div class="ap-alert error ap-anim" id="apAlertError">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= h($error_message) ?>
    <button class="ap-alert-close" onclick="document.getElementById('apAlertError').remove()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="ap-kpi-grid">
    <div class="ap-kpi-card ap-anim ap-a1" style="--kpi-color:var(--accent);--kpi-bg:var(--accent-dim);">
      <div class="ap-kpi-top">
        <div class="ap-kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
      </div>
      <div class="ap-kpi-value"><?= $status_counts['all'] ?></div>
      <div class="ap-kpi-label"><?= __('total_assets') ?></div>
    </div>
    <div class="ap-kpi-card ap-anim ap-a2" style="--kpi-color:var(--green);--kpi-bg:rgba(45,212,170,.12);">
      <div class="ap-kpi-top">
        <div class="ap-kpi-icon"><i class="fa-solid fa-circle-check"></i></div>
      </div>
      <div class="ap-kpi-value"><?= $status_counts['active'] ?></div>
      <div class="ap-kpi-label"><?= __('active') ?> <?= __('assets') ?></div>
    </div>
    <div class="ap-kpi-card ap-anim ap-a3" style="--kpi-color:var(--yellow);--kpi-bg:rgba(245,197,66,.12);">
      <div class="ap-kpi-top">
        <div class="ap-kpi-icon"><i class="fa-solid fa-wrench"></i></div>
      </div>
      <div class="ap-kpi-value"><?= $status_counts['maintenance'] ?></div>
      <div class="ap-kpi-label"><?= __('maintenance') ?></div>
    </div>
    <div class="ap-kpi-card ap-anim ap-a4" style="--kpi-color:var(--red);--kpi-bg:rgba(240,90,106,.12);">
      <div class="ap-kpi-top">
        <div class="ap-kpi-icon"><i class="fa-solid fa-ban"></i></div>
      </div>
      <div class="ap-kpi-value"><?= $status_counts['inactive'] + $status_counts['disposed'] ?></div>
      <div class="ap-kpi-label"><?= __('inactive') ?> / <?= __('disposed') ?></div>
    </div>
  </div>

  <!-- Currency value pills -->
  <?php if (!empty($currency_totals)): ?>
  <div class="ap-currency-pills ap-anim ap-a2">
    <?php foreach ($currency_totals as $cur => $total): ?>
    <div class="ap-currency-pill">
      <i class="fa-solid fa-coins" style="color:var(--yellow);font-size:13px;"></i>
      <span class="label"><?= __('total_value') ?></span>
      <span class="val"><?= number_format($total, 0) ?></span>
      <span class="cur"><?= h($cur) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Charts + Filters -->
  <div class="ap-middle-row ap-anim ap-a3">
    <!-- Charts -->
    <div class="ap-card">
      <div class="ap-card-header">
        <div class="ap-card-title"><span class="dot"></span> <?= __('asset_categories') ?> &amp; <?= __('asset_status_distribution') ?></div>
      </div>
      <div class="ap-charts-body">
        <div>
          <div class="ap-chart-label"><?= __('by_category') ?></div>
          <div class="ap-chart-wrap"><canvas id="apCategoryChart"></canvas></div>
        </div>
        <div>
          <div class="ap-chart-label"><?= __('by_status') ?></div>
          <div class="ap-chart-wrap"><canvas id="apStatusChart"></canvas></div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="ap-card">
      <div class="ap-card-header">
        <div class="ap-card-title"><span class="dot" style="background:var(--purple);"></span> <?= __('advanced_search') ?></div>
        <button class="ap-btn ap-btn-ghost ap-btn-sm" id="apClearBtn"><?= __('clear') ?></button>
      </div>
      <div class="ap-filter-body">
        <div class="ap-field">
          <label><?= __('category') ?></label>
          <select id="apFilterCat">
            <option value=""><?= __('all_categories') ?></option>
            <option>Electronics</option><option>Furniture</option><option>Vehicle</option>
            <option>Office Equipment</option><option>Real Estate</option><option>Software</option><option>Other</option>
          </select>
        </div>
        <div class="ap-field">
          <label><?= __('location') ?></label>
          <input type="text" id="apFilterLoc" placeholder="<?= __('filter_by_location') ?>…">
        </div>
        <div class="ap-field ap-date-range">
          <label><?= __('purchase_date_from') ?> — <?= __('purchase_date_to') ?></label>
          <input type="date" id="apFilterFrom">
          <input type="date" id="apFilterTo">
        </div>
        <div class="ap-field">
          <label><?= __('condition') ?></label>
          <select id="apFilterCond">
            <option value=""><?= __('any_condition') ?></option>
            <option>New</option><option>Excellent</option><option>Good</option><option>Fair</option><option>Poor</option>
          </select>
        </div>
        <button class="ap-btn ap-btn-primary" onclick="apApplyFilters()" style="justify-content:center;">
          <i class="fa-solid fa-filter"></i> <?= __('apply_filters') ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Assets Table Card -->
  <div class="ap-card ap-anim ap-a5">
    <div class="ap-card-header" style="flex-wrap:wrap;gap:12px;">
      <div class="ap-card-title"><span class="dot" style="background:var(--green);"></span> <?= __('company_assets') ?></div>
      <div class="ap-search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="apSearch" placeholder="<?= __('search') ?>…" oninput="apApplyFilters()">
      </div>
    </div>

    <!-- Status tabs -->
    <div class="ap-tabs-row">
      <div class="ap-tabs">
        <?php
        $tabs = [
          'all'         => __('all'),
          'active'      => __('active'),
          'maintenance' => __('maintenance'),
          'inactive'    => __('inactive'),
          'sold'        => __('sold'),
          'disposed'    => __('disposed'),
        ];
        foreach ($tabs as $tval => $tlabel):
          $active = ($status_filter === $tval) ? 'active' : '';
        ?>
        <a href="assets.php?status=<?= $tval ?>" class="ap-tab <?= $active ?>">
          <?= $tlabel ?> <span class="cnt"><?= $status_counts[$tval] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="ap-table-wrap">
      <table class="ap-table" id="apTable">
        <thead>
          <tr>
            <th><?= __('name') ?></th>
            <th><?= __('category') ?></th>
            <th><?= __('purchase_date') ?></th>
            <th><?= __('current_value') ?></th>
            <th><?= __('location') ?></th>
            <th><?= __('condition') ?></th>
            <th><?= __('status') ?></th>
            <th><?= __('actions') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($assets)): ?>
          <tr><td colspan="8"><div class="ap-empty"><i class="fa-solid fa-inbox"></i><p><?= __('no_assets_found') ?></p></div></td></tr>
        <?php else: ?>
          <?php foreach ($assets as $a):
            $depr = ($a['purchase_value'] > 0) ? max(0, 100 - ($a['current_value'] / $a['purchase_value'] * 100)) : 0;
            $deprColor = $depr < 25 ? 'var(--green)' : ($depr < 50 ? 'var(--yellow)' : ($depr < 75 ? 'var(--orange)' : 'var(--red)'));
            $catIcons  = ['Electronics'=>'fa-laptop','Furniture'=>'fa-couch','Vehicle'=>'fa-truck','Office Equipment'=>'fa-print','Real Estate'=>'fa-building','Software'=>'fa-code','Other'=>'fa-box'];
            $catColors = ['Electronics'=>'#38c7e8','Furniture'=>'#2dd4aa','Vehicle'=>'#f05a6a','Office Equipment'=>'#f5c542','Real Estate'=>'#9b72f2','Software'=>'#4f7fff','Other'=>'#8b93a8'];
            $icon  = $catIcons[$a['category']]  ?? 'fa-box';
            $color = $catColors[$a['category']] ?? '#8b93a8';
            $condMap = ['New'=>5,'Excellent'=>5,'Good'=>4,'Fair'=>3,'Poor'=>1];
            $condFill = $condMap[$a['condition_state']] ?? 3;
          ?>
          <tr data-status="<?= h($a['status']) ?>" data-category="<?= h($a['category']) ?>" data-location="<?= h(strtolower($a['location'])) ?>" data-name="<?= h(strtolower($a['name'])) ?>" data-serial="<?= h(strtolower($a['serial_number'])) ?>" data-date="<?= h($a['purchase_date']) ?>">
            <td>
              <div class="ap-asset-cell">
                <div class="ap-thumb" style="background:<?= $color ?>1a;color:<?= $color ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
                <div>
                  <div class="ap-asset-name"><?= htmlspecialchars($a['name']) ?></div>
                  <?php if ($a['serial_number']): ?><div class="ap-asset-serial">SN: <?= htmlspecialchars($a['serial_number']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="ap-chip"><?= htmlspecialchars($a['category']) ?></span></td>
            <td style="color:var(--text-2);"><?= date('M d, Y', strtotime($a['purchase_date'])) ?></td>
            <td>
              <span class="ap-val-amount"><?= number_format($a['current_value'], 0) ?></span>
              <span class="ap-val-currency"><?= h($a['currency']) ?></span>
              <div class="ap-depr-bar"><div class="ap-depr-fill" style="width:<?= min(100,$depr) ?>%;background:<?= $deprColor ?>;"></div></div>
            </td>
            <td style="color:var(--text-2);"><?= htmlspecialchars($a['location'] ?: '—') ?></td>
            <td>
              <div class="ap-cond-dots">
                <?php for ($d=1;$d<=5;$d++): ?>
                <div class="ap-cond-dot <?= $d <= $condFill ? 'on' : '' ?>"></div>
                <?php endfor; ?>
              </div>
            </td>
            <td><span class="ap-badge <?= h($a['status']) ?>"><span class="dot"></span><?= ucfirst($a['status']) ?></span></td>
            <td>
              <div class="ap-row-actions">
                <!-- View -->
                <button type="button" class="ap-row-btn view" title="<?= __('view_details') ?>"
                  onclick="apOpenView(<?= htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8') ?>)">
                  <i class="fa-solid fa-eye"></i>
                </button>
                <!-- Edit -->
                <button type="button" class="ap-row-btn edit" title="<?= __('edit_asset') ?>"
                  onclick="apOpenModal('apEditModal<?= h($a['id']) ?>')">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <!-- More -->
                <div class="ap-dd-wrap">
                  <button type="button" class="ap-row-btn" title="<?= __('more') ?>"
                    onclick="apToggleDd(event,'apDd<?= h($a['id']) ?>')">
                    <i class="fa-solid fa-ellipsis"></i>
                  </button>
                  <div class="ap-dd-menu" id="apDd<?= h($a['id']) ?>">
                    <?php if ($a['status'] === 'active'): ?>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <button type="submit" name="deactivate_asset" onclick="return confirm('<?= __('are_you_sure_you_want_to_deactivate_this_asset') ?>')">
                        <i class="fa-solid fa-circle-xmark" style="color:var(--text-3);width:14px;"></i> <?= __('mark_as_inactive') ?>
                      </button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <input type="hidden" name="new_status" value="maintenance">
                      <button type="submit" name="change_status" onclick="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_in_maintenance') ?>')">
                        <i class="fa-solid fa-wrench" style="color:var(--yellow);width:14px;"></i> <?= __('mark_as_in_maintenance') ?>
                      </button>
                    </form>
                    <?php elseif ($a['status'] === 'inactive'): ?>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <button type="submit" name="reactivate_asset" onclick="return confirm('<?= __('are_you_sure_you_want_to_reactivate_this_asset') ?>')">
                        <i class="fa-solid fa-circle-check" style="color:var(--green);width:14px;"></i> <?= __('mark_as_active') ?>
                      </button>
                    </form>
                    <?php elseif ($a['status'] === 'maintenance'): ?>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <input type="hidden" name="new_status" value="active">
                      <button type="submit" name="change_status" onclick="return confirm('<?= __('are_you_sure_you_want_to_reactivate_this_asset') ?>')">
                        <i class="fa-solid fa-circle-check" style="color:var(--green);width:14px;"></i> <?= __('mark_as_active') ?>
                      </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <input type="hidden" name="new_status" value="sold">
                      <button type="submit" name="change_status" onclick="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_sold') ?>')">
                        <i class="fa-solid fa-tag" style="color:var(--cyan);width:14px;"></i> <?= __('mark_as_sold') ?>
                      </button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <input type="hidden" name="new_status" value="disposed">
                      <button type="submit" name="change_status" onclick="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_disposed') ?>')">
                        <i class="fa-solid fa-trash-can" style="color:var(--orange);width:14px;"></i> <?= __('mark_as_disposed') ?>
                      </button>
                    </form>
                    <div class="ap-dd-divider"></div>
                    <form method="POST">
                      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
                      <button type="submit" name="delete_asset" class="ap-danger" onclick="return confirm('<?= __('are_you_sure_you_want_to_delete_this_asset') ?>')">
                        <i class="fa-solid fa-trash" style="width:14px;"></i> <?= __('delete_asset') ?>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /table card -->

</div><!-- /assets-page -->
</div></div></div>

<!-- ══════════════════════════════════════════════════════
     ADD MODAL
═══════════════════════════════════════════════════════ -->
<div class="ap-overlay" id="apAddModal">
  <div class="ap-modal" onclick="event.stopPropagation()">
    <div class="ap-modal-head">
      <div class="ap-modal-title"><?= __('add_new_asset') ?></div>
      <button class="ap-modal-close" onclick="apCloseModal('apAddModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <div class="ap-modal-body">
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('asset_name') ?> <span class="req">*</span></label>
            <input class="ap-input" type="text" name="name" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('category') ?> <span class="req">*</span></label>
            <select class="ap-select" name="category" required>
              <option value=""><?= __('select_category') ?></option>
              <option>Electronics</option><option>Furniture</option><option>Vehicle</option>
              <option>Office Equipment</option><option>Real Estate</option><option>Software</option><option>Other</option>
            </select>
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('purchase_date') ?> <span class="req">*</span></label>
            <input class="ap-input" type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('warranty_expiry_date') ?></label>
            <input class="ap-input" type="date" name="warranty_expiry">
          </div>
        </div>
        <div class="ap-form-grid c3">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('purchase_value') ?> <span class="req">*</span></label>
            <input class="ap-input" type="number" step="0.01" name="purchase_value" id="apAddPurchase" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('current_value') ?> <span class="req">*</span></label>
            <input class="ap-input" type="number" step="0.01" name="current_value" id="apAddCurrent" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('currency') ?></label>
            <select class="ap-select" name="currency">
              <option value="USD">USD</option><option value="EUR">EUR</option>
              <option value="AFS">AFS</option><option value="DARHAM">DARHAM</option>
              <option value="PKR">PKR</option><option value="INR">INR</option>
            </select>
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('location') ?></label>
            <input class="ap-input" type="text" name="location">
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('serial_number') ?></label>
            <input class="ap-input" type="text" name="serial_number">
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('status') ?></label>
            <select class="ap-select" name="status">
              <option value="active"><?= __('active') ?></option>
              <option value="inactive"><?= __('inactive') ?></option>
              <option value="maintenance"><?= __('maintenance') ?></option>
              <option value="sold"><?= __('sold') ?></option>
              <option value="disposed"><?= __('disposed') ?></option>
            </select>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('condition') ?></label>
            <select class="ap-select" name="condition_state">
              <option value=""><?= __('select_condition') ?></option>
              <option>New</option><option>Excellent</option><option>Good</option><option>Fair</option><option>Poor</option>
            </select>
          </div>
        </div>
        <div class="ap-form-grid c1">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('assigned_to') ?></label>
            <input class="ap-input" type="text" name="assigned_to">
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('description') ?></label>
            <textarea class="ap-textarea" name="description"></textarea>
          </div>
        </div>
        <div class="ap-form-group">
          <label class="ap-label"><?= __('document') ?></label>
          <div class="ap-file-drop" onclick="document.getElementById('apFileAdd').click()">
            <input id="apFileAdd" type="file" name="document" style="display:none" onchange="apFileLabel(this,'apFileLabelAdd')">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p><span><?= __('click_to_upload') ?></span> <?= __('or_drag_and_drop') ?></p>
            <p id="apFileLabelAdd" style="font-size:11.5px;color:var(--text-3);margin-top:3px;">PDF, DOC, JPG, PNG — max 10MB</p>
          </div>
        </div>
      </div>
      <div class="ap-modal-footer">
        <button type="button" class="ap-btn ap-btn-ghost" onclick="apCloseModal('apAddModal')"><?= __('cancel') ?></button>
        <button type="submit" name="add_asset" class="ap-btn ap-btn-primary"><i class="fa-solid fa-plus"></i> <?= __('add_asset') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     VIEW MODAL
═══════════════════════════════════════════════════════ -->
<div class="ap-overlay" id="apViewModal">
  <div class="ap-modal" onclick="event.stopPropagation()">
    <div class="ap-modal-head">
      <div class="ap-modal-title" id="apViewTitle"><?= __('asset_details') ?></div>
      <button class="ap-modal-close" onclick="apCloseModal('apViewModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="ap-modal-body" id="apViewBody"></div>
    <div class="ap-modal-footer" id="apViewFooter">
      <button type="button" class="ap-btn ap-btn-ghost" onclick="apCloseModal('apViewModal')"><?= __('close') ?></button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     EDIT MODALS (one per asset)
═══════════════════════════════════════════════════════ -->
<?php foreach ($assets as $a): ?>
<div class="ap-overlay" id="apEditModal<?= h($a['id']) ?>">
  <div class="ap-modal" onclick="event.stopPropagation()">
    <div class="ap-modal-head">
      <div class="ap-modal-title"><?= __('edit_asset') ?> — <?= htmlspecialchars($a['name']) ?></div>
      <button class="ap-modal-close" onclick="apCloseModal('apEditModal<?= h($a['id']) ?>')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="asset_id"   value="<?= h($a['id']) ?>">
      <div class="ap-modal-body">
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('asset_name') ?> <span class="req">*</span></label>
            <input class="ap-input" type="text" name="name" value="<?= htmlspecialchars($a['name']) ?>" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('category') ?> <span class="req">*</span></label>
            <select class="ap-select" name="category" required>
              <?php foreach (['Electronics','Furniture','Vehicle','Office Equipment','Real Estate','Software','Other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= $a['category']==$cat?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('purchase_date') ?> <span class="req">*</span></label>
            <input class="ap-input" type="date" name="purchase_date" value="<?= h($a['purchase_date']) ?>" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('warranty_expiry_date') ?></label>
            <input class="ap-input" type="date" name="warranty_expiry" value="<?= h($a['warranty_expiry']) ?>">
          </div>
        </div>
        <div class="ap-form-grid c3">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('purchase_value') ?> <span class="req">*</span></label>
            <input class="ap-input" type="number" step="0.01" name="purchase_value" value="<?= h($a['purchase_value']) ?>" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('current_value') ?> <span class="req">*</span></label>
            <input class="ap-input" type="number" step="0.01" name="current_value" value="<?= h($a['current_value']) ?>" required>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('currency') ?></label>
            <select class="ap-select" name="currency">
              <?php foreach (['USD','EUR','AFS','DARHAM','PKR','INR'] as $c): ?>
              <option value="<?= $c ?>" <?= $a['currency']==$c?'selected':'' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('location') ?></label>
            <input class="ap-input" type="text" name="location" value="<?= htmlspecialchars($a['location']) ?>">
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('serial_number') ?></label>
            <input class="ap-input" type="text" name="serial_number" value="<?= htmlspecialchars($a['serial_number']) ?>">
          </div>
        </div>
        <div class="ap-form-grid">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('status') ?></label>
            <select class="ap-select" name="status">
              <?php foreach (['active','inactive','maintenance','sold','disposed'] as $s): ?>
              <option value="<?= $s ?>" <?= $a['status']==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('condition') ?></label>
            <select class="ap-select" name="condition_state">
              <?php foreach (['New','Excellent','Good','Fair','Poor'] as $cnd): ?>
              <option value="<?= $cnd ?>" <?= $a['condition_state']==$cnd?'selected':'' ?>><?= $cnd ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ap-form-grid c1">
          <div class="ap-form-group">
            <label class="ap-label"><?= __('assigned_to') ?></label>
            <input class="ap-input" type="text" name="assigned_to" value="<?= htmlspecialchars($a['assigned_to']) ?>">
          </div>
          <div class="ap-form-group">
            <label class="ap-label"><?= __('description') ?></label>
            <textarea class="ap-textarea" name="description"><?= htmlspecialchars($a['description']) ?></textarea>
          </div>
        </div>
        <div class="ap-form-group">
          <label class="ap-label"><?= __('document') ?></label>
          <?php if (!empty($a['document'])): ?>
          <div style="margin-bottom:8px;">
            <a href="../uploads/assets/<?= h($a['document']) ?>" target="_blank" class="ap-doc-link">
              <i class="fa-solid fa-file"></i> <?= __('current_document') ?>
            </a>
          </div>
          <?php endif; ?>
          <div class="ap-file-drop" onclick="document.getElementById('apFileEdit<?= h($a['id']) ?>').click()">
            <input id="apFileEdit<?= h($a['id']) ?>" type="file" name="document" style="display:none"
              onchange="apFileLabel(this,'apFileLabelEdit<?= h($a['id']) ?>')">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p><span><?= __('click_to_upload') ?></span> — <?= __('replaces_existing') ?></p>
            <p id="apFileLabelEdit<?= h($a['id']) ?>" style="font-size:11.5px;color:var(--text-3);margin-top:3px;">PDF, DOC, JPG, PNG</p>
          </div>
        </div>
      </div>
      <div class="ap-modal-footer">
        <button type="button" class="ap-btn ap-btn-ghost" onclick="apCloseModal('apEditModal<?= h($a['id']) ?>')"><?= __('cancel') ?></button>
        <button type="submit" name="edit_asset" class="ap-btn ap-btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?= __('update_asset') ?></button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Toasts -->
<div class="ap-toasts" id="apToasts"></div>

<!-- ══════════════════════════════════════════════════════
     SCRIPTS
═══════════════════════════════════════════════════════ -->
<script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── DATA FROM PHP ── */
const apCategoryData = <?= json_encode($all_categories, JSON_UNESCAPED_UNICODE) ?>;
const apStatusData   = <?= json_encode($status_chart_data, JSON_UNESCAPED_UNICODE) ?>;

/* ── MODAL ── */
function apOpenModal(id) { document.getElementById(id).classList.add('open'); }
function apCloseModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.ap-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.ap-overlay.open').forEach(o => o.classList.remove('open'));
});

/* ── DROPDOWN ── */
function apToggleDd(e, id) {
  e.stopPropagation();
  const menu = document.getElementById(id);
  const isOpen = menu.classList.contains('open');
  document.querySelectorAll('.ap-dd-menu').forEach(m => m.classList.remove('open'));
  if (!isOpen) menu.classList.add('open');
}
document.addEventListener('click', () => document.querySelectorAll('.ap-dd-menu').forEach(m => m.classList.remove('open')));

/* ── FILE LABEL ── */
function apFileLabel(input, labelId) {
  const label = document.getElementById(labelId);
  if (label && input.files.length) label.textContent = input.files[0].name;
}

/* ── ADD: auto-fill current value from purchase value ── */
const apAddPurchase = document.getElementById('apAddPurchase');
const apAddCurrent  = document.getElementById('apAddCurrent');
if (apAddPurchase && apAddCurrent) {
  apAddPurchase.addEventListener('input', () => { if (!apAddCurrent.value) apAddCurrent.value = apAddPurchase.value; });
}

/* ── CLIENT-SIDE FILTER ── */
function apApplyFilters() {
  const cat   = document.getElementById('apFilterCat')?.value  || '';
  const loc   = (document.getElementById('apFilterLoc')?.value || '').toLowerCase();
  const cond  = document.getElementById('apFilterCond')?.value || '';
  const from  = document.getElementById('apFilterFrom')?.value || '';
  const to    = document.getElementById('apFilterTo')?.value   || '';
  const q     = (document.getElementById('apSearch')?.value    || '').toLowerCase();

  document.querySelectorAll('#apTable tbody tr').forEach(row => {
    const rCat  = row.dataset.category  || '';
    const rLoc  = row.dataset.location  || '';
    const rName = row.dataset.name      || '';
    const rSer  = row.dataset.serial    || '';
    const rDate = row.dataset.date      || '';
    // condition not stored in row data-attr, skip for now (server-side handles it)
    let show = true;
    if (cat  && rCat !== cat) show = false;
    if (loc  && !rLoc.includes(loc)) show = false;
    if (from && rDate < from) show = false;
    if (to   && rDate > to)   show = false;
    if (q    && !rName.includes(q) && !rSer.includes(q) && !rCat.toLowerCase().includes(q)) show = false;
    row.style.display = show ? '' : 'none';
  });
}

document.getElementById('apClearBtn')?.addEventListener('click', () => {
  ['apFilterCat','apFilterLoc','apFilterCond','apFilterFrom','apFilterTo'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  document.getElementById('apSearch').value = '';
  apApplyFilters();
});

/* ── VIEW MODAL ── */
function apOpenView(a) {
  document.getElementById('apViewTitle').textContent = a.name;
  const depr = a.purchase_value > 0 ? Math.max(0, 100 - (a.current_value / a.purchase_value * 100)).toFixed(1) : 0;
  const deprColor = depr < 25 ? 'var(--green)' : depr < 50 ? 'var(--yellow)' : depr < 75 ? 'var(--orange)' : 'var(--red)';
  const formatNum = n => Number(n).toLocaleString();
  const fmtDate   = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '-';
  function vf(label, val) {
    return `<div class="ap-view-field"><div class="ap-vf-label">${label}</div><div class="ap-vf-val">${val||'-'}</div></div>`;
  }
  document.getElementById('apViewBody').innerHTML = `
    <div class="ap-view-grid">
      ${vf('Name', a.name)}
      ${vf('Category', '<span class="ap-chip">' + a.category + '</span>')}
      ${vf('Purchase Date', fmtDate(a.purchase_date))}
      ${vf('Warranty Expiry', fmtDate(a.warranty_expiry))}
      ${vf('Purchase Value', formatNum(a.purchase_value)+' '+a.currency)}
      ${vf('Current Value',  formatNum(a.current_value) +' '+a.currency)}
      ${vf('Location', a.location)}
      ${vf('Serial Number', a.serial_number)}
      ${vf('Assigned To', a.assigned_to)}
      ${vf('Condition', a.condition_state)}
      ${vf('Status', '<span class="ap-badge ' + a.status + '"><span class="dot"></span>' + (a.status.charAt(0).toUpperCase()+a.status.slice(1)) + '</span>')}
      ${vf('Added On', fmtDate(a.created_at))}
    </div>
    ${a.description ? '<div style="margin-bottom:16px;">' + vf('Description', a.description) + '</div>' : ''}
    ${a.document ? '<div style="margin-bottom:16px;"><div class="ap-vf-label" style="font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px;">Document</div><a href="../uploads/assets/' + a.document + '" target="_blank" class="ap-doc-link"><i class="fa-solid fa-file"></i> View Document</a></div>' : ''}
    <div class="ap-depr-block">
      <div class="ap-depr-block-label">Depreciation</div>
      <div class="ap-depr-block-row">
        <span style="color:var(--text-2);font-size:13px;">${depr}% depreciated</span>
        <span style="font-family:var(--font-d);font-weight:700;">${formatNum(a.purchase_value - a.current_value)} ${a.currency} lost</span>
      </div>
      <div class="ap-depr-track"><div class="ap-depr-progress" style="width:${Math.min(100,depr)}%;background:${deprColor};"></div></div>
    </div>`;

  document.getElementById('apViewFooter').innerHTML = `
    <button type="button" class="ap-btn ap-btn-ghost" onclick="apCloseModal('apViewModal')">Close</button>
    <button type="button" class="ap-btn ap-btn-primary" onclick="apCloseModal('apViewModal');apOpenModal('apEditModal${a.id}')">
      <i class="fa-solid fa-pen"></i> Edit Asset
    </button>`;

  apOpenModal('apViewModal');
}

/* ── CHARTS ── */
(function initCharts() {
  const catLabels = Object.keys(apCategoryData);
  const catData   = Object.values(apCategoryData);
  const catColors = { Electronics:'#38c7e8', Furniture:'#2dd4aa', Vehicle:'#f05a6a', 'Office Equipment':'#f5c542', 'Real Estate':'#9b72f2', Software:'#4f7fff', Other:'#8b93a8' };
  const catBg = catLabels.map(k => catColors[k] || '#8b93a8');

  new Chart(document.getElementById('apCategoryChart'), {
    type: 'doughnut',
    data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: catBg, borderWidth: 0, hoverOffset: 5 }] },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: { legend: { position:'right', labels:{ color:'#8b93a8', font:{size:11}, boxWidth:10, padding:10 } } }
    }
  });

  const stLabels = Object.keys(apStatusData).map(s => s.charAt(0).toUpperCase()+s.slice(1));
  const stData   = Object.values(apStatusData);
  const stColors = { active:'#2dd4aa', inactive:'#5a6175', maintenance:'#f5c542', sold:'#38c7e8', disposed:'#f05a6a' };
  const stBg = Object.keys(apStatusData).map(k => stColors[k]);

  new Chart(document.getElementById('apStatusChart'), {
    type: 'bar',
    data: { labels: stLabels, datasets: [{ data: stData, backgroundColor: stBg, borderRadius: 6, borderWidth: 0 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid:{ display:false }, ticks:{ color:'#8b93a8', font:{size:11} } },
        y: { grid:{ color:'#2a2f3e', drawBorder:false }, ticks:{ color:'#8b93a8', stepSize:1, font:{size:11} }, border:{ dash:[4,4] } }
      }
    }
  });
})();

/* ── AUTO-DISMISS ALERTS ── */
setTimeout(() => {
  ['apAlertSuccess','apAlertError'].forEach(id => { const el = document.getElementById(id); if(el) el.remove(); });
}, 5000);
</script>

<?php include '../includes/admin_footer.php'; ?>