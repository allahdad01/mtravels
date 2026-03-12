<?php
include 'header.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$branch_filter    = isset($_GET['branch']) ? $_GET['branch'] : 'all';
$search           = isset($_GET['search']) ? trim($_GET['search']) : '';
$page             = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset           = ($page - 1) * $results_per_page;

$query = "SELECT va.*, u.name as created_by_name, b.name as branch_name, s.name as supplier_name
FROM visa_applications va
LEFT JOIN users u ON va.created_by = u.id
LEFT JOIN branches b ON va.branch_id = b.id
LEFT JOIN suppliers s ON va.supplier = s.id
WHERE va.tenant_id = ?";

$params = [$tenant_id];
if ($branch_filter !== 'all') { $query .= " AND va.branch_id = ?"; $params[] = $branch_filter; }
if (!empty($search)) {
    $query .= " AND (va.applicant_name LIKE ? OR va.passport_number LIKE ? OR va.country LIKE ? OR va.visa_type LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp,$sp]);
}
$query .= " ORDER BY va.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM visa_applications va WHERE va.tenant_id = ?";
$cp = [$tenant_id];
if ($branch_filter !== 'all') { $cq .= " AND va.branch_id = ?"; $cp[] = $branch_filter; }
if (!empty($search)) {
    $cq .= " AND (va.applicant_name LIKE ? OR va.passport_number LIKE ? OR va.country LIKE ? OR va.visa_type LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_applications = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages        = max(1, ceil($total_applications / $results_per_page));

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_applications);
$to   = min($page * $results_per_page, $total_applications);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:#2ed8b6; --blue:#4099ff;
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --amber:#f59e0b; --red:#ef4444;
    --visa1:#7c3aed; --visa2:#db2777;
    --radius:14px; --shadow:0 2px 12px rgba(64,153,255,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* Header — violet → pink for visa/official documents */
.dash-header{background:linear-gradient(135deg,#7c3aed 0%,#db2777 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(124,58,237,0.22);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#7c3aed,#db2777);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(124,58,237,.1);color:#5b21b6;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 220px;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#7c3aed;background:#fff;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.search-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap;flex-shrink:0}
.search-btn:hover{opacity:.9}
.clear-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s}
.clear-btn:hover{border-color:var(--text-sub);color:var(--text-main);text-decoration:none}

/* Table */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table thead th.r{text-align:right}
.data-table tbody tr{transition:background .15s}
.data-table tbody tr:hover{background:var(--surface)}
.data-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.td-ctr{text-align:center;font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}
.td-r{text-align:right}

/* Applicant cell */
.app-name{font-weight:700;color:var(--text-main);margin-bottom:2px}
.passport-badge{font-family:'JetBrains Mono',monospace;font-size:11px;color:#7c3aed;font-weight:600;background:rgba(124,58,237,.08);border-radius:6px;padding:2px 7px;display:inline-block}

/* Visa details cell */
.visa-country{font-weight:700;font-size:13px;color:var(--text-main);display:flex;align-items:center;gap:6px;margin-bottom:3px}
.visa-type-pill{font-size:10px;font-weight:700;background:rgba(219,39,119,.1);color:#9d174d;border-radius:6px;padding:2px 7px}
.visa-supplier{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:4px}

/* Application info cell */
.app-date{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:5px;margin-bottom:3px;font-family:'JetBrains Mono',monospace}
.app-date i{font-size:11px}
.app-date.issued i{color:var(--green)}
.app-date.applied i{color:#7c3aed}

/* Status pills */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;margin-top:4px}
.sp-issued   {background:rgba(34,197,94,.12);color:#166534}
.sp-applied  {background:rgba(124,58,237,.12);color:#5b21b6}
.sp-rejected {background:rgba(239,68,68,.12);color:#991b1b}
.sp-pending  {background:rgba(245,158,11,.12);color:#92400e}
.sp-other    {background:rgba(107,122,153,.1);color:var(--text-sub)}

.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(64,153,255,.08);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Amount cell */
.amt-sold  {font-family:'JetBrains Mono',monospace;font-weight:800;font-size:14px;color:var(--text-main)}
.amt-profit{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;margin-top:2px}
.profit-pos{color:var(--green)}
.profit-neg{color:var(--red)}

/* Action btn */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(124,58,237,.08);border-color:#7c3aed;color:#7c3aed}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:160px}
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
.pag-btn:hover{border-color:#7c3aed;color:#7c3aed;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#7c3aed,#db2777);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#7c3aed,#db2777);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

.modal-summary{display:grid;grid-template-columns:repeat(3,1fr);background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:5px}
.ms-val{font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1}
.ms-val.violet{color:#7c3aed}
.ms-val.pink  {color:#db2777}
.ms-val.green {color:var(--green)}
.ms-val.red   {color:var(--red)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#7c3aed;border-bottom-color:#7c3aed}
.modal-tab:hover{color:#7c3aed}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.green{color:var(--green)} .ds-val.red{color:var(--red)} .ds-val.violet{color:#7c3aed}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header — violet → pink for official documents/visas -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-globe" style="margin-right:8px;"></i>Visa Applications</h4>
            <p>Manage and track all visa application records</p>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-search"></i></span>Search & Filter</h6>
        </div>
        <div class="dash-card-body">
            <div class="search-row">
                <div>
                    <label class="form-label-custom">Search</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Applicant name, passport, country, or visa type…" value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= $branch_filter ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <label class="form-label-custom">Branch</label>
                    <select class="form-input" id="branchFilter">
                        <option value="all" <?= $branch_filter==='all'?'selected':'' ?>>All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branch_filter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-globe"></i></span>Visa Applications</h6>
            <span class="count-badge"><?= number_format($total_applications) ?> total</span>
        </div>

        <?php if (!empty($applications)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="applicationTable">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:44px;"></th>
                        <th>Applicant</th>
                        <th>Visa Details</th>
                        <th>Application Info</th>
                        <th>Branch</th>
                        <th class="r">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($applications as $app):
                    $profit    = floatval($app['profit'] ?? 0);
                    $profitCls = $profit >= 0 ? 'profit-pos' : 'profit-neg';
                    $profitSign = $profit >= 0 ? '+' : '';
                    $status    = strtolower($app['status'] ?? '');
                    $spClass   = match($status) {
                        'issued'   => 'sp-issued',
                        'applied'  => 'sp-applied',
                        'rejected' => 'sp-rejected',
                        'pending'  => 'sp-pending',
                        default    => 'sp-other'
                    };
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-application='<?= htmlspecialchars(json_encode($app)) ?>'>
                                    <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="app-name"><?= htmlspecialchars(trim($app['title'].' '.$app['applicant_name'])) ?></div>
                        <span class="passport-badge"><i class="feather icon-credit-card" style="font-size:10px;margin-right:3px;"></i><?= htmlspecialchars($app['passport_number']) ?></span>
                    </td>
                    <td>
                        <div class="visa-country">
                            <i class="feather icon-flag" style="color:#7c3aed;font-size:12px;"></i>
                            <?= htmlspecialchars($app['country']) ?>
                            <span class="visa-type-pill"><?= htmlspecialchars($app['visa_type']) ?></span>
                        </div>
                        <div class="visa-supplier"><i class="feather icon-user" style="font-size:11px;"></i><?= htmlspecialchars($app['supplier_name'] ?: 'No Supplier') ?></div>
                    </td>
                    <td>
                        <div class="app-date applied">
                            <i class="feather icon-send"></i>
                            <?= htmlspecialchars($app['applied_date'] ?: '—') ?>
                        </div>
                        <?php if (!empty($app['issued_date'])): ?>
                        <div class="app-date issued">
                            <i class="feather icon-check-circle"></i>
                            <?= htmlspecialchars($app['issued_date']) ?>
                        </div>
                        <?php endif; ?>
                        <span class="status-pill <?= $spClass ?>"><?= htmlspecialchars($app['status'] ?? 'Unknown') ?></span>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($app['branch_name'] ?: 'No Branch') ?></span>
                    </td>
                    <td class="td-r">
                        <div class="amt-sold"><?= htmlspecialchars($app['currency']) ?> <?= number_format($app['sold'], 2) ?></div>
                        <div class="amt-profit <?= $profitCls ?>"><?= $profitSign ?><?= htmlspecialchars($app['currency']) ?> <?= number_format($profit, 2) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>–<?= $to ?> of <?= number_format($total_applications) ?> visa applications</div>
            <div class="pag-links">
                <?php $base = '?branch='.urlencode($branch_filter).'&search='.urlencode($search); ?>
                <a href="<?= $base ?>&page=1" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevrons-left"></i></a>
                <a href="<?= $base ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevron-left"></i></a>
                <?php
                $sp2=max(1,$page-2); $ep=min($total_pages,$page+2);
                if($sp2>1){echo '<a href="'.$base.'&page=1" class="pag-btn">1</a>';if($sp2>2)echo '<span class="pag-dots">…</span>';}
                for($i=$sp2;$i<=$ep;$i++) echo '<a href="'.$base.'&page='.$i.'" class="pag-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="pag-dots">…</span>';echo '<a href="'.$base.'&page='.$total_pages.'" class="pag-btn">'.$total_pages.'</a>';}
                ?>
                <a href="<?= $base ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevron-right"></i></a>
                <a href="<?= $base ?>&page=<?= $total_pages ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevrons-right"></i></a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-globe"></i>
            <p>No visa applications found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-globe" style="margin-right:8px;"></i>Visa Application Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Financial summary strip -->
            <div class="modal-summary">
                <div class="ms-cell"><div class="ms-label">Sold Amount</div><div class="ms-val violet" id="sold-amount">—</div></div>
                <div class="ms-cell"><div class="ms-label">Base Amount</div><div class="ms-val pink" id="base-amount">—</div></div>
                <div class="ms-cell"><div class="ms-label">Profit</div><div class="ms-val green" id="profit">—</div></div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('application',this)"><i class="feather icon-file-text"></i>Application Details</button>
                </div>

                <!-- Summary pane -->
                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Applicant Information</div>
                            <div class="ds-row"><span class="ds-key">Full Name</span><span class="ds-val" id="applicant-name">—</span></div>
                            <div class="ds-row"><span class="ds-key">Passport No.</span><span class="ds-val violet" id="passport-number" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                            <div class="ds-row"><span class="ds-key">Gender</span><span class="ds-val" id="gender">—</span></div>
                            <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="phone" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="branch-name">—</span></div>
                            <div class="ds-row"><span class="ds-key">Created By</span><span class="ds-val" id="created-by">—</span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Visa Information</div>
                            <div class="ds-row"><span class="ds-key">Country</span><span class="ds-val" id="country">—</span></div>
                            <div class="ds-row"><span class="ds-key">Visa Type</span><span class="ds-val" id="visa-type">—</span></div>
                            <div class="ds-row"><span class="ds-key">Applied Date</span><span class="ds-val" id="applied-date" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                            <div class="ds-row"><span class="ds-key">Issued Date</span><span class="ds-val green" id="issued-date" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                            <div class="ds-row"><span class="ds-key">Supplier</span><span class="ds-val" id="supplier-name">—</span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="application-status">—</span></div>
                        </div>
                    </div>
                </div>

                <!-- Application Details pane -->
                <div class="modal-pane" id="pane-application">
                    <div class="detail-section">
                        <div class="ds-title">Processing Information</div>
                        <div class="ds-row"><span class="ds-key">Receive Date</span><span class="ds-val" id="receive-date" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                        <div class="ds-row"><span class="ds-key">Sold To</span><span class="ds-val" id="sold-to">—</span></div>
                        <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="currency" style="font-family:'JetBrains Mono',monospace;">—</span></div>
                    </div>
                    <div class="detail-section">
                        <div class="ds-title">Remarks</div>
                        <p id="remarks" style="font-size:14px;color:var(--text-main);margin:0;line-height:1.7;">—</p>
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
document.getElementById('searchBtn').addEventListener('click', doSearch);
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') doSearch(); });
document.getElementById('branchFilter').addEventListener('change', doSearch);

function doSearch() {
    const s = document.getElementById('searchInput').value.trim();
    const b = document.getElementById('branchFilter').value;
    window.location.href = '?branch=' + b + (s ? '&search=' + encodeURIComponent(s) : '');
}

function switchTab(tab, btn) {
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    btn.classList.add('active');
}

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const d    = JSON.parse(this.getAttribute('data-application'));
        const curr = d.currency || '';
        const sold = parseFloat(d.sold   || 0);
        const base = parseFloat(d.base   || 0);
        const prof = parseFloat(d.profit || 0);
        const profStr = (prof >= 0 ? '+' : '') + curr + ' ' + prof.toFixed(2);

        document.getElementById('sold-amount').textContent = curr + ' ' + sold.toFixed(2);
        document.getElementById('base-amount').textContent = curr + ' ' + base.toFixed(2);
        document.getElementById('profit').textContent      = profStr;
        document.getElementById('profit').className        = 'ms-val ' + (prof >= 0 ? 'green' : 'red');

        document.getElementById('applicant-name').textContent  = [(d.title||''), (d.applicant_name||'')].filter(Boolean).join(' ');
        document.getElementById('passport-number').textContent = d.passport_number || '—';
        document.getElementById('gender').textContent          = d.gender || 'N/A';
        document.getElementById('phone').textContent           = d.phone  || 'N/A';
        document.getElementById('branch-name').textContent     = d.branch_name || 'No Branch';
        document.getElementById('created-by').textContent      = d.created_by_name || 'N/A';

        document.getElementById('country').textContent            = d.country      || 'N/A';
        document.getElementById('visa-type').textContent          = d.visa_type    || 'N/A';
        document.getElementById('applied-date').textContent       = d.applied_date || 'N/A';
        document.getElementById('issued-date').textContent        = d.issued_date  || 'Not yet issued';
        document.getElementById('supplier-name').textContent      = d.supplier_name || 'N/A';
        document.getElementById('application-status').textContent = d.status        || 'N/A';

        document.getElementById('receive-date').textContent = d.receive_date || 'N/A';
        document.getElementById('sold-to').textContent      = d.sold_to      || 'N/A';
        document.getElementById('currency').textContent     = curr           || 'N/A';
        document.getElementById('remarks').textContent      = d.remarks      || 'No remarks.';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});
</script>