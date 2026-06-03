<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

$stmt = $pdo->query("
    SELECT sec.id, sec.name, sec.description, COUNT(se.id) as expense_count
    FROM system_expense_categories sec
    LEFT JOIN system_expenses se ON se.category_id = sec.id
    GROUP BY sec.id, sec.name, sec.description
    ORDER BY sec.name ASC
");
$categories = $stmt->fetchAll();
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
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
.sa-table-wrap {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; }
.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-btn-sm { padding: 6px 10px; font-size: .8rem; border-radius: 6px; }
.sa-btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; border-radius: 6px;
    cursor: pointer; transition: all .15s;
    background: transparent; color: var(--muted);
}
.sa-btn-icon:hover { background: var(--bg); color: var(--text); }
.sa-btn-icon.danger:hover { background: #fee2e2; color: #ef4444; }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }
.sa-td-actions { white-space: nowrap; text-align: right; }
.sa-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600;
    background: #dbeafe; color: #2563eb;
}
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.sa-alert {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
    font-size: .85rem;
}
.sa-alert.success { background: #d1fae5; color: #065f46; }
.sa-alert.error { background: #fee2e2; color: #991b1b; }
.sa-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: var(--surface); border-radius: var(--radius);
    width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.sa-modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sa-modal-hdr h3 { font-size: 1rem; font-weight: 600; }
.sa-modal-close {
    background: none; border: none; font-size: 1.2rem; cursor: pointer;
    color: var(--muted); padding: 4px; line-height: 1;
}
.sa-modal-body { padding: 20px; }
.sa-modal-ftr {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
}
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .85rem; }
.sa-form-control {
    width: 100%; padding: 10px 14px; border: 1px solid var(--border);
    border-radius: 8px; font-size: .85rem; font-family: inherit;
    background: var(--surface); color: var(--text);
}
.sa-form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.15); }
.sa-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text);
}
.sa-btn-secondary:hover { background: var(--bg); }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <path d="M4 21V9l8-6 8 6v12"/><path d="M10 21v-6h4v6"/>
                                </svg>
                                Expense Categories
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Manage system expense categories</p>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->

                        <?php if (isset($_GET['success'])): ?>
                        <div class="sa-alert success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($_GET['success']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                        <div class="sa-alert error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                        <?php endif; ?>

                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px">
                                        <path d="M4 21V9l8-6 8 6v12"/><path d="M10 21v-6h4v6"/>
                                    </svg>
                                    System Expense Categories
                                </h3>
                                <button type="button" class="sa-btn" onclick="openModal('addCategoryModal')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Category
                                </button>
                            </div>

                            <?php if (!empty($categories)): ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Description</th>
                                        <th>Expenses</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                        <td style="color:var(--muted)"><?= htmlspecialchars($cat['description'] ?? 'No description') ?></td>
                                        <td><span class="sa-badge"><?= $cat['expense_count'] ?> expenses</span></td>
                                        <td class="sa-td-actions">
                                            <button type="button" class="sa-btn-icon" title="Edit"
                                                onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES) ?>'); openModal('editCategoryModal')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <?php if ($cat['expense_count'] == 0): ?>
                                            <a href="delete_expense_category.php?id=<?= $cat['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                               class="sa-btn-icon danger" title="Delete"
                                               onclick="return confirm('Delete this category?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px"><path d="M4 21V9l8-6 8 6v12"/><path d="M10 21v-6h4v6"/></svg>
                                <p>No expense categories found</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="sa-modal-overlay" id="addCategoryModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Expense Category
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
            </div>
            <form action="create_expense_category.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label>Category Name *</label>
                        <input type="text" name="name" class="sa-form-control" required placeholder="e.g., Server & Hosting">
                    </div>
                    <div class="sa-form-group">
                        <label>Description</label>
                        <textarea name="description" class="sa-form-control" rows="3" placeholder="Describe what this category is for"></textarea>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
                    <button type="submit" class="sa-btn">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="sa-modal-overlay" id="editCategoryModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Expense Category
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
            </div>
            <form action="update_expense_category.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" id="edit_category_id">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label>Category Name *</label>
                        <input type="text" name="name" class="sa-form-control" id="edit_category_name" required>
                    </div>
                    <div class="sa-form-group">
                        <label>Description</label>
                        <textarea name="description" class="sa-form-control" id="edit_category_desc" rows="3"></textarea>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('editCategoryModal')">Cancel</button>
                    <button type="submit" class="sa-btn">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function editCategory(id, name, desc) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('edit_category_desc').value = desc;
}
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>
</body>
</html>
