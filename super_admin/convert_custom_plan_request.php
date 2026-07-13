<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
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
require_once '../includes/feature-selector.php';

$request_id = intval($_GET['id'] ?? 0);
if (!$request_id) {
    header('Location: manage_custom_plan_requests.php?error=invalid_id');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM custom_plan_requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: manage_custom_plan_requests.php?error=not_found');
    exit();
}

if (!in_array($request['status'], ['approved', 'negotiating'])) {
    header('Location: view_custom_plan_request.php?id=' . $request_id . '&error=Request+must+be+approved+or+in+negotiation+to+convert');
    exit();
}

$selected_features = json_decode($request['selected_features'], true) ?? [];
$categories = getCustomFeatureCategories();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: convert_custom_plan_request.php?id=' . $request_id . '&error=invalid_csrf');
        exit();
    }

    $plan_name = trim($_POST['plan_name'] ?? '');
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $tenant_identifier = trim($_POST['tenant_identifier'] ?? '');
    $max_users = intval($_POST['max_users'] ?? $request['max_users']);
    $price = floatval($_POST['price'] ?? 0);
    $currency = $_POST['currency'] ?? $request['currency'];
    $trial_days = intval($_POST['trial_days'] ?? 14);

    $errors = [];

    if (empty($plan_name)) $errors[] = 'Plan name is required.';
    if (empty($tenant_name)) $errors[] = 'Tenant name is required.';
    if (empty($tenant_identifier)) $errors[] = 'Tenant identifier is required.';

    // Check if plan name exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ?");
    $stmt->execute([$plan_name]);
    if ($stmt->fetch()['count'] > 0) {
        $errors[] = 'Plan name already exists. Please choose a different name.';
    }

    // Check if tenant identifier exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE identifier = ?");
    $stmt->execute([$tenant_identifier]);
    if ($stmt->fetch()['count'] > 0) {
        $errors[] = 'Tenant identifier already exists.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Create the custom plan
            $features_json = json_encode($selected_features);
            $description = 'Custom plan for ' . htmlspecialchars($tenant_name) . ' - created from request #' . $request_id;
            $stmt = $pdo->prepare("INSERT INTO plans (name, description, features, price, currency, max_users, max_branches, trial_days, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 'active', NOW(), NOW())");
            $stmt->execute([$plan_name, $description, $features_json, $price, $currency, $max_users, $trial_days]);
            $plan_id = $pdo->lastInsertId();

            // 2. Create the tenant
            $stmt = $pdo->prepare("INSERT INTO tenants (name, identifier, plan, status, trial_days, trial_end_date, created_at, updated_at) VALUES (?, ?, ?, 'trial', ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW(), NOW())");
            $stmt->execute([$tenant_name, $tenant_identifier, $plan_name, $trial_days, $trial_days]);
            $tenant_id = $pdo->lastInsertId();

            // 3. Create tenant settings
            $stmt = $pdo->prepare("INSERT INTO settings (tenant_id, agency_name, title, phone, email, address, logo) VALUES (?, ?, ?, ?, ?, '', '')");
            $stmt->execute([$tenant_id, $tenant_name, $tenant_name, $request['contact_phone'], $request['contact_email']]);

            // 4. Update request status to converted
            $stmt = $pdo->prepare("UPDATE custom_plan_requests SET status = 'converted', converted_tenant_id = ?, negotiated_price = COALESCE(negotiated_price, ?), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$tenant_id, $price, $request_id]);

            $pdo->commit();

            // Audit log
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'convert_custom_plan_request', 'custom_plan_request', ?, ?, ?, NOW())");
            $details = json_encode(['request_id' => $request_id, 'plan_name' => $plan_name, 'tenant_id' => $tenant_id, 'price' => $price, 'currency' => $currency]);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->execute([$user_id, $request_id, $details, $ip_address]);

            header('Location: manage_custom_plan_requests.php?success=request_converted');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Convert Custom Plan Request Error: " . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error_msg = urlencode(implode(', ', $errors));
        header('Location: convert_custom_plan_request.php?id=' . $request_id . '&error=' . $error_msg);
        exit();
    }
}

require_once '../includes/header_super_admin.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m0 10v10l8 4"/></svg>
                                Convert Request #<?= $request['id'] ?> to Tenant
                            </h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Create a tenant with a custom plan from this request</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="view_custom_plan_request.php?id=<?= $request['id'] ?>" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>Back to Request
                            </a>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                </div>
                                <?php endif; ?>

                                <div class="row">
                                    <!-- Request Summary -->
                                    <div class="col-md-5">
                                        <div class="card">
                                            <div class="card-header"><h5>Request Summary</h5></div>
                                            <div class="card-body">
                                                <table style="width:100%;font-size:0.9rem;">
                                                    <tr><td style="padding:4px 0;font-weight:600;width:100px;">Name</td><td><?= htmlspecialchars($request['contact_name']) ?></td></tr>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Email</td><td><?= htmlspecialchars($request['contact_email']) ?></td></tr>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Phone</td><td><?= htmlspecialchars($request['contact_phone']) ?></td></tr>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Agency</td><td><?= htmlspecialchars($request['agency_name'] ?: $request['contact_name']) ?></td></tr>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Users</td><td><?= $request['max_users'] ?></td></tr>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Features</td><td><?= count($selected_features) ?> selected</td></tr>
                                                    <?php if ($request['negotiated_price']): ?>
                                                    <tr><td style="padding:4px 0;font-weight:600;">Price</td><td><?= htmlspecialchars($request['currency'] ?: 'AFN') ?> <?= number_format($request['negotiated_price'], 2) ?></td></tr>
                                                    <?php endif; ?>
                                                </table>

                                                <div style="margin-top:16px;">
                                                    <h6 style="font-size:0.85rem;font-weight:600;margin-bottom:8px;">Selected Features:</h6>
                                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                                        <?php foreach ($selected_features as $feat):
                                                            $label = $feat;
                                                            foreach ($categories as $cat) {
                                                                if (isset($cat['features'][$feat])) {
                                                                    $label = $cat['features'][$feat];
                                                                    break;
                                                                }
                                                            }
                                                        ?>
                                                        <span style="font-size:0.72rem;padding:2px 8px;border-radius:4px;background:rgba(64,153,255,0.1);color:#4099ff;"><?= htmlspecialchars($label) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Conversion Form -->
                                    <div class="col-md-7">
                                        <div class="card">
                                            <div class="card-header"><h5>Create Tenant & Plan</h5></div>
                                            <div class="card-body">
                                                <form method="POST" action="convert_custom_plan_request.php?id=<?= $request_id ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Plan Name <span style="color:red">*</span></label>
                                                        <input type="text" name="plan_name" class="form-control" required
                                                               value="Custom - <?= htmlspecialchars($request['agency_name'] ?: $request['contact_name']) ?>"
                                                               placeholder="e.g. Custom - Agency Name">
                                                        <small style="color:#6b7280;">This will be the plan name in the system</small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Tenant Name <span style="color:red">*</span></label>
                                                        <input type="text" name="tenant_name" class="form-control" required
                                                               value="<?= htmlspecialchars($request['agency_name'] ?: '') ?>"
                                                               placeholder="Agency name">
                                                    </div>

                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Tenant Identifier <span style="color:red">*</span></label>
                                                        <input type="text" name="tenant_identifier" class="form-control" required
                                                               placeholder="e.g. agency-name">
                                                        <small style="color:#6b7280;">Unique identifier (lowercase, hyphens allowed). Used for subdomain.</small>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label style="font-weight:600;">Price</label>
                                                                <input type="number" step="0.01" min="0" name="price" class="form-control"
                                                                       value="<?= htmlspecialchars($request['negotiated_price'] ?? '0') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label style="font-weight:600;">Currency</label>
                                                                <select name="currency" class="form-control">
                                                                    <option value="AFN" <?php echo ($request['currency'] ?? 'AFN') === 'AFN' ? 'selected' : ''; ?>>AFN</option>
                                                                    <option value="USD" <?php echo ($request['currency'] ?? 'AFN') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label style="font-weight:600;">Max Users</label>
                                                                <input type="number" min="1" name="max_users" class="form-control"
                                                                       value="<?= $request['max_users'] ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label style="font-weight:600;">Trial Days</label>
                                                        <input type="number" min="0" name="trial_days" class="form-control" value="14">
                                                    </div>

                                                    <button type="submit" class="btn btn-success">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><polyline points="20 6 9 17 4 12"/></svg>
                                                        Create Tenant with Custom Plan
                                                    </button>
                                                    <a href="view_custom_plan_request.php?id=<?= $request['id'] ?>" class="btn btn-outline-secondary" style="margin-left:8px;">Cancel</a>
                                                </form>
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
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
