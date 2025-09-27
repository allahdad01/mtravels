<?php
// Include security module
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
// Database connection
require_once('../includes/db.php');

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found: " . $_SESSION['user_id']);
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $user = null;
}

// Fetch all users
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at DESC");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users Fetch Error: " . $e->getMessage());
    $users = []; // Ensure $users is always an array
}


?>
    <!-- Custom Styles -->
    <style>
        /* RTL Support */
        [dir="rtl"] .modal-header .close {
            margin: -1rem auto -1rem -1rem;
            float: left;
        }
        
        [dir="rtl"] .btn-group > .btn:not(:last-child):not(.dropdown-toggle) {
            border-radius: 0 0.25rem 0.25rem 0;
        }
        
        [dir="rtl"] .btn-group > .btn:not(:first-child) {
            border-radius: 0.25rem 0 0 0.25rem;
        }

        /* Enhanced Table Styles */
        .table td {
            vertical-align: middle;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Role Badge Styles */
        .badge-role {
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 500;
        }
        
        .badge-admin { background-color: #FF6B6B; color: white; }
        .badge-finance { background-color: #4ECDC4; color: white; }
        .badge-sales { background-color: #45B7D1; color: white; }
        .badge-umrah { background-color: #96CEB4; color: white; }
        .badge-staff { background-color: #6c757d; color: white; }

        /* Modal Enhancements */
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        /* Form Styling */
        .form-control:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
        }

        /* Avatar Upload */
        .avatar-upload {
            position: relative;
            max-width: 120px;
            margin: 0 auto 1rem;
        }

        .avatar-upload img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .avatar-upload .upload-button {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #4099ff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .avatar-upload .upload-button:hover {
            background-color: #2d7be3;
            transform: scale(1.1);
        }

        /* Search Box Enhancement */
        .search-box {
            max-width: 300px;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
        }

        .search-box .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .btn-group {
                display: flex;
                flex-direction: column;
            }
            
            .btn-group .btn {
                margin-bottom: 0.25rem;
            }
            
            .card-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-box {
                max-width: 100%;
            }
        }

        /* Add default avatar fallback */
        .user-avatar {
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="%23ccc" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
            background-size: cover;
            background-position: center;
        }

        /* Enhanced Tab Styles */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -2px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: #6c757d;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: #4099ff;
        }
        
        .nav-tabs .nav-link.active {
            color: #4099ff;
            background: transparent;
            border-bottom: 2px solid #4099ff;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 5px;
        }
        
        /* Tab Content Animation */
        .tab-content > .tab-pane {
            transition: all 0.3s ease-in-out;
        }
        
        .tab-content > .active {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
    </style>

    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">

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
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5><?= __("users_management") ?></h5>
                                            <div class="d-flex align-items-center gap-3">
                                                <button class="btn btn-primary" onclick="showAddUserModal()">
                                                    <i class="feather icon-plus"></i> <?= __("add_new_user") ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body table-border-style">
                                            <!-- Nav tabs -->
                                            <ul class="nav nav-tabs mb-3" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" data-toggle="tab" href="#active-users" role="tab">
                                                        <i class="feather icon-user mr-2"></i><?= __("active_users") ?>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" data-toggle="tab" href="#fired-users" role="tab">
                                                        <i class="feather icon-user-x mr-2"></i><?= __("fired_users") ?>
                                                    </a>
                                                </li>
                                            </ul>
                                            
                                            <!-- Tab panes -->
                                            <div class="tab-content">
                                                <!-- Active Users Tab -->
                                                <div class="tab-pane active" id="active-users" role="tabpanel">
                                                    <div class="table-responsive">
                                                        <table id="active-users-table" class="table table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __("profile") ?></th>
                                                                    <th><?= __("name") ?></th>
                                                                    <th><?= __("email") ?></th>
                                                                    <th><?= __("role") ?></th>
                                                                    <th><?= __("phone") ?></th>
                                                                    <th><?= __("join_date") ?></th>
                                                                    <th><?= __("status") ?></th>
                                                                    <th><?= __("actions") ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($users as $user): ?>
                                                                    <?php if (!$user['fired']): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <img src="../assets/images/user/<?= htmlspecialchars($user['profile_pic'] ?? 'default-avatar.jpg') ?>" 
                                                                                     class="user-avatar" alt="User Avatar">
                                                            <td>
                                                                <div class="font-weight-bold"><?= htmlspecialchars($user['name']) ?></div>
                                                            </td>
                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                            <td>
                                                                <span class="badge badge-role badge-<?= strtolower($user['role']) ?>">
                                                                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                            <td>
                                                                <span class="badge <?= $user['fired'] ? 'badge-danger' : 'badge-success' ?>">
                                                                    <?= $user['fired'] ? __('fired') : __('active') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group">
                                                                    <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                        <i class="feather icon-more-vertical"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <!-- Edit -->
                                                                        <a class="dropdown-item" href="#" onclick="editUser(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-edit mr-2"></i><?= __("edit") ?>
                                                                        </a>
                                                                        <!-- Delete -->
                                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-trash-2 mr-2"></i><?= __("delete") ?>
                                                                        </a>
                                                                        <!-- Fire/Unfire -->
                                                                        <a class="dropdown-item <?= $user['fired'] ? 'text-success' : 'text-warning' ?>" href="#" onclick="fireUser(<?= $user['id'] ?>)">
                                                                            <i class="feather <?= $user['fired'] ? 'icon-refresh-ccw' : 'icon-user-x' ?> mr-2"></i>
                                                                            <?= $user['fired'] ? __("reactivate") : __("fire") ?>
                                                                        </a>
                                                                        <div class="dropdown-divider"></div>
                                                                        <!-- Documents -->
                                                                        <a class="dropdown-item" href="#" onclick="showLanguageModal(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-file-plus mr-2"></i><?= __("employment_agreement") ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" onclick="showGuarantorLanguageModal(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-user-check mr-2"></i><?= __("guarantor_letter") ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" onclick="showTawseahModal(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-alert-circle mr-2"></i><?= __("tawseah") ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" onclick="showIkhtarModal(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-alert-triangle mr-2"></i><?= __("official_warning") ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" onclick="showFineLetterModal(<?= $user['id'] ?>)">
                                                                            <i class="fa fa-money-bill-alt mr-2"></i><?= __("fine_letter") ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" onclick="showTerminationLetterModal(<?= $user['id'] ?>)">
                                                                            <i class="feather icon-user-x mr-2"></i><?= __("termination_letter") ?>
                                                                        </a> 
                                                                    </div>
                                                                </div>
                                                            </td>
                                                                        </tr>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                
                                                <!-- Fired Users Tab -->
                                                <div class="tab-pane" id="fired-users" role="tabpanel">
                                                    <div class="table-responsive">
                                                        <table id="fired-users-table" class="table table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __("profile") ?></th>
                                                                    <th><?= __("name") ?></th>
                                                                    <th><?= __("email") ?></th>
                                                                    <th><?= __("role") ?></th>
                                                                    <th><?= __("phone") ?></th>
                                                                    <th><?= __("join_date") ?></th>
                                                                    <th><?= __("status") ?></th>
                                                                    <th><?= __("actions") ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($users as $user): ?>
                                                                    <?php if ($user['fired']): ?>
                                                                        <tr class="table-danger">
                                                                            <td>
                                                                                <img src="../assets/images/user/<?= htmlspecialchars($user['profile_pic'] ?? 'default-avatar.jpg') ?>" 
                                                                                     class="user-avatar" alt="User Avatar">
                                                                            </td>
                                                                            <td>
                                                                                <div class="font-weight-bold"><?= htmlspecialchars($user['name']) ?></div>
                                                                            </td>
                                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                                            <td>
                                                                                <span class="badge badge-role badge-<?= strtolower($user['role']) ?>">
                                                                                    <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                                                                </span>
                                                                            </td>
                                                                            <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                                                            <td>
                                                                                <span class="badge badge-danger">
                                                                                    <?= __('fired') ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <div class="btn-group">
                                                                                    <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                        <i class="feather icon-more-vertical"></i>
                                                                                    </button>
                                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                                        <!-- Edit -->
                                                                                        <a class="dropdown-item" href="#" onclick="editUser(<?= $user['id'] ?>)">
                                                                                            <i class="feather icon-edit mr-2"></i><?= __("edit") ?>
                                                                                        </a>
                                                                                        <!-- Delete -->
                                                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?= $user['id'] ?>)">
                                                                                            <i class="feather icon-trash-2 mr-2"></i><?= __("delete") ?>
                                                                                        </a>
                                                                                        <!-- Reactivate -->
                                                                                        <a class="dropdown-item text-success" href="#" onclick="fireUser(<?= $user['id'] ?>)">
                                                                                            <i class="feather icon-refresh-ccw mr-2"></i><?= __("reactivate") ?>
                                                                                        </a>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("add_new_user") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <div class="avatar-upload">
                            <img id="addAvatarPreview" src="../assets/images/user/default-avatar.jpg" alt="Profile Preview">
                            <label for="addProfilePic" class="upload-button">
                                <i class="feather icon-camera"></i>
                            </label>
                            <input type="file" id="addProfilePic" name="profile_pic" class="d-none" 
                                   accept="image/*" onchange="previewImage(this, 'addAvatarPreview')">
                        </div>

                        <div class="form-group">
                            <label><?= __("full_name") ?> *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("email") ?> *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("password") ?> *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("role") ?> *</label>
                            <select class="form-control" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="finance">Finance</option>
                                <option value="sales">Sales</option>
                                <option value="umrah">Umrah</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= __("phone") ?></label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="form-group">
                            <label><?= __("address") ?></label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("hire_date") ?></label>
                            <input type="date" class="form-control" name="hire_date">
                        </div>
                        <div class="form-group">
                            <label><?= __("documents") ?></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="userDocuments" name="user_documents[]" multiple>
                                <label class="custom-file-label" for="userDocuments"><?= __("choose_files") ?></label>
                            </div>
                            <small class="form-text text-muted">
                                <?= __("upload_user_documents") ?> (PDF, DOC, DOCX, JPG, PNG)
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <?= __("cancel") ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <?= __("save_user") ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_user") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editUserForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <div class="avatar-upload">
                            <img id="editAvatarPreview" src="../assets/images/user/default-avatar.jpg" alt="Profile Preview">
                            <label for="editProfilePic" class="upload-button">
                                <i class="feather icon-camera"></i>
                            </label>
                            <input type="file" id="editProfilePic" name="profile_pic" class="d-none" 
                                accept="image/*" onchange="previewImage(this, 'editAvatarPreview')">
                        </div>

                        <div class="form-group">
                            <label><?= __("full_name") ?> *</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("email") ?> *</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("password") ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="editPassword" 
                                    placeholder="<?= __("leave_blank_to_keep_current_password") ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editPassword')">
                                        <i class="feather icon-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">
                                <?= __("only_fill_if_you_want_to_change_password") ?>
                            </small>
                        </div>
                        <div class="form-group">
                            <label><?= __("role") ?> *</label>
                            <select class="form-control" name="role" id="editRole" required>
                                <option value="admin">Admin</option>
                                <option value="finance">Finance</option>
                                <option value="sales">Sales</option>
                                <option value="umrah">Umrah</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= __("phone") ?></label>
                            <input type="tel" class="form-control" name="phone" id="editPhone">
                        </div>
                        <div class="form-group">
                            <label><?= __("address") ?></label>
                            <textarea class="form-control" name="address" id="editAddress" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("hire_date") ?></label>
                            <input type="date" class="form-control" name="hire_date" id="editHireDate">
                        </div>
                        <div class="form-group">
                            <label><?= __("documents") ?></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="editUserDocuments" name="user_documents[]" multiple>
                                <label class="custom-file-label" for="editUserDocuments"><?= __("choose_files") ?></label>
                            </div>
                            <small class="form-text text-muted">
                                <?= __("upload_user_documents") ?> (PDF, DOC, DOCX, JPG, PNG)
                            </small>
                        </div>
                        <div id="existingDocuments" class="mt-3">
                            <!-- Existing documents will be loaded here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <?= __("cancel") ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <?= __("save_changes") ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

    <script>
    // Global toast function
    function createToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.position = 'fixed';
            toastContainer.style.top = '20px';
            toastContainer.style.right = '20px';
            toastContainer.style.zIndex = '99999';
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-message">${message}</div>
                <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Add to container
        toastContainer.appendChild(toast);

        // Force reflow to trigger animation
        toast.offsetHeight;

        // Show toast
        toast.classList.add('show');

        // Auto remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);

        return toast;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTables for both active and fired users
        const activeUsersTable = $('#active-users-table').DataTable({
            responsive: true,
            language: {
                url: '../assets/plugins/datatables/i18n/' + document.documentElement.lang + '.json'
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        const firedUsersTable = $('#fired-users-table').DataTable({
            responsive: true,
            language: {
                url: '../assets/plugins/datatables/i18n/' + document.documentElement.lang + '.json'
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });

        // Form submissions
        const addUserForm = document.getElementById('addUserForm');
        const editUserForm = document.getElementById('editUserForm');

        function handleFormSubmit(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"]');
            const isEdit = form.id === 'editUserForm';
            
            // Debug log the form data
            console.log('<?= __("form_id") ?>:', form.id);
            console.log('<?= __("is_edit") ?>:', isEdit);
            console.log('<?= __("form_data") ?>:', Object.fromEntries(formData));
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?= __("loading") ?>';
            
            // Choose the appropriate endpoint
            const endpoint = isEdit ? 'update_user.php' : 'save_user.php';
            console.log('<?= __("sending_request_to") ?>:', endpoint);
            
            fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('<?= __("response_status") ?>:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('<?= __("error_response") ?>:', text);
                        throw new Error('<?= __("network_error") ?>');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    createToast(data.message, 'success');
                    $(form.closest('.modal')).modal('hide');
                    // Reload the page after a longer delay to ensure message is visible
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || (isEdit ? '<?= __("error_updating_user") ?>' : '<?= __("error_adding_user") ?>'));
                }
            })
            .catch(error => {
                console.error('<?= __("error") ?>:', error);
                createToast(error.message, 'danger');
            })
            .finally(() => {
                // Re-enable submit button and restore original text
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        }

        // Attach form submit handlers
        if (addUserForm) {
            addUserForm.addEventListener('submit', handleFormSubmit);
        }
        if (editUserForm) {
            editUserForm.addEventListener('submit', handleFormSubmit);
        }

        // Initialize modals with proper backdrop handling
        $('.modal').on('show.bs.modal', function() {
            const zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(() => {
                $('.modal-backdrop').not('.modal-stack')
                    .css('z-index', zIndex - 1)
                    .addClass('modal-stack');
            });
        });

        // Handle modal close
        $('.modal').on('hidden.bs.modal', function() {
            if ($('.modal:visible').length) {
                $('body').addClass('modal-open');
            }
            // Reset form on modal close
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                // Reset any profile picture preview
                const preview = form.querySelector('img[id$="AvatarPreview"]');
                if (preview) {
                    preview.src = '../assets/images/user/default-avatar.jpg';
                }
            }
        });

        // Image preview function
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Handle custom file input display
        $('.custom-file-input').on('change', function() {
            let fileName = '';
            if (this.files && this.files.length > 1) {
                fileName = (this.getAttribute('data-multiple-caption') || '').replace('{count}', this.files.length);
            } else {
                fileName = this.files[0].name;
            }
            
            if (fileName) {
                $(this).next('.custom-file-label').html(fileName);
            }
        });
    });

    // Show add user modal
    function showAddUserModal() {
        const form = document.getElementById('addUserForm');
        if (form) {
            form.reset();
            document.getElementById('addAvatarPreview').src = '../assets/images/user/default-avatar.jpg';
            $('#addUserModal').modal('show');
        } else {
            console.error('<?= __("add_user_form_not_found") ?>');
        }
    }

    // Edit user function
    function editUser(userId) {
        console.log('<?= __("fetching_user_data_for_id") ?>:', userId);
        
        fetch(`get_user.php?id=${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('<?= __("edit_fetch_response_status") ?>:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('<?= __("edit_fetch_error_response") ?>:', text);
                    throw new Error('<?= __("network_response_was_not_ok") ?>');
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('<?= __("edit_fetch_server_response") ?>:', response);
            if (response.success && response.data) {
                const user = response.data;
                // Populate form fields
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editName').value = user.name || '';
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editRole').value = user.role || 'staff';
                document.getElementById('editPhone').value = user.phone || '';
                document.getElementById('editAddress').value = user.address || '';
                document.getElementById('editHireDate').value = user.hire_date || '';
                
                // Handle profile picture
                const profilePic = user.profile_pic ? 
                    `../assets/images/user/${user.profile_pic}` : 
                    '../assets/images/user/default-avatar.jpg';
                document.getElementById('editAvatarPreview').src = profilePic;

                // Reset password field
                document.getElementById('editPassword').value = '';
                document.getElementById('editPassword').placeholder = '<?= __("leave_blank_to_keep_current_password") ?>';

                // Display existing documents
                const existingDocsContainer = document.getElementById('existingDocuments');
                existingDocsContainer.innerHTML = '';
                
                if (user.documents && user.documents.length > 0) {
                    const docList = document.createElement('div');
                    docList.className = 'list-group';
                    
                    const docTitle = document.createElement('h6');
                    docTitle.className = 'mb-2';
                    docTitle.textContent = '<?= __("existing_documents") ?>';
                    existingDocsContainer.appendChild(docTitle);
                    
                    user.documents.forEach(doc => {
                        const docItem = document.createElement('div');
                        docItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                        
                        // Determine icon based on file type
                        let iconClass = 'far fa-file';
                        if (['pdf'].includes(doc.file_type)) {
                            iconClass = 'far fa-file-pdf';
                        } else if (['doc', 'docx'].includes(doc.file_type)) {
                            iconClass = 'far fa-file-word';
                        } else if (['jpg', 'jpeg', 'png'].includes(doc.file_type)) {
                            iconClass = 'far fa-file-image';
                        }
                        
                        docItem.innerHTML = `
                            <div>
                                <i class="${iconClass} mr-2"></i>
                                <span>${doc.original_name}</span>
                            </div>
                            <div>
                                <a href="../uploads/user_documents/${user.id}/${doc.filename}" 
                                   class="btn btn-sm btn-info mr-1" target="_blank">
                                    <i class="feather icon-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteDocument(${doc.id}, ${user.id})">
                                    <i class="feather icon-trash"></i>
                                </button>
                            </div>
                        `;
                        
                        docList.appendChild(docItem);
                    });
                    
                    existingDocsContainer.appendChild(docList);
                } else {
                    existingDocsContainer.innerHTML = '<p class="text-muted"><?= __("no_documents_found") ?></p>';
                }

                // Show the modal
                $('#editUserModal').modal('show');
            } else {
                throw new Error(response.message || '<?= __("error_loading_user_data") ?>');
            }
        })
        .catch(error => {
            console.error('<?= __("edit_error") ?>:', error);
            createToast(error.message, 'danger');
        });
    }

    // Delete document function
    function deleteDocument(docId, userId) {
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_document") ?>')) {
            console.log('<?= __("deleting_document") ?>:', docId);
            
            fetch('delete_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                },
                body: JSON.stringify({ 
                    id: docId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('<?= __("network_response_was_not_ok") ?>');
                }
                return response.json();
            })
            .then(data => {
                console.log('<?= __("delete_document_response") ?>:', data);
                if (data.success) {
                    createToast(data.message || '<?= __("document_deleted_successfully") ?>', 'success');
                    // Refresh user data to update document list
                    editUser(userId);
                } else {
                    throw new Error(data.message || '<?= __("error_deleting_document") ?>');
                }
            })
            .catch(error => {
                console.error('<?= __("delete_document_error") ?>:', error);
                createToast(error.message || '<?= __("failed_to_delete_document") ?>', 'danger');
            });
        }
    }

    // Add toggle password visibility function
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('icon-eye');
            icon.classList.add('icon-eye-off');
        } else {
            input.type = 'password';
            icon.classList.remove('icon-eye-off');
            icon.classList.add('icon-eye');
        }
    }

    // Delete user function
    function deleteUser(userId) {
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_user") ?>')) {
            console.log('<?= __("deleting_user") ?>:', userId);
            
            fetch('delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                },
                body: JSON.stringify({ 
                    id: userId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('<?= __("network_response_was_not_ok") ?>');
                }
                return response.json();
            })
            .then(data => {
                console.log('<?= __("delete_server_response") ?>:', data);
                if (data.success) {
                    createToast(data.message || '<?= __("user_deleted_successfully") ?>', 'success');
                    // Reload after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || '<?= __("error_deleting_user") ?>');
                }
            })
            .catch(error => {
                console.error('<?= __("delete_error") ?>:', error);
                createToast(error.message || '<?= __("failed_to_delete_user") ?>', 'danger');
            });
        }
    }

    // Fire user function
    function fireUser(userId) {
        const confirmMessage = confirm('<?= __("are_you_sure_you_want_to_fire_this_user") ?>');
        
        if (confirmMessage) {
            console.log('<?= __("firing_user") ?>:', userId);
            
            fetch('fire_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded', // Change this
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ // Use URLSearchParams
                    id: userId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('<?= __("network_response_was_not_ok") ?>');
                }
                return response.json();
            })
            .then(data => {
                console.log('<?= __("fire_server_response") ?>:', data);
                if (data.success) {
                    createToast(data.message, 'success');
                    // Reload after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || '<?= __("error_firing_user") ?>');
                }
            })
            .catch(error => {
                console.error('<?= __("fire_error") ?>:', error);
                createToast(error.message || '<?= __("failed_to_fire_user") ?>', 'danger');
            });
        }
    }

    // Image preview function
    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Add CSS to head
    const style = document.createElement('style');
    style.textContent = `
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
        }
        .toast-notification {
            min-width: 300px;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 4px;
            font-size: 14px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast-notification.success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .toast-notification.danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .toast-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .toast-message {
            flex-grow: 1;
            margin-right: 10px;
        }
        .toast-close {
            background: none;
            border: none;
            font-size: 20px;
            font-weight: bold;
            color: inherit;
            cursor: pointer;
            padding: 0 5px;
        }
        .toast-close:hover {
            opacity: 0.7;
        }
    `;
    document.head.appendChild(style);
    </script>

    <?php include '../includes/admin_footer.php'; ?>

    <!-- Language Selection Modal -->
    <div class="modal fade" id="languageSelectionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_agreement_language") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                <div>
                    <form id="agreementForm" onsubmit="generateAgreement(event)">
                        <div class="form-group">
                            <label for="rule"><?= __("rule") ?></label>
                            <textarea type="text" class="form-control" id="rule" placeholder="<?= __("rule") ?>"></textarea>
                        </div>
                    </form>
                </div>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('en')">
                            <i class="feather icon-globe mr-2"></i> English
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Global variable to store user ID for language selection
    let selectedUserId = null;

    // Function to show language selection modal
    function showLanguageModal(userId) {
        selectedUserId = userId;
        $('#languageSelectionModal').modal('show');
    }
    // Function to generate agreement based on selected language
    function generateAgreement(language) {
        if (!selectedUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }

        // Get the rule input value at the time of click
        const ruleValue = document.getElementById('rule').value;

        // Close the language selection modal
        $('#languageSelectionModal').modal('hide');

        // Determine the correct agreement generation URL based on language
        let agreementUrl = '';
        switch(language) {
            case 'en':
                agreementUrl = 'generate_user_agreement.php';
                break;
            case 'fa':
                agreementUrl = 'generate_user_dari_agreement.php';
                break;
            case 'ps':
                agreementUrl = 'generate_user_pashto_agreement.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }

        // Open the agreement in a new tab
        window.open(`${agreementUrl}?user_id=${selectedUserId}&rule=${encodeURIComponent(ruleValue)}`, '_blank');
    }
    </script>

    <!-- Guarantor Letter Language Selection Modal -->
    <div class="modal fade" id="guarantorLanguageModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_guarantor_letter_language") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateGuarantorLetter('fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateGuarantorLetter('ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tawseah Language Selection Modal -->
    <div class="modal fade" id="tawseahModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_tawseah_language") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="tawseahForm" onsubmit="generateTawseah(event)">
                    <div class="form-group">
                    <label for="job_title"><?= __("job_title") ?></label>
                    <input type="text" class="form-control" id="job_title" placeholder="<?= __("job_title") ?>">
                    </div>
                    <div class="form-group">
                    <label for="takhaluf"><?= __("takhaluf") ?></label>
                    <input type="text" class="form-control" id="takhaluf" placeholder="<?= __("takhaluf") ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTawseah(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTawseah(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Fine Letter Language Selection Modal -->

    <div class="modal fade" id="fineLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_fine_letter_language") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="fineLetterForm" onsubmit="generateFineLetter(event)">
                    <div class="form-group">
                    <label for="job_title"><?= __("job_title") ?></label>
                    <input type="text" class="form-control" id="job_title_fine" placeholder="<?= __("job_title") ?>">
                    </div>
                    <div class="form-group">
                    <label for="takhaluf"><?= __("takhaluf") ?></label>
                    <input type="text" class="form-control" id="takhaluf_fine" placeholder="<?= __("takhaluf") ?>">
                    </div>
                    <div class="form-group">
                    <label for="fine_amount"><?= __("fine_amount") ?></label>
                    <input type="text" class="form-control" id="fine_amount" placeholder="<?= __("fine_amount") ?>">
                    </div>
                    <div class="form-group">
                    <label for="currency"><?= __("currency") ?></label>
                    <select class="form-control" id="currency">
                            <option value="AFS">AFS</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'ps')">  
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">   
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ikhtar Language Selection Modal -->
    <div class="modal fade" id="ikhtarModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_official_warning_language") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="ikhtarForm" onsubmit="generateIkhtar(event)">
                    <div class="form-group">
                    <label for="job_title"><?= __("job_title") ?></label>
                    <input type="text" class="form-control" id="job_title_ikhtar" placeholder="<?= __("job_title") ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateIkhtar(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateIkhtar(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Termination Letter Language Selection Modal -->
    <div class="modal fade" id="terminationLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("select_termination_letter_language") ?></h5>
                </div>
                <div class="modal-body">
                    <form id="terminationLetterForm" onsubmit="generateTerminationLetter(event)">
                    <div class="form-group">
                    <label for="job_title"><?= __("job_title") ?></label>
                    <input type="text" class="form-control" id="job_title_termination" placeholder="<?= __("job_title") ?>">
                    </div>
                    <div class="form-group">
                    <label for="termination_date"><?= __("termination_date") ?></label>
                    <input type="date" class="form-control" id="termination_date" placeholder="<?= __("termination_date") ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?= __("cancel") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Global variable to store user ID for guarantor letter language selection
    let selectedGuarantorUserId = null;
    let selectedTawseahUserId = null;
    let selectedIkhtarUserId = null;
    let selectedFineLetterUserId = null;
    let selectedTerminationLetterUserId = null;
    // Function to show guarantor letter language selection modal
    function showGuarantorLanguageModal(userId) {
        selectedGuarantorUserId = userId;
        $('#guarantorLanguageModal').modal('show');
    }

    // Function to show tawseah language selection modal
    function showTawseahModal(userId) {
        selectedTawseahUserId = userId;
        $('#tawseahModal').modal('show');
    }

    // Function to show ikhtar language selection modal
    function showIkhtarModal(userId) {
        selectedIkhtarUserId = userId;
        $('#ikhtarModal').modal('show');
    }

    // Function to show fine letter language selection modal
    function showFineLetterModal(userId) {
        selectedFineLetterUserId = userId;
        $('#fineLetterModal').modal('show');
    }

    // Function to show termination letter language selection modal
    function showTerminationLetterModal(userId) {
        selectedTerminationLetterUserId = userId;
        $('#terminationLetterModal').modal('show');
    }

    function generateTawseah(event, language) {
        event.preventDefault();
        
    if (!selectedTawseahUserId) {
        createToast('<?= __("error_no_user_selected") ?>', 'danger');
        return;
    }

    // Get the takhaluf input value safely
    const takhalufValue = document.getElementById('takhaluf').value;
    const jobtitleValue = document.getElementById('job_title').value;

    // Close the modal
    $('#tawseahModal').modal('hide');

    // Determine URL
    let tawseahUrl = '';
    switch(language) {
        case 'fa':
            tawseahUrl = 'generate_tawseah.php';
            break;
        case 'ps':
            tawseahUrl = 'generate_tawseah_pashto.php';
            break;
        default:
            createToast('<?= __("error_invalid_language") ?>', 'danger');
            return;
    }

    // Open in new tab with encoded value
    window.open(`${tawseahUrl}?user_id=${selectedTawseahUserId}&language=${language}&takhaluf=${encodeURIComponent(takhalufValue)}&job_title=${encodeURIComponent(jobtitleValue)}`, '_blank');
}

function generateIkhtar(event, language) {
    event.preventDefault();
    
    if (!selectedIkhtarUserId) {
        createToast('<?= __("error_no_user_selected") ?>', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_ikhtar');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('<?= __("error_job_title_required") ?>', 'warning');
        return;
    }

    $('#ikhtarModal').modal('hide');

    let ikhtarUrl = '';
    switch(language) {
        case 'fa':
            ikhtarUrl = 'generate_ikhtar.php';
            break;  
        case 'ps':
            ikhtarUrl = 'generate_ikhtar_pashto.php';
            break;
        default:
            createToast('<?= __("error_invalid_language") ?>', 'danger');
            return; 
    }

    const finalUrl = `${ikhtarUrl}?user_id=${selectedIkhtarUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}`;
    console.log('Opening:', finalUrl);
    window.open(finalUrl, '_blank');
}

    function generateFineLetter(event, language) {
        event.preventDefault();
        
        if (!selectedFineLetterUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }

        const jobTitleInput = document.getElementById('job_title_fine');
        if (!jobTitleInput) {
            createToast('Job title field not found.', 'danger');
            return;
        }
        const takhalufInput = document.getElementById('takhaluf_fine');
        if (!takhalufInput) {
            createToast('Takhaluf field not found.', 'danger');
            return;
        }

        const jobtitleValue = jobTitleInput.value.trim();
        if (!jobtitleValue) {
            createToast('<?= __("error_job_title_required") ?>', 'warning');
            return;
        }

        const takhalufValue = takhalufInput.value.trim();
        if (!takhalufValue) {
            createToast('<?= __("error_takhaluf_required") ?>', 'warning');
            return;
        }


        const fineAmountInput = document.getElementById('fine_amount');
        if (!fineAmountInput) {
            createToast('Fine amount field not found.', 'danger');
            return;
        }

        const fineAmountValue = fineAmountInput.value.trim();
        if (!fineAmountValue) { 
            createToast('<?= __("error_fine_amount_required") ?>', 'warning');
            return;
        }

        const currencyInput = document.getElementById('currency');
        if (!currencyInput) {   
            createToast('Currency field not found.', 'danger');
            return;
        }

        const currencyValue = currencyInput.value.trim();
        if (!currencyValue) {   
            createToast('<?= __("error_currency_required") ?>', 'warning');
            return;
        }

        $('#fineLetterModal').modal('hide');

        let fineLetterUrl = '';
        switch(language) {
            case 'fa':
                fineLetterUrl = 'generate_fine.php';
                break;
            case 'ps':  
                fineLetterUrl = 'generate_fine_pashto.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }   

        const finalUrl = `${fineLetterUrl}?user_id=${selectedFineLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&takhaluf=${encodeURIComponent(takhalufValue)}&fine_amount=${encodeURIComponent(fineAmountValue)}&currency=${encodeURIComponent(currencyValue)}`;
        console.log('Opening:', finalUrl);
        window.open(finalUrl, '_blank');
    }

    function generateGuarantorLetter(language) {
        if (!selectedGuarantorUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }

        // Close the language selection modal
        $('#guarantorLanguageModal').modal('hide');

        // Determine the correct guarantor letter generation URL based on language
        let guarantorLetterUrl = '';
        switch(language) {
            case 'fa':
                guarantorLetterUrl = 'generate_guarantor_letter.php';
                break;
            case 'ps':
                guarantorLetterUrl = 'generate_guarantor_pashto_letter.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }

        // Open the guarantor letter in a new tab
        window.open(`${guarantorLetterUrl}?user_id=${selectedGuarantorUserId}`, '_blank');
    }

    function generateTerminationLetter(event, language) {
        event.preventDefault();
        
        if (!selectedTerminationLetterUserId) {
            createToast('<?= __("error_no_user_selected") ?>', 'danger');
            return;
        }

        const jobTitleInput = document.getElementById('job_title_termination');
        if (!jobTitleInput) {
            createToast('Job title field not found.', 'danger');
            return;
        }

        const jobtitleValue = jobTitleInput.value.trim();
        if (!jobtitleValue) {
            createToast('<?= __("error_job_title_required") ?>', 'warning');
            return;
        }

        const terminationDateInput = document.getElementById('termination_date');
        if (!terminationDateInput) {
            createToast('Termination date field not found.', 'danger');
            return;
        }   

        const terminationDateValue = terminationDateInput.value.trim();
        if (!terminationDateValue) {
            createToast('<?= __("error_termination_date_required") ?>', 'warning');
            return;
        }

        $('#terminationLetterModal').modal('hide');

        let terminationLetterUrl = '';
        switch(language) {
            case 'fa':
                terminationLetterUrl = 'generate_termination.php';
                break;
            case 'ps':
                terminationLetterUrl = 'generate_termination_pashto.php';
                break;
            default:
                createToast('<?= __("error_invalid_language") ?>', 'danger');
                return;
        }   

        const finalUrl = `${terminationLetterUrl}?user_id=${selectedTerminationLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&termination_date=${encodeURIComponent(terminationDateValue)}`;
        console.log('Opening:', finalUrl);
        window.open(finalUrl, '_blank');
    }
    </script>
</body>
</html>