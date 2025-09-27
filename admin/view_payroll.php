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
$tenant_id = $_SESSION['tenant_id'];
// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log the error
        error_log("User not found: " . $_SESSION['user_id'] . " - IP: " . $_SERVER['REMOTE_ADDR']);
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    // Log the error without exposing details
    error_log("Database Error in dashboard.php: " . $e->getMessage());
    
    $user = null;
    // Show generic error message
    $error_message = "A system error occurred. Please try again later.";
}

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}
$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
    $imagePath = "../assets/images/user/" . $profilePic;
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
                
                // Calculate new balance based on currency
                $balance = ($currency == "USD") ? $usd_balance : $afs_balance;
                $new_balance = $balance - $amount;
                
                // Update main account balance
                $update_sql = ($currency == "USD") 
                    ? "UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?"
                    : "UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?";
                    
                $update_stmt = mysqli_prepare($conection_db, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "di", $amount, $main_account_id, $tenant_id);
                mysqli_stmt_execute($update_stmt);
                
                // Insert into salary_payments
                $insert_sql = "INSERT INTO salary_payments (user_id, main_account_id, amount, currency, payment_date, 
                               payment_for_month, payment_type, description, receipt) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                               
                $insert_stmt = mysqli_prepare($conection_db, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "iidssssssi", $user_id, $main_account_id, $amount, $currency, 
                                      $payment_date, $payment_for_month, $payment_type, $description, $receipt, $tenant_id);
                mysqli_stmt_execute($insert_stmt);
                
                // Get the inserted payment ID
                $payment_id = mysqli_insert_id($conection_db);
                
                // Insert into main_account_transactions
                $transaction_sql = "INSERT INTO main_account_transactions (main_account_id, type, amount, balance, currency, 
                                   description, transaction_of, reference_id, receipt, tenant_id) 
                                   VALUES (?, 'debit', ?, ?, ?, ?, 'salary_payment', ?, ?, ?)";
                                   
                $transaction_stmt = mysqli_prepare($conection_db, $transaction_sql);
                mysqli_stmt_bind_param($transaction_stmt, "iddsssssi", $main_account_id, $amount, $new_balance, $currency, 
                                     $description, $payment_id, $receipt, $tenant_id);
                mysqli_stmt_execute($transaction_stmt);
                
                // If this is regular payment, check for any advance payment to be deducted
                if ($payment_type == 'regular') {
                    // Check for any pending advances
                    $advance_sql = "SELECT id, amount, amount_paid FROM salary_advances 
                                   WHERE user_id = ? AND currency = ? AND repayment_status != 'paid' AND tenant_id = ?";
                    $advance_stmt = mysqli_prepare($conection_db, $advance_sql);
                    mysqli_stmt_bind_param($advance_stmt, "isi", $user_id, $currency, $tenant_id);
                    mysqli_stmt_execute($advance_stmt);
                    $advance_result = mysqli_stmt_get_result($advance_stmt);
                    
                    while ($advance_row = mysqli_fetch_assoc($advance_result)) {
                        $advance_id = $advance_row['id'];
                        $advance_amount = $advance_row['amount'];
                        $amount_paid = $advance_row['amount_paid'];
                        $remaining = $advance_amount - $amount_paid;
                        
                        // Determine how much to deduct from this payment
                        $deduction = min($amount, $remaining);
                        
                        if ($deduction > 0) {
                            // Update the advance record
                            $new_paid = $amount_paid + $deduction;
                            $status = ($new_paid >= $advance_amount) ? 'paid' : 'partially_paid';
                            
                            $update_advance_sql = "UPDATE salary_advances SET amount_paid = ?, repayment_status = ? WHERE id = ? AND tenant_id = ?";
                            $update_advance_stmt = mysqli_prepare($conection_db, $update_advance_sql);
                            mysqli_stmt_bind_param($update_advance_stmt, "dsii", $new_paid, $status, $advance_id, $tenant_id);
                            mysqli_stmt_execute($update_advance_stmt);
                        }
                    }
                }
                
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

<!DOCTYPE html>
<html lang="en">


<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?></title>
  
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="<?= htmlspecialchars($settings['description'] ?? '') ?>" />
    <meta name="keywords" content="<?= htmlspecialchars($settings['keywords'] ?? '') ?>"/>
    <meta name="author" content="<?= htmlspecialchars($settings['author'] ?? 'CodedThemes') ?>"/>

    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- Feather Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons-css@1.2.0/css/feather.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->
    <!-- [ navigation menu ] start -->
    <?php include("../../includes/admin_sidebar.php"); ?>
    <!-- [ navigation menu ] end -->
    <!-- [ Header ] start -->
    <?php include("../../includes/admin_header.php"); ?>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10">Salary Payment</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php">Salary Management</a></li>
                                <li class="breadcrumb-item"><a href="#!">Salary Payment</a></li>
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
                            <h5>Process Salary Payment</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                            <div class="alert alert-success" role="alert">
                                Salary payment processed successfully!
                            </div>
                            <?php endif; ?>
                            
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="user_id">Employee</label>
                                            <select class="form-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" id="user_id" name="user_id" required>
                                                <option value="">Select Employee</option>
                                                <?php
                                                // Get all employees with salary records
                                                $sql = "SELECT u.id, u.name, sm.base_salary, sm.currency 
                                                        FROM users u 
                                                        JOIN salary_management sm ON u.id = sm.user_id 
                                                        WHERE sm.status = 'active' AND u.tenant_id = $tenant_id
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
                                            <label for="main_account_id">Select Account</label>
                                            <select class="form-control <?php echo (!empty($main_account_id_err)) ? 'is-invalid' : ''; ?>" id="main_account_id" name="main_account_id" required>
                                                <option value="">Select Account</option>
                                                <?php
                                                // Get all main accounts
                                                $sql = "SELECT id, name, usd_balance, afs_balance FROM main_account WHERE tenant_id = ?";
                                                $result = mysqli_query($conection_db, $sql);
                                                while ($row = mysqli_fetch_array($result)) {
                                                    echo "<option value='" . $row['id'] . "' data-usd='" . $row['usd_balance'] . "' data-afs='" . $row['afs_balance'] . "'>" . $row['name'] . " (USD: " . number_format($row['usd_balance'], 2) . ", AFS: " . number_format($row['afs_balance'], 2) . ")</option>";
                                                }
                                                ?>
                                            </select>
                                            <div class="invalid-feedback"><?php echo $main_account_id_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="payment_for_month">Payment For Month</label>
                                            <input type="month" class="form-control <?php echo (!empty($payment_for_month_err)) ? 'is-invalid' : ''; ?>" id="payment_for_month" name="payment_for_month" value="<?php echo date('Y-m'); ?>" required>
                                            <div class="invalid-feedback"><?php echo $payment_for_month_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="amount">Amount</label>
                                            <input type="number" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" step="0.01" value="<?php echo $amount; ?>" required>
                                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="currency">Currency</label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo ($currency == "USD") ? "selected" : ""; ?>>USD</option>
                                                <option value="AFS" <?php echo ($currency == "AFS") ? "selected" : ""; ?>>AFS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="payment_type">Payment Type</label>
                                            <select class="form-control" id="payment_type" name="payment_type">
                                                <option value="regular" <?php echo ($payment_type == "regular") ? "selected" : ""; ?>>Regular Salary</option>
                                                <option value="bonus" <?php echo ($payment_type == "bonus") ? "selected" : ""; ?>>Bonus</option>
                                                <option value="advance" <?php echo ($payment_type == "advance") ? "selected" : ""; ?>>Advance</option>
                                                <option value="other" <?php echo ($payment_type == "other") ? "selected" : ""; ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <input type="text" class="form-control" id="description" name="description" value="<?php echo $description; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Process Payment</button>
                                        <a href="salary_management.php" class="btn btn-secondary">Back to Salary Management</a>
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
                            <h5>Salary Payment History</h5>
                        </div>
                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <table id="payment-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Employee</th>
                                            <th>Account</th>
                                            <th>Amount</th>
                                            <th>Currency</th>
                                            <th>Type</th>
                                            <th>Payment Date</th>
                                            <th>For Month</th>
                                            <th>Receipt</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all salary payments
                                        $sql = "SELECT sp.*, u.name as employee_name, ma.name as account_name 
                                                FROM salary_payments sp 
                                                JOIN users u ON sp.user_id = u.id 
                                                JOIN main_account ma ON sp.main_account_id = ma.id
                                                WHERE sp.tenant_id = $tenant_id
                                                ORDER BY sp.created_at DESC";

                                        $result = mysqli_query($conection_db, $sql);
                                        while ($row = mysqli_fetch_array($result)) {
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . $row['employee_name'] . "</td>";
                                            echo "<td>" . $row['account_name'] . "</td>";
                                            echo "<td>" . number_format($row['amount'], 2) . "</td>";
                                            echo "<td>" . $row['currency'] . "</td>";
                                            echo "<td>" . ucfirst($row['payment_type']) . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['payment_date'])) . "</td>";
                                            echo "<td>" . date('Y-m', strtotime($row['payment_for_month'])) . "</td>";
                                            echo "<td>" . $row['receipt'] . "</td>";
                                            echo "<td>" . $row['description'] . "</td>";
                                            echo "<td>
                                                    <button type='button' 
                                                            class='btn btn-danger btn-sm delete-payment'
                                                            data-payment-id='" . $row['id'] . "'
                                                            data-amount='" . $row['amount'] . "'
                                                            data-main-account-id='" . $row['main_account_id'] . "'>
                                                        <i class='feather icon-trash-2'></i> Delete
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

             <!-- Profile Modal -->
             <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i>User Profile
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
                                <label class="text-muted mb-1">Email</label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Phone</label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Join Date</label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Address</label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3">Account Information</h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0">Account Created</p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                
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
                                                    <i class="feather icon-settings mr-2"></i>Profile Settings
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
                                                        <small class="text-muted d-block mt-2">Click to change profile picture</small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i>Personal Information
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName">Full Name</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail">Email Address</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone">Phone Number</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress">Address</label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i>Change Password
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword">Current Password</label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword">New Password</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword">Confirm Password</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i>Cancel
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i>Save Changes
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
            
            // Auto-fill salary amount when employee is selected
            $('#user_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var userId = selectedOption.val();
                var baseSalary = selectedOption.data('base-salary');
                var currency = selectedOption.data('currency');
                
                // Clear previous breakdown
                $('.salary-breakdown').remove();
                
                if (baseSalary && userId) {
                    // Get advances, deductions, and bonuses via AJAX
                    $.ajax({
                        url: 'get_salary_details.php',
                        type: 'POST',
                        data: {
                            user_id: userId,
                            currency: currency,
                            payment_for_month: $('#payment_for_month').val()
                        },
                        success: function(response) {
                            try {
                                var data = JSON.parse(response);
                                if(data.error) {
                                    console.error(data.error);
                                    return;
                                }
                                
                                var totalAdvances = parseFloat(data.totalAdvances) || 0;
                                var totalDeductions = parseFloat(data.totalDeductions) || 0;
                                var totalBonuses = parseFloat(data.totalBonuses) || 0;
                                
                                // Calculate remaining amount
                                var remainingAmount = baseSalary - totalAdvances - totalDeductions + totalBonuses;
                                remainingAmount = Math.max(0, remainingAmount); // Ensure it's not negative
                                
                                // Update form fields
                                $('#amount').val(remainingAmount.toFixed(2));
                                $('#currency').val(currency);
                                
                                // Show breakdown
                                var breakdownHtml = '<div class="salary-breakdown mt-2">';
                                breakdownHtml += '<small class="text-muted d-block">';
                                breakdownHtml += '<strong>Salary Breakdown:</strong><br>';
                                breakdownHtml += 'Base Salary: ' + baseSalary.toFixed(2) + '<br>';
                                if(totalBonuses > 0) breakdownHtml += 'Bonuses: +' + totalBonuses.toFixed(2) + '<br>';
                                if(totalDeductions > 0) breakdownHtml += 'Deductions: -' + totalDeductions.toFixed(2) + '<br>';
                                if(totalAdvances > 0) breakdownHtml += 'Advances: -' + totalAdvances.toFixed(2) + '<br>';
                                breakdownHtml += '<strong>Remaining: ' + remainingAmount.toFixed(2) + '</strong>';
                                breakdownHtml += '</small>';
                                breakdownHtml += '</div>';
                                
                                // Remove any existing breakdown and add new one
                                $('.salary-breakdown').remove();
                                $('#amount').parent().append(breakdownHtml);
                                
                                // Store the values for validation
                                $('#amount').data('max-amount', remainingAmount);
                            } catch(e) {
                                console.error('Error parsing response:', e);
                                // If error, just set base salary
                                $('#amount').val(baseSalary.toFixed(2));
                                $('#currency').val(currency);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            // If error, just set base salary
                            $('#amount').val(baseSalary.toFixed(2));
                            $('#currency').val(currency);
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
                        alert('Warning: The selected account does not have enough balance for this payment!');
                    }
                }
            });
            
            // Handle payment type
            $('#payment_type').change(function() {
                var paymentType = $(this).val();
                var description = $('#description');
                
                if (paymentType == 'regular') {
                    description.val('Regular salary payment');
                } else if (paymentType == 'bonus') {
                    description.val('Bonus payment');
                } else if (paymentType == 'advance') {
                    description.val('Salary advance');
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
                
                if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
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
                                    alert(data.message || 'Failed to delete payment');
                                }
                            } catch(e) {
                                console.error('Error parsing response:', e);
                                alert('An error occurred while deleting the payment');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', error);
                            alert('An error occurred while deleting the payment');
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
                        alert('Please enter your current password');
                        return;
                    }
                    if (!newPassword) {
                        alert('Please enter a new password');
                        return;
                    }
                    if (!confirmPassword) {
                        alert('Please confirm your new password');
                        return;
                    }
                    if (newPassword !== confirmPassword) {
                        alert('New passwords do not match');
                        return;
                    }
                    if (newPassword.length < 6) {
                        alert('New password must be at least 6 characters long');
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
                        alert(data.message || 'Failed to update profile');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the profile');
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