<?php
session_start();
require_once '../includes/db.php';

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

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

$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$tenants = $stmt->fetchAll();

$items_per_page = 10;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';
$role = $_GET['role'] ?? '';

$count_query = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
$filter_params = [];

if ($tenant_id) {
    $count_query .= " AND u.tenant_id = ?";
    $filter_params[] = $tenant_id;
}
if ($role) {
    $count_query .= " AND u.role = ?";
    $filter_params[] = $role;
}
if (!empty($search_query)) {
    $count_query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

$query = "SELECT u.id, u.name, u.email, u.role, u.tenant_id, u.created_at, t.name as tenant_name 
          FROM users u 
          LEFT JOIN tenants t ON u.tenant_id = t.id 
          WHERE 1=1";

if ($tenant_id) {
    $query .= " AND u.tenant_id = ?";
}
if ($role) {
    $query .= " AND u.role = ?";
}
if (!empty($search_query)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
}
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$users = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT DISTINCT role FROM users");
$stmt->execute();
$roles = $stmt->fetchAll();
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    <?php echo __('manage_users'); ?>
                                </h5>
                                <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9;"><?php echo __('manage_system_users'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="sa-btn" onclick="showModal('createUserModal')" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <?php echo __('create_user'); ?>
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
                                            <input type="text" name="search" class="sa-toolbar-input sa-search-input" placeholder="<?php echo __('search_name_email'); ?>" value="<?= htmlspecialchars($search_query) ?>">
                                        </div>
                                        <select name="tenant_id" class="sa-toolbar-input">
                                            <option value=""><?php echo __('all_tenants'); ?></option>
                                            <?php foreach ($tenants as $tenant): ?>
                                            <option value="<?= $tenant['id'] ?>" <?= $tenant_id == $tenant['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tenant['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="role" class="sa-toolbar-input">
                                            <option value=""><?php echo __('all_roles'); ?></option>
                                            <?php foreach ($roles as $r): ?>
                                            <option value="<?= htmlspecialchars($r['role']) ?>" <?= $role == $r['role'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r['role']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="sa-btn sa-btn-primary sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><polygon points="22 3 14 3 18 7 22 3"/><path d="M3 3h11l5 5v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3z"/></svg>
                                            <?php echo __('filter'); ?>
                                        </button>
                                        <?php if (!empty($search_query) || !empty($tenant_id) || !empty($role)): ?>
                                        <a href="manage_users.php" class="sa-btn sa-btn-ghost sa-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            <?php echo __('clear'); ?>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <!-- Users Table -->
                            <?php if (!empty($users)): ?>
                            <div class="sa-table-wrap">
                                <table class="sa-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('user'); ?></th>
                                            <th><?php echo __('tenant'); ?></th>
                                            <th><?php echo __('role'); ?></th>
                                            <th><?php echo __('created'); ?></th>
                                            <th class="sa-th-actions"><?php echo __('actions'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): 
                                            $initial = strtoupper(substr($user['name'], 0, 1));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="sa-avatar-cell">
                                                    <div class="sa-avatar" style="background:linear-gradient(135deg,#4099ff,#2ed8b6);"><?= $initial ?></div>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($user['name']) ?></div>
                                                        <div style="font-size:0.78rem;color:var(--muted);"><?= htmlspecialchars($user['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= $user['tenant_name'] ? htmlspecialchars($user['tenant_name']) : '<span style="color:var(--muted);">N/A</span>' ?></td>
                                            <td>
                                                <?php
                                                $role_colors = [
                                                    'super_admin' => '#8b5cf6',
                                                    'tenant_super_admin' => '#3b82f6',
                                                    'admin' => '#f59e0b',
                                                    'sales_agent' => '#10b981'
                                                ];
                                                $role_color = $role_colors[$user['role']] ?? '#6b7280';
                                                ?>
                                                <span class="pill" style="background:<?= $role_color ?>18;color:<?= $role_color ?>;"><?= htmlspecialchars($user['role']) ?></span>
                                            </td>
                                            <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                            <td class="sa-td-actions">
                                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="sa-btn-icon" title="<?php echo __('edit'); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </a>
                                                <button type="button" class="sa-btn-icon sa-btn-icon-danger" onclick="deleteUser(<?= $user['id'] ?>)" title="<?php echo __('delete'); ?>">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.4;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <p><?php echo __('no_users_found'); ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="sa-pagination">
                                <div class="sa-pagination-btns">
                                    <?php
                                    $q = '';
                                    if (!empty($search_query)) $q .= '&search=' . urlencode($search_query);
                                    if (!empty($tenant_id)) $q .= '&tenant_id=' . urlencode($tenant_id);
                                    if (!empty($role)) $q .= '&role=' . urlencode($role);
                                    ?>
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=1<?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                    </button>
                                    <button type="button" class="sa-page-btn" <?= $current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?page=<?= $current_page - 1 ?><?= $q ?>'">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                    </button>
                                    <span class="sa-pagination-info"><?php echo __('page'); ?> <?= $current_page ?> <?php echo __('of'); ?> <?= $total_pages ?> | <?= count($users) ?> <?php echo __('of'); ?> <?= $total_items ?> <?php echo __('users'); ?></span>
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

    <!-- Create User Modal -->
    <div class="sa-modal-overlay" id="createUserModal">
        <div class="sa-modal sa-modal-wide">
            <div class="sa-modal-header">
                <h5>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php echo __('create_user'); ?>
                </h5>
                <button type="button" class="sa-modal-close" onclick="closeModal('createUserModal')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <form method="POST" action="create_user.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('name'); ?> *</label>
                            <input type="text" name="name" class="sa-form-control" required placeholder="Enter full name">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" name="email" class="sa-form-control" required placeholder="Enter email address">
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-form-label"><?php echo __('password'); ?> *</label>
                        <input type="password" name="password" class="sa-form-control" required placeholder="Enter password">
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('role'); ?> *</label>
                            <select name="role" class="sa-form-control" required>
                                <option value="super_admin">Super Admin</option>
                                <option value="tenant_super_admin">Tenant Super Admin</option>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label"><?php echo __('tenant'); ?></label>
                            <select name="tenant_id" class="sa-form-control">
                                <option value=""><?php echo __('none'); ?></option>
                                <?php foreach ($tenants as $tenant): ?>
                                <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('createUserModal')"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="sa-btn sa-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?php echo __('create_user'); ?>
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

    .sa-avatar-cell { display:flex; align-items:center; gap:10px; }
    .sa-avatar {
        width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:700; font-size:0.85rem; flex-shrink:0; overflow:hidden;
    }

    .pill {
        font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:20px;
        text-transform:uppercase; letter-spacing:0.03em; white-space:nowrap; display:inline-block;
    }

    .sa-empty {
        text-align:center; padding:48px 20px; color:var(--muted);
        background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:16px;
    }
    .sa-empty p { margin-top:12px; font-size:0.9rem; }

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

    .sa-btn-icon {
        display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
        border-radius:6px; border:none; cursor:pointer; transition:all 0.2s;
        background:transparent; color:#666; text-decoration:none;
    }
    .sa-btn-icon:hover { background:#e8ecf1; color:var(--blue); }
    .sa-btn-icon-danger:hover { background:#fef2f2; color:var(--red); }

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
    .sa-modal-wide { max-width:640px; }
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

    .sa-form-group { margin-bottom:14px; }
    .sa-form-label { display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:5px; }
    .sa-form-control {
        width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:6px;
        font-size:0.85rem; background:var(--surface); color:var(--text); transition:border-color 0.15s;
    }
    .sa-form-control:focus { outline:none; border-color:#4099ff; box-shadow:0 0 0 2px rgba(64,153,255,0.2); }
    select.sa-form-control { cursor:pointer; }
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

    function deleteUser(id) {
        if (confirm('<?php echo __('confirm_delete_user'); ?>')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_user.php';
            form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="user_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
<?php include '../includes/admin_footer.php'; ?>
