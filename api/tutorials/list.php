<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin', 'super_admin']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$user_role = $_SESSION['role'];
$show_all = isset($_GET['all']) && $_GET['all'] === '1' && in_array($user_role, ['super_admin']);
$page_filter = isset($_GET['page']) ? trim($_GET['page']) : '';

try {
    $sql = "SELECT * FROM tutorials";
    $conditions = [];
    $params = [];

    if (!$show_all) {
        $conditions[] = "status = 1";
    }
    if ($page_filter !== '') {
        $conditions[] = "page = ?";
        $params[] = $page_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$show_all) {
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
