<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get hidden input value
    $id = $_POST["id"];
    
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
    
    // Check input errors before updating in database
    if (empty($user_id_err) && empty($amount_err) && empty($description_err) && empty($deduction_date_err)) {
        // Prepare an update statement
        $sql = "UPDATE salary_deductions SET user_id=?, amount=?, description=?, deduction_date=?, type=? WHERE id=? AND tenant_id = ? AND branch_id = ?";

        try {
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $amount, PDO::PARAM_STR);
            $stmt->bindParam(3, $description, PDO::PARAM_STR);
            $stmt->bindParam(4, $deduction_date, PDO::PARAM_STR);
            $stmt->bindParam(5, $type, PDO::PARAM_STR);
            $stmt->bindParam(6, $id, PDO::PARAM_INT);
            $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Records updated successfully. Redirect to landing page
                header("location: manage_deductions.php?updated=1");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        } catch (PDOException $e) {
            echo "Oops! Something went wrong. Please try again later.";
            error_log("Update deduction error: " . $e->getMessage());
        }
    }
} else {
    // Check existence of id parameter before processing further
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter
        $id = trim($_GET["id"]);
        
        // Prepare a select statement
        $sql = "SELECT * FROM salary_deductions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        try {
            $stmt = $pdo->prepare($sql);

            // Bind parameters
            $param_id = $id;
            $stmt->bindParam(1, $param_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $result = $stmt->fetchAll();

                if (count($result) == 1) {
                    /* Fetch result row as an associative array. Since the result set
                    contains only one row, we don't need to use while loop */
                    $row = $result[0];

                    // Retrieve individual field value
                    $user_id = $row["user_id"];
                    $amount = $row["amount"];
                    $description = $row["description"];
                    $deduction_date = $row["deduction_date"];
                    $type = $row["type"];
                } else {
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }

            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
        } catch (PDOException $e) {
            echo "Oops! Something went wrong. Please try again later.";
            error_log("Select deduction error: " . $e->getMessage());
        }
    } else {
        // URL doesn't contain id parameter. Redirect to error page
        header("location: error.php");
        exit();
    }
}

?>


    <!-- [ Header ] start -->
    <?php include("../includes/header.php"); ?>
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
                                <h5 class="m-b-10"><?= __('edit_deduction') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php"><?= __('salary_management') ?></a></li>
                                <li class="breadcrumb-item"><a href="manage_deductions.php"><?= __('manage_deductions') ?></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('edit_deduction') ?></a></li>
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
                            <h5><?= __('edit_deduction_details') ?></h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id"><?= __('employee') ?></label>
                                            <select class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" id="user_id" name="user_id">
                                                <?php
                                                // Get all active users with salary records
                                                $sql = "SELECT u.id, u.name
                                                        FROM users u
                                                        JOIN salary_management sm ON u.id = sm.user_id
                                                        WHERE sm.status = 'active' AND u.tenant_id = ? AND u.branch_id = ?
                                                        ORDER BY u.name ASC";
                                                try {
                                                    $stmt = $pdo->prepare($sql);
                                                    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                                                    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                                                    $stmt->execute();
                                                    $result = $stmt->fetchAll();
                                                    foreach ($result as $row) {
                                                        $selected = ($row['id'] == $user_id) ? 'selected' : '';
                                                        echo "<option value='" . $row['id'] . "' " . $selected . ">" . $row['name'] . "</option>";
                                                    }
                                                } catch (PDOException $e) {
                                                    error_log("Select users error: " . $e->getMessage());
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
                                                <option value="absence" <?php echo ($type == 'absence') ? 'selected' : ''; ?>><?= __('absence') ?></option>
                                                <option value="penalty" <?php echo ($type == 'penalty') ? 'selected' : ''; ?>><?= __('penalty') ?></option>
                                                <option value="tax" <?php echo ($type == 'tax') ? 'selected' : ''; ?>><?= __('tax') ?></option>
                                                <option value="other" <?php echo ($type == 'other') ? 'selected' : ''; ?>><?= __('other') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="deduction_date"><?= __('deduction_date') ?></label>
                                            <input type="date" class="form-control <?php echo (!empty($deduction_date_err)) ? 'is-invalid' : ''; ?>" id="deduction_date" name="deduction_date" value="<?php echo $deduction_date; ?>">
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
                                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                                        <button type="submit" class="btn btn-primary"><?= __('update_deduction') ?></button>
                                        <a href="manage_deductions.php" class="btn btn-secondary"><?= __('cancel') ?></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->
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
</body>
</html> 