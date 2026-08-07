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
$sale_currency = isset($_POST['sale_currency']) ? DbSecurity::validateInput($_POST['sale_currency'], 'string') : 'USD';
if (!in_array(strtoupper($sale_currency), ['USD', 'AFS'])) { $sale_currency = 'USD'; }
$exchange_rate = isset($_POST['exchange_rate']) ? (float)$_POST['exchange_rate'] : 1.0;
if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
$photo_path = isset($_POST['photo_path']) ? DbSecurity::validateInput($_POST['photo_path'], 'string') : null;
$passport_path = isset($_POST['passport_path']) ? DbSecurity::validateInput($_POST['passport_path'], 'string') : null;

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
    $base_currency = strtoupper(trim((string)$currency));
    $base_in_sale = (empty($base_currency) || $base_currency === strtoupper($sale_currency)) ? $base_price : ($base_price / $exchange_rate);
    $profit = $sold_price - $base_in_sale;

    if (!empty($service_type) && !empty($supplier_id)) {
        $processed_services[] = [
            'service_type' => $service_type,
            'supplier_id' => $supplier_id,
            'currency' => $currency,
            'base_price' => $base_price,
            'sold_price' => $sold_price,
            'profit' => $profit
        ];

        $total_base_price += $base_in_sale;
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
                 photo_path, passport_path, tenant_id, branch_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?)
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
        $stmt->bindParam(28, $photo_path, PDO::PARAM_STR);
        $stmt->bindParam(29, $passport_path, PDO::PARAM_STR);
        $stmt->bindParam(30, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(31, $branch_id, PDO::PARAM_INT);

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

        // Booking created - transactions will be processed when approved

        // Store sale currency + exchange rate (base -> sale) on the booking
        $stmt_upd_currency = $pdo->prepare("UPDATE umrah_bookings SET currency = ?, exchange_rate = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_upd_currency->bindParam(1, $sale_currency, PDO::PARAM_STR);
        $stmt_upd_currency->bindParam(2, $exchange_rate, PDO::PARAM_STR);
        $stmt_upd_currency->bindParam(3, $umrah_id, PDO::PARAM_INT);
        $stmt_upd_currency->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt_upd_currency->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt_upd_currency->execute();

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
