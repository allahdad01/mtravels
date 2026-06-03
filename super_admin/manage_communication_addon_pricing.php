<?php
/**
 * Super Admin: Communication Add-on Pricing
 * Configure tenant-wise pricing for WhatsApp and SMTP add-ons.
 */
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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

function getCommAddonCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$', 'EUR' => 'EUR', 'GBP' => 'GBP', 'JPY' => 'JPY',
        'AFN' => 'AFN', 'AED' => 'AED', 'INR' => 'INR', 'PKR' => 'PKR'
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pricing') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_communication_addon_pricing.php?error=invalid_csrf');
        exit();
    }

    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $whatsapp_monthly = floatval($_POST['whatsapp_addon_monthly_price'] ?? 0);
    $whatsapp_quarterly = floatval($_POST['whatsapp_addon_quarterly_price'] ?? 0);
    $whatsapp_yearly = floatval($_POST['whatsapp_addon_yearly_price'] ?? 0);
    $smtp_monthly = floatval($_POST['smtp_addon_monthly_price'] ?? 0);
    $smtp_quarterly = floatval($_POST['smtp_addon_quarterly_price'] ?? 0);
    $smtp_yearly = floatval($_POST['smtp_addon_yearly_price'] ?? 0);

    $errors = [];
    if ($tenant_id <= 0) {
        $errors[] = 'Invalid tenant ID.';
    }
    foreach ([
        'WhatsApp monthly' => $whatsapp_monthly,
        'WhatsApp quarterly' => $whatsapp_quarterly,
        'WhatsApp yearly' => $whatsapp_yearly,
        'SMTP monthly' => $smtp_monthly,
        'SMTP quarterly' => $smtp_quarterly,
        'SMTP yearly' => $smtp_yearly,
    ] as $label => $value) {
        if ($value <= 0) {
            $errors[] = $label . ' price must be greater than 0.';
        }
    }

    $tenantStmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status != 'deleted'");
    $tenantStmt->execute([$tenant_id]);
    if (!$tenantStmt->fetch()) {
        $errors[] = 'Tenant not found.';
    }

    if (empty($errors)) {
        try {
            $existsStmt = $pdo->prepare("SELECT tenant_id FROM settings WHERE tenant_id = ?");
            $existsStmt->execute([$tenant_id]);
            if ($existsStmt->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE settings
                    SET whatsapp_addon_monthly_price = ?,
                        whatsapp_addon_quarterly_price = ?,
                        whatsapp_addon_yearly_price = ?,
                        smtp_addon_monthly_price = ?,
                        smtp_addon_quarterly_price = ?,
                        smtp_addon_yearly_price = ?,
                        updated_at = NOW()
                    WHERE tenant_id = ?
                ");
                $stmt->execute([
                    $whatsapp_monthly, $whatsapp_quarterly, $whatsapp_yearly,
                    $smtp_monthly, $smtp_quarterly, $smtp_yearly,
                    $tenant_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO settings
                        (tenant_id, whatsapp_addon_monthly_price, whatsapp_addon_quarterly_price, whatsapp_addon_yearly_price,
                         smtp_addon_monthly_price, smtp_addon_quarterly_price, smtp_addon_yearly_price, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $tenant_id,
                    $whatsapp_monthly, $whatsapp_quarterly, $whatsapp_yearly,
                    $smtp_monthly, $smtp_quarterly, $smtp_yearly
                ]);
            }
            $success = 'Communication add-on pricing updated successfully!';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = trim($_GET['search'] ?? '');

$count_sql = "SELECT COUNT(*) as total FROM tenants t WHERE t.status != 'deleted'";
$filter_params = [];
if ($search_query !== '') {
    $count_sql .= " AND (t.name LIKE ? OR t.subdomain LIKE ?)";
    $searchTerm = '%' . $search_query . '%';
    $filter_params[] = $searchTerm;
    $filter_params[] = $searchTerm;
}

$countStmt = $pdo->prepare($count_sql);
$countStmt->execute($filter_params);
$total_items = intval($countStmt->fetch()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_items / $items_per_page));
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

$query = "
    SELECT
        t.id, t.name, t.subdomain, t.status,
        s.whatsapp_addon_monthly_price, s.whatsapp_addon_quarterly_price, s.whatsapp_addon_yearly_price,
        s.smtp_addon_monthly_price, s.smtp_addon_quarterly_price, s.smtp_addon_yearly_price,
        ts.currency
    FROM tenants t
    LEFT JOIN settings s ON t.id = s.tenant_id
    LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
    WHERE t.status != 'deleted'
";
if ($search_query !== '') {
    $query .= " AND (t.name LIKE ? OR t.subdomain LIKE ?)";
}
$query .= " ORDER BY t.name ASC LIMIT ? OFFSET ?";

$params = $filter_params;
$params[] = $items_per_page;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header_super_admin.php';
?>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root { --muted: #999; --red: #ef4444; --amber: #f59e0b; --blue: #4099ff; --grad-start: #4099ff; --grad-end: #2ed8b6; --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); --radius: 10px; }

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card { background: var(--grad) !important; color: #fff; border: none !important; margin-bottom: 24px; padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px; position: relative; overflow: hidden; }
.page-header.card::after { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%); pointer-events: none; }
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; border-radius: var(--radius); border: 1px solid #e0e0e0; margin-bottom: 16px; }
.sa-alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.sa-alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 20px; height: 20px; }
.sa-alert-content { flex: 1; align-self: center; }
.sa-alert-close { flex-shrink: 0; background: none; border: none; cursor: pointer; color: inherit; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
.sa-alert-close:hover { opacity: 0.7; }

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; font-weight: 500; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; }
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(64,153,255,0.3); color: #fff; }
.sa-btn-success { background: #10b981; color: white; }
.sa-btn-success:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); color: #fff; }
.sa-btn-ghost { background: #f0f0f0; color: #333; border: 1px solid #e0e0e0; }
.sa-btn-ghost:hover { background: #e8e8e8; border-color: #d0d0d0; }
.sa-btn-sm { padding: 0.4rem 0.75rem; font-size: 0.8rem; }

/* ─── TOOLBAR ─────────────────────────────────────────────── */
.sa-toolbar { background: white; border: 1px solid #e0e0e0; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; }
.sa-toolbar-form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.sa-search-box { position: relative; display: flex; align-items: center; }
.sa-search-icon { position: absolute; left: 12px; color: #999; pointer-events: none; }
.sa-search-input { padding: 0.5rem 0.75rem 0.5rem 2.2rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 0.85rem; min-width: 240px; transition: border-color 0.15s; }
.sa-search-input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }

/* ─── DATA TABLE ──────────────────────────────────────────── */
.sa-table-wrap { background: white; border: 1px solid #e0e0e0; border-radius: 10px; overflow-x: auto; }
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead th { text-align: left; padding: 14px 16px; font-size: 0.65rem; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 0.06em; background: #fafafa; border-bottom: 1px solid #e0e0e0; white-space: nowrap; }
.sa-table tbody tr { transition: background 0.15s; }
.sa-table tbody tr:hover { background: #f8faff; }
.sa-table tbody td { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.sa-table tbody tr:last-child td { border-bottom: none; }
.sa-td-actions { white-space: nowrap; }

/* ─── PILLS ───────────────────────────────────────────────── */
.pill { font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; display: inline-block; }
.pill-green { background: rgba(16,185,129,0.12); color: #10b981; }
.pill-gray { background: #f5f5f5; color: #999; }

/* ─── PAGINATION ──────────────────────────────────────────── */
.sa-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
.sa-page-btn { min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px; border: 1px solid #e0e0e0; background: #f5f5f5; color: #333; text-decoration: none; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
.sa-page-btn:hover { background: rgba(64,153,255,0.1); border-color: #4099ff; color: #4099ff; }
.sa-page-active { background: var(--grad); border-color: #4099ff; color: white; }
.sa-page-active:hover { color: white; }

/* ─── FORM INPUTS ─────────────────────────────────────────── */
.sa-pricing-input { width: 100%; padding: 0.4rem 0.5rem; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.8rem; font-family: inherit; transition: border-color 0.15s; box-sizing: border-box; }
.sa-pricing-input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }
.sa-pricing-grid { display: grid; grid-template-columns: repeat(2, minmax(100px, 1fr)); gap: 6px; }
.sa-pricing-label { font-size: 0.7rem; color: #999; font-weight: 600; }

/* ─── EMPTY STATE ─────────────────────────────────────────── */
.sa-empty { text-align: center; padding: 32px 20px; color: #ccc; font-size: 0.9rem; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ breadcrumb ] start -->
                        <div class="page-header card">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Communication Addon Pricing
                                    </h5>
                                    <p class="page-desc">Set tenant-wise monthly, quarterly, and yearly prices for WhatsApp/SMTP</p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="manage_communication_addons.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><polyline points="15 18 9 12 15 6"/></svg>Back to Add-ons
                                    </a>
                                </div>
                            </div>
                        </div>
                        <!-- [ breadcrumb ] end -->

                        <?php if (isset($success)): ?>
                        <div class="sa-alert sa-alert-success">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                            <div class="sa-alert-content"><?= htmlspecialchars($success) ?></div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                            <div class="sa-alert-content">
                                <ul style="margin:0;padding-left:18px;">
                                    <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>

                        <!-- Search Toolbar -->
                        <div class="sa-toolbar">
                            <form method="GET" action="manage_communication_addon_pricing.php" class="sa-toolbar-form">
                                <div class="sa-search-box" style="flex:1;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="sa-search-input" name="search" placeholder="Search tenant..." value="<?= htmlspecialchars($search_query) ?>" style="min-width:280px;">
                                </div>
                                <button type="submit" class="sa-btn sa-btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Search
                                </button>
                                <?php if ($search_query !== ''): ?>
                                <a href="manage_communication_addon_pricing.php" class="sa-btn sa-btn-ghost">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Clear
                                </a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Pricing Table -->
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>WhatsApp Pricing</th>
                                        <th>SMTP Pricing</th>
                                        <th class="sa-th-actions" style="text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($tenants)): ?>
                                    <tr><td colspan="4" style="text-align:center;color:#999;padding:32px;">No tenants found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($tenants as $tenant):
                                        $currency = $tenant['currency'] ?: 'USD';
                                        $symbol = getCommAddonCurrencySymbol($currency);
                                        $wa_m = floatval($tenant['whatsapp_addon_monthly_price'] ?? 30);
                                        $wa_q = floatval($tenant['whatsapp_addon_quarterly_price'] ?? 90);
                                        $wa_y = floatval($tenant['whatsapp_addon_yearly_price'] ?? 360);
                                        $sm_m = floatval($tenant['smtp_addon_monthly_price'] ?? 20);
                                        $sm_q = floatval($tenant['smtp_addon_quarterly_price'] ?? 60);
                                        $sm_y = floatval($tenant['smtp_addon_yearly_price'] ?? 240);
                                        $status_pill = ($tenant['status'] ?? '') === 'active' ? 'pill-green' : 'pill-gray';
                                    ?>
                                    <tr>
                                        <td style="min-width:140px;">
                                            <div style="font-weight:600;color:#333;"><?= htmlspecialchars($tenant['name']) ?></div>
                                            <div style="font-size:0.8rem;color:#999;margin:2px 0 4px;"><?= htmlspecialchars($tenant['subdomain']) ?></div>
                                            <span class="pill <?= $status_pill ?>"><?= htmlspecialchars($tenant['status']) ?></span>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <div><span class="sa-pricing-label">Monthly:</span> <?= $symbol . number_format($wa_m, 2) ?></div>
                                            <div><span class="sa-pricing-label">Quarterly:</span> <?= $symbol . number_format($wa_q, 2) ?></div>
                                            <div><span class="sa-pricing-label">Yearly:</span> <?= $symbol . number_format($wa_y, 2) ?></div>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <div><span class="sa-pricing-label">Monthly:</span> <?= $symbol . number_format($sm_m, 2) ?></div>
                                            <div><span class="sa-pricing-label">Quarterly:</span> <?= $symbol . number_format($sm_q, 2) ?></div>
                                            <div><span class="sa-pricing-label">Yearly:</span> <?= $symbol . number_format($sm_y, 2) ?></div>
                                        </td>
                                        <td class="sa-td-actions" style="min-width:260px;vertical-align:top;">
                                            <form method="POST" action="manage_communication_addon_pricing.php?page=<?= $current_page ?>&search=<?= urlencode($search_query) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="update_pricing">
                                                <input type="hidden" name="tenant_id" value="<?= intval($tenant['id']) ?>">
                                                <div class="sa-pricing-grid">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="whatsapp_addon_monthly_price" value="<?= htmlspecialchars($wa_m) ?>" title="WA Monthly" placeholder="WA Mo">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="whatsapp_addon_quarterly_price" value="<?= htmlspecialchars($wa_q) ?>" title="WA Quarterly" placeholder="WA Qr">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="whatsapp_addon_yearly_price" value="<?= htmlspecialchars($wa_y) ?>" title="WA Yearly" placeholder="WA Yr">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="smtp_addon_monthly_price" value="<?= htmlspecialchars($sm_m) ?>" title="SMTP Monthly" placeholder="SMTP Mo">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="smtp_addon_quarterly_price" value="<?= htmlspecialchars($sm_q) ?>" title="SMTP Quarterly" placeholder="SMTP Qr">
                                                    <input type="number" step="0.01" min="0.01" class="sa-pricing-input" name="smtp_addon_yearly_price" value="<?= htmlspecialchars($sm_y) ?>" title="SMTP Yearly" placeholder="SMTP Yr">
                                                </div>
                                                <button type="submit" class="sa-btn sa-btn-success sa-btn-sm" style="margin-top:6px;width:100%;justify-content:center;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Update Pricing
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <div class="sa-pagination" style="justify-content:center;margin-top:16px;">
                            <?php if ($current_page > 1): ?>
                            <a href="?page=1&search=<?= urlencode($search_query) ?>" class="sa-page-btn" title="First"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg></a>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_query) ?>" class="sa-page-btn" title="Prev"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg></a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>" class="sa-page-btn <?= $i === $current_page ? 'sa-page-active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_query) ?>" class="sa-page-btn" title="Next"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></a>
                            <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search_query) ?>" class="sa-page-btn" title="Last"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
