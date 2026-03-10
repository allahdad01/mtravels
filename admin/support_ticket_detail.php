<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['role'];

if (!in_array($user_role, ['admin', 'finance', 'sales', 'umrah'])) {
    header('Location: ../access_denied.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';
require_once '../includes/TicketNotificationService.php';
require_once '../includes/SecureFileUpload.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);
$notificationService = new TicketNotificationService($pdo);

$ticket_id = $_GET['id'] ?? 0;
if (!$ticket_id) {
    header('Location: support_tickets.php');
    exit();
}

$ticket = $ticketManager->getTicket($ticket_id);
if (!$ticket || $ticket['tenant_id'] != $tenant_id) {
    header('Location: ../access_denied.php');
    exit();
}

$replies = $ticketManager->getReplies($ticket_id, false);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_text = $_POST['reply'] ?? '';

    if (empty($reply_text)) {
        $error = 'Reply cannot be empty';
    } else {
        $screenshot_path = null;
        if (isset($_FILES['screenshot'])) {
            $uploader = new SecureFileUpload(5 * 1024 * 1024, '../uploads/');
            $result = $uploader->upload('screenshot', 'support_tickets');
            if ($result['success']) {
                $screenshot_path = 'uploads/support_tickets/' . $result['data']['filename'];
            } else {
                $error = "File upload failed: " . $result['error'];
            }
        }

        if (empty($error)) {
            $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
            $result = $ticketManager->addReply($ticket_id, $user_id, $reply_text, false, $screenshot_path);
            if ($result['success']) {
                $success = 'Reply added successfully!';
                $ticket = $ticketManager->getTicket($ticket_id);
                $replies = $ticketManager->getReplies($ticket_id, false);
            } else {
                $error = $result['error'] ?? 'Failed to add reply';
            }
        }
    }
}

if ($ticket['status'] !== 'closed' && isset($_POST['update_status'])) {
    $new_status = $_POST['update_status'] ?? '';
    if (in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
        $result = $ticketManager->updateStatus($ticket_id, $new_status, $user_id);
        if ($result['success']) {
            $success = 'Ticket status updated!';
            $ticket = $ticketManager->getTicket($ticket_id);
        } else {
            $error = $result['error'] ?? 'Failed to update status';
        }
    }
}

$sla_display = $slaCalculator->getSLADisplay($ticket);

$pageTitle = "Ticket " . htmlspecialchars($ticket['ticket_number']);
require_once '../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #0d0f12;
        --surface:   #f4f3ef;
        --card-bg:   #ffffff;
        --border:    #e3e1db;
        --muted:     #8a8880;
        --accent:    #e8533a;
        --accent-2:  #f5a623;
        --accent-3:  #2db899;
        --accent-4:  #4a7cf7;
        --critical:  #e8533a;
        --high:      #f5a623;
        --radius-sm: 6px;
        --radius-md: 12px;
        --shadow-sm: 0 1px 3px rgba(0,0,0,.07);
        --shadow-md: 0 4px 16px rgba(0,0,0,.09);
    }

    body, .pcoded-main-container { background: var(--surface) !important; font-family: 'DM Sans', sans-serif; color: var(--ink); }

    /* ── SHELL ── */
    .td-shell { max-width: 1400px; margin: 0 auto; padding: 32px 28px 60px; }

    /* ── TOP BAR ── */
    .td-topbar { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 12px; flex-wrap: wrap; }
    .td-eyebrow { font-size: 11px; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .td-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; line-height: 1; color: var(--ink); margin: 0; }
    .td-title span { color: var(--muted); font-weight: 600; }
    .td-topbar-right { display: flex; gap: 10px; align-items: center; }

    .st-btn-back {
        display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px;
        border-radius: var(--radius-sm); border: 1.5px solid var(--border);
        background: var(--card-bg); color: var(--muted); font-size: 13px; font-weight: 500;
        text-decoration: none; transition: border-color .15s, color .15s;
    }
    .st-btn-back:hover { border-color: var(--ink); color: var(--ink); }

    /* ── ALERTS ── */
    .td-alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 16px; font-size: 13.5px; font-weight: 500; }
    .td-alert-success { background: #d6f5ec; color: #1a7a5b; border: 1px solid #b8eddc; }
    .td-alert-danger  { background: #fde8e4; color: #c0392b; border: 1px solid #f8c9c2; }
    .td-alert-close { margin-left: auto; background: none; border: none; cursor: pointer; font-size: 16px; opacity: .5; line-height: 1; padding: 0; color: inherit; }
    .td-alert-close:hover { opacity: 1; }

    /* ── LAYOUT ── */
    .td-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; }
    @media (max-width: 900px) { .td-layout { grid-template-columns: 1fr; } }

    /* ── CARD BASE ── */
    .td-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: var(--radius-md); margin-bottom: 16px; overflow: hidden; }
    .td-card-header { padding: 16px 22px; border-bottom: 1.5px solid var(--border); display: flex; align-items: center; gap: 10px; }
    .td-card-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; margin: 0; }
    .td-card-body { padding: 22px; }

    /* ── TICKET DETAILS ── */
    .td-meta-row { display: flex; gap: 0; border-bottom: 1.5px solid var(--border); flex-wrap: wrap; }
    .td-meta-item { flex: 1; min-width: 140px; padding: 14px 20px; border-right: 1.5px solid var(--border); }
    .td-meta-item:last-child { border-right: none; }
    .td-meta-label { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }

    .td-description { padding: 22px; font-size: 14px; line-height: 1.7; color: #2a2d33; }
    .td-description-label { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
    .td-screenshot { padding: 0 22px 22px; }
    .td-screenshot img { border-radius: var(--radius-sm); border: 1.5px solid var(--border); max-width: 100%; max-height: 280px; display: block; }

    /* ── CHIPS ── */
    .chip { display: inline-block; padding: 4px 11px; border-radius: 4px; font-size: 11.5px; font-weight: 600; letter-spacing: .02em; white-space: nowrap; }
    .chip-cat      { background: #f0edff; color: #5b45d4; }
    .chip-critical { background: #fde8e4; color: #c0392b; }
    .chip-high     { background: #fef3cd; color: #9a6b00; }
    .chip-medium   { background: #ddeeff; color: #1a5fb4; }
    .chip-low      { background: #ebebeb; color: #666; }
    .chip-open        { background: #ddeeff; color: #1a5fb4; }
    .chip-in_progress { background: #fef3cd; color: #9a6b00; }
    .chip-resolved    { background: #d6f5ec; color: #1a7a5b; }
    .chip-closed      { background: #ebebeb; color: #555; }
    .chip-success  { background: #d6f5ec; color: #1a7a5b; }
    .chip-warning  { background: #fef3cd; color: #9a6b00; }
    .chip-danger   { background: #fde8e4; color: #c0392b; }
    .chip-secondary{ background: #ebebeb; color: #666; }
    .chip-info     { background: #ddeeff; color: #1a5fb4; }
    .chip-primary  { background: #ddeeff; color: #1a5fb4; }

    /* ── CONVERSATION ── */
    .td-thread { padding: 0 22px 22px; display: flex; flex-direction: column; gap: 12px; max-height: 460px; overflow-y: auto; }
    .td-thread::-webkit-scrollbar { width: 4px; }
    .td-thread::-webkit-scrollbar-track { background: transparent; }
    .td-thread::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    .td-reply { padding: 14px 16px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--surface); }
    .td-reply-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; gap: 10px; }
    .td-reply-name { font-size: 13px; font-weight: 600; color: var(--ink); }
    .td-reply-time { font-size: 11.5px; color: var(--muted); white-space: nowrap; }
    .td-reply-text { font-size: 13.5px; line-height: 1.6; color: #2a2d33; margin: 0; }
    .td-reply-img { margin-top: 10px; }
    .td-reply-img img { border-radius: var(--radius-sm); border: 1.5px solid var(--border); max-width: 180px; display: block; }

    .td-empty { text-align: center; padding: 32px 0; color: var(--muted); font-size: 13.5px; }

    /* ── REPLY FORM ── */
    .td-textarea {
        width: 100%; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
        padding: 12px 14px; font-family: 'DM Sans', sans-serif; font-size: 13.5px;
        color: var(--ink); resize: vertical; min-height: 100px; transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .td-textarea:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(13,15,18,.07); }
    .td-textarea::placeholder { color: var(--muted); }

    .td-file-label { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }
    .td-file-input {
        display: block; width: 100%; padding: 9px 12px; border: 1.5px dashed var(--border);
        border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 13px;
        color: var(--muted); background: var(--surface); cursor: pointer; box-sizing: border-box;
        transition: border-color .15s;
    }
    .td-file-input:hover { border-color: var(--ink); }
    .td-file-hint { font-size: 11px; color: var(--muted); margin-top: 5px; }

    .td-btn-send {
        display: inline-flex; align-items: center; gap: 7px; padding: 10px 24px;
        background: var(--ink); color: #fff; border: none; border-radius: var(--radius-sm);
        font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: .03em;
        cursor: pointer; transition: background .15s, transform .15s, box-shadow .15s;
    }
    .td-btn-send:hover { background: #1f2329; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }

    /* ── SIDEBAR ── */
    .td-info-list { display: flex; flex-direction: column; gap: 0; }
    .td-info-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 11px 0; border-bottom: 1px solid var(--border); gap: 12px; }
    .td-info-row:last-child { border-bottom: none; }
    .td-info-key { font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
    .td-info-val { font-size: 13px; font-weight: 500; color: var(--ink); text-align: right; }

    /* SLA Progress */
    .td-sla-bar-wrap { height: 6px; background: var(--border); border-radius: 6px; overflow: hidden; margin-top: 6px; }
    .td-sla-bar { height: 100%; border-radius: 6px; transition: width .4s ease; }
    .bar-success  { background: var(--accent-3); }
    .bar-warning  { background: var(--accent-2); }
    .bar-danger   { background: var(--critical); }
    .bar-secondary{ background: var(--muted); }

    /* Status buttons */
    .td-status-btn {
        display: block; width: 100%; padding: 9px 14px; margin-bottom: 8px;
        border-radius: var(--radius-sm); border: 1.5px solid var(--border);
        background: var(--surface); font-family: 'DM Sans', sans-serif; font-size: 13px;
        font-weight: 600; color: var(--ink); cursor: pointer; text-align: left;
        transition: all .15s; display: flex; align-items: center; gap: 8px;
    }
    .td-status-btn:last-child { margin-bottom: 0; }
    .td-status-btn:hover { border-color: var(--ink); background: #fff; transform: translateX(2px); }
    .td-status-btn .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .dot-open        { background: var(--accent-4); }
    .dot-in_progress { background: var(--accent-2); }
    .dot-resolved    { background: var(--accent-3); }
    .dot-closed      { background: var(--muted); }

    .td-closed-notice { padding: 12px 16px; background: #f9f9f7; border-radius: var(--radius-sm); border: 1.5px solid var(--border); font-size: 13px; color: var(--muted); text-align: center; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="td-shell">

                            <!-- Top Bar -->
                            <div class="td-topbar">
                                <div>
                                    <p class="td-eyebrow">Admin &rsaquo; Help Desk &rsaquo; Ticket</p>
                                    <h1 class="td-title">
                                        <span><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                    </h1>
                                </div>
                                <div class="td-topbar-right">
                                    <a href="support_tickets.php" class="st-btn-back">
                                        <i class="feather icon-arrow-left"></i> Back to Tickets
                                    </a>
                                </div>
                            </div>

                            <!-- Alerts -->
                            <?php if (!empty($success)): ?>
                                <div class="td-alert td-alert-success">
                                    <i class="feather icon-check-circle"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                    <button class="td-alert-close" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error)): ?>
                                <div class="td-alert td-alert-danger">
                                    <i class="feather icon-alert-circle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                    <button class="td-alert-close" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                            <?php endif; ?>

                            <!-- Layout -->
                            <div class="td-layout">

                                <!-- LEFT COLUMN -->
                                <div>
                                    <!-- Ticket Detail Card -->
                                    <div class="td-card">
                                        <div class="td-card-header">
                                            <i class="feather icon-file-text" style="opacity:.45"></i>
                                            <h5 class="td-card-title"><?php echo htmlspecialchars($ticket['title']); ?></h5>
                                        </div>
                                        <!-- Meta row -->
                                        <div class="td-meta-row">
                                            <div class="td-meta-item">
                                                <div class="td-meta-label">Status</div>
                                                <span class="chip chip-<?php echo $ticket['status']; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                                </span>
                                            </div>
                                            <div class="td-meta-item">
                                                <div class="td-meta-label">Priority</div>
                                                <span class="chip chip-<?php echo $ticket['priority']; ?>">
                                                    <?php echo ucfirst($ticket['priority']); ?>
                                                </span>
                                            </div>
                                            <div class="td-meta-item">
                                                <div class="td-meta-label">Category</div>
                                                <span class="chip chip-cat"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                            </div>
                                            <div class="td-meta-item">
                                                <div class="td-meta-label">SLA Status</div>
                                                <span class="chip chip-<?php echo $sla_display['color']; ?>">
                                                    <?php echo $sla_display['status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Description -->
                                        <div class="td-description">
                                            <div class="td-description-label">Description</div>
                                            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                                        </div>
                                        <!-- Screenshot -->
                                        <?php if ($ticket['screenshot_path']): ?>
                                            <div class="td-screenshot">
                                                <div class="td-description-label" style="margin-bottom:10px">Screenshot</div>
                                                <img src="../<?php echo htmlspecialchars($ticket['screenshot_path']); ?>" alt="Screenshot">
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Conversation Card -->
                                    <div class="td-card">
                                        <div class="td-card-header">
                                            <i class="feather icon-message-square" style="opacity:.45"></i>
                                            <h5 class="td-card-title">Conversation</h5>
                                            <span style="margin-left:auto;background:var(--ink);color:#fff;font-size:11px;font-weight:600;padding:2px 9px;border-radius:20px;"><?php echo count($replies); ?></span>
                                        </div>
                                        <div class="td-thread">
                                            <?php if (empty($replies)): ?>
                                                <div class="td-empty">
                                                    <i class="feather icon-message-circle" style="font-size:28px;display:block;margin-bottom:8px;opacity:.3"></i>
                                                    No replies yet
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($replies as $reply): ?>
                                                    <div class="td-reply">
                                                        <div class="td-reply-header">
                                                            <span class="td-reply-name"><?php echo htmlspecialchars($reply['replied_by_name']); ?></span>
                                                            <span class="td-reply-time"><?php echo date('M d, Y · H:i', strtotime($reply['created_at'])); ?></span>
                                                        </div>
                                                        <p class="td-reply-text"><?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?></p>
                                                        <?php if ($reply['screenshot_path']): ?>
                                                            <div class="td-reply-img">
                                                                <img src="../<?php echo htmlspecialchars($reply['screenshot_path']); ?>" alt="Attachment">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Reply Form -->
                                    <?php if ($ticket['status'] !== 'closed'): ?>
                                        <div class="td-card">
                                            <div class="td-card-header">
                                                <i class="feather icon-send" style="opacity:.45"></i>
                                                <h5 class="td-card-title">Add Reply</h5>
                                            </div>
                                            <div class="td-card-body">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <div style="margin-bottom:14px">
                                                        <textarea class="td-textarea" name="reply" rows="4" placeholder="Type your reply here…" required></textarea>
                                                    </div>
                                                    <div style="margin-bottom:18px">
                                                        <label class="td-file-label">Attach Screenshot</label>
                                                        <input type="file" class="td-file-input" name="screenshot" accept="image/*">
                                                        <p class="td-file-hint">JPG, PNG or GIF · max 5 MB</p>
                                                    </div>
                                                    <button type="submit" class="td-btn-send">
                                                        <i class="feather icon-send"></i> Send Reply
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- RIGHT COLUMN (sidebar) -->
                                <div>
                                    <!-- Update Status -->
                                    <?php if ($ticket['status'] !== 'closed'): ?>
                                        <div class="td-card">
                                            <div class="td-card-header">
                                                <i class="feather icon-refresh-cw" style="opacity:.45"></i>
                                                <h5 class="td-card-title">Update Status</h5>
                                            </div>
                                            <div class="td-card-body">
                                                <form method="POST">
                                                    <?php
                                                    $status_options = [
                                                        'open'        => ['label' => 'Open',        'dot' => 'dot-open'],
                                                        'in_progress' => ['label' => 'In Progress',  'dot' => 'dot-in_progress'],
                                                        'resolved'    => ['label' => 'Resolved',     'dot' => 'dot-resolved'],
                                                        'closed'      => ['label' => 'Close Ticket', 'dot' => 'dot-closed'],
                                                    ];
                                                    foreach ($status_options as $status => $opts):
                                                        if ($status !== $ticket['status']):
                                                    ?>
                                                        <button type="submit" name="update_status" value="<?php echo $status; ?>" class="td-status-btn">
                                                            <span class="status-dot <?php echo $opts['dot']; ?>"></span>
                                                            <?php echo $opts['label']; ?>
                                                        </button>
                                                    <?php endif; endforeach; ?>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="td-card">
                                            <div class="td-card-body">
                                                <div class="td-closed-notice">
                                                    <i class="feather icon-lock" style="display:block;font-size:20px;margin-bottom:6px;opacity:.4"></i>
                                                    This ticket is closed
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Ticket Information -->
                                    <div class="td-card">
                                        <div class="td-card-header">
                                            <i class="feather icon-info" style="opacity:.45"></i>
                                            <h5 class="td-card-title">Ticket Information</h5>
                                        </div>
                                        <div class="td-card-body">
                                            <div class="td-info-list">
                                                <div class="td-info-row">
                                                    <span class="td-info-key">Ticket #</span>
                                                    <span class="td-info-val" style="font-family:'Syne',sans-serif;font-weight:700"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                                </div>
                                                <div class="td-info-row">
                                                    <span class="td-info-key">Created by</span>
                                                    <span class="td-info-val"><?php echo htmlspecialchars($ticket['created_by_name']); ?></span>
                                                </div>
                                                <div class="td-info-row">
                                                    <span class="td-info-key">Created</span>
                                                    <span class="td-info-val"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?><br><span style="color:var(--muted);font-size:11px"><?php echo date('H:i', strtotime($ticket['created_at'])); ?></span></span>
                                                </div>
                                                <div class="td-info-row">
                                                    <span class="td-info-key">SLA Due</span>
                                                    <span class="td-info-val"><?php echo date('M d, Y', strtotime($ticket['sla_due_at'])); ?><br><span style="color:var(--muted);font-size:11px"><?php echo date('H:i', strtotime($ticket['sla_due_at'])); ?></span></span>
                                                </div>
                                                <div class="td-info-row">
                                                    <span class="td-info-key">Time Left</span>
                                                    <span class="td-info-val"><?php echo $sla_display['hours_remaining']; ?>h</span>
                                                </div>
                                                <div class="td-info-row" style="flex-direction:column;gap:6px;align-items:stretch">
                                                    <div style="display:flex;justify-content:space-between">
                                                        <span class="td-info-key">SLA Progress</span>
                                                        <span style="font-size:11px;font-weight:600;color:var(--muted)"><?php echo round($sla_display['percentage'], 1); ?>%</span>
                                                    </div>
                                                    <div class="td-sla-bar-wrap">
                                                        <div class="td-sla-bar bar-<?php echo $sla_display['color']; ?>" style="width:<?php echo $sla_display['percentage']; ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /td-layout -->
                        </div><!-- /td-shell -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>