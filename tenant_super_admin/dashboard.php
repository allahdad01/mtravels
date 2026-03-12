<?php
include 'header.php';

$tenant_id = $_SESSION['tenant_id'];

$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : null;
if ($selected_branch_id === 0) {
    $selected_branch_id = null;
}

$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'branch_switched':
            $branch_name = $_GET['branch'] ?? 'Unknown';
            $success_message = "Successfully switched to branch: " . htmlspecialchars($branch_name);
            break;
        default:
            $success_message = "Operation completed successfully";
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'branch_not_found':   $error_message = "Selected branch not found or not accessible"; break;
        case 'no_branch_selected': $error_message = "No branch selected"; break;
        case 'database_error':     $error_message = "Database error occurred"; break;
        default:                   $error_message = "An error occurred";
    }
}

$current_branch_name = "All Branches";
$current_branch_id = null;

if ($selected_branch_id) {
    $stmt = $pdo->prepare("SELECT name FROM branches WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$selected_branch_id, $tenant_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $current_branch_name = $branch_data['name'];
        $current_branch_id = $selected_branch_id;
    }
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_branches FROM branches WHERE tenant_id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $branchStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $userQuery = "SELECT COUNT(u.id) as total_users,
        COUNT(CASE WHEN u.role = 'admin' THEN 1 END) as admin_users,
        COUNT(CASE WHEN u.role = 'sales' THEN 1 END) as sales_users,
        COUNT(CASE WHEN u.role = 'finance' THEN 1 END) as finance_users,
        COUNT(CASE WHEN u.role = 'umrah' THEN 1 END) as umrah_users
        FROM users u WHERE u.tenant_id = ?";
    $userParams = [$tenant_id];
    if ($current_branch_id) { $userQuery .= " AND u.branch_id = ?"; $userParams[] = $current_branch_id; }
    $stmt = $pdo->prepare($userQuery);
    $stmt->execute($userParams);
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $activityQuery = "SELECT al.*, u.name as user_name, b.name as branch_name
        FROM activity_log al LEFT JOIN users u ON al.user_id = u.id LEFT JOIN branches b ON al.branch_id = b.id
        WHERE al.tenant_id = ?";
    $activityParams = [$tenant_id];
    if ($current_branch_id) { $activityQuery .= " AND al.branch_id = ?"; $activityParams[] = $current_branch_id; }
    $activityQuery .= " ORDER BY al.created_at DESC LIMIT 8";
    $stmt = $pdo->prepare($activityQuery);
    $stmt->execute($activityParams);
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $performanceQuery = "
        SELECT b.name as branch_name, b.id as branch_id,
            COALESCE(ts.ticket_bookings,0) as ticket_bookings, COALESCE(ts.ticket_profit_usd,0) as ticket_profit_usd, COALESCE(ts.ticket_profit_afs,0) as ticket_profit_afs,
            COALESCE(rs.ticket_reservations,0) as ticket_reservations, COALESCE(rs.reservation_profit_usd,0) as reservation_profit_usd, COALESCE(rs.reservation_profit_afs,0) as reservation_profit_afs,
            COALESCE(ws.ticket_weights,0) as ticket_weights, COALESCE(ws.weight_profit_usd,0) as weight_profit_usd, COALESCE(ws.weight_profit_afs,0) as weight_profit_afs,
            COALESCE(hs.hotel_bookings,0) as hotel_bookings, COALESCE(hs.hotel_profit_usd,0) as hotel_profit_usd, COALESCE(hs.hotel_profit_afs,0) as hotel_profit_afs,
            COALESCE(vs.visa_applications,0) as visa_applications, COALESCE(vs.visa_profit_usd,0) as visa_profit_usd, COALESCE(vs.visa_profit_afs,0) as visa_profit_afs,
            COALESCE(us.umrah_bookings,0) as umrah_bookings, COALESCE(us.umrah_profit_usd,0) as umrah_profit_usd, COALESCE(us.umrah_profit_afs,0) as umrah_profit_afs,
            COALESCE(as2.additional_payments,0) as additional_payments, COALESCE(as2.additional_profit_usd,0) as additional_profit_usd, COALESCE(as2.additional_profit_afs,0) as additional_profit_afs,
            COALESCE(rfs.refunded_tickets,0) as refunded_tickets, COALESCE(rfs.refund_profit_usd,0) as refund_profit_usd, COALESCE(rfs.refund_profit_afs,0) as refund_profit_afs,
            COALESCE(dcs.date_change_tickets,0) as date_change_tickets, COALESCE(dcs.date_change_profit_usd,0) as date_change_profit_usd, COALESCE(dcs.date_change_profit_afs,0) as date_change_profit_afs,
            COALESCE(ts.ticket_profit_usd,0)+COALESCE(rs.reservation_profit_usd,0)+COALESCE(ws.weight_profit_usd,0)+COALESCE(hs.hotel_profit_usd,0)+COALESCE(vs.visa_profit_usd,0)+COALESCE(us.umrah_profit_usd,0)+COALESCE(as2.additional_profit_usd,0)+COALESCE(rfs.refund_profit_usd,0)+COALESCE(dcs.date_change_profit_usd,0) as total_revenue_usd,
            COALESCE(ts.ticket_profit_afs,0)+COALESCE(rs.reservation_profit_afs,0)+COALESCE(ws.weight_profit_afs,0)+COALESCE(hs.hotel_profit_afs,0)+COALESCE(vs.visa_profit_afs,0)+COALESCE(us.umrah_profit_afs,0)+COALESCE(as2.additional_profit_afs,0)+COALESCE(rfs.refund_profit_afs,0)+COALESCE(dcs.date_change_profit_afs,0) as total_revenue_afs
        FROM branches b
        LEFT JOIN (SELECT u.branch_id, COUNT(t.id) as ticket_bookings, SUM(CASE WHEN t.currency='USD' THEN t.profit ELSE 0 END) as ticket_profit_usd, SUM(CASE WHEN t.currency='AFS' THEN t.profit ELSE 0 END) as ticket_profit_afs FROM ticket_bookings t JOIN users u ON t.created_by=u.id WHERE t.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) ts ON ts.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(tr.id) as ticket_reservations, SUM(CASE WHEN tr.currency='USD' THEN tr.profit ELSE 0 END) as reservation_profit_usd, SUM(CASE WHEN tr.currency='AFS' THEN tr.profit ELSE 0 END) as reservation_profit_afs FROM ticket_reservations tr JOIN users u ON tr.created_by=u.id WHERE tr.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) rs ON rs.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(tw.id) as ticket_weights, SUM(CASE WHEN tb.currency='USD' THEN tw.profit ELSE 0 END) as weight_profit_usd, SUM(CASE WHEN tb.currency='AFS' THEN tw.profit ELSE 0 END) as weight_profit_afs FROM ticket_weights tw JOIN users u ON tw.created_by=u.id LEFT JOIN ticket_bookings tb ON tb.id=tw.ticket_id WHERE tw.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) ws ON ws.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(h.id) as hotel_bookings, SUM(CASE WHEN h.currency='USD' THEN h.profit ELSE 0 END) as hotel_profit_usd, SUM(CASE WHEN h.currency='AFS' THEN h.profit ELSE 0 END) as hotel_profit_afs FROM hotel_bookings h JOIN users u ON h.created_by=u.id WHERE h.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) hs ON hs.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(v.id) as visa_applications, SUM(CASE WHEN v.currency='USD' THEN v.profit ELSE 0 END) as visa_profit_usd, SUM(CASE WHEN v.currency='AFS' THEN v.profit ELSE 0 END) as visa_profit_afs FROM visa_applications v JOIN users u ON v.created_by=u.id WHERE v.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) vs ON vs.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(um.booking_id) as umrah_bookings, SUM(CASE WHEN um.currency='USD' THEN um.profit ELSE 0 END) as umrah_profit_usd, SUM(CASE WHEN um.currency='AFS' THEN um.profit ELSE 0 END) as umrah_profit_afs FROM umrah_bookings um JOIN users u ON um.created_by=u.id WHERE um.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) us ON us.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(ap.id) as additional_payments, SUM(CASE WHEN ap.currency='USD' THEN ap.profit ELSE 0 END) as additional_profit_usd, SUM(CASE WHEN ap.currency='AFS' THEN ap.profit ELSE 0 END) as additional_profit_afs FROM additional_payments ap JOIN users u ON ap.created_by=u.id WHERE ap.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) as2 ON as2.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(rt.id) as refunded_tickets, SUM(CASE WHEN rt.currency='USD' THEN (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty WHEN rt.calculation_method='sold' THEN (rt.service_penalty-IFNULL(tb.profit,0)) ELSE rt.service_penalty END) ELSE 0 END) as refund_profit_usd, SUM(CASE WHEN rt.currency='AFS' THEN (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty WHEN rt.calculation_method='sold' THEN (rt.service_penalty-IFNULL(tb.profit,0)) ELSE rt.service_penalty END) ELSE 0 END) as refund_profit_afs FROM refunded_tickets rt JOIN users u ON rt.created_by=u.id LEFT JOIN ticket_bookings tb ON rt.ticket_id=tb.id WHERE rt.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) rfs ON rfs.branch_id=b.id
        LEFT JOIN (SELECT u.branch_id, COUNT(dt.id) as date_change_tickets, SUM(CASE WHEN dt.currency='USD' THEN dt.service_penalty ELSE 0 END) as date_change_profit_usd, SUM(CASE WHEN dt.currency='AFS' THEN dt.service_penalty ELSE 0 END) as date_change_profit_afs FROM date_change_tickets dt JOIN users u ON dt.created_by=u.id WHERE dt.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY u.branch_id) dcs ON dcs.branch_id=b.id
        WHERE b.tenant_id = ? AND b.status = 'active'";

    $performanceParams = [$tenant_id];
    if ($current_branch_id) { $performanceQuery .= " AND b.id = ?"; $performanceParams[] = $current_branch_id; }
    $performanceQuery .= " GROUP BY b.id, b.name ORDER BY total_revenue_usd DESC";

    $stmt = $pdo->prepare($performanceQuery);
    $stmt->execute($performanceParams);
    $branchPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $branchListQuery = "SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name";
    $branchListStmt = $pdo->prepare($branchListQuery);
    $branchListStmt->execute([$tenant_id]);
    $branches = $branchListStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $branchStats = ['total_branches' => 0];
    $userStats = ['total_users' => 0, 'admin_users' => 0, 'sales_users' => 0, 'finance_users' => 0, 'umrah_users' => 0];
    $recentActivities = [];
    $branchPerformance = [];
    $branches = [];
}

$staffUsers = ($userStats['sales_users'] ?? 0) + ($userStats['finance_users'] ?? 0) + ($userStats['umrah_users'] ?? 0);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:      #2ed8b6;
    --blue:      #4099ff;
    --grad:      linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --grad-rev:  linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
    --surface:   #f4f7fe;
    --card-bg:   #ffffff;
    --border:    #e8edf5;
    --text-main: #1a2340;
    --text-sub:  #6b7a99;
    --green:     #22c55e;
    --amber:     #f59e0b;
    --red:       #ef4444;
    --purple:    #8b5cf6;
    --radius:    14px;
    --shadow:    0 2px 12px rgba(64,153,255,0.08);
    --shadow-md: 0 6px 24px rgba(64,153,255,0.13);
}

*, *::before, *::after { box-sizing: border-box; }

body, .pcoded-main-container {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    background: var(--surface) !important;
    color: var(--text-main) !important;
}

/* ── Page Header ── */
.dash-header {
    background: var(--grad);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 8px 32px rgba(64,153,255,0.22);
    position: relative;
    overflow: hidden;
}
.dash-header::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.dash-header-left h4 {
    font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px;
    letter-spacing: -0.4px;
}
.dash-header-left p { color: rgba(255,255,255,0.8); margin: 0; font-size: 13px; }
.dash-header-actions { display: flex; gap: 10px; position: relative; }
.dash-header-actions .btn-ghost {
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
.dash-header-actions .btn-ghost:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-1px);
}

/* ── Alerts ── */
.dash-alert {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px; border-radius: var(--radius);
    margin-bottom: 16px; font-size: 14px; font-weight: 500;
    animation: slideDown 0.3s ease;
}
.dash-alert-success { background: #dcfce7; color: #166534; border-left: 4px solid var(--green); }
.dash-alert-danger  { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--red); }
.dash-alert .close-btn { margin-left: auto; background: none; border: none; cursor: pointer; opacity: 0.6; font-size: 18px; line-height: 1; padding: 0; color: inherit; }
.dash-alert .close-btn:hover { opacity: 1; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* ── Branch Filter Bar ── */
.filter-bar {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 16px 22px;
    margin-bottom: 24px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}
.filter-bar label {
    font-size: 13px; font-weight: 600; color: var(--text-sub);
    white-space: nowrap; margin: 0;
    text-transform: uppercase; letter-spacing: 0.6px;
}
.filter-bar select {
    flex: 1; max-width: 300px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 9px 14px;
    font-family: inherit; font-size: 14px; font-weight: 600;
    color: var(--text-main);
    background: var(--surface);
    cursor: pointer;
    transition: border-color 0.2s;
    outline: none;
}
.filter-bar select:focus { border-color: var(--blue); }
.filter-badge {
    background: var(--grad);
    color: #fff;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px; font-weight: 700;
    white-space: nowrap;
}

/* ── Stat Cards ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}
@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .stat-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 24px 22px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 18px;
    transition: box-shadow 0.2s, transform 0.2s;
    position: relative; overflow: hidden;
}
.stat-card::after {
    content: '';
    position: absolute; top: 0; left: 0;
    width: 4px; height: 100%;
    border-radius: var(--radius) 0 0 var(--radius);
}
.stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
.stat-card.c-blue::after  { background: var(--blue); }
.stat-card.c-teal::after  { background: var(--teal); }
.stat-card.c-green::after { background: var(--green); }
.stat-card.c-amber::after { background: var(--amber); }

.stat-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 22px;
}
.stat-card.c-blue  .stat-icon { background: rgba(64,153,255,0.12); color: var(--blue); }
.stat-card.c-teal  .stat-icon { background: rgba(46,216,182,0.12); color: var(--teal); }
.stat-card.c-green .stat-icon { background: rgba(34,197,94,0.12);  color: var(--green); }
.stat-card.c-amber .stat-icon { background: rgba(245,158,11,0.12); color: var(--amber); }

.stat-info { flex: 1; min-width: 0; }
.stat-value {
    font-size: 32px; font-weight: 800; line-height: 1;
    font-family: 'JetBrains Mono', monospace;
    margin-bottom: 4px;
    letter-spacing: -1px;
}
.stat-card.c-blue  .stat-value { color: var(--blue); }
.stat-card.c-teal  .stat-value { color: var(--teal); }
.stat-card.c-green .stat-value { color: var(--green); }
.stat-card.c-amber .stat-value { color: var(--amber); }
.stat-label { font-size: 14px; font-weight: 600; color: var(--text-main); margin-bottom: 2px; }
.stat-sub   { font-size: 12px; color: var(--text-sub); }

/* ── Section Headers ── */
.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
}
.section-title {
    font-size: 15px; font-weight: 700; color: var(--text-main);
    display: flex; align-items: center; gap: 8px;
}
.section-title .dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--grad);
    flex-shrink: 0;
}
.section-badge {
    font-size: 11px; font-weight: 700; color: var(--text-sub);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px; padding: 3px 10px;
    text-transform: uppercase; letter-spacing: 0.5px;
}

/* ── Branch Cards ── */
.branch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
.branch-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
}
.branch-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }

.branch-card-header {
    background: var(--grad);
    padding: 14px 18px;
    display: flex; align-items: center; justify-content: space-between;
}
.branch-card-header .branch-name {
    font-size: 15px; font-weight: 700; color: #fff;
}
.branch-card-header .branch-rev {
    text-align: right;
}
.branch-card-header .branch-rev span {
    display: block; font-size: 11px; color: rgba(255,255,255,0.75); margin-bottom: 1px;
}
.branch-card-header .branch-rev strong {
    font-size: 16px; font-weight: 800; color: #fff;
    font-family: 'JetBrains Mono', monospace;
}

.branch-services {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 1px; background: var(--border);
}
.service-cell {
    background: var(--card-bg);
    padding: 12px 10px; text-align: center;
}
.service-cell .svc-label {
    font-size: 10px; font-weight: 600; color: var(--text-sub);
    text-transform: uppercase; letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.service-cell .svc-count {
    font-size: 18px; font-weight: 800;
    color: var(--text-main);
    font-family: 'JetBrains Mono', monospace;
    line-height: 1;
    margin-bottom: 2px;
}
.service-cell .svc-usd {
    font-size: 11px; color: var(--blue); font-weight: 600;
}
.service-cell .svc-afs {
    font-size: 11px; color: var(--amber); font-weight: 600;
}

.branch-card-footer {
    padding: 12px 18px;
    background: var(--surface);
    border-top: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.rev-pill {
    border-radius: 8px; padding: 5px 12px;
    font-size: 12px; font-weight: 700;
    display: flex; align-items: center; gap: 5px;
}
.rev-pill.usd { background: rgba(64,153,255,0.1); color: var(--blue); }
.rev-pill.afs { background: rgba(245,158,11,0.1); color: var(--amber); }
.rev-pill span { font-family: 'JetBrains Mono', monospace; }

/* ── Two-col layout ── */
.two-col { display: grid; grid-template-columns: 1fr 340px; gap: 18px; margin-bottom: 24px; }
@media (max-width: 1024px) { .two-col { grid-template-columns: 1fr; } }

/* ── Activity Feed ── */
.dash-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.dash-card-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.dash-card-head h6 {
    font-size: 14px; font-weight: 700; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.dash-card-head h6 .ico {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--grad); display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 13px;
}
.dash-card-body { padding: 0; }

.activity-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.activity-item:last-child { border-bottom: none; }
.activity-item:hover { background: var(--surface); }
.activity-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--grad); flex-shrink: 0; margin-top: 5px;
}
.activity-body { flex: 1; min-width: 0; }
.activity-action { font-size: 13px; font-weight: 600; color: var(--text-main); margin-bottom: 2px; }
.activity-meta { font-size: 12px; color: var(--text-sub); }
.activity-time { font-size: 11px; color: var(--text-sub); white-space: nowrap; font-family: 'JetBrains Mono', monospace; }

/* ── Quick Actions ── */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    padding: 20px;
}
@media (max-width: 768px) { .actions-grid { grid-template-columns: repeat(2, 1fr); } }

.action-tile {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 20px 12px;
    border-radius: 12px;
    border: 1.5px solid var(--border);
    background: var(--card-bg);
    cursor: pointer; text-decoration: none !important;
    transition: all 0.2s; color: var(--text-main) !important;
}
.action-tile:hover {
    border-color: var(--blue);
    background: linear-gradient(135deg, rgba(64,153,255,0.04) 0%, rgba(46,216,182,0.04) 100%);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.action-tile .tile-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.action-tile .tile-label {
    font-size: 12px; font-weight: 700; text-align: center;
    color: var(--text-main); line-height: 1.3;
}

/* Icon color variants */
.ti-blue   { background: rgba(64,153,255,0.12); color: var(--blue); }
.ti-teal   { background: rgba(46,216,182,0.12); color: var(--teal); }
.ti-green  { background: rgba(34,197,94,0.12);  color: var(--green); }
.ti-amber  { background: rgba(245,158,11,0.12); color: var(--amber); }
.ti-purple { background: rgba(139,92,246,0.12); color: var(--purple); }
.ti-red    { background: rgba(239,68,68,0.12);  color: var(--red); }
.ti-gray   { background: rgba(107,122,153,0.12); color: var(--text-sub); }

/* ── Empty state ── */
.empty-state {
    text-align: center; padding: 40px 20px; color: var(--text-sub);
}
.empty-state i { font-size: 40px; opacity: 0.3; display: block; margin-bottom: 12px; }
.empty-state p { font-size: 14px; margin: 0; }

/* ── Modals ── */
.modal-content { border: none; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.18); font-family: inherit; }
.modal-header {
    background: var(--grad); color: #fff;
    border-radius: 16px 16px 0 0; border: none; padding: 18px 24px;
}
.modal-header .modal-title { font-weight: 700; font-size: 16px; }
.modal-header .close { color: #fff; opacity: 0.8; font-size: 22px; }
.modal-header .close:hover { opacity: 1; }
.modal-body { padding: 24px; }
.modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }

.form-group label { font-size: 13px; font-weight: 600; color: var(--text-sub); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
.form-control {
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 10px 14px; font-family: inherit; font-size: 14px;
    transition: border-color 0.2s; background: var(--surface);
    color: var(--text-main);
}
.form-control:focus { border-color: var(--blue); outline: none; box-shadow: 0 0 0 3px rgba(64,153,255,0.15); background: #fff; }

.btn-primary-grad {
    background: var(--grad); color: #fff; border: none;
    border-radius: 10px; padding: 10px 22px;
    font-family: inherit; font-size: 14px; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
}
.btn-primary-grad:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(64,153,255,0.3); }

/* ── Overrides for existing framework ── */
.pcoded-content { padding: 20px !important; }
.page-header { display: none !important; } /* hide old breadcrumb, replaced by dash-header */
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div class="dash-header-left">
            <h4><i class="feather icon-bar-chart-2" style="margin-right:8px;"></i>Owner Dashboard</h4>
            <p>Monitor performance &amp; manage your team across all branches</p>
        </div>
        <div class="dash-header-actions">
            <button class="btn-ghost" data-toggle="modal" data-target="#profileModal">
                <i class="feather icon-user" style="margin-right:5px;"></i>Profile
            </button>
            <button class="btn-ghost" data-toggle="modal" data-target="#settingsModal">
                <i class="feather icon-settings" style="margin-right:5px;"></i>Settings
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success_message)): ?>
    <div class="dash-alert dash-alert-success">
        <i class="feather icon-check-circle"></i>
        <?= $success_message ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
    <div class="dash-alert dash-alert-danger">
        <i class="feather icon-alert-circle"></i>
        <?= $error_message ?>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Branch Filter -->
    <div class="filter-bar">
        <label><i class="feather icon-filter" style="margin-right:5px;"></i>Branch Filter</label>
        <select id="branchSelector" onchange="changeBranch()">
            <option value="0" <?= !$current_branch_id ? 'selected' : '' ?>>All Branches</option>
            <?php foreach ($branches as $branch): ?>
            <option value="<?= $branch['id'] ?>" <?= ($current_branch_id == $branch['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($branch['name']) ?> (<?= htmlspecialchars($branch['code']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <div class="filter-badge">
            <i class="feather icon-git-branch" style="margin-right:4px;"></i>
            <?= htmlspecialchars($current_branch_name) ?>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card c-blue">
            <div class="stat-icon"><i class="feather icon-git-branch"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $branchStats['total_branches'] ?? 0 ?></div>
                <div class="stat-label">Total Branches</div>
                <div class="stat-sub">Active in your network</div>
            </div>
        </div>
        <div class="stat-card c-teal">
            <div class="stat-icon"><i class="feather icon-users"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $userStats['total_users'] ?? 0 ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-sub">Across all branches</div>
            </div>
        </div>
        <div class="stat-card c-green">
            <div class="stat-icon"><i class="feather icon-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $userStats['admin_users'] ?? 0 ?></div>
                <div class="stat-label">Branch Admins</div>
                <div class="stat-sub">Administrative users</div>
            </div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-icon"><i class="feather icon-user-plus"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $staffUsers ?></div>
                <div class="stat-label">Staff Users</div>
                <div class="stat-sub">Sales · Finance · Umrah</div>
            </div>
        </div>
    </div>

    <!-- Branch Performance Cards -->
    <div class="section-header">
        <div class="section-title"><span class="dot"></span>Branch Performance <small style="font-weight:500;color:var(--text-sub);font-size:12px;">(Last 30 Days)</small></div>
        <div class="section-badge"><?= count($branchPerformance) ?> Branch<?= count($branchPerformance) !== 1 ? 'es' : '' ?></div>
    </div>

    <?php if (!empty($branchPerformance)): ?>
    <div class="branch-grid">
        <?php foreach ($branchPerformance as $b): ?>
        <div class="branch-card">
            <div class="branch-card-header">
                <div class="branch-name">
                    <i class="feather icon-git-branch" style="margin-right:6px;"></i>
                    <?= htmlspecialchars($b['branch_name']) ?>
                </div>
                <div class="branch-rev">
                    <span>Total Revenue (USD)</span>
                    <strong>$<?= number_format($b['total_revenue_usd'] ?? 0, 0) ?></strong>
                </div>
            </div>
            <div class="branch-services">
                <?php
                $services = [
                    ['Tickets',      $b['ticket_bookings'],      $b['ticket_profit_usd'],      $b['ticket_profit_afs']],
                    ['Reservations', $b['ticket_reservations'],  $b['reservation_profit_usd'], $b['reservation_profit_afs']],
                    ['Weights',      $b['ticket_weights'],       $b['weight_profit_usd'],      $b['weight_profit_afs']],
                    ['Hotels',       $b['hotel_bookings'],       $b['hotel_profit_usd'],       $b['hotel_profit_afs']],
                    ['Visas',        $b['visa_applications'],    $b['visa_profit_usd'],        $b['visa_profit_afs']],
                    ['Umrah',        $b['umrah_bookings'],       $b['umrah_profit_usd'],       $b['umrah_profit_afs']],
                    ['Add. Payments',$b['additional_payments'],  $b['additional_profit_usd'],  $b['additional_profit_afs']],
                    ['Refunds',      $b['refunded_tickets'],     $b['refund_profit_usd'],      $b['refund_profit_afs']],
                    ['Date Changes', $b['date_change_tickets'],  $b['date_change_profit_usd'], $b['date_change_profit_afs']],
                ];
                foreach ($services as [$label, $count, $usd, $afs]):
                ?>
                <div class="service-cell">
                    <div class="svc-label"><?= $label ?></div>
                    <div class="svc-count"><?= $count ?></div>
                    <div class="svc-usd">$<?= number_format($usd, 0) ?></div>
                    <div class="svc-afs">؋<?= number_format($afs, 0) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="branch-card-footer">
                <div class="rev-pill usd">
                    <i class="feather icon-dollar-sign"></i>
                    <span>$<?= number_format($b['total_revenue_usd'] ?? 0, 2) ?></span>
                </div>
                <div class="rev-pill afs">
                    <i class="feather icon-trending-up"></i>
                    <span>؋<?= number_format($b['total_revenue_afs'] ?? 0, 0) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="dash-card" style="margin-bottom:24px;">
        <div class="empty-state">
            <i class="feather icon-git-branch"></i>
            <p>No branch performance data available</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Activity + Quick Actions -->
    <div class="two-col">

        <!-- Quick Actions -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico"><i class="feather icon-zap"></i></span>Quick Actions</h6>
            </div>
            <div class="actions-grid">
                <?php
                $actions = [
                    ['branches.php',             'icon-git-branch',  'ti-blue',   'Manage Branches'],
                    ['users.php',                'icon-user-plus',   'ti-teal',   'Manage Users'],
                    ['reports.php',              'icon-file-text',   'ti-green',  'View Reports'],
                    ['settings.php',             'icon-settings',    'ti-amber',  'Settings'],
                    ['generate_report.php',      'icon-download',    'ti-purple', 'Generate Report'],
                    ['report_settings.php',      'icon-mail',        'ti-blue',   'Report Settings'],
                    ['subscription_payments.php','icon-credit-card', 'ti-green',  'Payments'],
                    ['tenant_settings.php',      'icon-sliders',     'ti-gray',   'Tenant Settings'],
                ];
                foreach ($actions as [$href, $icon, $color, $label]):
                ?>
                <a href="<?= $href ?>" class="action-tile">
                    <div class="tile-icon <?= $color ?>">
                        <i class="feather <?= $icon ?>"></i>
                    </div>
                    <div class="tile-label"><?= $label ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico"><i class="feather icon-activity"></i></span>Recent Activity</h6>
                <span class="section-badge"><?= count($recentActivities) ?> events</span>
            </div>
            <div class="dash-card-body">
                <?php if (!empty($recentActivities)): ?>
                    <?php foreach ($recentActivities as $act): ?>
                    <div class="activity-item">
                        <div class="activity-dot"></div>
                        <div class="activity-body">
                            <div class="activity-action"><?= htmlspecialchars($act['action']) ?></div>
                            <div class="activity-meta">
                                <?= htmlspecialchars($act['table_name']) ?>
                                <?php if ($act['record_id']): ?> · ID <?= $act['record_id'] ?><?php endif; ?><br>
                                <?php if ($act['branch_name']): ?>
                                    <span style="color:var(--blue);font-weight:600;"><?= htmlspecialchars($act['branch_name']) ?></span> · 
                                <?php endif; ?>
                                <?= htmlspecialchars($act['user_name'] ?? 'System') ?>
                            </div>
                        </div>
                        <div class="activity-time"><?= date('M d\nH:i', strtotime($act['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="feather icon-inbox"></i>
                        <p>No recent activities</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>
</div>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-user" style="margin-right:8px;"></i>Profile Settings</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="profileUpdateForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="profile_pic">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                    </div>
                    <button type="submit" class="btn-primary-grad">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-settings" style="margin-right:8px;"></i>Account Settings</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="settingsUpdateForm">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-primary-grad">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function changeBranch() {
    const val = document.getElementById('branchSelector').value;
    const url = new URL(window.location);
    val === '0' ? url.searchParams.delete('branch') : url.searchParams.set('branch', val);
    window.location.href = url.toString();
}

$('#profileUpdateForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'update_profile.php', type: 'POST',
        data: new FormData(this), processData: false, contentType: false,
        success: function(r) {
            if (r.success) { $('#profileModal').modal('hide'); location.reload(); }
            else alert('Error: ' + r.message);
        },
        error: function() { alert('An error occurred while updating profile'); }
    });
});

$('#settingsUpdateForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'update_settings.php', type: 'POST', data: $(this).serialize(),
        success: function(r) {
            if (r.success) { $('#settingsModal').modal('hide'); alert('Password updated successfully'); }
            else alert('Error: ' + r.message);
        },
        error: function() { alert('An error occurred while updating settings'); }
    });
});
</script>

<?php include 'footer.php'; ?>