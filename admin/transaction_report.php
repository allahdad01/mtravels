<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

?>


<?php include '../includes/header.php'; ?>

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
                                    <h5 class="m-b-10"><?= __('transaction_management') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('transaction_reports') ?></a></li>
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
                                <div class="card">
                                    <!-- body -->

                                  
<div class="container mt-5">    
    <h3 class="text-center mb-4"><?= __('generate_transaction_report') ?></h3>

    <form id="reportForm">
    <div class="form-row">        
        <!-- Report Type Selection -->
        <div class="form-group col-md-2">
            <label for="reportType"><?= __('select_report_type') ?></label>
            <select id="reportType" class="form-control" onchange="loadOptions()">
                <option value=""><?= __('select') ?></option>
                <option value="ticket"><?= __('ticket') ?></option>
                <option value="supplier"><?= __('supplier') ?></option>
                <option value="main_account"><?= __('main_account') ?></option>
                <option value="client"><?= __('client') ?></option>
            </select>
        </div>

        <!-- Dynamic Dropdown for Selecting Entity -->
        <div class="form-group col-md-2" id="entitySelection" style="display: none;">
            <label for="entity"><?= __('select_entity') ?></label>
            <select id="entity" class="form-control"></select>
        </div>

        <!-- Report Category Selection -->
        <div class="form-group col-md-2" id="reportCategorySelection" style="display: none;">
            <label for="reportCategory"><?= __('select_report_category') ?></label>
            <select id="reportCategory" class="form-control">
                <option value="ticket"><?= __('ticket') ?></option>
                <option value="refund_ticket"><?= __('refund_ticket') ?></option>
                <option value="date_change_ticket"><?= __('date_change_ticket') ?></option>
                <option value="visa"><?= __('visa') ?></option>
                <option value="umrah"><?= __('umrah') ?></option>
            </select>
        </div>

        <!-- Date Range Selection -->
        <div class="form-group col-md-2">
            <label for="startDate"><?= __('start_date') ?></label>
            <input type="date" id="startDate" class="form-control">
        </div>
        <div class="form-group col-md-2">
            <label for="endDate"><?= __('end_date') ?></label>
            <input type="date" id="endDate" class="form-control">
        </div>

        <!-- Add Filter Button -->
        <div class="form-group col-md-2">
            <button type="button" class="btn btn-info" onclick="filterResults()"><?= __('filter_results') ?></button>
        </div>

</div>
   
<!-- Add Results Container -->
        <div id="resultsContainer" style="display: none;">
            <!-- Table will be inserted here -->
        </div>

        <!-- Export Buttons -->
        <div id="exportButtons" style="display: none;" class="mt-3">
            <button type="button" class="btn btn-danger" onclick="exportReport('pdf')"><?= __('export_pdf') ?></button>
            <button type="button" class="btn btn-success" onclick="exportReport('excel')"><?= __('export_excel') ?></button>
            <button type="button" class="btn btn-primary" onclick="exportReport('word')"><?= __('export_word') ?></button>
        </div>
    </form>
</div>                                  
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
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
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


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
                        <script>
function loadOptions() {
    var reportType = document.getElementById("reportType").value;
    var entitySelection = document.getElementById("entitySelection");
    var entityDropdown = document.getElementById("entity");
    var reportCategorySelection = document.getElementById("reportCategorySelection");

    entitySelection.style.display = "none";
    entityDropdown.innerHTML = "";
    reportCategorySelection.style.display = "none";

    if (reportType === "supplier" || reportType === "main_account" || reportType === "client") {
        entitySelection.style.display = "block";

        $.ajax({
            url: "load_entities.php",
            type: "POST",
            data: { type: reportType },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    entityDropdown.innerHTML = '<option value=""><?= __('select_an_entity') ?></option>';
                    response.data.forEach(function(entity) {
                        entityDropdown.innerHTML += `<option value="${entity.id}">${entity.name}</option>`;
                    });
                } else {
                    entityDropdown.innerHTML = '<option value=""><?= __('no_entities_found') ?></option>';
                }
            },
            error: function() {
                entityDropdown.innerHTML = '<option value=""><?= __('error_loading_entities') ?></option>';
            }
        });
    }

    if (reportType !== "") {
        reportCategorySelection.style.display = "block";
    }
}

function filterResults() {
    var reportType = document.getElementById("reportType").value;
    var entity = document.getElementById("entity") ? document.getElementById("entity").value : "";
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var resultsContainer = document.getElementById("resultsContainer");

    if (!reportType || !startDate || !endDate) {
            alert("<?= __('please_select_all_required_fields') ?>");
        return;
    }

    resultsContainer.style.display = "block";
    resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only"><?= __('loading') ?></span></div></div>';

    $.ajax({
        url: "fetch_transaction_data.php",
        type: "POST",
        data: {
            reportType: reportType,
            entity: entity,
            reportCategory: reportCategory,
            startDate: startDate,
            endDate: endDate
        },
        dataType: "json",
        success: function(response) {
            if (response.success && response.data.length > 0) {
                var tableHTML = `
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    ${response.headers.map(header => `<th>${header}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
                `;

                let currentReferenceId = null;
                let isFirstRow = true;

                response.data.forEach(function(row) {
                    // Add separator between different reference IDs
                    if (currentReferenceId !== row.reference_id && !isFirstRow) {
                        tableHTML += '<tr><td colspan="' + response.headers.length + '" style="background-color: #f5f5f5; height: 10px;"></td></tr>';
                    }
                    currentReferenceId = row.reference_id;
                    isFirstRow = false;

                    // Add the row with background color based on transaction source
                    let rowColor = '';
                    switch(row.transaction_from) {
                        case 'Supplier':
                            rowColor = '#f8f9fa';
                            break;
                        case 'Client':
                            rowColor = '#fff3cd';
                            break;
                        case 'Main Account':
                            rowColor = '#d1e7dd';
                            break;
                    }

                    tableHTML += `<tr style="background-color: ${rowColor}">`;
                    response.headers.forEach(function(header) {
                        let value = '';
                        switch(header) {
                            case 'From':
                                value = row.transaction_from;
                                break;
                            case 'Date':
                                value = row.transaction_date;
                                break;
                            case 'Reference No':
                                value = row.reference_id;
                                break;
                            case 'Passenger Name':
                                value = row.passenger_name;
                                break;
                            case 'Description':
                                value = row.remarks;
                                break;
                            case 'Transaction Type':
                                value = row.transaction_type;
                                break;
                            case 'Amount':
                                value = parseFloat(row.amount).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                break;
                            case 'Supplier':
                                value = row.supplier_name;
                                break;
                            case 'Client':
                                value = row.client_name;
                                break;
                            case 'Account':
                                value = row.account_name;
                                break;
                            default:
                                value = row[header.toLowerCase().replace(/\s+/g, '_')] || '';
                        }
                        tableHTML += `<td>${value}</td>`;
                    });
                    tableHTML += '</tr>';
                });

                tableHTML += '</tbody></table></div>';
                resultsContainer.innerHTML = tableHTML;
                document.getElementById("exportButtons").style.display = "block";
            } else {
                resultsContainer.innerHTML = '<div class="alert alert-warning"><?= __('no_data_found_for_the_selected_criteria') ?></div>';
                document.getElementById("exportButtons").style.display = "none";
            }
        },
        error: function(xhr, status, error) {
            console.error("Error:", error);
            resultsContainer.innerHTML = '<div class="alert alert-danger"><?= __('error_fetching_data_please_try_again') ?></div>';
            document.getElementById("exportButtons").style.display = "none";
        }
    });
}

function exportReport(format) {
    var reportType = document.getElementById("reportType").value;
    var entity = document.getElementById("entity") ? document.getElementById("entity").value : "";
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    
    if (!reportType || !reportCategory || !startDate || !endDate) {
            alert("<?= __('please_select_all_fields_and_filter_the_results_first') ?>");
        return;
    }

    // Create form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_transaction_report.php';

    // Add hidden fields
    const fields = {
        'format': format,
        'reportType': reportType,
        'entity': entity,
        'reportCategory': reportCategory,
        'startDate': startDate,
        'endDate': endDate
    };

    for (const [key, value] of Object.entries(fields)) {
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.name = key;
        hiddenField.value = value;
        form.appendChild(hiddenField);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
