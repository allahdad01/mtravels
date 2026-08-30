<?php
/**
 * Multi-Member Umrah Booking API
 * Handles insertion of multiple members with shared services, flight dates, and room info
 */

// Catch all fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.member_create');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

// Family creation fields (used when the booking carries no family_id, i.e.
// members are added to a brand-new family from the group "Add Member" flow)
$new_family_head   = isset($_POST['head_of_family']) ? DbSecurity::validateInput($_POST['head_of_family'], 'string', ['maxlength' => 255]) : null;
$new_family_contact = isset($_POST['contact']) ? DbSecurity::validateInput($_POST['contact'], 'string', ['maxlength' => 255]) : null;
$new_family_address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;
$new_family_tazmin = isset($_POST['tazmin']) ? DbSecurity::validateInput($_POST['tazmin'], 'string', ['maxlength' => 255]) : null;
$new_family_group  = isset($_POST['group_id']) ? DbSecurity::validateInput($_POST['group_id'], 'int') : null;
if (empty($new_family_tazmin)) { $new_family_tazmin = 'Not Done'; }
$familyWillBeCreated = false;

require_once '../../includes/db.php';
require_once '../../api/whatsapp/WhatsAppManager.php';

// ============================================
// VALIDATE & SANITIZE COMMON FIELDS
// ============================================

$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int') : null;
$package_id = isset($_POST['package_id']) ? DbSecurity::validateInput($_POST['package_id'], 'int') : null;
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int') : null;
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int') : null;
$entry_date = isset($_POST['entry_date']) ? DbSecurity::validateInput($_POST['entry_date'], 'string') : null;
$flight_date = !empty($_POST['flight_date']) ? DbSecurity::validateInput($_POST['flight_date'], 'string') : null;
$return_date = !empty($_POST['return_date']) ? DbSecurity::validateInput($_POST['return_date'], 'string') : null;
$duration = isset($_POST['duration']) ? DbSecurity::validateInput($_POST['duration'], 'string') : null;
$room_type = isset($_POST['room_type']) ? DbSecurity::validateInput($_POST['room_type'], 'string') : null;
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string') : null;
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float') : 0;
$grand_sold_price = isset($_POST['grand_sold_price']) ? DbSecurity::validateInput($_POST['grand_sold_price'], 'float') : 0;
if ($grand_sold_price < 0) { $grand_sold_price = 0; }

$sale_currency = isset($_POST['sale_currency']) ? DbSecurity::validateInput($_POST['sale_currency'], 'string') : 'USD';
if (!in_array(strtoupper($sale_currency), ['USD', 'AFS'])) { $sale_currency = 'USD'; }
$exchange_rate = isset($_POST['exchange_rate']) ? (float)$_POST['exchange_rate'] : 1.0;
if ($exchange_rate <= 0) { $exchange_rate = 1.0; }

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

// Apply discount
if ($grand_sold_price > 0) {
    $total_sold_price = $grand_sold_price;
}
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

// ---- Phase 31: booking integrity validation ---------------------------------
if (!$family_id) {
    // No family provided — create the new family along with these bookings
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
            $relation = isset($member['relation']) ? DbSecurity::validateInput($member['relation'], 'string') : '';
            $g_name = isset($member['g_name']) ? DbSecurity::validateInput($member['g_name'], 'string') : '';
            $father_name = isset($member['father_name']) ? DbSecurity::validateInput($member['father_name'], 'string') : '';
            $photo_path = isset($member['photo_path']) ? DbSecurity::validateInput($member['photo_path'], 'string') : null;
            $passport_path = isset($member['passport_path']) ? DbSecurity::validateInput($member['passport_path'], 'string') : null;

            // Passenger type & per-member sold price (new)
            $passenger_type = isset($member['passenger_type']) ? DbSecurity::validateInput($member['passenger_type'], 'string') : null;
            if (!in_array($passenger_type, ['adult', 'child', 'infant'], true)) {
                $passenger_type = null; // will be auto-detected from DOB below
            }
            $member_sold_price = isset($member['sold_price']) ? floatval($member['sold_price']) : 0;

            // Auto-detect passenger_type from DOB if not explicitly provided
            if ($passenger_type === null && !empty($dob)) {
                try {
                    $dobDate = new DateTime($dob);
                    $age = $dobDate->diff(new DateTime())->y;
                    if ($age < 2) {
                        $passenger_type = 'infant';
                    } elseif ($age <= 11) {
                        $passenger_type = 'child';
                    } else {
                        $passenger_type = 'adult';
                    }
                } catch (Exception $e) {
                    $passenger_type = 'adult'; // fallback
                }
            } elseif ($passenger_type === null) {
                $passenger_type = 'adult'; // default when no DOB
            }

            // Per-member sold price: use the member-level override if provided (> 0),
            // otherwise fall back to the shared grand total.
            if ($member_sold_price > 0) {
                $per_member_sold_price = $member_sold_price;
            } else {
                $per_member_sold_price = $total_sold_price;
            }
            $per_member_profit = $per_member_sold_price - $total_base_price;
            $per_member_due = $per_member_sold_price - (float)$paid - (float)$received_bank_payment;

            // Empty date fields must be NULL, not '' (MySQL DATE columns reject '')
            $dob = trim((string)$dob) === '' ? null : $dob;
            $passport_expiry = trim((string)$passport_expiry) === '' ? null : $passport_expiry;

            // Validate required fields
            if (empty($name)) {
                $failed_members[] = "Member " . ($member_index + 1) . ": Missing required fields";
                continue;
            }

            // Validate passport expiry (6 months minimum).
            // Invalid or past dates are treated as "not provided".
            if (!empty($passport_expiry)) {
                try {
                    $expiryDate = new DateTime($passport_expiry);
                } catch (Exception $e) {
                    $expiryDate = null;
                }
                if ($expiryDate !== null && $expiryDate >= (new DateTime())->setTime(0, 0)) {
                    $sixMonthsLater = (new DateTime())->modify('+6 months');
                    if ($expiryDate < $sixMonthsLater) {
                        $failed_members[] = "Member " . ($member_index + 1) . " (" . $name . "): Passport must be valid for at least 6 months";
                        continue;
                    }
                }
            }

            // Insert member booking
            $stmt = $pdo->prepare("
                INSERT INTO umrah_bookings (
                    family_id, package_id, sold_to, paid_to, entry_date, name,
                    dob, gender, passport_number, passport_expiry,
                    id_type, flight_date, return_date, duration, room_type,
                    price, sold_price, profit, received_bank_payment,
                    bank_receipt_number, paid, due,
                    created_by, remarks, relation, gfname, fname, discount,
                    photo_path, passport_path, tenant_id, branch_id,
                    passenger_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?, ?,
                         ?, ?, ?, ?, ?)
            ");

            $stmt->bindValue(1, $family_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $package_id, $package_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(3, $soldTo, PDO::PARAM_INT);
            $stmt->bindValue(4, $paidTo, PDO::PARAM_INT);
            $stmt->bindValue(5, $entry_date, PDO::PARAM_STR);
            $stmt->bindValue(6, $name, PDO::PARAM_STR);
            $stmt->bindValue(7, $dob, PDO::PARAM_STR);
            $stmt->bindValue(8, $gender, PDO::PARAM_STR);
            $stmt->bindValue(9, $passport_number, PDO::PARAM_STR);
            $stmt->bindValue(10, $passport_expiry, PDO::PARAM_STR);
            $stmt->bindValue(11, $id_type, PDO::PARAM_STR);
            $stmt->bindValue(12, $flight_date, PDO::PARAM_STR);
            $stmt->bindValue(13, $return_date, PDO::PARAM_STR);
            $stmt->bindValue(14, $duration, PDO::PARAM_STR);
            $stmt->bindValue(15, $room_type, PDO::PARAM_STR);
            $stmt->bindValue(16, $total_base_price, PDO::PARAM_STR);
            $stmt->bindValue(17, $per_member_sold_price, PDO::PARAM_STR);
            $stmt->bindValue(18, $per_member_profit, PDO::PARAM_STR);
            $stmt->bindValue(19, $received_bank_payment, PDO::PARAM_STR);
            $stmt->bindValue(20, $bank_receipt_number, PDO::PARAM_STR);
            $stmt->bindValue(21, $paid, PDO::PARAM_STR);
            $stmt->bindValue(22, $per_member_due, PDO::PARAM_STR);
            $stmt->bindValue(23, $user_id, PDO::PARAM_INT);
            $stmt->bindValue(24, $remarks, PDO::PARAM_STR);
            $stmt->bindValue(25, $relation, PDO::PARAM_STR);
            $stmt->bindValue(26, $g_name, PDO::PARAM_STR);
            $stmt->bindValue(27, $father_name, PDO::PARAM_STR);
            $stmt->bindValue(28, $discount, PDO::PARAM_STR);
            $stmt->bindValue(29, $photo_path, PDO::PARAM_STR);
            $stmt->bindValue(30, $passport_path, PDO::PARAM_STR);
            $stmt->bindValue(31, $tenant_id, PDO::PARAM_INT);
            $stmt->bindValue(32, $branch_id, PDO::PARAM_INT);
            $stmt->bindValue(33, $passenger_type, PDO::PARAM_STR);

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

            // Store sale currency + exchange rate (base -> sale) on the booking
            $stmt_upd_currency = $pdo->prepare("UPDATE umrah_bookings SET currency = ?, exchange_rate = ? WHERE booking_id = ? AND tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))");
            $stmt_upd_currency->execute([$sale_currency, $exchange_rate, $umrah_id, $tenant_id, $branch_id, $branch_id]);

            // Log the activity
            $new_values = json_encode([
                'name' => $name,
                'passport_number' => $passport_number,
                'dob' => $dob,
                'relation' => $relation,
                'family_id' => $family_id,
                'services_count' => count($processed_services),
                'passenger_type' => $passenger_type,
                'sold_price' => $per_member_sold_price
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
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
