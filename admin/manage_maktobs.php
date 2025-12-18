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

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search_query)) {
    $search_condition = "AND (maktob_number LIKE ? OR subject LIKE ? OR company_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $search_params = array_fill(0, 3, $search_param);
}

// Fetch maktobs with pagination and search via API
$recent_maktobs_result = null;
$total_records = 0;
$total_pages = 1;

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
            $allMaktobs = $responseData['data'];
            
            // Apply search filter if needed
            if (!empty($search_query)) {
                $search_lower = strtolower($search_query);
                $allMaktobs = array_filter($allMaktobs, function($maktob) use ($search_lower) {
                    return (
                        stripos($maktob['maktob_number'] ?? '', $search_query) !== false ||
                        stripos($maktob['subject'] ?? '', $search_query) !== false ||
                        stripos($maktob['company_name'] ?? '', $search_query) !== false
                    );
                });
                $allMaktobs = array_values($allMaktobs); // Re-index array
            }
            
            // Calculate pagination
            $total_records = count($allMaktobs);
            $total_pages = ceil($total_records / $items_per_page);
            
            // Ensure current page is valid
            if ($current_page > $total_pages && $total_pages > 0) {
                $current_page = $total_pages;
            }
            
            // Get page data
            $paged_maktobs = array_slice($allMaktobs, $offset, $items_per_page);

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

            $recent_maktobs_result = new MockMysqliResult($paged_maktobs);
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
    <link rel="stylesheet" href="../css/general/modal-styles.css">
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><i class="feather icon-clock mr-2"></i><?= __('recent_letters') ?></h5>
                                <small class="text-muted">Manage and view all your letters</small>
                            </div>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="card-body border-bottom pb-3">
                        <form method="GET" class="form-inline">
                            <div class="form-group mb-0 flex-grow-1">
                                <input 
                                    type="text" 
                                    name="search" 
                                    class="form-control w-100" 
                                    placeholder="Search by letter number, subject, company..." 
                                    value="<?= htmlspecialchars($search_query) ?>"
                                >
                            </div>
                            <button type="submit" class="btn btn-info ml-2">
                                <i class="feather icon-search"></i> <?= __('search') ?>
                            </button>
                            <?php if (!empty($search_query)): ?>
                                <a href="manage_maktobs.php" class="btn btn-secondary ml-2">
                                    <i class="feather icon-x"></i> <?= __('clear') ?>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <!-- Pagination Info -->
                        <div class="row mb-3 p-3">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <?php if ($total_records > 0): ?>
                                        Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                    <?php else: ?>
                                        <?= __('no_letters_found') ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

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
                                    <?php if ($recent_maktobs_result !== null && $total_records > 0): ?>
                                        <?php while ($row = $recent_maktobs_result->fetch_assoc()): ?>
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
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="dropdownMaktob<?php echo $row['id']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="feather icon-more-vertical"></i> Actions
                                                </button>
                                                <div class="dropdown-menu" aria-labelledby="dropdownMaktob<?php echo $row['id']; ?>">
                                                    <a class="dropdown-item view-maktob" href="#"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                        data-content="<?php echo htmlspecialchars($row['content']); ?>"
                                                        data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                                        data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                                                        data-date="<?php echo date('F j, Y', strtotime($row['maktob_date'])); ?>"
                                                        data-status="<?php echo $row['status']; ?>"
                                                        data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>"
                                                        data-file-path="<?php echo htmlspecialchars($row['file_path'] ?? ''); ?>"
                                                        data-pdf-path="<?php echo htmlspecialchars($row['pdf_path'] ?? ''); ?>">
                                                        <i class="feather icon-eye mr-2"></i><?= __('view') ?>
                                                    </a>
                                                    <a class="dropdown-item edit-maktob" href="#"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                        data-content="<?php echo htmlspecialchars($row['content']); ?>"
                                                        data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                                        data-number="<?php echo htmlspecialchars($row['maktob_number']); ?>"
                                                        data-date="<?php echo $row['maktob_date']; ?>"
                                                        data-language="<?php echo htmlspecialchars($row['language'] ?? 'english'); ?>">
                                                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="../api/maktob/download_maktob.php?id=<?php echo $row['id']; ?>" target="_blank">
                                                        <i class="feather icon-download mr-2"></i><?= __('download_pdf') ?>
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item delete-maktob" href="#"
                                                        data-id="<?php echo $row['id']; ?>">
                                                        <i class="feather icon-trash-2 mr-2"></i><?= __('delete') ?>
                                                    </a>
                                                </div>
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

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Page <?= $current_page ?> of <?= $total_pages ?></small>
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($current_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="manage_maktobs.php?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <i class="feather icon-chevrons-left"></i> <?= __('first') ?>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="manage_maktobs.php?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <i class="feather icon-chevron-left"></i> <?= __('previous') ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php 
                                    $start = max(1, $current_page - 2);
                                    $end = min($total_pages, $current_page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++): 
                                    ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="manage_maktobs.php?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="manage_maktobs.php?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <?= __('next') ?> <i class="feather icon-chevron-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="manage_maktobs.php?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">
                                                <?= __('last') ?> <i class="feather icon-chevrons-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
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