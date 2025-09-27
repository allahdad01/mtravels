<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];

// Set proper headers for JSON response
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get filter parameters
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$status = isset($_POST['status']) ? $_POST['status'] : 'read';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format'
    ]);
    exit();
}

// Validate status
if (!in_array($status, ['read', 'unread'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid status value'
    ]);
    exit();
}

// Include database connection
require_once '../../includes/db.php';

// Function to display notifications
function generateNotificationHtml($stmt, $status) {
    $html = '';
    
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notification_id = htmlspecialchars($row['id']);
            $message = htmlspecialchars($row['message']);
            $related_name = htmlspecialchars($row['related_name'] ?? '');
            $transaction_amount = htmlspecialchars($row['transaction_amount'] ?? '');
            $transaction_currency = htmlspecialchars($row['transaction_currency'] ?? '');
            $created_at = htmlspecialchars($row['created_at']);
            $transaction_type = htmlspecialchars($row['transaction_type'] ?? '');
            
            $html .= '<tr class="' . $status . '">';
            
            // User avatar
            $html .= '<td width="50" class="align-middle">';
            $html .= '<img class="rounded-circle" style="width:40px;" src="../assets/images/user/avatar-1.jpg" alt="activity-user">';
            $html .= '</td>';
            
            // Action buttons
            $html .= '<td width="100" class="align-middle">';
            if ($status === 'unread') {
                // Array of transaction types that should only show read button
                $read_only_types = ['deposit_sarafi', 'hawala_sarafi', 'withdrawal_sarafi', 'supplier_fund', 'client_fund', 'expense', 'expense_update', 'expense_delete'];
                $show_only_read = in_array($transaction_type, $read_only_types);
                
                if (!$show_only_read) {
                    $html .= '<button class="btn btn-success btn-sm approve-button" ' .
                             'data-id="' . $notification_id . '" ' .
                             'data-amount="' . $transaction_amount . '" ' .
                             'data-currency="' . $transaction_currency . '" ' .
                             'data-type="' . $transaction_type . '">' .
                             'Received' .
                             '</button>';
                }
                
                $html .= '<button class="btn btn-info btn-sm read-button" ' .
                         'data-id="' . $notification_id . '">' .
                         'Read' .
                         '</button>';
            } else {
                $html .= '<button class="btn btn-secondary btn-sm" disabled>' .
                         'Read' .
                         '</button>';
            }
            $html .= '</td>';
            
            // Message content
            $html .= '<td class="notification-content">';
            $html .= '<div class="message-wrapper">';
            $html .= '<h6 class="message-text">' . $message . '</h6>';
            $html .= '</div>';
            $html .= '</td>';
            
            // Date
            $html .= '<td width="150" class="align-middle">';
            $html .= '<h6 class="text-muted">';
            $html .= '<i class="fas fa-circle text-c-green f-10 m-r-15"></i>';
            $html .= date('M d, Y', strtotime($created_at));
            $html .= '</h6>';
            $html .= '</td>';
            
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="4">No ' . $status . ' notifications available</td></tr>';
    }
    
    return $html;
}

try {
    // Prepare the query to fetch notifications based on filter
    $query = "
        SELECT n.*, 
               CASE 
                   WHEN n.transaction_type = 'visa' THEN va.applicant_name 
                   WHEN n.transaction_type = 'supplier' THEN s.name
                   WHEN n.transaction_type = 'umrah' THEN ub.name 
                   ELSE NULL 
               END AS related_name,
               CASE 
                   WHEN n.transaction_type = 'visa' THEN va.base 
                   WHEN n.transaction_type = 'supplier' THEN st.amount
                   WHEN n.transaction_type = 'umrah' THEN ub.sold_price 
                   ELSE 0 
               END AS transaction_amount,
               CASE 
                   WHEN n.transaction_type = 'visa' THEN va.currency 
                   WHEN n.transaction_type = 'supplier' THEN s.currency 
                   ELSE NULL 
               END AS transaction_currency
        FROM notifications n
        LEFT JOIN visa_applications va ON n.transaction_id = va.id AND n.transaction_type = 'visa'
        LEFT JOIN umrah_bookings ub ON n.transaction_id = ub.booking_id AND n.transaction_type = 'umrah'
        LEFT JOIN supplier_transactions st ON n.transaction_id = st.id AND n.transaction_type = 'supplier'
        LEFT JOIN suppliers s ON st.supplier_id = s.id OR va.supplier = s.id
        WHERE n.status = ? AND DATE(n.created_at) = ? AND n.tenant_id = ?
        ORDER BY n.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$status, $date, $tenant_id]);
    
    // Generate HTML for notifications
    $html = generateNotificationHtml($stmt, $status);
    
    // Return success response with HTML
    echo json_encode([
        'status' => 'success',
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    // Log error but don't expose details in response
    error_log("Error in get_filtered_notifications.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit();
} 