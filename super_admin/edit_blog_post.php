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

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    header('Location: manage_blog_posts.php?error=invalid_id');
    exit();
}

$stmt = $pdo->prepare("SELECT `id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at` FROM `blog_posts` WHERE `id` = ?");
$stmt->execute([$post_id]);
$blog_post = $stmt->fetch();
if (!$blog_post) {
    header('Location: manage_blog_posts.php?error=post_not_found');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=csrf');
        exit();
    }

    $required_fields = ['title', 'slug', 'content', 'status'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=missing_' . $field);
            exit();
        }
    }

    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $content = trim($_POST['content']);
    $excerpt = isset($_POST['excerpt']) ? trim($_POST['excerpt']) : '';
    $author = isset($_POST['author']) ? trim($_POST['author']) : 'MTravels Team';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $status = $_POST['status'];

    if (!in_array($status, ['draft', 'published'])) {
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=invalid_status');
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $post_id]);
    if ($stmt->rowCount() > 0) {
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=slug_exists');
        exit();
    }

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

        if ($_FILES['featured_image']['size'] > 5 * 1024 * 1024) {
            header('Location: edit_blog_post.php?id=' . $post_id . '&error=file_too_large');
            exit();
        }

        $filename = uniqid('blog_') . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
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

    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, author = ?, category = ?, status = ?, updated_at = NOW() WHERE id = ?");
    if ($stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author, $category, $status, $post_id])) {
        header('Location: manage_blog_posts.php?success=updated');
        exit();
    } else {
        header('Location: edit_blog_post.php?id=' . $post_id . '&error=update_failed');
        exit();
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    <?php echo __('edit_blog_post'); ?>
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?php echo __('edit_blog_post_desc'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="manage_blog_posts.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                    <?php echo __('back_to_list'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->

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
                                    'update_failed' => 'Failed to update blog post.'
                                ];
                                echo $error_messages[$_GET['error']] ?? 'An error occurred.';
                                ?>
                            </div>
                            <?php endif; ?>

                            <div class="sa-card">
                                <div class="sa-card-header">
                                    <h5>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                        <?php echo htmlspecialchars($blog_post['title']); ?>
                                    </h5>
                                </div>
                                <div class="sa-card-body">
                                    <form method="POST" action="edit_blog_post.php?id=<?= $post_id ?>" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                        <div class="sa-form-group">
                                            <label class="sa-form-label"><?php echo __('title'); ?> *</label>
                                            <input type="text" class="sa-form-control" name="title" value="<?= htmlspecialchars($blog_post['title']) ?>" required>
                                        </div>

                                        <div class="sa-form-row">
                                            <div class="sa-form-group">
                                                <label class="sa-form-label"><?php echo __('slug'); ?> *</label>
                                                <input type="text" class="sa-form-control" name="slug" value="<?= htmlspecialchars($blog_post['slug']) ?>" required>
                                            </div>
                                            <div class="sa-form-group">
                                                <label class="sa-form-label"><?php echo __('status'); ?></label>
                                                <select class="sa-form-control" name="status" required>
                                                    <option value="draft" <?= $blog_post['status'] == 'draft' ? 'selected' : '' ?>><?php echo __('draft'); ?></option>
                                                    <option value="published" <?= $blog_post['status'] == 'published' ? 'selected' : '' ?>><?php echo __('published'); ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="sa-form-group">
                                            <label class="sa-form-label"><?php echo __('excerpt'); ?></label>
                                            <textarea class="sa-form-control" name="excerpt" rows="3"><?= htmlspecialchars($blog_post['excerpt']) ?></textarea>
                                        </div>

                                        <div class="sa-form-group">
                                            <label class="sa-form-label"><?php echo __('content'); ?> *</label>
                                            <textarea class="sa-form-control" name="content" rows="15" required><?= htmlspecialchars($blog_post['content']) ?></textarea>
                                        </div>

                                        <div class="sa-form-row">
                                            <div class="sa-form-group">
                                                <label class="sa-form-label"><?php echo __('author'); ?></label>
                                                <input type="text" class="sa-form-control" name="author" value="<?= htmlspecialchars($blog_post['author']) ?>">
                                            </div>
                                            <div class="sa-form-group">
                                                <label class="sa-form-label"><?php echo __('category'); ?></label>
                                                <input type="text" class="sa-form-control" name="category" value="<?= htmlspecialchars($blog_post['category']) ?>">
                                            </div>
                                        </div>

                                        <div class="sa-form-group">
                                            <label class="sa-form-label"><?php echo __('featured_image'); ?></label>
                                            <input type="file" class="sa-form-control" name="featured_image" accept="image/*">
                                            <small style="color:var(--muted);font-size:0.75rem;display:block;margin-top:4px;"><?php echo __('featured_image_update_hint'); ?></small>
                                            <?php if (!empty($blog_post['featured_image'])): ?>
                                            <div style="margin-top:8px;">
                                                <img src="..<?= htmlspecialchars($blog_post['featured_image']) ?>" alt="Current featured image" style="max-width:200px;max-height:200px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                                                <p style="font-size:0.78rem;color:var(--muted);margin-top:4px;"><?php echo __('current_image_will_be_replaced'); ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <div style="display:flex;gap:10px;margin-top:20px;">
                                            <button type="submit" class="sa-btn sa-btn-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                                <?php echo __('update_post'); ?>
                                            </button>
                                            <a href="manage_blog_posts.php" class="sa-btn sa-btn-ghost"><?php echo __('cancel'); ?></a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
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
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,sans-serif; background:#f0f2f5; color:var(--text); }

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

    .sa-alert {
        display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
        border-radius: var(--radius); border: 1px solid var(--border);
        margin-bottom: 16px; animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .sa-alert-danger { background:#fef2f2; border-color:#fecaca; color:#991b1b; }

    .sa-card {
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    }
    .sa-card-header {
        padding:16px 20px; border-bottom:1px solid var(--border);
    }
    .sa-card-header h5 {
        font-size:0.95rem; font-weight:700; margin:0; display:flex; align-items:center;
    }
    .sa-card-body { padding:24px; }

    .sa-btn {
        display:inline-flex; align-items:center; padding:9px 18px; border-radius:8px;
        font-size:0.85rem; font-weight:600; cursor:pointer; transition:all 0.2s;
        border:none; text-decoration:none; gap:4px;
    }
    .sa-btn-primary { background:var(--grad); color:#fff; }
    .sa-btn-primary:hover { box-shadow:0 4px 12px rgba(64,153,255,0.35); transform:translateY(-1px); }
    .sa-btn-ghost { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
    .sa-btn-ghost:hover { background:#e8e8e8; }

    .sa-form-group { margin-bottom:16px; }
    .sa-form-label { display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:5px; }
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
    textarea.sa-form-control { resize:vertical; }
    .sa-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

    @media (max-width:768px) {
        .sa-form-row { grid-template-columns:1fr; }
    }
    </style>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
    <script>
    // Auto-generate slug from title
    document.addEventListener('DOMContentLoaded', function() {
        const titleInput = document.querySelector('.sa-card-header h5');
        const slugInput = document.querySelector('input[name="slug"]');
        const titleField = document.querySelector('input[name="title"]');
        if (titleField && slugInput) {
            titleField.addEventListener('input', function() {
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
    </script>
<?php include '../includes/admin_footer.php'; ?>
