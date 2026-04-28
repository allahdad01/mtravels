<?php
session_start();
require_once '../includes/db.php';

// Function to generate unique identifier
function generateUniqueIdentifier($pdo, $name) {
    // Extract first 3-4 letters from name for readability
    $prefix = strtoupper(preg_replace('/[^a-zA-Z]/', '', substr($name, 0, 10)));
    // Ensure prefix is at least 3 chars, pad if needed
    $prefix = substr($prefix, 0, 4);
    if (strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'T');
    }
    
    // Generate random suffix (6 alphanumeric chars)
    $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    
    $identifier = $prefix . '-' . $suffix;
    
    // Check if identifier exists, if so, regenerate
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE identifier = ?");
    $stmt->execute([$identifier]);
    if ($stmt->fetch()['count'] > 0) {
        // Recursively generate new identifier if collision occurs
        return generateUniqueIdentifier($pdo, $name);
    }
    
    return $identifier;
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_tenants.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

$name = $_POST['name'] ?? '';
$plan = $_POST['plan'] ?? '';
$billing_email = $_POST['billing_email'] ?? '';
$agency_name = $_POST['agency_name'] ?? '';
$title = $_POST['title'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$has_trial = isset($_POST['has_trial']) ? true : false;
$trial_days = intval($_POST['trial_days'] ?? 0);
$errors = [];

// Validate input
if (empty($name) || empty($plan) || empty($billing_email) || empty($agency_name) || empty($title)) {
    $errors[] = "All required fields must be filled.";
}
if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid billing email format.";
}
if ($has_trial && ($trial_days < 1 || $trial_days > 365)) {
    $errors[] = "Trial days must be between 1 and 365.";
}

// Auto-generate unique identifier
$identifier = generateUniqueIdentifier($pdo, $name);
// Verify plan exists and get plan details
$stmt = $pdo->prepare("SELECT id, price, currency, trial_days FROM plans WHERE name = ? AND status = 'active'");
$stmt->execute([$plan]);
$plan_details = $stmt->fetch();
if (!$plan_details) {
    $errors[] = "Invalid or inactive plan selected.";
}

// Determine tenant status and trial dates
$tenant_status = 'active';
$trial_end_date = null;
$actual_trial_days = 0;

if ($has_trial && $trial_days > 0 && empty($errors)) {
    $tenant_status = 'trial';
    $actual_trial_days = $trial_days;
    $trial_end_date = date('Y-m-d', strtotime("+{$trial_days} days"));
}

if (empty($errors)) {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Insert new tenant with trial info
        $stmt = $pdo->prepare("INSERT INTO tenants (name, identifier, status, plan, trial_days, trial_end_date, billing_email, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $identifier, $tenant_status, $plan, $actual_trial_days, $trial_end_date, $billing_email]);
        $tenant_id = $pdo->lastInsertId();

        // Insert settings for the new tenant
        $stmt = $pdo->prepare("INSERT INTO settings (tenant_id, agency_name, title, phone, email, address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $agency_name, $title, $phone, $billing_email, $address]);

        // Generate temporary password (12+ characters for security)
        $temp_password = bin2hex(random_bytes(6)) . strtoupper(bin2hex(random_bytes(2))) . '!1';
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Create tenant super admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, name, email, password, role, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'tenant_super_admin', NOW(), NOW())
        ");
        $stmt->execute([$tenant_id, $agency_name, $billing_email, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Plan details already fetched above
        // Create automatic subscription for the tenant
        $billing_cycle = 'monthly'; // Default billing cycle
        $currency = $plan_details['currency'] ?? 'USD';
        $amount = $plan_details['price'] ?? 0;
        
        if ($has_trial && $trial_days > 0) {
            // Trial: subscription starts after trial ends, status is 'trial'
            $start_date = $trial_end_date;
            $end_date = date('Y-m-d', strtotime("+1 month", strtotime($start_date)));
            $next_billing_date = $start_date; // First payment due when trial ends
            
            $stmt = $pdo->prepare("
                INSERT INTO tenant_subscriptions
                (tenant_id, plan_id, status, billing_cycle, start_date, end_date,
                 amount, currency, payment_method, next_billing_date, created_at, updated_at)
                VALUES (?, ?, 'trial', ?, ?, ?, ?, ?, '', ?, NOW(), NOW())
            ");
            $stmt->execute([$tenant_id, $plan_details['id'], $billing_cycle, $start_date, $end_date, $amount, $currency, $next_billing_date]);
        } else {
            // No trial: subscription starts immediately
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+1 month", strtotime($start_date)));
            $next_billing_date = $end_date;
            
            $stmt = $pdo->prepare("
                INSERT INTO tenant_subscriptions
                (tenant_id, plan_id, status, billing_cycle, start_date, end_date,
                 amount, currency, payment_method, next_billing_date, created_at, updated_at)
                VALUES (?, ?, 'active', ?, ?, ?, ?, ?, '', ?, NOW(), NOW())
            ");
            $stmt->execute([$tenant_id, $plan_details['id'], $billing_cycle, $start_date, $end_date, $amount, $currency, $next_billing_date]);
        }

        // Send welcome email with login credentials
        require_once '../includes/functions.php';
        sendTenantWelcomeEmailWithCredentials($billing_email, $name, $agency_name, $temp_password);

        // Log action
        $admin_user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'create_tenant', 'tenant', ?, ?, ?, NOW())");
        $details = json_encode([
            'name' => $name,
            'identifier' => $identifier,
            'plan' => $plan,
            'has_trial' => $has_trial,
            'trial_days' => $actual_trial_days,
            'trial_end_date' => $trial_end_date,
            'subscription_amount' => $amount,
            'currency' => $currency,
            'admin_user_id' => $user_id,
            'admin_email' => $billing_email
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$admin_user_id, $tenant_id, $details, $ip_address]);

        // Commit transaction
        $pdo->commit();

        header('Location: manage_tenants.php?success=tenant_created');
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        header('Location: manage_tenants.php?error=' . urlencode("Error creating tenant: " . $e->getMessage()));
    }
} else {
    header('Location: manage_tenants.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>
