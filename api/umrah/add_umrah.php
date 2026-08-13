<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
umrah_require('member_create');

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

// Family creation fields (used when the booking carries no family_id, i.e.
// members are added to a brand-new family from the group "Add Member" flow)
$new_family_head   = isset($_POST['head_of_family']) ? DbSecurity::validateInput($_POST['head_of_family'], 'string', ['maxlength' => 255]) : null;
$new_family_contact = isset($_POST['contact']) ? DbSecurity::validateInput($_POST['contact'], 'string', ['maxlength' => 255]) : null;
$new_family_address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;
$new_family_tazmin = isset($_POST['tazmin']) ? DbSecurity::validateInput($_POST['tazmin'], 'string', ['maxlength' => 255]) : null;
$new_family_group  = isset($_POST['group_id']) ? DbSecurity::validateInput($_POST['group_id'], 'int') : null;
if (empty($new_family_tazmin)) { $new_family_tazmin = 'Not Done'; }

// Connect using PDO
require_once '../../includes/db.php';

// Include WhatsApp Manager for notifications
require_once '../../api/whatsapp/WhatsAppManager.php';

// Validate inputs using DbSecurity
$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int') : null;
$package_id = isset($_POST['package_id']) ? DbSecurity::validateInput($_POST['package_id'], 'int') : null;
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
$relation = isset($_POST['relation']) ? DbSecurity::validateInput($_POST['relation'], 'string') : '';
$g_name = isset($_POST['g_name']) ? DbSecurity::validateInput($_POST['g_name'], 'string') : '';
$father_name = isset($_POST['father_name']) ? DbSecurity::validateInput($_POST['father_name'], 'string') : '';
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float') : 0;
$grand_sold_price = isset($_POST['grand_sold_price']) ? DbSecurity::validateInput($_POST['grand_sold_price'], 'float') : 0;
if ($grand_sold_price < 0) { $grand_sold_price = 0; }
$sale_currency = isset($_POST['sale_currency']) ? DbSecurity::validateInput($_POST['sale_currency'], 'string') : 'USD';
if (!in_array(strtoupper($sale_currency), ['USD', 'AFS'])) { $sale_currency = 'USD'; }
$exchange_rate = isset($_POST['exchange_rate']) ? (float)$_POST['exchange_rate'] : 1.0;
if ($exchange_rate <= 0) { $exchange_rate = 1.0; }
$photo_path = isset($_POST['photo_path']) ? DbSecurity::validateInput($_POST['photo_path'], 'string') : null;
$passport_path = isset($_POST['passport_path']) ? DbSecurity::validateInput($_POST['passport_path'], 'string') : null;

// Empty date fields must be NULL, not '' (MySQL DATE columns reject '')
$entry_date = trim((string)$entry_date) === '' ? null : $entry_date;
$dob = trim((string)$dob) === '' ? null : $dob;
$passport_expiry = trim((string)$passport_expiry) === '' ? null : $passport_expiry;

// Process services
$services = $_POST['services'] ?? [];
$total_base_price = 0;
$total_sold_price = 0;
$total_profit = 0;

$processed_services = [];
foreach ($services as $service) {
    $service_type = isset($service['service_type']) ? DbSecurity::validateInput($service['service_type'], 'string') : null;
    $supplier_id = isset($service['supplier_id']) ? DbSecurity::validateInput($service['supplier_id'], 'int') : null;
    $supplier_id = $supplier_id ? (int)$supplier_id : null;
    $currency = isset($service['currency']) ? DbSecurity::validateInput($service['currency'], 'string') : null;
    // Package chip rows carry no currency; fall back to the booking's sale currency
    $currency = !empty($currency) ? $currency : $sale_currency;
    $base_price = isset($service['base_price']) ? DbSecurity::validateInput($service['base_price'], 'float') : 0;
    $sold_price = isset($service['sold_price']) ? DbSecurity::validateInput($service['sold_price'], 'float') : 0;

    // Price-engine snapshot fields (optional; manual rows keep null)
    $service_id = isset($service['service_id']) ? DbSecurity::validateInput($service['service_id'], 'int') : null;
    $pricing_unit = isset($service['pricing_unit']) ? DbSecurity::validateInput($service['pricing_unit'], 'string') : null;
    $quantity = isset($service['quantity']) ? max(1, (int)DbSecurity::validateInput($service['quantity'], 'int')) : 1;
    $is_optional = isset($service['is_optional']) ? (int)$service['is_optional'] : 0;
    $hotel_id = isset($service['hotel_id']) ? DbSecurity::validateInput($service['hotel_id'], 'int') : null;
    $room_type_id = isset($service['room_type_id']) ? DbSecurity::validateInput($service['room_type_id'], 'int') : null;

    $base_currency = strtoupper(trim((string)$currency));
    $base_in_sale = (empty($base_currency) || $base_currency === strtoupper($sale_currency)) ? $base_price : ($base_price / $exchange_rate);
    $profit = $sold_price - $base_in_sale;

    if (!empty($service_type)) {
        // Supplier-less package lines are kept as skeleton rows (supplier_id NULL;
        // view details shows them, fulfillment assigns the supplier later).
        $processed_services[] = [
            'service_type' => $service_type,
            'supplier_id' => $supplier_id,
            'service_id' => $service_id,
            'pricing_unit' => $pricing_unit,
            'quantity' => $quantity,
            'is_optional' => $is_optional,
            'hotel_id' => $hotel_id ? (int)$hotel_id : null,
            'room_type_id' => $room_type_id ? (int)$room_type_id : null,
            'currency' => $currency,
            'base_price' => $base_price,
            'sold_price' => $sold_price,
            'profit' => $profit,
            'base_in_sale' => $base_in_sale,
            'rate_conversion' => (!empty($currency) && $base_currency !== strtoupper($sale_currency)) ? $exchange_rate : null
        ];

        $total_base_price += $base_in_sale;
        $total_sold_price += $sold_price;
        $total_profit += $profit;
    }
}

// Apply discount to total sold price
if ($grand_sold_price > 0) {
    $total_sold_price = $grand_sold_price;
}
$total_sold_price -= $discount;
$total_profit = $total_sold_price - $total_base_price;

// Calculate due and amount paid
$due = (float)$total_sold_price - (float)$paid - (float)$received_bank_payment;
$amount_paid = (float)$paid + (float)$received_bank_payment;

// ---- Phase 31: booking integrity validation ---------------------------------
if (empty(trim((string)$name))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Member name is required.']);
    exit;
}
if (!$family_id) {
    // No family provided — create the new family along with this booking
    if (empty($new_family_head) || empty($new_family_contact) || empty($new_family_address) || empty($new_family_group)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Family details (head of family, contact, address and group) are required to add members to a new family.']);
        exit;
    }
    $famGroupOk = $pdo->prepare("SELECT 1 FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
    $famGroupOk->execute([$new_family_group, $tenant_id, $branch_id]);
    if (!$famGroupOk->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid group selected for the new family.']);
        exit;
    }
    $familyWillBeCreated = true;
} else {
    $familyWillBeCreated = false;
}
if (!$package_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A package is required to create a booking.']);
    exit;
}
$pkgOk = $pdo->prepare("SELECT 1 FROM umrah_packages WHERE id = ? AND tenant_id = ? AND status = 'active'");
$pkgOk->execute([$package_id, $tenant_id]);
if (!$pkgOk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Package is inactive or does not belong to this tenant.']);
    exit;
}
if ($paid < 0 || $received_bank_payment < 0 || $discount < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paid, bank payment and discount amounts cannot be negative.']);
    exit;
}
if ($received_bank_payment > 0 && empty($bank_receipt_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bank receipt number is required when a bank payment is received.']);
    exit;
}
if ($due < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment exceeds the sold price (due amount cannot be negative).']);
    exit;
}

// Validate passport expiry (must be at least 6 months from today).
// Invalid or past dates are treated as "not provided" — only real
// future expiries are enforced.
if (!empty($passport_expiry)) {
    try {
        $expiryDate = new DateTime($passport_expiry);
    } catch (Exception $e) {
        $expiryDate = null;
    }
    if ($expiryDate !== null && $expiryDate >= (new DateTime())->setTime(0, 0)) {
        $sixMonthsLater = (new DateTime())->modify('+6 months');
        if ($expiryDate < $sixMonthsLater) {
            echo json_encode([
                'success' => false,
                'message' => 'Passport must be valid for at least 6 months from today for Umrah visa requirements'
            ]);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    try {
        // Create the family first when members are added to a new family
        if ($familyWillBeCreated) {
            $stmt_new_family = $pdo->prepare("
                INSERT INTO families (group_id, head_of_family, contact, address, package_type, location, tazmin, visa_status, province, district, tenant_id, branch_id, created_by)
                VALUES (?, ?, ?, ?, NULL, NULL, ?, 'Not Applied', '', '', ?, ?, ?)
            ");
            $stmt_new_family->execute([$new_family_group, $new_family_head, $new_family_contact, $new_family_address, $new_family_tazmin, $tenant_id, $branch_id, $user_id]);
            $family_id = (int)$pdo->lastInsertId();
        }

        // Insert into umrah_bookings
         $stmt = $pdo->prepare("
             INSERT INTO umrah_bookings (
                 family_id, package_id, sold_to, paid_to, entry_date, name,
                 dob, gender, passport_number, passport_expiry,
                 id_type, flight_date, return_date, duration, room_type,
                 price, sold_price, profit, received_bank_payment,
                 bank_receipt_number, paid, due,
                 created_by, remarks, relation, gfname, fname, discount,
                 photo_path, passport_path, tenant_id, branch_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?, ?, ?, ?, ?, ?,
                       ?, ?, ?, ?)
         ");

        $stmt->bindParam(1, $family_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $package_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $soldTo, PDO::PARAM_INT);
        $stmt->bindParam(4, $paidTo, PDO::PARAM_INT);
        $stmt->bindParam(5, $entry_date, PDO::PARAM_STR);
        $stmt->bindParam(6, $name, PDO::PARAM_STR);
        $stmt->bindParam(7, $dob, PDO::PARAM_STR);
        $stmt->bindParam(8, $gender, PDO::PARAM_STR);
        $stmt->bindParam(9, $passport_number, PDO::PARAM_STR);
        $stmt->bindParam(10, $passport_expiry, PDO::PARAM_STR);
        $stmt->bindParam(11, $id_type, PDO::PARAM_STR);
        $stmt->bindParam(12, $flight_date, PDO::PARAM_STR);
        $stmt->bindParam(13, $return_date, PDO::PARAM_STR);
        $stmt->bindParam(14, $duration, PDO::PARAM_STR);
        $stmt->bindParam(15, $room_type, PDO::PARAM_STR);
        $stmt->bindParam(16, $total_base_price, PDO::PARAM_STR);
        $stmt->bindParam(17, $total_sold_price, PDO::PARAM_STR);
        $stmt->bindParam(18, $total_profit, PDO::PARAM_STR);
        $stmt->bindParam(19, $received_bank_payment, PDO::PARAM_STR);
        $stmt->bindParam(20, $bank_receipt_number, PDO::PARAM_STR);
        $stmt->bindParam(21, $paid, PDO::PARAM_STR);
        $stmt->bindParam(22, $due, PDO::PARAM_STR);
        $stmt->bindParam(23, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(24, $remarks, PDO::PARAM_STR);
        $stmt->bindParam(25, $relation, PDO::PARAM_STR);
        $stmt->bindParam(26, $g_name, PDO::PARAM_STR);
        $stmt->bindParam(27, $father_name, PDO::PARAM_STR);
        $stmt->bindParam(28, $discount, PDO::PARAM_STR);
        $stmt->bindParam(29, $photo_path, PDO::PARAM_STR);
        $stmt->bindParam(30, $passport_path, PDO::PARAM_STR);
        $stmt->bindParam(31, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(32, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException("Failed to insert umrah booking");
        }

        $umrah_id = $pdo->lastInsertId();

        // Insert services
        if (!empty($processed_services)) {
            $service_stmt = $pdo->prepare("
                INSERT INTO umrah_booking_services (
                    tenant_id, booking_id, service_type, supplier_id,
                    service_id, pricing_unit, quantity, is_optional,
                    base_price, sold_price, profit, currency,
                    supplier_currency, supplier_amount, exchange_rate,
                    cost_currency, cost_amount, selling_currency, selling_amount,
                    total_cost, total_selling, price_snapshot,
                    status, branch_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($processed_services as $service) {
                $snapshot = null;
                if (!empty($service['service_id'])) {
                    $snapshot = json_encode([
                        'service_id'      => $service['service_id'],
                        'package_quantity'=> $service['quantity'],
                        'pricing_unit'    => $service['pricing_unit'],
                        'is_optional'     => $service['is_optional'],
                        'hotel_id'        => $service['hotel_id'],
                        'room_type_id'    => $service['room_type_id'],
                        'currency'        => $service['currency'],
                        'base_price'      => (float)$service['base_price'],
                        'sold_price'      => (float)$service['sold_price'],
                        'profit'          => (float)$service['profit'],
                        'sale_currency'   => $sale_currency,
                        'sale_exchange_rate' => $service['rate_conversion']
                    ], JSON_UNESCAPED_UNICODE);
                }

                $service_stmt->bindValue(1, $tenant_id, PDO::PARAM_INT);
                $service_stmt->bindValue(2, $umrah_id, PDO::PARAM_INT);
                $service_stmt->bindValue(3, $service['service_type'], PDO::PARAM_STR);
                $service_stmt->bindValue(4, $service['supplier_id'], $service['supplier_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $service_stmt->bindValue(5, $service['service_id'], $service['service_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $service_stmt->bindValue(6, $service['pricing_unit'], $service['pricing_unit'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $service_stmt->bindValue(7, $service['quantity'], PDO::PARAM_INT);
                $service_stmt->bindValue(8, $service['is_optional'], PDO::PARAM_INT);
                $service_stmt->bindValue(9, $service['base_price'], PDO::PARAM_STR);
                $service_stmt->bindValue(10, $service['sold_price'], PDO::PARAM_STR);
                $service_stmt->bindValue(11, $service['profit'], PDO::PARAM_STR);
                $service_stmt->bindValue(12, $service['currency'], $service['currency'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $service_stmt->bindValue(13, $service['currency'], $service['currency'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $service_stmt->bindValue(14, $service['base_price'], PDO::PARAM_STR);
                $service_stmt->bindValue(15, $service['rate_conversion'], $service['rate_conversion'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $service_stmt->bindValue(16, $sale_currency, PDO::PARAM_STR);
                $service_stmt->bindValue(17, $service['base_in_sale'], PDO::PARAM_STR);
                $service_stmt->bindValue(18, $sale_currency, PDO::PARAM_STR);
                $service_stmt->bindValue(19, $service['sold_price'], PDO::PARAM_STR);
                $service_stmt->bindValue(20, $service['base_in_sale'], PDO::PARAM_STR);
                $service_stmt->bindValue(21, $service['sold_price'], PDO::PARAM_STR);
                $service_stmt->bindValue(22, $snapshot, $snapshot === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $service_stmt->bindValue(23, 'pending', PDO::PARAM_STR);
                $service_stmt->bindValue(24, $branch_id, PDO::PARAM_INT);
                $service_stmt->execute();
            }
        }

        // Booking created - transactions will be processed when approved

        // Store sale currency + exchange rate (base -> sale) on the booking
        $stmt_upd_currency = $pdo->prepare("UPDATE umrah_bookings SET currency = ?, exchange_rate = ? WHERE booking_id = ? AND tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))");
        $stmt_upd_currency->bindParam(1, $sale_currency, PDO::PARAM_STR);
        $stmt_upd_currency->bindParam(2, $exchange_rate, PDO::PARAM_STR);
        $stmt_upd_currency->bindParam(3, $umrah_id, PDO::PARAM_INT);
        $stmt_upd_currency->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt_upd_currency->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt_upd_currency->bindParam(6, $branch_id, PDO::PARAM_INT);
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
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false]);
}
?>
