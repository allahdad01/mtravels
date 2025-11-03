<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
       rt.*,
       rt.supplier_penalty AS refund_supplier_penalty,
       rt.service_penalty AS refund_service_penalty,
       rt.refund_to_passenger,
       rt.status AS refund_status,
       rt.remarks AS refund_remarks,
       
       s.name AS supplier_name,
       c.name AS sold_to_name,
       ma.name AS paid_to_name
   FROM 
       refunded_tickets rt
   LEFT JOIN 
       suppliers s ON rt.supplier = s.id
   LEFT JOIN 
       clients c ON rt.sold_to = c.id
   LEFT JOIN 
       main_account ma ON rt.paid_to = ma.id
   WHERE rt.sold_to = " . $_SESSION['user_id'] . "
   ORDER BY 
       rt.id ASC
";

$ticketsResult = $conn->query($ticketsQuery);

// Initialize the array to hold ticket details
$tickets = [];

if ($ticketsResult && $ticketsResult->num_rows > 0) {
    // Fetch results and push them into the array
    while ($row = $ticketsResult->fetch_assoc()) {
        $tickets[] = $row;
    }
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
                    

                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- [ Statistics ] start -->
                                <div class="col-md-12 col-xl-4">
                                    <div class="card">
                                        <div class="card-block">
                                            <h6 class="text-muted mb-3">Total Refunds</h6>
                                            <div class="row d-flex align-items-center">
                                                <div class="col-9">
                                                    <h3 class="f-w-300 d-flex align-items-center m-b-0">
                                                        <i class="feather icon-refresh-ccw text-c-blue f-30 m-r-10"></i>
                                                        <?= count($tickets) ?>
                                                    </h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- [ Statistics ] end -->

                                <!-- [ Ticket Table ] start -->
                                <div class="col-xl-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row align-items-center">
                                                <div class="col-auto">
                                                    <h5>Refunded Tickets Overview</h5>
                                                </div>
                                                <div class="col">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="feather icon-search"></i></span>
                                                        </div>
                                                        <input type="text" id="ticketSearch" class="form-control" placeholder="Search tickets...">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-block px-0 py-3">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">#</th>
                                                            <th>Passenger Details</th>
                                                            <th>Flight Info</th>
                                                            <th>Financial Details</th>
                                                            <th>Charges</th>
                                                            <th>Refund Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tickets as $index => $ticket): ?>
                                                        <tr>
                                                            <td class="text-center"><?= $index + 1 ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="avatar bg-light-primary">
                                                                        <span><?= strtoupper(substr($ticket['passenger_name'], 0, 1)) ?></span>
                                                                    </div>
                                                                    <div class="ml-3">
                                                                        <h6 class="mb-0"><?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?></h6>
                                                                        <small class="text-muted">
                                                                            PNR: <?= htmlspecialchars($ticket['pnr']) ?> | 
                                                                            Phone: <?= htmlspecialchars($ticket['phone']) ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($ticket['airline']) ?></h6>
                                                                    <small class="text-muted">
                                                                        <?= htmlspecialchars($ticket['origin']) ?> - <?= htmlspecialchars($ticket['destination']) ?>
                                                                    </small>
                                                                    <small class="text-primary">
                                                                        <?= date('d M Y', strtotime($ticket['departure_date'])) ?>
                                                                    </small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <div class="mb-1">
                                                                        <span class="text-muted">Sold:</span>
                                                                        <span class="font-weight-bold">
                                                                            <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['sold'], 2) ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex flex-column">
                                                                    <div class="mb-1">
                                                                        <h6 class="text-danger mb-0">
                                                                            <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?>
                                                                            
                                                        </h6>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <h6 class="text-success mb-0">
                                                                    <?= htmlspecialchars($ticket['currency']) ?> 
                                                                    <?= number_format($ticket['refund_to_passenger'], 2) ?>
                                                                </h6>
                                                            </td>
                    
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- [ Ticket Table ] end -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

  
                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>

                                <script>
// Search functionality
$(document).ready(function() {
    $("#ticketSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>

</body>
</html>