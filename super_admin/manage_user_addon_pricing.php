<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

function getUserAddonCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
        'AFN' => '؋', 'AED' => 'د.إ', 'INR' => '₹', 'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_user_addon_pricing.php?error=invalid_csrf');
        exit();
    }

    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $monthly_price = floatval($_POST['monthly_price'] ?? 0);
    $quarterly_price = floatval($_POST['quarterly_price'] ?? 0);
    $yearly_price = floatval($_POST['yearly_price'] ?? 0);
    $errors = [];

    if ($tenant_id <= 0) $errors[] = 'Invalid tenant ID.';
    if ($monthly_price <= 0) $errors[] = 'Monthly price must be greater than 0.';
    if ($quarterly_price <= 0) $errors[] = 'Quarterly price must be greater than 0.';
    if ($yearly_price <= 0) $errors[] = 'Yearly price must be greater than 0.';

    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$tenant_id]);
    if (!$stmt->fetch()) $errors[] = 'Tenant not found.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT tenant_id FROM settings WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE settings SET user_addon_monthly_price = ?, user_addon_quarterly_price = ?, user_addon_yearly_price = ?, updated_at = NOW() WHERE tenant_id = ?");
                $stmt->execute([$monthly_price, $quarterly_price, $yearly_price, $tenant_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (tenant_id, user_addon_monthly_price, user_addon_quarterly_price, user_addon_yearly_price, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$tenant_id, $monthly_price, $quarterly_price, $yearly_price]);
            }
            $success = 'Pricing updated successfully!';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

$count_query = "SELECT COUNT(*) as total FROM tenants t WHERE t.status != 'deleted'";
$filter_params = [];
if (!empty($search_query)) {
    $count_query .= " AND (t.name LIKE ? OR t.subdomain LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = max(1, ceil($total_items / $items_per_page));
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

$default_monthly = 25.00;
$default_quarterly = 75.00;
$default_yearly = 300.00;

$query = "
    SELECT t.id, t.name, t.subdomain, t.status,
        s.user_addon_monthly_price, s.user_addon_quarterly_price, s.user_addon_yearly_price,
        ts.currency
    FROM tenants t
    LEFT JOIN settings s ON t.id = s.tenant_id
    LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
    WHERE t.status != 'deleted'
";
if (!empty($search_query)) {
    $query .= " AND (t.name LIKE ? OR t.subdomain LIKE ?)";
}
$query .= " ORDER BY t.name LIMIT ? OFFSET ?";
$params = $filter_params;
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header_super_admin.php';
?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
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
.sa-table-wrap {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-btn-ghost {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text); text-decoration: none;
}
.sa-btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }
.sa-td-actions { white-space: nowrap; text-align: right; }
.tenant-cell { display: flex; flex-direction: column; }
.tenant-name { font-weight: 600; }
.tenant-meta { font-size: .8rem; color: var(--muted); }
.price-cell { font-weight: 600; font-family: 'Courier New', monospace; }
.sa-pill {
    display: inline-flex; padding: 2px 8px; border-radius: 20px;
    font-size: .7rem; font-weight: 600; text-transform: uppercase;
}
.sa-pill.active { background: #d1fae5; color: #065f46; }
.sa-pill.suspended { background: #fee2e2; color: #991b1b; }
.sa-pill.inactive { background: #fef3c7; color: #92400e; }

.sa-form-control {
    width: 100%; padding: 8px 12px; border: 1px solid var(--border);
    border-radius: 8px; font-size: .85rem; font-family: inherit;
    background: var(--surface); color: var(--text);
}
.sa-form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.15); }

.pagination-wrap {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border); flex-wrap: wrap;
}
.pag-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px;
    border: 1px solid var(--border); border-radius: 8px;
    background: var(--surface); color: var(--text);
    font-size: .8rem; text-decoration: none; transition: all .15s;
}
.pag-btn:hover { border-color: var(--brand); color: var(--brand); }
.pag-btn.active { background: linear-gradient(135deg, var(--brand), var(--brand2)); border-color: var(--brand2); color: #fff; }
.pag-btn.disabled { opacity: .4; pointer-events: none; }
.pag-info { font-size: .75rem; color: var(--muted); }

.sa-alert {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem;
}
.sa-alert.success { background: #d1fae5; color: #065f46; }
.sa-alert.error { background: #fee2e2; color: #991b1b; }
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.sa-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: var(--surface); border-radius: var(--radius);
    width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.sa-modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sa-modal-hdr h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--muted); padding: 4px; line-height: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-ftr {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
}
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .85rem; }
.sa-input-group { display: flex; align-items: center; }
.sa-input-prepend {
    padding: 8px 12px; background: var(--bg); border: 1px solid var(--border);
    border-right: none; border-radius: 8px 0 0 8px;
    font-size: .85rem; color: var(--muted); font-weight: 600;
}
.sa-input-group .sa-form-control { border-radius: 0 8px 8px 0; }
.sa-form-hint { font-size: .75rem; color: var(--muted); margin-top: 4px; display: block; }
.sa-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text);
}
.sa-btn-secondary:hover { background: var(--bg); }
.alert-info {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; background: #dbeafe; color: #1e40af; font-size: .85rem; margin-bottom: 16px;
}
.alert-warning {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: .85rem; margin-top: 16px;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                User Addon Pricing
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Set per-tenant pricing for additional user seat add-ons</p>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <?php if (isset($success)): ?>
                        <div class="sa-alert success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($success) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                        <div class="sa-alert error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <ul style="margin:0;padding-left:16px">
                                <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    Pricing Configuration
                                </h3>
                                <span style="font-size:.8rem;color:var(--muted)"><?= $total_items ?> tenants</span>
                            </div>

                            <div style="padding:12px 20px;border-bottom:1px solid var(--border)">
                                <form method="GET" action="manage_user_addon_pricing.php" style="display:flex;gap:8px;flex-wrap:wrap">
                                    <input type="text" class="sa-form-control" name="search" placeholder="Search by name or subdomain..." value="<?= htmlspecialchars($search_query) ?>" style="max-width:320px">
                                    <button type="submit" class="sa-btn" style="padding:8px 14px">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                        Search
                                    </button>
                                    <?php if (!empty($search_query)): ?>
                                    <a href="manage_user_addon_pricing.php" class="sa-btn-ghost" style="padding:8px 14px">Clear</a>
                                    <?php endif; ?>
                                    <?php if (!empty($search_query)): ?>
                                    <span style="font-size:.8rem;color:var(--muted);align-self:center"><?= $total_items ?> result<?= $total_items !== 1 ? 's' : '' ?> for "<strong><?= htmlspecialchars($search_query) ?></strong>"</span>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <?php if (!empty($tenants)): ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Status</th>
                                        <th>Monthly</th>
                                        <th>Quarterly</th>
                                        <th>Yearly</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenants as $tenant):
                                        $monthly = $tenant['user_addon_monthly_price'] ?? $default_monthly;
                                        $quarterly = $tenant['user_addon_quarterly_price'] ?? $default_quarterly;
                                        $yearly = $tenant['user_addon_yearly_price'] ?? $default_yearly;
                                        $currency = $tenant['currency'] ?? 'USD';
                                        $symbol = getUserAddonCurrencySymbol($currency);
                                        $initials = strtoupper(substr($tenant['name'], 0, 2));
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="tenant-cell">
                                                <span class="tenant-name"><?= htmlspecialchars($tenant['name']) ?></span>
                                                <span class="tenant-meta"><?= htmlspecialchars($tenant['subdomain']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="sa-pill <?= $tenant['status'] ?>"><?= htmlspecialchars($tenant['status']) ?></span></td>
                                        <td class="price-cell"><?= $symbol . number_format($monthly, 2) ?></td>
                                        <td class="price-cell"><?= $symbol . number_format($quarterly, 2) ?></td>
                                        <td class="price-cell"><?= $symbol . number_format($yearly, 2) ?></td>
                                        <td class="sa-td-actions">
                                            <button type="button" class="sa-btn sa-btn-ghost" style="padding:6px 12px"
                                                onclick="openEditModal(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($initials) ?>', <?= $monthly ?>, <?= $quarterly ?>, <?= $yearly ?>, '<?= htmlspecialchars($currency) ?>', '<?= htmlspecialchars($symbol) ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Edit Pricing
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <p style="font-weight:600;margin-bottom:4px">No tenants found</p>
                                <p style="font-size:.85rem">Try adjusting your search query</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrap">
                                <a class="pag-btn <?= $current_page <= 1 ? 'disabled' : '' ?>" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                                </a>
                                <a class="pag-btn <?= $current_page <= 1 ? 'disabled' : '' ?>" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                </a>
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                if ($start_page > 1): ?>
                                <span class="pag-btn disabled">...</span>
                                <?php endif; ?>
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a class="pag-btn <?= $i === $current_page ? 'active' : '' ?>" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($end_page < $total_pages): ?>
                                <span class="pag-btn disabled">...</span>
                                <?php endif; ?>
                                <a class="pag-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                                <a class="pag-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                </a>
                                <span class="pag-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Pricing Modal -->
    <div class="sa-modal-overlay" id="editPricingModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit User Addon Pricing
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('editPricingModal')">&times;</button>
            </div>
            <form method="POST" action="manage_user_addon_pricing.php" id="editPricingForm" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="tenant_id" id="edit_tenant_id">
                <div class="sa-modal-body">
                    <div class="alert-info">
                        <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0" id="chip_avatar">AB</div>
                        <strong><span id="modal_tenant_name"></span></strong>
                    </div>

                    <div class="sa-form-group">
                        <label>Monthly Price <span style="color:#ef4444">*</span></label>
                        <div class="sa-input-group">
                            <span class="sa-input-prepend" id="sym_monthly">$</span>
                            <input type="number" class="sa-form-control" id="edit_monthly_price" name="monthly_price" step="0.01" min="0.01" required>
                        </div>
                        <span class="sa-form-hint">per user / month</span>
                    </div>

                    <div class="sa-form-group">
                        <label>Quarterly Price <span style="color:#ef4444">*</span></label>
                        <div class="sa-input-group">
                            <span class="sa-input-prepend" id="sym_quarterly">$</span>
                            <input type="number" class="sa-form-control" id="edit_quarterly_price" name="quarterly_price" step="0.01" min="0.01" required>
                        </div>
                        <span class="sa-form-hint">per user / 3 months</span>
                    </div>

                    <div class="sa-form-group">
                        <label>Yearly Price <span style="color:#ef4444">*</span></label>
                        <div class="sa-input-group">
                            <span class="sa-input-prepend" id="sym_yearly">$</span>
                            <input type="number" class="sa-form-control" id="edit_yearly_price" name="yearly_price" step="0.01" min="0.01" required>
                        </div>
                        <span class="sa-form-hint">per user / 12 months</span>
                    </div>

                    <div class="alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <strong>Note:</strong> These prices are shown to the tenant when they request additional user seats.
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('editPricingModal')">Cancel</button>
                    <button type="submit" class="sa-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openEditModal(tenantId, tenantName, initials, monthly, quarterly, yearly, currency, symbol) {
    document.getElementById('edit_tenant_id').value = tenantId;
    document.getElementById('modal_tenant_name').textContent = tenantName;
    document.getElementById('chip_avatar').textContent = initials;
    document.getElementById('edit_monthly_price').value = monthly;
    document.getElementById('edit_quarterly_price').value = quarterly;
    document.getElementById('edit_yearly_price').value = yearly;
    var sym = symbol || currency || '$';
    document.getElementById('sym_monthly').textContent = sym;
    document.getElementById('sym_quarterly').textContent = sym;
    document.getElementById('sym_yearly').textContent = sym;
    openModal('editPricingModal');
}
function validateForm() {
    var m = parseFloat(document.getElementById('edit_monthly_price').value);
    var q = parseFloat(document.getElementById('edit_quarterly_price').value);
    var y = parseFloat(document.getElementById('edit_yearly_price').value);
    if (m <= 0 || q <= 0 || y <= 0) {
        alert('All prices must be greater than 0.');
        return false;
    }
    if (q < m * 2.5 || y < q * 3) {
        return confirm('Warning: Your quarterly/yearly pricing seems low compared to monthly. Continue anyway?');
    }
    return true;
}
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { document.querySelectorAll('.sa-modal-overlay.active').forEach(function(m) { m.classList.remove('active'); }); } });
</script>
</body>
</html>
