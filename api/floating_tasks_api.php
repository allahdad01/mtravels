<?php
/**
 * Floating Tasks API
 * Handle CRUD operations for floating tasks with database persistence
 */

header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../includes/db.php';
require_once '../admin/security.php';

// CSRF Protection for POST requests (skip for GET requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Security validation failed. Please try again.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;
$session_role = $_SESSION['role'] ?? 'user';
$user_type = $session_role === 'client' ? 'client' : 'user';

// Sales agents may not have tenant_id (they work across tenants)
// Allow null values for sales agents
if (is_null($tenant_id) || is_null($branch_id)) {
    // For sales agents, use a special marker or allow NULL in database
    if (is_null($tenant_id)) $tenant_id = 0;  // 0 = sales agent (global)
    if (is_null($branch_id)) $branch_id = 0;   // 0 = all branches
}

// Get action from GET, POST form, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// If no action found, try to parse JSON body
if (!$action) {
    $json = json_decode(file_get_contents('php://input'), true);
    $action = $json['action'] ?? null;
}

function floatingTasksTableExists() {
    global $pdo;

    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $checkStmt = $pdo->query("SHOW TABLES LIKE 'floating_tasks'");
    $exists = $checkStmt && $checkStmt->rowCount() > 0;

    return $exists;
}

function floatingTasksHasColumn($columnName) {
    global $pdo;

    static $columnCache = [];

    if (array_key_exists($columnName, $columnCache)) {
        return $columnCache[$columnName];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'floating_tasks'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$columnName]);
    $columnCache[$columnName] = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

    return $columnCache[$columnName];
}

function requireFloatingTasksTable() {
    if (floatingTasksTableExists()) {
        return;
    }

    http_response_code(503);
    echo json_encode(['error' => 'Database table not initialized. Please run migrations.']);
    exit();
}

function buildFloatingTasksScope(&$params) {
    global $user_id, $tenant_id, $branch_id, $user_type;

    $conditions = ['user_id = ?'];
    $params[] = $user_id;

    if (floatingTasksHasColumn('user_type')) {
        $conditions[] = 'user_type = ?';
        $params[] = $user_type;
    }

    $conditions[] = 'tenant_id = ?';
    $params[] = $tenant_id;
    $conditions[] = 'branch_id = ?';
    $params[] = $branch_id;

    return implode(' AND ', $conditions);
}

function respondWithPdoError($message, PDOException $e, array $context = []) {
    http_response_code(500);

    $payload = [
        'error' => $message,
        'details' => $e->getMessage(),
        'sql_state' => $e->getCode(),
    ];

    if (!empty($context)) {
        $payload['context'] = $context;
    }

    error_log('Floating tasks API error: ' . json_encode($payload));
    echo json_encode($payload);
    exit();
}

try {
    switch ($action) {
        case 'get':
            getTasks();
            break;
        case 'add':
            addTask();
            break;
        case 'update':
            updateTask();
            break;
        case 'delete':
            deleteTask();
            break;
        case 'clear_completed':
            clearCompleted();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit();
}

function getTasks() {
    global $pdo, $user_id, $tenant_id, $branch_id, $user_type;

    try {
        requireFloatingTasksTable();

        $params = [];
        $scope = buildFloatingTasksScope($params);
        
        $stmt = $pdo->prepare("
            SELECT id, task_text, completed, created_at
            FROM floating_tasks
            WHERE $scope
            ORDER BY created_at DESC
        ");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format response
        $formattedTasks = array_map(function($task) {
            return [
                'id' => (int)$task['id'],
                'text' => $task['task_text'],
                'completed' => (bool)$task['completed'],
                'createdAt' => $task['created_at']
            ];
        }, $tasks);
        
        echo json_encode(['success' => true, 'tasks' => $formattedTasks]);
    } catch (PDOException $e) {
        respondWithPdoError('Failed to fetch tasks', $e, [
            'user_id' => $user_id,
            'user_type' => $user_type,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
        ]);
    }
}

function addTask() {
    global $pdo, $user_id, $tenant_id, $branch_id, $user_type;

    requireFloatingTasksTable();

    // Parse JSON body first, then fall back to POST
    $json = json_decode(file_get_contents('php://input'), true);
    $input = is_array($json) ? $json : $_POST;
    $text = trim($input['text'] ?? '');

    if (empty($text) || strlen($text) > 200) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid task text']);
        exit();
    }

    try {
        if (floatingTasksHasColumn('user_type')) {
            $stmt = $pdo->prepare("
                INSERT INTO floating_tasks (user_id, user_type, tenant_id, branch_id, task_text, completed, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$user_id, $user_type, $tenant_id, $branch_id, $text]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO floating_tasks (user_id, tenant_id, branch_id, task_text, completed, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$user_id, $tenant_id, $branch_id, $text]);
        }
        
        $taskId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'task' => [
                'id' => (int)$taskId,
                'text' => $text,
                'completed' => false,
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (PDOException $e) {
        respondWithPdoError('Failed to add task', $e, [
            'user_id' => $user_id,
            'user_type' => $user_type,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
            'text_length' => strlen($text),
        ]);
    }
}

function updateTask() {
    global $pdo, $user_id, $tenant_id, $branch_id, $user_type;

    requireFloatingTasksTable();

    // Parse JSON body first, then fall back to POST
    $json = json_decode(file_get_contents('php://input'), true);
    $input = is_array($json) ? $json : $_POST;
    $id = (int)($input['id'] ?? 0);
    $completed = isset($input['completed']) ? (bool)$input['completed'] : null;

    if ($id <= 0 || $completed === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        exit();
    }

    try {
        $params = [$id];
        $scope = buildFloatingTasksScope($params);

        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT id FROM floating_tasks
            WHERE id = ? AND $scope
        ");
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Task not found or unauthorized']);
            exit();
        }

        $params = [$completed ? 1 : 0, $id];
        $scope = buildFloatingTasksScope($params);

        $stmt = $pdo->prepare("
            UPDATE floating_tasks
            SET completed = ?
            WHERE id = ? AND $scope
        ");
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        respondWithPdoError('Failed to update task', $e, [
            'task_id' => $id,
            'user_id' => $user_id,
            'user_type' => $user_type,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
        ]);
    }
}

function deleteTask() {
    global $pdo, $user_id, $tenant_id, $branch_id, $user_type;

    requireFloatingTasksTable();

    // Parse JSON body first, then fall back to POST
    $json = json_decode(file_get_contents('php://input'), true);
    $input = is_array($json) ? $json : $_POST;
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid task ID']);
        exit();
    }

    try {
        $params = [$id];
        $scope = buildFloatingTasksScope($params);

        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT id FROM floating_tasks
            WHERE id = ? AND $scope
        ");
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Task not found or unauthorized']);
            exit();
        }

        $params = [$id];
        $scope = buildFloatingTasksScope($params);

        $stmt = $pdo->prepare("
            DELETE FROM floating_tasks
            WHERE id = ? AND $scope
        ");
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        respondWithPdoError('Failed to delete task', $e, [
            'task_id' => $id,
            'user_id' => $user_id,
            'user_type' => $user_type,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
        ]);
    }
}

function clearCompleted() {
    global $pdo, $user_id, $tenant_id, $branch_id, $user_type;

    requireFloatingTasksTable();

    try {
        $params = [];
        $scope = buildFloatingTasksScope($params);

        $stmt = $pdo->prepare("
            DELETE FROM floating_tasks
            WHERE $scope AND completed = 1
        ");
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        respondWithPdoError('Failed to clear completed tasks', $e, [
            'user_id' => $user_id,
            'user_type' => $user_type,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
        ]);
    }
}
?>
