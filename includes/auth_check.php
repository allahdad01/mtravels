<?php
/**
 * auth_check.php
 * Handles session validation, user fetching, settings fetching,
 * and feature flag resolution. Include this at the top of every protected page.
 *
 * After inclusion the following variables are available globally:
 *   $user             – associative array of the logged-in user's DB row
 *   $settings         – associative array of the tenant's settings row
 *   $allowed_features – flat array of feature-key strings the tenant can access
 *   $tenant_id        – integer tenant ID from the session
 *   $profilePic       – escaped profile picture filename
 *   $imagePath        – full relative path to the profile image
 *   $remaining_time   – seconds left in the current session
 *   $session_timeout  – configured session lifetime in seconds
 */

// ── 1. Session ──────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once('../includes/session_check.php');
require_once('../includes/language_helpers.php');

$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// ── 2. Database connection ───────────────────────────────────────────────────
require_once('db.php');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id   = (int) $_SESSION['user_id'];

// ── 3. Fetch user, settings, and features in as few queries as possible ──────
try {
    // User row
    $stmt = $pdo->prepare(
        "SELECT id, tenant_id, name, email, role, profile_pic
         FROM users
         WHERE id = ? AND tenant_id = ?
         LIMIT 1"
    );
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("auth_check: user not found – id={$user_id} tenant={$tenant_id}");
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Settings row
    $settingStmt = $pdo->prepare(
        "SELECT * FROM settings WHERE tenant_id = ? LIMIT 1"
    );
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Active subscription features
    $featStmt = $pdo->prepare(
        "SELECT p.features
         FROM tenant_subscriptions ts
         JOIN plans p ON ts.plan_id = p.id
         WHERE ts.tenant_id = ? AND ts.status = 'active'
         ORDER BY ts.start_date DESC
         LIMIT 1"
    );
    $featStmt->execute([$tenant_id]);
    $featRow = $featStmt->fetch(PDO::FETCH_ASSOC);

    $allowed_features = [];
    if ($featRow && !empty($featRow['features'])) {
        $decoded = json_decode($featRow['features'], true);
        if (is_array($decoded)) {
            $allowed_features = $decoded;
        }
    }

} catch (PDOException $e) {
    // Log the sanitised message – never expose DB details to the browser
    error_log("auth_check DB error: " . $e->getMessage());
    // Hard-stop; the app cannot function without a DB connection
    http_response_code(503);
    exit('Service temporarily unavailable.');
}

// ── 4. Fail-closed feature guard ────────────────────────────────────────────
// NOTE: If no active subscription is found the tenant gets NO features.
// Remove this comment block once you're happy with the behaviour; do NOT
// re-introduce a default "allow everything" fallback here – it's a security risk.
if (empty($allowed_features)) {
    // Optionally log so you notice tenants without active subscriptions
    error_log("auth_check: tenant {$tenant_id} has no active subscription features.");
}

// ── 5. Profile image ─────────────────────────────────────────────────────────
$profilePic = !empty($user['profile_pic'])
    ? htmlspecialchars($user['profile_pic'], ENT_QUOTES, 'UTF-8')
    : 'default-avatar.jpg';
$imagePath = '../assets/images/user/' . basename($profilePic);

// ── 6. Session timer ─────────────────────────────────────────────────────────
$session_timeout = 1800; // 30 minutes in seconds
$last_activity   = $_SESSION['last_activity'] ?? time();
$remaining_time  = max(0, $session_timeout - (time() - $last_activity));

// ── 7. Helper functions ───────────────────────────────────────────────────────

/**
 * Escape a value for safe HTML output.
 */
if (!function_exists('h')) {
    function h(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Returns true when the given feature key is in the tenant's allowed list.
 */
if (!function_exists('hasFeature')) {
    function hasFeature(string $feature, array $allowed_features): bool {
        return in_array($feature, $allowed_features, true);
    }
}

/**
 * Returns true for any role that is NOT staff (i.e. should see restricted menus).
 * Staff can only see My Attendance and My Payments.
 */
if (!function_exists('staffCanSeeMenu')) {
    function staffCanSeeMenu(string $userRole): bool {
        return $userRole !== 'staff';
    }
}