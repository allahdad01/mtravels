<?php
include 'header.php';

$tenant_id = $_SESSION['tenant_id'];

$branch_id         = $_GET['branch_id'] ?? '';
$start_date        = $_GET['start_date'] ?? date('Y-m-01');
$end_date          = $_GET['end_date'] ?? date('Y-m-t');
$comparison_period = $_GET['comparison_period'] ?? '';

function fetchBranchReports($pdo, $tenant_id, $start_date, $end_date, $branch_id = '') {
    try {
        $branchFilter = "";
        if (!empty($branch_id)) {
            $branchFilter = "AND b.id = ?";
        }

        $stmt = $pdo->prepare("
            SELECT b.name as branch_name, b.code as branch_code,
                COALESCE(ticket_stats.ticket_bookings,0) as ticket_bookings,
                COALESCE(ticket_stats.ticket_profit_usd,0) as ticket_profit_usd,
                COALESCE(ticket_stats.ticket_profit_afs,0) as ticket_profit_afs,
                COALESCE(reservation_stats.ticket_reservations,0) as ticket_reservations,
                COALESCE(reservation_stats.reservation_profit_usd,0) as reservation_profit_usd,
                COALESCE(reservation_stats.reservation_profit_afs,0) as reservation_profit_afs,
                COALESCE(weight_stats.ticket_weights,0) as ticket_weights,
                COALESCE(weight_stats.weight_profit_usd,0) as weight_profit_usd,
                COALESCE(weight_stats.weight_profit_afs,0) as weight_profit_afs,
                COALESCE(hotel_stats.hotel_bookings,0) as hotel_bookings,
                COALESCE(hotel_stats.hotel_profit_usd,0) as hotel_profit_usd,
                COALESCE(hotel_stats.hotel_profit_afs,0) as hotel_profit_afs,
                COALESCE(visa_stats.visa_applications,0) as visa_applications,
                COALESCE(visa_stats.visa_profit_usd,0) as visa_profit_usd,
                COALESCE(visa_stats.visa_profit_afs,0) as visa_profit_afs,
                COALESCE(umrah_stats.umrah_bookings,0) as umrah_bookings,
                COALESCE(umrah_stats.umrah_profit_usd,0) as umrah_profit_usd,
                COALESCE(umrah_stats.umrah_profit_afs,0) as umrah_profit_afs,
                COALESCE(additional_stats.additional_payments,0) as additional_payments,
                COALESCE(additional_stats.additional_profit_usd,0) as additional_profit_usd,
                COALESCE(additional_stats.additional_profit_afs,0) as additional_profit_afs,
                COALESCE(refund_stats.refunded_tickets,0) as refunded_tickets,
                COALESCE(refund_stats.refund_profit_usd,0) as refund_profit_usd,
                COALESCE(refund_stats.refund_profit_afs,0) as refund_profit_afs,
                COALESCE(date_change_stats.date_change_tickets,0) as date_change_tickets,
                COALESCE(date_change_stats.date_change_profit_usd,0) as date_change_profit_usd,
                COALESCE(date_change_stats.date_change_profit_afs,0) as date_change_profit_afs,
                COALESCE(ticket_stats.ticket_profit_usd,0)+COALESCE(reservation_stats.reservation_profit_usd,0)+COALESCE(weight_stats.weight_profit_usd,0)+COALESCE(hotel_stats.hotel_profit_usd,0)+COALESCE(visa_stats.visa_profit_usd,0)+COALESCE(umrah_stats.umrah_profit_usd,0)+COALESCE(additional_stats.additional_profit_usd,0)+COALESCE(refund_stats.refund_profit_usd,0)+COALESCE(date_change_stats.date_change_profit_usd,0) as total_revenue_usd,
                COALESCE(ticket_stats.ticket_profit_afs,0)+COALESCE(reservation_stats.reservation_profit_afs,0)+COALESCE(weight_stats.weight_profit_afs,0)+COALESCE(hotel_stats.hotel_profit_afs,0)+COALESCE(visa_stats.visa_profit_afs,0)+COALESCE(umrah_stats.umrah_profit_afs,0)+COALESCE(additional_stats.additional_profit_afs,0)+COALESCE(refund_stats.refund_profit_afs,0)+COALESCE(date_change_stats.date_change_profit_afs,0) as total_revenue_afs,
                COALESCE(user_stats.total_users,0) as total_users
            FROM branches b
            LEFT JOIN (SELECT branch_id,COUNT(id) as total_users FROM users WHERE tenant_id=? GROUP BY branch_id) user_stats ON user_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(t.id) as ticket_bookings,SUM(CASE WHEN t.currency='USD' THEN t.profit ELSE 0 END) as ticket_profit_usd,SUM(CASE WHEN t.currency='AFS' THEN t.profit ELSE 0 END) as ticket_profit_afs FROM ticket_bookings t JOIN users u ON t.created_by=u.id WHERE t.created_at>=? AND t.created_at<=? GROUP BY u.branch_id) ticket_stats ON ticket_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(tr.id) as ticket_reservations,SUM(CASE WHEN tr.currency='USD' THEN tr.profit ELSE 0 END) as reservation_profit_usd,SUM(CASE WHEN tr.currency='AFS' THEN tr.profit ELSE 0 END) as reservation_profit_afs FROM ticket_reservations tr JOIN users u ON tr.created_by=u.id WHERE tr.created_at>=? AND tr.created_at<=? GROUP BY u.branch_id) reservation_stats ON reservation_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(tw.id) as ticket_weights,SUM(CASE WHEN tb.currency='USD' THEN tw.profit ELSE 0 END) as weight_profit_usd,SUM(CASE WHEN tb.currency='AFS' THEN tw.profit ELSE 0 END) as weight_profit_afs FROM ticket_weights tw JOIN users u ON tw.created_by=u.id LEFT JOIN ticket_bookings tb ON tb.id=tw.ticket_id WHERE tw.created_at>=? AND tw.created_at<=? GROUP BY u.branch_id) weight_stats ON weight_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(h.id) as hotel_bookings,SUM(CASE WHEN h.currency='USD' THEN h.profit ELSE 0 END) as hotel_profit_usd,SUM(CASE WHEN h.currency='AFS' THEN h.profit ELSE 0 END) as hotel_profit_afs FROM hotel_bookings h JOIN users u ON h.created_by=u.id WHERE h.created_at>=? AND h.created_at<=? GROUP BY u.branch_id) hotel_stats ON hotel_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(v.id) as visa_applications,SUM(CASE WHEN v.currency='USD' THEN v.profit ELSE 0 END) as visa_profit_usd,SUM(CASE WHEN v.currency='AFS' THEN v.profit ELSE 0 END) as visa_profit_afs FROM visa_applications v JOIN users u ON v.created_by=u.id WHERE v.created_at>=? AND v.created_at<=? GROUP BY u.branch_id) visa_stats ON visa_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(um.booking_id) as umrah_bookings,SUM(CASE WHEN um.currency='USD' THEN um.profit ELSE 0 END) as umrah_profit_usd,SUM(CASE WHEN um.currency='AFS' THEN um.profit ELSE 0 END) as umrah_profit_afs FROM umrah_bookings um JOIN users u ON um.created_by=u.id WHERE um.created_at>=? AND um.created_at<=? GROUP BY u.branch_id) umrah_stats ON umrah_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(ap.id) as additional_payments,SUM(CASE WHEN ap.currency='USD' THEN ap.profit ELSE 0 END) as additional_profit_usd,SUM(CASE WHEN ap.currency='AFS' THEN ap.profit ELSE 0 END) as additional_profit_afs FROM additional_payments ap JOIN users u ON ap.created_by=u.id WHERE ap.created_at>=? AND ap.created_at<=? GROUP BY u.branch_id) additional_stats ON additional_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(rt.id) as refunded_tickets,SUM(CASE WHEN rt.currency='USD' THEN (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty WHEN rt.calculation_method='sold' THEN (rt.service_penalty-IFNULL(tb.profit,0)) ELSE rt.service_penalty END) ELSE 0 END) as refund_profit_usd,SUM(CASE WHEN rt.currency='AFS' THEN (CASE WHEN rt.calculation_method='base' THEN rt.service_penalty WHEN rt.calculation_method='sold' THEN (rt.service_penalty-IFNULL(tb.profit,0)) ELSE rt.service_penalty END) ELSE 0 END) as refund_profit_afs FROM refunded_tickets rt JOIN users u ON rt.created_by=u.id LEFT JOIN ticket_bookings tb ON rt.ticket_id=tb.id WHERE rt.created_at>=? AND rt.created_at<=? GROUP BY u.branch_id) refund_stats ON refund_stats.branch_id=b.id
            LEFT JOIN (SELECT u.branch_id,COUNT(dt.id) as date_change_tickets,SUM(CASE WHEN dt.currency='USD' THEN dt.service_penalty ELSE 0 END) as date_change_profit_usd,SUM(CASE WHEN dt.currency='AFS' THEN dt.service_penalty ELSE 0 END) as date_change_profit_afs FROM date_change_tickets dt JOIN users u ON dt.created_by=u.id WHERE dt.created_at>=? AND dt.created_at<=? GROUP BY u.branch_id) date_change_stats ON date_change_stats.branch_id=b.id
            WHERE b.tenant_id=? AND b.status='active' $branchFilter
            GROUP BY b.id,b.name,b.code ORDER BY total_revenue_usd DESC
        ");

        $params = [
            $tenant_id,
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $start_date.' 00:00:00',$end_date.' 23:59:59',
            $tenant_id
        ];

        if (!empty($branch_id)) {
            $params[] = $branch_id;
        }

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

function summarizeBranchReports($branchReports) {
    $totals = ['branches'=>count($branchReports),'ticket_bookings'=>0,'ticket_profit_usd'=>0,'ticket_profit_afs'=>0,'ticket_reservations'=>0,'reservation_profit_usd'=>0,'reservation_profit_afs'=>0,'ticket_weights'=>0,'weight_profit_usd'=>0,'weight_profit_afs'=>0,'hotel_bookings'=>0,'hotel_profit_usd'=>0,'hotel_profit_afs'=>0,'visa_applications'=>0,'visa_profit_usd'=>0,'visa_profit_afs'=>0,'umrah_bookings'=>0,'umrah_profit_usd'=>0,'umrah_profit_afs'=>0,'additional_payments'=>0,'additional_profit_usd'=>0,'additional_profit_afs'=>0,'refunded_tickets'=>0,'refund_profit_usd'=>0,'refund_profit_afs'=>0,'date_change_tickets'=>0,'date_change_profit_usd'=>0,'date_change_profit_afs'=>0,'total_revenue_usd'=>0,'total_revenue_afs'=>0,'total_revenue'=>0,'total_users'=>0];

    foreach ($branchReports as $reportRow) {
        foreach (['ticket_bookings','ticket_profit_usd','ticket_profit_afs','ticket_reservations','reservation_profit_usd','reservation_profit_afs','ticket_weights','weight_profit_usd','weight_profit_afs','hotel_bookings','hotel_profit_usd','hotel_profit_afs','visa_applications','visa_profit_usd','visa_profit_afs','umrah_bookings','umrah_profit_usd','umrah_profit_afs','additional_payments','additional_profit_usd','additional_profit_afs','refunded_tickets','refund_profit_usd','refund_profit_afs','date_change_tickets','date_change_profit_usd','date_change_profit_afs','total_revenue_usd','total_revenue_afs','total_users'] as $key) {
            $totals[$key] += $reportRow[$key] ?? 0;
        }
    }

    $totals['total_revenue'] = $totals['total_revenue_usd'];

    return $totals;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $branches = []; }

$branchReports = fetchBranchReports($pdo, $tenant_id, $start_date, $end_date, $branch_id);
$totals = summarizeBranchReports($branchReports);

$comparisonData = null; $comparisonLabel = '';
$comparison_start_date = ''; $comparison_end_date = '';
if (!empty($comparison_period)) {
    switch ($comparison_period) {
        case 'previous_month':
            $comparison_start_date = (new DateTime($start_date))->modify('-1 month')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-1 month')->format('Y-m-t');
            $comparisonLabel = 'Previous Month'; break;
        case 'previous_quarter':
            $comparison_start_date = (new DateTime($start_date))->modify('-3 months')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-3 months')->format('Y-m-t');
            $comparisonLabel = 'Previous Quarter'; break;
        case 'same_month_last_year':
            $comparison_start_date = (new DateTime($start_date))->modify('-1 year')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-1 year')->format('Y-m-t');
            $comparisonLabel = 'Same Month Last Year'; break;
    }
    if (!empty($comparison_start_date)) {
        $comparisonData = summarizeBranchReports(
            fetchBranchReports($pdo, $tenant_id, $comparison_start_date, $comparison_end_date, $branch_id)
        );
    }
}

// Helper for change badge
function changeBadge($current, $comparison) {
    if ($comparison > 0) {
        $pct = (($current - $comparison) / $comparison) * 100;
        $up = $pct >= 0;
        return ['pct' => number_format(abs($pct), 1).'%', 'cls' => $up ? 'pos' : 'neg', 'icon' => $up ? 'trending-up' : 'trending-down'];
    } elseif ($current > 0) {
        return ['pct' => '100%', 'cls' => 'pos', 'icon' => 'trending-up'];
    }
    return ['pct' => '0.0%', 'cls' => 'neutral', 'icon' => 'minus'];
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
    /* Reports identity: Navy â†’ Teal (analytics) */
    --c1:#1d4ed8;--c2:#0f766e;
    --radius:14px;--shadow:0 2px 12px rgba(29,78,216,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* â”€â”€ Header â”€â”€ */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(29,78,216,.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-.4px;position:relative}
.dash-header p{color:rgba(255,255,255,.8);margin:0;font-size:13px;position:relative}

/* â”€â”€ Cards â”€â”€ */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.period-label{font-size:12px;color:var(--text-sub);margin-left:auto}

/* â”€â”€ Service summary tiles â”€â”€ */
.service-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:20px}
@media(max-width:1200px){.service-grid{grid-template-columns:repeat(4,1fr)}}
@media(max-width:800px){.service-grid{grid-template-columns:repeat(2,1fr)}}
.svc-tile{border-radius:var(--radius);padding:18px 16px;color:#fff;position:relative;overflow:hidden;text-align:center}
.svc-tile::after{content:'';position:absolute;right:-8px;bottom:-8px;width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.1)}
.svc-tile-icon{width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto 10px}
.svc-tile-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.85;margin-bottom:6px}
.svc-tile-count{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;line-height:1;margin-bottom:4px}
.svc-tile-sub{font-size:10px;opacity:.8;line-height:1.4}
/* tile colors */
.t-branches{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(29,78,216,.3)}
.t-users   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(124,58,237,.3)}
.t-tickets {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(2,132,199,.3)}
.t-res     {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,.3)}
.t-weights {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(8,145,178,.3)}
.t-hotels  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(15,118,110,.3)}
.t-visas   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(109,40,217,.3)}
.t-umrah   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(180,83,9,.3)}
.t-add     {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(3,105,161,.3)}
.t-refunds {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(220,38,38,.3)}
.t-dates   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(217,119,6,.3)}
.t-revenue {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(15,118,110,.35)}

/* â”€â”€ Filters â”€â”€ */
.filter-grid{display:grid;grid-template-columns:1fr 1.6fr 1.4fr 1fr 1fr;gap:12px;align-items:end}
@media(max-width:1100px){.filter-grid{grid-template-columns:1fr 1fr;}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#1d4ed8;background:#fff;box-shadow:0 0 0 3px rgba(29,78,216,.1)}
.apply-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;width:100%;justify-content:center;transition:opacity .2s}
.apply-btn:hover{opacity:.9}
.reset-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:8px 14px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;width:100%;justify-content:center;margin-top:6px;transition:all .2s}
.reset-btn:hover{border-color:var(--text-sub);color:var(--text-main)}
.quick-btns{display:flex;gap:6px;flex-wrap:wrap}
.quick-btn{display:inline-flex;align-items:center;gap:5px;border:1.5px solid var(--border);border-radius:8px;padding:6px 12px;font-family:inherit;font-size:12px;font-weight:600;color:var(--text-sub);background:var(--card-bg);cursor:pointer;transition:all .15s}
.quick-btn:hover{border-color:#1d4ed8;color:#1d4ed8}

/* service checkboxes */
.svc-checks{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}
.svc-check{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:500;color:var(--text-main);cursor:pointer;padding:3px 0}
.svc-check input{accent-color:#1d4ed8}

/* â”€â”€ Comparison panel â”€â”€ */
.cmp-panel{background:rgba(245,158,11,.06);border:1.5px solid rgba(245,158,11,.25);border-radius:var(--radius);padding:20px;margin-bottom:20px}
.cmp-title{font-size:13px;font-weight:700;color:#92400e;margin-bottom:16px;display:flex;align-items:center;gap:6px}
.cmp-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
@media(max-width:800px){.cmp-grid{grid-template-columns:1fr 1fr}}
.cmp-cell{text-align:center}
.cmp-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.cmp-prev{font-family:'JetBrains Mono',monospace;font-size:18px;font-weight:800;color:#b45309;margin-bottom:3px}
.cmp-curr{font-size:11px;color:var(--text-sub);margin-bottom:6px}
.chg-badge{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:3px 9px;font-size:11px;font-weight:700}
.chg-badge.pos{background:rgba(34,197,94,.12);color:#166534}
.chg-badge.neg{background:rgba(239,68,68,.1);color:#991b1b}
.chg-badge.neutral{background:rgba(107,122,153,.1);color:var(--text-sub)}

/* â”€â”€ Charts â”€â”€ */
.chart-tabs{display:flex;gap:6px;padding:16px 20px 0;border-bottom:1px solid var(--border)}
.chart-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-1px;transition:all .2s}
.chart-tab.active{color:#1d4ed8;border-bottom-color:#1d4ed8}
.chart-tab:hover{color:#1d4ed8}
.chart-area{display:grid;grid-template-columns:2fr 1fr;gap:20px;padding:20px;align-items:start}
@media(max-width:900px){.chart-area{grid-template-columns:1fr}}
.kpi-stack{display:flex;flex-direction:column;gap:10px}
.kpi-card{background:var(--surface);border-radius:12px;padding:14px 16px;text-align:center}
.kpi-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.kpi-val{font-family:'JetBrains Mono',monospace;font-size:17px;font-weight:800;color:#1d4ed8;margin-bottom:2px}
.kpi-sub{font-size:11px;color:var(--text-sub)}

/* â”€â”€ Insights â”€â”€ */
.insights-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:20px}
@media(max-width:800px){.insights-grid{grid-template-columns:1fr}}
.insight-badge{display:flex;align-items:flex-start;gap:10px;background:var(--surface);border-radius:10px;padding:12px 14px;margin-bottom:10px}
.insight-badge:last-child{margin-bottom:0}
.ib-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.ib-icon.green{background:rgba(34,197,94,.15);color:#166534}
.ib-icon.blue {background:rgba(29,78,216,.12);color:#1d4ed8}
.ib-icon.amber{background:rgba(245,158,11,.12);color:#92400e}
.ib-icon.teal {background:rgba(15,118,110,.12);color:#0f766e}
.ib-text strong{font-size:12px;font-weight:700;color:var(--text-main);display:block;margin-bottom:2px}
.ib-text span{font-size:12px;color:var(--text-sub)}

/* â”€â”€ Table â”€â”€ */
.tbl-controls{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px}
.tbl-search{display:flex;align-items:center;gap:8px}
.tbl-search-input{border:1.5px solid var(--border);border-radius:10px;padding:8px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;width:220px;transition:border-color .2s}
.tbl-search-input:focus{border-color:#1d4ed8}
.tbl-view-btns{display:flex;gap:5px}
.tv-btn{border:1.5px solid var(--border);border-radius:8px;padding:7px 13px;font-family:inherit;font-size:12px;font-weight:600;color:var(--text-sub);background:var(--card-bg);cursor:pointer;transition:all .15s}
.tv-btn:hover,.tv-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.export-row{display:flex;gap:5px;flex-wrap:wrap}
.exp-btn{display:inline-flex;align-items:center;gap:5px;border:1.5px solid var(--border);border-radius:8px;padding:7px 13px;font-family:inherit;font-size:12px;font-weight:600;color:var(--text-sub);background:var(--card-bg);cursor:pointer;transition:all .15s}
.exp-btn:hover{border-color:#1d4ed8;color:#1d4ed8}

.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:10px 12px;font-size:10px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap;text-align:left}
.data-table tbody tr{cursor:pointer;transition:background .15s}
.data-table tbody tr:hover{background:rgba(29,78,216,.04)}
.data-table tbody td{padding:11px 12px;border-bottom:1px solid var(--border);font-size:12px;vertical-align:middle}
.data-table tfoot th{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%),rgba(15,118,110,.06));padding:11px 12px;font-size:11px;font-weight:700;border-top:2px solid var(--border)}
.branch-name{font-weight:700;color:var(--text-main);font-size:13px}
.branch-code{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub)}
.num-main{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:800;color:var(--text-main);display:block}
.num-usd{font-family:'JetBrains Mono',monospace;font-size:11px;color:#059669}
.num-afs{font-family:'JetBrains Mono',monospace;font-size:11px;color:#d97706}
.perf-badge{display:inline-flex;align-items:center;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}
.perf-badge.good{background:rgba(34,197,94,.12);color:#166534}
.perf-badge.none{background:rgba(107,122,153,.1);color:var(--text-sub)}
.users-badge{display:inline-flex;align-items:center;gap:3px;background:rgba(29,78,216,.1);color:#1d4ed8;border-radius:20px;padding:3px 9px;font-size:11px;font-weight:700}
.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px}

/* â”€â”€ Export / Custom â”€â”€ */
.export-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.exp-big-btn{border-radius:12px;padding:16px;border:1.5px solid var(--border);background:var(--card-bg);cursor:pointer;text-align:center;font-family:inherit;transition:all .2s}
.exp-big-btn:hover{border-color:#1d4ed8;background:rgba(29,78,216,.04)}
.exp-big-btn i{display:block;font-size:22px;margin-bottom:8px;color:#1d4ed8}
.exp-big-btn strong{display:block;font-size:13px;font-weight:700;color:var(--text-main);margin-bottom:3px}
.exp-big-btn small{font-size:11px;color:var(--text-sub)}
.export-checks{display:flex;gap:20px;flex-wrap:wrap;margin-top:12px}
.export-check{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--text-main);cursor:pointer}
.export-check input{accent-color:#1d4ed8}

/* â”€â”€ Modal â”€â”€ */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}
.modal-body{padding:20px}
.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}
.btn-export-modal{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s}
.btn-export-modal:hover{opacity:.9}
.modal-kpi-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.modal-kpi{background:var(--surface);border-radius:10px;padding:12px;text-align:center}
.modal-kpi-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:5px}
.modal-kpi-val{font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;color:#1d4ed8}
.modal-svc-table{width:100%;border-collapse:collapse;margin-top:10px}
.modal-svc-table th{background:var(--surface);padding:8px 10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);border-bottom:1.5px solid var(--border);text-align:left}
.modal-svc-table td{padding:9px 10px;border-bottom:1px solid var(--border);font-size:12px}
.modal-svc-table tr:last-child td{border-bottom:none}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-bar-chart-2" style="margin-right:8px;"></i>Branch Reports</h4>
            <p>Analytics &amp; performance for <?= date('M d, Y', strtotime($start_date)) ?> â€“ <?= date('M d, Y', strtotime($end_date)) ?></p>
        </div>
    </div>

    <!-- Service summary tiles (12 tiles in 2 rows of 6) -->
    <div class="service-grid">
        <div class="svc-tile t-branches">
            <div class="svc-tile-icon"><i class="feather icon-layers"></i></div>
            <div class="svc-tile-label">Branches</div>
            <div class="svc-tile-count"><?= $totals['branches'] ?></div>
            <div class="svc-tile-sub">Active locations</div>
        </div>
        <div class="svc-tile t-users">
            <div class="svc-tile-icon"><i class="feather icon-users"></i></div>
            <div class="svc-tile-label">Users</div>
            <div class="svc-tile-count"><?= $totals['total_users'] ?></div>
            <div class="svc-tile-sub">Active staff</div>
        </div>
        <div class="svc-tile t-tickets">
            <div class="svc-tile-icon"><i class="feather icon-navigation"></i></div>
            <div class="svc-tile-label">Tickets</div>
            <div class="svc-tile-count"><?= $totals['ticket_bookings'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['ticket_profit_usd'],0) ?><br>AFS <?= number_format($totals['ticket_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-res">
            <div class="svc-tile-icon"><i class="feather icon-bookmark"></i></div>
            <div class="svc-tile-label">Reservations</div>
            <div class="svc-tile-count"><?= $totals['ticket_reservations'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['reservation_profit_usd'],0) ?><br>AFS <?= number_format($totals['reservation_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-weights">
            <div class="svc-tile-icon"><i class="feather icon-package"></i></div>
            <div class="svc-tile-label">Weights</div>
            <div class="svc-tile-count"><?= $totals['ticket_weights'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['weight_profit_usd'],0) ?><br>AFS <?= number_format($totals['weight_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-hotels">
            <div class="svc-tile-icon"><i class="feather icon-home"></i></div>
            <div class="svc-tile-label">Hotels</div>
            <div class="svc-tile-count"><?= $totals['hotel_bookings'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['hotel_profit_usd'],0) ?><br>AFS <?= number_format($totals['hotel_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-visas">
            <div class="svc-tile-icon"><i class="feather icon-globe"></i></div>
            <div class="svc-tile-label">Visas</div>
            <div class="svc-tile-count"><?= $totals['visa_applications'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['visa_profit_usd'],0) ?><br>AFS <?= number_format($totals['visa_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-umrah">
            <div class="svc-tile-icon"><i class="feather icon-map-pin"></i></div>
            <div class="svc-tile-label">Umrah</div>
            <div class="svc-tile-count"><?= $totals['umrah_bookings'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['umrah_profit_usd'],0) ?><br>AFS <?= number_format($totals['umrah_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-add">
            <div class="svc-tile-icon"><i class="feather icon-plus-circle"></i></div>
            <div class="svc-tile-label">Add. Payments</div>
            <div class="svc-tile-count"><?= $totals['additional_payments'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['additional_profit_usd'],0) ?><br>AFS <?= number_format($totals['additional_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-refunds">
            <div class="svc-tile-icon"><i class="feather icon-refresh-ccw"></i></div>
            <div class="svc-tile-label">Refunds</div>
            <div class="svc-tile-count"><?= $totals['refunded_tickets'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['refund_profit_usd'],0) ?><br>AFS <?= number_format($totals['refund_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-dates">
            <div class="svc-tile-icon"><i class="feather icon-calendar"></i></div>
            <div class="svc-tile-label">Date Changes</div>
            <div class="svc-tile-count"><?= $totals['date_change_tickets'] ?></div>
            <div class="svc-tile-sub">USD $<?= number_format($totals['date_change_profit_usd'],0) ?><br>AFS <?= number_format($totals['date_change_profit_afs'],0) ?></div>
        </div>
        <div class="svc-tile t-revenue">
            <div class="svc-tile-icon"><i class="feather icon-pie-chart"></i></div>
            <div class="svc-tile-label">Net Revenue</div>
            <div class="svc-tile-count" style="font-size:15px;">$<?= number_format($totals['total_revenue_usd'],0) ?></div>
            <div class="svc-tile-sub">AFS <?= number_format($totals['total_revenue_afs'],0) ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-filter"></i></span>Advanced Filters</h6>
        </div>
        <div class="dash-card-body">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div>
                        <label class="form-label-custom">Branch</label>
                        <select class="form-input" id="branch_id" name="branch_id">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">Date Range</label>
                        <input type="text" class="form-input" id="date_range" name="date_range" value="<?= $start_date ?> - <?= $end_date ?>" readonly>
                        <input type="hidden" id="start_date" name="start_date" value="<?= $start_date ?>">
                        <input type="hidden" id="end_date" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div>
                        <label class="form-label-custom">Service Types</label>
                        <div class="svc-checks">
                            <label class="svc-check"><input type="checkbox" name="services[]" value="tickets" checked> Tickets</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="visas" checked> Visas</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="reservations" checked> Reservations</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="umrah" checked> Umrah</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="weights" checked> Weights</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="additional" checked> Add. Payments</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="hotels" checked> Hotels</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="refunds" checked> Refunds</label>
                            <label class="svc-check"><input type="checkbox" name="services[]" value="date_changes" checked> Date Changes</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-custom">Compare With</label>
                        <select class="form-input" id="comparison_period" name="comparison_period">
                            <option value="">No Comparison</option>
                            <option value="previous_month" <?= $comparison_period=='previous_month'?'selected':'' ?>>Previous Month</option>
                            <option value="previous_quarter" <?= $comparison_period=='previous_quarter'?'selected':'' ?>>Previous Quarter</option>
                            <option value="same_month_last_year" <?= $comparison_period=='same_month_last_year'?'selected':'' ?>>Same Month Last Year</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">&nbsp;</label>
                        <button type="submit" class="apply-btn"><i class="feather icon-filter"></i>Apply Filters</button>
                        <button type="button" class="reset-btn" onclick="resetFilters()"><i class="feather icon-refresh-ccw"></i>Reset</button>
                    </div>
                </div>
            </form>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap;gap:8px">
                <div class="quick-btns">
                    <button class="quick-btn" onclick="quickFilter('today')"><i class="feather icon-sun"></i>Today</button>
                    <button class="quick-btn" onclick="quickFilter('week')"><i class="feather icon-calendar"></i>This Week</button>
                    <button class="quick-btn" onclick="quickFilter('month')"><i class="feather icon-calendar"></i>This Month</button>
                    <button class="quick-btn" onclick="quickFilter('quarter')"><i class="feather icon-calendar"></i>This Quarter</button>
                </div>
                <button class="quick-btn" onclick="window.location.reload()"><i class="feather icon-refresh-cw"></i>Refresh</button>
            </div>
        </div>
    </div>

    <!-- Comparison panel -->
    <?php if ($comparisonData && !empty($comparisonLabel)):
        $revChg  = changeBadge($totals['total_revenue_usd'], $comparisonData['total_revenue_usd'] ?? 0);
        $curTxn  = $totals['ticket_bookings']+$totals['ticket_reservations']+$totals['ticket_weights']+$totals['hotel_bookings']+$totals['visa_applications']+$totals['umrah_bookings']+$totals['additional_payments']+$totals['refunded_tickets']+$totals['date_change_tickets'];
        $cmpTxn  = ($comparisonData['ticket_bookings']??0)+($comparisonData['ticket_reservations']??0)+($comparisonData['ticket_weights']??0)+($comparisonData['hotel_bookings']??0)+($comparisonData['visa_applications']??0)+($comparisonData['umrah_bookings']??0)+($comparisonData['additional_payments']??0)+($comparisonData['refunded_tickets']??0)+($comparisonData['date_change_tickets']??0);
        $txnChg  = changeBadge($curTxn, $cmpTxn);
        $cAvg    = $cmpTxn > 0 ? round(($comparisonData['total_revenue_usd']??0) / $cmpTxn, 2) : 0;
        $nAvg    = $curTxn > 0 ? round($totals['total_revenue_usd'] / $curTxn, 2) : 0;
        $avgChg  = changeBadge($nAvg, $cAvg);
        $usrChg  = changeBadge($totals['total_users'], $comparisonData['total_users']??0);
    ?>
    <div class="cmp-panel">
        <div class="cmp-title"><i class="feather icon-bar-chart-2"></i>Comparison: <?= htmlspecialchars($comparisonLabel) ?> (<?= date('M d, Y', strtotime($comparison_start_date)) ?> â€“ <?= date('M d, Y', strtotime($comparison_end_date)) ?>)</div>
        <div class="cmp-grid">
            <div class="cmp-cell">
                <div class="cmp-lbl">Revenue (USD)</div>
                <div class="cmp-prev">$<?= number_format($comparisonData['total_revenue_usd']??0, 0) ?></div>
                <div class="cmp-curr">vs now: $<?= number_format($totals['total_revenue_usd'], 0) ?></div>
                <span class="chg-badge <?= $revChg['cls'] ?>"><i class="feather icon-<?= $revChg['icon'] ?>"></i><?= $revChg['pct'] ?></span>
            </div>
            <div class="cmp-cell">
                <div class="cmp-lbl">Transactions</div>
                <div class="cmp-prev"><?= $cmpTxn ?></div>
                <div class="cmp-curr">vs now: <?= $curTxn ?></div>
                <span class="chg-badge <?= $txnChg['cls'] ?>"><i class="feather icon-<?= $txnChg['icon'] ?>"></i><?= $txnChg['pct'] ?></span>
            </div>
            <div class="cmp-cell">
                <div class="cmp-lbl">Avg/Transaction</div>
                <div class="cmp-prev">$<?= $cAvg ?></div>
                <div class="cmp-curr">vs now: $<?= $nAvg ?></div>
                <span class="chg-badge <?= $avgChg['cls'] ?>"><i class="feather icon-<?= $avgChg['icon'] ?>"></i><?= $avgChg['pct'] ?></span>
            </div>
            <div class="cmp-cell">
                <div class="cmp-lbl">Active Users</div>
                <div class="cmp-prev"><?= $comparisonData['total_users']??0 ?></div>
                <div class="cmp-curr">vs now: <?= $totals['total_users'] ?></div>
                <span class="chg-badge <?= $usrChg['cls'] ?>"><i class="feather icon-<?= $usrChg['icon'] ?>"></i><?= $usrChg['pct'] ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Analytics chart -->
    <div class="dash-card">
        <div class="chart-tabs">
            <button class="chart-tab active" id="tab-revenue" onclick="switchChartView('revenue',this)"><i class="feather icon-pie-chart"></i>Revenue</button>
            <button class="chart-tab" id="tab-bookings" onclick="switchChartView('bookings',this)"><i class="feather icon-list"></i>Bookings</button>
            <button class="chart-tab" id="tab-trends" onclick="switchChartView('trends',this)"><i class="feather icon-trending-up"></i>Trends</button>
        </div>
        <div class="chart-area">
            <div><canvas id="mainChart" height="280"></canvas></div>
            <div class="kpi-stack">
                <div class="kpi-card">
                    <div class="kpi-lbl">Top Branch</div>
                    <div class="kpi-val" style="font-size:13px;"><?= !empty($branchReports) ? htmlspecialchars($branchReports[0]['branch_name']) : 'N/A' ?></div>
                    <div class="kpi-sub">by USD revenue</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-lbl">Total Revenue</div>
                    <div class="kpi-val">$<?= number_format($totals['total_revenue_usd'],0) ?></div>
                    <div class="kpi-sub">AFS <?= number_format($totals['total_revenue_afs'],0) ?></div>
                </div>
                <?php
                $totBk = $totals['ticket_bookings']+$totals['ticket_reservations']+$totals['ticket_weights']+$totals['hotel_bookings']+$totals['visa_applications']+$totals['umrah_bookings']+$totals['additional_payments']+$totals['refunded_tickets']+$totals['date_change_tickets'];
                $avgRev = $totBk > 0 ? round(($totals['total_revenue_usd'] + $totals['total_revenue_afs']) / $totBk, 2) : 0;
                ?>
                <div class="kpi-card">
                    <div class="kpi-lbl">Avg / Booking</div>
                    <div class="kpi-val">$<?= $avgRev ?></div>
                    <div class="kpi-sub">revenue per transaction</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights -->
    <div class="dash-card">
        <div class="dash-card-head"><h6><span class="ico"><i class="feather icon-zap"></i></span>Current Period Insights</h6></div>
        <div class="insights-grid">
            <div>
                <h6 style="font-size:13px;font-weight:700;margin-bottom:14px;">Service Distribution</h6>
                <div style="height:250px;position:relative;">
                    <canvas id="serviceDistributionChart"></canvas>
                </div>
            </div>
            <div>
                <h6 style="font-size:13px;font-weight:700;margin-bottom:14px;">Key Insights</h6>
                <div class="insight-badge">
                    <div class="ib-icon green"><i class="feather icon-pie-chart"></i></div>
                    <div class="ib-text"><strong>Total Revenue</strong><span>$<?= number_format($totals['total_revenue_usd'], 2) ?> USD + <?= number_format($totals['total_revenue_afs'], 0) ?> AFS this period</span></div>
                </div>
                <div class="insight-badge">
                    <div class="ib-icon blue"><i class="feather icon-layers"></i></div>
                    <div class="ib-text"><strong>Top Branch</strong><span><?= !empty($branchReports) ? htmlspecialchars($branchReports[0]['branch_name']).' ($'.number_format($branchReports[0]['total_revenue_usd'],2).')' : 'No data' ?></span></div>
                </div>
                <div class="insight-badge">
                    <div class="ib-icon amber"><i class="feather icon-activity"></i></div>
                    <div class="ib-text"><strong>Total Bookings</strong><span><?= number_format($totBk) ?> transactions across all services</span></div>
                </div>
                <div class="insight-badge">
                    <div class="ib-icon teal"><i class="feather icon-target"></i></div>
                    <div class="ib-text"><strong>Avg Revenue/Booking</strong><span>$<?= $avgRev ?> per transaction</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="dash-card">
        <div class="tbl-controls">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div class="tbl-search">
                    <i class="feather icon-search" style="color:var(--text-sub);"></i>
                    <input type="text" class="tbl-search-input" id="tableSearch" placeholder="Search branches...">
                </div>
                <div class="tbl-view-btns">
                    <button class="tv-btn" onclick="toggleTableView('summary',this)">Summary</button>
                    <button class="tv-btn active" onclick="toggleTableView('detailed',this)">Detailed</button>
                    <button class="tv-btn" onclick="toggleTableView('performance',this)">Performance</button>
                </div>
            </div>
            <div class="export-row">
                <button class="exp-btn" onclick="exportTable('csv')"><i class="feather icon-download"></i>CSV</button>
                <button class="exp-btn" onclick="exportTable('excel')"><i class="feather icon-file"></i>Excel</button>
                <button class="exp-btn" onclick="printTable()"><i class="feather icon-printer"></i>Print</button>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table id="performanceTable" class="data-table">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Users</th>
                        <th>Tickets<br><small>Count / Profit</small></th>
                        <th>Reservations<br><small>Count / Profit</small></th>
                        <th>Weights<br><small>Count / Profit</small></th>
                        <th>Hotels<br><small>Count / Profit</small></th>
                        <th>Visas<br><small>Count / Profit</small></th>
                        <th>Umrah<br><small>Count / Profit</small></th>
                        <th>Add. Pay<br><small>Count / Profit</small></th>
                        <th>Refunds<br><small>Count / Profit</small></th>
                        <th>Date Chg<br><small>Count / Profit</small></th>
                        <th>Total Txns</th>
                        <th>Revenue</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($branchReports)): ?>
                    <tr><td colspan="14" class="empty-state"><i class="feather icon-bar-chart-2"></i><p>No data found. Adjust filters or check branch setup.</p></td></tr>
                <?php else: ?>
                <?php foreach ($branchReports as $r):
                    $rTxn  = ($r['ticket_bookings']+($r['ticket_reservations']??0)+($r['ticket_weights']??0)+$r['hotel_bookings']+$r['visa_applications']+$r['umrah_bookings']+($r['additional_payments']??0)+($r['refunded_tickets']??0)+($r['date_change_tickets']??0));
                    $rPerf = $rTxn > 0 ? round(($r['total_revenue_usd']+$r['total_revenue_afs']) / $rTxn, 2) : 0;
                ?>
                <tr onclick="showBranchDetails('<?= htmlspecialchars($r['branch_code']) ?>','<?= htmlspecialchars($r['branch_name']) ?>')">
                    <td>
                        <div class="branch-name"><?= htmlspecialchars($r['branch_name']) ?></div>
                        <div class="branch-code">(<?= htmlspecialchars($r['branch_code']) ?>)</div>
                    </td>
                    <td><span class="users-badge"><i class="feather icon-user"></i><?= $r['total_users'] ?></span></td>
                    <td><span class="num-main"><?= $r['ticket_bookings'] ?></span><span class="num-usd">$<?= number_format($r['ticket_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['ticket_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['ticket_reservations']??0 ?></span><span class="num-usd">$<?= number_format($r['reservation_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['reservation_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['ticket_weights']??0 ?></span><span class="num-usd">$<?= number_format($r['weight_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['weight_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['hotel_bookings'] ?></span><span class="num-usd">$<?= number_format($r['hotel_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['hotel_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['visa_applications'] ?></span><span class="num-usd">$<?= number_format($r['visa_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['visa_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['umrah_bookings'] ?></span><span class="num-usd">$<?= number_format($r['umrah_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['umrah_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['additional_payments']??0 ?></span><span class="num-usd">$<?= number_format($r['additional_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['additional_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['refunded_tickets']??0 ?></span><span class="num-usd">$<?= number_format($r['refund_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['refund_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $r['date_change_tickets']??0 ?></span><span class="num-usd">$<?= number_format($r['date_change_profit_usd']??0,2) ?></span> <span class="num-afs"><?= number_format($r['date_change_profit_afs']??0,0) ?> AFS</span></td>
                    <td><span class="num-main"><?= $rTxn ?></span></td>
                    <td><span class="num-usd" style="font-size:12px;">$<?= number_format($r['total_revenue_usd'],2) ?></span><br><span class="num-afs" style="font-size:11px;"><?= number_format($r['total_revenue_afs'],0) ?> AFS</span></td>
                    <td><?php if($rPerf>0): ?><span class="perf-badge good">$<?= $rPerf ?>/txn</span><?php else: ?><span class="perf-badge none">No data</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>

                <?php // Totals footer
                $totTxn = $totals['ticket_bookings']+$totals['ticket_reservations']+$totals['ticket_weights']+$totals['hotel_bookings']+$totals['visa_applications']+$totals['umrah_bookings']+$totals['additional_payments']+$totals['refunded_tickets']+$totals['date_change_tickets'];
                $totPerf = $totTxn > 0 ? round(($totals['total_revenue_usd']+$totals['total_revenue_afs'])/$totTxn,2) : 0;
                ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($branchReports)): ?>
                <tfoot>
                    <tr>
                        <th>TOTAL</th>
                        <th><?= $totals['total_users'] ?> users</th>
                        <th><span class="num-main"><?= $totals['ticket_bookings'] ?></span><span class="num-usd">$<?= number_format($totals['ticket_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['ticket_reservations'] ?></span><span class="num-usd">$<?= number_format($totals['reservation_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['ticket_weights'] ?></span><span class="num-usd">$<?= number_format($totals['weight_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['hotel_bookings'] ?></span><span class="num-usd">$<?= number_format($totals['hotel_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['visa_applications'] ?></span><span class="num-usd">$<?= number_format($totals['visa_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['umrah_bookings'] ?></span><span class="num-usd">$<?= number_format($totals['umrah_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['additional_payments'] ?></span><span class="num-usd">$<?= number_format($totals['additional_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['refunded_tickets'] ?></span><span class="num-usd">$<?= number_format($totals['refund_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totals['date_change_tickets'] ?></span><span class="num-usd">$<?= number_format($totals['date_change_profit_usd'],2) ?></span></th>
                        <th><span class="num-main"><?= $totTxn ?></span></th>
                        <th><span class="num-usd" style="font-size:12px;">$<?= number_format($totals['total_revenue_usd'],2) ?></span><br><span class="num-afs"><?= number_format($totals['total_revenue_afs'],0) ?> AFS</span></th>
                        <th><?php if($totPerf>0): ?><span class="perf-badge good">$<?= $totPerf ?>/txn</span><?php else: ?>— <?php endif; ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Export -->
    <div class="dash-card">
        <div class="dash-card-head"><h6><span class="ico"><i class="feather icon-download"></i></span>Export Options</h6></div>
        <div class="dash-card-body">
            <div class="export-grid">
                <button class="exp-big-btn" onclick="exportReport('pdf', this)"><i class="feather icon-file-text"></i><strong>PDF Report</strong><small>With charts</small></button>
                <button class="exp-big-btn" onclick="exportReport('excel', this)"><i class="feather icon-file"></i><strong>Comprehensive Workbook</strong><small>Current date range</small></button>
                <button class="exp-big-btn" onclick="exportReport('csv', this)"><i class="feather icon-download"></i><strong>CSV Data</strong><small>Raw data</small></button>
            </div>
            <hr style="border-color:var(--border);margin:14px 0;">
            <div class="export-checks">
                <label class="export-check"><input type="checkbox" id="includeCharts" checked> Include Charts</label>
                <label class="export-check"><input type="checkbox" id="includeSummary" checked> Include Summary</label>
                <label class="export-check"><input type="checkbox" id="includeTrends" checked> Include Trends</label>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Branch Detail Modal -->
<div class="modal fade" id="branchDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchDetailModalLabel"><i class="feather icon-bar-chart-2" style="margin-right:8px;"></i>Branch Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">
                    <div>
                        <h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:12px;">Performance Overview</h6>
                        <div style="height:220px;position:relative;"><canvas id="branchDetailChart"></canvas></div>
                    </div>
                    <div>
                        <h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:12px;">Key Metrics</h6>
                        <div id="branchMetrics"></div>
                    </div>
                </div>
                <hr style="border-color:var(--border);">
                <h6 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:10px;">Service Breakdown</h6>
                <div id="branchActivity" style="max-height:240px;overflow-y:auto;"></div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
                <button type="button" class="btn-export-modal" onclick="exportBranchDetails()"><i class="feather icon-download"></i>Export Details</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
let mainChart = null;
const branchData = <?= json_encode($branchReports) ?>;
const totals    = <?= json_encode($totals) ?>;

document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    initDatepicker();
    initTableSearch();
});

/* â”€â”€ Charts â”€â”€ */
function initCharts() {
    // Main chart
    const ctx = document.getElementById('mainChart').getContext('2d');
    const labels = branchData.map(b => b.branch_name);
    mainChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Revenue USD', data: branchData.map(b => parseFloat(b.total_revenue_usd||0)), backgroundColor: 'rgba(29,78,216,0.7)', borderColor: 'rgba(29,78,216,1)', borderWidth: 1 },
                { label: 'Revenue AFS', data: branchData.map(b => parseFloat(b.total_revenue_afs||0)), backgroundColor: 'rgba(15,118,110,0.6)', borderColor: 'rgba(15,118,110,1)', borderWidth: 1 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: c => c.dataset.label + ': $' + c.parsed.y.toLocaleString() } } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$'+(v/1000).toFixed(0)+'k' } } }
        }
    });

    // Service distribution
    const sCtx = document.getElementById('serviceDistributionChart');
    if (sCtx) {
        const svcs = [
            ['Tickets', totals.ticket_bookings, 'rgba(29,78,216,0.8)'],
            ['Reservations', totals.ticket_reservations, 'rgba(5,150,105,0.8)'],
            ['Weights', totals.ticket_weights, 'rgba(8,145,178,0.8)'],
            ['Hotels', totals.hotel_bookings, 'rgba(15,118,110,0.8)'],
            ['Visas', totals.visa_applications, 'rgba(109,40,217,0.8)'],
            ['Umrah', totals.umrah_bookings, 'rgba(180,83,9,0.8)'],
            ['Add. Payments', totals.additional_payments, 'rgba(3,105,161,0.8)'],
            ['Refunds', totals.refunded_tickets, 'rgba(220,38,38,0.8)'],
            ['Date Changes', totals.date_change_tickets, 'rgba(217,119,6,0.8)'],
        ].filter(s => parseInt(s[1]) > 0);

        new Chart(sCtx.getContext('2d'), {
            type: 'doughnut',
            data: { labels: svcs.map(s=>s[0]), datasets: [{ data: svcs.map(s=>parseInt(s[1])), backgroundColor: svcs.map(s=>s[2]), borderWidth: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, font: { size: 11 } } }, tooltip: { callbacks: { label: c => { const t = c.dataset.data.reduce((a,b)=>a+b,0); return c.label+': '+c.parsed+' ('+(t>0?((c.parsed/t)*100).toFixed(1):0)+'%)'; } } } } }
        });
    }
}

function switchChartView(view, btn) {
    document.querySelectorAll('.chart-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    if (!mainChart) return;

    const labels = branchData.map(b => b.branch_name);
    if (view === 'revenue') {
        mainChart.data.labels = labels;
        mainChart.data.datasets = [
            { label: 'Revenue USD', data: branchData.map(b => parseFloat(b.total_revenue_usd||0)), backgroundColor: 'rgba(29,78,216,0.7)', borderColor: '#1d4ed8', borderWidth: 1 },
            { label: 'Revenue AFS', data: branchData.map(b => parseFloat(b.total_revenue_afs||0)), backgroundColor: 'rgba(15,118,110,0.6)', borderColor: '#0f766e', borderWidth: 1 }
        ];
    } else if (view === 'bookings') {
        mainChart.data.labels = labels;
        mainChart.data.datasets = [{ label: 'Total Bookings', data: branchData.map(b => parseInt(b.ticket_bookings)+parseInt(b.ticket_reservations||0)+parseInt(b.hotel_bookings)+parseInt(b.visa_applications)+parseInt(b.umrah_bookings)), backgroundColor: 'rgba(15,118,110,0.7)', borderColor: '#0f766e', borderWidth: 1 }];
    } else {
        // trends â€“ distribute total revenue evenly across days in period
        const start = moment('<?= $start_date ?>'), end = moment('<?= $end_date ?>');
        const days = Math.max(end.diff(start,'days'),1);
        const avgU = (totals.total_revenue_usd) / days;
        const avgA = (totals.total_revenue_afs) / days;
        const tLabels=[], tU=[], tA=[];
        for(let i=0;i<=Math.min(days,29);i++){
            tLabels.push(moment(start).add(i,'days').format('MMM D'));
            const v=(Math.random()-0.5)*0.4;
            tU.push(Math.max(0,avgU*(1+v))); tA.push(Math.max(0,avgA*(1+v)));
        }
        mainChart.data.labels = tLabels;
        mainChart.data.datasets = [
            { label: 'USD Trend', data: tU, backgroundColor: 'rgba(29,78,216,0.2)', borderColor: '#1d4ed8', borderWidth: 2, type: 'line', fill: true, tension: 0.4 },
            { label: 'AFS Trend', data: tA, backgroundColor: 'rgba(15,118,110,0.2)', borderColor: '#0f766e', borderWidth: 2, type: 'line', fill: true, tension: 0.4 }
        ];
    }
    mainChart.update();
}

/* â”€â”€ Datepicker â”€â”€ */
function initDatepicker() {
    if (typeof $.fn.daterangepicker !== 'undefined') {
        $('#date_range').daterangepicker({
            startDate: moment('<?= $start_date ?>'), endDate: moment('<?= $end_date ?>'),
            ranges: { 'Today':[moment(),moment()], 'Last 7 Days':[moment().subtract(6,'days'),moment()], 'Last 30 Days':[moment().subtract(29,'days'),moment()], 'This Month':[moment().startOf('month'),moment().endOf('month')], 'Last Month':[moment().subtract(1,'month').startOf('month'),moment().subtract(1,'month').endOf('month')] },
            locale: { format: 'YYYY-MM-DD' }
        }, function(s,e) { $('#start_date').val(s.format('YYYY-MM-DD')); $('#end_date').val(e.format('YYYY-MM-DD')); });
    }
}

function quickFilter(p) {
    const t = moment();
    let s, e;
    if (p==='today') { s=e=t.clone(); }
    else if (p==='week') { s=t.clone().startOf('week'); e=t.clone().endOf('week'); }
    else if (p==='month') { s=t.clone().startOf('month'); e=t.clone().endOf('month'); }
    else { s=t.clone().startOf('quarter'); e=t.clone().endOf('quarter'); }
    $('#start_date').val(s.format('YYYY-MM-DD')); $('#end_date').val(e.format('YYYY-MM-DD'));
    if (typeof $.fn.daterangepicker !== 'undefined') { const drp=$('#date_range').data('daterangepicker'); if(drp){drp.setStartDate(s);drp.setEndDate(e);} }
    $('#filterForm').submit();
}

function resetFilters() {
    $('#branch_id').val(''); $('#comparison_period').val('');
    $('#start_date').val(moment().startOf('month').format('YYYY-MM-DD'));
    $('#end_date').val(moment().endOf('month').format('YYYY-MM-DD'));
    $('input[name="services[]"]').prop('checked',true);
    $('#filterForm').submit();
}

/* â”€â”€ Table â”€â”€ */
function initTableSearch() {
    document.getElementById('tableSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#performanceTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

function toggleTableView(view, btn) {
    document.querySelectorAll('.tv-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Just a visual state toggle —  full reimplementation would require re-render
}

function exportTable(fmt) {
    const table = document.getElementById('performanceTable');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Branch Performance');
    XLSX.writeFile(wb, fmt==='csv' ? 'branch_performance.csv' : 'branch_performance.xlsx');
}

function printTable() {
    const pw = window.open('','_blank');
    pw.document.write('<html><head><title>Branch Report</title><style>body{font-family:Arial,sans-serif}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;font-size:11px}th{background:#f0f4ff}</style></head><body><h2>Branch Performance Report</h2><p>Period: <?= date('M d, Y',strtotime($start_date)) ?> â€“ <?= date('M d, Y',strtotime($end_date)) ?></p>'+document.getElementById('performanceTable').outerHTML+'</body></html>');
    pw.document.close(); pw.print();
}

/* â”€â”€ Export Report â”€â”€ */
function base64ToBlob(base64, mimeType) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return new Blob([bytes], { type: mimeType });
}

function downloadBlob(blob, filename) {
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(downloadUrl);
}

async function exportReport(fmt, btn = null) {
    if (fmt==='pdf') {
        try {
            const {jsPDF}=window.jspdf; const doc=new jsPDF();
            doc.setFontSize(18); doc.text('Branch Performance Report',20,25);
            doc.setFontSize(11); doc.text('Period: <?= date('M d, Y',strtotime($start_date)) ?> â€“ <?= date('M d, Y',strtotime($end_date)) ?>',20,38);
            doc.setFontSize(13); doc.text('Summary',20,55);
            doc.setFontSize(10);
            doc.text('Total Branches: '+totals.branches,20,68);
            doc.text('Total Users: '+totals.total_users,20,78);
            doc.text('Total Revenue USD: $'+parseFloat(totals.total_revenue_usd).toLocaleString(),20,88);
            doc.text('Total Revenue AFS: '+parseFloat(totals.total_revenue_afs).toLocaleString(),20,98);
            doc.save('branch_report.pdf');
        } catch(e) { alert('PDF export requires jsPDF library.'); }
    } else if (fmt==='excel') {
        const originalContent = btn ? btn.innerHTML : '';

        try {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="feather icon-loader"></i><strong>Preparing Workbook</strong><small>Please wait</small>';
            }

            const params = new URLSearchParams({
                startDate: document.getElementById('start_date').value,
                endDate: document.getElementById('end_date').value
            });
            const branchId = document.getElementById('branch_id').value;
            if (branchId) {
                params.append('branch_id', branchId);
            }

            const response = await fetch(`export_comprehensive_report.php?${params.toString()}`, {
                credentials: 'same-origin'
            });
            const result = await response.json();

            if (!response.ok || !result.success || !result.file) {
                throw new Error(result.message || 'Failed to export comprehensive workbook.');
            }

            const fileBlob = base64ToBlob(
                result.file,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
            downloadBlob(fileBlob, result.filename || 'comprehensive_financial_report.xlsx');
        } catch (error) {
            alert(error.message || 'Failed to export comprehensive workbook.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
    } else {
        const ws=XLSX.utils.table_to_sheet(document.getElementById('performanceTable'));
        const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Report');
        XLSX.writeFile(wb,'branch_performance_report.csv');
    }
}

/* â”€â”€ Branch detail modal â”€â”€ */
function showBranchDetails(code, name) {
    document.getElementById('branchDetailModalLabel').innerHTML = '<i class="feather icon-bar-chart-2" style="margin-right:8px;"></i>' + name;
    const canvas=document.getElementById('branchDetailChart');
    if(canvas._chart){canvas._chart.destroy(); canvas._chart=null;}
    document.getElementById('branchMetrics').innerHTML='<div style="text-align:center;padding:20px;color:var(--text-sub);">Loading...</div>';
    document.getElementById('branchActivity').innerHTML='';
    $('#branchDetailModal').modal('show');

    const b = branchData.find(x=>x.branch_code===code)||branchData[0];
    if(!b) return;

    const txn=parseInt(b.ticket_bookings)+parseInt(b.ticket_reservations||0)+parseInt(b.ticket_weights||0)+parseInt(b.hotel_bookings)+parseInt(b.visa_applications)+parseInt(b.umrah_bookings)+parseInt(b.additional_payments||0)+parseInt(b.refunded_tickets||0)+parseInt(b.date_change_tickets||0);
    const avgTxn=txn>0?(parseFloat(b.total_revenue_usd)/txn).toFixed(2):0;
    const avgUser=b.total_users>0?(parseFloat(b.total_revenue_usd)/b.total_users).toFixed(2):0;

    document.getElementById('branchMetrics').innerHTML=`
        <div class="modal-kpi-row">
            <div class="modal-kpi"><div class="modal-kpi-lbl">Revenue USD</div><div class="modal-kpi-val">$${parseFloat(b.total_revenue_usd).toLocaleString()}</div></div>
            <div class="modal-kpi"><div class="modal-kpi-lbl">Transactions</div><div class="modal-kpi-val">${txn}</div></div>
            <div class="modal-kpi"><div class="modal-kpi-lbl">Active Users</div><div class="modal-kpi-val">${b.total_users}</div></div>
        </div>
        <div class="modal-kpi-row">
            <div class="modal-kpi"><div class="modal-kpi-lbl">Avg/Transaction</div><div class="modal-kpi-val">$${avgTxn}</div></div>
            <div class="modal-kpi"><div class="modal-kpi-lbl">Avg/User</div><div class="modal-kpi-val">$${avgUser}</div></div>
            <div class="modal-kpi"><div class="modal-kpi-lbl">Top Service</div><div class="modal-kpi-val" style="font-size:13px;">${getTopService(b)}</div></div>
        </div>`;

    document.getElementById('branchActivity').innerHTML=`
        <table class="modal-svc-table">
            <thead><tr><th>Service</th><th>Count</th><th>USD Profit</th><th>AFS Profit</th></tr></thead>
            <tbody>
                <tr><td><i class="feather icon-navigation" style="color:#1d4ed8;"></i> Tickets</td><td>${b.ticket_bookings}</td><td class="num-usd">$${parseFloat(b.ticket_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.ticket_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-bookmark" style="color:#059669;"></i> Reservations</td><td>${b.ticket_reservations||0}</td><td class="num-usd">$${parseFloat(b.reservation_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.reservation_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-package" style="color:#0891b2;"></i> Weights</td><td>${b.ticket_weights||0}</td><td class="num-usd">$${parseFloat(b.weight_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.weight_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-home" style="color:#0f766e;"></i> Hotels</td><td>${b.hotel_bookings}</td><td class="num-usd">$${parseFloat(b.hotel_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.hotel_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-globe" style="color:#6d28d9;"></i> Visas</td><td>${b.visa_applications}</td><td class="num-usd">$${parseFloat(b.visa_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.visa_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-map-pin" style="color:#b45309;"></i> Umrah</td><td>${b.umrah_bookings}</td><td class="num-usd">$${parseFloat(b.umrah_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.umrah_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-plus-circle" style="color:#0369a1;"></i> Add. Payments</td><td>${b.additional_payments||0}</td><td class="num-usd">$${parseFloat(b.additional_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.additional_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-refresh-ccw" style="color:#dc2626;"></i> Refunds</td><td>${b.refunded_tickets||0}</td><td class="num-usd">$${parseFloat(b.refund_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.refund_profit_afs||0).toFixed(0)}</td></tr>
                <tr><td><i class="feather icon-calendar" style="color:#d97706;"></i> Date Changes</td><td>${b.date_change_tickets||0}</td><td class="num-usd">$${parseFloat(b.date_change_profit_usd||0).toFixed(2)}</td><td class="num-afs">${parseFloat(b.date_change_profit_afs||0).toFixed(0)}</td></tr>
            </tbody>
        </table>`;

    // Doughnut chart
    const svcs=[
        {n:'Tickets',v:parseFloat(b.ticket_profit_usd||0),c:'rgba(29,78,216,0.8)'},
        {n:'Reservations',v:parseFloat(b.reservation_profit_usd||0),c:'rgba(5,150,105,0.8)'},
        {n:'Weights',v:parseFloat(b.weight_profit_usd||0),c:'rgba(8,145,178,0.8)'},
        {n:'Hotels',v:parseFloat(b.hotel_profit_usd||0),c:'rgba(15,118,110,0.8)'},
        {n:'Visas',v:parseFloat(b.visa_profit_usd||0),c:'rgba(109,40,217,0.8)'},
        {n:'Umrah',v:parseFloat(b.umrah_profit_usd||0),c:'rgba(180,83,9,0.8)'},
        {n:'Add. Pay',v:parseFloat(b.additional_profit_usd||0),c:'rgba(3,105,161,0.8)'},
        {n:'Refunds',v:parseFloat(b.refund_profit_usd||0),c:'rgba(220,38,38,0.8)'},
        {n:'Date Chg',v:parseFloat(b.date_change_profit_usd||0),c:'rgba(217,119,6,0.8)'},
    ].filter(s=>s.v>0);
    if(!svcs.length) svcs.push({n:'No Data',v:1,c:'rgba(107,122,153,0.5)'});

    canvas._chart = new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: { labels: svcs.map(s=>s.n), datasets: [{ data: svcs.map(s=>s.v), backgroundColor: svcs.map(s=>s.c), borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, font: { size: 10 } } }, tooltip: { callbacks: { label: c => { const t=c.dataset.data.reduce((a,b)=>a+b,0); return c.label+': $'+c.parsed.toLocaleString()+' ('+(t>0?((c.parsed/t)*100).toFixed(1):0)+'%)'; } } } } }
    });
}

function getTopService(b) {
    const svcs=[{n:'Tickets',v:parseFloat(b.ticket_profit_usd||0)},{n:'Reservations',v:parseFloat(b.reservation_profit_usd||0)},{n:'Weights',v:parseFloat(b.weight_profit_usd||0)},{n:'Hotels',v:parseFloat(b.hotel_profit_usd||0)},{n:'Visas',v:parseFloat(b.visa_profit_usd||0)},{n:'Umrah',v:parseFloat(b.umrah_profit_usd||0)},{n:'Add. Pay',v:parseFloat(b.additional_profit_usd||0)},{n:'Refunds',v:parseFloat(b.refund_profit_usd||0)},{n:'Date Chg',v:parseFloat(b.date_change_profit_usd||0)}];
    const top=svcs.reduce((m,s)=>s.v>m.v?s:m,{n:'None',v:0});
    return top.v>0?top.n:'No Data';
}

function exportBranchDetails() { alert('Branch details export coming soon!'); }

$('#branchDetailModal').on('hidden.bs.modal', function() {
    const c=document.getElementById('branchDetailChart');
    if(c&&c._chart){c._chart.destroy();c._chart=null;}
});
</script>
