<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to admin dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Include language system
require_once('../includes/language_helpers.php');
$lang = init_language();

// Get any flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear flash messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Handle maktob submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $maktob_number = mysqli_real_escape_string($conn, $_POST['maktob_number']);
    $maktob_date = mysqli_real_escape_string($conn, $_POST['maktob_date']);
    $language = mysqli_real_escape_string($conn, $_POST['language']);
    $sender_id = $_SESSION['user_id'];

    // Validate company name
    if (!empty($company_name)) {
        $query = "INSERT INTO maktobs (tenant_id, subject, content, company_name, maktob_number, maktob_date, sender_id, status, language) 
                  VALUES ('$tenant_id', '$subject', '$content', '$company_name', '$maktob_number', '$maktob_date', $sender_id, 'draft', '$language')";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success_message'] = __('letter_created');
        } else {
            $_SESSION['error_message'] = __('error_creating_letter') . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = __('please_enter_company');
    }

    // Redirect back to the same page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch recent maktobs
$recent_maktobs_query = "SELECT m.*, 
    u.name as sender_name,
    m.status,
    COALESCE(m.language, 'english') as language
    FROM maktobs m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.tenant_id = ?
    ORDER BY maktob_date DESC 
    LIMIT 10";
$stmt = $conn->prepare($recent_maktobs_query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$recent_maktobs_result = $stmt->get_result();

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
                                                <a href="download_maktob.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-sm btn-success" data-toggle="tooltip" title="<?= __('download_pdf') ?>" target="_blank">
                                                    <i class="feather icon-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Maktob Modal -->
<div class="modal fade" id="viewMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>
                    <span id="maktobSubject"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="maktob-info mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?= __('letter_number') ?>:</strong> <span id="maktobNumber"></span></p>
                            <p><strong><?= __('company_name') ?>:</strong> <span id="maktobCompany"></span></p>
                            <p><strong><?= __('language') ?>:</strong> <span id="maktobLanguage"></span></p>
                        </div>
                        <div class="col-md-6 text-right">
                            <p><strong><?= __('date') ?>:</strong> <span id="maktobDate"></span></p>
                            <p><strong><?= __('status') ?>:</strong> <span id="maktobStatus"></span></p>
                            <p id="fileLinks"></p>
                        </div>
                    </div>
                    <hr>
                </div>
                <div class="maktob-content">
                    <p id="maktobContent"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Maktob Modal -->
<div class="modal fade" id="editMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i>
                    <?= __('edit_letter') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMaktobForm" method="POST" action="update_maktob.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_maktob_id" name="maktob_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_maktob_number"><?= __('letter_number') ?></label>
                                <input type="text" class="form-control" id="edit_maktob_number" name="maktob_number" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_maktob_date"><?= __('letter_date') ?></label>
                                <input type="date" class="form-control" id="edit_maktob_date" name="maktob_date" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_company_name"><?= __('company_name') ?></label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_language"><?= __('language') ?></label>
                                <select class="form-control" id="edit_language" name="language" required>
                                    <option value="english"><?= __('english') ?></option>
                                    <option value="dari"><?= __('dari') ?></option>
                                    <option value="pashto"><?= __('pashto') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_subject"><?= __('subject') ?></label>
                                <input type="text" class="form-control" id="edit_subject" name="subject" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_content"><?= __('content') ?></label>
                                <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMaktobModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-alert-triangle text-danger mr-2"></i>
                    <?= __('confirm_delete') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('delete_confirmation') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <form id="deleteMaktobForm" method="POST" action="delete_maktob.php">
                    <input type="hidden" id="delete_maktob_id" name="maktob_id">
                    <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

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

<script>
// Initialize when document is ready
jQuery(document).ready(function($) {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // View maktob button click
    $('.view-maktob').on('click', function() {
        var button = $(this);
        var subject = button.data('subject');
        var content = button.data('content');
        var company = button.data('company');
        var number = button.data('number');
        var date = button.data('date');
        var status = button.data('status');
        var language = button.data('language');
        var filePath = button.data('file-path');
        var pdfPath = button.data('pdf-path');
        
        // Set modal content
        $('#maktobSubject').text(subject);
        $('#maktobNumber').text(number);
        $('#maktobCompany').text(company);
        $('#maktobDate').text(date);
        $('#maktobContent').text(content);
        
        // Get translated language name
        var translatedLang;
        switch(language) {
            case 'dari':
                translatedLang = '<?= __('dari') ?>';
                break;
            case 'pashto':
                translatedLang = '<?= __('pashto') ?>';
                break;
            default:
                translatedLang = '<?= __('english') ?>';
        }
        $('#maktobLanguage').text(translatedLang);
        
        // Set the status indicator with translation
        if (status === 'sent') {
            $('#maktobStatus').html('<span class="badge badge-success"><i class="feather icon-check mr-1"></i> <?= __('sent') ?></span>');
        } else {
            $('#maktobStatus').html('<span class="badge badge-warning"><i class="feather icon-clock mr-1"></i> <?= __('draft') ?></span>');
        }

        // Display file links if available
        var fileLinksHtml = '';
        if (filePath) {
            fileLinksHtml += '<p><strong><?= __('original_file') ?>:</strong> <a href="../' + filePath + '" target="_blank"><?= __('view_file') ?></a></p>';
        }
        if (pdfPath) {
            fileLinksHtml += '<p><strong><?= __('pdf_version') ?>:</strong> <a href="../' + pdfPath + '" target="_blank"><?= __('view_pdf') ?></a></p>';
        }
        $('#fileLinks').html(fileLinksHtml);
        
        // Show modal
        $('#viewMaktobModal').modal('show');
    });
    
    // Edit maktob button click
    $('.edit-maktob').on('click', function() {
        var button = $(this);
        var id = button.data('id');
        var subject = button.data('subject');
        var content = button.data('content');
        var company = button.data('company');
        var number = button.data('number');
        var date = button.data('date');
        var language = button.data('language');
        
        // Set form values
        $('#edit_maktob_id').val(id);
        $('#edit_subject').val(subject);
        $('#edit_content').val(content);
        $('#edit_company_name').val(company);
        $('#edit_maktob_number').val(number);
        $('#edit_maktob_date').val(date);
        $('#edit_language').val(language);
        
        // Show modal
        $('#editMaktobModal').modal('show');
    });
    
    // Delete maktob button click
    $('.delete-maktob').on('click', function() {
        var id = $(this).data('id');
        $('#delete_maktob_id').val(id);
        $('#deleteMaktobModal').modal('show');
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?> 