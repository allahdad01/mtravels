<?php
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../includes/permissions.php';
require_permission('hr.attendance');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Parse month
$year = date('Y', strtotime($month . '-01'));
$month_num = date('m', strtotime($month . '-01'));

// Get attendance records
$query = "
    SELECT a.*, u.name as user_name, u.email
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.tenant_id = ? AND a.branch_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?
";

$params = [$tenant_id, $branch_id, $year, $month_num];
$types = "iiii";

if ($user_filter > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY a.date ASC, u.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_' . $month . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Date',
    'Employee Name',
    'Email',
    'Check In Time',
    'Check Out Time',
    'Working Minutes',
    'Status',
    'Notes'
]);

// Write data rows
foreach ($records as $record) {
    fputcsv($output, [
        $record['date'],
        $record['user_name'],
        $record['email'],
        $record['check_in_time'] ?: '',
        $record['check_out_time'] ?: '',
        $record['working_minutes'],
        ucfirst($record['status']),
        $record['notes'] ?: ''
    ]);
}

fclose($output);
exit();
?>