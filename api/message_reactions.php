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

	if ($method === 'GET') {
		$messageId = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;
		if ($messageId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_message_id']); exit; }

		// Get all reactions for this message
		$stmt = secure_query($pdo, '
			SELECT mr.emoji, mr.user_id, u.name as user_name
			FROM message_reactions mr
			JOIN users u ON mr.user_id = u.id
			WHERE mr.message_id = ?
			ORDER BY mr.created_at ASC
		', [$messageId]);

		$reactions = [];
		if ($stmt) {
			while ($row = $stmt->fetch()) {
				$emoji = $row['emoji'];
				if (!isset($reactions[$emoji])) {
					$reactions[$emoji] = [];
				}
				$reactions[$emoji][] = [
					'user_id' => (int)$row['user_id'],
					'user_name' => $row['user_name']
				];
			}
		}

		echo json_encode(['reactions' => $reactions]);
		exit;
	}

	if ($method === 'POST') {
		$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
		$emoji = isset($_POST['emoji']) ? trim($_POST['emoji']) : '';
		$action = isset($_POST['action']) ? $_POST['action'] : 'add'; // 'add' or 'remove'

		if ($messageId <= 0 || empty($emoji)) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_input']);
			exit;
		}

		// Verify message exists and user has access to it
		$stmt = secure_query($pdo, '
			SELECT cm.room_id
			FROM chat_messages cm
			WHERE cm.id = ? AND (cm.from_user_id = ? OR cm.to_user_id = ?)
		', [$messageId, $currentUserId, $currentUserId]);

		if (!$stmt || !$stmt->fetch()) {
			http_response_code(403);
			echo json_encode(['error' => 'message_not_found_or_access_denied']);
			exit;
		}

		if ($action === 'add') {
			// Check if reaction already exists
			$existing = secure_query($pdo, '
				SELECT id FROM message_reactions
				WHERE message_id = ? AND user_id = ? AND emoji = ?
			', [$messageId, $currentUserId, $emoji]);

			if ($existing && $existing->fetch()) {
				// Reaction already exists, remove it instead
				$del = secure_query($pdo, '
					DELETE FROM message_reactions
					WHERE message_id = ? AND user_id = ? AND emoji = ?
				', [$messageId, $currentUserId, $emoji]);
				echo json_encode(['ok' => true, 'action' => 'removed']);
			} else {
				// Add new reaction
				$stmt = secure_query($pdo, '
					INSERT INTO message_reactions (message_id, user_id, emoji, tenant_id)
					VALUES (?, ?, ?, ?)
				', [$messageId, $currentUserId, $emoji, $tenantId]);
				echo json_encode(['ok' => true, 'action' => 'added']);
			}
		} elseif ($action === 'remove') {
			// Remove specific reaction
			$del = secure_query($pdo, '
				DELETE FROM message_reactions
				WHERE message_id = ? AND user_id = ? AND emoji = ?
			', [$messageId, $currentUserId, $emoji]);
			echo json_encode(['ok' => true, 'action' => 'removed']);
		} else {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_action']);
		}

		exit;
	}

	if ($method === 'DELETE') {
		$messageId = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;
		$emoji = isset($_GET['emoji']) ? trim($_GET['emoji']) : '';

		if ($messageId <= 0) {
			http_response_code(400);
			echo json_encode(['error' => 'invalid_message_id']);
			exit;
		}

		// Remove all reactions by current user from this message, or specific emoji
		if (!empty($emoji)) {
			$del = secure_query($pdo, '
				DELETE FROM message_reactions
				WHERE message_id = ? AND user_id = ? AND emoji = ?
			', [$messageId, $currentUserId, $emoji]);
		} else {
			$del = secure_query($pdo, '
				DELETE FROM message_reactions
				WHERE message_id = ? AND user_id = ?
			', [$messageId, $currentUserId]);
		}

		echo json_encode(['ok' => true, 'deleted' => $del ? $del->rowCount() : 0]);
		exit;
	}

	http_response_code(405);
	echo json_encode(['error' => 'method_not_allowed']);
	exit;
?>