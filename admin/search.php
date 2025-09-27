<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize search variables
$searchTerm = '';
$searchResults = [];
$resultMessage = '';
$searchPerformed = false;

// Process search form submission
if (isset($_POST['search'])) {
    $searchTerm = trim($_POST['searchTerm']);
    $searchPerformed = true;
    
    if (empty($searchTerm)) {
        $resultMessage = "Please enter a search term";
    } else {
        // Array to store the combined results
        $searchResults = [];
        
        // Search in ticket_bookings table
        $ticketQuery = "SELECT 
                'Ticket' AS record_type,
                tb.id,
                tb.passenger_name AS name,
                tb.pnr AS reference,
                tb.phone,
                tb.gender,
                c.name AS client_name,
                c.id AS client_id,
                s.name AS supplier_name,
                s.id AS supplier_id,
                tb.origin,
                tb.destination,
                tb.departure_date,
                tb.issue_date,
                tb.status,
                tb.currency,
                tb.sold AS amount,
                NULL AS passport_number
            FROM ticket_bookings tb
            LEFT JOIN clients c ON tb.sold_to = c.id
            LEFT JOIN suppliers s ON tb.supplier = s.id
            WHERE tb.tenant_id = ? AND
                tb.passenger_name LIKE ? OR
                tb.pnr LIKE ? OR
                tb.phone LIKE ?";
                
        $stmt = $pdo->prepare($ticketQuery);
        $likeParam = "%$searchTerm%";
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $ticketResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $ticketResults);

                // Search in ticket_reservations table
                $ticketReservationQuery = "SELECT 
                'Ticket Reservation' AS record_type,
                tb.id,
                tb.passenger_name AS name,
                tb.pnr AS reference,
                tb.phone,
                tb.gender,
                c.name AS client_name,
                c.id AS client_id,
                s.name AS supplier_name,
                s.id AS supplier_id,
                tb.origin,
                tb.destination,
                tb.departure_date,
                tb.issue_date,
                tb.status,
                tb.currency,
                tb.sold AS amount,
                NULL AS passport_number
            FROM ticket_reservations tb
            LEFT JOIN clients c ON tb.sold_to = c.id
            LEFT JOIN suppliers s ON tb.supplier = s.id
            WHERE tb.tenant_id = ? AND
                tb.passenger_name LIKE ? OR
                tb.pnr LIKE ? OR
                tb.phone LIKE ?";
                
        $stmt = $pdo->prepare($ticketReservationQuery);
        $likeParam = "%$searchTerm%";
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $ticketReservationResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $ticketReservationResults);
        
        // Search in visa_applications table
        $visaQuery = "SELECT 
                'Visa' AS record_type,
                va.id,
                va.applicant_name AS name,
                va.passport_number AS reference,
                va.phone,
                va.gender,
                c.name AS client_name,
                c.id AS client_id,
                s.name AS supplier_name,
                s.id AS supplier_id,
                va.country AS origin,
                va.visa_type AS destination,
                va.applied_date AS departure_date,
                va.receive_date AS issue_date,
                va.status,
                va.currency,
                va.sold AS amount,
                va.passport_number
            FROM visa_applications va
            LEFT JOIN clients c ON va.sold_to = c.id
            LEFT JOIN suppliers s ON va.supplier = s.id
            WHERE va.tenant_id = ? AND
                va.applicant_name LIKE ? OR
                va.passport_number LIKE ? OR
                va.phone LIKE ?";
                
        $stmt = $pdo->prepare($visaQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $visaResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $visaResults);
        
        // Search in hotel_bookings table
        $hotelQuery = "SELECT 
                'Hotel' AS record_type,
                hb.id,
                CONCAT(hb.first_name, ' ', hb.last_name) AS name,
                hb.order_id AS reference,
                hb.contact_no AS phone,
                hb.gender,
                hb.sold_to AS client_name,
                NULL AS client_id,
                s.name AS supplier_name,
                s.id AS supplier_id,
                'Hotel' AS origin,
                hb.accommodation_details AS destination,
                hb.check_in_date AS departure_date,
                hb.issue_date,
                'Booked' AS status,
                hb.currency,
                hb.sold_amount AS amount,
                NULL AS passport_number
            FROM hotel_bookings hb
            LEFT JOIN suppliers s ON hb.supplier_id = s.id
            WHERE hb.tenant_id = ? AND
                CONCAT(hb.first_name, ' ', hb.last_name) LIKE ? OR
                hb.order_id LIKE ? OR
                hb.contact_no LIKE ?";
                
        $stmt = $pdo->prepare($hotelQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $hotelResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $hotelResults);
        
        // Search in umrah_bookings table
            $umrahQuery = "SELECT 
            'Umrah' AS record_type,
            ub.booking_id AS id,
            ub.name,
            ub.passport_number AS reference,
            NULL AS phone,
            NULL AS gender,
            c.name AS client_name,
            ub.sold_to AS client_id,
            s.name AS supplier_name,
            ub.supplier AS supplier_id,
            'Mecca/Medina' AS origin,
            ub.room_type AS destination,
            ub.flight_date AS departure_date,
            ub.created_at AS issue_date,
            'Booked' AS status,
            ub.currency,
            ub.sold_price AS amount,
            ub.passport_number
            FROM umrah_bookings ub
            LEFT JOIN clients c ON ub.sold_to = c.id
            LEFT JOIN suppliers s ON ub.supplier = s.id
            WHERE ub.tenant_id = ? AND
            ub.name LIKE ? OR
            ub.passport_number LIKE ? OR
            ub.id_type LIKE ?";
                
        $stmt = $pdo->prepare($umrahQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $umrahResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $umrahResults);
        
       // Search in additional_payments table
       $additionalPaymentsQuery = "SELECT 
       'Additional Payment' AS record_type,
       ap.id,
       ap.description, 
       ap.payment_type AS name,
       ap.id AS reference,
       NULL AS phone,
       NULL AS gender,
       NULL AS client_name,
       NULL AS client_id,
       NULL AS supplier_name,
       NULL AS supplier_id,
       ap.payment_type AS origin,
       ap.description AS destination,
       ap.created_at AS departure_date,
       ap.created_at AS issue_date,
       ap.payment_type AS status,
       ap.currency,
       ap.sold_amount AS amount,
       NULL AS passport_number
   FROM additional_payments ap
            WHERE ap.tenant_id = ? AND
            ap.description LIKE ? OR
            ap.payment_type LIKE ?";
                
        $stmt = $pdo->prepare($additionalPaymentsQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam]);
        $additionalPaymentsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $additionalPaymentsResults);
        
        // Search in expenses table
        $expensesQuery = "SELECT 
            'Expense' AS record_type,
            e.id,
            ec.name AS name,
            e.id AS reference,
            NULL AS phone,
            NULL AS gender,
            NULL AS client_name,
            NULL AS client_id,
            NULL AS supplier_name,
            NULL AS supplier_id,
            'Expense' AS origin,
            e.description AS destination,
            e.date AS departure_date,
            e.created_at AS issue_date,
            'Paid' AS status,
            e.currency,
            e.amount,
            NULL AS passport_number
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.tenant_id = ? AND
            e.description LIKE ? OR
            ec.name LIKE ?";
                
        $stmt = $pdo->prepare($expensesQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam]);
        $expensesResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $expensesResults);
        
       // Search in creditors table
            $creditorsQuery = "SELECT 
            'Creditor' AS record_type,
            cr.id,
            cr.name AS name,
            cr.id AS reference,
            cr.phone AS phone,
            NULL AS gender,
            NULL AS client_name,
            NULL AS client_id,
            NULL AS supplier_name,
            NULL AS supplier_id,
            'Credit' AS origin,
            cr.address AS destination,
            cr.created_at AS departure_date,
            cr.created_at AS issue_date,
            cr.status,
            cr.currency,
            cr.balance AS amount,
            NULL AS passport_number
            FROM creditors cr
            WHERE cr.tenant_id = ? AND
            cr.name LIKE ? OR
            cr.email LIKE ? OR
            cr.phone LIKE ?";
                
        $stmt = $pdo->prepare($creditorsQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $creditorsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $creditorsResults);
        
        // Search in debtors table
            $debtorsQuery = "SELECT 
            'Debtor' AS record_type,
            db.id,
            db.name AS name,
            db.id AS reference,
            db.phone AS phone,
            NULL AS gender,
            NULL AS client_name,
            NULL AS client_id,
            NULL AS supplier_name,
            NULL AS supplier_id,
            'Debt' AS origin,
            db.address AS destination,
            db.created_at AS departure_date,
            db.created_at AS issue_date,
            db.status,
            db.currency,
            db.balance AS amount,
            NULL AS passport_number
            FROM debtors db
            WHERE db.tenant_id = ? AND
            db.name LIKE ? OR
            db.email LIKE ? OR
            db.phone LIKE ?";
                
        $stmt = $pdo->prepare($debtorsQuery);
        $stmt->execute([$tenant_id, $likeParam, $likeParam, $likeParam]);
        $debtorsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $searchResults = array_merge($searchResults, $debtorsResults);
        
        // For each result, fetch related transactions
        foreach ($searchResults as $key => $result) {
            $transactions = [];
            
            if ($result['record_type'] == 'Ticket') {
                // Get main account transactions related to this ticket
                $mainAccountTransQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND (mat.transaction_of = 'ticket_sale' 
                    OR mat.transaction_of = 'ticket_refund' 
                    OR mat.transaction_of = 'date_change')";
                $stmt = $pdo->prepare($mainAccountTransQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $mainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $mainAccountTrans);
                
            } elseif ($result['record_type'] == 'Ticket Reservation') {
                // Get main account transactions related to ticket reservation
                $ticketReservationMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'ticket_reservation'";
                $stmt = $pdo->prepare($ticketReservationMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $ticketReservationMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $ticketReservationMainAccountTrans);

            } elseif ($result['record_type'] == 'Visa') {
                // Get main account transactions related to visa
                $visaMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'visa_sale'";
                $stmt = $pdo->prepare($visaMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $visaMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $visaMainAccountTrans);
            } elseif ($result['record_type'] == 'Hotel') {
                // Get main account transactions related to hotel booking
                $hotelMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'hotel_booking'";
                $stmt = $pdo->prepare($hotelMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $hotelMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $hotelMainAccountTrans);
            } elseif ($result['record_type'] == 'Umrah') {
                // Get main account transactions related to umrah booking
                $umrahMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'umrah_booking'";
                $stmt = $pdo->prepare($umrahMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $umrahMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $umrahMainAccountTrans);
            } elseif ($result['record_type'] == 'Additional Payment') {
                // Get main account transactions related to additional payment
                $apMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'additional_payment'";
                $stmt = $pdo->prepare($apMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $apMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $apMainAccountTrans);
            } elseif ($result['record_type'] == 'Expense') {
                // Get main account transactions related to expense
                $expenseMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'expense'";
                $stmt = $pdo->prepare($expenseMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $expenseMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $expenseMainAccountTrans);
            } elseif ($result['record_type'] == 'Creditor') {
                // Get main account transactions related to creditor
                $creditorMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'creditor'";
                $stmt = $pdo->prepare($creditorMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $creditorMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $creditorMainAccountTrans);
            } elseif ($result['record_type'] == 'Debtor') {
                // Get main account transactions related to debtor
                $debtorMainAccountQuery = "SELECT 
                        'Main Account' AS transaction_type,
                        mat.type,
                        mat.amount,
                        mat.currency,
                        mat.description,
                        mat.transaction_of,
                        mat.created_at AS transaction_date
                    FROM main_account_transactions mat
                    WHERE mat.reference_id = ? AND mat.tenant_id = ? AND mat.transaction_of = 'debtor'";
                $stmt = $pdo->prepare($debtorMainAccountQuery);
                $stmt->execute([$result['id'], $tenant_id]);
                $debtorMainAccountTrans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $transactions = array_merge($transactions, $debtorMainAccountTrans);
            }
            
            // Add transactions to the search result
            $searchResults[$key]['transactions'] = $transactions;
        }
        
        if (empty($searchResults)) {
            $resultMessage = "No results found for: " . htmlspecialchars($searchTerm);
        }
    }
}

// Include the header
include '../includes/header.php';
?>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('search') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('search') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><?= __('search_for_people') ?></h5>
                        <p class="text-muted"><?= __('search_by_name_passport_number_phone_number_or_any_other_identifier') ?></p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
    <!-- CSRF Protection -->
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
    
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" name="searchTerm" placeholder="Enter name, passport number, phone number..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit" name="search"><?= __('search') ?></button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($searchPerformed): ?>
                            <?php if (!empty($resultMessage)): ?>
                                <div class="alert alert-info mt-3"><?php echo h($resultMessage); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($searchResults)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th style="width: 10%"><?= __('type') ?></th>
                                                <th style="width: 15%"><?= __('name') ?> / <?= __('reference') ?></th>
                                                <th style="width: 15%" class="d-none d-md-table-cell"><?= __('contact') ?></th>
                                                <th style="width: 15%" class="d-none d-lg-table-cell"><?= __('client') ?> / <?= __('supplier') ?></th>
                                                <th style="width: 20%"><?= __('details') ?></th>
                                                <th style="width: 10%" class="d-none d-md-table-cell"><?= __('date') ?></th>
                                                <th style="width: 15%"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($searchResults as $index => $result): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                        if ($result['record_type'] == 'Ticket') echo 'primary';
                                                        elseif ($result['record_type'] == 'Ticket Reservation') echo 'primary';
                                                        elseif ($result['record_type'] == 'Visa') echo 'danger';
                                                        elseif ($result['record_type'] == 'Hotel') echo 'success';
                                                        elseif ($result['record_type'] == 'Umrah') echo 'warning';
                                                        elseif ($result['record_type'] == 'Additional Payment') echo 'info';
                                                        elseif ($result['record_type'] == 'Creditor') echo 'secondary';
                                                        elseif ($result['record_type'] == 'Debtor') echo 'dark';
                                                        elseif ($result['record_type'] == 'Expense') echo 'secondary';
                                                        ?>">
                                                            <?php echo h($result['record_type']); ?>
                                                        </span>
                                                        <div class="small text-muted mt-1 text-truncate">
                                                            <?php echo htmlspecialchars($result['status']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-truncate"><?php echo htmlspecialchars($result['name']) ?? ''; ?></div>
                                                        <small class="text-muted text-truncate d-inline-block"><?php echo htmlspecialchars($result['reference']) ?? ''; ?></small>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <?php if (!empty($result['phone'])): ?>
                                                            <div class="text-truncate"><?php echo htmlspecialchars($result['phone']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($result['gender'])): ?>
                                                            <small class="text-muted"><?php echo htmlspecialchars($result['gender']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="d-none d-lg-table-cell">
                                                        <?php if (!empty($result['client_name'])): ?>
                                                            <div class="text-truncate"><?php echo htmlspecialchars($result['client_name']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($result['supplier_name'])): ?>
                                                            <small class="text-muted text-truncate d-inline-block"><?php echo htmlspecialchars($result['supplier_name']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="description-text">
                                                            <?php 
                                                                echo htmlspecialchars($result['origin']); 
                                                                if ($result['record_type'] == 'Ticket' || $result['record_type'] == 'Ticket Reservation') {
                                                                    echo " → " . htmlspecialchars($result['destination']);
                                                                } else {
                                                                    echo ": " . htmlspecialchars($result['destination']);
                                                                }
                                                            ?>
                                                        </div>
                                                        <?php if (!empty($result['passport_number'])): ?>
                                                            <small class="text-muted text-truncate d-inline-block">Passport: <?php echo htmlspecialchars($result['passport_number']); ?></small>
                                                        <?php endif; ?>
                                                        <div class="small text-primary text-truncate">
                                                            <?php echo htmlspecialchars($result['currency']) . ' ' . htmlspecialchars($result['amount']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="d-none d-md-table-cell">
                                                        <div class="text-truncate">
                                                            <?php echo date('Y-m-d', strtotime($result['departure_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <?php if ($result['record_type'] == 'Ticket'): ?>
                                                                <a href="ticket_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Ticket Reservation'): ?>
                                                                <a href="ticket_reservation_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Visa'): ?>
                                                                <a href="visa_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Hotel'): ?>
                                                                <a href="hotel_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Umrah'): ?>
                                                                <a href="umrah_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Additional Payment'): ?>
                                                                <a href="additional_payments_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Creditor'): ?>
                                                                <a href="creditors_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Debtor'): ?>
                                                                <a href="debtors_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php elseif ($result['record_type'] == 'Expense'): ?>
                                                                <a href="expense_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-info">View</a>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-sm btn-secondary" 
                                                                data-toggle="collapse" 
                                                                data-target="#transactions-<?php echo h($index); ?>" 
                                                                aria-expanded="false" 
                                                                aria-controls="transactions-<?php echo h($index); ?>">
                                                                <?= __('transactions') ?>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="11" class="p-0">
                                                        <div class="collapse" id="transactions-<?php echo h($index); ?>">
                                                            <div class="card card-body m-2">
                                                                <h6><?= __('transaction_history') ?></h6>
                                                                <?php if (!empty($result['transactions'])): ?>
                                                                    <div class="table-responsive">
                                                                        <table class="table table-sm">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th><?= __('type') ?></th>
                                                                                    <th><?= __('transaction') ?></th>
                                                                                    <th><?= __('amount') ?></th>
                                                                                    <th><?= __('description') ?></th>
                                                                                    <th><?= __('date') ?></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <?php foreach ($result['transactions'] as $transaction): ?>
                                                                                    <tr>
                                                                                        <td>
                                                                                            <span class="badge badge-<?php 
                                                                                            if ($transaction['transaction_type'] == 'Main Account') {
                                                                                                if (strpos(strtolower($transaction['type']), 'debit') !== false) {
                                                                                                    echo 'danger';
                                                                                                } else {
                                                                                                    echo 'success';
                                                                                                }
                                                                                            } else {
                                                                                                echo 'secondary';
                                                                                            }
                                                                                            ?>">
                                                                                                <?php echo h($transaction['transaction_type']); ?>
                                                                                            </span>
                                                                                        </td>
                                                                                        <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                                                                        <td><?php echo (htmlspecialchars($transaction['currency'] ?? '')) . ' ' . htmlspecialchars($transaction['amount']); ?></td>
                                                                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($transaction['transaction_date']))); ?></td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <p class="text-muted"><?= __('no_transactions_found_for_this_item') ?></p>
                                                                <?php endif; ?>
                                                                
                                                                <div class="mt-3">
                                                                    <h6><?= __('view_complete_transaction_history') ?></h6>
                                                                    <div class="row">
                                                                        <div class="col-md-12">
                                                                            <?php if ($result['record_type'] == 'Ticket'): ?>
                                                                            <a href="ticket_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Ticket Reservation'): ?>
                                                                            <a href="ticket_reservation_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Visa'): ?>
                                                                            <a href="visa_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Hotel'): ?>
                                                                            <a href="hotel_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Umrah'): ?>
                                                                            <a href="umrah_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Additional Payment'): ?>
                                                                            <a href="additional_payments_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Creditor'): ?>
                                                                                <a href="creditors_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Debtor'): ?>
                                                                            <a href="debtors_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php elseif ($result['record_type'] == 'Expense'): ?>
                                                                            <a href="expense_detail.php?id=<?php echo h($result['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                                                <?= __('view_all_transactions') ?>
                                                                            </a>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                               <!-- Profile Modal -->
                               <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }

        /* Table text wrapping styles */
        .table td {
            max-width: 0;  /* Required for text-overflow to work */
            white-space: normal;
            word-wrap: break-word;
        }
        
        .table td .text-truncate {
            max-width: 150px;
            display: inline-block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .table td .description-text {
            max-height: 3.6em;  /* Show max 3 lines (1.2em * 3) */
            line-height: 1.2em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        /* Ensure consistent column widths */
        .table th, .table td {
            min-width: 80px;  /* Minimum width for any column */
        }

        /* Make the details column slightly larger */
        .table th[style*="width: 20%"],
        .table td:nth-child(5) {
            min-width: 200px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table td .text-truncate {
                max-width: 120px;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                            <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                        <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                            alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php
// Include the footer
include '../includes/admin_footer.php';

// Validate searchTerm
$searchTerm = isset($_POST['searchTerm']) ? DbSecurity::validateInput($_POST['searchTerm'], 'string', ['maxlength' => 255]) : null;

// Validate search
$search = isset($_POST['search']) ? DbSecurity::validateInput($_POST['search'], 'string', ['maxlength' => 255]) : null;
?> 