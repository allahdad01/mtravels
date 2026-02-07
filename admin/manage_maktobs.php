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

// Include database connection
require_once('../includes/db.php');
require_once('../includes/SecureFileUpload.php');

// Handle maktob submission directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== MAKTOB CREATE REQUEST FROM ADMIN ===");
    error_log("Raw POST data: " . json_encode($_POST));
    
    // Get input data
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $maktob_number = $_POST['maktob_number'] ?? '';
    $maktob_date = $_POST['maktob_date'] ?? '';
    $language = $_POST['language'] ?? 'english';
    $sender_id = $_SESSION['user_id'];

    // Handle file uploads
    $file_path = null;
    $pdf_path = null;

    // Handle PDF file upload - SECURE VERSION
     if (isset($_FILES['pdf_file'])) {
         $uploader = new SecureFileUpload(
             10 * 1024 * 1024, // 10MB max size
             'uploads/'
         );
         
         $result = $uploader->upload('pdf_file', 'maktobs');
         
         if ($result['success']) {
             $pdf_path = 'uploads/maktobs/' . $result['data']['filename'];
             error_log("PDF file uploaded securely: {$result['data']['filename']}");
         } else {
             $_SESSION['error_message'] = 'Failed to upload PDF file: ' . $result['error'];
             header('Location: ' . $_SERVER['PHP_SELF']);
             exit();
         }
     }

    // Handle attachment upload - SECURE VERSION
     if (isset($_FILES['attachment'])) {
         $uploader = new SecureFileUpload(
             10 * 1024 * 1024, // 10MB max size
             'uploads/'
         );
         
         $result = $uploader->upload('attachment', 'maktobs');
         
         if ($result['success']) {
             $file_path = 'uploads/maktobs/' . $result['data']['filename'];
             error_log("Attachment file uploaded securely: {$result['data']['filename']}");
         } else {
             $_SESSION['error_message'] = 'Failed to upload attachment: ' . $result['error'];
             header('Location: ' . $_SERVER['PHP_SELF']);
             exit();
         }
     }

    error_log("Session info - tenant_id: $tenant_id, branch_id: $branch_id, user_id: $sender_id");

    // Validate required fields
    if (empty($company_name)) {
        error_log("VALIDATION FAILED: company_name is empty");
        $_SESSION['error_message'] = __('please_enter_company');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if (empty($subject)) {
        error_log("VALIDATION FAILED: subject is empty");
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if (empty($content)) {
        error_log("VALIDATION FAILED: content is empty");
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Auto-generate maktob_number if empty
    if (empty($maktob_number) && !empty($maktob_date)) {
        try {
            $formattedDate = str_replace('-', '', $maktob_date);
            $baseNumber = $tenant_id . '-' . $branch_id . '-' . $formattedDate . '-';

            // Get next sequence number
            $stmt = $pdo->prepare("SELECT maktob_number FROM maktobs
                                  WHERE tenant_id = ? AND branch_id = ? AND maktob_number LIKE ?
                                  ORDER BY CAST(SUBSTRING_INDEX(maktob_number, '-', -1) AS UNSIGNED) DESC
                                  LIMIT 1");
            $stmt->execute([$tenant_id, $branch_id, $baseNumber . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $next_sequence = 1;
            if ($result) {
                $existing_number = $result['maktob_number'];
                $parts = explode('-', $existing_number);
                if (count($parts) >= 4) {
                    $last_part = end($parts);
                    if (is_numeric($last_part)) {
                        $next_sequence = intval($last_part) + 1;
                    }
                }
            }

            $formatted_sequence = str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
            $maktob_number = $baseNumber . $formatted_sequence;
        } catch (Exception $e) {
            error_log("Error auto-generating maktob number: " . $e->getMessage());
            $_SESSION['error_message'] = 'Error generating maktob number';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    if (empty($maktob_number)) {
        error_log("VALIDATION FAILED: maktob_number is empty after auto-generation");
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if (empty($maktob_date)) {
        error_log("VALIDATION FAILED: maktob_date is empty");
        $_SESSION['error_message'] = __('all_fields_required');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    if (!$sender_id) {
        error_log("VALIDATION FAILED: sender_id not found in session");
        $_SESSION['error_message'] = 'User session not found';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Validate maktob_date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maktob_date)) {
        error_log("VALIDATION FAILED: Invalid maktob_date format: $maktob_date");
        $_SESSION['error_message'] = 'Invalid date format. Use YYYY-MM-DD';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    error_log("All validations passed. Proceeding with INSERT");

    // Insert new maktob directly
    try {
        $query = "INSERT INTO maktobs (tenant_id, branch_id, subject, content, company_name, maktob_number, maktob_date, sender_id, status, language, file_path, pdf_path)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)";

        error_log("Preparing statement: $query");
        $stmt = $pdo->prepare($query);

        if (!$stmt) {
            error_log("PREPARE ERROR: " . json_encode($pdo->errorInfo()));
            $_SESSION['error_message'] = 'Database prepare error: ' . $pdo->errorInfo()[2];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        error_log("Statement prepared. Binding parameters...");
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $subject, PDO::PARAM_STR);
        $stmt->bindParam(4, $content, PDO::PARAM_STR);
        $stmt->bindParam(5, $company_name, PDO::PARAM_STR);
        $stmt->bindParam(6, $maktob_number, PDO::PARAM_STR);
        $stmt->bindParam(7, $maktob_date, PDO::PARAM_STR);
        $stmt->bindParam(8, $sender_id, PDO::PARAM_INT);
        $stmt->bindParam(9, $language, PDO::PARAM_STR);
        $stmt->bindParam(10, $file_path, PDO::PARAM_STR);
        $stmt->bindParam(11, $pdf_path, PDO::PARAM_STR);

        error_log("Parameters bound. Execution details:");
        error_log("  tenant_id: $tenant_id (int)");
        error_log("  branch_id: $branch_id (int)");
        error_log("  subject: $subject");
        error_log("  company_name: $company_name");
        error_log("  maktob_number: $maktob_number");
        error_log("  maktob_date: $maktob_date");
        error_log("  sender_id: $sender_id (int)");
        error_log("  language: $language");

        error_log("Executing INSERT statement...");
        if ($stmt->execute()) {
            $insert_id = $pdo->lastInsertId();
            error_log("=== MAKTOB CREATE SUCCESS: ID=$insert_id ===");

            // Log the create action
            try {
                $log_query = "INSERT INTO maktob_logs (tenant_id, maktob_id, user_id, action, new_values, ip_address, branch_id)
                             VALUES (?, ?, ?, 'create', ?, ?, ?)";
                $log_stmt = $pdo->prepare($log_query);
                $new_values = json_encode([
                    'subject' => $subject,
                    'content' => $content,
                    'company_name' => $company_name,
                    'maktob_number' => $maktob_number,
                    'maktob_date' => $maktob_date,
                    'language' => $language,
                    'file_path' => $file_path,
                    'pdf_path' => $pdf_path
                ]);
                $log_stmt->execute([$tenant_id, $insert_id, $sender_id, $new_values, $_SERVER['REMOTE_ADDR'], $branch_id]);
            } catch (Exception $e) {
                error_log("Failed to log maktob creation: " . $e->getMessage());
            }

            $_SESSION['success_message'] = __('letter_created');
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("=== MAKTOB EXECUTION FAILED ===");
            error_log("SQLSTATE: " . $errorInfo[0]);
            error_log("Driver Error Code: " . $errorInfo[1]);
            error_log("Driver Error Message: " . $errorInfo[2]);
            
            $_SESSION['error_message'] = __('error_creating_letter') . ": " . $errorInfo[2];
        }
    } catch (PDOException $e) {
        error_log("=== PDO EXCEPTION DURING INSERT ===");
        error_log("Exception Code: " . $e->getCode());
        error_log("Exception Message: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        
        $_SESSION['error_message'] = __('error_creating_letter') . ": " . $e->getMessage();
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$language_filter = isset($_GET['language']) ? $_GET['language'] : '';
$sender_filter = isset($_GET['sender']) ? $_GET['sender'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$search_condition = '';
$search_params = [];

if (!empty($search_query)) {
    $search_condition .= " AND (m.maktob_number LIKE ? OR m.subject LIKE ? OR m.company_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $search_params = array_merge($search_params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $search_condition .= " AND m.status = ?";
    $search_params[] = $status_filter;
}

if (!empty($language_filter)) {
    $search_condition .= " AND m.language = ?";
    $search_params[] = $language_filter;
}

if (!empty($sender_filter)) {
    $search_condition .= " AND m.sender_id = ?";
    $search_params[] = $sender_filter;
}

if (!empty($date_from)) {
    $search_condition .= " AND m.maktob_date >= ?";
    $search_params[] = $date_from;
}

if (!empty($date_to)) {
    $search_condition .= " AND m.maktob_date <= ?";
    $search_params[] = $date_to;
}

// Fetch maktobs directly from database
$recent_maktobs_result = null;
$total_records = 0;
$total_pages = 1;

try {
    error_log("=== MAKTOB FETCH REQUEST ===");
    error_log("Search query: " . ($search_query ?: 'none'));
    error_log("Pagination - Page: $current_page, Items per page: $items_per_page, Offset: $offset");
    
    // Build query
    $query = "SELECT m.*,
        u.name as sender_name
        FROM maktobs m
        JOIN users u ON m.sender_id = u.id
        WHERE m.tenant_id = ? AND m.branch_id = ?";

    $params = [$tenant_id, $branch_id];

    // Apply search filters
    if (!empty($search_condition)) {
        $query .= $search_condition;
        $params = array_merge($params, $search_params);
    }
    
    $query .= " ORDER BY m.maktob_date DESC";
    
    error_log("Executing query: $query");
    error_log("Params: " . json_encode($params));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allMaktobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Records found: " . count($allMaktobs));
    
    // Calculate pagination
    $total_records = count($allMaktobs);
    $total_pages = ceil($total_records / $items_per_page);
    
    error_log("Total records: $total_records, Total pages: $total_pages");
    
    // Ensure current page is valid
    if ($current_page > $total_pages && $total_pages > 0) {
        error_log("Current page $current_page exceeds total pages $total_pages. Adjusting to last page.");
        $current_page = $total_pages;
    }
    
    // Get page data
    $paged_maktobs = array_slice($allMaktobs, $offset, $items_per_page);
    error_log("Displaying " . count($paged_maktobs) . " records on page $current_page");

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
    error_log("=== MAKTOB FETCH SUCCESS ===\n");
} catch (Exception $e) {
    error_log("=== EXCEPTION DURING MAKTOB FETCH ===");
    error_log("Exception Code: " . $e->getCode());
    error_log("Exception Message: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    // Fallback to empty result
    $recent_maktobs_result = null;
}

// Include the header
include '../includes/header.php';

?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i><?php echo __('manage_letters'); ?></h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_and_view_all_letters'); ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <?php if (isset($error_message)): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($success_message)): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <strong>Success:</strong> <?php echo nl2br(htmlspecialchars($success_message)); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Dashboard Widget -->
                                    <?php
                                    // Get maktob statistics
                                    try {
                                        $stats_query = "SELECT
                                            COUNT(*) as total,
                                            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
                                            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                                            SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
                                            FROM maktobs WHERE tenant_id = ? AND branch_id = ?";
                                        $stats_stmt = $pdo->prepare($stats_query);
                                        $stats_stmt->execute([$tenant_id, $branch_id]);
                                        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

                                        // Get recent 5 maktobs
                                        $recent_query = "SELECT m.*, u.name as sender_name
                                                        FROM maktobs m
                                                        JOIN users u ON m.sender_id = u.id
                                                        WHERE m.tenant_id = ? AND m.branch_id = ?
                                                        ORDER BY m.created_at DESC LIMIT 5";
                                        $recent_stmt = $pdo->prepare($recent_query);
                                        $recent_stmt->execute([$tenant_id, $branch_id]);
                                        $recent_maktobs = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {
                                        $stats = ['total' => 0, 'drafts' => 0, 'sent' => 0, 'archived' => 0];
                                        $recent_maktobs = [];
                                    }
                                    ?>
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><i class="feather icon-bar-chart-2 mr-2"></i>Maktob Statistics</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h3 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h3>
                                                                <p class="mb-0">Total Maktobs</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h3 class="text-warning"><?php echo $stats['drafts'] ?? 0; ?></h3>
                                                                <p class="mb-0">Drafts</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h3 class="text-success"><?php echo $stats['sent'] ?? 0; ?></h3>
                                                                <p class="mb-0">Sent</p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h3 class="text-secondary"><?php echo $stats['archived'] ?? 0; ?></h3>
                                                                <p class="mb-0">Archived</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($recent_maktobs)): ?>
                                                    <hr>
                                                    <h6>Recent Maktobs</h6>
                                                    <div class="list-group list-group-flush">
                                                        <?php foreach ($recent_maktobs as $maktob): ?>
                                                        <div class="list-group-item px-0">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($maktob['maktob_number']); ?></strong>
                                                                    <br><small class="text-muted"><?php echo htmlspecialchars($maktob['subject']); ?></small>
                                                                </div>
                                                                <div class="text-right">
                                                                    <span class="badge-<?php
                                                                        echo $maktob['status'] === 'sent' ? 'success' :
                                                                             ($maktob['status'] === 'draft' ? 'warning' : 'secondary');
                                                                    ?>"><?php echo ucfirst($maktob['status']); ?></span>
                                                                    <br><small class="text-muted"><?php echo date('M d', strtotime($maktob['created_at'])); ?></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Create Maktob Card -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>
                                                <i class="feather icon-file-text mr-2"></i>
                                                <?= __('create_new_letter') ?>
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="maktob_number"><?= __('letter_number') ?></label>
                                                            <input type="text" class="form-control" id="maktob_number" name="maktob_number" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="maktob_date"><?= __('letter_date') ?></label>
                                                            <input type="date" class="form-control" id="maktob_date" name="maktob_date" value="<?php echo date('Y-m-d'); ?>" required>
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
                                                        <div class="form-group">
                                                            <label for="attachment">Supporting Documents (PDF)</label>
                                                            <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf">
                                                            <small class="form-text text-muted">Upload additional PDF attachments/supporting documents (optional)</small>
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
                                            <form method="GET" class="mb-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <input
                                                                type="text"
                                                                name="search"
                                                                class="form-control"
                                                                placeholder="Search by letter number, subject, company..."
                                                                value="<?= htmlspecialchars($search_query) ?>"
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="row">
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <select name="status" class="form-control">
                                                                        <option value="">All Status</option>
                                                                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                                        <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                                                                        <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <select name="language" class="form-control">
                                                                        <option value="">All Languages</option>
                                                                        <option value="english" <?= $language_filter === 'english' ? 'selected' : '' ?>>English</option>
                                                                        <option value="dari" <?= $language_filter === 'dari' ? 'selected' : '' ?>>Dari</option>
                                                                        <option value="pashto" <?= $language_filter === 'pashto' ? 'selected' : '' ?>>Pashto</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <select name="sender" class="form-control">
                                                                        <option value="">All Senders</option>
                                                                        <?php
                                                                        try {
                                                                            $sender_query = "SELECT DISTINCT u.id, u.name FROM users u
                                                                                            JOIN maktobs m ON u.id = m.sender_id
                                                                                            WHERE m.tenant_id = ? AND m.branch_id = ?
                                                                                            ORDER BY u.name";
                                                                            $sender_stmt = $pdo->prepare($sender_query);
                                                                            $sender_stmt->execute([$tenant_id, $branch_id]);
                                                                            while ($sender = $sender_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                                                $selected = $sender_filter == $sender['id'] ? 'selected' : '';
                                                                                echo "<option value='{$sender['id']}' {$selected}>{$sender['name']}</option>";
                                                                            }
                                                                        } catch (Exception $e) {
                                                                            // Ignore errors
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" placeholder="From Date">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <div class="form-group">
                                                                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" placeholder="To Date">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <button type="submit" class="btn btn-info">
                                                            <i class="feather icon-search"></i> <?= __('search') ?>
                                                        </button>
                                                        <?php if (!empty($search_query) || !empty($status_filter) || !empty($language_filter) || !empty($sender_filter) || !empty($date_from) || !empty($date_to)): ?>
                                                            <a href="manage_maktobs.php" class="btn btn-secondary ml-2">
                                                                <i class="feather icon-x"></i> Clear Filters
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
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
                                                                    <span class="badge-success">
                                                                        <i class="feather icon-check mr-1"></i> <?= __('sent') ?>
                                                                    </span>
                                                                <?php elseif ($row['status'] === 'archived'): ?>
                                                                    <span class="badge-secondary">
                                                                        <i class="feather icon-archive mr-1"></i> Archived
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge-warning">
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
                                                                <span class="badge-<?php echo $langBadgeClass; ?>">
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
                                                                        <?php if ($row['status'] === 'draft'): ?>
                                                                        <a class="dropdown-item send-maktob" href="#"
                                                                            data-id="<?php echo $row['id']; ?>">
                                                                            <i class="feather icon-send mr-2"></i>Send to Branch
                                                                        </a>
                                                                        <?php endif; ?>
                                                                        <?php if ($row['status'] !== 'archived'): ?>
                                                                        <a class="dropdown-item archive-maktob" href="#"
                                                                            data-id="<?php echo $row['id']; ?>">
                                                                            <i class="feather icon-archive mr-2"></i>Archive
                                                                        </a>
                                                                        <?php endif; ?>
                                                                        <a class="dropdown-item" href="../api/maktob/download_maktob.php?id=<?php echo $row['id']; ?>" target="_blank">
                                                                            <i class="feather icon-eye mr-2"></i>View Letter
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
                                                        <?php
                                                        // Build query string for pagination
                                                        $query_params = [];
                                                        if (!empty($search_query)) $query_params[] = 'search=' . urlencode($search_query);
                                                        if (!empty($status_filter)) $query_params[] = 'status=' . urlencode($status_filter);
                                                        if (!empty($language_filter)) $query_params[] = 'language=' . urlencode($language_filter);
                                                        if (!empty($sender_filter)) $query_params[] = 'sender=' . urlencode($sender_filter);
                                                        if (!empty($date_from)) $query_params[] = 'date_from=' . urlencode($date_from);
                                                        if (!empty($date_to)) $query_params[] = 'date_to=' . urlencode($date_to);
                                                        $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                                                        ?>

                                                        <?php if ($current_page > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="manage_maktobs.php?page=1<?= $query_string ?>">
                                                                    <i class="feather icon-chevrons-left"></i> <?= __('first') ?>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="manage_maktobs.php?page=<?= $current_page - 1 ?><?= $query_string ?>">
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
                                                                <a class="page-link" href="manage_maktobs.php?page=<?= $i ?><?= $query_string ?>">
                                                                    <?= $i ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($current_page < $total_pages): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="manage_maktobs.php?page=<?= $current_page + 1 ?><?= $query_string ?>">
                                                                    <?= __('next') ?> <i class="feather icon-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="manage_maktobs.php?page=<?= $total_pages ?><?= $query_string ?>">
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
                                </div>
                            </div>
                        </div>

<style>
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    .maktob-info p {
        margin-bottom: 0.5rem;
    }

    .maktob-content {
        background-color: #f8f9fa;
        padding: 1.5rem;
        border-radius: 0.5rem;
        white-space: pre-wrap;
    }

    /* Mobile responsive styles */
    @media (max-width: 768px) {
        .page-header.card .row {
            flex-direction: column;
            text-align: center;
        }

        .page-header.card .text-end {
            text-align: center;
            margin-top: 10px;
        }

        .card-body .row .col-md-3 {
            margin-bottom: 1rem;
        }

        .search-filters .row .col-md-3 {
            margin-bottom: 0.5rem;
        }

        .table-responsive {
            font-size: 0.875rem;
        }

        .table thead th,
        .table tbody td {
            padding: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .form-control {
            font-size: 0.875rem;
            padding: 0.5rem;
        }

        .card-header h5 {
            font-size: 1rem;
        }

        .dropdown-menu {
            min-width: 150px;
        }

        .pagination {
            flex-wrap: wrap;
        }

        .pagination .page-link {
            padding: 0.375rem 0.5rem;
        }
    }

    @media (max-width: 576px) {
        .container-fluid {
            padding-left: 10px;
            padding-right: 10px;
        }

        .card-body {
            padding: 1rem 0.5rem;
        }

        .search-filters .row {
            flex-direction: column;
        }

        .search-filters .row .col-md-6 {
            margin-bottom: 1rem;
        }

        .table-responsive {
            border: none;
        }

        .table {
            font-size: 0.75rem;
        }

        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
    }
</style>

    <?php include '../modals/maktob/view_modal.php'; ?>
    <?php include '../modals/maktob/edit_modal.php'; ?>
    <?php include '../modals/maktob/delete_modal.php'; ?>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/maktob/main.js"></script>

<script>
// Handle send maktob action
$(document).on('click', '.send-maktob', function(e) {
    e.preventDefault();
    var maktobId = $(this).data('id');

    if (confirm('Are you sure you want to send this maktob to branch?')) {
        $.ajax({
            url: '../api/maktob/update_status.php',
            method: 'POST',
            data: {
                id: maktobId,
                action: 'send'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating maktob status');
            }
        });
    }
});

// Handle archive maktob action
$(document).on('click', '.archive-maktob', function(e) {
    e.preventDefault();
    var maktobId = $(this).data('id');

    if (confirm('Are you sure you want to archive this maktob?')) {
        $.ajax({
            url: '../api/maktob/update_status.php',
            method: 'POST',
            data: {
                id: maktobId,
                action: 'archive'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating maktob status');
            }
        });
    }
});

// Auto-generate maktob number
$(document).ready(function() {
    function generateMaktobNumber() {
        var date = $('#maktob_date').val();
        if (date && $('#maktob_number').val() === '') {
            // Generate number in format: TENANT-BRANCH-YYYYMMDD-001
            var tenantId = <?php echo $tenant_id; ?>;
            var branchId = <?php echo $branch_id; ?>;
            var formattedDate = date.replace(/-/g, '');
            var baseNumber = tenantId + '-' + branchId + '-' + formattedDate + '-';

            // Get next sequence number
            $.ajax({
                url: '../api/maktob/get_next_number.php',
                method: 'POST',
                data: { base_number: baseNumber },
                success: function(response) {
                    if (response.number) {
                        $('#maktob_number').val(response.number);
                    }
                }
            });
        }
    }

    // Generate on date change
    $('#maktob_date').on('change', generateMaktobNumber);

    // Generate on date input/blur if number is empty
    $('#maktob_date').on('blur input', function() {
        setTimeout(generateMaktobNumber, 500); // Small delay to avoid too many requests
    });

    // Generate on page load if date is set and number is empty
    if ($('#maktob_date').val() && $('#maktob_number').val() === '') {
        generateMaktobNumber();
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>