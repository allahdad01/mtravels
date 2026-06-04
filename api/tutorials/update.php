<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

$allowed_manage = ['super_admin'];
enforce_auth($allowed_manage);

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

require_once '../../includes/db.php';

$user_id = $_SESSION['user_id'];

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
$category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');
$page = htmlspecialchars(trim($_POST['page'] ?? ''), ENT_QUOTES, 'UTF-8');
$video_type = ($_POST['video_type'] ?? 'vimeo') === 'youtube' ? 'youtube' : 'vimeo';
$video_id = htmlspecialchars(trim($_POST['video_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$duration = htmlspecialchars(trim($_POST['duration'] ?? '5:00'), ENT_QUOTES, 'UTF-8');
$level = in_array($_POST['level'] ?? 'Beginner', ['Beginner', 'Intermediate', 'Advanced']) ? $_POST['level'] : 'Beginner';
$chapters_raw = $_POST['chapters'] ?? '[]';
$chapters = is_string($chapters_raw) ? $chapters_raw : json_encode($chapters_raw);
if (!json_decode($chapters)) { $chapters = '[]'; }
$sort_order = intval($_POST['sort_order'] ?? 0);
$status = isset($_POST['status']) ? 1 : 0;

$roles_raw = $_POST['roles'] ?? [];
if (!is_array($roles_raw)) {
    $roles_raw = ['all'];
}
$roles = json_encode($roles_raw);

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tutorials SET title = ?, description = ?, category = ?, page = ?, video_type = ?, video_id = ?, duration = ?, level = ?, roles = ?, sort_order = ?, status = ?, chapters = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $description, $category, $page, $video_type, $video_id, $duration, $level, $roles, $sort_order, $status, $chapters, $id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
