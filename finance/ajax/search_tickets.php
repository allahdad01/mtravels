<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'tickets' => [],
    'debug' => [] // Add debug information
];

// Check if search parameters are provided
if (isset($_GET['pnr']) || isset($_GET['passenger'])) {
    try {
        // Build the query based on search parameters
        $query = "SELECT 
                    t.id,
                    t.title,
                    t.phone,
                    t.passenger_name,
                    t.pnr,
                    t.airline,
                    t.origin,
                    t.destination,
                    t.departure_date,
                    t.currency,
                    t.sold,
                    t.price,
                    s.name AS supplier_name,
                    c.name AS client_name
                FROM 
                    ticket_bookings t
                LEFT JOIN 
                    suppliers s ON t.supplier = s.id
                LEFT JOIN 
                    clients c ON t.sold_to = c.id
                WHERE 1=1 AND t.tenant_id = " . $tenant_id;
                

        $params = [];
        $types = "";

        if (isset($_GET['pnr']) && !empty($_GET['pnr'])) {
            $query .= " AND t.pnr LIKE ?";
            $params[] = "%" . $_GET['pnr'] . "%";
            $types .= "s";
            $response['debug'][] = "Searching by PNR: " . $_GET['pnr'];
        }

        if (isset($_GET['passenger']) && !empty($_GET['passenger'])) {
            $query .= " AND t.passenger_name LIKE ?";
            $params[] = "%" . $_GET['passenger'] . "%";
            $types .= "s";
            $response['debug'][] = "Searching by passenger name: " . $_GET['passenger'];
        }

        // Add order by clause
        $query .= " ORDER BY t.departure_date DESC";
        
        $response['debug'][] = "Query: " . $query;

        // Prepare and execute the statement
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        if (!empty($params)) {
            $bind_result = $stmt->bind_param($types, ...$params);
            if ($bind_result === false) {
                throw new Exception("Bind param failed: " . $stmt->error);
            }
        }

        $execute_result = $stmt->execute();
        if ($execute_result === false) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception("Get result failed: " . $stmt->error);
        }

        // Fetch results
        while ($row = $result->fetch_assoc()) {
            $response['tickets'][] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'phone' => $row['phone'],
                'passenger_name' => $row['passenger_name'],
                'pnr' => $row['pnr'],
                'airline' => $row['airline'],
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'departure_date' => $row['departure_date'],
                'currency' => $row['currency'],
                'sold' => $row['sold'],
                'price' => $row['price'],
                'supplier_name' => $row['supplier_name'],
                'client_name' => $row['client_name']
            ];
        }

        $response['success'] = true;
        if (empty($response['tickets'])) {
            $response['message'] = 'No tickets found matching your search criteria.';
        } else {
            $response['message'] = count($response['tickets']) . ' ticket(s) found.';
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'An error occurred while searching for tickets: ' . $e->getMessage();
        $response['debug'][] = "Error: " . $e->getMessage();
        error_log("Error in search_tickets.php: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Please provide search criteria.';
    $response['debug'][] = "No search parameters provided";
}

// Send JSON response
header('Content-Type: application/json');
// Remove debug info in production
$response_to_send = [
    'success' => $response['success'],
    'message' => $response['message'],
    'tickets' => $response['tickets']
];

// Add error handling for JSON encoding
$json_response = json_encode($response_to_send, JSON_PRETTY_PRINT);
if ($json_response === false) {
    // Log JSON encoding error
    error_log('JSON Encoding Error: ' . json_last_error_msg());
    // Fallback response
    $error_response = [
        'success' => false,
        'message' => 'JSON Encoding Error: ' . json_last_error_msg(),
        'tickets' => []
    ];
    echo json_encode($error_response);
} else {
    echo $json_response;
} 