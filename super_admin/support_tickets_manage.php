<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../access_denied.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$sla_status_filter = $_GET['sla_status'] ?? '';
$tenant_filter = $_GET['tenant'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($sla_status_filter && in_array($sla_status_filter, ['on_track', 'at_risk', 'breached', 'resolved'])) {
    $filters['sla_status'] = $sla_status_filter;
}
if ($tenant_filter && is_numeric($tenant_filter)) {
    $filters['tenant_id'] = intval($tenant_filter);
}
if ($priority_filter && in_array($priority_filter, ['low', 'medium', 'high', 'critical'])) {
    $filters['priority'] = $priority_filter;
}

// Get all tickets
$tickets = $ticketManager->getAllTickets($filters);

// Get tenants for dropdown
$tenantStmt = $pdo->query("SELECT id, name FROM tenants ORDER BY name");
$tenants = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall stats
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) as breached,
        SUM(CASE WHEN sla_status = 'at_risk' THEN 1 ELSE 0 END) as at_risk
    FROM support_tickets
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Update SLA displays
foreach ($tickets as &$ticket) {
    $ticket['sla_display'] = $slaCalculator->getSLADisplay($ticket);
}

$pageTitle = "Support Tickets Management";
require_once '../includes/header_super_admin.php';
?>

<style>
/* Enhanced custom styles for better layout and design */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.stats-card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
    height: 100%;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.stats-card .card-body {
    padding: 1.5rem;
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: #f1f3f4;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.btn {
    border-radius: 25px;
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.h3 {
    font-size: 2rem;
}

.h5 {
    font-size: 1.25rem;
}

.text-muted {
    font-size: 0.9rem;
}

/* ─── TICKET CARD STYLES ─────────────────────────────── */
.sa-ticket-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sa-ticket-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
}

.sa-ticket-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
}

.stc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.stc-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #333;
}

.stc-info .ticket-number {
    color: #4099ff;
    margin-right: 8px;
}

.stc-tenant {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.stc-badges {
    display: flex;
    gap: 8px;
}

.stc-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.stc-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stc-detail-label {
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
}

.stc-detail-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: #444;
}

.stc-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* ─── PILLS ────────────────────────────────────────────── */
.pill {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.pill-green {
    background: rgba(16,185,129,0.12);
    color: #10b981;
}

.pill-amber {
    background: rgba(245,158,11,0.12);
    color: #f59e0b;
}

.pill-red {
    background: rgba(239,68,68,0.12);
    color: #ef4444;
}

.pill-blue {
    background: rgba(59,130,246,0.12);
    color: #3b82f6;
}

.pill-gray {
    background: rgba(107,114,128,0.12);
    color: #6b7280;
}

/* ─── SECTION HEADER ───────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.sa-shdr h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.sa-shdr p {
    margin: 4px 0 0 0;
    font-size: 0.75rem;
    color: #999;
}

/* ─── CARDS ───────────────────────────────────────────── */
.sa-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.sa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.sa-card-body {
    padding: 1.5rem;
}

/* ─── BUTTONS ───────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.sa-btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}

.sa-btn-ghost:hover {
    background: #e8e8e8;
    border-color: #d0d0d0;
}

/* ─── SEARCH & FILTER ───────────────────────────────── */
.sa-search-filter {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.sa-search-group {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
    flex-wrap: wrap;
}

.sa-search-input {
    flex: 1;
    min-width: 120px;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* ─── RESPONSIVE ──────────────────────────────────────── */
@media (max-width: 768px) {
    .stc-header {
        flex-direction: column;
    }
    
    .stc-badges {
        margin-top: 12px;
    }
    
    .stc-details {
        grid-template-columns: 1fr;
    }
    
    .stc-actions {
        width: 100%;
    }
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="page-header card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Support Tickets Management</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <button onclick="location.reload()" class="btn btn-info btn-sm">
                            <i class="fas fa-sync"></i> Refresh SLA Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-list mr-1"></i>Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-info"><?php echo $stats['open'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-inbox mr-1"></i>Open</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-warning"><?php echo $stats['in_progress'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-clock mr-1"></i>In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-success"><?php echo $stats['resolved'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-check-circle mr-1"></i>Resolved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-danger"><?php echo $stats['breached'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-alert-triangle mr-1"></i>SLA Breached</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <h3 class="text-danger"><?php echo $stats['at_risk'] ?? 0; ?></h3>
                            <p class="text-muted mb-0"><i class="feather icon-alert-circle mr-1"></i>At Risk</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-body">
                    <form method="GET" class="sa-search-filter">
                        <div class="sa-search-group">
                            <select name="status" class="sa-search-input" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                            <select name="sla_status" class="sa-search-input" onchange="this.form.submit()">
                                <option value="">All SLA</option>
                                <option value="on_track" <?php echo $sla_status_filter === 'on_track' ? 'selected' : ''; ?>>On Track</option>
                                <option value="at_risk" <?php echo $sla_status_filter === 'at_risk' ? 'selected' : ''; ?>>At Risk</option>
                                <option value="breached" <?php echo $sla_status_filter === 'breached' ? 'selected' : ''; ?>>Breached</option>
                            </select>
                            <select name="priority" class="sa-search-input" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                            <select name="tenant" class="sa-search-input" onchange="this.form.submit()">
                                <option value="">All Tenants</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>" <?php echo $tenant_filter == $tenant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="support_tickets_manage.php" class="sa-btn sa-btn-ghost"><i class="feather icon-refresh-cw mr-1"></i>Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tickets Header -->
            <div class="sa-shdr" style="margin-bottom: 16px;">
                <div>
                    <h2>Support Tickets</h2>
                    <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Manage and view all support tickets</p>
                </div>
            </div>

            <!-- Tickets Cards -->
            <?php if (empty($tickets)): ?>
            <div class="sa-card">
                <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                    <div style="font-size: 2rem; margin-bottom: 12px;">🎫</div>
                    <div style="font-weight: 600; margin-bottom: 4px;">No Tickets Found</div>
                    <div style="font-size: 0.8rem;">No support tickets match your filters.</div>
                </div>
            </div>
            <?php else: ?>
            <div class="sa-ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                <div class="sa-ticket-card">
                    <div class="stc-header">
                        <div class="stc-info">
                            <h4>
                                <span class="ticket-number">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                <?php echo htmlspecialchars($ticket['title']); ?>
                            </h4>
                            <p class="stc-tenant">
                                <i class="feather icon-home"></i>
                                <?php echo htmlspecialchars($ticket['tenant_name']); ?>
                            </p>
                        </div>
                        <div class="stc-badges">
                            <span class="pill <?php 
                                echo $ticket['priority'] === 'critical' ? 'pill-red' : 
                                    ($ticket['priority'] === 'high' ? 'pill-amber' : 
                                    ($ticket['priority'] === 'medium' ? 'pill-blue' : 'pill-gray')); 
                            ?>">
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>
                            <span class="pill <?php 
                                echo $ticket['status'] === 'open' ? 'pill-blue' : 
                                    ($ticket['status'] === 'in_progress' ? 'pill-amber' : 
                                    ($ticket['status'] === 'resolved' ? 'pill-green' : 'pill-gray')); 
                            ?>">
                                <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="stc-details">
                        <div class="stc-detail-item">
                            <span class="stc-detail-label">SLA Status</span>
                            <span class="pill <?php echo 'pill-' . $ticket['sla_display']['color']; ?>">
                                <?php echo $ticket['sla_display']['status']; ?>
                            </span>
                        </div>
                        <div class="stc-detail-item">
                            <span class="stc-detail-label">Created</span>
                            <span class="stc-detail-value"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span>
                        </div>
                        <div class="stc-detail-item">
                            <span class="stc-detail-label">Time Left</span>
                            <span class="stc-detail-value"><?php echo $ticket['sla_display']['hours_remaining']; ?>h</span>
                        </div>
                    </div>
                    
                    <div class="stc-actions">
                        <a href="support_ticket_view.php?id=<?php echo $ticket['id']; ?>" class="sa-btn sa-btn-small sa-btn-primary">
                            <i class="feather icon-eye"></i> View
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>
