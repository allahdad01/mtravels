<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once '../includes/conn.php';

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
// Query to fetch ticket weights with related information
$weightsQuery = "
    SELECT 
        tw.*,
        t.passenger_name,
        t.pnr,
        t.phone,
        t.airline,
        t.origin,
        t.destination,
        t.departure_date,
        t.currency,
        s.name AS supplier_name,
        c.name AS sold_to_name
    FROM 
        ticket_weights tw
    LEFT JOIN 
        ticket_bookings t ON tw.ticket_id = t.id
    LEFT JOIN 
        suppliers s ON t.supplier = s.id
    LEFT JOIN 
        clients c ON t.sold_to = c.id
    WHERE
        tw.tenant_id = $tenant_id AND t.sold_to = $user_id
    ORDER BY 
        tw.created_at DESC
    ";

    $weightsResult = $conn->query($weightsQuery);
    $weights = $weightsResult->fetch_all(MYSQLI_ASSOC);

?>


    <?php include '../includes/header_client.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/ticket_styles.css">
    <link rel="stylesheet" href="css/ticket-components.css">
    <link rel="stylesheet" href="css/modal-styles.css">
    <link rel="stylesheet" href="css/ticket-form.css">
    <link rel="stylesheet" href="css/ticket_weight.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- [ Table ] start -->
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <h5><?= __('ticket_weights_management') ?></h5>
                                                </div>
                                                <div class="col text-right">
                                                     <button type="button" class="btn btn-success" id="generateInvoiceBtn" style="display: none;">
                                                         <i class="feather icon-file-text mr-2"></i>Generate Invoice
                                                     </button>
                                                     <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTransactionModal">
                                                         <i class="feather icon-plus mr-2"></i><?= __('add_weight') ?>
                                                     </button>
                                                 </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0" id="weightsTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">
                                                                <input type="checkbox" id="selectAllWeights">
                                                            </th>
                                                            <th><?= __('passenger') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('weight_details') ?></th>
                                                            <th><?= __('financial_details') ?></th>
                                                            <th><?= __('date_added') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ticketTable">
                                                        <?php foreach ($weights as $weight): ?>
                                                        <tr>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="weight-checkbox" value="<?= $weight['id'] ?>">
                                                            </td>
                                                            <td>
                                                                <div class="passenger-info">
                                                                    
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                         <?= htmlspecialchars($weight['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($weight['pnr']) ?>
                                                                            <br>
                                                                            <?= __('phone') ?>: <?= htmlspecialchars($weight['phone']) ?>
                                                                            <br>
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="flight-info">
                                                                    <div class="flight-info__segment">
                                                                        <div class="flight-info__city">
                                                                            <?= htmlspecialchars($weight['origin']) ?> - <?= htmlspecialchars($weight['destination']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="weight-info">
                                                                    <div class="weight-info__weight">
                                                                        <?= number_format($weight['weight'], 2) ?> kg
                                                                    </div>
                                                                    <?php if (!empty($weight['remarks'])): ?>
                                                                    <div class="weight-info__remarks">
                                                                        <?= htmlspecialchars($weight['remarks']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($weight['exchange_rate']) || !empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__exchange-rate">
                                                                        <?= __('rate') ?>: <?= number_format($weight['exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php if (!empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__market-exchange-rate">
                                                                        <?= __('market') ?>: <?= number_format($weight['market_exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['sold_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__base-price">
                                                                        <?= __('base') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['base_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__profit">
                                                                        <?= __('profit') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['profit'], 2) ?>
                                                                    </div>
                                                                    
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?= date('d M Y H:i', strtotime($weight['created_at'])) ?>
                                                                <br>
                                                                <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                            </td>

                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- [ Table ] end -->
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

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    

     
</body>
</html>
