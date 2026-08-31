<?php
/**
 * Shared data layer for the Umrah Service-wise Profit Report.
 *
 * Requires $pdo, $tenant_id, $branch_id already set by the caller.
 *
 * Filters by fulfillment date (umrah_fulfillments.created_at).
 *
 * Usage:
 *   $data = service_report_load($pdo, $tenant_id, $branch_id, $dateFrom, $dateTo, $groupBy);
 *   $data = [
 *       'summary'  => [ 'total_cost' => float, 'total_members' => int, 'service_count' => int ],
 *       'services' => [ 'visa' => [...], 'hotel' => [...], ... ],
 *       'details'  => [ grouped by $groupBy ],
 *       'totals'   => [ 'cost' => float, 'members' => int ],
 *   ];
 */

if (!function_exists('service_report_load')) {
    function service_report_load($pdo, $tenant_id, $branch_id, $dateFrom = null, $dateTo = null, $groupBy = 'service') {
        $groupBy = in_array($groupBy, ['service', 'group', 'family'], true) ? $groupBy : 'service';

        // Default date range: last 12 months
        if (empty($dateFrom)) {
            $dateFrom = date('Y-m-d', strtotime('-12 months'));
        }
        if (empty($dateTo)) {
            $dateTo = date('Y-m-d');
        }
        // Add time for inclusive range
        $dateFromTs = $dateFrom . ' 00:00:00';
        $dateToTs = $dateTo . ' 23:59:59';

        // ── Core query: per member per service, filtered by fulfillment date ──
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                bs.service_type,
                COALESCE(s.name, bs.service_type) AS service_name,
                b.booking_id,
                b.name AS member_name,
                b.fname,
                b.passport_number,
                b.sold_price,
                b.discount,
                b.currency,
                b.paid,
                b.due,
                b.status AS booking_status,
                COALESCE(b.is_extra_bed, 0) AS is_extra_bed,
                COALESCE(b.is_extra_transport, 0) AS is_extra_transport,
                b.family_id,
                f.head_of_family,
                g.group_id,
                g.group_number,
                g.group_name,
                c.name AS client_name,
                COALESCE(
                    (SELECT SUM(COALESCE(f2.cost_amount, 0))
                     FROM umrah_fulfillments f2
                     WHERE f2.booking_service_id = bs.id AND f2.status != 'cancelled'),
                    bs.base_price
                ) AS service_cost
            FROM umrah_fulfillments ful
            JOIN umrah_booking_services bs ON ful.booking_service_id = bs.id AND bs.tenant_id = ful.tenant_id
            JOIN umrah_bookings b ON bs.booking_id = b.booking_id AND b.tenant_id = bs.tenant_id
            LEFT JOIN families f ON b.family_id = f.family_id AND f.tenant_id = b.tenant_id
            LEFT JOIN umrah_groups g ON f.group_id = g.group_id AND f.tenant_id = g.tenant_id
            LEFT JOIN clients c ON b.sold_to = c.id AND c.tenant_id = b.tenant_id
            LEFT JOIN umrah_services s ON bs.service_id = s.id
            WHERE ful.tenant_id = ?
              AND b.branch_id = ?
              AND ful.status != 'cancelled'
              AND b.status NOT IN ('refunded', 'cancelled')
              AND bs.is_excluded = 0
              AND ful.created_at BETWEEN ? AND ?
            ORDER BY bs.service_type, b.family_id, b.booking_id
        ");
        $stmt->execute([$tenant_id, $branch_id, $dateFromTs, $dateToTs]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'summary'   => ['total_cost' => 0, 'total_members' => 0, 'service_count' => 0, 'total_sold' => 0, 'total_profit' => 0],
                'services'  => [],
                'details'   => [],
                'totals'    => ['cost' => 0, 'members' => 0, 'sold' => 0, 'profit' => 0],
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'group_by'  => $groupBy,
            ];
        }

        // ── Deduplicate: same member can have multiple fulfillments per service type ──
        // Aggregate cost per booking per service_type
        $memberServiceMap = []; // [booking_id][service_type] = { cost, ... }
        $allBookingIds = [];
        foreach ($rows as $r) {
            $bid = (int)$r['booking_id'];
            $st = (string)$r['service_type'];
            $allBookingIds[$bid] = true;

            if (!isset($memberServiceMap[$bid][$st])) {
                $memberServiceMap[$bid][$st] = [
                    'booking_id'        => $bid,
                    'family_id'         => (int)$r['family_id'],
                    'head_of_family'    => (string)$r['head_of_family'],
                    'group_id'          => (int)($r['group_id'] ?? 0),
                    'group_number'      => (string)($r['group_number'] ?? ''),
                    'group_name'        => (string)($r['group_name'] ?? ''),
                    'client_name'       => (string)($r['client_name'] ?? ''),
                    'member_name'       => (string)$r['member_name'],
                    'fname'             => (string)$r['fname'],
                    'passport_number'   => (string)$r['passport_number'],
                    'sold_price'        => (float)$r['sold_price'],
                    'discount'          => (float)$r['discount'],
                    'currency'          => (string)$r['currency'],
                    'paid'              => (float)$r['paid'],
                    'due'               => (float)$r['due'],
                    'is_extra_bed'      => (int)$r['is_extra_bed'],
                    'is_extra_transport' => (int)$r['is_extra_transport'],
                    'service_type'      => $st,
                    'service_name'      => (string)$r['service_name'],
                    'service_cost'      => 0.0,
                ];
            }
            $memberServiceMap[$bid][$st]['service_cost'] += (float)$r['service_cost'];
        }

        // ── Flatten to member list ──
        $members = [];
        foreach ($memberServiceMap as $bid => $services) {
            foreach ($services as $svc) {
                $members[] = $svc;
            }
        }

        // ── Aggregate per service type ──
        $serviceAgg = [];
        foreach ($members as $m) {
            $st = $m['service_type'];
            if (!isset($serviceAgg[$st])) {
                $serviceAgg[$st] = [
                    'service_type'  => $st,
                    'service_name'  => $m['service_name'],
                    'total_cost'    => 0.0,
                    'member_count'  => 0,
                    'booking_ids'   => [],
                ];
            }
            $serviceAgg[$st]['total_cost'] += $m['service_cost'];
            $serviceAgg[$st]['member_count']++;
            $serviceAgg[$st]['booking_ids'][$m['booking_id']] = true;
        }
        // Fix member_count: count unique bookings per service
        foreach ($serviceAgg as &$svc) {
            $svc['member_count'] = count($svc['booking_ids']);
            unset($svc['booking_ids']);
        }
        unset($svc);

        // Sort by cost descending
        uasort($serviceAgg, function ($a, $b) {
            return $b['total_cost'] <=> $a['total_cost'];
        });

        // ── Group by group or family ──
        $details = [];
        if ($groupBy === 'group') {
            // Build unique member data per group
            $groupMembers = []; // [group_id] => [booking_ids], [services], etc.
            foreach ($members as $m) {
                $gid = $m['group_id'];
                if ($gid <= 0) $gid = 0; // unassigned
                if (!isset($groupMembers[$gid])) {
                    $groupMembers[$gid] = [
                        'group_id'    => $gid,
                        'group_number' => $m['group_number'],
                        'group_name'  => $m['group_name'],
                        'members'     => [],
                        'services'    => [],
                        'unique_members' => [],
                        'total_cost'  => 0.0,
                    ];
                }
                $groupMembers[$gid]['total_cost'] += $m['service_cost'];
                $groupMembers[$gid]['unique_members'][$m['booking_id']] = true;

                $st = $m['service_type'];
                if (!isset($groupMembers[$gid]['services'][$st])) {
                    $groupMembers[$gid]['services'][$st] = [
                        'service_type' => $st,
                        'service_name' => $m['service_name'],
                        'total_cost'   => 0.0,
                        'member_count' => 0,
                        'booking_ids'  => [],
                    ];
                }
                $groupMembers[$gid]['services'][$st]['total_cost'] += $m['service_cost'];
                $groupMembers[$gid]['services'][$st]['booking_ids'][$m['booking_id']] = true;
            }
            // Fix counts
            foreach ($groupMembers as &$grp) {
                $grp['member_count'] = count($grp['unique_members']);
                unset($grp['unique_members']);
                foreach ($grp['services'] as &$svc) {
                    $svc['member_count'] = count($svc['booking_ids']);
                    unset($svc['booking_ids']);
                }
                unset($svc);
            }
            unset($grp);

            // Sort by group number
            uasort($groupMembers, function ($a, $b) {
                return (int)($a['group_number'] ?: 0) <=> (int)($b['group_number'] ?: 0);
            });
            $details = $groupMembers;

        } elseif ($groupBy === 'family') {
            $familyMembers = [];
            foreach ($members as $m) {
                $fid = $m['family_id'];
                if (!isset($familyMembers[$fid])) {
                    $familyMembers[$fid] = [
                        'family_id'       => $fid,
                        'head_of_family'  => $m['head_of_family'],
                        'group_id'        => $m['group_id'],
                        'group_name'      => $m['group_name'],
                        'group_number'    => $m['group_number'],
                        'client_name'     => $m['client_name'],
                        'services'        => [],
                        'unique_members'  => [],
                        'total_cost'      => 0.0,
                    ];
                }
                $familyMembers[$fid]['total_cost'] += $m['service_cost'];
                $familyMembers[$fid]['unique_members'][$m['booking_id']] = true;

                $st = $m['service_type'];
                if (!isset($familyMembers[$fid]['services'][$st])) {
                    $familyMembers[$fid]['services'][$st] = [
                        'service_type' => $st,
                        'service_name' => $m['service_name'],
                        'total_cost'   => 0.0,
                        'member_count' => 0,
                        'booking_ids'  => [],
                    ];
                }
                $familyMembers[$fid]['services'][$st]['total_cost'] += $m['service_cost'];
                $familyMembers[$fid]['services'][$st]['booking_ids'][$m['booking_id']] = true;
            }
            foreach ($familyMembers as &$fam) {
                $fam['member_count'] = count($fam['unique_members']);
                unset($fam['unique_members']);
                foreach ($fam['services'] as &$svc) {
                    $svc['member_count'] = count($svc['booking_ids']);
                    unset($svc['booking_ids']);
                }
                unset($svc);
            }
            unset($fam);

            // Sort by group then family
            uasort($familyMembers, function ($a, $b) {
                $gCmp = (int)($a['group_number'] ?: 0) <=> (int)($b['group_number'] ?: 0);
                return $gCmp !== 0 ? $gCmp : $a['head_of_family'] <=> $b['head_of_family'];
            });
            $details = $familyMembers;
        } else {
            // 'service' grouping — just the service aggregation
            $details = array_values($serviceAgg);
        }

        // ── Grand totals ──
        $totalCost = 0.0;
        $totalMembers = count(array_unique(array_column($members, 'booking_id')));
        $totalSold = 0.0;
        foreach ($members as $m) {
            $totalCost += $m['service_cost'];
        }
        // Sum sold_price once per unique member
        $seenBookings = [];
        foreach ($members as $m) {
            $bid = $m['booking_id'];
            if (!isset($seenBookings[$bid])) {
                $seenBookings[$bid] = true;
                $totalSold += max(0, (float)$m['sold_price'] - (float)$m['discount']);
            }
        }

        return [
            'summary'   => [
                'total_cost'    => round($totalCost, 2),
                'total_members' => $totalMembers,
                'service_count' => count($serviceAgg),
                'total_sold'    => round($totalSold, 2),
                'total_profit'  => round($totalSold - $totalCost, 2),
            ],
            'services'  => array_values($serviceAgg),
            'details'   => $details,
            'totals'    => [
                'cost'    => round($totalCost, 2),
                'members' => $totalMembers,
                'sold'    => round($totalSold, 2),
                'profit'  => round($totalSold - $totalCost, 2),
            ],
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'group_by'  => $groupBy,
        ];
    }
}
