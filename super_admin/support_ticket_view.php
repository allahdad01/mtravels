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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --orange:   #f97316;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 24px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* Card colors */
.sa-card:nth-child(1) { border-left-color: #6366f1; }
.sa-card:nth-child(2) { border-left-color: #10b981; }
.sa-card:nth-child(3) { border-left-color: #f59e0b; }

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.sa-btn-warning {
  background: linear-gradient(135deg, var(--amber), #fbbf24); color: white;
}

/* ─── FORM STYLES ────────────────────────────────────────── */
.form-group { margin-bottom: 16px; }

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
    background: var(--surface);
}

/* ─── BADGES ─────────────────────────────────────────────── */
.badge-custom {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-priority.urgent { background: rgba(239,68,68,.15); color: var(--red); }
.badge-priority.high { background: rgba(249,115,22,.15); color: var(--orange); }
.badge-priority.medium { background: rgba(245,158,11,.15); color: var(--amber); }
.badge-priority.low { background: rgba(16,185,129,.15); color: var(--green); }
.badge-status.open { background: rgba(239,68,68,.15); color: var(--red); }
.badge-status.in_progress { background: rgba(245,158,11,.15); color: var(--amber); }
.badge-status.resolved { background: rgba(16,185,129,.15); color: var(--green); }
.badge-status.closed { background: rgba(107,114,128,.15); color: var(--muted); }

/* ─── INFO GRID ─────────────────────────────────────────── */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
@media (max-width: 576px) {
    .info-grid { grid-template-columns: 1fr; }
}
.info-item label {
    font-weight: 600;
    color: var(--muted);
    font-size: 0.7rem;
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
}
.info-item p {
    margin: 0;
    color: var(--text);
    font-size: 0.9rem;
}

/* ─── DESCRIPTION ─────────────────────────────────────────── */
.description-box {
    background: var(--surface2);
    padding: 20px;
    border-radius: 10px;
    margin-top: 16px;
    line-height: 1.7;
    color: var(--text);
}

/* ─── REPLY ─────────────────────────────────────────────── */
.reply-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--accent);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
}
.reply-item:last-child { margin-bottom: 0; }
.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 8px;
}
.reply-author {
    font-weight: 600;
    color: var(--accent);
}
.reply-time {
    font-size: 0.75rem;
    color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
}
.reply-text {
    color: var(--text);
    line-height: 1.6;
}

/* ─── ALERT ─────────────────────────────────────────────── */
.alert-box {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box.success {
    background: rgba(16,185,129,.1);
    border: 1px solid rgba(16,185,129,.3);
    color: var(--green);
}
.alert-box.danger {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: var(--red);
}

/* ─── SCROLLBAR ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 10px; }

/* ─── PCODED LAYOUT INTEGRATION ──────────────────────────── */
body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Ticket: <?= htmlspecialchars($ticket['ticket_number'] ?? '') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="support_tickets_manage.php">Support Tickets</a></li>
                            <li class="breadcrumb-item"><a href="#!"><?= htmlspecialchars($ticket['ticket_number'] ?? '') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-box <?= $alert_type ?>">
                <i class="feather <?= $alert_type === 'success' ? 'icon-check-circle' : 'icon-alert-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="sa-wrap">
            <div class="sa-content">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
                    <div>
                        <!-- Ticket Details Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-file-text" style="margin-right:8px"></i><?= htmlspecialchars($ticket['title'] ?? 'No Title') ?></h3>
                                <div>
                                    <span class="badge-custom badge-priority <?= $ticket['priority'] ?? 'medium' ?>"><?= ucfirst($ticket['priority'] ?? 'medium') ?></span>
                                    <span class="badge-custom badge-status <?= $ticket['status'] ?? 'open' ?>"><?= str_replace('_', ' ', ucfirst($ticket['status'] ?? 'open')) ?></span>
                                </div>
                            </div>
                            <div class="sa-card-body">
                                <h6 style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:16px">Ticket Information</h6>
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Category</label>
                                        <p><?= htmlspecialchars($ticket['category_name'] ?? 'N/A') ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Submitted By</label>
                                        <p><?= htmlspecialchars($ticket['created_by_name'] ?? 'Unknown') ?></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Email</label>
                                        <p><a href="mailto:<?= htmlspecialchars($ticket['created_by_email'] ?? '') ?>" style="color:var(--accent)"><?= htmlspecialchars($ticket['created_by_email'] ?? 'N/A') ?></a></p>
                                    </div>
                                    <div class="info-item">
                                        <label>Created</label>
                                        <p><?= date('M d, Y H:i', strtotime($ticket['created_at'] ?? date('Y-m-d H:i:s'))) ?></p>
                                    </div>
                                </div>

                                <h6 style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-top:24px;margin-bottom:12px">Description</h6>
                                <div class="description-box">
                                    <?= nl2br(htmlspecialchars($ticket['description'] ?? 'No description')) ?>
                                </div>

                                <?php if (!empty($ticket['screenshot_path'])): ?>
                                <div style="margin-top:16px">
                                    <h6 style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:10px">Attached Screenshot</h6>
                                    <a href="../<?= htmlspecialchars($ticket['screenshot_path']) ?>" target="_blank">
                                        <img src="../<?= htmlspecialchars($ticket['screenshot_path']) ?>"
                                             alt="Ticket Screenshot"
                                             style="max-width:100%;max-height:320px;border-radius:10px;border:1px solid var(--border);cursor:zoom-in">
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Replies Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-message-circle" style="margin-right:8px"></i>Replies</h3>
                            </div>
                            <div class="sa-card-body">
                                <?php if (empty($replies)): ?>
                                    <p style="color:var(--muted);text-align:center;padding:24px">No replies yet.</p>
                                <?php else: ?>
                                    <?php foreach ($replies as $reply): ?>
                                    <div class="reply-item">
                                        <div class="reply-header">
                                            <div>
                                                <span class="reply-author"><?= htmlspecialchars($reply['replied_by_name'] ?? 'Unknown') ?></span>
                                            <?php if (!empty($reply['is_internal_note']) && $reply['is_internal_note']): ?>
                                                <span class="badge-custom" style="background:rgba(139,92,246,.15);color:var(--purple)">Internal</span>
                                            <?php endif; ?>
                                            </div>
                                            <span class="reply-time"><?= date('M d, Y H:i', strtotime($reply['created_at'] ?? date('Y-m-d H:i:s'))) ?></span>
                                        </div>
                                        <div class="reply-text">
                                            <?= nl2br(htmlspecialchars($reply['reply_text'] ?? '')) ?>
                                        </div>
                                        <?php if (!empty($reply['screenshot_path'])): ?>
                                        <div style="margin-top:10px">
                                            <a href="../<?= htmlspecialchars($reply['screenshot_path']) ?>" target="_blank">
                                                <img src="../<?= htmlspecialchars($reply['screenshot_path']) ?>"
                                                     alt="Reply Attachment"
                                                     style="max-width:240px;max-height:160px;border-radius:8px;border:1px solid var(--border);cursor:zoom-in">
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border)">
                                    <h6 style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;margin-bottom:12px">Add Reply</h6>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="add_reply">
                                        <div class="form-group">
                                            <textarea class="form-control" name="reply_text" rows="3" placeholder="Type your response..." required></textarea>
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-primary">
                                            <i class="feather icon-send"></i> Send Reply
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <!-- Status Management Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-settings" style="margin-right:8px"></i>Status Management</h3>
                            </div>
                            <div class="sa-card-body">
                                <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)">
                                    <div class="form-label">Update Status</div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <div class="form-group">
                                            <select name="status" class="form-control" required>
                                                <option value="open" <?= ($ticket['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                                                <option value="in_progress" <?= ($ticket['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="resolved" <?= ($ticket['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                                <option value="closed" <?= ($ticket['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-primary" style="width:100%;justify-content:center">
                                            <i class="feather icon-save"></i> Update Status
                                        </button>
                                    </form>
                                </div>

                                <div>
                                    <div class="form-label">Update Priority</div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_priority">
                                        <div class="form-group">
                                            <select name="priority" class="form-control" required>
                                                <option value="low" <?= ($ticket['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                                                <option value="medium" <?= ($ticket['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                                                <option value="high" <?= ($ticket['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                                                <option value="urgent" <?= ($ticket['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="sa-btn sa-btn-warning" style="width:100%;justify-content:center">
                                            <i class="feather icon-alert-triangle"></i> Update Priority
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Back Button -->
                        <a href="support_tickets_manage.php" class="sa-btn sa-btn-ghost" style="width:100%;justify-content:center">
                            <i class="feather icon-arrow-left"></i> Back to Tickets
                        </a>
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
