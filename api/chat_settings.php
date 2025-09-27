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
	$stmt = secure_query($pdo, 'SELECT t.chat_max_file_bytes, t.chat_allowed_mime_prefixes, t.chat_default_auto_download FROM tenants t JOIN users u ON u.tenant_id = t.id WHERE u.id = ?', [$currentUserId]);
	$row = $stmt ? $stmt->fetch() : null;
	if (!$row) { http_response_code(404); echo json_encode(['error' => 'tenant_not_found']); exit; }

	echo json_encode([
		'max_file_bytes' => (int)$row['chat_max_file_bytes'],
		'allowed_mime_prefixes' => explode(',', (string)$row['chat_allowed_mime_prefixes']),
		'default_auto_download' => (bool)$row['chat_default_auto_download']
	]);
	exit;
?>

