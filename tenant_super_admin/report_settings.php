<?php
/**
 * Report Settings Management for Tenant Super Admin
 * Allows configuration of monthly report generation and delivery
 */

include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'Security token validation failed. Please try again.';
    } else {
    try {
        // Check if settings exist
        $stmt = $pdo->prepare("SELECT id FROM report_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $settingsExist = $stmt->fetch();

        $monthly_report_enabled = isset($_POST['monthly_report_enabled']) ? 1 : 0;
        $monthly_report_day = max(1, min(31, (int)$_POST['monthly_report_day']));
        $monthly_report_time = $_POST['monthly_report_time'] ?? '08:00:00';
        $include_top_clients = isset($_POST['include_top_clients']) ? 1 : 0;
        $top_clients_limit = max(5, min(50, (int)($_POST['top_clients_limit'] ?? 10)));
        $include_top_suppliers = isset($_POST['include_top_suppliers']) ? 1 : 0;
        $top_suppliers_limit = max(5, min(50, (int)($_POST['top_suppliers_limit'] ?? 10)));
        $include_branch_comparison = isset($_POST['include_branch_comparison']) ? 1 : 0;
        $include_pdf_attachment = isset($_POST['include_pdf_attachment']) ? 1 : 0;

        if ($settingsExist) {
            // Update existing settings
            $stmt = $pdo->prepare("
                UPDATE report_settings
                SET monthly_report_enabled = ?,
                    monthly_report_day = ?,
                    monthly_report_time = ?,
                    include_top_clients = ?,
                    top_clients_limit = ?,
                    include_top_suppliers = ?,
                    top_suppliers_limit = ?,
                    include_branch_comparison = ?,
                    include_pdf_attachment = ?
                WHERE tenant_id = ?
            ");
            $stmt->execute([
                $monthly_report_enabled,
                $monthly_report_day,
                $monthly_report_time,
                $include_top_clients,
                $top_clients_limit,
                $include_top_suppliers,
                $top_suppliers_limit,
                $include_branch_comparison,
                $include_pdf_attachment,
                $tenant_id
            ]);
        } else {
            // Create new settings
            $stmt = $pdo->prepare("
                INSERT INTO report_settings
                (tenant_id, monthly_report_enabled, monthly_report_day, monthly_report_time, 
                 include_top_clients, top_clients_limit, include_top_suppliers, top_suppliers_limit, 
                 include_branch_comparison, include_pdf_attachment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenant_id,
                $monthly_report_enabled,
                $monthly_report_day,
                $monthly_report_time,
                $include_top_clients,
                $top_clients_limit,
                $include_top_suppliers,
                $top_suppliers_limit,
                $include_branch_comparison,
                $include_pdf_attachment
            ]);
        }

        $success_message = "Report settings saved successfully!";
    } catch (Exception $e) {
        error_log("Report settings error: " . $e->getMessage());
        $error_message = "Error saving settings: " . $e->getMessage();
    }
    }
}

// Handle recipient management
if (isset($_POST['add_recipient'])) {
    $recipient_email = filter_var($_POST['recipient_email'], FILTER_VALIDATE_EMAIL);
    $report_types = isset($_POST['report_types']) ? $_POST['report_types'] : [];

    if ($recipient_email) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO report_recipients (tenant_id, email, report_types, is_active)
                VALUES (?, ?, ?, TRUE)
                ON DUPLICATE KEY UPDATE report_types = ?, is_active = TRUE
            ");
            $reportTypesJson = json_encode($report_types);
            $stmt->execute([$tenant_id, $recipient_email, $reportTypesJson, $reportTypesJson]);
            $success_message = "Recipient added successfully!";
        } catch (Exception $e) {
            $error_message = "Error adding recipient: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid email address!";
    }
}

// Handle recipient deletion
if (isset($_POST['delete_recipient'])) {
    $recipient_id = (int)$_POST['recipient_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM report_recipients WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$recipient_id, $tenant_id]);
        $success_message = "Recipient removed successfully!";
    } catch (Exception $e) {
        $error_message = "Error removing recipient: " . $e->getMessage();
    }
}

// Fetch current settings
$stmt = $pdo->prepare("SELECT * FROM report_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];

// Fetch report recipients
$stmt = $pdo->prepare("SELECT * FROM report_recipients WHERE tenant_id = ? ORDER BY email");
$stmt->execute([$tenant_id]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent report logs
$stmt = $pdo->prepare("
    SELECT * FROM report_logs 
    WHERE tenant_id = ? 
    ORDER BY report_date DESC 
    LIMIT 20
");
$stmt->execute([$tenant_id]);
$reportLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Report Settings</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Report Settings</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather icon-check-circle"></i> <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="feather icon-alert-circle"></i> <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <!-- Report Settings Card -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-settings"></i> Monthly Report Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="monthly_report_enabled"
                                           name="monthly_report_enabled"
                                           <?= ($settings['monthly_report_enabled'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="monthly_report_enabled">
                                        Enable Monthly Profit Reports
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    Automatically generate and email monthly profit reports to configured recipients
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="monthly_report_day">Send Report On Day:</label>
                                        <input type="number" class="form-control" id="monthly_report_day"
                                               name="monthly_report_day" min="1" max="31"
                                               value="<?= $settings['monthly_report_day'] ?? 1 ?>">
                                        <small class="form-text text-muted">Day of month (1-31)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="monthly_report_time">Send at Time:</label>
                                        <input type="time" class="form-control" id="monthly_report_time"
                                               name="monthly_report_time"
                                               value="<?= $settings['monthly_report_time'] ?? '08:00:00' ?>">
                                        <small class="form-text text-muted">Server timezone</small>
                                    </div>
                                </div>
                            </div>

                            <hr>
                            <h6>Report Content Options</h6>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="include_top_clients"
                                           name="include_top_clients"
                                           <?= ($settings['include_top_clients'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="include_top_clients">
                                        Include Top Clients
                                    </label>
                                </div>
                                <input type="number" class="form-control mt-2" name="top_clients_limit"
                                       placeholder="Number of top clients to include" min="5" max="50"
                                       value="<?= $settings['top_clients_limit'] ?? 10 ?>">
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="include_top_suppliers"
                                           name="include_top_suppliers"
                                           <?= ($settings['include_top_suppliers'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="include_top_suppliers">
                                        Include Top Suppliers
                                    </label>
                                </div>
                                <input type="number" class="form-control mt-2" name="top_suppliers_limit"
                                       placeholder="Number of top suppliers to include" min="5" max="50"
                                       value="<?= $settings['top_suppliers_limit'] ?? 10 ?>">
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="include_branch_comparison"
                                           name="include_branch_comparison"
                                           <?= ($settings['include_branch_comparison'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="include_branch_comparison">
                                        Include Branch Comparison Report
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="include_pdf_attachment"
                                           name="include_pdf_attachment"
                                           <?= ($settings['include_pdf_attachment'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="include_pdf_attachment">
                                        Include Downloadable PDF Attachment
                                    </label>
                                </div>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-mail"></i> Recipients</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="text-muted mb-2">Report Recipients:</p>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php if (!empty($recipients)): ?>
                                    <?php foreach ($recipients as $recipient): ?>
                                        <div class="badge badge-info mb-2">
                                            <?= htmlspecialchars($recipient['email']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">No recipients configured</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Recipient -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-plus-circle"></i> Add Report Recipient</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="email" class="form-control" name="recipient_email"
                                           placeholder="Email address" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="add_recipient" class="btn btn-success btn-block">
                                        <i class="feather icon-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients List -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-list"></i> Report Recipients</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Email Address</th>
                                        <th>Report Types</th>
                                        <th>Status</th>
                                        <th>Added Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recipients)): ?>
                                        <?php foreach ($recipients as $recipient): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($recipient['email']) ?></td>
                                                <td>
                                                    <?php
                                                    $types = json_decode($recipient['report_types'], true) ?? [];
                                                    foreach ($types as $type) {
                                                        $typeName = ucwords(str_replace('_', ' ', $type));
                                                        echo '<span class="badge badge-primary">' . $typeName . '</span> ';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $recipient['is_active'] ? 'success' : 'danger' ?>">
                                                        <?= $recipient['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($recipient['created_at'])) ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="recipient_id" value="<?= $recipient['id'] ?>">
                                                        <button type="submit" name="delete_recipient" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Remove this recipient?')">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No recipients configured</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Log History -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-inbox"></i> Report Generation History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Report Type</th>
                                        <th>Generated Date</th>
                                        <th>Recipient</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($reportLogs)): ?>
                                        <?php foreach ($reportLogs as $log): ?>
                                            <tr>
                                                <td><?= ucwords(str_replace('_', ' ', $log['report_type'])) ?></td>
                                                <td><?= date('M d, Y H:i', strtotime($log['report_date'])) ?></td>
                                                <td><?= htmlspecialchars($log['recipient_email'] ?? '-') ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $log['status'] === 'sent' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($log['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y H:i', strtotime($log['updated_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No reports generated yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
