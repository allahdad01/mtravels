<?php
/**
 * Support Tickets - Admin Interface
 *
 * Manage and track support tickets for the tenant.
 */

require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';
require_once '../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'] ?? 0;
$user_role = $_SESSION['role'];

// Check role access
if (!in_array($user_role, ['admin', 'finance', 'sales', 'umrah'])) {
    header('Location: ../access_denied.php');
    exit();
}

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($category_filter && is_numeric($category_filter)) {
    $filters['category_id'] = intval($category_filter);
}
if ($priority_filter && in_array($priority_filter, ['low', 'medium', 'high', 'critical'])) {
    $filters['priority'] = $priority_filter;
}

// Get tickets
$tickets = $ticketManager->getTicketsByTenant($tenant_id, $filters);
$categories = $ticketManager->getCategories();
$stats = $ticketManager->getStatistics($tenant_id);

// Update SLA displays
foreach ($tickets as &$ticket) {
    $ticket['sla_display'] = $slaCalculator->getSLADisplay($ticket);
}

$pageTitle = __('support_tickets');
require_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #0d0f12;
        --surface:   #f4f3ef;
        --card-bg:   #ffffff;
        --border:    #e3e1db;
        --muted:     #8a8880;
        --accent:    #e8533a;
        --accent-2:  #f5a623;
        --accent-3:  #2db899;
        --accent-4:  #4a7cf7;
        --critical:  #e8533a;
        --high:      #f5a623;
        --medium:    #4a7cf7;
        --low:       #b0b0b0;

        --radius-sm: 6px;
        --radius-md: 12px;
        --radius-lg: 20px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.07);
        --shadow-md: 0 4px 16px rgba(0,0,0,.09);
    }

    body, .pcoded-main-container {
        background: var(--surface) !important;
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── PAGE SHELL ── */
    .st-shell {
        max-width: 1400px;
        margin: 0 auto;
        padding: 32px 28px 60px;
    }

    /* ── TOP BAR ── */
    .st-topbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 36px;
        gap: 16px;
        flex-wrap: wrap;
    }
    .st-topbar-left {}
    .st-eyebrow {
        font-family: 'DM Sans', sans-serif;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 4px;
    }
    .st-title {
        font-family: 'Syne', sans-serif;
        font-size: 34px;
        font-weight: 800;
        line-height: 1;
        color: var(--ink);
        margin: 0;
    }
    .st-topbar-right {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .st-btn-back {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        border: 1.5px solid var(--border);
        background: var(--card-bg);
        color: var(--muted);
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: border-color .18s, color .18s, box-shadow .18s;
    }
    .st-btn-back:hover {
        border-color: var(--ink);
        color: var(--ink);
        box-shadow: var(--shadow-sm);
    }
    .st-btn-create {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 22px;
        border-radius: var(--radius-sm);
        background: var(--ink);
        color: #fff;
        font-family: 'Syne', sans-serif;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        letter-spacing: .03em;
        transition: background .18s, transform .15s, box-shadow .15s;
        border: none;
    }
    .st-btn-create:hover {
        background: #1f2329;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(0,0,0,.18);
        color: #fff;
    }

    /* ── STATS STRIP ── */
    .st-stats {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 28px;
    }
    @media (max-width: 1100px) { .st-stats { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px)  { .st-stats { grid-template-columns: repeat(2, 1fr); } }

    .st-stat {
        background: var(--card-bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-md);
        padding: 20px 18px 16px;
        position: relative;
        overflow: hidden;
        transition: transform .18s, box-shadow .18s;
    }
    .st-stat:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .st-stat-bar {
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 3px 3px 0 0;
    }
    .st-stat-num {
        font-family: 'Syne', sans-serif;
        font-size: 32px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 4px;
    }
    .st-stat-label {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }
    .st-stat-icon {
        position: absolute;
        right: 14px; top: 18px;
        font-size: 20px;
        opacity: .12;
    }

    .stat-total    .st-stat-bar { background: var(--ink); }
    .stat-total    .st-stat-num { color: var(--ink); }
    .stat-open     .st-stat-bar { background: var(--accent-4); }
    .stat-open     .st-stat-num { color: var(--accent-4); }
    .stat-progress .st-stat-bar { background: var(--accent-2); }
    .stat-progress .st-stat-num { color: var(--accent-2); }
    .stat-resolved .st-stat-bar { background: var(--accent-3); }
    .stat-resolved .st-stat-num { color: var(--accent-3); }
    .stat-breached .st-stat-bar { background: var(--critical); }
    .stat-breached .st-stat-num { color: var(--critical); }
    .stat-risk     .st-stat-bar { background: var(--high); }
    .stat-risk     .st-stat-num { color: var(--high); }

    /* ── FILTER ROW ── */
    .st-filters {
        background: var(--card-bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-md);
        padding: 18px 22px;
        margin-bottom: 16px;
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    .st-filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        flex: 1;
        min-width: 140px;
    }
    .st-filter-group label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
        margin: 0;
    }
    .st-select {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 9px 32px 9px 12px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--ink);
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238a8880' fill='none' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 12px center;
        appearance: none;
        -webkit-appearance: none;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
        width: 100%;
    }
    .st-select:focus {
        outline: none;
        border-color: var(--ink);
        box-shadow: 0 0 0 3px rgba(13,15,18,.08);
    }
    .st-btn-reset {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        border-radius: var(--radius-sm);
        border: 1.5px solid var(--border);
        background: var(--surface);
        color: var(--muted);
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition: all .15s;
        align-self: flex-end;
    }
    .st-btn-reset:hover {
        border-color: var(--ink);
        color: var(--ink);
        background: #fff;
    }

    /* ── TABLE CARD ── */
    .st-table-card {
        background: var(--card-bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    .st-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        border-bottom: 1.5px solid var(--border);
    }
    .st-table-head-title {
        font-family: 'Syne', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: var(--ink);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }
    .st-count-badge {
        background: var(--ink);
        color: #fff;
        font-family: 'DM Sans', sans-serif;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 9px;
        border-radius: 20px;
        letter-spacing: .03em;
    }

    .st-table {
        width: 100%;
        border-collapse: collapse;
    }
    .st-table thead tr {
        background: var(--surface);
        border-bottom: 1.5px solid var(--border);
    }
    .st-table thead th {
        padding: 11px 16px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--muted);
        white-space: nowrap;
        border: none;
    }
    .st-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .12s;
    }
    .st-table tbody tr:last-child { border-bottom: none; }
    .st-table tbody tr:hover { background: #faf9f6; }
    .st-table tbody td {
        padding: 14px 16px;
        font-size: 13.5px;
        vertical-align: middle;
        border: none;
    }

    .st-ticket-num {
        font-family: 'Syne', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: .02em;
    }
    .st-ticket-title {
        color: var(--ink);
        font-weight: 400;
        max-width: 260px;
    }

    /* ── CHIPS / BADGES ── */
    .chip {
        display: inline-block;
        padding: 4px 11px;
        border-radius: 4px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: .02em;
        white-space: nowrap;
    }

    /* Category chip */
    .chip-cat {
        background: #f0edff;
        color: #5b45d4;
    }

    /* Priority chips */
    .chip-critical { background: #fde8e4; color: #c0392b; }
    .chip-high     { background: #fef3cd; color: #9a6b00; }
    .chip-medium   { background: #ddeeff; color: #1a5fb4; }
    .chip-low      { background: #ebebeb; color: #666; }

    /* Status chips */
    .chip-open        { background: #ddeeff; color: #1a5fb4; }
    .chip-in_progress { background: #fef3cd; color: #9a6b00; }
    .chip-resolved    { background: #d6f5ec; color: #1a7a5b; }
    .chip-closed      { background: #ebebeb; color: #555; }

    /* SLA chips */
    .chip-success  { background: #d6f5ec; color: #1a7a5b; }
    .chip-warning  { background: #fef3cd; color: #9a6b00; }
    .chip-danger   { background: #fde8e4; color: #c0392b; }
    .chip-secondary{ background: #ebebeb; color: #666; }
    .chip-info     { background: #ddeeff; color: #1a5fb4; }

    .st-date { color: var(--muted); font-size: 12.5px; }

    /* View button */
    .st-btn-view {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: var(--radius-sm);
        border: 1.5px solid var(--border);
        background: transparent;
        color: var(--ink);
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        transition: all .15s;
        white-space: nowrap;
    }
    .st-btn-view:hover {
        background: var(--ink);
        border-color: var(--ink);
        color: #fff;
    }

    /* Empty state */
    .st-empty {
        padding: 60px 24px;
        text-align: center;
        color: var(--muted);
    }
    .st-empty-icon {
        font-size: 40px;
        display: block;
        margin-bottom: 12px;
        opacity: .4;
    }
    .st-empty-text {
        font-size: 14px;
        font-weight: 500;
    }

    /* Responsive table */
    .st-table-wrap { overflow-x: auto; }
</style>

<div class="pcoded-main-container">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="st-shell">

                            <!-- Top Bar -->
                            <div class="st-topbar">
                                <div class="st-topbar-left">
                                    <p class="st-eyebrow">Admin &rsaquo; Help Desk</p>
                                    <h1 class="st-title"><?php echo __('support_tickets'); ?></h1>
                                </div>
                                <div class="st-topbar-right">
                                    <a href="dashboard.php" class="st-btn-back">
                                        <i class="feather icon-arrow-left"></i> <?php echo __('back_to_dashboard'); ?>
                                    </a>
                                    <a href="support_ticket_create.php" class="st-btn-create">
                                        <i class="feather icon-plus"></i> <?php echo __('create_new_ticket'); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Stats Strip -->
                            <div class="st-stats">
                                <div class="st-stat stat-total">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['total'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('total'); ?></div>
                                    <span class="st-stat-icon feather icon-bar-chart-2"></span>
                                </div>
                                <div class="st-stat stat-open">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['open'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('open'); ?></div>
                                    <span class="st-stat-icon feather icon-circle"></span>
                                </div>
                                <div class="st-stat stat-progress">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['in_progress'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('in_progress'); ?></div>
                                    <span class="st-stat-icon feather icon-clock"></span>
                                </div>
                                <div class="st-stat stat-resolved">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['resolved'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('resolved'); ?></div>
                                    <span class="st-stat-icon feather icon-check-circle"></span>
                                </div>
                                <div class="st-stat stat-breached">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['breached'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('sla_breached'); ?></div>
                                    <span class="st-stat-icon feather icon-alert-triangle"></span>
                                </div>
                                <div class="st-stat stat-risk">
                                    <div class="st-stat-bar"></div>
                                    <div class="st-stat-num"><?php echo $stats['at_risk'] ?? 0; ?></div>
                                    <div class="st-stat-label"><?php echo __('at_risk'); ?></div>
                                    <span class="st-stat-icon feather icon-alert-circle"></span>
                                </div>
                            </div>

                            <!-- Filters -->
                            <form method="GET">
                                <div class="st-filters">
                                    <div class="st-filter-group">
                                        <label><?php echo __('status'); ?></label>
                                        <select name="status" class="st-select" onchange="this.form.submit()">
                                            <option value=""><?php echo __('all_status'); ?></option>
                                            <option value="open"        <?php echo $status_filter === 'open'        ? 'selected' : ''; ?>><?php echo __('open'); ?></option>
                                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>><?php echo __('in_progress'); ?></option>
                                            <option value="resolved"    <?php echo $status_filter === 'resolved'    ? 'selected' : ''; ?>><?php echo __('resolved'); ?></option>
                                            <option value="closed"      <?php echo $status_filter === 'closed'      ? 'selected' : ''; ?>><?php echo __('closed'); ?></option>
                                        </select>
                                    </div>
                                    <div class="st-filter-group">
                                        <label><?php echo __('category'); ?></label>
                                        <select name="category" class="st-select" onchange="this.form.submit()">
                                            <option value=""><?php echo __('all_categories'); ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="st-filter-group">
                                        <label><?php echo __('priority'); ?></label>
                                        <select name="priority" class="st-select" onchange="this.form.submit()">
                                            <option value=""><?php echo __('all_priorities'); ?></option>
                                            <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>><?php echo __('critical'); ?></option>
                                            <option value="high"     <?php echo $priority_filter === 'high'     ? 'selected' : ''; ?>><?php echo __('high'); ?></option>
                                            <option value="medium"   <?php echo $priority_filter === 'medium'   ? 'selected' : ''; ?>><?php echo __('medium'); ?></option>
                                            <option value="low"      <?php echo $priority_filter === 'low'      ? 'selected' : ''; ?>><?php echo __('low'); ?></option>
                                        </select>
                                    </div>
                                    <a href="support_tickets.php" class="st-btn-reset">
                                        <i class="feather icon-refresh-cw"></i> <?php echo __('reset_filters'); ?>
                                    </a>
                                </div>
                            </form>

                            <!-- Table Card -->
                            <div class="st-table-card">
                                <div class="st-table-head">
                                    <h5 class="st-table-head-title">
                                        <i class="feather icon-list" style="opacity:.5"></i>
                                        <?php echo __('support_tickets'); ?>
                                        <span class="st-count-badge"><?php echo count($tickets); ?></span>
                                    </h5>
                                </div>
                                <div class="st-table-wrap">
                                    <table class="st-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo __('ticket_number'); ?></th>
                                                <th><?php echo __('title'); ?></th>
                                                <th><?php echo __('category'); ?></th>
                                                <th><?php echo __('priority'); ?></th>
                                                <th><?php echo __('status'); ?></th>
                                                <th><?php echo __('sla_status'); ?></th>
                                                <th><?php echo __('created'); ?></th>
                                                <th><?php echo __('action'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($tickets)): ?>
                                                <tr>
                                                    <td colspan="8">
                                                        <div class="st-empty">
                                                            <i class="feather icon-inbox st-empty-icon"></i>
                                                            <p class="st-empty-text"><?php echo __('no_tickets_found'); ?></p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($tickets as $ticket): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="st-ticket-num"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="st-ticket-title"><?php echo htmlspecialchars(substr($ticket['title'], 0, 48)); ?><?php echo strlen($ticket['title']) > 48 ? '…' : ''; ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="chip chip-cat"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="chip chip-<?php echo $ticket['priority']; ?>">
                                                                <?php echo ucfirst($ticket['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="chip chip-<?php echo $ticket['status']; ?>">
                                                                <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="chip chip-<?php echo $ticket['sla_display']['color']; ?>">
                                                                <?php echo $ticket['sla_display']['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="st-date"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="support_ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="st-btn-view">
                                                                <i class="feather icon-eye"></i> <?php echo __('view'); ?>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div><!-- /st-shell -->
                    </div>
                </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>