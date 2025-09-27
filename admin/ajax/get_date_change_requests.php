<?php
// Include security and database connections
require_once '../security.php';
require_once '../../includes/db.php';
require_once '../../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

header('Content-Type: application/json');

// Get status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query
$query = "
    SELECT dc.*, f.head_of_family as family_name
    FROM date_change_umrah dc
    LEFT JOIN families f ON dc.family_id = f.family_id
    WHERE dc.tenant_id = ?
";

$params = [$tenant_id];
$types = "i";

if ($status !== 'all') {
    $query .= " AND dc.status = ?";
    $params[] = $status;
    $types .= "s";
}

$query .= " ORDER BY dc.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    // Get counts for each status
    $countQuery = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM date_change_umrah
        WHERE tenant_id = ?
    ";

    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $tenant_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $counts = $countResult->fetch_assoc();

    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'counts' => [
            'all' => $counts['total'],
            'pending' => $counts['pending'],
            'approved' => $counts['approved'],
            'completed' => $counts['completed']
        ]
    ]);

} catch (Exception $e) {
    error_log("Get date change requests error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load date change requests']);
}
?>