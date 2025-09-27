<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
?>

<?php include '../includes/header_client.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                
                                        <!-- body -->

                                      
<div class="container-fluid px-4">
    <div class="card custom-card">
        <div class="card-header bg-gradient">
            <h5 class="mb-0 text-white">
                <i class="feather icon-file-text me-2"></i><?= __('generate_report') ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form id="reportForm" class="row g-4">
                <!-- Report Type Selection -->
                <div class="col-lg-6">
                    <div class="form-group custom-form-group">
                        <label class="floating-label" for="reportType"><?= __('report_type') ?></label>
                        <select id="reportType" class="form-select form-select-lg" onchange="loadOptions()">
                            <option value=""><?= __('select_report_type') ?></option>

                            <option value="client">👥 <?= __('client') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Dynamic Dropdown for Selecting Entity -->
                <div class="col-lg-6" id="entitySelection" style="display: none;">
                    <div class="form-group custom-form-group">
                        <label class="floating-label" for="entity"><?= __('select_entity') ?></label>
                        <select id="entity" class="form-select form-select-lg"></select>
                    </div>
                </div>

                <!-- Report Category Selection -->
                <div class="col-lg-6" id="reportCategorySelection" style="display: none;">
                    <div class="form-group custom-form-group">
                        <label class="floating-label" for="reportCategory"><?= __('report_category') ?></label>
                        <select id="reportCategory" class="form-select form-select-lg">
                            <option value=""><?= __('select_report_category') ?></option>
                            <option value="statement">📊 <?= __('statement') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Statement Currency Selection -->
                <div class="col-lg-6" id="statementFields" style="display: none;">
                    <div class="form-group custom-form-group">
                        <label class="floating-label" for="statementCurrency"><?= __('currency') ?></label>
                        <select id="statementCurrency" class="form-select form-select-lg">
                            <option value="USD">💵 <?= __('usd') ?></option>
                            <option value="AFS">🪙 <?= __('afs') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Date Range Selection -->
                <div class="col-lg-6">
                    <div class="form-group custom-form-group">
                        <label class="floating-label" for="dateRange"><?= __('date_range') ?></label>
                        <input type="text" id="dateRange" class="form-control form-control-lg" readonly>
                        <input type="hidden" id="startDate">
                        <input type="hidden" id="endDate">
                        <i class="feather icon-calendar input-icon"></i>
                    </div>
                </div>

                <!-- Generate Button -->
                <div class="col-12 text-end mt-4">
                    <button type="button" class="btn btn-primary btn-lg px-5 custom-btn" onclick="filterResults()">
                        <i class="feather icon-filter me-2"></i><?= __('generate_report') ?>
                        <div class="btn-hover-effect"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Container -->
    <div id="resultsContainer" class="mt-4 custom-results" style="display: none;">
        <!-- Table will be inserted here -->
    </div>

    <!-- Export Buttons -->
    <div id="exportButtons" class="mt-4 text-end" style="display: none;">
        <button type="button" class="btn btn-danger btn-lg me-2 custom-btn" onclick="exportReport('pdf')">
            <i class="feather icon-file-text me-2"></i><?= __('pdf') ?>
            <div class="btn-hover-effect"></div>
        </button>
        <button type="button" class="btn btn-success btn-lg me-2 custom-btn" onclick="exportReport('excel')">
            <i class="feather icon-file me-2"></i><?= __('excel') ?>
            <div class="btn-hover-effect"></div>
        </button>
        <button type="button" class="btn btn-primary btn-lg custom-btn" onclick="exportReport('word')">
            <i class="feather icon-file me-2"></i><?= __('word') ?>
            <div class="btn-hover-effect"></div>
        </button>
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

        /* RTL Specific Styles */
        [dir="rtl"] .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
            text-align: right;
        }

        [dir="rtl"] .form-select-lg, 
        [dir="rtl"] .form-control-lg {
            text-align: right;
            padding-right: 1.25rem;
            padding-left: 3rem;
        }

        [dir="rtl"] .floating-label label {
            position: absolute;
            right: 0.75rem;
            left: auto;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.2s ease;
            background-color: #fff;
            padding: 0 0.5rem;
            margin: 0;
            z-index: 1;
        }

        [dir="rtl"] .floating-label input:focus ~ label,
        [dir="rtl"] .floating-label input:not(:placeholder-shown) ~ label,
        [dir="rtl"] .floating-label select:focus ~ label,
        [dir="rtl"] .floating-label select:not(:placeholder-shown) ~ label {
            right: 0.75rem;
            top: 0;
            transform: translateY(-50%) scale(0.85);
        }

        [dir="rtl"] .input-icon {
            left: 15px;
            right: auto;
        }

        [dir="rtl"] .btn i {
            margin-left: 0.5rem;
            margin-right: 0;
        }

        [dir="rtl"] .card-header h5 i {
            margin-left: 0.5rem;
            margin-right: 0;
        }

        [dir="rtl"] .form-group {
            text-align: right;
        }

        [dir="rtl"] select.form-select {
            background-position: left 0.75rem center;
            padding-right: 1.25rem;
            padding-left: 3rem;
        }

        /* Original styles remain unchanged */
        #statementFields {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        #statementFields .form-group {
            margin-bottom: 0;
        }

        #statementFields label {
            font-weight: 500;
            color: #495057;
        }

        #statementFields select {
            border-color: #ced4da;
        }

        #statementFields select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        /* Add these styles to your CSS */
        .form-floating > .form-select,
        .form-floating > .form-control {
            height: calc(3.5rem + 2px);
            line-height: 1.25;
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
        }

        .form-floating > .form-select:focus,
        .form-floating > .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .card {
            border: none;
            border-radius: 0.5rem;
        }

        .card-header {
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.5rem;
        }

        .btn {
            border-radius: 0.35rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #5E72E4;
            border-color: #5E72E4;
        }

        .btn-primary:hover {
            background-color: #324cdd;
            border-color: #324cdd;
        }

        /* Modern Card Styling */
        .custom-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .custom-card:hover {
            transform: translateY(-5px);
        }

        .card-header.bg-gradient {
            background: linear-gradient(45deg, #4776E6, #8E54E9);
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }

        /* Form Controls Styling */
        .custom-form-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-select-lg, .form-control-lg {
            height: 60px;
            border-radius: 12px;
            border: 2px solid #e0e6ed;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            background-color: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-select-lg:focus, .form-control-lg:focus {
            border-color: #4776E6;
            box-shadow: 0 0 0 0.25rem rgba(71, 118, 230, 0.1);
            background-color: #fff;
        }

        .floating-label {
            position: absolute;
            top: -10px;
            left: 15px;
            background: #fff;
            padding: 0 8px;
            color: #4776E6;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #8E54E9;
        }

        /* Custom Button Styling */
        .custom-btn {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-hover-effect {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .custom-btn:hover .btn-hover-effect {
            transform: translateX(100%);
        }

        /* Results Container Styling */
        .custom-results {
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .form-select-lg, .form-control-lg {
                height: 50px;
            }
            
            .card-header.bg-gradient {
                padding: 1rem;
            }
        }

        /* Animation for Form Elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            animation: fadeIn 0.5s ease forwards;
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

<style>
#statementFields {
    margin-top: 15px;
    padding: 15px;
    border-radius: 4px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
}

#statementFields .form-group {
    margin-bottom: 0;
}

#statementFields label {
    font-weight: 500;
    color: #495057;
}

#statementFields select {
    border-color: #ced4da;
}

#statementFields select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

/* Add these styles to your CSS */
.form-floating > .form-select,
.form-floating > .form-control {
    height: calc(3.5rem + 2px);
    line-height: 1.25;
}

.form-floating > label {
    padding: 1rem 0.75rem;
}

.form-floating > .form-select:focus,
.form-floating > .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.card {
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem 1.5rem;
}

.btn {
    border-radius: 0.35rem;
    padding: 0.5rem 1rem;
    font-weight: 500;
}

.btn-primary {
    background-color: #5E72E4;
    border-color: #5E72E4;
}

.btn-primary:hover {
    background-color: #324cdd;
    border-color: #324cdd;
}

/* Modern Card Styling */
.custom-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
}

.custom-card:hover {
    transform: translateY(-5px);
}

.card-header.bg-gradient {
    background: linear-gradient(45deg, #4776E6, #8E54E9);
    border-radius: 15px 15px 0 0;
    padding: 1.5rem;
}

/* Form Controls Styling */
.custom-form-group {
    position: relative;
    margin-bottom: 1rem;
}

.form-select-lg, .form-control-lg {
    height: 60px;
    border-radius: 12px;
    border: 2px solid #e0e6ed;
    padding: 0.75rem 1.25rem;
    font-size: 1rem;
    background-color: #f8fafc;
    transition: all 0.3s ease;
}

.form-select-lg:focus, .form-control-lg:focus {
    border-color: #4776E6;
    box-shadow: 0 0 0 0.25rem rgba(71, 118, 230, 0.1);
    background-color: #fff;
}

.floating-label {
    position: absolute;
    top: -10px;
    left: 15px;
    background: #fff;
    padding: 0 8px;
    color: #4776E6;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.input-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #8E54E9;
}

/* Custom Button Styling */
.custom-btn {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    font-weight: 500;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.btn-hover-effect {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.custom-btn:hover .btn-hover-effect {
    transform: translateX(100%);
}

/* Results Container Styling */
.custom-results {
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    padding: 1.5rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .form-select-lg, .form-control-lg {
        height: 50px;
    }
    
    .card-header.bg-gradient {
        padding: 1rem;
    }
}

/* Animation for Form Elements */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-group {
    animation: fadeIn 0.5s ease forwards;
}
</style>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Add these scripts at the bottom of the file, before closing body tag -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>


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
    var statementFields = document.getElementById("statementFields");

    // Hide all optional fields initially
    entitySelection.style.display = "none";
    entityDropdown.innerHTML = "";
    reportCategorySelection.style.display = "none";
    statementFields.style.display = "none";

    if (reportType === "general" || reportType === "main_account") {
        // Show all options for general and main account
        reportCategorySelection.style.display = "block";
        
        // Reset or populate report category options for general report
        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = `
            <option value=""><?= __('select_report_category') ?></option>
            <option value="statement">📊 <?= __('statement') ?></option>
        `;
    } else if (reportType === "supplier" || reportType === "client") {
        // Show limited options for suppliers and clients
        reportCategorySelection.style.display = "block";
        
        var reportCategoryDropdown = document.getElementById("reportCategory");
        reportCategoryDropdown.innerHTML = `
            <option value=""><?= __('select_report_category') ?></option>
            <option value="statement">📊 <?= __('statement') ?></option>
        `;
    }

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
        
        // Add event listener for report category changes
        document.getElementById("reportCategory").addEventListener("change", function() {
            if (this.value === "statement") {
                statementFields.style.display = "block";
            } else {
                statementFields.style.display = "none";
            }
        });
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

    // Special handling for general report type - don't require entity selection
    if (reportType === "general" && (!reportCategory || !startDate || !endDate)) {
        alert("<?= __('please_select_report_category_and_date_range') ?>");
        return;
    } else if (reportType !== "general" && ((!entity && (reportType === "supplier" || reportType === "main_account" || reportType === "client")) || !reportCategory || !startDate || !endDate)) {
        alert("<?= __('please_select_all_required_fields') ?>");
        return;
    }

    // Show loading indicator
    resultsContainer.style.display = "block";
    resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only"><?= __('loading') ?>...</span></div></div>';

    // Check if statement report is selected
    if (reportCategory === "statement") {
        // Get the selected currency
        var currency = document.getElementById("statementCurrency").value;
        
        // Handle statement generation
        $.ajax({
            url: "generateStatement.php",
            type: "POST",
            data: {
                reportType: reportType,
                entityId: entity,
                startDate: startDate,
                endDate: endDate,
                currency: currency
            },
            dataType: "json",
            success: function(response) {
                if (response.status === 'success' && response.data.transactions) {
                    // Hide the results container and show only export buttons
                    resultsContainer.style.display = "none";
                    document.getElementById("exportButtons").style.display = "block";
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('success') ?>',
                        text: '<?= __('report_generated_successfully_you_can_now_export_it_in_your_preferred_format') ?>'
                    });
                } else {
                    resultsContainer.innerHTML = '<div class="alert alert-warning"><?= __('no_statement_data_found') ?></div>';
                    document.getElementById("exportButtons").style.display = "none";
                }
            },
            error: function(xhr, status, error) {
                console.error("Error:", error);
                resultsContainer.innerHTML = '<div class="alert alert-danger"><?= __('error_generating_statement_please_try_again') ?></div>';
                document.getElementById("exportButtons").style.display = "none";
            }
        });
    } else {
        // Original report generation code
        $.ajax({
            url: "fetch_report_data.php",
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
                    // Hide the results container and show only export buttons
                    resultsContainer.style.display = "none";
                    document.getElementById("exportButtons").style.display = "block";
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: '<?= __('success') ?>',
                        text: '<?= __('report_generated_successfully_you_can_now_export_it_in_your_preferred_format') ?>'
                    });
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
}

function exportReport(format) {
    var reportType = document.getElementById("reportType").value;
    var entity = document.getElementById("entity") ? document.getElementById("entity").value : "";
    var reportCategory = document.getElementById("reportCategory").value;
    var startDate = document.getElementById("startDate").value;
    var endDate = document.getElementById("endDate").value;
    var currency = document.getElementById("statementCurrency").value;
    
    if (!reportType || !reportCategory || !startDate || !endDate) {
        Swal.fire({
            icon: 'error',
            title: '<?= __('error') ?>',
            text: '<?= __('please_select_all_fields_and_filter_the_results_first') ?>'
        });
        return;
    }

    // If statement is selected, redirect to export_statement.php
    if (reportCategory === 'statement') {
        // Show loading message
        Swal.fire({
            title: '<?= __('generating_statement') ?>',
            text: '<?= __('please_wait') ?>...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Create a temporary form to handle the download
        var form = document.createElement('form');
        form.method = 'GET';
        form.action = 'export_statement.php';
        form.style.display = 'none';

        // Add parameters including format
        var params = {
            reportType: reportType,
            entity: entity,
            startDate: startDate,
            endDate: endDate,
            currency: currency,
            format: format // Add format parameter
        };

        for (var key in params) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        
        // Submit form and handle response
        form.submit();
        
        // Close loading after a short delay
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: '<?= __('success') ?>',
                text: '<?= __('statement_has_been_generated_successfully_in') ?> ' + format.toUpperCase() + ' <?= __('format') ?>!'
            });
        }, 2000);

        document.body.removeChild(form);
    } else {
        // For other report types, use the original export functionality
        Swal.fire({
            title: '<?= __('generating_report') ?>',
            text: '<?= __('please_wait') ?>...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        window.location.href = "export_report.php?format=" + format + 
                              "&reportType=" + reportType + 
                              "&entity=" + entity + 
                              "&reportCategory=" + reportCategory + 
                              "&startDate=" + startDate + 
                              "&endDate=" + endDate;

        // Close loading after a short delay
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                icon: 'success',
                title: '<?= __('success') ?>',
                text: '<?= __('report_has_been_generated_successfully') ?>'
            });
        }, 2000);
    }
}

// Utility functions for statement formatting
function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatAmount(amount) {
    if (!amount) return '0.00';
    return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    // Convert to string if it's not already a string
    str = String(str);
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Add this function for debugging
function debugDate(dateString) {
    console.log('Original date:', dateString);
    const date = new Date(dateString);
    console.log('Parsed date:', date);
    return formatDate(dateString);
}

// Add this at the start of your script to handle URL parameters
$(document).ready(function() {
    // Check for error parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        Swal.fire({
            icon: 'error',
            title: '<?= __('error') ?>',
            text: decodeURIComponent(error)
        });
    }

    if (success) {
        Swal.fire({
            icon: 'success',
                title: '<?= __('success') ?>',
            text: decodeURIComponent(success)
        });
    }
});

$(document).ready(function() {
    $('#dateRange').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        ranges: {
           '<?= __('today') ?>': [moment(), moment()],
           '<?= __('yesterday') ?>': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           '<?= __('last_7_days') ?>': [moment().subtract(6, 'days'), moment()],
           '<?= __('last_30_days') ?>': [moment().subtract(29, 'days'), moment()],
           '<?= __('this_month') ?>': [moment().startOf('month'), moment().endOf('month')],
           '<?= __('last_month') ?>': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
           '<?= __('this_year') ?>': [moment().startOf('year'), moment().endOf('year')],
           '<?= __('last_year') ?>': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
        },
        locale: {
            format: 'DD MMM YYYY'
        }
    }, function(start, end) {
        // Update hidden inputs with formatted dates
        $('#startDate').val(start.format('YYYY-MM-DD'));
        $('#endDate').val(end.format('YYYY-MM-DD'));
        
        // If you have any function that needs to run when dates change
        if (typeof updateReport === 'function') {
            updateReport();
        }
    });

    // Set initial values for hidden inputs
    $('#startDate').val(moment().startOf('month').format('YYYY-MM-DD'));
    $('#endDate').val(moment().endOf('month').format('YYYY-MM-DD'));
});
</script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
