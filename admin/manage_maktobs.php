<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Get any flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : null;

// Clear flash messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Include database connection
require_once('../includes/db.php');
require_once('../includes/SecureFileUpload.php');

// Handle maktob submission directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $subject      = $_POST['subject']      ?? '';
    $content      = $_POST['content']      ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $maktob_number= $_POST['maktob_number']?? '';
    $maktob_date  = $_POST['maktob_date']  ?? '';
    $language     = $_POST['language']     ?? 'english';
    $sender_id    = $_SESSION['user_id'];

    $file_path = null;
    $pdf_path  = null;

    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploader = new SecureFileUpload(10 * 1024 * 1024, 'uploads/');
        $result = $uploader->upload('pdf_file', 'maktobs');
        if ($result['success']) {
            $pdf_path = 'uploads/maktobs/' . $result['data']['filename'];
        } else {
            $_SESSION['error_message'] = 'Failed to upload PDF file: ' . $result['error'];
            header('Location: ' . $_SERVER['PHP_SELF']); exit();
        }
    }

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploader = new SecureFileUpload(10 * 1024 * 1024, 'uploads/');
        $result = $uploader->upload('attachment', 'maktobs');
        if ($result['success']) {
            $file_path = 'uploads/maktobs/' . $result['data']['filename'];
        } else {
            $_SESSION['error_message'] = 'Failed to upload attachment: ' . $result['error'];
            header('Location: ' . $_SERVER['PHP_SELF']); exit();
        }
    }

    if (empty($company_name)) {
        $_SESSION['error_message'] = __('please_enter_company');
        header('Location: ' . $_SERVER['PHP_SELF']); exit();
    }
    if (empty($subject) || empty($content)) {
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']); exit();
    }

    if (empty($maktob_number) && !empty($maktob_date)) {
        try {
            $formattedDate = str_replace('-', '', $maktob_date);
            $baseNumber = $tenant_id . '-' . $branch_id . '-' . $formattedDate . '-';
            $stmt = $pdo->prepare("SELECT maktob_number FROM maktobs
                                   WHERE tenant_id = ? AND branch_id = ? AND maktob_number LIKE ?
                                   ORDER BY CAST(SUBSTRING_INDEX(maktob_number, '-', -1) AS UNSIGNED) DESC LIMIT 1");
            $stmt->execute([$tenant_id, $branch_id, $baseNumber . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $next_sequence = 1;
            if ($result) {
                $parts = explode('-', $result['maktob_number']);
                if (count($parts) >= 4) {
                    $last_part = end($parts);
                    if (is_numeric($last_part)) $next_sequence = intval($last_part) + 1;
                }
            }
            $maktob_number = $baseNumber . str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error generating maktob number';
            header('Location: ' . $_SERVER['PHP_SELF']); exit();
        }
    }

    if (empty($maktob_number) || empty($maktob_date) || !$sender_id) {
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']); exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maktob_date)) {
        $_SESSION['error_message'] = 'Invalid date format. Use YYYY-MM-DD';
        header('Location: ' . $_SERVER['PHP_SELF']); exit();
    }

    try {
        $query = "INSERT INTO maktobs (tenant_id, branch_id, subject, content, company_name, maktob_number, maktob_date, sender_id, status, language, file_path, pdf_path)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1,  $tenant_id,     PDO::PARAM_INT);
        $stmt->bindParam(2,  $branch_id,     PDO::PARAM_INT);
        $stmt->bindParam(3,  $subject,       PDO::PARAM_STR);
        $stmt->bindParam(4,  $content,       PDO::PARAM_STR);
        $stmt->bindParam(5,  $company_name,  PDO::PARAM_STR);
        $stmt->bindParam(6,  $maktob_number, PDO::PARAM_STR);
        $stmt->bindParam(7,  $maktob_date,   PDO::PARAM_STR);
        $stmt->bindParam(8,  $sender_id,     PDO::PARAM_INT);
        $stmt->bindParam(9,  $language,      PDO::PARAM_STR);
        $stmt->bindParam(10, $file_path,     PDO::PARAM_STR);
        $stmt->bindParam(11, $pdf_path,      PDO::PARAM_STR);

        if ($stmt->execute()) {
            $insert_id = $pdo->lastInsertId();
            try {
                $log_stmt = $pdo->prepare("INSERT INTO maktob_logs (tenant_id, maktob_id, user_id, action, new_values, ip_address, branch_id) VALUES (?, ?, ?, 'create', ?, ?, ?)");
                $log_stmt->execute([$tenant_id, $insert_id, $sender_id, json_encode([
                    'subject' => $subject, 'content' => $content, 'company_name' => $company_name,
                    'maktob_number' => $maktob_number, 'maktob_date' => $maktob_date,
                    'language' => $language, 'file_path' => $file_path, 'pdf_path' => $pdf_path
                ]), $_SERVER['REMOTE_ADDR'], $branch_id]);
            } catch (Exception $e) {
                error_log("Failed to log maktob creation: " . $e->getMessage());
            }
            $_SESSION['success_message'] = __('letter_created');
        } else {
            $errorInfo = $stmt->errorInfo();
            $_SESSION['error_message'] = __('error_creating_letter') . ": " . $errorInfo[2];
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = __('error_creating_letter') . ": " . $e->getMessage();
    }

    header('Location: ' . $_SERVER['PHP_SELF']); exit();
}

// Pagination
$items_per_page = 10;
$current_page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset         = ($current_page - 1) * $items_per_page;

// Filters
$search_query   = isset($_GET['search'])    ? trim($_GET['search'])    : '';
$status_filter  = isset($_GET['status'])    ? $_GET['status']          : '';
$language_filter= isset($_GET['language'])  ? $_GET['language']        : '';
$sender_filter  = isset($_GET['sender'])    ? $_GET['sender']          : '';
$date_from      = isset($_GET['date_from']) ? $_GET['date_from']       : '';
$date_to        = isset($_GET['date_to'])   ? $_GET['date_to']         : '';

$search_condition = '';
$search_params    = [];

if (!empty($search_query)) {
    $search_condition .= " AND (m.maktob_number LIKE ? OR m.subject LIKE ? OR m.company_name LIKE ?)";
    $p = '%' . $search_query . '%';
    $search_params = array_merge($search_params, [$p, $p, $p]);
}
if (!empty($status_filter))   { $search_condition .= " AND m.status = ?";       $search_params[] = $status_filter; }
if (!empty($language_filter)) { $search_condition .= " AND m.language = ?";     $search_params[] = $language_filter; }
if (!empty($sender_filter))   { $search_condition .= " AND m.sender_id = ?";    $search_params[] = $sender_filter; }
if (!empty($date_from))       { $search_condition .= " AND m.maktob_date >= ?"; $search_params[] = $date_from; }
if (!empty($date_to))         { $search_condition .= " AND m.maktob_date <= ?"; $search_params[] = $date_to; }

$recent_maktobs_result = null;
$total_records = 0;
$total_pages   = 1;
$stats = ['total' => 0, 'drafts' => 0, 'sent' => 0, 'archived' => 0];

try {
    // Stats
    $stats_stmt = $pdo->prepare("SELECT COUNT(*) as total,
        SUM(CASE WHEN status='draft'    THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN status='sent'     THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status='archived' THEN 1 ELSE 0 END) as archived
        FROM maktobs WHERE tenant_id = ? AND branch_id = ?");
    $stats_stmt->execute([$tenant_id, $branch_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Full list with filters
    $query = "SELECT m.*, u.name as sender_name FROM maktobs m
              JOIN users u ON m.sender_id = u.id
              WHERE m.tenant_id = ? AND m.branch_id = ?";
    $params = [$tenant_id, $branch_id];
    if (!empty($search_condition)) { $query .= $search_condition; $params = array_merge($params, $search_params); }
    $query .= " ORDER BY m.maktob_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allMaktobs    = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_records = count($allMaktobs);
    $total_pages   = max(1, ceil($total_records / $items_per_page));
    if ($current_page > $total_pages) $current_page = $total_pages;
    $paged_maktobs = array_slice($allMaktobs, $offset, $items_per_page);

    class MockMysqliResult {
        private $data; private $idx = 0;
        public function __construct($data) { $this->data = $data; }
        public function fetch_assoc() {
            return $this->idx < count($this->data) ? $this->data[$this->idx++] : null;
        }
    }
    $recent_maktobs_result = new MockMysqliResult($paged_maktobs);
} catch (Exception $e) {
    $recent_maktobs_result = null;
}

// Build pagination query string
$qp = [];
if (!empty($search_query))    $qp[] = 'search='    . urlencode($search_query);
if (!empty($status_filter))   $qp[] = 'status='    . urlencode($status_filter);
if (!empty($language_filter)) $qp[] = 'language='  . urlencode($language_filter);
if (!empty($sender_filter))   $qp[] = 'sender='    . urlencode($sender_filter);
if (!empty($date_from))       $qp[] = 'date_from=' . urlencode($date_from);
if (!empty($date_to))         $qp[] = 'date_to='   . urlencode($date_to);
$qs = !empty($qp) ? '&' . implode('&', $qp) : '';

include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
  /* Site's color palette from header-styles.css */
  --grad-start: #4099ff;
  --grad-end:   #2ed8b6;
  --grad:       linear-gradient(135deg, var(--grad-start) 0%, var(--grad-end) 100%);
  
  --bg:        #ffffff;
  --surface:   #f8fafc;
  --surface2:  #f1f5f9;
  --border:    #e5e7eb;
  --border2:   #d1d5db;
  --accent:    #4099ff;
  --accent2:   #2ed8b6;
  --warn:      #f59e0b;
  --danger:    #ef4444;
  --muted:     #6b7280;
  --text:      #1f2937;
  --text2:     #4b5563;
  --radius:    10px;
  --radius-lg: 14px;
  --white:     #ffffff;
}
*, *::before, *::after { box-sizing: border-box; }

/* override pcoded background for this page */
.pcoded-main-container { background: var(--bg) !important; }
.pcoded-content        { background: var(--bg) !important; }

.mk-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 24px 20px 60px;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
  font-size: 14px;
}

/* ── PAGE HEADER ── */
.mk-page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 18px;
  border-bottom: 1px solid var(--border);
}
.mk-page-head h1 {
  font-size: 19px;
  font-weight: 600;
  letter-spacing: -.3px;
  display: flex;
  align-items: center;
  gap: 9px;
  color: var(--text);
  margin: 0;
}
.mk-page-head h1 svg { color: var(--accent); flex-shrink: 0; }
.mk-page-head p { color: var(--muted); margin: 3px 0 0; font-size: 12px; }

/* ── ALERTS ── */
.mk-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 15px;
  border-radius: var(--radius);
  margin-bottom: 16px;
  font-size: 13px;
  border: 1px solid transparent;
}
.mk-alert-success { background: rgba(61,214,163,.08); border-color: rgba(61,214,163,.2); color: var(--accent2); }
.mk-alert-danger  { background: rgba(255,92,114,.08);  border-color: rgba(255,92,114,.2);  color: var(--danger); }
.mk-alert button  { background: none; border: none; color: inherit; opacity: .6; cursor: pointer; margin-left: auto; font-size: 16px; line-height: 1; }
.mk-alert button:hover { opacity: 1; }

/* ── BUTTONS ── */
.mk-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 15px;
  border-radius: 8px;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all .18s ease;
  text-decoration: none;
  white-space: nowrap;
}
.mk-btn-ghost   { background: var(--surface2); color: var(--text2); border-color: var(--border); }
.mk-btn-ghost:hover { background: var(--border); color: var(--text); }
.mk-btn-primary { background: var(--accent); color: #fff; }
.mk-btn-primary:hover { background: var(--grad-end); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(64,153,255,.3); }
.mk-btn-sm { padding: 6px 12px; font-size: 12px; }
.mk-btn-info { background: var(--surface2); color: var(--accent); border-color: rgba(64,153,255,.3); }
.mk-btn-info:hover { background: rgba(64,153,255,.1); }

/* ── KPI STRIP ── */
.mk-kpi-strip {
  display: grid;
  grid-template-columns: repeat(4,1fr);
  gap: 12px;
  margin-bottom: 20px;
}
.mk-kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 15px 16px;
  display: flex;
  align-items: center;
  gap: 13px;
  transition: border-color .2s;
}
.mk-kpi-card:hover { border-color: var(--border2); }
.mk-kpi-icon {
  width: 40px; height: 40px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.mk-kpi-icon.blue  { background: rgba(78,140,255,.12); color: var(--accent); }
.mk-kpi-icon.green { background: rgba(61,214,163,.12); color: var(--accent2); }
.mk-kpi-icon.warn  { background: rgba(245,166,35,.12);  color: var(--warn); }
.mk-kpi-icon.muted { background: rgba(107,117,153,.12); color: var(--muted); }
.mk-kpi-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 2px; }
.mk-kpi-value { font-size: 22px; font-weight: 600; font-family: 'DM Mono', monospace; line-height: 1; color: var(--text); }

/* ── ACCORDION ── */
.mk-accordion {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-bottom: 20px;
  overflow: hidden;
}
.mk-accordion-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  background: none;
  border: none;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: background .15s;
}
.mk-accordion-trigger:hover { background: var(--surface2); }
.mk-acc-left { display: flex; align-items: center; gap: 9px; }
.mk-acc-left svg { color: var(--accent); }
.mk-acc-badge {
  background: rgba(78,140,255,.15);
  color: var(--accent);
  font-size: 10px;
  padding: 2px 8px;
  border-radius: 20px;
  font-weight: 500;
}
.mk-chevron { transition: transform .25s ease; color: var(--muted); }
.mk-accordion.open .mk-chevron { transform: rotate(180deg); }
.mk-accordion-body {
  display: none;
  padding: 0 20px 22px;
  border-top: 1px solid var(--border);
}
.mk-accordion.open .mk-accordion-body { display: block; }

/* form */
.mk-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 20px;
  margin-top: 18px;
}
.mk-form-group { display: flex; flex-direction: column; gap: 5px; }
.mk-form-group.span2 { grid-column: span 2; }
.mk-form-group label {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  font-family: 'DM Sans', sans-serif;
}
.mk-shell input,
.mk-shell select,
.mk-shell textarea {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  padding: 9px 12px;
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  width: 100%;
}
.mk-shell input:focus,
.mk-shell select:focus,
.mk-shell textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(78,140,255,.1);
}
.mk-shell textarea { resize: vertical; min-height: 90px; }
.mk-shell select option { background: var(--surface2); }
.mk-shell input[type="file"]::file-selector-button {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text2);
  padding: 5px 10px;
  border-radius: 5px;
  font-family: 'DM Sans', sans-serif;
  font-size: 12px;
  cursor: pointer;
  margin-right: 10px;
  transition: background .15s;
}
.mk-shell input[type="file"]::file-selector-button:hover { background: var(--border); }
.mk-form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

/* ── TABLE CARD ── */
.mk-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.mk-table-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 20px;
  border-bottom: 1px solid var(--border);
  gap: 12px;
  flex-wrap: wrap;
}
.mk-table-head-left {
  display: flex;
  align-items: center;
  gap: 9px;
  font-weight: 500;
  font-size: 14px;
}
.mk-table-head-left svg { color: var(--accent); }
.mk-record-count {
  font-size: 11px;
  color: var(--muted);
  background: var(--surface2);
  border: 1px solid var(--border);
  padding: 2px 9px;
  border-radius: 20px;
}

/* filter bar */
.mk-filter-bar {
  display: flex;
  gap: 8px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
  align-items: center;
  background: var(--bg);
}
.mk-filter-bar .mk-shell input,
.mk-filter-bar input,
.mk-filter-bar select {
  background: var(--surface) !important;
  border-color: var(--border) !important;
  padding: 7px 10px;
  font-size: 12px;
  width: auto;
  flex: 1;
  min-width: 110px;
}
.mk-search-wrap { position: relative; flex: 1; min-width: 200px; }
.mk-search-wrap svg {
  position: absolute;
  left: 10px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
}
.mk-search-wrap input { padding-left: 32px !important; }

/* table */
.mk-table-wrap { overflow-x: auto; }
.mk-table { width: 100%; border-collapse: collapse; }
.mk-table thead tr { border-bottom: 1px solid var(--border); }
.mk-table th {
  padding: 10px 16px;
  text-align: left;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  white-space: nowrap;
  background: var(--bg);
  font-family: 'DM Sans', sans-serif;
}
.mk-table td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
  color: var(--text2);
  vertical-align: middle;
  font-family: 'DM Sans', sans-serif;
}
.mk-table tbody tr:last-child td { border-bottom: none; }
.mk-table tbody tr { transition: background .12s; }
.mk-table tbody tr:hover td { background: var(--surface2); color: var(--text); }

.mk-num-cell { font-family: 'DM Mono', monospace; font-size: 11px; color: var(--accent); }
.mk-subject-cell { color: var(--text); font-weight: 500; max-width: 190px; }
.mk-subject-cell small { display: block; font-weight: 400; color: var(--muted); font-size: 11px; margin-top: 1px; }

/* badges */
.mk-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}
.mk-badge-sent     { background: rgba(61,214,163,.1);  color: var(--accent2); border: 1px solid rgba(61,214,163,.2); }
.mk-badge-draft    { background: rgba(245,166,35,.1);   color: var(--warn);    border: 1px solid rgba(245,166,35,.2); }
.mk-badge-archived { background: rgba(107,117,153,.1);  color: var(--muted);  border: 1px solid rgba(107,117,153,.2); }
.mk-dot { width: 5px; height: 5px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.mk-dot.green { background: var(--accent2); }
.mk-dot.warn  { background: var(--warn); }
.mk-dot.grey  { background: var(--muted); }

.mk-lang-badge {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text2);
  font-family: 'DM Mono', monospace;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: .5px;
}

/* action dropdown */
.mk-action-menu { position: relative; display: inline-block; }
.mk-action-btn {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text2);
  padding: 5px 10px;
  border-radius: 6px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  font-size: 12px;
  transition: all .15s;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.mk-action-btn:hover { background: var(--border); color: var(--text); }
.mk-dropdown {
  position: absolute;
  right: 0; top: calc(100% + 5px);
  background: var(--surface2);
  border: 1px solid var(--border2);
  border-radius: 10px;
  min-width: 168px;
  box-shadow: 0 8px 24px rgba(0,0,0,.45);
  z-index: 200;
  overflow: hidden;
  display: none;
}
.mk-action-menu.open .mk-dropdown { display: block; }
.mk-dropdown a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  color: var(--text2);
  font-size: 13px;
  text-decoration: none;
  transition: background .12s, color .12s;
  font-family: 'DM Sans', sans-serif;
}
.mk-dropdown a:hover { background: var(--border); color: var(--text); }
.mk-dropdown a svg   { width: 13px; height: 13px; flex-shrink: 0; }
.mk-dropdown .mk-divider { height: 1px; background: var(--border); margin: 3px 0; }
.mk-dropdown .mk-danger  { color: var(--danger); }
.mk-dropdown .mk-danger:hover { background: rgba(255,92,114,.08); color: var(--danger); }

/* pagination */
.mk-pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: var(--bg);
  flex-wrap: wrap;
  gap: 8px;
}
.mk-pagination-info { font-size: 12px; color: var(--muted); }
.mk-pagination-controls { display: flex; gap: 4px; }
.mk-page-btn {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text2);
  width: 30px; height: 30px;
  border-radius: 6px;
  display: grid;
  place-items: center;
  font-size: 12px;
  cursor: pointer;
  transition: all .15s;
  font-family: 'DM Mono', monospace;
  text-decoration: none;
}
.mk-page-btn:hover { background: var(--border); color: var(--text); }
.mk-page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* empty state */
.mk-empty {
  text-align: center;
  padding: 48px 20px;
  color: var(--muted);
}
.mk-empty svg { margin-bottom: 12px; opacity: .35; }
.mk-empty p   { font-size: 13px; }

/* scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* responsive */
@media (max-width: 900px) {
  .mk-kpi-strip { grid-template-columns: 1fr 1fr; }
  .mk-form-grid { grid-template-columns: 1fr; }
  .mk-form-group.span2 { grid-column: span 1; }
  .mk-table th:nth-child(6), .mk-table td:nth-child(6),
  .mk-table th:nth-child(7), .mk-table td:nth-child(7) { display: none; }
}
@media (max-width: 600px) {
  .mk-kpi-strip { grid-template-columns: 1fr 1fr; gap: 8px; }
  .mk-table th:nth-child(4), .mk-table td:nth-child(4),
  .mk-table th:nth-child(5), .mk-table td:nth-child(5) { display: none; }
  .mk-filter-bar { gap: 6px; }
}
</style>

<div class="pcoded-main-container">
 <div class="pcoded-wrapper">
  <div class="pcoded-content">
   <div class="pcoded-inner-content">
    <div class="main-body">
     <div class="page-wrapper">

<div class="mk-shell">

  <!-- ── PAGE HEADER ── -->
  <div class="mk-page-head">
    <div>
      <h1>
        <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <?php echo __('manage_letters'); ?>
      </h1>
      <p><?php echo __('manage_and_view_all_letters'); ?></p>
    </div>
    <a href="dashboard.php" class="mk-btn mk-btn-ghost mk-btn-sm">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      <?php echo __('back_to_dashboard'); ?>
    </a>
  </div>

  <!-- ── ALERTS ── -->
  <?php if ($error_message): ?>
  <div class="mk-alert mk-alert-danger">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo htmlspecialchars($error_message); ?>
    <button onclick="this.closest('.mk-alert').remove()">&times;</button>
  </div>
  <?php endif; ?>
  <?php if ($success_message): ?>
  <div class="mk-alert mk-alert-success">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?php echo nl2br(htmlspecialchars($success_message)); ?>
    <button onclick="this.closest('.mk-alert').remove()">&times;</button>
  </div>
  <?php endif; ?>

  <!-- ── KPI STRIP ── -->
  <div class="mk-kpi-strip">
    <div class="mk-kpi-card">
      <div class="mk-kpi-icon blue">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div>
        <div class="mk-kpi-label">Total</div>
        <div class="mk-kpi-value"><?php echo $stats['total'] ?? 0; ?></div>
      </div>
    </div>
    <div class="mk-kpi-card">
      <div class="mk-kpi-icon warn">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div>
        <div class="mk-kpi-label">Drafts</div>
        <div class="mk-kpi-value"><?php echo $stats['drafts'] ?? 0; ?></div>
      </div>
    </div>
    <div class="mk-kpi-card">
      <div class="mk-kpi-icon green">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </div>
      <div>
        <div class="mk-kpi-label">Sent</div>
        <div class="mk-kpi-value"><?php echo $stats['sent'] ?? 0; ?></div>
      </div>
    </div>
    <div class="mk-kpi-card">
      <div class="mk-kpi-icon muted">
        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
      </div>
      <div>
        <div class="mk-kpi-label">Archived</div>
        <div class="mk-kpi-value"><?php echo $stats['archived'] ?? 0; ?></div>
      </div>
    </div>
  </div>

  <!-- ── ACCORDION: CREATE FORM ── -->
  <div class="mk-accordion" id="mkCreateAccordion">
    <button class="mk-accordion-trigger" type="button" onclick="mkToggleAccordion()">
      <span class="mk-acc-left">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <?php echo __('create_new_letter'); ?>
        <span class="mk-acc-badge">New</span>
      </span>
      <svg class="mk-chevron" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
    </button>
    <div class="mk-accordion-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="mk-form-grid">
          <div class="mk-form-group">
            <label for="maktob_number"><?php echo __('letter_number'); ?></label>
            <input type="text" id="maktob_number" name="maktob_number" placeholder="Auto-generated on date select">
          </div>
          <div class="mk-form-group">
            <label for="maktob_date"><?php echo __('letter_date'); ?></label>
            <input type="date" id="maktob_date" name="maktob_date" value="<?php echo date('Y-m-d'); ?>">
          </div>
          <div class="mk-form-group">
            <label for="company_name"><?php echo __('company_name'); ?></label>
            <input type="text" id="company_name" name="company_name" placeholder="Recipient company">
          </div>
          <div class="mk-form-group">
            <label for="language"><?php echo __('language'); ?></label>
            <select id="language" name="language">
              <option value="english"><?php echo __('english'); ?></option>
              <option value="dari"><?php echo __('dari'); ?></option>
              <option value="pashto"><?php echo __('pashto'); ?></option>
            </select>
          </div>
          <div class="mk-form-group span2">
            <label for="subject"><?php echo __('subject'); ?></label>
            <input type="text" id="subject" name="subject" placeholder="Letter subject">
          </div>
          <div class="mk-form-group span2">
            <label for="content"><?php echo __('content'); ?></label>
            <textarea id="content" name="content" placeholder="Write the body of the letter here…"></textarea>
          </div>
          <div class="mk-form-group">
            <label for="pdf_file">PDF Letter File</label>
            <input type="file" id="pdf_file" name="pdf_file" accept=".pdf">
          </div>
          <div class="mk-form-group">
            <label for="attachment">Supporting Documents</label>
            <input type="file" id="attachment" name="attachment" accept=".pdf">
          </div>
        </div>
        <div class="mk-form-footer">
          <button type="button" class="mk-btn mk-btn-ghost" onclick="mkToggleAccordion()">Cancel</button>
          <button type="submit" class="mk-btn mk-btn-primary">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <?php echo __('create_letter'); ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── TABLE CARD ── -->
  <div class="mk-table-card">
    <!-- Card header -->
    <div class="mk-table-head">
      <div class="mk-table-head-left">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <?php echo __('recent_letters'); ?>
        <span class="mk-record-count"><?php echo $total_records; ?> entries</span>
      </div>
      <button class="mk-btn mk-btn-primary mk-btn-sm" type="button" onclick="mkToggleAccordion(true)">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <?php echo __('create_new_letter'); ?>
      </button>
    </div>

    <!-- Filter bar -->
    <form method="GET">
      <div class="mk-filter-bar">
        <div class="mk-search-wrap">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" placeholder="Search number, subject, company…" value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <select name="status" style="min-width:110px; flex:0;">
          <option value="">All Status</option>
          <option value="draft"    <?php echo $status_filter==='draft'    ? 'selected':''; ?>>Draft</option>
          <option value="sent"     <?php echo $status_filter==='sent'     ? 'selected':''; ?>>Sent</option>
          <option value="archived" <?php echo $status_filter==='archived' ? 'selected':''; ?>>Archived</option>
        </select>
        <select name="language" style="min-width:120px; flex:0;">
          <option value="">All Languages</option>
          <option value="english" <?php echo $language_filter==='english' ? 'selected':''; ?>>English</option>
          <option value="dari"    <?php echo $language_filter==='dari'    ? 'selected':''; ?>>Dari</option>
          <option value="pashto"  <?php echo $language_filter==='pashto'  ? 'selected':''; ?>>Pashto</option>
        </select>
        <select name="sender" style="min-width:130px; flex:0;">
          <option value="">All Senders</option>
          <?php
          try {
            $ss = $pdo->prepare("SELECT DISTINCT u.id, u.name FROM users u JOIN maktobs m ON u.id = m.sender_id WHERE m.tenant_id = ? AND m.branch_id = ? ORDER BY u.name");
            $ss->execute([$tenant_id, $branch_id]);
            while ($s = $ss->fetch(PDO::FETCH_ASSOC)) {
              $sel = $sender_filter == $s['id'] ? 'selected' : '';
              echo "<option value='{$s['id']}' {$sel}>" . htmlspecialchars($s['name']) . "</option>";
            }
          } catch (Exception $e) {}
          ?>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="min-width:140px; flex:0;">
        <input type="date" name="date_to"   value="<?php echo htmlspecialchars($date_to);   ?>" style="min-width:140px; flex:0;">
        <button type="submit" class="mk-btn mk-btn-info mk-btn-sm">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          <?php echo __('search'); ?>
        </button>
        <?php if (!empty($search_query)||!empty($status_filter)||!empty($language_filter)||!empty($sender_filter)||!empty($date_from)||!empty($date_to)): ?>
        <a href="manage_maktobs.php" class="mk-btn mk-btn-ghost mk-btn-sm">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Clear
        </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Table -->
    <div class="mk-table-wrap">
      <table class="mk-table">
        <thead>
          <tr>
            <th><?php echo __('letter_number'); ?></th>
            <th><?php echo __('date'); ?></th>
            <th><?php echo __('subject'); ?></th>
            <th><?php echo __('company_name'); ?></th>
            <th><?php echo __('status'); ?></th>
            <th><?php echo __('language'); ?></th>
            <th><?php echo __('created_by'); ?></th>
            <th style="text-align:right"><?php echo __('actions'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if ($recent_maktobs_result !== null && $total_records > 0): ?>
          <?php while ($row = $recent_maktobs_result->fetch_assoc()): ?>
          <tr>
            <td class="mk-num-cell"><?php echo htmlspecialchars($row['maktob_number']); ?></td>
            <td><?php echo date('Y-m-d', strtotime($row['maktob_date'])); ?></td>
            <td class="mk-subject-cell">
              <?php echo htmlspecialchars($row['subject']); ?>
              <small><?php echo htmlspecialchars($row['company_name']); ?></small>
            </td>
            <td><?php echo htmlspecialchars($row['company_name']); ?></td>
            <td>
              <?php if ($row['status'] === 'sent'): ?>
                <span class="mk-badge mk-badge-sent"><span class="mk-dot green"></span><?php echo __('sent'); ?></span>
              <?php elseif ($row['status'] === 'archived'): ?>
                <span class="mk-badge mk-badge-archived"><span class="mk-dot grey"></span>Archived</span>
              <?php else: ?>
                <span class="mk-badge mk-badge-draft"><span class="mk-dot warn"></span><?php echo __('draft'); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $langMap = ['english'=>'EN','dari'=>'DR','pashto'=>'PS'];
                $langCode = $langMap[$row['language'] ?? 'english'] ?? strtoupper(substr($row['language']??'EN',0,2));
              ?>
              <span class="mk-lang-badge"><?php echo $langCode; ?></span>
            </td>
            <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
            <td style="text-align:right">
              <div class="mk-action-menu" onclick="mkToggleMenu(this)">
                <button class="mk-action-btn" type="button">
                  Actions
                  <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="mk-dropdown">
                  <a href="#" class="view-maktob"
                    data-id="<?php echo $row['id']; ?>"
                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                    data-content="<?php echo htmlspecialchars($row['content']); ?>"
                    data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                    data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                    data-date="<?php echo date('F j, Y', strtotime($row['maktob_date'])); ?>"
                    data-status="<?php echo $row['status']; ?>"
                    data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>"
                    data-file-path="<?php echo htmlspecialchars($row['file_path'] ?? ''); ?>"
                    data-pdf-path="<?php echo htmlspecialchars($row['pdf_path'] ?? ''); ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?php echo __('view'); ?>
                  </a>
                  <a href="#" class="edit-maktob"
                    data-id="<?php echo $row['id']; ?>"
                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                    data-content="<?php echo htmlspecialchars($row['content']); ?>"
                    data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                    data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                    data-date="<?php echo $row['maktob_date']; ?>"
                    data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <?php echo __('edit'); ?>
                  </a>
                  <?php if ($row['status'] === 'draft'): ?>
                  <a href="#" class="send-maktob" data-id="<?php echo $row['id']; ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    Send to Branch
                  </a>
                  <?php endif; ?>
                  <?php if ($row['status'] !== 'archived'): ?>
                  <a href="#" class="archive-maktob" data-id="<?php echo $row['id']; ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/></svg>
                    Archive
                  </a>
                  <?php endif; ?>
                  <a href="../api/maktob/download_maktob.php?id=<?php echo $row['id']; ?>" target="_blank">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    View PDF
                  </a>
                  <div class="mk-divider"></div>
                  <a href="#" class="mk-danger delete-maktob" data-id="<?php echo $row['id']; ?>">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    <?php echo __('delete'); ?>
                  </a>
                </div>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="8">
              <div class="mk-empty">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <p><?php echo __('no_letters_found'); ?></p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="mk-pagination-bar">
      <div class="mk-pagination-info">
        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $items_per_page, $total_records); ?> of <?php echo $total_records; ?> entries
      </div>
      <div class="mk-pagination-controls">
        <?php if ($current_page > 1): ?>
          <a class="mk-page-btn" href="manage_maktobs.php?page=1<?php echo $qs; ?>" title="First">«</a>
          <a class="mk-page-btn" href="manage_maktobs.php?page=<?php echo $current_page-1; ?><?php echo $qs; ?>" title="Prev">‹</a>
        <?php endif; ?>
        <?php for ($i = max(1,$current_page-2); $i <= min($total_pages,$current_page+2); $i++): ?>
          <a class="mk-page-btn <?php echo $i===$current_page?'active':''; ?>" href="manage_maktobs.php?page=<?php echo $i.$qs; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($current_page < $total_pages): ?>
          <a class="mk-page-btn" href="manage_maktobs.php?page=<?php echo $current_page+1; ?><?php echo $qs; ?>" title="Next">›</a>
          <a class="mk-page-btn" href="manage_maktobs.php?page=<?php echo $total_pages.$qs; ?>" title="Last">»</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /.mk-table-card -->

</div><!-- /.mk-shell -->

     </div>
    </div>
   </div>
  </div>
 </div>
</div>

<?php include '../modals/maktob/view_modal.php'; ?>
<?php include '../modals/maktob/edit_modal.php'; ?>
<?php include '../modals/maktob/delete_modal.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/maktob/main.js"></script>

<script>
// ── Accordion ──
function mkToggleAccordion(forceOpen) {
  var acc = document.getElementById('mkCreateAccordion');
  if (forceOpen) {
    acc.classList.add('open');
    acc.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } else {
    acc.classList.toggle('open');
  }
}

// ── Action menus ──
function mkToggleMenu(el) {
  document.querySelectorAll('.mk-action-menu.open').forEach(function(m) {
    if (m !== el) m.classList.remove('open');
  });
  el.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  if (!e.target.closest('.mk-action-menu')) {
    document.querySelectorAll('.mk-action-menu.open').forEach(function(m) { m.classList.remove('open'); });
  }
});

// ── Auto-generate maktob number ──
$(document).ready(function () {
  function generateMaktobNumber() {
    var date = $('#maktob_date').val();
    if (date && $('#maktob_number').val() === '') {
      var tenantId  = <?php echo (int)$tenant_id; ?>;
      var branchId  = <?php echo (int)$branch_id; ?>;
      var formatted = date.replace(/-/g, '');
      var base      = tenantId + '-' + branchId + '-' + formatted + '-';
      $.ajax({
        url: '../api/maktob/get_next_number.php',
        method: 'POST',
        data: { base_number: base },
        success: function (r) { if (r.number) $('#maktob_number').val(r.number); }
      });
    }
  }
  $('#maktob_date').on('change blur', function () { setTimeout(generateMaktobNumber, 400); });
  if ($('#maktob_date').val() && $('#maktob_number').val() === '') generateMaktobNumber();
});

// ── Send maktob ──
$(document).on('click', '.send-maktob', function (e) {
  e.preventDefault();
  var id = $(this).data('id');
  if (confirm('Are you sure you want to send this maktob to branch?')) {
    $.ajax({
      url: '../api/maktob/update_status.php',
      method: 'POST',
      data: { id: id, action: 'send' },
      success: function (r) { r.success ? location.reload() : alert('Error: ' + r.message); },
      error: function () { alert('Error updating maktob status'); }
    });
  }
});

// ── Archive maktob ──
$(document).on('click', '.archive-maktob', function (e) {
  e.preventDefault();
  var id = $(this).data('id');
  if (confirm('Are you sure you want to archive this maktob?')) {
    $.ajax({
      url: '../api/maktob/update_status.php',
      method: 'POST',
      data: { id: id, action: 'archive' },
      success: function (r) { r.success ? location.reload() : alert('Error: ' + r.message); },
      error: function () { alert('Error updating maktob status'); }
    });
  }
});
</script>

<?php include '../includes/admin_footer.php'; ?>
