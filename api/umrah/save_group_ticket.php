<?php
header('Content-Type: application/json');

try {
    require_once '../../includes/db.php';
    require_once '../../admin/security.php';
    
    enforce_auth();
    
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }
    
    // Check permissions
    $allowed_roles = ['admin', 'finance', 'umrah'];
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        throw new Exception('Unauthorized access');
    }
    
    // Get input data
    $selected_members = isset($_POST['selected_members']) ? json_decode($_POST['selected_members'], true) : [];
    $airline_name = isset($_POST['airline_name']) ? trim($_POST['airline_name']) : '';
    $pnr = isset($_POST['pnr']) ? trim($_POST['pnr']) : '';
    $flight_type = isset($_POST['flight_type']) ? trim($_POST['flight_type']) : 'direct';
    $flight_date = isset($_POST['flight_date']) ? trim($_POST['flight_date']) : '';
    $return_date = isset($_POST['return_date']) ? trim($_POST['return_date']) : '';
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Validate inputs
    if (empty($selected_members) || !is_array($selected_members)) {
        throw new Exception('Please select at least one member');
    }
    
    if (!$airline_name || !$pnr || !$flight_date || !$return_date) {
        throw new Exception('All required fields must be filled');
    }
    
    // Validate dates
    $flightDateTime = DateTime::createFromFormat('Y-m-d', $flight_date);
    $returnDateTime = DateTime::createFromFormat('Y-m-d', $return_date);
    
    if (!$flightDateTime || !$returnDateTime) {
        throw new Exception('Invalid date format');
    }
    
    if ($returnDateTime <= $flightDateTime) {
        throw new Exception('Return date must be after flight date');
    }
    
    // Extract booking IDs and get family IDs
    $booking_ids = array_column($selected_members, 'id');
    $booking_ids = array_map('intval', $booking_ids);
    
    if (empty($booking_ids)) {
        throw new Exception('Invalid member data');
    }
    
    // Get family IDs from bookings
    $placeholders = implode(',', array_fill(0, count($booking_ids), '?'));
    $getFamiliesStmt = $pdo->prepare("
        SELECT DISTINCT family_id FROM umrah_bookings 
        WHERE booking_id IN ($placeholders) AND tenant_id = ? AND branch_id = ?
    ");
    $params = array_merge($booking_ids, [$tenant_id, $branch_id]);
    $getFamiliesStmt->execute($params);
    $familiesResult = $getFamiliesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $family_ids = array_column($familiesResult, 'family_id');
    
    if (empty($family_ids)) {
        throw new Exception('No valid families found for selected members');
    }
    
    // Update flight_date and return_date in umrah_bookings
    // NOTE: the member's own `duration` is intentionally NOT overwritten.
    // The group ticket must fit inside each member's existing package duration,
    // otherwise we abort below and tell the user which members conflict.
    $duration_days = $returnDateTime->diff($flightDateTime)->days;
    $duration = ($duration_days + 1) . ' Days';

    // Load each selected member's stored duration (single query) so we can
    // validate it against the group ticket's return date before saving.
    $bookingPlaceholders = implode(',', array_fill(0, count($booking_ids), '?'));
    $durationStmt = $pdo->prepare("
        SELECT booking_id, name, duration
        FROM umrah_bookings
        WHERE booking_id IN ($bookingPlaceholders) AND tenant_id = ? AND branch_id = ?
    ");
    $durationStmt->execute(array_merge($booking_ids, [$tenant_id, $branch_id]));
    $existingDurations = $durationStmt->fetchAll(PDO::FETCH_ASSOC);

    // A member's own return falls on flight_date + (duration - 1) days. If that
    // extends past the group ticket's return date, the member's package duration
    // is longer than the trip allows, so block the save and explain.
    $conflictingMembers = [];
    foreach ($existingDurations as $bm) {
        $durationValue = trim((string)($bm['duration'] ?? ''));
        if ($durationValue === '' || !preg_match('/^\s*(\d+)\s*(?:days?)/i', $durationValue, $m)) {
            continue;
        }
        $memberDays = max(1, (int)$m[1]);
        $memberReturn = (clone $flightDateTime)->modify('+' . ($memberDays - 1) . ' days');
        if ($memberReturn > $returnDateTime) {
            $conflictingMembers[] = $bm['name'] ?: ('Booking #' . $bm['booking_id']);
        }
    }

    if (!empty($conflictingMembers)) {
        throw new Exception(
            'Cannot save group ticket: these members\' duration exceeds the return date (' . $returnDate . '): ' .
            implode(', ', $conflictingMembers) . '. ' .
            'Their existing duration is longer than the group flight period. ' .
            'Adjust the group return date or change those members\' duration first.'
        );
    }

    $updateBookingsStmt = $pdo->prepare("
        UPDATE umrah_bookings 
        SET flight_date = ?, 
            return_date = ?,
            updated_at = NOW()
        WHERE booking_id = ? 
        AND tenant_id = ? 
        AND branch_id = ?
    ");
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update each booking with flight and return dates (duration left untouched)
    foreach ($booking_ids as $booking_id) {
        $updateBookingsStmt->execute([
            $flight_date,
            $return_date,
            $booking_id,
            $tenant_id,
            $branch_id
        ]);
    }
    
    // Prepare data for group_tickets table
    $direct_fields = [];
    $indirect_fields = [];
    
    if ($flight_type === 'direct') {
        $direct_fields = [
            'departure_city' => isset($_POST['departure_city']) ? trim($_POST['departure_city']) : '',
            'arrival_city' => isset($_POST['arrival_city']) ? trim($_POST['arrival_city']) : '',
            'flight_number_1' => isset($_POST['flight_number_1']) ? trim($_POST['flight_number_1']) : '',
            'flight_number_2' => isset($_POST['flight_number_2']) ? trim($_POST['flight_number_2']) : '',
            'departure_time' => isset($_POST['departure_time']) ? trim($_POST['departure_time']) : '',
            'arrival_time' => isset($_POST['arrival_time']) ? trim($_POST['arrival_time']) : '',
            'return_time' => isset($_POST['return_time']) ? trim($_POST['return_time']) : '',
            'return_arrival_time' => isset($_POST['return_arrival_time']) ? trim($_POST['return_arrival_time']) : ''
        ];
    } else {
        // Indirect flight data
        $indirect_fields = [
            'leg1_departure_city' => isset($_POST['leg1_departure_city']) ? trim($_POST['leg1_departure_city']) : '',
            'leg1_arrival_city' => isset($_POST['leg1_arrival_city']) ? trim($_POST['leg1_arrival_city']) : '',
            'leg1_flight_number' => isset($_POST['leg1_flight_number']) ? trim($_POST['leg1_flight_number']) : '',
            'leg1_departure_date' => isset($_POST['leg1_departure_date']) ? trim($_POST['leg1_departure_date']) : '',
            'leg1_departure_time' => isset($_POST['leg1_departure_time']) ? trim($_POST['leg1_departure_time']) : '',
            'leg1_arrival_date' => isset($_POST['leg1_arrival_date']) ? trim($_POST['leg1_arrival_date']) : '',
            'leg1_arrival_time' => isset($_POST['leg1_arrival_time']) ? trim($_POST['leg1_arrival_time']) : '',
            'leg2_departure_city' => isset($_POST['leg2_departure_city']) ? trim($_POST['leg2_departure_city']) : '',
            'leg2_arrival_city' => isset($_POST['leg2_arrival_city']) ? trim($_POST['leg2_arrival_city']) : '',
            'leg2_flight_number' => isset($_POST['leg2_flight_number']) ? trim($_POST['leg2_flight_number']) : '',
            'leg2_departure_date' => isset($_POST['leg2_departure_date']) ? trim($_POST['leg2_departure_date']) : '',
            'leg2_departure_time' => isset($_POST['leg2_departure_time']) ? trim($_POST['leg2_departure_time']) : '',
            'leg2_arrival_date' => isset($_POST['leg2_arrival_date']) ? trim($_POST['leg2_arrival_date']) : '',
            'leg2_arrival_time' => isset($_POST['leg2_arrival_time']) ? trim($_POST['leg2_arrival_time']) : '',
            'return_leg1_departure_city' => isset($_POST['return_leg1_departure_city']) ? trim($_POST['return_leg1_departure_city']) : '',
            'return_leg1_arrival_city' => isset($_POST['return_leg1_arrival_city']) ? trim($_POST['return_leg1_arrival_city']) : '',
            'return_leg1_flight_number' => isset($_POST['return_leg1_flight_number']) ? trim($_POST['return_leg1_flight_number']) : '',
            'return_leg1_departure_date' => isset($_POST['return_leg1_departure_date']) ? trim($_POST['return_leg1_departure_date']) : '',
            'return_leg1_departure_time' => isset($_POST['return_leg1_departure_time']) ? trim($_POST['return_leg1_departure_time']) : '',
            'return_leg1_arrival_date' => isset($_POST['return_leg1_arrival_date']) ? trim($_POST['return_leg1_arrival_date']) : '',
            'return_leg1_arrival_time' => isset($_POST['return_leg1_arrival_time']) ? trim($_POST['return_leg1_arrival_time']) : '',
            'return_leg2_departure_city' => isset($_POST['return_leg2_departure_city']) ? trim($_POST['return_leg2_departure_city']) : '',
            'return_leg2_arrival_city' => isset($_POST['return_leg2_arrival_city']) ? trim($_POST['return_leg2_arrival_city']) : '',
            'return_leg2_flight_number' => isset($_POST['return_leg2_flight_number']) ? trim($_POST['return_leg2_flight_number']) : '',
            'return_leg2_departure_date' => isset($_POST['return_leg2_departure_date']) ? trim($_POST['return_leg2_departure_date']) : '',
            'return_leg2_departure_time' => isset($_POST['return_leg2_departure_time']) ? trim($_POST['return_leg2_departure_time']) : '',
            'return_leg2_arrival_date' => isset($_POST['return_leg2_arrival_date']) ? trim($_POST['return_leg2_arrival_date']) : '',
            'return_leg2_arrival_time' => isset($_POST['return_leg2_arrival_time']) ? trim($_POST['return_leg2_arrival_time']) : ''
        ];
    }
    
    // Insert into group_tickets table
    $sql = "INSERT INTO group_tickets 
            (tenant_id, branch_id, family_ids, member_ids, airline_name, pnr, flight_type, flight_date, return_date, duration, remarks, created_by";
    
    $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
    $params = [
        $tenant_id,
        $branch_id,
        json_encode($family_ids),
        json_encode($booking_ids),
        $airline_name,
        $pnr,
        $flight_type,
        $flight_date,
        $return_date,
        $duration,
        $remarks,
        $user_id
    ];
    
    // Add direct flight fields if applicable
    if ($flight_type === 'direct') {
        $sql .= ", departure_city, arrival_city, flight_number_1, flight_number_2, departure_time, arrival_time, return_time, return_arrival_time";
        $values .= ", ?, ?, ?, ?, ?, ?, ?, ?";
        $params[] = $direct_fields['departure_city'];
        $params[] = $direct_fields['arrival_city'];
        $params[] = $direct_fields['flight_number_1'];
        $params[] = $direct_fields['flight_number_2'];
        $params[] = $direct_fields['departure_time'];
        $params[] = $direct_fields['arrival_time'];
        $params[] = $direct_fields['return_time'];
        $params[] = $direct_fields['return_arrival_time'];
    } else {
        // Add indirect flight fields
        foreach ($indirect_fields as $field => $value) {
            $sql .= ", $field";
            $values .= ", ?";
            $params[] = $value;
        }
    }
    
    $sql .= ") VALUES ($values)";
    
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute($params);
    $ticket_id = $pdo->lastInsertId();
    
    // Log the activity
    $new_values = json_encode([
        'ticket_id' => $ticket_id,
        'action' => 'create_group_ticket',
        'airline_name' => $airline_name,
        'pnr' => $pnr,
        'flight_type' => $flight_type,
        'flight_date' => $flight_date,
        'return_date' => $return_date,
        'duration' => $duration,
        'members_count' => count($booking_ids),
        'families_count' => count($family_ids),
        'member_ids' => $booking_ids,
        'family_ids' => $family_ids
    ]);
    
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log
            (tenant_id, branch_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, 'create', 'group_tickets', ?, '[]', ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $tenant_id,
            $branch_id,
            $user_id,
            $ticket_id,
            $new_values,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $logError) {
        error_log("Activity log error: " . $logError->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Group ticket created successfully with ' . count($booking_ids) . ' member(s)',
        'ticket_id' => $ticket_id,
        'members_updated' => count($booking_ids),
        'families_updated' => count($family_ids)
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
