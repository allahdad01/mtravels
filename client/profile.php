<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
require_once('../includes/session_check.php');
require_once('../includes/language_helpers.php');
$lang = init_language();

if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

require_once('../includes/db.php');
include '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? and tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found: " . $_SESSION['user_id']);
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

$profilePic = !empty($user['image']) ? htmlspecialchars($user['image']) : 'default-avatar.jpg';
$imagePath = "../assets/images/client/" . $profilePic;

try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= __('user_profile') ?> - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo']) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/mobile-responsive.css">

    <style>
        .profile-header {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
        }
        .profile-avatar {
            border: 4px solid rgba(255,255,255,0.3);
            object-fit: cover;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #4099ff;
            background: #f0f9ff;
        }
    </style>
</head>

<body>
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <?php include '../includes/header_client.php'; ?>

    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            
                            <!-- Profile Header -->
                            <div class="profile-header py-4 py-md-5 mb-4">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-12 text-center">
                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                 class="profile-avatar rounded-circle mx-auto d-block mb-3" 
                                                 style="width: 80px; height: 80px;" 
                                                 width="80" height="80">
                                            <h2 class="h4 h-md-3 mb-2"><?= htmlspecialchars($user['name']) ?></h2>
                                            <p class="mb-0 small"><?= htmlspecialchars($user['role'] ?? 'Client') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="container-fluid">
                                <!-- Profile Stats -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-body p-3 p-md-4">
                                                <div class="row">
                                                    <div class="col-md-4 col-6 mb-3 mb-md-0">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <h3 class="h4 text-primary mb-1">
                                                                <?php
                                                                try {
                                                                    $activityStmt = $pdo->prepare("SELECT COUNT(*) as activity_count FROM activity_log WHERE user_id = ? AND tenant_id = ?");
                                                                    $activityStmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                    $activity = $activityStmt->fetch(PDO::FETCH_ASSOC);
                                                                    echo $activity['activity_count'] ?? 0;
                                                                } catch (PDOException $e) {
                                                                    echo '0';
                                                                }
                                                                ?>
                                                            </h3>
                                                            <p class="small text-muted mb-0">Activities</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-6 mb-3 mb-md-0">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <h3 class="h4 text-primary mb-1">
                                                                <?php
                                                                $createdDate = strtotime($user['created_at'] ?? $user['hire_date'] ?? date('Y-m-d'));
                                                                $currentDate = time();
                                                                $daysDiff = floor(($currentDate - $createdDate) / (60 * 60 * 24));
                                                                echo max($daysDiff, 1);
                                                                ?>
                                                            </h3>
                                                            <p class="small text-muted mb-0">Days Active</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 col-12">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <h3 class="h4 text-primary mb-1">
                                                                <?php
                                                                $bookingCount = 0;
                                                                try {
                                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_bookings WHERE created_by = ? AND tenant_id = ?");
                                                                    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                    $bookingCount += $stmt->fetchColumn();

                                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hotel_bookings WHERE created_by = ? AND tenant_id = ?");
                                                                    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                    $bookingCount += $stmt->fetchColumn();

                                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE created_by = ? AND tenant_id = ?");
                                                                    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                    $bookingCount += $stmt->fetchColumn();

                                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visa_applications WHERE created_by = ? AND tenant_id = ?");
                                                                    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                    $bookingCount += $stmt->fetchColumn();

                                                                    echo $bookingCount;
                                                                } catch (PDOException $e) {
                                                                    echo '0';
                                                                }
                                                                ?>
                                                            </h3>
                                                            <p class="small text-muted mb-0">Bookings</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Profile Tabs -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-header bg-white border-bottom">
                                                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab">
                                                            <i class="feather icon-user mr-1 d-none d-sm-inline"></i>
                                                            <span class="small">Overview</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="activity-tab" data-toggle="tab" href="#activity" role="tab">
                                                            <i class="feather icon-activity mr-1 d-none d-sm-inline"></i>
                                                            <span class="small">Activity</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="settings-tab" data-toggle="tab" href="#settings" role="tab">
                                                            <i class="feather icon-settings mr-1 d-none d-sm-inline"></i>
                                                            <span class="small">Settings</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="card-body p-3 p-md-4">
                                                <div class="tab-content" id="profileTabsContent">
                                                    
                                                    <!-- Overview Tab -->
                                                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                                        <div class="row">
                                                            <div class="col-lg-8 mb-4 mb-lg-0">
                                                                <h5 class="mb-3">
                                                                    <i class="feather icon-user mr-2"></i>Personal Information
                                                                </h5>
                                                                <div class="table-responsive">
                                                                    <table class="table table-borderless">
                                                                        <tbody>
                                                                            <tr class="border-bottom">
                                                                                <td class="font-weight-bold py-3">Full Name</td>
                                                                                <td class="text-muted py-3"><?= htmlspecialchars($user['name']) ?></td>
                                                                            </tr>
                                                                            <tr class="border-bottom">
                                                                                <td class="font-weight-bold py-3">Email</td>
                                                                                <td class="text-muted py-3 text-break"><?= htmlspecialchars($user['email']) ?></td>
                                                                            </tr>
                                                                            <tr class="border-bottom">
                                                                                <td class="font-weight-bold py-3">Phone</td>
                                                                                <td class="text-muted py-3"><?= htmlspecialchars($user['phone']) ?: 'Not provided' ?></td>
                                                                            </tr>
                                                                            <tr class="border-bottom">
                                                                                <td class="font-weight-bold py-3">Address</td>
                                                                                <td class="text-muted py-3"><?= htmlspecialchars($user['address']) ?: 'Not provided' ?></td>
                                                                            </tr>
                                                                            <tr class="border-bottom">
                                                                                <td class="font-weight-bold py-3">Role</td>
                                                                                <td class="text-muted py-3"><?= htmlspecialchars($user['role'] ?? 'Client') ?></td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td class="font-weight-bold py-3">Join Date</td>
                                                                                <td class="text-muted py-3"><?= date('M d, Y', strtotime($user['created_at'] ?? date('Y-m-d'))) ?></td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-4">
                                                                <h5 class="mb-3">
                                                                    <i class="feather icon-image mr-2"></i>Profile Picture
                                                                </h5>
                                                                <div class="text-center">
                                                                    <img src="<?= $imagePath ?>" alt="Profile" 
                                                                         class="img-fluid rounded mb-3" 
                                                                         style="max-width: 150px;">
                                                                    <p class="text-muted small">Update in Settings tab</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Activity Tab -->
                                                    <div class="tab-pane fade" id="activity" role="tabpanel">
                                                        <h5 class="mb-4">
                                                            <i class="feather icon-activity mr-2"></i>Recent Activity
                                                        </h5>
                                                        <div class="list-group">
                                                            <?php
                                                            try {
                                                                $activityStmt = $pdo->prepare("
                                                                    SELECT action, table_name, created_at
                                                                    FROM activity_log
                                                                    WHERE user_id = ? AND tenant_id = ?
                                                                    ORDER BY created_at DESC
                                                                    LIMIT 10
                                                                ");
                                                                $activityStmt->execute([$_SESSION['user_id'], $tenant_id]);
                                                                $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

                                                                if (count($activities) > 0) {
                                                                    foreach ($activities as $activity) {
                                                                        $action = htmlspecialchars($activity['action']);
                                                                        $table = htmlspecialchars($activity['table_name'] ?? 'system');
                                                                        $timestamp = date('M d, Y H:i A', strtotime($activity['created_at']));

                                                                        echo '<div class="list-group-item border-left-0 border-right-0">';
                                                                        echo '<div class="d-flex w-100 justify-content-between align-items-start">';
                                                                        echo '<div>';
                                                                        echo '<h6 class="mb-1">' . ucfirst($action) . ' - ' . $table . '</h6>';
                                                                        echo '<small class="text-muted">' . $timestamp . '</small>';
                                                                        echo '</div>';
                                                                        echo '</div>';
                                                                        echo '</div>';
                                                                    }
                                                                } else {
                                                                    echo '<div class="text-center py-5">';
                                                                    echo '<i class="feather icon-activity" style="font-size: 48px; color: #d1d5db;"></i>';
                                                                    echo '<p class="text-muted mt-3">No recent activity</p>';
                                                                    echo '</div>';
                                                                }
                                                            } catch (PDOException $e) {
                                                                echo '<div class="alert alert-warning">Unable to load activity data</div>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <!-- Settings Tab -->
                                                    <div class="tab-pane fade" id="settings" role="tabpanel">
                                                        <form id="profileUpdateForm" enctype="multipart/form-data">
                                                            <div class="row">
                                                                <div class="col-lg-8 mb-4">
                                                                    <h5 class="mb-3">
                                                                        <i class="feather icon-edit mr-2"></i>Edit Profile
                                                                    </h5>
                                                                    
                                                                    <div class="form-group">
                                                                        <label>Full Name</label>
                                                                        <input type="text" class="form-control" name="name" 
                                                                               value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                    </div>

                                                                    <div class="form-group">
                                                                        <label>Email Address</label>
                                                                        <input type="email" class="form-control" name="email" 
                                                                               value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                    </div>

                                                                    <div class="form-group">
                                                                        <label>Phone Number</label>
                                                                        <input type="tel" class="form-control" name="phone" 
                                                                               value="<?= htmlspecialchars($user['phone']) ?>">
                                                                    </div>

                                                                    <div class="form-group">
                                                                        <label>Address</label>
                                                                        <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                    </div>

                                                                    <button type="submit" class="btn btn-primary">
                                                                        <i class="feather icon-save mr-2"></i>Save Changes
                                                                    </button>

                                                                    <hr class="my-4">

                                                                    <h5 class="mb-3">
                                                                        <i class="feather icon-lock mr-2"></i>Change Password
                                                                    </h5>

                                                                    <div class="form-group">
                                                                        <label>Current Password</label>
                                                                        <input type="password" class="form-control" id="currentPassword">
                                                                    </div>

                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <div class="form-group">
                                                                                <label>New Password</label>
                                                                                <input type="password" class="form-control" id="newPassword">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <div class="form-group">
                                                                                <label>Confirm Password</label>
                                                                                <input type="password" class="form-control" id="confirmPassword">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <button type="button" class="btn btn-outline-primary" id="changePasswordBtn">
                                                                        <i class="feather icon-lock mr-2"></i>Update Password
                                                                    </button>
                                                                </div>

                                                                <div class="col-lg-4">
                                                                    <h5 class="mb-3">
                                                                        <i class="feather icon-camera mr-2"></i>Profile Picture
                                                                    </h5>

                                                                    <div class="upload-area p-3 p-sm-4 text-center" 
                                                                         onclick="document.getElementById('profileImage').click()">
                                                                        <img src="<?= $imagePath ?>" alt="Profile" 
                                                                             class="img-fluid rounded mb-3" 
                                                                             id="profilePreview"
                                                                             style="max-width: 120px;">
                                                                        <p class="mb-2 small">Click to change</p>
                                                                        <small class="text-muted d-block">JPG, PNG, GIF up to 5MB</small>
                                                                    </div>

                                                                    <input type="file" class="d-none" id="profileImage" 
                                                                           name="profile_image" accept="image/*" 
                                                                           onchange="previewImage(this)">
                                                                </div>
                                                            </div>
                                                        </form>
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

    <?php include '../modals/umrah/profile_modal.php'; ?>
    <?php include '../modals/umrah/settings_modal.php'; ?>

    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="../assets/js/mobile-menu.js"></script>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.getElementById('profileUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('update_client_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Profile updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating profile');
        });
    });

    document.getElementById('changePasswordBtn').addEventListener('click', function() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (!currentPassword || !newPassword || !confirmPassword) {
            alert('Please fill in all password fields');
            return;
        }

        if (newPassword !== confirmPassword) {
            alert('New passwords do not match');
            return;
        }

        const formData = new FormData();
        formData.append('current_password', currentPassword);
        formData.append('new_password', newPassword);
        formData.append('confirm_password', confirmPassword);

        fetch('api/change_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Password changed successfully!');
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error changing password');
        });
    });
    </script>
</body>
</html>