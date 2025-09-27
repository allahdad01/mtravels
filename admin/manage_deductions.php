<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../access_denied.php");
    exit;
}

// Include config file
require_once "../includes/db.php";

// Define variables and initialize with empty values
$user_id = $amount = $description = $deduction_date = $type = "";
$user_id_err = $amount_err = $description_err = $deduction_date_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate user ID
    if (empty($_POST["user_id"])) {
        $user_id_err = "Please select an employee.";
    } else {
        $user_id = $_POST["user_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the deduction amount.";
    } else {
        $amount = $_POST["amount"];
        // Check if amount is a positive number
        if (!is_numeric($amount) || $amount <= 0) {
            $amount_err = "Please enter a positive number for the deduction amount.";
        }
    }
    
    // Validate description
    if (empty($_POST["description"])) {
        $description_err = "Please enter a description.";
    } else {
        $description = $_POST["description"];
    }
    
    // Validate deduction date
    if (empty($_POST["deduction_date"])) {
        $deduction_date_err = "Please enter the deduction date.";
    } else {
        $deduction_date = $_POST["deduction_date"];
    }
    
    // Set deduction type
    $type = $_POST["type"];
    
    // Check input errors before inserting in database
    if (empty($user_id_err) && empty($amount_err) && empty($description_err) && empty($deduction_date_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO salary_deductions (tenant_id, user_id, amount, description, deduction_date, type, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conection_db, $sql)) {
            // Get current user ID as created_by
            $created_by = $_SESSION["user_id"];
            
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "iidsssi", $tenant_id, $user_id, $amount, $description, $deduction_date, $type, $created_by);
            
            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Records created successfully. Redirect to landing page
                header("location: manage_deductions.php?success=1");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conection_db);
}

?>

    <!-- [ Header ] start -->
    <?php include("../includes/header.php"); ?>
    <link rel="stylesheet" href="css/modal-styles.css">
    <!-- [ Header ] end -->
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
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10"><?= __('manage_employee_deductions') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php"><?= __('salary_management') ?></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('manage_deductions') ?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->
            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- [ form-element ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('add_new_deduction') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('deduction_has_been_added_successfully') ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('deduction_has_been_updated_successfully') ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('deduction_has_been_deleted_successfully') ?>
                            </div>
                            <?php endif; ?>

                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id"><?= __('employee') ?></label>
                                            <select class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" id="user_id" name="user_id">
                                                <option value=""><?= __('select_employee') ?></option>
                                                <?php
                                                // Get all active users with salary records
                                                $sql = "SELECT u.id, u.name 
                                                        FROM users u 
                                                        JOIN salary_management sm ON u.id = sm.user_id 
                                                        WHERE sm.status = 'active'
                                                        ORDER BY u.name ASC";
                                                $result = mysqli_query($conection_db, $sql);
                                                while ($row = mysqli_fetch_array($result)) {
                                                    echo "<option value='" . $row['id'] . "'>" . $row['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $user_id_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="amount"><?= __('deduction_amount') ?></label>
                                            <input type="number" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" step="0.01" min="0" value="<?php echo $amount; ?>">
                                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="type"><?= __('deduction_type') ?></label>
                                            <select class="form-control" id="type" name="type">
                                                <option value="absence"><?= __('absence') ?></option>
                                                <option value="penalty"><?= __('penalty') ?></option>
                                                <option value="tax"><?= __('tax') ?></option>
                                                <option value="other"><?= __('other') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="deduction_date"><?= __('deduction_date') ?></label>
                                            <input type="date" class="form-control <?php echo (!empty($deduction_date_err)) ? 'is-invalid' : ''; ?>" id="deduction_date" name="deduction_date" value="<?php echo empty($deduction_date) ? date('Y-m-d') : $deduction_date; ?>">
                                            <div class="invalid-feedback"><?php echo $deduction_date_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description"><?= __('description') ?></label>
                                            <textarea class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                                            <div class="invalid-feedback"><?php echo $description_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary"><?= __('add_deduction') ?></button>
                                        <a href="salary_management.php" class="btn btn-secondary"><?= __('cancel') ?></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->
                
                <!-- [ Deduction Records ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('deduction_records') ?></h5>
                            <div class="float-right">
                                <a href="manage_bonuses.php" class="btn btn-success">
                                    <i class="feather icon-plus-circle mr-1"></i><?= __('manage_bonuses') ?>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Month filter -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="month-filter"><?= __('filter_by_month') ?></label>
                                        <select class="form-control" id="month-filter">
                                            <option value="all"><?= __('all_records') ?></option>
                                            <?php
                                            // Generate last 12 months options
                                            for ($i = 0; $i < 12; $i++) {
                                                $monthValue = date('Y-m', strtotime("-$i months"));
                                                $monthLabel = date('F Y', strtotime("-$i months"));
                                                $selected = ($i === 0) ? 'selected' : ''; // Select current month by default
                                                echo "<option value=\"$monthValue\" $selected>$monthLabel</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" id="reset-filter" class="btn btn-outline-secondary">
                                        <i class="feather icon-refresh-cw"></i> <?= __('reset') ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="dt-responsive table-responsive">
                                <table id="deduction-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th><?= __('id') ?></th>
                                            <th><?= __('employee') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('type') ?></th>
                                            <th><?= __('description') ?></th>
                                            <th><?= __('deduction_date') ?></th>
                                            <th><?= __('added_by') ?></th>
                                            <th><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all deduction records
                                        $sql = "SELECT sd.*, u.name as employee_name, a.name as added_by_name 
                                                FROM salary_deductions sd 
                                                JOIN users u ON sd.user_id = u.id 
                                                JOIN users a ON sd.created_by = a.id 
                                                ORDER BY sd.deduction_date DESC";
                                        $result = mysqli_query($conection_db, $sql);
                                        while ($row = mysqli_fetch_array($result)) {
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . $row['employee_name'] . "</td>";
                                            echo "<td>" . number_format($row['amount'], 2) . "</td>";
                                            echo "<td>" . ucfirst($row['type']) . "</td>";
                                            echo "<td>" . $row['description'] . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['deduction_date'])) . "</td>";
                                            echo "<td>" . $row['added_by_name'] . "</td>";
                                            echo "<td>";
                                            echo "<a href='edit_deduction.php?id=" . $row['id'] . "' class='btn btn-info btn-sm mr-1'><i class='feather icon-edit'></i></a>";
                                            echo "<a href='delete_deduction.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"" . __('are_you_sure_you_want_to_delete_this_deduction_record') . "\");'><i class='feather icon-trash-2'></i></a>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ Deduction Records ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            try {
                $('#deduction-list-table').DataTable();
            } catch(e) {
                console.error("DataTable error:", e);
            }
            
            // Simple jQuery filtering (no DataTables dependency)
            var $rows = $('#deduction-list-table tbody tr');
            
            // Filter table when month changes
            $('#month-filter').on('change', function() {
                var selectedMonth = $(this).val();
                
                if (selectedMonth === 'all') {
                    // Show all rows
                    $rows.show();
                } else {
                    // Hide all rows first
                    $rows.hide();
                    
                    // Show only rows that match the selected month
                    $rows.each(function() {
                        var dateCell = $(this).find('td:eq(5)').text().trim(); // 6th column (index 5) contains the date
                        if (dateCell && dateCell.length >= 7) {
                            var rowYearMonth = dateCell.substring(0, 7); // Extract YYYY-MM
                            if (rowYearMonth === selectedMonth) {
                                $(this).show();
                            }
                        }
                    });
                }
            });
            
            // Reset filter button
            $('#reset-filter').on('click', function() {
                $('#month-filter').val('all');
                $rows.show();
            });
            
            // Apply initial filter if not "all"
            var initialMonth = $('#month-filter').val();
            if (initialMonth !== 'all') {
                $('#month-filter').trigger('change');
            }
        });
    </script>
</body>
</html> 