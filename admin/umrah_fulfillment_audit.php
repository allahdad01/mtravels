<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'security.php';
enforce_auth();
require_permission('reports.view');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../includes/db.php');

$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Fetch all groups
$grpStmt = $pdo->prepare("
    SELECT g.group_id, g.group_number, g.group_name,
           (SELECT COUNT(DISTINCT b.family_id) FROM umrah_bookings b
            JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
            WHERE f.group_id = g.group_id AND f.tenant_id = g.tenant_id
              AND b.status NOT IN ('refunded','cancelled')) AS family_count,
           (SELECT COUNT(*) FROM umrah_bookings b
            JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
            WHERE f.group_id = g.group_id AND f.tenant_id = g.tenant_id
              AND b.status NOT IN ('refunded','cancelled')) AS member_count
    FROM umrah_groups g
    WHERE g.tenant_id = ? AND (g.branch_id = ? OR g.branch_id = 0)
    ORDER BY CAST(g.group_number AS UNSIGNED) ASC, g.group_number ASC
");
$grpStmt->execute([$tenant_id, $branch_id]);
$groups = $grpStmt->fetchAll(PDO::FETCH_ASSOC);

// Build full audit for a group
$groupData = null;
if ($groupId > 0) {
    $giStmt = $pdo->prepare("SELECT * FROM umrah_groups WHERE group_id = ? AND tenant_id = ?");
    $giStmt->execute([$groupId, $tenant_id]);
    $groupInfo = $giStmt->fetch(PDO::FETCH_ASSOC);

    if ($groupInfo) {
        // All families in this group
        $famStmt = $pdo->prepare("
            SELECT f.family_id, f.head_of_family
            FROM families f
            WHERE f.group_id = ? AND f.tenant_id = ?
            ORDER BY f.head_of_family
        ");
        $famStmt->execute([$groupId, $tenant_id]);
        $families = $famStmt->fetchAll(PDO::FETCH_ASSOC);

        // All hotel fulfillments for this group (single query)
        $ffStmt = $pdo->prepare("
            SELECT f.*, bs.booking_id, bs.service_id, bs.service_type,
                   s.name AS service_name, b.name AS member_name,
                   b.is_extra_bed, b.is_extra_transport, b.family_id, b.sold_price AS bk_sold, b.profit AS bk_profit, b.status AS bk_status
            FROM umrah_fulfillments f
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id AND bs.tenant_id = f.tenant_id
            JOIN umrah_bookings b ON b.booking_id = bs.booking_id AND b.tenant_id = bs.tenant_id
            LEFT JOIN umrah_services s ON s.id = bs.service_id
            WHERE b.tenant_id = ? AND b.family_id IN (
                SELECT DISTINCT f2.family_id FROM families f2 WHERE f2.group_id = ? AND f2.tenant_id = ?
            ) AND LOWER(bs.service_type) = 'hotel' AND b.status NOT IN ('refunded','cancelled')
            ORDER BY b.family_id, b.is_extra_bed ASC, b.booking_id, f.id
        ");
        $ffStmt->execute([$tenant_id, $groupId, $tenant_id]);
        $allFfs = $ffStmt->fetchAll(PDO::FETCH_ASSOC);

        // All city_* details for fulfillments in this group
        $allFfIds = array_unique(array_map(fn($r) => (int)$r['id'], $allFfs));
        $cityMap = [];
        $ebMap = [];
        $etMap = [];
        if ($allFfIds) {
            $ph = implode(',', array_fill(0, count($allFfIds), '?'));
            $detStmt = $pdo->prepare("SELECT fulfillment_id, detail_key, detail_value FROM umrah_fulfillment_details WHERE fulfillment_id IN ($ph) AND (detail_key LIKE 'city_%' OR detail_key LIKE 'eb_%' OR detail_key LIKE 'et_%')");
            $detStmt->execute($allFfIds);
            foreach ($detStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $fid = (int)$r['fulfillment_id'];
                if (strpos($r['detail_key'], 'city_') === 0) {
                    $cityMap[$fid][$r['detail_key']] = $r['detail_value'];
                } elseif (strpos($r['detail_key'], 'et_') === 0) {
                    $etMap[$fid][$r['detail_key']] = $r['detail_value'];
                } else {
                    $ebMap[$fid][$r['detail_key']] = $r['detail_value'];
                }
            }
        }

        // All supplier transactions for fulfillments in this group
        $supIds = array_unique(array_filter(array_map(fn($r) => (int)($r['supplier_id'] ?? 0), $allFfs)));
        // Also collect supplier IDs from city_* detail values
        foreach ($cityMap as $fid => $cd) {
            foreach (['city_makkah_supplier_id', 'city_madinah_supplier_id'] as $k) {
                if (!empty($cd[$k])) $supIds[] = (int)$cd[$k];
            }
        }
        $supIds = array_unique($supIds);
        $txMap = [];
        if ($supIds) {
            $allBookings = array_unique(array_map(fn($r) => (int)$r['booking_id'], $allFfs));
            $ph2 = implode(',', array_fill(0, count($supIds), '?'));
            $ph3 = implode(',', array_fill(0, count($allBookings), '?'));
            $txStmt = $pdo->prepare("
                SELECT st.*, s.name AS supplier_name, s.balance AS supplier_balance, s.supplier_type, st.reference_id AS tx_booking_id
                FROM supplier_transactions st
                LEFT JOIN suppliers s ON s.id = st.supplier_id
                WHERE st.supplier_id IN ($ph2) AND st.reference_id IN ($ph3) AND st.transaction_of = 'umrah' AND st.tenant_id = ?
                ORDER BY st.id
            ");
            $txStmt->execute(array_merge($supIds, $allBookings, [$tenant_id]));
            foreach ($txStmt->fetchAll(PDO::FETCH_ASSOC) as $tx) {
                $txBookingId = (int)$tx['tx_booking_id'];
                $txSupId = (int)$tx['supplier_id'];
                $txMap[$txBookingId][$txSupId][] = $tx;
            }
        }

        // Supplier info map
        $supMap = [];
        if ($supIds) {
            $ph = implode(',', array_fill(0, count($supIds), '?'));
            $supStmt = $pdo->prepare("SELECT id, name, balance, supplier_type, currency FROM suppliers WHERE id IN ($ph) AND tenant_id = ?");
            $supStmt->execute(array_merge($supIds, [$tenant_id]));
            foreach ($supStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $supMap[(int)$r['id']] = $r;
            }
        }

        // All active suppliers for dropdowns
        $allSupStmt = $pdo->prepare("SELECT id, name, currency, supplier_type FROM suppliers WHERE tenant_id = ? AND status = 'active' ORDER BY name");
        $allSupStmt->execute([$tenant_id]);
        $allSuppliers = $allSupStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build per-family data
        $groupData = ['group' => $groupInfo, 'families' => [], 'suppliers' => $supMap, 'all_suppliers' => $allSuppliers];
        $gTotals = ['cost' => 0, 'sold' => 0, 'profit' => 0, 'members' => 0, 'extra_beds' => 0];

        // Index fulfillments by booking_id
        $ffByBooking = [];
        foreach ($allFfs as $r) {
            $bid = (int)$r['booking_id'];
            $ffByBooking[$bid][] = $r;
        }

        foreach ($families as $fam) {
            $famId = (int)$fam['family_id'];
            // All members in this family (including extra beds)
            $memStmt = $pdo->prepare("
                SELECT booking_id, name, is_extra_bed, is_extra_transport, status, sold_price, due, profit, currency
                FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND status NOT IN ('refunded','cancelled')
                ORDER BY is_extra_bed ASC, is_extra_transport ASC, booking_id
            ");
            $memStmt->execute([$famId, $tenant_id]);
            $members = $memStmt->fetchAll(PDO::FETCH_ASSOC);

            $famAudit = ['family' => $fam, 'members' => [], 'totals' => ['cost' => 0, 'sold' => 0, 'profit' => 0]];

            foreach ($members as $mem) {
                $bid = (int)$mem['booking_id'];
                $isEb = !empty($mem['is_extra_bed']);
                $isEt = !empty($mem['is_extra_transport']);
                $memFfs = $ffByBooking[$bid] ?? [];

                $memData = [
                    'booking' => $mem,
                    'is_extra_bed' => $isEb,
                    'is_extra_transport' => $isEt,
                    'fulfillments' => [],
                ];

                foreach ($memFfs as $ffRow) {
                    $fid = (int)$ffRow['id'];
                    $supId = (int)($ffRow['supplier_id'] ?? 0);
                    $memData['fulfillments'][] = [
                        'fulfillment' => $ffRow,
                        'city_details' => $cityMap[$fid] ?? [],
                        'eb_details' => $ebMap[$fid] ?? [],
                        'transactions' => $txMap[$bid][$supId] ?? [],
                    ];
                }

                // Compute cost for this member
                $memCost = 0;
                foreach ($memData['fulfillments'] as $fd) {
                    $cd = $fd['city_details'];
                    if (!empty($cd)) {
                        $memCost += (float)($cd['city_makkah_cost_amount'] ?? 0);
                        $memCost += (float)($cd['city_madinah_cost_amount'] ?? 0);
                    } else {
                        // Fallback: use fulfillment's own cost_amount
                        $memCost += (float)($fd['fulfillment']['cost_amount'] ?? 0);
                    }
                }
                $memData['computed_cost'] = $memCost;

                $famAudit['members'][] = $memData;
                $famAudit['totals']['cost'] += $memCost;
                $famAudit['totals']['sold'] += (float)($mem['sold_price'] ?? 0);
                $famAudit['totals']['profit'] += (float)($mem['profit'] ?? 0);
            }

            $gTotals['cost'] += $famAudit['totals']['cost'];
            $gTotals['sold'] += $famAudit['totals']['sold'];
            $gTotals['profit'] += $famAudit['totals']['profit'];
            foreach ($famAudit['members'] as $m) {
                if ($m['is_extra_bed'] || $m['is_extra_transport']) $gTotals['extra_beds']++;
                else $gTotals['members']++;
            }

            $groupData['families'][] = $famAudit;
        }
        $groupData['totals'] = $gTotals;
    }
}

include '../includes/header.php';
?>

<style>
:root{--abg:#f1f5f9;--as:#fff;--ab:#e2e8f0;--at:#1e293b;--am:#64748b;--aac:#0e7490;--amk:#0e7490;--amd:#be185d;--asuc:#16a34a;--aw:#d97706;--ad:#dc2626}
.ap{background:var(--abg);min-height:100vh;padding:16px;font-family:'Inter',sans-serif;color:var(--at)}
.ap .card{background:var(--as);border:1px solid var(--ab);border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:12px}
.ap .ch{background:transparent;border-bottom:1px solid var(--ab);padding:10px 14px;font-weight:600;font-size:.83rem;display:flex;justify-content:space-between;align-items:center}
.ap .cb{padding:12px 14px}
.b{display:inline-block;padding:2px 7px;border-radius:4px;font-size:.66rem;font-weight:600;text-transform:uppercase}
.bmk{background:#e0f2fe;color:var(--amk)}.bmd{background:#fce7f3;color:var(--amd)}.bok{background:#dcfce7;color:var(--asuc)}.bwr{background:#fef3c7;color:var(--aw)}.ber{background:#fee2e2;color:var(--ad)}
.at{width:100%;border-collapse:collapse;font-size:.76rem}
.at th{background:#f8fafc;color:var(--am);font-weight:600;text-transform:uppercase;font-size:.66rem;letter-spacing:.04em;padding:6px 8px;border-bottom:2px solid var(--ab);text-align:left}
.at td{padding:5px 8px;border-bottom:1px solid var(--ab);vertical-align:top}
.at tr:last-child td{border-bottom:none}
.at .n{text-align:right;font-variant-numeric:tabular-nums}
.cs{border-left:3px solid var(--amk);padding-left:8px;margin-bottom:8px}
.cs.md{border-left-color:var(--amd)}
.cl{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
.cl.mk{color:var(--amk)}.cl.md{color:var(--amd)}
.akv{display:grid;grid-template-columns:90px 1fr;gap:2px 6px;font-size:.76rem}
.akv dt{color:var(--am);font-weight:500}.akv dd{font-weight:600;margin:0}
.asum{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px}
.asc{background:var(--as);border:1px solid var(--ab);border-radius:10px;padding:12px;text-align:center}
.asc .l{font-size:.66rem;color:var(--am);text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
.asc .v{font-size:1.2rem;font-weight:700}
.asc .s{font-size:.66rem;color:var(--am);margin-top:2px}
.af{background:var(--as);border:1px solid var(--ab);border-radius:10px;padding:14px;margin-bottom:14px}
.af label{font-size:.78rem;font-weight:600;color:var(--am)}
.af select{border:1px solid var(--ab);border-radius:6px;padding:5px 10px;font-size:.83rem;min-width:300px}
.af .btn{background:var(--aac);color:#fff;border:none;border-radius:6px;padding:6px 16px;font-weight:600;font-size:.83rem}
.ae{text-align:center;padding:40px;color:var(--am)}.ae i{font-size:2rem;margin-bottom:8px;display:block}
.fc{border:1px solid var(--ab);border-radius:10px;margin-bottom:14px;overflow:hidden}
.fh{background:#f8fafc;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--ab);cursor:pointer}
.fh h6{margin:0;font-size:.83rem}
.ft{display:flex;gap:14px;font-size:.76rem}
.ft span{white-space:nowrap}
.mr{padding:10px 14px;border-bottom:1px solid #f1f5f9}
.mr:last-child{border-bottom:none}
.mr.eb{background:#fffbeb}
.mn{font-weight:600;font-size:.82rem}
.mt{font-size:.66rem;color:var(--am);margin-left:5px}
.mt.xb{color:var(--aw);font-weight:600}
.ms{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:4px;font-size:.66rem;font-weight:600}
.ms.ok{background:#dcfce7;color:var(--asuc)}.ms.no{background:#fef3c7;color:var(--aw)}
.sc{border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px;margin-bottom:8px;background:#fafbfc}
.sv{display:flex;justify-content:space-between;align-items:center;margin-bottom:5px}
.sv span{font-size:.76rem;font-weight:600}
.sv .sd{color:var(--am);font-weight:400;font-size:.72rem;margin-left:4px}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="ap">

                            <div class="enhanced-page-header">
                                <div class="container-fluid">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="page-title-wrapper">
                                                <i class="fas fa-balance-scale page-icon" style="color:var(--aac)"></i>
                                                <div>
                                                    <h2 class="page-title">Fulfillment & Supplier Audit</h2>
                                                    <p class="page-subtitle">Full group audit — members, extra beds, per-city costs, supplier transactions</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="af">
                                <form method="get" style="display:flex;gap:12px;align-items:end;">
                                    <div>
                                        <label>Umrah Group</label><br>
                                        <select name="group_id">
                                            <option value="0">— Select Group —</option>
                                            <?php foreach ($groups as $g): ?>
                                            <option value="<?= $g['group_id'] ?>" <?= $groupId == $g['group_id'] ? 'selected' : '' ?>>
                                                #<?= htmlspecialchars($g['group_number']) ?> — <?= htmlspecialchars($g['group_name']) ?>
                                                (<?= $g['member_count'] ?> members)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div><button type="submit" class="btn"><i class="fas fa-search mr-1"></i> Load Audit</button></div>
                                </form>
                            </div>

                            <?php if (!$groupId): ?>
                                <div class="ae"><i class="fas fa-layer-group"></i><p>Select an <strong>Umrah Group</strong> to see all members, fulfillments, extra beds, and supplier transactions.</p></div>

                            <?php elseif (!$groupData): ?>
                                <div class="ae"><i class="fas fa-exclamation-triangle" style="color:var(--aw)"></i><p>Group not found.</p></div>

                            <?php else:
                                $gi = $groupData['group'];
                                $gt = $groupData['totals'];
                                $supMap = $groupData['suppliers'];
                            ?>
                            <!-- Group Summary -->
                            <div class="asum">
                                <div class="asc"><div class="l">Group</div><div class="v" style="font-size:1rem;">#<?= htmlspecialchars($gi['group_number']) ?></div><div class="s"><?= htmlspecialchars($gi['group_name']) ?></div></div>
                                <div class="asc"><div class="l">Members</div><div class="v"><?= $gt['members'] ?></div><div class="s">+ <?= $gt['extra_beds'] ?> extra beds</div></div>
                                <div class="asc"><div class="l">Families</div><div class="v"><?= count($groupData['families']) ?></div></div>
                                <div class="asc"><div class="l">Total Cost</div><div class="v" style="color:var(--aac);">$<?= number_format($gt['cost'], 2) ?></div><div class="s">Makkah + Madinah</div></div>
                                <div class="asc"><div class="l">Total Sold</div><div class="v">$<?= number_format($gt['sold'], 2) ?></div></div>
                                <div class="asc"><div class="l">Total Profit</div><div class="v" style="color:<?= $gt['profit'] >= 0 ? 'var(--asuc)' : 'var(--ad)' ?>">$<?= number_format($gt['profit'], 2) ?></div></div>
                            </div>

                            <!-- Supplier Overview -->
                            <?php if ($supMap): ?>
                            <div class="card">
                                <div class="ch"><span><i class="fas fa-building" style="margin-right:6px;color:var(--aac)"></i> <strong>Suppliers in This Group</strong></span></div>
                                <div class="cb" style="padding:0;">
                                    <table class="at">
                                        <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Currency</th><th class="n">Balance</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($supMap as $sid => $s): ?>
                                        <tr>
                                            <td>#<?= $sid ?></td>
                                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                            <td><?= htmlspecialchars($s['supplier_type']) ?></td>
                                            <td><?= htmlspecialchars($s['currency'] ?? '') ?></td>
                                            <td class="n" style="font-weight:700;color:<?= (float)$s['balance'] >= 0 ? 'var(--asuc)' : 'var(--ad)' ?>;font-size:.85rem;">
                                                $<?= number_format((float)$s['balance'], 2) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Per Family -->
                            <?php foreach ($groupData['families'] as $fa):
                                $fm = $fa['family'];
                                $ft = $fa['totals'];
                            ?>
                            <div class="fc">
                                <div class="fh">
                                    <h6><i class="fas fa-home" style="color:var(--aac);margin-right:6px;"></i> <?= htmlspecialchars($fm['head_of_family']) ?>
                                        <span style="color:var(--am);font-weight:400;font-size:.76rem;margin-left:6px;">(<?= count($fa['members']) ?> members)</span>
                                    </h6>
                                    <div class="ft">
                                        <span>Cost: <strong style="color:var(--aac);">$<?= number_format($ft['cost'], 2) ?></strong></span>
                                        <span>Sold: <strong>$<?= number_format($ft['sold'], 2) ?></strong></span>
                                        <span>Profit: <strong style="color:<?= $ft['profit'] >= 0 ? 'var(--asuc)' : 'var(--ad)' ?>">$<?= number_format($ft['profit'], 2) ?></strong></span>
                                    </div>
                                </div>

                                <?php foreach ($fa['members'] as $ma):
                                    $bk = $ma['booking'];
                                    $isEb = $ma['is_extra_bed'];
                                    $isEt = $ma['is_extra_transport'];
                                    $memCost = $ma['computed_cost'];
                                ?>
                                <div class="mr <?= $isEb || $isEt ? 'eb' : '' ?>">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                        <div>
                                            <span class="mn"><?= htmlspecialchars($bk['name']) ?></span>
                                            <?php if ($isEb): ?><span class="mt xb">EXTRA BED</span><?php endif; ?>
                                            <?php if ($isEt): ?><span class="mt xb">EXTRA TRANSPORT</span><?php endif; ?>
                                            <span style="color:var(--am);font-size:.72rem;margin-left:5px;">Booking #<?= $bk['booking_id'] ?></span>
                                            <span class="b <?= ($bk['bk_status'] ?? $bk['status']) === 'active' ? 'bok' : 'bwr' ?>" style="margin-left:5px;"><?= $bk['bk_status'] ?? $bk['status'] ?></span>
                                        </div>
                                        <div style="display:flex;gap:14px;font-size:.76rem;">
                                            <span>Cost: <strong style="color:var(--aac);">$<?= number_format($memCost, 2) ?></strong></span>
                                            <span>Sold: <strong>$<?= number_format((float)($bk['sold_price'] ?? 0), 2) ?></strong></span>
                                            <span>Profit: <strong style="color:<?= ((float)($bk['profit'] ?? 0)) >= 0 ? 'var(--asuc)' : 'var(--ad)' ?>">$<?= number_format((float)($bk['profit'] ?? 0), 2) ?></strong></span>
                                        </div>
                                    </div>

                                    <?php if (empty($ma['fulfillments'])): ?>
                                        <div style="font-size:.76rem;color:var(--am);padding:4px 0;">No hotel fulfillments yet.</div>
                                    <?php endif; ?>

                                    <?php foreach ($ma['fulfillments'] as $fd):
                                        $ff = $fd['fulfillment'];
                                        $cd = $fd['city_details'];
                                        $hasCd = !empty($cd);
                                        $migOk = $hasCd;
                                        $txns = $fd['transactions'];
                                        $supId = (int)($ff['supplier_id'] ?? 0);
                                        $sup = $supMap[$supId] ?? null;
                                    ?>
                                    <div class="sc">
                                        <div class="sv">
                                            <span>
                                                Hotel <?= $ff['service_name'] ? '— ' . htmlspecialchars($ff['service_name']) : '' ?>
                                                <span class="sd">Fulfillment #<?= $ff['id'] ?></span>
                                            </span>
                                            <span class="ms <?= $migOk ? 'ok' : 'no' ?>">
                                                <i class="fas <?= $migOk ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                                                <?= $migOk ? 'Per-City' : 'Legacy' ?>
                                            </span>
                                        </div>

                                        <?php if ($hasCd): ?>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">
                                            <div class="cs">
                                                <div class="cl mk"><i class="fas fa-map-pin"></i> Makkah</div>
                                                <dl class="akv">
                                                    <dt>Supplier</dt>
                                                    <dd>
                                                        <select class="form-control form-control-sm aud-sup-select" data-ff-id="<?= $ff['id'] ?>" data-city="makkah" style="font-size:.76rem;padding:2px 6px;height:auto;">
                                                            <option value="">— Select —</option>
                                                            <?php foreach ($groupData['all_suppliers'] as $asup): ?>
                                                            <option value="<?= $asup['id'] ?>" <?= (int)($cd['city_makkah_supplier_id'] ?? 0) === (int)$asup['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($asup['name']) ?> (<?= htmlspecialchars($asup['currency'] ?? '') ?>)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </dd>
                                                    <dt>Cost</dt><dd><?= ($cd['city_makkah_cost'] ?? '') !== '' ? $cd['city_makkah_cost'] . ' ' . htmlspecialchars($cd['city_makkah_currency'] ?? '') : '—' ?></dd>
                                                    <dt>Rate</dt><dd><?= $cd['city_makkah_rate'] ?: '—' ?></dd>
                                                    <dt>USD</dt><dd><?= ($cd['city_makkah_cost_amount'] ?? '') !== '' ? '$' . number_format((float)$cd['city_makkah_cost_amount'], 2) : '—' ?></dd>
                                                </dl>
                                            </div>
                                            <div class="cs md">
                                                <div class="cl md"><i class="fas fa-map-pin"></i> Madinah</div>
                                                <dl class="akv">
                                                    <dt>Supplier</dt>
                                                    <dd>
                                                        <select class="form-control form-control-sm aud-sup-select" data-ff-id="<?= $ff['id'] ?>" data-city="madinah" style="font-size:.76rem;padding:2px 6px;height:auto;">
                                                            <option value="">— Select —</option>
                                                            <?php foreach ($groupData['all_suppliers'] as $asup): ?>
                                                            <option value="<?= $asup['id'] ?>" <?= (int)($cd['city_madinah_supplier_id'] ?? 0) === (int)$asup['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($asup['name']) ?> (<?= htmlspecialchars($asup['currency'] ?? '') ?>)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </dd>
                                                    <dt>Cost</dt><dd><?= ($cd['city_madinah_cost'] ?? '') !== '' ? $cd['city_madinah_cost'] . ' ' . htmlspecialchars($cd['city_madinah_currency'] ?? '') : '—' ?></dd>
                                                    <dt>Rate</dt><dd><?= $cd['city_madinah_rate'] ?: '—' ?></dd>
                                                    <dt>USD</dt><dd><?= ($cd['city_madinah_cost_amount'] ?? '') !== '' ? '$' . number_format((float)$cd['city_madinah_cost_amount'], 2) : '—' ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                        <div style="text-align:right;margin-bottom:6px;">
                                            <button type="button" class="btn btn-sm btn-aud-save" data-ff-id="<?= $ff['id'] ?>" style="background:var(--aac);color:#fff;border:none;border-radius:4px;padding:3px 12px;font-size:.74rem;font-weight:600;">
                                                <i class="fas fa-save"></i> Save Suppliers
                                            </button>
                                            <span class="aud-save-msg" data-ff-id="<?= $ff['id'] ?>" style="font-size:.72rem;margin-left:8px;"></span>
                                        </div>
                                        <?php else: ?>
                                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;font-size:.76rem;margin-bottom:6px;">
                                            <div><span style="color:var(--am)">Supplier:</span> <?= htmlspecialchars($sup['name'] ?? ($ff['supplier_id'] ?? '—')) ?></div>
                                            <div><span style="color:var(--am)">Currency:</span> <?= htmlspecialchars($ff['supplier_currency'] ?? '—') ?></div>
                                            <div><span style="color:var(--am)">Cost:</span> <strong><?= $ff['supplier_cost'] !== null ? number_format((float)$ff['supplier_cost'], 2) : '—' ?></strong></div>
                                            <div><span style="color:var(--am)">USD:</span> <strong><?= $ff['cost_amount'] !== null ? '$' . number_format((float)$ff['cost_amount'], 2) : '—' ?></strong></div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($txns): ?>
                                        <table class="at" style="margin-top:4px;">
                                            <thead><tr><th>Supplier</th><th>Type</th><th class="n">Amount</th><th class="n">Balance After</th><th>Remarks</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($txns as $tx): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tx['supplier_name'] ?? '') ?></td>
                                                <td><span class="b <?= $tx['transaction_type'] === 'Debit' ? 'ber' : 'bok' ?>"><?= $tx['transaction_type'] ?></span></td>
                                                <td class="n" style="color:<?= $tx['transaction_type'] === 'Debit' ? 'var(--ad)' : 'var(--asuc)' ?>;font-weight:600;"><?= $tx['transaction_type'] === 'Debit' ? '−' : '+' ?><?= number_format((float)$tx['amount'], 2) ?></td>
                                                <td class="n"><?= number_format((float)$tx['balance'], 2) ?></td>
                                                <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($tx['remarks'] ?? '') ?>"><?= htmlspecialchars($tx['remarks'] ?? '') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
document.querySelectorAll('.btn-aud-save').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var ffId = parseInt(this.dataset.ffId);
        var card = this.closest('.sc');
        var makSel = card.querySelector('.aud-sup-select[data-city="makkah"]');
        var madSel = card.querySelector('.aud-sup-select[data-city="madinah"]');
        var msg = card.querySelector('.aud-save-msg[data-ff-id="' + ffId + '"]');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        msg.textContent = '';

        fetch('../api/umrah/save_fulfillment_suppliers.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                fulfillment_id: ffId,
                makkah_supplier_id: makSel ? makSel.value : '',
                madinah_supplier_id: madSel ? madSel.value : ''
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Suppliers';
            if (res.success) {
                msg.style.color = '#16a34a';
                msg.textContent = 'Saved!';
                setTimeout(function() { msg.textContent = ''; }, 2000);
            } else {
                msg.style.color = '#dc2626';
                msg.textContent = res.message || 'Error';
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Suppliers';
            msg.style.color = '#dc2626';
            msg.textContent = 'Network error';
        });
    });
});
</script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
