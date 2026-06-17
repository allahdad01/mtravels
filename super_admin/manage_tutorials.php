<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

require_once '../includes/db.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

include '../includes/header_super_admin.php';
?>

<style>
:root {
    --sa-primary: #4099ff;
    --sa-success: #2ed8b6;
    --sa-danger: #ff5370;
    --sa-warning: #ffb64d;
    --sa-bg: #f4f6f9;
    --sa-surface: #fff;
    --sa-text: #2c3e50;
    --sa-text-muted: #6c757d;
    --sa-border: #e8ecf1;
    --sa-radius: 8px;
    --sa-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.sa-page { padding: 20px; }
.sa-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.sa-header h2 { margin:0; font-size:1.5rem; font-weight:600; color:var(--sa-text); display:flex; align-items:center; gap:10px; }
.sa-btn { padding:8px 20px; border:none; border-radius:6px; font-size:0.9rem; font-weight:500; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:6px; }
.sa-btn-primary { background:var(--sa-primary); color:#fff; }
.sa-btn-primary:hover { background:#3a8df5; transform:translateY(-1px); box-shadow:0 4px 12px rgba(64,153,255,.3); }
.sa-btn-danger { background:var(--sa-danger); color:#fff; }
.sa-btn-danger:hover { opacity:.9; }
.sa-btn-sm { padding:4px 12px; font-size:.8rem; }
.sa-card { background:var(--sa-surface); border-radius:var(--sa-radius); box-shadow:var(--sa-shadow); overflow:hidden; }
.sa-card-body { padding:20px; }
.sa-table { width:100%; border-collapse:collapse; }
.sa-table th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:.8rem; font-weight:600; color:var(--sa-text-muted); text-transform:uppercase; letter-spacing:.5px; border-bottom:2px solid var(--sa-border); }
.sa-table td { padding:10px 16px; font-size:.88rem; color:var(--sa-text); border-bottom:1px solid var(--sa-border); vertical-align:middle; }
.sa-table tr:hover td { background:#f8f9fa; }
.sa-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:.75rem; font-weight:500; }
.sa-badge-active { background:#d4edda; color:#155724; }
.sa-badge-inactive { background:#f8d7da; color:#721c24; }
.sa-badge-role { background:#e8f0fe; color:#1967d2; margin:2px; display:inline-block; padding:2px 8px; border-radius:10px; font-size:.7rem; }
.sa-badge-video { background:#e8f5e9; color:#2e7d32; }
.sa-empty { text-align:center; padding:40px; color:var(--sa-text-muted); }
.sa-empty-icon { font-size:3rem; margin-bottom:12px; }
.sa-modal .modal-dialog { max-width:650px; }
.sa-modal .modal-content { border:none; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.15); }
.sa-modal .modal-header { border-bottom:1px solid var(--sa-border); padding:16px 24px; }
.sa-modal .modal-body { padding:24px; }
.sa-modal .modal-footer { border-top:1px solid var(--sa-border); padding:12px 24px; }
.sa-form-label { display:block; font-size:.82rem; font-weight:500; color:var(--sa-text); margin-bottom:4px; }
.sa-form-control { width:100%; padding:8px 12px; border:1.5px solid var(--sa-border); border-radius:6px; font-size:.88rem; transition:border-color .2s; }
.sa-form-control:focus { outline:none; border-color:var(--sa-primary); box-shadow:0 0 0 3px rgba(64,153,255,.12); }
.sa-form-group { margin-bottom:16px; }
.sa-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.sa-checkbox-group { display:flex; flex-wrap:wrap; gap:8px; }
.sa-checkbox-label { display:flex; align-items:center; gap:6px; font-size:.85rem; color:var(--sa-text); cursor:pointer; padding:4px 10px; border:1.5px solid var(--sa-border); border-radius:6px; transition:all .2s; user-select:none; }
.sa-checkbox-label:hover { border-color:var(--sa-primary); }
.sa-checkbox-label input:checked + span { color:var(--sa-primary); }
.sa-checkbox-label:has(input:checked) { border-color:var(--sa-primary); background:#f0f7ff; }
.sa-checkbox-label input[type="checkbox"] { accent-color:var(--sa-primary); }
.sa-actions { display:flex; gap:4px; }
.sa-toast { position:fixed; top:20px; right:20px; z-index:9999; padding:14px 20px; border-radius:8px; color:#fff; font-size:.88rem; box-shadow:0 4px 16px rgba(0,0,0,.15); transform:translateX(120%); transition:transform .3s ease; }
.sa-toast.show { transform:translateX(0); }
.sa-toast-success { background:var(--sa-success); }
.sa-toast-error { background:var(--sa-danger); }
@media (max-width:768px) { .sa-form-row { grid-template-columns:1fr; } }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">
                            <div class="sa-page">

                                <div class="sa-header">
                                    <h2><i class="feather icon-book" style="color:var(--sa-primary)"></i> Manage Tutorials</h2>
                                    <button class="sa-btn sa-btn-primary" onclick="openAddModal()">
                                        <i class="feather icon-plus"></i> Add Tutorial
                                    </button>
                                </div>

                                <div class="sa-card">
                                    <div class="sa-card-body">
                                        <table class="sa-table" id="tutorialsTable">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Title</th>
                                                    <th>Category</th>
                                                    <th>Video</th>
                                                    <th>Duration</th>
                                                    <th>Chapters</th>
                                                    <th>Level</th>
                                                    <th>On Load</th>
                                                    <th>Roles</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tutorialsTableBody">
                                                <tr><td colspan="11" class="sa-empty">Loading...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sa-modal" id="tutorialModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="tutorialForm">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" id="tutorialId" value="0">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Tutorial</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="sa-form-group">
                        <label class="sa-form-label">Title <span style="color:var(--sa-danger)">*</span></label>
                        <input type="text" class="sa-form-control" name="title" id="fTitle" required>
                    </div>

                    <div class="sa-form-group">
                        <label class="sa-form-label">Description</label>
                        <textarea class="sa-form-control" name="description" id="fDescription" rows="3"></textarea>
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label">Category</label>
                            <input type="text" class="sa-form-control" name="category" id="fCategory" placeholder="e.g. dashboard, tickets">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label">Page (optional)</label>
                            <input type="text" class="sa-form-control" name="page" id="fPage" placeholder="e.g. dashboard.php">
                        </div>
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label">Video Type</label>
                            <select class="sa-form-control" name="video_type" id="fVideoType">
                                <option value="vimeo">Vimeo</option>
                                <option value="youtube">YouTube</option>
                            </select>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label">Video ID <span style="color:var(--sa-danger)">*</span></label>
                            <input type="text" class="sa-form-control" name="video_id" id="fVideoId" placeholder="Video ID from URL" required>
                        </div>
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label">Duration</label>
                            <input type="text" class="sa-form-control" name="duration" id="fDuration" placeholder="e.g. 5:00" value="5:00">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-form-label">Level</label>
                            <select class="sa-form-control" name="level" id="fLevel">
                                <option value="Beginner">Beginner</option>
                                <option value="Intermediate">Intermediate</option>
                                <option value="Advanced">Advanced</option>
                            </select>
                        </div>
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-form-label">Sort Order</label>
                            <input type="number" class="sa-form-control" name="sort_order" id="fSortOrder" value="0" min="0">
                        </div>
                        <div class="sa-form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;gap:12px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="status" id="fStatus" checked>
                                <span style="font-size:.88rem;">Active</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;" title="Auto-play this tutorial when user first loads the page">
                                <input type="checkbox" name="show_on_load" id="fShowOnLoad">
                                <span style="font-size:.88rem;">Show on Page Load</span>
                            </label>
                        </div>
                    </div>

                    <div class="sa-form-group">
                        <label class="sa-form-label">Chapters / Timestamps</label>
                        <div id="chaptersContainer">
                            <div class="chapter-entry" style="display:flex;gap:8px;margin-bottom:6px;">
                                <input type="text" class="sa-form-control" name="chapters[label][]" placeholder="Label (e.g. Add Ticket)" style="flex:1;">
                                <input type="text" class="sa-form-control" name="chapters[time][]" placeholder="Time (e.g. 3:50)" style="width:100px;">
                                <button type="button" class="sa-btn sa-btn-danger sa-btn-sm" onclick="this.parentElement.remove()" style="flex-shrink:0;">&times;</button>
                            </div>
                        </div>
                        <button type="button" class="sa-btn sa-btn-sm" style="background:#e8ecf1;color:var(--sa-text);margin-top:4px;" onclick="addChapter()">
                            <i class="feather icon-plus"></i> Add Chapter
                        </button>
                    </div>

                    <div class="sa-form-group">
                        <label class="sa-form-label">Visible to Roles</label>
                        <div class="sa-checkbox-group">
                            <label class="sa-checkbox-label">
                                <input type="checkbox" id="roleAll" onchange="toggleAllRoles(this.checked)">
                                <span>All Roles</span>
                            </label>
                            <?php
                            $all_roles = ['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin'];
                            foreach ($all_roles as $role):
                            ?>
                            <label class="sa-checkbox-label">
                                <input type="checkbox" name="roles[]" value="<?= $role ?>" class="role-checkbox">
                                <span><?= ucfirst(str_replace('_', ' ', $role)) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="sa-btn" style="background:#e8ecf1;color:var(--sa-text);" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary" id="formSubmitBtn">
                        <i class="feather icon-save"></i> Save Tutorial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
let tutorials = [];

function loadTutorials() {
    fetch('../api/tutorials/list.php?all=1')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                tutorials = data.tutorials;
                renderTable();
            }
        });
}

function renderTable() {
    const tbody = document.getElementById('tutorialsTableBody');
    if (!tutorials.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="sa-empty"><div class="sa-empty-icon"><i class="feather icon-inbox"></i></div><div>No tutorials found. Click "Add Tutorial" to create one.</div></td></tr>';
        return;
    }
    tbody.innerHTML = tutorials.map((t, i) => {
        let roles = [];
        try { roles = JSON.parse(t.roles || '["all"]'); } catch(e) { roles = ['all']; }
        const roleBadges = roles.includes('all')
            ? '<span class="sa-badge sa-badge-active">All</span>'
            : roles.map(r => `<span class="sa-badge-role">${r.replace(/_/g,' ')}</span>`).join(' ');
        const statusBadge = t.status == 1
            ? '<span class="sa-badge sa-badge-active">Active</span>'
            : '<span class="sa-badge sa-badge-inactive">Inactive</span>';
        const videoBadge = t.video_type === 'youtube'
            ? '<span class="sa-badge sa-badge-video"><i class="fab fa-youtube"></i> YouTube</span>'
            : '<span class="sa-badge" style="background:#e3f2fd;color:#1565c0;"><i class="fab fa-vimeo-v"></i> Vimeo</span>';
        const onLoadBadge = t.show_on_load == 1
            ? '<span class="sa-badge" style="background:#fff3cd;color:#856404;"><i class="feather icon-play"></i> Yes</span>'
            : '<span class="sa-badge" style="background:#e8ecf1;color:#6c757d;">No</span>';
        return `<tr>
            <td>${i + 1}</td>
            <td><strong>${esc(t.title)}</strong></td>
            <td><span class="sa-badge" style="background:#f0f0f0;color:#666;">${esc(t.category)}</span></td>
            <td>${videoBadge}</td>
            <td>${esc(t.duration)}</td>
            <td style="font-size:.75rem;color:var(--sa-text-muted);">${(() => { try { const c = JSON.parse(t.chapters || '[]'); return c.length ? '<span class="sa-badge" style="background:#e8f0fe;color:#1967d2;">' + c.length + ' chapter' + (c.length > 1 ? 's' : '') + '</span>' : '-'; } catch(e) { return '-'; } })()}</td>
            <td>${esc(t.level)}</td>
            <td>${onLoadBadge}</td>
            <td>${roleBadges}</td>
            <td>${statusBadge}</td>
            <td><div class="sa-actions">
                <button class="sa-btn sa-btn-primary sa-btn-sm" onclick="editTutorial(${t.id})" title="Edit"><i class="feather icon-edit-2"></i></button>
                <button class="sa-btn sa-btn-danger sa-btn-sm" onclick="deleteTutorial(${t.id})" title="Delete"><i class="feather icon-trash-2"></i></button>
            </div></td>
        </tr>`;
    }).join('');
}

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function timeToSeconds(t) {
    const parts = t.split(':');
    if (parts.length === 2) return parseInt(parts[0]) * 60 + parseInt(parts[1]);
    if (parts.length === 3) return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60 + parseInt(parts[2]);
    return parseInt(t) || 0;
}

function addChapter(label, time) {
    const container = document.getElementById('chaptersContainer');
    const entry = document.createElement('div');
    entry.className = 'chapter-entry';
    entry.style.cssText = 'display:flex;gap:8px;margin-bottom:6px;';
    entry.innerHTML = '<input type="text" class="sa-form-control" name="chapters[label][]" placeholder="Label (e.g. Add Ticket)" style="flex:1;" value="' + esc(label || '') + '">'
        + '<input type="text" class="sa-form-control" name="chapters[time][]" placeholder="Time (e.g. 3:50)" style="width:100px;" value="' + esc(time || '') + '">'
        + '<button type="button" class="sa-btn sa-btn-danger sa-btn-sm" onclick="this.parentElement.remove()" style="flex-shrink:0;">&times;</button>';
    container.appendChild(entry);
}

function toggleAllRoles(checked) {
    document.querySelectorAll('.role-checkbox').forEach(cb => {
        cb.checked = !checked;
        cb.disabled = checked;
    });
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Tutorial';
    document.getElementById('tutorialForm').reset();
    document.getElementById('tutorialId').value = 0;
    document.getElementById('fStatus').checked = true;
    document.getElementById('fShowOnLoad').checked = false;
    document.getElementById('roleAll').checked = false;
    toggleAllRoles(false);
    document.querySelectorAll('.role-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('chaptersContainer').innerHTML = '';
    document.getElementById('formSubmitBtn').innerHTML = '<i class="feather icon-save"></i> Save Tutorial';
    $('#tutorialModal').modal('show');
}

function editTutorial(id) {
    const t = tutorials.find(x => x.id == id);
    if (!t) return;
    document.getElementById('modalTitle').textContent = 'Edit Tutorial';
    document.getElementById('tutorialId').value = t.id;
    document.getElementById('fTitle').value = t.title || '';
    document.getElementById('fDescription').value = t.description || '';
    document.getElementById('fCategory').value = t.category || '';
    document.getElementById('fPage').value = t.page || '';
    document.getElementById('fVideoType').value = t.video_type || 'vimeo';
    document.getElementById('fVideoId').value = t.video_id || '';
    document.getElementById('fDuration').value = t.duration || '5:00';
    document.getElementById('fLevel').value = t.level || 'Beginner';
    document.getElementById('fSortOrder').value = t.sort_order || 0;
    document.getElementById('fStatus').checked = t.status == 1;
    document.getElementById('fShowOnLoad').checked = t.show_on_load == 1;

    let roles = [];
    try { roles = JSON.parse(t.roles || '["all"]'); } catch(e) { roles = ['all']; }
    const isAll = roles.includes('all');
    document.getElementById('roleAll').checked = isAll;
    toggleAllRoles(isAll);
    document.querySelectorAll('.role-checkbox').forEach(cb => {
        cb.checked = isAll || roles.includes(cb.value);
    });

    // Populate chapters
    document.getElementById('chaptersContainer').innerHTML = '';
    let chapters = [];
    try { chapters = JSON.parse(t.chapters || '[]'); } catch(e) { chapters = []; }
    if (chapters.length) {
        chapters.forEach(function(ch) { addChapter(ch.label, ch.time); });
    }

    document.getElementById('formSubmitBtn').innerHTML = '<i class="feather icon-save"></i> Update Tutorial';
    $('#tutorialModal').modal('show');
}

document.getElementById('tutorialForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const id = document.getElementById('tutorialId').value;
    const isEdit = id > 0;
    const url = isEdit ? '../api/tutorials/update.php' : '../api/tutorials/add.php';

    if (isEdit) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = id;
        this.appendChild(input);
    }

    const formData = new FormData(this);
    // Serialize chapters as JSON
    const labelInputs = document.querySelectorAll('input[name="chapters[label][]"]');
    const timeInputs = document.querySelectorAll('input[name="chapters[time][]"]');
    const chapters = [];
    for (let i = 0; i < labelInputs.length; i++) {
        const label = labelInputs[i].value.trim();
        const time = timeInputs[i].value.trim();
        if (label && time) {
            const seconds = timeToSeconds(time);
            chapters.push({ label: label, time: time, seconds: seconds });
        }
    }
    formData.set('chapters', JSON.stringify(chapters));

    if (document.getElementById('roleAll').checked) {
        formData.delete('roles[]');
        formData.append('roles[]', 'all');
    }

    const btn = document.getElementById('formSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather icon-loader"></i> Saving...';

    fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            $('#tutorialModal').modal('hide');
            if (data.success) {
                showToast('Tutorial ' + (isEdit ? 'updated' : 'created') + ' successfully!', 'success');
                loadTutorials();
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="feather icon-save"></i> ' + (isEdit ? 'Update' : 'Save') + ' Tutorial'; });
});

function deleteTutorial(id) {
    if (!confirm('Are you sure you want to delete this tutorial?')) return;
    fetch('../api/tutorials/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: '<?= $_SESSION['csrf_token'] ?>' })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Tutorial deleted!', 'success');
                loadTutorials();
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        });
}

let toastTimer;

function showToast(msg, type) {
    const existing = document.querySelector('.sa-toast');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'sa-toast sa-toast-' + type;
    div.textContent = msg;
    document.body.appendChild(div);
    clearTimeout(toastTimer);
    requestAnimationFrame(() => div.classList.add('show'));
    toastTimer = setTimeout(() => { div.classList.remove('show'); setTimeout(() => div.remove(), 300); }, 3000);
}

loadTutorials();
</script>

</body>
</html>
