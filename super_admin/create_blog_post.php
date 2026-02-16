<?php
session_start();
require_once '../includes/db.php';
require_once 'includes/file_upload_security.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to create_blog_post.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_blog_posts.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("CSRF token mismatch in create_blog_post.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: manage_blog_posts.php?error=csrf');
    exit();
}

// Validate required fields
$required_fields = ['title', 'slug', 'content', 'status'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        header('Location: manage_blog_posts.php?error=missing_' . $field);
        exit();
    }
}

// Sanitize and validate input
$title = trim($_POST['title']);
$slug = trim($_POST['slug']);
$content = trim($_POST['content']);
$excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
$author = isset($_POST['author']) ? trim($_POST['author']) : 'MTravels Team';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$status = $_POST['status'];

// Validate status
if (!in_array($status, ['draft', 'published'])) {
    header('Location: manage_blog_posts.php?error=invalid_status');
    exit();
}

// Check if slug already exists
$stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    header('Location: manage_blog_posts.php?error=slug_exists');
    exit();
}
// Handle file upload with MIME validation
$featured_image = '';
if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/blog/';
    
    // Validate file using FileUploadSecurity
    $validation = FileUploadSecurity::validateUpload($_FILES['featured_image'], 'image', 5242880);
    
    if (!$validation['success']) {
        header('Location: manage_blog_posts.php?error=' . urlencode($validation['error']));
        exit();
    }
    
    // Create upload directory if needed
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move file using secure method
    $moveResult = FileUploadSecurity::moveUploadedFile(
        $_FILES['featured_image']['tmp_name'],
        $upload_dir,
        $validation['safe_name']
    );
    
    if ($moveResult['success']) {
        $featured_image = '/uploads/blog/' . $validation['safe_name'];
    } else {
        header('Location: manage_blog_posts.php?error=' . urlencode($moveResult['error']));
        exit();
    }
}

// Insert blog post
$stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author, category, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author, $category, $status]);

if ($stmt->execute()) {
    $post_id = $pdo->lastInsertId();
    // Log the action
    error_log("Blog post created: ID=$post_id, Title=$title, Author=" . $_SESSION['user_id'] . ", IP=" . $_SERVER['REMOTE_ADDR']);

    header('Location: manage_blog_posts.php?success=created');
    exit();
} else {
    error_log("Failed to create blog post: " . $stmt->error);
    header('Location: manage_blog_posts.php?error=create_failed');
    exit();
}
?>
