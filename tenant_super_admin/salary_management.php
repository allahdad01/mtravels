<?php
include 'header.php';

$tenant_id      = $_SESSION['tenant_id'];
$user_id        = $_SESSION['user_id'];
$user_role      = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : ($user_branch_id ?: 'all');
$page            = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset          = ($page - 1) * $results_per_page;

$query = "SELECT sm.*,
                 u.name as employee_name, u.email as employee_email,
                 u.phone as employee_phone, u.hire_date,
                 b.name as branch_name,
                 COUNT(sp.id) as payment_count,
                 COALESCE(SUM(sp.amount), 0) as total_paid
          FROM salary_management sm
          LEFT JOIN users u ON sm.user_id = u.id
          LEFT JOIN branches b ON u.branch_id = b.id
          LEFT JOIN salary_payments sp ON sm.user_id = sp.user_id AND sp.tenant_id = sm.tenant_id
          WHERE sm.tenant_id = ?";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND u.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp]);
}
$query .= " GROUP BY sm.id ORDER BY u.name ASC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM salary_management sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.tenant_id = ?";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND u.branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_salaries = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages    = max(1, ceil($total_salaries / $results_per_page));

$sq = "SELECT COUNT(*) as total_employees, SUM(sm.base_salary) as total_salary_budget,
    AVG(sm.base_salary) as avg_salary,
    COUNT(CASE WHEN sm.status='active' THEN 1 END) as active_employees
FROM salary_management sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.tenant_id = ?";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND u.branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_salaries);
$to   = min($page * $results_per_page, $total_salaries);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444;
    /* Salary identity: indigo â†’ violet */
    --c1:#4f46e5; --c2:#7c3aed;
    --radius:14px; --shadow:0 2px 12px rgba(79,70,229,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(79,70,229,0.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(79,70,229,0.3)}
.stat-card.active {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,0.3)}
.stat-card.budget {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(124,58,237,0.3)}
.stat-card.avg    {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(180,83,9,0.3)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.8;margin-bottom:8px}
.stat-value{font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:800;line-height:1}
.stat-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:30px;opacity:.22}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(79,70,229,.1);color:#4f46e5;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#4f46e5;background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.search-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;transition:opacity .2s}
.search-btn:hover{opacity:.9}
.clear-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s}
.clear-btn:hover{border-color:var(--text-sub);color:var(--text-main);text-decoration:none}

/* Table */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody tr{transition:background .15s}
.data-table tbody tr:hover{background:var(--surface)}
.data-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.td-ctr{text-align:center;font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

/* Employee cell */
.emp-name{font-weight:700;color:var(--text-main);margin-bottom:4px}
.emp-contact{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-sub);margin-bottom:3px}
.emp-contact:last-child{margin-bottom:0}
.emp-contact i{font-size:11px}

/* Salary cell */
.sal-amount{font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;color:#059669;margin-bottom:3px}
.sal-day{font-size:11px;color:var(--text-sub);display:flex;align-items:center;gap:4px}

/* Employment cell */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;margin-bottom:5px}
.sp-active  {background:rgba(34,197,94,.12);color:#166534}
.sp-inactive{background:rgba(107,122,153,.1);color:var(--text-sub)}
.hire-date{font-size:11px;color:var(--text-sub);display:flex;align-items:center;gap:4px;font-family:'JetBrains Mono',monospace}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(79,70,229,.08);color:#4f46e5;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Payment history cell */
.pay-count{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main);margin-bottom:3px;display:flex;align-items:center;gap:5px}
.pay-total{font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(79,70,229,.08);border-color:#4f46e5;color:#4f46e5}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:180px}
.dropdown-item{border-radius:8px;padding:8px 12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;transition:background .15s}
.dropdown-item:hover{background:var(--surface)}

.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px;margin:0}

/* Pagination */
.pag-wrap{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:16px 20px;border-top:1px solid var(--border)}
.pag-info{font-size:12px;color:var(--text-sub)}
.pag-links{display:flex;gap:4px}
.pag-btn{min-width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--text-main);font-size:12px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;padding:0 8px;transition:all .15s}
.pag-btn:hover{border-color:#4f46e5;color:#4f46e5;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modals */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header.pay-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* 2-col strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.ms-val{font-size:24px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
.ms-val.salary{color:#059669}
.ms-val.indigo{color:#4f46e5}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#4f46e5;border-bottom-color:#4f46e5}
.modal-tab:hover{color:#4f46e5}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.indigo{color:#4f46e5}
.ds-val.violet{color:#7c3aed}
.ds-val.green {color:#059669}

.txn-loading{text-align:center;padding:40px}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:#4f46e5;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-dollar-sign" style="margin-right:8px;"></i>Salary Management</h4>
            <p>Manage employee salaries and payment history</p>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Employees</div>
            <div class="stat-value"><?= number_format($summary['total_employees'] ?? 0) ?></div>
            <i class="feather icon-users stat-icon"></i>
        </div>
        <div class="stat-card active">
            <div class="stat-label">Active Employees</div>
            <div class="stat-value"><?= number_format($summary['active_employees'] ?? 0) ?></div>
            <i class="feather icon-user-check stat-icon"></i>
        </div>
        <div class="stat-card budget">
            <div class="stat-label">Salary Budget</div>
            <div class="stat-value">$<?= number_format($summary['total_salary_budget'] ?? 0, 2) ?></div>
            <i class="feather icon-dollar-sign stat-icon"></i>
        </div>
        <div class="stat-card avg">
            <div class="stat-label">Average Salary</div>
            <div class="stat-value">$<?= number_format($summary['avg_salary'] ?? 0, 2) ?></div>
            <i class="feather icon-trending-up stat-icon"></i>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-filter"></i></span>Filter &amp; Search</h6>
        </div>
        <div class="dash-card-body">
            <div class="search-row">
                <div>
                    <label class="form-label-custom">Branch</label>
                    <select class="form-input" id="branchSelect">
                        <option value="all" <?= $selected_branch==='all'?'selected':'' ?>>All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selected_branch==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label-custom">Search Employee</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= urlencode($selected_branch) ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Employee Salary List</h6>
            <span class="count-badge"><?= number_format($total_salaries) ?> employees</span>
        </div>

        <?php if (!empty($salaries)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Employee Details</th>
                        <th>Salary</th>
                        <th>Branch</th>
                        <th>Employment</th>
                        <th>Payment History</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($salaries as $sal):
                    $statusCls = $sal['status'] === 'active' ? 'sp-active' : 'sp-inactive';
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-salary='<?= htmlspecialchars(json_encode($sal)) ?>'>
                                    <i class="feather icon-eye" style="color:#4f46e5"></i>View Details
                                </button>
                                <button class="dropdown-item view-payments" data-user-id="<?= $sal['user_id'] ?>" data-employee-name="<?= htmlspecialchars($sal['employee_name']) ?>">
                                    <i class="feather icon-credit-card" style="color:#7c3aed"></i>View Payments
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="emp-name"><?= htmlspecialchars($sal['employee_name']) ?></div>
                        <?php if (!empty($sal['employee_email'])): ?>
                        <div class="emp-contact"><i class="feather icon-mail"></i><?= htmlspecialchars($sal['employee_email']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($sal['employee_phone'])): ?>
                        <div class="emp-contact"><i class="feather icon-phone"></i><?= htmlspecialchars($sal['employee_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="sal-amount">$<?= number_format($sal['base_salary'], 2) ?></div>
                        <div class="sal-day"><i class="feather icon-calendar"></i>Day <?= $sal['payment_day'] ?> Â· <?= htmlspecialchars($sal['currency']) ?></div>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($sal['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= $statusCls ?>"><?= ucfirst($sal['status']) ?></span>
                        <div class="hire-date"><i class="feather icon-calendar"></i><?= $sal['hire_date'] ? date('d/m/Y', strtotime($sal['hire_date'])) : 'N/A' ?></div>
                    </td>
                    <td>
                        <div class="pay-count"><i class="feather icon-credit-card" style="color:var(--text-sub);font-size:12px;"></i><?= number_format($sal['payment_count']) ?> payments</div>
                        <div class="pay-total">Total: $<?= number_format($sal['total_paid'], 2) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_salaries) ?> employees</div>
            <div class="pag-links">
                <?php $base = '?branch='.urlencode($selected_branch).'&search='.urlencode($search); ?>
                <a href="<?= $base ?>&page=1" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevrons-left"></i></a>
                <a href="<?= $base ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevron-left"></i></a>
                <?php
                $sp2=max(1,$page-2); $ep=min($total_pages,$page+2);
                if($sp2>1){echo '<a href="'.$base.'&page=1" class="pag-btn">1</a>';if($sp2>2)echo '<span class="pag-dots">...</span>';}
                for($i=$sp2;$i<=$ep;$i++) echo '<a href="'.$base.'&page='.$i.'" class="pag-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="pag-dots">...</span>';echo '<a href="'.$base.'&page='.$total_pages.'" class="pag-btn">'.$total_pages.'</a>';}
                ?>
                <a href="<?= $base ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevron-right"></i></a>
                <a href="<?= $base ?>&page=<?= $total_pages ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevrons-right"></i></a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-users"></i>
            <p>No employees found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Salary Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-user" style="margin-right:8px;"></i>Salary Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- 2-col strip: base salary / payment history -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">Base Salary</div>
                    <div class="ms-val salary" id="modal-base-salary">— </div>
                    <div class="ms-sub" id="modal-salary-currency">— </div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Total Paid</div>
                    <div class="ms-val indigo" id="modal-total-paid">— </div>
                    <div class="ms-sub" id="modal-payment-count">— </div>
                </div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('employment',this)"><i class="feather icon-briefcase"></i>Employment</button>
                </div>

                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Employee Information</div>
                            <div class="ds-row"><span class="ds-key">Full Name</span><span class="ds-val" id="emp-name">— </span></div>
                            <div class="ds-row"><span class="ds-key">Email</span><span class="ds-val" id="emp-email" style="font-size:12px;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="emp-phone" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="emp-branch">— </span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Salary Details</div>
                            <div class="ds-row"><span class="ds-key">Base Salary</span><span class="ds-val green" id="sal-amount" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="sal-currency" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Payment Day</span><span class="ds-val indigo" id="sal-pay-day" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="sal-status">— </span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-employment">
                    <div class="detail-section">
                        <div class="ds-title">Employment Information</div>
                        <div class="ds-row"><span class="ds-key">Hire Date</span><span class="ds-val" id="emp-hire-date" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Employment Status</span><span class="ds-val" id="emp-status">— </span></div>
                        <div class="ds-row"><span class="ds-key">Total Payments</span><span class="ds-val indigo" id="emp-pay-count" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Total Amount Paid</span><span class="ds-val green" id="emp-total-paid" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pay-header">
                <h5 class="modal-title"><i class="feather icon-credit-card" style="margin-right:8px;"></i>Payment History —  <span id="employee-name-header"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="paymentsContent">
                    <div class="txn-loading">
                        <div class="spinner"></div>
                        <p style="color:var(--text-sub);font-size:13px;margin:0;">Loading payment history...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.getElementById('branchSelect').addEventListener('change', doSearch);
document.getElementById('searchBtn').addEventListener('click', doSearch);
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') doSearch(); });

function doSearch() {
    const s = document.getElementById('searchInput').value.trim();
    const b = document.getElementById('branchSelect').value;
    window.location.href = '?branch=' + encodeURIComponent(b) + (s ? '&search=' + encodeURIComponent(s) : '');
}

function switchTab(tab, btn) {
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    btn.classList.add('active');
}

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const s = JSON.parse(this.getAttribute('data-salary'));

        document.getElementById('modal-base-salary').textContent  = '$' + parseFloat(s.base_salary || 0).toFixed(2);
        document.getElementById('modal-salary-currency').textContent = s.currency || '— ';
        document.getElementById('modal-total-paid').textContent   = '$' + parseFloat(s.total_paid || 0).toFixed(2);
        document.getElementById('modal-payment-count').textContent = s.payment_count + ' payments made';

        document.getElementById('emp-name').textContent   = s.employee_name  || '— ';
        document.getElementById('emp-email').textContent  = s.employee_email || 'N/A';
        document.getElementById('emp-phone').textContent  = s.employee_phone || 'N/A';
        document.getElementById('emp-branch').textContent = s.branch_name    || 'N/A';

        document.getElementById('sal-amount').textContent   = '$' + parseFloat(s.base_salary || 0).toFixed(2);
        document.getElementById('sal-currency').textContent = s.currency || '— ';
        document.getElementById('sal-pay-day').textContent  = 'Day ' + s.payment_day;
        document.getElementById('sal-status').textContent   = (s.status||'').charAt(0).toUpperCase() + (s.status||'').slice(1);

        document.getElementById('emp-hire-date').textContent  = s.hire_date ? new Date(s.hire_date).toLocaleDateString() : 'N/A';
        document.getElementById('emp-status').textContent     = (s.status||'').charAt(0).toUpperCase() + (s.status||'').slice(1);
        document.getElementById('emp-pay-count').textContent  = s.payment_count + ' payments';
        document.getElementById('emp-total-paid').textContent = '$' + parseFloat(s.total_paid || 0).toFixed(2);

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});

document.querySelectorAll('.view-payments').forEach(btn => {
    btn.addEventListener('click', function() {
        const uid  = this.getAttribute('data-user-id');
        const name = this.getAttribute('data-employee-name');
        document.getElementById('employee-name-header').textContent = name;
        document.getElementById('paymentsContent').innerHTML =
            '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading payment history...</p></div>';
        $('#paymentsModal').modal('show');
        fetch('get_employee_payments.php?user_id=' + uid)
            .then(r => r.text())
            .then(html => { document.getElementById('paymentsContent').innerHTML = html; })
            .catch(err => {
                document.getElementById('paymentsContent').innerHTML =
                    '<div style="padding:20px;color:var(--red);">Error: ' + err.message + '</div>';
            });
    });
});
</script>
