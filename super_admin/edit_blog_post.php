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
    error_log("Unauthorized access attempt to edit_blog_post.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    header('Location: manage_blog_posts.php?error=invalid_id');
    exit();
}

// Fetch blog post
$stmt = $pdo->prepare("SELECT `id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at` FROM `blog_posts` WHERE `id` = ?");
$stmt->execute([$post_id]);
$result = $stmt->get_result();
$blog_post = $result->fetch();
if (!$blog_post) {
    header('Location: manage_blog_posts.php?error=post_not_found');
    exit();
}

// Handle POST request for updating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("CSRF token mismatch in edit_blog_post.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=csrf');
        exit();
    }

    // Validate required fields
    $required_fields = ['title', 'slug', 'content', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=missing_' . $field);
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
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=invalid_status');
        exit();
    }

    // Check if slug already exists (excluding current post)
    $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $post_id]);
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=slug_exists');
        exit();
    }
    // Handle file upload
    $featured_image = $blog_post['featured_image'];
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/blog/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=invalid_file_type');
            exit();
        }

        // Check file size (max 5MB)
        if ($_FILES['featured_image']['size'] > 5 * 1024 * 1024) {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=file_too_large');
            exit();
        }

        // Generate unique filename
        $filename = uniqid('blog_') . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
            // Delete old image if exists
            if (!empty($blog_post['featured_image'])) {
                $old_image_path = '../' . ltrim($blog_post['featured_image'], '/');
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $featured_image = '/uploads/blog/' . $filename;
        } else {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=upload_failed');
            exit();
        }
    }

    // Update blog post
    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, author = ?, category = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author, $category, $status, $post_id]);

    if ($stmt->execute()) {
        // Log the action
        error_log("Blog post updated: ID=$post_id, Title=$title, Author=" . $_SESSION['user_id'] . ", IP=" . $_SERVER['REMOTE_ADDR']);

        header('Location: manage_blog_posts.php?success=updated');
        exit();
    } else {
        error_log("Failed to update blog post: " . $stmt->error);
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=update_failed');
        exit();
    }
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Edit Blog Post</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_blog_posts.php">Blog Posts</a></li>
                                    <li class="breadcrumb-item"><a href="#!">Edit Post</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Edit Blog Post: <?= htmlspecialchars($blog_post['title']) ?></h5>
                                        <a href="manage_blog_posts.php" class="btn btn-outline-secondary float-right">
                                            <i class="feather icon-arrow-left mr-1"></i>Back to List
                                        </a>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($_GET['error'])): ?>
                                        <div class="alert alert-danger">
                                            <?php
                                            $error_messages = [
                                                'csrf' => 'Security token expired. Please try again.',
                                                'missing_title' => 'Title is required.',
                                                'missing_slug' => 'Slug is required.',
                                                'missing_content' => 'Content is required.',
                                                'invalid_status' => 'Invalid status selected.',
                                                'slug_exists' => 'A post with this slug already exists.',
                                                'invalid_file_type' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.',
                                                'file_too_large' => 'File size too large. Maximum size is 5MB.',
                                                'upload_failed' => 'Failed to upload image.',
                                                'update_failed' => 'Failed to update blog post.'
                                            ];
                                            echo $error_messages[$_GET['error']] ?? 'An error occurred.';
                                            ?>
                                        </div>
                                        <?php endif; ?>

                                        <form method="POST" action="edit_blog_post.php?id=<?= $post_id ?>" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                            <div class="form-group">
                                                <label for="title">Title *</label>
                                                <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($blog_post['title']) ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="slug">Slug *</label>
                                                <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($blog_post['slug']) ?>" required>
                                                <small class="form-text text-muted">URL-friendly version of the title</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="excerpt">Excerpt</label>
                                                <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?= htmlspecialchars($blog_post['excerpt']) ?></textarea>
                                                <small class="form-text text-muted">Brief summary of the post</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="content">Content *</label>
                                                <textarea class="form-control" id="content" name="content" rows="15" required><?= htmlspecialchars($blog_post['content']) ?></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="featured_image">Featured Image</label>
                                                <input type="file" class="form-control-file" id="featured_image" name="featured_image" accept="image/*">
                                                <small class="form-text text-muted">Upload a new image to replace the current one</small>
                                                <?php if (!empty($blog_post['featured_image'])): ?>
                                                <div class="mt-2">
                                                    <img src="..<?= htmlspecialchars($blog_post['featured_image']) ?>" alt="Current featured image" style="max-width: 200px; max-height: 200px; object-fit: cover;">
                                                    <p class="text-muted mt-1">Current image will be replaced if you upload a new one.</p>
                                                </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="form-group">
                                                <label for="author">Author</label>
                                                <input type="text" class="form-control" id="author" name="author" value="<?= htmlspecialchars($blog_post['author']) ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="category">Category</label>
                                                <input type="text" class="form-control" id="category" name="category" value="<?= htmlspecialchars($blog_post['category']) ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="draft" <?= $blog_post['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                                    <option value="published" <?= $blog_post['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-1"></i>Update Post
                                                </button>
                                                <a href="manage_blog_posts.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
// Auto-generate slug from title
document.getElementById('title').addEventListener('input', function() {
    const title = this.value;
    const slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim('-');
    document.getElementById('slug').value = slug;
});
</script>
</body>
</html>
