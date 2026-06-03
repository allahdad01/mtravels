<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';
require_once '../includes/SSLCertificateMonitor.php';

$sslMonitor = new SSLCertificateMonitor($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_domain'])) {
        $domain = trim($_POST['domain'] ?? '');
        $port = (int)($_POST['port'] ?? 443);
        $description = trim($_POST['description'] ?? '');
        if (empty($domain)) {
            $error = 'Domain is required';
        } else {
            $result = $sslMonitor->addDomain($domain, $port, $description);
            if ($result['success'] ?? false) {
                $message = "Domain '{$domain}' added to monitoring successfully!";
            } else {
                $error = $result['error'] ?? 'Failed to add domain';
            }
        }
    } elseif (isset($_POST['remove_domain'])) {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        if ($sslMonitor->removeDomain($domainId)) {
            $message = 'Domain removed from monitoring successfully!';
        } else {
            $error = 'Failed to remove domain';
        }
    } elseif (isset($_POST['check_certificates'])) {
        $results = $sslMonitor->checkAllCertificates();
        $checkedCount = is_array($results) ? count($results) : 0;
        $message = "Checked {$checkedCount} SSL certificate(s) successfully!";
    } elseif (isset($_POST['send_alerts'])) {
        $alertsSent = $sslMonitor->sendExpiryAlerts() ?? 0;
        $message = "Sent {$alertsSent} SSL expiry alert(s)!";
    }
}

$certificates = $sslMonitor->getMonitoredDomains() ?? [];
$attentionNeeded = $sslMonitor->getCertificatesNeedingAttention() ?? [];
$alertThresholds = $sslMonitor->getAlertThresholds() ?? ['critical' => 7, 'warning' => 30, 'info' => 60];

include '../includes/header_super_admin.php';
?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
.page-header.card {
    background: var(--grad) !important; color: #fff; border: none !important;
    margin-bottom: 20px; padding: 22px 28px !important;
    box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
    position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }

.sa-table-wrap {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    margin-bottom: 24px;
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-toolbar-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }

.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-btn-ghost {
    background: var(--surface); color: var(--text);
    border: 1px solid var(--border);
}
.sa-btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
.sa-btn-danger {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; border-radius: 6px;
    cursor: pointer; background: transparent; color: var(--muted); transition: all .15s;
}
.sa-btn-danger:hover { background: #fee2e2; color: #ef4444; }
.sa-btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff;
}
.sa-btn-warning:hover { opacity: .85; }

/* ─── THRESHOLD GRID ─── */
.threshold-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
@media (max-width: 768px) { .threshold-grid { grid-template-columns: repeat(2, 1fr); } }
.threshold-item {
    text-align: center; padding: 16px; border-radius: 10px;
    background: var(--surface); border: 1px solid var(--border);
}
.threshold-item.critical { border-left: 3px solid #ef4444; }
.threshold-item.warning { border-left: 3px solid #f59e0b; }
.threshold-item.info { border-left: 3px solid #3b82f6; }
.threshold-item.ok { border-left: 3px solid #10b981; }
.threshold-number { font-size: 1.5rem; font-weight: 700; }
.threshold-label { font-size: .75rem; color: var(--muted); text-transform: uppercase; margin-top: 4px; }

/* ─── BADGES ─── */
.badge-cert {
    display: inline-flex; padding: 3px 10px; border-radius: 20px;
    font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .02em;
}
.badge-expired, .badge-critical { background: #fee2e2; color: #991b1b; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-info { background: #dbeafe; color: #1e40af; }
.badge-ok, .badge-valid { background: #d1fae5; color: #065f46; }
.badge-unknown, .badge-not-checked { background: #f3f4f6; color: #6b7280; }
.badge-invalid { background: #fee2e2; color: #991b1b; }

.sa-alert {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem;
}
.sa-alert.success { background: #d1fae5; color: #065f46; }
.sa-alert.error { background: #fee2e2; color: #991b1b; }

.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.sa-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: var(--surface); border-radius: var(--radius);
    width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.sa-modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sa-modal-hdr h3 { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.sa-modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--muted); padding: 4px; line-height: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-ftr {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
}
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .85rem; }
.sa-form-control {
    width: 100%; padding: 10px 14px; border: 1px solid var(--border);
    border-radius: 8px; font-size: .85rem; font-family: inherit;
    background: var(--surface); color: var(--text);
}
.sa-form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.15); }
.sa-form-hint { font-size: .75rem; color: var(--muted); margin-top: 4px; display: block; }
.sa-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text);
}
.sa-btn-secondary:hover { background: var(--bg); }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                SSL Certificate Monitoring
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Monitor SSL certificate expiry dates and receive alerts</p>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="button" class="sa-btn" onclick="openModal('addDomainModal')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add Domain
                            </button>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <?php if (!empty($message)): ?>
                        <div class="sa-alert success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                        <div class="sa-alert error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Alert Thresholds -->
                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    Alert Thresholds
                                </h3>
                            </div>
                            <div style="padding:20px">
                                <div class="threshold-grid">
                                    <div class="threshold-item critical">
                                        <div class="threshold-number" style="color:#ef4444"><?= $alertThresholds['critical'] ?? 7 ?></div>
                                        <div class="threshold-label">Critical Days</div>
                                    </div>
                                    <div class="threshold-item warning">
                                        <div class="threshold-number" style="color:#f59e0b"><?= $alertThresholds['warning'] ?? 30 ?></div>
                                        <div class="threshold-label">Warning Days</div>
                                    </div>
                                    <div class="threshold-item info">
                                        <div class="threshold-number" style="color:#3b82f6"><?= $alertThresholds['info'] ?? 60 ?></div>
                                        <div class="threshold-label">Info Days</div>
                                    </div>
                                    <div class="threshold-item ok">
                                        <div class="threshold-number" style="color:#10b981">OK</div>
                                        <div class="threshold-label">Above Info</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attention Needed -->
                        <?php if (!empty($attentionNeeded)): ?>
                        <div class="sa-table-wrap" style="border-left:3px solid #ef4444">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    Attention Required (<?= count($attentionNeeded) ?>)
                                </h3>
                            </div>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Status</th>
                                        <th>Days Left</th>
                                        <th>Expires</th>
                                        <th>Last Checked</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attentionNeeded as $cert): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($cert['domain'] ?? 'Unknown') ?></strong>
                                            <?php if (($cert['port'] ?? 443) != 443): ?>
                                            <span style="color:var(--muted);font-size:.8rem">(Port: <?= $cert['port'] ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $alertLevel = $cert['alert_level'] ?? 'unknown'; ?>
                                            <span class="badge-cert badge-<?= $alertLevel ?>"><?= ucfirst($alertLevel) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($cert['is_expired'] ?? false): ?>
                                            <span style="color:#ef4444;font-weight:600">EXPIRED</span>
                                            <?php elseif (isset($cert['days_until_expiry'])): ?>
                                            <span style="font-weight:600"><?= $cert['days_until_expiry'] ?> days</span>
                                            <?php else: ?>
                                            <span style="color:var(--muted)">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $cert['valid_to'] ? date('M d, Y', strtotime($cert['valid_to'])) : 'Unknown' ?></td>
                                        <td><?= $cert['last_checked'] ? date('M d, Y H:i', strtotime($cert['last_checked'])) : 'Never' ?></td>
                                        <td>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="domain_id" value="<?= $cert['id'] ?? 0 ?>">
                                                <button type="submit" name="remove_domain" class="sa-btn-danger" title="Remove"
                                                        onclick="return confirm('Remove <?= htmlspecialchars($cert['domain'] ?? '') ?>?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- All Certificates -->
                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    Monitored Domains (<?= count($certificates) ?>)
                                </h3>
                                <div class="sa-toolbar-actions">
                                    <form method="post" style="display:inline">
                                        <button type="submit" name="check_certificates" class="sa-btn sa-btn-ghost">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                            Check All
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline">
                                        <button type="submit" name="send_alerts" class="sa-btn sa-btn-warning">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                            Send Alerts
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if (empty($certificates)): ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px">
                                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <p>No domains configured</p>
                                <p style="font-size:.85rem;margin-top:4px">Add your first domain to start monitoring SSL certificates.</p>
                                <button type="button" class="sa-btn" style="margin-top:16px" onclick="openModal('addDomainModal')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Domain
                                </button>
                            </div>
                            <?php else: ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Domain</th>
                                        <th>Status</th>
                                        <th>Issuer</th>
                                        <th>Valid Until</th>
                                        <th>Days Left</th>
                                        <th>Last Checked</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certificates as $cert): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($cert['domain'] ?? 'Unknown') ?></strong>
                                            <?php if (($cert['port'] ?? 443) != 443): ?>
                                            <span style="color:var(--muted);font-size:.8rem">(Port: <?= $cert['port'] ?>)</span>
                                            <?php endif; ?>
                                            <?php if (!empty($cert['description'])): ?>
                                            <br><span style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($cert['description']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cert['last_checked'] === null): ?>
                                            <span class="badge-cert badge-not-checked">Not Checked</span>
                                            <?php elseif (!($cert['is_valid'] ?? true)): ?>
                                            <span class="badge-cert badge-invalid">Invalid</span>
                                            <?php else: ?>
                                            <?php $alertLevel = $cert['alert_level'] ?? 'unknown'; ?>
                                            <span class="badge-cert badge-<?= $alertLevel ?>"><?= ucfirst($alertLevel) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($cert['issuer'] ?? 'Unknown') ?></td>
                                        <td>
                                            <?php if (!empty($cert['valid_to'])): ?>
                                            <?= date('M d, Y', strtotime($cert['valid_to'])) ?>
                                            <br><span style="color:var(--muted);font-size:.75rem"><?= date('H:i', strtotime($cert['valid_to'])) ?></span>
                                            <?php else: ?>
                                            <span style="color:var(--muted)">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cert['is_expired'] ?? false): ?>
                                            <span style="color:#ef4444;font-weight:600">EXPIRED</span>
                                            <?php elseif (isset($cert['days_until_expiry'])): ?>
                                            <span style="font-weight:600"><?= $cert['days_until_expiry'] ?> days</span>
                                            <?php else: ?>
                                            <span style="color:var(--muted)">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($cert['last_checked'])): ?>
                                            <?= date('M d, Y H:i', strtotime($cert['last_checked'])) ?>
                                            <?php else: ?>
                                            <span style="color:var(--muted)">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="domain_id" value="<?= $cert['id'] ?? 0 ?>">
                                                <button type="submit" name="remove_domain" class="sa-btn-danger" title="Remove"
                                                        onclick="return confirm('Remove <?= htmlspecialchars($cert['domain'] ?? '') ?>?')">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Domain Modal -->
    <div class="sa-modal-overlay" id="addDomainModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Domain to Monitor
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('addDomainModal')">&times;</button>
            </div>
            <form method="post">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label>Domain Name <span style="color:#ef4444">*</span></label>
                        <input type="text" class="sa-form-control" name="domain" placeholder="example.com" required>
                        <span class="sa-form-hint">Enter the domain name without https:// (e.g., almoqadas.com)</span>
                    </div>
                    <div class="sa-form-group">
                        <label>Port</label>
                        <input type="number" class="sa-form-control" name="port" value="443" min="1" max="65535">
                        <span class="sa-form-hint">Default SSL port is 443. Change only if using a different port.</span>
                    </div>
                    <div class="sa-form-group">
                        <label>Description (Optional)</label>
                        <input type="text" class="sa-form-control" name="description" placeholder="Main website domain">
                        <span class="sa-form-hint">Add a description to help identify this domain.</span>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('addDomainModal')">Cancel</button>
                    <button type="submit" name="add_domain" class="sa-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Domain
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>
</body>
</html>
