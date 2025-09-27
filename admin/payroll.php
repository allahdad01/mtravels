<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../../access_denied.php");
    exit;
}

// Include config file
require_once "../../includes/db.php";

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
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?", [$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}
$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../../assets/images/user/" . $profilePic;

// Define variables and initialize with empty values
$pay_period = $currency = "";
$pay_period_err = $currency_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate pay period
    if (empty($_POST["pay_period"])) {
        $pay_period_err = "Please select the pay period.";
    } else {
        $pay_period = $_POST["pay_period"];
        
        // Check if payroll already exists for this period and currency
        $currency = $_POST["currency"];
        $sql = "SELECT id FROM payroll_records WHERE pay_period = ? AND currency = ? AND tenant_id = ?";
        
        if ($stmt = mysqli_prepare($conection_db, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssi", $pay_period, $currency, $tenant_id);
            
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $pay_period_err = "Payroll already exists for this period and currency.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
    
    // Check input errors before generating payroll
    if (empty($pay_period_err)) {
        // Start transaction
        mysqli_begin_transaction($conection_db);
        
        try {
            // Get all active employees with their salaries for the selected currency
            $sql = "SELECT u.id, u.name, sm.base_salary
                   FROM users u 
                   JOIN salary_management sm ON u.id = sm.user_id 
                   WHERE sm.status = 'active' AND sm.currency = ? AND sm.tenant_id = ?";
            
            if ($stmt = mysqli_prepare($conection_db, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $currency, $tenant_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                $total_payroll = 0;
                $employees = [];
                
                // Calculate total payroll and store employee data
                while ($row = mysqli_fetch_assoc($result)) {
                    $employee = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'base_salary' => $row['base_salary'],
                        'bonus' => 0,
                        'deductions' => 0,
                        'advance_deduction' => 0,
                        'net_salary' => $row['base_salary']
                    ];
                    
                    // Check for any unpaid advances to be deducted
                    $advance_sql = "SELECT id, amount, amount_paid 
                                   FROM salary_advances 
                                   WHERE user_id = ? AND currency = ? AND repayment_status != 'paid' AND tenant_id = ?";
                    $advance_stmt = mysqli_prepare($conection_db, $advance_sql);
                    mysqli_stmt_bind_param($advance_stmt, "isi", $row['id'], $currency, $tenant_id);
                    mysqli_stmt_execute($advance_stmt);
                    $advance_result = mysqli_stmt_get_result($advance_stmt);
                    
                    while ($advance = mysqli_fetch_assoc($advance_result)) {
                        $remaining = $advance['amount'] - $advance['amount_paid'];
                        // Deduct up to 30% of base salary for advances
                        $max_deduction = $row['base_salary'] * 0.3;
                        $deduction = min($remaining, $max_deduction);
                        
                        $employee['advance_deduction'] += $deduction;
                    }
                    
                    // Calculate net salary
                    $employee['net_salary'] = $row['base_salary'] + $employee['bonus'] - $employee['deductions'] - $employee['advance_deduction'];
                    $total_payroll += $employee['net_salary'];
                    
                    $employees[] = $employee;
                }
                
                // Insert payroll record
                $generated_date = date("Y-m-d");
                $generated_by = $_SESSION["user_id"];
                
                $payroll_sql = "INSERT INTO payroll_records (tenant_id, pay_period, generated_date, total_amount, currency, status, generated_by) 
                               VALUES (?, ?, ?, ?, ?, 'draft', ?)";
                
                if ($payroll_stmt = mysqli_prepare($conection_db, $payroll_sql)) {
                    mysqli_stmt_bind_param($payroll_stmt, "isssdsi", $tenant_id, $pay_period, $generated_date, $total_payroll, $currency, $generated_by);
                    mysqli_stmt_execute($payroll_stmt);
                    
                    $payroll_id = mysqli_insert_id($conection_db);
                    
                    // Insert payroll details for each employee
                    $detail_sql = "INSERT INTO payroll_details (tenant_id, payroll_id, user_id, base_salary, bonus, deductions, advance_deduction, net_salary) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    foreach ($employees as $employee) {
                        $detail_stmt = mysqli_prepare($conection_db, $detail_sql);
                        mysqli_stmt_bind_param($detail_stmt, "iiiddddd", $tenant_id, $payroll_id, $employee['id'], $employee['base_salary'], 
                                             $employee['bonus'], $employee['deductions'], $employee['advance_deduction'], 
                                             $employee['net_salary']);
                        mysqli_stmt_execute($detail_stmt);
                    }
                    
                    // Commit transaction
                    mysqli_commit($conection_db);
                    
                    // Redirect to payroll view page
                    header("location: view_payroll.php?id=$payroll_id");
                    exit();
                }
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
  <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon">
  <!-- fontawesome icon -->
  <link rel="stylesheet" href="../../assets/fonts/fontawesome/css/fontawesome-all.min.css">
  <!-- Feather Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons-css@1.2.0/css/feather.min.css">
  <!-- animation css -->
  <link rel="stylesheet" href="../../assets/plugins/animation/css/animate.min.css">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">
  <!-- vendor css -->
  <link rel="stylesheet" href="../../assets/css/style.css">
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
                                <h5 class="m-b-10">Payroll Generation</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../index.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item"><a href="salary_management.php">Salary Management</a></li>
                                <li class="breadcrumb-item"><a href="#!">Payroll Generation</a></li>
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
                            <h5>Generate Monthly Payroll</h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="pay_period">Pay Period</label>
                                            <input type="month" class="form-control <?php echo (!empty($pay_period_err)) ? 'is-invalid' : ''; ?>" id="pay_period" name="pay_period" value="<?php echo date('Y-m'); ?>" required>
                                            <div class="invalid-feedback"><?php echo $pay_period_err; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="currency">Currency</label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo ($currency == "USD") ? "selected" : ""; ?>>USD</option>
                                                <option value="AFS" <?php echo ($currency == "AFS") ? "selected" : ""; ?>>AFS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Generate Payroll</button>
                                        <a href="salary_management.php" class="btn btn-secondary">Back to Salary Management</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- [ form-element ] end -->

                <!-- [ Payroll History ] start -->
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Payroll History</h5>
                        </div>
                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <table id="payroll-list-table" class="table nowrap">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Pay Period</th>
                                            <th>Generated Date</th>
                                            <th>Currency</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Generated By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get all payroll records
                                        $sql = "SELECT pr.*, u.name as generated_by_name 
                                               FROM payroll_records pr 
                                               JOIN users u ON pr.generated_by = u.id 
                                               ORDER BY pr.created_at DESC";
                                        
                                        $result = mysqli_query($conection_db, $sql);
                                        while ($row = mysqli_fetch_array($result)) {
                                            $status_class = "";
                                            switch($row['status']) {
                                                case 'paid':
                                                    $status_class = "badge-success";
                                                    break;
                                                case 'processed':
                                                    $status_class = "badge-primary";
                                                    break;
                                                default:
                                                    $status_class = "badge-warning";
                                                    break;
                                            }
                                            
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . $row['pay_period'] . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['generated_date'])) . "</td>";
                                            echo "<td>" . $row['currency'] . "</td>";
                                            echo "<td>" . number_format($row['total_amount'], 2) . "</td>";
                                            echo "<td><span class='badge " . $status_class . "'>" . ucfirst($row['status']) . "</span></td>";
                                            echo "<td>" . $row['generated_by_name'] . "</td>";
                                            echo "<td>";
                                            echo "<a href='view_payroll.php?id=" . $row['id'] . "' class='btn btn-info btn-sm'><i class='feather icon-eye'></i></a> ";
                                            
                                            if ($row['status'] == 'draft') {
                                                echo "<a href='process_payroll.php?id=" . $row['id'] . "' class='btn btn-success btn-sm'><i class='feather icon-check'></i></a> ";
                                                echo "<a href='delete_payroll.php?id=" . $row['id'] . "' class='btn btn-danger btn-sm'><i class='feather icon-trash'></i></a>";
                                            }
                                            
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
                <!-- [ Payroll History ] end -->
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
<?php include '../../includes/admin_footer.php'; ?>

    <!-- Required Js -->
  
    
    <!-- Custom scripts -->
    <script src="../../assets/js/vendor-all.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/js/ripple.js"></script>
    <script src="../../assets/js/pcoded.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            try {
                $('#payroll-list-table').DataTable();
            } catch(e) {
                console.error("DataTable error:", e);
            }
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