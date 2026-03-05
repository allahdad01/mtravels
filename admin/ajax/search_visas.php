<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'visas' => [],
    'debug' => [] // Add debug information
];

// Check if search parameters are provided
if (isset($_GET['passport']) || isset($_GET['applicant'])) {
    try {
        // Build the query based on search parameters
        $query = "SELECT
                    va.id,
                    va.title,
                    va.phone,
                    va.applicant_name,
                    va.passport_number,
                    va.country,
                    va.visa_type,
                    va.applied_date,
                    va.currency,
                    va.sold,
                    va.base,
                    s.name AS supplier_name,
                    c.name AS client_name
                FROM
                    visa_applications va
                LEFT JOIN
                    suppliers s ON va.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
                LEFT JOIN
                    clients c ON va.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
                WHERE 1=1 AND va.tenant_id = ? AND va.branch_id = ?";

        $params = [$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id];
        $types = "iiiiii";

        if (isset($_GET['passport']) && !empty($_GET['passport'])) {
            $query .= " AND va.passport_number LIKE ?";
            $params[] = "%" . $_GET['passport'] . "%";
            $types .= "s";
            $response['debug'][] = "Searching by Passport: " . $_GET['passport'];
        }

        if (isset($_GET['applicant']) && !empty($_GET['applicant'])) {
            $query .= " AND va.applicant_name LIKE ?";
            $params[] = "%" . $_GET['applicant'] . "%";
            $types .= "s";
            $response['debug'][] = "Searching by Applicant Name: " . $_GET['applicant'];
        }

        // Add order by clause
        $query .= " ORDER BY va.applied_date DESC";
        
        $response['debug'][] = "Query: " . $query;

        // Prepare and execute the statement
         $stmt = $pdo->prepare($query);
         if ($stmt === false) {
             throw new Exception("Prepare failed");
         }

         $stmt->execute($params);
         $result = $stmt;

         // Fetch results
         while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $response['visas'][] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'phone' => $row['phone'],
                'applicant_name' => $row['applicant_name'],
                'passport_number' => $row['passport_number'],
                'country' => $row['country'],
                'visa_type' => $row['visa_type'],
                'applied_date' => $row['applied_date'],
                'currency' => $row['currency'],
                'sold' => $row['sold'],
                'base' => $row['base'],
                'supplier_name' => $row['supplier_name'],
                'client_name' => $row['client_name']
            ];
        }

        $response['success'] = true;
        if (empty($response['visas'])) {
            $response['message'] = 'No visas found matching your search criteria.';
        } else {
            $response['message'] = count($response['visas']) . ' visa(s) found.';
            }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'An error occurred while searching for visas: ' . $e->getMessage();
        $response['debug'][] = "Error: " . $e->getMessage();
        error_log("Error in search_visas.php: " . $e->getMessage());
    }
} else {
    $response['message'] = 'Please provide search criteria.';
    $response['debug'][] = "No search parameters provided";
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 