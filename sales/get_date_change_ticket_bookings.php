<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

if (isset($_GET['id'])) {
    $ticket_id = intval($_GET['id']);

    try {
        // Get date change ticket details with all necessary information
        $ticketStmt = $pdo->prepare("
            SELECT 
                dct.id, 
                dct.ticket_id, 
                dct.departure_date, 
                dct.supplier_penalty, 
                dct.service_penalty, 
                dct.status,
                dct.currency,
                tb.passenger_name, 
                tb.pnr
            FROM date_change_tickets dct
            LEFT JOIN ticket_bookings tb ON dct.ticket_id = tb.id
            WHERE dct.id = ? AND dct.tenant_id = ?
        ");
        
        $ticketStmt->execute([$ticket_id, $tenant_id]);
        $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

        // Check if ticket exists
        if (!$ticket) {
            echo json_encode([
                'success' => false,
                'message' => 'Date change ticket not found'
            ]);
            exit;
        }

        // Calculate the total amount (supplier_penalty + service_penalty)
        $totalAmount = floatval($ticket['supplier_penalty']) + floatval($ticket['service_penalty']);
        
        // Return ticket data directly (not nested) as the frontend expects
        echo json_encode([
            'id' => $ticket['id'],
            'passenger_name' => $ticket['passenger_name'],
            'pnr' => $ticket['pnr'],
            'departure_date' => $ticket['departure_date'],
            'currency' => $ticket['currency'] ?: 'USD',
            'sold' => $totalAmount,
            'success' => true
        ]);
        
    } catch (PDOException $e) {
        error_log("Error fetching date change ticket: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching ticket details: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ticket ID provided'
    ]);
}
?> 