<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Handle messages from handlers
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch all expense categories
$categoriesQuery = "SELECT id, name, description FROM system_expense_categories ORDER BY name";
$categoriesStmt = $pdo->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter parameters
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-t');
$categoryFilter = $_GET['category'] ?? null;

// Build query
$expenseQuery = "SELECT se.*, sec.name as category_name, u.name as created_by_name 
                 FROM system_expenses se
                 LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
                 LEFT JOIN users u ON se.created_by = u.id
                 WHERE se.date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($categoryFilter) {
    $expenseQuery .= " AND se.category_id = ?";
    $params[] = $categoryFilter;
}

$expenseQuery .= " ORDER BY se.date DESC";
$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute($params);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary by currency
$totalAmount = 0;
$totalAmountByUSD = 0;
$totalAmountByAFS = 0;
$categoryTotals = [];
$currencies = [];

foreach ($expenses as $expense) {
    $totalAmount += $expense['amount'];
    
    // Track by currency
    if (!isset($currencies[$expense['currency']])) {
        $currencies[$expense['currency']] = 0;
    }
    $currencies[$expense['currency']] += $expense['amount'];
    
    if ($expense['currency'] === 'USD') {
        $totalAmountByUSD += $expense['amount'];
    } else {
        $totalAmountByAFS += $expense['amount'];
    }
    
    $catId = $expense['category_id'];
    if (!isset($categoryTotals[$catId])) {
        $categoryTotals[$catId] = ['name' => $expense['category_name'], 'total' => 0, 'count' => 0, 'currencies' => []];
    }
    $categoryTotals[$catId]['total'] += $expense['amount'];
    $categoryTotals[$catId]['count']++;
    
    if (!isset($categoryTotals[$catId]['currencies'][$expense['currency']])) {
        $categoryTotals[$catId]['currencies'][$expense['currency']] = 0;
    }
    $categoryTotals[$catId]['currencies'][$expense['currency']] += $expense['amount'];
}

// Calculate averages per currency
$usdCount = 0;
$afsCount = 0;
foreach ($expenses as $expense) {
    if ($expense['currency'] === 'USD') {
        $usdCount++;
    } else {
        $afsCount++;
    }
}
$avgUSD = $usdCount > 0 ? $totalAmountByUSD / $usdCount : 0;
$avgAFS = $afsCount > 0 ? $totalAmountByAFS / $afsCount : 0;
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

    /* ─── ALERTS ──────────────────────────────────────────────── */
    .sa-alert {
        display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
        border-radius: var(--radius); border: 1px solid var(--border);
        margin-bottom: 16px; animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .sa-alert-success { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .sa-alert-danger { background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    /* ─── METRIC CARDS ────────────────────────────────────────── */
    .metric-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:16px; }
    .metric-card {
        background:var(--surface); border-radius:12px; padding:20px; border:1px solid var(--border);
        border-left:4px solid var(--blue); box-shadow:0 2px 8px rgba(0,0,0,0.06); transition:all 0.2s;
    }
    .metric-card:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.1); }
    .metric-card.green { border-left-color:var(--green); }
    .metric-card.amber { border-left-color:var(--amber); }
    .metric-card.red { border-left-color:var(--red); }
    .metric-value { font-size:1.5rem; font-weight:700; margin-bottom:6px; display:flex; gap:8px; align-items:baseline; flex-wrap:wrap; }
    .metric-label { font-size:0.78rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.04em; }

    /* ─── SECTION LABEL ───────────────────────────────────────── */
    .section-label {
        font-size:0.82rem; font-weight:700; color:var(--muted); text-transform:uppercase;
        letter-spacing:0.05em; margin-bottom:10px;
    }

    /* ─── TOOLBAR ─────────────────────────────────────────────── */
    .sa-toolbar {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        padding:16px; margin-bottom:16px;
    }
    .sa-toolbar-form { display:flex; }
    .sa-toolbar-group { display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%; }
    .sa-toolbar-input {
        padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.85rem;
        background:var(--surface); color:var(--text); min-width:140px; flex:1;
    }
    .sa-toolbar-input:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }

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

    /* ─── BUTTONS ─────────────────────────────────────────────── */
    .sa-btn {
        display:inline-flex; align-items:center; padding:9px 18px; border-radius:8px;
        font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        border:none; text-decoration:none; gap:4px;
    }
    .sa-btn-sm { padding:6px 12px; font-size:0.8rem; }
    .sa-btn-primary { background:var(--grad); color:#fff; }
    .sa-btn-primary:hover { box-shadow:0 4px 12px rgba(64,153,255,0.35); transform:translateY(-1px); }
    .sa-btn-success { background:var(--green); color:#fff; }
    .sa-btn-success:hover { box-shadow:0 4px 12px rgba(16,185,129,0.35); transform:translateY(-1px); }
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

    /* ─── PILLS ───────────────────────────────────────────────── */
    .pill {
        font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:20px;
        text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap; display:inline-block;
    }
    .pill-blue { background:rgba(59,130,246,0.12); color:#3b82f6; }
    .pill-green { background:rgba(16,185,129,0.12); color:#10b981; }

    /* ─── EMPTY STATE ─────────────────────────────────────────── */
    .sa-empty {
        text-align:center; padding:48px 20px; color:var(--muted);
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    }
    .sa-empty p { margin-top:12px; font-size:0.9rem; }

    /* ─── MODAL ───────────────────────────────────────────────── */
    .sa-modal-overlay {
        display:none; position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,0.45); backdrop-filter:blur(2px);
        align-items:center; justify-content:center;
    }
    .sa-modal {
        background:var(--surface); border-radius:14px; width:100%; max-width:580px;
        max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);
        animation:modalIn 0.25s ease-out;
    }
    .sa-modal-wide { max-width:720px; }
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
    .sa-form-group label { display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:5px; }
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
    textarea.sa-form-control { resize:vertical; }
    .sa-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    /* ─── RESPONSIVE ──────────────────────────────────────────── */
    @media (max-width:768px) {
        .sa-toolbar-group { flex-direction:column; }
        .sa-toolbar-input { width:100%; }
        .sa-form-row { grid-template-columns:1fr; }
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    <?php echo __('system_expenses'); ?>
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?php echo __('manage_system_expenses'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="sa-btn" onclick="showModal('addExpenseModal')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php echo __('add_expense'); ?>
                                </button>
                                <button type="button" class="sa-btn" onclick="showModal('manageCategories')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                    <?php echo __('manage_categories'); ?>
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
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($error)): ?>
                            <div class="sa-alert sa-alert-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            <?php endif; ?>

                            <!-- Summary -->
                            <p class="section-label">Summary</p>
                            <div class="metric-grid">
                                <div class="metric-card blue">
                                    <div class="metric-value">
                                        <?php if ($totalAmountByUSD > 0): ?>
                                        <span>$<?php echo number_format($totalAmountByUSD, 2); ?></span>
                                        <?php endif; ?>
                                        <?php if ($totalAmountByAFS > 0): ?>
                                        <span style="font-size:0.85rem;opacity:0.8;">؋ <?php echo number_format($totalAmountByAFS, 2); ?></span>
                                        <?php endif; ?>
                                        <?php if ($totalAmountByUSD == 0 && $totalAmountByAFS == 0): ?>
                                        <span>&ndash;</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="metric-label"><?php echo __('total_expenses'); ?></div>
                                </div>
                                <div class="metric-card green">
                                    <div class="metric-value" style="font-size:1.8rem;"><?php echo count($expenses); ?></div>
                                    <div class="metric-label"><?php echo __('number_of_expenses'); ?></div>
                                </div>
                                <div class="metric-card amber">
                                    <div class="metric-value">
                                        <?php if ($avgUSD > 0): ?>
                                        <span>$<?php echo number_format($avgUSD, 2); ?></span>
                                        <?php endif; ?>
                                        <?php if ($avgAFS > 0): ?>
                                        <span style="font-size:0.85rem;opacity:0.8;">؋ <?php echo number_format($avgAFS, 2); ?></span>
                                        <?php endif; ?>
                                        <?php if ($avgUSD == 0 && $avgAFS == 0): ?>
                                        <span>&ndash;</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="metric-label"><?php echo __('average_expense'); ?></div>
                                </div>
                                <div class="metric-card red">
                                    <div class="metric-value" style="font-size:1.8rem;"><?php echo count($categoryTotals); ?></div>
                                    <div class="metric-label"><?php echo __('categories_used'); ?></div>
                                </div>
                            </div>

                            <!-- Filter -->
                            <p class="section-label" style="margin-top:24px;"><?php echo __('filter_expenses'); ?></p>
                            <div class="sa-toolbar">
                                <form method="GET" class="sa-toolbar-form">
                                    <div class="sa-toolbar-group">
                                        <input type="date" name="startDate" class="sa-toolbar-input" value="<?php echo htmlspecialchars($startDate); ?>" placeholder="<?php echo __('from_date'); ?>">
                                        <input type="date" name="endDate" class="sa-toolbar-input" value="<?php echo htmlspecialchars($endDate); ?>" placeholder="<?php echo __('to_date'); ?>">
                                        <select name="category" class="sa-toolbar-input">
                                            <option value=""><?php echo __('all_categories'); ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <?php echo __('apply_filter'); ?>
                                        </button>
                                        <a href="system_expenses.php" class="sa-btn sa-btn-ghost sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                            <?php echo __('reset'); ?>
                                        </a>
                                    </div>
                                </form>
                            </div>

                            <!-- Expenses Table -->
                            <p class="section-label" style="margin-top:24px;"><?php echo __('expense_list'); ?></p>
                            <?php if (empty($expenses)): ?>
                            <div class="sa-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                <p><?php echo __('no_expenses_found'); ?></p>
                            </div>
                            <?php else: ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('date'); ?></th>
                                            <th><?php echo __('category'); ?></th>
                                            <th><?php echo __('description'); ?></th>
                                            <th><?php echo __('amount'); ?></th>
                                            <th><?php echo __('created_by'); ?></th>
                                            <th class="sa-th-actions"><?php echo __('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                            <td><span class="pill pill-blue"><?php echo htmlspecialchars($expense['category_name']); ?></span></td>
                                            <td style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($expense['description']); ?>">
                                                <?php echo htmlspecialchars($expense['description']); ?>
                                            </td>
                                            <td><strong><?php echo formatCurrency($expense['amount'], $expense['currency']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($expense['created_by_name'] ?? 'System'); ?></td>
                                            <td class="sa-td-actions">
                                                <button type="button" class="sa-btn-icon" onclick="editExpense(<?php echo $expense['id']; ?>)" title="<?php echo __('edit'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                <button type="button" class="sa-btn-icon sa-btn-icon-danger" onclick="deleteExpense(<?php echo $expense['id']; ?>)" title="<?php echo __('delete'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- Category Summary -->
                            <?php if (!empty($categoryTotals)): ?>
                            <p class="section-label" style="margin-top:24px;"><?php echo __('expense_by_category'); ?></p>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('category'); ?></th>
                                            <th><?php echo __('count'); ?></th>
                                            <th><?php echo __('total'); ?></th>
                                            <th>% <?php echo __('of_total'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categoryTotals as $catId => $catData): ?>
                                        <?php 
                                        $catTotal = 0;
                                        foreach ($catData['currencies'] as $amount) {
                                            $catTotal += $amount;
                                        }
                                        ?>
                                        <tr>
                                            <td><span class="pill pill-green"><?php echo htmlspecialchars($catData['name']); ?></span></td>
                                            <td><?php echo $catData['count']; ?></td>
                                            <td>
                                                <?php if (count($catData['currencies']) === 1): ?>
                                                    <?php foreach ($catData['currencies'] as $curr => $amount): ?>
                                                        <strong><?php echo ($curr === 'USD' ? '$' : '؋ ').number_format($amount, 2); ?></strong>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php $first = true; foreach ($catData['currencies'] as $curr => $amount): ?>
                                                        <?php if (!$first) echo ' + '; $first = false; ?>
                                                        <strong><?php echo ($curr === 'USD' ? '$' : '؋ ').number_format($amount, 2); ?></strong>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $totalAmount > 0 ? number_format(($catTotal / $totalAmount) * 100, 1) : 0; ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="sa-modal-overlay" id="addExpenseModal">
        <div class="sa-modal">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    <?php echo __('add_expense'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('addExpenseModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form id="addExpenseForm" method="POST" action="handlers/create_system_expense.php" enctype="multipart/form-data" onsubmit="handleExpenseSubmit(event)">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label><?php echo __('category'); ?> *</label>
                            <select name="category_id" class="sa-form-control" required>
                                <option value=""><?php echo __('select_category'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label><?php echo __('date'); ?> *</label>
                            <input type="date" name="date" class="sa-form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label><?php echo __('description'); ?> *</label>
                        <textarea name="description" class="sa-form-control" rows="3" required></textarea>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label><?php echo __('amount'); ?> *</label>
                            <input type="number" name="amount" class="sa-form-control" step="0.01" min="0" required>
                        </div>
                        <div class="sa-form-group">
                            <label><?php echo __('currency'); ?></label>
                            <select name="currency" class="sa-form-control">
                                <option value="USD">USD</option>
                                <option value="AFS">AFS</option>
                            </select>
                        </div>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label><?php echo __('payment_method'); ?></label>
                            <input type="text" name="payment_method" class="sa-form-control" placeholder="e.g., Bank Transfer, Cash">
                        </div>
                        <div class="sa-form-group">
                            <label><?php echo __('reference_number'); ?></label>
                            <input type="text" name="reference_number" class="sa-form-control" placeholder="Invoice, Check, Transaction ID">
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label><?php echo __('receipt_file'); ?></label>
                        <input type="file" name="receipt_file" class="sa-form-control" accept=".pdf,.jpg,.jpeg,.png" multiple="false">
                        <small style="color:var(--muted);font-size:0.75rem;">Max 5MB, PDF/JPG/PNG only</small>
                    </div>
                    <div class="sa-form-group">
                        <label><?php echo __('notes'); ?></label>
                        <textarea name="notes" class="sa-form-control" rows="2" placeholder="Additional notes (optional)"></textarea>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('addExpenseModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php echo __('save_expense'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div class="sa-modal-overlay" id="editExpenseModal">
        <div class="sa-modal">
            <div id="editExpenseContent"><!-- Loaded via AJAX --></div>
        </div>
    </div>

    <!-- Manage Categories Modal -->
    <div class="sa-modal-overlay" id="manageCategories">
        <div class="sa-modal">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <?php echo __('expense_categories'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('manageCategories')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="sa-modal-body">
                <button type="button" class="sa-btn sa-btn-success" onclick="closeModal('manageCategories'); showModal('addCategoryModal')" style="margin-bottom:16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo __('add_category'); ?>
                </button>
                <?php if (empty($categories)): ?>
                <p style="color:var(--muted);text-align:center;padding:20px;"><?php echo __('no_categories_found'); ?></p>
                <?php else: ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <?php foreach ($categories as $cat): ?>
                    <div style="background:var(--surface2);border-radius:8px;padding:14px;display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div>
                            <div style="font-weight:600;color:var(--text);margin-bottom:4px;"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <?php if (!empty($cat['description'])): ?>
                            <div style="font-size:0.78rem;color:var(--muted);"><?php echo htmlspecialchars($cat['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:6px;flex-shrink:0;">
                            <button type="button" class="sa-btn-icon" onclick="closeModal('manageCategories'); editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')" title="<?php echo __('edit'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button type="button" class="sa-btn-icon sa-btn-icon-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>)" title="<?php echo __('delete'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('manageCategories')"><?php echo __('close'); ?></button>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="sa-modal-overlay" id="addCategoryModal">
        <div class="sa-modal">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo __('add_category'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('addCategoryModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="handlers/create_system_expense_category.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label><?php echo __('category_name'); ?> *</label>
                        <input type="text" name="name" class="sa-form-control" required>
                    </div>
                    <div class="sa-form-group">
                        <label><?php echo __('description'); ?></label>
                        <textarea name="description" class="sa-form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('addCategoryModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-success">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php echo __('save_category'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="sa-modal-overlay" id="editCategoryModal">
        <div class="sa-modal">
            <div id="editCategoryContent"><!-- Loaded via AJAX --></div>
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

    // Handle form submission with AJAX
    function handleExpenseSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('<?php echo __("expense_created_successfully"); ?>');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }

    // Delete expense
    function deleteExpense(id) {
        if (confirm('<?php echo __('are_you_sure'); ?>')) {
            fetch('handlers/delete_system_expense.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('<?php echo __("deleted_successfully"); ?>');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            }).catch(err => alert('Error: ' + err.message));
        }
    }

    // Delete category
    function deleteCategory(id) {
        if (confirm('<?php echo __('are_you_sure'); ?>')) {
            fetch('handlers/delete_system_expense_category.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>'
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }
    }

    // Edit expense (AJAX load)
    function editExpense(id) {
        fetch('handlers/get_system_expense.php?id=' + id)
            .then(r => r.json())
            .then(data => {
                const cats = <?php echo json_encode($categories); ?>;
                let catOptions = '<option value=""><?php echo __('select_category'); ?></option>';
                cats.forEach(c => {
                    const sel = c.id == data.category_id ? 'selected' : '';
                    catOptions += '<option value="' + c.id + '" ' + sel + '>' + c.name + '</option>';
                });
                const html = `
                    <div class="sa-modal-header">
                        <h5>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <?php echo __('edit_expense'); ?>
                        </h5>
                        <button type="button" class="sa-modal-close" onclick="closeModal('editExpenseModal')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form method="POST" action="handlers/update_system_expense.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="id" value="\${data.id}">
                        <div class="sa-modal-body">
                            <div class="sa-form-row">
                                <div class="sa-form-group">
                                    <label><?php echo __('category'); ?> *</label>
                                    <select name="category_id" class="sa-form-control" required>\${catOptions}</select>
                                </div>
                                <div class="sa-form-group">
                                    <label><?php echo __('date'); ?> *</label>
                                    <input type="date" name="date" class="sa-form-control" value="\${data.date}" required>
                                </div>
                            </div>
                            <div class="sa-form-group">
                                <label><?php echo __('description'); ?> *</label>
                                <textarea name="description" class="sa-form-control" rows="3" required>\${data.description}</textarea>
                            </div>
                            <div class="sa-form-row">
                                <div class="sa-form-group">
                                    <label><?php echo __('amount'); ?> *</label>
                                    <input type="number" name="amount" class="sa-form-control" step="0.01" value="\${data.amount}" required>
                                </div>
                                <div class="sa-form-group">
                                    <label><?php echo __('currency'); ?></label>
                                    <select name="currency" class="sa-form-control">
                                        <option value="USD" \${data.currency === 'USD' ? 'selected' : ''}>USD</option>
                                        <option value="AFS" \${data.currency === 'AFS' ? 'selected' : ''}>AFS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="sa-form-row">
                                <div class="sa-form-group">
                                    <label><?php echo __('payment_method'); ?></label>
                                    <input type="text" name="payment_method" class="sa-form-control" value="\${data.payment_method || ''}">
                                </div>
                                <div class="sa-form-group">
                                    <label><?php echo __('reference_number'); ?></label>
                                    <input type="text" name="reference_number" class="sa-form-control" value="\${data.reference_number || ''}">
                                </div>
                            </div>
                            <div class="sa-form-group">
                                <label><?php echo __('notes'); ?></label>
                                <textarea name="notes" class="sa-form-control" rows="2">\${data.notes || ''}</textarea>
                            </div>
                        </div>
                        <div class="sa-modal-footer">
                            <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('editExpenseModal')"><?php echo __('cancel'); ?></button>
                            <button type="submit" class="sa-btn sa-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                <?php echo __('update'); ?>
                            </button>
                        </div>
                    </form>
                `;
                document.getElementById('editExpenseContent').innerHTML = html;
                showModal('editExpenseModal');
            });
    }

    // Edit category
    function editCategory(id, name) {
        const html = `
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    <?php echo __('edit_category'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('editCategoryModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="handlers/update_system_expense_category.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="id" value="\${id}">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label><?php echo __('category_name'); ?> *</label>
                        <input type="text" name="name" class="sa-form-control" value="\${name}" required>
                    </div>
                    <div class="sa-form-group">
                        <label><?php echo __('description'); ?></label>
                        <textarea name="description" class="sa-form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('editCategoryModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php echo __('save'); ?>
                    </button>
                </div>
            </form>
        `;
        document.getElementById('editCategoryContent').innerHTML = html;
        showModal('editCategoryModal');
    }
    </script>
<?php include '../includes/admin_footer.php'; ?>
