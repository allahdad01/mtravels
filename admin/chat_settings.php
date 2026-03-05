<?php
require_once '../includes/session_check.php';
require_once '../includes/db.php';
require_once '../includes/language_helpers.php';

// Load my tenant and get available branches
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { 
    header('Location: ../login.php'); 
    exit; 
}

$uStmt = secure_query($pdo, 'SELECT tenant_id, branch_id FROM users WHERE id = ?', [$uid]);
$u = $uStmt ? $uStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$u) { 
    die('User not found'); 
}
$tenantId = (int)$u['tenant_id'];
$userBranchId = (int)$u['branch_id'];

// Use user's branch from session (no branch selector)
$selectedBranch = $userBranchId;

// Get current branch info
$branchStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND id = ? AND status = "active"', [$tenantId, $selectedBranch]);
$branch = $branchStmt ? $branchStmt->fetch(PDO::FETCH_ASSOC) : null;
$branchExists = $branch ? true : false;

if (!$branchExists) {
    die('Branch not found or inactive');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$branchExists) {
        die('Invalid branch selected');
    }

    $max = isset($_POST['max_file_bytes']) ? max(1048576, (int)$_POST['max_file_bytes']) : 26214400;
    $pref = isset($_POST['allowed_mime_prefixes']) ? trim($_POST['allowed_mime_prefixes']) : 'image/,video/,audio/,application/pdf,text/';
    $auto = isset($_POST['default_auto_download']) ? 1 : 0;

    $checkStmt = secure_query($pdo, 'SELECT id FROM branch_chat_settings WHERE tenant_id = ? AND branch_id = ?', [$tenantId, $selectedBranch]);
    $exists = $checkStmt && $checkStmt->fetch();

    if ($exists) {
        secure_query($pdo,
            'UPDATE branch_chat_settings 
             SET chat_max_file_bytes = ?, chat_allowed_mime_prefixes = ?, chat_default_auto_download = ? 
             WHERE tenant_id = ? AND branch_id = ?',
            [$max, $pref, $auto, $tenantId, $selectedBranch]
        );
    } else {
        secure_query($pdo,
            'INSERT INTO branch_chat_settings (tenant_id, branch_id, chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download)
             VALUES (?, ?, ?, ?, ?)',
            [$tenantId, $selectedBranch, $max, $pref, $auto]
        );
    }

    header('Location: chat_settings.php?ok=1');
    exit;
}

// Load current settings for selected branch
$sStmt = secure_query($pdo,
    'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
     FROM branch_chat_settings 
     WHERE tenant_id = ? AND branch_id = ?',
    [$tenantId, $selectedBranch]
);
$s = $sStmt ? $sStmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$s) {
    $fallbackStmt = secure_query($pdo,
        'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
         FROM tenants 
         WHERE id = ?',
        [$tenantId]
    );
    $s = $fallbackStmt ? $fallbackStmt->fetch(PDO::FETCH_ASSOC) : null;
}

$chatSettings = [
    'chat_max_file_bytes'        => $s['chat_max_file_bytes'] ?? 26214400,
    'chat_allowed_mime_prefixes' => $s['chat_allowed_mime_prefixes'] ?? 'image/,video/,audio/,application/pdf,text/',
    'chat_default_auto_download' => $s['chat_default_auto_download'] ?? 0,
];


?>
<?php include '../includes/header.php'; ?>

<style>
/* ============================================
   DASH HEADER
   ============================================ */
.dash-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.dash-header h1 {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 0;
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.dash-header p {
    color: var(--gray-600);
    font-size: 14px;
    margin-top: 3px;
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .dash-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .dash-header h1 {
        font-size: 20px;
    }
}

/* ============================================
   CSS VARIABLES
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
    --purple: #7c4dff;
    --purple-light: #ede7f6;
    --purple-dark: #4a148c;
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
.settings-card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
    background: #fff;
    opacity: 0;
    transform: translateY(15px);
    animation: cardFadeIn 0.5s ease forwards;
}

.settings-card:nth-child(2) { animation-delay: 0.1s; }
.settings-card:nth-child(3) { animation-delay: 0.2s; }
.settings-card:nth-child(4) { animation-delay: 0.3s; }

@keyframes cardFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.settings-card > .card-header {
    background: #ffffff !important;
    color: var(--gray-800) !important;
    border-bottom: 1px solid var(--gray-200) !important;
    border-left: 4px solid var(--primary) !important;
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.settings-card > .card-header h5 {
    color: var(--gray-800) !important;
    margin-bottom: 0 !important;
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-card > .card-header h5 i {
    color: var(--primary);
    font-size: 1.15rem;
}

.settings-card > .card-header p {
    margin: 0;
    color: var(--gray-500);
    font-size: 0.82rem;
}

.settings-card > .card-body {
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

.stat-icon.branches { background: var(--primary-light); color: var(--primary); }
.stat-icon.configured { background: var(--success-light); color: var(--success-dark); }
.stat-icon.pending { background: var(--warning-light); color: var(--warning-dark); }
.stat-icon.auto-dl { background: var(--purple-light); color: var(--purple); }

.stat-info h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1;
}

.stat-info p {
    margin: 4px 0 0 0;
    font-size: 0.78rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* ============================================
   BRANCH SELECTOR
   ============================================ */
.branch-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.branch-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1.5px solid var(--gray-300);
    background: #fff;
    color: var(--gray-600);
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
}

.branch-pill:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(64, 153, 255, 0.15);
}

.branch-pill.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(64, 153, 255, 0.3);
}

.branch-pill.active:hover {
    background: var(--primary-dark);
    color: #fff;
}

.branch-pill i {
    font-size: 0.8rem;
}

/* ============================================
   FORM STYLES
   ============================================ */
.settings-form .form-group {
    margin-bottom: 20px;
}

.settings-form label {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-700);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.settings-form label i {
    color: var(--primary);
    font-size: 0.9rem;
}

.settings-form .form-control {
    border-radius: var(--border-radius-sm);
    border: 1.5px solid var(--gray-300);
    padding: 10px 14px;
    font-size: 0.9rem;
    transition: var(--transition);
    background: var(--gray-50);
}

.settings-form .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.12);
    background: #fff;
}

.settings-form .form-text {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-top: 6px;
    line-height: 1.5;
}

/* ============================================
   FILE SIZE VISUAL SLIDER
   ============================================ */
.file-size-display {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}

.file-size-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    background: var(--primary-light);
    color: var(--primary-dark);
}

.file-size-bar {
    flex: 1;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}

.file-size-bar-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--success) 0%, var(--primary) 50%, var(--warning) 100%);
    transition: width 0.3s ease;
}

/* ============================================
   MIME TAG DISPLAY
   ============================================ */
.mime-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}

.mime-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 500;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}

.mime-tag i {
    font-size: 0.7rem;
}

.mime-tag.image { background: #fff3e0; color: #e65100; border-color: #ffe0b2; }
.mime-tag.video { background: #e8eaf6; color: #283593; border-color: #c5cae9; }
.mime-tag.audio { background: #fce4ec; color: #ad1457; border-color: #f8bbd0; }
.mime-tag.pdf { background: #ffebee; color: #b71c1c; border-color: #ffcdd2; }
.mime-tag.text { background: #e0f2f1; color: #00695c; border-color: #b2dfdb; }

/* ============================================
   TOGGLE SWITCH
   ============================================ */
.toggle-switch {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-radius: var(--border-radius-sm);
    border: 1.5px solid var(--gray-200);
    background: var(--gray-50);
    transition: var(--transition);
    cursor: pointer;
}

.toggle-switch:hover {
    border-color: var(--primary);
    background: var(--primary-light);
}

.toggle-switch.checked {
    border-color: var(--success);
    background: var(--success-light);
}

.toggle-track {
    width: 48px;
    height: 26px;
    border-radius: 13px;
    background: var(--gray-300);
    position: relative;
    transition: var(--transition);
    flex-shrink: 0;
}

.toggle-track.active {
    background: var(--success);
}

.toggle-thumb {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #fff;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: var(--transition);
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.toggle-track.active .toggle-thumb {
    left: 24px;
}

.toggle-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.toggle-label-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.toggle-label-desc {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* ============================================
   BUTTONS
   ============================================ */
.btn-save {
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

.btn-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.35);
    color: #fff;
}

.btn-reset {
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

.btn-reset:hover {
    border-color: var(--gray-400);
    background: var(--gray-100);
    color: var(--gray-700);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-100);
    margin-top: 8px;
}

/* ============================================
   SUMMARY TABLE
   ============================================ */
.summary-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.summary-table thead th {
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

.summary-table tbody tr {
    transition: var(--transition);
}

.summary-table tbody tr:hover {
    background-color: var(--primary-light);
}

.summary-table tbody tr.active-row {
    background-color: var(--primary-light);
    border-left: 3px solid var(--primary);
}

.summary-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.88rem;
    color: var(--gray-700);
}

.summary-table tbody tr:last-child td {
    border-bottom: none;
}

/* ============================================
   BADGES
   ============================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-badge-success {
    background: var(--success-light);
    color: var(--success-dark);
}

.status-badge-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}

.status-badge-warning {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.config-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 16px;
    font-size: 0.72rem;
    font-weight: 500;
}

.config-badge-configured {
    background: var(--success-light);
    color: var(--success-dark);
}

.config-badge-default {
    background: var(--gray-100);
    color: var(--gray-500);
}

/* ============================================
   BRANCH NAME IN TABLE
   ============================================ */
.branch-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.branch-avatar {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
    background: var(--primary-light);
    color: var(--primary);
}

.branch-name-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.branch-name-text strong {
    font-size: 0.88rem;
    color: var(--gray-800);
}

.branch-name-text small {
    font-size: 0.72rem;
    color: var(--gray-400);
}

/* ============================================
   ACTION BUTTON IN TABLE
   ============================================ */
.btn-edit-branch {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    border-radius: var(--border-radius-sm);
    font-size: 0.8rem;
    font-weight: 500;
    border: 1.5px solid var(--primary);
    color: var(--primary);
    background: transparent;
    text-decoration: none;
    transition: var(--transition);
}

.btn-edit-branch:hover {
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(64, 153, 255, 0.25);
}

/* ============================================
   ALERTS
   ============================================ */
.alert-custom {
    border-radius: var(--border-radius-sm);
    border: none;
    padding: 14px 18px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.88rem;
    margin-bottom: 0;
}

.alert-custom-success {
    background: var(--success-light);
    color: var(--success-dark);
}

.alert-custom-info {
    background: var(--info-light);
    color: var(--info-dark);
}

.alert-custom i {
    margin-top: 2px;
    flex-shrink: 0;
}

/* ============================================
   SECTION LABEL
   ============================================ */
.section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-100);
}

.section-label i {
    color: var(--primary);
}

/* ============================================
   TOAST
   ============================================ */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .stats-row {
        flex-direction: column;
    }

    .stat-card {
        min-width: 100%;
    }

    .settings-card > .card-body {
        padding: 16px;
    }

    .settings-card > .card-header {
        padding: 14px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .branch-selector {
        flex-direction: column;
    }

    .branch-pill {
        justify-content: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .form-actions .btn-save,
    .form-actions .btn-reset {
        width: 100%;
        justify-content: center;
    }

    .summary-table thead {
        display: none;
    }

    .summary-table tbody tr {
        display: block;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius-sm);
        margin-bottom: 12px;
        padding: 12px;
    }

    .summary-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 4px;
        border-bottom: 1px solid var(--gray-100);
    }

    .summary-table tbody td:last-child {
        border-bottom: none;
        justify-content: flex-end;
    }

    .summary-table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        font-size: 0.78rem;
        text-transform: uppercase;
        color: var(--gray-500);
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

/* ============================================
   SCROLLBAR
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
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- Page Header -->
        <div class="dash-header" style="margin-bottom: 24px;">
            <div>
                <h1><i class="feather icon-message-circle mr-2" style="color: var(--primary);"></i>Chat Settings</h1>
                <p><?= htmlspecialchars($branch['name']) ?> - Configure file upload limits and MIME type restrictions</p>
            </div>
        </div>

        <!-- Success Alert -->
         <?php if (!empty($_GET['ok'])): ?>
         <div class="alert-custom alert-custom-success mb-3" id="successAlert" style="animation: cardFadeIn 0.3s ease forwards;">
             <i class="feather icon-check-circle"></i>
             <div>
                 Settings saved successfully for <strong><?= htmlspecialchars($branch['name']) ?></strong>
             </div>
         </div>
         <?php endif; ?>



        <div class="row">
            <div class="col-md-12">

                <!-- Settings Form Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <h5>
                            <i class="feather icon-sliders"></i>
                            Chat Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="settings-form" id="settingsForm">
                            <div class="row">
                                <!-- Max File Size -->
                                <div class="col-lg-6">
                                    <div class="section-label">
                                        <i class="feather icon-hard-drive"></i>
                                        File Upload Limits
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <i class="feather icon-upload"></i>
                                            Maximum File Size
                                        </label>
                                        <input type="number" name="max_file_bytes" class="form-control" 
                                               id="maxFileBytes"
                                               value="<?= (int)$chatSettings['chat_max_file_bytes'] ?>" 
                                               min="1048576" step="1048576" required />
                                        <div class="file-size-display">
                                            <span class="file-size-badge" id="fileSizeBadge">
                                                <i class="feather icon-hard-drive"></i>
                                                <span id="fileSizeText"><?= number_format((int)$chatSettings['chat_max_file_bytes'] / 1048576, 1) ?></span> MB
                                            </span>
                                            <div class="file-size-bar">
                                                <div class="file-size-bar-fill" id="fileSizeBar" style="width: <?= min(100, ((int)$chatSettings['chat_max_file_bytes'] / 104857600) * 100) ?>%;"></div>
                                            </div>
                                        </div>
                                        <small class="form-text">
                                            Minimum: 1 MB · Maximum recommended: 100 MB · Applies to all uploads in this branch
                                        </small>
                                    </div>
                                </div>

                                <!-- MIME Prefixes -->
                                <div class="col-lg-6">
                                    <div class="section-label">
                                        <i class="feather icon-file"></i>
                                        Allowed File Types
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <i class="feather icon-filter"></i>
                                            MIME Type Prefixes
                                        </label>
                                        <input type="text" name="allowed_mime_prefixes" class="form-control" 
                                               id="mimePrefixes"
                                               value="<?= htmlspecialchars($chatSettings['chat_allowed_mime_prefixes']) ?>" required />
                                        <div class="mime-tags" id="mimeTags">
                                            <!-- Populated by JS -->
                                        </div>
                                        <small class="form-text">
                                            Comma-separated MIME prefixes. Examples: image/, video/, audio/, application/pdf, text/
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Auto-Download Toggle -->
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="section-label">
                                        <i class="feather icon-download"></i>
                                        Download Behavior
                                    </div>
                                    <div class="toggle-switch <?= ((int)$chatSettings['chat_default_auto_download']) ? 'checked' : '' ?>" 
                                         id="autoDownloadToggle" onclick="toggleAutoDownload()">
                                        <div class="toggle-track <?= ((int)$chatSettings['chat_default_auto_download']) ? 'active' : '' ?>" id="toggleTrack">
                                            <div class="toggle-thumb"></div>
                                        </div>
                                        <div class="toggle-label">
                                            <span class="toggle-label-title">Auto-download files</span>
                                            <span class="toggle-label-desc">
                                                When enabled, files are downloaded automatically. Users can still override this individually.
                                            </span>
                                        </div>
                                    </div>
                                    <input type="checkbox" name="default_auto_download" id="autoDownloadInput" 
                                           <?= ((int)$chatSettings['chat_default_auto_download']) ? 'checked' : '' ?> 
                                           style="display: none;">
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button class="btn-reset" type="button" onclick="resetDefaults()">
                                    <i class="feather icon-refresh-ccw"></i>
                                    Reset to Recommended
                                </button>
                                <button class="btn-save" type="submit">
                                    <i class="feather icon-save"></i>
                                    Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast container -->
<div class="toast-container"></div>

<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize MIME tags
    updateMimeTags();

    // File size live update
    var maxFileInput = document.getElementById('maxFileBytes');
    if (maxFileInput) {
        maxFileInput.addEventListener('input', updateFileSizeDisplay);
    }

    // MIME input live update
    var mimeInput = document.getElementById('mimePrefixes');
    if (mimeInput) {
        mimeInput.addEventListener('input', updateMimeTags);
    }

    // Auto-dismiss success alert
    var successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                successAlert.remove();
            }, 500);
        }, 5000);
    }

    // Initialize tooltips
    if (typeof $ !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

// File size display updater
function updateFileSizeDisplay() {
    var bytes = parseInt(document.getElementById('maxFileBytes').value) || 0;
    var mb = (bytes / 1048576).toFixed(1);
    var percent = Math.min(100, (bytes / 104857600) * 100);
    
    document.getElementById('fileSizeText').textContent = mb;
    document.getElementById('fileSizeBar').style.width = percent + '%';

    // Color coding
    var badge = document.getElementById('fileSizeBadge');
    if (mb <= 5) {
        badge.style.background = 'var(--success-light)';
        badge.style.color = 'var(--success-dark)';
    } else if (mb <= 25) {
        badge.style.background = 'var(--primary-light)';
        badge.style.color = 'var(--primary-dark)';
    } else if (mb <= 50) {
        badge.style.background = 'var(--warning-light)';
        badge.style.color = 'var(--warning-dark)';
    } else {
        badge.style.background = 'var(--danger-light)';
        badge.style.color = 'var(--danger-dark)';
    }
}

// MIME tags updater
function updateMimeTags() {
    var input = document.getElementById('mimePrefixes');
    var container = document.getElementById('mimeTags');
    if (!input || !container) return;

    var value = input.value.trim();
    if (!value) {
        container.innerHTML = '';
        return;
    }

    var prefixes = value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
    var html = '';

    var iconMap = {
        'image': { icon: 'icon-image', class: 'image' },
        'video': { icon: 'icon-video', class: 'video' },
        'audio': { icon: 'icon-headphones', class: 'audio' },
        'pdf':   { icon: 'icon-file-text', class: 'pdf' },
        'text':  { icon: 'icon-file-text', class: 'text' },
    };

    prefixes.forEach(function(prefix) {
        var matchedClass = '';
        var matchedIcon = 'icon-file';

        for (var key in iconMap) {
            if (prefix.toLowerCase().indexOf(key) !== -1) {
                matchedClass = iconMap[key].class;
                matchedIcon = iconMap[key].icon;
                break;
            }
        }

        html += '<span class="mime-tag ' + matchedClass + '">' +
                '<i class="feather ' + matchedIcon + '"></i>' +
                prefix +
                '</span>';
    });

    container.innerHTML = html;
}

// Toggle auto-download
function toggleAutoDownload() {
    var checkbox = document.getElementById('autoDownloadInput');
    var toggle = document.getElementById('autoDownloadToggle');
    var track = document.getElementById('toggleTrack');

    checkbox.checked = !checkbox.checked;

    if (checkbox.checked) {
        toggle.classList.add('checked');
        track.classList.add('active');
    } else {
        toggle.classList.remove('checked');
        track.classList.remove('active');
    }
}

// Reset to defaults
function resetDefaults() {
    var confirmed = confirm('Reset all settings to recommended defaults?');
    if (!confirmed) return;

    var maxFile = document.getElementById('maxFileBytes');
    var mime = document.getElementById('mimePrefixes');
    var autoCheckbox = document.getElementById('autoDownloadInput');
    var toggle = document.getElementById('autoDownloadToggle');
    var track = document.getElementById('toggleTrack');

    if (maxFile) maxFile.value = 26214400;
    if (mime) mime.value = 'image/,video/,audio/,application/pdf,text/';
    
    if (autoCheckbox) {
        autoCheckbox.checked = false;
        toggle.classList.remove('checked');
        track.classList.remove('active');
    }

    updateFileSizeDisplay();
    updateMimeTags();

    // Show brief toast
    showToast('info', 'Settings reset to recommended defaults. Click Save to apply.');
}

// Toast notification
function showToast(type, message) {
    var colors = {
        success: 'var(--success)',
        error: 'var(--danger)',
        info: 'var(--primary)'
    };
    var icons = {
        success: 'icon-check-circle',
        error: 'icon-alert-circle',
        info: 'icon-info'
    };

    var toast = document.createElement('div');
    toast.style.cssText = 'background:' + (colors[type] || colors.info) + ';color:#fff;padding:14px 20px;border-radius:8px;margin-bottom:8px;display:flex;align-items:center;gap:10px;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.15);min-width:300px;opacity:0;transform:translateX(20px);transition:all 0.3s ease;';
    toast.innerHTML = '<i class="feather ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span>';
    
    document.querySelector('.toast-container').appendChild(toast);
    
    setTimeout(function() {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 10);
    
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 4000);
}
</script>

<?php include '../includes/admin_footer.php'; ?>