<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include global security module which sets headers
require_once 'security.php';

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

// ── CURRENCY HELPER ───────────────────────────────────────────────────────────
function getCurrencySymbol($currency) {
    $symbols = [
        'USD' => '$',
        'AFN' => '؋',
        'AFS' => '؋',  // Legacy support
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'JPY' => '¥',
        'CNY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NZD' => 'NZ$',
    ];
    return $symbols[strtoupper($currency)] ?? '$';
}

// ── USER DATA ─────────────────────────────────────────────────────────────────
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, name, profile_pic, created_at FROM users WHERE id = ? AND role = 'super_admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Admin', 'email' => 'Not Set', 'profile_pic' => null, 'created_at' => 'now'];
$imagePath = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/images/user/avatar-2.jpg';

// ── PLATFORM OVERVIEW ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) as total_tenants FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$total_tenants = $stmt->fetch()['total_tenants'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE deleted_at IS NULL");
$stmt->execute();
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->prepare("SELECT COUNT(*) as active_subscriptions FROM tenant_subscriptions WHERE status = 'active'");
$stmt->execute();
$active_subscriptions = $stmt->fetch()['active_subscriptions'];

// ── FINANCIAL DATA (current month) ───────────────────────────────────────────
$current_month = date('Y-m');
$start_date    = date('Y-m-01');
$end_date      = date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT currency, SUM(amount) as total FROM system_revenue
    WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
    GROUP BY currency
");
$stmt->execute([$start_date, $end_date]);
$revenue_by_currency = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("
    SELECT currency, SUM(amount) as total FROM system_expenses
    WHERE date BETWEEN ? AND ?
    GROUP BY currency
");
$stmt->execute([$start_date, $end_date]);
$expense_by_currency = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthly_data    = [];
$all_currencies  = array_unique(array_merge(array_keys($revenue_by_currency), array_keys($expense_by_currency)));
foreach ($all_currencies as $currency) {
    $rev    = floatval($revenue_by_currency[$currency] ?? 0);
    $exp    = floatval($expense_by_currency[$currency] ?? 0);
    $profit = $rev - $exp;
    $margin = $rev > 0 ? ($profit / $rev) * 100 : 0;
    $monthly_data[$currency] = compact('rev', 'exp', 'profit', 'margin');
}

$total_revenue  = array_sum($revenue_by_currency);
$total_expenses = array_sum($expense_by_currency);
$monthly_profit = array_sum(array_column($monthly_data, 'profit'));

// ── MRR / ARR ─────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT ts.currency, SUM(ts.amount) as mrr, COUNT(*) as subscription_count FROM tenant_subscriptions ts WHERE ts.status = 'active' AND ts.billing_cycle = 'monthly' GROUP BY ts.currency");
$stmt->execute();
$mrr_by_currency = [];
$mrr = 0;
$mrr_subs = 0;
$mrr_currency = 'USD';
foreach ($stmt->fetchAll() as $row) {
    $mrr_by_currency[$row['currency']] = floatval($row['mrr'] ?? 0);
    $mrr += floatval($row['mrr'] ?? 0);
    $mrr_subs += intval($row['subscription_count'] ?? 0);
    if (empty($mrr_currency) || $row['currency'] !== 'USD') {
        $mrr_currency = $row['currency'];
    }
}

$stmt = $pdo->prepare("SELECT ts.currency, SUM(ts.amount) as yearly_revenue FROM tenant_subscriptions ts WHERE ts.status = 'active' AND ts.billing_cycle = 'yearly' GROUP BY ts.currency");
$stmt->execute();
$yearly_by_currency = [];
$yearly_revenue = 0;
foreach ($stmt->fetchAll() as $row) {
    $yearly_by_currency[$row['currency']] = floatval($row['yearly_revenue'] ?? 0);
    $yearly_revenue += floatval($row['yearly_revenue'] ?? 0);
}

$stmt = $pdo->prepare("SELECT ts.currency, SUM(ts.amount) as quarterly_revenue FROM tenant_subscriptions ts WHERE ts.status = 'active' AND ts.billing_cycle = 'quarterly' GROUP BY ts.currency");
$stmt->execute();
$quarterly_by_currency = [];
$quarterly_revenue = 0;
foreach ($stmt->fetchAll() as $row) {
    $quarterly_by_currency[$row['currency']] = floatval($row['quarterly_revenue'] ?? 0);
    $quarterly_revenue += floatval($row['quarterly_revenue'] ?? 0);
}

$arr = ($mrr * 12) + $yearly_revenue + ($quarterly_revenue * 4);

// ── REVENUE TREND (12 months) ─────────────────────────────────────────────────
$revenue_trend  = [];
$expense_trend  = [];
$revenue_labels = [];
for ($i = 11; $i >= 0; $i--) {
    $month_date  = date('Y-m-01', strtotime("-$i months"));
    $month_key   = date('Y-m', strtotime($month_date));
    $month_start = $month_key . '-01';
    $month_end   = date('Y-m-t', strtotime($month_start));

    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM system_revenue WHERE payment_date BETWEEN ? AND ? AND status = 'completed'");
    $stmt->execute([$month_start, $month_end]);
    $revenue_trend[] = floatval($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM system_expenses WHERE date BETWEEN ? AND ?");
    $stmt->execute([$month_start, $month_end]);
    $expense_trend[] = floatval($stmt->fetch()['total'] ?? 0);

    $revenue_labels[] = date('M y', strtotime($month_start));
}

// ── SUBSCRIPTION STATUS ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tenant_subscriptions GROUP BY status");
$stmt->execute();
$sub_status_data = ['active' => 0, 'expired' => 0, 'pending' => 0];
foreach ($stmt->fetchAll() as $s) {
    $sub_status_data[$s['status']] = $s['count'];
}

// ── SUBSCRIPTIONS BY PLAN ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.name, ts.currency, COUNT(ts.id) as count, SUM(ts.amount) as total_value
    FROM tenant_subscriptions ts
    LEFT JOIN plans p ON CAST(ts.plan_id AS UNSIGNED) = p.id
    WHERE ts.status = 'active'
    GROUP BY ts.plan_id, p.name, ts.currency
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute();
$subscriptions_by_plan = $stmt->fetchAll();
$total_plan_subs = array_sum(array_column($subscriptions_by_plan, 'count'));
$max_plan_count  = $subscriptions_by_plan[0]['count'] ?? 1;

// ── CHURN RATE ────────────────────────────────────────────────────────────────
$prev_month = date('Y-m', strtotime('-1 month'));
$prev_start = $prev_month . '-01';
$prev_end   = date('Y-m-t', strtotime($prev_start));

$stmt = $pdo->prepare("SELECT COUNT(*) as expired FROM tenant_subscriptions WHERE status = 'expired' AND updated_at BETWEEN ? AND ?");
$stmt->execute([$prev_start, $prev_end]);
$prev_expired = $stmt->fetch()['expired'];

$stmt = $pdo->prepare("SELECT COUNT(*) as expired FROM tenant_subscriptions WHERE status = 'expired' AND updated_at BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$curr_expired = $stmt->fetch()['expired'];
$churn_rate   = $prev_expired > 0 ? (($curr_expired - $prev_expired) / $prev_expired) * 100 : 0;

// ── REVENUE AT RISK ────────────────────────────────────────────────────────────
$risk_date = date('Y-m-d', strtotime('+30 days'));
$stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(ts.amount) as total_value FROM tenant_subscriptions ts WHERE ts.status = 'active' AND ts.end_date IS NOT NULL AND DATE(ts.end_date) BETWEEN ? AND ?");
$stmt->execute([$end_date, $risk_date]);
$revenue_at_risk = $stmt->fetch();

// ── SUBSCRIPTION METRICS ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT AVG(ts.amount) as avg_value, MIN(ts.amount) as min_value, MAX(ts.amount) as max_value FROM tenant_subscriptions ts WHERE ts.status = 'active'");
$stmt->execute();
$subscription_metrics = $stmt->fetch();

// ── TENANT HEALTH ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT t.id) as total_active,
        COUNT(DISTINCT CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN t.id END) as new_this_month,
        COUNT(DISTINCT CASE WHEN t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN t.id END) as new_this_week
    FROM tenants t WHERE t.status != 'deleted'
");
$stmt->execute();
$tenant_health = $stmt->fetch();

// ── SUPPORT TICKETS ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM support_tickets WHERE status IN ('open', 'pending')");
$stmt->execute();
$open_tickets = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM support_tickets GROUP BY status");
$stmt->execute();
$ticket_rows = $stmt->fetchAll();
$ticket_status = ['open' => 0, 'pending' => 0, 'resolved' => 0, 'closed' => 0];
foreach ($ticket_rows as $t) { $ticket_status[$t['status']] = $t['count']; }

// ── DEMO CONVERSION ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as conversion_rate
    FROM demo_requests
    WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$demo_conversion = $stmt->fetch();

// ── RECENT TRANSACTIONS ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    (SELECT 'revenue' as type, sr.amount, sr.currency, sr.created_at as timestamp, sr.description
     FROM system_revenue sr WHERE sr.payment_date BETWEEN ? AND ? ORDER BY sr.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'expense' as type, se.amount, se.currency, se.created_at as timestamp, se.description
     FROM system_expenses se WHERE se.date BETWEEN ? AND ? ORDER BY se.created_at DESC LIMIT 5)
    ORDER BY timestamp DESC LIMIT 10
");
$stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$recent_transactions = $stmt->fetchAll();

// ── EXPENSES BY CATEGORY ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT sec.name, sec.id, COUNT(se.id) as count, SUM(se.amount) as total, se.currency
    FROM system_expenses se
    LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
    WHERE se.date BETWEEN ? AND ?
    GROUP BY sec.id, sec.name, se.currency
    ORDER BY total DESC LIMIT 8
");
$stmt->execute([$start_date, $end_date]);
$expenses_by_category = $stmt->fetchAll();
$total_cat_expenses   = array_sum(array_column($expenses_by_category, 'total'));

// ── MOST ACTIVE TENANTS ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.name as tenant_name,
        COUNT(DISTINCT al.id) as activity_count,
        COUNT(DISTINCT al.user_id) as active_users,
        MAX(al.created_at) as last_activity,
        COUNT(DISTINCT CASE WHEN al.action = 'create' THEN 1 END) as records_created,
        COUNT(DISTINCT CASE WHEN al.action = 'update' THEN 1 END) as records_updated,
        COUNT(DISTINCT CASE WHEN al.action = 'delete' THEN 1 END) as records_deleted
    FROM tenants t
    LEFT JOIN activity_log al ON t.id = al.tenant_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE t.status != 'deleted'
    GROUP BY t.id, t.name
    ORDER BY activity_count DESC
    LIMIT 6
");
$stmt->execute();
$most_active_tenants = $stmt->fetchAll();
$max_activity = $most_active_tenants[0]['activity_count'] ?? 1;

// ── TENANT ENGAGEMENT SCORE ───────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.name as tenant_name, ts.status as subscription_status,
        COUNT(DISTINCT u.id) as user_count,
        COUNT(DISTINCT lh.user_id) as users_logged_in_7d,
        COUNT(DISTINCT al.id) as activities_30d,
        CASE
            WHEN COUNT(DISTINCT al.id) > 100 THEN 'Very High'
            WHEN COUNT(DISTINCT al.id) > 50  THEN 'High'
            WHEN COUNT(DISTINCT al.id) > 10  THEN 'Medium'
            WHEN COUNT(DISTINCT al.id) > 0   THEN 'Low'
            ELSE 'Inactive'
        END as engagement_level,
        ROUND((COUNT(DISTINCT lh.user_id) / NULLIF(COUNT(DISTINCT u.id), 0)) * 100, 1) as login_rate_percent
    FROM tenants t
    LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
    LEFT JOIN users u ON t.id = u.tenant_id AND u.deleted_at IS NULL
    LEFT JOIN login_history lh ON t.id = lh.tenant_id AND lh.action = 'login' AND lh.action_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    LEFT JOIN activity_log al ON t.id = al.tenant_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE t.status != 'deleted'
    GROUP BY t.id, t.name, ts.status
    ORDER BY COUNT(DISTINCT al.id) DESC
    LIMIT 20
");
$stmt->execute();
$tenant_engagement_score = $stmt->fetchAll();

$eng_counts = ['Very High' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0, 'Inactive' => 0];
$total_logins_7d = 0;
foreach ($tenant_engagement_score as $t) {
    $eng_counts[$t['engagement_level']] = ($eng_counts[$t['engagement_level']] ?? 0) + 1;
    $total_logins_7d += $t['users_logged_in_7d'];
}

// ── TENANT USER METRICS (online) ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ous.user_id) as currently_online
    FROM user_online_sessions ous
    WHERE ous.last_activity >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute();
$currently_online = $stmt->fetch()['currently_online'] ?? 0;

// ── FEATURE ADOPTION ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.name as tenant_name,
        COUNT(DISTINCT CASE WHEN al.table_name = 'chat_messages' THEN 1 END) as uses_chat,
        COUNT(DISTINCT CASE WHEN al.table_name IN ('ticket_bookings','ticket_refunds') THEN 1 END) as uses_tickets,
        COUNT(DISTINCT CASE WHEN al.table_name IN ('umrah_bookings','umrah_refunds') THEN 1 END) as uses_umrah,
        COUNT(DISTINCT CASE WHEN al.table_name IN ('hotel_bookings','hotel_refunds') THEN 1 END) as uses_hotels,
        COUNT(DISTINCT CASE WHEN al.table_name IN ('visa_applications','visa_refunds') THEN 1 END) as uses_visas
    FROM tenants t
    LEFT JOIN activity_log al ON t.id = al.tenant_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE t.status != 'deleted'
    GROUP BY t.id, t.name
    ORDER BY t.name LIMIT 20
");
$stmt->execute();
$tenant_feature_adoption = $stmt->fetchAll();

// ── AT-RISK TENANTS (churn scoring) ──────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT t.id, t.name as tenant_name,
        COUNT(DISTINCT al.id) as activities_30d,
        COUNT(DISTINCT lh.user_id) as users_logged_in_7d,
        COUNT(DISTINCT u.id) as total_users,
        DATEDIFF(NOW(), IFNULL(MAX(al.created_at), '2000-01-01')) as days_since_activity,
        DATEDIFF(NOW(), ts.start_date) as subscription_age_days
    FROM tenants t
    LEFT JOIN activity_log al ON t.id = al.tenant_id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN login_history lh ON t.id = lh.tenant_id AND lh.action = 'login' AND lh.action_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    LEFT JOIN users u ON t.id = u.tenant_id AND u.deleted_at IS NULL AND u.fired = 0
    LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
    WHERE t.status != 'deleted'
    GROUP BY t.id, t.name, ts.start_date
");
$stmt->execute();
$all_tenants_for_risk    = $stmt->fetchAll();
$at_risk_tenants_phase1  = [];

foreach ($all_tenants_for_risk as $tenant) {
    $risk_score    = 0;
    $days_inactive = intval($tenant['days_since_activity'] ?? 0);
    $activities    = intval($tenant['activities_30d'] ?? 0);
    $total_users   = intval($tenant['total_users'] ?? 1);
    $users_active  = intval($tenant['users_logged_in_7d'] ?? 0);
    $adoption_rate = $total_users > 0 ? ($users_active / $total_users) : 0;
    $sub_age       = intval($tenant['subscription_age_days'] ?? 999);

    if ($days_inactive > 30)    $risk_score += min(30, ($days_inactive - 30) / 2);
    if ($activities < 5)        $risk_score += ((5 - $activities) / 5) * 25;
    if ($adoption_rate < 0.5)   $risk_score += ((0.5 - $adoption_rate) / 0.5) * 20;
    if ($sub_age < 30 && $sub_age > 0) $risk_score += ((30 - $sub_age) / 30) * 15;

    $risk_score = min(100, round($risk_score, 1));
    if ($risk_score < 25) continue;

    $risk_level = 'low';
    if ($risk_score >= 70)      $risk_level = 'critical';
    elseif ($risk_score >= 50)  $risk_level = 'high';
    elseif ($risk_score >= 30)  $risk_level = 'medium';

    $at_risk_tenants_phase1[] = [
        'tenant_id'    => $tenant['id'],
        'tenant_name'  => $tenant['tenant_name'],
        'risk_score'   => $risk_score,
        'risk_level'   => $risk_level,
        'days_inactive'=> $days_inactive,
        'activities_30d'=> $activities,
        'users_active_7d'=> $users_active,
        'total_users'  => $total_users,
        'adoption_rate'=> round($adoption_rate * 100, 1),
    ];
}
usort($at_risk_tenants_phase1, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

$risk_counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
foreach ($at_risk_tenants_phase1 as $t) $risk_counts[$t['risk_level']]++;

// ── TOP SALES AGENTS ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.id, u.name,
        COUNT(DISTINCT ts.id) as subscriptions_count,
        COALESCE(SUM(ts.amount), 0) as total_revenue, 'USD' as currency
    FROM users u
    LEFT JOIN tenant_subscriptions ts ON u.id = ts.tenant_id
    WHERE u.role = 'sales_agent' AND (ts.status = 'active' OR ts.status IS NULL)
    GROUP BY u.id, u.name
    ORDER BY total_revenue DESC LIMIT 8
");
$stmt->execute();
$top_sales_agents = $stmt->fetchAll();

// ── RECENT AUDIT LOGS ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT action, entity_type, entity_id, details, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_audit_logs = $stmt->fetchAll();

// ── ACTIVITY BY ACTION (30d) ──────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT action, COUNT(*) as count FROM audit_logs WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY action");
$stmt->execute();
$activity_rows = $stmt->fetchAll();
$activity_labels = [];
$activity_counts = [];
foreach ($activity_rows as $d) { $activity_labels[] = $d['action']; $activity_counts[] = $d['count']; }

// ── TENANT GROWTH (6 months) ──────────────────────────────────────────────────
$tenant_growth = [];
$growth_months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $growth_months[] = date('M Y', strtotime($month));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE status != 'deleted' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $tenant_growth[] = $stmt->fetch()['count'];
}
?>
<?php
// Inject dashboard fonts + scoped CSS before header closes its <head>
// The header_super_admin include outputs <html><head>...<body><nav><header>
// Our custom CSS goes into a variable that header can echo in <head> if supported,
// or we inject it right after the include via an inline <style> tag.
include '../includes/header_super_admin.php';
?>
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

/* ─── TOP BAR ────────────────────────────────────────────── */
.sa-topbar {
  padding: 16px 28px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
}
.sa-topbar-left h1 { font-size: 1.05rem; font-weight: 600; letter-spacing: -.02em; }
.sa-topbar-left p  { font-size: .75rem; color: var(--muted); margin-top: 2px; }
.sa-topbar-right   { display: flex; align-items: center; gap: 10px; }
.sa-topbar-date {
  font-family: 'JetBrains Mono', monospace; font-size: .73rem;
  color: var(--muted); background: var(--surface2);
  padding: 5px 11px; border-radius: 8px; border: 1px solid var(--border);
}
.sa-avatar {
  width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
  border: 2px solid var(--accent); cursor: pointer; flex-shrink: 0;
}
.sa-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sa-btn {
  font-size: .75rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 6px 14px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 5px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: rgba(255,255,255,.15); }
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }

/* ─── KPI RAIL ───────────────────────────────────────────── */
.sa-kpi-rail {
  display: grid; grid-template-columns: repeat(4, 1fr);
  background: var(--surface); border-bottom: 1px solid var(--border);
}
.sa-kpi-item {
  padding: 16px 24px; border-right: 1px solid var(--border);
  position: relative; overflow: hidden; cursor: default;
}
.sa-kpi-item:last-child { border-right: none; }
.sa-kpi-item::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  background: var(--accent); transform: scaleX(0); transition: transform .3s;
  transform-origin: left;
}
.sa-kpi-item:hover::after { transform: scaleX(1); }
.sa-kpi-val {
  font-size: 1.5rem; font-weight: 700; letter-spacing: -.03em;
  font-family: 'JetBrains Mono', monospace; line-height: 1;
}
.sa-kpi-label { font-size: .7rem; color: var(--muted); margin-top: 4px; font-weight: 500; }
.sa-kpi-delta {
  font-size: .68rem; font-family: 'JetBrains Mono', monospace;
  margin-top: 5px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;
}
.sa-kpi-delta.up   { color: var(--green); }
.sa-kpi-delta.down { color: var(--red); }
.sa-kpi-delta.neu  { color: var(--muted); }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { padding: 24px 28px; display: flex; flex-direction: column; gap: 24px; }

/* ─── SECTION HEADER ─────────────────────────────────────── */
.sa-shdr {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 14px;
}
.sa-shdr h2 {
  font-size: .72rem; text-transform: uppercase; letter-spacing: .1em;
  color: var(--muted); font-weight: 700;
}
.sa-shdr a { font-size: .72rem; color: var(--accent2); text-decoration: none; font-weight: 600; }
.sa-shdr a:hover { color: var(--accent); }

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
  transition: border-color .2s, transform .2s;
}
.sa-card:hover { border-color: rgba(108,99,255,.25); transform: translateY(-1px); }
.sa-card-hdr {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.sa-card-hdr h3 { font-size: .85rem; font-weight: 600; }
.sa-card-body { padding: 18px 20px; }

/* ─── STAT CARDS ─────────────────────────────────────────── */
.sa-stat-grid  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.sa-stat-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.sa-stat {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 20px 22px;
  position: relative; overflow: hidden; transition: all .2s;
}
.sa-stat::after {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
  background: var(--accent); opacity: 0; transition: opacity .2s;
}
.sa-stat:hover::after { opacity: 1; }
.sa-stat:hover { transform: translateY(-2px); border-color: rgba(108,99,255,.3); }
.sa-stat-top  { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.sa-stat-icon {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sa-stat-icon svg { width: 17px; height: 17px; }
.si-green  { background: rgba(34,211,160,.12); color: var(--green); }
.si-red    { background: rgba(244,63,94,.12);  color: var(--red); }
.si-amber  { background: rgba(245,158,11,.12); color: var(--amber); }
.si-blue   { background: rgba(56,189,248,.12); color: var(--blue); }
.si-purple { background: rgba(108,99,255,.12); color: var(--accent2); }
.sa-stat-val  { font-size: 1.45rem; font-weight: 700; letter-spacing: -.03em; font-family: 'JetBrains Mono', monospace; }
.sa-stat-name { font-size: .72rem; color: var(--muted); margin-top: 2px; font-weight: 500; }
.sa-stat-foot {
  font-size: .68rem; color: var(--muted); margin-top: 10px; padding-top: 10px;
  border-top: 1px solid var(--border); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}

/* ─── PILL / BADGE ───────────────────────────────────────── */
.pill {
  font-size: .62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
  text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.pill-green  { background: rgba(34,211,160,.12); color: var(--green); }
.pill-red    { background: rgba(244,63,94,.12);  color: var(--red); }
.pill-amber  { background: rgba(245,158,11,.12); color: var(--amber); }
.pill-blue   { background: rgba(56,189,248,.12); color: var(--blue); }
.pill-purple { background: rgba(108,99,255,.12); color: var(--accent2); }
.pill-muted  { background: var(--surface2);      color: var(--muted); }
.badge-num {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 5px; border-radius: 20px;
  font-size: .63rem; font-weight: 700;
  background: rgba(108,99,255,.2); color: var(--accent2);
  font-family: 'JetBrains Mono', monospace;
}

/* ─── 2 / 3 COL GRIDS ────────────────────────────────────── */
.sa-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.sa-3col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }

/* ─── PLAN RANK ──────────────────────────────────────────── */
.plan-rank {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; background: var(--surface2); border-radius: 9px;
  margin-bottom: 6px; transition: background .15s;
}
.plan-rank:last-child { margin-bottom: 0; }
.plan-rank:hover { background: rgba(108,99,255,.08); }
.pr-num {
  width: 24px; height: 24px; border-radius: 7px; background: var(--surface);
  border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
  font-size: .68rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
  color: var(--muted); flex-shrink: 0;
}
.pr-num.gold   { border-color: var(--amber); color: var(--amber); background: rgba(245,158,11,.08); }
.pr-num.silver { border-color: #94a3b8; color: #94a3b8; }
.pr-num.bronze { border-color: #cd7f32; color: #cd7f32; }
.pr-info { flex: 1; min-width: 0; }
.pr-name  { font-size: .8rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pr-sub   { font-size: .68rem; color: var(--muted); margin-top: 1px; }
.pr-bar   { flex: 2; }
.pr-track { height: 5px; background: var(--surface); border-radius: 10px; overflow: hidden; }
.pr-fill  { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--accent), var(--accent2)); }
.pr-val   { font-size: .75rem; font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--accent2); flex-shrink: 0; margin-left: 8px; }

/* ─── TRANSACTION LIST ───────────────────────────────────── */
.txn-list { display: flex; flex-direction: column; gap: 6px; }
.txn-item {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px;
  background: var(--surface2); border-radius: 9px; transition: background .15s;
}
.txn-item:hover { background: rgba(108,99,255,.08); }
.txn-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.txn-dot.rev { background: var(--green); }
.txn-dot.exp { background: var(--red); }
.txn-desc { flex: 1; font-size: .75rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.txn-date { font-size: .65rem; color: var(--muted); font-family: 'JetBrains Mono', monospace; flex-shrink: 0; }
.txn-amt  { font-size: .78rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; flex-shrink: 0; }
.txn-amt.rev { color: var(--green); }
.txn-amt.exp { color: var(--red); }

/* ─── ENGAGE SUMMARY ─────────────────────────────────────── */
.eng-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 14px; }
.eng-item {
  background: var(--surface2); border-radius: 9px; padding: 12px 8px; text-align: center;
  transition: all .2s;
}
.eng-item:hover { background: rgba(108,99,255,.08); }
.eng-val   { font-size: 1.35rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.eng-label { font-size: .6rem; color: var(--muted); margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }

/* ─── TENANT CARDS ───────────────────────────────────────── */
.tc {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 16px 18px;
  display: flex; flex-direction: column; gap: 10px; transition: all .2s;
}
.tc:hover { border-color: rgba(108,99,255,.3); transform: translateY(-1px); }
.tc-top  { display: flex; align-items: flex-start; justify-content: space-between; }
.tc-name { font-weight: 600; font-size: .85rem; margin-bottom: 2px; }
.tc-meta { font-size: .68rem; color: var(--muted); }
.minibar-wrap { display: flex; flex-direction: column; gap: 4px; }
.minibar-row  { display: flex; align-items: center; gap: 7px; }
.minibar-lbl  { font-size: .63rem; color: var(--muted); width: 54px; flex-shrink: 0; }
.minibar-track { flex: 1; height: 4px; background: var(--surface2); border-radius: 10px; overflow: hidden; }
.minibar-fill  { height: 100%; border-radius: 10px; transition: width .8s cubic-bezier(.25,.8,.25,1); }
.minibar-val  { font-size: .63rem; font-family: 'JetBrains Mono', monospace; color: var(--muted); width: 26px; text-align: right; flex-shrink: 0; }
.tc-foot { display: flex; align-items: center; justify-content: space-between; }
.tc-stat { text-align: center; }
.tc-stat-v { font-size: .95rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.tc-stat-k { font-size: .6rem; color: var(--muted); margin-top: 1px; }

/* ─── RISK CARDS ─────────────────────────────────────────── */
.risk-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 16px 18px;
  display: flex; align-items: flex-start; gap: 12px; transition: all .2s;
}
.risk-card:hover { border-color: rgba(244,63,94,.3); transform: translateY(-1px); }
.risk-ring { width: 50px; height: 50px; flex-shrink: 0; position: relative; }
.risk-ring svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--surface2); stroke-width: 5; }
.ring-fill { fill: none; stroke-width: 5; stroke-linecap: round; }
.ring-lbl  {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  font-size: .68rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
}
.risk-body  { flex: 1; min-width: 0; }
.risk-name  { font-weight: 600; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.risk-why   { font-size: .68rem; color: var(--muted); margin-top: 2px; }
.risk-tags  { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 7px; }
.risk-tag   { font-size: .58rem; padding: 2px 6px; border-radius: 20px; background: var(--surface2); color: var(--muted); font-family: 'JetBrains Mono', monospace; }

/* ─── HEATMAP ────────────────────────────────────────────── */
.hm-table { width: 100%; border-collapse: collapse; }
.hm-table th { font-size: .65rem; color: var(--muted); font-weight: 600; padding: 4px 8px; text-align: center; text-transform: uppercase; letter-spacing: .05em; }
.hm-table td { padding: 4px 8px; }
.hm-table .row-lbl { font-size: .72rem; font-weight: 500; white-space: nowrap; padding-right: 14px; }
.hm-cell {
  width: 32px; height: 26px; border-radius: 5px; margin: 0 auto;
  display: flex; align-items: center; justify-content: center;
  font-size: .65rem; font-weight: 700; transition: transform .15s; cursor: default;
}
.hm-cell:hover { transform: scale(1.15); }
.hm-on  { background: rgba(34,211,160,.18); color: var(--green); }
.hm-off { background: var(--surface2); color: var(--muted); }

/* ─── AUDIT TIMELINE ─────────────────────────────────────── */
.timeline { position: relative; padding-left: 26px; }
.timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 1px; background: var(--border); }
.tl-item  { position: relative; padding-bottom: 14px; }
.tl-item:last-child { padding-bottom: 0; }
.tl-dot {
  position: absolute; left: -26px; width: 20px; height: 20px;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  font-size: .6rem; color: white;
}
.tl-dot.create { background: var(--green); }
.tl-dot.other  { background: var(--accent); }
.tl-box { background: var(--surface2); padding: 10px 12px; border-radius: 8px; }
.tl-title { font-weight: 600; font-size: .78rem; color: var(--text); }
.tl-meta  { font-size: .65rem; color: var(--muted); margin-top: 3px; }

/* ─── LEGEND ─────────────────────────────────────────────── */
.legend { display: flex; align-items: center; gap: 14px; }
.legend-item { display: flex; align-items: center; gap: 4px; font-size: .68rem; color: var(--muted); }
.legend-dot  { width: 7px; height: 7px; border-radius: 50%; }

/* ─── MODAL OVERRIDES ────────────────────────────────────── */
.modal-content { background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: var(--radius); }
.modal-header  { background: linear-gradient(135deg, var(--accent), var(--accent2)); border-radius: var(--radius) var(--radius) 0 0; border-bottom: none; padding: 16px 20px; }
.modal-header .modal-title { color: #fff; font-weight: 600; font-size: .95rem; }
.modal-header .close { color: rgba(255,255,255,.7); text-shadow: none; }
.modal-header .close:hover { color: #fff; opacity: 1; }
.modal-body   { padding: 20px; }
.modal-footer { border-top: 1px solid var(--border); padding: 14px 20px; }
.form-control {
  background: var(--surface2); border: 1px solid var(--border); color: var(--text);
  border-radius: 8px; padding: 9px 12px; font-family: 'Sora', sans-serif; font-size: .82rem;
}
.form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(108,99,255,.2); background: var(--surface2); color: var(--text); outline: none; }
.form-label { font-size: .75rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block; }

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
.sa-kpi-rail { position: sticky; top: 0; z-index: 30; }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <!-- Page breadcrumb -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <div class="page-header-title">
                  <h5 class="m-b-10"><?= __('super_admin_dashboard') ?></h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item active"><?= __('dashboard') ?></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- DASHBOARD SHELL -->
        <div class="sa-wrap">

        <!-- ── KPI RAIL ── -->
  <div class="sa-kpi-rail">
    <div class="sa-kpi-item">
      <div class="sa-kpi-val" style="color:var(--green)"><?= getCurrencySymbol($mrr_currency) ?><?= number_format($mrr, 0) ?></div>
      <div class="sa-kpi-label">Monthly Recurring Revenue</div>
      <div class="sa-kpi-delta neu"><?= $mrr_subs ?> active subscriptions</div>
    </div>
    <div class="sa-kpi-item">
      <div class="sa-kpi-val"><?= getCurrencySymbol($mrr_currency) ?><?= number_format($arr, 0) ?></div>
      <div class="sa-kpi-label">Annual Recurring Revenue</div>
      <div class="sa-kpi-delta neu">MRR × 12 + yearly plans</div>
    </div>
    <div class="sa-kpi-item">
      <div class="sa-kpi-val"><?= $total_tenants ?></div>
      <div class="sa-kpi-label">Active Tenants</div>
      <div class="sa-kpi-delta up">↑ <?= $tenant_health['new_this_month'] ?? 0 ?> new this month</div>
    </div>
    <div class="sa-kpi-item">
      <div class="sa-kpi-val" style="color:<?= count($at_risk_tenants_phase1) > 0 ? 'var(--red)' : 'var(--green)' ?>">
        <?= count($at_risk_tenants_phase1) ?>
      </div>
      <div class="sa-kpi-label">At-Risk Tenants</div>
      <div class="sa-kpi-delta <?= $risk_counts['critical'] > 0 ? 'down' : 'neu' ?>">
        <?= $risk_counts['critical'] ?> critical · <?= $risk_counts['high'] ?> high
      </div>
    </div>
  </div>

  <!-- ── CONTENT ── -->
  <div class="sa-content">

    <!-- ── FINANCIAL HEALTH ── -->
    <div>
      <div class="sa-shdr">
        <h2>Financial Health — <?= date('F Y') ?></h2>
        <a href="profit_loss_dashboard.php?period=<?= date('Y-m') ?>">View P&L →</a>
      </div>
      <div class="sa-stat-grid">
        <?php foreach ($monthly_data as $currency => $data):
          $sym       = getCurrencySymbol($currency);
          $isPos     = $data['profit'] >= 0;
          $col       = $isPos ? 'var(--green)' : 'var(--red)';
          $pill_cls  = $isPos ? 'pill-green' : 'pill-red';
          $arrow     = $isPos ? '↑' : '↓';
        ?>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val" style="color:<?= $col ?>"><?= $sym . number_format($data['rev'], 2) ?></div>
              <div class="sa-stat-name">Revenue (<?= htmlspecialchars($currency) ?>)</div>
            </div>
            <div class="sa-stat-icon <?= $isPos ? 'si-green' : 'si-red' ?>">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $isPos ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' ?>"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">
            <span class="pill <?= $pill_cls ?>"><?= $arrow ?> <?= $sym . number_format(abs($data['profit']), 2) ?> profit</span>
            <span>Margin: <?= number_format($data['margin'], 1) ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val" style="color:var(--red)">
                <?php
                  $exp_currency = !empty($expenses_by_category) ? $expenses_by_category[0]['currency'] : 'USD';
                  $exp_sym = getCurrencySymbol($exp_currency);
                  echo $exp_sym . number_format($total_cat_expenses, 2);
                ?>
              </div>
              <div class="sa-stat-name">Total Expenses</div>
            </div>
            <div class="sa-stat-icon si-red">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">
            <?php foreach (array_slice($expenses_by_category, 0, 3) as $ec): ?>
              <span class="pill pill-muted"><?= htmlspecialchars($ec['name'] ?? 'Other') ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── CHARTS ROW ── -->
    <div class="sa-2col">
      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Revenue vs Expenses — 12 Months</h3>
          <div class="legend">
            <div class="legend-item"><div class="legend-dot" style="background:var(--accent)"></div>Revenue</div>
            <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>Expenses</div>
          </div>
        </div>
        <div class="sa-card-body">
          <canvas id="revenueChart" height="220"></canvas>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Subscription Status</h3>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <span class="pill pill-green">Active: <?= $sub_status_data['active'] ?></span>
            <span class="pill pill-red">Expired: <?= $sub_status_data['expired'] ?></span>
            <span class="pill pill-amber">Pending: <?= $sub_status_data['pending'] ?></span>
          </div>
        </div>
        <div class="sa-card-body">
          <canvas id="subChart" height="220"></canvas>
        </div>
      </div>
    </div>

    <!-- ── PLANS + TRANSACTIONS ── -->
    <div class="sa-2col">
      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Plans by Subscription Count</h3>
          <span class="badge-num"><?= count($subscriptions_by_plan) ?></span>
        </div>
        <div class="sa-card-body">
          <?php if (!empty($subscriptions_by_plan)):
            $rank_classes = ['gold','silver','bronze','','',''];
            foreach ($subscriptions_by_plan as $i => $plan):
              $pct = $max_plan_count > 0 ? ($plan['count'] / $max_plan_count) * 100 : 0;
              $sym = getCurrencySymbol($plan['currency'] ?? 'USD');
          ?>
          <div class="plan-rank">
            <div class="pr-num <?= $rank_classes[$i] ?? '' ?>">#<?= $i + 1 ?></div>
            <div class="pr-info">
              <div class="pr-name"><?= htmlspecialchars($plan['name'] ?? 'Unnamed') ?></div>
              <div class="pr-sub"><?= $plan['count'] ?> active</div>
            </div>
            <div class="pr-bar"><div class="pr-track"><div class="pr-fill" style="width:<?= round($pct) ?>%"></div></div></div>
            <div class="pr-val"><?= $sym . number_format($plan['total_value'] ?? 0, 0) ?></div>
          </div>
          <?php endforeach; else: ?>
          <p style="text-align:center;color:var(--muted);padding:24px 0">No active subscriptions</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Recent Transactions</h3>
          <a href="profit_loss_dashboard.php" style="font-size:.72rem;color:var(--accent2);text-decoration:none">View all →</a>
        </div>
        <div class="sa-card-body">
          <?php if (!empty($recent_transactions)): ?>
          <div class="txn-list">
            <?php foreach ($recent_transactions as $txn):
              $sym = getCurrencySymbol($txn['currency']);
              $cls = $txn['type'] === 'revenue' ? 'rev' : 'exp';
              $prefix = $txn['type'] === 'revenue' ? '+' : '-';
            ?>
            <div class="txn-item">
              <div class="txn-dot <?= $cls ?>"></div>
              <div class="txn-desc"><?= htmlspecialchars($txn['description'] ?? 'N/A') ?></div>
              <div class="txn-date"><?= date('M d', strtotime($txn['timestamp'])) ?></div>
              <div class="txn-amt <?= $cls ?>"><?= $prefix . $sym . number_format($txn['amount'], 2) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="text-align:center;color:var(--muted);padding:24px 0">No recent transactions</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── CUSTOMER / SUB HEALTH ── -->
    <div>
      <div class="sa-shdr">
        <h2>Customer &amp; Subscription Health</h2>
      </div>
      <div class="sa-stat-grid4">
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val" style="color:var(--amber)"><?= round($churn_rate, 1) ?>%</div>
              <div class="sa-stat-name">Churn Rate (MoM)</div>
            </div>
            <div class="sa-stat-icon si-amber">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">Month-over-month change</div>
        </div>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val" style="color:var(--red)"><?= $revenue_at_risk['count'] ?? 0 ?></div>
              <div class="sa-stat-name">Expiring in 30 Days</div>
            </div>
            <div class="sa-stat-icon si-red">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">Revenue at risk: $<?= number_format($revenue_at_risk['total_value'] ?? 0, 0) ?></div>
        </div>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val">$<?= number_format($subscription_metrics['avg_value'] ?? 0, 0) ?></div>
              <div class="sa-stat-name">Avg Subscription Value</div>
            </div>
            <div class="sa-stat-icon si-green">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">Min $<?= number_format($subscription_metrics['min_value'] ?? 0, 0) ?> · Max $<?= number_format($subscription_metrics['max_value'] ?? 0, 0) ?></div>
        </div>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val"><?= round($demo_conversion['conversion_rate'] ?? 0, 1) ?>%</div>
              <div class="sa-stat-name">Demo Conversion</div>
            </div>
            <div class="sa-stat-icon si-blue">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot"><?= $demo_conversion['total_requests'] ?? 0 ?> requests · <?= $demo_conversion['completed'] ?? 0 ?> converted</div>
        </div>
      </div>
    </div>

    <!-- ── TENANT HEALTH ── -->
    <div>
      <div class="sa-shdr">
        <h2>Tenant Health &amp; Engagement — Last 30 Days</h2>
        <a href="manage_tenants.php">Manage Tenants →</a>
      </div>

      <!-- Engagement Summary Bar -->
      <div class="eng-grid">
        <div class="eng-item">
          <div class="eng-val" style="color:var(--green)"><?= $eng_counts['Very High'] ?></div>
          <div class="eng-label">Very High</div>
        </div>
        <div class="eng-item">
          <div class="eng-val" style="color:var(--blue)"><?= $eng_counts['High'] ?></div>
          <div class="eng-label">High</div>
        </div>
        <div class="eng-item">
          <div class="eng-val" style="color:var(--amber)"><?= $eng_counts['Medium'] ?></div>
          <div class="eng-label">Medium</div>
        </div>
        <div class="eng-item">
          <div class="eng-val" style="color:var(--muted)"><?= $eng_counts['Low'] ?></div>
          <div class="eng-label">Low</div>
        </div>
        <div class="eng-item">
          <div class="eng-val" style="color:var(--red)"><?= $eng_counts['Inactive'] ?></div>
          <div class="eng-label">Inactive</div>
        </div>
      </div>

      <!-- Top Active Tenant Cards -->
      <div class="sa-3col">
        <?php foreach (array_slice($most_active_tenants, 0, 6) as $tenant):
          $act   = intval($tenant['activity_count']);
          $actPct= $max_activity > 0 ? min(100, round($act / $max_activity * 100)) : 0;
          $users = intval($tenant['active_users']);
          $total_u = max(1, $users);
          // find login rate from engagement data
          $loginRate = 0;
          foreach ($tenant_engagement_score as $te) {
            if ($te['id'] == $tenant['id']) {
              $loginRate = floatval($te['login_rate_percent'] ?? 0);
              $engLevel  = $te['engagement_level'] ?? 'Low';
              $subStatus = $te['subscription_status'] ?? null;
              break;
            }
          }
          $engLevel  = $engLevel ?? 'Low';
          $subStatus = $subStatus ?? null;
          $eng_pill_cls = match($engLevel) {
            'Very High' => 'pill-green',
            'High'      => 'pill-blue',
            'Medium'    => 'pill-amber',
            'Low'       => 'pill-muted',
            default     => 'pill-red'
          };
          $barColor = match($engLevel) {
            'Very High' => 'var(--green)',
            'High'      => 'var(--blue)',
            'Medium'    => 'var(--amber)',
            default     => 'var(--red)'
          };
          // find feature adoption
          $features = 0;
          foreach ($tenant_feature_adoption as $tf) {
            if ($tf['id'] == $tenant['id']) {
              $features = (intval($tf['uses_chat']) > 0 ? 1 : 0)
                        + (intval($tf['uses_tickets']) > 0 ? 1 : 0)
                        + (intval($tf['uses_umrah']) > 0 ? 1 : 0)
                        + (intval($tf['uses_hotels']) > 0 ? 1 : 0)
                        + (intval($tf['uses_visas']) > 0 ? 1 : 0);
              break;
            }
          }
        ?>
        <div class="tc">
          <div class="tc-top">
            <div>
              <div class="tc-name"><?= htmlspecialchars($tenant['tenant_name']) ?></div>
              <div class="tc-meta"><?= $users ?> active users</div>
            </div>
            <span class="pill <?= $eng_pill_cls ?>"><?= htmlspecialchars($engLevel) ?></span>
          </div>
          <div class="minibar-wrap">
            <div class="minibar-row">
              <div class="minibar-lbl">Activities</div>
              <div class="minibar-track"><div class="minibar-fill" style="width:<?= $actPct ?>%;background:<?= $barColor ?>"></div></div>
              <div class="minibar-val"><?= $act ?></div>
            </div>
            <div class="minibar-row">
              <div class="minibar-lbl">Login rate</div>
              <div class="minibar-track"><div class="minibar-fill" style="width:<?= $loginRate ?>%;background:var(--blue)"></div></div>
              <div class="minibar-val"><?= $loginRate ?>%</div>
            </div>
            <div class="minibar-row">
              <div class="minibar-lbl">Features</div>
              <div class="minibar-track"><div class="minibar-fill" style="width:<?= $features * 20 ?>%;background:var(--accent2)"></div></div>
              <div class="minibar-val"><?= $features ?>/5</div>
            </div>
          </div>
          <div class="tc-foot">
            <div class="tc-stat">
              <div class="tc-stat-v" style="color:var(--green)">+<?= $tenant['records_created'] ?></div>
              <div class="tc-stat-k">Created</div>
            </div>
            <div class="tc-stat">
              <div class="tc-stat-v" style="color:var(--amber)">~<?= $tenant['records_updated'] ?></div>
              <div class="tc-stat-k">Updated</div>
            </div>
            <div class="tc-stat">
              <div class="tc-stat-v" style="color:var(--red)">-<?= $tenant['records_deleted'] ?></div>
              <div class="tc-stat-k">Deleted</div>
            </div>
            <span class="pill <?= $subStatus === 'active' ? 'pill-green' : 'pill-muted' ?>">
              <?= $subStatus ? ucfirst($subStatus) : 'No Sub' ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($most_active_tenants)): ?>
          <p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:24px 0">No activity data available</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── AT-RISK TENANTS ── -->
    <?php if (!empty($at_risk_tenants_phase1)): ?>
    <div>
      <div class="sa-shdr">
        <h2>⚠ At-Risk Tenants — Churn Prevention</h2>
        <div style="display:flex;gap:6px">
          <span class="pill pill-red"><?= $risk_counts['critical'] ?> Critical</span>
          <span class="pill pill-amber"><?= $risk_counts['high'] ?> High</span>
          <span class="pill pill-blue"><?= $risk_counts['medium'] ?> Medium</span>
        </div>
      </div>
      <div class="sa-3col">
        <?php foreach (array_slice($at_risk_tenants_phase1, 0, 9) as $rt):
          $score    = $rt['risk_score'];
          $circ     = 138; // 2π * r (r=22)
          $filled   = round(($score / 100) * $circ, 1);
          $ringColor = match($rt['risk_level']) {
            'critical' => 'var(--red)',
            'high'     => 'var(--amber)',
            'medium'   => 'var(--blue)',
            default    => 'var(--muted)'
          };
          $pillCls = match($rt['risk_level']) {
            'critical' => 'pill-red',
            'high'     => 'pill-amber',
            'medium'   => 'pill-blue',
            default    => 'pill-muted'
          };
          $tags = [];
          if ($rt['days_inactive'] > 14)   $tags[] = $rt['days_inactive'] . 'd inactive';
          if ($rt['activities_30d'] < 5)   $tags[] = 'low activity';
          if ($rt['adoption_rate'] < 30)   $tags[] = $rt['adoption_rate'] . '% adoption';
          if ($rt['users_active_7d'] == 0) $tags[] = 'no logins';
        ?>
        <div class="risk-card">
          <div class="risk-ring">
            <svg viewBox="0 0 52 52" width="50" height="50">
              <circle class="ring-bg" cx="26" cy="26" r="22"/>
              <circle class="ring-fill" cx="26" cy="26" r="22"
                stroke="<?= $ringColor ?>"
                stroke-dasharray="<?= $filled ?> <?= $circ ?>"
                stroke-dashoffset="0"/>
            </svg>
            <div class="ring-lbl" style="color:<?= $ringColor ?>"><?= $score ?></div>
          </div>
          <div class="risk-body">
            <div class="risk-name"><?= htmlspecialchars($rt['tenant_name']) ?></div>
            <div class="risk-why"><?= $rt['days_inactive'] ?>d inactive · <?= $rt['adoption_rate'] ?>% adoption</div>
            <div class="risk-tags">
              <?php foreach ($tags as $tag): ?>
              <span class="risk-tag"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
            <span class="pill <?= $pillCls ?>"><?= ucfirst($rt['risk_level']) ?></span>
            <a href="manage_tenants.php?id=<?= $rt['tenant_id'] ?>" class="sa-btn sa-btn-ghost" style="padding:4px 10px;font-size:.65rem">View</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="sa-card">
      <div class="sa-card-body" style="text-align:center;padding:28px;color:var(--green)">
        <div style="font-size:1.5rem;margin-bottom:6px">✓</div>
        <div style="font-weight:600">All tenants are healthy!</div>
        <div style="font-size:.75rem;color:var(--muted);margin-top:4px">No tenants with risk score ≥ 25</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── FEATURE ADOPTION HEATMAP ── -->
    <div class="sa-card">
      <div class="sa-card-hdr">
        <h3>Feature Adoption by Tenant — 30 Days</h3>
        <div class="legend">
          <div class="legend-item"><div class="legend-dot" style="background:var(--green)"></div>Used</div>
          <div class="legend-item"><div class="legend-dot" style="background:var(--surface2);border:1px solid var(--border)"></div>Not used</div>
        </div>
      </div>
      <div class="sa-card-body" style="overflow-x:auto">
        <?php if (!empty($tenant_feature_adoption)): ?>
        <table class="hm-table">
          <thead>
            <tr>
              <th style="text-align:left">Tenant</th>
              <th>💬 Chat</th>
              <th>✈ Tickets</th>
              <th>🕌 Umrah</th>
              <th>🏨 Hotels</th>
              <th>🛂 Visas</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tenant_feature_adoption as $tf): ?>
            <tr>
              <td class="row-lbl"><?= htmlspecialchars($tf['tenant_name']) ?></td>
              <td><div class="hm-cell <?= intval($tf['uses_chat']) > 0    ? 'hm-on' : 'hm-off' ?>"><?= intval($tf['uses_chat']) > 0    ? '✓' : '—' ?></div></td>
              <td><div class="hm-cell <?= intval($tf['uses_tickets']) > 0 ? 'hm-on' : 'hm-off' ?>"><?= intval($tf['uses_tickets']) > 0 ? '✓' : '—' ?></div></td>
              <td><div class="hm-cell <?= intval($tf['uses_umrah']) > 0   ? 'hm-on' : 'hm-off' ?>"><?= intval($tf['uses_umrah']) > 0   ? '✓' : '—' ?></div></td>
              <td><div class="hm-cell <?= intval($tf['uses_hotels']) > 0  ? 'hm-on' : 'hm-off' ?>"><?= intval($tf['uses_hotels']) > 0  ? '✓' : '—' ?></div></td>
              <td><div class="hm-cell <?= intval($tf['uses_visas']) > 0   ? 'hm-on' : 'hm-off' ?>"><?= intval($tf['uses_visas']) > 0   ? '✓' : '—' ?></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="text-align:center;color:var(--muted);padding:20px 0">No feature adoption data</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── OPERATIONS + SUPPORT ── -->
    <div>
      <div class="sa-shdr">
        <h2>Operations &amp; Support</h2>
        <a href="support_tickets_list.php">Manage Tickets →</a>
      </div>
      <div class="sa-stat-grid">
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val" style="color:var(--red)"><?= $open_tickets ?></div>
              <div class="sa-stat-name">Open Support Tickets</div>
            </div>
            <div class="sa-stat-icon si-red">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">
            <span class="pill pill-red">Open: <?= $ticket_status['open'] ?? 0 ?></span>
            <span class="pill pill-amber">Pending: <?= $ticket_status['pending'] ?? 0 ?></span>
          </div>
        </div>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val"><?= $total_users ?></div>
              <div class="sa-stat-name">Total Platform Users</div>
            </div>
            <div class="sa-stat-icon si-blue">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">
            <span><?= $currently_online ?> online now</span>
            <span>·</span>
            <span><?= $total_logins_7d ?> logins (7d)</span>
          </div>
        </div>
        <?php if (!empty($top_sales_agents)): $agent = $top_sales_agents[0]; ?>
        <div class="sa-stat">
          <div class="sa-stat-top">
            <div>
              <div class="sa-stat-val"><?= $agent['subscriptions_count'] ?></div>
              <div class="sa-stat-name">Top Agent Subscriptions</div>
            </div>
            <div class="sa-stat-icon si-green">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
          </div>
          <div class="sa-stat-foot">
            <span><?= htmlspecialchars($agent['name']) ?></span>
            <span>·</span>
            <span>$<?= number_format($agent['total_revenue'], 0) ?> revenue</span>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── ACTIVITY + AUDIT LOG ── -->
    <div class="sa-2col">
      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Tenant Growth — 6 Months</h3>
        </div>
        <div class="sa-card-body">
          <canvas id="growthChart" height="200"></canvas>
        </div>
      </div>
      <div class="sa-card">
        <div class="sa-card-hdr">
          <h3>Recent Activity</h3>
          <a href="audit_logs.php" style="font-size:.72rem;color:var(--accent2);text-decoration:none">View all →</a>
        </div>
        <div class="sa-card-body">
          <?php if (!empty($recent_audit_logs)): ?>
          <div class="timeline">
            <?php foreach ($recent_audit_logs as $log):
              $isCreate = strpos($log['action'], 'create') !== false;
            ?>
            <div class="tl-item">
              <div class="tl-dot <?= $isCreate ? 'create' : 'other' ?>">
                <?= $isCreate ? '+' : '✎' ?>
              </div>
              <div class="tl-box">
                <div class="tl-title"><?= htmlspecialchars($log['action']) ?> on <?= htmlspecialchars($log['entity_type']) ?> <span style="color:var(--muted)">#<?= htmlspecialchars($log['entity_id']) ?></span></div>
                <div class="tl-meta"><?= date('M d, Y H:i', strtotime($log['created_at'])) ?> · <?= htmlspecialchars(mb_strimwidth($log['details'] ?? '', 0, 60, '…')) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="text-align:center;color:var(--muted);padding:24px 0"><?= __('no_recent_activity') ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div><!-- /sa-content -->

<!-- ── PROFILE MODAL ── -->
<div class="modal fade" id="profileModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= __('user_profile') ?></h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body" style="text-align:center">
        <img src="<?= $imagePath ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);margin-bottom:12px" alt="Profile">
        <div style="font-weight:600;font-size:1rem"><?= htmlspecialchars($user['name']) ?></div>
        <div style="margin:4px 0"><span class="pill pill-purple"><?= htmlspecialchars($_SESSION['role']) ?></span></div>
        <div style="color:var(--muted);font-size:.78rem;margin-top:8px"><?= htmlspecialchars($user['email']) ?></div>
        <div style="color:var(--muted);font-size:.72rem;margin-top:4px">Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('close') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- ── SETTINGS MODAL ── -->
<div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="updateProfileForm" enctype="multipart/form-data" method="POST" action="update_profile.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= __('profile_settings') ?></h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div style="display:grid;grid-template-columns:140px 1fr;gap:24px;align-items:start">
            <div style="text-align:center">
              <img src="<?= $imagePath ?>" id="profilePreview" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);display:block;margin:0 auto 10px" alt="Preview">
              <label for="profileImage" style="cursor:pointer">
                <span class="sa-btn sa-btn-ghost" style="font-size:.7rem;padding:5px 12px">Change Photo</span>
              </label>
              <input type="file" id="profileImage" name="image" accept="image/*" style="display:none" onchange="previewImage(this)">
            </div>
            <div>
              <div style="margin-bottom:14px">
                <label class="form-label"><?= __('full_name') ?></label>
                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
              </div>
              <div style="margin-bottom:14px">
                <label class="form-label"><?= __('email_address') ?></label>
                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
              <div style="height:1px;background:var(--border);margin:16px 0"></div>
              <div style="font-size:.75rem;font-weight:700;color:var(--muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em"><?= __('change_password') ?></div>
              <div style="margin-bottom:14px">
                <label class="form-label"><?= __('current_password') ?></label>
                <input type="password" class="form-control" name="current_password">
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="form-label"><?= __('new_password') ?></label>
                  <input type="password" class="form-control" name="new_password">
                </div>
                <div>
                  <label class="form-label"><?= __('confirm_password') ?></label>
                  <input type="password" class="form-control" name="confirm_password">
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal"><?= __('cancel') ?></button>
          <button type="submit" class="sa-btn sa-btn-primary"><?= __('save_changes') ?></button>
        </div>
      </div>
    </form>
  </div>
        </div><!-- /.sa-wrap -->
      </div><!-- /.pcoded-inner-content -->
    </div><!-- /.pcoded-content -->
  </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<!-- Chart.js only (vendor-all + bootstrap + pcoded already loaded by header) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Live clock
(function tick() {
  const el = document.getElementById('live-clock');
  if (el) el.textContent = new Date().toLocaleTimeString('en-GB', { hour12: false });
  setTimeout(tick, 1000);
})();

// Profile image preview
function previewImage(input) {
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => document.getElementById('profilePreview').src = e.target.result;
    r.readAsDataURL(input.files[0]);
  }
}

// Chart defaults
Chart.defaults.color = '#6b7280';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
Chart.defaults.font.family = "'JetBrains Mono', monospace";
Chart.defaults.font.size = 11;

// Revenue vs Expenses (12m)
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($revenue_labels) ?>,
    datasets: [
      {
        label: 'Revenue',
        data: <?= json_encode($revenue_trend) ?>,
        backgroundColor: 'rgba(108,99,255,0.65)',
        borderColor: '#6c63ff',
        borderWidth: 1,
        borderRadius: 4,
        order: 2
      },
      {
        label: 'Expenses',
        data: <?= json_encode($expense_trend) ?>,
        type: 'line',
        borderColor: '#f43f5e',
        backgroundColor: 'rgba(244,63,94,0.07)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#f43f5e',
        order: 1
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { callback: v => '$' + (v / 1000).toFixed(0) + 'k' }
      },
      x: { grid: { display: false } }
    }
  }
});

// Subscription donut
new Chart(document.getElementById('subChart'), {
  type: 'doughnut',
  data: {
    labels: ['Active', 'Expired', 'Pending'],
    datasets: [{
      data: [<?= $sub_status_data['active'] ?>, <?= $sub_status_data['expired'] ?>, <?= $sub_status_data['pending'] ?>],
      backgroundColor: ['#22d3a0', '#f43f5e', '#f59e0b'],
      borderColor: '#151820',
      borderWidth: 3,
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
      legend: {
        position: 'right',
        labels: { usePointStyle: true, pointStyle: 'circle', padding: 16 }
      }
    }
  }
});

// Tenant Growth (6m)
new Chart(document.getElementById('growthChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($growth_months) ?>,
    datasets: [{
      label: 'New Tenants',
      data: <?= json_encode($tenant_growth) ?>,
      borderColor: '#a78bfa',
      backgroundColor: 'rgba(167,139,250,0.12)',
      fill: true,
      tension: 0.4,
      pointBackgroundColor: '#a78bfa',
      pointRadius: 4,
      pointHoverRadius: 6,
      borderWidth: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(255,255,255,0.04)' },
        ticks: { stepSize: 1 }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>