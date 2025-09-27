<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Handle new asset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $purchase_date = $_POST['purchase_date'];
    $purchase_value = $_POST['purchase_value'];
    $current_value = $_POST['current_value'];
    $currency = $_POST['currency'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $serial_number = $_POST['serial_number'];
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = $_POST['status'];
    $assigned_to = $_POST['assigned_to'];
    $condition_state = $_POST['condition_state'];
    
    // Handle document upload
    $document = '';
    if(isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx');
        $filename = $_FILES['document']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $new_filename = 'asset_doc_' . time() . '.' . $ext;
            $destination = '../uploads/assets/' . $new_filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/assets/')) {
                mkdir('../uploads/assets/', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
                $document = $new_filename;
            }
        }
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO assets (name, category, purchase_date, purchase_value, current_value, currency, description, location, serial_number, warranty_expiry, status, assigned_to, condition_state, document, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddsssssssssi", $name, $category, $purchase_date, $purchase_value, $current_value, $currency, $description, $location, $serial_number, $warranty_expiry, $status, $assigned_to, $condition_state, $document, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Asset added successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error adding asset: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle asset editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_asset'])) {
    $asset_id = $_POST['asset_id'];
    $name = $_POST['name'];
    $category = $_POST['category'];
    $purchase_date = $_POST['purchase_date'];
    $purchase_value = $_POST['purchase_value'];
    $current_value = $_POST['current_value'];
    $currency = $_POST['currency'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $serial_number = $_POST['serial_number'];
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = $_POST['status'];
    $assigned_to = $_POST['assigned_to'];
    $condition_state = $_POST['condition_state'];
    
    // Get current document
    $stmt = $conn->prepare("SELECT document FROM assets WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $asset_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $asset = $result->fetch_assoc();
    $current_document = $asset['document'];
    
    // Handle document upload
    $document = $current_document;
    if(isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx');
        $filename = $_FILES['document']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if(in_array(strtolower($ext), $allowed)) {
            $new_filename = 'asset_doc_' . time() . '.' . $ext;
            $destination = '../uploads/assets/' . $new_filename;
            
            // Create directory if it doesn't exist
            if (!file_exists('../uploads/assets/')) {
                mkdir('../uploads/assets/', 0777, true);
            }
            
            if(move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
                $document = $new_filename;
                
                // Delete old document if it exists
                if(!empty($current_document)) {
                    $old_file = '../uploads/assets/' . $current_document;
                    if(file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
            }
        }
    }
    
    try {
        $stmt = $conn->prepare("UPDATE assets SET name = ?, category = ?, purchase_date = ?, purchase_value = ?, current_value = ?, currency = ?, description = ?, location = ?, serial_number = ?, warranty_expiry = ?, status = ?, assigned_to = ?, condition_state = ?, document = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("sssddsssssssssi", $name, $category, $purchase_date, $purchase_value, $current_value, $currency, $description, $location, $serial_number, $warranty_expiry, $status, $assigned_to, $condition_state, $document, $asset_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Asset updated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating asset: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle asset deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_asset'])) {
    $asset_id = $_POST['asset_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE assets SET status = 'inactive' WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $asset_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Asset deactivated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deactivating asset: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle asset reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_asset'])) {
    $asset_id = $_POST['asset_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE assets SET status = 'active' WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $asset_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Asset reactivated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error reactivating asset: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle general status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $asset_id = $_POST['asset_id'];
    $new_status = $_POST['new_status'];
    
    // Validate the status value
    $valid_statuses = ['active', 'inactive', 'maintenance', 'sold', 'disposed'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error_message'] = "Invalid status value!";
        header('Location: ' . $redirect_url);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE assets SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("sii", $new_status, $asset_id, $tenant_id);
        $stmt->execute();
        
        $status_message = ucfirst($new_status);
        $_SESSION['success_message'] = "Asset marked as {$status_message} successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error changing asset status: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle asset deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset'])) {
    $asset_id = $_POST['asset_id'];
    
    try {
        // Get current document
        $stmt = $conn->prepare("SELECT document FROM assets WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $asset_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $asset = $result->fetch_assoc();
        
        // Delete the asset
        $stmt = $conn->prepare("DELETE FROM assets WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $asset_id, $tenant_id);
        $stmt->execute();
        
        // Delete the document file if it exists
        if(!empty($asset['document'])) {
            $file_path = '../uploads/assets/' . $asset['document'];
            if(file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $_SESSION['success_message'] = "Asset deleted successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting asset: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Determine which status to display
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';

// Build the SQL query with status filter
$sql = "SELECT * FROM assets WHERE 1=1 AND tenant_id = ?";

if ($status_filter !== 'all') {
    $sql .= " AND status = ?";
}

$sql .= " ORDER BY created_at DESC";

// Prepare and execute the query
if ($status_filter !== 'all') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status_filter, $tenant_id);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tenant_id);
}

$stmt->execute();
$result = $stmt->get_result();
$assets = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total value by currency
$currency_totals = [];
if (count($assets) > 0) {
    foreach ($assets as $asset) {
        $currency = $asset['currency'];
        $current_value = $asset['current_value'];
        
        if (!isset($currency_totals[$currency])) {
            $currency_totals[$currency] = 0;
        }
        
        $currency_totals[$currency] += $current_value;
    }
}



?>

    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-inactive {
            background-color: #6c757d;
            color: white;
        }
        .status-maintenance {
            background-color: #ffc107;
            color: black;
        }
        .status-sold {
            background-color: #17a2b8;
            color: white;
        }
        .status-disposed {
            background-color: #dc3545;
            color: white;
        }
        
        /* Enhanced UI Styles */
        .asset-card {
            transition: all 0.3s;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
        }
        
        .asset-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }
        
        .category-icon {
            font-size: 2rem;
            padding: 15px;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 123, 255, 0.1);
            color: #007bff;
            margin-bottom: 15px;
        }
        
        .summary-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
            transition: all 0.3s;
        }
        
        .summary-card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .summary-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2.5rem;
            opacity: 0.2;
            color: #007bff;
        }
        
        .asset-table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        .asset-detailed-view {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-section {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        .select2-container .select2-selection--single {
            height: 38px !important;
            padding: 8px 5px;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            margin-bottom: 20px;
        }

        /* Custom card headers */
        .card-header-gradient {
            background: linear-gradient(45deg, #007bff, #00c6ff);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        /* Custom badges */
        .custom-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 500;
        }

        /* Animation for cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate3d(0, 40px, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        .animated-card {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Action buttons styling */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
        }

        /* Timeline for asset history */
        .asset-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .asset-timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #007bff;
        }
        
        /* Progress bar for asset depreciation */
        .depreciation-progress {
            height: 8px;
            border-radius: 4px;
        }
        
        /* Custom tabs */
        .custom-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 10px 15px;
            font-weight: 500;
        }
        
        .custom-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            background-color: transparent;
        }
    </style>
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

    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('company_assets_management') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('assets') ?></a></li>
                                    </ul>
                                </div>
                                
                                <!-- Alerts -->
                                <?php if ($success_message): ?>
                                    <div class="col-md-12">
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php echo h($success_message); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($error_message): ?>
                                    <div class="col-md-12">
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <?php echo h($error_message); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Status Filters -->
                                <div class="col-md-12 mb-4">
                                    <div class="filter-section">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h5><i class="feather icon-filter mr-2"></i><?= __('filter_assets') ?></h5>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="btn-group float-right">
                                                    <a href="assets.php?status=active" class="btn <?php echo h($status_filter) == 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-check-circle mr-1"></i> <?= __('active') ?>
                                                    </a>
                                                    <a href="assets.php?status=inactive" class="btn <?php echo h($status_filter) == 'inactive' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-circle mr-1"></i> <?= __('inactive') ?>
                                                    </a>
                                                    <a href="assets.php?status=maintenance" class="btn <?php echo h($status_filter) == 'maintenance' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-tool mr-1"></i> <?= __('maintenance') ?>
                                                    </a>
                                                    <a href="assets.php?status=sold" class="btn <?php echo h($status_filter) == 'sold' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-shopping-cart mr-1"></i> <?= __('sold') ?>
                                                    </a>
                                                    <a href="assets.php?status=disposed" class="btn <?php echo h($status_filter) == 'disposed' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-trash-2 mr-1"></i> <?= __('disposed') ?>
                                                    </a>
                                                    <a href="assets.php?status=all" class="btn <?php echo h($status_filter) == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                                        <i class="feather icon-list mr-1"></i> <?= __('all') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Charts and Summary Section -->
                                <div class="col-md-12 mb-4">
                                    <div class="row">
                                        <!-- Total Assets Count -->
                                        <div class="col-md-3 mb-4">
                                            <div class="card summary-card animated-card">
                                                <div class="card-body">
                                                    <i class="feather icon-package summary-icon"></i>
                                                    <h6 class="text-uppercase text-muted"><?= __('total_assets') ?></h6>
                                                    <h2 class="mt-2 mb-2 text-primary"><?php echo count($assets); ?></h2>
                                                    <p class="mb-0 text-muted">
                                                        <span class="text-nowrap"><?php echo h($status_filter) == 'all' ? __('all') : ucfirst($status_filter); ?> <?= __('assets') ?></span>
                                                    </p>
                                                    <div class="progress mt-3 depreciation-progress">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Total Value by Currency -->
                                        <?php foreach ($currency_totals as $currency => $total): ?>
                                            <div class="col-md-3 mb-4">
                                                <div class="card summary-card animated-card" style="animation-delay: 0.1s;">
                                                    <div class="card-body">
                                                        <i class="feather icon-dollar-sign summary-icon"></i>
                                                        <h6 class="text-uppercase text-muted"><?= __('total_value') ?> (<?php echo h($currency); ?>)</h6>
                                                        <h2 class="mt-2 mb-2 text-success"><?php echo number_format($total, 2); ?></h2>
                                                        <p class="mb-0 text-muted">
                                                            <span class="text-nowrap"><?= __('current_value_of_assets') ?></span>
                                                        </p>
                                                        <div class="progress mt-3 depreciation-progress">
                                                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Charts Row - Fixed to display side-by-side -->
                                    <div class="row">
                                        <?php
                                        // Calculate categories count for the pie chart
                                        $categories = [];
                                        foreach ($assets as $asset) {
                                            $category = $asset['category'];
                                            if (!isset($categories[$category])) {
                                                $categories[$category] = 0;
                                            }
                                            $categories[$category]++;
                                        }
                                        ?>

                                        <!-- Category Distribution Card -->
                                        <div class="col-md-6 mb-4">
                                            <div class="card asset-card animated-card" style="animation-delay: 0.2s;">
                                                <div class="card-header card-header-gradient">
                                                    <h5 class="card-title text-white mb-0"><i class="feather icon-pie-chart mr-2"></i><?= __('asset_categories') ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="chart-container" style="position: relative; height: 300px; margin: 0 auto; max-width: 100%;">
                                                        <canvas id="categoryPieChart" width="400" height="300"></canvas>
                                                        <div id="category-chart-loading" class="text-center py-5">
                                                            <i class="feather icon-loader spin" style="font-size: 24px;"></i>
                                                            <p class="mt-2 text-muted"><?= __('loading_chart') ?>...</p>
                                                        </div>
                                                        <div id="category-chart-no-data" class="text-center py-5" style="display: none;">
                                                            <i class="feather icon-info text-muted" style="font-size: 36px;"></i>
                                                            <p class="mt-2 text-muted"><?= __('no_category_data_available') ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Asset Status Distribution Card -->
                                        <div class="col-md-6 mb-4">
                                            <div class="card asset-card animated-card" style="animation-delay: 0.3s;">
                                                <div class="card-header card-header-gradient">
                                                    <h5 class="card-title text-white mb-0"><i class="feather icon-bar-chart-2 mr-2"></i><?= __('asset_status_distribution') ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="chart-container" style="position: relative; height: 300px; margin: 0 auto; max-width: 100%;">
                                                        <canvas id="statusBarChart" width="400" height="300"></canvas>
                                                        <div id="status-chart-loading" class="text-center py-5">
                                                            <i class="feather icon-loader spin" style="font-size: 24px;"></i>
                                                            <p class="mt-2 text-muted"><?= __('loading_chart') ?>...</p>
                                                        </div>
                                                        <div id="status-chart-no-data" class="text-center py-5" style="display: none;">
                                                            <i class="feather icon-info text-muted" style="font-size: 36px;"></i>
                                                            <p class="mt-2 text-muted"><?= __('no_status_data_available') ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Advanced Filter and Search Section -->
                                <div class="col-md-12 mb-4">
                                    <div class="card asset-card animated-card" style="animation-delay: 0.4s;">
                                        <div class="card-header">
                                            <h5><i class="feather icon-search mr-2"></i><?= __('advanced_search') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="filter-category"><?= __('category') ?></label>
                                                        <select class="form-control select2" id="filter-category">
                                                            <option value=""><?= __('all_categories') ?></option>
                                                            <option value="Electronics"><?= __('electronics') ?></option>
                                                            <option value="Furniture"><?= __('furniture') ?></option>
                                                            <option value="Vehicle"><?= __('vehicle') ?></option>
                                                            <option value="Office Equipment"><?= __('office_equipment') ?></option>
                                                            <option value="Real Estate"><?= __('real_estate') ?></option>
                                                            <option value="Software"><?= __('software') ?></option>
                                                            <option value="Other"><?= __('other') ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="filter-location"><?= __('location') ?></label>
                                                        <input type="text" class="form-control" id="filter-location" placeholder="Filter by location">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="filter-date-from"><?= __('purchase_date_from') ?></label>
                                                        <input type="date" class="form-control" id="filter-date-from">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="filter-date-to"><?= __('purchase_date_to') ?></label>
                                                        <input type="date" class="form-control" id="filter-date-to">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-md-12 text-right">
                                                    <button type="button" id="clear-filters" class="btn btn-light mr-2">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('clear') ?>
                                                    </button>
                                                    <button type="button" id="apply-filters" class="btn btn-primary">
                                                        <i class="feather icon-filter mr-1"></i><?= __('apply_filters') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Add Asset Button -->
                                <div class="col-md-12 mb-4">
                                    <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addAssetModal">
                                        <i class="feather icon-plus-circle mr-1"></i> <?= __('add_new_asset') ?>
                                    </button>
                                </div>
                                
                                <!-- Assets Table -->
                                <div class="col-md-12">
                                    <div class="card asset-card animated-card" style="animation-delay: 0.5s;">
                                        <div class="card-header card-header-gradient">
                                            <h5 class="card-title text-white mb-0"><i class="feather icon-database mr-2"></i><?= __('company_assets') ?></h5>
                                        </div>
                                        <div class="card-body table-border-style">
                                            <div class="table-responsive">
                                                <table id="assets-table" class="table table-hover asset-table">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('id') ?></th>
                                                            <th><?= __('name') ?></th>
                                                            <th><?= __('category') ?></th>
                                                            <th><?= __('purchase_date') ?></th>
                                                            <th><?= __('current_value') ?></th>
                                                            <th><?= __('location') ?></th>
                                                            <th><?= __('status') ?></th>
                                                            <th><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (count($assets) > 0): ?>
                                                            <?php foreach ($assets as $asset): ?>
                                                                <tr>
                                                                    <td><?php echo h($asset['id']); ?></td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <?php
                                                                            // Define icon based on category
                                                                            $icon = 'box';
                                                                            switch($asset['category']) {
                                                                                case 'Electronics':
                                                                                    $icon = 'cpu';
                                                                                    break;
                                                                                case 'Furniture':
                                                                                    $icon = 'home';
                                                                                    break;
                                                                                case 'Vehicle':
                                                                                    $icon = 'truck';
                                                                                    break;
                                                                                case 'Office Equipment':
                                                                                    $icon = 'printer';
                                                                                    break;
                                                                                case 'Real Estate':
                                                                                    $icon = 'layout';
                                                                                    break;
                                                                                case 'Software':
                                                                                    $icon = 'code';
                                                                                    break;
                                                                            }
                                                                            ?>
                                                                            <div class="mr-3 bg-light p-2 rounded">
                                                                                <i class="feather icon-<?php echo h($icon); ?>"></i>
                                                                            </div>
                                                                            <div>
                                                                                <h6 class="mb-0"><?php echo htmlspecialchars($asset['name']); ?></h6>
                                                                                <small class="text-muted"><?php echo !empty($asset['serial_number']) ? 'SN: ' . htmlspecialchars($asset['serial_number']) : ''; ?></small>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td><span class="custom-badge bg-light"><?php echo htmlspecialchars($asset['category']); ?></span></td>
                                                                    <td><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></td>
                                                                    <td>
                                                                        <strong><?php echo number_format($asset['current_value'], 2); ?></strong>
                                                                        <small class="text-muted"><?php echo h($asset['currency']); ?></small>
                                                                        
                                                                        <?php 
                                                                        // Calculate depreciation percentage
                                                                        $depreciation = 0;
                                                                        if ($asset['purchase_value'] > 0) {
                                                                            $depreciation = 100 - (($asset['current_value'] / $asset['purchase_value']) * 100);
                                                                        }
                                                                        
                                                                        $depreciationClass = 'success';
                                                                        if ($depreciation > 25) $depreciationClass = 'info';
                                                                        if ($depreciation > 50) $depreciationClass = 'warning';
                                                                        if ($depreciation > 75) $depreciationClass = 'danger';
                                                                        ?>
                                                                        
                                                                        <div class="progress mt-1 depreciation-progress">
                                                                            <div class="progress-bar bg-<?php echo h($depreciationClass); ?>" role="progressbar" 
                                                                                 style="width: <?php echo min(100, $depreciation); ?>%" 
                                                                                 aria-valuenow="<?php echo h($depreciation); ?>" 
                                                                                 aria-valuemin="0" 
                                                                                 aria-valuemax="100" 
                                                                                 title="Depreciated <?php echo round($depreciation, 1); ?>%">
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($asset['location'] ?: 'N/A'); ?></td>
                                                                    <td>
                                                                        <?php
                                                                        // Define badge colors
                                                                        $statusClass = 'secondary';
                                                                        $statusIcon = 'circle';
                                                                        
                                                                        switch($asset['status']) {
                                                                            case 'active':
                                                                                $statusClass = 'success';
                                                                                $statusIcon = 'check-circle';
                                                                                break;
                                                                            case 'inactive':
                                                                                $statusClass = 'secondary';
                                                                                $statusIcon = 'circle';
                                                                                break;
                                                                            case 'maintenance':
                                                                                $statusClass = 'warning';
                                                                                $statusIcon = 'tool';
                                                                                break;
                                                                            case 'sold':
                                                                                $statusClass = 'info';
                                                                                $statusIcon = 'shopping-cart';
                                                                                break;
                                                                            case 'disposed':
                                                                                $statusClass = 'danger';
                                                                                $statusIcon = 'trash-2';
                                                                                break;
                                                                        }
                                                                        ?>
                                                                        <span class="badge badge-<?php echo h($statusClass); ?>">
                                                                            <i class="feather icon-<?php echo h($statusIcon); ?> mr-1"></i>
                                                                            <?php echo ucfirst($asset['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex">
                                                                            <!-- View Details Button -->
                                                                            <button type="button" class="action-btn btn btn-icon btn-info btn-sm" data-toggle="modal" data-target="#viewAssetModal<?php echo h($asset['id']); ?>" title="<?= __('view_details') ?>">
                                                                                <i class="feather icon-eye"></i>
                                                                            </button>
                                                                            
                                                                            <!-- Edit Button -->
                                                                            <button type="button" class="action-btn btn btn-icon btn-warning btn-sm" data-toggle="modal" data-target="#editAssetModal<?php echo h($asset['id']); ?>" title="<?= __('edit_asset') ?>">
                                                                                <i class="feather icon-edit-2"></i>
                                                                            </button>
                                                                            
                                                                            <!-- Status Actions Dropdown -->
                                                                            <div class="dropdown">
                                                                                <button class="action-btn btn btn-icon btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton<?php echo h($asset['id']); ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                    <i class="feather icon-more-vertical"></i>
                                                                                </button>
                                                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton<?php echo h($asset['id']); ?>">
                                                                                    <?php if ($asset['status'] === 'active'): ?>
                                                                                        <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_deactivate_this_asset') ?>');">

    
                                                                                            <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                            <button type="submit" name="deactivate_asset" class="dropdown-item">
                                                                                                <i class="feather icon-circle mr-2"></i><?= __('mark_as_inactive') ?>
                                                                                            </button>
                                                                                        </form>
                                                                                        <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_in_maintenance') ?>');">

    
                                                                                            <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                            <input type="hidden" name="new_status" value="maintenance">
                                                                                            <button type="submit" name="change_status" class="dropdown-item">
                                                                                                <i class="feather icon-tool mr-2"></i><?= __('mark_as_in_maintenance') ?>
                                                                                            </button>
                                                                                        </form>
                                                                                    <?php elseif ($asset['status'] === 'inactive'): ?>
                                                                                        <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_reactivate_this_asset') ?>');">

    
                                                                                            <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                            <button type="submit" name="reactivate_asset" class="dropdown-item">
                                                                                                <i class="feather icon-check-circle mr-2"></i><?= __('mark_as_active') ?>
                                                                                            </button>
                                                                                        </form>
                                                                                    <?php endif; ?>
                                                                                    
                                                                                    <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_sold') ?>');">
 
    
                                                                                        <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                        <input type="hidden" name="new_status" value="sold">
                                                                                        <button type="submit" name="change_status" class="dropdown-item">
                                                                                            <i class="feather icon-shopping-cart mr-2"></i><?= __('mark_as_sold') ?>
                                                                                        </button>
                                                                                    </form>
                                                                                    
                                                                                    <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_mark_this_asset_as_disposed') ?>');">
 
    
                                                                                        <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                        <input type="hidden" name="new_status" value="disposed">
                                                                                        <button type="submit" name="change_status" class="dropdown-item">
                                                                                            <i class="feather icon-trash-2 mr-2"></i><?= __('mark_as_disposed') ?>
                                                                                        </button>
                                                                                    </form>
                                                                                    
                                                                                    <div class="dropdown-divider"></div>
                                                                                    
                                                                                    <form method="POST" onsubmit="return confirm('<?= __('are_you_sure_you_want_to_delete_this_asset') ?>');">
   
    
                                                                                        <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                                                                                        <button type="submit" name="delete_asset" class="dropdown-item text-danger">
                                                                                            <i class="feather icon-trash mr-2"></i><?= __('delete_asset') ?>
                                                                                        </button>
                                                                                    </form>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
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
    </div>

    <!-- Add Asset Modal -->
    <div class="modal fade" id="addAssetModal" tabindex="-1" role="dialog" aria-labelledby="addAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAssetModalLabel"><?= __('add_new_asset') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name"><?= __('asset_name') ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category"><?= __('category') ?> *</label>
                                    <select class="form-control" id="category" name="category" required>
                                        <option value=""><?= __('select_category') ?></option>
                                        <option value="Electronics"><?= __('electronics') ?></option>
                                        <option value="Furniture"><?= __('furniture') ?></option>
                                        <option value="Vehicle"><?= __('vehicle') ?></option>
                                        <option value="Office Equipment"><?= __('office_equipment') ?></option>
                                        <option value="Real Estate"><?= __('real_estate') ?></option>
                                        <option value="Software"><?= __('software') ?></option>
                                        <option value="Other"><?= __('other') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="purchase_date"><?= __('purchase_date') ?> *</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="warranty_expiry"><?= __('warranty_expiry_date') ?></label>
                                    <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="purchase_value"><?= __('purchase_value') ?> *</label>
                                    <input type="number" step="0.01" class="form-control" id="purchase_value" name="purchase_value" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="current_value"><?= __('current_value') ?> *</label>
                                    <input type="number" step="0.01" class="form-control" id="current_value" name="current_value" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="currency"><?= __('currency') ?> *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value="USD"><?= __('usd') ?></option>
                                        <option value="AFS"><?= __('afs') ?></option>
                                        <option value="EUR"><?= __('eur') ?></option>
                                        <option value="DARHAM"><?= __('darham') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="location"><?= __('location') ?></label>
                                    <input type="text" class="form-control" id="location" name="location">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="serial_number"><?= __('serial_number') ?></label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status"><?= __('status') ?> *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="active"><?= __('active') ?></option>
                                        <option value="inactive"><?= __('inactive') ?></option>
                                        <option value="maintenance"><?= __('maintenance') ?></option>
                                        <option value="sold"><?= __('sold') ?></option>
                                        <option value="disposed"><?= __('disposed') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="condition_state"><?= __('condition') ?></label>
                                    <select class="form-control" id="condition_state" name="condition_state">
                                        <option value=""><?= __('select_condition') ?></option>
                                        <option value="New"><?= __('new') ?></option>
                                        <option value="Excellent"><?= __('excellent') ?></option>
                                        <option value="Good"><?= __('good') ?></option>
                                        <option value="Fair"><?= __('fair') ?></option>
                                        <option value="Poor"><?= __('poor') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="assigned_to"><?= __('assigned_to') ?></label>
                            <input type="text" class="form-control" id="assigned_to" name="assigned_to">
                        </div>
                        
                        <div class="form-group">
                            <label for="description"><?= __('description') ?></label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="document"><?= __('document') ?> (<?= __('invoice') ?>/<?= __('warranty') ?>/<?= __('receipt') ?>)</label>
                            <input type="file" class="form-control-file" id="document" name="document">
                            <small class="form-text text-muted"><?= __('supported_formats') ?>: JPG, JPEG, PNG, PDF, DOC, DOCX</small
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" name="add_asset" class="btn btn-primary"><?= __('add_asset') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Asset Modals (View and Edit) -->
    <?php foreach ($assets as $asset): ?>
        <!-- View Asset Modal -->
        <div class="modal fade" id="viewAssetModal<?php echo h($asset['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('asset_details') ?> - <?php echo htmlspecialchars($asset['name']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('name') ?>:</strong></label>
                                    <p><?php echo htmlspecialchars($asset['name']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('category') ?>:</strong></label>
                                    <p><?php echo htmlspecialchars($asset['category']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('purchase_date') ?>:</strong></label>
                                    <p><?php echo date('M d, Y', strtotime($asset['purchase_date'])); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('warranty_expiry') ?>:</strong></label>
                                    <p><?php echo h($asset['warranty_expiry']) ? date('M d, Y', strtotime($asset['warranty_expiry'])) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><strong><?= __('purchase_value') ?>:</strong></label>
                                    <p><?php echo number_format($asset['purchase_value'], 2) . ' ' . $asset['currency']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><strong><?= __('current_value') ?>:</strong></label>
                                    <p><?php echo number_format($asset['current_value'], 2) . ' ' . $asset['currency']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><strong><?= __('currency') ?>:</strong></label>
                                    <p><?php echo h($asset['currency']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('location') ?>:</strong></label>
                                    <p><?php echo htmlspecialchars($asset['location'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('serial_number') ?>:</strong></label>
                                    <p><?php echo htmlspecialchars($asset['serial_number'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('status') ?>:</strong></label>
                                    <p>
                                        <span class="status-badge status-<?php echo h($asset['status']); ?>">
                                            <?php echo ucfirst($asset['status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><strong><?= __('condition') ?>:</strong></label>
                                    <p><?php echo htmlspecialchars($asset['condition_state'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><strong><?= __('assigned_to') ?>:</strong></label>
                            <p><?php echo htmlspecialchars($asset['assigned_to'] ?: 'N/A'); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label><strong><?= __('description') ?>:</strong></label>
                            <p><?php echo nl2br(htmlspecialchars($asset['description'] ?: 'N/A')); ?></p>
                        </div>
                        
                        <?php if (!empty($asset['document'])): ?>
                        <div class="form-group">
                            <label><strong><?= __('document') ?>:</strong></label>
                            <p>
                                <a href="../uploads/assets/<?php echo h($asset['document']); ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="feather icon-file"></i> <?= __('view_document') ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label><strong><?= __('added_on') ?>:</strong></label>
                            <p><?php echo date('M d, Y H:i', strtotime($asset['created_at'])); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label><strong><?= __('last_updated') ?>:</strong></label>
                            <p><?php echo date('M d, Y H:i', strtotime($asset['updated_at'])); ?></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                        <button type="button" 
                                class="btn btn-warning openEditFromView" 
                                data-id="<?= h($asset['id']); ?>">
                            <?= __('edit_asset') ?>
                        </button>

                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Asset Modal -->
        <div class="modal fade" id="editAssetModal<?php echo h($asset['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('edit_asset') ?> - <?php echo htmlspecialchars($asset['name']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="asset_id" value="<?php echo h($asset['id']); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name<?php echo h($asset['id']); ?>"><?= __('asset_name') ?> *</label>
                                        <input type="text" class="form-control" id="name<?php echo h($asset['id']); ?>" name="name" value="<?php echo htmlspecialchars($asset['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category<?php echo h($asset['id']); ?>"><?= __('category') ?> *</label>
                                        <select class="form-control" id="category<?php echo h($asset['id']); ?>" name="category" required>
                                            <option value=""><?= __('select_category') ?></option>
                                            <option value="Electronics" <?php echo h($asset['category']) == 'Electronics' ? 'selected' : ''; ?>><?= __('electronics') ?></option>
                                            <option value="Furniture" <?php echo h($asset['category']) == 'Furniture' ? 'selected' : ''; ?>><?= __('furniture') ?></option>
                                            <option value="Vehicle" <?php echo h($asset['category']) == 'Vehicle' ? 'selected' : ''; ?>><?= __('vehicle') ?></option>
                                            <option value="Office Equipment" <?php echo h($asset['category']) == 'Office Equipment' ? 'selected' : ''; ?>><?= __('office_equipment') ?></option>
                                            <option value="Real Estate" <?php echo h($asset['category']) == 'Real Estate' ? 'selected' : ''; ?>><?= __('real_estate') ?></option>
                                            <option value="Software" <?php echo h($asset['category']) == 'Software' ? 'selected' : ''; ?>><?= __('software') ?></option>
                                            <option value="Other" <?php echo h($asset['category']) == 'Other' ? 'selected' : ''; ?>><?= __('other') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purchase_date<?php echo h($asset['id']); ?>"><?= __('purchase_date') ?> *</label>
                                        <input type="date" class="form-control" id="purchase_date<?php echo h($asset['id']); ?>" name="purchase_date" value="<?php echo h($asset['purchase_date']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="warranty_expiry<?php echo h($asset['id']); ?>"><?= __('warranty_expiry_date') ?></label>
                                        <input type="date" class="form-control" id="warranty_expiry<?php echo h($asset['id']); ?>" name="warranty_expiry" value="<?php echo h($asset['warranty_expiry']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="purchase_value<?php echo h($asset['id']); ?>"><?= __('purchase_value') ?> *</label>
                                        <input type="number" step="0.01" class="form-control" id="purchase_value<?php echo h($asset['id']); ?>" name="purchase_value" value="<?php echo h($asset['purchase_value']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="current_value<?php echo h($asset['id']); ?>"><?= __('current_value') ?> *</label>
                                        <input type="number" step="0.01" class="form-control" id="current_value<?php echo h($asset['id']); ?>" name="current_value" value="<?php echo h($asset['current_value']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="currency<?php echo h($asset['id']); ?>"><?= __('currency') ?> *</label>
                                        <select class="form-control" id="currency<?php echo h($asset['id']); ?>" name="currency" required>
                                            <option value="USD" <?php echo h($asset['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                            <option value="AFS" <?php echo h($asset['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                            <option value="EUR" <?php echo h($asset['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                            <option value="DARHAM" <?php echo h($asset['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="location<?php echo h($asset['id']); ?>"><?= __('location') ?></label>
                                        <input type="text" class="form-control" id="location<?php echo h($asset['id']); ?>" name="location" value="<?php echo htmlspecialchars($asset['location']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="serial_number<?php echo h($asset['id']); ?>"><?= __('serial_number') ?></label>
                                        <input type="text" class="form-control" id="serial_number<?php echo h($asset['id']); ?>" name="serial_number" value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status<?php echo h($asset['id']); ?>"><?= __('status') ?> *</label>
                                        <select class="form-control" id="status<?php echo h($asset['id']); ?>" name="status" required>
                                            <option value="active" <?php echo h($asset['status']) == 'active' ? 'selected' : ''; ?>><?= __('active') ?></option>
                                            <option value="inactive" <?php echo h($asset['status']) == 'inactive' ? 'selected' : ''; ?>><?= __('inactive') ?></option>
                                            <option value="maintenance" <?php echo h($asset['status']) == 'maintenance' ? 'selected' : ''; ?>><?= __('maintenance') ?></option>
                                            <option value="sold" <?php echo h($asset['status']) == 'sold' ? 'selected' : ''; ?>><?= __('sold') ?></option>
                                            <option value="disposed" <?php echo h($asset['status']) == 'disposed' ? 'selected' : ''; ?>><?= __('disposed') ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="condition_state<?php echo h($asset['id']); ?>"><?= __('condition') ?></label>
                                        <select class="form-control" id="condition_state<?php echo h($asset['id']); ?>" name="condition_state">
                                            <option value=""><?= __('select_condition') ?></option>
                                            <option value="New" <?php echo h($asset['condition_state']) == 'New' ? 'selected' : ''; ?>><?= __('new') ?></option>
                                            <option value="Excellent" <?php echo h($asset['condition_state']) == 'Excellent' ? 'selected' : ''; ?>><?= __('excellent') ?></option>
                                            <option value="Good" <?php echo h($asset['condition_state']) == 'Good' ? 'selected' : ''; ?>><?= __('good') ?></option>
                                            <option value="Fair" <?php echo h($asset['condition_state']) == 'Fair' ? 'selected' : ''; ?>><?= __('fair') ?></option>
                                            <option value="Poor" <?php echo h($asset['condition_state']) == 'Poor' ? 'selected' : ''; ?>><?= __('poor') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="assigned_to<?php echo h($asset['id']); ?>"><?= __('assigned_to') ?></label>
                                <input type="text" class="form-control" id="assigned_to<?php echo h($asset['id']); ?>" name="assigned_to" value="<?php echo htmlspecialchars($asset['assigned_to']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="description<?php echo h($asset['id']); ?>"><?= __('description') ?></label>
                                    <textarea class="form-control" id="description<?php echo h($asset['id']); ?>" name="description" rows="3"><?php echo htmlspecialchars($asset['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="document<?php echo h($asset['id']); ?>"><?= __('document') ?> (<?= __('invoice') ?>/<?= __('warranty') ?>/<?= __('receipt') ?>)</label>
                                <?php if (!empty($asset['document'])): ?>
                                    <div class="mb-2">
                                        <a href="../uploads/assets/<?php echo h($asset['document']); ?>" target="_blank"><?= __('current_document') ?></a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control-file" id="document<?php echo h($asset['id']); ?>" name="document">
                                <small class="form-text text-muted"><?= __('supported_formats') ?>: JPG, JPEG, PNG, PDF, DOC, DOCX</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                            <button type="submit" name="edit_asset" class="btn btn-warning"><?= __('update_asset') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

    <script>
        // Initialize datepickers and set default values
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date as default for date fields
            var today = new Date().toISOString().split('T')[0];
            document.getElementById('purchase_date').value = today;
            
            // Auto-calculate current value based on purchase value
            document.getElementById('purchase_value').addEventListener('change', function() {
                document.getElementById('current_value').value = this.value;
            });
            
            // Initialize DataTables
            $('#assets-table').DataTable({
                responsive: true,
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "<i class='feather icon-search'></i>",
                    searchPlaceholder: "<?= __('search_assets') ?>...",
                    paginate: {
                        next: '<i class="feather icon-chevron-right"></i>',
                        previous: '<i class="feather icon-chevron-left"></i>'
                    }
                }
            });
            
            // Initialize Select2
            $('.select2').select2({
                width: '100%'
            });
            
            // Initialize category pie chart
            var ctxPie = document.getElementById('categoryPieChart');
            if (ctxPie) {
                var categoryData = <?php echo json_encode($categories); ?>;
                var labels = Object.keys(categoryData);
                var data = Object.values(categoryData);
                
                var backgroundColors = [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)'
                ];
                
                new Chart(ctxPie, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: backgroundColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            title: {
                                display: false
                            }
                        },
                        cutout: '60%'
                    }
                });
            }
            
            // Initialize status bar chart
            var ctxBar = document.getElementById('statusBarChart');
            if (ctxBar) {
                <?php
                // Calculate status count
                $statuses = [
                    'active' => 0,
                    'inactive' => 0,
                    'maintenance' => 0,
                    'sold' => 0,
                    'disposed' => 0
                ];
                
                foreach ($assets as $asset) {
                    if (isset($statuses[$asset['status']])) {
                        $statuses[$asset['status']]++;
                    }
                }
                ?>
                
                var statusData = <?php echo json_encode($statuses); ?>;
                var statusLabels = Object.keys(statusData).map(function(status) {
                    return status.charAt(0).toUpperCase() + status.slice(1);
                });
                var statusCounts = Object.values(statusData);
                
                var statusColors = {
                    'active': '#28a745',
                    'inactive': '#6c757d',
                    'maintenance': '#ffc107',
                    'sold': '#17a2b8',
                    'disposed': '#dc3545'
                };
                
                var backgroundColors = Object.keys(statusData).map(function(status) {
                    return statusColors[status];
                });
                
                new Chart(ctxBar, {
                    type: 'bar',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            label: 'Assets',
                            data: statusCounts,
                            backgroundColor: backgroundColors,
                            borderWidth: 0,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    drawBorder: false
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
            
            // Advanced filters functionality
            $('#apply-filters').on('click', function() {
                var table = $('#assets-table').DataTable();
                
                // Clear existing filters
                table.search('').columns().search('').draw();
                
                // Apply category filter
                var category = $('#filter-category').val();
                if (category) {
                    table.column(2).search(category).draw();
                }
                
                // Apply location filter
                var location = $('#filter-location').val();
                if (location) {
                    table.column(5).search(location).draw();
                }
                
                // Apply purchase date range filter - this requires custom filtering
                var dateFrom = $('#filter-date-from').val();
                var dateTo = $('#filter-date-to').val();
                
                if (dateFrom || dateTo) {
                    $.fn.dataTable.ext.search.push(
                        function(settings, data, dataIndex) {
                            var purchaseDate = new Date(data[3]);
                            purchaseDate.setHours(0, 0, 0, 0);
                            
                            var from = dateFrom ? new Date(dateFrom) : null;
                            if (from) from.setHours(0, 0, 0, 0);
                            
                            var to = dateTo ? new Date(dateTo) : null;
                            if (to) to.setHours(23, 59, 59, 999);
                            
                            if (from && to) {
                                return purchaseDate >= from && purchaseDate <= to;
                            } else if (from) {
                                return purchaseDate >= from;
                            } else if (to) {
                                return purchaseDate <= to;
                            }
                            return true;
                        }
                    );
                    
                    table.draw();
                    
                    // Remove the custom filter after use
                    $.fn.dataTable.ext.search.pop();
                }
            });
            
            // Clear all filters
            $('#clear-filters').on('click', function() {
                $('#filter-category').val('').trigger('change');
                $('#filter-location').val('');
                $('#filter-date-from').val('');
                $('#filter-date-to').val('');
                
                var table = $('#assets-table').DataTable();
                table.search('').columns().search('').draw();
            });
        });
    </script>
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
$(document).on('click', '.openEditFromView', function() {
    var id = $(this).data('id');

    // Close the view modal first
    $('#viewAssetModal' + id).modal('hide');

    // After it's fully hidden, open the edit modal
    $('#viewAssetModal' + id).on('hidden.bs.modal', function () {
        $('#editAssetModal' + id).modal('show');
        $(this).off('hidden.bs.modal'); // prevent duplicate firing
    });
});
</script>
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
</body>
</html> 