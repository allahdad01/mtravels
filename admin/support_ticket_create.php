<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'] ?? 0;
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
$ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);

$categories = $ticketManager->getCategories();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($title) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields';
    } elseif (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $error = 'Invalid priority selected';
    } else {
        $screenshot_path = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['size'] > 0) {
            $uploader = new SecureFileUpload(5 * 1024 * 1024, '../uploads/');
            $result = $uploader->upload('screenshot', 'support_tickets');
            if ($result['success']) {
                $screenshot_path = 'uploads/support_tickets/' . $result['data']['filename'];
            } else {
                $error = "File upload failed: " . $result['error'];
            }
        }

        if (empty($error)) {
            try {
                $result = $ticketManager->createTicket([
                    'tenant_id'           => $tenant_id,
                    'branch_id'           => $branch_id,
                    'created_by_user_id'  => $user_id,
                    'created_by_role'     => $user_role,
                    'category_id'         => intval($category_id),
                    'title'               => $title,
                    'description'         => $description,
                    'priority'            => $priority,
                    'screenshot_path'     => $screenshot_path,
                ]);
                if ($result['success']) {
                    $success = true;
                    $ticket_id = $result['ticket_id'];
                } else {
                    $error = $result['error'] ?? 'Failed to create ticket';
                }
            } catch (Exception $e) {
                $error = 'Error creating ticket: ' . $e->getMessage();
                error_log("Support Ticket Creation Error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = "Create Support Ticket";
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
        --accent-3:  #2db899;
        --critical:  #e8533a;
        --high:      #f5a623;
        --medium:    #4a7cf7;
        --radius-sm: 6px;
        --radius-md: 12px;
        --shadow-md: 0 4px 16px rgba(0,0,0,.09);
    }

    body, .pcoded-main-container { background: var(--surface) !important; font-family: 'DM Sans', sans-serif; color: var(--ink); }

    .tc-shell { max-width: 1100px; margin: 0 auto; padding: 32px 28px 60px; }

    /* ── TOP BAR ── */
    .tc-topbar { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 12px; flex-wrap: wrap; }
    .tc-eyebrow { font-size: 11px; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
    .tc-title { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; line-height: 1; color: var(--ink); margin: 0; }
    .st-btn-back { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--card-bg); color: var(--muted); font-size: 13px; font-weight: 500; text-decoration: none; transition: border-color .15s, color .15s; }
    .st-btn-back:hover { border-color: var(--ink); color: var(--ink); }

    /* ── ALERTS ── */
    .tc-alert { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13.5px; }
    .tc-alert-success { background: #d6f5ec; color: #1a7a5b; border: 1px solid #b8eddc; }
    .tc-alert-danger  { background: #fde8e4; color: #c0392b; border: 1px solid #f8c9c2; }
    .tc-alert-icon { font-size: 18px; margin-top: 1px; flex-shrink: 0; }
    .tc-alert-body { flex: 1; }
    .tc-alert-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px; margin-bottom: 4px; }
    .tc-alert a { color: inherit; font-weight: 600; }
    .tc-alert-close { background: none; border: none; cursor: pointer; font-size: 16px; opacity: .45; color: inherit; padding: 0; line-height: 1; margin-left: auto; }
    .tc-alert-close:hover { opacity: 1; }

    /* ── LAYOUT ── */
    .tc-layout { display: grid; grid-template-columns: 1fr 280px; gap: 20px; align-items: start; }
    @media (max-width: 860px) { .tc-layout { grid-template-columns: 1fr; } }

    /* ── CARD ── */
    .tc-card { background: var(--card-bg); border: 1.5px solid var(--border); border-radius: var(--radius-md); overflow: hidden; margin-bottom: 16px; }
    .tc-card-header { padding: 16px 22px; border-bottom: 1.5px solid var(--border); display: flex; align-items: center; gap: 10px; }
    .tc-card-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; margin: 0; }
    .tc-card-body { padding: 24px; }

    /* ── FORM ── */
    .tc-field { margin-bottom: 22px; }
    .tc-field:last-child { margin-bottom: 0; }
    .tc-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); margin-bottom: 7px; }
    .tc-required { color: var(--critical); margin-left: 2px; }
    .tc-hint { font-size: 11.5px; color: var(--muted); margin-top: 5px; }

    .tc-input, .tc-textarea, .tc-select {
        width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
        font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--ink);
        background: #fff; box-sizing: border-box; transition: border-color .15s, box-shadow .15s;
    }
    .tc-input:focus, .tc-textarea:focus, .tc-select:focus {
        outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(13,15,18,.07);
    }
    .tc-input::placeholder, .tc-textarea::placeholder { color: var(--muted); }
    .tc-textarea { resize: vertical; min-height: 130px; line-height: 1.6; }
    .tc-select {
        appearance: none; -webkit-appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238a8880' fill='none' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px;
    }

    /* Priority selector */
    .tc-priority-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .tc-priority-opt { display: none; }
    .tc-priority-label {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 5px; padding: 12px 8px; border-radius: var(--radius-sm); border: 1.5px solid var(--border);
        cursor: pointer; text-align: center; transition: all .15s; background: var(--surface);
    }
    .tc-priority-label:hover { border-color: var(--ink); background: #fff; }
    .tc-priority-opt:checked + .tc-priority-label { border-width: 2px; background: #fff; }
    .tc-priority-dot { width: 10px; height: 10px; border-radius: 50%; }
    .tc-priority-name { font-size: 12px; font-weight: 600; color: var(--ink); }
    .tc-priority-sla { font-size: 10px; color: var(--muted); }

    input[value="low"]:checked   + .tc-priority-label { border-color: #b0b0b0; }
    input[value="medium"]:checked+ .tc-priority-label { border-color: var(--medium); }
    input[value="high"]:checked  + .tc-priority-label { border-color: var(--high); }
    input[value="critical"]:checked + .tc-priority-label { border-color: var(--critical); }

    .dot-low      { background: #b0b0b0; }
    .dot-medium   { background: var(--medium); }
    .dot-high     { background: var(--high); }
    .dot-critical { background: var(--critical); }

    /* File input */
    .tc-file-zone {
        display: flex; align-items: center; gap: 12px; padding: 13px 14px;
        border: 1.5px dashed var(--border); border-radius: var(--radius-sm);
        background: var(--surface); cursor: pointer; transition: border-color .15s;
    }
    .tc-file-zone:hover { border-color: var(--ink); background: #fff; }
    .tc-file-zone input { display: none; }
    .tc-file-icon { font-size: 20px; opacity: .4; }
    .tc-file-text { font-size: 13px; color: var(--muted); }
    .tc-file-text strong { color: var(--ink); font-weight: 600; }

    /* Form actions */
    .tc-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 4px; }
    .tc-btn-reset {
        padding: 10px 22px; border-radius: var(--radius-sm); border: 1.5px solid var(--border);
        background: var(--surface); font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500;
        color: var(--muted); cursor: pointer; transition: all .15s;
    }
    .tc-btn-reset:hover { border-color: var(--ink); color: var(--ink); background: #fff; }
    .tc-btn-submit {
        display: inline-flex; align-items: center; gap: 7px; padding: 10px 26px;
        background: var(--ink); color: #fff; border: none; border-radius: var(--radius-sm);
        font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: .03em;
        cursor: pointer; transition: background .15s, transform .15s, box-shadow .15s;
    }
    .tc-btn-submit:hover { background: #1f2329; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }

    /* ── SIDEBAR ── */
    .tc-tip-section { margin-bottom: 20px; }
    .tc-tip-section:last-child { margin-bottom: 0; }
    .tc-tip-heading { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
    .tc-tip-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
    .tc-tip-item { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; line-height: 1.45; }
    .tc-tip-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
    .tc-tip-label { font-weight: 600; color: var(--ink); }
    .tc-tip-desc  { color: var(--muted); font-size: 12px; }

    .tc-note { background: var(--surface); border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 12.5px; color: var(--muted); line-height: 1.5; display: flex; gap: 8px; align-items: flex-start; }
    .tc-note i { opacity: .5; flex-shrink: 0; margin-top: 1px; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
                        <div class="tc-shell">

                            <!-- Top Bar -->
                            <div class="tc-topbar">
                                <div>
                                    <p class="tc-eyebrow">Admin &rsaquo; Help Desk &rsaquo; New Ticket</p>
                                    <h1 class="tc-title">Create Support Ticket</h1>
                                </div>
                                <a href="support_tickets.php" class="st-btn-back">
                                    <i class="feather icon-arrow-left"></i> Back to Tickets
                                </a>
                            </div>

                            <!-- Alerts -->
                            <?php if ($success): ?>
                                <div class="tc-alert tc-alert-success">
                                    <i class="feather icon-check-circle tc-alert-icon"></i>
                                    <div class="tc-alert-body">
                                        <div class="tc-alert-title">Ticket Created!</div>
                                        Your support ticket was submitted successfully.
                                        <a href="support_ticket_detail.php?id=<?php echo $ticket_id; ?>">View ticket &rarr;</a>
                                    </div>
                                    <button class="tc-alert-close" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($error)): ?>
                                <div class="tc-alert tc-alert-danger">
                                    <i class="feather icon-alert-circle tc-alert-icon"></i>
                                    <div class="tc-alert-body"><?php echo htmlspecialchars($error); ?></div>
                                    <button class="tc-alert-close" onclick="this.parentElement.remove()">&times;</button>
                                </div>
                            <?php endif; ?>

                            <!-- Layout -->
                            <div class="tc-layout">

                                <!-- FORM COLUMN -->
                                <div>
                                    <div class="tc-card">
                                        <div class="tc-card-header">
                                            <i class="feather icon-edit-3" style="opacity:.45"></i>
                                            <h5 class="tc-card-title">New Ticket</h5>
                                        </div>
                                        <div class="tc-card-body">
                                            <form method="POST" enctype="multipart/form-data">

                                                <div class="tc-field">
                                                    <label class="tc-label" for="title">Title <span class="tc-required">*</span></label>
                                                    <input type="text" class="tc-input" id="title" name="title"
                                                        placeholder="Brief description of the issue"
                                                        maxlength="255" required
                                                        value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                                    <p class="tc-hint">Maximum 255 characters</p>
                                                </div>

                                                <div class="tc-field">
                                                    <label class="tc-label" for="description">Description <span class="tc-required">*</span></label>
                                                    <textarea class="tc-textarea" id="description" name="description"
                                                        rows="6" placeholder="Provide detailed information about the issue…"
                                                        required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                                    <p class="tc-hint">Include steps to reproduce, error messages, and expected vs actual behaviour.</p>
                                                </div>

                                                <div class="tc-field">
                                                    <label class="tc-label" for="category">Category <span class="tc-required">*</span></label>
                                                    <select class="tc-select" id="category" name="category_id" required>
                                                        <option value="">Select a category…</option>
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo $cat['id']; ?>"
                                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($cat['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="tc-field">
                                                    <label class="tc-label">Priority <span class="tc-required">*</span></label>
                                                    <div class="tc-priority-grid">
                                                        <?php
                                                        $priorities = [
                                                            'low'      => ['label' => 'Low',      'sla' => '24 h SLA', 'dot' => 'dot-low'],
                                                            'medium'   => ['label' => 'Medium',   'sla' => '12 h SLA', 'dot' => 'dot-medium'],
                                                            'high'     => ['label' => 'High',     'sla' => '4 h SLA',  'dot' => 'dot-high'],
                                                            'critical' => ['label' => 'Critical', 'sla' => '1 h SLA',  'dot' => 'dot-critical'],
                                                        ];
                                                        $selected_priority = $_POST['priority'] ?? 'medium';
                                                        foreach ($priorities as $val => $p):
                                                        ?>
                                                        <div>
                                                            <input type="radio" class="tc-priority-opt" name="priority"
                                                                id="p_<?php echo $val; ?>" value="<?php echo $val; ?>"
                                                                <?php echo $selected_priority === $val ? 'checked' : ''; ?>>
                                                            <label class="tc-priority-label" for="p_<?php echo $val; ?>">
                                                                <span class="tc-priority-dot <?php echo $p['dot']; ?>"></span>
                                                                <span class="tc-priority-name"><?php echo $p['label']; ?></span>
                                                                <span class="tc-priority-sla"><?php echo $p['sla']; ?></span>
                                                            </label>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>

                                                <div class="tc-field">
                                                    <label class="tc-label">Screenshot <span style="font-weight:400;letter-spacing:0;text-transform:none;font-size:11px">(optional)</span></label>
                                                    <label class="tc-file-zone" for="screenshot">
                                                        <input type="file" id="screenshot" name="screenshot" accept="image/*"
                                                            onchange="document.getElementById('file-name').textContent = this.files[0]?.name || 'No file chosen'">
                                                        <i class="feather icon-image tc-file-icon"></i>
                                                        <div class="tc-file-text">
                                                            <strong>Click to upload</strong> or drag and drop<br>
                                                            <span id="file-name">JPG, PNG or GIF · max 5 MB</span>
                                                        </div>
                                                    </label>
                                                </div>

                                                <div class="tc-actions">
                                                    <button type="reset" class="tc-btn-reset">Clear</button>
                                                    <button type="submit" class="tc-btn-submit">
                                                        <i class="feather icon-send"></i> Submit Ticket
                                                    </button>
                                                </div>

                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- SIDEBAR COLUMN -->
                                <div>
                                    <div class="tc-card">
                                        <div class="tc-card-header">
                                            <i class="feather icon-info" style="opacity:.45"></i>
                                            <h5 class="tc-card-title">Help &amp; Tips</h5>
                                        </div>
                                        <div class="tc-card-body">

                                            <div class="tc-tip-section">
                                                <div class="tc-tip-heading">Priority Guidelines</div>
                                                <ul class="tc-tip-list">
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-critical"></span>
                                                        <div><span class="tc-tip-label">Critical</span><br><span class="tc-tip-desc">System down or data loss risk</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-high"></span>
                                                        <div><span class="tc-tip-label">High</span><br><span class="tc-tip-desc">Major functionality broken</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-medium"></span>
                                                        <div><span class="tc-tip-label">Medium</span><br><span class="tc-tip-desc">Non-critical issues</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-low"></span>
                                                        <div><span class="tc-tip-label">Low</span><br><span class="tc-tip-desc">Enhancement requests</span></div>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="tc-tip-section">
                                                <div class="tc-tip-heading">Response Times</div>
                                                <ul class="tc-tip-list">
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-critical"></span>
                                                        <div><span class="tc-tip-label">Critical</span> &mdash; <span class="tc-tip-desc">1 hour</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-high"></span>
                                                        <div><span class="tc-tip-label">High</span> &mdash; <span class="tc-tip-desc">4 hours</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-medium"></span>
                                                        <div><span class="tc-tip-label">Medium</span> &mdash; <span class="tc-tip-desc">12 hours</span></div>
                                                    </li>
                                                    <li class="tc-tip-item">
                                                        <span class="tc-tip-dot dot-low"></span>
                                                        <div><span class="tc-tip-label">Low</span> &mdash; <span class="tc-tip-desc">24 hours</span></div>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="tc-note">
                                                <i class="feather icon-zap"></i>
                                                Include screenshots and steps to reproduce to help us resolve your issue faster.
                                            </div>

                                        </div>
                                    </div>
                                </div>

                            </div><!-- /tc-layout -->
                        </div><!-- /tc-shell -->
              
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>