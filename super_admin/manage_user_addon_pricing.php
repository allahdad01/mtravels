<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

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

// Helper function to get currency symbol
function getUserAddonCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
        'AFN' => '؋', 'AED' => 'د.إ', 'INR' => '₹', 'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

// Handle form submission for updating pricing
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

// Pagination and search
$items_per_page = 9; // 3-column grid looks better with multiples of 3
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
  @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap');

  :root {
    --ink: #0f1117;
    --ink-2: #3d4251;
    --ink-3: #8891a4;
    --surface: #f5f6fa;
    --surface-2: #ffffff;
    --border: #e4e7ef;
    --accent: #4f6ef7;
    --accent-light: #eef1ff;
    --accent-dark: #3a56d4;
    --success: #22c55e;
    --success-light: #dcfce7;
    --warning: #f59e0b;
    --warning-light: #fef3c7;
    --danger: #ef4444;
    --danger-light: #fee2e2;
    --monthly: #6366f1;
    --quarterly: #0ea5e9;
    --yearly: #10b981;
    --radius: 16px;
    --radius-sm: 10px;
    --shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06);
    --shadow-lg: 0 8px 32px rgba(0,0,0,0.10);
  }

  * { box-sizing: border-box; }

  .uap-wrapper {
    font-family: 'DM Sans', sans-serif;
    background: var(--surface);
    min-height: 100vh;
    padding: 32px 28px 48px;
    color: var(--ink);
  }

  /* ── Page header ── */
  .uap-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 32px;
    flex-wrap: wrap;
  }
  .uap-page-header-left {}
  .uap-breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--ink-3);
    margin-bottom: 8px;
    list-style: none;
    padding: 0;
  }
  .uap-breadcrumb li + li::before { content: '/'; margin-right: 6px; opacity: .5; }
  .uap-breadcrumb a { color: var(--ink-3); text-decoration: none; }
  .uap-breadcrumb a:hover { color: var(--accent); }
  .uap-page-title {
    font-family: 'Syne', sans-serif;
    font-size: 26px;
    font-weight: 700;
    color: var(--ink);
    margin: 0 0 4px;
    letter-spacing: -.3px;
  }
  .uap-page-subtitle { font-size: 14px; color: var(--ink-3); margin: 0; }

  .uap-tenant-count {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 40px;
    padding: 8px 18px;
    font-size: 14px;
    font-weight: 500;
    color: var(--ink-2);
    box-shadow: var(--shadow);
  }
  .uap-tenant-count strong { color: var(--accent); font-family: 'Syne', sans-serif; font-size: 18px; }

  /* ── Alerts ── */
  .uap-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 18px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    margin-bottom: 24px;
    animation: slideDown .3s ease;
  }
  @keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
  .uap-alert-success { background: var(--success-light); color: #166534; border-left: 4px solid var(--success); }
  .uap-alert-danger  { background: var(--danger-light);  color: #991b1b; border-left: 4px solid var(--danger); }
  .uap-alert-icon { font-size: 18px; margin-top: 1px; flex-shrink: 0; }
  .uap-alert-close {
    margin-left: auto; background: none; border: none; cursor: pointer;
    opacity: .5; font-size: 18px; line-height: 1; padding: 0;
  }
  .uap-alert-close:hover { opacity: 1; }

  /* ── Search bar ── */
  .uap-search-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
    flex-wrap: wrap;
  }
  .uap-search-wrap {
    position: relative;
    flex: 1;
    max-width: 380px;
  }
  .uap-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ink-3);
    pointer-events: none;
    font-size: 16px;
  }
  .uap-search-input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    background: var(--surface-2);
    color: var(--ink);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  .uap-search-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,110,247,.12);
  }
  .uap-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all .18s ease;
    text-decoration: none;
  }
  .uap-btn-primary { background: var(--accent); color: #fff; }
  .uap-btn-primary:hover { background: var(--accent-dark); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(79,110,247,.35); color: #fff; }
  .uap-btn-ghost { background: var(--surface-2); color: var(--ink-2); border: 1.5px solid var(--border); }
  .uap-btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

  .uap-results-label {
    margin-left: auto;
    font-size: 13px;
    color: var(--ink-3);
  }

  /* ── Tenant grid ── */
  .uap-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
  }

  .uap-card {
    background: var(--surface-2);
    border-radius: var(--radius);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    animation: cardIn .35s ease both;
  }
  .uap-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
    border-color: rgba(79,110,247,.25);
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  /* stagger children */
  .uap-card:nth-child(1)  { animation-delay: .03s; }
  .uap-card:nth-child(2)  { animation-delay: .06s; }
  .uap-card:nth-child(3)  { animation-delay: .09s; }
  .uap-card:nth-child(4)  { animation-delay: .12s; }
  .uap-card:nth-child(5)  { animation-delay: .15s; }
  .uap-card:nth-child(6)  { animation-delay: .18s; }
  .uap-card:nth-child(7)  { animation-delay: .21s; }
  .uap-card:nth-child(8)  { animation-delay: .24s; }
  .uap-card:nth-child(9)  { animation-delay: .27s; }

  .uap-card-header {
    padding: 18px 20px 14px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    border-bottom: 1px solid var(--border);
  }
  .uap-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--accent-light);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 17px;
    flex-shrink: 0;
    letter-spacing: -.5px;
  }
  .uap-card-tenant-info { flex: 1; min-width: 0; }
  .uap-card-tenant-name {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 15px;
    color: var(--ink);
    margin: 0 0 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .uap-card-subdomain {
    font-size: 12px;
    color: var(--ink-3);
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .uap-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .2px;
    text-transform: uppercase;
    flex-shrink: 0;
  }
  .uap-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
  .uap-badge-active   { background: var(--success-light); color: #15803d; }
  .uap-badge-active::before { background: var(--success); }
  .uap-badge-suspended { background: var(--danger-light); color: #b91c1c; }
  .uap-badge-suspended::before { background: var(--danger); }
  .uap-badge-inactive { background: var(--warning-light); color: #92400e; }
  .uap-badge-inactive::before { background: var(--warning); }

  /* ── Pricing rows ── */
  .uap-card-pricing {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
  }
  .uap-price-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .uap-price-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    background: var(--surface);
    border-radius: 10px;
    padding: 9px 12px;
  }
  .uap-price-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .uap-price-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .4px;
    text-transform: uppercase;
    color: var(--ink-3);
    flex: 1;
  }
  .uap-price-value {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    font-size: 14px;
    color: var(--ink);
  }
  .uap-currency-tag {
    font-size: 11px;
    background: var(--surface);
    color: var(--ink-3);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2px 7px;
    font-weight: 500;
  }

  /* ── Card footer ── */
  .uap-card-footer {
    padding: 12px 20px 16px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
  }
  .uap-edit-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--accent);
    background: var(--accent-light);
    color: var(--accent);
    transition: all .18s ease;
    width: 100%;
    justify-content: center;
  }
  .uap-edit-btn:hover {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 4px 14px rgba(79,110,247,.30);
    transform: translateY(-1px);
  }
  .uap-edit-btn svg { transition: transform .18s; }
  .uap-edit-btn:hover svg { transform: rotate(-8deg) scale(1.1); }

  /* ── Empty state ── */
  .uap-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 64px 24px;
  }
  .uap-empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: .35;
  }
  .uap-empty-title {
    font-family: 'Syne', sans-serif;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 6px;
    color: var(--ink-2);
  }
  .uap-empty-text { font-size: 14px; color: var(--ink-3); margin: 0; }

  /* ── Pagination ── */
  .uap-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .uap-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    color: var(--ink-2);
    background: var(--surface-2);
    border: 1.5px solid var(--border);
    transition: all .15s;
  }
  .uap-page-btn:hover { border-color: var(--accent); color: var(--accent); }
  .uap-page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); font-weight: 700; }
  .uap-page-btn.disabled { opacity: .4; pointer-events: none; }
  .uap-page-btn.ellipsis { border: none; background: none; cursor: default; }
  .uap-pagination-meta {
    text-align: center;
    font-size: 12px;
    color: var(--ink-3);
    margin-top: 10px;
  }

  /* ── Modal ── */
  .uap-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15,17,23,.55);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn .2s ease;
  }
  .uap-modal-overlay.open { display: flex; }
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

  .uap-modal {
    background: var(--surface-2);
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 24px 64px rgba(0,0,0,.18);
    animation: modalIn .25s cubic-bezier(.34,1.56,.64,1);
    overflow: hidden;
  }
  @keyframes modalIn { from { opacity:0; transform:scale(.92) translateY(16px); } to { opacity:1; transform:scale(1) translateY(0); } }

  .uap-modal-header {
    padding: 24px 24px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
  }
  .uap-modal-title-group {}
  .uap-modal-eyebrow {
    font-size: 11px;
    letter-spacing: .6px;
    text-transform: uppercase;
    color: var(--accent);
    font-weight: 600;
    margin-bottom: 4px;
  }
  .uap-modal-title {
    font-family: 'Syne', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
  }
  .uap-modal-close {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: 1.5px solid var(--border);
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ink-3);
    font-size: 18px;
    transition: all .15s;
    flex-shrink: 0;
  }
  .uap-modal-close:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-light); }

  .uap-modal-body { padding: 24px; }

  .uap-tenant-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent-light);
    color: var(--accent-dark);
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 22px;
    width: 100%;
  }
  .uap-tenant-chip-avatar {
    width: 28px; height: 28px;
    border-radius: 7px;
    background: var(--accent);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
  }

  .uap-price-inputs { display: flex; flex-direction: column; gap: 16px; }

  .uap-field {}
  .uap-field-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-2);
    margin-bottom: 8px;
  }
  .uap-field-label-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .uap-field-hint { font-size: 11px; color: var(--ink-3); font-weight: 400; margin-left: auto; }
  .uap-input-group {
    display: flex;
    align-items: stretch;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    overflow: hidden;
    transition: border-color .2s, box-shadow .2s;
  }
  .uap-input-group:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,110,247,.12);
  }
  .uap-input-prefix {
    padding: 0 14px;
    background: var(--surface);
    color: var(--ink-3);
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    border-right: 1.5px solid var(--border);
    min-width: 42px;
    justify-content: center;
  }
  .uap-input {
    flex: 1;
    padding: 10px 14px;
    border: none;
    outline: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    color: var(--ink);
    background: transparent;
    min-width: 0;
  }

  .uap-modal-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: var(--radius-sm);
    padding: 12px 14px;
    font-size: 12.5px;
    color: #78350f;
    margin-top: 20px;
    line-height: 1.5;
  }
  .uap-modal-notice-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }

  .uap-modal-footer {
    padding: 16px 24px 24px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }
  .uap-btn-cancel {
    padding: 10px 22px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: transparent;
    color: var(--ink-2);
    transition: all .15s;
  }
  .uap-btn-cancel:hover { border-color: var(--ink-2); }
  .uap-btn-save {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 24px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: var(--accent);
    color: #fff;
    transition: all .18s ease;
  }
  .uap-btn-save:hover { background: var(--accent-dark); box-shadow: 0 4px 14px rgba(79,110,247,.35); transform: translateY(-1px); }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="uap-wrapper">

          <!-- Page header -->
          <div class="uap-page-header">
            <div class="uap-page-header-left">
              <ul class="uap-breadcrumb">
                <li><a href="dashboard.php"><i class="feather icon-home"></i> Home</a></li>
                <li><a href="javascript:void(0)">Settings</a></li>
                <li>User Addon Pricing</li>
              </ul>
              <h1 class="uap-page-title">User Addon Pricing</h1>
              <p class="uap-page-subtitle">Set per-tenant pricing for additional user seat add-ons</p>
            </div>
            <div class="uap-tenant-count">
              <strong><?= $total_items ?></strong>
              <span>tenants</span>
            </div>
          </div>

          <!-- Alerts -->
          <?php if (isset($success)): ?>
          <div class="uap-alert uap-alert-success">
            <span class="uap-alert-icon">✓</span>
            <span><?= htmlspecialchars($success) ?></span>
            <button class="uap-alert-close" onclick="this.parentElement.remove()">×</button>
          </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
          <div class="uap-alert uap-alert-danger">
            <span class="uap-alert-icon">⚠</span>
            <div>
              <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
              <?php endforeach; ?>
            </div>
            <button class="uap-alert-close" onclick="this.parentElement.remove()">×</button>
          </div>
          <?php endif; ?>

          <!-- Search row -->
          <form method="GET" action="manage_user_addon_pricing.php">
            <div class="uap-search-row">
              <div class="uap-search-wrap">
                <span class="uap-search-icon">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input type="text" class="uap-search-input" name="search"
                       placeholder="Search by name or subdomain…"
                       value="<?= htmlspecialchars($search_query) ?>">
              </div>
              <button type="submit" class="uap-btn uap-btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Search
              </button>
              <?php if (!empty($search_query)): ?>
              <a href="manage_user_addon_pricing.php" class="uap-btn uap-btn-ghost">Clear</a>
              <?php endif; ?>
              <?php if (!empty($search_query)): ?>
              <span class="uap-results-label"><?= $total_items ?> result<?= $total_items !== 1 ? 's' : '' ?> for "<strong><?= htmlspecialchars($search_query) ?></strong>"</span>
              <?php endif; ?>
            </div>
          </form>

          <!-- Tenant cards grid -->
          <div class="uap-grid">
            <?php if (empty($tenants)): ?>
            <div class="uap-empty">
              <div class="uap-empty-icon">🏢</div>
              <h3 class="uap-empty-title">No tenants found</h3>
              <p class="uap-empty-text">Try adjusting your search query</p>
            </div>
            <?php else: ?>
            <?php foreach ($tenants as $tenant):
              $monthly    = $tenant['user_addon_monthly_price']   ?? $default_monthly;
              $quarterly  = $tenant['user_addon_quarterly_price'] ?? $default_quarterly;
              $yearly     = $tenant['user_addon_yearly_price']    ?? $default_yearly;
              $currency   = $tenant['currency'] ?? 'USD';
              $symbol     = getUserAddonCurrencySymbol($currency);
              $initials   = strtoupper(substr($tenant['name'], 0, 2));
              $statusClass = match($tenant['status']) {
                'active'    => 'uap-badge-active',
                'suspended' => 'uap-badge-suspended',
                default     => 'uap-badge-inactive',
              };
            ?>
            <div class="uap-card">
              <div class="uap-card-header">
                <div class="uap-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="uap-card-tenant-info">
                  <h3 class="uap-card-tenant-name"><?= htmlspecialchars($tenant['name']) ?></h3>
                  <div class="uap-card-subdomain">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    <?= htmlspecialchars($tenant['subdomain']) ?>
                  </div>
                </div>
                <span class="uap-badge <?= $statusClass ?>"><?= htmlspecialchars($tenant['status']) ?></span>
              </div>

              <div class="uap-card-pricing">
                <div class="uap-price-row">
                  <div class="uap-price-pill">
                    <span class="uap-price-dot" style="background:var(--monthly)"></span>
                    <span class="uap-price-label">Monthly</span>
                    <span class="uap-price-value"><?= $symbol . number_format($monthly, 2) ?></span>
                  </div>
                  <span class="uap-currency-tag"><?= htmlspecialchars($currency) ?></span>
                </div>
                <div class="uap-price-row">
                  <div class="uap-price-pill">
                    <span class="uap-price-dot" style="background:var(--quarterly)"></span>
                    <span class="uap-price-label">Quarterly</span>
                    <span class="uap-price-value"><?= $symbol . number_format($quarterly, 2) ?></span>
                  </div>
                </div>
                <div class="uap-price-row">
                  <div class="uap-price-pill">
                    <span class="uap-price-dot" style="background:var(--yearly)"></span>
                    <span class="uap-price-label">Yearly</span>
                    <span class="uap-price-value"><?= $symbol . number_format($yearly, 2) ?></span>
                  </div>
                </div>
              </div>

              <div class="uap-card-footer">
                <button type="button" class="uap-edit-btn"
                        data-tenant-id="<?= $tenant['id'] ?>"
                        data-tenant-name="<?= htmlspecialchars($tenant['name']) ?>"
                        data-initials="<?= htmlspecialchars($initials) ?>"
                        data-monthly="<?= $monthly ?>"
                        data-quarterly="<?= $quarterly ?>"
                        data-yearly="<?= $yearly ?>"
                        data-currency="<?= htmlspecialchars($currency) ?>"
                        data-symbol="<?= htmlspecialchars($symbol) ?>"
                        onclick="openEditModal(this)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit Pricing
                </button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <div class="uap-pagination">
            <a class="uap-page-btn <?= $current_page === 1 ? 'disabled' : '' ?>"
               href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <?php
              $sp = max(1, $current_page - 2);
              $ep = min($total_pages, $current_page + 2);
              if ($sp > 1): ?>
                <a class="uap-page-btn" href="?page=1<?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>">1</a>
                <?php if ($sp > 2): ?><span class="uap-page-btn ellipsis">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $sp; $i <= $ep; $i++): ?>
              <a class="uap-page-btn <?= $i === $current_page ? 'active' : '' ?>"
                 href="?page=<?= $i ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($ep < $total_pages): ?>
              <?php if ($ep < $total_pages - 1): ?><span class="uap-page-btn ellipsis">…</span><?php endif; ?>
              <a class="uap-page-btn" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
            <?php endif; ?>
            <a class="uap-page-btn <?= $current_page === $total_pages ? 'disabled' : '' ?>"
               href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
          </div>
          <div class="uap-pagination-meta">
            Page <?= $current_page ?> of <?= $total_pages ?> · Showing <?= count($tenants) ?> of <?= $total_items ?> tenants
          </div>
          <?php endif; ?>

        </div><!-- /uap-wrapper -->
      </div>
    </div>
  </div>
</div>

<!-- Edit Pricing Modal -->
<div class="uap-modal-overlay" id="uapModalOverlay" onclick="closeModalOnOverlay(event)">
  <div class="uap-modal" role="dialog" aria-modal="true" aria-labelledby="uapModalTitle">
    <div class="uap-modal-header">
      <div class="uap-modal-title-group">
        <div class="uap-modal-eyebrow">User Addon Pricing</div>
        <h2 class="uap-modal-title" id="uapModalTitle">Edit Pricing</h2>
      </div>
      <button class="uap-modal-close" onclick="closeModal()" aria-label="Close">×</button>
    </div>

    <form method="POST" action="manage_user_addon_pricing.php" id="uapEditForm" onsubmit="return validateForm()">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="update_pricing">
      <input type="hidden" name="tenant_id" id="uap_tenant_id">

      <div class="uap-modal-body">
        <div class="uap-tenant-chip">
          <div class="uap-tenant-chip-avatar" id="uap_chip_avatar">AB</div>
          <span id="uap_tenant_name_display">Tenant Name</span>
        </div>

        <div class="uap-price-inputs">
          <div class="uap-field">
            <div class="uap-field-label">
              <span class="uap-field-label-dot" style="background:var(--monthly)"></span>
              Monthly Price
              <span class="uap-field-hint">per user / month</span>
            </div>
            <div class="uap-input-group">
              <div class="uap-input-prefix" id="uap_sym_monthly">$</div>
              <input type="number" class="uap-input" id="uap_monthly" name="monthly_price"
                     step="0.01" min="0.01" required placeholder="0.00">
            </div>
          </div>

          <div class="uap-field">
            <div class="uap-field-label">
              <span class="uap-field-label-dot" style="background:var(--quarterly)"></span>
              Quarterly Price
              <span class="uap-field-hint">per user / 3 months</span>
            </div>
            <div class="uap-input-group">
              <div class="uap-input-prefix" id="uap_sym_quarterly">$</div>
              <input type="number" class="uap-input" id="uap_quarterly" name="quarterly_price"
                     step="0.01" min="0.01" required placeholder="0.00">
            </div>
          </div>

          <div class="uap-field">
            <div class="uap-field-label">
              <span class="uap-field-label-dot" style="background:var(--yearly)"></span>
              Yearly Price
              <span class="uap-field-hint">per user / 12 months</span>
            </div>
            <div class="uap-input-group">
              <div class="uap-input-prefix" id="uap_sym_yearly">$</div>
              <input type="number" class="uap-input" id="uap_yearly" name="yearly_price"
                     step="0.01" min="0.01" required placeholder="0.00">
            </div>
          </div>
        </div>

        <div class="uap-modal-notice">
          <span class="uap-modal-notice-icon">ℹ</span>
          <span>These prices are shown to the tenant when they request additional user seats.</span>
        </div>
      </div>

      <div class="uap-modal-footer">
        <button type="button" class="uap-btn-cancel" onclick="closeModal()">Cancel</button>
        <button type="submit" class="uap-btn-save">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Pricing
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function openEditModal(btn) {
  const d = btn.dataset;
  document.getElementById('uap_tenant_id').value        = d.tenantId;
  document.getElementById('uap_tenant_name_display').textContent = d.tenantName;
  document.getElementById('uap_chip_avatar').textContent = d.initials || d.tenantName.substring(0,2).toUpperCase();
  document.getElementById('uap_monthly').value   = d.monthly;
  document.getElementById('uap_quarterly').value = d.quarterly;
  document.getElementById('uap_yearly').value    = d.yearly;
  const sym = d.symbol || d.currency || '$';
  document.getElementById('uap_sym_monthly').textContent   = sym;
  document.getElementById('uap_sym_quarterly').textContent = sym;
  document.getElementById('uap_sym_yearly').textContent    = sym;
  document.getElementById('uapModalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('uapModalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function closeModalOnOverlay(e) {
  if (e.target === document.getElementById('uapModalOverlay')) closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function validateForm() {
  const m = parseFloat(document.getElementById('uap_monthly').value);
  const q = parseFloat(document.getElementById('uap_quarterly').value);
  const y = parseFloat(document.getElementById('uap_yearly').value);
  if (m <= 0 || q <= 0 || y <= 0) {
    alert('All prices must be greater than 0.');
    return false;
  }
  if (q < m * 2.5 || y < q * 3) {
    return confirm('Warning: Your quarterly/yearly pricing seems low compared to monthly. Continue anyway?');
  }
  return true;
}
</script>

</body>
</html>