<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

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
    error_log("Unauthorized access attempt to manage_blog_posts.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pagination and filters
$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

// Count total
$count_query = "SELECT COUNT(*) as total FROM `blog_posts` WHERE 1=1";
$filter_params = [];
if ($status) {
    $count_query .= " AND `status` = ?";
    $filter_params[] = $status;
}
if ($category) {
    $count_query .= " AND `category` LIKE ?";
    $filter_params[] = '%' . $category . '%';
}
if (!empty($search_query)) {
    $count_query .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated posts
$query = "SELECT `id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at` FROM `blog_posts` WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND `status` = ?";
    $params[] = $status;
}
if ($category) {
    $query .= " AND `category` LIKE ?";
    $params[] = '%' . $category . '%';
}
if (!empty($search_query)) {
    $query .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $search_term = "%{$search_query}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$query .= " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$blog_posts = $stmt->fetchAll();
// Fetch distinct categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT `category` FROM `blog_posts` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category`");
$stmt->execute();
$categories = $stmt->fetchAll();
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
                                    <h5 class="m-b-10">Manage Blog Posts</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Blog Posts</a></li>
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
                                        <h5>Blog Posts List</h5>
                                        <button class="btn btn-primary float-right" data-toggle="modal" data-target="#createBlogPostModal">
                                            <i class="feather icon-plus mr-1"></i>Create Blog Post
                                        </button>
                                    </div>
                                    <div class="card-body">
                                         <form method="GET" action="manage_blog_posts.php">
                                             <div class="row">
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="search">Search</label>
                                                         <input type="text" class="form-control" id="search" name="search" placeholder="Title, content..." value="<?= htmlspecialchars($search_query) ?>">
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="status">Status</label>
                                                         <select class="form-control" id="status" name="status">
                                                             <option value="">All Status</option>
                                                             <option value="draft" <?= $status == 'draft' ? 'selected' : '' ?>>Draft</option>
                                                             <option value="published" <?= $status == 'published' ? 'selected' : '' ?>>Published</option>
                                                         </select>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label for="category">Category</label>
                                                         <select class="form-control" id="category" name="category">
                                                             <option value="">All Categories</option>
                                                             <?php foreach ($categories as $cat): ?>
                                                             <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category == $cat['category'] ? 'selected' : '' ?>>
                                                                 <?= htmlspecialchars($cat['category']) ?>
                                                             </option>
                                                             <?php endforeach; ?>
                                                         </select>
                                                     </div>
                                                 </div>
                                                 <div class="col-md-3">
                                                     <div class="form-group">
                                                         <label>&nbsp;</label>
                                                         <button type="submit" class="btn btn-primary btn-block">Filter</button>
                                                     </div>
                                                 </div>
                                             </div>
                                         </form>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Title</th>
                                                        <th>Author</th>
                                                        <th>Category</th>
                                                        <th>Status</th>
                                                        <th>Created At</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($blog_posts as $post): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($post['featured_image'])): ?>
                                                                <img src="..<?= htmlspecialchars($post['featured_image']) ?>" alt="Featured" class="rounded mr-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                                <?php endif; ?>
                                                                <div>
                                                                    <strong><?= htmlspecialchars($post['title']) ?></strong>
                                                                    <br><small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($post['author'] ?? 'Unknown') ?></td>
                                                        <td><?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= $post['status'] == 'published' ? 'success' : 'warning' ?>">
                                                                <?= ucfirst($post['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                                                        <td>
                                                            <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                                <i class="feather icon-edit"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger delete-blog-post" data-id="<?= $post['id'] ?>" data-title="<?= htmlspecialchars($post['title']) ?>" title="Delete">
                                                                <i class="feather icon-trash-2"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($blog_posts)): ?>
                                                    <tr><td colspan="6" class="text-center">No blog posts found</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                </table>
                                                </div>
                                                
                                                <!-- Pagination -->
                                                <?php if ($total_pages > 1): ?>
                                                <nav aria-label="Page navigation" class="mt-3">
                                                <ul class="pagination justify-content-center">
                                                <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>">Previous</a>
                                                </li>
                                                <?php 
                                                $start_page = max(1, $current_page - 2);
                                                $end_page = min($total_pages, $current_page + 2);
                                                if ($start_page > 1): ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=1<?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"><?= $i ?></a>
                                                </li>
                                                <?php endfor; ?>
                                                <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>"><?= $total_pages ?></a>
                                                </li>
                                                <?php endif; ?>
                                                <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>">Next</a>
                                                </li>
                                                </ul>
                                                </nav>
                                                <div class="text-center text-muted small mt-2">
                                                Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($blog_posts) ?> of <?= $total_items ?> posts
                                                </div>
                                                <?php endif; ?>

                                                <!-- Success/Error Messages -->
                                        <?php if (isset($_GET['success'])): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <?php
                                            $success_messages = [
                                                'created' => 'Blog post created successfully!',
                                                'updated' => 'Blog post updated successfully!',
                                                'deleted' => 'Blog post deleted successfully!'
                                            ];
                                            echo $success_messages[$_GET['success']] ?? 'Operation completed successfully!';
                                            ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($_GET['error'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
                                                'create_failed' => 'Failed to create blog post.',
                                                'update_failed' => 'Failed to update blog post.',
                                                'delete_failed' => 'Failed to delete blog post.',
                                                'invalid_id' => 'Invalid blog post ID.',
                                                'post_not_found' => 'Blog post not found.'
                                            ];
                                            echo $error_messages[$_GET['error']] ?? 'An error occurred.';
                                            ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Create Blog Post Modal -->
                        <div class="modal fade" id="createBlogPostModal" tabindex="-1" role="dialog" aria-labelledby="createBlogPostModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="createBlogPostModalLabel">Create New Blog Post</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="createBlogPostForm" method="POST" action="create_blog_post.php" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                            <div class="form-group">
                                                <label for="title">Title *</label>
                                                <input type="text" class="form-control" id="title" name="title" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="slug">Slug *</label>
                                                <input type="text" class="form-control" id="slug" name="slug" required>
                                                <small class="form-text text-muted">URL-friendly version of the title</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="excerpt">Excerpt</label>
                                                <textarea class="form-control" id="excerpt" name="excerpt" rows="3"></textarea>
                                                <small class="form-text text-muted">Brief summary of the post</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="content">Content *</label>
                                                <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label for="featured_image">Featured Image</label>
                                                <input type="file" class="form-control-file" id="featured_image" name="featured_image" accept="image/*">
                                                <small class="form-text text-muted">Upload an image for the blog post</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="author">Author</label>
                                                <input type="text" class="form-control" id="author" name="author" value="MTravels Team">
                                            </div>

                                            <div class="form-group">
                                                <label for="category">Category</label>
                                                <input type="text" class="form-control" id="category" name="category">
                                            </div>

                                            <div class="form-group">
                                                <label for="status">Status</label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="draft">Draft</option>
                                                    <option value="published">Published</option>
                                                </select>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" form="createBlogPostForm" class="btn btn-primary">Create Post</button>
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

// Delete blog post confirmation
document.querySelectorAll('.delete-blog-post').forEach(button => {
    button.addEventListener('click', function() {
        const postId = this.getAttribute('data-id');
        const postTitle = this.getAttribute('data-title');

        if (confirm(`Are you sure you want to delete the blog post "${postTitle}"? This action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_blog_post.php';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="post_id" value="${postId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>
</body>
</html>
