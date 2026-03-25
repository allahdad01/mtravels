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

$query = "SELECT hb.*, u.name as created_by_name, b.name as branch_name, s.name as supplier_name
FROM hotel_bookings hb
LEFT JOIN users u ON hb.created_by = u.id AND u.tenant_id = hb.tenant_id
LEFT JOIN branches b ON hb.branch_id = b.id
LEFT JOIN suppliers s ON hb.supplier_id = s.id AND s.tenant_id = hb.tenant_id
WHERE hb.tenant_id = ?";

$params = [$tenant_id];
if ($branch_filter !== 'all') { $query .= " AND hb.branch_id = ?"; $params[] = $branch_filter; }
if (!empty($search)) {
    $query .= " AND (hb.first_name LIKE ? OR hb.last_name LIKE ? OR hb.order_id LIKE ? OR hb.accommodation_details LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp,$sp]);
}
$query .= " ORDER BY hb.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM hotel_bookings hb WHERE hb.tenant_id = ?";
$cp = [$tenant_id];
if ($branch_filter !== 'all') { $cq .= " AND hb.branch_id = ?"; $cp[] = $branch_filter; }
if (!empty($search)) {
    $cq .= " AND (hb.first_name LIKE ? OR hb.last_name LIKE ? OR hb.order_id LIKE ? OR hb.accommodation_details LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_bookings = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages    = max(1, ceil($total_bookings / $results_per_page));

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_bookings);
$to   = min($page * $results_per_page, $total_bookings);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:#2ed8b6; --blue:#4099ff;
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --amber:#f59e0b; --red:#ef4444;
    --hotel1:#10b981; --hotel2:#059669;
    --radius:14px; --shadow:0 2px 12px rgba(64,153,255,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* Header â€” emerald green for hotels */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(16,185,129,0.22);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(16,185,129,.1);color:#065f46;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 220px;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#10b981;background:#fff;box-shadow:0 0 0 3px rgba(16,185,129,.1)}
.search-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap;flex-shrink:0}
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

/* Guest cell */
.guest-name{font-weight:700;color:var(--text-main);margin-bottom:2px}
.order-id{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--blue);font-weight:600;background:rgba(64,153,255,.08);border-radius:6px;padding:2px 7px;display:inline-block}

/* Hotel cell */
.hotel-name{font-weight:700;color:var(--text-main);margin-bottom:2px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hotel-supplier{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:4px;margin-top:3px}

/* Booking info cell */
.book-dates{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:5px;margin-bottom:5px}
.book-dates i{color:#10b981;font-size:11px}
.date-range{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:var(--text-main)}

/* Status pills */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700}
.sp-active   {background:rgba(16,185,129,.12);color:#065f46}
.sp-cancelled{background:rgba(239,68,68,.12);color:#991b1b}
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
.act-btn:hover{background:rgba(16,185,129,.08);border-color:#10b981;color:#10b981}
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
.pag-btn:hover{border-color:#10b981;color:#10b981;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

.modal-summary{display:grid;grid-template-columns:repeat(3,1fr);background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:5px}
.ms-val{font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1}
.ms-val.blue {color:var(--blue)}
.ms-val.teal {color:#0284c7}
.ms-val.green{color:var(--green)}
.ms-val.red  {color:var(--red)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#10b981;border-bottom-color:#10b981}
.modal-tab:hover{color:#10b981}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.green{color:var(--green)} .ds-val.red{color:var(--red)}

/* Accommodation block */
.accommodation-block{background:var(--surface);border-radius:12px;padding:18px;font-size:14px;line-height:1.8;color:var(--text-main);margin-bottom:14px}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header â€” emerald â†’ sky blue for hotel bookings -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-home" style="margin-right:8px;"></i>Hotel Bookings</h4>
            <p>View and manage all hotel booking records</p>
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
                        <input type="text" id="searchInput" class="form-input" placeholder="Guest name, order ID, or accommodationâ€¦" value="<?= htmlspecialchars($search) ?>">
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
            <h6><span class="ico"><i class="feather icon-home"></i></span>Hotel Bookings</h6>
            <span class="count-badge"><?= number_format($total_bookings) ?> total</span>
        </div>

        <?php if (!empty($bookings)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table" id="bookingTable">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:44px;"></th>
                        <th>Guest</th>
                        <th>Hotel / Accommodation</th>
                        <th>Check-in â†’ Check-out</th>
                        <th>Branch</th>
                        <th class="r">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($bookings as $bk):
                    $profit     = floatval($bk['profit'] ?? 0);
                    $profitCls  = $profit >= 0 ? 'profit-pos' : 'profit-neg';
                    $profitSign = $profit >= 0 ? '+' : '';
                    $status     = strtolower($bk['status'] ?? '');
                    $spClass    = match($status) {
                        'active'    => 'sp-active',
                        'cancelled' => 'sp-cancelled',
                        'pending'   => 'sp-pending',
                        default     => 'sp-other'
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
                                <button class="dropdown-item view-details" data-booking='<?= htmlspecialchars(json_encode($bk)) ?>'>
                                    <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="guest-name"><?= htmlspecialchars(trim($bk['title'].' '.$bk['first_name'].' '.$bk['last_name'])) ?></div>
                        <span class="order-id"><?= htmlspecialchars($bk['order_id']) ?></span>
                    </td>
                    <td>
                        <div class="hotel-name"><?= htmlspecialchars($bk['accommodation_details'] ?: 'N/A') ?></div>
                        <div class="hotel-supplier"><i class="feather icon-user" style="font-size:11px;"></i><?= htmlspecialchars($bk['supplier_name'] ?: 'No Supplier') ?></div>
                    </td>
                    <td>
                        <div class="book-dates">
                            <i class="feather icon-log-in"></i>
                            <span class="date-range"><?= htmlspecialchars($bk['check_in_date'] ?: 'â€”') ?></span>
                        </div>
                        <div class="book-dates">
                            <i class="feather icon-log-out" style="color:var(--text-sub)!important"></i>
                            <span class="date-range"><?= htmlspecialchars($bk['check_out_date'] ?: 'â€”') ?></span>
                        </div>
                        <span class="status-pill <?= $spClass ?>"><?= ucfirst(htmlspecialchars($bk['status'] ?? 'Unknown')) ?></span>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($bk['branch_name'] ?: 'No Branch') ?></span>
                    </td>
                    <td class="td-r">
                        <div class="amt-sold"><?= htmlspecialchars($bk['currency']) ?> <?= number_format($bk['sold_amount'], 2) ?></div>
                        <div class="amt-profit <?= $profitCls ?>"><?= $profitSign ?><?= htmlspecialchars($bk['currency']) ?> <?= number_format($profit, 2) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_bookings) ?> hotel bookings</div>
            <div class="pag-links">
                <?php $base = '?branch='.urlencode($branch_filter).'&search='.urlencode($search); ?>
                <a href="<?= $base ?>&page=1" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevrons-left"></i></a>
                <a href="<?= $base ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevron-left"></i></a>
                <?php
                $sp2=max(1,$page-2); $ep=min($total_pages,$page+2);
                if($sp2>1){echo '<a href="'.$base.'&page=1" class="pag-btn">1</a>';if($sp2>2)echo '<span class="pag-dots">â€¦</span>';}
                for($i=$sp2;$i<=$ep;$i++) echo '<a href="'.$base.'&page='.$i.'" class="pag-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="pag-dots">â€¦</span>';echo '<a href="'.$base.'&page='.$total_pages.'" class="pag-btn">'.$total_pages.'</a>';}
                ?>
                <a href="<?= $base ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevron-right"></i></a>
                <a href="<?= $base ?>&page=<?= $total_pages ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevrons-right"></i></a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-home"></i>
            <p>No hotel bookings found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
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
                <h5 class="modal-title"><i class="feather icon-home" style="margin-right:8px;"></i>Hotel Booking Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Financial summary strip -->
            <div class="modal-summary">
                <div class="ms-cell"><div class="ms-label">Sold Amount</div><div class="ms-val blue" id="sold-amount">â€”</div></div>
                <div class="ms-cell"><div class="ms-label">Base Amount</div><div class="ms-val teal" id="base-amount">â€”</div></div>
                <div class="ms-cell"><div class="ms-label">Profit</div><div class="ms-val green" id="profit">â€”</div></div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('accommodation',this)"><i class="feather icon-home"></i>Accommodation</button>
                </div>

                <!-- Summary pane -->
                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Guest Information</div>
                            <div class="ds-row"><span class="ds-key">Guest Name</span><span class="ds-val" id="guest-name">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Order ID</span><span class="ds-val" id="order-id" style="font-family:'JetBrains Mono',monospace;color:var(--blue);">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Gender</span><span class="ds-val" id="gender">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="phone" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="branch-name">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Created By</span><span class="ds-val" id="created-by">â€”</span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Booking Information</div>
                            <div class="ds-row"><span class="ds-key">Check-in</span><span class="ds-val" id="check-in-date" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Check-out</span><span class="ds-val" id="check-out-date" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Issue Date</span><span class="ds-val" id="issue-date" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Supplier</span><span class="ds-val" id="supplier-name">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Sold To</span><span class="ds-val" id="sold-to">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="booking-status">â€”</span></div>
                        </div>
                    </div>
                </div>

                <!-- Accommodation pane -->
                <div class="modal-pane" id="pane-accommodation">
                    <div class="detail-section">
                        <div class="ds-title">Accommodation Details</div>
                        <div id="accommodation-details" style="font-size:14px;color:var(--text-main);line-height:1.8;">â€”</div>
                    </div>
                    <div class="detail-section">
                        <div class="ds-title">Remarks</div>
                        <p id="remarks" style="font-size:14px;color:var(--text-main);margin:0;line-height:1.7;">â€”</p>
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
        const d    = JSON.parse(this.getAttribute('data-booking'));
        const curr = d.currency || '';
        const sold = parseFloat(d.sold_amount  || 0);
        const base = parseFloat(d.base_amount  || 0);
        const prof = parseFloat(d.profit       || 0);
        const profStr = (prof >= 0 ? '+' : '') + curr + ' ' + prof.toFixed(2);

        document.getElementById('sold-amount').textContent = curr + ' ' + sold.toFixed(2);
        document.getElementById('base-amount').textContent = curr + ' ' + base.toFixed(2);
        document.getElementById('profit').textContent      = profStr;

        const profEl = document.getElementById('profit');
        profEl.className = 'ms-val ' + (prof >= 0 ? 'green' : 'red');

        document.getElementById('guest-name').textContent   = [(d.title||''), (d.first_name||''), (d.last_name||'')].filter(Boolean).join(' ');
        document.getElementById('order-id').textContent     = d.order_id || 'â€”';
        document.getElementById('gender').textContent       = d.gender || 'N/A';
        document.getElementById('phone').textContent        = d.contact_no || 'N/A';
        document.getElementById('branch-name').textContent  = d.branch_name || 'No Branch';
        document.getElementById('created-by').textContent   = d.created_by_name || 'N/A';

        document.getElementById('check-in-date').textContent  = d.check_in_date  || 'N/A';
        document.getElementById('check-out-date').textContent = d.check_out_date || 'N/A';
        document.getElementById('issue-date').textContent     = d.issue_date     || 'N/A';
        document.getElementById('supplier-name').textContent  = d.supplier_name  || 'N/A';
        document.getElementById('sold-to').textContent        = d.sold_to        || 'N/A';
        document.getElementById('booking-status').textContent = d.status         || 'N/A';

        document.getElementById('accommodation-details').innerHTML = d.accommodation_details || 'No accommodation details available.';
        document.getElementById('remarks').textContent = d.remarks || 'No remarks.';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});
</script>
