<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and handler
require_once('../handlers/dashboard_handler.php');

$tenant_id = $_SESSION['tenant_id'];

// Get filter parameters
$filter_date = isset($_POST['filter_date']) ? $_POST['filter_date'] : '';

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'Invalid parameters',
    'data' => []
];

// Validate filter date format (YYYY-MM)
if (!preg_match('/^\d{4}-\d{2}$/', $filter_date)) {
    $response['message'] = 'Invalid date format. Expected: YYYY-MM';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

try {
    list($year, $month) = explode('-', $filter_date);

    // Get top performers for the selected month/year
    $topPerformers = getTopPerformersByTicketProfit($month, $year);

    $response['status'] = 'success';
    $response['message'] = 'Performance data retrieved successfully';
    $response['data'] = $topPerformers;

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
exit();