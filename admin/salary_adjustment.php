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
$adjustment_type = $amount = $percentage = $effective_date = $reason = "";
$amount_err = $percentage_err = $effective_date_err = $reason_err = "";

// Check if user_id is passed in the URL
if (isset($_GET["adjustment_user_id"]) && !empty(trim($_GET["adjustment_user_id"]))) {
    $adjustment_user_id = trim($_GET["adjustment_user_id"]);
    
    // Get user information
    $sql = "SELECT u.name, sm.base_salary, sm.currency 
            FROM users u 
            JOIN salary_management sm ON u.id = sm.user_id 
            WHERE u.id = ? AND u.tenant_id = ?";
    
    if ($stmt = mysqli_prepare($conection_db, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $adjustment_user_id, $tenant_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
                $employee_name = $row["name"];
                $current_salary = $row["base_salary"];
                $currency = $row["currency"];
            } else {
                // URL doesn't contain valid id parameter
                header("location: salary_management.php");
                exit();
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
        
        mysqli_stmt_close($stmt);
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
        mysqli_begin_transaction($conection_db);
        
        try {
            // First, insert into salary_adjustments table
            $sql = "INSERT INTO salary_adjustments (user_id, adjustment_type, amount, percentage, effective_date, 
                   previous_salary, new_salary, reason, approved_by, tenant_id) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = mysqli_prepare($conection_db, $sql)) {
                // Get approved_by (current user ID)
                $approved_by = $_SESSION["user_id"];
                
                // Bind variables to the statement
                mysqli_stmt_bind_param($stmt, "issdsddssi", $adjustment_user_id, $adjustment_type, $amount, $percentage, 
                                     $effective_date, $current_salary, $new_salary, $reason, $approved_by, $tenant_id);
                
                // Execute the statement
                mysqli_stmt_execute($stmt);
                
                // Update the base salary in salary_management table
                $update_sql = "UPDATE salary_management SET base_salary = ? WHERE user_id = ? AND tenant_id = ?";
                
                if ($update_stmt = mysqli_prepare($conection_db, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "dii", $new_salary, $adjustment_user_id, $tenant_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
                
                // Commit transaction
                mysqli_commit($conection_db);
                
                // Redirect to success page
                header("location: salary_adjustment.php?adjustment_user_id=$adjustment_user_id&success=1");
                exit();
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
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
                                                WHERE sa.user_id = ? AND sa.tenant_id = ?
                                                ORDER BY sa.created_at DESC";
                                        
                                        if ($stmt = mysqli_prepare($conection_db, $sql)) {
                                            mysqli_stmt_bind_param($stmt, "ii", $adjustment_user_id, $tenant_id);
                                            
                                            if (mysqli_stmt_execute($stmt)) {
                                                $result = mysqli_stmt_get_result($stmt);
                                                
                                                while ($row = mysqli_fetch_array($result)) {
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
                                            }
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
</body>
</html> 