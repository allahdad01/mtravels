<?php
// Start session if not already started
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'client')) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
include '../includes/conn.php';


// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$ticketsQuery = "
   SELECT 
    tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline, 
    tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price, 
    tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status, 
    tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,

    s.name as supplier_name,
    c.name as sold_to_name,
    ma.name as paid_to_name,
    
    
    tb.price as price,
    tb.profit as profit,
    tb.currency currency,
    tb.phone as phone,
    tb.gender as gender,
    
    tb.description as description -- Ensure description field is also included
FROM 
    ticket_reservations tb

LEFT JOIN 
    suppliers s ON tb.supplier = s.id
LEFT JOIN 
    clients c ON tb.sold_to = c.id
LEFT JOIN 
    main_account ma ON tb.paid_to = ma.id
    WHERE tb.sold_to = " . $_SESSION['user_id'] . "
ORDER BY 
    tb.id ASC
";

$ticketsResult = $conn->query($ticketsQuery);

$tickets = [];
if ($ticketsResult) {
    while ($row = $ticketsResult->fetch_assoc()) {
        // Map base ticket data
        $ticket_id = $row['id'];
        if (!isset($tickets[$ticket_id])) {
            $tickets[$ticket_id] = [
                'ticket' => [
                    'id' => $row['id'],
                    'supplier_name' => $row['supplier_name'],
                    'sold_to' => $row['sold_to_name'],
                    'paid_to' => $row['paid_to_name'],
                    'title' => $row['title'],
                    'passenger_name' => $row['passenger_name'],
                    'pnr' => $row['pnr'],
                    'airline' => $row['airline'],
                    'origin' => $row['origin'],
                    'destination' => $row['destination'],
                    'issue_date' => $row['issue_date'],
                    'departure_date' => $row['departure_date'],
                    'sold' => $row['sold'],
                    'price' => $row['price'],
                    'profit' => $row['profit'],
                    'gender' => $row['gender'],
                    'currency' => $row['currency'],

                    'phone' => $row['phone'],
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'trip_type' => $row['trip_type'],
                    'return_date' => $row['return_date'],
                    'return_origin' => $row['return_origin'],
                    'return_destination' => $row['return_destination']
                ]
            ];
        }

       
    }
} else {
    echo "Error: " . $conn->error;
}




// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers where status = 'active'";
$suppliersResult = $conn->query($suppliersQuery);
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}

?>

<?php include '../includes/header_client.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10">Ticket</h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:">Ticket Reservations</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="mb-3 text-right">
                                        <!-- Filter Input -->
                                    <input type="text" id="pnrFilter" class="form-control mb-3" placeholder="Search by PNR...">
                                       
                                    </div>
                                    <div class="card">
                                        <!-- body -->
                                        
                                         <div class="table-responsive">
                                            


                                <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Action</th>
                                                <th>Payment</th>
                                                <th>Sold To</th>
                                                <th>Paid To</th>
                                                <th>Title</th>
                                                <th>Passenger Name</th>
                                                <th>PNR</th>
                                                <th>Sector</th>
                                                <th>Airline</th>
                                                <th>Issue Date</th>
                                                <th>Departure Date</th>
                                                <th>Sold</th>
                                            </tr>
                                        </thead>
                                       <tbody id="ticketTable">
                                            <?php 
                                            $counter = 1; // Start counter from 1
                                            foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td><?= $counter++ ?></td> <!-- Increment counter for each row -->
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle" type="button" id="actionDropdown<?= $ticket['ticket']['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            <i class="feather icon-more-vertical"></i> Actions
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionDropdown<?= $ticket['ticket']['id'] ?>">
                                                            <button class="dropdown-item view-details" data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                                                                <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                <?php
                                                    // Get client type from clients table
                                                    $soldTo = $ticket['ticket']['sold_to'];
                                                    $isAgencyClient = false; // Default to not agency client
                                                    
                                                    // Fix: We need to query the clients table using the client name from sold_to
                                                    $clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo'");
                                                    if ($clientQuery && $clientQuery->num_rows > 0) {
                                                        $clientRow = $clientQuery->fetch_assoc();
                                                        // Only show payment status for agency clients
                                                        $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                    }
                                                    
                                                    // Only show payment status for agency clients
                                                    if ($isAgencyClient) {
                                                        // Calculate payment status
                                                        $transactionTotal = 0;
                                                        $soldAmount = floatval($ticket['ticket']['sold']);
                                                        
                                                        // Get ticket ID first
                                                        $ticketId = $ticket['ticket']['id'];
                                                        
                                                        // Get exchange rate from database
                                                        $exchangeRateQuery = $conn->query("SELECT exchange_rate FROM ticket_bookings WHERE id = '$ticketId' LIMIT 1");
                                                        $exchangeRate = 1; // Default value
                                                        if ($exchangeRateRow = $exchangeRateQuery->fetch_assoc()) {
                                                            $exchangeRate = floatval($exchangeRateRow['exchange_rate']);
                                                        }
                                                        
                                                        // Query transactions from account_transactions table
                                                        $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE 
                                                            transaction_of = 'ticket_sale' 
                                                            AND reference_id = '$ticketId'");
                                                        
                                                        if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                            while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                if ($transaction['currency'] === 'USD') {
                                                                    // Convert USD to AFS
                                                                    $transactionTotal += floatval($transaction['amount']) * $exchangeRate;
                                                                } else {
                                                                    // Already in AFS
                                                                    $transactionTotal += floatval($transaction['amount']);
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Status icon based on payment status
                                                        if ($transactionTotal <= 0) {
                                                            // No transactions
                                                            echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                        } elseif ($transactionTotal < $soldAmount) {
                                                            // Partial payment
                                                            $percentage = round(($transactionTotal / $soldAmount) * 100);
                                                            echo '<i class="fas fa-circle text-warning" title="Partial payment: ' . number_format($transactionTotal, 2) . ' / ' . number_format($soldAmount, 2) . ' AFS (' . $percentage . '%)"></i>';
                                                        } else {
                                                            // Fully paid
                                                            echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                        }
                                                    } else {
                                                        // Not an agency client - show neutral icon
                                                        echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                    }
                                                ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['sold_to']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['paid_to']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['title']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['passenger_name']) ?></td>
                                                <td class="pnr-field"><?= htmlspecialchars($ticket['ticket']['pnr']) ?></td>
                                                <td>
                                                    <?php if ($ticket['ticket']['trip_type'] === 'one_way'): ?>
                                                        <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?> - 
                                                        <?= htmlspecialchars($ticket['ticket']['return_destination']) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['airline']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['issue_date']) ?></td>
                                                <td>
                                                <?php if ($ticket['ticket']['trip_type'] === 'one_way'): ?>
                                                    <?= htmlspecialchars($ticket['ticket']['departure_date']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($ticket['ticket']['departure_date']) ?> - <?= htmlspecialchars($ticket['ticket']['return_date']) ?>
                                                <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['sold']) ?></td>
                                                
                                            </tr>

                                           
                                            <?php endforeach; ?>
                                        </tbody>
                            </table>
                                   <!-- Ticket details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-clipboard mr-2"></i>Ticket Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Sold Price</div>
                            <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Base Price</div>
                            <h4 class="mb-0 text-info" id="base-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Profit</div>
                            <h4 class="mb-0 text-success" id="profit">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Payment Amount</div>
                            <h4 class="mb-0 text-success" id="paymentAmount">-</h4>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i>Summary
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description" role="tab">
                            <i class="feather icon-file-text mr-2"></i>Description
                        </a>
                    </li>
                    
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Client Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Passenger Name</span>
                                            <strong id="passenger-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">PNR</span>
                                            <strong id="pnr">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Sold To</span>
                                            <strong id="sold-to">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Paid To</span>
                                            <strong id="paid-to">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Additional Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Payment Currency</span>
                                            <strong id="payment-currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Exchange Rate</span>
                                            <strong id="exchangeRate">-</strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Phone</span>
                                            <strong id="phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Gender</span>
                                            <strong id="gender">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Tab -->
                    <div class="tab-pane fade" id="details-description" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p id="description" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>

                   
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
                
            </div>
        </div>
    </div>
</div>







                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>



                                    <!-- view ticket details -->
                                <script>
                                  // Function to populate and display modal details
                                    $(document).on('click', '.view-details', function() {
                                        var ticketData = $(this).data('ticket');

                                        console.log(ticketData);  // Log ticket data for debugging
                                         if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        // Attach ticket data to the modal
                                        $('#detailsModal').data('ticket', ticketData); // Attach full ticket data
                                        $('#detailsModal').data('ticket-id', ticketData.ticket.id); // Attach ticket ID


                                        if (ticketData) {
                                            // Supplier, sold to, and paid to names should now be populated correctly
                                            $('#passenger-name').text(ticketData.ticket.passenger_name || 'N/A');
                                            $('#pnr').text(ticketData.ticket.pnr || 'N/A');
                                            $('#sold-to').text(ticketData.ticket.sold_to || 'N/A');
                                            $('#paid-to').text(ticketData.ticket.paid_to || 'N/A');
                                            
                                            // Populate other fields...
                                            $('#sold-price').text(ticketData.ticket.sold || 'N/A');
                                            $('#base-price').text(ticketData.ticket.price || 'N/A');
                                            $('#profit').text(ticketData.ticket.profit || 'N/A');
                                            $('#paymentAmount').text(ticketData.ticket.paymentAmount || 'N/A');
                                            $('#currency').text(ticketData.ticket.currency || 'N/A');
                                            $('#payment-currency').text(ticketData.ticket.paymentCurrency || 'N/A');
                                            $('#exchangeRate').text(ticketData.ticket.exchangeRate || 'N/A');
                                            $('#phone').text(ticketData.ticket.phone || 'N/A');
                                            $('#gender').text(ticketData.ticket.gender || 'N/A');
                                            $('#description').text(ticketData.ticket.description || 'N/A');
                                            
                                            // Handle refund data...
                                            if (ticketData.refund_data) {
                                                $('#refund-supplier-penalty').text(ticketData.refund_data.supplier_penalty || 'N/A');
                                                $('#refund-service-penalty').text(ticketData.refund_data.service_penalty || 'N/A');
                                                $('#refund-to-passenger').text(ticketData.refund_data.refund_to_passenger || 'N/A');
                                                $('#refund-status').text(ticketData.refund_data.status || 'N/A');
                                                $('#refund-remarks').text(ticketData.refund_data.remarks || 'N/A');
                                            }

                                            // Handle date change data...
                                            if (ticketData.date_change_data) {
                                                $('#date-change-departure-date').text(ticketData.date_change_data.departure_date || 'N/A');
                                                $('#date-change-currency').text(ticketData.date_change_data.currency || 'N/A');
                                                $('#date-change-supplier-penalty').text(ticketData.date_change_data.supplier_penalty || 'N/A');
                                                $('#date-change-service-penalty').text(ticketData.date_change_data.service_penalty || 'N/A');
                                                $('#date-change-status').text(ticketData.date_change_data.status || 'N/A');
                                                $('#date-change-remarks').text(ticketData.date_change_data.remarks || 'N/A');
                                            }

                                            $('#detailsModal').modal('show');  // Show the modal with details
                                        } else {
                                            alert('Ticket data not available!');
                                        }
                                    });
                                    // Date change function
                                $(document).ready(function () {
                                    // Open Date Change Modal
                                    $('#dateChangeBtn').click(function () {
                                       const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
                                        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        const ticketId = ticketData.ticket.id; // Extract the ticket ID

                                        // Pass the ticketId dynamically to the Date Change modal fields
                                        $('#dateChangeTicketId').val(ticketId);  // Set ticketId in the hidden field for the date change form

                                        // Populate fields (fetch dynamically or mock data)
                                        $('#dateChangeSold').val($('#sold-price').text());
                                        $('#dateChangeBase').val($('#base-price').text());
                                        $('#dateChangeDescription').val($('#description').text());
                                        $('#dateChangeDepartureDate').val('');  // Empty the departure date for the user to enter

                                        $('#dateChangeModal').modal('show');
                                    });

                                    // Open Refund Modal
                                    $('#refundBtn').click(function () {
                                        const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
                                        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        const ticketId = ticketData.ticket.id; // Extract the ticket ID

                                        $('#refundTicketId').val(ticketId); // Set the hidden field for the refund form

                                        // Fetch client type and handle the refund modal
                                        $.ajax({
                                            type: 'POST',
                                            url: 'getClientType.php',
                                            data: { ticketId: ticketId }, // Send only the ticket ID
                                            success: function (response) {
                                                const data = JSON.parse(response);

                                                if (data.status === 'success') {
                                                    const clientType = data.client_type; // Client type: agency or regular
                                                    const basePrice = parseFloat($('#base-price').text()); // Base price
                                                    const soldPrice = parseFloat($('#sold-price').text()); // Sold price
                                                    
                                                    // Dynamically retrieve and display initial penalties
                                                    let supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0; 
                                                    let servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;

                                                    console.log(`Initial Values: Client Type = ${clientType}`);
                                                    console.log(`Sold Price = ${soldPrice}, Base Price = ${basePrice}`);
                                                    console.log(`Supplier Penalty = ${supplierPenalty}, Service Penalty = ${servicePenalty}`);

                                                    let refundAmount = 0;

                                                    if (clientType === 'agency') {
                                                        // Use Base Price for Agencies
                                                        refundAmount = basePrice - supplierPenalty - servicePenalty;
                                                    } else if (clientType === 'regular') {
                                                        // Use Sold Price for Regular Clients
                                                        refundAmount = soldPrice - supplierPenalty - servicePenalty;
                                                    }

                                                    console.log(`Initial Refund Amount = ${refundAmount.toFixed(2)}`);

                                                    // Populate initial modal fields
                                                    $('#refundBase').val(basePrice.toFixed(2));
                                                    $('#refundSold').val(soldPrice.toFixed(2));
                                                    $('#refundAmount').val(refundAmount.toFixed(2));

                                                    // On change of penalties, update the refund calculation
                                                    $('#supplierRefundPenalty, #serviceRefundPenalty').on('input', function () {
                                                        supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0;
                                                        servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;

                                                        if (clientType === 'agency') {
                                                            refundAmount = basePrice - supplierPenalty - servicePenalty;
                                                        } else if (clientType === 'regular') {
                                                            refundAmount = soldPrice - supplierPenalty - servicePenalty;
                                                        }

                                                        // Ensure refundAmount is non-negative
                                                        if (refundAmount < 0) refundAmount = 0;

                                                        console.log(`Updated Values: Supplier Penalty = ${supplierPenalty}, Service Penalty = ${servicePenalty}`);
                                                        console.log(`Updated Refund Amount = ${refundAmount.toFixed(2)}`);

                                                        $('#refundAmount').val(refundAmount.toFixed(2));
                                                    });

                                                    // Show the modal
                                                    $('#refundModal').modal('show');
                                                } else {
                                                    alert('Error: ' + data.message); // If there was an error fetching client type
                                                }
                                            },
                                            error: function () {
                                                alert('Error fetching client type.'); // AJAX error
                                            }
                                        });
                                    });
                                    // Submit Date Change Form
                                    $('#dateChangeForm').submit(function (e) {
                                        e.preventDefault();
                                        const formData = $(this).serialize();

                                        $.ajax({
                                            url: 'insert_ticket_record_dc.php',
                                            method: 'POST',
                                            data: formData,
                                            success: function (response) {
                                                console.log('Server Response:', response); // Log response for debugging
                                                if ($.trim(response) === 'success') { // Trim whitespace
                                                    alert('Date Change recorded successfully!');
                                                    $('#dateChangeModal').modal('hide');
                                                } else {
                                                    alert('Error recording Date Change: ' + response);
                                                }
                                            },
                                            error: function () {
                                                alert('An error occurred.');
                                            },
                                        });
                                    });
                                    // Submit Refund Form
                                    $('#refundForm').submit(function (e) {
                                        e.preventDefault();
                                        const formData = $(this).serialize();

                                        $.ajax({
                                            url: 'insert_ticket_record.php',
                                            method: 'POST',
                                            data: formData,
                                            success: function (response) {
                                                 console.log('Server Response:', response); // Log response for debugging
                                                if ($.trim(response) === 'success') {
                                                    alert('Refund recorded successfully!');
                                                    $('#refundModal').modal('hide');
                                                } else {
                                                    alert('Error recording Refund.');
                                                }
                                            },
                                            error: function () {
                                                alert('An error occurred.');
                                            },
                                        });
                                    });
                                });

                            </script>
                                <script>
                                   document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
                                    event.preventDefault(); // Prevent default form submission
                                    const formData = new FormData(this); // Collect form data

                                    fetch('save_ticket_reserve.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json()) // Parse JSON response
                                    .then(data => {
                                        if (data.status === 'success') { // Check for status
                                            alert(data.message); // Show success message
                                            location.reload(); // Reload page
                                        } else {
                                            alert('Error: ' + data.message); // Display specific error message
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error); // Log error
                                        alert('An unexpected error occurred.');
                                    });
                                });

                                </script>

                               
                            <!-- Fetch supplier curency -->
                            <script>
                                document.getElementById('supplier').addEventListener('change', function () {
                                    const supplierId = this.value;

                                    console.log('Selected Supplier ID:', supplierId);

                                    if (supplierId) {
                                        fetch(`get_supplier_currency.php?supplier_id=${supplierId}`)
                                            .then(response => {
                                                console.log('Response status:', response.status); // Log status
                                                return response.json();
                                            })
                                            .then(data => {
                                                console.log('Response data:', data); // Log full response
                                                const currInput = document.getElementById('curr');
                                                if (data.currency) {
                                                    currInput.value = data.currency;

                                                    console.log('Currency input updated to:', data.currency);
                                                } else {
                                                    currInput.value = '';
                                                    console.warn('No currency found in response!');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error fetching supplier currency:', error);
                                            });
                                    } else {
                                        console.log('No supplier selected, clearing input.');
                                        document.getElementById('curr').value = '';
                                    }
                                });


                                </script>

                                <script>
                                        function deleteTicket(id) {
                                            if (confirm('Are you sure you want to delete this Ticket?')) {
                                                fetch('delete_ticket_reserve.php', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json' },
                                                    body: JSON.stringify({ id }),
                                                })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        alert('Ticket deleted successfully!');
                                                        location.reload();
                                                    } else {
                                                        alert('Error: ' + data.message);
                                                    }
                                                })
                                                .catch(error => console.error('Error deleting Ticket:', error));
                                            }
                                        }
                                        </script>

  
                                    </div>
                                </div>
                                    <!-- [ refund calculation ]-->
                        <script>
                                    // Refund calculation logic
                                    const supplierRefundPenaltyElement = document.getElementById('supplierRefundPenalty');
                                    const serviceRefundPenaltyElement = document.getElementById('serviceRefundPenalty');
                                    
                                    if (supplierRefundPenaltyElement) {
                                        supplierRefundPenaltyElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    if (serviceRefundPenaltyElement) {
                                        serviceRefundPenaltyElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    const refundBaseElement = document.getElementById('refundBase');
                                    if (refundBaseElement) {
                                        refundBaseElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    function updateRefundAmount() {
                                        const refundBaseEl = document.getElementById('refundBase');
                                        const supplierPenaltyEl = document.getElementById('supplierRefundPenalty');
                                        const servicePenaltyEl = document.getElementById('serviceRefundPenalty');
                                        const refundAmountEl = document.getElementById('refundAmount');
                                        
                                        if (!refundBaseEl || !supplierPenaltyEl || !servicePenaltyEl || !refundAmountEl) {
                                            console.error('Missing required elements for refund calculation');
                                            return;
                                        }
                                        
                                        const base = parseFloat(refundBaseEl.value) || 0;
                                        const supplierPenalty = parseFloat(supplierPenaltyEl.value) || 0;
                                        const servicePenalty = parseFloat(servicePenaltyEl.value) || 0;
                                        
                                        // Total penalty
                                        const totalPenalty = supplierPenalty + servicePenalty;
                                        
                                        // Refund amount calculation
                                        const refundAmount = base - supplierPenalty - servicePenalty;
                                        
                                        // Show refund amount in readonly input
                                        refundAmountEl.value = refundAmount > 0 ? refundAmount : 0;
                                    }
                                </script>
                                <script>
                                    $(document).on('click', '.generate-invoice', function () {
                                    const ticketId = $(this).data('ticket-id');
                                    if (!ticketId) {
                                        alert('Ticket ID is missing!');
                                        return;
                                    }
                                    window.location.href = `generateInvoice.php?ticketId=${ticketId}`;
                                });

                         </script>

 <script>
        document.getElementById('pnrFilter').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#ticketTable tr');

            rows.forEach(row => {
                let pnr = row.querySelector('.pnr-field').textContent.toLowerCase();
                row.style.display = pnr.includes(filter) ? '' : 'none';
            });
        });
</script>




                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  
  
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>