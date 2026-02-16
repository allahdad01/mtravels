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
$main_account_id = $amount = $currency = $description = "";
$main_account_id_err = $amount_err = $currency_err = $description_err = "";

// Generate receipt number
function generateReceiptNumber() {
    return "SA" . date("YmdHis");
}

// Check if user_id is passed in the URL
if (isset($_GET["advance_user_id"]) && !empty(trim($_GET["advance_user_id"]))) {
    $advance_user_id = trim($_GET["advance_user_id"]);
    
    // Get user information
    $sql = "SELECT u.name, sm.base_salary, sm.currency
            FROM users u
            JOIN salary_management sm ON u.id = sm.user_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $employee_name = $result["name"];
            $current_salary = $result["base_salary"];
            $default_currency = $result["currency"];
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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET["advance_user_id"])) {
    $advance_user_id = trim($_GET["advance_user_id"]); // Get the user_id from URL parameter
    
    // Validate main account
    if (empty($_POST["main_account_id"])) {
        $main_account_id_err = "Please select a main account.";
    } else {
        $main_account_id = $_POST["main_account_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the advance amount.";
    } else if (!is_numeric($_POST["amount"]) || floatval($_POST["amount"]) <= 0) {
        $amount_err = "Advance amount must be a positive number.";
    } else {
        $amount = floatval($_POST["amount"]);
        
        // Check if amount is reasonable (not more than 3x monthly salary)
        if ($amount > ($current_salary * 3)) {
            $amount_err = "Advance amount exceeds 3 times monthly salary.";
        }
    }
    
    // Set other values
    $currency = $_POST["currency"];
    $description = $_POST["description"];
    $advance_date = date("Y-m-d");
    $receipt = generateReceiptNumber();
    
    // Check input errors before inserting in database
    if (empty($main_account_id_err) && empty($amount_err)) {
        // Start transaction
        $pdo->beginTransaction();

        try {
            // Get current main account balance
            $sql = "SELECT usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $usd_balance = $result['usd_balance'];
                $afs_balance = $result['afs_balance'];
                
                // Calculate new balance based on currency
                $balance = ($currency == "USD") ? $usd_balance : $afs_balance;
                $new_balance = $balance - $amount;
                
                // Check if account has enough balance
                if ($new_balance < 0) {
                    throw new Exception("Account does not have enough balance.");
                }
                
                // Update main account balance
                $update_sql = ($currency == "USD")
                    ? "UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    : "UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";

                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $update_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $update_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $update_stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Insert into salary_advances
                $insert_sql = "INSERT INTO salary_advances (user_id, main_account_id, amount, currency, advance_date,
                              description, receipt, tenant_id, branch_id)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(3, $amount, PDO::PARAM_STR);
                $insert_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $insert_stmt->bindParam(5, $advance_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(6, $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(7, $receipt, PDO::PARAM_STR);
                $insert_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $insert_stmt->execute();

                // Get the inserted advance ID
                $advance_id = $pdo->lastInsertId();

                // Also insert into salary_payments as an advance payment
                $payment_sql = "INSERT INTO salary_payments (user_id, main_account_id, amount, currency, payment_date,
                              payment_for_month, payment_type, description, receipt, tenant_id, branch_id)
                              VALUES (?, ?, ?, ?, ?, ?, 'advance', ?, ?, ?, ?)";

                $payment_stmt = $pdo->prepare($payment_sql);
                $payment_for_month = date("Y-m-01"); // Current month
                $payment_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(3, $amount, PDO::PARAM_STR);
                $payment_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $payment_stmt->bindParam(5, $advance_date, PDO::PARAM_STR);
                $payment_stmt->bindParam(6, $payment_for_month, PDO::PARAM_STR);
                $payment_stmt->bindParam(7, $description, PDO::PARAM_STR);
                $payment_stmt->bindParam(8, $receipt, PDO::PARAM_STR);
                $payment_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
                $payment_stmt->execute();

                // Get the inserted payment ID
                $payment_id = $pdo->lastInsertId();
                
                // Insert into main_account_transactions
                $transaction_sql = "INSERT INTO main_account_transactions (main_account_id, type, amount, balance, currency,
                                   description, transaction_of, reference_id, receipt, tenant_id, branch_id)
                                   VALUES (?, 'debit', ?, ?, ?, ?, 'salary_payment', ?, ?, ?, ?)";

                $transaction_stmt = $pdo->prepare($transaction_sql);
                $transaction_stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(2, $amount, PDO::PARAM_STR);
                $transaction_stmt->bindParam(3, $new_balance, PDO::PARAM_STR);
                $transaction_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $transaction_stmt->bindParam(5, $description, PDO::PARAM_STR);
                $transaction_stmt->bindParam(6, $payment_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(7, $receipt, PDO::PARAM_STR);
                $transaction_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $transaction_stmt->execute();

                // Commit transaction
                $pdo->commit();
                
                // Send email notification to employee
                require_once '../includes/functions.php';

                // Get employee email
                $email_sql = "SELECT email FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $email_stmt = $pdo->prepare($email_sql);
                $email_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $email_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $email_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $email_stmt->execute();
                $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);

                if ($email_result) {
                    $employee_email = $email_result['email'];
                    
                    if (!empty($employee_email)) {
                        sendSalaryAdvanceNotification(
                            $employee_email,
                            $employee_name,
                            $advance_id,
                            $amount,
                            $currency,
                            $advance_date,
                            $description,
                            $receipt
                        );
                    }
                }

                // Redirect back to the same employee's page with success message
                header("location: salary_advances.php?advance_user_id=" . $advance_user_id . "&success=1");
                exit();
            } else {
                throw new Exception("Main account not found.");
            }
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
    <link rel="stylesheet" href="../css/general/modal-styles.css">
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
                                <h5 class="m-b-10"><?= __('salary_advances') ?></h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php"><?= __('salary_management') ?></a></li>
                                <li class="breadcrumb-item"><a href="#!"><?= __('salary_advances') ?></a></li>
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
                            <h5><?= __('salary_advance_for') ?> <?php echo $employee_name; ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                <?= __('salary_advance_processed_successfully') ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6><?= __('employee') ?>: <strong><?php echo $employee_name; ?></strong></h6>
                                </div>
                                <div class="col-md-6">
                                    <h6><?= __('monthly_salary') ?>: <strong><?php echo number_format($current_salary, 2) . " " . $default_currency; ?></strong></h6>
                                </div>
                            </div>
                            
                            <form action="salary_advances.php?advance_user_id=<?php echo $advance_user_id; ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="main_account_id"><?= __('select_account') ?></label>
                                            <select class="form-control <?php echo (!empty($main_account_id_err)) ? 'is-invalid' : ''; ?>" id="main_account_id" name="main_account_id" required>
                                                <option value=""><?= __('select_account') ?></option>
                                                <?php
                                                // Get all main accounts
                                                $sql = "SELECT id, name, usd_balance, afs_balance FROM main_account";
                                                $result = $pdo->query($sql);
                                                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                                                    echo "<option value='" . $row['id'] . "' data-usd='" . $row['usd_balance'] . "' data-afs='" . $row['afs_balance'] . "'>" . $row['name'] . " (USD: " . number_format($row['usd_balance'], 2) . ", AFS: " . number_format($row['afs_balance'], 2) . ")</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $main_account_id_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="amount"><?= __('advance_amount') ?></label>
                                            <input type="number" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" step="0.01" value="<?php echo $amount; ?>" required>
                                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                                            <small class="form-text text-muted">Max: <span id="max-advance"><?php echo number_format($current_salary * 3, 2); ?></span></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="currency"><?= __('currency') ?></label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo ($default_currency == "USD") ? "selected" : ""; ?>>USD</option>
                                                <option value="AFS" <?php echo ($default_currency == "AFS") ? "selected" : ""; ?>>AFS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="description"><?= __('description') ?></label>
                                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary"><?= __('process_advance') ?></button>
                                        <a href="salary_management.php" class="btn btn-secondary"><?= __('back_to_salary_management') ?></a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->

                <!-- [ Advances History ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('salary_advances_history') ?></h5>
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
                                <table id="advances-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th><?= __('id') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('currency') ?></th>
                                            <th><?= __('advance_date') ?></th>
                                            <th><?= __('description') ?></th>
                                            <th><?= __('receipt') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all advances for this user
                                        $sql = "SELECT * FROM salary_advances WHERE user_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY created_at DESC";

                                        $stmt = $pdo->prepare($sql);
                                        $stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                                        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($result as $row) {
                                                    $status_class = "";
                                                    switch($row['repayment_status']) {
                                                        case 'paid':
                                                            $status_class = "badge-success";
                                                            break;
                                                        case 'partially_paid':
                                                            $status_class = "badge-warning";
                                                            break;
                                                        default:
                                                            $status_class = "badge-danger";
                                                            break;
                                                    }
                                                    
                                                    // Format the date for data-date attribute (YYYY-MM)
                                                    $year_month = date('Y-m', strtotime($row['advance_date']));
                                                    
                                                    echo "<tr data-date='" . $year_month . "'>";
                                                    echo "<td>" . $row['id'] . "</td>";
                                                    echo "<td>" . number_format($row['amount'], 2) . "</td>";
                                                    echo "<td>" . $row['currency'] . "</td>";
                                                    echo "<td>" . date('Y-m-d', strtotime($row['advance_date'])) . "</td>";
                                                    echo "<td>" . $row['description'] . "</td>";
                                                    echo "<td>" . $row['receipt'] . "</td>";
                                                    echo "</tr>";
                                                }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ Advances History ] end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </div>
    <!-- [ Main Content ] end -->


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- Include DataTables -->
    <script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="../assets/plugins/datatables/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize tooltips manually
            if ($.fn.tooltip) {
                $('[data-toggle="tooltip"]').tooltip();
            }
            
            // Simple jQuery filtering (no DataTables dependency)
            var $rows = $('#advances-list-table tbody tr');
            
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
                        var dateCell = $(this).find('td:eq(3)').text().trim();
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
            
            // Current salary value
            var currentSalary = <?php echo $current_salary; ?>;
            
            // Validate advance amount
            $('#amount').on('input', function() {
                var amount = parseFloat($(this).val()) || 0;
                var maxAdvance = currentSalary * 3;
                
                if (amount > maxAdvance) {
                    $(this).addClass('is-invalid');
                    $(this).next('.invalid-feedback').text('<?= __('advance_amount_exceeds_3_times_monthly_salary') ?>');
                } else {
                    $(this).removeClass('is-invalid');
                }
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
                        alert('<?= __('warning_the_selected_account_does_not_have_enough_balance_for_this_advance') ?>');
                    }
                }
            });
        });
    </script>

</body>
</html> 