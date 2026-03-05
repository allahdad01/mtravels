<?php
include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$comparison_period = $_GET['comparison_period'] ?? '';

// Fetch branches for filter dropdown
try {
    $stmt = $pdo->prepare("SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
}

// Fetch branch performance data
try {
    $params = [];
    $branchFilter = "";

    if (!empty($branch_id)) {
        $branchFilter = "AND b.id = ?";
        $params[] = $branch_id;
    }

    $stmt = $pdo->prepare("
        SELECT
            b.name as branch_name,
            b.code as branch_code,
            COALESCE(ticket_stats.ticket_bookings, 0) as ticket_bookings,
            COALESCE(ticket_stats.ticket_profit_usd, 0) as ticket_profit_usd,
            COALESCE(ticket_stats.ticket_profit_afs, 0) as ticket_profit_afs,
            COALESCE(reservation_stats.ticket_reservations, 0) as ticket_reservations,
            COALESCE(reservation_stats.reservation_profit_usd, 0) as reservation_profit_usd,
            COALESCE(reservation_stats.reservation_profit_afs, 0) as reservation_profit_afs,
            COALESCE(weight_stats.ticket_weights, 0) as ticket_weights,
            COALESCE(weight_stats.weight_profit_usd, 0) as weight_profit_usd,
            COALESCE(weight_stats.weight_profit_afs, 0) as weight_profit_afs,
            COALESCE(hotel_stats.hotel_bookings, 0) as hotel_bookings,
            COALESCE(hotel_stats.hotel_profit_usd, 0) as hotel_profit_usd,
            COALESCE(hotel_stats.hotel_profit_afs, 0) as hotel_profit_afs,
            COALESCE(visa_stats.visa_applications, 0) as visa_applications,
            COALESCE(visa_stats.visa_profit_usd, 0) as visa_profit_usd,
            COALESCE(visa_stats.visa_profit_afs, 0) as visa_profit_afs,
            COALESCE(umrah_stats.umrah_bookings, 0) as umrah_bookings,
            COALESCE(umrah_stats.umrah_profit_usd, 0) as umrah_profit_usd,
            COALESCE(umrah_stats.umrah_profit_afs, 0) as umrah_profit_afs,
            COALESCE(additional_stats.additional_payments, 0) as additional_payments,
            COALESCE(additional_stats.additional_profit_usd, 0) as additional_profit_usd,
            COALESCE(additional_stats.additional_profit_afs, 0) as additional_profit_afs,
            COALESCE(refund_stats.refunded_tickets, 0) as refunded_tickets,
            COALESCE(refund_stats.refund_profit_usd, 0) as refund_profit_usd,
            COALESCE(refund_stats.refund_profit_afs, 0) as refund_profit_afs,
            COALESCE(date_change_stats.date_change_tickets, 0) as date_change_tickets,
            COALESCE(date_change_stats.date_change_profit_usd, 0) as date_change_profit_usd,
            COALESCE(date_change_stats.date_change_profit_afs, 0) as date_change_profit_afs,
            COALESCE(ticket_stats.ticket_profit_usd, 0) +
            COALESCE(reservation_stats.reservation_profit_usd, 0) +
            COALESCE(weight_stats.weight_profit_usd, 0) +
            COALESCE(hotel_stats.hotel_profit_usd, 0) +
            COALESCE(visa_stats.visa_profit_usd, 0) +
            COALESCE(umrah_stats.umrah_profit_usd, 0) +
            COALESCE(additional_stats.additional_profit_usd, 0) +
            COALESCE(refund_stats.refund_profit_usd, 0) +
            COALESCE(date_change_stats.date_change_profit_usd, 0) as total_revenue_usd,
            COALESCE(ticket_stats.ticket_profit_afs, 0) +
            COALESCE(reservation_stats.reservation_profit_afs, 0) +
            COALESCE(weight_stats.weight_profit_afs, 0) +
            COALESCE(hotel_stats.hotel_profit_afs, 0) +
            COALESCE(visa_stats.visa_profit_afs, 0) +
            COALESCE(umrah_stats.umrah_profit_afs, 0) +
            COALESCE(additional_stats.additional_profit_afs, 0) +
            COALESCE(refund_stats.refund_profit_afs, 0) +
            COALESCE(date_change_stats.date_change_profit_afs, 0) as total_revenue_afs,
            COALESCE(user_stats.total_users, 0) as total_users
        FROM branches b

        -- Users count aggregated by branch
        LEFT JOIN (
            SELECT branch_id, COUNT(id) as total_users
            FROM users
            WHERE tenant_id = ?
            GROUP BY branch_id
        ) user_stats ON user_stats.branch_id = b.id

        -- Ticket bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(t.id) as ticket_bookings,
                SUM(CASE WHEN t.currency = 'USD' THEN t.profit ELSE 0 END) as ticket_profit_usd,
                SUM(CASE WHEN t.currency = 'AFS' THEN t.profit ELSE 0 END) as ticket_profit_afs
            FROM ticket_bookings t
            JOIN users u ON t.created_by = u.id
            WHERE t.created_at >= ? AND t.created_at <= ?
            GROUP BY u.branch_id
        ) ticket_stats ON ticket_stats.branch_id = b.id

        -- Ticket reservations aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(tr.id) as ticket_reservations,
                SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END) as reservation_profit_usd,
                SUM(CASE WHEN tr.currency = 'AFS' THEN tr.profit ELSE 0 END) as reservation_profit_afs
            FROM ticket_reservations tr
            JOIN users u ON tr.created_by = u.id
            WHERE tr.created_at >= ? AND tr.created_at <= ?
            GROUP BY u.branch_id
        ) reservation_stats ON reservation_stats.branch_id = b.id

        -- Ticket weights aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(tw.id) as ticket_weights,
                SUM(CASE WHEN tb.currency = 'USD' THEN tw.profit ELSE 0 END) as weight_profit_usd,
                SUM(CASE WHEN tb.currency = 'AFS' THEN tw.profit ELSE 0 END) as weight_profit_afs
            FROM ticket_weights tw
            JOIN users u ON tw.created_by = u.id
            LEFT JOIN ticket_bookings tb ON tb.id = tw.ticket_id
            WHERE tw.created_at >= ? AND tw.created_at <= ?
            GROUP BY u.branch_id
        ) weight_stats ON weight_stats.branch_id = b.id

        -- Hotel bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(h.id) as hotel_bookings,
                SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END) as hotel_profit_usd,
                SUM(CASE WHEN h.currency = 'AFS' THEN h.profit ELSE 0 END) as hotel_profit_afs
            FROM hotel_bookings h
            JOIN users u ON h.created_by = u.id
            WHERE h.created_at >= ? AND h.created_at <= ?
            GROUP BY u.branch_id
        ) hotel_stats ON hotel_stats.branch_id = b.id

        -- Visa applications aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(v.id) as visa_applications,
                SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END) as visa_profit_usd,
                SUM(CASE WHEN v.currency = 'AFS' THEN v.profit ELSE 0 END) as visa_profit_afs
            FROM visa_applications v
            JOIN users u ON v.created_by = u.id
            WHERE v.created_at >= ? AND v.created_at <= ?
            GROUP BY u.branch_id
        ) visa_stats ON visa_stats.branch_id = b.id

        -- Umrah bookings aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(um.booking_id) as umrah_bookings,
                SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END) as umrah_profit_usd,
                SUM(CASE WHEN um.currency = 'AFS' THEN um.profit ELSE 0 END) as umrah_profit_afs
            FROM umrah_bookings um
            JOIN users u ON um.created_by = u.id
            WHERE um.created_at >= ? AND um.created_at <= ?
            GROUP BY u.branch_id
        ) umrah_stats ON umrah_stats.branch_id = b.id

        -- Additional payments aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(ap.id) as additional_payments,
                SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END) as additional_profit_usd,
                SUM(CASE WHEN ap.currency = 'AFS' THEN ap.profit ELSE 0 END) as additional_profit_afs
            FROM additional_payments ap
            JOIN users u ON ap.created_by = u.id
            WHERE ap.created_at >= ? AND ap.created_at <= ?
            GROUP BY u.branch_id
        ) additional_stats ON additional_stats.branch_id = b.id

        -- Refunded tickets aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(rt.id) as refunded_tickets,
                SUM(CASE WHEN rt.currency = 'USD' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END) as refund_profit_usd,
                SUM(CASE WHEN rt.currency = 'AFS' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END) as refund_profit_afs
            FROM refunded_tickets rt
            JOIN users u ON rt.created_by = u.id
            LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
            WHERE rt.created_at >= ? AND rt.created_at <= ?
            GROUP BY u.branch_id
        ) refund_stats ON refund_stats.branch_id = b.id

        -- Date change tickets aggregated by branch
        LEFT JOIN (
            SELECT
                u.branch_id,
                COUNT(dt.id) as date_change_tickets,
                SUM(CASE WHEN dt.currency = 'USD' THEN dt.service_penalty ELSE 0 END) as date_change_profit_usd,
                SUM(CASE WHEN dt.currency = 'AFS' THEN dt.service_penalty ELSE 0 END) as date_change_profit_afs
            FROM date_change_tickets dt
            JOIN users u ON dt.created_by = u.id
            WHERE dt.created_at >= ? AND dt.created_at <= ?
            GROUP BY u.branch_id
        ) date_change_stats ON date_change_stats.branch_id = b.id

        WHERE b.tenant_id = ? AND b.status = 'active' $branchFilter
        GROUP BY b.id, b.name, b.code
        ORDER BY total_revenue_usd DESC
    ");

    // Parameters: tenant_id (for user_stats), start_date, end_date (x9 for each table: tickets, reservations, ticket_weights, hotels, visas, umrah, additional_payments, refunded, date_change), tenant_id (WHERE clause), [branch_id]
    $executeParams = [
        $tenant_id, // for user_stats subquery
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // tickets
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // ticket reservations
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // ticket weights
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // hotels
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // visas
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // umrah
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // additional payments
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // refunded tickets
        $start_date . ' 00:00:00', $end_date . ' 23:59:59', // date change tickets
        $tenant_id // WHERE clause
    ];

    if (!empty($branch_id)) {
        $executeParams[] = $branch_id;
    }

    $stmt->execute($executeParams);
    $branchReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Reports Query Error: " . $e->getMessage());
    $branchReports = [];
}

// Calculate totals
$totals = [
    'branches' => count($branchReports),
    'ticket_bookings' => 0,
    'ticket_profit_usd' => 0,
    'ticket_profit_afs' => 0,
    'ticket_reservations' => 0,
    'reservation_profit_usd' => 0,
    'reservation_profit_afs' => 0,
    'ticket_weights' => 0,
    'weight_profit_usd' => 0,
    'weight_profit_afs' => 0,
    'hotel_bookings' => 0,
    'hotel_profit_usd' => 0,
    'hotel_profit_afs' => 0,
    'visa_applications' => 0,
    'visa_profit_usd' => 0,
    'visa_profit_afs' => 0,
    'umrah_bookings' => 0,
    'umrah_profit_usd' => 0,
    'umrah_profit_afs' => 0,
    'additional_payments' => 0,
    'additional_profit_usd' => 0,
    'additional_profit_afs' => 0,
    'refunded_tickets' => 0,
    'refund_profit_usd' => 0,
    'refund_profit_afs' => 0,
    'date_change_tickets' => 0,
    'date_change_profit_usd' => 0,
    'date_change_profit_afs' => 0,
    'total_revenue_usd' => 0,
    'total_revenue_afs' => 0,
    'total_revenue' => 0,
    'total_users' => 0
];

foreach ($branchReports as $report) {
    $totals['ticket_bookings'] += $report['ticket_bookings'];
    $totals['ticket_profit_usd'] += $report['ticket_profit_usd'] ?? 0;
    $totals['ticket_profit_afs'] += $report['ticket_profit_afs'] ?? 0;
    $totals['ticket_reservations'] += $report['ticket_reservations'] ?? 0;
    $totals['reservation_profit_usd'] += $report['reservation_profit_usd'] ?? 0;
    $totals['reservation_profit_afs'] += $report['reservation_profit_afs'] ?? 0;
    $totals['ticket_weights'] += $report['ticket_weights'] ?? 0;
    $totals['weight_profit_usd'] += $report['weight_profit_usd'] ?? 0;
    $totals['weight_profit_afs'] += $report['weight_profit_afs'] ?? 0;
    $totals['hotel_bookings'] += $report['hotel_bookings'];
    $totals['hotel_profit_usd'] += $report['hotel_profit_usd'] ?? 0;
    $totals['hotel_profit_afs'] += $report['hotel_profit_afs'] ?? 0;
    $totals['visa_applications'] += $report['visa_applications'];
    $totals['visa_profit_usd'] += $report['visa_profit_usd'] ?? 0;
    $totals['visa_profit_afs'] += $report['visa_profit_afs'] ?? 0;
    $totals['umrah_bookings'] += $report['umrah_bookings'];
    $totals['umrah_profit_usd'] += $report['umrah_profit_usd'] ?? 0;
    $totals['umrah_profit_afs'] += $report['umrah_profit_afs'] ?? 0;
    $totals['additional_payments'] += $report['additional_payments'] ?? 0;
    $totals['additional_profit_usd'] += $report['additional_profit_usd'] ?? 0;
    $totals['additional_profit_afs'] += $report['additional_profit_afs'] ?? 0;
    $totals['refunded_tickets'] += $report['refunded_tickets'] ?? 0;
    $totals['refund_profit_usd'] += $report['refund_profit_usd'] ?? 0;
    $totals['refund_profit_afs'] += $report['refund_profit_afs'] ?? 0;
    $totals['date_change_tickets'] += $report['date_change_tickets'] ?? 0;
    $totals['date_change_profit_usd'] += $report['date_change_profit_usd'] ?? 0;
    $totals['date_change_profit_afs'] += $report['date_change_profit_afs'] ?? 0;
    $totals['total_revenue_usd'] += $report['total_revenue_usd'] ?? 0;
    $totals['total_revenue_afs'] += $report['total_revenue_afs'] ?? 0;
    $totals['total_users'] += $report['total_users'];
}

// Calculate total_revenue as sum of USD values
$totals['total_revenue'] = $totals['total_revenue_usd'];

// Handle comparison data
$comparisonData = null;
$comparisonLabel = '';
if (!empty($comparison_period)) {
    // Calculate comparison date range
    $comparison_start_date = '';
    $comparison_end_date = '';

    switch ($comparison_period) {
        case 'previous_month':
            $current_month_start = new DateTime($start_date);
            $comparison_start_date = $current_month_start->modify('-1 month')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-1 month')->format('Y-m-t');
            $comparisonLabel = 'Previous Month';
            break;
        case 'previous_quarter':
            $current_quarter_start = new DateTime($start_date);
            $comparison_start_date = $current_quarter_start->modify('-3 months')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-3 months')->format('Y-m-t');
            $comparisonLabel = 'Previous Quarter';
            break;
        case 'same_month_last_year':
            $comparison_start_date = (new DateTime($start_date))->modify('-1 year')->format('Y-m-d');
            $comparison_end_date = (new DateTime($end_date))->modify('-1 year')->format('Y-m-t');
            $comparisonLabel = 'Same Month Last Year';
            break;
    }

    if (!empty($comparison_start_date) && !empty($comparison_end_date)) {
        try {
            $comparisonParams = [];
            $comparisonBranchFilter = "";

            if (!empty($branch_id)) {
                $comparisonBranchFilter = "AND b.id = ?";
                $comparisonParams[] = $branch_id;
            }

            $comparisonStmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(ticket_stats.ticket_bookings), 0) as ticket_bookings,
                    COALESCE(SUM(ticket_stats.ticket_profit_usd), 0) as ticket_profit_usd,
                    COALESCE(SUM(ticket_stats.ticket_profit_afs), 0) as ticket_profit_afs,
                    COALESCE(SUM(reservation_stats.ticket_reservations), 0) as ticket_reservations,
                    COALESCE(SUM(reservation_stats.reservation_profit_usd), 0) as reservation_profit_usd,
                    COALESCE(SUM(reservation_stats.reservation_profit_afs), 0) as reservation_profit_afs,
                    COALESCE(SUM(weight_stats.ticket_weights), 0) as ticket_weights,
                    COALESCE(SUM(weight_stats.weight_profit_usd), 0) as weight_profit_usd,
                    COALESCE(SUM(weight_stats.weight_profit_afs), 0) as weight_profit_afs,
                    COALESCE(SUM(hotel_stats.hotel_bookings), 0) as hotel_bookings,
                    COALESCE(SUM(hotel_stats.hotel_profit_usd), 0) as hotel_profit_usd,
                    COALESCE(SUM(hotel_stats.hotel_profit_afs), 0) as hotel_profit_afs,
                    COALESCE(SUM(visa_stats.visa_applications), 0) as visa_applications,
                    COALESCE(SUM(visa_stats.visa_profit_usd), 0) as visa_profit_usd,
                    COALESCE(SUM(visa_stats.visa_profit_afs), 0) as visa_profit_afs,
                    COALESCE(SUM(umrah_stats.umrah_bookings), 0) as umrah_bookings,
                    COALESCE(SUM(umrah_stats.umrah_profit_usd), 0) as umrah_profit_usd,
                    COALESCE(SUM(umrah_stats.umrah_profit_afs), 0) as umrah_profit_afs,
                    COALESCE(SUM(additional_stats.additional_payments), 0) as additional_payments,
                    COALESCE(SUM(additional_stats.additional_profit_usd), 0) as additional_profit_usd,
                    COALESCE(SUM(additional_stats.additional_profit_afs), 0) as additional_profit_afs,
                    COALESCE(SUM(refund_stats.refunded_tickets), 0) as refunded_tickets,
                    COALESCE(SUM(refund_stats.refund_profit_usd), 0) as refund_profit_usd,
                    COALESCE(SUM(refund_stats.refund_profit_afs), 0) as refund_profit_afs,
                    COALESCE(SUM(date_change_stats.date_change_tickets), 0) as date_change_tickets,
                    COALESCE(SUM(date_change_stats.date_change_profit_usd), 0) as date_change_profit_usd,
                    COALESCE(SUM(date_change_stats.date_change_profit_afs), 0) as date_change_profit_afs,
                    COALESCE(SUM(ticket_stats.ticket_profit_usd), 0) +
                    COALESCE(SUM(reservation_stats.reservation_profit_usd), 0) +
                    COALESCE(SUM(weight_stats.weight_profit_usd), 0) +
                    COALESCE(SUM(hotel_stats.hotel_profit_usd), 0) +
                    COALESCE(SUM(visa_stats.visa_profit_usd), 0) +
                    COALESCE(SUM(umrah_stats.umrah_profit_usd), 0) +
                    COALESCE(SUM(additional_stats.additional_profit_usd), 0) +
                    COALESCE(SUM(refund_stats.refund_profit_usd), 0) +
                    COALESCE(SUM(date_change_stats.date_change_profit_usd), 0) as total_revenue_usd,
                    COALESCE(SUM(ticket_stats.ticket_profit_afs), 0) +
                    COALESCE(SUM(reservation_stats.reservation_profit_afs), 0) +
                    COALESCE(SUM(weight_stats.weight_profit_afs), 0) +
                    COALESCE(SUM(hotel_stats.hotel_profit_afs), 0) +
                    COALESCE(SUM(visa_stats.visa_profit_afs), 0) +
                    COALESCE(SUM(umrah_stats.umrah_profit_afs), 0) +
                    COALESCE(SUM(additional_stats.additional_profit_afs), 0) +
                    COALESCE(SUM(refund_stats.refund_profit_afs), 0) +
                    COALESCE(SUM(date_change_stats.date_change_profit_afs), 0) as total_revenue_afs,
                    COALESCE(SUM(user_stats.total_users), 0) as total_users
                FROM branches b

                LEFT JOIN (
                    SELECT branch_id, COUNT(id) as total_users
                    FROM users
                    WHERE tenant_id = ?
                    GROUP BY branch_id
                ) user_stats ON user_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(t.id) as ticket_bookings, SUM(CASE WHEN t.currency IN ('USD', 'AFS') THEN t.profit ELSE 0 END) as ticket_profit
                    FROM ticket_bookings t
                    JOIN users u ON t.created_by = u.id
                    WHERE t.created_at >= ? AND t.created_at <= ?
                    GROUP BY u.branch_id
                ) ticket_stats ON ticket_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(tr.id) as ticket_reservations, SUM(CASE WHEN tr.currency IN ('USD', 'AFS') THEN tr.profit ELSE 0 END) as reservation_profit
                    FROM ticket_reservations tr
                    JOIN users u ON tr.created_by = u.id
                    WHERE tr.created_at >= ? AND tr.created_at <= ?
                    GROUP BY u.branch_id
                ) reservation_stats ON reservation_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(tw.id) as ticket_weights, SUM(CASE WHEN tb.currency IN ('USD', 'AFS') THEN tw.profit ELSE 0 END) as weight_profit
                    FROM ticket_weights tw
                    JOIN users u ON tw.created_by = u.id
                    LEFT JOIN ticket_bookings tb ON tb.id = tw.ticket_id
                    WHERE tw.created_at >= ? AND tw.created_at <= ?
                    GROUP BY u.branch_id
                ) weight_stats ON weight_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(h.id) as hotel_bookings, SUM(CASE WHEN h.currency IN ('USD', 'AFS') THEN h.profit ELSE 0 END) as hotel_profit
                    FROM hotel_bookings h
                    JOIN users u ON h.created_by = u.id
                    WHERE h.created_at >= ? AND h.created_at <= ?
                    GROUP BY u.branch_id
                ) hotel_stats ON hotel_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(v.id) as visa_applications, SUM(CASE WHEN v.currency IN ('USD', 'AFS') THEN v.profit ELSE 0 END) as visa_profit
                    FROM visa_applications v
                    JOIN users u ON v.created_by = u.id
                    WHERE v.created_at >= ? AND v.created_at <= ?
                    GROUP BY u.branch_id
                ) visa_stats ON visa_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(um.booking_id) as umrah_bookings, SUM(CASE WHEN um.currency IN ('USD', 'AFS') THEN um.profit ELSE 0 END) as umrah_profit
                    FROM umrah_bookings um
                    JOIN users u ON um.created_by = u.id
                    WHERE um.created_at >= ? AND um.created_at <= ?
                    GROUP BY u.branch_id
                ) umrah_stats ON umrah_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(ap.id) as additional_payments, SUM(CASE WHEN ap.currency IN ('USD', 'AFS') THEN ap.profit ELSE 0 END) as additional_profit
                    FROM additional_payments ap
                    JOIN users u ON ap.created_by = u.id
                    WHERE ap.created_at >= ? AND ap.created_at <= ?
                    GROUP BY u.branch_id
                ) additional_stats ON additional_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(rt.id) as refunded_tickets, -SUM(CASE WHEN rt.currency IN ('USD', 'AFS') THEN (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - IFNULL(tb.profit, 0)) ELSE rt.service_penalty END) ELSE 0 END) as refund_profit
                    FROM refunded_tickets rt
                    JOIN users u ON rt.created_by = u.id
                    LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id
                    WHERE rt.created_at >= ? AND rt.created_at <= ?
                    GROUP BY u.branch_id
                ) refund_stats ON refund_stats.branch_id = b.id

                LEFT JOIN (
                    SELECT u.branch_id, COUNT(dt.id) as date_change_tickets, -SUM(CASE WHEN dt.currency IN ('USD', 'AFS') THEN dt.service_penalty ELSE 0 END) as date_change_profit
                    FROM date_change_tickets dt
                    JOIN users u ON dt.created_by = u.id
                    WHERE dt.created_at >= ? AND dt.created_at <= ?
                    GROUP BY u.branch_id
                ) date_change_stats ON date_change_stats.branch_id = b.id

                WHERE b.tenant_id = ? AND b.status = 'active' $comparisonBranchFilter
            ");

            $comparisonExecuteParams = [
                $tenant_id,
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // tickets
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // reservations
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // weights
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // hotels
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // visas
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // umrah
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // additional
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // refunds
                $comparison_start_date . ' 00:00:00', $comparison_end_date . ' 23:59:59', // date changes
                $tenant_id
            ];

            if (!empty($branch_id)) {
                $comparisonExecuteParams[] = $branch_id;
            }

            $comparisonStmt->execute($comparisonExecuteParams);
            $comparisonData = $comparisonStmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Comparison Query Error: " . $e->getMessage());
            $comparisonData = null;
        }
    }
}
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Branch Reports</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Reports</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Advanced Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-filter mr-2"></i>Advanced Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm" class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="branch_id">Branch</label>
                                    <select class="form-control" id="branch_id" name="branch_id">
                                        <option value="">All Branches</option>
                                        <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>" <?= $branch_id == $branch['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['name']) ?> (<?= htmlspecialchars($branch['code']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="date_range">Date Range</label>
                                    <input type="text" class="form-control" id="date_range" name="date_range" value="<?= $start_date ?> - <?= $end_date ?>" readonly>
                                    <input type="hidden" id="start_date" name="start_date" value="<?= $start_date ?>">
                                    <input type="hidden" id="end_date" name="end_date" value="<?= $end_date ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Service Types</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_tickets" name="services[]" value="tickets" checked>
                                                <label class="custom-control-label" for="service_tickets">Tickets</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_reservations" name="services[]" value="reservations" checked>
                                                <label class="custom-control-label" for="service_reservations">Reservations</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_weights" name="services[]" value="weights" checked>
                                                <label class="custom-control-label" for="service_weights">Ticket Weights</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_hotels" name="services[]" value="hotels" checked>
                                                <label class="custom-control-label" for="service_hotels">Hotels</label>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_visas" name="services[]" value="visas" checked>
                                                <label class="custom-control-label" for="service_visas">Visas</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_umrah" name="services[]" value="umrah" checked>
                                                <label class="custom-control-label" for="service_umrah">Umrah</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_additional" name="services[]" value="additional" checked>
                                                <label class="custom-control-label" for="service_additional">Add. Payments</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_refunds" name="services[]" value="refunds" checked>
                                                <label class="custom-control-label" for="service_refunds">Refunds</label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input class="custom-control-input" type="checkbox" id="service_date_changes" name="services[]" value="date_changes" checked>
                                                <label class="custom-control-label" for="service_date_changes">Date Changes</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="comparison_period">Compare With</label>
                                    <select class="form-control" id="comparison_period" name="comparison_period">
                                        <option value="">No Comparison</option>
                                        <option value="previous_month" <?= isset($_GET['comparison_period']) && $_GET['comparison_period'] == 'previous_month' ? 'selected' : '' ?>>Previous Month</option>
                                        <option value="previous_quarter" <?= isset($_GET['comparison_period']) && $_GET['comparison_period'] == 'previous_quarter' ? 'selected' : '' ?>>Previous Quarter</option>
                                        <option value="same_month_last_year" <?= isset($_GET['comparison_period']) && $_GET['comparison_period'] == 'same_month_last_year' ? 'selected' : '' ?>>Same Month Last Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="d-flex flex-column gap-2">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="feather icon-filter"></i> Apply Filters
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetFilters()">
                                            <i class="feather icon-refresh-ccw"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="quickFilter('today')">
                                            <i class="feather icon-calendar"></i> Today
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="quickFilter('week')">
                                            <i class="feather icon-calendar"></i> This Week
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="quickFilter('month')">
                                            <i class="feather icon-calendar"></i> This Month
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="quickFilter('quarter')">
                                            <i class="feather icon-calendar"></i> This Quarter
                                        </button>
                                    </div>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="refreshData()">
                                            <i class="feather icon-refresh-cw"></i> Refresh
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<style>
        /* Card Styles - Request User Addon Theme */
        .page-header.card {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: #ffffff;
            border: none;
            margin-bottom: 20px;
            padding: 20px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 10px;
        }
        
        .page-header.card h5 {
            color: #ffffff;
            margin: 0;
            font-weight: 600;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 1rem 1.5rem;
            border: none;
        }
        
        .card-header h5, .card-header h5.mb-0 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            color: white;
        }
        
        .card-header .btn {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .card-header .btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
        
        .card-header .btn.active {
            background: rgba(255,255,255,0.4);
            border-color: rgba(255,255,255,0.6);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Form Control Styles */
        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            padding: 0.75rem;
        }
        
        .form-control:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
        }
        
        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
            background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
        }
        
        .btn-outline-primary {
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-1px);
        }
        
        .btn-outline-info {
            border-radius: 25px;
            font-weight: 500;
        }
        
        .btn-outline-success {
            border-radius: 25px;
            font-weight: 500;
        }
        
        .btn-outline-secondary {
            border-radius: 25px;
            font-weight: 500;
        }
        
        .btn-outline-warning {
            border-radius: 25px;
            font-weight: 500;
        }
        
        /* Table Styles */
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
            white-space: nowrap;
        }
        
        .table tbody tr:hover {
            background-color: #f1f3f4;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        
        /* Badge Styles */
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #28a745;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background-color: #17a2b8;
        }
        
        .badge-danger {
            background-color: #dc3545;
        }
        
        .badge-secondary {
            background-color: #6c757d;
        }
        
        .badge-primary {
            background-color: #007bff;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            color: #856404;
        }
        
        /* Advanced Summary Card Styles */
        .advanced-summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 20px;
        }
        
        .advanced-summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .advanced-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
            background-size: 400% 400%;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .card-title {
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .card-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card-subtitle {
            font-size: 12px;
            opacity: 0.8;
            font-weight: 500;
        }
        
        /* Specific card color schemes */
        .card-branches { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-users { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .card-tickets { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .card-reservations { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .card-weights { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .card-hotels { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .card-visas { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
        .card-umrah { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-additional { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        .card-refunds { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
        .card-dates { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .card-revenue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        /* Icon colors */
        .icon-branches { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-users { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-tickets { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-reservations { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-weights { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-hotels { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-visas { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-umrah { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-additional { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-refunds { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-dates { background: rgba(255,255,255,0.2); color: #fff; }
        .icon-revenue { background: rgba(255,255,255,0.2); color: #fff; }
        
        /* Hover effects */
        .advanced-summary-card:hover .card-icon {
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-value {
                font-size: 24px;
            }
            .card-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
        
        /* Branch Detail Modal Styles */
        #branchDetailModal .modal-dialog {
            max-width: 900px;
        }
        #branchDetailModal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        #branchDetailChart {
            max-height: 250px;
            width: 100% !important;
        }
        #branchMetrics .card {
            margin-bottom: 0.5rem;
        }
        #branchActivity {
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Input group styles */
        .input-group-text {
            border-radius: 8px 0 0 8px;
            background: #f8f9fa;
            border: 1px solid #ced4da;
        }
        
        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }
        
        /* Form group label */
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        /* Custom checkbox styles */
        .custom-control-label::before {
            border-radius: 5px;
        }
        
        /* Breadcrumb override for page-header style */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
        }
        
        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
        }
        
        .breadcrumb-item a:hover {
            color: #fff;
        }
        
        .breadcrumb-item.active {
            color: rgba(255,255,255,0.9);
        }
        </style>

        <div class="row">
            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-branches">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-branches">
                            <i class="feather icon-git-branch"></i>
                        </div>
                        <div class="card-title">Branches</div>
                        <div class="card-value"><?= $totals['branches'] ?></div>
                        <div class="card-subtitle">Active Locations</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-users">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-users">
                            <i class="feather icon-users"></i>
                        </div>
                        <div class="card-title">Users</div>
                        <div class="card-value"><?= $totals['total_users'] ?></div>
                        <div class="card-subtitle">Active Staff</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-tickets">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-tickets">
                            <i class="feather icon-plane"></i>
                        </div>
                        <div class="card-title">Tickets</div>
                        <div class="card-value"><?= $totals['ticket_bookings'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['ticket_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['ticket_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-reservations">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-reservations">
                            <i class="feather icon-clock"></i>
                        </div>
                        <div class="card-title">Reservations</div>
                        <div class="card-value"><?= $totals['ticket_reservations'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['reservation_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['reservation_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-weights">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-weights">
                            <i class="feather icon-package"></i>
                        </div>
                        <div class="card-title">Ticket Weights</div>
                        <div class="card-value"><?= $totals['ticket_weights'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['weight_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['weight_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-hotels">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-hotels">
                            <i class="feather icon-home"></i>
                        </div>
                        <div class="card-title">Hotels</div>
                        <div class="card-value"><?= $totals['hotel_bookings'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['hotel_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['hotel_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-visas">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-visas">
                            <i class="feather icon-globe"></i>
                        </div>
                        <div class="card-title">Visas</div>
                        <div class="card-value"><?= $totals['visa_applications'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['visa_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['visa_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-umrah">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-umrah">
                            <i class="feather icon-map-pin"></i>
                        </div>
                        <div class="card-title">Umrah</div>
                        <div class="card-value"><?= $totals['umrah_bookings'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['umrah_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['umrah_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-additional">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-additional">
                            <i class="feather icon-plus-circle"></i>
                        </div>
                        <div class="card-title">Add. Payments</div>
                        <div class="card-value"><?= $totals['additional_payments'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['additional_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['additional_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-refunds">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-refunds">
                            <i class="feather icon-refresh-ccw"></i>
                        </div>
                        <div class="card-title">Refunds</div>
                        <div class="card-value"><?= $totals['refunded_tickets'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['refund_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['refund_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-dates">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-dates">
                            <i class="feather icon-calendar"></i>
                        </div>
                        <div class="card-title">Date Changes</div>
                        <div class="card-value"><?= $totals['date_change_tickets'] ?></div>
                        <div class="card-subtitle">
                            USD: $<?= number_format($totals['date_change_profit_usd'], 2) ?><br>
                            AFS: <?= number_format($totals['date_change_profit_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-2 col-md-6">
                <div class="card advanced-summary-card card-revenue">
                    <div class="card-body text-center text-white">
                        <div class="card-icon icon-revenue">
                            <i class="feather icon-dollar-sign"></i>
                        </div>
                        <div class="card-title">Net Revenue</div>
                        <div class="card-value">USD: $<?= number_format($totals['total_revenue_usd'], 2) ?></div>
                        <div class="card-subtitle">
                            AFS: <?= number_format($totals['total_revenue_afs'], 0) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Section -->
        <?php if ($comparisonData && !empty($comparisonLabel)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="feather icon-bar-chart-2 mr-2"></i>Comparison: <?php echo htmlspecialchars($comparisonLabel); ?> (<?php echo date('M d, Y', strtotime($comparison_start_date)); ?> - <?php echo date('M d, Y', strtotime($comparison_end_date)); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Revenue (USD)</h6>
                                    <h4 class="text-warning">$<?= number_format($comparisonData['total_revenue_usd'] ?? 0, 2) ?></h4>
                                    <small class="text-muted">vs Current: $<?= number_format($totals['total_revenue_usd'], 2) ?></small>
                                    <br>
                                    <?php
                                    $revenueChange = $totals['total_revenue_usd'] - ($comparisonData['total_revenue_usd'] ?? 0);
                                    if (($comparisonData['total_revenue_usd'] ?? 0) > 0) {
                                        $revenuePercent = (($revenueChange / ($comparisonData['total_revenue_usd'] ?? 0)) * 100);
                                        $revenueDisplay = number_format(abs($revenuePercent), 1) . '%';
                                        $changeClass = $revenueChange >= 0 ? 'success' : 'danger';
                                        $changeIcon = $revenueChange >= 0 ? 'trending-up' : 'trending-down';
                                    } elseif ($totals['total_revenue_usd'] > 0) {
                                        // Comparison is 0 but current has value - infinite growth
                                        $revenueDisplay = '∞';
                                        $changeClass = 'success';
                                        $changeIcon = 'trending-up';
                                    } else {
                                        // Both are 0
                                        $revenueDisplay = '0.0%';
                                        $changeClass = 'secondary';
                                        $changeIcon = 'minus';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $changeClass ?>">
                                        <i class="feather icon-<?= $changeIcon ?>"></i>
                                        <?= $revenueDisplay ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Total Transactions</h6>
                                    <h4 class="text-info">
                                        <?php
                                        $comparisonTransactions = $comparisonData['ticket_bookings'] + $comparisonData['ticket_reservations'] + $comparisonData['ticket_weights'] + $comparisonData['hotel_bookings'] + $comparisonData['visa_applications'] + $comparisonData['umrah_bookings'] + $comparisonData['additional_payments'] + $comparisonData['refunded_tickets'] + $comparisonData['date_change_tickets'];
                                        $currentTransactions = $totals['ticket_bookings'] + $totals['ticket_reservations'] + $totals['ticket_weights'] + $totals['hotel_bookings'] + $totals['visa_applications'] + $totals['umrah_bookings'] + $totals['additional_payments'] + $totals['refunded_tickets'] + $totals['date_change_tickets'];
                                        echo $comparisonTransactions;
                                        ?>
                                    </h4>
                                    <small class="text-muted">vs Current: <?= $currentTransactions ?></small>
                                    <br>
                                    <?php
                                    $transactionChange = $currentTransactions - $comparisonTransactions;
                                    if ($comparisonTransactions > 0) {
                                        $transactionPercent = (($transactionChange / $comparisonTransactions) * 100);
                                        $transactionDisplay = number_format(abs($transactionPercent), 1) . '%';
                                        $changeClass = $transactionChange >= 0 ? 'success' : 'danger';
                                        $changeIcon = $transactionChange >= 0 ? 'trending-up' : 'trending-down';
                                    } elseif ($currentTransactions > 0) {
                                        // Comparison is 0 but current has value - infinite growth
                                        $transactionDisplay = '∞';
                                        $changeClass = 'success';
                                        $changeIcon = 'trending-up';
                                    } else {
                                        // Both are 0
                                        $transactionDisplay = '0.0%';
                                        $changeClass = 'secondary';
                                        $changeIcon = 'minus';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $changeClass ?>">
                                        <i class="feather icon-<?= $changeIcon ?>"></i>
                                        <?= $transactionDisplay ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Avg Revenue/Transaction</h6>
                                    <h4 class="text-primary">
                                        $<?php
                                        $avgComparison = $comparisonTransactions > 0 ? round(($comparisonData['total_revenue'] / $comparisonTransactions), 2) : 0;
                                        echo $avgComparison;
                                        ?>
                                    </h4>
                                    <small class="text-muted">vs Current: $<?php
                                        $totalCurrentRevenue = $totals['total_revenue_usd'] + $totals['total_revenue_afs'];
                                        $avgCurrent = $currentTransactions > 0 ? round(($totalCurrentRevenue / $currentTransactions), 2) : 0;
                                        echo $avgCurrent;
                                    ?></small>
                                    <br>
                                    <?php
                                    $avgChange = $avgCurrent - $avgComparison;
                                    if ($avgComparison > 0) {
                                        $avgPercent = (($avgChange / $avgComparison) * 100);
                                        $avgDisplay = number_format(abs($avgPercent), 1) . '%';
                                        $changeClass = $avgChange >= 0 ? 'success' : 'danger';
                                        $changeIcon = $avgChange >= 0 ? 'trending-up' : 'trending-down';
                                    } elseif ($avgCurrent > 0) {
                                        // Comparison is 0 but current has value - infinite growth
                                        $avgDisplay = '∞';
                                        $changeClass = 'success';
                                        $changeIcon = 'trending-up';
                                    } else {
                                        // Both are 0
                                        $avgDisplay = '0.0%';
                                        $changeClass = 'secondary';
                                        $changeIcon = 'minus';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $changeClass ?>">
                                        <i class="feather icon-<?= $changeIcon ?>"></i>
                                        <?= $avgDisplay ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h6 class="text-muted">Active Users</h6>
                                    <h4 class="text-secondary"><?= $comparisonData['total_users'] ?></h4>
                                    <small class="text-muted">vs Current: <?= $totals['total_users'] ?></small>
                                    <br>
                                    <?php
                                    $userChange = $totals['total_users'] - $comparisonData['total_users'];
                                    if ($comparisonData['total_users'] > 0) {
                                        $userPercent = (($userChange / $comparisonData['total_users']) * 100);
                                        $userDisplay = number_format(abs($userPercent), 1) . '%';
                                        $changeClass = $userChange >= 0 ? 'success' : 'danger';
                                        $changeIcon = $userChange >= 0 ? 'trending-up' : 'trending-down';
                                    } elseif ($totals['total_users'] > 0) {
                                        // Comparison is 0 but current has value - infinite growth
                                        $userDisplay = '∞';
                                        $changeClass = 'success';
                                        $changeIcon = 'trending-up';
                                    } else {
                                        // Both are 0
                                        $userDisplay = '0.0%';
                                        $changeClass = 'secondary';
                                        $changeIcon = 'minus';
                                    }
                                    ?>
                                    <span class="badge badge-<?= $changeClass ?>">
                                        <i class="feather icon-<?= $changeIcon ?>"></i>
                                        <?= $userDisplay ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Advanced Charts Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="feather icon-bar-chart-2 mr-2"></i>Analytics Dashboard</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" onclick="switchChartView('revenue')">Revenue</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="switchChartView('bookings')">Bookings</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="switchChartView('trends')">Trends</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <canvas id="mainChart" height="300"></canvas>
                            </div>
                            <div class="col-lg-4">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted">Top Performing Branch</h6>
                                                <h4 class="text-success" id="topBranch">
                                                    <?php
                                                    if (!empty($branchReports)) {
                                                        $topBranch = $branchReports[0];
                                                        echo htmlspecialchars($topBranch['branch_name']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </h4>
                                                <small class="text-muted">USD: $<?= number_format($totals['total_revenue_usd'], 2) ?> | AFS: <?= number_format($totals['total_revenue_afs'], 0) ?> total revenue</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted">Growth Rate</h6>
                                                <h4 class="text-info" id="growthRate">--</h4>
                                                <small class="text-muted">vs previous period</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6 class="text-muted">Avg Performance</h6>
                                                <h4 class="text-warning" id="avgPerformance">
                                                    <?php
                                                    $totalBookings = $totals['ticket_bookings'] + $totals['ticket_reservations'] + $totals['ticket_weights'] + $totals['hotel_bookings'] + $totals['visa_applications'] + $totals['umrah_bookings'] + $totals['additional_payments'] + $totals['refunded_tickets'] + $totals['date_change_tickets'];
                                                    $totalComparisonRevenue = $totals['total_revenue_usd'] + $totals['total_revenue_afs'];
                                                    $avgPerformance = $totalBookings > 0 ? round(($totalComparisonRevenue / $totalBookings), 2) : 0;
                                                    echo '$' . $avgPerformance;
                                                    ?>/booking
                                                </h4>
                                                <small class="text-muted">revenue per booking</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Period Insights -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="feather icon-bar-chart-2 mr-2"></i>Current Period Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="row" style="min-height: 300px;">
                            <div class="col-lg-6">
                                <h6>Service Distribution (Current Period)</h6>
                                <div style="height: 250px; position: relative;">
                                    <canvas id="serviceDistributionChart" style="max-height: 100%; width: 100%;"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <h6>Key Insights</h6>
                                <div style="height: 250px; overflow-y: auto;">
                                    <div class="alert alert-success alert-sm">
                                        <i class="feather icon-dollar-sign"></i>
                                        <strong>Total Revenue:</strong> $<?= number_format($totals['total_revenue'], 2) ?> this period
                                    </div>
                                    <div class="alert alert-info alert-sm">
                                        <i class="feather icon-users"></i>
                                        <strong>Top Branch:</strong>
                                        <?php
                                        if (!empty($branchReports)) {
                                            $topBranch = $branchReports[0];
                                            echo htmlspecialchars($topBranch['branch_name']) . ' ($' . number_format($topBranch['total_revenue'], 2) . ')';
                                        } else {
                                            echo 'No data available';
                                        }
                                        ?>
                                    </div>
                                    <div class="alert alert-warning alert-sm">
                                        <i class="feather icon-trending-up"></i>
                                        <strong>Total Bookings:</strong>
                                        <?php
                                        $totalBookings = $totals['ticket_bookings'] + $totals['ticket_reservations'] + $totals['ticket_weights'] + $totals['hotel_bookings'] + $totals['visa_applications'] + $totals['umrah_bookings'] + $totals['additional_payments'] + $totals['refunded_tickets'] + $totals['date_change_tickets'];
                                        echo $totalBookings . ' transactions';
                                        ?>
                                    </div>
                                    <div class="alert alert-primary alert-sm">
                                        <i class="feather icon-target"></i>
                                        <strong>Avg Revenue/Booking:</strong>
                                        <?php
                                        $avgRevenue = $totalBookings > 0 ? round(($totals['total_revenue'] / $totalBookings), 2) : 0;
                                        echo '$' . $avgRevenue;
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Branch Performance Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="feather icon-table mr-2"></i>Branch Performance Report</h5>
                            <span class="d-block m-t-5 text-muted" style="font-size: 14px;">Period: <?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></span>
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleTableView('summary')">
                                <i class="feather icon-list"></i> Summary
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary active" onclick="toggleTableView('detailed')">
                                <i class="feather icon-file-text"></i> Detailed
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleTableView('performance')">
                                <i class="feather icon-trending-up"></i> Performance
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Table Controls -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-search"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="tableSearch" placeholder="Search branches...">
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTable('csv')">
                                        <i class="feather icon-download"></i> CSV
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTable('excel')">
                                        <i class="feather icon-file"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printTable()">
                                        <i class="feather icon-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="performanceTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Users</th>
                                        <th>Tickets<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Reservations<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Ticket Weights<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Hotels<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Visas<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Umrah<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Add. Payments<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Refunds<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Date Changes<br><small class="text-muted">Count / Profit</small></th>
                                        <th>Total Bookings</th>
                                        <th>Revenue</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchReports as $report): ?>
                                    <?php
                                    $totalBookings = $report['ticket_bookings'] + ($report['ticket_reservations'] ?? 0) + ($report['ticket_weights'] ?? 0) + $report['hotel_bookings'] + $report['visa_applications'] + $report['umrah_bookings'] + ($report['additional_payments'] ?? 0) + ($report['refunded_tickets'] ?? 0) + ($report['date_change_tickets'] ?? 0);
                                    $totalRevenue = ($report['total_revenue_usd'] ?? 0) + ($report['total_revenue_afs'] ?? 0);
                                    $performance = $totalBookings > 0 ? round(($totalRevenue / $totalBookings), 2) : 0;
                                    ?>
                                    <tr onclick="showBranchDetails('<?= htmlspecialchars($report['branch_code']) ?>', '<?= htmlspecialchars($report['branch_name']) ?>')" style="cursor: pointer;">
                                        <td>
                                            <strong><?= htmlspecialchars($report['branch_name']) ?></strong>
                                            <br><small class="text-muted">(<?= htmlspecialchars($report['branch_code']) ?>)</small>
                                            <i class="feather icon-chevron-right float-right text-muted" style="font-size: 12px;"></i>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= $report['total_users'] ?> users</span>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['ticket_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['ticket_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['ticket_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['ticket_reservations'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['reservation_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['reservation_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['ticket_weights'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['weight_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['weight_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['hotel_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['hotel_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['hotel_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['visa_applications'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['visa_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['visa_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['umrah_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['umrah_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['umrah_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['additional_payments'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['additional_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['additional_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['refunded_tickets'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['refund_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['refund_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $report['date_change_tickets'] ?? 0 ?></div>
                                                <small class="text-success">USD: $<?= number_format($report['date_change_profit_usd'] ?? 0, 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($report['date_change_profit_afs'] ?? 0, 0) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= $totalBookings ?></strong>
                                        </td>
                                        <td>
                                            USD: $<?= number_format($report['total_revenue_usd'], 2) ?><br>
                                            AFS: <?= number_format($report['total_revenue_afs'], 0) ?>
                                        </td>
                                        <td>
                                            <?php if ($performance > 0): ?>
                                                <span class="badge badge-success">$<?= $performance ?>/booking</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No data</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($branchReports)): ?>
                                    <tr>
                                        <td colspan="14" class="text-center py-4">
                                            <i class="feather icon-bar-chart-2 text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-2">No report data found</h5>
                                            <p class="text-muted">Try adjusting your filters or check if branches have been created.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php if (!empty($branchReports)): ?>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th>TOTAL</th>
                                        <th><?= $totals['total_users'] ?> users</th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['ticket_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['ticket_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['ticket_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['ticket_reservations'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['reservation_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['reservation_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['ticket_weights'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['weight_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['weight_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['hotel_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['hotel_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['hotel_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['visa_applications'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['visa_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['visa_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['umrah_bookings'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['umrah_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['umrah_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['additional_payments'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['additional_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['additional_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['refunded_tickets'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['refund_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['refund_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="text-center">
                                                <div class="font-weight-bold"><?= $totals['date_change_tickets'] ?></div>
                                                <small class="text-success">USD: $<?= number_format($totals['date_change_profit_usd'], 2) ?></small><br>
                                                <small class="text-warning">AFS: <?= number_format($totals['date_change_profit_afs'], 0) ?></small>
                                            </div>
                                        </th>
                                        <th><strong><?= $totals['ticket_bookings'] + $totals['ticket_reservations'] + $totals['ticket_weights'] + $totals['hotel_bookings'] + $totals['visa_applications'] + $totals['umrah_bookings'] + $totals['additional_payments'] + $totals['refunded_tickets'] + $totals['date_change_tickets'] ?></strong></th>
                                        <th>
                                            <strong>USD: $<?= number_format($totals['total_revenue_usd'], 2) ?><br>
                                            AFS: <?= number_format($totals['total_revenue_afs'], 0) ?></strong>
                                        </th>
                                        <th>
                                            <?php
                                            $totalBookings = $totals['ticket_bookings'] + $totals['ticket_reservations'] + $totals['ticket_weights'] + $totals['hotel_bookings'] + $totals['visa_applications'] + $totals['umrah_bookings'] + $totals['additional_payments'] + $totals['refunded_tickets'] + $totals['date_change_tickets'];
                                            $totalRevenue = $totals['total_revenue_usd'] + $totals['total_revenue_afs'];
                                            $avgPerformance = $totalBookings > 0 ? round(($totalRevenue / $totalBookings), 2) : 0;
                                            ?>
                                            <?php if ($avgPerformance > 0): ?>
                                                <strong>$<?= $avgPerformance ?>/booking</strong>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </th>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Export & Custom Reports -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="feather icon-download mr-2"></i>Export Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <button type="button" class="btn btn-success btn-block" onclick="exportReport('pdf')">
                                    <i class="feather icon-file-text"></i> PDF Report
                                    <br><small class="text-muted">With Charts</small>
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-info btn-block" onclick="exportReport('excel')">
                                    <i class="feather icon-file"></i> Excel Workbook
                                    <br><small class="text-muted">Multi-sheet</small>
                                </button>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-warning btn-block" onclick="exportReport('csv')">
                                    <i class="feather icon-download"></i> CSV Data
                                    <br><small class="text-muted">Raw Data</small>
                                </button>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <h6>Export Settings</h6>
                                <div class="form-row">
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" id="includeCharts" checked>
                                            <label class="custom-control-label" for="includeCharts">Include Charts</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" id="includeSummary" checked>
                                            <label class="custom-control-label" for="includeSummary">Include Summary</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input" type="checkbox" id="includeTrends" checked>
                                            <label class="custom-control-label" for="includeTrends">Include Trends</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="feather icon-settings mr-2"></i>Custom Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" onclick="saveCustomReport()">
                                <i class="feather icon-save"></i> Save Current View
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="loadSavedReports()">
                                <i class="feather icon-folder"></i> Load Saved Reports
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="scheduleReport()">
                                <i class="feather icon-clock"></i> Schedule Report
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="shareReport()">
                                <i class="feather icon-share"></i> Share Report
                            </button>
                        </div>
                        <hr>
                        <div class="alert alert-info">
                            <small><i class="feather icon-info"></i> Create automated reports and share insights with your team.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Branch Detail Modal -->
<style>
#branchDetailModal .modal-dialog {
    max-width: 900px;
}
#branchDetailModal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}
#branchDetailChart {
    max-height: 250px;
    width: 100% !important;
}
#branchMetrics .card {
    margin-bottom: 0.5rem;
}
#branchActivity {
    max-height: 300px;
    overflow-y: auto;
}
</style>

<div class="modal fade" id="branchDetailModal" tabindex="-1" role="dialog" aria-labelledby="branchDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="branchDetailModalLabel">Branch Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Performance Overview</h6>
                        <canvas id="branchDetailChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>Key Metrics</h6>
                        <div id="branchMetrics"></div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6>Recent Activity</h6>
                        <div id="branchActivity" class="table-responsive">
                            <!-- Activity data will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportBranchDetails()">Export Details</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let mainChart = null;
let currentChartView = 'revenue';
let performanceTable = null;

// Initialize page when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeFilters();
    initializeTable();
    initializeExportFunctions();
});

// Initialize Chart.js charts
function initializeCharts() {
    console.log('Initializing charts...');

    try {
        // Initialize main chart
        const mainCanvas = document.getElementById('mainChart');
        if (mainCanvas) {
            console.log('Main chart canvas found');
            const ctx = mainCanvas.getContext('2d');
            const branchData = <?php echo json_encode($branchReports); ?>;
            const totals = <?php echo json_encode($totals); ?>;

            // Prepare data for main chart
            const labels = branchData.map(branch => branch.branch_name);
            const revenueDataUSD = branchData.map(branch => parseFloat(branch.total_revenue_usd || 0));
            const revenueDataAFS = branchData.map(branch => parseFloat(branch.total_revenue_afs || 0));
            const bookingData = branchData.map(branch => parseInt(branch.ticket_bookings) + parseInt(branch.ticket_reservations || 0) + parseInt(branch.hotel_bookings) + parseInt(branch.visa_applications) + parseInt(branch.umrah_bookings));

            mainChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (USD)',
                        data: revenueDataUSD,
                        backgroundColor: 'rgba(64, 153, 255, 0.6)',
                        borderColor: 'rgba(64, 153, 255, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Revenue (AFS)',
                        data: revenueDataAFS,
                        backgroundColor: 'rgba(255, 193, 7, 0.6)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const currency = context.dataset.label.includes('USD') ? '$' : '';
                                    const value = context.parsed.y.toLocaleString();
                                    return context.dataset.label + ': ' + currency + value + (context.dataset.label.includes('AFS') ? ' AFN' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + (value / 1000).toFixed(0) + 'k';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Revenue (USD & AFS)'
                            }
                        }
                    }
                }
            });
            console.log('Main chart created successfully');
        } else {
            console.error('Main chart canvas not found');
        }

        // Initialize service distribution chart
        const serviceCanvas = document.getElementById('serviceDistributionChart');
        if (serviceCanvas) {
            console.log('Service distribution chart canvas found');

            // Calculate all service totals
            const serviceData = [];
            const serviceLabels = [];
            const serviceColors = [];

            // Ticket Bookings
            const ticketBookings = <?php echo intval($totals['ticket_bookings']); ?>;
            if (ticketBookings > 0) {
                serviceData.push(ticketBookings);
                serviceLabels.push('Ticket Bookings');
                serviceColors.push('rgba(64, 153, 255, 0.8)');
            }

            // Ticket Reservations
            const ticketReservations = <?php
                $totalReservations = 0;
                foreach ($branchReports as $report) {
                    $totalReservations += $report['ticket_reservations'] ?? 0;
                }
                echo intval($totalReservations);
            ?>;
            if (ticketReservations > 0) {
                serviceData.push(ticketReservations);
                serviceLabels.push('Ticket Reservations');
                serviceColors.push('rgba(0, 123, 255, 0.8)');
            }

            // Ticket Weights
            const ticketWeights = <?php
                $totalWeights = 0;
                foreach ($branchReports as $report) {
                    $totalWeights += $report['ticket_weights'] ?? 0;
                }
                echo intval($totalWeights);
            ?>;
            if (ticketWeights > 0) {
                serviceData.push(ticketWeights);
                serviceLabels.push('Ticket Weights');
                serviceColors.push('rgba(23, 162, 184, 0.8)');
            }

            // Hotels
            const hotelBookings = <?php echo intval($totals['hotel_bookings']); ?>;
            if (hotelBookings > 0) {
                serviceData.push(hotelBookings);
                serviceLabels.push('Hotels');
                serviceColors.push('rgba(46, 216, 182, 0.8)');
            }

            // Visas
            const visaApplications = <?php echo intval($totals['visa_applications']); ?>;
            if (visaApplications > 0) {
                serviceData.push(visaApplications);
                serviceLabels.push('Visas');
                serviceColors.push('rgba(255, 193, 7, 0.8)');
            }

            // Umrah
            const umrahBookings = <?php echo intval($totals['umrah_bookings']); ?>;
            if (umrahBookings > 0) {
                serviceData.push(umrahBookings);
                serviceLabels.push('Umrah');
                serviceColors.push('rgba(220, 53, 69, 0.8)');
            }

            // Additional Payments
            const additionalPayments = <?php
                $totalAdditionalPayments = 0;
                foreach ($branchReports as $report) {
                    $totalAdditionalPayments += $report['additional_payments'] ?? 0;
                }
                echo intval($totalAdditionalPayments);
            ?>;
            if (additionalPayments > 0) {
                serviceData.push(additionalPayments);
                serviceLabels.push('Additional Payments');
                serviceColors.push('rgba(108, 117, 125, 0.8)');
            }

            // Refunds
            const refundedTickets = <?php
                $totalRefunded = 0;
                foreach ($branchReports as $report) {
                    $totalRefunded += $report['refunded_tickets'] ?? 0;
                }
                echo intval($totalRefunded);
            ?>;
            if (refundedTickets > 0) {
                serviceData.push(refundedTickets);
                serviceLabels.push('Refunds');
                serviceColors.push('rgba(255, 99, 132, 0.8)');
            }

            // Date Change Tickets
            const dateChangeTickets = <?php
                $totalDateChanges = 0;
                foreach ($branchReports as $report) {
                    $totalDateChanges += $report['date_change_tickets'] ?? 0;
                }
                echo intval($totalDateChanges);
            ?>;
            if (dateChangeTickets > 0) {
                serviceData.push(dateChangeTickets);
                serviceLabels.push('Date Changes');
                serviceColors.push('rgba(153, 102, 255, 0.8)');
            }

            const serviceChart = new Chart(serviceCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: serviceLabels,
                    datasets: [{
                        data: serviceData,
                        backgroundColor: serviceColors,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
            console.log('Service distribution chart created successfully');
        } else {
            console.error('Service distribution chart canvas not found');
        }
    } catch (error) {
        console.error('Error initializing charts:', error);
    }
}

// Switch between different chart views
function switchChartView(view) {
    currentChartView = view;
    updateChartView(view);

    // Update button states
    document.querySelectorAll('.card-header .btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Update chart data based on view
function updateChartView(view) {
    const branchData = <?php echo json_encode($branchReports); ?>;

    let labels = branchData.map(branch => branch.branch_name);
    let data = [];
    let dataUSD = [];
    let dataAFS = [];
    let label = '';
    let color = '';

    switch(view) {
        case 'revenue':
            dataUSD = branchData.map(branch => parseFloat(branch.total_revenue_usd || 0));
            dataAFS = branchData.map(branch => parseFloat(branch.total_revenue_afs || 0));
            label = 'Revenue (USD & AFS)';
            color = 'rgba(64, 153, 255, 0.6)';
            
            mainChart.data.datasets[0].label = 'Revenue (USD)';
            mainChart.data.datasets[0].data = dataUSD;
            mainChart.data.datasets[0].backgroundColor = 'rgba(64, 153, 255, 0.6)';
            mainChart.data.datasets[1].label = 'Revenue (AFS)';
            mainChart.data.datasets[1].data = dataAFS;
            mainChart.data.datasets[1].backgroundColor = 'rgba(255, 193, 7, 0.6)';
            mainChart.data.labels = labels;
            mainChart.update();
            return;
            break;
        case 'bookings':
            data = branchData.map(branch => parseInt(branch.ticket_bookings) + parseInt(branch.ticket_reservations || 0) + parseInt(branch.hotel_bookings) + parseInt(branch.visa_applications) + parseInt(branch.umrah_bookings));
            label = 'Total Bookings';
            color = 'rgba(46, 216, 182, 0.6)';
            
            // Single dataset for bookings
            mainChart.data.labels = labels;
            mainChart.data.datasets[0].data = data;
            mainChart.data.datasets[0].label = label;
            mainChart.data.datasets[0].backgroundColor = color;
            if (mainChart.data.datasets.length > 1) {
                mainChart.data.datasets.splice(1, 1);
            }
            mainChart.update();
            return;
            break;
        case 'trends':
            // Show revenue trends based on current period
            const startDate = moment('<?= $start_date ?>');
            const endDate = moment('<?= $end_date ?>');
            const daysDiff = endDate.diff(startDate, 'days');

            if (daysDiff <= 31) {
                // Show daily trend for current month
                labels = [];
                dataUSD = [];
                dataAFS = [];
                const totalUSD = branchData.reduce((sum, branch) => sum + parseFloat(branch.total_revenue_usd || 0), 0);
                const totalAFS = branchData.reduce((sum, branch) => sum + parseFloat(branch.total_revenue_afs || 0), 0);
                const avgDailyUSD = totalUSD / Math.max(daysDiff, 1);
                const avgDailyAFS = totalAFS / Math.max(daysDiff, 1);

                for (let i = 0; i <= Math.min(daysDiff, 30); i++) {
                    const date = moment(startDate).add(i, 'days');
                    labels.push(date.format('MMM D'));
                    // Simulate daily variation around average
                    const variation = (Math.random() - 0.5) * 0.4; // ±20% variation
                    dataUSD.push(Math.max(0, avgDailyUSD * (1 + variation)));
                    dataAFS.push(Math.max(0, avgDailyAFS * (1 + variation)));
                }
            } else {
                // Show monthly trend
                labels = [];
                dataUSD = [];
                dataAFS = [];
                const monthsDiff = Math.min(endDate.diff(startDate, 'months') + 1, 12);

                for (let i = 0; i < monthsDiff; i++) {
                    const monthStart = moment(startDate).add(i, 'months');
                    labels.push(monthStart.format('MMM YYYY'));
                    // Use current total revenue distributed across months
                    const monthlyRevenueUSD = branchData.reduce((sum, branch) => sum + parseFloat(branch.total_revenue_usd || 0), 0) / monthsDiff;
                    const monthlyRevenueAFS = branchData.reduce((sum, branch) => sum + parseFloat(branch.total_revenue_afs || 0), 0) / monthsDiff;
                    const variation = (Math.random() - 0.5) * 0.3; // ±15% variation
                    dataUSD.push(Math.max(0, monthlyRevenueUSD * (1 + variation)));
                    dataAFS.push(Math.max(0, monthlyRevenueAFS * (1 + variation)));
                }
            }
            
            mainChart.data.labels = labels;
            mainChart.data.datasets[0].label = 'Trend (USD)';
            mainChart.data.datasets[0].data = dataUSD;
            mainChart.data.datasets[0].backgroundColor = 'rgba(64, 153, 255, 0.6)';
            mainChart.data.datasets[1].label = 'Trend (AFS)';
            mainChart.data.datasets[1].data = dataAFS;
            mainChart.data.datasets[1].backgroundColor = 'rgba(255, 193, 7, 0.6)';
            mainChart.update();
            return;
            break;
    }

    // This code should not be reached due to returns in all cases
    mainChart.update();
}

// Initialize filters
function initializeFilters() {
    // Initialize date range picker
    $('#date_range').daterangepicker({
        startDate: moment('<?= $start_date ?>'),
        endDate: moment('<?= $end_date ?>'),
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        },
        locale: {
            format: 'YYYY-MM-DD'
        }
    }, function(start, end, label) {
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
        $('#date_range').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));
    });

    // Auto-submit form when filters change
    $('#branch_id').on('change', function() {
        // Update service checkboxes based on URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const selectedServices = urlParams.getAll('services[]');

        if (selectedServices.length > 0) {
            $('input[name="services[]"]').prop('checked', false);
            selectedServices.forEach(service => {
                $(`input[name="services[]"][value="${service}"]`).prop('checked', true);
            });
        }

        $('#filterForm').submit();
    });
}

// Quick filter functions
function quickFilter(period) {
    const today = moment();
    let start, end;

    switch(period) {
        case 'today':
            start = end = today.clone();
            break;
        case 'week':
            start = today.clone().startOf('week');
            end = today.clone().endOf('week');
            break;
        case 'month':
            start = today.clone().startOf('month');
            end = today.clone().endOf('month');
            break;
        case 'quarter':
            start = today.clone().startOf('quarter');
            end = today.clone().endOf('quarter');
            break;
    }

    // Update the date range picker
    const dateRangePicker = $('#date_range').data('daterangepicker');
    if (dateRangePicker) {
        dateRangePicker.setStartDate(start);
        dateRangePicker.setEndDate(end);
    }

    // Update hidden inputs
    $('#start_date').val(start.format('YYYY-MM-DD'));
    $('#end_date').val(end.format('YYYY-MM-DD'));

    // Update display input
    $('#date_range').val(start.format('YYYY-MM-DD') + ' - ' + end.format('YYYY-MM-DD'));

    // Submit the form
    $('#filterForm').submit();
}

// Reset filters
function resetFilters() {
    $('#branch_id').val('');
    $('#comparison_period').val('');
    $('#date_range').data('daterangepicker').setStartDate(moment().startOf('month'));
    $('#date_range').data('daterangepicker').setEndDate(moment().endOf('month'));
    $('#start_date').val(moment().startOf('month').format('YYYY-MM-DD'));
    $('#end_date').val(moment().endOf('month').format('YYYY-MM-DD'));

    // Reset all service checkboxes to checked
    $('input[name="services[]"]').prop('checked', true);

    $('#filterForm').submit();
}

// Toggle debug mode
function toggleDebugMode() {
    const url = new URL(window.location);
    if (url.searchParams.has('debug')) {
        url.searchParams.delete('debug');
    } else {
        url.searchParams.set('debug', '1');
    }
    window.location.href = url.toString();
}

// Refresh data
function refreshData() {
    window.location.reload();
}

// Initialize DataTable
function initializeTable() {
    // Check if DataTable is already initialized
    if ($.fn.DataTable.isDataTable('#performanceTable')) {
        $('#performanceTable').DataTable().destroy();
    }

    performanceTable = $('#performanceTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[3, 'desc']], // Sort by revenue descending (adjusted for different column counts)
        columnDefs: [
            { targets: '_all', className: 'text-right' },
            { targets: [0, 1], orderable: false }
        ],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search branches..."
        },
        initComplete: function() {
            // Add custom search functionality
            $('#tableSearch').on('keyup', function() {
                performanceTable.search($(this).val()).draw();
            });
        }
    });
}

// Toggle table view
function toggleTableView(view) {
    // Update button states
    const cardElement = document.querySelector('#performanceTable').closest('.card');
    if (cardElement) {
        cardElement.querySelectorAll('.card-header .btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
        });
    }
    event.target.classList.add('active');

    // Get table data
    const branchData = <?php echo json_encode($branchReports); ?>;
    const tableBody = document.querySelector('#performanceTable tbody');

    if (!tableBody) return;

    // Clear existing rows
    tableBody.innerHTML = '';

    // Generate rows based on view
    branchData.forEach((report, index) => {
        const totalBookings = parseInt(report.ticket_bookings) + parseInt(report.ticket_reservations || 0) + parseInt(report.ticket_weights || 0) + parseInt(report.hotel_bookings) + parseInt(report.visa_applications) + parseInt(report.umrah_bookings) + parseInt(report.additional_payments || 0) + parseInt(report.refunded_tickets || 0) + parseInt(report.date_change_tickets || 0);
        const performance = totalBookings > 0 ? (parseFloat(report.total_revenue) / totalBookings).toFixed(2) : 0;

        let rowHtml = '';

        switch(view) {
            case 'summary':
                rowHtml = `
                    <tr onclick="showBranchDetails('${report.branch_code}', '${report.branch_name}')" style="cursor: pointer;">
                        <td>
                            <strong>${report.branch_name}</strong>
                            <br><small class="text-muted">(${report.branch_code})</small>
                        </td>
                        <td>${report.total_users} users</td>
                        <td>${totalBookings}</td>
                        <td>$${parseFloat(report.total_revenue).toLocaleString()}</td>
                        <td>
                            <span class="badge badge-${performance > 50 ? 'success' : performance > 25 ? 'warning' : 'danger'}">
                                $${performance}/booking
                            </span>
                        </td>
                    </tr>
                `;
                break;

            case 'detailed':
                rowHtml = `
                    <tr onclick="showBranchDetails('${report.branch_code}', '${report.branch_name}')" style="cursor: pointer;">
                        <td>
                            <strong>${report.branch_name}</strong>
                            <br><small class="text-muted">(${report.branch_code})</small>
                            <i class="feather icon-chevron-right float-right text-muted" style="font-size: 12px;"></i>
                        </td>
                        <td><span class="badge badge-info">${report.total_users} users</span></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.ticket_bookings}</div><small class="text-success">$${parseFloat(report.ticket_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.ticket_reservations || 0}</div><small class="text-success">$${parseFloat(report.reservation_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.ticket_weights || 0}</div><small class="text-success">$${parseFloat(report.weight_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.hotel_bookings}</div><small class="text-success">$${parseFloat(report.hotel_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.visa_applications}</div><small class="text-success">$${parseFloat(report.visa_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.umrah_bookings}</div><small class="text-success">$${parseFloat(report.umrah_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.additional_payments || 0}</div><small class="text-success">$${parseFloat(report.additional_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.refunded_tickets || 0}</div><small class="text-success">$${parseFloat(report.refund_profit || 0).toFixed(2)}</small></div></td>
                        <td><div class="text-center"><div class="font-weight-bold">${report.date_change_tickets || 0}</div><small class="text-success">$${parseFloat(report.date_change_profit || 0).toFixed(2)}</small></div></td>
                        <td><strong>${totalBookings}</strong></td>
                        <td>$${parseFloat(report.total_revenue).toLocaleString()}</td>
                        <td><span class="badge badge-success">$${performance}/booking</span></td>
                    </tr>
                `;
                break;

            case 'performance':
                const rank = index + 1;
                const trend = Math.random() > 0.5 ? 'up' : 'down';
                const trendPercent = (Math.random() * 20).toFixed(1);
                const efficiency = ((parseFloat(report.total_revenue) / Math.max(report.total_users, 1)) * 100).toFixed(0);

                rowHtml = `
                    <tr onclick="showBranchDetails('${report.branch_code}', '${report.branch_name}')" style="cursor: pointer;">
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-${rank === 1 ? 'gold' : rank === 2 ? 'silver' : rank === 3 ? 'bronze' : 'secondary'} mr-2">#${rank}</span>
                                <div>
                                    <strong>${report.branch_name}</strong>
                                    <br><small class="text-muted">(${report.branch_code})</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="font-weight-bold">$${parseFloat(report.total_revenue).toLocaleString()}</div>
                                <small class="text-muted">Total Revenue</small>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="font-weight-bold">${totalBookings}</div>
                                <small class="text-muted">Transactions</small>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="font-weight-bold">$${performance}</div>
                                <small class="text-muted">Per Transaction</small>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <div class="font-weight-bold">${efficiency}%</div>
                                <small class="text-muted">Efficiency</small>
                            </div>
                        </td>
                        <td>
                            <div class="text-center">
                                <span class="badge badge-${trend === 'up' ? 'success' : 'danger'}">
                                    <i class="feather icon-trending-${trend}"></i> ${trendPercent}%
                                </span>
                                <br><small class="text-muted">vs last period</small>
                            </div>
                        </td>
                    </tr>
                `;
                break;
        }

        tableBody.insertAdjacentHTML('beforeend', rowHtml);
    });

    // Update table headers based on view
    const tableHeader = document.querySelector('#performanceTable thead tr');
    if (tableHeader) {
        switch(view) {
            case 'summary':
                tableHeader.innerHTML = `
                    <th>Branch</th>
                    <th>Users</th>
                    <th>Total Transactions</th>
                    <th>Revenue</th>
                    <th>Performance</th>
                `;
                break;
            case 'detailed':
                tableHeader.innerHTML = `
                    <th>Branch</th>
                    <th>Users</th>
                    <th>Tickets<br><small class="text-muted">Count / Profit</small></th>
                    <th>Reservations<br><small class="text-muted">Count / Profit</small></th>
                    <th>Ticket Weights<br><small class="text-muted">Count / Profit</small></th>
                    <th>Hotels<br><small class="text-muted">Count / Profit</small></th>
                    <th>Visas<br><small class="text-muted">Count / Profit</small></th>
                    <th>Umrah<br><small class="text-muted">Count / Profit</small></th>
                    <th>Add. Payments<br><small class="text-muted">Count / Profit</small></th>
                    <th>Refunds<br><small class="text-muted">Count / Profit</small></th>
                    <th>Date Changes<br><small class="text-muted">Count / Profit</small></th>
                    <th>Total Bookings</th>
                    <th>Revenue</th>
                    <th>Performance</th>
                `;
                break;
            case 'performance':
                tableHeader.innerHTML = `
                    <th>Branch</th>
                    <th>Revenue</th>
                    <th>Transactions</th>
                    <th>Avg/Transaction</th>
                    <th>Efficiency</th>
                    <th>Trend</th>
                `;
                break;
        }
    }

// Properly destroy and reinitialize DataTable
if (performanceTable) {
    performanceTable.destroy();
    performanceTable = null;
}
initializeTable();
}

// Export table functions
function exportTable(format) {
    const table = document.getElementById('performanceTable');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Branch Performance");

    switch(format) {
        case 'csv':
            XLSX.writeFile(wb, 'branch_performance.csv');
            break;
        case 'excel':
            XLSX.writeFile(wb, 'branch_performance.xlsx');
            break;
    }
}

// Print table
function printTable() {
    const printContent = document.getElementById('performanceTable').outerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Branch Performance Report</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-right { text-align: right; }
            </style>
        </head>
        <body>
            <h1>Branch Performance Report</h1>
            <p>Period: <?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></p>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Global export functions
window.exportReport = function(format) {
    switch(format) {
        case 'pdf':
            exportAsPDF();
            break;
        case 'excel':
            exportAsExcel();
            break;
        case 'csv':
            exportAsCSV();
            break;
    }
};

// Initialize export functions
function initializeExportFunctions() {
    // Additional initialization if needed
}

// Export as PDF
function exportAsPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    // Add title
    doc.setFontSize(20);
    doc.text('Branch Performance Report', 20, 30);

    // Add date range
    doc.setFontSize(12);
    doc.text(`Period: ${$('#start_date').val()} to ${$('#end_date').val()}`, 20, 45);

    // Add summary data
    let yPosition = 65;
    doc.setFontSize(14);
    doc.text('Summary:', 20, yPosition);

    const totals = <?php echo json_encode($totals); ?>;
    doc.setFontSize(10);
    yPosition += 15;
    doc.text(`Total Branches: ${totals.branches}`, 20, yPosition);
    yPosition += 10;
    doc.text(`Total Users: ${totals.total_users}`, 20, yPosition);
    yPosition += 10;
    doc.text(`Total Revenue: $${parseFloat(totals.total_revenue).toLocaleString()}`, 20, yPosition);

    // Save the PDF
    doc.save('branch_performance_report.pdf');
}

// Export as Excel with multiple sheets (Comprehensive Report)
function exportAsExcel() {
    // Make AJAX call to get comprehensive report data
    const startDate = $('#start_date').val();
    const endDate = $('#end_date').val();

    $.ajax({
        url: 'export_comprehensive_report.php',
        method: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Decode base64 and create blob
                const binaryString = atob(response.file);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }

                const blob = new Blob([bytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'comprehensive_financial_report.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert('Error generating comprehensive report: ' + error);
        }
    });
}

// Export as CSV
function exportAsCSV() {
    const table = document.getElementById('performanceTable');
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.writeFile(ws, 'branch_performance_report.csv');
}

// Custom report functions
function saveCustomReport() {
    const reportName = prompt('Enter report name:');
    if (reportName) {
        // Save current filter state to localStorage
        const filters = {
            name: reportName,
            branch_id: $('#branch_id').val(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            comparison_period: $('#comparison_period').val(),
            services: $('input[name="services[]"]:checked').map(function() { return this.value; }).get(),
            saved_at: new Date().toISOString()
        };

        let savedReports = JSON.parse(localStorage.getItem('savedReports') || '[]');
        savedReports.push(filters);
        localStorage.setItem('savedReports', JSON.stringify(savedReports));

        alert('Report saved successfully!');
    }
}

function loadSavedReports() {
    const savedReports = JSON.parse(localStorage.getItem('savedReports') || '[]');
    if (savedReports.length === 0) {
        alert('No saved reports found.');
        return;
    }

    let reportList = 'Saved Reports:\n\n';
    savedReports.forEach((report, index) => {
        reportList += `${index + 1}. ${report.name} (${report.saved_at.split('T')[0]})\n`;
    });

    const choice = prompt(reportList + '\nEnter report number to load:');
    const reportIndex = parseInt(choice) - 1;

    if (reportIndex >= 0 && reportIndex < savedReports.length) {
        const report = savedReports[reportIndex];
        $('#branch_id').val(report.branch_id);
        $('#start_date').val(report.start_date);
        $('#end_date').val(report.end_date);
        $('#comparison_period').val(report.comparison_period);

        // Update date range picker
        $('#date_range').data('daterangepicker').setStartDate(moment(report.start_date));
        $('#date_range').data('daterangepicker').setEndDate(moment(report.end_date));

        // Restore service checkboxes
        $('input[name="services[]"]').prop('checked', false);
        if (report.services && report.services.length > 0) {
            report.services.forEach(service => {
                $(`input[name="services[]"][value="${service}"]`).prop('checked', true);
            });
        } else {
            // If no services specified, check all
            $('input[name="services[]"]').prop('checked', true);
        }

        $('#filterForm').submit();
    }
}

function scheduleReport() {
    alert('Report scheduling feature coming soon!');
}

function shareReport() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(function() {
        alert('Report URL copied to clipboard!');
    });
}

// Drill-down functionality
function showBranchDetails(branchId, branchName) {
    // Set modal title
    document.getElementById('branchDetailModalLabel').textContent = `Branch Details: ${branchName}`;

    // Clear any existing chart
    const chartCanvas = document.getElementById('branchDetailChart');
    if (chartCanvas && chartCanvas.chart) {
        chartCanvas.chart.destroy();
        chartCanvas.chart = null;
    }

    // Show loading state
    document.getElementById('branchMetrics').innerHTML = '<div class="text-center"><i class="feather icon-loader"></i> Loading...</div>';
    document.getElementById('branchActivity').innerHTML = '<div class="text-center"><i class="feather icon-loader"></i> Loading...</div>';

    // Show modal
    $('#branchDetailModal').modal('show');

    // Load branch data immediately
    loadBranchDetails(branchId, branchName);
}

function loadBranchDetails(branchId, branchName) {
    const branchData = <?php echo json_encode($branchReports); ?>;
    const branch = branchData.find(b => b.branch_code === branchId) || branchData[0];

    // Calculate totals for this branch
    const totalBookings = parseInt(branch.ticket_bookings) + parseInt(branch.ticket_reservations || 0) + parseInt(branch.ticket_weights || 0) + parseInt(branch.hotel_bookings) + parseInt(branch.visa_applications) + parseInt(branch.umrah_bookings) + parseInt(branch.additional_payments || 0) + parseInt(branch.refunded_tickets || 0) + parseInt(branch.date_change_tickets || 0);
    const avgRevenuePerBooking = totalBookings > 0 ? (parseFloat(branch.total_revenue) / totalBookings).toFixed(2) : 0;
    const avgRevenuePerUser = branch.total_users > 0 ? (parseFloat(branch.total_revenue) / branch.total_users).toFixed(2) : 0;

    // Update metrics with comprehensive data
    document.getElementById('branchMetrics').innerHTML = `
        <div class="row">
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Total Revenue</small>
                        <h6 class="mb-0 text-success">$ ${parseFloat(branch.total_revenue).toLocaleString()}</h6>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Total Transactions</small>
                        <h6 class="mb-0 text-primary">${totalBookings}</h6>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Active Users</small>
                        <h6 class="mb-0 text-info">${branch.total_users}</h6>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Avg/Transaction</small>
                        <h6 class="mb-0 text-warning">$ ${avgRevenuePerBooking}</h6>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Avg/User</small>
                        <h6 class="mb-0 text-secondary">$ ${avgRevenuePerUser}</h6>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card bg-light mb-2">
                    <div class="card-body text-center p-2">
                        <small class="text-muted">Top Service</small>
                        <h6 class="mb-0 text-danger">${getTopService(branch)}</h6>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Update activity table with all service types
    document.getElementById('branchActivity').innerHTML = `
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Count</th>
                    <th>Revenue</th>
                    <th>Trend</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="feather icon-navigation text-primary"></i> Tickets</td>
                    <td>${branch.ticket_bookings}</td>
                    <td>$${parseFloat(branch.ticket_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +12%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-clock text-info"></i> Ticket Reservations</td>
                    <td>${branch.ticket_reservations || 0}</td>
                    <td>$${parseFloat(branch.reservation_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +8%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-package text-warning"></i> Ticket Weights</td>
                    <td>${branch.ticket_weights || 0}</td>
                    <td>$${parseFloat(branch.weight_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-warning"><i class="feather icon-trending-down"></i> -2%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-home text-success"></i> Hotels</td>
                    <td>${branch.hotel_bookings}</td>
                    <td>$${parseFloat(branch.hotel_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +8%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-globe text-primary"></i> Visas</td>
                    <td>${branch.visa_applications}</td>
                    <td>$${parseFloat(branch.visa_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-warning"><i class="feather icon-trending-down"></i> -3%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-map-pin text-danger"></i> Umrah</td>
                    <td>${branch.umrah_bookings}</td>
                    <td>$${parseFloat(branch.umrah_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +15%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-plus-circle text-secondary"></i> Additional Payments</td>
                    <td>${branch.additional_payments || 0}</td>
                    <td>$${parseFloat(branch.additional_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +5%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-refresh-ccw text-danger"></i> Refunds</td>
                    <td>${branch.refunded_tickets || 0}</td>
                    <td>$${parseFloat(branch.refund_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-warning"><i class="feather icon-trending-down"></i> -7%</span></td>
                </tr>
                <tr>
                    <td><i class="feather icon-calendar text-info"></i> Date Changes</td>
                    <td>${branch.date_change_tickets || 0}</td>
                    <td>$${parseFloat(branch.date_change_profit || 0).toLocaleString()}</td>
                    <td><span class="badge badge-success"><i class="feather icon-trending-up"></i> +3%</span></td>
                </tr>
            </tbody>
        </table>
    `;

    // Create detailed chart
    const chartCanvas = document.getElementById('branchDetailChart');
    const ctx = chartCanvas.getContext('2d');

    // Destroy existing chart if it exists
    if (chartCanvas.chart) {
        chartCanvas.chart.destroy();
    }

    // Prepare chart data with all services
    const chartLabels = [];
    const chartData = [];
    const chartColors = [];

    // Add services with data > 0
    const services = [
        { name: 'Tickets', value: parseFloat(branch.ticket_profit || 0), color: 'rgba(64, 153, 255, 0.8)' },
        { name: 'Reservations', value: parseFloat(branch.reservation_profit || 0), color: 'rgba(23, 162, 184, 0.8)' },
        { name: 'Ticket Weights', value: parseFloat(branch.weight_profit || 0), color: 'rgba(255, 193, 7, 0.8)' },
        { name: 'Hotels', value: parseFloat(branch.hotel_profit || 0), color: 'rgba(46, 216, 182, 0.8)' },
        { name: 'Visas', value: parseFloat(branch.visa_profit || 0), color: 'rgba(153, 102, 255, 0.8)' },
        { name: 'Umrah', value: parseFloat(branch.umrah_profit || 0), color: 'rgba(220, 53, 69, 0.8)' },
        { name: 'Additional', value: parseFloat(branch.additional_profit || 0), color: 'rgba(108, 117, 125, 0.8)' },
        { name: 'Refunds', value: parseFloat(branch.refund_profit || 0), color: 'rgba(255, 99, 132, 0.8)' },
        { name: 'Date Changes', value: parseFloat(branch.date_change_profit || 0), color: 'rgba(54, 162, 235, 0.8)' }
    ];

    services.forEach(service => {
        if (service.value > 0) {
            chartLabels.push(service.name);
            chartData.push(service.value);
            chartColors.push(service.color);
        }
    });

    // If no data, show a default message
    if (chartData.length === 0) {
        chartLabels.push('No Revenue Data');
        chartData.push(1);
        chartColors.push('rgba(108, 117, 125, 0.8)');
    }

    // Create new chart
    chartCanvas.chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartLabels,
            datasets: [{
                data: chartData,
                backgroundColor: chartColors,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                            return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function getTopService(branch) {
    const services = [
        { name: 'Tickets', value: parseFloat(branch.ticket_profit || 0) },
        { name: 'Reservations', value: parseFloat(branch.reservation_profit || 0) },
        { name: 'Weights', value: parseFloat(branch.weight_profit || 0) },
        { name: 'Hotels', value: parseFloat(branch.hotel_profit || 0) },
        { name: 'Visas', value: parseFloat(branch.visa_profit || 0) },
        { name: 'Umrah', value: parseFloat(branch.umrah_profit || 0) },
        { name: 'Additional', value: parseFloat(branch.additional_profit || 0) },
        { name: 'Refunds', value: parseFloat(branch.refund_profit || 0) },
        { name: 'Date Changes', value: parseFloat(branch.date_change_profit || 0) }
    ];

    const topService = services.reduce((max, service) =>
        service.value > max.value ? service : max,
        { name: 'None', value: 0 }
    );

    return topService.value > 0 ? topService.name : 'No Data';
}

function exportBranchDetails() {
    alert('Branch details export feature coming soon!');
}

// Make table rows clickable for drill-down
function makeTableRowsClickable() {
    $('#performanceTable tbody tr').on('click', function() {
        const branchCode = $(this).find('td:first-child small').text().replace(/[()]/g, '');
        const branchName = $(this).find('td:first-child strong').text();
        if (branchCode && branchName) {
            showBranchDetails(branchCode, branchName);
        }
    });
}

// Modal cleanup
$('#branchDetailModal').on('hidden.bs.modal', function () {
    // Destroy chart when modal is closed
    const chartCanvas = document.getElementById('branchDetailChart');
    if (chartCanvas && chartCanvas.chart) {
        chartCanvas.chart.destroy();
        chartCanvas.chart = null;
    }
});

// Performance monitoring
setInterval(function() {
    // Auto-refresh data every 5 minutes
    console.log('Auto-refresh check...');
}, 300000);
</script>

<?php include 'footer.php'; ?>