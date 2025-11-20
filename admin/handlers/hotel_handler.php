<?php
// Ensure direct access is prevented
if (!defined('HOTEL_HANDLER_LOADED')) {
    define('HOTEL_HANDLER_LOADED', true);
}

require_once('../includes/db.php');
include '../includes/conn.php';

$tenant_id   = $_SESSION['tenant_id'];
$user_id     = $_SESSION['user_id'] ?? null;

$itemsPerPage = 10;
$currentPage  = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset       = ($currentPage - 1) * $itemsPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];
$searchTypes  = '';

if ($search !== '') {
    $searchTerm = "%{$search}%";
    $searchCondition = " AND (
        hb.order_id LIKE ? OR
        hb.first_name LIKE ? OR
        hb.last_name LIKE ? OR
        hb.contact_no LIKE ? OR
        hb.accommodation_details LIKE ?
    )";
    $searchParams = array_fill(0, 5, $searchTerm);
    $searchTypes  = str_repeat('s', 5);
}

// Count total records
$totalQuery = "SELECT COUNT(*) as total FROM hotel_bookings hb WHERE hb.tenant_id = ? {$searchCondition}";
$stmtTotal  = $conn->prepare($totalQuery);

if ($searchCondition !== '') {
    $types = 's' . $searchTypes;
    $params = array_merge([$tenant_id], $searchParams);
    $bindTotal = [$types];
    foreach ($params as $key => $value) {
        $bindTotal[] = &$params[$key];
    }
    call_user_func_array([$stmtTotal, 'bind_param'], $bindTotal);
} else {
    $stmtTotal->bind_param('s', $tenant_id);
}

$stmtTotal->execute();
$totalResult   = $stmtTotal->get_result();
$totalRecords  = (int) ($totalResult->fetch_assoc()['total'] ?? 0);
$stmtTotal->close();

$totalPages = max(1, (int) ceil($totalRecords / $itemsPerPage));

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
}

$bookingsQuery = "
    SELECT
        hb.id,
        hb.title,
        hb.first_name,
        hb.last_name,
        CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) AS guest_name,
        hb.gender,
        hb.order_id,
        hb.check_in_date,
        hb.check_out_date,
        hb.accommodation_details,
        hb.issue_date,
        hb.supplier_id,
        hb.sold_to,
        hb.paid_to,
        hb.contact_no,
        hb.base_amount,
        hb.sold_amount,
        hb.profit,
        hb.currency,
        hb.remarks,
        hb.status,
        s.name  AS supplier_name,
        c.name  AS client_name,
        ma.name AS paid_to_name,
        u.name  AS created_by
    FROM hotel_bookings hb
    LEFT JOIN suppliers s    ON hb.supplier_id = s.id
    LEFT JOIN clients c      ON hb.sold_to     = c.id
    LEFT JOIN main_account ma ON hb.paid_to    = ma.id
    LEFT JOIN users u        ON hb.created_by  = u.id
    WHERE hb.tenant_id = ? {$searchCondition}
    ORDER BY hb.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($bookingsQuery);

if ($searchCondition !== '') {
    $params = array_merge([$tenant_id], $searchParams, [$offset, $itemsPerPage]);
    $types  = 's' . $searchTypes . 'ii';
    $bindMain[] = $types;
    foreach ($params as $key => $value) {
        $bindMain[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindMain);
} else {
    $stmt->bind_param('sii', $tenant_id, $offset, $itemsPerPage);
}

$stmt->execute();
$result   = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$startRecord = $totalRecords > 0 ? $offset + 1 : 0;
$endRecord   = $totalRecords > 0 ? min($offset + count($bookings), $totalRecords) : 0;
?>

