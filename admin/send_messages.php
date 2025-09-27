<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to admin dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

include 'handlers/sendMessages_handler.php';

// Include the header
include '../includes/header.php';
?>

<!-- JavaScript translations for DataTables -->
<script>
// Translation variables for the DataTable and other components
var searchText = "<?= __('search') ?>";
var showText = "<?= __('show') ?>";
var entriesText = "<?= __('entries') ?>";
var showingText = "<?= __('showing') ?>";
var toText = "<?= __('to') ?>";
var ofText = "<?= __('of') ?>";
var filteredFromText = "<?= __('filtered_from') ?>";
var totalEntriesText = "<?= __('total_entries') ?>";
var firstText = "<?= __('first') ?>";
var lastText = "<?= __('last') ?>";
var nextText = "<?= __('next') ?>";
var previousText = "<?= __('previous') ?>";
var allText = "<?= __('all') ?>";
var selectRecipientText = "<?= __('select_a_recipient') ?>";
var readText = "<?= __('read') ?>";
var unreadText = "<?= __('unread') ?>";

// Flash messages for toasts
var successMessage = <?= $success_message ? json_encode($success_message) : 'null' ?>;
var errorMessage = <?= $error_message ? json_encode($error_message) : 'null' ?>;
</script>
<link rel="stylesheet" href="css/send-messages.css">
<link rel="stylesheet" href="css/modal-styles.css">
<!-- Animation CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

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
                            <h5 class="m-b-10"><?= __("send_messages") ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php"><?= __("dashboard") ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __("send_messages") ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <!-- Compose Message Card -->
                <div class="card animate__animated animate__fadeIn">
                    <div class="card-header">
                        <h5>
                            <i class="feather icon-edit mr-2"></i>
                            <?= __("compose_message") ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="composeMessageForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subject"><?= __("subject") ?></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="feather icon-bookmark"></i></span>
                                            </div>
                                            <input type="text" class="form-control" id="subject" name="subject" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="recipient_type"><?= __("send_to") ?></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="feather icon-users"></i></span>
                                            </div>
                                            <select class="form-control" id="recipient_type" name="recipient_type" required onchange="toggleRecipientSelect()">
                                                <option value="clients"><?= __("all_clients") ?></option>
                                                <option value="individual"><?= __("individual_client") ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group" id="recipient_select_group" style="display: none;">
                                        <label for="recipient_id"><?= __("select_recipient") ?></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="feather icon-user"></i></span>
                                            </div>
                                            <select class="form-control select2" id="recipient_id" name="recipient_id">
                                                <?php if (!empty($clients)): ?>
                                                <optgroup label="Clients">
                                                    <?php foreach ($clients as $client): ?>
                                                        <option value="<?php echo $client['id']; ?>">
                                                            <?php echo htmlspecialchars($client['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="message"><?= __("message") ?></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="feather icon-message-circle"></i></span>
                                            </div>
                                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary float-right">
                                        <i class="feather icon-send mr-2"></i><?= __("send_message") ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Messages Card -->
                <div class="card animate__animated animate__fadeIn animate__delay-1s">
                    <div class="card-header">
                        <h5><i class="feather icon-clock mr-2"></i><?= __("recent_messages") ?></h5>
                        <div class="card-header-right">
                            <button class="btn btn-sm btn-light-primary" id="refreshMessages">
                                <i class="feather icon-refresh-cw mr-1"></i> <?= __("refresh") ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="messagesTable">
                                <thead>
                                    <tr>
                                        <th><?= __("date") ?></th>
                                        <th><?= __("subject") ?></th>
                                        <th><?= __("recipient") ?></th>
                                        <th><?= __("status") ?></th>
                                        <th><?= __("sender") ?></th>
                                        <th class="no-sort"><?= __("actions") ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recent_messages_result)): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                        <td>
                                            <?php if ($row['recipient_type'] === 'individual'): ?>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($row['recipient_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-primary">
                                                    <?php echo ucfirst($row['recipient_type']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($row['status']) && $row['status'] === 'read'): ?>
                                                <span class="badge badge-success">
                                                    <i class="feather icon-check mr-1"></i> <?= __("read") ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">
                                                    <i class="feather icon-clock mr-1"></i> <?= __("unread") ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['sender_name']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-icon btn-sm btn-primary view-message" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                    data-message="<?php echo htmlspecialchars($row['message']); ?>"
                                                    data-sender="<?php echo htmlspecialchars($row['sender_name']); ?>"
                                                    data-recipient="<?php echo htmlspecialchars($row['recipient_name']); ?>"
                                                    data-date="<?php echo date('F j, Y g:i A', strtotime($row['created_at'])); ?>"
                                                    data-read-status="<?php echo isset($row['status']) ? $row['status'] : 'unread'; ?>"
                                                    data-toggle="tooltip" title="<?= __("view") ?>">
                                                    <i class="feather icon-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-warning edit-message" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($row['subject']); ?>"
                                                    data-message="<?php echo htmlspecialchars($row['message']); ?>"
                                                    data-recipient-type="<?php echo $row['recipient_type']; ?>"
                                                    data-recipient-id="<?php echo $row['recipient_id'] ? $row['recipient_id'] : ''; ?>"
                                                    data-toggle="tooltip" title="<?= __("edit") ?>">
                                                    <i class="feather icon-edit-2"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-sm btn-danger delete-message" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-toggle="tooltip" title="<?= __("delete") ?>">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
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

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-message-circle mr-2"></i>
                    <span id="messageSubject"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="message-info mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="feather icon-user mr-1"></i> <?= __("from") ?>:</strong> <span id="messageSender"></span></p>
                            <p><strong><i class="feather icon-users mr-1"></i> <?= __("to") ?>:</strong> <span id="messageRecipient"></span></p>
                        </div>
                        <div class="col-md-6 text-right">
                            <p><strong><i class="feather icon-calendar mr-1"></i> <?= __("date") ?>:</strong> <span id="messageDate"></span></p>
                            <p><strong><i class="feather icon-flag mr-1"></i> <?= __("status") ?>:</strong> <span id="messageStatus"></span></p>
                        </div>
                    </div>
                    <hr>
                </div>
                <div class="message-content">
                    <p id="messageBody"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("close") ?></button>
                <button type="button" class="btn btn-primary reply-message">
                    <i class="feather icon-corner-up-left mr-1"></i> <?= __("reply") ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Message Modal -->
<div class="modal fade" id="editMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i>
                    <?= __("edit_message") ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMessageForm" method="POST" action="update_message.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_message_id" name="message_id">
                    <div class="form-group">
                        <label for="edit_subject"><?= __("subject") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-bookmark"></i></span>
                            </div>
                            <input type="text" class="form-control" id="edit_subject" name="subject" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_message"><?= __("message") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-message-circle"></i></span>
                            </div>
                            <textarea class="form-control" id="edit_message" name="message" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_recipient_type"><?= __("send_to") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-users"></i></span>
                            </div>
                            <select class="form-control" id="edit_recipient_type" name="recipient_type" required onchange="toggleEditRecipientSelect()">
                                <option value="clients"><?= __("all_clients") ?></option>
                                <option value="individual"><?= __("individual_client") ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="edit_recipient_select_group" style="display: none;">
                        <label for="edit_recipient_id"><?= __("select_recipient") ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="feather icon-user"></i></span>
                            </div>
                            <select class="form-control select2" id="edit_recipient_id" name="recipient_id">
                                <?php if (!empty($clients)): ?>
                                <optgroup label="Clients">
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-1"></i> <?= __("save_changes") ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMessageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="feather icon-alert-triangle mr-2"></i>
                    <?= __("confirm_delete") ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="feather icon-trash-2 text-danger" style="font-size: 4rem;"></i>
                </div>
                <p class="text-center lead"><?= __("are_you_sure_you_want_to_delete_this_message") ?></p>
                <p class="text-center text-muted"><?= __("this_action_cannot_be_undone") ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i> <?= __("cancel") ?>
                </button>
                <form id="deleteMessageForm" method="POST" action="delete_message.php">
                    <input type="hidden" id="delete_message_id" name="message_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="feather icon-trash-2 mr-1"></i> <?= __("delete") ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container"></div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

<!-- Toast Notification JS - Make sure this is loaded before message-management.js -->
<script src="js/toast-notifications.js"></script>

<!-- Custom Message Management JS -->
<script src="js/message-management.js"></script>

<!-- Prevent duplicate toast display -->
<script>
// Set a flag to prevent multiple toast displays
window.toastsDisplayed = false;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize refresh button
    document.getElementById('refreshMessages').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="feather icon-refresh-cw mr-1 fa-spin"></i> <?= __("loading") ?>';
        window.location.reload();
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>
