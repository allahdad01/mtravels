<?php
// ajax/get_family_members.php
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../../includes/db.php');
require_once('../../includes/conn.php');
require_once('../security.php');

// Enforce authentication
try {
    enforce_auth();
    
    
    // Check if family ID is provided
    if (!isset($_GET['family_id']) || empty($_GET['family_id'])) {
        throw new Exception('Family ID is required');
    }
    
    $familyId = intval($_GET['family_id']);
    
    // Get family information
    $familyQuery = "SELECT * FROM families WHERE family_id = ?";
    $familyStmt = $pdo->prepare($familyQuery);
    $familyStmt->execute([$familyId]);
    $family = $familyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$family) {
        throw new Exception('Family not found');
    }
    
    // Get all family members' booking details
    $membersQuery = "
        SELECT 
            um.booking_id,
            um.name,
            um.passport_number,
            f.contact,

            um.relation,
            um.entry_date,
            um.flight_date,
            f.package_type,
            f.head_of_family
        FROM umrah_bookings um
        LEFT JOIN families f ON um.family_id = f.family_id
        WHERE um.family_id = ?
        ORDER BY 
            CASE 
                WHEN um.name = f.head_of_family THEN 1 
                ELSE 2 
            END,
            um.booking_id ASC
    ";
    
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$familyId]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($members)) {
        throw new Exception('No family members found');
    }
    
    // Format the response data
    $responseData = [
        'family_id' => $familyId,
        'family_name' => $family['head_of_family'],
        'package_type' => $members[0]['package_type'] ?? 'N/A',
        'total_members' => count($members),
        'members' => []
    ];
    
    // Process each member
    foreach ($members as $member) {
        $responseData['members'][] = [
            'booking_id' => $member['booking_id'],
            'name' => $member['name'],
            'passport_number' => $member['passport_number'],
            'phone' => $member['contact'] ?? '',

            'relation' => $member['relation'] ?? ($member['name'] === $family['head_of_family'] ? 'Head of Family' : 'Member'),
            'entry_date' => $member['entry_date'],
            'flight_date' => $member['flight_date'],
            'is_head' => ($member['name'] === $family['head_of_family'])
        ];
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Family members loaded successfully',
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_family_members.php: " . $e->getMessage() . " - User: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ]);
}
?>