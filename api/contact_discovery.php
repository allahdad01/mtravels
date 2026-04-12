<?php
/**
 * Contact Discovery API with Rate Limiting
 * 
 * Handles user search and contact discovery with rate limiting
 * to prevent contact enumeration attacks.
 * 
 * Rate Limits:
 * - 20 searches per hour per user
 * - 10 failed searches per hour per user
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/ChatAudit.php';

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
if (!$me) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}
$tenantId = (int)$me['tenant_id'];

// Check rate limit for contact discovery
if (!RateLimiter::isAllowed($currentUserId, 'contact_discovery_per_hour', $tenantId)) {
    $quota = RateLimiter::getRemainingQuota($currentUserId, 'contact_discovery_per_hour', $tenantId);
    ChatAudit::logFailedAccess($tenantId, 0, $currentUserId, 0, 'contact_search', 'rate_limit_exceeded', 'Rate limit exceeded for contact discovery');
    http_response_code(429);
    echo json_encode([
        'error' => 'rate_limited',
        'message' => 'Too many searches. Please try again later.',
        'retry_after' => $quota['reset_in'],
        'reset_at' => $quota['reset_at']
    ]);
    exit;
}

if ($method === 'GET') {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query) || strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_query', 'message' => 'Search query must be at least 2 characters']);
        exit;
    }
    
    // Record the action for rate limiting
    RateLimiter::recordAction($currentUserId, 'contact_discovery_per_hour', $tenantId, $_SERVER['REMOTE_ADDR']);
    
    try {
        $searchQuery = '%' . $query . '%';
        $params = [$tenantId, $currentUserId, $searchQuery];
        
        // Search for users by name or email
        $sql = 'SELECT id, name, email, role, tenant_id, branch_id, profile_pic 
                FROM users 
                WHERE tenant_id = ? 
                AND id <> ? 
                AND deleted_at IS NULL 
                AND fired <> 1
                AND (name LIKE ? OR email LIKE ?)
                ORDER BY name ASC 
                LIMIT 20';
        
        $stmt = secure_query($pdo, $sql, array_merge($params, [$searchQuery]));
        $results = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Log successful search
        ChatAudit::logAction($tenantId, 0, $currentUserId, 'contact_search', 0, [
            'query' => $query,
            'results_count' => count($results),
            'success' => true
        ]);
        
        // Format results
        $contacts = array_map(function($r) {
            $photo = !empty($r['profile_pic']) ? ('assets/images/user/' . $r['profile_pic']) : null;
            return [
                'id' => (int)$r['id'],
                'name' => $r['name'] ?: 'Unknown User',
                'email' => $r['email'],
                'role' => $r['role'],
                'tenant_id' => (int)$r['tenant_id'],
                'branch_id' => (int)$r['branch_id'],
                'photo' => $photo
            ];
        }, $results);
        
        echo json_encode([
            'success' => true,
            'query' => $query,
            'count' => count($contacts),
            'results' => $contacts
        ]);
        
    } catch (Exception $e) {
        ChatAudit::logAction($tenantId, 0, $currentUserId, 'contact_search', 0, [
            'query' => $query,
            'error' => $e->getMessage()
        ]);
        
        http_response_code(500);
        echo json_encode(['error' => 'search_failed']);
    }
    
} else if ($method === 'POST') {
    // Bulk contact discovery with rate limiting
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_ids']) || !is_array($input['user_ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request']);
        exit;
    }
    
    $userIds = array_map('intval', $input['user_ids']);
    
    // Check rate limit for bulk search
    if (!RateLimiter::isAllowed($currentUserId, 'contact_discovery_per_hour', $tenantId)) {
        $quota = RateLimiter::getRemainingQuota($currentUserId, 'contact_discovery_per_hour', $tenantId);
        http_response_code(429);
        echo json_encode([
            'error' => 'rate_limited',
            'retry_after' => $quota['reset_in']
        ]);
        exit;
    }
    
    // Record bulk action as single search
    RateLimiter::recordAction($currentUserId, 'contact_discovery_per_hour', $tenantId, $_SERVER['REMOTE_ADDR']);
    
    try {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = [$tenantId, $currentUserId];
        $params = array_merge($params, $userIds);
        
        $sql = "SELECT id, name, email, role, tenant_id, branch_id, profile_pic 
                FROM users 
                WHERE tenant_id = ? 
                AND id <> ? 
                AND id IN ($placeholders)
                AND deleted_at IS NULL 
                AND fired <> 1
                ORDER BY name ASC";
        
        $stmt = secure_query($pdo, $sql, $params);
        $results = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Format results
        $contacts = array_map(function($r) {
            $photo = !empty($r['profile_pic']) ? ('assets/images/user/' . $r['profile_pic']) : null;
            return [
                'id' => (int)$r['id'],
                'name' => $r['name'] ?: 'Unknown User',
                'email' => $r['email'],
                'role' => $r['role'],
                'tenant_id' => (int)$r['tenant_id'],
                'branch_id' => (int)$r['branch_id'],
                'photo' => $photo
            ];
        }, $results);
        
        echo json_encode([
            'success' => true,
            'count' => count($contacts),
            'results' => $contacts
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'search_failed']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
}

exit;
?>
