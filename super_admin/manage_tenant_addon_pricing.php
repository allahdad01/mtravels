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
function getCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => '؋',
        'AED' => 'د.إ',
        'INR' => '₹',
        'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

// Handle form submission for updating pricing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_tenant_addon_pricing.php?error=invalid_csrf');
        exit();
    }
    
    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $monthly_price = floatval($_POST['monthly_price'] ?? 0);
    $quarterly_price = floatval($_POST['quarterly_price'] ?? 0);
    $yearly_price = floatval($_POST['yearly_price'] ?? 0);
    
    $errors = [];
    
    // Validate input
    if ($tenant_id <= 0) {
        $errors[] = 'Invalid tenant ID.';
    }
    if ($monthly_price <= 0) {
        $errors[] = 'Monthly price must be greater than 0.';
    }
    if ($quarterly_price <= 0) {
        $errors[] = 'Quarterly price must be greater than 0.';
    }
    if ($yearly_price <= 0) {
        $errors[] = 'Yearly price must be greater than 0.';
    }
    
    // Check if tenant exists
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$tenant_id]);
    if (!$stmt->fetch()) {
        $errors[] = 'Tenant not found.';
    }
    
    if (empty($errors)) {
        try {
            // Update or insert pricing
            $stmt = $pdo->prepare("
                UPDATE settings 
                SET monthly_price = ?, 
                    quarterly_price = ?, 
                    yearly_price = ?
                WHERE tenant_id = ?
            ");
            $stmt->execute([$monthly_price, $quarterly_price, $yearly_price, $tenant_id]);
            
            $success = 'Branch addon pricing updated successfully!';
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Pagination and search
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

// Count total items
$count_query = "
    SELECT COUNT(*) as total FROM tenants t
    WHERE t.status != 'deleted'
";
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
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated tenants with their current pricing
$query = "
    SELECT 
        t.id,
        t.name,
        t.subdomain,
        t.status,
        s.monthly_price,
        s.quarterly_price,
        s.yearly_price,
        ts.currency
    FROM tenants t
    LEFT JOIN settings s ON t.id = s.tenant_id
    LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id
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

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-header-content">
                                <h5 class="page-title mb-0">
                                    <i class="feather icon-dollar-sign mr-2"></i>Tenant Addon Pricing
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Manage pricing for additional branch add-ons per tenant
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-arrow-left mr-1"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <!-- Success/Error Alerts -->
                                <?php if (isset($success)): ?>
                                <div class="sa-alert sa-alert-success">
                                    <div class="sa-alert-icon">✓</div>
                                    <div class="sa-alert-content">
                                        <?= htmlspecialchars($success) ?>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($errors)): ?>
                                <div class="sa-alert sa-alert-danger">
                                    <div class="sa-alert-icon">⚠</div>
                                    <div class="sa-alert-content">
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Search and Filter Bar -->
                                <div class="sa-card" style="margin-bottom: 20px;">
                                    <div class="sa-card-body">
                                        <form method="GET" action="manage_tenant_addon_pricing.php" class="sa-search-filter">
                                            <div class="sa-search-group">
                                                <input type="text" class="sa-search-input" name="search" placeholder="Search tenant name or subdomain..." value="<?= htmlspecialchars($search_query) ?>">
                                                <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                                <?php if (!empty($search_query)): ?>
                                                <a href="manage_tenant_addon_pricing.php" class="sa-btn sa-btn-ghost">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Pricing Header -->
                                <div class="sa-shdr" style="margin-bottom: 16px;">
                                    <div>
                                        <h2>Pricing Configuration</h2>
                                        <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted);">Total: <?= $total_items ?> tenants</p>
                                    </div>
                                </div>

                                <!-- Pricing List -->
                                <?php if (empty($tenants)): ?>
                                <div class="sa-card">
                                    <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                        <div style="font-size: 2rem; margin-bottom: 12px;">📋</div>
                                        <div style="font-weight: 600; margin-bottom: 4px;">No Tenants Found</div>
                                        <div style="font-size: 0.8rem;"><?= !empty($search_query) ? 'Try adjusting your search filters.' : 'No tenants available for pricing configuration.' ?></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="sa-pricing-list">
                                    <?php foreach ($tenants as $tenant): ?>
                                    <div class="sa-pricing-card">
                                        <div class="spc-header">
                                            <div class="spc-info">
                                                <h4><?= htmlspecialchars($tenant['name']) ?></h4>
                                                <p class="spc-subdomain"><?= htmlspecialchars($tenant['subdomain']) ?> • <span class="pill <?= $tenant['status'] === 'active' ? 'pill-green' : 'pill-amber' ?>"><?= htmlspecialchars($tenant['status']) ?></span></p>
                                            </div>
                                        </div>
                                        
                                        <div class="spc-pricing">
                                            <?php 
                                            $symbol = getCurrencySymbol($tenant['currency'] ?? 'USD');
                                            $monthly = $tenant['monthly_price'] ?: 'Not set';
                                            $quarterly = $tenant['quarterly_price'] ?: 'Not set';
                                            $yearly = $tenant['yearly_price'] ?: 'Not set';
                                            if ($tenant['monthly_price']) {
                                                $monthly = $symbol . number_format($tenant['monthly_price'], 2);
                                                $quarterly = $symbol . number_format($tenant['quarterly_price'], 2);
                                                $yearly = $symbol . number_format($tenant['yearly_price'], 2);
                                            }
                                            ?>
                                            <div class="price-item">
                                                <span class="price-label">Monthly</span>
                                                <span class="price-value"><?= $monthly ?></span>
                                            </div>
                                            <div class="price-item">
                                                <span class="price-label">Quarterly</span>
                                                <span class="price-value"><?= $quarterly ?></span>
                                            </div>
                                            <div class="price-item">
                                                <span class="price-label">Yearly</span>
                                                <span class="price-value"><?= $yearly ?></span>
                                            </div>
                                            <div class="price-action">
                                                <button type="button" class="sa-btn sa-btn-small sa-btn-primary edit-pricing-btn" 
                                                        data-tenant-id="<?= $tenant['id'] ?>"
                                                        data-tenant-name="<?= htmlspecialchars($tenant['name']) ?>"
                                                        data-monthly="<?= $tenant['monthly_price'] ?? 50 ?>"
                                                        data-quarterly="<?= $tenant['quarterly_price'] ?? 150 ?>"
                                                        data-yearly="<?= $tenant['yearly_price'] ?? 600 ?>"
                                                        data-currency="<?= htmlspecialchars($tenant['currency'] ?? 'USD') ?>"
                                                        data-toggle="modal" 
                                                        data-target="#editPricingModal">
                                                    <i class="feather icon-edit mr-1"></i>Edit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                <div class="sa-pagination">
                                                <?php 
                                                $query_string = '';
                                                if (!empty($search_query)) $query_string .= '&search=' . urlencode($search_query);
                                                
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                ?>
                                                
                                                <?php if ($current_page > 1): ?>
                                                <a href="?page=1<?= $query_string ?>" class="sa-pagination-item">First</a>
                                                <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="sa-pagination-item">← Prev</a>
                                                <?php endif; ?>
                                                
                                                <?php if ($start_page > 1): ?>
                                                <span class="sa-pagination-ellipsis">...</span>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $current_page ? 'active' : '' ?>">
                                                <?= $i ?>
                                                </a>
                                                <?php endfor; ?>
                                                
                                                <?php if ($end_page < $total_pages): ?>
                                                <span class="sa-pagination-ellipsis">...</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($current_page < $total_pages): ?>
                                                <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-pagination-item">Next →</a>
                                                <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-pagination-item">Last</a>
                                                <?php endif; ?>
                                                
                                                <span class="sa-pagination-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
                                                </div>
                                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Pricing Modal -->
<div class="modal fade" id="editPricingModal" tabindex="-1" role="dialog" aria-labelledby="editPricingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editPricingModalLabel">Edit Branch Addon Pricing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="manage_tenant_addon_pricing.php" id="editPricingForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="tenant_id" id="edit_tenant_id">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Tenant:</strong> <span id="modal_tenant_name"></span>
                    </div>
                    
                    <div class="form-group">
                         <label for="edit_monthly_price">Monthly Price <span class="text-danger">*</span></label>
                         <div class="input-group">
                             <span class="input-group-text" id="currency_symbol_monthly">$</span>
                             <input type="number" class="form-control" id="edit_monthly_price" name="monthly_price" 
                                    step="0.01" min="0.01" required>
                         </div>
                         <small class="form-text text-muted">Price per additional branch per month</small>
                     </div>
                     
                     <div class="form-group">
                         <label for="edit_quarterly_price">Quarterly Price <span class="text-danger">*</span></label>
                         <div class="input-group">
                             <span class="input-group-text" id="currency_symbol_quarterly">$</span>
                             <input type="number" class="form-control" id="edit_quarterly_price" name="quarterly_price" 
                                    step="0.01" min="0.01" required>
                         </div>
                         <small class="form-text text-muted">Price per additional branch for 3 months</small>
                     </div>
                     
                     <div class="form-group">
                         <label for="edit_yearly_price">Yearly Price <span class="text-danger">*</span></label>
                         <div class="input-group">
                             <span class="input-group-text" id="currency_symbol_yearly">$</span>
                             <input type="number" class="form-control" id="edit_yearly_price" name="yearly_price" 
                                    step="0.01" min="0.01" required>
                         </div>
                         <small class="form-text text-muted">Price per additional branch for 12 months</small>
                     </div>
                    
                    <div class="alert alert-warning">
                        <i class="feather icon-info"></i>
                        <strong>Note:</strong> These prices are displayed to the tenant when requesting additional branches.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-1"></i> Save Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
}

/* ─── PAGE HEADER ────────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 2rem 2.5rem;
    border: none;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header.card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card .row {
    position: relative;
    z-index: 1;
}

.page-header-content {
    padding: 0.5rem 0;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
}

.page-title i {
    font-size: 2rem;
    margin-right: 0.75rem;
    opacity: 0.95;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.10) !important;
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.30) !important;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.22) !important;
    border-color: rgba(255,255,255,0.50) !important;
    transform: translateY(-1px);
}

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 10px;
    border: none;
    margin-bottom: 1.5rem;
}

.sa-alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.sa-alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.sa-alert-icon {
    flex-shrink: 0;
    font-weight: bold;
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
}

.sa-alert-content {
    flex: 1;
    align-self: center;
}

.sa-alert-close {
    flex-shrink: 0;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: inherit;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.2s ease;
}

.sa-alert-close:hover {
    opacity: 0.7;
}

/* ─── CARDS ───────────────────────────────────────────────── */
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

/* ─── BUTTONS ─────────────────────────────────────────────── */
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

/* ─── SEARCH & FILTER ─────────────────────────────────────── */
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
    min-width: 300px;
}

.sa-search-input {
    flex: 1;
    min-width: 150px;
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

/* ─── SECTION HEADER ──────────────────────────────────────── */
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
    color: var(--muted);
}

/* ─── PRICING LIST ────────────────────────────────────────── */
.sa-pricing-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sa-pricing-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
}

.sa-pricing-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
}

.spc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.spc-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #333;
}

.spc-subdomain {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.spc-pricing {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 16px;
    align-items: center;
}

.price-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.price-label {
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
}

.price-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #4099ff;
}

.price-action {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* ─── PILLS ───────────────────────────────────────────────── */
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

/* ─── PAGINATION ──────────────────────────────────────────── */
.sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    flex-wrap: wrap;
}

.sa-pagination-item {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.sa-pagination-item:hover:not(.active) {
    background: rgba(64, 153, 255, 0.1);
    border-color: #4099ff;
    color: #4099ff;
}

.sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: #4099ff;
    color: white;
}

.sa-pagination-ellipsis {
    color: #999;
    font-size: 0.8rem;
}

.sa-pagination-info {
    font-size: 0.8rem;
    color: #999;
    margin-left: auto;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 1024px) {
    .spc-pricing {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .spc-header {
        flex-direction: column;
    }
    
    .spc-pricing {
        grid-template-columns: 1fr;
    }
    
    .price-action {
        width: 100%;
    }
    
    .page-header.card {
        padding: 1.5rem;
    }
}
</style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Currency symbol mapping
const currencySymbols = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'JPY': '¥',
    'AFN': '؋',
    'AED': 'د.إ',
    'INR': '₹',
    'PKR': '₨',
};

// Handle edit pricing button click
$(document).on('click', '.edit-pricing-btn', function() {
    const tenantId = $(this).data('tenant-id');
    const tenantName = $(this).data('tenant-name');
    const monthly = $(this).data('monthly');
    const quarterly = $(this).data('quarterly');
    const yearly = $(this).data('yearly');
    const currency = $(this).data('currency') || 'USD';
    
    // Get currency symbol
    const symbol = currencySymbols[currency] || currency;
    
    // Populate form
    $('#edit_tenant_id').val(tenantId);
    $('#modal_tenant_name').text(tenantName);
    $('#edit_monthly_price').val(monthly);
    $('#edit_quarterly_price').val(quarterly);
    $('#edit_yearly_price').val(yearly);
    
    // Update currency symbols
    $('#currency_symbol_monthly').text(symbol);
    $('#currency_symbol_quarterly').text(symbol);
    $('#currency_symbol_yearly').text(symbol);
});

// Form validation
$('#editPricingForm').on('submit', function(e) {
    const monthly = parseFloat($('#edit_monthly_price').val());
    const quarterly = parseFloat($('#edit_quarterly_price').val());
    const yearly = parseFloat($('#edit_yearly_price').val());
    
    if (monthly <= 0 || quarterly <= 0 || yearly <= 0) {
        e.preventDefault();
        alert('All prices must be greater than 0');
        return false;
    }
    
    // Optional: Validate pricing hierarchy
    if (quarterly < monthly * 2.5 || yearly < quarterly * 3) {
        const confirm_text = 'Warning: Your quarterly/yearly pricing may be too low compared to monthly pricing. Continue anyway?';
        if (!confirm(confirm_text)) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

</body>
</html>
