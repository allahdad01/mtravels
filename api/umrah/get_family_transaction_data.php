<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['family_id'])) {
    $family_id = intval($_GET['family_id']);

    try {
        // Get family financial summary
        $familyQuery = "
            SELECT
                f.total_price,
                f.total_paid,
                f.total_due,
                COUNT(ub.booking_id) as member_count
            FROM families f
            LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id AND ub.tenant_id = ? AND ub.branch_id = ?
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
            GROUP BY f.family_id
        ";

        $stmt = $pdo->prepare($familyQuery);
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $family_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $familyData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get family members with their payment details
        $membersQuery = "
            SELECT
                ub.booking_id,
                ub.name,
                ub.sold_price,
                ub.paid,
                ub.due,
                ub.currency
            FROM umrah_bookings ub
            WHERE ub.family_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
            ORDER BY ub.name
        ";

        $stmt = $pdo->prepare($membersQuery);
        $stmt->bindParam(1, $family_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $membersResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $members = [];
        foreach ($membersResult as $member) {
            $members[] = [
                'booking_id' => $member['booking_id'],
                'name' => $member['name'],
                'sold_price' => number_format($member['sold_price'] ?? 0, 2),
                'paid' => number_format($member['paid'] ?? 0, 2),
                'due' => number_format($member['due'] ?? 0, 2),
                'currency' => $member['currency'] ?? 'USD'
            ];
        }

        // Prepare response data
        $response = [
            'success' => true,
            'data' => [
                'total_price' => number_format($familyData['total_price'] ?? 0, 2),
                'total_paid' => number_format($familyData['total_paid'] ?? 0, 2),
                'total_due' => number_format($familyData['total_due'] ?? 0, 2),
                'member_count' => $familyData['member_count'] ?? 0,
                'members' => $members
            ]
        ];

        echo json_encode($response);

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>