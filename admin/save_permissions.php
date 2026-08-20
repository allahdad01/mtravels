<?php
/**
 * save_permissions.php
 * JSON endpoint that replaces a user's custom permission set.
 *
 * Callers: admin/users.php (branch admin) and tenant_super_admin/users.php.
 * Authorization: branch admins may only edit users in their own branch;
 * tenant super admins may edit any user in the tenant. Nobody may edit
 * themselves, and bypass roles (super_admin/tenant_super_admin/admin) are
 * always full-access and cannot be customized.
 */

require_once 'security.php';
require_once '../includes/db.php';
require_once '../includes/permissions.php';

header('Content-Type: application/json');

enforce_auth();

require_permission('users.permissions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please refresh and try again.']);
    exit;
}

$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$user_id   = (int) ($_POST['user_id'] ?? 0);

if (!$tenant_id || !$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user ID.']);
    exit;
}

// Sanitize keys against the catalog so arbitrary strings are never stored.
$valid_keys = permission_keys();
$raw_keys   = $_POST['permissions'] ?? [];
if (!is_array($raw_keys)) {
    $raw_keys = [];
}
$keys = array_values(array_unique(array_intersect($raw_keys, $valid_keys)));

try {
    // Target user must exist in this tenant (and same branch for branch admins).
    $stmt = $pdo->prepare("SELECT id, role, branch_id, name FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
    if ($user_id === (int) $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You cannot change your own permissions.']);
        exit;
    }
    if (in_array($target['role'], ['super_admin', 'tenant_super_admin', 'admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This user has full access by role and cannot be restricted.']);
        exit;
    }
    if ($role === 'admin' && (int) $target['branch_id'] !== (int) ($_SESSION['branch_id'] ?? 0)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only manage permissions for users in your own branch.']);
        exit;
    }

    // Replace the user's permission set (saving empty = grant nothing).
    $row_branch_id = ($role === 'admin') ? ($_SESSION['branch_id'] ?? null) : $target['branch_id'];

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ? AND tenant_id = ?")->execute([$user_id, $tenant_id]);
    $insert = $pdo->prepare(
        "INSERT INTO user_permissions (tenant_id, branch_id, user_id, permission_key, granted)
         VALUES (?, ?, ?, ?, 1)"
    );
    foreach ($keys as $key) {
        $insert->execute([$tenant_id, $row_branch_id, $user_id, $key]);
    }
    $pdo->commit();

    // Audit trail
    try {
        $pdo->prepare(
            "INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, 'permissions', 'user_permissions', ?, NULL, ?, ?, ?)"
        )->execute([
            $tenant_id,
            $_SESSION['user_id'],
            $user_id,
            json_encode($keys),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log permissions change: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Permissions saved for ' . $target['name'] . ' (' . count($keys) . ' granted).',
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error saving permissions: ' . $e->getMessage()]);
    exit;
}