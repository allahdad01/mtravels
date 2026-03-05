<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once '../../includes/db.php';

// Pagination
$results_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Get total count
$countQuery = "SELECT COUNT(DISTINCT f.family_id) as total 
              FROM families f 
              WHERE f.tenant_id = ? AND f.branch_id = ?";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtCount->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmtCount->execute();
$totalFamilies = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($totalFamilies / $results_per_page);

// Main query
$familiesQuery = "
    SELECT
        f.family_id, f.head_of_family, f.contact, f.address, f.package_type,
        f.location, f.created_at, f.status, f.visa_status,
        u.name as created_by_name,
        (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = f.tenant_id AND branch_id = f.branch_id) as member_count,
        (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND status = 'approved' AND tenant_id = f.tenant_id AND branch_id = f.branch_id) as approved_count
    FROM families f
    LEFT JOIN users u ON f.created_by = u.id AND u.tenant_id = f.tenant_id AND u.branch_id = f.branch_id
    WHERE f.tenant_id = ? AND f.branch_id = ?
    ORDER BY f.created_at DESC
    LIMIT ?, ?
";

$stmt = $pdo->prepare($familiesQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $offset, PDO::PARAM_INT);
$stmt->bindParam(4, $results_per_page, PDO::PARAM_INT);
$stmt->execute();
$families = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status' => 'success',
    'families' => $families,
    'total' => $totalFamilies,
    'total_pages' => $total_pages,
    'current_page' => $page
]);
?>
