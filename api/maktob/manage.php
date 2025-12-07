<?php
// API endpoint for managing maktobs
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Initialize language
$lang = init_language();

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => __('unauthorized_access')
    ]);
    exit();
}

// Include database connection
include '../../includes/db.php';
include '../../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => __('server_error') . ': ' . $e->getMessage()
    ]);
}

/**
 * Handle GET requests - fetch recent maktobs
 */
function handleGetRequest() {
    global $conn, $tenant_id, $branch_id;

    $recent_maktobs_query = "SELECT m.*,
        u.name as sender_name,
        m.status,
        COALESCE(m.language, 'english') as language
        FROM maktobs m
        JOIN users u ON m.sender_id = u.id
        WHERE m.tenant_id = ? AND m.branch_id = ?
        ORDER BY maktob_date DESC
        LIMIT 10";

    $stmt = $conn->prepare($recent_maktobs_query);
    $stmt->bind_param("ii", $tenant_id, $branch_id);
    $stmt->execute();
    $recent_maktobs_result = $stmt->get_result();

    $maktobs = [];
    while ($row = mysqli_fetch_assoc($recent_maktobs_result)) {
        $maktobs[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $maktobs
    ]);
}

/**
 * Handle POST requests - create new maktob
 */
function handlePostRequest() {
    global $conn, $tenant_id, $branch_id;

    // Get and sanitize input data
    $subject = mysqli_real_escape_string($conn, $_POST['subject'] ?? '');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? '');
    $maktob_number = mysqli_real_escape_string($conn, $_POST['maktob_number'] ?? '');
    $maktob_date = mysqli_real_escape_string($conn, $_POST['maktob_date'] ?? '');
    $language = mysqli_real_escape_string($conn, $_POST['language'] ?? 'english');
    $sender_id = $_SESSION['user_id'];

    // Validate required fields
    if (empty($company_name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => __('please_enter_company')
        ]);
        return;
    }

    if (empty($subject) || empty($content) || empty($maktob_number) || empty($maktob_date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => __('all_fields_required')
        ]);
        return;
    }

    // Insert new maktob
    $query = "INSERT INTO maktobs (tenant_id, branch_id, subject, content, company_name, maktob_number, maktob_date, sender_id, status, language)
              VALUES ('$tenant_id', '$branch_id', '$subject', '$content', '$company_name', '$maktob_number', '$maktob_date', $sender_id, 'draft', '$language')";

    if (mysqli_query($conn, $query)) {
        $insert_id = mysqli_insert_id($conn);

        echo json_encode([
            'success' => true,
            'message' => __('letter_created'),
            'maktob_id' => $insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => __('error_creating_letter') . ': ' . mysqli_error($conn)
        ]);
    }
}
?>