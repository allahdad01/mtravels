<?php
	session_start();
	header('Content-Type: application/json');
	require_once dirname(__FILE__) . '/../includes/db.php';

	if (!isset($_SESSION['user_id'])) {
		http_response_code(401);
		echo json_encode(['error' => 'unauthorized']);
		exit;
	}

	$currentUserId = (int)$_SESSION['user_id'];
	
	// Get user with tenant and branch
	$userStmt = secure_query($pdo, 'SELECT u.tenant_id, u.branch_id FROM users u WHERE u.id = ?', [$currentUserId]);
	$user = $userStmt ? $userStmt->fetch(PDO::FETCH_ASSOC) : null;
	if (!$user) {
		http_response_code(404);
		echo json_encode(['error' => 'user_not_found']);
		exit;
	}
	
	$tenantId = (int)$user['tenant_id'];
	$branchId = (int)$user['branch_id'];
	
	// Try to get branch-specific settings first
	$stmt = secure_query($pdo, 'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download FROM branch_chat_settings WHERE tenant_id = ? AND branch_id = ?', [$tenantId, $branchId]);
	$row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
	
	// Fallback to tenant settings if branch settings don't exist
	if (!$row) {
		$fallbackStmt = secure_query($pdo, 'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download FROM tenants WHERE id = ?', [$tenantId]);
		$row = $fallbackStmt ? $fallbackStmt->fetch(PDO::FETCH_ASSOC) : null;
	}
	
	// Use defaults if still no settings found
	if (!$row) {
		$row = [
			'chat_max_file_bytes' => 26214400,
			'chat_allowed_mime_prefixes' => 'image/,video/,audio/,application/pdf,text/',
			'chat_default_auto_download' => 0
		];
	}

	echo json_encode([
		'max_file_bytes' => (int)$row['chat_max_file_bytes'],
		'allowed_mime_prefixes' => explode(',', (string)$row['chat_allowed_mime_prefixes']),
		'default_auto_download' => (bool)$row['chat_default_auto_download'],
		'branch_id' => $branchId,
		'tenant_id' => $tenantId
	]);
	exit;
?>

