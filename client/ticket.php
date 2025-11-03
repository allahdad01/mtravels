<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'client')) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');


// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$ticketsQuery = "
   SELECT 
    tb.*, -- Base ticket details
    
    rt.supplier_penalty AS refund_supplier_penalty,
    rt.service_penalty AS refund_service_penalty,
    rt.refund_to_passenger,
    rt.status AS refund_status,
    rt.remarks AS refund_remarks,
    dct.departure_date AS date_change_departure_date,
    dct.currency AS date_change_currency,
    dct.supplier_penalty AS date_change_supplier_penalty,
    dct.service_penalty AS date_change_service_penalty,
    dct.status AS date_change_status,
    dct.remarks AS date_change_remarks,
    s.name AS supplier_name,
    c.name AS sold_to_name,
    ma.name AS paid_to_name,
    
    tb.price as price,
    tb.profit as profit,
    tb.currency currency,
    tb.phone as phone,
    tb.gender as gender,
    tb.description as description -- Ensure description field is also included
FROM 
    ticket_bookings tb
LEFT JOIN 
    refunded_tickets rt ON tb.id = rt.ticket_id
LEFT JOIN 
    date_change_tickets dct ON tb.id = dct.ticket_id
LEFT JOIN 
    suppliers s ON tb.supplier = s.id
LEFT JOIN 
    clients c ON tb.sold_to = c.id
LEFT JOIN 
    main_account ma ON tb.paid_to = ma.id
    where tb.sold_to = " . $_SESSION['user_id'] . "
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
                    'issue_date' => $row['issue_date'],
                    'departure_date' => $row['departure_date'],
                    'sold' => $row['sold'],
                    'price' => $row['price'],
                    'profit' => $row['profit'],
                    'gender' => $row['gender'],
                    'currency' => $row['currency'],
                    'phone' => $row['phone'],
                    'description' => $row['description'],
                    'status' => $row['status']
                ],
                'refund_data' => null,
                'date_change_data' => null
            ];
        }

        // Map refund data if exists
        if (!empty($row['refund_supplier_penalty']) || !empty($row['refund_service_penalty'])) {
            $tickets[$ticket_id]['refund_data'] = [
                'supplier_penalty' => $row['refund_supplier_penalty'],
                'service_penalty' => $row['refund_service_penalty'],
                'refund_to_passenger' => $row['refund_to_passenger'],
                
                'remarks' => $row['refund_remarks']
            ];
        }

        // Map date change data if exists
        if (!empty($row['date_change_departure_date'])) {
            $tickets[$ticket_id]['date_change_data'] = [
                'departure_date' => $row['date_change_departure_date'],
                'currency' => $row['date_change_currency'],
                'supplier_penalty' => $row['date_change_supplier_penalty'],
                'service_penalty' => $row['date_change_service_penalty'],
                
                'remarks' => $row['date_change_remarks']
            ];
        }
    }
} else {
    echo "Error: " . $conn->error;
}




// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers";
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
                                        <li class="breadcrumb-item"><a href="javascript:">Ticket</a></li>
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
                                            


                                            <table class="table table-hover shadow-sm">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th class="rounded-left">#</th>
                                                
                                                <th>Sold To</th>
                                                <th>Paid To</th>
                                                <th>Title</th>
                                                <th>Passenger Name</th>
                                                <th>PNR</th>
                                                <th>Airline</th>
                                                <th>Issue Date</th>
                                                <th>Departure Date</th>
                                                <th>Sold</th>
                                                <th class="rounded-right">Action</th>
                                            </tr>
                                        </thead>
                                       <tbody id="ticketTable">
    <?php 
    $counter = 1; // Start counter from 1
    foreach ($tickets as $ticket): ?>
    <tr class="border-bottom">
        <td class="align-middle"><?= $counter++ ?></td>
      
        <td class="align-middle"><?= htmlspecialchars($ticket['ticket']['sold_to']) ?></td>
        <td class="align-middle"><?= htmlspecialchars($ticket['ticket']['paid_to']) ?></td>
        <td class="align-middle"><?= htmlspecialchars($ticket['ticket']['title']) ?></td>
        <td class="align-middle">
            <div class="d-flex align-items-center">
                <i class="feather icon-user text-info mr-2"></i>
                <span><?= htmlspecialchars($ticket['ticket']['passenger_name']) ?></span>
            </div>
        </td>
        <td class="align-middle pnr-field font-weight-bold"><?= htmlspecialchars($ticket['ticket']['pnr']) ?></td>
        <td class="align-middle">
            <div class="d-flex align-items-center">
                <i class="feather icon-plane text-success mr-2"></i>
                <span><?= htmlspecialchars($ticket['ticket']['airline']) ?></span>
            </div>
        </td>
        <td class="align-middle">
            <div class="small text-muted">
                <i class="feather icon-calendar mr-1"></i>
                <?= htmlspecialchars($ticket['ticket']['issue_date']) ?>
            </div>
        </td>
        <td class="align-middle">
            <div class="small text-muted">
                <i class="feather icon-calendar mr-1"></i>
                <?= htmlspecialchars($ticket['ticket']['departure_date']) ?>
            </div>
        </td>
        <td class="align-middle">
            <div class="badge badge-light-primary">
                <?= htmlspecialchars($ticket['ticket']['sold']) ?>
            </div>
        </td>
        <td class="align-middle">
            <button class="btn btn-primary btn-sm rounded-pill view-details" 
                    data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                <i class="feather icon-eye"></i> View
            </button>
        </td>
    </tr>

    <?php if ($ticket['refund_data']): ?>
    <tr class="bg-light">
        <td colspan="12">
            <div class="p-2 rounded border-left border-info border-w-4">
                <span class="badge badge-info mr-2">Refund Info</span>
                
                <span class="mr-3">
                    <i class="feather icon-tool text-warning"></i>
                    Charges: <?= htmlspecialchars($ticket['refund_data']['service_penalty']) + htmlspecialchars($ticket['refund_data']['supplier_penalty']) ?>
                </span>
                <span class="mr-3">
                    <i class="feather icon-dollar-sign text-danger"></i>
                    Refund Amount: <?= htmlspecialchars($ticket['refund_data']['refund_to_passenger']) ?>
                </span>
                <span>
                    <i class="feather icon-message-circle text-secondary"></i>
                    <?= htmlspecialchars($ticket['refund_data']['remarks']) ?>
                </span>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ($ticket['date_change_data']): ?>
    <tr class="bg-light">
        <td colspan="12">
            <div class="p-2 rounded border-left border-warning border-w-4">
                <span class="badge badge-warning mr-2">Date Change Info</span>
                <span class="mr-3">
                    <i class="feather icon-calendar text-primary"></i>
                    New Date: <?= htmlspecialchars($ticket['date_change_data']['departure_date']) ?>
                </span>
                <span class="mr-3">
                    <i class="feather icon-dollar-sign text-success"></i>
                    Currency: <?= htmlspecialchars($ticket['date_change_data']['currency']) ?>
                </span>
                <span class="mr-3">
                    <i class="feather icon-tool text-danger"></i>
                    Penalty: <?= htmlspecialchars($ticket['date_change_data']['supplier_penalty'] + $ticket['date_change_data']['service_penalty']) ?>
                </span>
                <span>
                    <i class="feather icon-message-circle text-secondary"></i>
                    <?= htmlspecialchars($ticket['date_change_data']['remarks']) ?>
                </span>
            </div>
        </td>
    </tr>
    <?php endif; ?>
    <?php endforeach; ?>
</tbody>
</table>
                                                                 <!-- Ticket details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="feather icon-ticket mr-2"></i>
                    Ticket Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="ticket-status-badge mb-2">
                                <i class="feather icon-check-circle text-success"></i>
                            </div>
                            <h6 class="mb-1" id="sold-price"></h6>
                            <small class="text-muted" id="currency"></small>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                
                                <div class="col-sm-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="feather icon-user text-info mr-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Sold To</small>
                                            <strong id="sold-to"></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center">
                                        <i class="feather icon-credit-card text-success mr-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Paid To</small>
                                            <strong id="paid-to"></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center">
                                        <i class="feather icon-phone text-warning mr-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Contact</small>
                                            <strong id="phone"></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill border-bottom p-2" id="detailsTab" role="tablist">
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

                <!-- Tabs Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="ticket-info-grid">
                            <div class="info-item">
                                <span class="info-label">Gender</span>
                                <span class="info-value" id="gender"></span>
                            </div>
                            <!-- Add more ticket details as needed -->
                        </div>
                    </div>

                    <!-- Description Tab -->
                    <div class="tab-pane fade" id="details-description" role="tabpanel">
                        <div class="description-container">
                            <p id="description" class="mb-0"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
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
                    <i class="feather icon-user mr-2"></i>Client Profile
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
                             alt="Client Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['client_type']) ? htmlspecialchars($user['client_type']) : 'Client' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Email</label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Phone</label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">USD Balance</label>
                                <p class="mb-0">$<?= number_format($user['usd_balance'], 2) ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">AFS Balance</label>
                                <p class="mb-0">؋<?= number_format($user['afs_balance'], 2) ?></p>
                            </div>
                        </div>
                        <div class="col-sm-12 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Address</label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3">Account Information</h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0">Account Created</p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-sync bg-info"></i>
                                <div class="timeline-content">
                                    <p class="mb-0">Last Updated</p>
                                    <small class="text-muted"><?= !empty($user['updated_at']) ? date('M d, Y H:i A', strtotime($user['updated_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>


.table {
    border-radius: 8px;
    border-collapse: separate;
    border-spacing: 0;
    background: #fff;
}

.table thead th {
    border: none;
    padding: 1rem;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background-color: rgba(64, 153, 255, 0.05);
    transform: translateY(-1px);
}

.table td {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.badge-light-primary {
    background: rgba(64, 153, 255, 0.15);
    color: #4099ff;
    font-weight: 500;
    padding: 5px 10px;
    border-radius: 4px;
}

.border-w-4 {
    border-width: 4px !important;
}

.rounded-pill {
    border-radius: 50rem !important;
}

.btn-sm {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
}

.table-hover tbody tr:hover {
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.shadow-sm {
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075) !important;
}

/* Modal Styles */
.modal-content {
    border-radius: 1rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.modal-header {
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
    padding: 1.5rem;
}

.ticket-status-badge {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.ticket-status-badge i {
    font-size: 24px;
}

.nav-pills .nav-link {
    border-radius: 0.5rem;
    padding: 0.75rem 1.25rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.nav-pills .nav-link.active {
    background-color: #4099ff;
    color: white;
}

.ticket-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.info-item {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.info-label {
    display: block;
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.info-value {
    display: block;
    font-weight: 500;
    color: #2c3e50;
}

.description-container {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 0.5rem;
    min-height: 150px;
}

.btn-light-secondary {
    background: #e9ecef;
    color: #6c757d;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.btn-light-secondary:hover {
    background: #dee2e6;
    color: #495057;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .ticket-info-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
}
</style>





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
                                            $('#sold-to').text(ticketData.ticket.sold_to || 'N/A');
                                            $('#paid-to').text(ticketData.ticket.paid_to || 'N/A');
                                            
                                            // Populate other fields...
                                            $('#sold-price').text(ticketData.ticket.sold || 'N/A');
                                            $('#base-price').text(ticketData.ticket.price || 'N/A');
                                            $('#profit').text(ticketData.ticket.profit || 'N/A');
                                            $('#currency').text(ticketData.ticket.currency || 'N/A');
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