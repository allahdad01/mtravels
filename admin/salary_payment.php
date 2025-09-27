<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../access_denied.php");
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
// Include config file
require_once "../includes/db.php";

// Define variables and initialize with empty values
$user_id = $main_account_id = $amount = $currency = $payment_type = $description = $payment_for_month = "";
$user_id_err = $main_account_id_err = $amount_err = $currency_err = $payment_type_err = $payment_for_month_err = "";

// Generate receipt number
function generateReceiptNumber() {
    return "SP" . date("YmdHis");
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate user ID
    if (empty($_POST["user_id"])) {
        $user_id_err = "Please select an employee.";
    } else {
        $user_id = $_POST["user_id"];
    }
    
    // Validate main account
    if (empty($_POST["main_account_id"])) {
        $main_account_id_err = "Please select a main account.";
    } else {
        $main_account_id = $_POST["main_account_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the payment amount.";
    } else {
        $amount = $_POST["amount"];
    }
    
    // Validate payment for month
    if (empty($_POST["payment_for_month"])) {
        $payment_for_month_err = "Please enter the month this payment is for.";
    } else {
        $payment_for_month = $_POST["payment_for_month"] . "-01"; // Convert to YYYY-MM-01 format
    }
    
    // Set other values
    $currency = $_POST["currency"];
    $payment_type = $_POST["payment_type"];
    $description = $_POST["description"];
    $payment_date = date("Y-m-d");
    $receipt = generateReceiptNumber();
    // New: number of months to pay (defaults to 1)
    $months_to_pay = isset($_POST['months_to_pay']) ? (int)$_POST['months_to_pay'] : 1;
    if ($months_to_pay < 1) { $months_to_pay = 1; }
    
    // Check input errors before inserting in database
    if (empty($user_id_err) && empty($main_account_id_err) && empty($amount_err) && empty($payment_for_month_err)) {
        // Start transaction
        mysqli_begin_transaction($conection_db);
        
        try {
            // Get current main account balance
            $sql = "SELECT usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ?";
            $stmt = mysqli_prepare($conection_db, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $main_account_id, $tenant_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $usd_balance, $afs_balance);
                mysqli_stmt_fetch($stmt);
                
                // Calculate starting balance and total deduction
                $starting_balance = ($currency == "USD") ? $usd_balance : $afs_balance;
                $total_deduction = $amount * $months_to_pay;

                // Loop through each month and create individual payment + transaction
                for ($i = 0; $i < $months_to_pay; $i++) {
                    $this_month_for = date('Y-m-01', strtotime("+{$i} month", strtotime($payment_for_month)));
                    $this_receipt = $receipt . '-' . ($i + 1);

                    // Insert into salary_payments (one row per month)
                    $insert_sql = "INSERT INTO salary_payments (user_id, main_account_id, amount, currency, payment_date, 
                                   payment_for_month, payment_type, description, receipt, tenant_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $insert_stmt = mysqli_prepare($conection_db, $insert_sql);
                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        "iidssssssi",
                        $user_id,
                        $main_account_id,
                        $amount,
                        $currency,
                        $payment_date,
                        $this_month_for,
                        $payment_type,
                        $description,
                        $this_receipt,
                        $tenant_id
                    );
                    mysqli_stmt_execute($insert_stmt);

                    // Get the inserted payment ID
                    $payment_id = mysqli_insert_id($conection_db);

                    // Running balance after this month's payment
                    $running_balance = $starting_balance - ($amount * ($i + 1));

                    // Insert into main_account_transactions (one per month)
                    $transaction_sql = "INSERT INTO main_account_transactions (main_account_id, type, amount, balance, currency, 
                                       description, transaction_of, reference_id, receipt, tenant_id) 
                                       VALUES (?, 'debit', ?, ?, ?, ?, 'salary_payment', ?, ?, ?)";

                    $transaction_stmt = mysqli_prepare($conection_db, $transaction_sql);
                    mysqli_stmt_bind_param(
                        $transaction_stmt,
                        "iddssisi",
                        $main_account_id,
                        $amount,
                        $running_balance,
                        $currency,
                        $description,
                        $payment_id,
                        $this_receipt,
                        $tenant_id
                    );
                    mysqli_stmt_execute($transaction_stmt);

                    // If this is a regular payment, deduct advances per month
                    if ($payment_type == 'regular') {
                        $advance_sql = "SELECT id, amount, amount_paid FROM salary_advances 
                                       WHERE user_id = ? AND currency = ? AND repayment_status != 'paid' AND tenant_id = ?";
                        $advance_stmt = mysqli_prepare($conection_db, $advance_sql);
                        mysqli_stmt_bind_param($advance_stmt, "isi", $user_id, $currency, $tenant_id);
                        mysqli_stmt_execute($advance_stmt);
                        $advance_result = mysqli_stmt_get_result($advance_stmt);

                        while ($advance_row = mysqli_fetch_array($advance_result)) {
                            $advance_id = $advance_row['id'];
                            $advance_amount = $advance_row['amount'];
                            $amount_paid_adv = $advance_row['amount_paid'];
                            $remaining = $advance_amount - $amount_paid_adv;
                            $deduction = min($amount, $remaining);
                            if ($deduction > 0) {
                                $new_paid = $amount_paid_adv + $deduction;
                                $status = ($new_paid >= $advance_amount) ? 'paid' : 'partially_paid';
                                $update_advance_sql = "UPDATE salary_advances SET amount_paid = ?, repayment_status = ? WHERE id = ? AND tenant_id = ?";
                                $update_advance_stmt = mysqli_prepare($conection_db, $update_advance_sql);
                                mysqli_stmt_bind_param($update_advance_stmt, "dsii", $new_paid, $status, $advance_id, $tenant_id);
                                mysqli_stmt_execute($update_advance_stmt);
                            }
                        }
                    }
                }

                // After creating all payments + transactions, update the main account balance once by total
                $update_sql = ($currency == "USD") 
                    ? "UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ?"
                    : "UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conection_db, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "di", $total_deduction, $main_account_id);
                mysqli_stmt_execute($update_stmt);

                // Commit transaction
                mysqli_commit($conection_db);
                
                // Redirect to success page
                header("location: salary_payment.php?success=1");
                exit();
            } else {
                throw new Exception("Main account not found.");
            }
        } catch (Exception $e) {
            // Roll back transaction on error
            mysqli_rollback($conection_db);
            echo "Error: " . $e->getMessage();
        }
    }
    
    // Close connection
    mysqli_close($conection_db);
}
?>


    <style>
        .description-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }
        
        /* Make sure the table is responsive */
        .table-responsive {
            overflow-x: auto;
        }
        
        /* Ensure consistent column widths */
        .table th, .table td {
            vertical-align: middle;
        }
    </style>


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
                                <h5 class="m-b-10"><?= __('salary_payment') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php"><?= __('salary_management') ?></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('salary_payment') ?></a></li>
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
                            <h5><?= __('process_salary_payment') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('salary_payment_processed_successfully') ?>
                            </div>
                            <?php endif; ?>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id"><?= __('employee') ?></label>
                                            <select class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" id="user_id" name="user_id" required>
                                                <option value=""><?= __('select_employee') ?></option>
                                                <?php
                                                // Get all employees with salary records
                                                $sql = "SELECT u.id, u.name, sm.base_salary, sm.currency 
                                                        FROM users u 
                                                        JOIN salary_management sm ON u.id = sm.user_id 
                                                        WHERE sm.status = 'active' and u.fired = 0
                                                        ORDER BY u.name";
                                                $result = mysqli_query($conection_db, $sql);
                                                while ($row = mysqli_fetch_array($result)) {
                                                    echo "<option value='" . $row['id'] . "' data-base-salary='" . $row['base_salary'] . "' data-currency='" . $row['currency'] . "'>" . $row['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $user_id_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="main_account_id"><?= __('select_account') ?></label>
                                            <select class="form-control <?php echo (!empty($main_account_id_err)) ? 'is-invalid' : ''; ?>" id="main_account_id" name="main_account_id" required>
                                                <option value=""><?= __('select_account') ?></option>
                                                <?php
                                                // Get all main accounts
                                                $sql = "SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' and tenant_id = $tenant_id";
                                                $result = mysqli_query($conection_db, $sql);
                                                while ($row = mysqli_fetch_array($result)) {
                                                    echo "<option value='" . $row['id'] . "'>" . $row['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $main_account_id_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="payment_for_month"><?= __('payment_for_month') ?></label>
                                            <input type="month" class="form-control <?php echo (!empty($payment_for_month_err)) ? 'is-invalid' : ''; ?>" id="payment_for_month" name="payment_for_month" value="<?php echo date('Y-m'); ?>" required>
                                            <div class="invalid-feedback"><?php echo $payment_for_month_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="months_to_pay"><?= __('months_to_pay') ?></label>
                                            <input type="number" class="form-control" id="months_to_pay" name="months_to_pay" min="1" step="1" value="1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="amount"><?= __('amount') ?></label>
                                            <input type="number" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" step="0.01" value="<?php echo $amount; ?>" required>
                                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <small class="text-muted" id="totalAmountHint"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="currency"><?= __('currency') ?></label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo ($currency == "USD") ? "selected" : ""; ?>>USD</option>
                                                <option value="AFS" <?php echo ($currency == "AFS") ? "selected" : ""; ?>>AFS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="payment_type"><?= __('payment_type') ?></label>
                                            <select class="form-control" id="payment_type" name="payment_type">
                                                <option value="regular" <?php echo ($payment_type == "regular") ? "selected" : ""; ?>><?= __('regular_salary') ?></option>
                                                <option value="bonus" <?php echo ($payment_type == "bonus") ? "selected" : ""; ?>><?= __('bonus') ?></option>
                                                <option value="advance" <?php echo ($payment_type == "advance") ? "selected" : ""; ?>><?= __('advance') ?></option>
                                                <option value="other" <?php echo ($payment_type == "other") ? "selected" : ""; ?>><?= __('other') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="description"><?= __('description') ?></label>
                                            <input type="text" class="form-control" id="description" name="description" value="<?php echo $description; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary"><?= __('process_payment') ?></button>
                                        <a href="salary_management.php" class="btn btn-secondary"><?= __('back_to_salary_management') ?></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->

                <!-- [ Payment History ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('salary_payment_history') ?></h5>
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
                                <table id="payment-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th><?= __('id') ?></th>
                                            <th><?= __('employee') ?></th>
                                            <th><?= __('account') ?></th>
                                            <th><?= __('amount') ?></th>
                                            
                                            <th><?= __('type') ?></th>
                                            <th><?= __('payment_date') ?></th>
                                            <th><?= __('for_month') ?></th>
                                            
                                            <th><?= __('description') ?></th>
                                            <th><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all salary payments
                                        $sql = "SELECT sp.*, u.name as employee_name, ma.name as account_name 
                                                FROM salary_payments sp 
                                                JOIN users u ON sp.user_id = u.id 
                                                JOIN main_account ma ON sp.main_account_id = ma.id
                                                ORDER BY sp.created_at DESC";
                                        $result = mysqli_query($conection_db, $sql);
                                        while ($row = mysqli_fetch_array($result)) {
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . $row['employee_name'] . "</td>";
                                            echo "<td>" . $row['account_name'] . "</td>";
                                            echo "<td>" . number_format($row['amount'], 2) . " " . $row['currency'] . " <br>
                                            <small class='text-muted'>" . $row['receipt'] . "</small>
                                            </td>";
                                            
                                            echo "<td>" . ucfirst($row['payment_type']) . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['payment_date'])) . "</td>";
                                            echo "<td>" . date('Y-m', strtotime($row['payment_for_month'])) . "</td>";
                                            echo "<td class='description-cell' title='" . htmlspecialchars($row['description']) . "'>" 
                                                . (strlen($row['description']) > 50 ? substr(htmlspecialchars($row['description']), 0, 50) . '...' : htmlspecialchars($row['description'])) 
                                                . "</td>";
                                            echo "<td>
                                                    <button type='button' 
                                                            class='btn btn-danger btn-sm delete-payment'
                                                            data-payment-id='" . $row['id'] . "'
                                                            data-amount='" . $row['amount'] . "'
                                                            data-main-account-id='" . $row['main_account_id'] . "'>
                                                            <i class='feather icon-trash-2'></i> <?= __('delete') ?>
                                                    </button>
                                                    <button type='button' 
                                                            class='btn btn-info btn-sm edit-payment'
                                                            data-payment-id='" . $row['id'] . "'
                                                            data-amount='" . $row['amount'] . "'
                                                            data-currency='" . $row['currency'] . "'
                                                            data-date='" . date('Y-m-d', strtotime($row['payment_date'])) . "'
                                                            data-description='" . htmlspecialchars($row['description']) . "'
                                                            data-payment-type='" . $row['payment_type'] . "'
                                                            data-user-id='" . $row['user_id'] . "'
                                                            data-main-account-id='" . $row['main_account_id'] . "'>
                                                            <i class='feather icon-edit-2'></i> <?= __('edit') ?>
                                                    </button>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ Payment History ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="editPaymentModalLabel">
                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_salary_payment') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editPaymentForm">
                        <input type="hidden" id="edit_payment_id" name="payment_id">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <input type="hidden" id="edit_original_amount" name="original_amount">
                        <input type="hidden" id="edit_main_account_id" name="main_account_id">
                        
                        <div class="form-group">
                            <label for="edit_payment_amount"><?= __('amount') ?></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="edit_payment_amount" name="payment_amount" step="0.01" required>
                                <div class="input-group-append">
                                    <span class="input-group-text" id="edit_currency_display">USD</span>
                                </div>
                            </div>
                            <input type="hidden" id="edit_currency" name="currency">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_payment_date"><?= __('payment_date') ?></label>
                            <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_payment_type"><?= __('payment_type') ?></label>
                            <select class="form-control" id="edit_payment_type" name="payment_type">
                                <option value="regular"><?= __('regular_salary') ?></option>
                                <option value="bonus"><?= __('bonus') ?></option>
                                <option value="advance"><?= __('advance') ?></option>
                                <option value="other"><?= __('other') ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_payment_description"><?= __('description') ?></label>
                            <textarea class="form-control" id="edit_payment_description" name="payment_description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-info" id="savePaymentChanges">
                        <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
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
                    <i class="feather icon-user mr-2"></i> <?= __('user_profile') ?>
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
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            try {
                $('#payment-list-table').DataTable();
            } catch(e) {
                console.error("DataTable error:", e);
            }
            
            // Simple jQuery filtering (no DataTables dependency)
            var $rows = $('#payment-list-table tbody tr');
            
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
                        // Check both payment date (column 5) and for month (column 6)
                        var paymentDateCell = $(this).find('td:eq(5)').text().trim(); // 6th column (index 5) contains the payment date
                        var forMonthCell = $(this).find('td:eq(6)').text().trim(); // 7th column (index 6) contains the for month
                        
                        var matchPaymentDate = false;
                        var matchForMonth = false;
                        
                        // Check payment date
                        if (paymentDateCell && paymentDateCell.length >= 7) {
                            var paymentDateYearMonth = paymentDateCell.substring(0, 7); // Extract YYYY-MM
                            if (paymentDateYearMonth === selectedMonth) {
                                matchPaymentDate = true;
                            }
                        }
                        
                        // Check for month
                        if (forMonthCell && forMonthCell.length >= 7) {
                            if (forMonthCell === selectedMonth) {
                                matchForMonth = true;
                            }
                        }
                        
                        // Show if either date matches
                        if (matchPaymentDate || matchForMonth) {
                            $(this).show();
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
            
            // Auto-fill salary amount when employee is selected
            $('#user_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var userId = selectedOption.val();
                var baseSalary = parseFloat(selectedOption.data('base-salary')) || 0;
                var currency = selectedOption.data('currency');
                
                // Clear previous breakdown
                $('.salary-breakdown').remove();
                
                if (baseSalary && userId) {
                    // Get advances, deductions, and bonuses via AJAX
                    $.ajax({
                        url: 'get_salary_details.php',
                        type: 'POST',
                        dataType: 'json', // Explicitly expect JSON response
                        data: {
                            user_id: userId,
                            currency: currency,
                            payment_for_month: $('#payment_for_month').val()
                        },
                        success: function(data) {
                            // No need to parse, jQuery will do it automatically with dataType: 'json'
                            if(data.error) {
                                console.error('Server error:', data.error);
                                return;
                            }
                            
                            var totalAdvances = parseFloat(data.totalAdvances) || 0;
                            var totalDeductions = parseFloat(data.totalDeductions) || 0;
                            var totalBonuses = parseFloat(data.totalBonuses) || 0;
                            
                            // Calculate remaining amount
                            var remainingAmount = baseSalary - totalAdvances - totalDeductions + totalBonuses;
                            remainingAmount = Math.max(0, remainingAmount); // Ensure it's not negative
                            
                            // Check if salary is already paid
                            if (data.salaryAlreadyPaid) {
                                // Show warning and disable the form
                                var warningHtml = '<div class="alert alert-warning" role="alert">';
                                warningHtml += '<i class="feather icon-alert-triangle mr-2"></i>';
                                warningHtml += '<?= __('salary_already_paid_for_this_month') ?>';
                                warningHtml += '<br><strong><?= __('payment_details') ?>:</strong>';
                                warningHtml += '<ul class="mb-0 mt-2">';
                                warningHtml += '<li><?= __('amount') ?>: ' + data.existingPayment.amount + ' ' + currency + '</li>';
                                warningHtml += '<li><?= __('payment_date') ?>: ' + data.existingPayment.payment_date + '</li>';
                                warningHtml += '</ul>';
                                warningHtml += '</div>';
                                
                                // Remove any existing warning
                                $('.salary-already-paid-warning').remove();
                                
                                // Add warning before the form
                                $('form').before(warningHtml);
                                
                                // Disable form elements if payment type is regular
                                if ($('#payment_type').val() === 'regular') {
                                    $('#amount').prop('disabled', true);
                                    $('button[type="submit"]').prop('disabled', true);
                                }
                            } else {
                                // Remove any existing warning
                                $('.salary-already-paid-warning').remove();
                                
                                // Enable form elements
                                $('#amount').prop('disabled', false);
                                $('button[type="submit"]').prop('disabled', false);
                            }
                            
                            // Update form fields
                            $('#amount').val(remainingAmount.toFixed(2));
                            $('#currency').val(currency);
                            
                            // Show breakdown
                            var breakdownHtml = '<div class="salary-breakdown mt-2 p-3 border rounded bg-light">';
                            breakdownHtml += '<h6 class="text-primary mb-2"><i class="feather icon-list mr-1"></i><?= __('salary_breakdown') ?></h6>';
                            breakdownHtml += '<div class="table-responsive">';
                            breakdownHtml += '<table class="table table-sm table-borderless mb-0">';
                            breakdownHtml += '<tr><td><?= __('base_salary') ?></td><td class="text-right">+ ' + baseSalary.toFixed(2) + ' ' + currency + '</td></tr>';
                            if(totalBonuses > 0) {
                                breakdownHtml += '<tr class="text-success"><td><?= __('bonuses') ?></td><td class="text-right">+ ' + totalBonuses.toFixed(2) + ' ' + currency + '</td></tr>';
                            }
                            if(totalDeductions > 0) {
                                breakdownHtml += '<tr class="text-danger"><td><?= __('deductions') ?></td><td class="text-right">- ' + totalDeductions.toFixed(2) + ' ' + currency + '</td></tr>';
                            }
                            if(totalAdvances > 0) {
                                breakdownHtml += '<tr class="text-warning"><td><?= __('advances_this_month') ?></td><td class="text-right">- ' + totalAdvances.toFixed(2) + ' ' + currency + '</td></tr>';
                            }
                            breakdownHtml += '<tr class="font-weight-bold border-top"><td><?= __('remaining_amount') ?></td><td class="text-right">' + remainingAmount.toFixed(2) + ' ' + currency + '</td></tr>';
                            breakdownHtml += '</table>';
                            breakdownHtml += '</div>';
                            breakdownHtml += '</div>';
                            
                            // Remove any existing breakdown and add new one
                            $('.salary-breakdown').remove();
                            $('#amount').parent().after(breakdownHtml);
                            
                            // Store the values for validation
                            $('#amount').data('max-amount', remainingAmount);
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            // Log the actual response for debugging
                            console.log('Raw response:', xhr.responseText);
                            
                            // If error, just set base salary
                            $('#amount').val(baseSalary.toFixed(2));
                            $('#currency').val(currency);
                            
                            // Show error message to user
                            alert('<?= __('error_fetching_salary_details') ?>');
                        }
                    });
                }
            });
            
            // Add amount validation
            $('#amount').on('input', function() {
                var enteredAmount = parseFloat($(this).val()) || 0;
                var maxAmount = parseFloat($(this).data('max-amount')) || 0;
                
                if(enteredAmount > maxAmount) {
                    $(this).addClass('is-invalid');
                    if(!$(this).next('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Amount cannot exceed ' + maxAmount.toFixed(2) + '</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            });

            // Update total amount hint when amount or months change
            function updateTotalHint() {
                var amount = parseFloat($('#amount').val()) || 0;
                var months = parseInt($('#months_to_pay').val(), 10) || 1;
                var currency = $('#currency').val();
                var total = amount * months;
                if (amount > 0 && months > 0) {
                    $('#totalAmountHint').text('<?= __('total_payment') ?>: ' + total.toFixed(2) + ' ' + currency + ' (<?= __('months') ?>: ' + months + ')');
                } else {
                    $('#totalAmountHint').text('');
                }
            }
            $('#amount, #months_to_pay, #currency').on('input change', updateTotalHint);
            updateTotalHint();
            
            // Update calculations when payment month changes
            $('#payment_for_month').change(function() {
                $('#user_id').trigger('change');
            });
            
            // Validate if account has enough balance
            $('#main_account_id, #amount, #currency').change(function() {
                var selectedAccount = $('#main_account_id').find('option:selected');
                var amount = parseFloat($('#amount').val()) || 0;
                var currency = $('#currency').val();
                
                if (selectedAccount.val() && amount > 0) {
                    var accountBalance = (currency == 'USD') ? 
                        parseFloat(selectedAccount.data('usd')) : 
                        parseFloat(selectedAccount.data('afs'));
                    
                    if (amount > accountBalance) {
                        alert('<?= __('warning_the_selected_account_does_not_have_enough_balance_for_this_payment') ?>');
                    }
                }
            });
            
            // Handle payment type
            $('#payment_type').change(function() {
                var paymentType = $(this).val();
                var description = $('#description');
                
                if (paymentType == 'regular') {
                    description.val('<?= __('regular_salary_payment') ?>');
                } else if (paymentType == 'bonus') {
                    description.val('<?= __('bonus_payment') ?>');
                } else if (paymentType == 'advance') {
                    description.val('<?= __('salary_advance') ?>');
                } else {
                    description.val('');
                }
            });

            // Handle delete payment
            $(document).on('click', '.delete-payment', function() {
                var button = $(this);
                var paymentId = button.data('payment-id');
                var amount = button.data('amount');
                var mainAccountId = button.data('main-account-id');
                
                if (confirm('<?= __('are_you_sure_you_want_to_delete_this_payment') ?>')) {
                    $.ajax({
                        url: 'delete_salary_payment.php',
                        type: 'POST',
                        data: {
                            payment_id: paymentId,
                            amount: amount,
                            main_account_id: mainAccountId
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if (data.success) {
                                    alert(data.message);
                                    // Reload the page to refresh the table
                                    location.reload();
                                } else {
                                    alert(data.message || '<?= __('failed_to_delete_payment') ?>');
                                }
                            } catch(e) {
                                console.error('Error parsing response:', e);
                                alert('<?= __('an_error_occurred_while_deleting_the_payment') ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            alert('<?= __('an_error_occurred_while_deleting_the_payment') ?>');
                        }
                    });
                }
            });
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
    
    <!-- Salary Payment Edit Script -->
    <script>
        $(document).ready(function() {
            // Handle edit payment button click
            $(document).on('click', '.edit-payment', function() {
                // Get data from button attributes
                var paymentId = $(this).data('payment-id');
                var amount = $(this).data('amount');
                var currency = $(this).data('currency');
                var date = $(this).data('date');
                var description = $(this).data('description');
                var paymentType = $(this).data('payment-type');
                var userId = $(this).data('user-id');
                var mainAccountId = $(this).data('main-account-id');
                
                // Populate the form fields
                $('#edit_payment_id').val(paymentId);
                $('#edit_user_id').val(userId);
                $('#edit_payment_amount').val(amount);
                $('#edit_original_amount').val(amount);
                $('#edit_currency').val(currency);
                $('#edit_currency_display').text(currency);
                $('#edit_payment_date').val(date);
                $('#edit_payment_description').val(description);
                $('#edit_payment_type').val(paymentType);
                $('#edit_main_account_id').val(mainAccountId);
                
                // Show the modal
                $('#editPaymentModal').modal('show');
            });
            
            // Handle save changes button click
            $('#savePaymentChanges').click(function() {
                // Validate form
                var form = $('#editPaymentForm');
                
                if (!form[0].checkValidity()) {
                    form[0].reportValidity();
                    return;
                }
                
                // Get form data
                var formData = form.serialize();
                
                // Show loading state
                var saveBtn = $(this);
                var originalText = saveBtn.html();
                saveBtn.html('<i class="feather icon-loader mr-2 spinner"></i><?= __("saving") ?>...');
                saveBtn.prop('disabled', true);
                
                // Send AJAX request
                $.ajax({
                    url: 'update_salary_payment.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            alert('<?= __("payment_updated_successfully") ?>');
                            
                            // Close modal and reload page to refresh data
                            $('#editPaymentModal').modal('hide');
                            location.reload();
                        } else {
                            // Show error message
                            alert(response.message || '<?= __("failed_to_update_payment") ?>');
                            
                            // Reset button state
                            saveBtn.html(originalText);
                            saveBtn.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        alert('<?= __("an_error_occurred_while_updating_the_payment") ?>');
                        
                        // Reset button state
                        saveBtn.html(originalText);
                        saveBtn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html> 