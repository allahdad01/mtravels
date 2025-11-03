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
                                        <h5 class="m-b-10">Umrah Management</h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:">Umrah</a></li>
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
                                    <div class="d-flex justify-content-between align-items-center mb-3">
    <!-- Search Input (Left Side) -->
    <div class="search-filter">
        <input type="text" id="searchInput" class="form-control" placeholder="Search Family by Name..." onkeyup="searchFamily()">
    </div>
</div>
                    
                                        <!-- body -->
                                        <?php
                      

                        // Fetch all family data where any member was sold to the current user
                        $sqlFamilies = "SELECT DISTINCT f.* FROM families f 
                                        INNER JOIN umrah_bookings u ON f.family_id = u.family_id 
                                        WHERE u.sold_to = " . $_SESSION['user_id'];
                        $resultFamilies = $conn->query($sqlFamilies);

                        // Fetch families again for dropdown use
                        $resultFamiliesForDropdown = $conn->query($sqlFamilies);
                        ?>

                        <!-- Display Families and Bookings -->
                         <div class="container-fluid px-4">
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="feather icon-users mr-2"></i>Family List</h5>
                                   
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="familyTable">
                                            <thead class="thead-light">
                        <tr>
                            <th><i class="feather icon-user mr-2"></i>Family Head</th>
                            <th><i class="feather icon-phone mr-2"></i>Contact</th>
                            <th><i class="feather icon-map-pin mr-2"></i>Address</th>
                            <th><i class="feather icon-package mr-2"></i>Package</th>
                            <th><i class="feather icon-map mr-2"></i>Location</th>
                            <th><i class="feather icon-users mr-2"></i>Members</th>
                            <th><i class="feather icon-shield mr-2"></i>Tazmin</th>
                            <th><i class="feather icon-check-circle mr-2"></i>Visa Status</th>
                            <th><i class="feather icon-dollar-sign mr-2"></i>Price</th>
                            <th><i class="feather icon-check mr-2"></i>Paid</th>
                            <th><i class="feather icon-credit-card mr-2"></i>Bank</th>
                            <th><i class="feather icon-alert-circle mr-2"></i>Due</th>
                            <th><i class="feather icon-settings mr-2"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultFamilies->num_rows > 0) {
                            while ($row = $resultFamilies->fetch_assoc()) {
                                $familyId = $row['family_id']; ?>
                                <tr>
                                    <td class="font-weight-bold"><?= htmlspecialchars($row['head_of_family']) ?></td>
                                    <td><?= htmlspecialchars($row['contact']) ?></td>
                                    <td><?= htmlspecialchars($row['address']) ?></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($row['package_type']) ?></span></td>
                                    <td><?= htmlspecialchars($row['location']) ?></td>
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($row['total_members']) ?></span></td>
                                    <td><?= htmlspecialchars($row['tazmin']) ?></td>
                                    <td><span class="badge badge-<?= $row['visa_status'] == 'Approved' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($row['visa_status']) ?>
                                    </span></td>
                                    <td class="font-weight-bold"><?= htmlspecialchars($row['total_price']) ?></td>
                                    <td class="text-success"><?= htmlspecialchars($row['total_paid']) ?></td>
                                    <td class="text-primary"><?= htmlspecialchars($row['total_paid_to_bank']) ?></td>
                                    <td class="text-danger"><?= htmlspecialchars($row['total_due']) ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="actionMenu<?= $familyId ?>" data-toggle="dropdown">
                                                <i class="feather icon-more-horizontal"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                            
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="toggleMembers(<?= $familyId ?>)">
                                                    <i class="feather icon-list text-info mr-2"></i>View Members
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Members Details Row -->
                                <tr id="family-members-<?= $familyId ?>" style="display: none;">
                                    <td colspan="13" class="p-0">
                                        <div class="card m-2 border-primary">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0"><i class="feather icon-users mr-2"></i>Family Members</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Entry Date</th>
                                                                <th>Name</th>
                                                                <th>DOB</th>
                                                                <th>Passport</th>
                                                                <th>ID Type</th>
                                                                <th>Flight Date</th>
                                                                <th>Return Date</th>
                                                                <th>Room</th>
                                                                <th>Duration</th>
                                                                <th>Base</th>
                                                                <th>Sold</th>
                                                                <th>Paid</th>
                                                                <th>Bank</th>
                                                                <th>Receipt</th>
                                                                <th>Due</th>
                                                                <th>Profit</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $sqlMembers = "SELECT * FROM umrah_bookings WHERE family_id = $familyId";
                                                            $resultMembers = $conn->query($sqlMembers);
                                                            if ($resultMembers->num_rows > 0) {
                                                                while ($member = $resultMembers->fetch_assoc()) { ?>
                                                                    <tr>
                                                                        <td><?= htmlspecialchars($member['entry_date']) ?></td>
                                                                        <td><?= htmlspecialchars($member['name']) ?></td>
                                                                        <td><?= htmlspecialchars($member['dob']) ?></td>
                                                                        <td><?= htmlspecialchars($member['passport_number']) ?></td>
                                                                        <td><span class="badge badge-info"><?= htmlspecialchars($member['id_type']) ?></span></td>
                                                                        <td><?= htmlspecialchars($member['flight_date']) ?></td>
                                                                        <td><?= htmlspecialchars($member['return_date']) ?></td>
                                                                        <td><?= htmlspecialchars($member['room_type']) ?></td>
                                                                        <td><?= htmlspecialchars($member['duration']) ?></td>
                                                                        <td><?= htmlspecialchars($member['sold_price']) ?></td>
                                                                        <td class="text-success"><?= htmlspecialchars($member['paid']) ?></td>
                                                                        <td><?= htmlspecialchars($member['received_bank_payment']) ?></td>
                                                                        <td><?= htmlspecialchars($member['bank_receipt_number']) ?></td>
                                                                        <td class="text-danger"><?= htmlspecialchars($member['due']) ?></td>
                                                                        
                                                                    </tr>
                                                                <?php }
                                                            } else { ?>
                                                                <tr>
                                                                    <td colspan="16" class="text-center text-muted">No members found</td>
                                                                </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else { ?>
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    <i class="feather icon-users h1"></i>
                                    <p class="mb-0">No families available</p>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>







                                        <!-- end of body -->
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


                                 
                        <script>
// Modal Functionality
document.getElementById("createFamilyBtn").addEventListener("click", function() {
    document.getElementById("createFamilyModal").style.display = "block";
});

document.getElementById("closeCreateFamilyModal").addEventListener("click", function() {
    document.getElementById("createFamilyModal").style.display = "none";
});



// AJAX Form Submission (Create Family)
function submitCreateFamilyForm() {
    var formData = new FormData(document.getElementById("createFamilyForm"));
    
    fetch('create_family.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if(data.success) {
              alert("Family created successfully!");
              location.reload();
          } else {
              alert("Error creating family.");
          }
      });
    return false;
}

// Search functionality
function searchFamily() {
    var input = document.getElementById("searchInput");
    var filter = input.value.toLowerCase();
    var table = document.getElementById("familyTable");
    var rows = table.getElementsByTagName("tr");

    for (var i = 1; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName("td");
        var familyHead = cells[0].textContent || cells[0].innerText;
        if (familyHead.toLowerCase().indexOf(filter) > -1) {
            rows[i].style.display = "";
        } else {
            rows[i].style.display = "none";
        }
    }
}

// Export to Excel
document.getElementById("exportBtn").addEventListener("click", function() {
    var table = document.getElementById("familyTable");
    var rows = table.rows;
    var csv = [];

    for (var i = 0; i < rows.length; i++) {
        var cols = rows[i].cells;
        var row = [];
        for (var j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        csv.push(row.join(","));
    }

    var csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "family_bookings.csv");
    link.click();
});
</script>
<!-- JavaScript -->
<script>
function openBookingModal(familyId) {
    document.getElementById("familyId").value = familyId;
    $('#umrahModal').modal('show'); // Bootstrap modal fix
}

// Calculate Profit and Due on Input Change
document.getElementById("sold_price").addEventListener("input", calculateFinancials);
document.getElementById("price").addEventListener("input", calculateFinancials);
document.getElementById("paid").addEventListener("input", calculateFinancials);

function calculateFinancials() {
    let price = parseFloat(document.getElementById("price").value) || 0;
    let soldPrice = parseFloat(document.getElementById("sold_price").value) || 0;
    let paid = parseFloat(document.getElementById("paid").value) || 0;

    let profit = soldPrice - price;
    let due = soldPrice - paid;

    document.getElementById("profit").value = profit.toFixed(2);
    document.getElementById("due").value = due.toFixed(2);
}
</script>
<script>
function toggleMembers(familyId) {
    var row = document.getElementById("family-members-" + familyId);
    if (row.style.display === "none") {
        row.style.display = "table-row"; // Show members
    } else {
        row.style.display = "none"; // Hide members
    }
}
</script>


</body>
</html>
