<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Query to fetch visa details along with the supplier's name and sold_to name
    $query = "
        SELECT
            v.*,
            s1.name AS supplier_name,
            s2.name AS sold_to_name
        FROM visa_applications v
        LEFT JOIN suppliers s1 ON v.supplier = s1.id
        LEFT JOIN suppliers s2 ON v.sold_to = s2.id
        WHERE v.id = ? AND v.tenant_id = ? AND v.branch_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($visa) {
        echo json_encode($visa);
    } else {
        echo json_encode(['error' => 'Visa not found.']);
    }
} else {
    echo json_encode(['error' => 'Invalid ID.']);
}
?>
