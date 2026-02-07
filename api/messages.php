<?php
	session_start();
	header('Content-Type: application/json');
	require_once __DIR__ . '/../includes/db.php';
	require_once __DIR__ . '/../includes/MessageEncryption.php';
	require_once __DIR__ . '/../includes/ChatAudit.php';
	require_once __DIR__ . '/../includes/RateLimiter.php';

	if (!isset($_SESSION['user_id'])) {
		http_response_code(401);
		echo json_encode(['error' => 'unauthorized']);
		exit;
	}

	// CSRF Protection for POST/PUT/DELETE requests
	if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE']) && !verify_csrf_token()) {
		http_response_code(403);
		echo json_encode(['error' => 'Security validation failed. Please try again.']);
		exit;
	}
	
	// Initialize encryption handler
	$encryptor = new MessageEncryption($pdo);

	$currentUserId = (int)$_SESSION['user_id'];

	$method = $_SERVER['REQUEST_METHOD'];

	// Validate current user and get tenant and role
	$stmt = secure_query($pdo, 'SELECT id, tenant_id, role FROM users WHERE id = ?', [$currentUserId]);
	$me = $stmt ? $stmt->fetch() : null;
	if (!$me) { http_response_code(404); echo json_encode(['error' => 'user_not_found']); exit; }
	$tenantId = (int)$me['tenant_id'];
	$userRole = $me['role'];

	function room_from_users($a, $b) {
		$ids = [$a, $b]; sort($ids, SORT_NUMERIC); return 'u-' . $ids[0] . '-' . $ids[1];
	}

	if ($method === 'GET') {
		$peerId = isset($_GET['peer_id']) ? (int)$_GET['peer_id'] : 0;
		if ($peerId <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid_peer']); exit; }
		
		// Validate peer exists and get their info
		$peerStmt = secure_query($pdo, 'SELECT id, tenant_id, branch_id, role FROM users WHERE id = ?', [$peerId]);
		$peerUser = $peerStmt ? $peerStmt->fetch(PDO::FETCH_ASSOC) : null;
		if (!$peerUser) { http_response_code(404); echo json_encode(['error' => 'peer_not_found']); exit; }
		
		$peerTenant = (int)$peerUser['tenant_id'];
		$peerBranch = (int)$peerUser['branch_id'];
		$peerRole = $peerUser['role'];
		
		// Get current user's branch
		$meStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
		$meUser = $meStmt ? $meStmt->fetch(PDO::FETCH_ASSOC) : null;
		$myBranch = $meUser ? (int)$meUser['branch_id'] : 0;
		
		// Validate communication is allowed
		if ($peerTenant === $tenantId) {
			// Same tenant: must be same branch UNLESS either user is tenant_super_admin
			if ($peerBranch !== $myBranch && $userRole !== 'tenant_super_admin' && $peerRole !== 'tenant_super_admin') {
				ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $peerId, 'read_message', 'cross_branch_denied', 'Cross-branch chat not allowed');
				http_response_code(403);
				echo json_encode(['error' => 'cross_branch_chat_not_allowed']);
				exit;
			}
		} else {
			// Cross-tenant: check both tenant and branch peering
			// First check tenant-level peering (optional - can work with just branch peering)
			$tenantPeeringAllow = secure_query($pdo, 'SELECT 1 FROM tenant_peering WHERE status = "approved" AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) LIMIT 1', [$tenantId, $peerTenant, $peerTenant, $tenantId]);
			$tenantPeeringExists = $tenantPeeringAllow && $tenantPeeringAllow->fetch();
			
			// Then check branch-level peering (both directions with full tenant+branch validation)
			$branchPeeringAllow = secure_query($pdo, 
				'SELECT 1 FROM branch_peering WHERE status = "approved" AND (
					(tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
					OR
					(tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
				) LIMIT 1', 
				[$tenantId, $myBranch, $peerTenant, $peerBranch, $peerTenant, $peerBranch, $tenantId, $myBranch]);
			$branchPeeringExists = $branchPeeringAllow && $branchPeeringAllow->fetch();
			
			// Allow if EITHER tenant peering OR branch peering is approved
			if (!$tenantPeeringExists && !$branchPeeringExists) { 
				ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $peerId, 'read_message', 'peer_not_allowed', 'Peer not allowed');
				http_response_code(403); 
				echo json_encode(['error' => 'peer_not_allowed']); 
				exit; 
			}
		}
		
		$room = room_from_users($currentUserId, $peerId);

		$limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
		$beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
		$params = [$room];
		$where = 'room_id = ?';
		if ($beforeId > 0) { $where .= ' AND id < ?'; $params[] = $beforeId; }
		$sql = 'SELECT id, room_id, from_user_id, to_user_id, content, encrypted_content, is_encrypted, encryption_key_id, tenant_id_from, message_type, duration,
		        DATE_FORMAT(created_at, "%Y-%m-%dT%H:%i:%sZ") AS created_at, 
                DATE_FORMAT(seen_at, "%Y-%m-%dT%H:%i:%sZ") AS seen_at FROM chat_messages WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $limit;
		$stmt = secure_query($pdo, $sql, $params);
		$rowsDesc = $stmt ? $stmt->fetchAll() : [];
		
		// Decrypt encrypted messages and extract voice message metadata
		foreach ($rowsDesc as &$row) {
			// Check if message is encrypted (is_encrypted can be 0, 1, or NULL)
			if (!empty($row['is_encrypted']) && !empty($row['encrypted_content'])) {
				try {
					// Use the tenant_id_from that encrypted the message, not the current user's tenant
					$messageDecryptTenant = isset($row['tenant_id_from']) && $row['tenant_id_from'] 
						? (int)$row['tenant_id_from'] 
						: $tenantId;
					$row['content'] = $encryptor->decrypt($row['encrypted_content'], $messageDecryptTenant, (int)$row['encryption_key_id']);
				} catch (Exception $e) {
					error_log('Message decryption failed for message ID ' . $row['id'] . ': ' . $e->getMessage());
					$row['content'] = '[Encrypted message - decryption failed]';
				}
			} else if (empty($row['content']) && !empty($row['encrypted_content'])) {
				// Fallback: if content is empty but encrypted_content exists, try to decrypt anyway
				try {
					$messageDecryptTenant = isset($row['tenant_id_from']) && $row['tenant_id_from'] 
						? (int)$row['tenant_id_from'] 
						: $tenantId;
					$row['content'] = $encryptor->decrypt($row['encrypted_content'], $messageDecryptTenant, (int)$row['encryption_key_id']);
				} catch (Exception $e) {
					error_log('Fallback decryption failed for message ID ' . $row['id'] . ': ' . $e->getMessage());
					$row['content'] = '[Unable to decrypt message]';
				}
			}
			// If content is empty and no encrypted_content, it's a corrupted message
			if (empty($row['content'])) {
				$row['content'] = '[Message content missing or corrupted]';
			}
			
			// Extract voice message metadata if voice message
			if ($row['message_type'] === 'voice' && !empty($row['content'])) {
				try {
					$contentData = json_decode($row['content'], true);
					if (is_array($contentData) && isset($contentData['url'])) {
						$row['url'] = $contentData['url'];
					}
				} catch (Exception $e) {
					// Silently ignore JSON parsing errors for voice content
				}
			}
			
			// Remove sensitive fields from response
			unset($row['encrypted_content']);
			unset($row['encryption_key_id']);
			unset($row['is_encrypted']);
			unset($row['tenant_id_from']);
		}
		
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
			
			// Log the mark_seen action for each message read
			if ($upd && $upd->rowCount() > 0) {
				$messagesStmt = secure_query($pdo, 'SELECT id FROM chat_messages WHERE room_id = ? AND to_user_id = ? AND seen_at IS NOT NULL ORDER BY id DESC LIMIT ?', [$room, $currentUserId, $upd->rowCount()]);
				if ($messagesStmt) {
					$messages = $messagesStmt->fetchAll();
					foreach ($messages as $msg) {
						ChatAudit::logRead($tenantId, $myBranch, $currentUserId, $msg['id']);
					}
				}
			}
			
			echo json_encode(['ok' => true, 'updated' => $upd ? $upd->rowCount() : 0]);
			exit;
		}

		$toUserId = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
		$content = isset($_POST['content']) ? trim($_POST['content']) : '';
		if ($toUserId <= 0 || $content === '') { http_response_code(400); echo json_encode(['error' => 'invalid_input']); exit; }
		
		// Rate limit check: messages per hour
		if (!RateLimiter::isAllowed($currentUserId, 'messages_per_hour', $tenantId)) {
			$quota = RateLimiter::getRemainingQuota($currentUserId, 'messages_per_hour', $tenantId);
			ChatAudit::logFailedAccess($tenantId, 0, $currentUserId, $toUserId, 'send_message', 'rate_limit_exceeded', 'Rate limit exceeded');
			http_response_code(429);
			echo json_encode([
				'error' => 'rate_limited',
				'message' => 'Too many messages. Please try again later.',
				'retry_after' => $quota['reset_in']
			]);
			exit;
		}
		
		// Rate limit check: messages per day
		if (!RateLimiter::isAllowed($currentUserId, 'messages_per_day', $tenantId)) {
			$quota = RateLimiter::getRemainingQuota($currentUserId, 'messages_per_day', $tenantId);
			ChatAudit::logFailedAccess($tenantId, 0, $currentUserId, $toUserId, 'send_message', 'daily_limit_exceeded', 'Daily message limit exceeded');
			http_response_code(429);
			echo json_encode([
				'error' => 'daily_limit_exceeded',
				'message' => 'Daily message limit exceeded. Try again tomorrow.',
				'retry_after' => $quota['reset_in']
			]);
			exit;
		}

		// Validate recipient exists and get their details
		$recipientStmt = secure_query($pdo, 'SELECT id, tenant_id, branch_id, role, deleted_at, fired FROM users WHERE id = ?', [$toUserId]);
		$recipient = $recipientStmt ? $recipientStmt->fetch(PDO::FETCH_ASSOC) : null;
		if (!$recipient || $recipient['deleted_at'] !== null || $recipient['fired']) {
			http_response_code(404);
			echo json_encode(['error' => 'recipient_not_found']);
			exit;
		}
		
		$recipientTenant = (int)$recipient['tenant_id'];
		$recipientBranch = (int)$recipient['branch_id'];
		$recipientRole = $recipient['role'];
		
		// Get current user's branch
		$meStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
		$meUser = $meStmt ? $meStmt->fetch(PDO::FETCH_ASSOC) : null;
		$myBranch = $meUser ? (int)$meUser['branch_id'] : 0;
		
		// Validate branch compatibility (same tenant = same branch UNLESS either user is tenant_super_admin)
		if ($recipientTenant === $tenantId && $recipientBranch !== $myBranch && $userRole !== 'tenant_super_admin' && $recipientRole !== 'tenant_super_admin') {
			ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $toUserId, 'send_message', 'cross_branch_denied', 'Cross-branch chat not allowed');
			http_response_code(403);
			echo json_encode(['error' => 'cross_branch_chat_not_allowed']);
			exit;
		}

		// Check block relations (either side)
		$blockedA = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $currentUserId, $toUserId]);
		$blockedB = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $toUserId, $currentUserId]);
		if (($blockedA && $blockedA->fetch()) || ($blockedB && $blockedB->fetch())) { 
			ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $toUserId, 'send_message', 'user_blocked', 'User blocked');
			http_response_code(403); echo json_encode(['error' => 'blocked']); exit; 
		}

		// Check tenant and branch peering if cross-tenant
		if ($recipientTenant !== $tenantId) {
			// First check tenant-level peering (optional - can work with just branch peering)
			$tenantPeeringAllow = secure_query($pdo, 'SELECT 1 FROM tenant_peering WHERE status = "approved" AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) LIMIT 1', [$tenantId, $recipientTenant, $recipientTenant, $tenantId]);
			$tenantPeeringExists = $tenantPeeringAllow && $tenantPeeringAllow->fetch();
			
			// Then check branch-level peering (both directions with full tenant+branch validation)
			$branchPeeringAllow = secure_query($pdo, 
				'SELECT 1 FROM branch_peering WHERE status = "approved" AND (
					(tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
					OR
					(tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
				) LIMIT 1', 
				[$tenantId, $myBranch, $recipientTenant, $recipientBranch, $recipientTenant, $recipientBranch, $tenantId, $myBranch]);
			$branchPeeringExists = $branchPeeringAllow && $branchPeeringAllow->fetch();
			
			// Allow if EITHER tenant peering OR branch peering is approved
			if (!$tenantPeeringExists && !$branchPeeringExists) { 
				ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $toUserId, 'send_message', 'peer_not_allowed', 'Peer not allowed');
				http_response_code(403); 
				echo json_encode(['error' => 'peer_not_allowed']); 
				exit; 
			}
		}

		// Validate content against branch chat settings
		$settingsStmt = secure_query($pdo, 'SELECT chat_max_file_bytes FROM branch_chat_settings WHERE tenant_id = ? AND branch_id = ?', [$tenantId, $myBranch]);
		$settings = $settingsStmt ? $settingsStmt->fetch(PDO::FETCH_ASSOC) : null;
		$maxBytes = $settings ? (int)$settings['chat_max_file_bytes'] : 26214400;
		
		if (strlen($content) > $maxBytes) {
			http_response_code(400);
			echo json_encode(['error' => 'message_too_large', 'max_bytes' => $maxBytes]);
			exit;
		}

		$room = room_from_users($currentUserId, $toUserId);
		
		// Try to encrypt message content (if encryption columns exist)
		$encryptionData = null;
		try {
			// First, check if encryption columns exist
			$checkStmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
										WHERE TABLE_SCHEMA = DATABASE() 
										AND TABLE_NAME = 'chat_messages' 
										AND COLUMN_NAME = 'encrypted_content' LIMIT 1");
			$encryptionColumnsExist = $checkStmt && $checkStmt->rowCount() > 0;
			
			if ($encryptionColumnsExist) {
				// Try to encrypt
				try {
					$encryptionData = $encryptor->encrypt($content, $tenantId);
					// IMPORTANT: Keep plaintext content in 'content' column for fallback/readability
					// encrypted_content stores the encrypted version
					$stmt = secure_query($pdo, 
						'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content, encrypted_content, encryption_key_id, is_encrypted) 
						 VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 
						[$room, $currentUserId, $toUserId, $tenantId, $content, $encryptionData['encrypted_content'], $encryptionData['key_id'], 1]);
				} catch (Exception $e) {
					error_log('Message encryption failed: ' . $e->getMessage());
					// Fallback: store in plaintext if encryption fails
					$stmt = secure_query($pdo, 
						'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content, is_encrypted) 
						 VALUES (?, ?, ?, ?, ?, 0)', 
						[$room, $currentUserId, $toUserId, $tenantId, $content]);
				}
			} else {
				// Encryption columns don't exist - store as plaintext with tenant_id_from
				error_log('Encryption columns missing - storing message as plaintext');
				$stmt = secure_query($pdo, 
					'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content) 
					 VALUES (?, ?, ?, ?, ?)', 
					[$room, $currentUserId, $toUserId, $tenantId, $content]);
			}
		} catch (Exception $e) {
			error_log('Message save error: ' . $e->getMessage());
			// Final fallback - try basic insert
			try {
				$stmt = secure_query($pdo, 
					'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, content) 
					 VALUES (?, ?, ?, ?)', 
					[$room, $currentUserId, $toUserId, $content]);
			} catch (Exception $e2) {
				error_log('All message save attempts failed: ' . $e2->getMessage());
				$stmt = null;
			}
		}
		
		if (!$stmt) { 
			error_log('Message insert failed - SQL statement returned false');
			http_response_code(500); 
			echo json_encode(['error' => 'save_failed', 'details' => 'Database insert failed']); 
			exit; 
		}
		
		$id = $pdo->lastInsertId();
		if (!$id) {
			error_log('Message insert - no ID returned from database');
			http_response_code(500);
			echo json_encode(['error' => 'save_failed', 'details' => 'No ID returned']);
			exit;
		}
		
		// Log the message send
		ChatAudit::logSend($tenantId, $myBranch, $currentUserId, $toUserId, $id, strlen($content), !empty($encryptionData), $encryptionData['key_id'] ?? null);
		
		// Record the action for rate limiting
		RateLimiter::recordAction($currentUserId, 'messages_per_hour', $tenantId, $_SERVER['REMOTE_ADDR']);
		RateLimiter::recordAction($currentUserId, 'messages_per_day', $tenantId, $_SERVER['REMOTE_ADDR']);
		
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

