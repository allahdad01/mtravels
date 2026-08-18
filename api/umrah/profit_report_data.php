<?php
/**
 * Shared data layer for the Umrah profit report
 * (printable template + Excel export).
 *
 * Requires $pdo, $tenant_id, $branch_id already set by the caller
 * (the caller enforces authentication).
 *
 * Usage:
 *   $data = profit_report_load($pdo, $tenant_id, $branch_id, $scope, $id);
 *   $data = [
 *       'scope'      => 'group'|'family'|'member',
 *       'scope_id'   => int,
 *       'title_name' => string (group name / head of family / member name),
 *       'members'    => [
 *           [
 *               'booking_id', 'family_id', 'name', 'fname', 'gender',
 *               'passport_number', 'room_type', 'duration',
 *               'sold_price', 'discount', 'currency', 'status',
 *               'head_of_family', 'client_name',
 *               'services' => [ ['label' => string, 'cost' => float], ... ],
 *               'brn_cost'   => float,
 *               'cost_total' => float,
 *               'sold_total' => float (sold_price - discount),
 *               'profit'     => float,
 *           ], ...
 *       ],
 *       'cost_total', 'sold_total', 'profit_total' (scope-wide),
 *   ];
 */

if (!function_exists('profit_report_load')) {
    function profit_report_load($pdo, $tenant_id, $branch_id, $scope, $id) {
        $scope = in_array($scope, ['group', 'family', 'member'], true) ? $scope : 'member';
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $titleName = '';
        $memberIds = [];

        if ($scope === 'member') {
            $st = $pdo->prepare("SELECT booking_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
            $st->execute([$id, $tenant_id, $branch_id]);
            $b = $st->fetch(PDO::FETCH_ASSOC);
            if (!$b) {
                return null;
            }
            $memberIds = [(int)$b['booking_id']];
        } elseif ($scope === 'family') {
            $st = $pdo->prepare("SELECT head_of_family FROM families WHERE family_id = ? AND tenant_id = ?");
            $st->execute([$id, $tenant_id]);
            $f = $st->fetch(PDO::FETCH_ASSOC);
            if (!$f) {
                return null;
            }
            $titleName = (string)($f['head_of_family'] ?? '');
            $st = $pdo->prepare("SELECT booking_id FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND status NOT IN ('refunded', 'cancelled') ORDER BY booking_id");
            $st->execute([$id, $tenant_id, $branch_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $memberIds[] = (int)$r['booking_id'];
            }
        } else {
            $st = $pdo->prepare("SELECT group_name FROM umrah_groups WHERE group_id = ? AND tenant_id = ?");
            $st->execute([$id, $tenant_id]);
            $g = $st->fetch(PDO::FETCH_ASSOC);
            if (!$g) {
                return null;
            }
            $titleName = (string)($g['group_name'] ?? '');
            $st = $pdo->prepare("
                SELECT b.booking_id
                FROM umrah_bookings b
                JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
                WHERE f.group_id = ? AND b.tenant_id = ? AND b.branch_id = ?
                  AND b.status NOT IN ('refunded', 'cancelled')
                ORDER BY b.booking_id");
            $st->execute([$id, $tenant_id, $branch_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $memberIds[] = (int)$r['booking_id'];
            }
        }

        if (empty($memberIds)) {
            return null;
        }
        $memberIds = array_values(array_unique($memberIds));

        $ph = implode(',', array_fill(0, count($memberIds), '?'));

        $mStmt = $pdo->prepare("
            SELECT b.booking_id, b.family_id, b.name, b.fname, b.gender, b.passport_number,
                   b.room_type, b.duration, b.sold_price, b.discount, b.currency, b.status,
                   f.head_of_family, c.name AS client_name
            FROM umrah_bookings b
            LEFT JOIN families f ON f.family_id = b.family_id AND f.tenant_id = b.tenant_id
            LEFT JOIN clients c ON c.id = b.sold_to
            WHERE b.booking_id IN ($ph) AND b.tenant_id = ? AND b.branch_id = ?
            ORDER BY b.booking_id");
        $mStmt->execute(array_merge($memberIds, [$tenant_id, $branch_id]));
        $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($members)) {
            return null;
        }

        // Per service line: frozen fulfillment cost in booking currency;
        // falls back to the line's base_price when nothing is fulfilled yet.
        $svcStmt = $pdo->prepare("
            SELECT bs.booking_id, bs.id AS line_id, bs.service_type, bs.service_id,
                   s.name AS service_name, c.name AS category_name,
                   COALESCE(
                       (SELECT SUM(COALESCE(f2.cost_amount, 0)) FROM umrah_fulfillments f2
                        WHERE f2.booking_service_id = bs.id),
                       bs.base_price
                   ) AS line_cost
            FROM umrah_booking_services bs
            LEFT JOIN umrah_services s ON bs.service_id = s.id
            LEFT JOIN umrah_service_categories c ON s.category_id = c.id
            WHERE bs.booking_id IN ($ph) AND bs.tenant_id = ?
            ORDER BY bs.booking_id, bs.id");
        $svcStmt->execute(array_merge($memberIds, [$tenant_id]));
        $svcRows = $svcStmt->fetchAll(PDO::FETCH_ASSOC);

        // BRN procurement cost per booking (booking currency).
        $brnStmt = $pdo->prepare("
            SELECT booking_id, COALESCE(SUM(COALESCE(cost_amount, 0)), 0) AS brn_cost
            FROM umrah_brn_costs
            WHERE booking_id IN ($ph) AND tenant_id = ?
            GROUP BY booking_id");
        $brnStmt->execute(array_merge($memberIds, [$tenant_id]));
        $brnMap = [];
        foreach ($brnStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $brnMap[(int)$r['booking_id']] = (float)$r['brn_cost'];
        }

        $svcByBooking = [];
        foreach ($svcRows as $r) {
            $svcByBooking[(int)$r['booking_id']][] = $r;
        }

        $costTotal = 0.0;
        $soldTotal = 0.0;
        $profitTotal = 0.0;

        foreach ($members as &$m) {
            $services = [];
            foreach (($svcByBooking[(int)$m['booking_id']] ?? []) as $r) {
                $label = (string)($r['service_name'] ?? '');
                if ($label === '') {
                    $label = (string)($r['category_name'] ?? '');
                }
                if ($label === '') {
                    $label = (string)($r['service_type'] ?? '');
                }
                $cost = (float)($r['line_cost'] ?? 0);
                $services[] = ['label' => $label, 'cost' => $cost];
            }
            $brn = (float)($brnMap[(int)$m['booking_id']] ?? 0);
            $memberCost = 0.0;
            foreach ($services as $s) {
                $memberCost += $s['cost'];
            }
            $memberCost += $brn;
            $memberSold = (float)($m['sold_price'] ?? 0) - (float)($m['discount'] ?? 0);

            $m['services'] = $services;
            $m['brn_cost'] = $brn;
            $m['cost_total'] = $memberCost;
            $m['sold_total'] = $memberSold;
            $m['profit'] = $memberSold - $memberCost;

            $costTotal += $memberCost;
            $soldTotal += $memberSold;
            $profitTotal += $m['profit'];
        }
        unset($m);

        return [
            'scope'       => $scope,
            'scope_id'    => $id,
            'title_name'  => $titleName,
            'members'     => $members,
            'cost_total'  => $costTotal,
            'sold_total'  => $soldTotal,
            'profit_total'=> $profitTotal,
        ];
    }
}