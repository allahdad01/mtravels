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
        header('Location: manage_user_addon_pricing.php?error=invalid_csrf');
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
            // Check if settings exist for this tenant
            $stmt = $pdo->prepare("SELECT tenant_id FROM settings WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            
            if ($stmt->fetch()) {
                // Update existing settings
                $stmt = $pdo->prepare("
                    UPDATE settings 
                    SET user_addon_monthly_price = ?, 
                        user_addon_quarterly_price = ?, 
                        user_addon_yearly_price = ?,
                        updated_at = NOW()
                    WHERE tenant_id = ?
                ");
                $stmt->execute([$monthly_price, $quarterly_price, $yearly_price, $tenant_id]);
            } else {
                // Insert new settings
                $stmt = $pdo->prepare("
                    INSERT INTO settings 
                    (tenant_id, user_addon_monthly_price, user_addon_quarterly_price, user_addon_yearly_price, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$tenant_id, $monthly_price, $quarterly_price, $yearly_price]);
            }
            
            $success = 'User addon pricing updated successfully!';
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

// Default pricing values
$default_monthly = 25.00;
$default_quarterly = 75.00;
$default_yearly = 300.00;

// Fetch paginated tenants with their current user addon pricing
$query = "
    SELECT 
        t.id,
        t.name,
        t.subdomain,
        t.status,
        s.user_addon_monthly_price,
        s.user_addon_quarterly_price,
        s.user_addon_yearly_price,
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

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Manage Tenant User Addon Pricing</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:void(0)">Settings</a></li>
                                    <li class="breadcrumb-item"><a href="javascript:void(0)">User Addon Pricing</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($success)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="feather icon-check-circle"></i> <?= htmlspecialchars($success) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="feather icon-alert-circle"></i>
                                    <ul class="mb-0 ml-3">
                                        <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5>User Addon Pricing by Tenant <span class="badge badge-info"><?= $total_items ?> total</span></h5>
                                        <span class="text-muted">Manage the pricing for additional user add-ons per tenant</span>
                                    </div>
                                    <div class="card-body table-border-style">
                                        <!-- Search Form -->
                                        <div class="mb-3">
                                            <form method="GET" action="manage_user_addon_pricing.php" class="form-inline">
                                                <input type="text" class="form-control mr-2" name="search" placeholder="Tenant name or subdomain..." value="<?= htmlspecialchars($search_query) ?>" style="width: 300px;">
                                                <button type="submit" class="btn btn-primary mr-2">Search</button>
                                                <?php if (!empty($search_query)): ?>
                                                <a href="manage_user_addon_pricing.php" class="btn btn-secondary">Clear</a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Tenant Name</th>
                                                        <th>Subdomain</th>
                                                        <th>Status</th>
                                                        <th>Monthly Price</th>
                                                        <th>Quarterly Price</th>
                                                        <th>Yearly Price</th>
                                                        <th>Currency</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($tenants as $tenant): ?>
                                                    <?php 
                                                        $monthly = $tenant['user_addon_monthly_price'] ?? $default_monthly;
                                                        $quarterly = $tenant['user_addon_quarterly_price'] ?? $default_quarterly;
                                                        $yearly = $tenant['user_addon_yearly_price'] ?? $default_yearly;
                                                        $currency = $tenant['currency'] ?? 'USD';
                                                        $symbol = getUserAddonCurrencySymbol($currency);
                                                    ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($tenant['name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($tenant['subdomain']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $tenant['status'] === 'active' ? 'success' : ($tenant['status'] === 'suspended' ? 'danger' : 'warning') ?>">
                                                                <?= htmlspecialchars($tenant['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="price-display" data-tenant-id="<?= $tenant['id'] ?>">
                                                                <?= $symbol . number_format($monthly, 2) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="price-display" data-tenant-id="<?= $tenant['id'] ?>">
                                                                <?= $symbol . number_format($quarterly, 2) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="price-display" data-tenant-id="<?= $tenant['id'] ?>">
                                                                <?= $symbol . number_format($yearly, 2) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($currency) ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary edit-pricing-btn" 
                                                                    data-tenant-id="<?= $tenant['id'] ?>"
                                                                    data-tenant-name="<?= htmlspecialchars($tenant['name']) ?>"
                                                                    data-monthly="<?= $monthly ?>"
                                                                    data-quarterly="<?= $quarterly ?>"
                                                                    data-yearly="<?= $yearly ?>"
                                                                    data-currency="<?= htmlspecialchars($currency) ?>"
                                                                    data-toggle="modal" 
                                                                    data-target="#editPricingModal">
                                                                <i class="feather icon-edit"></i> Edit Pricing
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($tenants)): ?>
                                                    <tr><td colspan="8" class="text-center">No tenants found</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Previous</a>
                                        </li>
                                        <?php 
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        if ($start_page > 1): ?>
                                        <li class="page-item">
                                        <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Next</a>
                                        </li>
                                        </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($tenants) ?> of <?= $total_items ?> tenants
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
    </div>
</div>

<!-- Edit Pricing Modal -->
<div class="modal fade" id="editPricingModal" tabindex="-1" role="dialog" aria-labelledby="editPricingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editPricingModalLabel">Edit User Addon Pricing</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="manage_user_addon_pricing.php" id="editPricingForm">
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
                        <small class="form-text text-muted">Price per additional user per month</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_quarterly_price">Quarterly Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_quarterly">$</span>
                            <input type="number" class="form-control" id="edit_quarterly_price" name="quarterly_price" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <small class="form-text text-muted">Price per additional user for 3 months</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_yearly_price">Yearly Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" id="currency_symbol_yearly">$</span>
                            <input type="number" class="form-control" id="edit_yearly_price" name="yearly_price" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <small class="form-text text-muted">Price per additional user for 12 months</small>
                    </div>
                   
                    <div class="alert alert-warning">
                        <i class="feather icon-info"></i>
                        <strong>Note:</strong> These prices are displayed to the tenant when requesting additional users.
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Currency symbol mapping
const userAddonCurrencySymbols = {
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
    const symbol = userAddonCurrencySymbols[currency] || currency;
    
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
