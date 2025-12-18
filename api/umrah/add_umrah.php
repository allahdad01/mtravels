<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";
$user_id = $_SESSION['user_id'] ?? 0;

// Connect using PDO
require_once '../../includes/db.php';

// Include WhatsApp Manager for notifications
require_once '../../api/whatsapp/WhatsAppManager.php';

// Validate inputs using DbSecurity
$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int') : null;
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int') : null;
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int') : null;
$entry_date = isset($_POST['entry_date']) ? DbSecurity::validateInput($_POST['entry_date'], 'string') : null;
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string') : null;
$dob = isset($_POST['dob']) ? DbSecurity::validateInput($_POST['dob'], 'string') : null;
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string') : null;
$passport_number = isset($_POST['passport_number']) ? DbSecurity::validateInput($_POST['passport_number'], 'string') : null;
$passport_expiry = isset($_POST['passport_expiry']) ? DbSecurity::validateInput($_POST['passport_expiry'], 'string') : null;
$id_type = isset($_POST['id_type']) ? DbSecurity::validateInput($_POST['id_type'], 'string') : null;
$flight_date = !empty($_POST['flight_date']) ? DbSecurity::validateInput($_POST['flight_date'], 'string') : null;
$return_date = !empty($_POST['return_date']) ? DbSecurity::validateInput($_POST['return_date'], 'string') : null;
$duration = isset($_POST['duration']) ? DbSecurity::validateInput($_POST['duration'], 'string') : null;
$room_type = isset($_POST['room_type']) ? DbSecurity::validateInput($_POST['room_type'], 'string') : null;
$received_bank_payment = isset($_POST['received_bank_payment']) ? DbSecurity::validateInput($_POST['received_bank_payment'], 'float') : 0;
$bank_receipt_number = isset($_POST['bank_receipt_number']) ? DbSecurity::validateInput($_POST['bank_receipt_number'], 'string') : null;
$paid = isset($_POST['paid']) ? DbSecurity::validateInput($_POST['paid'], 'float') : 0;
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string') : null;
$relation = isset($_POST['relation']) ? DbSecurity::validateInput($_POST['relation'], 'string') : null;
$g_name = isset($_POST['g_name']) ? DbSecurity::validateInput($_POST['g_name'], 'string') : null;
$father_name = isset($_POST['father_name']) ? DbSecurity::validateInput($_POST['father_name'], 'string') : null;
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float') : 0;

// Process services
$services = $_POST['services'] ?? [];
$total_base_price = 0;
$total_sold_price = 0;
$total_profit = 0;

$processed_services = [];
foreach ($services as $service) {
    $service_type = isset($service['service_type']) ? DbSecurity::validateInput($service['service_type'], 'string') : null;
    $supplier_id = isset($service['supplier_id']) ? DbSecurity::validateInput($service['supplier_id'], 'int') : null;
    $currency = isset($service['currency']) ? DbSecurity::validateInput($service['currency'], 'string') : null;
    $base_price = isset($service['base_price']) ? DbSecurity::validateInput($service['base_price'], 'float') : 0;
    $sold_price = isset($service['sold_price']) ? DbSecurity::validateInput($service['sold_price'], 'float') : 0;
    $profit = $sold_price - $base_price;

    if (!empty($service_type) && !empty($supplier_id)) {
        $processed_services[] = [
            'service_type' => $service_type,
            'supplier_id' => $supplier_id,
            'currency' => $currency,
            'base_price' => $base_price,
            'sold_price' => $sold_price,
            'profit' => $profit
        ];

        $total_base_price += $base_price;
        $total_sold_price += $sold_price;
        $total_profit += $profit;
    }
}

// Apply discount to total sold price
$total_sold_price -= $discount;
$total_profit = $total_sold_price - $total_base_price;

// Calculate due and amount paid
$due = (float)$total_sold_price - (float)$paid - (float)$received_bank_payment;
$amount_paid = (float)$paid + (float)$received_bank_payment;

// Validate passport expiry (must be at least 6 months from today)
if (!empty($passport_expiry)) {
    $today = new DateTime();
    $sixMonthsLater = (new DateTime())->modify('+6 months');
    $expiryDate = new DateTime($passport_expiry);

    if ($expiryDate < $sixMonthsLater) {
        echo json_encode([
            'success' => false,
            'message' => 'Passport must be valid for at least 6 months from today for Umrah visa requirements'
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    try {
        // Insert into umrah_bookings
        $stmt = $pdo->prepare("
            INSERT INTO umrah_bookings (
                family_id, sold_to, paid_to, entry_date, name,
                dob, gender, passport_number, passport_expiry,
                id_type, flight_date, return_date, duration, room_type,
                price, sold_price, profit, received_bank_payment,
                bank_receipt_number, paid, due,
                created_by, remarks, relation, gfname, fname, discount,
                tenant_id, branch_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                      ?, ?, ?, ?, ?, ?, ?, ?, ?,
                      ?, ?, ?, ?, ?, ?, ?, ?, ?,
                      ?, ?)
        ");

        $stmt->bindParam(1, $family_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $soldTo, PDO::PARAM_INT);
        $stmt->bindParam(3, $paidTo, PDO::PARAM_INT);
        $stmt->bindParam(4, $entry_date, PDO::PARAM_STR);
        $stmt->bindParam(5, $name, PDO::PARAM_STR);
        $stmt->bindParam(6, $dob, PDO::PARAM_STR);
        $stmt->bindParam(7, $gender, PDO::PARAM_STR);
        $stmt->bindParam(8, $passport_number, PDO::PARAM_STR);
        $stmt->bindParam(9, $passport_expiry, PDO::PARAM_STR);
        $stmt->bindParam(10, $id_type, PDO::PARAM_STR);
        $stmt->bindParam(11, $flight_date, PDO::PARAM_STR);
        $stmt->bindParam(12, $return_date, PDO::PARAM_STR);
        $stmt->bindParam(13, $duration, PDO::PARAM_STR);
        $stmt->bindParam(14, $room_type, PDO::PARAM_STR);
        $stmt->bindParam(15, $total_base_price, PDO::PARAM_STR);
        $stmt->bindParam(16, $total_sold_price, PDO::PARAM_STR);
        $stmt->bindParam(17, $total_profit, PDO::PARAM_STR);
        $stmt->bindParam(18, $received_bank_payment, PDO::PARAM_STR);
        $stmt->bindParam(19, $bank_receipt_number, PDO::PARAM_STR);
        $stmt->bindParam(20, $paid, PDO::PARAM_STR);
        $stmt->bindParam(21, $due, PDO::PARAM_STR);
        $stmt->bindParam(22, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(23, $remarks, PDO::PARAM_STR);
        $stmt->bindParam(24, $relation, PDO::PARAM_STR);
        $stmt->bindParam(25, $g_name, PDO::PARAM_STR);
        $stmt->bindParam(26, $father_name, PDO::PARAM_STR);
        $stmt->bindParam(27, $discount, PDO::PARAM_STR);
        $stmt->bindParam(28, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(29, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException("Failed to insert umrah booking");
        }

        $umrah_id = $pdo->lastInsertId();

        // Insert services
        if (!empty($processed_services)) {
            $service_stmt = $pdo->prepare("
                INSERT INTO umrah_booking_services (
                    tenant_id, booking_id, service_type, supplier_id, base_price, sold_price, profit, currency, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($processed_services as $service) {
                $service_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                $service_stmt->bindParam(2, $umrah_id, PDO::PARAM_INT);
                $service_stmt->bindParam(3, $service['service_type'], PDO::PARAM_STR);
                $service_stmt->bindParam(4, $service['supplier_id'], PDO::PARAM_INT);
                $service_stmt->bindParam(5, $service['base_price'], PDO::PARAM_STR);
                $service_stmt->bindParam(6, $service['sold_price'], PDO::PARAM_STR);
                $service_stmt->bindParam(7, $service['profit'], PDO::PARAM_STR);
                $service_stmt->bindParam(8, $service['currency'], PDO::PARAM_STR);
                $service_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $service_stmt->execute();
            }
        }

        // Get client email and name for notification
        $client_email = '';
        $client_name = '';

        $stmt_client_email = $pdo->prepare("SELECT email, name FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_email->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_email->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_email->execute();
        $client_email_data = $stmt_client_email->fetch(PDO::FETCH_ASSOC);
        if ($client_email_data) {
            $client_email = $client_email_data['email'];
            $client_name = $client_email_data['name'];
        }

        // Send email notification to client
        require_once '../../includes/functions.php';

        if (!empty($client_email)) {
            $currency = isset($processed_services[0]['currency']) ? $processed_services[0]['currency'] : 'USD';
            sendUmrahNotification(
                $client_email,
                $client_name,
                $umrah_id,
                $name,
                $flight_date,
                $return_date,
                $room_type,
                $total_sold_price,
                $amount_paid,
                $due,
                $currency
            );
        }

        // Send WhatsApp notification to client (if configured)
        try {
            $whatsappManager = new WhatsAppManager($tenant_id);
            $whatsapp_result = $whatsappManager->sendBookingNotification('umrah', $umrah_id);

            if ($whatsapp_result['success']) {
                error_log("WhatsApp notification sent for Umrah booking ID: $umrah_id");
            } else {
                error_log("WhatsApp notification failed for Umrah booking ID: $umrah_id - " . $whatsapp_result['message']);
            }
        } catch (Exception $e) {
            // Don't fail the operation if WhatsApp fails
            error_log("WhatsApp integration error for Umrah booking ID: $umrah_id - " . $e->getMessage());
        }

        // Fetch client details
        $stmt_client_info = $pdo->prepare("SELECT name, usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_client_info->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_info->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_client_info->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_client_info->execute();
        $client_data = $stmt_client_info->fetch(PDO::FETCH_ASSOC);
        $client_name = $client_data['name'];
        $usd_balance = $client_data['usd_balance'];
        $afs_balance = $client_data['afs_balance'];
        $client_type = $client_data['client_type'];

        // Record client transaction for amount paid
        $client_currency = 'USD'; // Default, or determine based on services
        $current_balance_client = ($client_currency === 'USD') ? $usd_balance : $afs_balance;
        $new_balance_client = $current_balance_client - $amount_paid;

        $description = "Client was debited $amount_paid for umrah booking for $name";
        $stmt_client_transaction = $pdo->prepare("INSERT INTO client_transactions (
            client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id
        ) VALUES (?, 'Debit', 'umrah', ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt_client_transaction->bindParam(1, $soldTo, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(2, $umrah_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(3, $amount_paid, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(4, $new_balance_client, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(5, $client_currency, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(6, $description, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_client_transaction->execute();

        // Process transactions for each service/supplier
        foreach ($processed_services as $service) {
            $supplier_id = $service['supplier_id'];
            $base_price = $service['base_price'];
            $currency = $service['currency'];
            $service_type = $service['service_type'];

            // Fetch supplier details
            $stmt_supplier_info = $pdo->prepare("SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_supplier_info->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmt_supplier_info->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_supplier_info->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_supplier_info->execute();
            $supplier_data = $stmt_supplier_info->fetch(PDO::FETCH_ASSOC);
            $supplier_name = $supplier_data['name'];
            $supplier_type = $supplier_data['supplier_type'];
            $balance = $supplier_data['balance'];

            $new_balance_supplier = $balance - $base_price;

            // Insert supplier transaction
            $stmt_transaction = $pdo->prepare("INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id
            ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'umrah', ?, ?)");
            $remarks = "Base amount of $base_price $currency deducted for umrah $service_type.";
            $stmt_transaction->bindParam(1, $supplier_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(2, $umrah_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(3, $base_price, PDO::PARAM_STR);
            $stmt_transaction->bindParam(4, $new_balance_supplier, PDO::PARAM_STR);
            $stmt_transaction->bindParam(5, $remarks, PDO::PARAM_STR);
            $stmt_transaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmt_transaction->bindParam(7, $branch_id, PDO::PARAM_INT);
            $stmt_transaction->execute();

            // Update supplier balance if external
            if ($supplier_type === 'External') {
                $stmt_balance = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_balance->bindParam(1, $base_price, PDO::PARAM_STR);
                $stmt_balance->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmt_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_balance->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_balance->execute();
            }
        }

        // Update client's balance
        if ($client_type === 'regular') {
            if ($client_currency === 'USD') {
                $stmt_deduct_client_balance = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            } else {
                $stmt_deduct_client_balance = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            }

            $stmt_deduct_client_balance->bindParam(1, $amount_paid, PDO::PARAM_STR);
            $stmt_deduct_client_balance->bindParam(2, $soldTo, PDO::PARAM_INT);
            $stmt_deduct_client_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_deduct_client_balance->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt_deduct_client_balance->execute();
        }

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'family_id' => $family_id,
            'sold_to' => $soldTo,
            'paid_to' => $paidTo,
            'name' => $name,
            'passport_number' => $passport_number,
            'flight_date' => $flight_date,
            'return_date' => $return_date,
            'room_type' => $room_type,
            'total_base_price' => $total_base_price,
            'total_sold_price' => $total_sold_price,
            'total_profit' => $total_profit,
            'services' => $processed_services,
            'remarks' => $remarks,
            'discount' => $discount
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'umrah_bookings', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $umrah_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        // Commit the transaction
        $pdo->commit();

        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false]);
}
?>
