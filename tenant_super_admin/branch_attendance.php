<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'header.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$tenant_id = $_SESSION['tenant_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../login.php'); exit();
}

// Filters
$month         = isset($_GET['month'])  ? $_GET['month']        : date('Y-m');
$branch_filter = isset($_GET['branch']) ? (int)$_GET['branch']  : 0;
$user_filter   = isset($_GET['user'])   ? (int)$_GET['user']    : 0;
$status_filter = isset($_GET['status']) ? $_GET['status']       : 'all';

$year      = date('Y', strtotime($month . '-01'));
$month_num = date('m', strtotime($month . '-01'));

// Branches
$stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? ORDER BY name ASC");
$stmt->execute([$tenant_id]);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance records
$query  = "SELECT a.*, u.name as user_name, u.email, b.name as branch_name
           FROM attendance a
           JOIN users u ON a.user_id = u.id
           JOIN branches b ON a.branch_id = b.id
           WHERE a.tenant_id = ? AND YEAR(a.date) = ? AND MONTH(a.date) = ?";
$params = [$tenant_id, $year, $month_num];

if ($branch_filter > 0) { $query .= " AND a.branch_id = ?"; $params[] = $branch_filter; }
if ($user_filter   > 0) { $query .= " AND a.user_id = ?";   $params[] = $user_filter; }
if ($status_filter !== 'all') { $query .= " AND a.status = ?"; $params[] = $status_filter; }

$query .= " ORDER BY a.date DESC, b.name ASC, u.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Users for filter
$stmt = $pdo->prepare("SELECT DISTINCT u.id, u.name, b.name as branch_name
    FROM users u JOIN branches b ON u.branch_id = b.id
    WHERE u.tenant_id = ? AND u.fired = 0 ORDER BY b.name ASC, u.name ASC");
$stmt->execute([$tenant_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$present_count = $late_count = $half_day_count = $absent_count = 0;
foreach ($attendance_records as $r) {
    match($r['status']) {
        'present'  => $present_count++,
        'late'     => $late_count++,
        'half_day' => $half_day_count++,
        'absent'   => $absent_count++,
        default    => null
    };
}
$total = count($attendance_records);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:    #2ed8b6; --blue:   #4099ff;
    --grad:    linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    --surface: #f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --amber:#f59e0b; --red:#ef4444; --purple:#8b5cf6;
    --radius:14px;
    --shadow:0 2px 12px rgba(64,153,255,0.08);
}

*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* ── Header ── */
.dash-header{background:var(--grad);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(64,153,255,0.22);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}
.month-pill{background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.3);border-radius:20px;padding:6px 16px;font-size:13px;font-weight:700;position:relative}

/* ── Cards ── */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:var(--grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(64,153,255,0.1);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* ── Stat strip ── */
.stat-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
@media(max-width:900px){.stat-strip{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-strip{grid-template-columns:1fr}}

.stat-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);padding:18px 20px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow)}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.si-green{background:rgba(34,197,94,0.12);color:var(--green)}
.si-amber{background:rgba(245,158,11,0.12);color:var(--amber)}
.si-blue {background:rgba(64,153,255,0.12);color:var(--blue)}
.si-red  {background:rgba(239,68,68,0.12); color:var(--red)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:3px}
.stat-num{font-size:28px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1}
.sn-green{color:var(--green)} .sn-amber{color:var(--amber)} .sn-blue{color:var(--blue)} .sn-red{color:var(--red)}

/* ── Filter bar ── */
.filter-grid{display:grid;grid-template-columns:160px 1fr 1fr 1fr auto;gap:14px;align-items:end}
@media(max-width:900px){.filter-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.filter-grid{grid-template-columns:1fr;}}

.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:var(--blue);background:#fff;box-shadow:0 0 0 3px rgba(64,153,255,.1)}
.filter-btn{display:inline-flex;align-items:center;gap:7px;background:var(--grad);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap}
.filter-btn:hover{opacity:.9;transform:translateY(-1px)}

/* ── Table ── */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody tr{transition:background .15s}
.data-table tbody tr:hover{background:var(--surface)}
.data-table tbody td{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}

.td-date{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-sub);font-weight:600}
.td-time{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main)}
.td-mins{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-sub)}
.td-name{font-weight:700;color:var(--text-main)}
.td-notes{font-size:12px;color:var(--text-sub);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(64,153,255,.08);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Status pills */
.status-pill{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;text-transform:capitalize}
.sp-present {background:rgba(34,197,94,.12); color:#166534}
.sp-late    {background:rgba(245,158,11,.12);color:#92400e}
.sp-half_day{background:rgba(64,153,255,.1); color:#1e40af}
.sp-absent  {background:rgba(239,68,68,.12); color:#991b1b}

/* Action button */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;font-size:13px;color:var(--blue)}
.act-btn:hover{background:rgba(64,153,255,.1);border-color:var(--blue)}

/* Export btn */
.export-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(34,197,94,.1);color:var(--green);border:1.5px solid rgba(34,197,94,.25);border-radius:10px;padding:7px 14px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;margin-left:auto}
.export-btn:hover{background:rgba(34,197,94,.2)}

/* Empty */
.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px;margin:0}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:var(--grad);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}
.modal-body{padding:24px}

/* Overrides */
.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-calendar" style="margin-right:8px;"></i><?= __('branch_attendance_overview') ?></h4>
            <p><?= __('view_attendance_across_all_branches') ?></p>
        </div>
        <div class="month-pill"><i class="feather icon-calendar" style="margin-right:5px;"></i><?= date('F Y', strtotime($month . '-01')) ?></div>
    </div>

    <!-- Summary Stats -->
    <div class="stat-strip">
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="feather icon-check-circle"></i></div>
            <div>
                <div class="stat-label"><?= __('present') ?></div>
                <div class="stat-num sn-green"><?= $present_count ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="feather icon-clock"></i></div>
            <div>
                <div class="stat-label"><?= __('late') ?></div>
                <div class="stat-num sn-amber"><?= $late_count ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="feather icon-minus-circle"></i></div>
            <div>
                <div class="stat-label"><?= __('half_day') ?></div>
                <div class="stat-num sn-blue"><?= $half_day_count ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="feather icon-x-circle"></i></div>
            <div>
                <div class="stat-label"><?= __('absent') ?></div>
                <div class="stat-num sn-red"><?= $absent_count ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-filter"></i></span><?= __('filter') ?></h6>
        </div>
        <div class="dash-card-body">
            <form method="GET">
                <div class="filter-grid">
                    <div>
                        <label class="form-label-custom"><?= __('month') ?></label>
                        <input type="month" class="form-input" name="month" value="<?= htmlspecialchars($month) ?>">
                    </div>
                    <div>
                        <label class="form-label-custom"><?= __('branch') ?></label>
                        <select class="form-input" name="branch">
                            <option value="0"><?= __('all_branches') ?></option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom"><?= __('employee') ?></label>
                        <select class="form-input" name="user">
                            <option value="0"><?= __('all_employees') ?></option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name'].' ('.$u['branch_name'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom"><?= __('status') ?></label>
                        <select class="form-input" name="status">
                            <option value="all"     <?= $status_filter==='all'     ?'selected':'' ?>><?= __('all_statuses') ?></option>
                            <option value="present" <?= $status_filter==='present' ?'selected':'' ?>><?= __('present') ?></option>
                            <option value="late"    <?= $status_filter==='late'    ?'selected':'' ?>><?= __('late') ?></option>
                            <option value="half_day"<?= $status_filter==='half_day'?'selected':'' ?>><?= __('half_day') ?></option>
                            <option value="absent"  <?= $status_filter==='absent'  ?'selected':'' ?>><?= __('absent') ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">&nbsp;</label>
                        <button type="submit" class="filter-btn">
                            <i class="feather icon-search"></i><?= __('filter') ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Records Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span><?= __('attendance_records') ?></h6>
            <span class="count-badge"><?= $total ?> record<?= $total!==1?'s':'' ?></span>
            <button class="export-btn" onclick="exportAttendance()">
                <i class="feather icon-download"></i><?= __('export') ?>
            </button>
        </div>

        <?php if (!empty($attendance_records)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="attendance-tab">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('branch') ?></th>
                        <th><?= __('employee') ?></th>
                        <th><?= __('check_in') ?></th>
                        <th><?= __('check_out') ?></th>
                        <th><?= __('working_minutes') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('notes') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $r): ?>
                    <tr>
                        <td class="td-date"><?= date('M d, Y', strtotime($r['date'])) ?></td>
                        <td><span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($r['branch_name']) ?></span></td>
                        <td class="td-name"><?= htmlspecialchars($r['user_name']) ?></td>
                        <td class="td-time"><?= $r['check_in_time']  ? date('H:i', strtotime($r['check_in_time']))  : '<span style="color:var(--border)">—</span>' ?></td>
                        <td class="td-time"><?= $r['check_out_time'] ? date('H:i', strtotime($r['check_out_time'])) : '<span style="color:var(--border)">—</span>' ?></td>
                        <td class="td-mins"><?= intval($r['working_minutes']) ?> min</td>
                        <td>
                            <?php
                            $s = strtolower($r['status']);
                            $labels = ['present'=>'✓ Present','late'=>'Late','half_day'=>'Half Day','absent'=>'✗ Absent'];
                            ?>
                            <span class="status-pill sp-<?= $s ?>"><?= $labels[$s] ?? ucfirst($s) ?></span>
                        </td>
                        <td class="td-notes" title="<?= htmlspecialchars($r['notes'] ?? '') ?>"><?= htmlspecialchars($r['notes'] ?? '') ?: '<span style="color:var(--border)">—</span>' ?></td>
                        <td>
                            <button class="act-btn" onclick="viewDetails(<?= $r['id'] ?>)" title="<?= __('view_details') ?>"><i class="feather icon-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-calendar"></i>
            <p>No attendance records for this period.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="attendanceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-calendar" style="margin-right:7px;"></i><?= __('attendance_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="attendanceDetailsContent"></div>
        </div>
    </div>
</div>

<script>
function viewDetails(id) {
    fetch(`../api/attendance/get_attendance_details.php?id=${id}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('attendanceDetailsContent').innerHTML = html;
            $('#attendanceDetailsModal').modal('show');
        })
        .catch(() => alert('<?= addslashes(__('error_loading_details')) ?>'));
}

function exportAttendance() {
    const p = new URLSearchParams({
        month:  '<?= addslashes($month) ?>',
        branch: '<?= $branch_filter ?>',
        user:   '<?= $user_filter ?>',
        status: '<?= addslashes($status_filter) ?>'
    });
    window.open(`../api/attendance/export_attendance.php?${p}`, '_blank');
}
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include 'footer.php'; ?>