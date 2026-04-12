<?php
/**
 * auth_check_sales_agent.php
 * Handles session validation for sales agents.
 * Sales agents are super-admin users with role='sales_agent' and tenant_id IS NULL
 *
 * After inclusion the following variables are available globally:
 *   $user             – associative array of the logged-in user's DB row
 *   $settings         – associative array of the global settings row (tenant_id=NULL)
 *   $allowed_features – array of available features for sales agents
 *   $tenant_id        – always 0 for sales agents
 *   $sales_agent      – full sales_agents row with commission info, etc.
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
require_once('../config.php'); // Load platform name and config

$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// ── 2. Database connection ───────────────────────────────────────────────────
require_once('db.php');

$user_id   = (int) $_SESSION['user_id'];
$tenant_id = 0; // Sales agents always have tenant_id = NULL (super-admin)

// ── 3. Fetch user, settings, and sales agent info ────────────────────────────
try {
    // User row (sales agents are users with role='sales_agent' and tenant_id IS NULL)
    $stmt = $pdo->prepare(
        "SELECT id, tenant_id, name, email, role, profile_pic
         FROM users
         WHERE id = ? AND tenant_id IS NULL AND role = 'sales_agent'
         LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Sales agent record
    $agentStmt = $pdo->prepare(
        "SELECT * FROM sales_agents WHERE user_id = ? LIMIT 1"
    );
    $agentStmt->execute([$user_id]);
    $sales_agent = $agentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$sales_agent) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Sales agents use platform settings (key-value pairs), not tenant-specific settings
    try {
        $settingStmt = $pdo->prepare(
            "SELECT `key`, `value` FROM platform_settings"
        );
        $settingStmt->execute();
        $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // Fallback to hardcoded defaults
        $settings = [
            'platform_name' => PLATFORM_NAME,
            'platform_logo' => 'default-logo.png'
        ];
    }
    
    // Ensure settings has required keys with fallback
    $settings['platform_name'] = $settings['platform_name'] ?? PLATFORM_NAME;
    $settings['platform_logo'] = $settings['platform_logo'] ?? 'default-logo.png';

    // Sales agents get a predefined set of features
    $allowed_features = [
        'dashboard_access',
        'tenant_management',
        'commission_tracking',
        'payment_tracking',
        'statement_generation',
        'inter_tenant_chat',
        'profile_management'
    ];

} catch (PDOException $e) {
    // Hard-stop; the app cannot function without a DB connection
    http_response_code(503);
    exit('Service temporarily unavailable.');
}

// ── 4. Profile image ─────────────────────────────────────────────────────────
$profilePic = !empty($user['profile_pic'])
    ? htmlspecialchars($user['profile_pic'], ENT_QUOTES, 'UTF-8')
    : 'default-avatar.jpg';
$imagePath = '../assets/images/user/' . basename($profilePic);

// ── 5. Session timer ─────────────────────────────────────────────────────────
$session_timeout = 1800; // 30 minutes in seconds
$last_activity   = $_SESSION['last_activity'] ?? time();
$remaining_time  = max(0, $session_timeout - (time() - $last_activity));

// ── 6. Helper functions ───────────────────────────────────────────────────────

/**
 * Escape a value for safe HTML output.
 */
if (!function_exists('h')) {
    function h(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Returns true when the given feature key is in the sales agent's allowed list.
 */
if (!function_exists('hasFeature')) {
    function hasFeature(string $feature, array $allowed_features): bool {
        return in_array($feature, $allowed_features, true);
    }
}

/**
 * Returns true if the sales agent has an active status.
 */
if (!function_exists('isSalesAgentActive')) {
    function isSalesAgentActive(array $sales_agent): bool {
        return isset($sales_agent['status']) && $sales_agent['status'] === 'active';
    }
}
