<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';

$ticketManager = new SupportTicketManager($pdo);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? $_SESSION['email'];

// Get ticket ID
$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: support_tickets_manage.php');
    exit();
}

$ticket = $ticketManager->getTicketDetails($ticket_id);

if (!$ticket) {
    header('Location: support_tickets_manage.php');
    exit();
}

$replies = $ticketManager->getTicketReplies($ticket_id, true);
$message = '';
$alert_type = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = trim($_POST['status'] ?? '');
        
        if (in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            $result = $ticketManager->updateTicketStatus($ticket_id, $status, $user_id);
            if ($result['success'] ?? false) {
                $message = 'Ticket status updated successfully';
                $alert_type = 'success';
                $ticket = $ticketManager->getTicketDetails($ticket_id);
            } else {
                $message = 'Failed to update ticket status';
                $alert_type = 'danger';
            }
        }
    } 
    elseif ($action === 'update_priority') {
        $priority = trim($_POST['priority'] ?? '');
        
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            if ($ticketManager->updateTicketPriority($ticket_id, $priority)) {
                $message = 'Ticket priority updated successfully';
                $alert_type = 'success';
                $ticket = $ticketManager->getTicketDetails($ticket_id);
            } else {
                $message = 'Failed to update priority';
                $alert_type = 'danger';
            }
        }
    }
    elseif ($action === 'add_reply') {
        $reply_text = trim($_POST['reply_text'] ?? '');
        
        if (empty($reply_text)) {
            $message = 'Reply cannot be empty';
            $alert_type = 'danger';
        } else {
            $result = $ticketManager->addReply($ticket_id, $user_id, $reply_text);
            
            if ($result['success'] ?? false) {
                $message = 'Reply added successfully';
                $alert_type = 'success';
                $replies = $ticketManager->getTicketReplies($ticket_id, true);
            } else {
                $message = 'Failed to add reply: ' . ($result['error'] ?? '');
                $alert_type = 'danger';
            }
        }
    }
}

include '../includes/header_super_admin.php';
?>

<style>
:root {
    --primary: #4099ff; --primary-dark: #2673cc; --primary-glow: rgba(64,153,255,0.2);
    --secondary: #2ed8b6; --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --bg: #f0f8ff; --surface: #ffffff; --surface2: #f3f8ff;
    --text: #1a2332; --muted: #6b7280; --border: #e2e8f0;
    --radius: 10px; --green: #10b981; --red: #ef4444; --amber: #f59e0b; --blue: #3b82f6; --purple: #8b5cf6;
}
.stv-page-header {
    background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%);
    color: #fff; border: none; margin-bottom: 24px;
    padding: 22px 28px; box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px; position: relative; overflow: hidden;
}
.stv-page-header::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.stv-page-header .stv-row { display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2; }
.stv-page-header h5 { color: #fff; margin: 0; font-weight: 700; font-size: 1.15rem; display: flex; align-items: center; gap: 8px; }
.stv-breadcrumb { display: flex; align-items: center; gap: 8px; margin-top: 6px; font-size: 0.78rem; color: rgba(255,255,255,0.75); position: relative; z-index: 2; }
.stv-breadcrumb a { color: rgba(255,255,255,0.85); text-decoration: none; }
.stv-breadcrumb a:hover { text-decoration: underline; }
.stv-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 992px) { .stv-layout { grid-template-columns: 1fr; } }
.stv-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 24px;
}
.stv-card-hdr {
    padding: 14px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: var(--surface2);
}
.stv-card-hdr h3 { font-size: 0.9rem; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px; margin: 0; }
.stv-card-body { padding: 20px; }
.stv-section-title {
    font-size: 0.7rem; color: var(--muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
    margin: 0 0 14px 0; padding-bottom: 10px; border-bottom: 2px solid var(--border);
}
.stv-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
@media (max-width: 576px) { .stv-info-grid { grid-template-columns: 1fr; } }
.stv-info-item { display: flex; flex-direction: column; gap: 3px; }
.stv-info-item label { font-size: 0.65rem; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
.stv-info-item p { margin: 0; font-size: 0.85rem; color: var(--text); }
.stv-info-item a { color: var(--primary); text-decoration: none; }
.stv-info-item a:hover { text-decoration: underline; }
.stv-desc-box {
    background: var(--surface2); padding: 16px; border-radius: 8px;
    line-height: 1.7; font-size: 0.85rem; color: var(--text);
    margin-top: 14px;
}
.stv-img-link { display: inline-block; margin-top: 12px; }
.stv-img-link img { max-width: 100%; max-height: 320px; border-radius: 8px; border: 1px solid var(--border); cursor: zoom-in; }
.stv-reply-item {
    background: var(--surface); border: 1px solid var(--border);
    border-left: 3px solid var(--primary); border-radius: 8px;
    padding: 14px; margin-bottom: 12px;
}
.stv-reply-item:last-child { margin-bottom: 0; }
.stv-reply-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px; }
.stv-reply-author { font-weight: 600; font-size: 0.85rem; color: var(--primary); }
.stv-reply-time { font-size: 0.72rem; color: var(--muted); }
.stv-reply-text { font-size: 0.85rem; color: var(--text); line-height: 1.6; }
.stv-reply-img { margin-top: 10px; }
.stv-reply-img img { max-width: 240px; max-height: 160px; border-radius: 6px; border: 1px solid var(--border); cursor: zoom-in; }
.stv-divider { margin: 20px 0 0; padding-top: 20px; border-top: 1px solid var(--border); }
.stv-no-replies { color: var(--muted); text-align: center; padding: 24px; font-size: 0.85rem; }
.sa-alert {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px; border-radius: var(--radius); border: 1px solid var(--border);
    margin-bottom: 20px; font-size: 0.85rem;
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
.sa-alert-icon { flex-shrink: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 18px; height: 18px; }
.sa-alert-success { background: #d1fae5; border-color: var(--green); color: #065f46; }
.sa-alert-success .sa-alert-icon svg { color: var(--green); }
.sa-alert-danger { background: #fee2e2; border-color: var(--red); color: #7f1d1d; }
.sa-alert-danger .sa-alert-icon svg { color: var(--red); }
.sa-form-group { display: flex; flex-direction: column; margin-bottom: 14px; }
.sa-form-label { font-size: 0.82rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }
.sa-form-input { padding: 9px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--text); font-size: 0.85rem; transition: all 0.2s; font-family: inherit; width: 100%; box-sizing: border-box; }
.sa-form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.sa-form-textarea { resize: vertical; min-height: 70px; }
.sa-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 0.82rem; font-weight: 600; transition: all 0.2s;
    text-decoration: none; white-space: nowrap;
}
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px var(--primary-glow); }
.sa-btn-ghost { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.sa-btn-ghost:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-btn-warning { background: linear-gradient(135deg, var(--amber), #fbbf24); color: white; }
.sa-btn-block { width: 100%; justify-content: center; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 4px;
}
.pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
.pill-blue { background: rgba(56,189,248,0.12); color: var(--blue); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
.pill-purple { background: rgba(139,92,246,0.12); color: var(--purple); }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">

        <div class="stv-page-header">
            <div class="stv-row">
                <div>
                    <h5>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        Ticket: <?= htmlspecialchars($ticket['ticket_number'] ?? '') ?>
                    </h5>
                    <div class="stv-breadcrumb">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <a href="dashboard.php">Dashboard</a>
                        <span>/</span>
                        <a href="support_tickets_manage.php">Support Tickets</a>
                        <span>/</span>
                        <span style="color:rgba(255,255,255,0.6)"><?= htmlspecialchars($ticket['ticket_number'] ?? '') ?></span>
                    </div>
                </div>
                <a href="support_tickets_manage.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);backdrop-filter:blur(4px);position:relative;z-index:2;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Back to Tickets
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="sa-alert sa-alert-<?= $alert_type ?>">
            <div class="sa-alert-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><?= $alert_type === 'success' ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>' ?></svg>
            </div>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="stv-layout">

            <!-- Left Column -->
            <div>

                <!-- Ticket Details -->
                <div class="stv-card">
                    <div class="stv-card-hdr">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <?= htmlspecialchars($ticket['title'] ?? 'No Title') ?>
                        </h3>
                        <div style="display:flex;gap:6px">
                            <span class="pill <?= ($ticket['priority'] ?? 'medium') === 'urgent' ? 'pill-red' : (($ticket['priority'] ?? 'medium') === 'high' ? 'pill-amber' : (($ticket['priority'] ?? 'medium') === 'medium' ? 'pill-blue' : 'pill-green')) ?>"><?= ucfirst($ticket['priority'] ?? 'medium') ?></span>
                            <span class="pill <?= ($ticket['status'] ?? 'open') === 'open' ? 'pill-blue' : (($ticket['status'] ?? '') === 'in_progress' ? 'pill-amber' : (($ticket['status'] ?? '') === 'resolved' ? 'pill-green' : 'pill-gray')) ?>"><?= str_replace('_', ' ', ucfirst($ticket['status'] ?? 'open')) ?></span>
                        </div>
                    </div>
                    <div class="stv-card-body">
                        <h6 class="stv-section-title">Ticket Information</h6>
                        <div class="stv-info-grid">
                            <div class="stv-info-item">
                                <label>Category</label>
                                <p><?= htmlspecialchars($ticket['category_name'] ?? 'N/A') ?></p>
                            </div>
                            <div class="stv-info-item">
                                <label>Submitted By</label>
                                <p><?= htmlspecialchars($ticket['created_by_name'] ?? 'Unknown') ?></p>
                            </div>
                            <div class="stv-info-item">
                                <label>Email</label>
                                <p><a href="mailto:<?= htmlspecialchars($ticket['created_by_email'] ?? '') ?>"><?= htmlspecialchars($ticket['created_by_email'] ?? 'N/A') ?></a></p>
                            </div>
                            <div class="stv-info-item">
                                <label>Created</label>
                                <p><?= date('M d, Y H:i', strtotime($ticket['created_at'] ?? date('Y-m-d H:i:s'))) ?></p>
                            </div>
                        </div>

                        <h6 class="stv-section-title" style="margin-top:20px">Description</h6>
                        <div class="stv-desc-box"><?= nl2br(htmlspecialchars($ticket['description'] ?? 'No description')) ?></div>

                        <?php if (!empty($ticket['screenshot_path'])): ?>
                        <h6 class="stv-section-title" style="margin-top:16px">Attached Screenshot</h6>
                        <a class="stv-img-link" href="../<?= htmlspecialchars($ticket['screenshot_path']) ?>" target="_blank">
                            <img src="../<?= htmlspecialchars($ticket['screenshot_path']) ?>" alt="Ticket Screenshot">
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Replies -->
                <div class="stv-card">
                    <div class="stv-card-hdr">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Replies
                        </h3>
                    </div>
                    <div class="stv-card-body">
                        <?php if (empty($replies)): ?>
                        <div class="stv-no-replies">No replies yet.</div>
                        <?php else: ?>
                        <?php foreach ($replies as $reply): ?>
                        <div class="stv-reply-item">
                            <div class="stv-reply-hdr">
                                <div>
                                    <span class="stv-reply-author"><?= htmlspecialchars($reply['replied_by_name'] ?? 'Unknown') ?></span>
                                    <?php if (!empty($reply['is_internal_note']) && $reply['is_internal_note']): ?>
                                    <span class="pill pill-purple" style="margin-left:6px">Internal</span>
                                    <?php endif; ?>
                                </div>
                                <span class="stv-reply-time"><?= date('M d, Y H:i', strtotime($reply['created_at'] ?? date('Y-m-d H:i:s'))) ?></span>
                            </div>
                            <div class="stv-reply-text"><?= nl2br(htmlspecialchars($reply['reply_text'] ?? '')) ?></div>
                            <?php if (!empty($reply['screenshot_path'])): ?>
                            <div class="stv-reply-img">
                                <a href="../<?= htmlspecialchars($reply['screenshot_path']) ?>" target="_blank">
                                    <img src="../<?= htmlspecialchars($reply['screenshot_path']) ?>" alt="Reply Attachment">
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="stv-divider">
                            <h6 class="stv-section-title">Add Reply</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_reply">
                                <div class="sa-form-group">
                                    <textarea class="sa-form-input sa-form-textarea" name="reply_text" rows="3" placeholder="Type your response..." required></textarea>
                                </div>
                                <button type="submit" class="sa-btn sa-btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <div class="stv-card">
                    <div class="stv-card-hdr">
                        <h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            Status Management
                        </h3>
                    </div>
                    <div class="stv-card-body">
                        <div style="margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border)">
                            <div class="sa-form-label">Update Status</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="sa-form-group">
                                    <select name="status" class="sa-form-input" required>
                                        <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="closed" <?= ($ticket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                <button type="submit" class="sa-btn sa-btn-primary sa-btn-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Update Status
                                </button>
                            </form>
                        </div>
                        <div>
                            <div class="sa-form-label">Update Priority</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_priority">
                                <div class="sa-form-group">
                                    <select name="priority" class="sa-form-input" required>
                                        <option value="low" <?= ($ticket['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                                        <option value="medium" <?= ($ticket['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                                        <option value="high" <?= ($ticket['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                                        <option value="urgent" <?= ($ticket['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                    </select>
                                </div>
                                <button type="submit" class="sa-btn sa-btn-warning sa-btn-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Update Priority
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
