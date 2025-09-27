<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
// DB Connection
require_once '../includes/conn.php';

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate receipt_number
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;

// Validate notification_id
$notification_id = isset($_POST['notification_id']) ? DbSecurity::validateInput($_POST['notification_id'], 'int', ['min' => 0]) : null;


// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $receipt_number = $_POST['receipt_number'];
    $remarks = $_POST['remarks'];

    // Start a transaction
    $conn->begin_transaction();

    try {
        // 1. Fetch transaction details from notifications table
        $stmt_fetch_notification = $conn->prepare("SELECT transaction_id, transaction_type FROM notifications WHERE id = ? AND tenant_id = ?");
        $stmt_fetch_notification->bind_param("ii", $notification_id, $tenant_id);
        $stmt_fetch_notification->execute();
        $stmt_fetch_notification->bind_result($transaction_id, $transaction_type);
        if (!$stmt_fetch_notification->fetch()) {
            throw new Exception('Notification not found or missing transaction details.');
        }
        $stmt_fetch_notification->close();

        if ($transaction_type === 'visa') {
            // Fetch visa transaction details
            $stmt_fetch_visa = $conn->prepare("
                SELECT mt.id, mt.reference_id, mt.amount, mt.currency, mt.description, mt.type,
                       va.applicant_name, va.supplier, va.paid_to
                FROM main_account_transactions mt
                JOIN visa_applications va ON mt.reference_id = va.id
                WHERE mt.id = ? AND mt.tenant_id = ?
            ");
            
            if (!$stmt_fetch_visa) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt_fetch_visa->bind_param("ii", $transaction_id, $tenant_id);
            
            if (!$stmt_fetch_visa->execute()) {
                throw new Exception('Execute failed: ' . $stmt_fetch_visa->error);
            }
            
            $result = $stmt_fetch_visa->get_result();
            if ($result->num_rows === 0) {
                throw new Exception('Visa transaction details not found. Transaction ID: ' . $transaction_id);
            }
            
            $row = $result->fetch_assoc();
            $visa_id = $row['reference_id'];
            $amount = $row['amount'];
            $currency = $row['currency'];
            $transaction_type = $row['type'];
            $applicant_name = $row['applicant_name'];
            $supplier_id = $row['supplier'];
            $paid_to = $row['paid_to'];
            
            $stmt_fetch_visa->close();

            // Update receipt and remarks in main account transactions table
            $stmt_update_visa = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? AND tenant_id = ?");
            $stmt_update_visa->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
            
            if (!$stmt_update_visa->execute()) {
                throw new Exception('Failed to update visa transaction: ' . $stmt_update_visa->error);
            }
            
            $stmt_update_visa->close();
        }
        elseif ($transaction_type === 'umrah') {
            // Debug: Log the transaction ID
            error_log("Processing Umrah transaction ID: " . $transaction_id);

            // Fetch Umrah transaction details with error checking
            $stmt_fetch_umrah = $conn->prepare("
                SELECT id, payment_amount, currency, transaction_type, umrah_booking_id, transaction_to 
                FROM umrah_transactions 
                WHERE id = ? AND tenant_id = ?
            ");
            
            if (!$stmt_fetch_umrah) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt_fetch_umrah->bind_param("ii", $transaction_id, $tenant_id);
            
            if (!$stmt_fetch_umrah->execute()) {
                throw new Exception('Execute failed: ' . $stmt_fetch_umrah->error);
            }
            
            $result = $stmt_fetch_umrah->get_result();
            if ($result->num_rows === 0) {
                // Debug: Query the table directly to see what's there
                $debug_query = $conn->query("SELECT id FROM umrah_transactions WHERE id = " . intval($transaction_id) . " AND tenant_id = " . intval($tenant_id));
                $debug_count = $debug_query ? $debug_query->num_rows : 0;
                throw new Exception('Umrah transaction details not found. Transaction ID: ' . $transaction_id . 
                                  '. Records found: ' . $debug_count);
            }
            
            $row = $result->fetch_assoc();
            $transaction_id = $row['id'];
            $amount = $row['payment_amount'];
            $umrahCurrency = $row['currency'];
            $umrah_transaction_type = $row['transaction_type'];
            $umrah_booking_id = $row['umrah_booking_id'];
            $transaction_to = $row['transaction_to'];
            
            $stmt_fetch_umrah->close();

            // Fetch Umrah booking details
            $stmt_fetch_umrah_app = $conn->prepare("SELECT paid_to, supplier, received_bank_payment FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
            $stmt_fetch_umrah_app->bind_param("ii", $umrah_booking_id, $tenant_id);
            $stmt_fetch_umrah_app->execute();
            $stmt_fetch_umrah_app->bind_result($paid_to, $supplier_id, $received_bank_payment);
            if (!$stmt_fetch_umrah_app->fetch()) {
                throw new Exception('Umrah booking details not found.');
            }
            $stmt_fetch_umrah_app->close();

            // Fetch Supplier Type
            $stmt_fetch_supplier = $conn->prepare("SELECT supplier_type, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
            $stmt_fetch_supplier->bind_param("ii", $supplier_id, $tenant_id);
            $stmt_fetch_supplier->execute();
            $stmt_fetch_supplier->bind_result($supplier_type, $currency);
            if (!$stmt_fetch_supplier->fetch()) {
                throw new Exception('Supplier details not found.');
            }
            $stmt_fetch_supplier->close();

            // Update `receipt` and `remarks` in umrah_transactions table
            $stmt_update_umrah = $conn->prepare("UPDATE umrah_transactions SET receipt = ?, payment_description = CONCAT(payment_description, ' | Additional Remarks: ', ?) WHERE id = ? AND tenant_id = ?");
            $stmt_update_umrah->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
            if (!$stmt_update_umrah->execute()) {
                throw new Exception('Failed to update umrah transactions: ' . $stmt_update_umrah->error);
            }
            $stmt_update_umrah->close();

            // Normalize $transaction_to to lowercase for case-insensitive comparison
            $transaction_to = strtolower(trim($transaction_to));

            if ($transaction_to === 'bank') {
                if ($supplier_type === 'External') {
                   
                }
             }  
             elseif ($transaction_to === 'internal account') {
               

               
                // ✅ Update `received_bank_payment` in `umrah_bookings`
                
                $stmt_update_umrah_booking = $conn->prepare("UPDATE main_account_transactions SET receipt = ? WHERE reference_id = ? and transaction_of = 'umrah' AND tenant_id = ?");
                $stmt_update_umrah_booking->bind_param("sii", $receipt_number, $transaction_id, $tenant_id);
                if (!$stmt_update_umrah_booking->execute()) {
                    throw new Exception('Failed to update received bank payment in umrah_bookings: ' . $stmt_update_umrah_booking->error);
                }
                $stmt_update_umrah_booking->close();
            } else {
                throw new Exception("Invalid transaction type: " . htmlspecialchars($transaction_to));
            }
        }

        elseif ($transaction_type === 'ticket_sale') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $conn->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN ticket_bookings tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'ticket_sale' AND mt.tenant_id = ? AND tb.tenant_id = ?");
            $stmt_fetch_supplier->bind_param("iii", $transaction_id, $tenant_id, $tenant_id);
            $stmt_fetch_supplier->execute();
            $stmt_fetch_supplier->bind_result($main_account_id, $ticket_id, $amount, $currency, $paid_to, $passenger_name);
            if (!$stmt_fetch_supplier->fetch()) {
                throw new Exception('Main account transaction details not found.');
            }
            $stmt_fetch_supplier->close();


            $stmt_update_ticket = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_sale' AND tenant_id = ?");
            $stmt_update_ticket->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
            if (!$stmt_update_ticket->execute()) {
                throw new Exception('Failed to update ticket main account transaction: ' . $stmt_update_ticket->error);
            }
            $stmt_update_ticket->close();
            
            
        } elseif ($transaction_type === 'ticket_reserve') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $conn->prepare("
                SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
                FROM main_account_transactions mt
                JOIN main_account m ON mt.main_account_id = m.id
                JOIN 	ticket_reservations tb ON mt.reference_id = tb.id
                WHERE mt.id = ? and mt.transaction_of = 'ticket_reserve' AND mt.tenant_id = ? AND tb.tenant_id = ?");
            $stmt_fetch_supplier->bind_param("iii", $transaction_id, $tenant_id, $tenant_id);
            $stmt_fetch_supplier->execute();
            $stmt_fetch_supplier->bind_result($main_account_id, $ticket_id, $amount, $currency, $paid_to, $passenger_name);
            if (!$stmt_fetch_supplier->fetch()) {
                throw new Exception('Main account transaction details not found.');
            }
            $stmt_fetch_supplier->close();


            $stmt_update_ticket = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_reserve' AND tenant_id = ?");
            $stmt_update_ticket->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
            if (!$stmt_update_ticket->execute()) {
                throw new Exception('Failed to update ticket main account transaction: ' . $stmt_update_ticket->error);
            }
            $stmt_update_ticket->close();
            
            
        } elseif ($transaction_type === 'ticket_refund') {
            // Handle Supplier Transactions
            $stmt_fetch_supplier = $conn->prepare("
            SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
            FROM main_account_transactions mt
            JOIN main_account m ON mt.main_account_id = m.id
            JOIN refunded_tickets tb ON mt.reference_id = tb.id
            WHERE mt.id = ? and mt.transaction_of = 'ticket_refund' AND mt.tenant_id = ? AND tb.tenant_id = ?");
        $stmt_fetch_supplier->bind_param("iii", $transaction_id, $tenant_id, $tenant_id);
        $stmt_fetch_supplier->execute();
        $stmt_fetch_supplier->bind_result($main_account_id, $ticket_id, $amount, $currency, $paid_to, $passenger_name);
        if (!$stmt_fetch_supplier->fetch()) {
            throw new Exception('Main account transaction details not found.');
        }
        $stmt_fetch_supplier->close();


        $stmt_update_ticket = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'ticket_refund' AND tenant_id = ?");
        $stmt_update_ticket->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
        if (!$stmt_update_ticket->execute()) {
            throw new Exception('Failed to update ticket main account transaction: ' . $stmt_update_ticket->error);
        }
        $stmt_update_ticket->close();



        } elseif ($transaction_type === 'ticket_date_change') {
    // Handle Supplier Transactions
    $stmt_fetch_supplier = $conn->prepare("
    SELECT m.id AS main_account_id, tb.id AS ticket_id, mt.amount, mt.currency, tb.paid_to, tb.passenger_name
    FROM main_account_transactions mt
    JOIN main_account m ON mt.main_account_id = m.id
    JOIN date_change_tickets tb ON mt.reference_id = tb.id
    WHERE mt.id = ? and mt.transaction_of = 'date_change' AND mt.tenant_id = ? AND tb.tenant_id = ?");
 $stmt_fetch_supplier->bind_param("iii", $transaction_id, $tenant_id, $tenant_id);
$stmt_fetch_supplier->execute();
$stmt_fetch_supplier->bind_result($main_account_id, $ticket_id, $amount, $currency, $paid_to, $passenger_name);
if (!$stmt_fetch_supplier->fetch()) {
    throw new Exception('Main account transaction details not found.');
}
$stmt_fetch_supplier->close();


$stmt_update_ticket = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? and transaction_of = 'date_change' AND tenant_id = ?");
$stmt_update_ticket->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
if (!$stmt_update_ticket->execute()) {
    throw new Exception('Failed to update ticket main account transaction: ' . $stmt_update_ticket->error);
}
$stmt_update_ticket->close();

}
 elseif ($transaction_type === 'hotel') {
     // Handle Hotel Transactions
     // First get the hotel booking ID from main account transaction
     $stmt_fetch_transaction = $conn->prepare("
         SELECT mt.reference_id
         FROM main_account_transactions mt
         WHERE mt.id = ? AND mt.transaction_of = 'hotel' AND mt.tenant_id = ?");
     $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
     $stmt_fetch_transaction->execute();
     $stmt_fetch_transaction->bind_result($booking_id);
     if (!$stmt_fetch_transaction->fetch()) {
         throw new Exception('Hotel transaction details not found.');
     }
     $stmt_fetch_transaction->close();
 
     // Now get the actual hotel booking details
     $stmt_fetch_supplier = $conn->prepare("
         SELECT hb.id
         FROM hotel_bookings hb
         WHERE hb.id = ? AND hb.tenant_id = ?");
     $stmt_fetch_supplier->bind_param("ii", $booking_id, $tenant_id);
     $stmt_fetch_supplier->execute();
     $stmt_fetch_supplier->bind_result($booking_id_verify);
     if (!$stmt_fetch_supplier->fetch()) {
         throw new Exception('Hotel booking details not found.');
     }
     $stmt_fetch_supplier->close();

    // Update main account transaction receipt and description
    $stmt_update_ticket = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = CONCAT(description, ' | Additional Remarks: ', ?) WHERE id = ? AND transaction_of = 'hotel' AND tenant_id = ?");
    $stmt_update_ticket->bind_param("ssii", $receipt_number, $remarks, $transaction_id, $tenant_id);
    if (!$stmt_update_ticket->execute()) {
        throw new Exception('Failed to update hotel bookings: ' . $stmt_update_ticket->error);
    }
    $stmt_update_ticket->close();
}
elseif ($transaction_type === 'additional_payment') {
    // Handle Additional Payment Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'additional_payment' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $reference_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Additional payment transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'additional_payment' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update additional payment transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();

    // Check if there's a corresponding supplier transaction
    $stmt_check_supplier = $conn->prepare("
        SELECT id FROM supplier_transactions 
        WHERE reference_id = ? AND transaction_of = 'additional_payment'");
    $stmt_check_supplier->bind_param("i", $reference_id);
    $stmt_check_supplier->execute();
    $supplier_result = $stmt_check_supplier->get_result();
    
    if ($supplier_result->num_rows > 0) {
        // If supplier transaction exists, update it
        $supplier_transaction = $supplier_result->fetch_assoc();
        $supplier_transaction_id = $supplier_transaction['id'];
        
        $stmt_update_supplier = $conn->prepare("
            UPDATE supplier_transactions 
            SET receipt = ?, 
                remarks = ? 
            WHERE id = ?");
        $stmt_update_supplier->bind_param("ssi", $receipt_number, $combined_description, $supplier_transaction_id);
        
        if (!$stmt_update_supplier->execute()) {
            throw new Exception('Failed to update supplier transaction: ' . $stmt_update_supplier->error);
        }
        $stmt_update_supplier->close();
    }
    $stmt_check_supplier->close();
}
elseif ($transaction_type === 'debtor') {
    // Handle Debtor Payment Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'debtor' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $debtor_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Debtor payment transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'debtor' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update debtor payment transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();

    // Update debtor transaction
    $stmt_update_debtor = $conn->prepare("UPDATE debtor_transactions SET reference_number = ?, description = ? WHERE id = ?");
    $stmt_update_debtor->bind_param("ssi", $receipt_number, $combined_description, $debtor_transaction_id);
    if (!$stmt_update_debtor->execute()) {
        throw new Exception('Failed to update debtor transaction: ' . $stmt_update_debtor->error);
    }
    $stmt_update_debtor->close();
}
elseif ($transaction_type === 'creditor') {
    // Handle Creditor Payment Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'creditor' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $creditor_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Creditor payment transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'creditor' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update creditor payment transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();

    // Update creditor transaction
    $stmt_update_creditor = $conn->prepare("UPDATE creditor_transactions SET reference_number = ?, description = ? WHERE id = ?");
    $stmt_update_creditor->bind_param("ssi", $receipt_number, $combined_description, $creditor_transaction_id);
    if (!$stmt_update_creditor->execute()) {
        throw new Exception('Failed to update creditor transaction: ' . $stmt_update_creditor->error);
    }
    $stmt_update_creditor->close();
}
elseif ($transaction_type === 'weight') {
    // Handle Weight Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'weight' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $weight_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Weight transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'weight' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update weight transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();
}
elseif ($transaction_type === 'umrah_refund') {
    // Handle Weight Refund Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'umrah_refund' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $umrah_refund_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Umrah refund transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'umrah_refund' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update umrah refund transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();

}
elseif ($transaction_type === 'visa_refund') {
    // Handle Weight Refund Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'visa_refund' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $umrah_refund_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Visa refund transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'visa_refund' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update visa refund transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();
}
elseif ($transaction_type === 'hotel_refund') {
    // Handle Weight Refund Transactions
    $stmt_fetch_transaction = $conn->prepare("
        SELECT mt.id, mt.description, mt.amount, mt.currency, mt.main_account_id, mt.reference_id
        FROM main_account_transactions mt 
        WHERE mt.id = ? AND mt.transaction_of = 'hotel_refund' AND mt.tenant_id = ?");
    $stmt_fetch_transaction->bind_param("ii", $transaction_id, $tenant_id);
    $stmt_fetch_transaction->execute();
    $stmt_fetch_transaction->bind_result($trans_id, $existing_description, $amount, $currency, $main_account_id, $umrah_refund_transaction_id);
    if (!$stmt_fetch_transaction->fetch()) {
        throw new Exception('Hotel refund transaction details not found.');
    }
    $stmt_fetch_transaction->close();

    // Combine existing description with new remarks
    $combined_description = $existing_description . " | Additional Remarks: " . $remarks;

    // Update main account transaction
    $stmt_update_transaction = $conn->prepare("UPDATE main_account_transactions SET receipt = ?, description = ? WHERE id = ? AND transaction_of = 'hotel_refund' AND tenant_id = ?");
    $stmt_update_transaction->bind_param("ssii", $receipt_number, $combined_description, $transaction_id, $tenant_id);
    if (!$stmt_update_transaction->execute()) {
        throw new Exception('Failed to update hotel refund transaction: ' . $stmt_update_transaction->error);
    }
    $stmt_update_transaction->close();
}

else {
    throw new Exception('Unsupported transaction type.');
}

        // 2. Update notification status to 'Read'
        $stmt_update_notification = $conn->prepare("UPDATE notifications SET status = 'Read' WHERE id = ? AND tenant_id = ?");
        $stmt_update_notification->bind_param("ii", $notification_id, $tenant_id);
        if (!$stmt_update_notification->execute()) {
            throw new Exception('Failed to update notification status: ' . $stmt_update_notification->error);
        }
        $stmt_update_notification->close();

        // Commit transaction
        $conn->commit();

        echo json_encode(["status" => "success", "message" => "Notification approved, transaction marked as 'Paid', and balance updated."]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    } finally {
        $conn->close();
    }
}
?>
