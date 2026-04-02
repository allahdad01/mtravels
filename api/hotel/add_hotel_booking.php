<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Include WhatsApp Manager for notifications
require_once '../../api/whatsapp/WhatsAppManager.php';

// Database connection
require_once '../../includes/db.php';

$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize_input($_POST['title']);
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $gender = sanitize_input($_POST['gender']);
    $check_in_date = sanitize_input($_POST['check_in_date']);
    $check_out_date = sanitize_input($_POST['check_out_date']);
    $issue_date = sanitize_input($_POST['issue_date']);
    $accommodation_details = sanitize_input($_POST['accommodation_details']);
    $supplier_id = intval($_POST['supplier_id']);
    $sold_to = sanitize_input($_POST['sold_to']);
    $contact_no = sanitize_input($_POST['contact_no']);
    $base_amount = floatval($_POST['base_amount']);
    $sold_amount = floatval($_POST['sold_amount']);
    $profit = floatval($_POST['profit']);
    $currency = sanitize_input($_POST['currency']);
    $remarks = sanitize_input($_POST['remarks']);
    $order_id = sanitize_input($_POST['order_id']);
    $paid_to = sanitize_input($_POST['paid_to']);

    // Validate required fields
    if (empty($title) || empty($first_name) || empty($last_name) || empty($gender) || empty($check_in_date) ||
        empty($check_out_date) || empty($accommodation_details) || empty($supplier_id) || empty($sold_to) ||
        empty($contact_no) || empty($sold_amount) || empty($profit) || empty($currency)) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);


// Validate paid_to
$paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

// Validate order_id
$order_id = isset($_POST['order_id']) ? DbSecurity::validateInput($_POST['order_id'], 'int', ['min' => 0]) : null;

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate profit
$profit = isset($_POST['profit']) ? DbSecurity::validateInput($_POST['profit'], 'float', ['min' => 0]) : null;

// Validate sold_amount
$sold_amount = isset($_POST['sold_amount']) ? DbSecurity::validateInput($_POST['sold_amount'], 'float', ['min' => 0]) : null;

// Validate base_amount
$base_amount = isset($_POST['base_amount']) ? DbSecurity::validateInput($_POST['base_amount'], 'float', ['min' => 0]) : null;

// Validate contact_no
$contact_no = isset($_POST['contact_no']) ? DbSecurity::validateInput($_POST['contact_no'], 'string', ['maxlength' => 255]) : null;

// Validate sold_to
$sold_to = isset($_POST['sold_to']) ? DbSecurity::validateInput($_POST['sold_to'], 'string', ['maxlength' => 255]) : null;

// Validate supplier_id
$supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : null;

// Validate accommodation_details
$accommodation_details = isset($_POST['accommodation_details']) ? DbSecurity::validateInput($_POST['accommodation_details'], 'string', ['maxlength' => 255]) : null;

// Validate issue_date
$issue_date = isset($_POST['issue_date']) ? DbSecurity::validateInput($_POST['issue_date'], 'date') : null;

// Validate check_out_date
$check_out_date = isset($_POST['check_out_date']) ? DbSecurity::validateInput($_POST['check_out_date'], 'date') : null;

// Validate check_in_date
$check_in_date = isset($_POST['check_in_date']) ? DbSecurity::validateInput($_POST['check_in_date'], 'date') : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate last_name
$last_name = isset($_POST['last_name']) ? DbSecurity::validateInput($_POST['last_name'], 'string', ['maxlength' => 255]) : null;

// Validate first_name
$first_name = isset($_POST['first_name']) ? DbSecurity::validateInput($_POST['first_name'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Insert into hotel_bookings
        $stmt = $pdo->prepare("INSERT INTO hotel_bookings (title, first_name, last_name, gender, order_id, check_in_date, check_out_date, issue_date, accommodation_details, supplier_id, sold_to, paid_to, contact_no, base_amount, sold_amount, profit, currency, remarks, created_by, tenant_id, branch_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $title, PDO::PARAM_STR);
        $stmt->bindParam(2, $first_name, PDO::PARAM_STR);
        $stmt->bindParam(3, $last_name, PDO::PARAM_STR);
        $stmt->bindParam(4, $gender, PDO::PARAM_STR);
        $stmt->bindParam(5, $order_id, PDO::PARAM_STR);
        $stmt->bindParam(6, $check_in_date, PDO::PARAM_STR);
        $stmt->bindParam(7, $check_out_date, PDO::PARAM_STR);
        $stmt->bindParam(8, $issue_date, PDO::PARAM_STR);
        $stmt->bindParam(9, $accommodation_details, PDO::PARAM_STR);
        $stmt->bindParam(10, $supplier_id, PDO::PARAM_INT);
        $stmt->bindParam(11, $sold_to, PDO::PARAM_STR);
        $stmt->bindParam(12, $paid_to, PDO::PARAM_STR);
        $stmt->bindParam(13, $contact_no, PDO::PARAM_STR);
        $stmt->bindParam(14, $base_amount, PDO::PARAM_STR);
        $stmt->bindParam(15, $sold_amount, PDO::PARAM_STR);
        $stmt->bindParam(16, $profit, PDO::PARAM_STR);
        $stmt->bindParam(17, $currency, PDO::PARAM_STR);
        $stmt->bindParam(18, $remarks, PDO::PARAM_STR);
        $stmt->bindParam(19, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(20, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(21, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception("Error inserting hotel booking");
        }

        $booking_id = $pdo->lastInsertId();

        // Fetch client details
        $stmtClient = $pdo->prepare("SELECT name, client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmtClient->bindParam(1, $sold_to, PDO::PARAM_STR);
        $stmtClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtClient->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmtClient->execute()) {
            throw new Exception("Failed to fetch client details");
        }
        $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);

        if (!$clientData) {
            throw new Exception("Client not found");
        }

        // Handle client balance and transactions for regular clients
        if ($clientData['client_type'] === 'regular') {
            $currentBalance = ($currency === 'USD') ? $clientData['usd_balance'] : $clientData['afs_balance'];
            $newBalance = $currentBalance - $sold_amount;

            // Update client balance
            if ($currency === 'USD') {
                $stmtUpdateBalance = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? And tenant_id = ? And branch_id = ?");
            } else {
                $stmtUpdateBalance = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? And tenant_id = ? And branch_id = ?");
            }
            $stmtUpdateBalance->bindParam(1, $sold_amount, PDO::PARAM_STR);
            $stmtUpdateBalance->bindParam(2, $sold_to, PDO::PARAM_INT);
            $stmtUpdateBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtUpdateBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmtUpdateBalance->execute()) {
                throw new Exception("Failed to update client balance");
            }

            // Insert client transaction
            $stmtClientTrans = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, created_at, tenant_id, branch_id)
                                             VALUES (?, 'Debit', ?, ?, ?, 'hotel', ?, ?, NOW(), ?, ?)");
            $description = "Hotel booking for $title $first_name $last_name";
            $stmtClientTrans->bindParam(1, $sold_to, PDO::PARAM_INT);
            $stmtClientTrans->bindParam(2, $currency, PDO::PARAM_STR);
            $stmtClientTrans->bindParam(3, $sold_amount, PDO::PARAM_STR);
            $stmtClientTrans->bindParam(4, $newBalance, PDO::PARAM_STR);
            $stmtClientTrans->bindParam(5, $description, PDO::PARAM_STR);
            $stmtClientTrans->bindParam(6, $booking_id, PDO::PARAM_INT);
            $stmtClientTrans->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmtClientTrans->bindParam(8, $branch_id, PDO::PARAM_INT);
            if (!$stmtClientTrans->execute()) {
                throw new Exception("Failed to create client transaction");
            }
        }

        // Fetch supplier details
        $stmtSupplier = $pdo->prepare("SELECT name, balance, supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmtSupplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
        if (!$stmtSupplier->execute()) {
            throw new Exception("Failed to fetch supplier details");
        }
        $supplierData = $stmtSupplier->fetch(PDO::FETCH_ASSOC);

        if (!$supplierData) {
            throw new Exception("Supplier not found");
        }

        // Update supplier balance only if it's an external supplier
        if ($supplierData['supplier_type'] === 'External') {
            $stmtUpdateSupplier = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? And tenant_id = ? And branch_id = ?");
            $stmtUpdateSupplier->bindParam(1, $base_amount, PDO::PARAM_STR);
            $stmtUpdateSupplier->bindParam(2, $supplier_id, PDO::PARAM_INT);
            $stmtUpdateSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtUpdateSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmtUpdateSupplier->execute()) {
                throw new Exception("Failed to update supplier balance");
            }

            // Insert supplier transaction with balance
            $supplierNewBalance = $supplierData['balance'] - $base_amount;

            $stmtSupplierTrans = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, balance, transaction_of, remarks, reference_id, transaction_date, tenant_id, branch_id)
                                               VALUES (?, 'Debit', ?, ?, 'hotel', ?, ?, NOW(), ?, ?)");
            $supplierDescription = "Hotel booking for $title $first_name $last_name";
            $stmtSupplierTrans->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(2, $base_amount, PDO::PARAM_STR);
            $stmtSupplierTrans->bindParam(3, $supplierNewBalance, PDO::PARAM_STR);
            $stmtSupplierTrans->bindParam(4, $supplierDescription, PDO::PARAM_STR);
            $stmtSupplierTrans->bindParam(5, $booking_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(7, $branch_id, PDO::PARAM_INT);
        } else {
            // For non-external suppliers, just record the transaction without balance
            $stmtSupplierTrans = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, transaction_of, remarks, reference_id, transaction_date, tenant_id, branch_id, balance)
                                               VALUES (?, 'Debit', ?, 'hotel', ?, ?, NOW(), ?, ?, '0')");
            $supplierDescription = "Hotel booking for $title $first_name $last_name";
            $stmtSupplierTrans->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(2, $base_amount, PDO::PARAM_STR);
            $stmtSupplierTrans->bindParam(3, $supplierDescription, PDO::PARAM_STR);
            $stmtSupplierTrans->bindParam(4, $booking_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmtSupplierTrans->bindParam(6, $branch_id, PDO::PARAM_INT);
        }

        if (!$stmtSupplierTrans->execute()) {
            throw new Exception("Failed to create supplier transaction");
        }

        // Commit transaction
        $pdo->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'title' => $title,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,
            'accommodation_details' => $accommodation_details,
            'supplier_id' => $supplier_id,
            'sold_to' => $sold_to,
            'base_amount' => $base_amount,
            'sold_amount' => $sold_amount,
            'profit' => $profit,
            'currency' => $currency,
            'paid_to' => $paid_to,
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'hotel_bookings', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        // Send email notification to client
        require_once '../../includes/functions.php';

        // Get client email and name
        $stmt_client_email = $pdo->prepare("SELECT email, name FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmt_client_email->bindParam(1, $sold_to, PDO::PARAM_INT);
        $stmt_client_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_email->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_email->execute();
        $client_email_data = $stmt_client_email->fetch(PDO::FETCH_ASSOC);
        $client_email = $client_email_data['email'];
        $client_name = $client_email_data['name'];

        // Send email notification to client
        require_once '../../includes/functions.php';

        if (!empty($client_email)) {
            $guestName = $title . ' ' . $first_name . ' ' . $last_name;
            sendHotelNotification(
                $client_email,
                $client_name,
                $booking_id,
                $guestName,
                $check_in_date,
                $check_out_date,
                $accommodation_details,
                $sold_amount,
                $currency
            );
        }

        // Send WhatsApp notification to client (if configured)
        try {
            $whatsappManager = new WhatsAppManager($tenant_id);
            $whatsapp_result = $whatsappManager->sendBookingNotification('hotel', $booking_id);
            
            if ($whatsapp_result['success']) {
                error_log("WhatsApp notification sent for Hotel booking ID: $booking_id");
            } else {
                error_log("WhatsApp notification failed for Hotel booking ID: $booking_id - " . $whatsapp_result['message']);
            }
        } catch (Exception $e) {
            // Don't fail the operation if WhatsApp fails
            error_log("WhatsApp integration error for Hotel booking ID: $booking_id - " . $e->getMessage());
        }

        echo json_encode(["success" => true, "message" => "Hotel booking added successfully."]);

    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
}
?>

