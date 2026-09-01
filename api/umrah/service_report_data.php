<?php
/**
 * Shared data layer for the Umrah Service Report.
 *
 * Requires $pdo, $tenant_id, $branch_id already set by the caller.
 *
 * Filters by fulfillment date (umrah_fulfillments.created_at).
 * Returns members with per-service costs, structured like the profit report.
 *
 * Usage:
 *   $data = service_report_load($pdo, $tenant_id, $branch_id, $dateFrom, $dateTo, $serviceTypes);
 *   $data = [
 *       'members'      => [ [booking_id, name, fname, passport_number, head_of_family,
 *                            client_name, is_extra_bed, is_extra_transport,
 *                            services => [{label, cost}], cost_total], ... ],
 *       'cost_total'   => float,
 *       'total_members' => int,
 *       'service_count' => int,
 *       'date_from'    => string,
 *       'date_to'      => string,
 *   ];
 */

if (!function_exists('service_report_load')) {
    function service_report_load($pdo, $tenant_id, $branch_id, $dateFrom = null, $dateTo = null, $serviceTypes = [], $groupId = null) {
        $serviceTypes = array_filter($serviceTypes, function ($st) {
            return in_array($st, ['visa', 'hotel', 'transport', 'flight', 'meal', 'ziyarat'], true);
        });
        $groupId = !empty($groupId) ? (int)$groupId : null;

        if (empty($dateFrom)) {
            $dateFrom = date('Y-m-d', strtotime('-12 months'));
        }
        if (empty($dateTo)) {
            $dateTo = date('Y-m-d');
        }
        $dateFromTs = $dateFrom . ' 00:00:00';
        $dateToTs = $dateTo . ' 23:59:59';

        // Core query: per member per service, filtered by fulfillment date
        $groupClause = '';
        if ($groupId !== null) {
            $groupClause = "AND f.group_id = ?";
        }
        $stmt = $pdo->prepare("
            SELECT
                bs.booking_id,
                bs.service_type,
                COALESCE(s.name, bs.service_type) AS service_name,
                b.name AS member_name,
                b.fname,
                b.passport_number,
                COALESCE(b.is_extra_bed, 0) AS is_extra_bed,
                COALESCE(b.is_extra_transport, 0) AS is_extra_transport,
                b.family_id,
                f.head_of_family,
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
            LEFT JOIN clients c ON b.sold_to = c.id AND c.tenant_id = b.tenant_id
            LEFT JOIN umrah_services s ON bs.service_id = s.id
            WHERE ful.tenant_id = ?
              AND b.branch_id = ?
              AND ful.status != 'cancelled'
              AND b.status NOT IN ('refunded', 'cancelled')
              AND bs.is_excluded = 0
              AND ful.created_at BETWEEN ? AND ?
              " . $groupClause . "
              " . (count($serviceTypes) > 0 ? "AND bs.service_type IN (" . implode(',', array_fill(0, count($serviceTypes), '?')) . ")" : "") . "
            ORDER BY b.family_id, b.booking_id, bs.service_type
        ");
        $executeParams = [$tenant_id, $branch_id, $dateFromTs, $dateToTs];
        if ($groupId !== null) {
            $executeParams[] = $groupId;
        }
        if (count($serviceTypes) > 0) {
            $executeParams = array_merge($executeParams, $serviceTypes);
        }
        $stmt->execute($executeParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'members'       => [],
                'cost_total'    => 0,
                'total_members' => 0,
                'service_count' => 0,
                'date_from'     => $dateFrom,
                'date_to'       => $dateTo,
            ];
        }

        // Aggregate cost per booking per service_type
        $memberServiceMap = []; // [booking_id][service_type] = { label, cost }
        $memberInfo = [];      // [booking_id] = { name, fname, ... }
        foreach ($rows as $r) {
            $bid = (int)$r['booking_id'];
            $st = (string)$r['service_type'];

            if (!isset($memberInfo[$bid])) {
                $memberInfo[$bid] = [
                    'booking_id'         => $bid,
                    'family_id'          => (int)$r['family_id'],
                    'head_of_family'     => (string)$r['head_of_family'],
                    'client_name'        => (string)($r['client_name'] ?? ''),
                    'name'               => (string)$r['member_name'],
                    'fname'              => (string)$r['fname'],
                    'passport_number'    => (string)$r['passport_number'],
                    'is_extra_bed'       => (int)$r['is_extra_bed'],
                    'is_extra_transport' => (int)$r['is_extra_transport'],
                ];
            }

            if (!isset($memberServiceMap[$bid][$st])) {
                $label = (string)($r['service_name'] ?: $r['service_type']);
                $memberServiceMap[$bid][$st] = ['label' => $label, 'cost' => 0.0];
            }
            $memberServiceMap[$bid][$st]['cost'] += (float)$r['service_cost'];
        }

        // Build flat member list with services
        $members = [];
        $costTotal = 0.0;
        $serviceTypesFound = [];
        foreach ($memberInfo as $bid => $info) {
            $services = [];
            $memberCost = 0.0;
            foreach ($memberServiceMap[$bid] as $st => $svc) {
                $services[] = $svc;
                $memberCost += $svc['cost'];
                $serviceTypesFound[$st] = true;
            }
            $info['services'] = $services;
            $info['cost_total'] = $memberCost;
            $members[] = $info;
            $costTotal += $memberCost;
        }

        return [
            'members'        => $members,
            'cost_total'     => round($costTotal, 2),
            'total_members'  => count($members),
            'service_count'  => count($serviceTypesFound),
            'date_from'      => $dateFrom,
            'date_to'        => $dateTo,
        ];
    }
}
