<?php
// Prevent direct access to admin directory
// Redirect to login if no admin session
if (!isset($_SESSION['loggedin'])) {
    header('Location: ../login.php');
    exit;
}

// Redirect authenticated users to dashboard
switch(strtolower($_SESSION['role'] ?? 'user')) {
    case 'super_admin':
        header('Location: ../super_admin/dashboard.php');
        break;
    case 'tenant_super_admin':
        header('Location: ../tenant_super_admin/dashboard.php');
        break;
    case 'admin':
        header('Location: dashboard.php');
        break;
    default:
        header('Location: ../index.php');
}
exit;
?>
