<?php
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../includes/language_helpers.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
enforce_auth();

// Check if ticket_id is provided (GET parameter from database record)
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
$isFulfillment = ($_GET['src'] ?? '') === 'fulfillment';

if ($ticketId) {
    // Fulfillment mode: ticket_id is a booking id whose flight fulfillment is used
    if ($isFulfillment) {
        require_once __DIR__ . '/fulfillment_flight_context.php';
        $ffCtx = fulfillment_flight_context($pdo, (int)$tenant_id, (int)$branch_id, $ticketId);
        if (!$ffCtx) {
            die('Invalid request: flight fulfillment not found');
        }
        $ticket = $ffCtx['ticket'];
        $selectedPilgrims = $ffCtx['member_ids'];
        $airlineName = $ticket['airline_name'] ?: 'Unknown Airline';
        $pnr = $ticket['pnr'] ?: 'N/A';
        $remarks = $ticket['remarks'];
        $flightType = $ticket['flight_type'];
    } else {
        // Fetch group ticket from database
        $stmt = $pdo->prepare("
            SELECT * FROM group_tickets 
            WHERE ticket_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$ticketId, $tenant_id, $branch_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            die('Invalid request: ticket not found');
        }
        
        // Extract data from database record
        $selectedPilgrims = json_decode($ticket['member_ids'], true) ?: [];
        $airlineName = $ticket['airline_name'] ?? 'Unknown Airline';
        $pnr = $ticket['pnr'] ?? 'N/A';
        $remarks = $ticket['remarks'] ?? '';
        $flightType = $ticket['flight_type'] ?? 'direct';
    }
    
    // Fetch member details from umrah_bookings
    if (!empty($selectedPilgrims)) {
        $placeholders = implode(',', array_fill(0, count($selectedPilgrims), '?'));
        $memberStmt = $pdo->prepare("
            SELECT booking_id, name FROM umrah_bookings 
            WHERE booking_id IN ($placeholders)
        ");
        $memberStmt->execute($selectedPilgrims);
        $memberDetails = $memberStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Rebuild selectedPilgrims with name and id
        $selectedPilgrims = [];
        foreach ($memberDetails as $member) {
            $selectedPilgrims[] = [
                'id' => $member['booking_id'],
                'name' => $member['name']
            ];
        }
    }
} else {
    // Validate POST data (original format)
    if (empty($_POST['selected_members'])) {
        die('Invalid request: no members selected');
    }
    
    $selectedPilgrims = json_decode($_POST['selected_members'], true);
    if (!is_array($selectedPilgrims) || count($selectedPilgrims) === 0) {
        die('Invalid request: invalid member data');
    }
    
    // Basic ticket details
    $airlineName = $_POST['airline_name'] ?? 'Unknown Airline';
    $pnr = $_POST['pnr'] ?? 'N/A';
    $remarks = $_POST['remarks'] ?? '';
    $flightType = $_POST['flight_type'] ?? 'direct';
}

// Initialize flight data arrays
$outboundFlights = [];
$returnFlights = [];

// Determine flight data source (database or POST)
if ($isFulfillment || ($ticketId && isset($ticket))) {
    // Use data from database record
    if ($flightType === 'direct') {
        $departureCity = $ticket['departure_city'] ?? 'Kabul';
        $arrivalCity = $ticket['arrival_city'] ?? 'Jeddah';
        $flightNumber1 = $ticket['flight_number_1'] ?? 'RQ993';
        $flightNumber2 = $ticket['flight_number_2'] ?? 'RQ994';
    } else {
        // Indirect flights from database
        $departureCity = null; // Not used for indirect
        $arrivalCity = null;
        $flightNumber1 = null;
        $flightNumber2 = null;
    }
} else {
    // Use data from POST request
    $departureCity = $_POST['departure_city'] ?? 'Kabul';
    $arrivalCity = $_POST['arrival_city'] ?? 'Jeddah';
    $flightNumber1 = $_POST['flight_number_1'] ?? 'RQ993';
    $flightNumber2 = $_POST['flight_number_2'] ?? 'RQ994';
}

// Process flight data based on type
if ($flightType === 'direct') {
    // Direct flight processing
    if ($isFulfillment || ($ticketId && isset($ticket))) {
        // Use dates and times from database
        $departureDate = ($ticket['flight_date'] ?? '') . ' ' . ($ticket['departure_time'] ?? '');
        $arrivalDate = ($ticket['flight_date'] ?? '') . ' ' . ($ticket['arrival_time'] ?? '');
        $returnDate = ($ticket['return_date'] ?? '') . ' ' . ($ticket['return_time'] ?? '');
        $retArrivalDate = ($ticket['return_date'] ?? '') . ' ' . ($ticket['return_arrival_time'] ?? '');
    } else {
        // Use dates and times from POST
        $departureDate = ($_POST['departure_date'] ?? '') . ' ' . ($_POST['departure_time'] ?? '');
        $arrivalDate = ($_POST['arrival_date'] ?? '') . ' ' . ($_POST['arrival_time'] ?? '');
        $returnDate = ($_POST['return_date'] ?? '') . ' ' . ($_POST['return_time'] ?? '');
        $retArrivalDate = ($_POST['ret_arrival_date'] ?? '') . ' ' . ($_POST['return_arrival_time'] ?? '');
    }
    
    $outboundFlights[] = [
        'flight_number' => $flightNumber1,
        'departure_city' => $departureCity,
        'arrival_city' => $arrivalCity,
        'departure_datetime' => $departureDate,
        'arrival_datetime' => $arrivalDate
    ];
    
    $returnFlights[] = [
        'flight_number' => $flightNumber2,
        'departure_city' => $arrivalCity,
        'arrival_city' => $departureCity,
        'departure_datetime' => $returnDate,
        'arrival_datetime' => $retArrivalDate
    ];
} elseif ($isFulfillment) {
    // Fulfillment-saved indirect flight: legs come from the flight_legs JSON
    $legs = fulfillment_flight_legs($ffCtx['flight']);
    $outboundFlights = $legs['outbound'];
    $returnFlights = $legs['return'];
} else {
    // Indirect/Connecting flight processing
    
    // Determine data source
    $dataSource = ($ticketId && isset($ticket)) ? 'database' : 'post';
    
    // Outbound Journey - First Leg
    if ($dataSource === 'database') {
        $leg1DepartureDate = ($ticket['leg1_departure_date'] ?? '') . ' ' . ($ticket['leg1_departure_time'] ?? '');
        $leg1ArrivalDate = ($ticket['leg1_arrival_date'] ?? '') . ' ' . ($ticket['leg1_arrival_time'] ?? '');
    } else {
        $leg1DepartureDate = ($_POST['leg1_departure_date'] ?? '') . ' ' . ($_POST['leg1_departure_time'] ?? '');
        $leg1ArrivalDate = ($_POST['leg1_arrival_date'] ?? '') . ' ' . ($_POST['leg1_arrival_time'] ?? '');
    }
    
    $outboundFlights[] = [
        'flight_number' => $dataSource === 'database' ? ($ticket['leg1_flight_number'] ?? 'FZ341') : ($_POST['leg1_flight_number'] ?? 'FZ341'),
        'departure_city' => $dataSource === 'database' ? ($ticket['leg1_departure_city'] ?? 'Kabul') : ($_POST['leg1_departure_city'] ?? 'Kabul'),
        'arrival_city' => $dataSource === 'database' ? ($ticket['leg1_arrival_city'] ?? 'Dubai') : ($_POST['leg1_arrival_city'] ?? 'Dubai'),
        'departure_datetime' => $leg1DepartureDate,
        'arrival_datetime' => $leg1ArrivalDate
    ];
    
    // Outbound Journey - Second Leg
    if ($dataSource === 'database') {
        $leg2DepartureDate = ($ticket['leg2_departure_date'] ?? '') . ' ' . ($ticket['leg2_departure_time'] ?? '');
        $leg2ArrivalDate = ($ticket['leg2_arrival_date'] ?? '') . ' ' . ($ticket['leg2_arrival_time'] ?? '');
    } else {
        $leg2DepartureDate = ($_POST['leg2_departure_date'] ?? '') . ' ' . ($_POST['leg2_departure_time'] ?? '');
        $leg2ArrivalDate = ($_POST['leg2_arrival_date'] ?? '') . ' ' . ($_POST['leg2_arrival_time'] ?? '');
    }
    
    $outboundFlights[] = [
        'flight_number' => $dataSource === 'database' ? ($ticket['leg2_flight_number'] ?? 'FZ415') : ($_POST['leg2_flight_number'] ?? 'FZ415'),
        'departure_city' => $dataSource === 'database' ? ($ticket['leg2_departure_city'] ?? 'Dubai') : ($_POST['leg2_departure_city'] ?? 'Dubai'),
        'arrival_city' => $dataSource === 'database' ? ($ticket['leg2_arrival_city'] ?? 'Jeddah') : ($_POST['leg2_arrival_city'] ?? 'Jeddah'),
        'departure_datetime' => $leg2DepartureDate,
        'arrival_datetime' => $leg2ArrivalDate
    ];
    
    // Return Journey - First Leg
    if ($dataSource === 'database') {
        $returnLeg1DepartureDate = ($ticket['return_leg1_departure_date'] ?? '') . ' ' . ($ticket['return_leg1_departure_time'] ?? '');
        $returnLeg1ArrivalDate = ($ticket['return_leg1_arrival_date'] ?? '') . ' ' . ($ticket['return_leg1_arrival_time'] ?? '');
    } else {
        $returnLeg1DepartureDate = ($_POST['return_leg1_departure_date'] ?? '') . ' ' . ($_POST['return_leg1_departure_time'] ?? '');
        $returnLeg1ArrivalDate = ($_POST['return_leg1_arrival_date'] ?? '') . ' ' . ($_POST['return_leg1_arrival_time'] ?? '');
    }
    
    $returnFlights[] = [
        'flight_number' => $dataSource === 'database' ? ($ticket['return_leg1_flight_number'] ?? 'FZ416') : ($_POST['return_leg1_flight_number'] ?? 'FZ416'),
        'departure_city' => $dataSource === 'database' ? ($ticket['return_leg1_departure_city'] ?? 'Jeddah') : ($_POST['return_leg1_departure_city'] ?? 'Jeddah'),
        'arrival_city' => $dataSource === 'database' ? ($ticket['return_leg1_arrival_city'] ?? 'Dubai') : ($_POST['return_leg1_arrival_city'] ?? 'Dubai'),
        'departure_datetime' => $returnLeg1DepartureDate,
        'arrival_datetime' => $returnLeg1ArrivalDate
    ];
    
    // Return Journey - Second Leg
    if ($dataSource === 'database') {
        $returnLeg2DepartureDate = ($ticket['return_leg2_departure_date'] ?? '') . ' ' . ($ticket['return_leg2_departure_time'] ?? '');
        $returnLeg2ArrivalDate = ($ticket['return_leg2_arrival_date'] ?? '') . ' ' . ($ticket['return_leg2_arrival_time'] ?? '');
    } else {
        $returnLeg2DepartureDate = ($_POST['return_leg2_departure_date'] ?? '') . ' ' . ($_POST['return_leg2_departure_time'] ?? '');
        $returnLeg2ArrivalDate = ($_POST['return_leg2_arrival_date'] ?? '') . ' ' . ($_POST['return_leg2_arrival_time'] ?? '');
    }
    
    $returnFlights[] = [
        'flight_number' => $dataSource === 'database' ? ($ticket['return_leg2_flight_number'] ?? 'FZ342') : ($_POST['return_leg2_flight_number'] ?? 'FZ342'),
        'departure_city' => $dataSource === 'database' ? ($ticket['return_leg2_departure_city'] ?? 'Dubai') : ($_POST['return_leg2_departure_city'] ?? 'Dubai'),
        'arrival_city' => $dataSource === 'database' ? ($ticket['return_leg2_arrival_city'] ?? 'Kabul') : ($_POST['return_leg2_arrival_city'] ?? 'Kabul'),
        'departure_datetime' => $returnLeg2DepartureDate,
        'arrival_datetime' => $returnLeg2ArrivalDate
    ];
}

// Fulfillment tickets print as two separate documents (outbound / return)
$renderDirection = null;
if ($isFulfillment && isset($_GET['dir'])) {
    if ($_GET['dir'] === 'outbound' || $_GET['dir'] === 'return') {
        $renderDirection = $_GET['dir'];
    }
}

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

$agencyName = $settings['agency_name'] ?? 'Travel Agency';
$agencyEmail = $branch['email'] ?? 'info@travelagency.com';
$agencyPhone = $branch['phone'] ?? '+1 (555) 123-4567';
$agencyAddress = $branch['address'] ?? '123 Travel Street';
$agencyLogoPath = '../../uploads/logo/' . ($settings['logo'] ?? 'assets/images/logo.png');
$logoBase64 = '';
if (file_exists($agencyLogoPath)) {
    $logoType = pathinfo($agencyLogoPath, PATHINFO_EXTENSION);
    $logoData = file_get_contents($agencyLogoPath);
    $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
}

// Fetch pilgrim details
$pilgrimIds = array_map(fn($p) => $p['id'], $selectedPilgrims);
$placeholders = str_repeat('?,', count($pilgrimIds) - 1) . '?';

$sql = "
    SELECT b.*, f.head_of_family, f.package_type
    FROM umrah_bookings b
    LEFT JOIN families f ON b.family_id = f.family_id AND f.tenant_id = ? AND f.branch_id = ?
    WHERE b.booking_id IN ($placeholders) AND b.tenant_id = ? AND b.branch_id = ?
";
$stmt = $pdo->prepare($sql);
$params = array_merge([$tenant_id, $branch_id], $pilgrimIds, [$tenant_id, $branch_id]);
$stmt->execute($params);
$pilgrims = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pilgrims)) die('No pilgrim data found');
?>

<?php
require __DIR__ . '/ticket_pdf_render.php';

