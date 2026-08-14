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
                COUNT(ub.booking_id) as member_count,
                MAX(c.client_type) as client_type
            FROM families f
            LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id AND ub.tenant_id = ? AND ub.branch_id = ?
            LEFT JOIN clients c ON ub.sold_to = c.id
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
                ub.currency,
                ub.sold_to,
                p.name AS package_name,
                (SELECT s.supplier_type FROM umrah_booking_services ubs
                 LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
                   AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
                 JOIN suppliers s ON s.id = COALESCE(f.supplier_id, ubs.supplier_id)
                 WHERE ubs.booking_id = ub.booking_id AND ubs.tenant_id = ub.tenant_id
                 AND ubs.branch_id = ub.branch_id
                 AND (ubs.service_type = 'all' OR FIND_IN_SET('visa', REPLACE(ubs.service_type, '+', ',')) > 0)
                 LIMIT 1) as supplier_type,
                (SELECT s.route_payment_to_main_account FROM umrah_booking_services ubs
                 LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
                   AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
                 JOIN suppliers s ON s.id = COALESCE(f.supplier_id, ubs.supplier_id)
                 WHERE ubs.booking_id = ub.booking_id AND ubs.tenant_id = ub.tenant_id
                 AND ubs.branch_id = ub.branch_id
                 AND (ubs.service_type = 'all' OR FIND_IN_SET('visa', REPLACE(ubs.service_type, '+', ',')) > 0)
                 LIMIT 1) as route_payment_to_main_account
            FROM umrah_bookings ub
            LEFT JOIN umrah_packages p ON ub.package_id = p.id AND p.tenant_id = ub.tenant_id
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
                'currency' => $member['currency'] ?? 'USD',
                'supplier_type' => $member['supplier_type'] ?? '',
                'route_payment_to_main_account' => (int)($member['route_payment_to_main_account'] ?? 0),
                'package' => $member['package_name'] ?? '',
                'sold_to' => $member['sold_to'] ?? ''
            ];
        }

        // Family package label: stored family package_type when set, otherwise
        // the most common package name across the family's member bookings
        // (families created from a booking are saved with package_type NULL).
        $packageType = $familyData['package_type'] ?? '';
        if (empty($packageType)) {
            $pkgCounts = [];
            foreach ($members as $m) {
                if (!empty($m['package'])) {
                    $pkgCounts[$m['package']] = ($pkgCounts[$m['package']] ?? 0) + 1;
                }
            }
            if ($pkgCounts) {
                arsort($pkgCounts);
                $packageType = key($pkgCounts);
            }
        }

        // Prepare response data
        $response = [
            'success' => true,
            'data' => [
                'total_price' => number_format($familyData['total_price'] ?? 0, 2),
                'total_paid' => number_format($familyData['total_paid'] ?? 0, 2),
                'total_due' => number_format($familyData['total_due'] ?? 0, 2),
                'member_count' => $familyData['member_count'] ?? 0,
                'client_type' => $familyData['client_type'] ?? '',
                'package_type' => $packageType,
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