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

// Database connection
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';

$ticketManager = new SupportTicketManager($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$tenant_filter = $_GET['tenant_id'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($tenant_filter && is_numeric($tenant_filter)) {
    $filters['tenant_id'] = intval($tenant_filter);
}

// Get all tickets for super admin
$tickets = $ticketManager->getAllTickets($filters);
$stats = $ticketManager->getStatistics();

// Get list of tenants
try {
    $stmt = $pdo->query("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    $tenants = [];
}

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

/* ─── STATS GRID ─────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 4px solid var(--accent);
    border-radius: var(--radius);
    padding: 20px;
    text-align: center;
    transition: all .2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.stat-card.total { border-left-color: #6366f1; }
.stat-card.open { border-left-color: var(--red); }
.stat-card.in_progress { border-left-color: var(--amber); }
.stat-card.resolved { border-left-color: var(--green); }
.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 4px;
    font-family: 'JetBrains Mono', monospace;
}
.stat-label {
    font-size: 0.75rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

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

/* ─── FORM STYLES ────────────────────────────────────────── */
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

/* ─── TICKET CARD ───────────────────────────────────────── */
.ticket-entry {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--muted);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all .2s;
    display: flex;
    gap: 16px;
    align-items: center;
}
.ticket-entry:last-child { margin-bottom: 0; }
.ticket-entry:hover {
    border-left-color: var(--accent);
    background: rgba(108,99,255,.02);
}
.ticket-entry.open { border-left-color: var(--red); }
.ticket-entry.in_progress { border-left-color: var(--amber); }
.ticket-entry.resolved { border-left-color: var(--green); }
.ticket-entry.closed { border-left-color: var(--muted); }

.ticket-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--accent);
    min-width: 90px;
}
.ticket-content {
    flex: 1;
    min-width: 0;
}
.ticket-subject {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}
.ticket-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--muted);
}
.ticket-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.ticket-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.priority-badge, .status-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.priority-badge.urgent { background: rgba(239,68,68,.15); color: var(--red); }
.priority-badge.high { background: rgba(249,115,22,.15); color: var(--orange); }
.priority-badge.medium { background: rgba(245,158,11,.15); color: var(--amber); }
.priority-badge.low { background: rgba(16,185,129,.15); color: var(--green); }
.status-badge.open { background: rgba(239,68,68,.15); color: var(--red); }
.status-badge.in_progress { background: rgba(245,158,11,.15); color: var(--amber); }
.status-badge.resolved { background: rgba(16,185,129,.15); color: var(--green); }
.status-badge.closed { background: rgba(107,114,128,.15); color: var(--muted); }

.ticket-actions {
    display: flex;
    gap: 8px;
}
.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s;
    color: var(--muted);
    text-decoration: none;
}
.action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
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
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-grid { grid-template-columns: 1fr; }
    .ticket-entry { flex-direction: column; align-items: flex-start; }
    .ticket-meta { flex-direction: column; align-items: flex-start; gap: 8px; }
    .ticket-actions { width: 100%; justify-content: flex-end; margin-top: 8px; }
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Support Tickets Management</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#!">Support Tickets</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="sa-wrap">
            <div class="sa-content">

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                        <div class="stat-label">Total Tickets</div>
                    </div>
                    <div class="stat-card open">
                        <div class="stat-number"><?= $stats['open'] ?? 0 ?></div>
                        <div class="stat-label">Open</div>
                    </div>
                    <div class="stat-card in_progress">
                        <div class="stat-number"><?= $stats['in_progress'] ?? 0 ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card resolved">
                        <div class="stat-number"><?= $stats['resolved'] ?? 0 ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="sa-card">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-filter" style="margin-right:8px"></i>Filters</h3>
                    </div>
                    <div class="sa-card-body">
                        <form method="GET">
                            <div class="filter-grid">
                                <div class="form-group">
                                    <label class="form-label" for="status">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="closed" <?= $status_filter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="tenant_id">Tenant</label>
                                    <select class="form-control" id="tenant_id" name="tenant_id">
                                        <option value="">All Tenants</option>
                                        <?php foreach ($tenants as $tenant): ?>
                                            <option value="<?= $tenant['id'] ?>" <?= $tenant_filter === (string)$tenant['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tenant['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="sa-btn sa-btn-primary" style="width:100%; justify-content:center;">
                                        <i class="feather icon-filter"></i> Filter
                                    </button>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <a href="support_tickets_list.php" class="sa-btn sa-btn-ghost" style="width:100%; justify-content:center;">
                                        <i class="feather icon-x"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tickets List Card -->
                <div class="sa-card">
                    <div class="sa-card-hdr">
                        <h3><i class="feather icon-ticket" style="margin-right:8px"></i>Support Tickets</h3>
                    </div>
                    <div class="sa-card-body">
                        <?php if (empty($tickets)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="feather icon-inbox"></i></div>
                                <div class="empty-state-text">No support tickets found</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-entry <?= $ticket['status'] ?>">
                                <div class="ticket-id">#<?= htmlspecialchars($ticket['ticket_number']) ?></div>
                                <div class="ticket-content">
                                    <div class="ticket-subject"><?= htmlspecialchars($ticket['title'] ?? 'No Title') ?></div>
                                    <div class="ticket-meta">
                                <span><i class="feather icon-user"></i> <?= htmlspecialchars($ticket['created_by_name'] ?? 'Unknown') ?></span>
                                <span><i class="feather icon-building"></i> <?= htmlspecialchars($ticket['tenant_name'] ?? 'N/A') ?></span>
                                <span><i class="feather icon-folder"></i> <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?></span>
                                <span><i class="feather icon-calendar"></i> <?= date('M d, Y', strtotime($ticket['created_at'] ?? date('Y-m-d'))) ?></span>
                                    </div>
                                </div>
                                <div class="ticket-badges">
                                    <span class="priority-badge <?= $ticket['priority'] ?>"><?= ucfirst($ticket['priority']) ?></span>
                                    <span class="status-badge <?= $ticket['status'] ?>"><?= str_replace('_', ' ', ucfirst($ticket['status'])) ?></span>
                                </div>
                                <div class="ticket-actions">
                                    <a href="support_ticket_view.php?id=<?= $ticket['id'] ?>" class="action-btn" title="View">
                                        <i class="feather icon-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
