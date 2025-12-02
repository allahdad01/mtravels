<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

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

        $stmt = $conn->prepare($familyQuery);
        $stmt->bind_param("iiiii", $tenant_id, $branch_id, $family_id, $tenant_id, $branch_id);
        $stmt->execute();
        $familyResult = $stmt->get_result();
        $familyData = $familyResult->fetch_assoc();
        $stmt->close();

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

        $stmt = $conn->prepare($membersQuery);
        $stmt->bind_param("iii", $family_id, $tenant_id, $branch_id);
        $stmt->execute();
        $membersResult = $stmt->get_result();

        $members = [];
        while ($member = $membersResult->fetch_assoc()) {
            $members[] = [
                'booking_id' => $member['booking_id'],
                'name' => $member['name'],
                'sold_price' => number_format($member['sold_price'] ?? 0, 2),
                'paid' => number_format($member['paid'] ?? 0, 2),
                'due' => number_format($member['due'] ?? 0, 2),
                'currency' => $member['currency'] ?? 'USD'
            ];
        }
        $stmt->close();

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

    } catch (Exception $e) {
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

$conn->close();
?>