<?php
// Include database security module for input validation
require_once '../includes/db_security.php';

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Initialize response array
$response = [
    'exists' => false
];
$tenant_id = $_SESSION['tenant_id'];

// Check if passenger name was provided
if (isset($_POST['passenger_name']) && !empty($_POST['passenger_name'])) {
    $passengerName = trim($_POST['passenger_name']);
    
    // Connect to database
    require_once '../../includes/conn.php';

// Validate passenger_name
$passenger_name = isset($_POST['passenger_name']) ? DbSecurity::validateInput($_POST['passenger_name'], 'string', ['maxlength' => 255]) : null;
    
    // Check connection
    if ($conn->connect_error) {
        $response['error'] = 'Database connection failed: ' . $conn->connect_error;
        echo json_encode($response);
        exit;
    }
    
    // Get all tables in the database to debug
    $tables = [];
    $tablesResult = $conn->query("SHOW TABLES");
    while ($tableRow = $tablesResult->fetch_array()) {
        $tables[] = $tableRow[0];
    }
    $response['tables'] = $tables;
    
    // Try different potential table names
    $tablesToTry = ['tickets', 'ticket_bookings', 'ticket', 'bookings'];
    $foundTable = false;
    
    foreach ($tablesToTry as $tableName) {
        // Check if table exists
        $tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
        if (!$tableExists) {
            continue;
        }
        
        // Table exists, check for passenger data
        $stmt = $conn->prepare("SELECT * FROM $tableName WHERE passenger_name LIKE ? AND tenant_id = ? ORDER BY issue_date DESC LIMIT 1");
        if (!$stmt) {
            continue; // Skip if prepare fails
        }
        
        $searchName = "%" . $passengerName . "%";
        $stmt->bind_param("si", $searchName, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $foundTable = true;
            $row = $result->fetch_assoc();
            
            // Debug: Log all column names from the row
            $response['debug_columns'] = array_keys($row);
            $response['found_in_table'] = $tableName;
            
            // Set response data
            $response['exists'] = true;
            $response['title'] = $row['title'] ?? '';
            $response['gender'] = $row['gender'] ?? '';
            
            // Handle different possible phone field names
            if (isset($row['phone'])) {
                $response['phone'] = $row['phone'];
            } elseif (isset($row['phone_number'])) {
                $response['phone'] = $row['phone_number'];
            } elseif (isset($row['contact'])) {
                $response['phone'] = $row['contact'];
            } else {
                $response['phone'] = '';
                $response['phone_field_missing'] = true;
            }
            
            $response['origin'] = $row['origin'] ?? '';
            $response['destination'] = $row['destination'] ?? '';
            $response['airline'] = $row['airline'] ?? '';
            $response['pnr'] = $row['pnr'] ?? '';
            $response['issue_date'] = $row['issue_date'] ?? '';
            
            // Include full row data for debugging
            $response['debug_row'] = $row;
            
            $stmt->close();
            break; // Exit loop as we found a match
        }
        
        $stmt->close();
    }
    
    // If no table matched, add error to response
    if (!$foundTable) {
        $response['error'] = 'No matching table or data found';
    }
    
    $conn->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 