<?php
// Initialize the session
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];

// Include config file
require_once "../includes/db.php"; // assumes both $pdo and $conection_db are set here

// Fetch logged-in user (only from same tenant)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error in dashboard.php: " . $e->getMessage());
}

// Define variables and initialize with empty values
$user_id = $base_salary = $currency = $joining_date = $payment_day = "";
$user_id_err = $base_salary_err = $currency_err = $joining_date_err = $payment_day_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // If this is an update operation
    if (isset($_POST["update_salary"])) {
        if (empty($_POST["base_salary"])) {
            $base_salary_err = "Please enter the base salary.";
        } else {
            $base_salary = $_POST["base_salary"];
        }

        $user_id = $_POST["user_id"];
        $currency = $_POST["currency"];
        $payment_day = $_POST["payment_day"];
        $status = $_POST["status"] ?? 'active';
        $fired = isset($_POST["fired"]) ? intval($_POST["fired"]) : 0;

        if (empty($base_salary_err)) {
            $sql_salary = "UPDATE salary_management 
                           SET base_salary = ?, currency = ?, payment_day = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                           WHERE user_id = ? AND tenant_id = ?";

            $sql_user = "UPDATE users 
                         SET fired = ?, fired_at = CURRENT_TIMESTAMP 
                         WHERE id = ? AND tenant_id = ?";

            try {
                mysqli_begin_transaction($conection_db);

                if ($stmt_salary = mysqli_prepare($conection_db, $sql_salary)) {
                    mysqli_stmt_bind_param($stmt_salary, "dsssii", $base_salary, $currency, $payment_day, $status, $user_id, $tenant_id);
                    if (!mysqli_stmt_execute($stmt_salary)) {
                        throw new Exception("Error updating salary management");
                    }
                    mysqli_stmt_close($stmt_salary);
                }

                if ($stmt_user = mysqli_prepare($conection_db, $sql_user)) {
                    mysqli_stmt_bind_param($stmt_user, "iii", $fired, $user_id, $tenant_id);
                    if (!mysqli_stmt_execute($stmt_user)) {
                        throw new Exception("Error updating user fired status");
                    }
                    mysqli_stmt_close($stmt_user);
                }

                mysqli_commit($conection_db);
                header("location: salary_management.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conection_db);
                error_log("Salary update error: " . $e->getMessage());
                echo "Oops! Something went wrong. Please try again later.";
            }
        }
    } else {
        // Add new salary
        if (empty($_POST["user_id"])) {
            $user_id_err = "Please select an employee.";
        } else {
            $user_id = $_POST["user_id"];

            $sql = "SELECT id FROM salary_management WHERE user_id = ? AND tenant_id = ?";
            if ($stmt = mysqli_prepare($conection_db, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $tenant_id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        $user_id_err = "This employee already has a salary record.";
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        if (empty($_POST["base_salary"])) {
            $base_salary_err = "Please enter the base salary.";
        } else {
            $base_salary = $_POST["base_salary"];
        }

        if (empty($_POST["joining_date"])) {
            $joining_date_err = "Please enter the joining date.";
        } else {
            $joining_date = $_POST["joining_date"];
        }

        $currency = $_POST["currency"];
        $payment_day = $_POST["payment_day"];

        if (empty($user_id_err) && empty($base_salary_err) && empty($joining_date_err)) {
            $sql = "INSERT INTO salary_management (user_id, base_salary, currency, joining_date, payment_day, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            if ($stmt = mysqli_prepare($conection_db, $sql)) {
                mysqli_stmt_bind_param($stmt, "idssii", $user_id, $base_salary, $currency, $joining_date, $payment_day, $tenant_id);
                if (mysqli_stmt_execute($stmt)) {
                    header("location: salary_management.php");
                    exit();
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    mysqli_close($conection_db);
}

// ---------- FETCH DATA FOR DISPLAY ----------

// Fetch current tenant's salary records
try {
    $stmt = $pdo->prepare("
        SELECT sm.*, u.username, u.email 
        FROM salary_management sm
        JOIN users u ON sm.user_id = u.id
        WHERE sm.tenant_id = ?
        ORDER BY u.username ASC
    ");
    $stmt->execute([$tenant_id]);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching salaries: " . $e->getMessage());
    $salaries = [];
}

// Fetch only tenant's users for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE tenant_id = ? AND fired = 0 ORDER BY username ASC");
    $stmt->execute([$tenant_id]);
    $users_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users_dropdown = [];
}
?>

<!-- [ Header ] start -->
<?php include '../includes/header.php'; ?>
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
                                <h5 class="m-b-10"><?= __('salary_management') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('salary_management') ?></a></li>
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
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="feather icon-user-plus mr-2"></i><?= __('add_new_salary_record') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="addSalaryForm" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id" class="font-weight-bold">
                                                <i class="feather icon-user mr-1"></i><?= __('employee') ?>
                                            </label>
                                            <select class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" 
                                                    id="user_id" name="user_id" required>
                                                <option value=""><?= __('select_employee') ?></option>
                                                <?php
                                                // Get all users without salary records
                                                $sql = "SELECT u.id, u.name 
                                                        FROM users u 
                                                        LEFT JOIN salary_management sm ON u.id = sm.user_id 
                                                        WHERE sm.id IS NULL AND u.tenant_id = ?";
                                                $stmt = mysqli_prepare($conection_db, $sql);
                                                mysqli_stmt_bind_param($stmt, "i", $tenant_id);
                                                mysqli_stmt_execute($stmt);
                                                $result = mysqli_stmt_get_result($stmt);
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    echo "<option value='" . $row['id'] . "'>" . $row['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $user_id_err ?: __('please_select_employee'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="base_salary" class="font-weight-bold">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('base_salary') ?>
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control <?php echo (!empty($base_salary_err)) ? 'is-invalid' : ''; ?>" 
                                                       id="base_salary" name="base_salary" step="0.01" value="<?php echo $base_salary; ?>" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                                </div>
                                                <div class="invalid-feedback"><?php echo $base_salary_err ?: __('please_enter_base_salary'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="currency" class="font-weight-bold">
                                                <i class="feather icon-credit-card mr-1"></i><?= __('currency') ?>
                                            </label>
                                            <select class="form-control" id="currency" name="currency" required>
                                                <option value="USD" <?php echo ($currency == "USD") ? "selected" : ""; ?>><?= __('usd') ?></option>
                                                <option value="AFS" <?php echo ($currency == "AFS") ? "selected" : ""; ?>><?= __('afs') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="joining_date" class="font-weight-bold">
                                                <i class="feather icon-calendar mr-1"></i><?= __('joining_date') ?>
                                            </label>
                                            <input type="date" class="form-control <?php echo (!empty($joining_date_err)) ? 'is-invalid' : ''; ?>" 
                                                   id="joining_date" name="joining_date" value="<?php echo $joining_date; ?>" required>
                                            <div class="invalid-feedback"><?php echo $joining_date_err ?: __('please_select_joining_date'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="payment_day" class="font-weight-bold">
                                                <i class="feather icon-clock mr-1"></i><?= __('payment_day') ?> (<?= __('of_month') ?>)
                                            </label>
                                            <input type="number" class="form-control" id="payment_day" name="payment_day" 
                                                   min="1" max="31" value="<?php echo empty($payment_day) ? "1" : $payment_day; ?>" required>
                                            <div class="invalid-feedback"><?= __('please_enter_valid_payment_day') ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-save mr-1"></i><?= __('add_salary_record') ?>
                                        </button>
                                        <button type="reset" class="btn btn-light ml-2">
                                            <i class="feather icon-refresh-ccw mr-1"></i><?= __('reset') ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->

                <script>
                // Form validation
                document.getElementById('addSalaryForm').addEventListener('submit', function(event) {
                    if (!this.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    this.classList.add('was-validated');
                });

                // Reset form validation state on reset
                document.querySelector('button[type="reset"]').addEventListener('click', function() {
                    document.getElementById('addSalaryForm').classList.remove('was-validated');
                });
                </script>

                <!-- [ Salary Records ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="feather icon-list mr-2"></i><?= __('current_employee_salaries') ?>
                            </h5>
                            <div class="btn-group">
                                <a href="manage_bonuses.php" class="btn btn-success">
                                    <i class="feather icon-plus-circle mr-1"></i><?= __('manage_bonuses') ?>
                                </a>
                                <a href="manage_deductions.php" class="btn btn-warning">
                                    <i class="feather icon-minus-circle mr-1"></i><?= __('manage_deductions') ?>
                                </a>
                                <a href="print_payroll.php" class="btn btn-primary" target="_blank">
                                    <i class="feather icon-printer mr-1"></i><?= __('print_group_payroll') ?>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <table id="user-list-table" class="table table-hover nowrap">
                                    <thead class="bg-light">
                                        <tr>
                                            <th><?= __('id') ?></th>
                                            <th><?= __('employee_name') ?></th>
                                            <th><?= __('base_salary') ?></th>
                                            <th><?= __('currency') ?></th>
                                            <th><?= __('joining_date') ?></th>
                                            <th><?= __('payment_day') ?></th>
                                            <th><?= __('status') ?></th>
                                            <th class="text-center"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all salary records
                                        $sql = "SELECT sm.*, u.name as employee_name, u.fired as is_fired 
                                                FROM salary_management sm 
                                                JOIN users u ON sm.user_id = u.id 
                                                WHERE u.tenant_id = ?
                                                ORDER BY sm.id DESC";
                                        $stmt = mysqli_prepare($conection_db, $sql);
                                        mysqli_stmt_bind_param($stmt, "i", $tenant_id);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $row_class = $row['is_fired'] ? 'table-danger fired-user' : '';
                                            $status_badge = $row['status'] == 'active' 
                                                ? ($row['is_fired'] 
                                                    ? '<span class="badge badge-danger">Fired</span>' 
                                                    : '<span class="badge badge-success">Active</span>') 
                                                : '<span class="badge badge-warning">Inactive</span>';
                                            
                                            echo "<tr class='" . $row_class . "'>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td class='font-weight-bold'>" . $row['employee_name'] . 
                                                 ($row['is_fired'] ? " <span class='badge badge-danger ml-2'>Fired</span>" : "") . 
                                                 "</td>";
                                            echo "<td class='text-right'>" . number_format($row['base_salary'], 2) . "</td>";
                                            echo "<td>" . $row['currency'] . "</td>";
                                            echo "<td>" . date('M d, Y', strtotime($row['joining_date'])) . "</td>";
                                            echo "<td class='text-center'>" . $row['payment_day'] . "</td>";
                                            echo "<td>" . $status_badge . "</td>";
                                            echo "<td class='text-center'>";
                                            echo "<div class='dropdown'>";
                                            echo "<button class='btn btn-secondary btn-sm dropdown-toggle' type='button' id='actionDropdown" . $row['id'] . "' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>";
                                            echo "<i class='feather icon-more-horizontal'></i>";
                                            echo "</button>";
                                            echo "<div class='dropdown-menu dropdown-menu-right shadow-sm' aria-labelledby='actionDropdown" . $row['id'] . "'>";
                                            
                                            // Edit button
                                            echo "<a class='dropdown-item edit-salary' href='#' data-toggle='modal' data-target='#editSalaryModal' 
                                                    data-id='" . $row['id'] . "' 
                                                    data-user-id='" . $row['user_id'] . "' 
                                                    data-name='" . $row['employee_name'] . "' 
                                                    data-base-salary='" . $row['base_salary'] . "' 
                                                    data-currency='" . $row['currency'] . "' 
                                                    data-payment-day='" . $row['payment_day'] . "' 
                                                    data-status='" . $row['status'] . "' 
                                                    data-fired='" . $row['is_fired'] . "'>";
                                            echo "<i class='feather icon-edit mr-2 text-primary'></i>Edit Salary</a>";
                                            
                                            // Adjustment button
                                            echo "<a class='dropdown-item' href='salary_adjustment.php?adjustment_user_id=" . $row['user_id'] . "'>";
                                            echo "<i class='feather icon-dollar-sign mr-2 text-success'></i>Adjustments</a>";
                                            
                                            // Advances button
                                            echo "<a class='dropdown-item' href='salary_advances.php?advance_user_id=" . $row['user_id'] . "'>";
                                            echo "<i class='feather icon-credit-card mr-2 text-warning'></i>Salary Advances</a>";
                                            
                                            // Print button
                                            echo "<div class='dropdown-divider'></div>";
                                            echo "<a class='dropdown-item' href='print_payroll.php?user_id=" . $row['user_id'] . "' target='_blank'>";
                                            echo "<i class='feather icon-printer mr-2 text-info'></i>Print Payroll</a>";
                                            
                                            echo "</div>";
                                            echo "</div>";
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
                <!-- [ Salary Records ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Edit Salary Modal -->
    <div id="editSalaryModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-edit mr-2"></i><?= __('edit_salary') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="editSalaryForm">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="update_salary" value="1">
                        
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="feather icon-user mr-1"></i><?= __('employee') ?>
                            </label>
                            <input type="text" class="form-control" id="edit_employee_name" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('base_salary') ?>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_base_salary" name="base_salary" 
                                       step="0.01" required>
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="feather icon-credit-card mr-1"></i><?= __('currency') ?>
                            </label>
                            <select class="form-control" id="edit_currency" name="currency">
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="font-weight-bold">
                                <i class="feather icon-calendar mr-1"></i><?= __('payment_day') ?>
                            </label>
                            <input type="number" class="form-control" id="edit_payment_day" name="payment_day" 
                                   min="1" max="31">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="feather icon-toggle-right mr-1"></i><?= __('status') ?>
                                    </label>
                                    <select class="form-control" id="edit_status" name="status">
                                        <option value="active"><?= __('active') ?></option>
                                        <option value="inactive"><?= __('inactive') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        <i class="feather icon-user-minus mr-1"></i><?= __('employment_status') ?>
                                    </label>
                                    <select class="form-control" id="edit_fired" name="fired">
                                        <option value="0"><?= __('employed') ?></option>
                                        <option value="1"><?= __('fired') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('close') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save mr-1"></i><?= __('update') ?>
                        </button>
                    </div>
                </form>
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
        /* Fired User Styles */
        .table-danger {
            background-color: #f8d7da !important;
        }
        
        .table-danger td {
            color: #721c24 !important;
        }
        
        .table-danger .user-avatar {
            opacity: 0.6;
            filter: grayscale(100%);
        }
        
        .badge-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }
/* Enhanced Table Styles */
.table {
    margin-bottom: 0;
}

.table thead th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
    padding: 1rem;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.02);
}

/* Badge Styles */
.badge {
    padding: 0.5em 0.75em;
    font-weight: 500;
}

.badge-success {
    background-color: #2ed8b6;
}

.badge-warning {
    background-color: #ffb64d;
    color: #fff;
}

.badge-danger {
    background-color: #ff5370;
}

/* Button Group Styles */
.btn-group .btn {
    margin-left: 0.5rem;
}

.btn-group .btn:first-child {
    margin-left: 0;
}

/* Dropdown Styles */
.dropdown-menu {
    padding: 0.5rem 0;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-divider {
    margin: 0.5rem 0;
}

/* Modal Enhancements */
.modal-content {
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,.2);
}

.modal-header {
    padding: 1rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem;
}

/* Form Enhancements */
.form-control {
    height: calc(1.5em + 1rem + 2px);
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin: 0.25rem 0;
        width: 100%;
    }
    
    .table thead th {
        font-size: 0.75rem;
        padding: 0.75rem;
    }
    
    .table tbody td {
        padding: 0.75rem;
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


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
    <!-- Required Js -->
   
    
    <!-- Custom scripts -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.7/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.7/js/responsive.bootstrap4.min.js"></script>

    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


      <script>
    // Handle edit salary button clicks
    document.addEventListener('DOMContentLoaded', function() {
        // Add click event listeners to all edit salary buttons
        document.querySelectorAll('.edit-salary').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get data from button attributes
                const userId = this.getAttribute('data-user-id');
                const name = this.getAttribute('data-name');
                const baseSalary = this.getAttribute('data-base-salary');
                const currency = this.getAttribute('data-currency');
                const paymentDay = this.getAttribute('data-payment-day');
                const status = this.getAttribute('data-status');
                const fired = this.getAttribute('data-fired');
                
                // Populate modal fields
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_employee_name').value = name;
                document.getElementById('edit_base_salary').value = baseSalary;
                document.getElementById('edit_currency').value = currency;
                document.getElementById('edit_payment_day').value = paymentDay;
                document.getElementById('edit_status').value = status;
                document.getElementById('edit_fired').value = fired;
            });
        });

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

</body>
</html> 