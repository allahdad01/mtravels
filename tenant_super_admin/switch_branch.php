<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user is tenant_super_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../access_denied.php');
    exit();
}

// Include database connection
require_once('../includes/db.php');
include '../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];

if (isset($_GET['branch_id'])) {
    $branch_id = (int)$_GET['branch_id'];

    // Verify that the branch belongs to this tenant and is active
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND tenant_id = ? AND status = 'active'");
        $stmt->execute([$branch_id, $tenant_id]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($branch) {
            // Update session with current branch
            $_SESSION['current_branch_id'] = $branch_id;
            $_SESSION['current_branch_name'] = $branch['name'];

            // Log the branch switch
            $stmt = $pdo->prepare("INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) VALUES (?, ?, 'switch_branch', 'branches', ?, NULL, ?, ?, ?, NOW())");
            $details = json_encode(['branch_name' => $branch['name']]);
            $stmt->execute([
                $tenant_id,
                $user_id,
                $branch_id,
                $details,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);

            // Redirect back to dashboard with success message
            header('Location: dashboard.php?success=branch_switched&branch=' . urlencode($branch['name']));
            exit();
        } else {
            // Branch not found or not accessible
            header('Location: dashboard.php?error=branch_not_found');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database Error in switch_branch.php: " . $e->getMessage());
        header('Location: dashboard.php?error=database_error');
        exit();
    }
} else {
    // No branch_id provided
    header('Location: dashboard.php?error=no_branch_selected');
    exit();
}
?>