<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../access_denied.php");
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include config file
require_once "../includes/db.php";

// Define variables and initialize with empty values
$adjustment_type = $amount = $percentage = $effective_date = $reason = "";
$amount_err = $percentage_err = $effective_date_err = $reason_err = "";

// Check if user_id is passed in the URL
if (isset($_GET["adjustment_user_id"]) && !empty(trim($_GET["adjustment_user_id"]))) {
    $adjustment_user_id = trim($_GET["adjustment_user_id"]);
    
    // Get user information
    $sql = "SELECT u.name, sm.base_salary, sm.currency
            FROM users u
            JOIN salary_management sm ON u.id = sm.user_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $employee_name = $result["name"];
            $current_salary = $result["base_salary"];
            $currency = $result["currency"];
        } else {
            // URL doesn't contain valid id parameter
            header("location: salary_management.php");
            exit();
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
} else {
    // URL doesn't contain id parameter
    header("location: salary_management.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate adjustment type
    $adjustment_type = $_POST["adjustment_type"];
    
    // Validate amount
    if (empty($_POST["amount"]) && empty($_POST["percentage"])) {
        $amount_err = "Please enter either an amount or percentage.";
        $percentage_err = "Please enter either an amount or percentage.";
    } else {
        if (!empty($_POST["amount"])) {
            $amount = $_POST["amount"];
        }
        
        if (!empty($_POST["percentage"])) {
            $percentage = $_POST["percentage"];
        }
    }
    
    // Validate effective date
    if (empty($_POST["effective_date"])) {
        $effective_date_err = "Please enter the effective date.";
    } else {
        $effective_date = $_POST["effective_date"];
    }
    
    // Validate reason
    if (empty($_POST["reason"])) {
        $reason_err = "Please enter the reason for adjustment.";
    } else {
        $reason = $_POST["reason"];
    }
    
    // Calculate new salary
    if (!empty($amount)) {
        if ($adjustment_type == "increment") {
            $new_salary = $current_salary + $amount;
        } else {
            $new_salary = $current_salary - $amount;
        }
    } else if (!empty($percentage)) {
        $adjustment_value = $current_salary * ($percentage / 100);
        if ($adjustment_type == "increment") {
            $new_salary = $current_salary + $adjustment_value;
        } else {
            $new_salary = $current_salary - $adjustment_value;
        }
    }
    
    // Ensure new salary is not negative
    if (isset($new_salary) && $new_salary < 0) {
        $amount_err = "The adjustment would result in a negative salary.";
        $percentage_err = "The adjustment would result in a negative salary.";
    }
    
    // Check input errors before inserting in database
    if (empty($amount_err) && empty($percentage_err) && empty($effective_date_err) && empty($reason_err)) {
        // Start transaction
        $pdo->beginTransaction();

        try {
            // First, insert into salary_adjustments table
            $sql = "INSERT INTO salary_adjustments (user_id, adjustment_type, amount, percentage, effective_date,
                   previous_salary, new_salary, reason, approved_by, tenant_id, branch_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            // Get approved_by (current user ID)
            $approved_by = $_SESSION["user_id"];

            // Bind variables to the statement
            $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $adjustment_type, PDO::PARAM_STR);
            $stmt->bindParam(3, $amount, PDO::PARAM_STR);
            $stmt->bindParam(4, $percentage, PDO::PARAM_STR);
            $stmt->bindParam(5, $effective_date, PDO::PARAM_STR);
            $stmt->bindParam(6, $current_salary, PDO::PARAM_STR);
            $stmt->bindParam(7, $new_salary, PDO::PARAM_STR);
            $stmt->bindParam(8, $reason, PDO::PARAM_STR);
            $stmt->bindParam(9, $approved_by, PDO::PARAM_INT);
            $stmt->bindParam(10, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(11, $branch_id, PDO::PARAM_INT);

            // Execute the statement
            $stmt->execute();

            // Update the base salary in salary_management table
            $update_sql = "UPDATE salary_management SET base_salary = ? WHERE user_id = ? AND tenant_id = ? AND branch_id = ?";

            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(1, $new_salary, PDO::PARAM_STR);
            $update_stmt->bindParam(2, $adjustment_user_id, PDO::PARAM_INT);
            $update_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $update_stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $update_stmt->execute();

            // Commit transaction
            $pdo->commit();

            // Redirect to success page
            header("location: salary_adjustment.php?adjustment_user_id=$adjustment_user_id&success=1");
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
            $pdo->rollBack();
            echo "Error: " . $e->getMessage();
        }
    }

    // PDO connection will be closed automatically when script ends
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
                                <h5 class="m-b-10"><?= __('salary_adjustment') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php"><?= __('salary_management') ?></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('salary_adjustment') ?></a></li>
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
                            <h5><?= __('salary_adjustment_for') ?> <?php echo $employee_name; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('salary_adjustment_processed_successfully') ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6><?= __('employee') ?>: <strong><?php echo $employee_name; ?></strong></h6>
                                </div>
                                <div class="col-md-6">
                                    <h6><?= __('current_salary') ?>: <strong><?php echo number_format($current_salary, 2) . " " . $currency; ?></strong></h6>
                                </div>
                            </div>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?adjustment_user_id=" . $adjustment_user_id; ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="adjustment_type"><?= __('adjustment_type') ?></label>
                                            <select class="form-control" id="adjustment_type" name="adjustment_type">
                                                <option value="increment" <?php echo ($adjustment_type == "increment") ? "selected" : ""; ?>><?= __('increment') ?></option>
                                                <option value="decrement" <?php echo ($adjustment_type == "decrement") ? "selected" : ""; ?>><?= __('decrement') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="effective_date"><?= __('effective_date') ?></label>
                                            <input type="date" class="form-control <?php echo (!empty($effective_date_err)) ? 'is-invalid' : ''; ?>" id="effective_date" name="effective_date" value="<?php echo $effective_date; ?>">
                                            <div class="invalid-feedback"><?php echo $effective_date_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="amount"><?= __('amount') ?> (<?= __('fixed') ?>)</label>
                                            <input type="number" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" step="0.01" value="<?php echo $amount; ?>">
                                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                                            <small class="form-text text-muted">Enter either a fixed amount or percentage.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="percentage"><?= __('percentage') ?> (<?= __('percentage') ?>)</label>
                                            <input type="number" class="form-control <?php echo (!empty($percentage_err)) ? 'is-invalid' : ''; ?>" id="percentage" name="percentage" step="0.01" value="<?php echo $percentage; ?>">
                                            <div class="invalid-feedback"><?php echo $percentage_err; ?></div>
                                            <small class="form-text text-muted">Enter either a fixed amount or percentage.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="reason"><?= __('reason_for_adjustment') ?></label>
                                            <textarea class="form-control <?php echo (!empty($reason_err)) ? 'is-invalid' : ''; ?>" id="reason" name="reason" rows="3"><?php echo $reason; ?></textarea>
                                            <div class="invalid-feedback"><?php echo $reason_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label><?= __('new_salary_preview') ?>:</label>
                                            <h4 id="salary-preview"><?php echo number_format($current_salary, 2) . " " . $currency; ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary"><?= __('process_adjustment') ?></button>
                                        <a href="salary_management.php" class="btn btn-secondary"><?= __('back_to_salary_management') ?></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->

                <!-- [ Adjustment History ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('salary_adjustment_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <table id="adjustment-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th><?= __('id') ?></th>
                                            <th><?= __('type') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('percentage') ?></th>
                                            <th><?= __('previous_salary') ?></th>
                                            <th><?= __('new_salary') ?></th>
                                            <th><?= __('effective_date') ?></th>
                                            <th><?= __('reason') ?></th>
                                            <th><?= __('approved_by') ?></th>
                                            <th><?= __('date') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get adjustment history for this user
                                        $sql = "SELECT sa.*, u.name as approved_by_name
                                                FROM salary_adjustments sa
                                                JOIN users u ON sa.approved_by = u.id
                                                WHERE sa.user_id = ? AND sa.tenant_id = ? AND sa.branch_id = ?
                                                ORDER BY sa.created_at DESC";

                                        $stmt = $pdo->prepare($sql);
                                        $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
                                        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($result as $row) {
                                                    echo "<tr>";
                                                    echo "<td>" . $row['id'] . "</td>";
                                                    echo "<td>" . ucfirst($row['adjustment_type']) . "</td>";
                                                    echo "<td>" . ($row['amount'] ? number_format($row['amount'], 2) : "-") . "</td>";
                                                    echo "<td>" . ($row['percentage'] ? $row['percentage'] . "%" : "-") . "</td>";
                                                    echo "<td>" . number_format($row['previous_salary'], 2) . "</td>";
                                                    echo "<td>" . number_format($row['new_salary'], 2) . "</td>";
                                                    echo "<td>" . date('Y-m-d', strtotime($row['effective_date'])) . "</td>";
                                                    echo "<td>" . $row['reason'] . "</td>";
                                                    echo "<td>" . $row['approved_by_name'] . "</td>";
                                                    echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                                    echo "</tr>";
                                                }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ Adjustment History ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

    <!-- Required Js -->
    <!-- jQuery first -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <!-- Bootstrap after jQuery -->
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <!-- Other scripts -->
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- Custom scripts after all libraries are loaded -->
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            try {
                $('#adjustment-list-table').DataTable();
            } catch(e) {
                console.error("DataTable error:", e);
            }
            
            // Current salary value
            var currentSalary = <?php echo $current_salary; ?>;
            var currency = "<?php echo $currency; ?>";
            
            // Function to calculate new salary preview
            function calculateNewSalary() {
                var adjustmentType = $('#adjustment_type').val();
                var amount = parseFloat($('#amount').val()) || 0;
                var percentage = parseFloat($('#percentage').val()) || 0;
                var newSalary = currentSalary;
                
                if (amount > 0) {
                    // Using fixed amount
                    if (adjustmentType === 'increment') {
                        newSalary = currentSalary + amount;
                    } else {
                        newSalary = currentSalary - amount;
                    }
                } else if (percentage > 0) {
                    // Using percentage
                    var adjustmentValue = currentSalary * (percentage / 100);
                    if (adjustmentType === 'increment') {
                        newSalary = currentSalary + adjustmentValue;
                    } else {
                        newSalary = currentSalary - adjustmentValue;
                    }
                }
                
                // Ensure new salary is not negative
                newSalary = Math.max(0, newSalary);
                
                // Update preview
                $('#salary-preview').text(newSalary.toFixed(2) + " " + currency);
            }
            
            // Handle input changes
            $('#adjustment_type, #amount, #percentage').on('change keyup', function() {
                // If both amount and percentage are filled, clear the other one
                if ($(this).attr('id') === 'amount' && $(this).val() !== '') {
                    $('#percentage').val('');
                } else if ($(this).attr('id') === 'percentage' && $(this).val() !== '') {
                    $('#amount').val('');
                }
                
                calculateNewSalary();
            });
        });
    </script>

</body>
</html> 