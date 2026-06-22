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

<div id="playVideoOverlay" style="display:none;position:fixed;z-index:99999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;">
    <div style="position:relative;width:90%;max-width:900px;border-radius:8px;overflow:hidden;background:#000;">
        <div style="background:#1a1a2e;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;">
            <span style="color:#fff;font-weight:600;font-size:.9rem;" id="playVideoTitle">Tutorial</span>
            <div>
                <span style="color:#fff;font-size:24px;cursor:pointer;line-height:1;" onclick="closePlayOverlay()">&times;</span>
            </div>
        </div>
        <div style="position:relative;width:100%;padding-bottom:56.25%;height:0;">
            <iframe id="playVideoPlayer" src="" allow="autoplay; fullscreen; picture-in-picture" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe>
        </div>
        <div id="playVideoChapters" style="background:#1a1a2e;padding:12px 16px;border-top:1px solid #333;display:none;">
            <div style="color:#aaa;font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Chapters</div>
            <div id="playVideoChaptersList" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
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
                            <div style="display:flex;gap:6px;">
                                <input type="text" class="sa-form-control" name="video_id" id="fVideoId" placeholder="Video ID from URL" required style="flex:1;">
                                <button type="button" class="sa-btn sa-btn-primary sa-btn-sm" onclick="loadPreview()" id="previewBtn" style="white-space:nowrap;" title="Load video preview">
                                    <i class="feather icon-play"></i> Preview
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="videoPreviewArea" style="display:none;margin-bottom:16px;border-radius:8px;overflow:hidden;background:#000;">
                        <div style="position:relative;width:100%;padding-bottom:56.25%;height:0;">
                            <iframe id="modalVideoPlayer" src="" allow="autoplay; fullscreen; picture-in-picture" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe>
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
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
                            <button type="button" class="sa-btn sa-btn-sm" style="background:#4099ff;color:#fff;" onclick="addChapter()">
                                <i class="feather icon-plus"></i> Add Manually
                            </button>
                            <button type="button" class="sa-btn sa-btn-sm" style="background:#ffb64d;color:#fff;" onclick="captureChapter()" id="captureChapterBtn" title="Capture current video time as chapter">
                                <i class="feather icon-camera"></i> Capture from Video
                            </button>
                        </div>
                        <div id="chaptersContainer"></div>
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
let modalYtPlayer = null;
let modalVimeoPlayer = null;
let modalYtApiLoaded = false;
let modalVimeoApiLoaded = false;

function loadPreview() {
    const type = document.getElementById('fVideoType').value;
    const id = document.getElementById('fVideoId').value.trim();
    if (!id) { showToast('Enter a Video ID first', 'error'); return; }

    const area = document.getElementById('videoPreviewArea');
    const iframe = document.getElementById('modalVideoPlayer');
    const url = type === 'youtube'
        ? 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&enablejsapi=1'
        : 'https://player.vimeo.com/video/' + id + '?autoplay=1';
    iframe.src = url;
    area.style.display = 'block';

    modalYtPlayer = null;
    modalVimeoPlayer = null;

    if (type === 'youtube') {
        if (!modalYtApiLoaded) {
            modalYtApiLoaded = true;
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.body.appendChild(tag);
        }
        const checkYt = setInterval(function() {
            if (typeof YT !== 'undefined' && YT.loaded) {
                clearInterval(checkYt);
                if (!modalYtPlayer) {
                    try { modalYtPlayer = new YT.Player('modalVideoPlayer', {}); } catch(e) {}
                }
            }
        }, 500);
    } else {
        if (!modalVimeoApiLoaded) {
            modalVimeoApiLoaded = true;
            const tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            document.body.appendChild(tag);
        }
        const checkVimeo = setInterval(function() {
            if (typeof Vimeo !== 'undefined' && Vimeo.Player) {
                clearInterval(checkVimeo);
                if (!modalVimeoPlayer) {
                    try { modalVimeoPlayer = new Vimeo.Player(iframe); } catch(e) {}
                }
            }
        }, 500);
    }
}

function captureChapter() {
    const type = document.getElementById('fVideoType').value;
    let currentSeconds = 0;

    if (type === 'youtube' && modalYtPlayer && typeof modalYtPlayer.getCurrentTime === 'function') {
        currentSeconds = Math.floor(modalYtPlayer.getCurrentTime());
    } else if (type === 'vimeo' && modalVimeoPlayer && typeof modalVimeoPlayer.getCurrentTime === 'function') {
        modalVimeoPlayer.getCurrentTime().then(function(s) {
            currentSeconds = Math.floor(s);
            addChapterEntryAtTime(currentSeconds);
        }).catch(function() {
            showToast('Could not get video time. Make sure the video is playing.', 'error');
        });
        return;
    } else {
        showToast('Video not loaded or player not ready. Click Preview first.', 'error');
        return;
    }

    addChapterEntryAtTime(currentSeconds);
}

function addChapterEntryAtTime(seconds) {
    const label = prompt('Enter label for chapter at ' + secondsToTime(seconds) + ':');
    if (label === null) return;
    const timeStr = secondsToTime(seconds);
    addChapter(label, timeStr);
    showToast('Chapter "' + label + '" at ' + timeStr + ' added!', 'success');
}

function secondsToTime(s) {
    if (isNaN(s) || s < 0) return '0:00';
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return m + ':' + (sec < 10 ? '0' : '') + sec;
}

let playYtPlayer = null;
let playVimeoPlayer = null;
let playYtApiLoaded = false;
let playVimeoApiLoaded = false;

function playTutorial(id) {
    const t = tutorials.find(x => x.id == id);
    if (!t) return;

    currentPlayTutorialId = t.id;
    currentPlayType = t.video_type || 'vimeo';

    document.getElementById('playVideoTitle').textContent = t.title || 'Tutorial';
    const type = currentPlayType;
    const vid = t.video_id || '';
    if (!vid) { showToast('No video ID', 'error'); return; }

    const iframe = document.getElementById('playVideoPlayer');
    const url = type === 'youtube'
        ? 'https://www.youtube.com/embed/' + vid + '?autoplay=1&rel=0&enablejsapi=1'
        : 'https://player.vimeo.com/video/' + vid + '?autoplay=1';
    iframe.src = url;

    playYtPlayer = null;
    playVimeoPlayer = null;

    // Render chapters
    let chapters = [];
    try { chapters = JSON.parse(t.chapters || '[]'); } catch(e) { chapters = []; }
    const chContainer = document.getElementById('playVideoChapters');
    const chList = document.getElementById('playVideoChaptersList');
    if (chapters.length) {
        chContainer.style.display = 'block';
        chList.innerHTML = chapters.map(function(ch, i) {
            return '<div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,0.08);border-radius:5px;padding:5px 10px;cursor:pointer;transition:all .2s;border:1px solid transparent;hover:{background:rgba(70,128,255,0.2);border-color:#4680ff;}" onclick="seekPlayVideo(' + i + ')">'
                + '<span style="font-size:.75rem;font-weight:700;color:#4680ff;font-family:monospace;">' + esc(ch.time) + '</span>'
                + '<span style="font-size:.8rem;color:#e0e0e0;">' + esc(ch.label) + '</span>'
                + '</div>';
        }).join('');
    } else {
        chContainer.style.display = 'none';
    }

    document.getElementById('playVideoOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Load player API
    if (type === 'youtube') {
        if (!playYtApiLoaded) {
            playYtApiLoaded = true;
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            document.body.appendChild(tag);
        }
        const checkYt = setInterval(function() {
            if (typeof YT !== 'undefined' && YT.loaded) {
                clearInterval(checkYt);
                if (!playYtPlayer) {
                    try { playYtPlayer = new YT.Player('playVideoPlayer', {}); } catch(e) {}
                }
            }
        }, 500);
    } else {
        if (!playVimeoApiLoaded) {
            playVimeoApiLoaded = true;
            const tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            document.body.appendChild(tag);
        }
        const checkVimeo = setInterval(function() {
            if (typeof Vimeo !== 'undefined' && Vimeo.Player) {
                clearInterval(checkVimeo);
                if (!playVimeoPlayer) {
                    try { playVimeoPlayer = new Vimeo.Player(iframe); } catch(e) {}
                }
            }
        }, 500);
    }
}

function seekPlayVideo(index) {
    const t = tutorials.find(x => x.id == currentPlayTutorialId);
    if (!t) return;
    let chapters = [];
    try { chapters = JSON.parse(t.chapters || '[]'); } catch(e) { chapters = []; }
    const ch = chapters[index];
    if (!ch) return;
    const seconds = parseInt(ch.seconds, 10) || 0;

    const type = t.video_type || 'vimeo';
    if (type === 'youtube' && playYtPlayer && typeof playYtPlayer.seekTo === 'function') {
        playYtPlayer.seekTo(seconds, true);
    } else if (type === 'vimeo' && playVimeoPlayer && typeof playVimeoPlayer.setCurrentTime === 'function') {
        playVimeoPlayer.setCurrentTime(seconds).catch(function() {});
    }
}

let currentPlayTutorialId = null;
let currentPlayType = null;

function closePlayOverlay() {
    document.getElementById('playVideoOverlay').style.display = 'none';
    document.getElementById('playVideoPlayer').src = '';
    document.body.style.overflow = 'auto';
    playYtPlayer = null;
    playVimeoPlayer = null;
    currentPlayTutorialId = null;
    currentPlayType = null;
}

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
                <button class="sa-btn sa-btn-sm" style="background:#10b981;color:#fff;" onclick="playTutorial(${t.id})" title="Play Video"><i class="feather icon-play"></i></button>
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

function resetPreview() {
    const area = document.getElementById('videoPreviewArea');
    const iframe = document.getElementById('modalVideoPlayer');
    iframe.src = '';
    area.style.display = 'none';
    modalYtPlayer = null;
    modalVimeoPlayer = null;
}

function openAddModal() {
    resetPreview();
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
    resetPreview();
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

document.getElementById('playVideoOverlay').addEventListener('click', function(e) {
    if (e.target === this) closePlayOverlay();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('playVideoOverlay').style.display === 'flex') {
        closePlayOverlay();
    }
});

$('#tutorialModal').on('hidden.bs.modal', function () {
    resetPreview();
});

loadTutorials();
</script>

</body>
</html>
