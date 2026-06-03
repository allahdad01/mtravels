<?php
session_start();
require_once '../includes/db.php';

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';

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
$stmt = $pdo->prepare("SELECT DISTINCT `category` FROM `blog_posts` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category`");
$stmt->execute();
$categories = $stmt->fetchAll();
?>
<?php include '../includes/header_super_admin.php'; ?>
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header card">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                    <?php echo __('manage_blog_posts'); ?>
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?php echo __('manage_blog_content'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="sa-btn" onclick="showModal('createBlogPostModal')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php echo __('create_post'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->

                            <!-- Filter Toolbar -->
                            <div class="sa-toolbar">
                                <form method="GET" class="sa-toolbar-form">
                                    <div class="sa-toolbar-group">
                                        <div class="sa-search-wrap">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sa-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                            <input type="text" name="search" class="sa-toolbar-input sa-search-input" placeholder="<?php echo __('search_title_content'); ?>" value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <select name="status" class="sa-toolbar-input">
                                            <option value=""><?php echo __('all_statuses'); ?></option>
                                            <option value="draft" <?= $status == 'draft' ? 'selected' : '' ?>><?php echo __('draft'); ?></option>
                                            <option value="published" <?= $status == 'published' ? 'selected' : '' ?>><?php echo __('published'); ?></option>
                                        </select>
                                        <select name="category" class="sa-toolbar-input">
                                            <option value=""><?php echo __('all_categories'); ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category == $cat['category'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['category']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><polygon points="22 3 14 3 18 7 22 3"/><path d="M3 3h11l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3z"/></svg>
                                            <?php echo __('filter'); ?>
                                        </button>
                                        <?php if (!empty($search_query) || !empty($status) || !empty($category)): ?>
                                        <a href="manage_blog_posts.php" class="sa-btn sa-btn-ghost sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            <?php echo __('clear'); ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Alerts -->
                            <?php if (isset($_GET['success'])): ?>
                            <div class="sa-alert sa-alert-success">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
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
                            <div class="sa-alert sa-alert-danger">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
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

                            <!-- Blog Posts Table -->
                            <?php if (!empty($blog_posts)): ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('post'); ?></th>
                                            <th><?php echo __('author'); ?></th>
                                            <th><?php echo __('category'); ?></th>
                                            <th><?php echo __('status'); ?></th>
                                            <th><?php echo __('created'); ?></th>
                                            <th class="sa-th-actions"><?php echo __('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blog_posts as $post): ?>
                                        <tr>
                                            <td>
                                                <div class="sa-avatar-cell">
                                                    <?php if (!empty($post['featured_image'])): ?>
                                                    <div class="sa-avatar sa-avatar-img">
                                                        <img src="..<?= htmlspecialchars($post['featured_image']) ?>" alt="">
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="sa-avatar" style="background:var(--surface2);color:var(--muted);font-size:1rem;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($post['title']) ?></div>
                                                        <div style="font-size:0.75rem;color:var(--muted);font-family:monospace;">/<?= htmlspecialchars($post['slug']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($post['author'] ?? 'Unknown') ?></td>
                                            <td><span class="pill pill-blue"><?= htmlspecialchars($post['category'] ?? 'Uncategorized') ?></span></td>
                                            <td>
                                                <span class="pill <?= $post['status'] == 'published' ? 'pill-green' : 'pill-amber' ?>"><?= ucfirst($post['status']) ?></span>
                                            </td>
                                            <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="sa-btn-icon" title="<?php echo __('edit'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </a>
                                                <button type="button" class="sa-btn-icon sa-btn-icon-danger" onclick="deletePost(<?= $post['id'] ?>, '<?= htmlspecialchars($post['title'], ENT_QUOTES) ?>')" title="<?php echo __('delete'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="sa-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <p><?php echo __('no_blog_posts_found'); ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="sa-pagination">
                                <div class="sa-pagination-btns">
                                    <?php
                                    $q = '';
                                    if (!empty($search_query)) $q .= '&search=' . urlencode($search_query);
                                    if (!empty($status)) $q .= '&status=' . urlencode($status);
                                    if (!empty($category)) $q .= '&category=' . urlencode($category);
                                    ?>
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=1<?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                    </button>
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page - 1 ?><?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                    </button>
                                    <span class="sa-pagination-info"><?php echo __('page'); ?> <?= $current_page ?> <?php echo __('of'); ?> <?= $total_pages ?> | <?= count($blog_posts) ?> <?php echo __('of'); ?> <?= $total_items ?> <?php echo __('posts'); ?></span>
                                    <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page + 1 ?><?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                    </button>
                                    <button type="button" class="sa-page-btn" <?= $current_page >= $total_pages ? 'disabled' : '' ?> onclick="window.location='?page=<?= $total_pages ?><?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 18 12 11 7"/><polyline points="6 17 13 12 6 7"/></svg>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Blog Post Modal -->
    <div class="sa-modal-overlay" id="createBlogPostModal">
        <div class="sa-modal sa-modal-wide">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo __('create_blog_post'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('createBlogPostModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="create_blog_post.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('title'); ?> *</label>
                        <input type="text" name="title" id="bp_title" class="sa-form-control" required placeholder="Enter post title">
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('slug'); ?> *</label>
                        <input type="text" name="slug" id="bp_slug" class="sa-form-control" required placeholder="url-friendly-version">
                        <small style="color:var(--muted);font-size:0.75rem;"><?php echo __('slug_hint'); ?></small>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('excerpt'); ?></label>
                        <textarea name="excerpt" class="sa-form-control" rows="2" placeholder="Brief summary of the post"></textarea>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('content'); ?> *</label>
                        <textarea name="content" class="sa-form-control" rows="6" required placeholder="Write your post content here..."></textarea>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('featured_image'); ?></label>
                        <input type="file" name="featured_image" class="sa-form-control" accept="image/*">
                        <small style="color:var(--muted);font-size:0.75rem;"><?php echo __('featured_image_hint'); ?></small>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('author'); ?></label>
                            <input type="text" name="author" class="sa-form-control" value="MTravels Team">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('category'); ?></label>
                            <input type="text" name="category" class="sa-form-control" placeholder="e.g., Travel Tips">
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('status'); ?></label>
                        <select name="status" class="sa-form-control" required>
                            <option value="draft"><?php echo __('draft'); ?></option>
                            <option value="published"><?php echo __('published'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('createBlogPostModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?php echo __('create_post'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
    :root {
        --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --muted: #888;
        --surface: #fff;
        --surface2: #f5f6fa;
        --border: #e0e0e0;
        --text: #333;
        --radius: 10px;
        --green: #10b981;
        --red: #ef4444;
        --amber: #f59e0b;
        --blue: #3b82f6;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif; background:#f0f2f5; color:var(--text); }

    /* ─── PAGE HEADER ────────────────────────────────────────── */
    .page-header.card {
        background: var(--grad) !important; color: #fff; border: none !important;
        margin-bottom: 20px; padding: 22px 28px !important;
        box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
        position: relative; overflow: hidden;
    }
    .page-header.card::after {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }
    .page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
    .page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
    .page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
    .page-header.card .sa-btn:hover { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; transform: translateY(-1px); }

    /* ─── ALERTS ──────────────────────────────────────────────── */
    .sa-alert {
        display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
        border-radius: var(--radius); border: 1px solid var(--border);
        margin-bottom: 16px; animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .sa-alert-success { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
    .sa-alert-danger { background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    /* ─── TOOLBAR ─────────────────────────────────────────────── */
    .sa-toolbar {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        padding:16px; margin-bottom:16px;
    }
    .sa-toolbar-form { display:flex; }
    .sa-toolbar-group { display:flex; gap:10px; align-items:center; flex-wrap:wrap; width:100%; }
    .sa-search-wrap { position:relative; flex:1; min-width:180px; }
    .sa-search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none; }
    .sa-toolbar-input {
        padding:8px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.85rem;
        background:var(--surface); color:var(--text); min-width:140px; flex:1;
    }
    .sa-toolbar-input:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    .sa-search-input { padding-left:36px !important; }

    /* ─── DATA TABLE ──────────────────────────────────────────── */
    .sa-table-wrap {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
        overflow-x:auto; margin-bottom:16px;
    }
    .sa-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    .sa-table thead { background:#f8f9fc; }
    .sa-table th {
        padding:12px 14px; text-align:left; font-weight:600; color:#555;
        border-bottom:2px solid var(--border); white-space:nowrap;
    }
    .sa-table td { padding:10px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .sa-table tbody tr:hover { background:#f8f9fc; }
    .sa-table tbody tr:last-child td { border-bottom:none; }
    .sa-th-actions { text-align:right; width:80px; }
    .sa-td-actions { text-align:right; white-space:nowrap; }

    /* ─── AVATAR CELL ─────────────────────────────────────────── */
    .sa-avatar-cell { display:flex; align-items:center; gap:10px; }
    .sa-avatar {
        width:36px; height:36px; border-radius:6px; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:700; font-size:0.85rem; flex-shrink:0; overflow:hidden;
    }
    .sa-avatar-img { border-radius:6px; }
    .sa-avatar-img img { width:100%; height:100%; object-fit:cover; }

    /* ─── PILLS ───────────────────────────────────────────────── */
    .pill {
        font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:20px;
        text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap; display:inline-block;
    }
    .pill-green { background:rgba(16,185,129,0.12); color:#10b981; }
    .pill-amber { background:rgba(245,158,11,0.12); color:#d97706; }
    .pill-blue { background:rgba(59,130,246,0.12); color:#3b82f6; }

    /* ─── EMPTY STATE ─────────────────────────────────────────── */
    .sa-empty {
        text-align:center; padding:48px 20px; color:var(--muted);
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:16px;
    }
    .sa-empty p { margin-top:12px; font-size:0.9rem; }

    /* ─── BUTTONS ─────────────────────────────────────────────── */
    .sa-btn {
        display:inline-flex; align-items:center; padding:9px 18px; border-radius:8px;
        font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        border:none; text-decoration:none; gap:4px;
    }
    .sa-btn-sm { padding:6px 12px; font-size:0.8rem; }
    .sa-btn-primary { background:var(--grad); color:#fff; }
    .sa-btn-primary:hover { box-shadow:0 4px 12px rgba(64,153,255,0.35); transform:translateY(-1px); }
    .sa-btn-ghost { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
    .sa-btn-ghost:hover { background:#e8e8e8; }

    /* ─── ICON BUTTONS ─────────────────────────────────────────── */
    .sa-btn-icon {
        display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
        border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;
        background:transparent; color:#666; text-decoration:none;
    }
    .sa-btn-icon:hover { background:#e8ecf1; color:var(--blue); }
    .sa-btn-icon-danger:hover { background:#fef2f2; color:var(--red); }

    /* ─── PAGINATION ──────────────────────────────────────────── */
    .sa-pagination { margin-top:16px; }
    .sa-pagination-btns { display:flex; align-items:center; justify-content:center; gap:6px; flex-wrap:wrap; }
    .sa-page-btn {
        display:inline-flex; align-items:center; justify-content:center;
        width:36px; height:36px; border-radius:8px; border:1px solid var(--border);
        background:var(--surface); cursor:pointer; transition:all 0.2s; color:#555;
    }
    .sa-page-btn:hover:not(:disabled) { border-color:var(--blue); color:var(--blue); background:#f0f4ff; }
    .sa-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
    .sa-pagination-info { font-size:0.8rem; color:var(--muted); margin:0 8px; white-space:nowrap; }

    /* ─── MODAL ───────────────────────────────────────────────── */
    .sa-modal-overlay {
        display:none; position:fixed; inset:0; z-index:9999;
        background:rgba(0,0,0,0.45); backdrop-filter:blur(2px);
        align-items:center; justify-content:center;
    }
    .sa-modal {
        background:var(--surface); border-radius:14px; width:100%; max-width:520px;
        max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3);
        animation:modalIn 0.25s ease-out;
    }
    .sa-modal-wide { max-width:680px; }
    @keyframes modalIn { from { opacity:0; transform:scale(0.95) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
    .sa-modal-header {
        display:flex; align-items:center; justify-content:space-between;
        padding:18px 22px; border-bottom:1px solid var(--border);
    }
    .sa-modal-header h5 { font-size:1.05rem; font-weight:700; display:flex; align-items:center; margin:0; }
    .sa-modal-close {
        background:none; border:none; cursor:pointer; color:#999; padding:4px; border-radius:6px;
        display:flex; align-items:center; justify-content:center;
    }
    .sa-modal-close:hover { background:var(--surface2); color:var(--text); }
    .sa-modal-body { padding:20px 22px; }
    .sa-modal-footer {
        display:flex; justify-content:flex-end; gap:10px;
        padding:16px 22px; border-top:1px solid var(--border); background:var(--surface2);
    }

    /* ─── FORM ELEMENTS ───────────────────────────────────────── */
    .sa-form-group { margin-bottom:14px; }
    .sa-form-label { display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:5px; }
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
    textarea.sa-form-control { resize:vertical; }
    .sa-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    @media (max-width:768px) {
        .sa-form-row { grid-template-columns:1fr; }
        .sa-toolbar-group { flex-direction:column; }
        .sa-search-wrap { width:100%; }
        .sa-toolbar-input { width:100%; }
    }
    </style>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
    <script>
    function showModal(id) {
        document.getElementById(id).style.display = 'flex';
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Auto-generate slug from title
    document.addEventListener('DOMContentLoaded', function() {
        const titleInput = document.getElementById('bp_title');
        const slugInput = document.getElementById('bp_slug');
        if (titleInput && slugInput) {
            titleInput.addEventListener('input', function() {
                const title = this.value;
                const slug = title.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                slugInput.value = slug;
            });
        }
    });

    // Delete blog post confirmation
    function deletePost(id, title) {
        if (confirm('Delete blog post "' + title + '"? This cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_blog_post.php';
            form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="post_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
<?php include '../includes/admin_footer.php'; ?>
