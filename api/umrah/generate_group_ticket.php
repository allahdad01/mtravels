<?php
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../includes/language_helpers.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
enforce_auth();

// Check if ticket_id is provided (GET parameter from database record)
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;

if ($ticketId) {
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
if ($ticketId && isset($ticket)) {
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
    if ($ticketId && isset($ticket)) {
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
    error_log("Settings Error: " . $e->getMessage());
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
    error_log("Branch Error: " . $e->getMessage());
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

// Format dates for display
function formatFlightDate($dateTime) {
    if (empty($dateTime) || trim($dateTime) === '') return '';
    $date = DateTime::createFromFormat('Y-m-d H:i', trim($dateTime));
    return $date ? $date->format('H:i / d. M. Y') : $dateTime;
}

// Calculate stopover duration
function calculateStopover($arrivalTime, $departureTime) {
    if (empty($arrivalTime) || empty($departureTime)) return '';
    
    $arrival = DateTime::createFromFormat('Y-m-d H:i', trim($arrivalTime));
    $departure = DateTime::createFromFormat('Y-m-d H:i', trim($departureTime));
    
    if (!$arrival || !$departure) return '';
    
    $diff = $departure->diff($arrival);
    return sprintf('%dh %dm', $diff->h + ($diff->days * 24), $diff->i);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Ticket - <?php echo htmlspecialchars($pnr); ?></title>
    <style>
        @media print {
            body { margin: 0; padding: 20px; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
        
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11pt; 
            color: #000; 
            margin: 0; 
            padding: 20px; 
            line-height: 1.3;
            background-color: white;
        }
        
        .container { 
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header { 
            margin-bottom: 20px; 
            padding-bottom: 15px;
            border-bottom: 2px solid #2c3e50;
            display: table;
            width: 100%;
        }
        
        .header-left,
        .header-center,
        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 33.33%;
        }
        
        .header-left {
            text-align: left;
        }
        
        .header-center {
            text-align: center;
        }
        
        .header-right {
            text-align: right;
        }
        
        .logo { 
            max-width: 80px; 
            height: auto;
        }
        
        .company-name { 
            font-size: 18pt; 
            font-weight: bold; 
            color: #2c3e50; 
            text-transform: uppercase; 
            margin: 0;
        }
        
        .contact-info {
            font-size: 10pt;
            color: #666;
            line-height: 1.4;
        }
        
        .contact-email {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .contact-phone {
            margin-top: 2px;
        }
        
        .contact-address {
            margin-top: 2px;
            font-size: 9pt;
        }
        
        .flight-details-header {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            margin: 25px 0 5px 0;
        }
        
        .pnr-display {
            font-size: 12pt;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .flight-type-badge {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
        }
        
        .flight-section {
            margin: 20px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .section-header {
            background-color: #f8f9fa;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 12pt;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
        }
        
        .outbound { border-left: 4px solid #27ae60; }
        .return { border-left: 4px solid #e67e22; }
        
        .flight-layout-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .flight-layout-table td {
            vertical-align: top;
            padding: 15px;
            border: none;
        }
        
        .flight-departs {
            width: 40%;
        }
        
        .flight-center {
            width: 20%;
            text-align: center;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
        }
        
        .flight-arrives {
            width: 40%;
            text-align: right;
        }
        
        .flight-label {
            font-size: 11pt;
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
        }
        
        .flight-city {
            font-size: 16pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 5px;
        }
        
        .flight-time {
            font-size: 11pt;
            color: #333;
        }
        
        .flight-number {
            font-size: 14pt;
            font-weight: bold;
            color: #000;
            margin-bottom: 8px;
        }
        
        .plane-icon {
            font-size: 18pt;
            color: #666;
        }
        
        .flight-separator {
            height: 1px;
            background-color: #eee;
            margin: 10px 0;
        }
        
        .stopover-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 10pt;
            text-align: center;
        }
        
        .stopover-duration {
            font-weight: bold;
            color: #856404;
        }
        
        .passengers-header {
            font-size: 14pt;
            font-weight: bold;
            margin: 30px 0 15px 0;
            color: #2c3e50;
            text-decoration: underline;
        }
        
        .passengers-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px;
            font-size: 10pt;
        }
        
        .passengers-table th { 
            background-color: #2c3e50;
            color: white;
            font-weight: bold; 
            padding: 10px 8px;
            border: 1px solid #2c3e50;
            text-align: left;
        }
        
        .passengers-table td { 
            padding: 8px; 
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .passengers-table tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        
        .passengers-table tr:hover { 
            background-color: #f0f8ff; 
        }
        
        .sno-col { 
            width: 50px; 
            text-align: center; 
            font-weight: bold;
        }
        
        .name-col { 
            width: 35%; 
        }
        
        .passport-col { 
            width: 25%; 
            font-weight: bold;
        }
        
        .gender-col { 
            width: 60px; 
            text-align: center; 
            font-weight: bold;
        }
        
        .remarks { 
            margin-top: 20px; 
            font-size: 10pt;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 5px solid #2c3e50;
            border-radius: 5px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12pt;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .print-button:hover {
            background-color: #34495e;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>
    
    <div class="container">
        <div class="header">
            <div class="header-left">
                <?php if (!empty($logoBase64)): ?>
                    <img src="<?php echo $logoBase64; ?>" class="logo" alt="Company Logo">
                <?php endif; ?>
            </div>
            <div class="header-center">
                <div class="company-name"><?php echo htmlspecialchars($agencyName); ?> - <?php echo htmlspecialchars($branch['name']); ?></div>
            </div>
            <div class="header-right">
                <div class="contact-info">
                    <div class="contact-email"><?php echo htmlspecialchars($agencyEmail); ?></div>
                    <div class="contact-phone"><?php echo htmlspecialchars($agencyPhone); ?></div>
                    <?php if (!empty($agencyAddress)): ?>
                        <div class="contact-address"><?php echo htmlspecialchars($agencyAddress); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="flight-details-header">Your Flight Details</div>
        <div class="pnr-display">PNR: <?php echo htmlspecialchars($pnr); ?></div>
        <div class="flight-type-badge">
            <?php echo $flightType === 'direct' ? '✈ Direct Flight' : '🔄 Connecting Flight'; ?>
        </div>

        <!-- Outbound Journey -->
        <div class="flight-section outbound">
            <div class="section-header">
                <i>🛫</i> Outbound Journey
            </div>
            
            <?php foreach ($outboundFlights as $index => $flight): ?>
                <table class="flight-layout-table">
                    <tr>
                        <td class="flight-departs">
                            <div class="flight-label">Departs</div>
                            <div class="flight-city"><?php echo htmlspecialchars($flight['departure_city']); ?></div>
                            <div class="flight-time"><?php echo formatFlightDate($flight['departure_datetime']); ?></div>
                        </td>
                        <td class="flight-center">
                            <div class="flight-number"><?php echo htmlspecialchars($flight['flight_number']); ?></div>
                            <div class="plane-icon">✈</div>
                        </td>
                        <td class="flight-arrives">
                            <div class="flight-label">Arrives</div>
                            <div class="flight-city"><?php echo htmlspecialchars($flight['arrival_city']); ?></div>
                            <div class="flight-time"><?php echo formatFlightDate($flight['arrival_datetime']); ?></div>
                        </td>
                    </tr>
                </table>
                
                <?php if (count($outboundFlights) > 1 && $index < count($outboundFlights) - 1): ?>
                    <?php 
                    $stopoverDuration = calculateStopover(
                        $flight['arrival_datetime'], 
                        $outboundFlights[$index + 1]['departure_datetime']
                    ); 
                    ?>
                    <div class="stopover-info">
                        <strong>Stopover in <?php echo htmlspecialchars($flight['arrival_city']); ?>:</strong>
                        <span class="stopover-duration"><?php echo $stopoverDuration; ?></span>
                    </div>
                    <div class="flight-separator"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Return Journey -->
        <div class="flight-section return">
            <div class="section-header">
                <i>🛬</i> Return Journey
            </div>
            
            <?php foreach ($returnFlights as $index => $flight): ?>
                <table class="flight-layout-table">
                    <tr>
                        <td class="flight-departs">
                            <div class="flight-label">Departs</div>
                            <div class="flight-city"><?php echo htmlspecialchars($flight['departure_city']); ?></div>
                            <div class="flight-time"><?php echo formatFlightDate($flight['departure_datetime']); ?></div>
                        </td>
                        <td class="flight-center">
                            <div class="flight-number"><?php echo htmlspecialchars($flight['flight_number']); ?></div>
                            <div class="plane-icon">✈</div>
                        </td>
                        <td class="flight-arrives">
                            <div class="flight-label">Arrives</div>
                            <div class="flight-city"><?php echo htmlspecialchars($flight['arrival_city']); ?></div>
                            <div class="flight-time"><?php echo formatFlightDate($flight['arrival_datetime']); ?></div>
                        </td>
                    </tr>
                </table>
                
                <?php if (count($returnFlights) > 1 && $index < count($returnFlights) - 1): ?>
                    <?php 
                    $stopoverDuration = calculateStopover(
                        $flight['arrival_datetime'], 
                        $returnFlights[$index + 1]['departure_datetime']
                    ); 
                    ?>
                    <div class="stopover-info">
                        <strong>Stopover in <?php echo htmlspecialchars($flight['arrival_city']); ?>:</strong>
                        <span class="stopover-duration"><?php echo $stopoverDuration; ?></span>
                    </div>
                    <div class="flight-separator"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="passengers-header">Passengers Details</div>
        
        <table class="passengers-table">
            <thead>
                <tr>
                    <th class="sno-col">S/NO</th>
                    <th class="name-col">First Name</th>
                    <th class="name-col">Last Name</th>
                    <th class="passport-col">Passport No</th>
                    <th class="gender-col">Gender</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pilgrims as $i => $p): ?>
                    <?php 
                    // Split name into first and last name
                    $nameParts = explode(' ', trim($p['name']), 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';
                    
                    // Determine gender (you might need to add gender field to your database)
                    $gender = strtoupper(substr($p['gender'] ?? 'M', 0, 1)); // Assumes you have gender field
                    ?>
                    <tr>
                        <td class="sno-col"><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars(strtoupper($firstName)); ?></td>
                        <td><?php echo htmlspecialchars(strtoupper($lastName)); ?></td>
                        <td><?php echo htmlspecialchars($p['passport_number']); ?></td>
                        <td class="gender-col"><?php echo $gender; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($remarks)): ?>
            <div class="remarks">
                <strong>Remarks:</strong> <?php echo htmlspecialchars($remarks); ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-focus for printing
        window.addEventListener('load', function() {
            // Auto print if URL contains print parameter
            if (window.location.search.includes('print=1')) {
                window.print();
            }
        });
    </script>
</body>
</html>