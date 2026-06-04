<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin', 'super_admin']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role'];
$show_all = isset($_GET['all']) && $_GET['all'] === '1' && in_array($user_role, ['super_admin']);

try {
    if ($show_all) {
        $stmt = $pdo->prepare("SELECT * FROM tutorials ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE status = 1 ORDER BY sort_order ASC, id ASC");
        $stmt->execute();
        $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filtered = [];
        foreach ($tutorials as $t) {
            $roles = json_decode($t['roles'], true);
            if (!is_array($roles)) {
                $roles = ['all'];
            }
            if (in_array('all', $roles) || in_array($user_role, $roles)) {
                $filtered[] = $t;
            }
        }
        $tutorials = $filtered;
    }

    echo json_encode(['success' => true, 'tutorials' => $tutorials]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'tutorials' => [], 'message' => 'Database error']);
}
