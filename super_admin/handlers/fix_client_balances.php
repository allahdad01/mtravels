<?php
// Force OPcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

session_start();

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../../includes/db.php';
require_once __DIR__ . '/fix_balances_engine_v2.php';

$mode = $_POST['mode'] ?? 'scan';
$targetTenant = !empty($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
$targetClient = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

try {
    if ($mode === 'apply') {
        $result = fix_client_balances_apply($pdo, $targetTenant, $targetClient);
    } else {
        $result = fix_client_balances_calculate($pdo, $targetTenant, $targetClient);
    }
    $result['mode'] = $mode;
    $result['version'] = 'v2';
    exit(json_encode($result));
} catch (Exception $e) {
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
}
