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
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
     header('Location: ../login.php');
    exit();
}

include '../api/send_message/sendMessages_handler.php';
include '../includes/header.php';
?>

<!-- Translation variables -->
<script>
var selectRecipientText = "<?= __('select_a_recipient') ?>";
var readText = "<?= __('read') ?>";
var unreadText = "<?= __('unread') ?>";
var successMessage = <?= $success_message ? json_encode($success_message) : 'null' ?>;
var errorMessage = <?= $error_message ? json_encode($error_message) : 'null' ?>;
</script>

<link rel="stylesheet" href="../css/send_message/send-messages.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
/* ============================================
   GENERAL PAGE STYLES
   ============================================ */
:root {
    --primary: #4099ff;
    --primary-light: #e8f2ff;
    --primary-dark: #2a7de1;
    --success: #2ed8b6;
    --success-light: #e8f5e9;
    --success-dark: #2e7d32;
    --warning: #FFB64D;
    --warning-light: #fff3e0;
    --warning-dark: #e65100;
    --danger: #FF5370;
    --danger-light: #fce4ec;
    --danger-dark: #c62828;
    --info: #00bcd4;
    --info-light: #e0f7fa;
    --info-dark: #00838f;
    --gray-50: #fafbfc;
    --gray-100: #f4f5f7;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    --border-radius: 12px;
    --border-radius-sm: 8px;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
    --shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 4px 16px rgba(0, 0, 0, 0.1);
    --transition: all 0.25s ease;
}

/* ============================================
   CARD STYLES
   ============================================ */
.msg-card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
    opacity: 0;
    transform: translateY(15px);
    animation: cardFadeIn 0.5s ease forwards;
    background: #fff;
}

.msg-card:nth-child(2) {
    animation-delay: 0.15s;
}

@keyframes cardFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.msg-card .card-header {
    background: #ffffff !important;
    color: var(--gray-800) !important;
    border-bottom: 1px solid var(--gray-200) !important;
    border-left: 4px solid var(--primary) !important;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.msg-card .card-header h5 {
    color: var(--gray-800) !important;
    margin-bottom: 0 !important;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.msg-card .card-header h5 i {
    color: var(--primary);
    font-size: 1.1rem;
}

.msg-card .card-body {
    padding: 24px;
}

/* ============================================
   STATS ROW
   ============================================ */
.stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.stat-card {
    flex: 1;
    min-width: 140px;
    background: #fff;
    border-radius: var(--border-radius);
    padding: 20px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--transition);
    opacity: 0;
    transform: translateY(10px);
    animation: cardFadeIn 0.4s ease forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

.stat-card:hover {
    box-shadow: var(--shadow-hover);
    transform: translateY(-2px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.stat-icon.total {
    background: var(--primary-light);
    color: var(--primary);
}

.stat-icon.read {
    background: var(--success-light);
    color: var(--success-dark);
}

.stat-icon.unread {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.stat-icon.clients {
    background: var(--info-light);
    color: var(--info-dark);
}

.stat-info h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1;
}

.stat-info p {
    margin: 4px 0 0 0;
    font-size: 0.8rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* ============================================
   FORM STYLES
   ============================================ */
.msg-form .form-group {
    margin-bottom: 18px;
}

.msg-form label {
    font-weight: 500;
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 6px;
    display: block;
}

.msg-form .form-control {
    border-radius: var(--border-radius-sm);
    border: 1.5px solid var(--gray-300);
    padding: 10px 14px;
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--gray-50);
}

.msg-form .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.12);
    background: #fff;
}

.msg-form textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.msg-form .form-control::placeholder {
    color: var(--gray-400);
    font-style: italic;
}

/* Select2 custom styling */
.select2-container--default .select2-selection--single {
    border-radius: var(--border-radius-sm) !important;
    border: 1.5px solid var(--gray-300) !important;
    height: 42px !important;
    padding: 6px 14px !important;
    background: var(--gray-50) !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
    color: var(--gray-700) !important;
    padding-left: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.12) !important;
}

.select2-dropdown {
    border-radius: var(--border-radius-sm) !important;
    border: 1.5px solid var(--gray-300) !important;
    box-shadow: var(--shadow-lg) !important;
    margin-top: 4px !important;
}

.select2-results__option--highlighted {
    background-color: var(--primary-light) !important;
    color: var(--primary-dark) !important;
}

/* ============================================
   BUTTON STYLES
   ============================================ */
.btn-send {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border: none;
    color: #fff;
    padding: 10px 28px;
    border-radius: var(--border-radius-sm);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-send:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.35);
    color: #fff;
}

.btn-send:active {
    transform: translateY(0);
}

.btn-clear {
    background: transparent;
    border: 1.5px solid var(--gray-300);
    color: var(--gray-600);
    padding: 10px 24px;
    border-radius: var(--border-radius-sm);
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-clear:hover {
    border-color: var(--gray-400);
    background: var(--gray-100);
    color: var(--gray-700);
}

.btn-refresh {
    background: transparent;
    border: 1.5px solid var(--primary);
    color: var(--primary);
    padding: 6px 16px;
    border-radius: var(--border-radius-sm);
    font-weight: 500;
    font-size: 0.82rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-refresh:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* ============================================
   TABLE STYLES
   ============================================ */
.msg-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.msg-table thead th {
    background: var(--gray-50);
    border-bottom: 2px solid var(--gray-200);
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: var(--gray-500);
    padding: 14px 16px;
    white-space: nowrap;
}

.msg-table tbody tr {
    transition: var(--transition);
}

.msg-table tbody tr:hover {
    background-color: var(--primary-light);
}

.msg-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.88rem;
    color: var(--gray-700);
}

.msg-table tbody tr:last-child td {
    border-bottom: none;
}

/* ============================================
   BADGE STYLES
   ============================================ */
.msg-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.msg-badge-info {
    background: var(--info-light);
    color: var(--info-dark);
}

.msg-badge-primary {
    background: var(--primary-light);
    color: var(--primary-dark);
}

.msg-badge-success {
    background: var(--success-light);
    color: var(--success-dark);
}

.msg-badge-warning {
    background: var(--warning-light);
    color: var(--warning-dark);
}

/* ============================================
   ACTION BUTTONS
   ============================================ */
.action-btn-group {
    display: flex;
    gap: 4px;
}

.action-btn {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-sm);
    border: none;
    font-size: 0.85rem;
    transition: var(--transition);
    cursor: pointer;
}

.action-btn-view {
    background: var(--primary-light);
    color: var(--primary);
}

.action-btn-view:hover {
    background: var(--primary);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(64, 153, 255, 0.3);
}

.action-btn-edit {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.action-btn-edit:hover {
    background: var(--warning);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(255, 182, 77, 0.3);
}

.action-btn-delete {
    background: var(--danger-light);
    color: var(--danger);
}

.action-btn-delete:hover {
    background: var(--danger);
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(255, 83, 112, 0.3);
}

/* ============================================
   EMPTY STATE
   ============================================ */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--gray-100);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.empty-state-icon i {
    font-size: 32px;
    color: var(--gray-400);
}

.empty-state h6 {
    color: var(--gray-600);
    font-weight: 600;
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray-400);
    font-size: 0.88rem;
    margin: 0;
}

/* ============================================
   FORM ACTION BAR
   ============================================ */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-100);
    margin-top: 8px;
}

/* ============================================
   DATE COLUMN STYLING
   ============================================ */
.msg-date {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.msg-date-day {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.msg-date-time {
    font-size: 0.75rem;
    color: var(--gray-400);
}

/* ============================================
   SUBJECT COLUMN
   ============================================ */
.msg-subject {
    font-weight: 500;
    color: var(--gray-800);
}

.msg-sender {
    display: flex;
    align-items: center;
    gap: 8px;
}

.msg-sender-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--primary-light);
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
}

.msg-sender-name {
    font-size: 0.85rem;
    color: var(--gray-700);
}

/* ============================================
   TOAST CONTAINER
   ============================================ */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

/* ============================================
   CHARACTER COUNTER
   ============================================ */
.char-counter {
    text-align: right;
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 4px;
}

/* ============================================
   RESPONSIVE STYLES
   ============================================ */
@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
    }

    .stat-card {
        min-width: 100%;
    }

    .msg-card .card-body {
        padding: 16px;
    }

    .msg-card .card-header {
        padding: 14px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn-send,
    .form-actions .btn-clear {
        width: 100%;
        justify-content: center;
    }

    .action-btn-group {
        flex-wrap: wrap;
    }

    .msg-table thead {
        display: none;
    }

    .msg-table tbody tr {
        display: block;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius-sm);
        margin-bottom: 12px;
        padding: 12px;
    }

    .msg-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 4px;
        border-bottom: 1px solid var(--gray-100);
    }

    .msg-table tbody td:last-child {
        border-bottom: none;
        justify-content: flex-end;
    }

    .msg-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.78rem;
        text-transform: uppercase;
        color: var(--gray-500);
        letter-spacing: 0.5px;
    }
}

/* ============================================
   SCROLLBAR STYLING
   ============================================ */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: var(--gray-400);
}

/* ============================================
   RECIPIENT SELECT TRANSITION
   ============================================ */
#recipient_select_group {
    transition: all 0.3s ease;
    overflow: hidden;
}

#recipient_select_group.show {
    display: block !important;
    animation: slideDown 0.3s ease forwards;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        max-height: 200px;
        transform: translateY(0);
    }
}

/* ============================================
   PAGE HEADER
   ============================================ */
.page-header-title h5 {
    font-weight: 700;
    font-size: 1.25rem;
    color: var(--gray-800);
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin: 8px 0 0 0;
}

.breadcrumb-item a {
    color: var(--gray-500);
    text-decoration: none;
    font-size: 0.85rem;
}

.breadcrumb-item a:hover {
    color: var(--primary);
}

.breadcrumb-item.active {
    color: var(--gray-400);
    font-size: 0.85rem;
}

/* ============================================
   LOADING SPINNER FOR REFRESH
   ============================================ */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.icon-spin {
    animation: spin 1s linear infinite;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5><?= __("send_messages") ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php"><?= __("dashboard") ?></a></li>
                            <li class="breadcrumb-item active"><?= __("send_messages") ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <?php
        $total_messages = count($recent_messages_result ?? []);
        $read_count = 0;
        $unread_count = 0;
        $client_count = count($clients ?? []);
        if (!empty($recent_messages_result)) {
            foreach ($recent_messages_result as $msg) {
                if (isset($msg['status']) && $msg['status'] === 'read') {
                    $read_count++;
                } else {
                    $unread_count++;
                }
            }
        }
        ?>
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="feather icon-mail"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $total_messages ?></h3>
                    <p><?= __("total_messages") ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon read">
                    <i class="feather icon-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $read_count ?></h3>
                    <p><?= __("read") ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon unread">
                    <i class="feather icon-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $unread_count ?></h3>
                    <p><?= __("unread") ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon clients">
                    <i class="feather icon-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $client_count ?></h3>
                    <p><?= __("clients") ?></p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <!-- Compose Message Card -->
                <div class="msg-card">
                    <div class="card-header">
                        <h5>
                            <i class="feather icon-edit"></i>
                            <?= __("compose_message") ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="composeMessageForm" class="msg-form">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="recipient_type"><?= __("send_to") ?></label>
                                        <select class="form-control" id="recipient_type" name="recipient_type" required onchange="toggleRecipientSelect()">
                                            <option value="clients"><?= __("all_clients") ?></option>
                                            <option value="individual"><?= __("individual_client") ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4" id="recipient_select_group" style="display: none;">
                                    <div class="form-group">
                                        <label for="recipient_id"><?= __("select_recipient") ?></label>
                                        <select class="form-control select2" id="recipient_id" name="recipient_id">
                                            <option value=""><?= __("select_a_recipient") ?></option>
                                            <?php if (!empty($clients)): ?>
                                                <?php foreach ($clients as $client): ?>
                                                    <option value="<?= $client['id']; ?>">
                                                        <?= htmlspecialchars($client['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4" id="subject_col">
                                    <div class="form-group">
                                        <label for="subject"><?= __("subject") ?></label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               placeholder="<?= __('enter_subject') ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="message"><?= __("message") ?></label>
                                        <textarea class="form-control" id="message" name="message" rows="4" 
                                                  placeholder="<?= __('type_your_message_here') ?>" required></textarea>
                                        <div class="char-counter">
                                            <span id="charCount">0</span> <?= __("characters") ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-clear" onclick="clearForm()">
                                    <i class="feather icon-x"></i>
                                    <?= __("clear") ?>
                                </button>
                                <button type="submit" class="btn-send">
                                    <i class="feather icon-send"></i>
                                    <?= __("send_message") ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Recent Messages Card -->
                <div class="msg-card">
                    <div class="card-header">
                        <h5>
                            <i class="feather icon-inbox"></i>
                            <?= __("recent_messages") ?>
                        </h5>
                        <button class="btn-refresh" id="refreshMessages">
                            <i class="feather icon-refresh-cw" id="refreshIcon"></i>
                            <?= __("refresh") ?>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_messages_result)): ?>
                        <div class="table-responsive">
                            <table class="msg-table" id="messagesTable">
                                <thead>
                                    <tr>
                                        <th><?= __("date") ?></th>
                                        <th><?= __("subject") ?></th>
                                        <th><?= __("recipient") ?></th>
                                        <th><?= __("status") ?></th>
                                        <th><?= __("sender") ?></th>
                                        <th><?= __("actions") ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_messages_result as $row): ?>
                                    <tr>
                                        <td data-label="<?= __('date') ?>">
                                            <div class="msg-date">
                                                <span class="msg-date-day"><?= date('M d, Y', strtotime($row['created_at'])); ?></span>
                                                <span class="msg-date-time"><?= date('g:i A', strtotime($row['created_at'])); ?></span>
                                            </div>
                                        </td>
                                        <td data-label="<?= __('subject') ?>">
                                            <span class="msg-subject"><?= htmlspecialchars($row['subject']); ?></span>
                                        </td>
                                        <td data-label="<?= __('recipient') ?>">
                                            <?php if ($row['recipient_type'] === 'individual'): ?>
                                                <span class="msg-badge msg-badge-info">
                                                    <i class="feather icon-user" style="font-size: 0.7rem;"></i>
                                                    <?= htmlspecialchars($row['recipient_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="msg-badge msg-badge-primary">
                                                    <i class="feather icon-users" style="font-size: 0.7rem;"></i>
                                                    <?= ucfirst($row['recipient_type']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?= __('status') ?>">
                                            <?php if (isset($row['status']) && $row['status'] === 'read'): ?>
                                                <span class="msg-badge msg-badge-success">
                                                    <i class="feather icon-check" style="font-size: 0.7rem;"></i>
                                                    <?= __("read") ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="msg-badge msg-badge-warning">
                                                    <i class="feather icon-clock" style="font-size: 0.7rem;"></i>
                                                    <?= __("unread") ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?= __('sender') ?>">
                                            <div class="msg-sender">
                                                <span class="msg-sender-avatar">
                                                    <?= strtoupper(substr($row['sender_name'], 0, 1)); ?>
                                                </span>
                                                <span class="msg-sender-name"><?= htmlspecialchars($row['sender_name']); ?></span>
                                            </div>
                                        </td>
                                        <td data-label="<?= __('actions') ?>">
                                            <div class="action-btn-group">
                                                <button type="button" class="action-btn action-btn-view view-message" 
                                                    data-id="<?= $row['id']; ?>"
                                                    data-subject="<?= htmlspecialchars($row['subject']); ?>"
                                                    data-message="<?= htmlspecialchars($row['message']); ?>"
                                                    data-sender="<?= htmlspecialchars($row['sender_name']); ?>"
                                                    data-recipient="<?= htmlspecialchars($row['recipient_name']); ?>"
                                                    data-date="<?= date('F j, Y g:i A', strtotime($row['created_at'])); ?>"
                                                    data-read-status="<?= isset($row['status']) ? $row['status'] : 'unread'; ?>"
                                                    data-toggle="tooltip" title="<?= __("view") ?>">
                                                    <i class="feather icon-eye"></i>
                                                </button>
                                                <button type="button" class="action-btn action-btn-edit edit-message" 
                                                    data-id="<?= $row['id']; ?>"
                                                    data-subject="<?= htmlspecialchars($row['subject']); ?>"
                                                    data-message="<?= htmlspecialchars($row['message']); ?>"
                                                    data-recipient-type="<?= $row['recipient_type']; ?>"
                                                    data-recipient-id="<?= $row['recipient_id'] ? $row['recipient_id'] : ''; ?>"
                                                    data-toggle="tooltip" title="<?= __("edit") ?>">
                                                    <i class="feather icon-edit-2"></i>
                                                </button>
                                                <button type="button" class="action-btn action-btn-delete delete-message" 
                                                    data-id="<?= $row['id']; ?>"
                                                    data-toggle="tooltip" title="<?= __("delete") ?>">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="feather icon-inbox"></i>
                            </div>
                            <h6><?= __("no_messages_yet") ?></h6>
                            <p><?= __("compose_your_first_message_above") ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/send_message/view_modal.php'; ?>
<?php include '../modals/send_message/edit_message_modal.php'; ?>
<?php include '../modals/send_message/delete_message_modal.php'; ?>

<!-- Toast container -->
<div class="toast-container"></div>

<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Prevent duplicate toast display
window.toastsDisplayed = false;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: selectRecipientText,
        allowClear: true,
        width: '100%'
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Refresh button
    document.getElementById('refreshMessages').addEventListener('click', function() {
        this.disabled = true;
        var icon = document.getElementById('refreshIcon');
        icon.classList.add('icon-spin');
        this.innerHTML = '<i class="feather icon-refresh-cw icon-spin" id="refreshIcon"></i> <?= __("loading") ?>';
        window.location.reload();
    });

    // Character counter
    var messageField = document.getElementById('message');
    var charCount = document.getElementById('charCount');
    if (messageField && charCount) {
        messageField.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }

    // Show toasts for flash messages
    if (!window.toastsDisplayed) {
        window.toastsDisplayed = true;
        if (successMessage) {
            showToast('success', successMessage);
        }
        if (errorMessage) {
            showToast('error', errorMessage);
        }
    }
});

// Toggle recipient select visibility
function toggleRecipientSelect() {
    var recipientType = document.getElementById('recipient_type').value;
    var recipientGroup = document.getElementById('recipient_select_group');
    var subjectCol = document.getElementById('subject_col');
    
    if (recipientType === 'individual') {
        recipientGroup.style.display = 'block';
        recipientGroup.classList.add('show');
        subjectCol.className = 'col-md-4';
    } else {
        recipientGroup.style.display = 'none';
        recipientGroup.classList.remove('show');
        subjectCol.className = 'col-md-8';
    }
}

// Clear form
function clearForm() {
    document.getElementById('composeMessageForm').reset();
    document.getElementById('charCount').textContent = '0';
    var recipientGroup = document.getElementById('recipient_select_group');
    recipientGroup.style.display = 'none';
    recipientGroup.classList.remove('show');
    document.getElementById('subject_col').className = 'col-md-8';
    
    // Reset Select2
    $('#recipient_id').val(null).trigger('change');
}

// Toast notification function
function showToast(type, message) {
var bgColor = type === 'success' ? 'var(--success)' : 'var(--danger)';
var icon = type === 'success' ? 'icon-check-circle' : 'icon-alert-circle';

var toast = document.createElement('div');
toast.style.cssText = 'background:' + bgColor + ';color:#fff;padding:14px 20px;border-radius:8px;margin-bottom:8px;display:flex;align-items:center;gap:10px;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);min-width:280px;opacity:0;transform:translateX(20px);transition:all 0.3s ease;';
toast.innerHTML = '<i class="feather ' + icon + '"></i><span>' + message + '</span>';

document.querySelector('.toast-container').appendChild(toast);

// Animate in
setTimeout(function() {
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(0)';
}, 10);

// Animate out
setTimeout(function() {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    setTimeout(function() {
        toast.remove();
    }, 300);
}, 4000);
}

// View message button handler
document.addEventListener('click', function(e) {
if (e.target.closest('.view-message')) {
    var btn = e.target.closest('.view-message');
    document.getElementById('messageSubject').textContent = btn.dataset.subject;
    document.getElementById('messageSender').textContent = btn.dataset.sender;
    document.getElementById('messageRecipient').textContent = btn.dataset.recipient;
    document.getElementById('messageDate').textContent = btn.dataset.date;
    document.getElementById('messageBody').textContent = btn.dataset.message;
    
    var statusText = btn.dataset.readStatus === 'read' ? '<?= __("read") ?>' : '<?= __("unread") ?>';
    var statusHtml = btn.dataset.readStatus === 'read' 
        ? '<span class="msg-badge msg-badge-success"><i class="feather icon-check"></i> ' + statusText + '</span>'
        : '<span class="msg-badge msg-badge-warning"><i class="feather icon-clock"></i> ' + statusText + '</span>';
    document.getElementById('messageStatus').innerHTML = statusHtml;
    
    $('#viewMessageModal').modal('show');
}
});

// Edit message button handler
document.addEventListener('click', function(e) {
if (e.target.closest('.edit-message')) {
    var btn = e.target.closest('.edit-message');
    document.getElementById('edit_message_id').value = btn.dataset.id;
    document.getElementById('edit_subject').value = btn.dataset.subject;
    document.getElementById('edit_message').value = btn.dataset.message;
    document.getElementById('edit_recipient_type').value = btn.dataset.recipientType;
    
    if (btn.dataset.recipientType === 'individual') {
        document.getElementById('edit_recipient_select_group').style.display = 'block';
        if (btn.dataset.recipientId) {
            document.getElementById('edit_recipient_id').value = btn.dataset.recipientId;
            $('#edit_recipient_id').trigger('change');
        }
    } else {
        document.getElementById('edit_recipient_select_group').style.display = 'none';
    }
    
    $('#editMessageModal').modal('show');
}
});

// Delete message button handler
document.addEventListener('click', function(e) {
if (e.target.closest('.delete-message')) {
    var btn = e.target.closest('.delete-message');
    document.getElementById('delete_message_id').value = btn.dataset.id;
    $('#deleteMessageModal').modal('show');
}
});

// Toggle recipient select for edit form
function toggleEditRecipientSelect() {
var recipientType = document.getElementById('edit_recipient_type').value;
var recipientGroup = document.getElementById('edit_recipient_select_group');

if (recipientType === 'individual') {
    recipientGroup.style.display = 'block';
} else {
    recipientGroup.style.display = 'none';
}
}
</script>

<?php include '../includes/admin_footer.php'; ?>