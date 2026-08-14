<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
     header('Location: ../login.php');
    exit();
}
// Database connection
require_once('../includes/db.php');

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
$isAdmin = $_SESSION['role'] === 'admin';
?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<?php
    // Cache-busting versions for the combined CSS/JS bundles (max filemtime of bundled files)
    $umrahCssVersion = 0;
    foreach (require '../css/bundle_files.php' as $bundleFile) {
        $bundleMtime = @filemtime('../css/' . $bundleFile);
        if ($bundleMtime > $umrahCssVersion) { $umrahCssVersion = $bundleMtime; }
    }
    $umrahJsVersion = 0;
    foreach (require '../js/umrah/bundle_files.php' as $bundleFile) {
        $bundleMtime = @filemtime('../js/' . $bundleFile);
        if ($bundleMtime > $umrahJsVersion) { $umrahJsVersion = $bundleMtime; }
    }
?>
<link rel="stylesheet" href="../css/bundle.php?v=<?= $umrahCssVersion ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-kaaba page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_management') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_families_and_bookings') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">

                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createGroupModal">
                                    <i class="fas fa-plus-circle mr-2"></i><?= __('add_group') ?>
                                </button>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal" <?= !empty($groupFilter) && (int)$groupFilter > 0 ? 'onclick="setPendingFamilyGroup(' . (int)$groupFilter . ')"' : '' ?>>
                                    <i class="fas fa-plus-circle mr-2"></i><?= __('add_family') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php
                    // Search and Pagination setup
                    $resultsPerPage = 12;
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                    $visaStatus = isset($_GET['visa_status']) ? trim($_GET['visa_status']) : '';
                    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'groups';
                    $groupFilter = isset($_GET['group_id']) ? trim($_GET['group_id']) : '';
                    $offset = ($page - 1) * $resultsPerPage;

                    // Views: 'groups' (default), 'families', 'members', 'flights', 'refunded', 'cancelled'
                    $showGroups = ($filter === 'groups');
                    $showFlights = ($filter === 'flights');
                    $showMembers = ($filter === 'members');

                    // Total of active tickets (badge on the Flights pill)
                    $flightsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM group_tickets WHERE tenant_id = ? AND branch_id = ? AND status = 'active'");
                    $flightsCountStmt->execute([$tenant_id, $branch_id]);
                    $totalFlights = (int)$flightsCountStmt->fetchColumn();
                    $fulfillCountStmt = $pdo->prepare("
                        SELECT COUNT(*) FROM (
                            SELECT CONCAT_WS('|',
                                COALESCE(ff.airline,''), COALESCE(ff.flight_number,''), COALESCE(ff.pnr,''),
                                COALESCE(ff.ticket_number,''), COALESCE(ff.departure_city,''), COALESCE(ff.arrival_city,''),
                                COALESCE(ff.return_flight_number,''), COALESCE(ff.return_departure_time,''))
                            FROM umrah_flight_fulfillments ff
                            JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
                            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                            JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
                            WHERE f.tenant_id = ? AND ub.branch_id = ? AND ub.status NOT IN ('refunded','cancelled')
                            GROUP BY 1
                        ) t");
                    $fulfillCountStmt->execute([$tenant_id, $branch_id]);
                    $totalFlights += (int)$fulfillCountStmt->fetchColumn();

                    if ($showGroups) {
                        // GROUPS VIEW: one card per group with family/member counts + finance rollup
                        $groupsCountSql = "SELECT COUNT(*) FROM umrah_groups WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0)";
                        $groupsCountParams = [$tenant_id, $branch_id];
                        if (!empty($search)) {
                            $groupsCountSql .= " AND (group_number LIKE ? OR group_name LIKE ?)";
                            $searchTerm = "%$search%";
                            $groupsCountParams = array_merge($groupsCountParams, [$searchTerm, $searchTerm]);
                        }
                        $groupsCountStmt = $pdo->prepare($groupsCountSql);
                        $groupsCountStmt->execute($groupsCountParams);
                        $totalGroups = (int)$groupsCountStmt->fetchColumn();
                        $totalPages = ceil($totalGroups / $resultsPerPage);

                        $groupsSql = "SELECT
                                        g.*,
                                        u.name AS created_by,
                                        COUNT(DISTINCT f.family_id) AS family_count,
                                        COUNT(ub.booking_id) AS member_count,
                                        COALESCE(fam.total_price, 0) AS total_price,
                                        COALESCE(fam.total_paid, 0) AS total_paid,
                                        COALESCE(fam.total_due, 0) AS total_due
                                    FROM umrah_groups g
                                    LEFT JOIN users u ON g.created_by = u.id
                                    LEFT JOIN families f ON f.group_id = g.group_id AND f.tenant_id = g.tenant_id
                                    LEFT JOIN umrah_bookings ub ON ub.family_id = f.family_id
                                    LEFT JOIN (
                                        SELECT group_id, tenant_id,
                                               SUM(total_price) AS total_price,
                                               SUM(total_paid) AS total_paid,
                                               SUM(total_due) AS total_due
                                        FROM families
                                        GROUP BY group_id, tenant_id
                                    ) fam ON fam.group_id = g.group_id AND fam.tenant_id = g.tenant_id
                                    WHERE g.tenant_id = ? AND (g.branch_id = ? OR g.branch_id = 0)";
                        $groupsParams = [$tenant_id, $branch_id];
                        if (!empty($search)) {
                            $groupsSql .= " AND (g.group_number LIKE ? OR g.group_name LIKE ?)";
                            $searchTerm = "%$search%";
                            $groupsParams = array_merge($groupsParams, [$searchTerm, $searchTerm]);
                        }
                        $groupsSql .= " GROUP BY g.group_id
                                    ORDER BY CAST(g.group_number AS UNSIGNED) ASC, g.group_id ASC
                                    LIMIT ? OFFSET ?";
                        $groupsParams[] = $resultsPerPage;
                        $groupsParams[] = $offset;
                        $groupsStmt = $pdo->prepare($groupsSql);
                        $groupsStmt->execute($groupsParams);
                        $resultGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Badges on the pills
                        $familiesCountStmt = $pdo->prepare("SELECT COUNT(*) FROM families WHERE tenant_id = ? AND branch_id = ?");
                        $familiesCountStmt->execute([$tenant_id, $branch_id]);
                        $totalFamilies = (int)$familiesCountStmt->fetchColumn();
                        $membersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE tenant_id = ? AND branch_id = ?");
                        $membersCountStmt->execute([$tenant_id, $branch_id]);
                        $totalMembers = (int)$membersCountStmt->fetchColumn();
                        $flightsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM group_tickets WHERE tenant_id = ? AND branch_id = ? AND status = 'active'");
                        $flightsCountStmt->execute([$tenant_id, $branch_id]);
                        $totalFlights = (int)$flightsCountStmt->fetchColumn();
                        $fulfillCountStmt = $pdo->prepare("
                            SELECT COUNT(*) FROM (
                                SELECT CONCAT_WS('|',
                                    COALESCE(ff.airline,''), COALESCE(ff.flight_number,''), COALESCE(ff.pnr,''),
                                    COALESCE(ff.ticket_number,''), COALESCE(ff.departure_city,''), COALESCE(ff.arrival_city,''),
                                    COALESCE(ff.return_flight_number,''), COALESCE(ff.return_departure_time,''))
                                FROM umrah_flight_fulfillments ff
                                JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
                                JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                                JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
                                WHERE f.tenant_id = ? AND ub.branch_id = ? AND ub.status NOT IN ('refunded','cancelled')
                                GROUP BY 1
                            ) t");
                        $fulfillCountStmt->execute([$tenant_id, $branch_id]);
                        $totalFlights += (int)$fulfillCountStmt->fetchColumn();

                        $resultFamilies = [];
                        $resultMembers = [];
                    } elseif ($showFlights) {
                        // FLIGHTS VIEW: fetch all flights for this tenant/branch
                        $flightsStmt = $pdo->prepare("SELECT gt.*, u.name AS created_by_name
                                                    FROM group_tickets gt
                                                    LEFT JOIN users u ON gt.created_by = u.id
                                                    WHERE gt.tenant_id = ? AND gt.branch_id = ? AND gt.status = 'active'
                                                    ORDER BY gt.created_at DESC");
                        $flightsStmt->execute([$tenant_id, $branch_id]);
                        $resultFlights = $flightsStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Fulfillment-based flight tickets: per-member flight fulfillments
                        // saved from the fulfillment flow. Members on the same flight
                        // (airline + flight + pnr + ticket + route) share one card.
                        $fulfillStmt = $pdo->prepare("
                            SELECT ff.airline, ff.flight_number, ff.pnr, ff.ticket_number,
                                   ff.departure_city, ff.arrival_city, ff.departure_time,
                                   ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time,
                                   f.status AS fulfillment_status,
                                   ub.booking_id, ub.family_id, ub.name, ub.status AS booking_status,
                                   fh.head_of_family, ub.sold_price, ub.paid, ub.due, ub.currency
                            FROM umrah_flight_fulfillments ff
                            JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
                            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
                            JOIN umrah_bookings ub ON ub.booking_id = bs.booking_id
                            LEFT JOIN families fh ON ub.family_id = fh.family_id
                            WHERE f.tenant_id = ? AND ub.branch_id = ? AND ub.status NOT IN ('refunded', 'cancelled')
                            ORDER BY ff.created_at DESC");
                        $fulfillStmt->execute([$tenant_id, $branch_id]);
                        $flightTicketMap = [];
                        foreach ($fulfillStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                            $key = implode('|', [(string)$r['airline'], (string)$r['flight_number'], (string)$r['pnr'], (string)$r['ticket_number'], (string)$r['departure_city'], (string)$r['arrival_city'], (string)$r['return_flight_number'], (string)$r['return_departure_time']]);
                            if (!isset($flightTicketMap[$key])) {
                                $flightTicketMap[$key] = [
                                    '_fulfillment' => true,
                                    'ticket_id' => 0,
                                    'member_ids' => [],
                                    'airline_name' => (string)$r['airline'],
                                    'pnr' => (string)$r['pnr'],
                                    'ticket_number' => (string)$r['ticket_number'],
                                    'flight_number' => (string)$r['flight_number'],
                                    'flight_date' => !empty($r['departure_time']) ? date('Y-m-d', strtotime($r['departure_time'])) : '',
                                    'return_date' => !empty($r['return_departure_time']) ? date('Y-m-d', strtotime($r['return_departure_time'])) : '',
                                    'return_time' => !empty($r['return_departure_time']) ? date('H:i', strtotime($r['return_departure_time'])) : '',
                                    'return_arrival_time' => !empty($r['return_arrival_time']) ? date('H:i', strtotime($r['return_arrival_time'])) : '',
                                    'return_flight_number' => (string)$r['return_flight_number'],
                                    'departure_city' => (string)$r['departure_city'],
                                    'arrival_city' => (string)$r['arrival_city'],
                                    'duration' => '',
                                    'flight_type' => '',
                                    'fulfillment_status' => (string)$r['fulfillment_status'],
                                    'created_by_name' => '',
                                ];
                            }
                                    $flightTicketMap[$key]['member_ids'][] = (int)$r['booking_id'];
                                    if (!isset($flightTicketMap[$key]['first_booking_id'])) {
                                        $flightTicketMap[$key]['first_booking_id'] = (int)$r['booking_id'];
                                    }
                                }
                                $fulfillFlights = array_map(function ($t) {
                                    $t['member_ids_csv'] = implode(',', $t['member_ids']);
                                    $t['member_ids'] = $t['member_ids'] ? json_encode($t['member_ids']) : '[]';
                                    return $t;
                                }, array_values($flightTicketMap));
                        $resultFlights = array_merge($resultFlights, $fulfillFlights);

                        // Collect every member id referenced by all tickets (single query)
                        $allMemberIds = [];
                        foreach ($resultFlights as $flight) {
                            $decoded = json_decode($flight['member_ids'], true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $id) {
                                    $allMemberIds[] = (int)$id;
                                }
                            }
                        }
                        $allMemberIds = array_values(array_unique($allMemberIds));

                        $flightsMemberMap = [];
                        if (!empty($allMemberIds)) {
                            $placeholders = implode(',', array_fill(0, count($allMemberIds), '?'));
                            $flightMemberStmt = $pdo->prepare("
                                SELECT ub.booking_id, ub.family_id, ub.name, ub.passport_number, ub.status,
                                       ub.sold_price, ub.paid, ub.due, ub.currency,
                                       f.head_of_family,
                                       (SELECT MIN(bs2.id)
                                        FROM umrah_booking_services bs2
                                        JOIN umrah_fulfillments f2 ON f2.booking_service_id = bs2.id AND f2.tenant_id = bs2.tenant_id
                                        JOIN umrah_flight_fulfillments ff2 ON ff2.fulfillment_id = f2.id
                                        WHERE bs2.booking_id = ub.booking_id AND bs2.tenant_id = ub.tenant_id) AS flight_svc_id
                                FROM umrah_bookings ub
                                LEFT JOIN families f ON ub.family_id = f.family_id
                                WHERE ub.booking_id IN ({$placeholders}) AND ub.tenant_id = ? AND ub.branch_id = ?
                            ");
                            $flightMemberStmt->execute(array_merge($allMemberIds, [$tenant_id, $branch_id]));
                            foreach ($flightMemberStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                                $flightsMemberMap[(int)$m['booking_id']] = $m;
                            }
                        }

                        // Fallbacks needed by the shared header/pagination markup
                        $familiesCountStmt = $pdo->prepare("SELECT COUNT(DISTINCT family_id) AS total FROM families WHERE tenant_id = ? AND branch_id = ?");
                        $familiesCountStmt->execute([$tenant_id, $branch_id]);
                        $totalFamilies = (int)$familiesCountStmt->fetchColumn();
                        $totalPages = 1;
                        $resultFamilies = [];
                        $regularClientFamilies = [];
                        $resultMembers = [];
                        $membersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE tenant_id = ? AND branch_id = ?");
                        $membersCountStmt->execute([$tenant_id, $branch_id]);
                        $totalMembers = (int)$membersCountStmt->fetchColumn();
                        $groupsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_groups WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
                        $groupsCountStmt->execute([$tenant_id, $branch_id]);
                        $totalGroups = (int)$groupsCountStmt->fetchColumn();
                    } elseif ($showMembers) {
                    // MEMBERS VIEW: one card per member (All tab)
                    $membersCountSql = "SELECT COUNT(*)
                                        FROM umrah_bookings b
                                        LEFT JOIN families f ON b.family_id = f.family_id
                                        LEFT JOIN clients c ON b.sold_to = c.id
                                        WHERE b.tenant_id = ? AND b.branch_id = ?";
                    $membersCountParams = [$tenant_id, $branch_id];
                    $membersCountTypes = "ii";

                    if (!empty($search)) {
                        $membersCountSql .= " AND (
                            b.name LIKE ? OR
                            b.fname LIKE ? OR
                            b.passport_number LIKE ? OR
                            f.head_of_family LIKE ? OR
                            f.contact LIKE ? OR
                            c.name LIKE ?
                        )";
                        $searchTerm = "%$search%";
                        $membersCountParams = array_merge($membersCountParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                        $membersCountTypes .= "ssssss";
                    }

                    $membersCountStmt = $pdo->prepare($membersCountSql);
                    $membersCountStmt->execute($membersCountParams);
                    $totalMembers = (int)$membersCountStmt->fetchColumn();
                    $totalPages = ceil($totalMembers / $resultsPerPage);

                    $membersSql = "SELECT
                                        b.booking_id, b.family_id, b.name, b.fname, b.gender, b.duration, b.room_type,
                                        b.passport_number, b.sold_price, b.paid, b.due, b.currency, b.status, b.sold_to,
                                        b.price, b.profit,
                                        (SELECT DATE(ff.departure_time) FROM umrah_flight_fulfillments ff
                                            JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                            JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                            WHERE ubs2.booking_id = b.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                            ORDER BY ff.id DESC LIMIT 1) AS flight_date,
                                        (SELECT DATE(ff.return_departure_time) FROM umrah_flight_fulfillments ff
                                            JOIN umrah_fulfillments uf ON uf.id = ff.fulfillment_id
                                            JOIN umrah_booking_services ubs2 ON ubs2.id = uf.booking_service_id
                                            WHERE ubs2.booking_id = b.booking_id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled'
                                            ORDER BY ff.id DESC LIMIT 1) AS return_date,
                                        f.head_of_family, f.package_type, f.location, f.visa_status, f.contact,
                                        c.name AS client_name
                                    FROM umrah_bookings b
                                    LEFT JOIN families f ON b.family_id = f.family_id
                                    LEFT JOIN clients c ON b.sold_to = c.id
                                    WHERE b.tenant_id = ? AND b.branch_id = ?";
                    $membersParams = [$tenant_id, $branch_id];
                    $membersTypes = "ii";

                    if (!empty($search)) {
                        $membersSql .= " AND (
                            b.name LIKE ? OR
                            b.fname LIKE ? OR
                            b.passport_number LIKE ? OR
                            f.head_of_family LIKE ? OR
                            f.contact LIKE ? OR
                            c.name LIKE ?
                        )";
                        $searchTerm = "%$search%";
                        $membersParams = array_merge($membersParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                        $membersTypes .= "ssssss";
                    }

                    $membersSql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
                    $membersParams[] = $resultsPerPage;
                    $membersParams[] = $offset;
                    $membersTypes .= "ii";

                    $membersStmt = $pdo->prepare($membersSql);
                    $membersStmt->execute($membersParams);
                    $resultMembers = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Fallbacks needed by the shared header/pagination markup
                    $familiesCountStmt = $pdo->prepare("SELECT COUNT(DISTINCT family_id) AS total FROM families WHERE tenant_id = ? AND branch_id = ?");
                    $familiesCountStmt->execute([$tenant_id, $branch_id]);
                    $totalFamilies = (int)$familiesCountStmt->fetchColumn();
                    $resultFamilies = [];
                    $regularClientFamilies = [];
                    $groupsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_groups WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
                    $groupsCountStmt->execute([$tenant_id, $branch_id]);
                    $totalGroups = (int)$groupsCountStmt->fetchColumn();
                    } else {
                    // COUNT QUERY
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";
                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

                        if (!empty($search)) {
                            $countSql .= " AND (
                                f.head_of_family LIKE ? OR
                                f.contact LIKE ? OR
                                f.address LIKE ? OR
                                f.package_type LIKE ? OR
                                f.location LIKE ? OR
                                u.name LIKE ? OR
                                EXISTS (SELECT 1 FROM umrah_bookings ub2 WHERE ub2.family_id = f.family_id AND ub2.tenant_id = ? AND ub2.branch_id = ? AND (
                                    ub2.name LIKE ? OR
                                    ub2.passport_number LIKE ?
                                ))
                            )";
                            $searchTerm = "%$search%";
                            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                            $countTypes .= "ssssssiiiss";
                        }

                        $countSql .= " GROUP BY f.family_id
                                    HAVING SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END) > 0";
                    } else {
                        $countSql = "SELECT COUNT(DISTINCT f.family_id) as total
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                        $countParams = [$tenant_id, $branch_id];
                        $countTypes = "ii";

                        if (!empty($visaStatus)) {
                            $countSql .= " AND f.visa_status = ?";
                            $countParams[] = $visaStatus;
                            $countTypes .= "s";
                        }

                        if (!empty($search)) {
                            $countSql .= " AND (
                                f.head_of_family LIKE ? OR
                                f.contact LIKE ? OR
                                f.address LIKE ? OR
                                f.package_type LIKE ? OR
                                f.location LIKE ? OR
                                u.name LIKE ? OR
                                EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                    ub.name LIKE ? OR
                                    ub.passport_number LIKE ?
                                ))
                            )";
                            $searchTerm = "%$search%";
                            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                            $countTypes .= "ssssssiiiss";
                        }
                    }

                    $countStmt = $pdo->prepare($countSql);
                    $countStmt->execute($countParams);
                    $totalFamilies = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                    $totalPages = ceil($totalFamilies / $resultsPerPage);

                    // MAIN QUERY
                    $sqlFamilies = "SELECT
                                        f.*,
                                        u.name as created_by,
                                        COUNT(ub.booking_id) AS total_members,
                                        SUM(CASE WHEN ub.status = 'refunded' THEN 1 ELSE 0 END) AS refunded_members,
                                        SUM(CASE WHEN ub.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_members,
                                        (SELECT ub2.currency FROM umrah_bookings ub2
                                         WHERE ub2.family_id = f.family_id
                                         ORDER BY ub2.created_at DESC LIMIT 1) AS family_currency,
                                        (SELECT COUNT(*) FROM group_tickets gt WHERE gt.tenant_id = f.tenant_id AND gt.branch_id = f.branch_id AND JSON_CONTAINS(gt.member_ids, JSON_ARRAY((SELECT booking_id FROM umrah_bookings ub2 WHERE ub2.family_id = f.family_id LIMIT 1))) AND gt.status = 'active') AS has_group_tickets
                                    FROM families f
                                    LEFT JOIN users u ON f.created_by = u.id
                                    LEFT JOIN umrah_bookings ub ON f.family_id = ub.family_id
                                    WHERE 1=1 AND f.tenant_id = ? AND f.branch_id = ?";

                    $familiesParams = [$tenant_id, $branch_id];
                    $familiesTypes = "ii";

                    $currentGroupName = '';
                    if ((int)$groupFilter > 0) {
                        $sqlFamilies .= " AND f.group_id = ?";
                        $familiesParams[] = (int)$groupFilter;
                        $familiesTypes .= "i";
                        $gnStmt = $pdo->prepare("SELECT group_name FROM umrah_groups WHERE group_id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
                        $gnStmt->execute([(int)$groupFilter, $tenant_id, $branch_id]);
                        $currentGroupName = (string)$gnStmt->fetchColumn();
                    } elseif ($groupFilter === 'unassigned') {
                        $sqlFamilies .= " AND f.group_id IS NULL";
                        $currentGroupName = __('unassigned');
                    }

                    if (($filter !== 'refunded' && $filter !== 'cancelled') && !empty($visaStatus)) {
                        $sqlFamilies .= " AND f.visa_status = ?";
                        $familiesParams[] = $visaStatus;
                        $familiesTypes .= "s";
                    }

                    if (!empty($search)) {
                        $sqlFamilies .= " AND (
                            f.head_of_family LIKE ? OR
                            f.contact LIKE ? OR
                            f.address LIKE ? OR
                            f.package_type LIKE ? OR
                            f.location LIKE ? OR
                            u.name LIKE ? OR
                            EXISTS (SELECT 1 FROM umrah_bookings ub WHERE ub.family_id = f.family_id AND ub.tenant_id = ? AND ub.branch_id = ? AND (
                                ub.name LIKE ? OR
                                ub.passport_number LIKE ?
                            ))
                        )";
                        $searchTerm = "%$search%";
                        $familiesParams = array_merge($familiesParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $tenant_id, $branch_id, $searchTerm, $searchTerm]);
                        $familiesTypes .= "ssssssiiiss";
                    }

                    $sqlFamilies .= " GROUP BY f.family_id";
                    if ($filter === 'refunded' || $filter === 'cancelled') {
                        $statusFilter = $filter === 'refunded' ? 'refunded' : 'cancelled';
                        $sqlFamilies .= " HAVING SUM(CASE WHEN ub.status = '$statusFilter' THEN 1 ELSE 0 END) > 0";
                    }
                    $sqlFamilies .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
                    $familiesParams[] = $resultsPerPage;
                    $familiesParams[] = $offset;
                    $familiesTypes .= "ii";

                    $familiesStmt = $pdo->prepare($sqlFamilies);
                    $familiesStmt->execute($familiesParams);
                    $resultFamilies = $familiesStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Families with at least one regular client (single query for all
                    // families on this page — replaces the per-card N+1 query)
                    $regularClientFamilies = [];
                    $familyIds = array_column($resultFamilies, 'family_id');
                    if (!empty($familyIds)) {
                        $placeholders = implode(',', array_fill(0, count($familyIds), '?'));
                        $clientTypeStmt = $pdo->prepare("
                            SELECT DISTINCT ub.family_id
                            FROM umrah_bookings ub
                            JOIN clients c ON ub.sold_to = c.id
                            WHERE c.client_type = 'regular'
                              AND ub.tenant_id = ? AND ub.branch_id = ?
                              AND ub.family_id IN ($placeholders)
                        ");
                        $clientTypeStmt->execute(array_merge([$tenant_id, $branch_id], $familyIds));
                        $regularClientFamilies = array_flip($clientTypeStmt->fetchAll(PDO::FETCH_COLUMN));
                    }

                    // Total member count (badge on the All pill)
                    $membersCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_bookings WHERE tenant_id = ? AND branch_id = ?");
                    $membersCountStmt->execute([$tenant_id, $branch_id]);
                    $totalMembers = (int)$membersCountStmt->fetchColumn();
                    $resultMembers = [];
                    $groupsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM umrah_groups WHERE tenant_id = ? AND (branch_id = ? OR branch_id = 0)");
                    $groupsCountStmt->execute([$tenant_id, $branch_id]);
                    $totalGroups = (int)$groupsCountStmt->fetchColumn();
                    }
                ?>

                <!-- Filters and Search -->
                <div class="container-fluid px-4 mb-4">
                    <div class="filters-wrapper">
                        <!-- Filter Pills -->
                        <div class="filter-pills">
                            <a href="?filter=groups" class="filter-pill <?= $filter === 'groups' ? 'active' : '' ?>">
                                <i class="fas fa-object-group"></i>
                                <span><?= __('groups') ?></span>
                                <span class="pill-badge"><?= $totalGroups ?? 0 ?></span>
                            </a>
                            <a href="?filter=families<?= !empty($groupFilter) ? '&group_id=' . urlencode($groupFilter) : '' ?>" class="filter-pill <?= $filter === 'families' && empty($visaStatus) ? 'active' : '' ?>">
                                <i class="fas fa-layer-group"></i>
                                <span><?= __('families') ?></span>
                                <span class="pill-badge"><?= $totalFamilies ?></span>
                            </a>
                            <a href="?filter=members" class="filter-pill <?= $filter === 'members' ? 'active' : '' ?>">
                                <i class="fas fa-users"></i>
                                <span><?= __('members') ?></span>
                                <span class="pill-badge"><?= $totalMembers ?></span>
                            </a>
                            <a href="?filter=flights" class="filter-pill <?= $filter === 'flights' ? 'active' : '' ?>">
                                <i class="fas fa-plane"></i>
                                <span><?= __('flights') ?></span>
                                <span class="pill-badge"><?= $totalFlights ?></span>
                            </a>
                            <a href="?filter=refunded" class="filter-pill <?= $filter === 'refunded' ? 'active' : '' ?>">
                                <i class="fas fa-undo"></i>
                                <span><?= __('refunded') ?></span>
                            </a>
                            <a href="?filter=cancelled" class="filter-pill <?= $filter === 'cancelled' ? 'active' : '' ?>">
                                <i class="fas fa-times-circle"></i>
                                <span><?= __('cancelled') ?></span>
                            </a>
                        </div>

                        <!-- Enhanced Search -->
                        <div class="search-wrapper">
                            <form method="GET" class="search-form">
                                <div class="search-input-group">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="search" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>"
                                           placeholder="<?= __('search_families_members_passports') ?>"
                                           class="search-input">
                                    <input type="hidden" name="group_id" value="<?= htmlspecialchars($groupFilter) ?>">
                                    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                                    <?php if (!empty($search)): ?>
                                        <a href="?" class="clear-search">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="search-button">
                                        <?= __('search') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Family Cards Grid -->
                <div class="container-fluid px-4">
                    <?php if ($showGroups): ?>
                        <?php if (!empty($resultGroups)): ?>
                        <div class="family-cards-grid group-cards-grid">
                            <?php foreach ($resultGroups as $group):
                                $memberCount = (int)($group['member_count'] ?? 0);
                                $familyCount = (int)($group['family_count'] ?? 0);
                                $groupTotal = floatval($group['total_price'] ?? 0);
                                $groupPaid = floatval($group['total_paid'] ?? 0);
                                $groupDue = floatval($group['total_due'] ?? 0);
                                $groupPercentage = $groupTotal > 0 ? ($groupPaid / $groupTotal) * 100 : 0;
                            ?>
                                <div class="family-card group-card" data-group-id="<?= (int)$group['group_id'] ?>">
                                    <div class="card-header-section">
                                        <div class="family-avatar">
                                            <i class="fas fa-object-group"></i>
                                        </div>
                                        <div class="family-main-info">
                                            <h3 class="family-name"><?= htmlspecialchars($group['group_name']) ?></h3>
                                            <div class="family-meta">
                                                <span class="meta-item group-number-chip">
                                                    <i class="fas fa-hashtag"></i>
                                                    <?= htmlspecialchars($group['group_number']) ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="fas fa-users"></i>
                                                    <?= $familyCount ?> <?= __('families') ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="fas fa-user"></i>
                                                    <?= $memberCount ?> <?= __('members') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-actions">
                                            <a class="btn-icon" href="?filter=families&group_id=<?= (int)$group['group_id'] ?>" title="<?= __('view') ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn-icon btn-icon-add" type="button" title="<?= __('add_member') ?>" onclick="openAddMemberModal(<?= (int)$group['group_id'] ?>)">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button class="btn-icon" type="button" title="<?= __('edit_group') ?>" onclick="openEditGroupModal(<?= (int)$group['group_id'] ?>, '<?= htmlspecialchars(addslashes($group['group_number']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($group['group_name']), ENT_QUOTES) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="btn-icon" type="button" data-toggle="dropdown" aria-haspopup="true">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="openGroupFulfillmentModal(<?= (int)$group['group_id'] ?>, '<?= htmlspecialchars(addslashes($group['group_name']), ENT_QUOTES) ?>')">
                                                        <i class="fas fa-truck-loading"></i><?= __('fulfill_group_services') ?>
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteGroup(<?= (int)$group['group_id'] ?>, '<?= htmlspecialchars(addslashes($group['group_name']), ENT_QUOTES) ?>')">
                                                        <i class="fas fa-trash"></i><?= __('delete_group') ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body-section">
                                        <div class="info-row">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?= __('created_by') ?>: <?= htmlspecialchars($group['created_by'] ?: '—') ?> · <?= date('Y-m-d', strtotime($group['created_at'])) ?></span>
                                        </div>
                                        <div class="financial-summary">
                                            <div class="financial-details">
                                                <div class="financial-item">
                                                    <span class="label"><?= __('total_price') ?></span>
                                                    <span class="value"><?= number_format($groupTotal) ?> <?= __('usd') ?></span>
                                                </div>
                                                <div class="financial-item success">
                                                    <span class="label"><?= __('paid') ?></span>
                                                    <span class="value"><?= number_format($groupPaid) ?> <?= __('usd') ?></span>
                                                </div>
                                                <div class="financial-item warning">
                                                    <span class="label"><?= __('due') ?></span>
                                                    <span class="value"><?= number_format($groupDue) ?> <?= __('usd') ?></span>
                                                </div>
                                            </div>
                                            <?php if ($groupTotal > 0): ?>
                                            <div class="payment-progress">
                                                <span class="percentage"><?= number_format($groupPercentage, 1) ?>%</span>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar-fill" style="width: <?= $groupPercentage ?>%"></div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <nav class="pagination-wrapper" aria-label="Group list pagination">
                            <ul class="pagination-list">
                                <?php
                                $groupQueryString = "";
                                if (!empty($search)) {
                                    $groupQueryString .= "&search=" . urlencode($search);
                                }
                                $groupQueryString .= "&filter=groups";
                                ?>
                                <?php if ($page > 1): ?>
                                    <li>
                                        <a href="?page=<?= $page - 1 . $groupQueryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li>
                                        <a href="?page=<?= $i . $groupQueryString ?>"
                                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?page=<?= $page + 1 . $groupQueryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="pagination-info">
                                <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?>
                                (<?= $totalGroups ?> <?= __('groups') ?>)
                            </div>
                        </nav>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-object-group"></i>
                            </div>
                            <h3><?= !empty($search) ? sprintf(__('no_groups_found_for_search'), htmlspecialchars($search)) : __('no_groups_available') ?></h3>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
                                </a>
                            <?php else: ?>
                                <p><?= __('start_by_adding_a_new_group') ?></p>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createGroupModal">
                                    <i class="fas fa-plus mr-2"></i><?= __('add_new_group') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php elseif ($showFlights): ?>
                        <!-- Flights View: group members by flight, then by family -->
                        <div class="rooming-toolbar">
                            <label class="rooming-tick-toggle">
                                <input type="checkbox" id="roomingSelectAll" class="rooming-select-all">
                                <i class="fas fa-check-square"></i>
                                <span><?= __('select_all') ?></span>
                            </label>
                            <span class="rooming-selected-count" id="roomingSelectedCount">0 <?= __('tickets') ?></span>
                            <div class="rooming-actions-dropdown" id="roomingActions">
                                <button type="button" class="btn-icon btn-icon-rooming rooming-actions-btn" title="<?= __('rooming_list') ?>" aria-label="<?= __('rooming_list') ?>">
                                    <i class="fas fa-bed"></i>
                                </button>
                                <button type="button" class="btn-icon btn-icon-client client-report-btn" title="<?= __('client_report') ?>" aria-label="<?= __('client_report') ?>">
                                    <i class="fas fa-users"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flights-list">
                            <?php foreach ($resultFlights as $flight):
                                $memberIds = json_decode($flight['member_ids'] ?? '[]', true);
                                $flightMembers = [];
                                if (is_array($memberIds)) {
                                    foreach ($memberIds as $bid) {
                                        if (isset($flightsMemberMap[(int)$bid])) {
                                            $flightMembers[] = $flightsMemberMap[(int)$bid];
                                        }
                                    }
                                }
                                $flightFamilies = [];
                                $flightTotals = [];
                                foreach ($flightMembers as $m) {
                                    $flightFamilies[$m['family_id']][] = $m;
                                    if (isset($m['status']) && in_array($m['status'], ['refunded', 'cancelled'])) {
                                        continue;
                                    }
                                    $cur = $m['currency'] ?: 'USD';
                                    if (!isset($flightTotals[$cur])) {
                                        $flightTotals[$cur] = ['price' => 0.0, 'paid' => 0.0, 'due' => 0.0];
                                    }
                                    $flightTotals[$cur]['price'] += (float)($m['sold_price'] ?? 0);
                                    $flightTotals[$cur]['paid']  += (float)($m['paid'] ?? 0);
                                    $flightTotals[$cur]['due']   += (float)($m['due'] ?? 0);
                                }
                                $flightType = $flight['flight_type'] === 'indirect' ? __('indirect') : __('direct');
                            ?>
                                <div class="flight-card" data-flight-id="<?= (int)$flight['ticket_id'] ?>">
                                    <div class="flight-card-header">
                                        <?php if (empty($flight['_fulfillment'])): ?>
                                        <label class="flight-tick-wrap" title="<?= __('select_all') ?>">
                                            <input type="checkbox" class="rooming-ticket-check" value="<?= (int)$flight['ticket_id'] ?>">
                                        </label>
                                        <?php else: ?>
                                        <label class="flight-tick-wrap" title="<?= __('select_all') ?>">
                                            <input type="checkbox" class="rooming-ticket-check" value="b<?= htmlspecialchars($flight['member_ids_csv']) ?>">
                                        </label>
                                        <?php endif; ?>
                                        <div class="flight-avatar">
                                            <i class="fas fa-plane"></i>
                                        </div>
                                        <div class="flight-main-info">
                                            <h3 class="flight-name"><?= htmlspecialchars($flight['airline_name'] ?? '') ?> <span class="flight-pnr"><i class="fas fa-ticket-alt"></i> <?= htmlspecialchars($flight['pnr'] ?: ($flight['ticket_number'] ?? '')) ?></span></h3>
                                            <div class="flight-meta">
                                                <?php if (!empty($flight['flight_date'])): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?= htmlspecialchars($flight['flight_date']) ?> <i class="fas fa-long-arrow-alt-right"></i> <?= htmlspecialchars($flight['return_date'] ?? '') ?>
                                                </span>
                                                <?php endif; ?>
                                                <?php if (!empty($flight['flight_number'])): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-fighter-jet"></i>
                                                    <?= htmlspecialchars($flight['flight_number']) ?>
                                                </span>
                                                <?php endif; ?>
                                                <?php if (!empty($flight['ticket_number'])): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-hashtag"></i>
                                                    <?= __('ticket_number') ?>: <?= htmlspecialchars($flight['ticket_number']) ?>
                                                </span>
                                                <?php endif; ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-route"></i>
                                                    <?= htmlspecialchars($flight['departure_city'] ?? '') ?> <i class="fas fa-long-arrow-alt-right"></i> <?= htmlspecialchars($flight['arrival_city'] ?? '') ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="fas fa-users"></i>
                                                    <?= count($flightMembers) ?> <?= __('members') ?>
                                                </span>
                                                <span class="meta-item">
                                                    <i class="fas fa-user-friends"></i>
                                                    <?= count($flightFamilies) ?> <?= __('families') ?>
                                                </span>
                                                <?php if (!empty($flight['duration'])): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <?= htmlspecialchars($flight['duration']) ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="flight-type-badge"><?= !empty($flight['_fulfillment']) ? htmlspecialchars($flight['fulfillment_status']) : $flightType ?></span>
                                        <?php if (empty($flight['_fulfillment'])): ?>
                                        <button type="button" class="btn-icon btn-icon-manifest manifest-actions-btn" data-ticket-id="<?= (int)$flight['ticket_id'] ?>" title="<?= __('passenger_manifest') ?>" aria-label="<?= __('passenger_manifest') ?>">
                                            <i class="fas fa-list-alt"></i>
                                        </button>
                                        <button type="button" class="btn-icon btn-icon-print" onclick="window.open('../api/umrah/generate_group_ticket.php?ticket_id=<?= (int)$flight['ticket_id'] ?>', '_blank')" title="<?= __('print_group_ticket') ?>" aria-label="<?= __('print_group_ticket') ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn-icon btn-icon-manifest manifest-actions-btn" data-ticket-id="<?= (int)$flight['first_booking_id'] ?>" data-src="fulfillment" title="<?= __('passenger_manifest') ?>" aria-label="<?= __('passenger_manifest') ?>">
                                            <i class="fas fa-list-alt"></i>
                                        </button>
                                        <button type="button" class="btn-icon btn-icon-print btn-print-fulfillment" data-booking-id="<?= (int)$flight['first_booking_id'] ?>" data-has-return="<?= (!empty($flight['return_flight_number']) || !empty($flight['return_date'])) ? '1' : '0' ?>" title="<?= __('print_group_ticket') ?>" aria-label="<?= __('print_group_ticket') ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($flightTotals)): ?>
                                    <div class="flight-summary">
                                        <?php foreach ($flightTotals as $totCurrency => $tot): ?>
                                        <div class="flight-stat">
                                            <span class="flight-stat-label"><i class="fas fa-tag"></i> <?= htmlspecialchars($totCurrency) ?></span>
                                            <span class="flight-stat-value"><?= number_format($tot['price']) ?></span>
                                            <span class="flight-stat-hint"><?= __('total_price') ?></span>
                                        </div>
                                        <div class="flight-stat">
                                            <span class="flight-stat-label"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($totCurrency) ?></span>
                                            <span class="flight-stat-value success"><?= number_format($tot['paid']) ?></span>
                                            <span class="flight-stat-hint"><?= __('paid') ?></span>
                                        </div>
                                        <div class="flight-stat">
                                            <span class="flight-stat-label"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($totCurrency) ?></span>
                                            <span class="flight-stat-value danger"><?= number_format($tot['due']) ?></span>
                                            <span class="flight-stat-hint"><?= __('due') ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flight-families">
                                        <?php if (empty($flightMembers)): ?>
                                        <div class="flight-no-members">
                                            <i class="fas fa-info-circle"></i> <?= __('no_members_found') ?>
                                        </div>
                                        <?php else: ?>
                                        <?php foreach ($flightFamilies as $familyId => $familyMembers):
                                            $famTotals = [];
                                            foreach ($familyMembers as $fm) {
                                                if (isset($fm['status']) && in_array($fm['status'], ['refunded', 'cancelled'])) {
                                                    continue;
                                                }
                                                $fcur = $fm['currency'] ?: 'USD';
                                                if (!isset($famTotals[$fcur])) {
                                                    $famTotals[$fcur] = ['price' => 0.0, 'paid' => 0.0];
                                                }
                                                $famTotals[$fcur]['price'] += (float)($fm['sold_price'] ?? 0);
                                                $famTotals[$fcur]['paid']  += (float)($fm['paid'] ?? 0);
                                            }
                                        ?>
                                        <div class="flight-family-group">
                                            <div class="flight-family-header">
                                                <i class="fas fa-users"></i>
                                                <span class="flight-family-name"><?= htmlspecialchars($familyMembers[0]['head_of_family'] ?? '') ?></span>
                                                <?php if (!empty($famTotals)): ?>
                                                <span class="flight-family-finance">
                                                    <i class="fas fa-credit-card"></i>
                                                    <?php foreach ($famTotals as $fcur => $ft): ?>
                                                        <?= __('paid') ?> <?= number_format($ft['paid']) ?> / <?= number_format($ft['price']) ?> <?= htmlspecialchars($fcur) ?>
                                                    <?php endforeach; ?>
                                                </span>
                                                <?php endif; ?>
                                                <span class="flight-family-count"><?= count($familyMembers) ?> <?= __('members') ?></span>
                                            </div>
                                            <div class="flight-members-grid">
                                                <?php foreach ($familyMembers as $member):
                                                    $mStatus = $member['status'] ?? '';
                                                    if ($mStatus === 'refunded') {
                                                        $mBadgeClass = 'badge-danger'; $mBadgeIcon = 'fa-times-circle'; $mBadgeText = __('refunded');
                                                    } elseif ($mStatus === 'cancelled') {
                                                        $mBadgeClass = 'badge-secondary'; $mBadgeIcon = 'fa-ban'; $mBadgeText = __('cancelled');
                                                    } elseif ($mStatus === 'pending') {
                                                        $mBadgeClass = 'badge-warning'; $mBadgeIcon = 'fa-clock'; $mBadgeText = __('pending');
                                                    } else {
                                                        $mBadgeClass = 'badge-success'; $mBadgeIcon = 'fa-check-circle'; $mBadgeText = __('active');
                                                    }
                                                ?>
                                                <div class="flight-member" onclick="viewMemberDetails(<?= (int)$member['booking_id'] ?>)" title="<?= __('view_details') ?>">
                                                    <div class="flight-member-avatar">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <div class="flight-member-info">
                                                        <span class="flight-member-name"><?= htmlspecialchars($member['name'] ?? '') ?></span>
                                                        <span class="flight-member-passport"><i class="fas fa-id-card"></i> <?= htmlspecialchars($member['passport_number'] ?? '') ?></span>
                                                    </div>
                                                    <span class="flight-member-badge <?= $mBadgeClass ?>"><i class="fas <?= $mBadgeIcon ?>"></i> <?= $mBadgeText ?></span>
                                                    <?php if (!empty($flight['_fulfillment']) && !empty($member['flight_svc_id'])): ?>
                                                    <button type="button" class="btn-icon btn-icon-print" style="width:26px;height:26px;font-size:.7rem;" onclick="event.stopPropagation(); window.open('../api/umrah/generate_fulfillment_ticket.php?booking_service_id=<?= (int)$member['flight_svc_id'] ?>', '_blank')" title="<?= __('print_ticket') ?>" aria-label="<?= __('print_ticket') ?>">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($resultFlights)): ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-plane"></i>
                            </div>
                            <h3><?= __('no_flights_available') ?></h3>
                            <p><?= __('start_by_adding_a_new_family') ?></p>
                            <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                <i class="fas fa-plus mr-2"></i><?= __('add_new_family') ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php elseif ($showMembers): ?>
                        <?php if (!empty($resultMembers)): ?>
                        <div class="members-table-wrapper">
                            <table class="table table-hover members-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('name') ?></th>
                                        <th><?= __('passport') ?></th>
                                        <th><?= __('family_head') ?></th>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('duration') ?></th>
                                        <th><?= __('room_type') ?></th>
                                        <th><?= __('visa_status') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('price') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $memberRowNo = ($page - 1) * $resultsPerPage; ?>
                                <?php foreach ($resultMembers as $m):
                                    $memberRowNo++;
                                    $mStatus = $m['status'] ?? '';
                                    if ($mStatus === 'refunded') {
                                        $mBadgeClass = 'badge-danger'; $mBadgeIcon = 'fa-times-circle'; $mBadgeText = __('refunded');
                                    } elseif ($mStatus === 'cancelled') {
                                        $mBadgeClass = 'badge-secondary'; $mBadgeIcon = 'fa-ban'; $mBadgeText = __('cancelled');
                                    } elseif ($mStatus === 'pending') {
                                        $mBadgeClass = 'badge-warning'; $mBadgeIcon = 'fa-clock'; $mBadgeText = __('pending');
                                    } else {
                                        $mBadgeClass = 'badge-success'; $mBadgeIcon = 'fa-check-circle'; $mBadgeText = __('active');
                                    }
                                    $mCurrency = strtoupper((string)($m['currency'] ?: 'USD'));
                                ?>
                                    <tr class="member-row" data-booking-id="<?= (int)$m['booking_id'] ?>">
                                        <td class="member-row-no"><?= $memberRowNo ?></td>
                                        <td>
                                            <div class="member-cell-name">
                                                <span class="member-cell-avatar"><i class="fas fa-user"></i></span>
                                                <span class="member-cell-name-text"><?= htmlspecialchars($m['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($m['passport_number']) ?></td>
                                        <td><?= htmlspecialchars($m['head_of_family']) ?></td>
                                        <td><?= htmlspecialchars($m['client_name']) ?: '—' ?></td>
                                        <td><?= htmlspecialchars($m['duration']) ?: '—' ?></td>
                                        <td><?= htmlspecialchars($m['room_type']) ?: '—' ?></td>
                                        <td><?= htmlspecialchars($m['visa_status']) ?: '—' ?></td>
                                        <td><span class="flight-member-badge <?= $mBadgeClass ?>"><i class="fas <?= $mBadgeIcon ?>"></i> <?= $mBadgeText ?></span></td>
                                        <td class="member-cell-finance">
                                            <b><?= number_format((float)($m['sold_price'] ?? 0), 2) ?> <?= $mCurrency ?></b><br>
                                            <span class="text-muted"><?= __('paid') ?>: <?= number_format((float)($m['paid'] ?? 0), 2) ?></span><br>
                                            <span class="<?= ((float)($m['due'] ?? 0) > 0) ? 'text-danger' : 'text-success' ?>"><?= __('due') ?>: <?= number_format((float)($m['due'] ?? 0), 2) ?></span>
                                        </td>
                                        <td class="member-cell-actions">
                                            <button class="btn-icon-sm" type="button" title="<?= __('view_details') ?>" aria-label="<?= __('view_details') ?>" onclick="viewMemberDetails(<?= (int)$m['booking_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="dropdown">
                                                <button class="btn-icon-sm" type="button" data-toggle="dropdown" title="<?= __('more_actions') ?>" aria-label="<?= __('more_actions') ?>">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <h6 class="dropdown-header"><?= __('primary_actions') ?></h6>
                                                    <a class="dropdown-item" href="#" onclick="openMemberDashboard(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES) ?>'); return false;">
                                                        <i class="feather icon-layout"></i><?= __('member_dashboard') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="viewMemberDetails(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-eye"></i><?= __('view_details') ?>
                                                    </a>
                                                    <?php if ($mStatus !== 'active' || $canEdit): ?>
                                                    <a class="dropdown-item" href="#" onclick="openEditMemberModal(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-edit"></i><?= __('edit') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($canEdit): ?>
                                                    <a class="dropdown-item" href="#" onclick="openTransactionTab(<?= (int)$m['booking_id'] ?>, <?= (float)($m['sold_price'] ?? 0) ?>); return false;">
                                                        <i class="fas fa-credit-card"></i><?= __('transaction') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a class="dropdown-item" href="#" onclick="openFulfillmentModal(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-truck-loading"></i><?= __('fulfill_services') ?>
                                                    </a>

                                                    <div class="dropdown-divider"></div>
                                                    <h6 class="dropdown-header"><?= __('documents') ?></h6>
                                                    <a class="dropdown-item" href="#" onclick="generateTazminAgreement(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-shield-alt"></i><?= __('generate_tazmin') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="generateAgreement(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-file-contract"></i><?= __('generate_agreement') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="generateCompletionForm(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-check-circle"></i><?= __('generate_completion_form') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="selectForIdCard(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-id-card"></i><?= __('select_for_id_card') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="selectForGroupTicket(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-plane"></i><?= __('select_for_group_ticket') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="openMemberDocumentsModal(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name']), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-file-upload"></i>Photo & Passport & Visa
                                                    </a>

                                                    <div class="dropdown-divider"></div>
                                                    <h6 class="dropdown-header"><?= __('advanced_actions') ?></h6>
                                                    <?php if ($mStatus === 'active'): ?>
                                                    <a class="dropdown-item" href="#" onclick="openRefundModal(<?= (int)$m['booking_id'] ?>, <?= (float)($m['sold_price'] ?? 0) ?>, <?= (float)($m['price'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($m['currency'] ?? 'USD'), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-undo"></i><?= __('process_refund') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a class="dropdown-item" href="#" onclick="openCancellationReapplyModal(<?= (int)$m['booking_id'] ?>, <?= (float)($m['price'] ?? 0) ?>, <?= (float)($m['sold_price'] ?? 0) ?>, <?= (float)($m['profit'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($m['currency'] ?? 'USD'), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($mStatus), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-cog"></i>Manage Booking Status
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="openDateChangeModal(<?= (int)$m['booking_id'] ?>, '<?= htmlspecialchars(addslashes($m['name'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($m['flight_date'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($m['return_date'] ?? ''), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($m['duration'] ?? ''), ENT_QUOTES) ?>', <?= (float)($m['price'] ?? 0) ?>, '<?= htmlspecialchars(addslashes($m['currency'] ?? 'USD'), ENT_QUOTES) ?>'); return false;">
                                                        <i class="fas fa-calendar"></i><?= __('request_date_change') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="generateCancellationForm(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-times-circle"></i><?= __('generate_cancellation_form') ?>
                                                    </a>

                                                    <?php if ($canEdit && ($mStatus !== 'active' || $isAdmin)): ?>
                                                    <div class="dropdown-divider"></div>
                                                    <h6 class="dropdown-header text-danger"><?= __('danger_zone') ?></h6>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteBooking(<?= (int)$m['booking_id'] ?>); return false;">
                                                        <i class="fas fa-trash"></i><?= __('delete') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Enhanced Pagination -->
                        <nav class="pagination-wrapper" aria-label="Member list pagination">
                            <ul class="pagination-list">
                                <?php
                                $queryString = "";
                                if (!empty($search)) {
                                    $queryString .= "&search=" . urlencode($search);
                                }
                                if (!empty($filter)) {
                                    $queryString .= "&filter=" . urlencode($filter);
                                }

                                if ($page > 1): ?>
                                    <li>
                                        <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li>
                                        <a href="?page=<?= $i . $queryString ?>" 
                                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="pagination-info">
                                <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?> 
                                (<?= $totalMembers ?> <?= __('members') ?>)
                            </div>
                        </nav>
                        <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3><?= !empty($search) ? sprintf(__('no_members_found_for_search'), htmlspecialchars($search)) : __('no_members_available') ?></h3>
                            <?php if (!empty($search)): ?>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
                                </a>
                            <?php else: ?>
                                <p><?= __('start_by_adding_a_new_family') ?></p>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus mr-2"></i><?= __('add_new_family') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php elseif (!empty($resultFamilies)): ?>
                        <?php if (!empty($groupFilter)): ?>
                        <div class="group-breadcrumb">
                            <a href="?filter=groups" class="crumb-link"><i class="fas fa-object-group"></i> <?= __('all_groups') ?></a>
                            <span class="crumb-sep"><i class="fas fa-chevron-right"></i></span>
                            <span class="crumb-current"><?= htmlspecialchars($currentGroupName ?: __('families')) ?></span>
                            <span class="crumb-count"><?= count($resultFamilies) ?> <?= __('families') ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="family-cards-grid">
                            <?php foreach ($resultFamilies as $row): 
                                $familyId = $row['family_id'];
                                $isFullyRefunded = ($row['total_members'] > 0 && $row['total_members'] == $row['refunded_members']);
                                
                                // Calculate payment percentage
                                $totalPrice = floatval($row['total_price'] ?? 0);
                                $totalPaid = floatval($row['total_paid'] ?? 0);
                                $paymentPercentage = $totalPrice > 0 ? ($totalPaid / $totalPrice) * 100 : 0;
                            ?>
                                <div class="family-card <?= $isFullyRefunded ? 'refunded-family' : '' ?>" data-family-id="<?= $familyId ?>">
                                    <!-- Card Header -->
                                    <div class="card-header-section">
                                        <div class="family-avatar">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="family-main-info">
                                            <h3 class="family-name"><?= htmlspecialchars($row['head_of_family']) ?></h3>
                                            <div class="family-meta">
                                                    <span class="meta-item">
                                                        <i class="fas fa-users"></i>
                                                        <?= $row['total_members'] ?> <?= __('members') ?>
                                                    </span>
                                                    <?php if ($row['refunded_members'] > 0): ?>
                                                    <span class="meta-item text-warning" title="<?= __('refunded_members') ?>">
                                                        <i class="fas fa-undo"></i>
                                                        <?= $row['refunded_members'] ?> <?= __('refunded') ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($row['cancelled_members'] > 0): ?>
                                                    <span class="meta-item text-danger" title="<?= __('cancelled_members') ?>">
                                                        <i class="fas fa-ban"></i>
                                                        <?= $row['cancelled_members'] ?> <?= __('cancelled') ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                        </div>
                                        <div class="card-actions">
                                             <button class="btn-icon view-members-btn" data-family-id="<?= $familyId ?>" type="button" title="<?= __('view_members') ?>" aria-label="<?= __('view_members') ?>">
                                                 <i class="fas fa-eye"></i>
                                             </button>
                                             <button class="btn-icon btn-icon-add" type="button" title="<?= __('add_member') ?>" aria-label="<?= __('add_member') ?>" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                 <i class="fas fa-user-plus"></i>
                                             </button>
                                             <button class="btn-icon" type="button" title="<?= __('edit') ?>" aria-label="<?= __('edit') ?>" onclick="openEditFamilyModal(<?= $familyId ?>, '<?= htmlspecialchars(addslashes($row['head_of_family']), ENT_QUOTES) ?>',
                                                  '<?= htmlspecialchars(addslashes($row['contact']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($row['address']), ENT_QUOTES) ?>',
                                                  '<?= htmlspecialchars(addslashes($row['tazmin']), ENT_QUOTES) ?>', <?= (int)($row['group_id'] ?? 0) ?>)">
                                                 <i class="fas fa-edit"></i>
                                             </button>
                                            <div class="dropdown">
                                                <button class="btn-icon" type="button" data-toggle="dropdown" title="<?= __('more_actions') ?>" aria-label="<?= __('more_actions') ?>" aria-haspopup="true">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                     <?php if ($canEdit): ?>
                                                     <h6 class="dropdown-header"><?= __('finance') ?></h6>
                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="openFamilyTransactionModal(<?= $familyId ?>, '<?= htmlspecialchars(addslashes($row['head_of_family']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($row['package_type']), ENT_QUOTES) ?>', <?= (int)$row['total_members'] ?>)">
                                                         <i class="fas fa-credit-card"></i><?= __('family_transaction') ?>
                                                     </a>
                                                     <a class="dropdown-item" href="javascript:void(0)" onclick="openFamilyFulfillmentModal(<?= $familyId ?>, '<?= htmlspecialchars(addslashes($row['head_of_family']), ENT_QUOTES) ?>')">
                                                         <i class="fas fa-truck-loading"></i><?= __('fulfill_family_services') ?>
                                                     </a>
                                                     <?php endif; ?>
                                                    <h6 class="dropdown-header"><?= __('documents') ?></h6>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyTazmin(<?= $familyId ?>)">
                                                        <i class="fas fa-shield-alt"></i><?= __('generate_family_tazmin') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyAgreement(<?= $familyId ?>)">
                                                        <i class="fas fa-file-contract"></i><?= __('generate_family_agreement') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCompletion(<?= $familyId ?>)">
                                                        <i class="fas fa-check-circle"></i><?= __('generate_family_completion') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="generateFamilyCancellation(<?= $familyId ?>)">
                                                        <i class="fas fa-times-circle"></i><?= __('generate_family_cancellation') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showBankLetterModal(<?= $familyId ?>)">
                                                        <i class="fas fa-file-invoice"></i><?= __("bank_receipt") ?>
                                                    </a>
                                                    <a class="dropdown-item" href="#" onclick="showUmrahPresidencyModal(<?= $familyId ?>)">
                                                        <i class="fas fa-landmark"></i><?= __("umrah_presidency") ?>
                                                    </a>
                                                    <?php if ($canEdit): ?>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteFamily(<?= $familyId ?>)">
                                                        <i class="fas fa-trash"></i><?= __('delete') ?>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="card-body-section">
                                        <!-- Contact Information -->
                                        <div class="info-row">
                                            <i class="fas fa-phone"></i>
                                            <span><?= htmlspecialchars($row['contact']) ?></span>
                                        </div>
                                        <div class="info-row">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?= htmlspecialchars($row['address']) ?></span>
                                        </div>

                                         <!-- Flight Status Badge -->
                                         <div class="flight-badge flight-loading" id="flight-status-<?= $familyId ?>">
                                             <i class="fas fa-plane"></i>
                                             <span id="flight-status-text-<?= $familyId ?>">&nbsp;</span>
                                         </div>

                                         <!-- Flight Details Button (if group ticket exists) -->
                                         <?php if ($row['has_group_tickets'] > 0): ?>
                                         <button class="btn btn-sm btn-info" 
                                                  onclick="viewFamilyFlightDetails(<?= $familyId ?>, '<?= htmlspecialchars(addslashes($row['head_of_family']), ENT_QUOTES) ?>')"
                                                 title="<?= __('view_flight_details') ?>"
                                                 style="margin-top: 8px;">
                                             <i class="fas fa-ticket-alt"></i> <?= __('flight_details') ?>
                                         </button>
                                         <?php endif; ?>

                                        <!-- Financial Summary (collapsible) -->
                                        <?php $hasRegularClient = isset($regularClientFamilies[$familyId]); ?>
                                        <button type="button" class="payment-summary-toggle" data-toggle="collapse" data-target="#payment-summary-<?= $familyId ?>" aria-expanded="false" aria-controls="payment-summary-<?= $familyId ?>">
                                            <span class="toggle-label"><i class="fas fa-credit-card"></i><?= __('payment_status') ?></span>
                                            <?php $familyCurrency = htmlspecialchars($row['family_currency'] ?? 'USD'); ?>
                                            <?php if (!$hasRegularClient): ?>
                                            <span class="toggle-quick">
                                                <span class="toggle-pct"><?= number_format($paymentPercentage, 1) ?>%</span>
                                                <?= __('paid') ?> <?= number_format(floatval($row['total_paid'] ?? 0)) ?> / <?= number_format(floatval($row['total_price'] ?? 0)) ?> <?= $familyCurrency ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="toggle-quick">
                                                <?= __('bank') ?> <?= number_format(floatval($row['total_paid_to_bank'] ?? 0)) ?> <?= $familyCurrency ?>
                                            </span>
                                            <?php endif; ?>
                                            <i class="fas fa-chevron-down toggle-chevron"></i>
                                        </button>
                                        <div class="collapse" id="payment-summary-<?= $familyId ?>">
                                        <div class="financial-summary">
                                            <?php if (!$hasRegularClient): ?>
                                            <div class="financial-header">
                                                <span><?= __('payment_status') ?></span>
                                                <span class="percentage"><?= number_format($paymentPercentage, 1) ?>%</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= $paymentPercentage ?>%"></div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="financial-details">
                                                <div class="financial-item">
                                                    <span class="label"><?= __('total_price') ?></span>
                                                    <span class="value"><?= number_format(floatval($row['total_price'] ?? 0)) ?> <?= $familyCurrency ?></span>
                                                </div>
                                                <?php if (!$hasRegularClient): ?>
                                                <div class="financial-item success">
                                                    <span class="label"><?= __('paid') ?></span>
                                                    <span class="value"><?= number_format(floatval($row['total_paid'] ?? 0)) ?> <?= $familyCurrency ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <div class="financial-item warning">
                                                    <span class="label"><?= __('bank') ?></span>
                                                    <span class="value"><?= number_format(floatval($row['total_paid_to_bank'] ?? 0)) ?> <?= $familyCurrency ?></span>
                                                </div>
                                                <?php if (!$hasRegularClient): ?>
                                                <div class="financial-item danger">
                                                    <span class="label"><?= __('due') ?></span>
                                                    <span class="value"><?= number_format(floatval($row['total_due'] ?? 0)) ?> <?= $familyCurrency ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($hasRegularClient): ?>
                                            <div class="alert alert-info mt-3" style="margin: 10px 0 0 0; padding: 8px 12px; font-size: 12px;">
                                                <i class="fas fa-info-circle"></i>
                                                <strong><?= __('note') ?>:</strong> <?= __('add_only_bank_transaction_for_outsider_client') ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        </div>
                                    </div>

                                    <!-- Members Section (Initially Hidden) -->
                                    <div class="members-section" id="members-<?= $familyId ?>" style="display: none;">
                                        <div class="members-header">
                                            <h4><?= __('family_members') ?></h4>
                                            <button class="btn-sm btn-primary" onclick="openBookingModal(<?= $familyId ?>, '<?= addslashes($row['package_type']) ?>')">
                                                <i class="fas fa-plus"></i> <?= __('add_member') ?>
                                            </button>
                                        </div>
                                        <div class="members-grid" id="members-grid-<?= $familyId ?>">
                                            <!-- Members will be loaded via AJAX -->
                                            <div class="loading-spinner members-loading" aria-label="<?= __('loading_members') ?>">
                                                <div class="member-skeleton"><div class="skeleton skeleton-avatar"></div><div class="skeleton skeleton-line w-75"></div><div class="skeleton skeleton-line w-50"></div></div>
                                                <div class="member-skeleton"><div class="skeleton skeleton-avatar"></div><div class="skeleton skeleton-line w-75"></div><div class="skeleton skeleton-line w-50"></div></div>
                                                <div class="member-skeleton"><div class="skeleton skeleton-avatar"></div><div class="skeleton skeleton-line w-75"></div><div class="skeleton skeleton-line w-50"></div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Enhanced Pagination -->
                        <nav class="pagination-wrapper" aria-label="Family list pagination">
                            <ul class="pagination-list">
                                <?php
                                $queryString = "";
                                if (!empty($search)) {
                                    $queryString .= "&search=" . urlencode($search);
                                }
                                if (!empty($visaStatus)) {
                                    $queryString .= "&visa_status=" . urlencode($visaStatus);
                                }
                                if (!empty($filter)) {
                                    $queryString .= "&filter=" . urlencode($filter);
                                }
                                if (!empty($groupFilter)) {
                                    $queryString .= "&group_id=" . urlencode($groupFilter);
                                }

                                if ($page > 1): ?>
                                    <li>
                                        <a href="?page=<?= $page - 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li>
                                        <a href="?page=<?= $i . $queryString ?>" 
                                           class="pagination-link <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?page=<?= $page + 1 . $queryString ?>" class="pagination-link">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="pagination-info">
                                <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?> 
                                (<?= $totalFamilies ?> <?= __('total_families') ?>)
                            </div>
                        </nav>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3><?= !empty($search) ? sprintf(__('no_families_found_for_search'), htmlspecialchars($search)) : __('no_families_available') ?></h3>
                            <?php if (!empty($groupFilter)): ?>
                                <a href="?filter=groups" class="btn btn-primary">
                                    <i class="fas fa-object-group mr-2"></i><?= __('back_to_groups') ?>
                                </a>
                            <?php elseif (!empty($search)): ?>
                                <a href="?" class="btn btn-primary">
                                    <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
                                </a>
                            <?php else: ?>
                                <p><?= __('start_by_adding_a_new_family') ?></p>
                                <button class="btn btn-gradient-primary" data-toggle="modal" data-target="#createFamilyModal">
                                    <i class="fas fa-plus mr-2"></i><?= __('add_new_family') ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/umrah/edit_transaction_modal.php'; ?>
<?php include '../modals/umrah/language_modal.php'; ?>
<?php include '../modals/umrah/edit_member_modal.php'; ?>
<?php include '../modals/umrah/umrah_modal.php'; ?>
<?php include '../modals/umrah/create_group_modal.php'; ?>
<?php include '../modals/umrah/edit_group_modal.php'; ?>
<?php include '../modals/umrah/create_family_modal.php'; ?>
<?php include '../modals/umrah/transaction_modal.php'; ?>
<?php include '../modals/umrah/edit_family_modal.php'; ?>
<?php include '../modals/umrah/refund_modal.php'; ?>
<?php include '../modals/umrah/cancellation_reapply_modal.php'; ?>
<?php include '../modals/umrah/multi_ticket_invoice_modal.php'; ?>
<?php include '../modals/umrah/completion_details_modal.php'; ?>
<?php include '../modals/umrah/cancellation_details_modal.php'; ?>
<?php include '../modals/umrah/family_language_modal.php'; ?>
<?php include '../modals/umrah/family_completion_details_modal.php'; ?>
<?php include '../modals/umrah/family_cancellation_details_modal.php'; ?>
<?php include '../modals/umrah/member_document_template.php'; ?>
<?php include '../modals/umrah/member_details_modal.php'; ?>
<?php include '../modals/umrah/member_dashboard_modal.php'; ?>
<?php include '../modals/umrah/member_documents_modal.php'; ?>
<?php include '../modals/umrah/date_change_modal.php'; ?>
<?php include '../modals/umrah/bank_receipt_modal.php'; ?>
<?php include '../modals/umrah/umrah_presidency_modal.php'; ?>
<?php include '../modals/umrah/group_ticket_modal.php'; ?>
<?php include '../modals/umrah/id_card_modal.php'; ?>
<?php include '../modals/umrah/family_transaction_modal.php'; ?>
<?php include '../modals/umrah/flight_details_modal.php'; ?>
<?php include '../modals/umrah/fulfillment_modal.php'; ?>

<!-- Floating action buttons -->
<div id="groupTicketFloatingButton" class="floating-action-btn" style="display: none; bottom: 220px; right: 23px;">
    <button type="button" class="fab-button" id="showGroupTicketModal" aria-label="<?= __('generate_group_ticket') ?>" title="<?= __('generate_group_ticket') ?>">
        <i class="fas fa-plane"></i>
        <span class="fab-badge" id="groupTicketSelectionCount">0</span>
    </button>
</div>

<div id="idCardFloatingButton" class="floating-action-btn" style="display: none; bottom: 85px; right: 23px;">
    <button type="button" class="fab-button fab-dark" id="showIdCardModal" aria-label="<?= __('id_card') ?>" title="<?= __('id_card') ?>">
        <i class="fas fa-id-card"></i>
        <span class="fab-badge" id="idCardSelectionCount">0</span>
    </button>
</div>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<!-- Combined page scripts (single request, cache-busted by max filemtime) -->
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>

<!-- Tesseract.js for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.0/dist/tesseract.min.js"></script>

<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    // Debug logging: enable with ?debug in the URL
    const DEBUG_MODE = new URLSearchParams(window.location.search).has('debug');
    const dbg = (...args) => { if (DEBUG_MODE) console.log(...args); };

    // Toast notification
    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // View family members with AJAX loading
    window.viewFamilyMembers = function(familyId) {
        try {
            const sectionId = 'members-' + familyId;
            const gridId = 'members-grid-' + familyId;
            const section = document.getElementById(sectionId);
            const grid = document.getElementById(gridId);
            const card = document.querySelector('[data-family-id="' + familyId + '"]');
            
            dbg('VIEW: familyId=' + familyId + ', section=' + (section ? 'FOUND' : 'NOT FOUND') + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
            
            if (!section || !grid) {
                console.error('ERROR: Could not find section or grid');
                return false;
            }
            
            const isHidden = section.style.display === 'none';
            section.style.display = isHidden ? 'block' : 'none';
            
            // Add/remove members-visible class to the card
            if (card) {
                if (isHidden) {
                    card.classList.add('members-visible');
                } else {
                    card.classList.remove('members-visible');
                }
            }
            
            dbg('VIEW: Display changed to ' + section.style.display);
            
            if (isHidden && grid.innerHTML.includes('loading-spinner')) {
                dbg('VIEW: Loading members...');
                window.loadFamilyMembers(familyId);
            }
            return false;
        } catch(err) {
            console.error('VIEW ERROR:', err);
            console.error('Stack:', err.stack);
        }
    };

    // Load family members via AJAX
    window.loadFamilyMembers = function(familyId) {
        const gridId = 'members-grid-' + familyId;
        const grid = document.getElementById(gridId);
        
        dbg('LOAD: familyId=' + familyId + ', grid=' + (grid ? 'FOUND' : 'NOT FOUND'));
        
        if (!grid) {
            console.error('ERROR: Grid element not found: ' + gridId);
            return;
        }
        
        const memberSkeleton = '<div class="member-skeleton">' +
            '<div class="skeleton skeleton-avatar"></div>' +
            '<div class="skeleton skeleton-line w-75"></div>' +
            '<div class="skeleton skeleton-line w-50"></div>' +
            '</div>';
        grid.innerHTML = '<div class="loading-spinner members-loading" aria-label="Loading members">' +
            memberSkeleton.repeat(3) + '</div>';
        
        // Get filter parameter from current URL
        const urlParams = new URLSearchParams(window.location.search);
        const filter = urlParams.get('filter') || '';
        let url = '../api/umrah/load_family_members.php?family_id=' + familyId;
        if (filter) {
            url += '&filter=' + encodeURIComponent(filter);
        }
        dbg('LOAD: Fetching from ' + url);
        
        fetch(url)
            .then(function(response) {
                dbg('LOAD: Response status ' + response.status);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                dbg('LOAD: Data received', data);
                if (data.success) {
                    grid.innerHTML = data.html;
                    dbg('LOAD: Success - members displayed');
                } else {
                    grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + (data.message || 'Unknown error') + '</div>';
                    console.error('LOAD: API error - ' + data.message);
                }
            })
            .catch(function(error) {
                console.error('LOAD: Fetch error', error);
                grid.innerHTML = '<div style="color: red; padding: 20px;">ERROR: ' + error.message + '</div>';
            });
    };

    // Auto-expand members when searching
    if (window.location.search.includes('search=')) {
        document.querySelectorAll('.members-section').forEach(section => {
            section.style.display = 'block';
            const familyId = section.id.replace('members-', '');
            loadFamilyMembers(familyId);
        });
    }

    // Add event listener to view members buttons (run immediately, don't wait for DOMContentLoaded)
    function attachMembersButtonListeners() {
        dbg('Attaching members button listeners...');
        const buttons = document.querySelectorAll('.view-members-btn');
        dbg('Found ' + buttons.length + ' buttons');
        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const familyId = this.getAttribute('data-family-id');
                dbg('Button clicked for family ' + familyId);
                try {
                    var result = window.viewFamilyMembers(familyId);
                    dbg('viewFamilyMembers returned:', result);
                } catch(ex) {
                    console.error('Exception:', ex);
                }
            });
        });
    }
    
    // Attach listeners immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachMembersButtonListeners);
    } else {
        attachMembersButtonListeners();
    }

    // Load flight status for each family card (staggered to avoid request bursts)
    function loadFlightStatusForFamilies() {
        const familyCards = document.querySelectorAll('[data-family-id]');
        familyCards.forEach((card, index) => {
            const familyId = card.getAttribute('data-family-id');
            setTimeout(() => loadFlightStatus(familyId), index * 120);
        });
    }

    function loadFlightStatus(familyId) {
        const statusBadge = document.getElementById('flight-status-' + familyId);
        const statusText = document.getElementById('flight-status-text-' + familyId);
        if (!statusBadge || !statusText) return;

        fetch(`../api/umrah/get_group_ticket_info.php?family_id=${familyId}`)
            .then(response => response.json())
            .then(data => {
                // Remove skeleton, then apply the real status
                statusBadge.classList.remove('flight-loading', 'flight-complete', 'flight-partial', 'flight-pending');
                if (data.success) {
                    const totalMembers = data.members_total;
                    const flightDone = data.members_with_flight;
                    
                    if (flightDone === totalMembers && totalMembers > 0) {
                        statusBadge.classList.add('flight-complete');
                        statusText.textContent = `✓ Flight Done (${flightDone}/${totalMembers})`;
                    } else if (flightDone > 0) {
                        statusBadge.classList.add('flight-partial');
                        statusText.textContent = `⚠ Flight Done (${flightDone}/${totalMembers})`;
                    } else {
                        statusBadge.classList.add('flight-pending');
                        statusText.textContent = `Flight Pending (0/${totalMembers})`;
                    }
                } else {
                    statusBadge.classList.add('flight-pending');
                    statusText.textContent = '-';
                }
            })
            .catch(error => {
                console.error('Error loading flight status:', error);
                statusBadge.classList.remove('flight-loading');
                statusBadge.classList.add('flight-pending');
                statusText.textContent = '-';
            });
    }

    // Load flight status after page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadFlightStatusForFamilies);
    } else {
        loadFlightStatusForFamilies();
    }

    // Manifest document type chooser (printable page / Excel), then language chooser
    let manifestDocUrl = '';
    let manifestDocBlank = false;
    let manifestTypeContext = ''; // 'rooming' | 'manifest'
    let manifestRoomingIds = '';
    let manifestTicketId = '';
    let manifestSrc = '';

    function openManifestTypeModal() {
        document.getElementById('manifestTypeModal').classList.add('open');
    }

    function closeManifestTypeModal() {
        document.getElementById('manifestTypeModal').classList.remove('open');
    }

    document.addEventListener('click', function (e) {
        // Rooming list button (toolbar) -> type chooser
        const roomingBtn = e.target.closest('.rooming-actions-btn');
        if (roomingBtn) {
            e.preventDefault();
            const ticketIds = [];
            document.querySelectorAll('.rooming-ticket-check:checked').forEach(cb => {
                const v = cb.value;
                if (v.charAt(0) === 'b') {
                    // Fulfillment card: "b<id1>,<id2>,..." -> one b-entry per member
                    v.slice(1).split(',').forEach(bid => {
                        if (bid) ticketIds.push('b' + bid);
                    });
                } else if (v) {
                    ticketIds.push(v);
                }
            });
            if (ticketIds.length === 0) {
                const count = document.getElementById('roomingSelectedCount');
                if (count) {
                    count.textContent = <?= json_encode(__('no_tickets_selected')) ?>;
                    count.classList.add('rooming-count-error');
                }
                return;
            }
            manifestTypeContext = 'rooming';
            manifestRoomingIds = ticketIds.join(',');
            manifestSrc = '';
            openManifestTypeModal();
            return;
        }
        // Client report button (toolbar) -> type chooser
        const clientBtn = e.target.closest('.client-report-btn');
        if (clientBtn) {
            e.preventDefault();
            const ticketIds = [];
            document.querySelectorAll('.rooming-ticket-check:checked').forEach(cb => {
                const v = cb.value;
                if (v.charAt(0) === 'b') {
                    v.slice(1).split(',').forEach(bid => {
                        if (bid) ticketIds.push('b' + bid);
                    });
                } else if (v) {
                    ticketIds.push(v);
                }
            });
            if (ticketIds.length === 0) {
                const count = document.getElementById('roomingSelectedCount');
                if (count) {
                    count.textContent = <?= json_encode(__('no_tickets_selected')) ?>;
                    count.classList.add('rooming-count-error');
                }
                return;
            }
            manifestTypeContext = 'client';
            manifestRoomingIds = ticketIds.join(',');
            manifestSrc = '';
            openManifestTypeModal();
            return;
        }
        // Fulfillment flight card: print outbound + return in one ticket window
        const ffPrintBtn = e.target.closest('.btn-print-fulfillment');
        if (ffPrintBtn) {
            e.preventDefault();
            window.open('../api/umrah/generate_group_ticket.php?src=fulfillment&ticket_id=' + ffPrintBtn.getAttribute('data-booking-id'), '_blank');
            return;
        }
        // Passenger manifest button (per flight) -> type chooser
        const manifestBtn = e.target.closest('.manifest-actions-btn');
        if (manifestBtn) {
            e.preventDefault();
            manifestTypeContext = 'manifest';
            manifestTicketId = manifestBtn.getAttribute('data-ticket-id');
            manifestSrc = manifestBtn.getAttribute('data-src') || '';
            openManifestTypeModal();
            return;
        }
        // Type chooser selection -> build doc URL, then open language chooser
        const typeBtn = e.target.closest('[data-doc-type]');
        if (typeBtn && manifestTypeContext) {
            const type = typeBtn.getAttribute('data-doc-type');
            if (manifestTypeContext === 'client') {
                manifestDocUrl = '../api/umrah/' + (type === 'print' ? 'client_report_template' : 'client_report_excel') + '.php?ticket_ids=' + manifestRoomingIds;
                manifestDocBlank = (type === 'print');
            } else if (manifestTypeContext === 'rooming') {
                manifestDocUrl = '../api/umrah/' + (type === 'print' ? 'saudi_agent_template' : 'rooming_list_excel') + '.php?ticket_ids=' + manifestRoomingIds;
                manifestDocBlank = (type === 'print');
            } else {
                manifestDocUrl = '../api/umrah/passenger_manifest_' + (type === 'print' ? 'template' : 'excel') + '.php?ticket_id=' + manifestTicketId + (manifestSrc ? '&src=' + manifestSrc : '');
                manifestDocBlank = (type === 'print');
            }
            closeManifestTypeModal();
            document.getElementById('manifestLangModal').classList.add('open');
            return;
        }
        // Close type chooser (backdrop or close button)
        if (e.target.closest('#manifestTypeModal') && (e.target.classList.contains('manifest-lang-modal') || e.target.closest('.manifest-lang-close'))) {
            closeManifestTypeModal();
            return;
        }
        // Language selection -> generate document
        const langBtn = e.target.closest('[data-doc-lang]');
        if (langBtn && manifestDocUrl) {
            e.preventDefault();
            const url = manifestDocUrl + (manifestDocUrl.indexOf('?') !== -1 ? '&' : '?') + 'language=' + langBtn.getAttribute('data-doc-lang');
            document.getElementById('manifestLangModal').classList.remove('open');
            if (manifestDocBlank) {
                showDocLoading();
                const win = window.open(url, '_blank');
                if (win) {
                    win.onload = hideDocLoading;
                    setTimeout(hideDocLoading, 15000);
                } else {
                    hideDocLoading();
                }
            } else {
                showDocLoading();
                downloadDocAsFile(url);
            }
            return;
        }
        // Close language chooser (backdrop or close button)
        if (e.target.closest('#manifestLangModal') && (e.target.classList.contains('manifest-lang-modal') || e.target.closest('.manifest-lang-close'))) {
            document.getElementById('manifestLangModal').classList.remove('open');
        }
    });

    // Rooming multi-ticket selection (checkbox on each flight card)
    function updateRoomingSelection() {
        const checks = Array.from(document.querySelectorAll('.rooming-ticket-check'));
        const selected = checks.filter(cb => cb.checked).length;
        const countEl = document.getElementById('roomingSelectedCount');
        if (countEl) {
            countEl.textContent = selected + ' <?= __('tickets') ?>';
            countEl.classList.remove('rooming-count-error');
        }
        checks.forEach(cb => {
            cb.closest('.flight-card').classList.toggle('rooming-selected', cb.checked);
        });
        const selectAll = document.getElementById('roomingSelectAll');
        if (selectAll) {
            selectAll.checked = checks.length > 0 && selected === checks.length;
        }
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('rooming-ticket-check')) {
            updateRoomingSelection();
        }
        if (e.target.id === 'roomingSelectAll') {
            document.querySelectorAll('.rooming-ticket-check').forEach(cb => {
                cb.checked = e.target.checked;
            });
            updateRoomingSelection();
        }
    });

    // Document generation loading overlay
    function showDocLoading() {
        document.getElementById('docLoadingOverlay').classList.add('open');
    }

    function hideDocLoading() {
        document.getElementById('docLoadingOverlay').classList.remove('open');
    }

    // Fetch the generated file and trigger a download (Excel exports)
    async function downloadDocAsFile(url) {
        try {
            const res = await fetch(url);
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            const blob = await res.blob();
            let filename = 'document.xlsx';
            const cd = res.headers.get('Content-Disposition');
            const m = cd && cd.match(/filename="?([^";]+)"?/i);
            if (m) {
                filename = m[1];
            }
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            hideDocLoading();
        } catch (err) {
            hideDocLoading();
            alert(<?= json_encode(__('error_generating_document_please_try_again')) ?>);
        }
    }
</script>

<div class="manifest-lang-modal" id="manifestTypeModal">
    <div class="manifest-lang-dialog">
        <button type="button" class="manifest-lang-close" aria-label="Close">×</button>
        <div class="manifest-lang-title"><?= __('select_document_type') ?></div>
        <div class="manifest-lang-actions">
            <button type="button" class="manifest-lang-btn" data-doc-type="print">
                <i class="fas fa-file-alt"></i> <?= __('printable_page') ?>
            </button>
            <button type="button" class="manifest-lang-btn" data-doc-type="excel">
                <i class="fas fa-file-excel"></i> <?= __('excel') ?>
            </button>
        </div>
    </div>
</div>
<div class="manifest-lang-modal" id="manifestLangModal">
    <div class="manifest-lang-dialog">
        <button type="button" class="manifest-lang-close" aria-label="Close">×</button>
        <div class="manifest-lang-title"><?= __('language') ?></div>
        <div class="manifest-lang-actions">
            <button type="button" class="manifest-lang-btn" data-doc-lang="dari"><?= __('dari') ?></button>
            <button type="button" class="manifest-lang-btn" data-doc-lang="ps"><?= __('pashto') ?></button>
            <button type="button" class="manifest-lang-btn" data-doc-lang="en"><?= __('english') ?></button>
        </div>
    </div>
</div>
<div class="doc-loading-overlay" id="docLoadingOverlay">
    <div class="doc-loading-box">
        <div class="doc-loading-spinner"></div>
        <div class="doc-loading-text"><?= __('generating_document') ?>...</div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>