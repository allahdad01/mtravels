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
    .cp-wrap { padding: 20px; }
    .cp-head { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); color: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
    .cp-head h4 { margin: 0; color: #fff; font-weight: 700; }
    .cp-head p { margin: 6px 0 0; opacity: .9; font-size: 13px; }
    .cp-card { background: #fff; border: 1px solid #e8edf5; border-radius: 12px; box-shadow: 0 2px 12px rgba(64,153,255,.08); }
    .cp-card-head { padding: 14px 16px; border-bottom: 1px solid #e8edf5; }
    .cp-card-head h5 { margin: 0; font-weight: 700; font-size: 15px; }
    .cp-card-body { padding: 14px 16px; }
    .cp-search { display: flex; gap: 8px; margin-bottom: 12px; align-items: center; }
    .cp-search input { width: 280px; max-width: 100%; }
    .cp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cp-table thead th { background: #f4f7fe; color: #6b7a99; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; padding: 10px; border-bottom: 1px solid #e8edf5; }
    .cp-table tbody td { padding: 10px; border-bottom: 1px solid #eef2f8; vertical-align: top; }
    .cp-table tbody tr:last-child td { border-bottom: none; }
    .cp-grid { display: grid; grid-template-columns: repeat(2, minmax(120px, 1fr)); gap: 6px; }
    .cp-grid input { width: 100%; }
    .cp-pill { display: inline-flex; align-items: center; border-radius: 16px; padding: 2px 9px; font-size: 11px; font-weight: 700; }
    .cp-pill.active { background: rgba(34,197,94,.12); color: #166534; }
    .cp-pill.other { background: rgba(107,122,153,.12); color: #475569; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="cp-wrap">
                        <div class="cp-head">
                            <h4><i class="feather icon-dollar-sign mr-1"></i>Communication Addon Pricing</h4>
                            <p>Set tenant-wise monthly, quarterly, and yearly prices for WhatsApp/SMTP</p>
                        </div>
                        <div class="cp-card">
                            <div class="cp-card-head">
                                <h5>Pricing Configuration</h5>
                            </div>
                            <div class="cp-card-body">
                                <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                                <?php endif; ?>

                                <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>

                                <form method="GET" action="manage_communication_addon_pricing.php" class="cp-search">
                                    <input type="text" class="form-control" name="search" placeholder="Search tenant..." value="<?= htmlspecialchars($search_query) ?>">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <?php if ($search_query !== ''): ?>
                                    <a href="manage_communication_addon_pricing.php" class="btn btn-light">Clear</a>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="cp-table">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>WhatsApp Pricing</th>
                                                <th>SMTP Pricing</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($tenants)): ?>
                                            <tr><td colspan="4" class="text-center text-muted">No tenants found.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($tenants as $tenant): ?>
                                            <?php
                                                $currency = $tenant['currency'] ?: 'USD';
                                                $symbol = getCommAddonCurrencySymbol($currency);
                                                $wa_m = floatval($tenant['whatsapp_addon_monthly_price'] ?? 30);
                                                $wa_q = floatval($tenant['whatsapp_addon_quarterly_price'] ?? 90);
                                                $wa_y = floatval($tenant['whatsapp_addon_yearly_price'] ?? 360);
                                                $sm_m = floatval($tenant['smtp_addon_monthly_price'] ?? 20);
                                                $sm_q = floatval($tenant['smtp_addon_quarterly_price'] ?? 60);
                                                $sm_y = floatval($tenant['smtp_addon_yearly_price'] ?? 240);
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($tenant['name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($tenant['subdomain']) ?></small><br>
                                                    <span class="cp-pill <?= ($tenant['status'] ?? '') === 'active' ? 'active' : 'other' ?>"><?= htmlspecialchars($tenant['status']) ?></span>
                                                </td>
                                                <td>
                                                    <div>Monthly: <?= $symbol . number_format($wa_m, 2) ?></div>
                                                    <div>Quarterly: <?= $symbol . number_format($wa_q, 2) ?></div>
                                                    <div>Yearly: <?= $symbol . number_format($wa_y, 2) ?></div>
                                                </td>
                                                <td>
                                                    <div>Monthly: <?= $symbol . number_format($sm_m, 2) ?></div>
                                                    <div>Quarterly: <?= $symbol . number_format($sm_q, 2) ?></div>
                                                    <div>Yearly: <?= $symbol . number_format($sm_y, 2) ?></div>
                                                </td>
                                                <td style="min-width:260px;">
                                                    <form method="POST" action="manage_communication_addon_pricing.php?page=<?= $current_page ?>&search=<?= urlencode($search_query) ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="action" value="update_pricing">
                                                        <input type="hidden" name="tenant_id" value="<?= intval($tenant['id']) ?>">
                                                        <div class="cp-grid">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="whatsapp_addon_monthly_price" value="<?= htmlspecialchars($wa_m) ?>" title="WA Monthly">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="whatsapp_addon_quarterly_price" value="<?= htmlspecialchars($wa_q) ?>" title="WA Quarterly">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="whatsapp_addon_yearly_price" value="<?= htmlspecialchars($wa_y) ?>" title="WA Yearly">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="smtp_addon_monthly_price" value="<?= htmlspecialchars($sm_m) ?>" title="SMTP Monthly">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="smtp_addon_quarterly_price" value="<?= htmlspecialchars($sm_q) ?>" title="SMTP Quarterly">
                                                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="smtp_addon_yearly_price" value="<?= htmlspecialchars($sm_y) ?>" title="SMTP Yearly">
                                                        </div>
                                                        <button type="submit" class="btn btn-success btn-sm mt-1">Update Pricing</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($total_pages > 1): ?>
                                <nav class="mt-3">
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                        </div>
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
