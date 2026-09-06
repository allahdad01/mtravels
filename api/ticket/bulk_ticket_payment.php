<?php
// Bulk Ticket Payment - processes payments for multiple tickets at once
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

enforce_auth();
require_permission('tickets.transactions');

// CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['ticket_ids']) || !is_array($input['ticket_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request: ticket_ids required']);
    exit;
}

$ticket_ids = $input['ticket_ids'];
$amounts = $input['amounts'] ?? [];
$currency = trim($input['currency'] ?? '');
$payment_date = trim($input['date'] ?? '');
$description = trim($input['description'] ?? '');
$receipt_number = trim($input['receipt_number'] ?? '');
$exchange_rate = isset($input['exchange_rate']) && $input['exchange_rate'] !== '' && $input['exchange_rate'] !== null
    ? floatval($input['exchange_rate']) : null;

// Validate required fields
if (empty($ticket_ids)) {
    echo json_encode(['success' => false, 'message' => 'No tickets selected']);
    exit;
}

if (empty($currency) || !in_array($currency, ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid currency']);
    exit;
}

if (empty($payment_date)) {
    echo json_encode(['success' => false, 'message' => 'Payment date is required']);
    exit;
}

if (empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Description is required']);
    exit;
}

// Validate total amount - skip tickets with 0 allocation
$total_amount = 0;
$valid_ticket_ids = [];
foreach ($ticket_ids as $ticket_id) {
    $ticket_id = intval($ticket_id);
    $amount = isset($amounts[$ticket_id]) ? floatval($amounts[$ticket_id]) : 0;
    if ($amount > 0) {
        $total_amount += $amount;
        $valid_ticket_ids[] = $ticket_id;
    }
}

if (empty($valid_ticket_ids)) {
    echo json_encode(['success' => false, 'message' => 'No tickets with a valid allocation amount']);
    exit;
}

if ($total_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total payment amount must be greater than zero']);
    exit;
}

// Use only tickets with valid amounts
$ticket_ids = $valid_ticket_ids;

// Determine balance field
switch ($currency) {
    case 'USD':     $balanceField = 'usd_balance'; break;
    case 'AFS':     $balanceField = 'afs_balance'; break;
    case 'EUR':     $balanceField = 'euro_balance'; break;
    case 'DARHAM':  $balanceField = 'darham_balance'; break;
    case 'SAR':     $balanceField = 'sar_balance'; break;
    default:
        echo json_encode(['success' => false, 'message' => "Unsupported currency: $currency"]);
        exit;
}

try {
    $pdo->beginTransaction();

    $processed_tickets = [];
    $notifications = [];
    $activity_logs = [];

    foreach ($ticket_ids as $ticket_id) {
        $ticket_id = intval($ticket_id);
        $amount = isset($amounts[$ticket_id]) ? floatval($amounts[$ticket_id]) : 0;

        if ($amount <= 0) {
            throw new Exception("Invalid amount for ticket #$ticket_id");
        }

        // Validate ticket exists and belongs to this tenant/branch
        $stmt = $pdo->prepare("
            SELECT tb.id, tb.paid_to, tb.title, tb.passenger_name, tb.pnr, tb.sold_to,
                   c.name as client_name, c.client_type
            FROM ticket_bookings tb
            JOIN clients c ON tb.sold_to = c.id
            WHERE tb.id = ? AND tb.tenant_id = ? AND tb.branch_id = ?
        ");
        $stmt->execute([$ticket_id, $tenant_id, $branch_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("Ticket #$ticket_id not found or access denied");
        }

        if ($booking['client_type'] !== 'agency') {
            throw new Exception("Ticket #$ticket_id is not an agency ticket - bulk payment only for agency clients");
        }

        // Get current account balance
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$booking['paid_to'], $tenant_id, $branch_id]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult) {
            throw new Exception("Account not found for ticket #$ticket_id");
        }

        $current_balance = floatval($balanceResult['current_balance']);
        $new_balance = $current_balance + $amount;

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$new_balance, $booking['paid_to'], $tenant_id, $branch_id]);

        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, branch_id, receipt, exchange_rate, created_by)
            VALUES (?, 'credit', ?, ?, ?, 'ticket_sale', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $booking['paid_to'],
            $amount,
            $currency,
            $description . " [Bulk Payment]",
            $ticket_id,
            $new_balance,
            $payment_date,
            $tenant_id,
            $branch_id,
            $receipt_number,
            $exchange_rate,
            $user_id
        ]);

        $transaction_id = $pdo->lastInsertId();

        // Create notification
        $notificationMessage = sprintf(
            "Bulk ticket payment received for PNR %s - %s %s: %s %.2f%s",
            $booking['pnr'],
            $booking['title'],
            $booking['passenger_name'],
            $currency,
            $amount,
            $receipt_number !== '' ? " | Receipt: {$receipt_number}" : ''
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications
            (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
            VALUES (?, 'ticket_sale', ?, 'Unread', NOW(), ?, ?)
        ");
        $notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id, $branch_id]);

        // Prepare activity log
        $new_values = json_encode([
            'ticket_id' => $ticket_id,
            'pnr' => $booking['pnr'],
            'payment_date' => $payment_date,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'receipt_number' => $receipt_number,
            'exchange_rate' => $exchange_rate,
            'main_account_id' => $booking['paid_to']
        ]);

        $processed_tickets[] = [
            'ticket_id' => $ticket_id,
            'pnr' => $booking['pnr'],
            'amount' => $amount,
            'transaction_id' => $transaction_id
        ];
    }

    // Commit all transactions
    $pdo->commit();

    // Log activity for each processed ticket
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    foreach ($processed_tickets as $pt) {
        $new_values = json_encode([
            'ticket_id' => $pt['ticket_id'],
            'pnr' => $pt['pnr'],
            'amount' => $pt['amount'],
            'currency' => $currency,
            'bulk_payment' => true
        ]);

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $pt['transaction_id'], '{}', $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);
    }

    echo json_encode([
        'success' => true,
        'message' => sprintf('Bulk payment of %s %.2f processed for %d ticket(s)', $currency, $total_amount, count($processed_tickets)),
        'processed_count' => count($processed_tickets),
        'total_amount' => $total_amount,
        'currency' => $currency,
        'tickets' => $processed_tickets
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
