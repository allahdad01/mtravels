<?php
session_start();
require_once '../includes/db.php';

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
    header('Location: manage_blog_posts.php?error=csrf');
    exit();
}

// Get post ID
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if (!$post_id) {
    header('Location: manage_blog_posts.php?error=invalid_id');
    exit();
}

// Fetch blog post to get image path for deletion
$stmt = $pdo->prepare("SELECT title, featured_image FROM blog_posts WHERE id = ?");
$stmt->execute([$post_id]);
$result = $stmt->get_result();
$blog_post = $result->fetch();
if (!$blog_post) {
    header('Location: manage_blog_posts.php?error=post_not_found');
    exit();
}

// Delete the blog post
$stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
$stmt->execute([$post_id]);

if ($stmt->execute()) {
    // Delete associated image file if exists
    if (!empty($blog_post['featured_image'])) {
        $image_path = '../' . ltrim($blog_post['featured_image'], '/');
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

   
    header('Location: manage_blog_posts.php?success=deleted');
    exit();
} else {
    header('Location: manage_blog_posts.php?error=delete_failed');
    exit();
}
?>
