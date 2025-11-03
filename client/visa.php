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

// Fetch Tickets
$visaQuery = "SELECT * FROM visa_applications WHERE sold_to = " . $_SESSION['user_id'];
$visaResult = $conn->query($visaQuery);
$visas = $visaResult->fetch_all(MYSQLI_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers";
$suppliersResult = $conn->query($suppliersQuery);
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);


// Fetch Clients
$clientsQuery = "SELECT id, name FROM clients";
$clientsResult = $conn->query($clientsQuery);
$clients = $clientsResult->fetch_all(MYSQLI_ASSOC);

// Fetch internal
$internalQuery = "SELECT id, name FROM main_account";
$internalResult = $conn->query($internalQuery);
$internal = $internalResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}

// Create an associative array of client id to client name for easy lookup
$client_names = [];
foreach ($clients as $client) {
    $client_names[$client['id']] = $client['name'];
}

// Create an associative array of internal id to internal name for easy lookup
$internal_names = [];
foreach ($internal as $int) {
    $internal_names[$int['id']] = $int['name'];
}

// Now, for each visa, add the supplier's name and other names based on their IDs
foreach ($visas as $key => $visa) {
    $supplier_id = $visa['supplier'];
    $client_id = $visa['sold_to'];
    $internal_id = $visa['paid_to'];
    
    // Add supplier name
    $visas[$key]['supplier_name'] = isset($supplier_names[$supplier_id]) ? $supplier_names[$supplier_id] : 'Unknown';
    
    // Add client name
    $visas[$key]['sold_name'] = isset($client_names[$client_id]) ? $client_names[$client_id] : 'Unknown';
    
    // Add paid_to name
    $visas[$key]['paid_name'] = isset($internal_names[$internal_id]) ? $internal_names[$internal_id] : 'Unknown';
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
                                <div class="col-sm-12">
                                
                                    
                                        <!-- body -->


                                            <!-- Visa Management Section -->
                <div class="container-fluid px-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i>Visa Applications</h5>
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search Visa by Passport number ..." onkeyup="searchVisa()">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover" id="visaTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><i class="feather icon-hash mr-2"></i>#</th>
                                            <th><i class="feather icon-user mr-2"></i>Sold To</th>
                                            <th><i class="feather icon-user mr-2"></i>Passenger</th>
                                            <th><i class="feather icon-book mr-2"></i>Passport</th>
                                            <th><i class="feather icon-map mr-2"></i>Country</th>
                                            <th><i class="feather icon-dollar-sign mr-2"></i>Amount</th>
                                            <th><i class="feather icon-check-circle mr-2"></i>Status</th>
                                            <th><i class="feather icon-settings mr-2"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($visas as $index => $visa): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm bg-light text-primary mr-2">
                                                        <?= strtoupper(substr($visa['supplier_name'], 0, 1)) ?>
                                                    </span>
                                                    <?= htmlspecialchars($visa['supplier_name']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($visa['sold_name']) ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bold"><?= htmlspecialchars($visa['title'] . ' ' . $visa['applicant_name']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($visa['gender']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-light">
                                                    <?= htmlspecialchars($visa['passport_number']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?= htmlspecialchars($visa['country']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bold text-primary">
                                                        <?= htmlspecialchars($visa['currency'] . ' ' . number_format($visa['sold'], 2)) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= getStatusBadgeClass($visa['status']) ?>">
                                                    <?= htmlspecialchars($visa['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                        <i class="feather icon-more-horizontal"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item view-details" href="javascript:void(0)" 
                                                        data-visa='<?= htmlspecialchars(json_encode($visa)) ?>'>
                                                            <i class="feather icon-eye text-info mr-2"></i>View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                                <!-- Visa Details Modal -->
                                <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-file-text mr-2"></i>Visa Details
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="nav nav-pills nav-fill mb-3" id="detailsTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary">
                                                            <i class="feather icon-info mr-1"></i>Summary
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description">
                                                            <i class="feather icon-file-text mr-1"></i>Description
                                                        </a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content p-3 border rounded">
                                                    <div class="tab-pane fade show active" id="details-summary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card border-primary mb-3">
                                                                    <div class="card-header bg-primary text-white">
                                                                        <i class="feather icon-user mr-1"></i>Personal Details
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong>Paid To:</strong> <span id="paid-to"></span></p>
                                                                        <p class="mb-2"><strong>Country:</strong> <span id="country"></span></p>
                                                                        <p class="mb-2"><strong>Visa Type:</strong> <span id="visa-type"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-success mb-3">
                                                                    <div class="card-header bg-success text-white">
                                                                        <i class="feather icon-dollar-sign mr-1"></i>Financial Details
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong>Currency:</strong> <span id="currency"></span></p>
                                                                        <p class="mb-2"><strong>Sold Price:</strong> <span id="sold-price"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border-info">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="feather icon-calendar mr-1"></i>Dates
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Receive Date:</strong> <span id="receive-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Applied Date:</strong> <span id="applied-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Issued Date:</strong> <span id="issued-date"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="tab-pane fade" id="details-description">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <p id="description" class="mb-0"></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-1"></i>Close
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <?php
                                function getStatusBadgeClass($status) {
                                    switch (strtolower($status)) {
                                        case 'approved':
                                            return 'success';
                                        case 'pending':
                                            return 'warning';
                                        case 'rejected':
                                            return 'danger';
                                        default:
                                            return 'secondary';
                                    }
                                }
                                ?>
                                        
                                    </div>
                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
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
    <!-- update profile -->

    <script>
                                    
                                    document.querySelectorAll('.view-details').forEach(button => {
                                   button.addEventListener('click', function () {
                                       const visa = JSON.parse(this.getAttribute('data-visa'));
                                       
                                       // Update modal fields
                                       document.getElementById('country').textContent = visa.country;
                                       document.getElementById('paid-to').textContent = visa.paid_name || 'Not specified';
                                       document.getElementById('visa-type').textContent = visa.visa_type;
                                       document.getElementById('receive-date').textContent = visa.receive_date;
                                       document.getElementById('applied-date').textContent = visa.applied_date;
                                       document.getElementById('issued-date').textContent = visa.issued_date;
                                       document.getElementById('sold-price').textContent = visa.sold;
                                       document.getElementById('currency').textContent = visa.currency;

                                       document.getElementById('description').textContent = visa.description;
                                       $('#detailsModal').data('visa-id', visa.id);

                                       // Show the modal
                                       $('#detailsModal').modal('show');
                                   });
                               });

                                     function deleteVisa(id) {
                                           if (confirm('Are you sure you want to delete this Visa?')) {
                                               fetch('delete_visa.php', {
                                                   method: 'POST',
                                                   headers: { 'Content-Type': 'application/json' },
                                                   body: JSON.stringify({ id }),
                                               })
                                               .then(response => response.json())
                                               .then(data => {
                                                   if (data.success) {
                                                       alert('Visa deleted successfully!');
                                                       location.reload(); // Refresh table
                                                   } else {
                                                       alert('Error: ' + data.message);
                                                   }
                                               })
                                               .catch(error => console.error('Error deleting Visa:', error));
                                           }
                                       }
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
                                                const currencyInput = document.getElementById('curr');
                                                if (data.currency) {
                                                    currencyInput.value = data.currency;

                                                    console.log('Currency input updated to:', data.currency);
                                                } else {
                                                    currencyInput.value = '';
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


                              
</body>
</html>

<!-- Add this script section after your table -->
<script>
function searchVisa() {
    // Get input value and convert to lowercase for case-insensitive search
    let input = document.getElementById('searchInput').value.toLowerCase();
    let table = document.querySelector('.table');
    let rows = table.getElementsByTagName('tr');

    // Loop through all table rows, starting from index 1 to skip header
    for (let i = 1; i < rows.length; i++) {
        let row = rows[i];
        let passportCell = row.getElementsByTagName('td')[6]; // Index 6 is the passport number column
        
        if (passportCell) {
            let passportNumber = passportCell.textContent || passportCell.innerText;
            
            // Show/hide row based on whether passport number contains the search input
            if (passportNumber.toLowerCase().indexOf(input) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
}

// Add event listener for real-time search
document.getElementById('searchInput').addEventListener('input', function() {
    searchVisa();
});
</script>