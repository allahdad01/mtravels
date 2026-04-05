<?php
// ─── SESSION & SECURITY ───────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1'); exit();
}
$_SESSION['last_activity'] = time();

$allowed_roles = ['admin', 'finance', 'sales', 'umrah', 'staff'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php'); exit();
}
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require_once '../includes/InputValidator.php';

if ($_SESSION['role'] === 'staff') {
    require_once '../api/dashboard/staff_dashboard.php';
    include '../includes/header.php';
    include '../admin/staff_dashboard_view.php';
    exit();
}

require_once '../api/dashboard/dashboard_handler.php';
require_once '../api/dashboard/supplier_notification.php';
require_once '../api/dashboard/client_notification.php';

// Get today's attendance for logged-in user
$att_stmt = $pdo->prepare("
    SELECT * FROM attendance
    WHERE tenant_id = ? AND user_id = ? AND date = CURDATE()
");
$att_stmt->execute([$tenant_id, $_SESSION['user_id']]);
$today_attendance = $att_stmt->fetch(PDO::FETCH_ASSOC);

// Get attendance settings
$att_settings_stmt = $pdo->prepare("
    SELECT * FROM attendance_settings
    WHERE tenant_id = ? AND branch_id = ?
");
$att_settings_stmt->execute([$tenant_id, $_SESSION['branch_id'] ?? 0]);
$attendance_settings = $att_settings_stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance_settings) {
    $attendance_settings = [
        'office_start_time' => '09:00:00',
        'office_end_time' => '17:00:00'
    ];
}

// Calculate attendance state and progress
$att_state = 'not_checked_in';
$att_progress = 0;
$att_working_minutes = 0;
$show_att_widget = true; // Flag to show/hide widget

if ($today_attendance && $today_attendance['check_in_time']) {
    if ($today_attendance['check_out_time']) {
        $att_state = 'completed';
        $att_working_minutes = $today_attendance['working_minutes'] ?? 0;
        $show_att_widget = false; // Hide when completed
    } else {
        $att_state = 'checked_in';
        $checkin = new DateTime($today_attendance['check_in_time']);
        $now = new DateTime();
        $att_working_minutes = ($now->getTimestamp() - $checkin->getTimestamp()) / 60;
        
        $office_start = new DateTime($attendance_settings['office_start_time']);
        $office_end = new DateTime($attendance_settings['office_end_time']);
        $total_office_minutes = ($office_end->getTimestamp() - $office_start->getTimestamp()) / 60;
        $att_progress = min(100, ($att_working_minutes / $total_office_minutes) * 100);
        
        // Calculate minutes until checkout (office end time)
        $today = new DateTime('now');
        $checkout_time = new DateTime($today->format('Y-m-d') . ' ' . $attendance_settings['office_end_time']);
        $minutes_until_checkout = ($checkout_time->getTimestamp() - $now->getTimestamp()) / 60;
        
        // Show widget only 30 minutes before checkout
        $show_att_widget = ($minutes_until_checkout <= 30);
    }
}
?>
<?php include '../includes/header.php'; ?>
<?php
$showTickets = hasFeature('ticket_bookings', $allowed_features) ||
               hasFeature('ticket_reservations', $allowed_features) ||
               hasFeature('refunded_tickets', $allowed_features) ||
               hasFeature('date_change_tickets', $allowed_features) ||
               hasFeature('ticket_weights', $allowed_features);
if (!file_exists($imagePath)) { $imagePath = "../assets/images/user/avatar-1.jpg"; }
$selected_date = InputValidator::getDate($_GET['departure_date'] ?? '', 'Y-m-d', date('Y-m-d'));
?>

<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<style>
:root {
  --primary:#4099ff;--primary-dark:#2563eb;--primary-light:#60a5fa;
  --accent:#2ed8b6;--accent-dark:#14b8a6;--accent-light:#5eead4;
  --violet:#7c3aed;--violet-light:#a78bfa;--indigo:#4f46e5;
  --sky:#0ea5e9;--emerald:#10b981;--amber:#f59e0b;
  --rose:#f43f5e;--orange:#f97316;--pink:#ec4899;--teal:#14b8a6;
  --bg:#f8fafc;--surface:#ffffff;--surface2:#f1f5f9;--surface3:#e2e8f0;
  --border:rgba(0,0,0,0.08);
  --text:#1e293b;--text-muted:#64748b;
  --grad-start:#4099ff;--grad-end:#2ed8b6;--grad:linear-gradient(135deg,var(--grad-start) 0%,var(--grad-end) 100%);
}

/* ─── ATTENDANCE STATUS WIDGET ─── */
.att-status-widget {
  background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
  border-radius:18px;
  padding:24px;
  margin-bottom:28px;
  display:flex;align-items:center;justify-content:space-between;gap:24px;
  color:#fff;box-shadow:0 10px 40px rgba(102,126,234,.25);
  position:relative;overflow:hidden;flex-wrap:wrap;
}
.att-status-widget::before {
  content:'';position:absolute;inset:0;background:radial-gradient(circle at 80% 20%,rgba(255,255,255,.15),transparent 50%);pointer-events:none;
}
.att-widget-left {
  display:flex;align-items:center;gap:18px;z-index:1;flex:1;min-width:200px;
}
.att-widget-indicator {
  width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;
  position:relative;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.3);
}
.att-widget-indicator.online {background:rgba(16,185,129,.3);border-color:rgba(16,185,129,.5);}
.att-widget-indicator.offline{background:rgba(244,63,94,.3);border-color:rgba(244,63,94,.5);}
.att-widget-indicator.completed{background:rgba(34,197,94,.3);border-color:rgba(34,197,94,.5);}
.att-widget-indicator.pulse::after {
  content:'';position:absolute;width:100%;height:100%;border-radius:50%;border:2px solid rgba(16,185,129,.6);
  animation:att-pulse 2s ease-in-out infinite;
}
@keyframes att-pulse {0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(1.3);opacity:0;}}
.att-widget-info h3 {font-size:16px;font-weight:700;margin:0 0 6px 0;}
.att-widget-info p {font-size:13px;opacity:.85;margin:0;}
.att-widget-right {
  display:flex;align-items:center;gap:16px;z-index:1;flex-wrap:wrap;
}
.att-widget-time {text-align:right;}
.att-widget-time-label {font-size:11px;opacity:.75;text-transform:uppercase;letter-spacing:.5px;}
.att-widget-time-value {font-size:22px;font-weight:700;font-family:'JetBrains Mono',monospace;margin:4px 0 0 0;}
.att-widget-action-btn {
  background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;
  padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;
  transition:all .3s ease;font-family:inherit;
}
.att-widget-action-btn:hover {
  background:rgba(255,255,255,.3);border-color:rgba(255,255,255,.5);transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.15);
}
.att-widget-action-btn.loading {pointer-events:none;opacity:.6;}

@media (max-width:768px) {
  .att-status-widget {flex-direction:column;align-items:flex-start;}
  .att-widget-left {width:100%;}
  .att-widget-right {width:100%;justify-content:space-between;}
}
.pcoded-main-container{background:var(--bg)!important;}
.pcoded-content,.pcoded-inner-content{background:transparent!important;}
.dash-wrap{font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);padding:28px 20px;position:relative;}
.dash-wrap::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(124,58,237,.15) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 10%,rgba(14,165,233,.12) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 50% 100%,rgba(16,185,129,.08) 0%,transparent 50%);}
.dash-inner{position:relative;z-index:1;}
.sec-label{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sec-label::after{content:'';flex:1;height:1px;background:var(--border);}
.d-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:22px;}
.d-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.d-card-title{font-size:15px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--text);}
.ci{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.ci-violet{background:rgba(124,58,237,.2);color:var(--violet-light);}
.ci-sky{background:rgba(14,165,233,.2);color:var(--sky);}
.ci-emerald{background:rgba(16,185,129,.2);color:var(--emerald);}
.ci-amber{background:rgba(245,158,11,.2);color:var(--amber);}
.ci-rose{background:rgba(244,63,94,.2);color:var(--rose);}

/* Toast Notifications */
.toast-notification{
  position:fixed;top:20px;right:20px;padding:16px 20px;border-radius:10px;
  display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:500;
  z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);
  opacity:0;transform:translateX(400px);transition:all 0.3s ease;
}
.toast-notification.show{opacity:1;transform:translateX(0);}
.toast-notification.bg-success{background:#10b981;}
.toast-notification.bg-danger{background:#ef4444;}
.toast-notification.bg-warning{background:#f59e0b;}
.toast-notification .close{background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0;opacity:0.8;}
.toast-notification .close:hover{opacity:1;}

/* Header */
.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;flex-wrap:wrap;gap:16px;}
.dash-header h1{font-size:24px;font-weight:800;letter-spacing:-.5px;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.dash-header p{color:var(--text-muted);font-size:14px;margin-top:3px;}
.header-actions{display:flex;gap:10px;flex-wrap:wrap;}
.dbtn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.dbtn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.dbtn-ghost:hover{background:var(--surface3);transform:translateY(-1px);color:var(--text);}
.dbtn-primary{background:var(--grad);color:#fff;box-shadow:0 4px 20px rgba(64,153,255,.35);}
.dbtn-primary:hover{transform:translateY(-2px);color:#fff;}
.dbtn-info{background:var(--grad);color:#fff;box-shadow:0 4px 16px rgba(46,216,182,.3);}
.dbtn-info:hover{transform:translateY(-2px);color:#fff;}

/* Alerts */
.d-alert{border-radius:14px;padding:16px 20px;margin-bottom:12px;display:flex;align-items:flex-start;gap:14px;border:1px solid;animation:slideIn .4s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.d-alert-warning{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);}
.d-alert-danger{background:rgba(244,63,94,.1);border-color:rgba(244,63,94,.3);}
.d-alert-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
.d-alert-warning .d-alert-icon{background:rgba(245,158,11,.2);color:var(--amber);}
.d-alert-danger .d-alert-icon{background:rgba(244,63,94,.2);color:var(--rose);}
.d-alert-title{font-size:13px;font-weight:700;margin-bottom:4px;}
.d-alert-items{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.d-alert-chip{font-size:12px;padding:4px 12px;border-radius:20px;font-weight:600;}
.d-alert-warning .d-alert-chip{background:rgba(245,158,11,.2);color:var(--amber);}
.d-alert-danger .d-alert-chip{background:rgba(244,63,94,.2);color:var(--rose);}
.d-alert-close{margin-left:auto;background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;padding:4px;}

/* Financial chart */
.fin-chart-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px;margin-bottom:22px;position:relative;overflow:hidden;}
.fin-chart-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:20px 20px 0 0;background:var(--grad);}
.fin-chart-layout{display:grid;grid-template-columns:1fr 280px;gap:28px;align-items:start;}
.fin-select{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:9px;padding:7px 14px;font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;outline:none;transition:border-color .2s;}
.fin-select:focus{border-color:rgba(124,58,237,.5);}
.fin-select option{background:var(--surface2);}
.fin-controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.wealth-panel{background:var(--surface2);border:1px solid var(--border);border-radius:16px;padding:22px;}
.wealth-panel-title{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.wealth-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid var(--border);}
.wealth-row:last-of-type{border-bottom:none;}
.wealth-row-label{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--text-muted);}
.wealth-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.wealth-row-value{font-size:14px;font-weight:700;font-family:'JetBrains Mono',monospace;}
.wealth-total-row{margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center;}
.wealth-total-label{font-size:13px;font-weight:700;}
.wealth-total-value{font-size:20px;font-weight:800;font-family:'JetBrains Mono',monospace;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.wealth-date-badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:rgba(124,58,237,.15);color:var(--violet-light);margin-bottom:16px;}

/* Sales cards */
.sales-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;}
.sales-card{border-radius:18px;padding:22px;position:relative;overflow:hidden;cursor:pointer;transition:transform .25s,box-shadow .25s;border:1px solid rgba(255,255,255,.08);}
.sales-card:hover{transform:translateY(-4px);}
.sales-card::before{content:'';position:absolute;inset:0;opacity:.08;background:radial-gradient(ellipse at top right,white,transparent 70%);}
.sc-daily{background:var(--grad);box-shadow:0 8px 32px rgba(64,153,255,.3);}
.sc-monthly{background:linear-gradient(135deg,var(--primary-dark),var(--primary));box-shadow:0 8px 32px rgba(37,99,235,.3);}
.sc-yearly{background:linear-gradient(135deg,var(--accent),var(--accent-dark));box-shadow:0 8px 32px rgba(46,216,182,.3);}
.sc-label{font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;opacity:.75;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.sc-amount{font-size:28px;font-weight:800;letter-spacing:-1px;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:5px;}
.sc-secondary{font-size:13px;opacity:.65;font-family:'JetBrains Mono',monospace;margin-bottom:14px;}
.sc-footer{display:flex;justify-content:space-between;align-items:center;}
.trend-badge{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:700;background:rgba(255,255,255,.15);padding:4px 10px;border-radius:20px;}
.sc-sparkline{display:flex;align-items:flex-end;gap:3px;height:30px;}
.spark-bar{width:5px;border-radius:3px;background:rgba(255,255,255,.25);}
.spark-bar.hi{background:rgba(255,255,255,.9);}
.sc-filter-panel{margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.15);display:none;}
.sc-filter-panel.open{display:block;}
.sc-filter-panel label{font-size:11px;opacity:.7;display:block;margin-bottom:4px;}
.sc-filter-input{width:100%;background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:8px;padding:6px 10px;font-size:12px;font-family:inherit;}
.sc-filter-input:focus{outline:none;border-color:rgba(255,255,255,.5);}
.sc-filter-input option{background:#1e1e35;}
.sc-filter-btn{margin-top:8px;background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;width:100%;}
.sc-filter-btn:hover{background:rgba(255,255,255,.25);}

/* Dues */
.dues-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:22px;}
.due-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s;cursor:pointer;}
.due-card:hover{transform:translateY(-3px);border-color:rgba(255,255,255,.15);}
.due-card::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:16px 16px 0 0;}
.dc-ticket::after{background:linear-gradient(90deg,var(--violet),var(--indigo));}
.dc-datechange::after{background:linear-gradient(90deg,var(--amber),var(--orange));}
.dc-refund::after{background:linear-gradient(90deg,var(--sky),var(--teal));}
.dc-umrah::after{background:linear-gradient(90deg,var(--emerald),var(--teal));}
.dc-visa::after{background:linear-gradient(90deg,var(--indigo),var(--violet));}
.dc-hotel::after{background:linear-gradient(90deg,var(--orange),var(--amber));}
.dc-addpay::after{background:linear-gradient(90deg,var(--rose),var(--pink));}
.dc-weight::after{background:linear-gradient(90deg,var(--pink),var(--violet));}
.dc-reserve::after{background:linear-gradient(90deg,var(--teal),var(--emerald));}
.due-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:12px;}
.dc-ticket .due-icon{background:rgba(124,58,237,.15);color:var(--violet-light);}
.dc-datechange .due-icon{background:rgba(245,158,11,.15);color:var(--amber);}
.dc-refund .due-icon{background:rgba(14,165,233,.15);color:var(--sky);}
.dc-umrah .due-icon{background:rgba(16,185,129,.15);color:var(--emerald);}
.dc-visa .due-icon{background:rgba(79,70,229,.15);color:var(--indigo);}
.dc-hotel .due-icon{background:rgba(249,115,22,.15);color:var(--orange);}
.dc-addpay .due-icon{background:rgba(244,63,94,.15);color:var(--rose);}
.dc-weight .due-icon{background:rgba(236,72,153,.15);color:var(--pink);}
.dc-reserve .due-icon{background:rgba(20,184,166,.15);color:var(--teal);}
.due-label{font-size:10px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
.due-usd{font-size:18px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-.5px;}
.due-afs{font-size:11px;color:var(--text-muted);font-family:'JetBrains Mono',monospace;margin-top:2px;}
.due-bar-track{height:4px;background:var(--surface3);border-radius:4px;margin-top:12px;overflow:hidden;}
.due-bar-fill{height:100%;border-radius:4px;width:0%;transition:width 1s ease;}
.dc-ticket .due-bar-fill{background:linear-gradient(90deg,var(--violet),var(--indigo));}
.dc-datechange .due-bar-fill{background:linear-gradient(90deg,var(--amber),var(--orange));}
.dc-refund .due-bar-fill{background:linear-gradient(90deg,var(--sky),var(--teal));}
.dc-umrah .due-bar-fill{background:linear-gradient(90deg,var(--emerald),var(--teal));}
.dc-visa .due-bar-fill{background:linear-gradient(90deg,var(--indigo),var(--violet));}
.dc-hotel .due-bar-fill{background:linear-gradient(90deg,var(--orange),var(--amber));}
.dc-addpay .due-bar-fill{background:linear-gradient(90deg,var(--rose),var(--pink));}
.dc-weight .due-bar-fill{background:linear-gradient(90deg,var(--pink),var(--violet));}
.dc-reserve .due-bar-fill{background:linear-gradient(90deg,var(--teal),var(--emerald));}

/* Flight cards */
.ftabs{display:flex;gap:4px;background:var(--surface2);border-radius:12px;padding:4px;margin-bottom:18px;width:fit-content;border:1px solid var(--border);flex-wrap:wrap;}
.ftab{padding:7px 14px;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;color:var(--text-muted);border:none;background:none;font-family:inherit;transition:all .2s;}
.ftab.active{background:var(--grad);color:#fff;box-shadow:0 4px 14px rgba(64,153,255,.4);}
.flight-cards{display:flex;flex-direction:column;gap:10px;}
.flight-card{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:14px 16px;display:grid;grid-template-columns:1.2fr auto 1.2fr 1fr auto;align-items:center;gap:14px;transition:transform .2s,border-color .2s,box-shadow .2s;position:relative;overflow:hidden;cursor:pointer;}
.flight-card:hover{transform:translateY(-2px);border-color:rgba(64,153,255,.4);box-shadow:0 8px 30px rgba(64,153,255,.12);}
.flight-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--grad);border-radius:14px 0 0 14px;}
.fc-pass h3{font-size:13px;font-weight:700;margin-bottom:3px;}
.fc-pass span{font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:4px;}
.fc-route{display:flex;align-items:center;gap:8px;min-width:150px;justify-content:center;}
.fc-city-code{font-size:18px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-1px;text-align:center;}
.fc-city-name{font-size:9px;color:var(--text-muted);text-align:center;}
.fc-arrow{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;}
.fc-arrow-line{width:100%;height:1px;background:linear-gradient(90deg,var(--violet),var(--sky));position:relative;}
.fc-arrow-line::after{content:'✈';position:absolute;top:-9px;left:50%;transform:translateX(-50%);font-size:12px;color:var(--sky);}
.fc-airline{font-size:9px;color:var(--text-muted);margin-top:5px;}
.fc-date-row{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted);margin-bottom:2px;}
.fc-date-row span{color:var(--text);font-weight:600;}
.fc-date-row.dep span,.fc-date-row.dep{color:var(--rose);}
.fc-sold{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;color:var(--emerald);white-space:nowrap;text-align:right;}
.dep-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(244,63,94,.15);color:var(--rose);margin-top:3px;}
.dep-filter-bar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.dep-filter-bar label{font-size:12px;color:var(--text-muted);font-weight:600;}
.dep-date-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:9px;padding:7px 12px;font-size:12px;font-family:inherit;outline:none;}
.dep-date-input:focus{border-color:rgba(124,58,237,.5);}

/* Notifications */
.notif-tabs-row{display:flex;gap:8px;background:transparent;border-radius:0;padding:14px 0;border-bottom:1px solid var(--border);margin-bottom:12px;}
.notif-tab-btn{padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:var(--text-muted);border:1px solid transparent;background:transparent;font-family:inherit;transition:all .2s;display:flex;align-items:center;gap:8px;}
.notif-tab-btn:hover{color:var(--text);background:var(--surface2);}
.notif-tab-btn.active{background:var(--grad);color:#fff;border-color:transparent;}
.nbadge{background:var(--rose);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;margin-left:4px;}
.timeline{display:flex;flex-direction:column;}
.tl-date-group{margin-bottom:16px;}
.tl-date-hdr{font-size:10px;font-weight:700;color:var(--text-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.tl-date-hdr::after{content:'';flex:1;height:1px;background:var(--border);}
.tl-item{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);}
.tl-item:last-child{border-bottom:none;}
.tl-dot{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;position:relative;}
.tl-dot.unread::after{content:'';width:7px;height:7px;background:var(--rose);border-radius:50%;position:absolute;top:-2px;right:-2px;border:2px solid var(--surface);}
.tld-visa{background:rgba(14,165,233,.15);color:var(--sky);}
.tld-supplier{background:rgba(245,158,11,.15);color:var(--amber);}
.tld-umrah{background:rgba(16,185,129,.15);color:var(--emerald);}
.tld-ticket{background:rgba(124,58,237,.15);color:var(--violet-light);}
.tld-expense{background:rgba(244,63,94,.15);color:var(--rose);}
.tld-hotel{background:rgba(249,115,22,.15);color:var(--orange);}
.tld-refund{background:rgba(14,165,233,.15);color:var(--sky);}
.tld-sarafi{background:rgba(20,184,166,.15);color:var(--teal);}
.tld-default{background:rgba(255,255,255,.07);color:var(--text-muted);}
.tl-body{flex:1;}
.tl-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:3px;}
.tl-type{font-size:12px;font-weight:700;text-transform:capitalize;}
.tl-time{font-size:11px;color:var(--text-muted);}
.tl-msg{font-size:13px;color:var(--text-muted);line-height:1.5;}
.tl-meta{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;}
.tl-chip{font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600;background:var(--surface2);color:var(--text-muted);display:flex;align-items:center;gap:4px;border:1px solid var(--border);}
.tl-actions{display:flex;gap:8px;margin-top:8px;}
.tl-btn{font-size:11px;padding:5px 12px;border-radius:8px;font-weight:600;cursor:pointer;border:1px solid;font-family:inherit;transition:all .2s;display:flex;align-items:center;gap:5px;}
.tl-btn-receive{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);color:var(--emerald);}
.tl-btn-receive:hover{background:rgba(16,185,129,.2);}
.tl-btn-read{background:rgba(14,165,233,.1);border-color:rgba(14,165,233,.3);color:var(--sky);}
.tl-btn-read:hover{background:rgba(14,165,233,.2);}
.read-filter{display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap;}
.read-filter label{font-size:12px;color:var(--text-muted);font-weight:600;}

/* Leaderboard */
.lb-list{display:flex;flex-direction:column;gap:10px;}
.lb-item{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:14px 16px;display:grid;grid-template-columns:44px 1fr auto;align-items:center;gap:14px;transition:transform .2s,border-color .2s;cursor:pointer;}
.lb-item:hover{transform:translateX(4px);border-color:rgba(255,255,255,.15);}
.lb-rank{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:900;flex-shrink:0;}
.rank-1{background:linear-gradient(135deg,#fbbf24,#f59e0b);box-shadow:0 4px 14px rgba(245,158,11,.4);}
.rank-2{background:linear-gradient(135deg,#cbd5e1,#94a3b8);box-shadow:0 4px 14px rgba(148,163,184,.3);}
.rank-3{background:linear-gradient(135deg,#cd7c42,#b45309);box-shadow:0 4px 14px rgba(180,83,9,.3);}
.rank-other{background:var(--surface3);color:var(--text-muted);font-size:14px;font-family:'JetBrains Mono',monospace;border:1px solid var(--border);}
.lb-info .lb-name{font-size:14px;font-weight:700;margin-bottom:6px;}
.lb-bar-track{height:5px;background:var(--surface3);border-radius:4px;overflow:hidden;}
.lb-bar-fill{height:100%;border-radius:4px;transition:width 1.2s cubic-bezier(.34,1.56,.64,1);}
.lb-bar-1{background:linear-gradient(90deg,var(--amber),var(--orange));}
.lb-bar-2{background:linear-gradient(90deg,var(--sky),var(--indigo));}
.lb-bar-3{background:linear-gradient(90deg,var(--emerald),var(--teal));}
.lb-bar-o{background:linear-gradient(90deg,var(--violet),var(--violet-light));}
.lb-right{text-align:right;}
.lb-profit{font-size:15px;font-weight:800;font-family:'JetBrains Mono',monospace;color:var(--emerald);}
.lb-tickets{font-size:11px;color:var(--text-muted);margin-top:2px;}
.lb-filter-row{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.lb-filter-row label{font-size:12px;color:var(--text-muted);font-weight:600;}

/* Debt pills */
.debt-pills{display:flex;flex-wrap:wrap;gap:10px;}
.debt-pill{background:var(--surface2);border:1px solid rgba(244,63,94,.25);border-radius:12px;padding:12px 16px;cursor:pointer;transition:all .2s;min-width:175px;text-decoration:none;color:inherit;display:block;}
.debt-pill:hover{border-color:rgba(244,63,94,.5);background:rgba(244,63,94,.06);color:inherit;text-decoration:none;}
.debt-pill-name{font-size:13px;font-weight:700;margin-bottom:5px;}
.debt-pill-amounts{display:flex;gap:8px;flex-wrap:wrap;}
.debt-amt{font-size:12px;font-family:'JetBrains Mono',monospace;}
.debt-neg{color:var(--rose);}
.debt-ok{color:var(--text-muted);}

/* Umrah */
.umrah-stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-bottom:20px;}
.umrah-stat{background:#fff;border:1px solid var(--border);border-radius:16px;padding:20px;text-align:center;position:relative;overflow:hidden;transition:transform 0.2s,box-shadow 0.2s;}
.umrah-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);}
.umrah-stat:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,0.08);}
.umrah-stat-val{font-size:32px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-1px;margin-bottom:8px;}
.umrah-stat-lbl{font-size:11px;color:var(--text-muted);margin-top:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;}
.us-green{color:var(--emerald);}
.us-blue{color:var(--primary);}
.us-violet{color:var(--violet);}

/* Two-col */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px;}

/* Misc */
.d-empty{text-align:center;padding:28px;color:var(--text-muted);}
.d-empty i{font-size:36px;margin-bottom:8px;display:block;}
.d-spinner{display:flex;justify-content:center;align-items:center;padding:36px;color:var(--text-muted);}

/* Responsive */
@media(max-width:1100px){.fin-chart-layout{grid-template-columns:1fr;}.two-col{grid-template-columns:1fr;}}
@media(max-width:900px){.sales-grid{grid-template-columns:1fr 1fr;}.flight-card{grid-template-columns:1fr auto;}.fc-route{display:none;}.umrah-stat-row{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.sales-grid{grid-template-columns:1fr;}.dues-grid{grid-template-columns:1fr 1fr;}.umrah-stat-row{grid-template-columns:1fr;}}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.d-card,.fin-chart-card,.dues-grid,.sales-grid{animation:fadeUp .4s ease both;}

/* Dropdown override */
.dropdown-menu{background:var(--surface2)!important;border:1px solid var(--border)!important;border-radius:12px!important;padding:6px!important;box-shadow:0 12px 40px rgba(0,0,0,.5)!important;}
.dropdown-item{color:var(--text)!important;border-radius:8px!important;padding:8px 14px!important;font-size:13px!important;font-family:'Plus Jakarta Sans',sans-serif!important;}
.dropdown-item:hover{background:var(--surface3)!important;}

/* Notifications Collapse */
#notificationsContent{max-height:500px;overflow-y:auto;overflow-x:hidden;transition:max-height 0.3s ease,opacity 0.3s ease;opacity:1;}
#notificationsContent.collapsed{max-height:0;opacity:0;overflow:hidden;}
.notif-collapse-btn i{transition:transform 0.3s ease;}
.notif-collapse-btn.collapsed i{transform:rotate(180deg);}

/* Umrah Bookings Table */
.umrah-bookings-wrapper{width:100%;overflow-x:auto;}
.umrah-bookings-table{width:100%;border-collapse:collapse;font-family:'Plus Jakarta Sans',sans-serif;}
.umrah-bookings-table thead{background:var(--grad);color:#fff;}
.umrah-bookings-table th{padding:12px 16px;text-align:left;font-size:11px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;}
.umrah-bookings-table tbody tr{border-bottom:1px solid var(--border);transition:background 0.2s;}
.umrah-bookings-table tbody tr:hover{background:var(--surface2);}
.umrah-bookings-table td{padding:12px 16px;font-size:12px;color:var(--text);}
.umrah-bookings-table .text-center{text-align:center;}
.umrah-bookings-table .badge-success{background:rgba(16,185,129,.15);color:var(--emerald);padding:4px 12px;border-radius:6px;font-size:10px;font-weight:700;}
.umrah-bookings-table .badge-info{background:rgba(14,165,233,.15);color:var(--sky);padding:4px 12px;border-radius:6px;font-size:10px;font-weight:700;}
.umrah-bookings-table .badge-warning{background:rgba(245,158,11,.15);color:var(--amber);padding:4px 12px;border-radius:6px;font-size:10px;font-weight:700;}
.umrah-bookings-table .badge-danger{background:rgba(244,63,94,.15);color:var(--rose);padding:4px 12px;border-radius:6px;font-size:10px;font-weight:700;}
.umrah-bookings-table .badge-secondary{background:rgba(100,116,139,.15);color:var(--text-muted);padding:4px 12px;border-radius:6px;font-size:10px;font-weight:700;}
.umrah-bookings-table .badge-pill{display:inline-block;}
.umrah-bookings-table .font-weight-bold{font-weight:700;}
.umrah-bookings-table small{font-size:12px;}
.umrah-bookings-table .text-success{color:var(--emerald);}
</style>

<!-- ─── MAIN CONTENT ──────────────────────────────────────────────────────── -->
<div class="pcoded-main-container">
 <div class="pcoded-wrapper">
  <div class="pcoded-content">
   <div class="pcoded-inner-content">
    <div class="main-body">
     <div class="page-wrapper">
      <div class="dash-wrap">
       <div class="dash-inner">

         <!-- ATTENDANCE STATUS WIDGET -->
         <?php if ($show_att_widget): ?>
         <div class="att-status-widget" id="attStatusWidget">
           <div class="att-widget-left">
             <div class="att-widget-indicator <?= $att_state === 'checked_in' ? 'online pulse' : ($att_state === 'completed' ? 'completed' : 'offline') ?>">
               <?php if ($att_state === 'checked_in'): ?>
                 <i class="fas fa-check" style="color:#10b981;"></i>
               <?php elseif ($att_state === 'completed'): ?>
                 <i class="fas fa-check-double" style="color:#22c55e;"></i>
               <?php else: ?>
                 <i class="fas fa-sign-in-alt" style="color:#f43f5e;"></i>
               <?php endif; ?>
             </div>
             <div class="att-widget-info">
               <h3>
                 <?php if ($att_state === 'checked_in'): ?>
                   <?= __('currently_working') ?>
                 <?php elseif ($att_state === 'completed'): ?>
                   <?= __('shift_completed') ?>
                 <?php else: ?>
                   <?= __('not_checked_in') ?>
                 <?php endif; ?>
               </h3>
               <p>
                 <?php if ($att_state === 'checked_in'): ?>
                   <?= __('checked_in_at') ?>: <strong><?= date('h:i A', strtotime($today_attendance['check_in_time'])) ?></strong>
                 <?php elseif ($att_state === 'completed'): ?>
                   <?= __('checked_out_at') ?>: <strong><?= date('h:i A', strtotime($today_attendance['check_out_time'])) ?></strong>
                 <?php else: ?>
                   Office: <?= date('h:i A', strtotime($attendance_settings['office_start_time'])) ?> - <?= date('h:i A', strtotime($attendance_settings['office_end_time'])) ?>
                 <?php endif; ?>
               </p>
             </div>
           </div>
           <div class="att-widget-right">
             <?php if ($att_state === 'checked_in'): ?>
               <div class="att-widget-time">
                 <div class="att-widget-time-label"><?= __('working_time') ?></div>
                 <div class="att-widget-time-value" id="workingTime"><?= intval(floor($att_working_minutes / 60)) ?>h <?= intval(round($att_working_minutes % 60)) ?>m</div>
               </div>
             <?php elseif ($att_state === 'completed'): ?>
               <div class="att-widget-time">
                 <div class="att-widget-time-label"><?= __('total_hours') ?></div>
                 <div class="att-widget-time-value"><?= intval(floor($att_working_minutes / 60)) ?>h <?= intval(round($att_working_minutes % 60)) ?>m</div>
               </div>
             <?php endif; ?>
             <button class="att-widget-action-btn" onclick="goToAttendance()">
               <i class="fas fa-clock"></i> <?= __('attendance') ?>
             </button>
           </div>
           </div>
           <?php endif; ?>

           <!-- HEADER -->
         <div class="dash-header">
          <div>
            <h1><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name'] ?? 'Admin') ?> 👋</h1>
            <p><?= __('dashboard_subtitle') ?></p>
          </div>
          <div class="header-actions">
            <?php if (in_array($user['role'], ['admin','finance'])): ?>
            <div class="dropdown">
              <button class="dbtn dbtn-ghost dropdown-toggle" data-toggle="dropdown">
                <i class="fas fa-bolt"></i> <?= __('quick_actions') ?>
              </button>
              <div class="dropdown-menu dropdown-menu-right">
                <a class="dropdown-item" href="ticket.php"><i class="fas fa-plus-circle mr-2" style="color:var(--violet-light);"></i><?= __('add_ticket') ?></a>
                <a class="dropdown-item" href="client.php"><i class="fas fa-user-plus mr-2" style="color:var(--emerald);"></i><?= __('add_client') ?></a>
                <a class="dropdown-item" href="supplier.php"><i class="fas fa-truck mr-2" style="color:var(--amber);"></i><?= __('add_supplier') ?></a>
              </div>
            </div>
            <?php endif; ?>
            <button class="dbtn dbtn-info" data-toggle="modal" data-target="#dashboardTutorialsModal">
              <i class="fas fa-play-circle"></i> Watch Tutorials
            </button>
          </div>
        </div>

        <!-- ALERTS -->
        <?php if (in_array($user['role'], ['admin','finance'])): ?>
        <?php if (!empty($suppliersWithLowBalance)): ?>
        <div class="d-alert d-alert-warning">
          <div class="d-alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div style="flex:1">
            <div class="d-alert-title">Low Supplier Balance Alert</div>
            <div style="font-size:13px;color:rgba(255,255,255,.55);"><?= count($suppliersWithLowBalance) ?> supplier(s) need attention</div>
            <div class="d-alert-items">
              <?php foreach ($suppliersWithLowBalance as $sup):
                $sym = ($sup['currency']==='USD')?'$':'؋'; ?>
              <span class="d-alert-chip"><?= htmlspecialchars($sup['name']) ?> · <?= $sym.number_format($sup['balance'],2) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="d-alert-close" onclick="this.parentElement.style.display='none'">✕</button>
        </div>
        <?php endif; ?>
        <?php if (!empty($clientsWithLowBalance)): ?>
        <div class="d-alert d-alert-danger">
          <div class="d-alert-icon"><i class="fas fa-circle-exclamation"></i></div>
          <div style="flex:1">
            <div class="d-alert-title">Client Balance Due Alert</div>
            <div style="font-size:13px;color:rgba(255,255,255,.55);"><?= count($clientsWithLowBalance) ?> client(s) exceeded thresholds</div>
            <div class="d-alert-items">
              <?php foreach ($clientsWithLowBalance as $cl):
                $parts=[];
                if ($cl['usd_balance']<-1000) $parts[]="USD: $".number_format($cl['usd_balance'],2);
                if ($cl['afs_balance']<-20000) $parts[]="AFS: ؋".number_format($cl['afs_balance'],2); ?>
              <span class="d-alert-chip"><?= htmlspecialchars($cl['name']) ?> · <?= implode(' | ',$parts) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="d-alert-close" onclick="this.parentElement.style.display='none'">✕</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- FINANCIAL WEALTH CHART (admin only) -->
        <?php if ($user['role']==='admin'): ?>
        <div class="sec-label"><i class="fas fa-chart-area"></i> <?= __('financial_wealth_distribution') ?></div>
        <div class="fin-chart-card">
          <div class="d-card-header" style="margin-bottom:18px;">
            <div class="d-card-title"><div class="ci ci-violet"><i class="fas fa-chart-area"></i></div><?= __('financial_wealth_distribution') ?></div>
            <div class="fin-controls">
              <select class="fin-select" id="financeChartPeriod">
                <option value="daily"><?= __('daily') ?></option>
                <option value="monthly" selected><?= __('monthly') ?></option>
                <option value="yearly"><?= __('yearly') ?></option>
              </select>
              <select class="fin-select" id="financeChartCurrency">
                <option value="USD" selected><?= __('usd') ?></option>
                <option value="AFS"><?= __('afs') ?></option>
                <option value="EUR"><?= __('eur') ?></option>
                <option value="AED"><?= __('aed') ?></option>
              </select>
            </div>
          </div>
          <div class="fin-chart-layout">
            <div><div id="financeFlowChart" style="min-height:350px;"></div></div>
            <div class="wealth-panel">
              <div class="wealth-panel-title"><i class="fas fa-wallet"></i> <?= __('wealth_distribution') ?></div>
              <div class="wealth-date-badge" id="currentDateBadge"><?= date('F Y') ?></div>
              <div class="wealth-row">
                <div class="wealth-row-label"><div class="wealth-dot" style="background:var(--violet);"></div><?= __('main_accounts') ?></div>
                <div class="wealth-row-value" id="mainAccountBalance" style="color:var(--violet-light);">$0.00</div>
              </div>
              <div class="wealth-row">
                <div class="wealth-row-label"><div class="wealth-dot" style="background:var(--sky);"></div><?= __('supplier_credits') ?></div>
                <div class="wealth-row-value" id="supplierBalance" style="color:var(--sky);">$0.00</div>
              </div>
              <div class="wealth-row">
                <div class="wealth-row-label"><div class="wealth-dot" style="background:var(--emerald);"></div><?= __('client_credits') ?></div>
                <div class="wealth-row-value" id="clientBalance" style="color:var(--emerald);">$0.00</div>
              </div>
              <div class="wealth-row">
                <div class="wealth-row-label"><div class="wealth-dot" style="background:var(--rose);"></div><?= __('debtor_balance') ?></div>
                <div class="wealth-row-value" id="debtorBalance" style="color:var(--rose);">$0.00</div>
              </div>
              <div class="wealth-total-row">
                <div class="wealth-total-label"><?= __('total_net_worth') ?></div>
                <div class="wealth-total-value" id="totalNetWorth">$0.00</div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- SALES CARDS (admin only) -->
         <?php if ($user['role']==='admin'): ?>
         <div class="sec-label"><i class="fas fa-chart-line"></i> Performance Overview</div>
         
         <!-- Sales Filter Controls (Outside Cards) -->
         <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px;">
           <!-- Daily Filter -->
           <div style="background:linear-gradient(135deg,rgba(255,193,7,.08),rgba(255,193,7,.04));border:1.5px solid rgba(255,193,7,.3);border-radius:14px;padding:14px;position:relative;overflow:hidden;">
             <div style="position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:radial-gradient(circle,rgba(255,193,7,.1),transparent);border-radius:50%;"></div>
             <div style="position:relative;z-index:1;">
               <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                 <label style="font-size:12px;font-weight:700;color:var(--text);margin:0;letter-spacing:0.5px;text-transform:uppercase;"><i class="fas fa-sun" style="color:#f59e0b;margin-right:6px;"></i><?= __('daily') ?></label>
                 <button class="dbtn dbtn-ghost" style="padding:3px 8px;font-size:10px;border-radius:6px;color:#f59e0b;opacity:0.7;transition:all .2s;" onclick="resetDailyFilter()" title="Reset to today"><i class="fas fa-redo-alt"></i></button>
               </div>
               <div style="display:flex;gap:6px;align-items:stretch;">
                 <input type="date" class="sc-filter-input date-filter" id="dailyDateInput" value="<?= date('Y-m-d') ?>" style="flex:1;padding:9px 12px;font-size:12px;background:#fff;color:var(--text);border:1px solid rgba(245,158,11,.2);border-radius:8px;">
                 <button class="sc-filter-btn apply-daily-filter" style="flex:0;padding:9px 12px;background:#f59e0b;color:#fff;border-radius:8px;border:none;font-weight:600;transition:all .2s;" onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'"><i class="fas fa-check"></i></button>
               </div>
             </div>
           </div>
           
           <!-- Monthly Filter -->
           <div style="background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(59,130,246,.04));border:1.5px solid rgba(59,130,246,.3);border-radius:14px;padding:14px;position:relative;overflow:hidden;">
             <div style="position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:radial-gradient(circle,rgba(59,130,246,.1),transparent);border-radius:50%;"></div>
             <div style="position:relative;z-index:1;">
               <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                 <label style="font-size:12px;font-weight:700;color:var(--text);margin:0;letter-spacing:0.5px;text-transform:uppercase;"><i class="fas fa-calendar-alt" style="color:#3b82f6;margin-right:6px;"></i><?= __('monthly') ?></label>
                 <button class="dbtn dbtn-ghost" style="padding:3px 8px;font-size:10px;border-radius:6px;color:#3b82f6;opacity:0.7;transition:all .2s;" onclick="resetMonthlyFilter()" title="Reset to this month"><i class="fas fa-redo-alt"></i></button>
               </div>
               <div style="display:flex;gap:6px;align-items:stretch;">
                 <input type="month" class="sc-filter-input date-filter" id="monthlyDateInput" value="<?= date('Y-m') ?>" style="flex:1;padding:9px 12px;font-size:12px;background:#fff;color:var(--text);border:1px solid rgba(59,130,246,.2);border-radius:8px;">
                 <button class="sc-filter-btn apply-monthly-filter" style="flex:0;padding:9px 12px;background:#3b82f6;color:#fff;border-radius:8px;border:none;font-weight:600;transition:all .2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'"><i class="fas fa-check"></i></button>
               </div>
             </div>
           </div>
           
           <!-- Yearly Filter -->
           <div style="background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(16,185,129,.04));border:1.5px solid rgba(16,185,129,.3);border-radius:14px;padding:14px;position:relative;overflow:hidden;">
             <div style="position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:radial-gradient(circle,rgba(16,185,129,.1),transparent);border-radius:50%;"></div>
             <div style="position:relative;z-index:1;">
               <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                 <label style="font-size:12px;font-weight:700;color:var(--text);margin:0;letter-spacing:0.5px;text-transform:uppercase;"><i class="fas fa-chart-line" style="color:#10b981;margin-right:6px;"></i><?= __('yearly') ?></label>
                 <button class="dbtn dbtn-ghost" style="padding:3px 8px;font-size:10px;border-radius:6px;color:#10b981;opacity:0.7;transition:all .2s;" onclick="resetYearlyFilter()" title="Reset to this year"><i class="fas fa-redo-alt"></i></button>
               </div>
               <div style="display:flex;gap:6px;align-items:stretch;">
                 <select class="sc-filter-input date-filter" id="yearlyDateInput" style="flex:1;padding:9px 12px;font-size:12px;background:#fff;color:var(--text);border:1px solid rgba(16,185,129,.2);border-radius:8px;">
                   <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                   <option value="<?=$y?>"<?=$y==$cy?' selected':''?>><?=$y?></option>
                   <?php endfor; ?>
                 </select>
                 <button class="sc-filter-btn apply-yearly-filter" style="flex:0;padding:9px 12px;background:#10b981;color:#fff;border-radius:8px;border:none;font-weight:600;transition:all .2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'"><i class="fas fa-check"></i></button>
               </div>
             </div>
           </div>
         </div>
         
         <div class="sales-grid">
           <!-- Daily -->
           <div class="sales-card sc-daily daily-sales" data-type="daily"
                data-usd="<?= number_format($dailySales['usd_profit'],2) ?>"
                data-afs="<?= number_format($dailySales['afs_profit'],2) ?>">
             <div class="sc-label"><i class="fas fa-sun"></i> <?= __('daily_sales') ?></div>
             <div class="sc-amount">$<span id="dailyUsdProfit"><?= number_format($dailySales['usd_profit'],2) ?></span></div>
             <div class="sc-secondary">؋<span id="dailyAfsProfit"><?= number_format($dailySales['afs_profit'],2) ?></span></div>
             <div class="sc-footer">
               <span class="trend-badge"><?php if($dailyTrendPercent>=0):?><i class="fas fa-arrow-up"></i><?php else:?><i class="fas fa-arrow-down"></i><?php endif;?> <?= number_format(abs($dailyTrendPercent),1) ?>%</span>
               <div class="sc-sparkline"><div class="spark-bar" style="height:35%"></div><div class="spark-bar" style="height:55%"></div><div class="spark-bar" style="height:40%"></div><div class="spark-bar" style="height:75%"></div><div class="spark-bar" style="height:60%"></div><div class="spark-bar" style="height:80%"></div><div class="spark-bar hi" style="height:100%"></div></div>
             </div>
             <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
               <span style="font-size:11px;opacity:.6;" id="dailyDateDisplay"><?= __('today') ?></span>
             </div>
           </div>
           
           <!-- Monthly -->
           <div class="sales-card sc-monthly monthly-sales" data-type="monthly"
                data-usd="<?= number_format($monthlySales['usd_profit'],2) ?>"
                data-afs="<?= number_format($monthlySales['afs_profit'],2) ?>">
             <div class="sc-label"><i class="fas fa-calendar-alt"></i> <?= __('monthly_sales') ?></div>
             <div class="sc-amount">$<span id="monthlyUsdProfit"><?= number_format($monthlySales['usd_profit'],2) ?></span></div>
             <div class="sc-secondary">؋<span id="monthlyAfsProfit"><?= number_format($monthlySales['afs_profit'],2) ?></span></div>
             <div class="sc-footer">
               <span class="trend-badge"><?php if($monthlyTrendPercent>=0):?><i class="fas fa-arrow-up"></i><?php else:?><i class="fas fa-arrow-down"></i><?php endif;?> <?= number_format(abs($monthlyTrendPercent),1) ?>%</span>
               <div class="sc-sparkline"><div class="spark-bar" style="height:30%"></div><div class="spark-bar" style="height:50%"></div><div class="spark-bar" style="height:65%"></div><div class="spark-bar" style="height:45%"></div><div class="spark-bar" style="height:80%"></div><div class="spark-bar" style="height:60%"></div><div class="spark-bar hi" style="height:100%"></div></div>
             </div>
             <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
               <span style="font-size:11px;opacity:.6;" id="monthlyDateDisplay"><?= date('M Y') ?></span>
             </div>
           </div>
           
           <!-- Yearly -->
           <div class="sales-card sc-yearly yearly-sales" data-type="yearly"
                data-usd="<?= number_format($yearlySales['usd_profit'],2) ?>"
                data-afs="<?= number_format($yearlySales['afs_profit'],2) ?>">
             <div class="sc-label"><i class="fas fa-chart-line"></i> <?= __('yearly_sales') ?></div>
             <div class="sc-amount">$<span id="yearlyUsdProfit"><?= number_format($yearlySales['usd_profit'],2) ?></span></div>
             <div class="sc-secondary">؋<span id="yearlyAfsProfit"><?= number_format($yearlySales['afs_profit'],2) ?></span></div>
             <div class="sc-footer">
               <span class="trend-badge"><?php if($yearlyTrendPercent>=0):?><i class="fas fa-arrow-up"></i><?php else:?><i class="fas fa-arrow-down"></i><?php endif;?> <?= number_format(abs($yearlyTrendPercent),1) ?>%</span>
               <div class="sc-sparkline"><div class="spark-bar" style="height:20%"></div><div class="spark-bar" style="height:38%"></div><div class="spark-bar" style="height:32%"></div><div class="spark-bar" style="height:58%"></div><div class="spark-bar" style="height:52%"></div><div class="spark-bar" style="height:72%"></div><div class="spark-bar hi" style="height:100%"></div></div>
             </div>
             <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
               <span style="font-size:11px;opacity:.6;" id="yearlyDateDisplay"><?= date('Y') ?></span>
             </div>
           </div>
         </div>
         <?php endif; ?>

        <!-- OUTSTANDING DUES (admin + finance) -->
        <?php if (in_array($user['role'],['admin','finance'])): ?>
        <div class="sec-label"><i class="fas fa-receipt"></i> <?= __('outstanding_dues') ?></div>
        <div class="dues-grid">
          <?php if (hasFeature('ticket_bookings',$allowed_features)): ?>
          <div class="due-card dc-ticket" data-type="ticket"><div class="due-icon"><i class="fas fa-ticket-alt"></i></div><div class="due-label"><?= __('ticket_bookings') ?></div><div class="due-usd" id="ticketDuesUSD">$0.00</div><div class="due-afs" id="ticketDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('date_change_tickets',$allowed_features)): ?>
          <div class="due-card dc-datechange" data-type="datechange"><div class="due-icon"><i class="fas fa-calendar-times"></i></div><div class="due-label"><?= __('date_change') ?></div><div class="due-usd" id="dateChangeDuesUSD">$0.00</div><div class="due-afs" id="dateChangeDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('refunded_tickets',$allowed_features)): ?>
          <div class="due-card dc-refund" data-type="refunded"><div class="due-icon"><i class="fas fa-undo-alt"></i></div><div class="due-label"><?= __('refunded_tickets') ?></div><div class="due-usd" id="refundedDuesUSD">$0.00</div><div class="due-afs" id="refundedDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('umrah_bookings',$allowed_features)): ?>
          <div class="due-card dc-umrah" data-type="umrah"><div class="due-icon"><i class="fas fa-mosque"></i></div><div class="due-label"><?= __('umrah') ?></div><div class="due-usd" id="umrahDuesUSD">$0.00</div><div class="due-afs" id="umrahDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('visa_applications',$allowed_features)): ?>
          <div class="due-card dc-visa" data-type="visa"><div class="due-icon"><i class="fas fa-passport"></i></div><div class="due-label"><?= __('visa') ?></div><div class="due-usd" id="visaDuesUSD">$0.00</div><div class="due-afs" id="visaDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('hotel_bookings',$allowed_features)): ?>
          <div class="due-card dc-hotel" data-type="hotel"><div class="due-icon"><i class="fas fa-hotel"></i></div><div class="due-label"><?= __('hotel') ?></div><div class="due-usd" id="hotelDuesUSD">$0.00</div><div class="due-afs" id="hotelDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('additional_payments',$allowed_features)): ?>
          <div class="due-card dc-addpay" data-type="addpayment"><div class="due-icon"><i class="fas fa-dollar-sign"></i></div><div class="due-label"><?= __('additional_payments') ?></div><div class="due-usd" id="addpaymentDuesUSD">$0.00</div><div class="due-afs" id="addpaymentDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('ticket_weights',$allowed_features)): ?>
          <div class="due-card dc-weight" data-type="weight"><div class="due-icon"><i class="fas fa-weight-hanging"></i></div><div class="due-label"><?= __('weight') ?></div><div class="due-usd" id="weightDuesUSD">$0.00</div><div class="due-afs" id="weightDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
          <?php if (hasFeature('ticket_reservations',$allowed_features)): ?>
          <div class="due-card dc-reserve" data-type="ticket_reserve"><div class="due-icon"><i class="fas fa-bookmark"></i></div><div class="due-label"><?= __('ticket_reserve') ?></div><div class="due-usd" id="ticketReserveDuesUSD">$0.00</div><div class="due-afs" id="ticketReserveDuesAFS">؋0.00</div><div class="due-bar-track"><div class="due-bar-fill"></div></div></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- TICKET BOOKINGS + NOTIFICATIONS -->
        <?php
        $today_stmt=$this_week_stmt=$this_month_stmt=$today_departures_stmt=null;
        try {
          $today_stmt=$pdo->prepare("SELECT tb.*,s.name AS supplier_name FROM ticket_bookings tb LEFT JOIN suppliers s ON tb.supplier=s.id AND s.tenant_id=tb.tenant_id WHERE DATE(tb.created_at)=CURDATE() AND tb.tenant_id=?");
          $today_stmt->execute([$tenant_id]);
          $this_week_stmt=$pdo->prepare("SELECT tb.*,s.name AS supplier_name FROM ticket_bookings tb LEFT JOIN suppliers s ON tb.supplier=s.id AND s.tenant_id=tb.tenant_id WHERE YEARWEEK(tb.created_at,1)=YEARWEEK(CURDATE(),1) AND tb.tenant_id=?");
          $this_week_stmt->execute([$tenant_id]);
          $this_month_stmt=$pdo->prepare("SELECT tb.*,s.name AS supplier_name FROM ticket_bookings tb LEFT JOIN suppliers s ON tb.supplier=s.id AND s.tenant_id=tb.tenant_id WHERE YEAR(tb.created_at)=YEAR(CURDATE()) AND MONTH(tb.created_at)=MONTH(CURDATE()) AND tb.tenant_id=?");
          $this_month_stmt->execute([$tenant_id]);
          $today_departures_stmt=$pdo->prepare("SELECT tb.*,s.name AS supplier_name,tb.phone AS passenger_phone FROM ticket_bookings tb LEFT JOIN suppliers s ON tb.supplier=s.id AND s.tenant_id=tb.tenant_id WHERE DATE(tb.departure_date)=? AND tb.tenant_id=?");
          $today_departures_stmt->execute([$selected_date,$tenant_id]);
        } catch(PDOException $e){error_log($e->getMessage());}

        // Helper to render flight card rows
        function renderFlightCard($row) {
          $o=$row['origin']??$row['from_city']??''; $d=$row['destination']??$row['to_city']??'';
          $oc=$row['origin_code']??$row['from_code']??''; $dc=$row['destination_code']??$row['to_code']??'';
          echo '<div class="flight-card">';
          echo '<div class="fc-pass"><h3>'.htmlspecialchars($row['passenger_name']).'</h3>';
          echo '<span><i class="fas fa-barcode" style="font-size:10px;"></i> '.htmlspecialchars($row['pnr']).'</span>';
          if (!empty($row['passenger_phone'])) echo '<span style="color:var(--sky);margin-top:3px;"><i class="fas fa-phone-alt" style="font-size:10px;"></i> '.htmlspecialchars($row['passenger_phone']).'</span>';
          echo '</div>';
          echo '<div class="fc-route"><div><div class="fc-city-code">'.htmlspecialchars($oc?:strtoupper(substr($o,0,3))).'</div><div class="fc-city-name">'.htmlspecialchars($o).'</div></div><div class="fc-arrow"><div class="fc-arrow-line"></div><div class="fc-airline">'.htmlspecialchars($row['airline']??$row['supplier_name']??'').'</div></div><div><div class="fc-city-code">'.htmlspecialchars($dc?:strtoupper(substr($d,0,3))).'</div><div class="fc-city-name">'.htmlspecialchars($d).'</div></div></div>';
          echo '<div class="fc-dates"><div class="fc-date-row"><i class="fas fa-calendar-check"></i> '.htmlspecialchars(__('issue')).': <span>'.date('d M Y',strtotime($row['issue_date'])).'</span></div><div class="fc-date-row dep"><i class="fas fa-plane-departure"></i> '.htmlspecialchars(__('departure')).': <span>'.date('d M Y',strtotime($row['departure_date'])).'</span></div></div>';
          echo '<div class="fc-sold">'.htmlspecialchars($row['sold']).'</div>';
          echo '</div>';
        }
        ?>

        <div class="two-col">

          <!-- Ticket Bookings -->
          <?php if ($showTickets && in_array($user['role'],['admin','finance','sales'])): ?>
          <div class="d-card" style="margin-bottom:0;">
            <div class="d-card-header">
              <div class="d-card-title"><div class="ci ci-violet"><i class="fas fa-plane"></i></div><?= __('ticket_bookings_overview') ?></div>
            </div>
            <div class="ftabs">
              <button class="ftab active" onclick="switchFlightTab(this,'ftab-dep')">✈ <?= $selected_date===date('Y-m-d')?__('todays_departures'):'Dep '.date('M d',strtotime($selected_date)) ?></button>
              <button class="ftab" onclick="switchFlightTab(this,'ftab-today')"><?= __('today') ?></button>
              <button class="ftab" onclick="switchFlightTab(this,'ftab-week')"><?= __('this_week') ?></button>
              <button class="ftab" onclick="switchFlightTab(this,'ftab-month')"><?= __('this_month') ?></button>
            </div>

            <div id="ftab-dep">
              <div class="dep-filter-bar">
                <form method="GET" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                  <label>Departure Date:</label>
                  <input type="date" name="departure_date" class="dep-date-input" value="<?= htmlspecialchars($selected_date) ?>">
                  <button type="submit" class="dbtn dbtn-primary" style="padding:6px 14px;font-size:12px;">Filter</button>
                  <a href="dashboard.php" class="dbtn dbtn-ghost" style="padding:6px 14px;font-size:12px;">Today</a>
                </form>
              </div>
              <div class="flight-cards">
                <?php if ($today_departures_stmt&&$today_departures_stmt->rowCount()>0): while($row=$today_departures_stmt->fetch(PDO::FETCH_ASSOC)): renderFlightCard($row); endwhile; else: ?>
                <div class="d-empty"><i class="fas fa-plane-slash"></i>No departures for this date</div>
                <?php endif; ?>
              </div>
            </div>
            <div id="ftab-today" style="display:none;"><div class="flight-cards">
              <?php if ($today_stmt&&$today_stmt->rowCount()>0): while($row=$today_stmt->fetch(PDO::FETCH_ASSOC)): renderFlightCard($row); endwhile; else: ?>
              <div class="d-empty"><i class="fas fa-ticket-alt"></i>No tickets booked today</div>
              <?php endif; ?>
            </div></div>
            <div id="ftab-week" style="display:none;"><div class="flight-cards">
              <?php if ($this_week_stmt&&$this_week_stmt->rowCount()>0): while($row=$this_week_stmt->fetch(PDO::FETCH_ASSOC)): renderFlightCard($row); endwhile; else: ?>
              <div class="d-empty"><i class="fas fa-ticket-alt"></i>No tickets this week</div>
              <?php endif; ?>
            </div></div>
            <div id="ftab-month" style="display:none;"><div class="flight-cards">
              <?php if ($this_month_stmt&&$this_month_stmt->rowCount()>0): while($row=$this_month_stmt->fetch(PDO::FETCH_ASSOC)): renderFlightCard($row); endwhile; else: ?>
              <div class="d-empty"><i class="fas fa-ticket-alt"></i>No tickets this month</div>
              <?php endif; ?>
            </div></div>
          </div>
          <?php endif; ?>

          <!-- Notifications (admin only) -->
          <?php if ($user['role']==='admin'): ?>
          <div class="d-card" style="margin-bottom:0;" id="notificationsCard">
            <div class="d-card-header" style="justify-content: space-between;">
              <div class="d-card-title"><div class="ci ci-sky"><i class="fas fa-bell"></i></div><?= __('recent_notifications') ?></div>
              <button class="notif-collapse-btn" onclick="toggleNotificationsCollapse()" title="Collapse/Expand" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:18px;padding:4px;transition:transform 0.3s ease;">
                <i class="fas fa-chevron-up"></i>
              </button>
            </div>
            <div class="notif-tabs-row">
              <button class="notif-tab-btn active" onclick="switchNotifTab(this,'ntab-unread')">
                <?= __('unread') ?> <span class="nbadge" id="unreadNotifCount"><?php try{$cs=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE status='unread' AND tenant_id=?");$cs->execute([$tenant_id]);echo $cs->fetchColumn();}catch(Exception $e){echo 0;} ?></span>
              </button>
              <button class="notif-tab-btn" onclick="switchNotifTab(this,'ntab-read')"><?= __('read') ?></button>
            </div>
            <div id="notificationsContent">
              <div id="ntab-unread">
              <?php
              try {
                $nq="SELECT n.*,CASE WHEN n.transaction_type='visa' THEN va.applicant_name WHEN n.transaction_type='supplier' THEN s.name WHEN n.transaction_type='umrah' THEN ub.name ELSE NULL END AS related_name,CASE WHEN n.transaction_type='visa' THEN va.base WHEN n.transaction_type='supplier' THEN st.amount WHEN n.transaction_type='umrah' THEN ub.sold_price ELSE 0 END AS transaction_amount,CASE WHEN n.transaction_type='visa' THEN va.currency WHEN n.transaction_type='supplier' THEN s.currency ELSE NULL END AS transaction_currency,CASE WHEN n.transaction_type='umrah' THEN ut.transaction_to ELSE NULL END AS umrah_transaction_to FROM notifications n LEFT JOIN visa_applications va ON n.transaction_id=va.id AND n.transaction_type='visa' LEFT JOIN umrah_bookings ub ON n.transaction_id=ub.booking_id AND n.transaction_type='umrah' LEFT JOIN umrah_transactions ut ON n.transaction_id=ut.id AND n.transaction_type='umrah' LEFT JOIN supplier_transactions st ON n.transaction_id=st.id AND n.transaction_type='supplier' LEFT JOIN suppliers s ON st.supplier_id=s.id OR va.supplier=s.id WHERE n.status='unread' AND n.tenant_id=? ORDER BY n.created_at DESC";
                $ns=$pdo->prepare($nq);$ns->execute([$tenant_id]);
                if($ns->rowCount()>0) displayModernNotifications($ns,'unread');
                else echo '<div class="d-empty"><i class="fas fa-bell-slash"></i>'.__('no_unread_notifications_available').'</div>';
              } catch(PDOException $e){error_log($e->getMessage());}
              ?>
            </div>
            <div id="ntab-read" style="display:none;">
              <div class="read-filter">
                <label><?= __('filter') ?>:</label>
                <input type="date" class="dep-date-input" id="readNotificationsDate" value="<?= date('Y-m-d') ?>">
                <button class="dbtn dbtn-ghost" id="applyReadDateFilter" style="padding:6px 14px;font-size:12px;"><i class="fas fa-filter"></i> <?= __('filter') ?></button>
              </div>
              <div id="readNotificationsBody">
                <?php
                  try {
                    $rq="SELECT n.*,CASE WHEN n.transaction_type='visa' THEN va.applicant_name WHEN n.transaction_type='supplier' THEN s.name WHEN n.transaction_type='umrah' THEN ub.name ELSE NULL END AS related_name,CASE WHEN n.transaction_type='visa' THEN va.base WHEN n.transaction_type='supplier' THEN st.amount WHEN n.transaction_type='umrah' THEN ub.sold_price ELSE 0 END AS transaction_amount,CASE WHEN n.transaction_type='visa' THEN va.currency WHEN n.transaction_type='supplier' THEN s.currency ELSE NULL END AS transaction_currency,CASE WHEN n.transaction_type='umrah' THEN ut.transaction_to ELSE NULL END AS umrah_transaction_to FROM notifications n LEFT JOIN visa_applications va ON n.transaction_id=va.id AND n.transaction_type='visa' LEFT JOIN umrah_bookings ub ON n.transaction_id=ub.booking_id AND n.transaction_type='umrah' LEFT JOIN umrah_transactions ut ON n.transaction_id=ut.id AND n.transaction_type='umrah' LEFT JOIN supplier_transactions st ON n.transaction_id=st.id AND n.transaction_type='supplier' LEFT JOIN suppliers s ON st.supplier_id=s.id OR va.supplier=s.id WHERE n.status='read' AND DATE(n.created_at)=? AND n.tenant_id=? ORDER BY n.created_at DESC";
                    $rs=$pdo->prepare($rq);$rs->execute([date('Y-m-d'),$tenant_id]);
                    if($rs->rowCount()>0) displayModernNotifications($rs,'read');
                    else echo '<div class="d-empty"><i class="fas fa-inbox"></i>'.__('no_read_notifications_for_selected_date').'</div>';
                  } catch(PDOException $e){error_log($e->getMessage());}
                  ?>
                </div>
                </div>
                </div>
                </div>
                <?php endif; ?>

            </div><!-- /.two-col -->

        <!-- TOP PERFORMERS + CLIENT DEBTS -->
        <?php if (in_array($user['role'],['admin','finance'])): ?>
        <div class="two-col">
          <?php if ($showTickets && $user['role']==='admin'): ?>
          <div class="d-card" style="margin-bottom:0;">
            <div class="d-card-header">
              <div class="d-card-title"><div class="ci ci-amber"><i class="fas fa-trophy"></i></div><?= __('top_performers') ?></div>
              <span style="font-size:12px;color:var(--text-muted);"><?= date('M Y') ?></span>
            </div>
            <div class="lb-filter-row">
              <label><?= __('performance_period') ?>:</label>
              <input type="month" class="dep-date-input date-filter" id="performanceDateInput" value="<?= date('Y-m') ?>">
              <button class="dbtn dbtn-ghost apply-performance-filter" style="padding:6px 14px;font-size:12px;"><i class="fas fa-filter"></i> <?= __('apply_filter') ?></button>
            </div>
            <div class="lb-list" id="topPerformersTableBody">
              <?php
              $topPerformers=getTopPerformersByTicketProfit(date('m'),date('Y'));
              if (count($topPerformers)>0):
                $rank=1; $maxP=max(array_column($topPerformers,'total_profit_usd'));
                foreach($topPerformers as $p):
                  $pct=$maxP>0?round(($p['total_profit_usd']/$maxP)*100):0;
                  $rc=$rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'rank-other'));
                  $ri=$rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':$rank));
                  $bc=$rank===1?'lb-bar-1':($rank===2?'lb-bar-2':($rank===3?'lb-bar-3':'lb-bar-o'));
              ?>
              <div class="lb-item">
                <div class="lb-rank <?=$rc?>"><?=$ri?></div>
                <div class="lb-info"><div class="lb-name"><?=htmlspecialchars($p['user_name'])?></div><div class="lb-bar-track"><div class="lb-bar-fill <?=$bc?>" style="width:<?=$pct?>%"></div></div></div>
                <div class="lb-right"><div class="lb-profit">$<?=number_format($p['total_profit_usd'],2)?></div><div class="lb-tickets"><?=$p['total_tickets']?> <?=__('total_tickets')?></div></div>
              </div>
              <?php $rank++; endforeach;
              else: echo '<div class="d-empty"><i class="fas fa-award"></i>'.__('no_ticket_sales_data_available').'</div>';
              endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <div class="d-card" style="margin-bottom:0;">
            <div class="d-card-header">
              <div class="d-card-title"><div class="ci ci-rose"><i class="fas fa-exclamation-circle"></i></div><?= __('client_debts') ?></div>
              <span style="font-size:12px;color:var(--text-muted);"><?= __('clients_with_negative_balance') ?></span>
            </div>
            <div class="debt-pills">
              <?php $clientsWithDebts=getClientsWithDebts();
              if (count($clientsWithDebts)>0):
                foreach($clientsWithDebts as $cl): ?>
              <a href="client_detail.php?id=<?=$cl['id']?>" class="debt-pill">
                <div class="debt-pill-name"><?=htmlspecialchars($cl['name'])?></div>
                <div class="debt-pill-amounts">
                  <span class="debt-amt <?=$cl['usd_balance']<0?'debt-neg':'debt-ok'?>">$<?=number_format($cl['usd_balance'],2)?></span>
                  <span class="debt-amt debt-ok" style="opacity:.4;">|</span>
                  <span class="debt-amt <?=$cl['afs_balance']<0?'debt-neg':'debt-ok'?>">؋<?=number_format($cl['afs_balance'],2)?></span>
                </div>
              </a>
              <?php endforeach;
              else: echo '<div class="d-empty"><i class="fas fa-check-circle" style="color:var(--emerald);"></i>'.__('no_clients_with_negative_balance_found').'</div>';
              endif; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- UMRAH STATISTICS -->
        <div class="d-card">
          <div class="d-card-header">
            <div class="d-card-title"><div class="ci ci-emerald"><i class="feather icon-map-pin"></i></div><?= __('umrah_statistics') ?></div>
            <span style="font-size:12px;color:var(--text-muted);"><?= __('this_month') ?></span>
          </div>
          <div class="umrah-stat-row">
            <div class="umrah-stat"><div class="umrah-stat-val us-green" id="umrahTotalBookings">0</div><div class="umrah-stat-lbl"><?= __('total_bookings') ?></div></div>
            <div class="umrah-stat"><div class="umrah-stat-val us-blue"  id="umrahActivePackages">0</div><div class="umrah-stat-lbl"><?= __('ongoing_bookings') ?></div></div>
            <div class="umrah-stat"><div class="umrah-stat-val us-violet" id="umrahServices">0</div><div class="umrah-stat-lbl"><?= __('total_pilgrims') ?></div></div>
          </div>
          <div class="sec-label"><?= __('recent_bookings') ?></div>
          <div class="umrah-bookings-wrapper">
            <table class="umrah-bookings-table">
              <thead>
                <tr>
                  <th><?= __('booking_id') ?></th>
                  <th><?= __('package_type') ?></th>
                  <th><?= __('passenger_name') ?></th>
                  <th><?= __('amount') ?></th>
                  <th><?= __('status') ?></th>
                </tr>
              </thead>
              <tbody id="umrahBookingsTable">
                <tr>
                  <td colspan="5" class="text-center"><div class="d-spinner"><i class="fas fa-spinner fa-spin mr-2"></i><?= __('loading') ?>...</div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

       </div><!-- /.dash-inner -->
      </div><!-- /.dash-wrap -->
     </div>
    </div>
   </div>
  </div>
 </div>
</div>

<?php
// ─── displayModernNotifications() ────────────────────────────────────────────
function displayModernNotifications($stmt, $status) {
     $byDate=[];
     while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
         $date=date('Y-m-d',strtotime($row['created_at']));
         $byDate[$date][]=$row;
     }
     foreach($byDate as $date=>$rows){
         $lbl=($date===date('Y-m-d'))?__('today'):(($date===date('Y-m-d',strtotime('-1 day')))?__('yesterday'):date('l, F j, Y',strtotime($date)));
         echo '<div class="tl-date-group"><div class="tl-date-hdr">'.$lbl.'</div>';
         foreach($rows as $row){
             $id=htmlspecialchars($row['id']);
             $msg=htmlspecialchars($row['message']);
             $name=htmlspecialchars($row['related_name']??'');
             $amt=$row['transaction_amount']??0;
             $cur=htmlspecialchars($row['transaction_currency']??'');
             $type=htmlspecialchars($row['transaction_type']??'');
             $umrahTransactionTo=htmlspecialchars($row['umrah_transaction_to']??'');
             $time=date('g:i A',strtotime($row['created_at']));
             $sym=($cur==='USD')?'$':($cur==='AFS'?'؋':($cur==='EUR'?'€':''));
             $dotClass='tld-default';$icon='fa-bell';$iconPrefix='fas';
              switch($type){
                  case 'visa':    $dotClass='tld-visa';    $icon='fa-passport';$iconPrefix='fas';break;
                  case 'supplier':$dotClass='tld-supplier';$icon='fa-truck';$iconPrefix='fas';break;
                  case 'umrah':   $dotClass='tld-umrah';   $icon='icon-map-pin';$iconPrefix='feather';break;
                  case 'ticket':  $dotClass='tld-ticket';  $icon='fa-ticket-alt';$iconPrefix='fas';break;
                  case 'refund':  $dotClass='tld-refund';  $icon='fa-undo-alt';$iconPrefix='fas';break;
                  case 'expense':case 'expense_update':case 'expense_delete':$dotClass='tld-expense';$icon='fa-receipt';$iconPrefix='fas';break;
                  case 'hotel':   $dotClass='tld-hotel';   $icon='fa-hotel';$iconPrefix='fas';break;
                  case 'deposit_sarafi':case 'hawala_sarafi':case 'withdrawal_sarafi':$dotClass='tld-sarafi';$icon='fa-exchange-alt';$iconPrefix='fas';break;
              }
              // Check if notification contains "deleted" in the message
              $isDeleted = stripos($msg, 'deleted') !== false;
              // For umrah transactions with transaction_to='Bank', hide the "Received" button
              $isBankTransaction = $type === 'umrah' && $umrahTransactionTo === 'Bank';
              $readOnly=in_array($type,['deposit_sarafi','hawala_sarafi','withdrawal_sarafi','supplier_fund','client_fund','expense','expense_update','expense_delete','refund','ticket_refund']) || $isDeleted || $isBankTransaction;
              echo '<div class="tl-item notification-'.htmlspecialchars($status).'" data-id="'.$id.'">';
              echo '<div class="tl-dot '.$dotClass.($status==='unread'?' unread':'').'"><i class="'.$iconPrefix.' '.$icon.'"></i></div>';
             echo '<div class="tl-body">';
             echo '<div class="tl-top"><span class="tl-type">'.$type.'</span><span class="tl-time">'.$time.'</span></div>';
             echo '<div class="tl-msg">'.$msg.'</div>';
             if($name||$amt){
                 echo '<div class="tl-meta">';
                 if($name) echo '<span class="tl-chip"><i class="fas fa-user"></i>'.$name.'</span>';
                 if($amt)  echo '<span class="tl-chip"><i class="fas fa-credit-card"></i>'.$sym.number_format((float)$amt,2).'</span>';
                 echo '</div>';
             }
             if($status==='unread'){
                 echo '<div class="tl-actions">';
                 if(!$readOnly) echo '<button class="tl-btn tl-btn-receive approve-button" data-id="'.$id.'" data-amount="'.$amt.'" data-currency="'.$cur.'" data-type="'.$type.'"><i class="fas fa-check"></i>'.htmlspecialchars(__('received')).'</button>';
                 echo '<button class="tl-btn tl-btn-read read-button" data-id="'.$id.'"><i class="fas fa-eye"></i>'.htmlspecialchars(__('mark_as_read')).'</button>';
                 echo '</div>';
             }
             echo '</div></div>';
         }
         echo '</div>';
     }
}
?>

<!-- MODALS -->
<?php include '../modals/dashboard/receipt_modal.php'; ?>
<?php include '../modals/dashboard/debtor_modal.php'; ?>
<?php include '../modals/dashboard/sales_modal.php'; ?>
<?php include '../includes/admin_footer.php'; ?>

<!-- VENDOR SCRIPTS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- ORIGINAL JS — all logic & API calls preserved unchanged -->
<script src="../js/dashboard/dashboard-charts.js"></script>
<script src="../js/dashboard/dashboard-notifications.js"></script>
<script src="../js/dashboard/dashboard-sales.js"></script>
<script src="../js/dashboard/dashboard-filters.js"></script>
<script src="../js/dashboard/dashboard-receipt.js"></script>
<script src="../js/dashboard/dashboard-debtors.js"></script>
<script src="../js/dashboard/dashboard-dues.js"></script>
<script src="../js/dashboard/umrah-statistics.js"></script>

<!-- NEW UI HELPERS -->
<script>
/* Tab switchers */
function switchFlightTab(btn,id){
  const card=btn.closest('.d-card');
  card.querySelectorAll('.ftab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  ['ftab-dep','ftab-today','ftab-week','ftab-month'].forEach(t=>{
    const el=document.getElementById(t);
    if(el) el.style.display=(t===id)?'block':'none';
  });
}
function switchNotifTab(btn,id){
  btn.closest('.d-card').querySelectorAll('.notif-tab-btn').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  ['ntab-unread','ntab-read'].forEach(t=>{
    const el=document.getElementById(t);
    if(el) el.style.display=(t===id)?'block':'none';
  });
}

/* Reset filter functions */
function resetDailyFilter(){
  document.getElementById('dailyDateInput').value = '<?= date('Y-m-d') ?>';
}
function resetMonthlyFilter(){
  document.getElementById('monthlyDateInput').value = '<?= date('Y-m') ?>';
}
function resetYearlyFilter(){
  document.getElementById('yearlyDateInput').value = '<?= date('Y') ?>';
}

/* Animate leaderboard bars on load */
document.addEventListener('DOMContentLoaded',function(){
  setTimeout(()=>{
    document.querySelectorAll('.lb-bar-fill').forEach(b=>{
      const w=b.style.width; b.style.width='0%';
      requestAnimationFrame(()=>{ b.style.transition='width 1.2s cubic-bezier(.34,1.56,.64,1)'; b.style.width=w; });
    });
  },300);
});

/* Override performance filter response to render new leaderboard style */
$(document).ajaxSuccess(function(ev,xhr,settings,data){
  if(!settings.url||!settings.url.includes('get_filtered_performance')) return;
  if(!data||data.status!=='success') return;
  const performers=data.data;
  let html='';
  if(performers&&performers.length>0){
    const maxP=Math.max(...performers.map(p=>parseFloat(p.total_profit_usd)));
    performers.forEach((p,i)=>{
      const rank=i+1;
      const pct=maxP>0?Math.round((parseFloat(p.total_profit_usd)/maxP)*100):0;
      const rc=rank===1?'rank-1':rank===2?'rank-2':rank===3?'rank-3':'rank-other';
      const ri=rank===1?'🥇':rank===2?'🥈':rank===3?'🥉':rank;
      const bc=rank===1?'lb-bar-1':rank===2?'lb-bar-2':rank===3?'lb-bar-3':'lb-bar-o';
      html+=`<div class="lb-item"><div class="lb-rank ${rc}">${ri}</div><div class="lb-info"><div class="lb-name">${p.user_name}</div><div class="lb-bar-track"><div class="lb-bar-fill ${bc}" style="width:${pct}%"></div></div></div><div class="lb-right"><div class="lb-profit">$${parseFloat(p.total_profit_usd).toFixed(2)}</div><div class="lb-tickets">${p.total_tickets} tickets</div></div></div>`;
    });
  } else {
    html='<div class="d-empty"><i class="fas fa-award"></i>No data for selected period</div>';
  }
  $('#topPerformersTableBody').html(html);
  setTimeout(()=>{document.querySelectorAll('#topPerformersTableBody .lb-bar-fill').forEach(b=>{const w=b.style.width;b.style.width='0%';requestAnimationFrame(()=>{b.style.transition='width 1.2s cubic-bezier(.34,1.56,.64,1)';b.style.width=w;});});},100);
});
</script>

<!-- TUTORIALS MODAL -->
<div class="modal fade" id="dashboardTutorialsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);border-radius:20px;color:var(--text);">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--violet),var(--indigo));border-radius:20px 20px 0 0;border:none;">
        <h5 class="modal-title" style="color:#fff;font-weight:700;"><i class="fas fa-play-circle mr-2"></i>Dashboard Tutorials</h5>
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;"><span>&times;</span></button>
      </div>
      <div class="modal-body p-0">
        <div class="row no-gutters">
          <div class="col-md-8" style="background:#000;border-radius:0 0 0 20px;">
            <iframe id="tutorialVideoPlayer" style="width:100%;height:420px;border:none;" src="" allowfullscreen></iframe>
            <div class="p-3" style="background:var(--surface2);">
              <h6 id="tutorialTitle" style="font-weight:700;">Select a tutorial</h6>
              <p id="tutorialDescription" style="font-size:13px;color:var(--text-muted);margin-bottom:6px;"></p>
              <span id="tutorialLevel" style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(124,58,237,.2);color:var(--violet-light);"></span>
              <span id="tutorialDuration" style="font-size:11px;margin-left:8px;color:var(--text-muted);"></span>
            </div>
          </div>
          <div class="col-md-4" style="max-height:520px;overflow-y:auto;border-left:1px solid var(--border);">
            <div class="p-3">
              <h6 style="font-weight:700;margin-bottom:14px;">Available Tutorials</h6>
              <div id="tutorialsListContainer"><p style="color:var(--text-muted);text-align:center;padding:20px;">Loading...</p></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--border);">
        <button class="dbtn dbtn-ghost" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
const dashboardTutorials=[
  {id:1,title:'Dashboard Overview',description:'Quick actions, balance alerts, financial wealth distribution chart, multi-currency support.',duration:'5:00',level:'Beginner',vimeo_id:''},
  {id:2,title:'Sales Cards & Dues',description:'Daily/Monthly/Yearly sales cards with trend percentages, outstanding dues metric grid.',duration:'6:00',level:'Beginner',vimeo_id:''},
  {id:3,title:'Departures & Top Performers',description:"Flight cards, departure board, top performers leaderboard, client debts.",duration:'5:30',level:'Beginner',vimeo_id:''}
];
$('#dashboardTutorialsModal').on('show.bs.modal',function(){
  let html='';
  dashboardTutorials.forEach(t=>{
    html+=`<div class="tutorial-item" data-id="${t.id}" style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:8px;cursor:pointer;transition:all .2s;"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:30px;height:30px;background:rgba(124,58,237,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--violet-light);flex-shrink:0;font-size:11px;"><i class="fas fa-play"></i></div><div><div style="font-weight:700;font-size:13px;margin-bottom:2px;">${t.title}</div><div style="font-size:11px;color:var(--text-muted);">${t.duration} · ${t.level}</div></div></div></div>`;
  });
  $('#tutorialsListContainer').html(html);
  $('.tutorial-item').on('click',function(){
    const t=dashboardTutorials.find(x=>x.id===$(this).data('id'));
    if(!t) return;
    $('.tutorial-item').css({'background':'var(--surface2)','border-color':'var(--border)'});
    $(this).css({'background':'rgba(124,58,237,.1)','border-color':'rgba(124,58,237,.4)'});
    $('#tutorialVideoPlayer').attr('src',t.vimeo_id?'https://player.vimeo.com/video/'+t.vimeo_id:'');
    $('#tutorialTitle').text(t.title);
    $('#tutorialDescription').text(t.description);
    $('#tutorialLevel').text(t.level);
    $('#tutorialDuration').text(t.duration);
  });
  if(dashboardTutorials.length) $('.tutorial-item').first().trigger('click');
  });

  // Notifications collapse/expand functionality
  function toggleNotificationsCollapse() {
  const content = document.getElementById('notificationsContent');
  const btn = document.querySelector('.notif-collapse-btn');
  
  if (content.classList.contains('collapsed')) {
    content.classList.remove('collapsed');
    btn.classList.remove('collapsed');
    localStorage.setItem('notificationsCollapsed', 'false');
  } else {
    content.classList.add('collapsed');
    btn.classList.add('collapsed');
    localStorage.setItem('notificationsCollapsed', 'true');
  }
  }

  // Restore collapse state on page load & auto-collapse if count >= 10
  document.addEventListener('DOMContentLoaded', function() {
  const content = document.getElementById('notificationsContent');
  const btn = document.querySelector('.notif-collapse-btn');
  const unreadCountElement = document.getElementById('unreadNotifCount');
  const unreadCount = unreadCountElement ? parseInt(unreadCountElement.textContent) : 0;
  
  // Auto-collapse if unread count >= 10
  if (unreadCount >= 10) {
    if (content) content.classList.add('collapsed');
    if (btn) btn.classList.add('collapsed');
    localStorage.setItem('notificationsCollapsed', 'true');
  } else {
    // Otherwise, restore previous state
    const isCollapsed = localStorage.getItem('notificationsCollapsed') === 'true';
    if (isCollapsed) {
      if (content) content.classList.add('collapsed');
      if (btn) btn.classList.add('collapsed');
    }
  }
  });

  // Navigation to attendance page
  function goToAttendance() {
    window.location.href = 'attendance.php';
  }

  // Update working time every minute if checked in
  <?php if ($att_state === 'checked_in'): ?>
  (function updateWorkingTime() {
    const workingTimeEl = document.getElementById('workingTime');
    if (!workingTimeEl) return;
    
    const checkinTime = new Date('<?php echo date('Y-m-d') . 'T' . $today_attendance['check_in_time']; ?>');
    
    function update() {
      const now = new Date();
      const elapsed = Math.floor((now - checkinTime) / 60000); // minutes
      const hours = Math.floor(elapsed / 60);
      const mins = elapsed % 60;
      workingTimeEl.textContent = hours + 'h ' + mins + 'm';
    }
    
    update();
    setInterval(update, 60000); // Update every minute
  })();
  <?php endif; ?>
  </script>
</body>
</html>