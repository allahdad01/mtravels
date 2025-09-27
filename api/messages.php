<?php
	session_start();
	header('Content-Type: application/json');
	require_once __DIR__ . '/../includes/db.php';

	if (!isset($_SESSION['user_id'])) {
		http_response_code(401);
		echo json_encode(['error' => 'unauthorized']);
		exit;
	}

	$currentUserId = (int)$_SESSION['user_id'];

	$method = $_SERVER['REQUEST_METHOD'];

	// Validate current user and get tenant
	$stmt = secure_query($pdo, 'SELECT id, tenant_id FROM users WHERE id = ?', [$currentUserId]);
	$me = $stmt ? $stmt->fetch() : null;
	if (!$me) { http_response_code(404); echo json_encode(['error' => 'user_not_found']); exit; }
	$tenantId = (int)$me['tenant_id'];

	function room_from_users($a, $b) {
		$ids = [$a, $b]; sort($ids, SORT_NUMERIC); return 'u-' . $ids[0] . '-' . $ids[1];
	}

	if ($method === 'GET') {
		$peerId = isset($_GET['peer_id']) ? (int)$_GET['peer_id'] : 0;
		if ($peerId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_peer']); exit; }
		$room = room_from_users($currentUserId, $peerId);

		$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
		$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
		$params = [$room];
		$where = 'room_id = ?';
		if ($beforeId > 0) { $where .= ' AND id < ?'; $params[] = $beforeId; }
		$sql = 'SELECT id, room_id, from_user_id, to_user_id, content, DATE_FORMAT(created_at, "%Y-%m-%dT%H:%i:%sZ") AS created_at, 
            DATE_FORMAT(seen_at, "%Y-%m-%dT%H:%i:%sZ") AS seen_at FROM chat_messages WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit;
		$stmt = secure_query($pdo, $sql, $params);
		$rowsDesc = $stmt ? $stmt->fetchAll() : [];
		$rows = array_reverse($rowsDesc);
		$next_before_id = count($rowsDesc) ? (int)$rowsDesc[count($rowsDesc)-1]['id'] : 0;
		echo json_encode(['room_id' => $room, 'messages' => $rows, 'next_before_id' => $next_before_id]);
		exit;
	}

	if ($method === 'POST') {
		$action = $_POST['action'] ?? '';
		if ($action === 'mark_seen') {
			$peerId = isset($_POST['peer_id']) ? (int)$_POST['peer_id'] : 0;
			if ($peerId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_peer']); exit; }
			$room = room_from_users($currentUserId, $peerId);
			$upd = secure_query($pdo, 'UPDATE chat_messages SET seen_at = NOW() WHERE room_id = ? AND to_user_id = ? AND seen_at IS NULL', [$room, $currentUserId]);
			echo json_encode(['ok' => true, 'updated' => $upd ? $upd->rowCount() : 0]);
			exit;
		}

		$toUserId = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
		$content = isset($_POST['content']) ? trim($_POST['content']) : '';
		if ($toUserId <= 0 || $content === '') { http_response_code(400); echo json_encode(['error' => 'invalid_input']); exit; }

		// Check block relations (either side)
		$blockedA = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $currentUserId, $toUserId]);
		$blockedB = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $toUserId, $currentUserId]);
		if (($blockedA && $blockedA->fetch()) || ($blockedB && $blockedB->fetch())) { http_response_code(403); echo json_encode(['error' => 'blocked']); exit; }

		// Check tenant peering if cross-tenant
		$peerStmt = secure_query($pdo, 'SELECT tenant_id FROM users WHERE id = ?', [$toUserId]);
		$peer = $peerStmt ? $peerStmt->fetch() : null;
		if (!$peer) { http_response_code(404); echo json_encode(['error' => 'peer_not_found']); exit; }
		$peerTenant = (int)$peer['tenant_id'];
		if ($peerTenant !== $tenantId) {
			$allow = secure_query($pdo, 'SELECT 1 FROM tenant_peering WHERE status = "approved" AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) LIMIT 1', [$tenantId, $peerTenant, $peerTenant, $tenantId]);
			if (!$allow || !$allow->fetch()) { http_response_code(403); echo json_encode(['error' => 'peer_not_allowed']); exit; }
		}

		$room = room_from_users($currentUserId, $toUserId);
		$stmt = secure_query($pdo, 'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content) VALUES (?, ?, ?, ?, ?)', [$room, $currentUserId, $toUserId, $tenantId, $content]);
		if (!$stmt) { http_response_code(500); echo json_encode(['error' => 'save_failed']); exit; }
		$id = $pdo->lastInsertId();
		echo json_encode(['ok' => true, 'id' => (int)$id, 'room_id' => $room]);
		exit;
	}

	if ($method === 'PUT') {
		$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($messageId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_message_id']); exit; }

		$input = json_decode(file_get_contents('php://input'), true);
		$content = isset($input['content']) ? trim($input['content']) : '';
		if ($content === '') { http_response_code(400); echo json_encode(['error' => 'invalid_content']); exit; }

		// Check if message exists and belongs to current user
		$stmt = secure_query($pdo, 'SELECT from_user_id FROM chat_messages WHERE id = ? AND from_user_id = ?', [$messageId, $currentUserId]);
		if (!$stmt || !$stmt->fetch()) { http_response_code(403); echo json_encode(['error' => 'not_authorized_or_not_found']); exit; }

		$upd = secure_query($pdo, 'UPDATE chat_messages SET content = ? WHERE id = ?', [$content, $messageId]);
		if (!$upd) { http_response_code(500); echo json_encode(['error' => 'update_failed']); exit; }

		echo json_encode(['ok' => true, 'updated' => $upd->rowCount()]);
		exit;
	}

	if ($method === 'DELETE') {
		$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		if ($messageId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_message_id']); exit; }

		// Check if message exists and belongs to current user
		$stmt = secure_query($pdo, 'SELECT from_user_id FROM chat_messages WHERE id = ? AND from_user_id = ?', [$messageId, $currentUserId]);
		if (!$stmt || !$stmt->fetch()) { http_response_code(403); echo json_encode(['error' => 'not_authorized_or_not_found']); exit; }

		$del = secure_query($pdo, 'DELETE FROM chat_messages WHERE id = ?', [$messageId]);
		if (!$del) { http_response_code(500); echo json_encode(['error' => 'delete_failed']); exit; }

		echo json_encode(['ok' => true, 'deleted' => $del->rowCount()]);
		exit;
	}

	http_response_code(405);
	echo json_encode(['error' => 'method_not_allowed']);
	exit;
?>

