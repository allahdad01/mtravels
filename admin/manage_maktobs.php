<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to admin dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Get any flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear flash messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Handle maktob submission via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prepare data for API call
    $postData = [
        'subject' => $_POST['subject'] ?? '',
        'content' => $_POST['content'] ?? '',
        'company_name' => $_POST['company_name'] ?? '',
        'maktob_number' => $_POST['maktob_number'] ?? '',
        'maktob_date' => $_POST['maktob_date'] ?? '',
        'language' => $_POST['language'] ?? 'english'
    ];

    // Call the API endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, '../api/maktob/manage.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['success']) && $responseData['success']) {
        $_SESSION['success_message'] = $responseData['message'] ?? __('letter_created');
    } else {
        $_SESSION['error_message'] = $responseData['message'] ?? __('error_creating_letter');
    }

    // Redirect back to the same page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch recent maktobs via API
$recent_maktobs_result = null;
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, '../api/maktob/manage.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data'])) {
            // Convert API response to a format similar to the original mysqli result
            $maktobsData = $responseData['data'];

            // Create a mock result object that mimics mysqli_result
            class MockMysqliResult {
                private $data;
                private $currentIndex = 0;

                public function __construct($data) {
                    $this->data = $data;
                }

                public function fetch_assoc() {
                    if ($this->currentIndex < count($this->data)) {
                        $row = $this->data[$this->currentIndex];
                        $this->currentIndex++;
                        return $row;
                    }
                    return null;
                }
            }

            $recent_maktobs_result = new MockMysqliResult($maktobsData);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching maktobs: " . $e->getMessage());
    // Fallback to empty result
    $recent_maktobs_result = null;
}

// Include the header
include '../includes/header.php';

?>
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
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('manage_letters') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php"><?= __('dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('manage_letters') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <!-- Create Maktob Card -->
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="feather icon-file-text mr-2"></i>
                            <?= __('create_new_letter') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="maktob_number"><?= __('letter_number') ?></label>
                                        <input type="text" class="form-control" id="maktob_number" name="maktob_number" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="maktob_date"><?= __('letter_date') ?></label>
                                        <input type="date" class="form-control" id="maktob_date" name="maktob_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="company_name"><?= __('company_name') ?></label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="language"><?= __('language') ?></label>
                                        <select class="form-control" id="language" name="language" required>
                                            <option value="english"><?= __('english') ?></option>
                                            <option value="dari"><?= __('dari') ?></option>
                                            <option value="pashto"><?= __('pashto') ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subject"><?= __('subject') ?></label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="content"><?= __('content') ?></label>
                                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary float-right">
                                        <i class="feather icon-save mr-2"></i><?= __('create_letter') ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Maktobs Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-clock mr-2"></i><?= __('recent_letters') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('letter_number') ?></th>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('subject') ?></th>
                                        <th><?= __('company_name') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('language') ?></th>
                                        <th><?= __('created_by') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_maktobs_result !== null): ?>
                                        <?php while ($row = mysqli_fetch_assoc($recent_maktobs_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['maktob_number']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($row['maktob_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'sent'): ?>
                                                <span class="badge badge-success">
                                                    <i class="feather icon-check mr-1"></i> <?= __('sent') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">
                                                    <i class="feather icon-clock mr-1"></i> <?= __('draft') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $langBadgeClass = 'primary';
                                            if ($row['language'] === 'dari') $langBadgeClass = 'info';
                                            if ($row['language'] === 'pashto') $langBadgeClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $langBadgeClass; ?>">
                                                <?= __($row['language'] ?? 'english') ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-icon btn-sm btn-primary view-maktob" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                    data-content="<?php echo htmlspecialchars($row['content']); ?>"
                                                    data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                                    data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                                                    data-date="<?php echo date('F j, Y', strtotime($row['maktob_date'])); ?>"
                                                    data-status="<?php echo $row['status']; ?>"
                                                    data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>"
                                                    data-file-path="<?php echo htmlspecialchars($row['file_path'] ?? ''); ?>"
                                                    data-pdf-path="<?php echo htmlspecialchars($row['pdf_path'] ?? ''); ?>"
                                                    data-toggle="tooltip" title="<?= __('view') ?>">
                                                    <i class="feather icon-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-warning edit-maktob" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                    data-content="<?php echo htmlspecialchars($row['content']); ?>"
                                                    data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                                    data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                                                    data-date="<?php echo $row['maktob_date']; ?>"
                                                    data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>"
                                                    data-toggle="tooltip" title="<?= __('edit') ?>">
                                                    <i class="feather icon-edit-2"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-danger delete-maktob" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-toggle="tooltip" title="<?= __('delete') ?>">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                                <a href="../api/maktob/download_maktob.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-sm btn-success" data-toggle="tooltip" title="<?= __('download_pdf') ?>" target="_blank">
                                                    <i class="feather icon-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted"><?= __('no_letters_found') ?></td>
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


<?php include '../modals/maktob/view_modal.php'; ?>
<?php include '../modals/maktob/edit_modal.php'; ?>
<?php include '../modals/maktob/delete_modal.php'; ?>



<style>
.maktob-info p {
    margin-bottom: 0.5rem;
}

.maktob-content {
    background-color: #f8f9fa;
    padding: 1.5rem;
    border-radius: 0.5rem;
    white-space: pre-wrap;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transition: background-color 0.2s ease;
}
</style>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/maktob/main.js"></script>

<script>

</script>

<?php include '../includes/admin_footer.php'; ?> 