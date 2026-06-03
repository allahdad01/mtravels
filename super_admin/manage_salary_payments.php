<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout
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

// Handle salary payment creation/update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_salary_payment'])) {
    $agent_id = intval($_POST['agent_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $notes = $_POST['notes'] ?? '';

    if ($agent_id <= 0 || $amount <= 0) {
        $error = "Please provide valid agent and amount.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sales_agent_salary_payments 
                                   (sales_agent_id, amount, payment_date, status, notes, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$agent_id, $amount, $payment_date, $status, $notes]);
            $message = "Salary payment recorded successfully.";
            
            // Log action
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                   VALUES (?, 'create_salary_payment', 'salary_payment', ?, ?, ?, NOW())");
            $details = json_encode(['agent_id' => $agent_id, 'amount' => $amount, 'payment_date' => $payment_date]);
            $stmt->execute([$_SESSION['user_id'], $agent_id, $details, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            $error = "Error recording salary payment: " . $e->getMessage();
        }
    }
}

// Pagination
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$agent_filter = intval($_GET['agent'] ?? 0);
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($agent_filter > 0) {
    $where_conditions[] = "sp.sales_agent_id = ?";
    $params[] = $agent_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_query = "SELECT COUNT(*) as total FROM sales_agent_salary_payments sp " . $where_clause;
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch salary payments
$query = "SELECT sp.*, sa.name as agent_name FROM sales_agent_salary_payments sp 
          JOIN sales_agents sa ON sp.sales_agent_id = sa.id 
          $where_clause
          ORDER BY sp.payment_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$salary_payments = $stmt->fetchAll();

// Get summary stats
$summary_query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                    SUM(CASE WHEN status = 'processing' THEN amount ELSE 0 END) as processing,
                    COUNT(DISTINCT sales_agent_id) as agents_paid
                  FROM sales_agent_salary_payments 
                  WHERE status IN ('paid', 'processing')";
$stmt = $pdo->prepare($summary_query);
$stmt->execute();
$summary = $stmt->fetch();

// Get all agents with salary
$stmt = $pdo->prepare("SELECT id, name, salary_type FROM sales_agents WHERE salary_type IN ('salary', 'both') ORDER BY name ASC");
$stmt->execute();
$all_agents = $stmt->fetchAll();
?>

<?php include '../includes/header_super_admin.php'; ?>

<style>
/* ─── ROOT VARIABLES ─────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
}

/* ─── PAGE HEADER ────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #fff; border: none !important;
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
/* ─── METRIC CARDS ───────────────────────────────────────── */
.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 576px) { .metric-grid { grid-template-columns: 1fr; } }
.metric-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
.metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
.metric-card.blue::before { background: #4099ff; }
.metric-card.green::before { background: #2ed8b6; }
.metric-card.warning::before { background: #f59e0b; }
.metric-value { font-size: 1.75rem; font-weight: 800; color: #333; letter-spacing: -0.02em; }
.metric-label { font-size: 0.83rem; color: #999; margin-top: 8px; }
.section-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #999; margin-bottom: 12px; }

/* ─── CARDS ──────────────────────────────────────────────── */
.sa-card { background: white; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.sa-card-body { padding: 16px; }

/* ─── SEARCH FILTER ──────────────────────────────────────── */
.sa-search-filter { display: flex; }
.sa-search-group { display: flex; gap: 8px; width: 100%; flex-wrap: wrap; }
.sa-search-input {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    flex: 1;
    min-width: 150px;
}
.sa-search-input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 3px rgba(64,153,255,0.1); }

/* ─── SECTION HEADER ─────────────────────────────────────── */
.sa-shdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.sa-shdr h2 { font-size: 1.25rem; font-weight: 600; margin: 0; color: #333; }
.sa-shdr p { margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted); }

/* ─── BUTTONS ────────────────────────────────────────────── */
.sa-btn {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    border: 1px solid;
}
.sa-btn:hover { transform: translateY(-1px); }
.sa-btn-primary { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); border-color: transparent; color: white; }
.sa-btn-ghost { background: transparent; border-color: var(--border); color: #333; }
.sa-btn-ghost:hover { background: #f5f5f5; border-color: #999; }
.sa-btn-small { padding: 6px 12px; font-size: 0.75rem; }
.sa-td-actions { display: flex; gap: 6px; white-space: nowrap; }
.sa-btn-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #f8f9fa;
    cursor: pointer;
    display: inline-flex;
    align-items: center; justify-content: center;
    transition: all 0.2s;
}
.sa-btn-icon:hover { background: rgba(64,153,255,0.1); border-color: #4099ff; }
.sa-btn-icon svg { width: 16px; height: 16px; stroke: #666; }
.sa-btn-icon:hover svg { stroke: #4099ff; }

/* ─── TABLE ──────────────────────────────────────────────── */
.sa-table-wrap {
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--border);
    overflow-x: auto;
}
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 600px; }
.sa-table thead { background: #f8f9fa; }
.sa-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #666;
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sa-table td { padding: 12px 16px; border-bottom: 1px solid #eee; vertical-align: middle; }
.sa-table tbody tr:hover { background: #f8f9fa; }
.sa-table tbody tr:last-child td { border-bottom: none; }

/* ─── PILLS ──────────────────────────────────────────────── */
.pill { display: inline-block; font-size: 0.7rem; font-weight: 600; padding: 2px 9px; border-radius: 20px; }
.pill-paid { background: rgba(16,185,129,0.12); color: #10b981; }
.pill-processing { background: rgba(245,158,11,0.12); color: #f59e0b; }

/* ─── PAGINATION ─────────────────────────────────────────── */
.sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    flex-wrap: wrap;
}
.sa-pagination-item {
    min-width: 36px; height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center; justify-content: center;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}
.sa-pagination-item:hover:not(.active):not(.disabled) { background: rgba(64,153,255,0.1); border-color: #4099ff; color: #4099ff; }
.sa-pagination-item.active { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); border-color: #4099ff; color: white; }
.sa-pagination-ellipsis { color: #999; font-size: 0.8rem; }
.sa-pagination-info { font-size: 0.8rem; color: #999; margin-left: auto; }
.sa-page-btn {
    background: none;
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    padding: 6px 10px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #555;
    transition: all 0.2s;
}
.sa-page-btn:hover:not(:disabled) { background: rgba(64,153,255,0.1); border-color: #4099ff; color: #4099ff; }
.sa-page-btn:disabled { opacity: 0.4; cursor: default; }
.sa-page-btn svg { width: 16px; height: 16px; }

/* ─── MODAL OVERLAY ──────────────────────────────────────── */
.sa-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 20px;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: #fff;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalIn 0.2s ease;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes modalIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.sa-modal-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-radius: 12px 12px 0 0;
    position: sticky;
    top: 0;
    z-index: 1;
}
.sa-modal-header h5 { margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.sa-modal-close { background: none; border: none; color: #fff; cursor: pointer; padding: 4px; opacity: 0.8; display: flex; }
.sa-modal-close:hover { opacity: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ─── ALERT ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
.sa-alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #065f46; }
.sa-alert-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #991b1b; }
.sa-alert-close { margin-left: auto; background: none; border: none; cursor: pointer; display: flex; padding: 2px; opacity: 0.5; }
.sa-alert-close:hover { opacity: 0.8; }

/* ─── FORM ───────────────────────────────────────────────── */
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #333; margin-bottom: 6px; }
.sa-form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: inherit;
    box-sizing: border-box;
}
.sa-form-control:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 3px rgba(64,153,255,0.15); }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .page-header.card { padding: 1.5rem; }
    .page-header.card h5 { font-size: 1.25rem; }
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                Salary Payments
                            </h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage sales agent salary payments</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="sa-btn" onclick="showModal('addPaymentModal')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Record Payment
                            </button>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->

                        <?php if (!empty($message)): ?>
                        <div class="sa-alert sa-alert-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Summary Stats -->
                        <p class="section-label">Summary</p>
                        <div class="metric-grid">
                            <div class="metric-card blue">
                                <div class="metric-value">$<?= number_format($summary['total_paid'] ?? 0, 2) ?></div>
                                <div class="metric-label">Total Paid</div>
                            </div>
                            <div class="metric-card warning">
                                <div class="metric-value">$<?= number_format($summary['processing'] ?? 0, 2) ?></div>
                                <div class="metric-label">Processing</div>
                            </div>
                            <div class="metric-card green">
                                <div class="metric-value"><?= $summary['total_payments'] ?? 0 ?></div>
                                <div class="metric-label">Total Payments</div>
                            </div>
                            <div class="metric-card blue">
                                <div class="metric-value"><?= $summary['agents_paid'] ?? 0 ?></div>
                                <div class="metric-label">Agents Paid</div>
                            </div>
                        </div>

                        <!-- Salary Payments Header -->
                        <div class="sa-shdr">
                            <div>
                                <h2>All Payments</h2>
                                <p>Total: <?= $total_items ?> payments</p>
                            </div>
                        </div>

                        <!-- Filter Bar -->
                        <div class="sa-card" style="margin-bottom: 20px;">
                            <div class="sa-card-body">
                                <form method="GET" action="manage_salary_payments.php" class="sa-search-filter">
                                    <div class="sa-search-group">
                                        <select class="sa-search-input" name="agent" style="flex: 0 0 auto;" onchange="this.form.submit()">
                                            <option value="">All Agents</option>
                                            <?php foreach ($all_agents as $agent): ?>
                                            <option value="<?= $agent['id'] ?>" <?= $agent_filter == $agent['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($agent['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="sa-search-input" name="status" style="flex: 0 0 auto;" onchange="this.form.submit()">
                                            <option value="">All Status</option>
                                            <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                                            <option value="processing" <?= $status_filter == 'processing' ? 'selected' : '' ?>>Processing</option>
                                        </select>
                                        <?php if ($agent_filter > 0 || !empty($status_filter)): ?>
                                        <a href="manage_salary_payments.php" class="sa-btn sa-btn-ghost">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Salary Payments Table -->
                        <?php if (empty($salary_payments)): ?>
                        <div class="sa-card">
                            <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 12px; opacity: 0.4;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                <div style="font-weight: 600; margin-bottom: 4px;">No Salary Payments Found</div>
                                <div style="font-size: 0.8rem;"><?= !empty($agent_filter) || !empty($status_filter) ? 'Try adjusting your filters.' : 'No salary payments recorded yet.' ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salary_payments as $payment): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($payment['agent_name']) ?></strong></td>
                                        <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td><strong style="color: #2ed8b6;">$<?= number_format($payment['amount'], 2) ?></strong></td>
                                        <td><span class="pill pill-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span></td>
                                        <td style="color: var(--muted);"><?= $payment['notes'] ? htmlspecialchars($payment['notes']) : '—' ?></td>
                                        <td>
                                            <div class="sa-td-actions">
                                                <a href="edit_salary_payment.php?id=<?= $payment['id'] ?>" class="sa-btn-icon" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="sa-pagination">
                            <?php 
                            $q = '';
                            if ($agent_filter > 0) $q .= '&agent=' . $agent_filter;
                            if (!empty($status_filter)) $q .= '&status=' . urlencode($status_filter);
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            ?>
                            <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=1<?= $q ?>'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page - 1 ?><?= $q ?>'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <?php if ($start_page > 1): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i ?><?= $q ?>" class="sa-pagination-item <?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($end_page < $total_pages): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page + 1 ?><?= $q ?>'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $total_pages ?><?= $q ?>'">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </button>
                            <span class="sa-pagination-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="sa-modal-overlay" id="addPaymentModal">
    <div class="sa-modal">
        <div class="sa-modal-header">
            <h5>Record Salary Payment</h5>
            <button type="button" class="sa-modal-close" onclick="closeModal('addPaymentModal')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" action="">
            <div class="sa-modal-body">
                <div class="sa-form-group">
                    <label for="agent_id">Sales Agent *</label>
                    <select id="agent_id" name="agent_id" class="sa-form-control" required>
                        <option value="">-- Select Agent --</option>
                        <?php foreach ($all_agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sa-form-group">
                    <label for="amount">Amount *</label>
                    <input type="number" id="amount" name="amount" class="sa-form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <div class="sa-form-group">
                    <label for="payment_date">Payment Date *</label>
                    <input type="date" id="payment_date" name="payment_date" class="sa-form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="sa-form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" class="sa-form-control" required>
                        <option value="paid">Paid</option>
                        <option value="processing">Processing</option>
                    </select>
                </div>
                <div class="sa-form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="sa-form-control" rows="3" placeholder="Add any notes..."></textarea>
                </div>
                <input type="hidden" name="add_salary_payment" value="1">
                <button type="submit" class="sa-btn sa-btn-primary" style="width: 100%; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Record Payment
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
function showModal(id) { document.getElementById(id).classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = ''; }
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) { if (e.target === this) { this.classList.remove('active'); document.body.style.overflow = ''; } });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sa-modal-overlay.active').forEach(function(el) { el.classList.remove('active'); document.body.style.overflow = ''; });
    }
});
</script>
</body>
</html>
