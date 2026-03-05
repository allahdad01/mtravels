<?php
header('Content-Type: application/json');

try {
    require_once '../../includes/db.php';
    require_once '../../admin/security.php';
    
    enforce_auth();
    
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    
    // Get family ID from query parameter
    $family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
    
    if (!$family_id) {
        throw new Exception('Family ID is required');
    }
    
    // Get all group tickets that include members from this family
    $sql = "SELECT gt.ticket_id, gt.airline_name, gt.pnr, gt.flight_date, gt.return_date, gt.duration, gt.flight_type, gt.member_ids
            FROM group_tickets gt
            WHERE gt.tenant_id = ? AND gt.branch_id = ? AND gt.status = 'active'
            AND JSON_CONTAINS(gt.member_ids, JSON_ARRAY(
                (SELECT booking_id FROM umrah_bookings WHERE family_id = ? LIMIT 1)
            ))
            ORDER BY gt.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $branch_id, $family_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get members from this family and check which have flights set
    $membersSql = "SELECT booking_id, name, flight_date, return_date
                   FROM umrah_bookings
                   WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND status = 'active'
                   ORDER BY name ASC";
    
    $membersStmt = $pdo->prepare($membersSql);
    $membersStmt->execute([$family_id, $tenant_id, $branch_id]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count how many members have flight dates set
    $flightDoneCount = 0;
    foreach ($members as $member) {
        if ($member['flight_date'] && $member['return_date']) {
            $flightDoneCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'family_id' => $family_id,
        'tickets' => $tickets,
        'members_total' => count($members),
        'members_with_flight' => $flightDoneCount,
        'members' => $members
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
