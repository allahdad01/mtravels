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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ─── TOKENS ─────────────────────────────────────────────── */
:root {
  --bg:       #f8fafc;
  --surface:  #ffffff;
  --surface2: #f1f5f9;
  --border:   #e5e7eb;
  --text:     #1f2937;
  --muted:    #6b7280;
  --accent:   #4099ff;
  --accent2:  #2ed8b6;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --blue:     #3b82f6;
  --purple:   #8b5cf6;
  --orange:   #f97316;
  --radius:   14px;
}

/* ─── RESET / BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; }
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ─── MAIN WRAPPER ───────────────────────────────────────── */
.sa-wrap { display: flex; flex-direction: column; min-height: 100vh; }

/* ─── CONTENT ────────────────────────────────────────────── */
.sa-content { 
    padding: 24px 28px; 
    display: flex; 
    flex-direction: column; 
    gap: 24px; 
}

/* ─── CARD ───────────────────────────────────────────────── */
.sa-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius); 
  overflow: hidden;
  transition: all .2s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  margin-bottom: 24px;
}
.sa-card:last-child { margin-bottom: 0; }
.sa-card:hover { 
    border-left-color: var(--accent2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.sa-card-hdr {
  padding: 16px 24px; 
  border-bottom: 1px solid var(--border);
  display: flex; 
  align-items: center; 
  justify-content: space-between;
  background: linear-gradient(135deg, rgba(108,99,255,0.04), rgba(46,216,182,0.02));
}
.sa-card-hdr h3 { 
    font-size: .95rem; 
    font-weight: 600; 
    color: var(--text);
    display: flex;
    align-items: center;
    letter-spacing: -0.01em;
}
.sa-card-body { 
    padding: 24px; 
}

/* Card colors */
.sa-card:nth-child(1) { border-left-color: #6366f1; }
.sa-card:nth-child(2) { border-left-color: #10b981; }

/* ─── BUTTON ─────────────────────────────────────────────── */
.sa-btn {
  font-size: .8rem; font-weight: 600; font-family: 'Sora', sans-serif;
  padding: 8px 16px; border-radius: 20px; cursor: pointer; border: none;
  display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
  transition: all .15s;
}
.sa-btn-primary {
  background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
}
.sa-btn-primary:hover { opacity: .85; transform: translateY(-1px); }
.sa-btn-ghost {
  background: var(--surface2); color: var(--muted); border: 1px solid var(--border);
}
.sa-btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.sa-btn-sm { padding: 6px 12px; font-size: .75rem; }

/* ─── BADGE ─────────────────────────────────────────────── */
.badge-num {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 20px; height: 20px; padding: 0 6px; border-radius: 20px;
  font-size: .7rem; font-weight: 700;
  background: rgba(108,99,255,.2); color: var(--accent);
  font-family: 'JetBrains Mono', monospace;
}

/* ─── FORM STYLES ────────────────────────────────────────── */
.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    align-items: end;
}

.form-group { position: relative; }

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.85rem;
    transition: all .15s ease;
    background: var(--surface2);
    color: var(--text);
    font-family: 'Sora', sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.15);
    background: var(--surface);
}

/* ─── BLOG POST CARD ────────────────────────────────────── */
.post-entry {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 3px solid var(--muted);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all .2s;
    display: flex;
    gap: 16px;
}
.post-entry:last-child { margin-bottom: 0; }
.post-entry:hover {
    border-left-color: var(--accent);
    background: rgba(108,99,255,.02);
}
.post-image {
    width: 100px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    background: var(--surface2);
}
.post-image-placeholder {
    width: 100px;
    height: 80px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--surface2), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--muted);
}
.post-content {
    flex: 1;
    min-width: 0;
}
.post-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}
.post-slug {
    font-size: 0.75rem;
    color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
    margin-bottom: 8px;
}
.post-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--muted);
}
.post-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.post-status {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.post-status.published { background: rgba(16,185,129,.15); color: var(--green); }
.post-status.draft { background: rgba(245,158,11,.15); color: var(--amber); }
.post-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    justify-content: center;
}
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .15s;
    color: var(--muted);
}
.action-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.action-btn.delete:hover {
    border-color: var(--red);
    color: var(--red);
    background: rgba(239,68,68,.05);
}

/* ─── PAGINATION ─────────────────────────────────────────── */
.pagination-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
}
.pagination {
    display: flex;
    gap: 6px;
    list-style: none;
    flex-wrap: wrap;
    justify-content: center;
}
.page-item { display: flex; }
.page-link {
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all .15s;
}
.page-link:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(108,99,255,.05);
}
.page-item.active .page-link {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border-color: var(--accent);
    color: white;
}
.page-item.disabled .page-link {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.pagination-info {
    font-size: 0.75rem;
    color: var(--muted);
}

/* ─── ALERT ─────────────────────────────────────────────── */
.alert-box {
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-box.success {
    background: rgba(16,185,129,.1);
    border: 1px solid rgba(16,185,129,.3);
    color: var(--green);
}
.alert-box.error {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    color: var(--red);
}

/* ─── EMPTY STATE ───────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--muted);
}
.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-state-text {
    font-size: 0.9rem;
}

/* ─── SCROLLBAR ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface2); border-radius: 10px; }

/* ─── PCODED LAYOUT INTEGRATION ──────────────────────────── */
body { background: var(--bg) !important; }
.pcoded-main-container, .pcoded-wrapper, .pcoded-content, .pcoded-inner-content { background: var(--bg) !important; }
.page-header { background: transparent !important; border: none !important; box-shadow: none !important; }
.page-header h5 { color: var(--text) !important; }
.breadcrumb { background: transparent !important; }
.breadcrumb-item a, .breadcrumb-item.active { color: var(--muted) !important; }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .sa-content { padding: 16px; }
    .filter-grid { grid-template-columns: 1fr; }
    .post-entry { flex-direction: column; }
    .post-image, .post-image-placeholder { width: 100%; height: 120px; }
    .post-actions { flex-direction: row; width: 100%; justify-content: flex-end; }
    .pagination { gap: 4px; }
    .page-link { padding: 6px 10px; font-size: 0.75rem; }
}
</style>

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

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Filter Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-filter" style="margin-right:8px"></i>Filters</h3>
                                <button class="sa-btn sa-btn-primary" data-toggle="modal" data-target="#createBlogPostModal">
                                    <i class="feather icon-plus"></i>Create Post
                                </button>
                            </div>
                            <div class="sa-card-body">
                                <form method="GET" action="manage_blog_posts.php">
                                    <div class="filter-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="search">Search</label>
                                            <input type="text" class="form-control" id="search" name="search" placeholder="Title, content..." value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="status">Status</label>
                                            <select class="form-control" id="status" name="status">
                                                <option value="">All Status</option>
                                                <option value="draft" <?= $status == 'draft' ? 'selected' : '' ?>>Draft</option>
                                                <option value="published" <?= $status == 'published' ? 'selected' : '' ?>>Published</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="category">Category</label>
                                            <select class="form-control" id="category" name="category">
                                                <option value="">All Categories</option>
                                                <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category == $cat['category'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['category']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="sa-btn sa-btn-primary" style="width:100%; justify-content:center;">
                                                <i class="feather icon-filter"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Success/Error Messages -->
                        <?php if (isset($_GET['success'])): ?>
                        <div class="alert-box success">
                            <i class="feather icon-check-circle"></i>
                            <?php
                            $success_messages = [
                                'created' => 'Blog post created successfully!',
                                'updated' => 'Blog post updated successfully!',
                                'deleted' => 'Blog post deleted successfully!'
                            ];
                            echo $success_messages[$_GET['success']] ?? 'Operation completed successfully!';
                            ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                        <div class="alert-box error">
                            <i class="feather icon-alert-circle"></i>
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
                        </div>
                        <?php endif; ?>

                        <!-- Blog Posts List Card -->
                        <div class="sa-card">
                            <div class="sa-card-hdr">
                                <h3><i class="feather icon-file-text" style="margin-right:8px"></i>Blog Posts</h3>
                                <span class="badge-num"><?= $total_items ?> total</span>
                            </div>
                            <div class="sa-card-body">
                                <?php if (!empty($blog_posts)): ?>
                                    <?php foreach ($blog_posts as $post): ?>
                                    <div class="post-entry" style="border-left-color: <?= $post['status'] == 'published' ? 'var(--green)' : 'var(--amber)' ?>">
                                        <?php if (!empty($post['featured_image'])): ?>
                                            <img src="..<?= htmlspecialchars($post['featured_image']) ?>" alt="Featured" class="post-image">
                                        <?php else: ?>
                                            <div class="post-image-placeholder">
                                                <i class="feather icon-image" style="font-size: 1.5rem"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="post-content">
                                            <h4 class="post-title"><?= htmlspecialchars($post['title']) ?></h4>
                                            <div class="post-slug">/<?= htmlspecialchars($post['slug']) ?></div>
                                            <div class="post-meta">
                                                <span><i class="feather icon-user"></i> <?= htmlspecialchars($post['author'] ?? 'Unknown') ?></span>
                                                <span><i class="feather icon-folder"></i> <?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></span>
                                                <span><i class="feather icon-calendar"></i> <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                                <span class="post-status <?= $post['status'] ?>"><?= ucfirst($post['status']) ?></span>
                                            </div>
                                        </div>
                                        <div class="post-actions">
                                            <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="action-btn" title="Edit">
                                                <i class="feather icon-edit-2"></i>
                                            </a>
                                            <button class="action-btn delete delete-blog-post" data-id="<?= $post['id'] ?>" data-title="<?= htmlspecialchars($post['title']) ?>" title="Delete">
                                                <i class="feather icon-trash-2"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="feather icon-file-text"></i></div>
                                        <div class="empty-state-text">No blog posts found</div>
                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrap">
                                    <ul class="pagination">
                                        <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>">
                                                <i class="feather icon-chevron-left"></i>
                                            </a>
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
                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= !empty($status) ? '&status=' . urlencode($status) : '' ?><?= !empty($category) ? '&category=' . urlencode($category) : '' ?>">
                                                <i class="feather icon-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="pagination-info">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($blog_posts) ?> of <?= $total_items ?> posts
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->

<!-- Create Blog Post Modal -->
<div class="modal fade" id="createBlogPostModal" tabindex="-1" role="dialog" aria-labelledby="createBlogPostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden">
            <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #2ed8b6);border:none;padding:20px 24px">
                <h5 class="modal-title" id="createBlogPostModalLabel" style="color:white;font-weight:600">
                    <i class="feather icon-plus" style="margin-right:8px"></i>Create New Blog Post
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="opacity:0.8">
                    <span style="color:white">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding:24px">
                <form id="createBlogPostForm" method="POST" action="create_blog_post.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="title">Title *</label>
                        <input type="text" class="form-control" id="title" name="title" required placeholder="Enter post title">
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="slug">Slug *</label>
                        <input type="text" class="form-control" id="slug" name="slug" required placeholder="url-friendly-version">
                        <small style="color:var(--muted);font-size:0.75rem">URL-friendly version of the title</small>
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="excerpt">Excerpt</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="2" placeholder="Brief summary of the post"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="content">Content *</label>
                        <textarea class="form-control" id="content" name="content" rows="6" required placeholder="Write your post content here..."></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom:16px">
                        <label class="form-label" for="featured_image">Featured Image</label>
                        <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*">
                        <small style="color:var(--muted);font-size:0.75rem">Upload an image for the blog post</small>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label" for="author">Author</label>
                            <input type="text" class="form-control" id="author" name="author" value="MTravels Team">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="category">Category</label>
                            <input type="text" class="form-control" id="category" name="category" placeholder="e.g., Travel Tips">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:16px;margin-bottom:0">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--border);padding:16px 24px">
                <button type="button" class="sa-btn sa-btn-ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" form="createBlogPostForm" class="sa-btn sa-btn-primary">Create Post</button>
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
