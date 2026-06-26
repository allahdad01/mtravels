<?php
require_once '../../admin/security.php';
enforce_auth(['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin']);
require_once '../../includes/db.php';

header('Content-Type: application/json');

$page_filter = isset($_GET['page']) ? trim($_GET['page']) : '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);

if ($page_filter === '') {
    echo json_encode(['success' => true, 'tutorials' => []]);
    exit;
}

try {
    $sql = "SELECT * FROM tutorials WHERE status = 1 AND show_on_load = 1 AND FIND_IN_SET(?, REPLACE(page, ' ', ''))";
    $params = [$page_filter];
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter by role
    $user_role = $_SESSION['role'];
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

    // Remove already-learned tutorials
    if ($user_id > 0 && !empty($filtered)) {
        $ids = array_column($filtered, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $learnedStmt = $pdo->prepare("SELECT tutorial_id FROM user_tutorial_learned WHERE user_id = ? AND tutorial_id IN ($placeholders)");
        $learnedStmt->execute(array_merge([$user_id], $ids));
        $learnedIds = $learnedStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($learnedIds)) {
            $result = [];
            foreach ($filtered as $t) {
                if (!in_array($t['id'], $learnedIds)) {
                    $result[] = $t;
                }
            }
            $filtered = $result;
        }
    }

    echo json_encode(['success' => true, 'tutorials' => $filtered]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'tutorials' => [], 'message' => 'Database error']);
}
