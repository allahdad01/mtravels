<?php
session_start();
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// DB Connection
require_once '../../includes/db.php';

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate receipt_number
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;

// Validate notification_id
$notification_id = isset($_POST['notification_id']) ? DbSecurity::validateInput($_POST['notification_id'], 'int', ['min' => 0]) : null;

// Check Connection
if (!$pdo) {
    die("Connection failed");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $receipt_number = $_POST['receipt_number'];
    $remarks = $_POST['remarks'];

    // Start a transaction
    $pdo->beginTransaction();

    try {
        // 1. Fetch transaction details from notifications table
        $stmt_fetch_notification = $pdo->prepare("SELECT transaction_id, transaction_type FROM notifications WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_fetch_notification->execute([$notification_id, $tenant_id, $branch_id]);
        $notification_data = $stmt_fetch_notification->fetch(PDO::FETCH_ASSOC);
        if (!$notification_data) {
            throw new Exception('Notification not found or missing transaction details.');
        }
        $transaction_id = $notification_data['transaction_id'];
        $transaction_type = $notification_data['transaction_type'];

        if ($transaction_type === 'visa') {
            // Fetch visa transaction details
            $stmt_fetch_visa = $pdo->prepare("
                SELECT mt.id, mt.reference_id, mt.amount, mt.currency, mt.description, mt.type,
                       va.applicant_name, va.supplier, va.paid_to
                FROM main_account_transactions mt
                JOIN visa_applications va ON mt.reference_id = va.id
                WHERE mt.id = ? AND mt.tenant_id = ? AND mt.branch_id = ?
            ");

            $stmt_fetch_visa->execute([$transaction_id, $tenant_id, $branch_id]);
            $visa_data = $stmt_fetch_visa->fetch(PDO::FETCH_ASSOC);
            if (!$visa_data) {
                throw new Exception('Visa transaction details not found. Transaction ID: ' . $transaction_id);
            }

            $visa_id = $visa_data['reference_id'];
            $amount = $visa_data['amount'];
            $currency = $visa_data['currency'];
            $transaction_type = $visa_data['type'];
            $applicant_name = $visa_data['applicant_name'];
            $supplier_id = $visa_data['supplier'];
            $paid_to = $visa_data['paid_to'];

            // Update receipt and remarks in main account transactions table
            $stmt_update_visa = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_visa->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        }
        elseif ($transaction_type === 'umrah') {
            // Debug: Log the transaction ID
            error_log("Processing Umrah transaction ID: " . $transaction_id);

            // Fetch Umrah transaction details with error checking
            $stmt_fetch_umrah = $pdo->prepare("
                SELECT id, payment_amount, umrah_booking_id, transaction_to
                FROM umrah_transactions
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");

            $stmt_fetch_umrah->execute([$transaction_id, $tenant_id, $branch_id]);
            $umrah_data = $stmt_fetch_umrah->fetch(PDO::FETCH_ASSOC);
            if (!$umrah_data) {
                // Debug: Query the table directly to see what's there
                $debug_query = $pdo->query("SELECT id FROM umrah_transactions WHERE id = " . intval($transaction_id) . " AND tenant_id = " . intval($tenant_id) . " AND branch_id = " . intval($branch_id));
                $debug_count = $debug_query ? $debug_query->rowCount() : 0;
                throw new Exception('Umrah transaction details not found. Transaction ID: ' . $transaction_id .
                                  '. Records found: ' . $debug_count);
            }

            $transaction_id = $umrah_data['id'];
            $amount = $umrah_data['payment_amount'];
            $umrah_booking_id = $umrah_data['umrah_booking_id'];
            $transaction_to = $umrah_data['transaction_to'];

            // Fetch supplier_id from umrah_booking_services
            $stmt_fetch_umrah_app = $pdo->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
            $stmt_fetch_umrah_app->execute([$umrah_booking_id, $tenant_id, $branch_id]);
            $supplier_data = $stmt_fetch_umrah_app->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_data) {
                throw new Exception('Umrah booking services details not found.');
            }
            $supplier_id = $supplier_data['supplier_id'];

            // Fetch Supplier Type
            $stmt_fetch_supplier = $pdo->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_fetch_supplier->execute([$supplier_id, $tenant_id, $branch_id]);
            $supplier_info = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_info) {
                throw new Exception('Supplier details not found.');
            }
            $supplier_type = $supplier_info['supplier_type'];
            $currency = $supplier_info['currency'];

            // Update `receipt` and `remarks` in umrah_transactions table
            $stmt_update_umrah = $pdo->prepare("UPDATE umrah_transactions SET receipt = ?, payment_description = CONCAT(payment_description, ' | Additional Remarks: ', ?) WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_umrah->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

            // Normalize $transaction_to to lowercase for case-insensitive comparison
            $transaction_to = strtolower(trim($transaction_to));

            if ($transaction_to === 'bank') {
                if ($supplier_type === 'External') {

                }
            }
            elseif ($transaction_to === 'internal account') {

                // ✅ Update `received_bank_payment` in `umrah_bookings`

                $stmt_update_umrah_booking = $pdo->prepare("UPDATE main_account_transactions SET receipt = ? WHERE reference_id = ? and transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?");
                $stmt_update_umrah_booking->execute([$receipt_number, $transaction_id, $tenant_id, $branch_id]);

            } else {
                throw new Exception("Invalid transaction type: " . htmlspecialchars($transaction_to));
            }
        }

        elseif ($transaction_type === 'ticket_sale') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $pdo->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN ticket_bookings tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'ticket_sale' AND mt.tenant_id = ? AND mt.branch_id = ? AND tb.tenant_id = ? AND tb.branch_id = ?");
            $stmt_fetch_supplier->execute([$transaction_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
            $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_data) {
                throw new Exception('Main account transaction details not found.');
            }
            $main_account_id = $supplier_data['main_account_id'];
            $ticket_id = $supplier_data['ticket_id'];
            $amount = $supplier_data['amount'];
            $currency = $supplier_data['currency'];
            $paid_to = $supplier_data['paid_to'];
            $passenger_name = $supplier_data['passenger_name'];

            $stmt_update_ticket = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_sale' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_ticket->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'ticket_reserve') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $pdo->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN ticket_reservations tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'ticket_reserve' AND mt.tenant_id = ? AND mt.branch_id = ? AND tb.tenant_id = ? AND tb.branch_id = ?");
            $stmt_fetch_supplier->execute([$transaction_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
            $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_data) {
                throw new Exception('Main account transaction details not found.');
            }
            $main_account_id = $supplier_data['main_account_id'];
            $ticket_id = $supplier_data['ticket_id'];
            $amount = $supplier_data['amount'];
            $currency = $supplier_data['currency'];
            $paid_to = $supplier_data['paid_to'];
            $passenger_name = $supplier_data['passenger_name'];

            $stmt_update_ticket = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_reserve' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_ticket->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'ticket_refund') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $pdo->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN refunded_tickets tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'ticket_refund' AND mt.tenant_id = ? AND mt.branch_id = ? AND tb.tenant_id = ? AND tb.branch_id = ?");
            $stmt_fetch_supplier->execute([$transaction_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
            $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_data) {
                throw new Exception('Main account transaction details not found.');
            }
            $main_account_id = $supplier_data['main_account_id'];
            $ticket_id = $supplier_data['ticket_id'];
            $amount = $supplier_data['amount'];
            $currency = $supplier_data['currency'];
            $paid_to = $supplier_data['paid_to'];
            $passenger_name = $supplier_data['passenger_name'];

            $stmt_update_ticket = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_refund' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_ticket->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'ticket_date_change') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $pdo->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN date_change_tickets tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'date_change' AND mt.tenant_id = ? AND mt.branch_id = ? AND tb.tenant_id = ? AND tb.branch_id = ?");
            $stmt_fetch_supplier->execute([$transaction_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
            $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$supplier_data) {
                throw new Exception('Main account transaction details not found.');
            }
            $main_account_id = $supplier_data['main_account_id'];
            $ticket_id = $supplier_data['ticket_id'];
            $amount = $supplier_data['amount'];
            $currency = $supplier_data['currency'];
            $paid_to = $supplier_data['paid_to'];
            $passenger_name = $supplier_data['passenger_name'];

            $stmt_update_ticket = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'date_change' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_ticket->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'hotel') {
            // Handle Hotel Transactions
            // First get the hotel booking ID from main account transaction
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'hotel' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Hotel transaction details not found.');
            }
            $booking_id = $transaction_data['reference_id'];

            // Now get the actual hotel booking details
            $stmt_fetch_supplier = $pdo->prepare("
                SELECT hb.id
                FROM hotel_bookings hb
                WHERE hb.id = ? AND hb.tenant_id = ? AND hb.branch_id = ?");
            $stmt_fetch_supplier->execute([$booking_id, $tenant_id, $branch_id]);
            $booking_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);
            if (!$booking_data) {
                throw new Exception('Hotel booking details not found.');
            }
            $booking_id_verify = $booking_data['id'];

            // Update main account transaction receipt and description
            $stmt_update_ticket = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? AND transaction_of = 'hotel' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_ticket->execute([$receipt_number, $remarks, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'additional_payment') {
            // Handle Additional Payment Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'additional_payment' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Additional payment transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $reference_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

            // Check if there's a corresponding supplier transaction
            $stmt_check_supplier = $pdo->prepare("
                SELECT id FROM supplier_transactions
                WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? AND branch_id = ?");
            $stmt_check_supplier->execute([$reference_id, $tenant_id, $branch_id]);
            $supplier_result = $stmt_check_supplier->fetch(PDO::FETCH_ASSOC);

            if ($supplier_result) {
                // If supplier transaction exists, update it
                $supplier_transaction_id = $supplier_result['id'];

                $stmt_update_supplier = $pdo->prepare("
                    UPDATE supplier_transactions
                    SET receipt = ?,
                        remarks = ?
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt_update_supplier->execute([$receipt_number, $combined_description, $supplier_transaction_id, $tenant_id, $branch_id]);
            }

        } elseif ($transaction_type === 'debtor') {
            // Handle Debtor Payment Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'debtor' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Debtor payment transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $debtor_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'debtor' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

            // Update debtor transaction
            $stmt_update_debtor = $pdo->prepare("UPDATE debtor_transactions SET reference_number = ?, description = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_debtor->execute([$receipt_number, $combined_description, $debtor_transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'creditor') {
            // Handle Creditor Payment Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'creditor' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Creditor payment transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $creditor_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'creditor' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

            // Update creditor transaction
            $stmt_update_creditor = $pdo->prepare("UPDATE creditor_transactions SET reference_number = ?, description = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_update_creditor->execute([$receipt_number, $combined_description, $creditor_transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'weight') {
            // Handle Weight Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'weight' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Weight transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $weight_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'weight' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'umrah_refund') {
            // Handle Umrah Refund Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'umrah_refund' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Umrah refund transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $umrah_refund_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'umrah_refund' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'visa_refund') {
            // Handle Visa Refund Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'visa_refund' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Visa refund transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $umrah_refund_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'visa_refund' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

        } elseif ($transaction_type === 'hotel_refund') {
            // Handle Hotel Refund Transactions
            $stmt_fetch_transaction = $pdo->prepare("
                SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
                FROM main_account_transactions mt
                WHERE mt.id = ? AND mt.transaction_of = 'hotel_refund' AND mt.tenant_id = ? AND mt.branch_id = ?");
            $stmt_fetch_transaction->execute([$transaction_id, $tenant_id, $branch_id]);
            $transaction_data = $stmt_fetch_transaction->fetch(PDO::FETCH_ASSOC);
            if (!$transaction_data) {
                throw new Exception('Hotel refund transaction details not found.');
            }
            $trans_id = $transaction_data['id'];
            $existing_description = $transaction_data['description'];
            $amount = $transaction_data['amount'];
            $currency = $transaction_data['currency'];
            $main_account_id = $transaction_data['main_account_id'];
            $umrah_refund_transaction_id = $transaction_data['reference_id'];

            // Combine existing description with new remarks
            $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

            // Update main account transaction
            $stmt_update_transaction = $pdo->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'hotel_refund' AND tenant_id = ? AND branch_id = ?");
            $stmt_update_transaction->execute([$receipt_number, $combined_description, $transaction_id, $tenant_id, $branch_id]);

        } else {
            throw new Exception('Unsupported transaction type.');
        }

        // 2. Update notification status to 'Read'
        $stmt_update_notification = $pdo->prepare("UPDATE notifications SET status = 'Read' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_notification->execute([$notification_id, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        echo json_encode(["status" => "success", "message" => "Notification approved, transaction marked as 'Paid', and balance updated."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
