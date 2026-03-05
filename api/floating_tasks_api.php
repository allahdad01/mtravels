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

// CSRF Protection for POST requests
if (($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['action'])) && !verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Security validation failed. Please try again.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

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
    error_log("Floating Tasks API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit();
}

function getTasks() {
    global $pdo, $user_id, $tenant_id, $branch_id;

    try {
        // Check if table exists first
        $checkStmt = $pdo->query("SHOW TABLES LIKE 'floating_tasks'");
        if ($checkStmt->rowCount() === 0) {
            http_response_code(503);
            echo json_encode(['error' => 'Database table not initialized. Please run migrations.']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT id, task_text, completed, created_at
            FROM floating_tasks
            WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id, $tenant_id, $branch_id]);
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
        error_log("Get tasks error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch tasks']);
    }
}

function addTask() {
    global $pdo, $user_id, $tenant_id, $branch_id;

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
        $stmt = $pdo->prepare("
            INSERT INTO floating_tasks (user_id, tenant_id, branch_id, task_text, completed, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$user_id, $tenant_id, $branch_id, $text]);
        
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
        error_log("Add task error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add task']);
    }
}

function updateTask() {
    global $pdo, $user_id, $tenant_id, $branch_id;

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
        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT id FROM floating_tasks
            WHERE id = ? AND user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_id, $tenant_id, $branch_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Task not found or unauthorized']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE floating_tasks
            SET completed = ?
            WHERE id = ? AND user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$completed ? 1 : 0, $id, $user_id, $tenant_id, $branch_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Update task error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update task']);
    }
}

function deleteTask() {
    global $pdo, $user_id, $tenant_id, $branch_id;

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
        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT id FROM floating_tasks
            WHERE id = ? AND user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_id, $tenant_id, $branch_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Task not found or unauthorized']);
            exit();
        }

        $stmt = $pdo->prepare("
            DELETE FROM floating_tasks
            WHERE id = ? AND user_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$id, $user_id, $tenant_id, $branch_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Delete task error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task']);
    }
}

function clearCompleted() {
    global $pdo, $user_id, $tenant_id, $branch_id;

    try {
        $stmt = $pdo->prepare("
            DELETE FROM floating_tasks
            WHERE user_id = ? AND tenant_id = ? AND branch_id = ? AND completed = 1
        ");
        $stmt->execute([$user_id, $tenant_id, $branch_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log("Clear completed error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to clear completed tasks']);
    }
}
?>
