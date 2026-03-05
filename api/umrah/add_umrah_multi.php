<?php
/**
 * Multi-Member Umrah Booking API
 * Handles insertion of multiple members with shared services, flight dates, and room info
 */

// Catch all fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once '../../api/whatsapp/WhatsAppManager.php';

// ============================================
// VALIDATE & SANITIZE COMMON FIELDS
// ============================================

$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int') : null;
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int') : null;
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int') : null;
$entry_date = isset($_POST['entry_date']) ? DbSecurity::validateInput($_POST['entry_date'], 'string') : null;
$flight_date = !empty($_POST['flight_date']) ? DbSecurity::validateInput($_POST['flight_date'], 'string') : null;
$return_date = !empty($_POST['return_date']) ? DbSecurity::validateInput($_POST['return_date'], 'string') : null;
$duration = isset($_POST['duration']) ? DbSecurity::validateInput($_POST['duration'], 'string') : null;
$room_type = isset($_POST['room_type']) ? DbSecurity::validateInput($_POST['room_type'], 'string') : null;
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string') : null;
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float') : 0;

// Hidden fields
$received_bank_payment = isset($_POST['received_bank_payment']) ? DbSecurity::validateInput($_POST['received_bank_payment'], 'float') : 0;
$bank_receipt_number = isset($_POST['bank_receipt_number']) ? DbSecurity::validateInput($_POST['bank_receipt_number'], 'string') : null;
$paid = isset($_POST['paid']) ? DbSecurity::validateInput($_POST['paid'], 'float') : 0;

// ============================================
// PROCESS SERVICES (SHARED FOR ALL MEMBERS)
// ============================================

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

// Apply discount
$total_sold_price -= $discount;
$total_profit = $total_sold_price - $total_base_price;

// ============================================
// PROCESS MEMBERS
// ============================================

$members = $_POST['members'] ?? [];

if (empty($members)) {
    echo json_encode(['success' => false, 'message' => 'No members to add']);
    exit;
}

// Calculate due amount (shared across members or per member - decide based on your logic)
$due = (float)$total_sold_price - (float)$paid - (float)$received_bank_payment;
$amount_paid = (float)$paid + (float)$received_bank_payment;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    try {
        $successful_bookings = [];
        $failed_members = [];

        foreach ($members as $member_index => $member) {
            // Validate member data
            $name = isset($member['name']) ? DbSecurity::validateInput($member['name'], 'string') : null;
            $dob = isset($member['dob']) ? DbSecurity::validateInput($member['dob'], 'string') : null;
            $gender = isset($member['gender']) ? DbSecurity::validateInput($member['gender'], 'string') : null;
            $passport_number = isset($member['passport_number']) ? DbSecurity::validateInput($member['passport_number'], 'string') : null;
            $passport_expiry = isset($member['passport_expiry']) ? DbSecurity::validateInput($member['passport_expiry'], 'string') : null;
            $id_type = isset($member['id_type']) ? DbSecurity::validateInput($member['id_type'], 'string') : null;
            $relation = isset($member['relation']) ? DbSecurity::validateInput($member['relation'], 'string') : null;
            $g_name = isset($member['g_name']) ? DbSecurity::validateInput($member['g_name'], 'string') : null;
            $father_name = isset($member['father_name']) ? DbSecurity::validateInput($member['father_name'], 'string') : null;
            $photo_path = isset($member['photo_path']) ? DbSecurity::validateInput($member['photo_path'], 'string') : null;
            $passport_path = isset($member['passport_path']) ? DbSecurity::validateInput($member['passport_path'], 'string') : null;

            // Validate required fields
            if (empty($name) || empty($dob) || empty($passport_number)) {
                $failed_members[] = "Member " . ($member_index + 1) . ": Missing required fields";
                continue;
            }

            // Validate passport expiry (6 months minimum)
            if (!empty($passport_expiry)) {
                $today = new DateTime();
                $sixMonthsLater = (new DateTime())->modify('+6 months');
                $expiryDate = new DateTime($passport_expiry);

                if ($expiryDate < $sixMonthsLater) {
                    $failed_members[] = "Member " . ($member_index + 1) . " (" . $name . "): Passport must be valid for at least 6 months";
                    continue;
                }
            }

            // Insert member booking
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

            $stmt->bindValue(1, $family_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $soldTo, PDO::PARAM_INT);
            $stmt->bindValue(3, $paidTo, PDO::PARAM_INT);
            $stmt->bindValue(4, $entry_date, PDO::PARAM_STR);
            $stmt->bindValue(5, $name, PDO::PARAM_STR);
            $stmt->bindValue(6, $dob, PDO::PARAM_STR);
            $stmt->bindValue(7, $gender, PDO::PARAM_STR);
            $stmt->bindValue(8, $passport_number, PDO::PARAM_STR);
            $stmt->bindValue(9, $passport_expiry, PDO::PARAM_STR);
            $stmt->bindValue(10, $id_type, PDO::PARAM_STR);
            $stmt->bindValue(11, $flight_date, PDO::PARAM_STR);
            $stmt->bindValue(12, $return_date, PDO::PARAM_STR);
            $stmt->bindValue(13, $duration, PDO::PARAM_STR);
            $stmt->bindValue(14, $room_type, PDO::PARAM_STR);
            $stmt->bindValue(15, $total_base_price, PDO::PARAM_STR);
            $stmt->bindValue(16, $total_sold_price, PDO::PARAM_STR);
            $stmt->bindValue(17, $total_profit, PDO::PARAM_STR);
            $stmt->bindValue(18, $received_bank_payment, PDO::PARAM_STR);
            $stmt->bindValue(19, $bank_receipt_number, PDO::PARAM_STR);
            $stmt->bindValue(20, $paid, PDO::PARAM_STR);
            $stmt->bindValue(21, $due, PDO::PARAM_STR);
            $stmt->bindValue(22, $user_id, PDO::PARAM_INT);
            $stmt->bindValue(23, $remarks, PDO::PARAM_STR);
            $stmt->bindValue(24, $relation, PDO::PARAM_STR);
            $stmt->bindValue(25, $g_name, PDO::PARAM_STR);
            $stmt->bindValue(26, $father_name, PDO::PARAM_STR);
            $stmt->bindValue(27, $discount, PDO::PARAM_STR);
            $stmt->bindValue(28, $photo_path, PDO::PARAM_STR);
            $stmt->bindValue(29, $passport_path, PDO::PARAM_STR);
            $stmt->bindValue(30, $tenant_id, PDO::PARAM_INT);
            $stmt->bindValue(31, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                $failed_members[] = "Member " . ($member_index + 1) . " (" . $name . "): Database insert failed";
                continue;
            }

            $umrah_id = $pdo->lastInsertId();
            $successful_bookings[] = ['id' => $umrah_id, 'name' => $name];

            // Insert services for this member
            if (!empty($processed_services)) {
                $service_stmt = $pdo->prepare("
                    INSERT INTO umrah_booking_services (
                        tenant_id, booking_id, service_type, supplier_id, base_price, sold_price, profit, currency, branch_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($processed_services as $service) {
                    $service_stmt->bindValue(1, $tenant_id, PDO::PARAM_INT);
                    $service_stmt->bindValue(2, $umrah_id, PDO::PARAM_INT);
                    $service_stmt->bindValue(3, $service['service_type'], PDO::PARAM_STR);
                    $service_stmt->bindValue(4, $service['supplier_id'], PDO::PARAM_INT);
                    $service_stmt->bindValue(5, $service['base_price'], PDO::PARAM_STR);
                    $service_stmt->bindValue(6, $service['sold_price'], PDO::PARAM_STR);
                    $service_stmt->bindValue(7, $service['profit'], PDO::PARAM_STR);
                    $service_stmt->bindValue(8, $service['currency'], PDO::PARAM_STR);
                    $service_stmt->bindValue(9, $branch_id, PDO::PARAM_INT);
                    $service_stmt->execute();
                }
            }

            // Log the activity
            $new_values = json_encode([
                'name' => $name,
                'passport_number' => $passport_number,
                'dob' => $dob,
                'relation' => $relation,
                'family_id' => $family_id,
                'services_count' => count($processed_services)
            ]);

            $stmt_log = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'add', 'umrah_bookings', ?, '{}', ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt_log->bindValue(1, $user_id, PDO::PARAM_INT);
            $stmt_log->bindValue(2, $umrah_id, PDO::PARAM_INT);
            $stmt_log->bindValue(3, $new_values, PDO::PARAM_STR);
            $stmt_log->bindValue(4, $_SERVER['REMOTE_ADDR'] ?? '', PDO::PARAM_STR);
            $stmt_log->bindValue(5, $_SERVER['HTTP_USER_AGENT'] ?? '', PDO::PARAM_STR);
            $stmt_log->bindValue(6, $tenant_id, PDO::PARAM_INT);
            $stmt_log->bindValue(7, $branch_id, PDO::PARAM_INT);
            $stmt_log->execute();
        }

        // Commit transaction
        $pdo->commit();

        // Return response with status
        $response = [
            'success' => true,
            'message' => count($successful_bookings) . ' member(s) added successfully'
        ];

        if (!empty($failed_members)) {
            $response['warnings'] = $failed_members;
            $response['partial'] = true;
        }

        echo json_encode($response);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding members: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
