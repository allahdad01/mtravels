<?php
/**
 * Approve Visa Application - Transaction Processing
 * Handles approval of visa applications with supplier and client transactions
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

    $visa_id = isset($_POST['visa_id']) ? DbSecurity::validateInput($_POST['visa_id'], 'int') : null;

    if (empty($visa_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Visa ID is required']);
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

    // ─── 1. Fetch visa application ──────────────────────────────────────────────
    $stmt_visa = $pdo->prepare("
        SELECT
            id, supplier, sold_to, paid_to, applicant_name, passport_number,
            base, sold, profit, currency, remarks, status, country, visa_type,
            applied_date, issued_date, receive_date
        FROM visa_applications
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_visa->execute([$visa_id, $tenant_id, $branch_id]);
    $visa_data = $stmt_visa->fetch(PDO::FETCH_ASSOC);

    if (!$visa_data) {
        throw new Exception("Visa application not found for ID: $visa_id");
    }

    // Guard: prevent double-approval
    if ($visa_data['status'] === 'Approved') {
        throw new Exception("Visa application is already approved.");
    }

    $supplier_id      = $visa_data['supplier'];
    $client_id        = $visa_data['sold_to'];
    $base_price       = $visa_data['base'];
    $sold_price       = $visa_data['sold'];
    $profit           = $visa_data['profit'];
    $currency         = $visa_data['currency'];
    $applicant_name   = $visa_data['applicant_name'];
    $passport_number  = $visa_data['passport_number'];
    $country          = $visa_data['country'];
    $visa_type        = $visa_data['visa_type'];
    $applied_date     = $visa_data['applied_date'];
    $issued_date      = $visa_data['issued_date'];
    $receive_date     = $visa_data['receive_date'];

    // ─── 2. Fetch supplier details ──────────────────────────────────────────────
    $stmt_supplier = $pdo->prepare("
        SELECT name, supplier_type, balance
        FROM suppliers
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_supplier->execute([$supplier_id, $tenant_id, $branch_id]);
    $supplier_data = $stmt_supplier->fetch(PDO::FETCH_ASSOC);

    if (!$supplier_data) {
        throw new Exception("Supplier not found for ID: $supplier_id");
    }

    $supplier_type        = $supplier_data['supplier_type'];
    $supplier_balance     = $supplier_data['balance'] ?? 0;
    $new_supplier_balance = $supplier_balance - $base_price;
    $supplier_remarks     = "Visa purchase for $applicant_name - $passport_number";

    // ─── 3. Fetch client details ───────────────────────────────────────────────
    $stmt_client = $pdo->prepare("
        SELECT name, email, client_type, usd_balance, afs_balance
        FROM clients
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_client->execute([$client_id, $tenant_id, $branch_id]);
    $client_data = $stmt_client->fetch(PDO::FETCH_ASSOC);

    if (!$client_data) {
        throw new Exception("Client not found for ID: $client_id");
    }

    $client_name  = $client_data['name'];
    $client_email = $client_data['email'] ?? '';
    $client_type  = $client_data['client_type'];
    $usd_balance  = $client_data['usd_balance'] ?? 0;
    $afs_balance  = $client_data['afs_balance'] ?? 0;

    // ─── 4. Email notification (non-fatal) ─────────────────────────────────────
    try {
        require_once '../../includes/functions.php';
        if (!empty($client_email) && function_exists('sendVisaNotification')) {
            sendVisaNotification(
                $client_email,
                $client_name,
                $visa_id,
                $applicant_name,
                $passport_number,
                $country,
                $visa_type,
                $applied_date,
                $issued_date,
                $sold_price,
                $currency
            );
        }
    } catch (Exception $e) {
        error_log("Email notification failed: " . $e->getMessage());
    }

    // ─── 5. WhatsApp notification (non-fatal) ──────────────────────────────────
    try {
        $whatsapp_file = '../../api/whatsapp/WhatsAppManager.php';
        if (file_exists($whatsapp_file)) {
            require_once $whatsapp_file;
            $whatsappManager = new WhatsAppManager($tenant_id);
            $whatsappManager->sendBookingNotification('visa', $visa_id);
        }
    } catch (Exception $e) {
        error_log("WhatsApp notification failed: " . $e->getMessage());
    }

    // ─── 6. Supplier transaction ────────────────────────────────────────────────
    $stmt_supplier_txn = $pdo->prepare("
        INSERT INTO supplier_transactions
            (supplier_id, transaction_type, amount, transaction_of, reference_id, remarks, transaction_date, balance, tenant_id, branch_id)
        VALUES
            (?, 'Debit', ?, 'visa_sale', ?, ?, NOW(), ?, ?, ?)
    ");
    $stmt_supplier_txn->execute([
        $supplier_id,
        $base_price,
        $visa_id,
        $supplier_remarks,
        $new_supplier_balance,
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

    // ─── 7. Client transaction ─────────────────────────────────────────────────
    $current_balance = ($currency === 'USD') ? $usd_balance : $afs_balance;
    $new_client_balance = $current_balance - $sold_price;
    $client_description = "Visa booking for $applicant_name";

    $stmt_client_txn = $pdo->prepare("
        INSERT INTO client_transactions
            (client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id)
        VALUES
            (?, 'Debit', 'visa_sale', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_client_txn->execute([
        $client_id,
        $visa_id,
        $sold_price,
        $new_client_balance,
        $currency,
        $client_description,
        $tenant_id,
        $branch_id,
    ]);

    // ─── 8. Update client balance ──────────────────────────────────────────────
    if ($client_type === 'regular') {
        if ($currency === 'USD') {
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
        $stmt_client_balance->execute([$sold_price, $client_id, $tenant_id, $branch_id]);
    }

    // ─── 9. Update visa status ────────────────────────────────────────────────
    $stmt_update = $pdo->prepare("
        UPDATE visa_applications SET status = 'Approved'
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_update->execute([$visa_id, $tenant_id, $branch_id]);

    // ─── 10. Activity log ──────────────────────────────────────────────────────
    $ip_address = $_SERVER['REMOTE_ADDR']     ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $old_values = json_encode(['status' => $visa_data['status'], 'transactions_created' => false]);
    $new_values = json_encode(['status' => 'Approved',  'transactions_created' => true]);

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES
            (?, 'approve', 'visa_applications', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->execute([
        $user_id,
        $visa_id,
        $old_values,
        $new_values,
        $ip_address,
        $user_agent,
        $tenant_id,
        $branch_id,
    ]);

    $pdo->commit();

    ob_end_clean();
    echo json_encode(["success" => true, "message" => "Visa application approved and transactions processed successfully"]);

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
?>
