<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

header('Content-Type: application/json');

// Get status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$query = "
    SELECT dc.*, f.head_of_family as family_name
    FROM date_change_umrah dc
    LEFT JOIN families f ON dc.family_id = f.family_id AND f.tenant_id = ? AND f.branch_id = ?
    WHERE dc.tenant_id = ? AND dc.branch_id = ?
";

$params = [$tenant_id, $branch_id, $tenant_id, $branch_id];

if ($status !== 'all') {
    $query .= " AND dc.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY dc.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts for each status
    $countQuery = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM date_change_umrah
        WHERE tenant_id = ? AND branch_id = ?
    ";

    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute([$tenant_id, $branch_id]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'counts' => [
            'all' => (int)$counts['total'],
            'pending' => (int)$counts['pending'],
            'approved' => (int)$counts['approved'],
            'completed' => (int)$counts['completed']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>