<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

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
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once __DIR__ . '/../includes/db.php';

// ── Handle AJAX requests ──────────────────────────────────────────
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    switch ($action) {

        case 'list':
            $search = $_POST['search'] ?? '';
            $category = $_POST['category'] ?? '';
            $source = $_POST['source'] ?? '';
            $page = max(1, (int) ($_POST['page'] ?? 1));
            $perPage = 50;
            $offset = ($page - 1) * $perPage;

            $where = [];
            $params = [];

            if ($search !== '') {
                $where[] = "(english_name LIKE ? OR dari LIKE ? OR pashto LIKE ?)";
                $like = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
            if ($category !== '') {
                $where[] = "category = ?";
                $params[] = $category;
            }
            if ($source !== '') {
                $where[] = "source = ?";
                $params[] = $source;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM name_dictionary {$whereClause}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT * FROM name_dictionary {$whereClause} ORDER BY hit_count DESC, english_name ASC LIMIT {$perPage} OFFSET {$offset}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'rows'    => $rows,
                'total'   => $total,
                'pages'   => (int) ceil($total / $perPage),
                'page'    => $page,
            ]);
            exit;

        case 'create':
            $english = trim($_POST['english_name'] ?? '');
            $dari = trim($_POST['dari'] ?? '') ?: null;
            $pashto = trim($_POST['pashto'] ?? '') ?: null;
            $category = $_POST['category'] ?? 'person';

            if ($english === '') {
                echo json_encode(['success' => false, 'message' => 'English name is required']);
                exit;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO name_dictionary (english_name, dari, pashto, category, source, verified)
                 VALUES (?, ?, ?, ?, 'manual', 1)"
            );
            try {
                $stmt->execute([mb_strtolower($english), $dari, $pashto, $category]);
                echo json_encode(['success' => true, 'message' => 'Entry created', 'id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    echo json_encode(['success' => false, 'message' => 'This English name already exists']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
            }
            exit;

        case 'update':
            $id = (int) ($_POST['id'] ?? 0);
            $dari = trim($_POST['dari'] ?? '') ?: null;
            $pashto = trim($_POST['pashto'] ?? '') ?: null;
            $category = $_POST['category'] ?? 'person';
            $verified = isset($_POST['verified']) ? 1 : 0;

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }

            $stmt = $pdo->prepare(
                "UPDATE name_dictionary SET dari = ?, pashto = ?, category = ?, verified = ? WHERE id = ?"
            );
            $stmt->execute([$dari, $pashto, $category, $verified, $id]);
            echo json_encode(['success' => true, 'message' => 'Entry updated']);
            exit;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM name_dictionary WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Entry deleted']);
            exit;

        case 'stats':
            $stats = $pdo->query("
                SELECT
                    COUNT(*) AS total,
                    SUM(source = 'seeded') AS seeded,
                    SUM(source = 'gemini') AS gemini_learned,
                    SUM(source = 'passport') AS passport_learned,
                    SUM(source = 'manual') AS manual,
                    SUM(category = 'person') AS persons,
                    SUM(category = 'place') AS places,
                    SUM(category = 'document') AS documents,
                    SUM(category = 'compound') AS compounds,
                    SUM(verified = 1) AS verified_count,
                    SUM(hit_count > 0) AS used_count
                FROM name_dictionary
            ")->fetch(PDO::FETCH_ASSOC);

            $topUsed = $pdo->query(
                "SELECT english_name, dari, pashto, hit_count, source
                 FROM name_dictionary WHERE hit_count > 0
                 ORDER BY hit_count DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'stats' => $stats, 'top_used' => $topUsed]);
            exit;

        case 'translate_test':
            require_once __DIR__ . '/../includes/translate_helper.php';
            $name = trim($_POST['name'] ?? '');
            $lang = $_POST['lang'] ?? 'fa';
            if ($name === '') {
                echo json_encode(['success' => false, 'message' => 'Name is required']);
                exit;
            }
            $result = translate_name($name, $lang);
            echo json_encode(['success' => true, 'result' => $result]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

$csrfToken = $_SESSION['csrf_token'];
?>

<?php include __DIR__ . '/../includes/header_super_admin.php'; ?>

<style>
.dict-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 16px;
}
.dict-stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.dict-stat-card.orange { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); }
.dict-stat-card.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.dict-stat-card.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
.dict-stat-num { font-size: 28px; font-weight: 700; }
.dict-stat-label { font-size: 13px; opacity: .85; }
.table td { vertical-align: middle; }
.dict-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.3;
}
.dict-badge-person { background: #e0e7ff; color: #3730a3; }
.dict-badge-place { background: #dbeafe; color: #1e40af; }
.dict-badge-document { background: #fef3c7; color: #92400e; }
.dict-badge-compound { background: #ede9fe; color: #5b21b6; }
.dict-badge-other { background: #f3f4f6; color: #374151; }
.dict-badge-seeded { background: #d1fae5; color: #065f46; }
.dict-badge-gemini { background: #fef9c3; color: #854d0e; }
.dict-badge-passport { background: #dbeafe; color: #1e40af; }
.dict-badge-manual { background: #dbeafe; color: #1e40af; }
.arabic-cell { font-size: 16px; direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', 'Arial', sans-serif; }
#loadingOverlay {
    display: none;
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,.7); z-index: 9999;
    justify-content: center; align-items: center;
}
#loadingOverlay.active { display: flex; }
</style>

<div id="loadingOverlay"><div class="spinner-border text-primary" role="status"></div></div>

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
                                    <h5 class="m-b-10">Name Dictionary</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="platform_settings.php">Settings</a></li>
                                    <li class="breadcrumb-item"><a href="#!">Name Dictionary</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Stats Cards -->
                        <div class="row mb-4" id="statsRow">
                            <div class="col-md-3 col-6">
                                <div class="dict-stat-card">
                                    <div class="dict-stat-num" id="statTotal">-</div>
                                    <div class="dict-stat-label">Total Entries</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="dict-stat-card green">
                                    <div class="dict-stat-num" id="statSeeded">-</div>
                                    <div class="dict-stat-label">Seeded</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="dict-stat-card orange">
                                    <div class="dict-stat-num" id="statLearned">-</div>
                                    <div class="dict-stat-label">Gemini Learned</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="dict-stat-card blue">
                                    <div class="dict-stat-num" id="statPassport">-</div>
                                    <div class="dict-stat-label">Passport</div>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="dict-stat-card red">
                                    <div class="dict-stat-num" id="statManual">-</div>
                                    <div class="dict-stat-label">Manual</div>
                                </div>
                            </div>
                        </div>

                        <!-- Toolbar -->
                        <div class="card mb-3">
                            <div class="card-body py-2">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search names...">
                                    </div>
                                    <div class="col-md-2">
                                        <select id="filterCategory" class="form-select form-select-sm">
                                            <option value="">All Categories</option>
                                            <option value="person">Person</option>
                                            <option value="place">Place</option>
                                            <option value="document">Document</option>
                                            <option value="compound">Compound</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select id="filterSource" class="form-select form-select-sm">
                                            <option value="">All Sources</option>
                                            <option value="seeded">Seeded</option>
                                            <option value="gemini">Gemini</option>
                                            <option value="passport">Passport</option>
                                            <option value="manual">Manual</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary btn-sm w-100" onclick="loadEntries(1)"><i class="fas fa-search me-1"></i>Search</button>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-success btn-sm w-100" onclick="showAddModal()"><i class="fas fa-plus me-1"></i>Add Entry</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Results -->
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:50px">ID</th>
                                                <th>English Name</th>
                                                <th>Dari</th>
                                                <th>Pashto</th>
                                                <th style="width:100px">Category</th>
                                                <th style="width:80px">Source</th>
                                                <th style="width:60px">Hits</th>
                                                <th style="width:50px">OK</th>
                                                <th style="width:100px">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="entriesBody">
                                            <tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center">
                                <small class="text-muted" id="resultInfo">-</small>
                                <nav id="pagination"></nav>
                            </div>
                        </div>

                        <!-- Top Used Names -->
                        <div class="card mt-3">
                            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Most Used Names</div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr><th>English</th><th>Dari</th><th>Pashto</th><th>Hits</th><th>Source</th></tr>
                                    </thead>
                                    <tbody id="topUsedBody">
                                        <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Translate Test -->
                        <div class="card mt-3">
                            <div class="card-header"><i class="fas fa-flask me-2"></i>Test Translation</div>
                            <div class="card-body">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label form-label-sm">Name (English)</label>
                                        <input type="text" id="testInput" class="form-control form-control-sm" placeholder="e.g. Mohammad Ahmad">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label form-label-sm">Language</label>
                                        <select id="testLang" class="form-select form-select-sm">
                                            <option value="fa">Dari</option>
                                            <option value="ps">Pashto</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-info btn-sm" onclick="testTranslation()"><i class="fas fa-play me-1"></i>Test</button>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm">Result</label>
                                        <div id="testResult" class="form-control form-control-sm arabic-cell" style="min-height:38px;background:#f8f9fa;">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->

<!-- Add/Edit Modal -->
<div class="modal fade" id="entryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="entryId">
                <div class="mb-3">
                    <label class="form-label">English Name <span class="text-danger">*</span></label>
                    <input type="text" id="entryEnglish" class="form-control" placeholder="e.g. mohammad ahmad">
                </div>
                <div class="mb-3">
                    <label class="form-label">Dari</label>
                    <input type="text" id="entryDari" class="form-control arabic-cell" placeholder="محمد احمد" dir="rtl">
                </div>
                <div class="mb-3">
                    <label class="form-label">Pashto</label>
                    <input type="text" id="entryPashto" class="form-control arabic-cell" placeholder="محمد احمد" dir="rtl">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select id="entryCategory" class="form-select">
                        <option value="person">Person</option>
                        <option value="place">Place</option>
                        <option value="document">Document</option>
                        <option value="compound">Compound</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-check" id="verifiedGroup" style="display:none">
                    <input class="form-check-input" type="checkbox" id="entryVerified">
                    <label class="form-check-label" for="entryVerified">Verified (trusted entry)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEntry()">Save</button>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CSRF = '<?= $csrfToken ?>';
let currentPage = 1;

function showLoading() { document.getElementById('loadingOverlay').classList.add('active'); }
function hideLoading() { document.getElementById('loadingOverlay').classList.remove('active'); }

function ajax(action, data) {
    data.append('action', action);
    data.append('csrf_token', CSRF);
    return fetch('name_dictionary.php', { method: 'POST', body: data })
        .then(r => r.json());
}

function loadStats() {
    const fd = new FormData();
    ajax('stats', fd).then(res => {
        if (!res.success) return;
        const s = res.stats;
        document.getElementById('statTotal').textContent = s.total || 0;
        document.getElementById('statSeeded').textContent = s.seeded || 0;
        document.getElementById('statLearned').textContent = s.gemini_learned || 0;
        document.getElementById('statPassport').textContent = s.passport_learned || 0;
        document.getElementById('statManual').textContent = s.manual || 0;

        const tbody = document.getElementById('topUsedBody');
        if (!res.top_used || res.top_used.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No data yet</td></tr>';
            return;
        }
        tbody.innerHTML = res.top_used.map(r => `
            <tr>
                <td>${esc(r.english_name)}</td>
                <td class="arabic-cell">${esc(r.dari||'-')}</td>
                <td class="arabic-cell">${esc(r.pashto||'-')}</td>
                <td><strong>${r.hit_count}</strong></td>
                <td><span class="dict-badge dict-badge-${r.source}">${r.source}</span></td>
            </tr>
        `).join('');
    });
}

function loadEntries(page) {
    currentPage = page || 1;
    const fd = new FormData();
    fd.append('search', document.getElementById('searchInput').value);
    fd.append('category', document.getElementById('filterCategory').value);
    fd.append('source', document.getElementById('filterSource').value);
    fd.append('page', currentPage);

    ajax('list', fd).then(res => {
        const tbody = document.getElementById('entriesBody');
        if (!res.success || res.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No entries found</td></tr>';
            document.getElementById('resultInfo').textContent = '0 results';
            document.getElementById('pagination').innerHTML = '';
            return;
        }

        tbody.innerHTML = res.rows.map(r => `
            <tr>
                <td class="text-muted">${r.id}</td>
                <td><strong>${esc(r.english_name)}</strong></td>
                <td class="arabic-cell">${esc(r.dari||'')}</td>
                <td class="arabic-cell">${esc(r.pashto||'')}</td>
                <td><span class="dict-badge dict-badge-${r.category}">${r.category}</span></td>
                <td><span class="dict-badge dict-badge-${r.source}">${r.source}</span></td>
                <td>${r.hit_count}</td>
                <td>${r.verified ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-muted"></i>'}</td>
                <td>
                    <button class="btn btn-outline-primary btn-sm" onclick='editEntry(${JSON.stringify(r)})' title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteEntry(${r.id})" title="Delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');

        document.getElementById('resultInfo').textContent = `${res.total} results (page ${res.page}/${res.pages})`;
        renderPagination(res.page, res.pages);
    });
}

function renderPagination(current, total) {
    if (total <= 1) { document.getElementById('pagination').innerHTML = ''; return; }
    let html = '<ul class="pagination pagination-sm mb-0">';
    for (let i = 1; i <= total; i++) {
        html += `<li class="page-item ${i===current?'active':''}"><a class="page-link" href="#" onclick="loadEntries(${i});return false;">${i}</a></li>`;
    }
    html += '</ul>';
    document.getElementById('pagination').innerHTML = html;
}

function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Entry';
    document.getElementById('entryId').value = '';
    document.getElementById('entryEnglish').value = '';
    document.getElementById('entryDari').value = '';
    document.getElementById('entryPashto').value = '';
    document.getElementById('entryCategory').value = 'person';
    document.getElementById('entryVerified').checked = false;
    document.getElementById('verifiedGroup').style.display = 'none';
    document.getElementById('entryEnglish').disabled = false;
    new bootstrap.Modal(document.getElementById('entryModal')).show();
}

function editEntry(row) {
    document.getElementById('modalTitle').textContent = 'Edit Entry';
    document.getElementById('entryId').value = row.id;
    document.getElementById('entryEnglish').value = row.english_name;
    document.getElementById('entryEnglish').disabled = true;
    document.getElementById('entryDari').value = row.dari || '';
    document.getElementById('entryPashto').value = row.pashto || '';
    document.getElementById('entryCategory').value = row.category;
    document.getElementById('entryVerified').checked = !!row.verified;
    document.getElementById('verifiedGroup').style.display = 'block';
    new bootstrap.Modal(document.getElementById('entryModal')).show();
}

function saveEntry() {
    const id = document.getElementById('entryId').value;
    const fd = new FormData();
    fd.append('english_name', document.getElementById('entryEnglish').value);
    fd.append('dari', document.getElementById('entryDari').value);
    fd.append('pashto', document.getElementById('entryPashto').value);
    fd.append('category', document.getElementById('entryCategory').value);

    if (id) {
        fd.append('id', id);
        if (document.getElementById('entryVerified').checked) fd.append('verified', '1');
        ajax('update', fd).then(res => {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('entryModal')).hide();
                loadEntries(currentPage);
                loadStats();
            } else {
                alert(res.message);
            }
        });
    } else {
        ajax('create', fd).then(res => {
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('entryModal')).hide();
                loadEntries(1);
                loadStats();
            } else {
                alert(res.message);
            }
        });
    }
}

function deleteEntry(id) {
    if (!confirm('Delete this entry?')) return;
    const fd = new FormData();
    fd.append('id', id);
    ajax('delete', fd).then(res => {
        if (res.success) {
            loadEntries(currentPage);
            loadStats();
        } else {
            alert(res.message);
        }
    });
}

function testTranslation() {
    const name = document.getElementById('testInput').value.trim();
    const lang = document.getElementById('testLang').value;
    if (!name) return;
    document.getElementById('testResult').textContent = '...';
    const fd = new FormData();
    fd.append('name', name);
    fd.append('lang', lang);
    ajax('translate_test', fd).then(res => {
        document.getElementById('testResult').textContent = res.success ? res.result : 'Error';
    });
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Init
loadStats();
loadEntries(1);
</script>
</body>
</html>
