<?php
/**
 * Approve Umrah Booking - Transaction Processing
 * Handles approval of member bookings with supplier and client transactions
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

try {
    require_once '../../admin/includes/db_security.php';
    require_once '../../admin/security.php';
    enforce_auth();

    if (!verify_csrf_token()) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
        exit;
    }

    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    $user_id   = $_SESSION['user_id'] ?? 0;

    require_once '../../includes/db.php';

    $booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int') : null;

    if (empty($booking_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
        exit;
    }
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Initialization error: " . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ─── 1. Fetch booking ───────────────────────────────────────────────────────
    $stmt_booking = $pdo->prepare("
        SELECT
            booking_id, family_id, sold_to, paid_to, name, flight_date, return_date, room_type,
            price, sold_price, profit, received_bank_payment, paid, due, discount,
            remarks, relation, gfname, fname, status, currency
        FROM umrah_bookings
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_booking->execute([$booking_id, $tenant_id, $branch_id]);
    $booking_data = $stmt_booking->fetch(PDO::FETCH_ASSOC);

    if (!$booking_data) {
        throw new Exception("Booking not found for ID: $booking_id");
    }

    // Guard: prevent double-approval
    if ($booking_data['status'] === 'active') {
        throw new Exception("Booking is already approved.");
    }

    $umrah_id         = $booking_data['booking_id'];
    $family_id        = $booking_data['family_id'];
    $soldTo           = $booking_data['sold_to'];
    $total_base_price = $booking_data['price'];
    $total_sold_price = $booking_data['sold_price'];
    $total_profit     = $booking_data['profit'];
    $amount_paid      = $booking_data['paid'] + $booking_data['received_bank_payment'];
    $member_name      = $booking_data['name'];
    $booking_currency = $booking_data['currency'];

    // ─── 2. Fetch client (single query, merged) ─────────────────────────────────
    $stmt_client = $pdo->prepare("
        SELECT name, email, usd_balance, afs_balance, client_type
        FROM clients
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_client->execute([$soldTo, $tenant_id, $branch_id]);
    $client_data = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client_data) {
        throw new Exception("Client not found for ID: $soldTo");
    }

    $client_email = $client_data['email']       ?? '';
    $client_name  = $client_data['name']        ?? '';
    $usd_balance  = $client_data['usd_balance'] ?? 0;
    $afs_balance  = $client_data['afs_balance'] ?? 0;
    $client_type  = $client_data['client_type'] ?? '';

    // ─── 3. Fetch services ──────────────────────────────────────────────────────
    $stmt_services = $pdo->prepare("
        SELECT supplier_id, base_price, sold_price, profit, currency, service_type
        FROM umrah_booking_services
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_services->execute([$umrah_id, $tenant_id, $branch_id]);
    $processed_services = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

    // ─── 4. Email notification (non-fatal) ──────────────────────────────────────
    try {
        require_once '../../includes/functions.php';
        if (!empty($client_email) && function_exists('sendUmrahNotification')) {
            sendUmrahNotification(
                $client_email,
                $client_name,
                $umrah_id,
                $member_name,
                $booking_data['flight_date'],
                $booking_data['return_date'],
                $booking_data['room_type'],
                $total_sold_price,
                $amount_paid,
                $booking_data['due'],
                $booking_currency
            );
        }
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
    }

    // ─── 5. WhatsApp notification (non-fatal) ────────────────────────────────────
    try {
        $whatsapp_file = '../../api/whatsapp/WhatsAppManager.php';
        if (file_exists($whatsapp_file)) {
            require_once $whatsapp_file;
            $whatsappManager = new WhatsAppManager($tenant_id);
            $whatsappManager->sendBookingNotification('umrah', $umrah_id);
        }
    } catch (Exception $e) {
        error_log("WhatsApp notification failed: " . $e->getMessage());
    }

    // ─── 6. Client transaction (debit) ──────────────────────────────────────────
    $booking_currency_upper = strtoupper($booking_currency);
    if ($booking_currency_upper === 'USD') {
        $current_balance = $usd_balance;
    } else {
        $current_balance = $afs_balance;
    }
    $new_balance_client = $current_balance - $total_sold_price;
    $description = "Client was debited $total_sold_price $booking_currency for umrah booking for $member_name";

    $stmt_client_txn = $pdo->prepare("
        INSERT INTO client_transactions
            (client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id)
        VALUES
            (?, 'Debit', 'umrah', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_client_txn->execute([
        $soldTo,
        $umrah_id,
        $total_sold_price,
        $new_balance_client,
        $booking_currency,
        $description,
        $tenant_id,
        $branch_id,
    ]);

    // ─── 7. Supplier transactions ───────────────────────────────────────────────
    foreach ($processed_services as $service) {
        $supplier_id  = $service['supplier_id'];
        $base_price   = $service['base_price'];
        $currency     = $service['currency'];
        $service_type = $service['service_type'];

        // Fetch supplier with null-guard
        $stmt_supplier = $pdo->prepare("
            SELECT name, supplier_type, balance
            FROM suppliers
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt_supplier->execute([$supplier_id, $tenant_id, $branch_id]);
        $supplier_data = $stmt_supplier->fetch(PDO::FETCH_ASSOC);

        if (!$supplier_data) {
            throw new Exception("Supplier not found for ID: $supplier_id (service: $service_type)");
        }

        $supplier_type        = $supplier_data['supplier_type'];
        $supplier_balance     = $supplier_data['balance'] ?? 0;
        $new_balance_supplier = $supplier_balance - $base_price;
        $remarks              = "Base amount of $base_price $currency deducted for umrah $service_type.";

        $stmt_supplier_txn = $pdo->prepare("
            INSERT INTO supplier_transactions
                (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id)
            VALUES
                (?, ?, 'Debit', ?, ?, ?, NOW(), 'umrah', ?, ?)
        ");
        $stmt_supplier_txn->execute([
            $supplier_id,
            $umrah_id,
            $base_price,
            $new_balance_supplier,
            $remarks,
            $tenant_id,
            $branch_id,
        ]);

        // Only deduct balance for external suppliers
        if ($supplier_type === 'External') {
            $stmt_supplier_balance = $pdo->prepare("
                UPDATE suppliers SET balance = balance - ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_supplier_balance->execute([$base_price, $supplier_id, $tenant_id, $branch_id]);
        }
    }

    // ─── 8. Update client balance ────────────────────────────────────────────────
    if ($client_type === 'regular') {
        $booking_currency_upper = strtoupper($booking_currency);
        if ($booking_currency_upper === 'USD') {
            $stmt_client_balance = $pdo->prepare("
                UPDATE clients SET usd_balance = usd_balance - ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        } else {
            $stmt_client_balance = $pdo->prepare("
                UPDATE clients SET afs_balance = afs_balance - ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        }
        $stmt_client_balance->execute([$total_sold_price, $soldTo, $tenant_id, $branch_id]);
    }

    // ─── 9. Update booking status ────────────────────────────────────────────────
    $stmt_update = $pdo->prepare("
        UPDATE umrah_bookings SET status = 'active'
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_update->execute([$umrah_id, $tenant_id, $branch_id]);

    // ─── 10. Update family totals after approval ─────────────────────────────────
    // Family calculations happen ONLY at approval time
    $stmt_family = $pdo->prepare("
        UPDATE families f
        SET
            f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
            f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
            f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
            f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
            f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
        WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
    ");
    $stmt_family->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $family_id, $tenant_id, $branch_id]);

    // ─── 11. Activity log ────────────────────────────────────────────────────────
    $ip_address = $_SERVER['REMOTE_ADDR']     ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $old_values = json_encode(['status' => 'pending', 'transactions_created' => false]);
    $new_values = json_encode(['status' => 'active',  'transactions_created' => true]);

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES
            (?, 'approve', 'umrah_bookings', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->execute([
        $user_id,
        $umrah_id,
        $old_values,
        $new_values,
        $ip_address,
        $user_agent,
        $tenant_id,
        $branch_id,
    ]);

    $pdo->commit();

    ob_end_clean();
    echo json_encode(["success" => true, "message" => "Booking approved and transactions processed"]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}