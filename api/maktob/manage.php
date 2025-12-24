<?php
// API endpoint for managing maktobs
// Start output buffering to catch any stray output from includes
ob_start();

// Set JSON header FIRST
header('Content-Type: application/json; charset=utf-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting to not display errors (we'll handle them)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

error_log("API: Starting maktob management endpoint");

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Initialize language
$lang = init_language();

// Include database connection
require_once('../../includes/db.php');

// Clear any buffered output from includes
$buffered = ob_get_clean();
if (!empty($buffered)) {
    error_log("WARNING: Buffered output from includes: " . $buffered);
}

// Start fresh output buffering for the response
ob_start();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

error_log("API: tenant_id=$tenant_id, branch_id=$branch_id");

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => __('method_not_allowed')
            ]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("PDO Error in maktob API: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database Error: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("General Error in maktob API: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => __('server_error') . ': ' . $e->getMessage()
    ]);
}

/**
 * Handle GET requests - fetch recent maktobs
 */
function handleGetRequest() {
    global $pdo, $tenant_id, $branch_id;

    $recent_maktobs_query = "SELECT m.*,
        u.name as sender_name,
        m.status,
        COALESCE(m.language, 'english') as language
        FROM maktobs m
        JOIN users u ON m.sender_id = u.id
        WHERE m.tenant_id = ? AND m.branch_id = ?
        ORDER BY maktob_date DESC
        LIMIT 10";

    $stmt = $pdo->prepare($recent_maktobs_query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $maktobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $maktobs
    ]);
}

/**
 * Handle POST requests - create new maktob
 */
function handlePostRequest() {
    global $pdo, $tenant_id, $branch_id;

    // Get input data
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';
    $company_name = $_POST['company_name'] ?? '';
    $maktob_number = $_POST['maktob_number'] ?? '';
    $maktob_date = $_POST['maktob_date'] ?? '';
    $language = $_POST['language'] ?? 'english';
    $sender_id = $_SESSION['user_id'] ?? null;

    error_log("=== MAKTOB CREATE REQUEST START ===");
    error_log("POST Data received: " . json_encode($_POST));
    error_log("Session tenant_id: $tenant_id, branch_id: $branch_id, user_id: $sender_id");

    // Validate required fields
    if (empty($company_name)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: company_name is empty");
        echo json_encode([
            'success' => false,
            'message' => __('please_enter_company'),
            'field' => 'company_name'
        ]);
        return;
    }

    if (empty($subject)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: subject is empty");
        echo json_encode([
            'success' => false,
            'message' => __('all_fields_required'),
            'field' => 'subject'
        ]);
        return;
    }

    if (empty($content)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: content is empty");
        echo json_encode([
            'success' => false,
            'message' => __('all_fields_required'),
            'field' => 'content'
        ]);
        return;
    }

    if (empty($maktob_number)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: maktob_number is empty");
        echo json_encode([
            'success' => false,
            'message' => __('all_fields_required'),
            'field' => 'maktob_number'
        ]);
        return;
    }

    if (empty($maktob_date)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: maktob_date is empty");
        echo json_encode([
            'success' => false,
            'message' => __('all_fields_required'),
            'field' => 'maktob_date'
        ]);
        return;
    }

    if (!$sender_id) {
        http_response_code(401);
        error_log("VALIDATION FAILED: sender_id not found in session");
        echo json_encode([
            'success' => false,
            'message' => 'User session not found'
        ]);
        return;
    }

    // Validate maktob_date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maktob_date)) {
        http_response_code(400);
        error_log("VALIDATION FAILED: Invalid maktob_date format: $maktob_date (expected YYYY-MM-DD)");
        echo json_encode([
            'success' => false,
            'message' => 'Invalid date format. Use YYYY-MM-DD',
            'field' => 'maktob_date'
        ]);
        return;
    }

    error_log("All validations passed. Proceeding with INSERT");

    // Insert new maktob
    $query = "INSERT INTO maktobs (tenant_id, branch_id, subject, content, company_name, maktob_number, maktob_date, sender_id, status, language)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)";

    try {
        error_log("Preparing statement: $query");
        $stmt = $pdo->prepare($query);
        
        if (!$stmt) {
            error_log("PREPARE ERROR: " . json_encode($pdo->errorInfo()));
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database prepare error',
                'error_info' => $pdo->errorInfo()
            ]);
            return;
        }

        error_log("Statement prepared successfully. Binding parameters...");
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $subject, PDO::PARAM_STR);
        $stmt->bindParam(4, $content, PDO::PARAM_STR);
        $stmt->bindParam(5, $company_name, PDO::PARAM_STR);
        $stmt->bindParam(6, $maktob_number, PDO::PARAM_STR);
        $stmt->bindParam(7, $maktob_date, PDO::PARAM_STR);
        $stmt->bindParam(8, $sender_id, PDO::PARAM_INT);
        $stmt->bindParam(9, $language, PDO::PARAM_STR);

        error_log("Parameters bound. Execution details:");
        error_log("  tenant_id: $tenant_id (int)");
        error_log("  branch_id: $branch_id (int)");
        error_log("  subject: $subject");
        error_log("  company_name: $company_name");
        error_log("  maktob_number: $maktob_number");
        error_log("  maktob_date: $maktob_date");
        error_log("  sender_id: $sender_id (int)");
        error_log("  language: $language");
        error_log("  content length: " . strlen($content) . " chars");

        error_log("Executing INSERT statement...");
        if ($stmt->execute()) {
            $insert_id = $pdo->lastInsertId();
            error_log("=== MAKTOB CREATE SUCCESS: ID=$insert_id ===");

            echo json_encode([
                'success' => true,
                'message' => __('letter_created'),
                'maktob_id' => $insert_id
            ]);
        } else {
            http_response_code(500);
            $errorInfo = $stmt->errorInfo();
            error_log("=== MAKTOB EXECUTION FAILED ===");
            error_log("SQLSTATE: " . $errorInfo[0]);
            error_log("Driver Error Code: " . $errorInfo[1]);
            error_log("Driver Error Message: " . $errorInfo[2]);
            error_log("Full Error Info: " . json_encode($errorInfo));
            
            echo json_encode([
                'success' => false,
                'message' => __('error_creating_letter'),
                'error' => $errorInfo[2] ?? 'Unknown error',
                'sqlstate' => $errorInfo[0],
                'error_code' => $errorInfo[1]
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("=== PDO EXCEPTION DURING INSERT ===");
        error_log("Exception Code: " . $e->getCode());
        error_log("Exception Message: " . $e->getMessage());
        error_log("Stack Trace: " . $e->getTraceAsString());
        
        echo json_encode([
            'success' => false,
            'message' => __('error_creating_letter'),
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
    }
    
    error_log("=== MAKTOB CREATE REQUEST END ===\n");
}

// Ensure clean output - clear any buffered content and output JSON properly
ob_end_clean();
ob_start();
// Output is already echoed above, just ensure we're not buffering anymore
?>