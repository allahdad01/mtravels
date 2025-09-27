<?php
// Include necessary files and create DB connection
include_once '../includes/db.php'; // Assuming db.php handles DB connection

try {
    // Query the invoices table and join necessary tables
    $stmt = $pdo->prepare("
        SELECT 
            invoices.id AS invoice_id,
            invoices.invoice_number,
            invoices.type AS invoice_type,
            invoices.invoice_date,
            invoices.total_amount,
            clients.name AS client_name,
            main_account.name AS main_account_name,
            clients.id AS client_id,      
            main_account.id AS main_account_id, 
            invoices.reference_id,
            visa_applications.applicant_name AS visa_applicant_name,
            visa_applications.sold AS visa_total,
            ticket_bookings.passenger_name AS ticket_passenger_name,
            refunded_tickets.passenger_name AS ticket_passenger_rname,
            date_change_tickets.passenger_name AS ticket_passenger_dname,
            refunded_tickets.refund_to_passenger AS refund_amount,
            date_change_tickets.supplier_penalty,
            date_change_tickets.service_penalty
        FROM invoices
        LEFT JOIN clients ON invoices.client_id = clients.id
        LEFT JOIN main_account ON invoices.main_account_id = main_account.id
        LEFT JOIN visa_applications ON invoices.reference_id = visa_applications.id AND invoices.type = 'visa'
        LEFT JOIN ticket_bookings ON invoices.reference_id = ticket_bookings.id AND invoices.type = 'ticket'
        LEFT JOIN refunded_tickets ON invoices.reference_id = refunded_tickets.id AND invoices.type = 'refund_ticket'
        LEFT JOIN date_change_tickets ON invoices.reference_id = date_change_tickets.id AND invoices.type = 'date_change_ticket'
    ");
    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each invoice for dynamic name and amount fields
    foreach ($invoices as &$invoice) {
        switch ($invoice['invoice_type']) {
            case 'visa':
                // Visa details: name from `applicant_name` and amount from `sold`
                $invoice['name'] = $invoice['visa_applicant_name'] ?? 'N/A';
                $invoice['amount'] = $invoice['visa_total'] ?? 'N/A';
                break;

            case 'ticket':
                // Ticket details: name from `passenger_name` and total as is
                $invoice['name'] = $invoice['ticket_passenger_name'] ?? 'N/A';
                $invoice['amount'] = $invoice['total_amount'] ?? 'N/A';
                break;

            case 'refund_ticket':
                // Refund tickets: name from `passenger_name` and refund amount
                $invoice['name'] = $invoice['ticket_passenger_rname'] ?? 'N/A';
                $invoice['amount'] = $invoice['refund_amount'] ?? 'N/A';
                break;

            case 'date_change_ticket':
                // Date change tickets: name from `passenger_name` and penalties as amount
                $invoice['name'] = $invoice['ticket_passenger_dname'] ?? 'N/A';
                $invoice['amount'] = ($invoice['supplier_penalty'] + $invoice['service_penalty']) ?? 'N/A';
                break;

            default:
                // Default case if type doesn't match
                $invoice['name'] = 'N/A';
                $invoice['amount'] = 'N/A';
                break;
        }

        // Remove unused fields after processing
        unset($invoice['visa_applicant_name']);
        unset($invoice['visa_total']);
        unset($invoice['ticket_passenger_name']);
        unset($invoice['refund_amount']);
        unset($invoice['supplier_penalty']);
        unset($invoice['service_penalty']);
    }

    // Prepare response
    $response = [
        'status' => 'success',
        'invoices' => $invoices,
    ];

} catch (PDOException $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
}

// Output response as JSON
echo json_encode($response);
?>
