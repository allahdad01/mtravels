<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$role_filter   = isset($_GET['role'])   ? $_GET['role']   : 'all';
$user_id       = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$query = "
    SELECT u.*, sm.base_salary, sm.currency as salary_currency, sm.status as salary_status
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
";
$params = [$tenant_id, $branch_id];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($status_filter === 'active')     $query .= " AND u.fired = 0";
if ($status_filter === 'terminated') $query .= " AND u.fired = 1";
if ($role_filter !== 'all') { $query .= " AND u.role = ?"; $params[] = $role_filter; }
if ($user_id)               { $query .= " AND u.id = ?";   $params[] = $user_id; }
$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_employees = array_filter($employees, fn($e) => !$e['fired']);
$fired_employees  = array_filter($employees, fn($e) =>  $e['fired']);

$roles_stmt = $pdo->prepare("SELECT DISTINCT role FROM users WHERE tenant_id = ? AND branch_id = ? AND role IS NOT NULL AND role != 'super_admin' ORDER BY role");
$roles_stmt->execute([$tenant_id, $branch_id]);
$roles = $roles_stmt->fetchAll(PDO::FETCH_COLUMN);

/* avatar colour fallback palette */
$palette = ['#4099ff','#2ed8b6','#10b981','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#ef4444'];
function avatarColor(string $name, array $p): string {
    return $p[crc32($name) % count($p)];
}
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}

$page_title = $user_id ? __('manage_employee') : __('employee_management');
include '../includes/header.php';
?>
<script>
window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap');

/* ─── tokens ─────────────────────────────────────────────────── */
:root {
  --bg:       #f1f5fb;
  --surface:  #ffffff;
  --border:   #e3e9f4;
  --text:     #0d1321;
  --muted:    #5a6482;
  --faint:    #9aa3be;
  --blue:     #4099ff;
  --indigo:   #2ed8b6;
  --cyan:     #00b4d8;
  --green:    #00c896;
  --amber:    #f9a825;
  --rose:     #ff4d6d;
  --violet:   #7c3aed;
  --font:     'Sora', sans-serif;
  --r:        18px;
}

* { font-family: var(--font); box-sizing: border-box; }

/* override pcoded bg */
.pcoded-content,
.pcoded-inner-content { background: var(--bg) !important; }

/* ─── page layout ─────────────────────────────────────────────── */
.em-page { padding: 24px 28px 40px; }

/* ─── TOP BANNER ─────────────────────────────────────────────── */
.em-banner {
  position: relative;
  border-radius: 22px;

  margin-bottom: 22px;
  background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  padding: 30px 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  min-height: 120px;
}

/* decorative circles */
.em-banner::before,
.em-banner::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.em-banner::before {
  width: 340px; height: 340px;
  background: radial-gradient(circle, rgba(108,92,231,.25) 0%, transparent 70%);
  top: -100px; right: 80px;
}
.em-banner::after {
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(79,110,247,.3) 0%, transparent 70%);
  bottom: -60px; right: 30%;
}

.em-banner-dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.em-banner-left { position: relative; z-index: 1; }
.em-banner-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.75);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 4px 11px;
  border-radius: 20px;
  margin-bottom: 12px;
}
.em-banner-tag i { font-size: .7rem; }

.em-banner-h1 {
  font-size: 1.7rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
  letter-spacing: -.03em;
  line-height: 1.15;
}
.em-banner-sub {
  font-size: .78rem;
  color: rgba(255,255,255,.5);
  margin: 0;
  font-weight: 500;
}

/* stat pills */
.em-banner-right {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
  flex-wrap: wrap;
}
.em-stat-pill {
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.15);
  backdrop-filter: blur(8px);
  border-radius: 14px;
  padding: 12px 20px;
  text-align: center;
  min-width: 78px;
  transition: background .2s;
}
.em-stat-pill:hover { background: rgba(255,255,255,.17); }
.em-stat-n {
  font-size: 1.55rem;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  letter-spacing: -.04em;
  margin-bottom: 3px;
}
.em-stat-n.accent { color: #7ee8c4; }
.em-stat-n.danger { color: #ff8fa3; }
.em-stat-l {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: rgba(255,255,255,.45);
}
.em-banner-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 22px;
  background: #fff;
  color: var(--blue);
  border: none;
  border-radius: 12px;
  font-size: .84rem;
  font-weight: 700;
  cursor: pointer;
  transition: transform .15s, box-shadow .15s;
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
  white-space: nowrap;
}
.em-banner-add-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0,0,0,.25);
  color: var(--indigo);
}
.em-banner-add-btn i { font-size: .9rem; }

/* ─── TOOLBAR ─────────────────────────────────────────────────── */
.em-toolbar {
  position: sticky;
  top: 0;
  z-index: 300;
  padding-bottom: 16px;
}
.em-toolbar-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
}

/* inline tab toggle */
.em-tabs {
  display: flex;
  background: var(--bg);
  border-radius: 10px;
  padding: 3px;
  border: 1px solid var(--border);
  flex-shrink: 0;
}
.em-tab {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: .79rem;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
  transition: all .18s;
  user-select: none;
  white-space: nowrap;
}
.em-tab i { font-size: .78rem; }
.em-tab.active {
  background: var(--blue);
  color: #fff;
  box-shadow: 0 2px 10px rgba(79,110,247,.35);
}
.em-tab-badge {
  background: rgba(255,255,255,.25);
  color: #fff;
  font-size: .62rem;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 10px;
  line-height: 1.6;
}
.em-tab:not(.active) .em-tab-badge {
  background: var(--border);
  color: var(--muted);
}

/* divider */
.em-divider { width: 1px; background: var(--border); align-self: stretch; margin: 3px 2px; flex-shrink: 0; }

/* search */
.em-search {
  flex: 1 1 180px;
  position: relative;
  min-width: 140px;
}
.em-search i {
  position: absolute;
  left: 11px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--faint);
  font-size: .84rem;
  pointer-events: none;
}
.em-search input {
  width: 100%;
  height: 36px;
  padding: 0 10px 0 34px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-size: .82rem;
  background: var(--bg);
  color: var(--text);
  outline: none;
  transition: border-color .18s, box-shadow .18s;
  font-family: var(--font);
}
.em-search input:focus {
  border-color: var(--blue);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(79,110,247,.1);
}
.em-select {
  height: 36px;
  padding: 0 30px 0 10px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  font-size: .82rem;
  background: var(--bg);
  color: var(--text);
  cursor: pointer;
  outline: none;
  appearance: none;
  font-family: var(--font);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%239aa3be' stroke-width='2.5'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 9px center;
  transition: border-color .18s;
}
.em-select:focus { border-color: var(--blue); background-color: #fff; }

.em-btn-apply {
  height: 36px;
  padding: 0 18px;
  background: linear-gradient(135deg, var(--blue), var(--indigo));
  border: none;
  border-radius: 9px;
  color: #fff;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: opacity .18s;
  white-space: nowrap;
  font-family: var(--font);
}
.em-btn-apply:hover { opacity: .88; }

/* ─── LIST CONTAINER ─────────────────────────────────────────── */
.em-list-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;

  box-shadow: 0 4px 32px rgba(13,19,33,.06);
}

/* column header */
.em-col-head {
  display: flex;
  align-items: center;
  height: 40px;
  padding: 0 20px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}
.em-col-head span {
  font-size: .62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--faint);

  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ─── column widths ───────────────────────────────────────────── */
.col-av { width: 60px;       flex-shrink: 0; }
.col-nm { flex: 0 0 195px;   min-width: 0; padding-right: 8px; }
.col-rl { flex: 0 0 115px;   flex-shrink: 0; }
.col-ct { flex: 1 1 200px;   min-width: 0; padding-right: 8px; }
.col-dt { flex: 0 0 96px;    flex-shrink: 0; }
.col-st { flex: 0 0 115px;   flex-shrink: 0; }
.col-ac { flex: 0 0 44px;    flex-shrink: 0; text-align: right; }

/* ─── ROW ─────────────────────────────────────────────────────── */
.em-row {
  display: flex;
  flex-direction: column;
  border-bottom: 1px solid var(--border);
  transition: background .14s;
  position: relative;
  cursor: default;
  background: var(--surface);
}
.em-row:last-child { border-bottom: none; }
.em-row:hover { background: #f6f8ff; }

.em-row-content {
  display: flex;
  align-items: center;
  padding: 0 20px;
  height: 70px;
}

.em-row.ac-expanded .em-row-content {
  border-bottom: 1px solid var(--border);
}

/* left accent on hover */
.em-row::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  border-radius: 0 4px 4px 0;
  background: linear-gradient(180deg, var(--blue), var(--indigo));
  transform: scaleY(0);
  transform-origin: center;
  transition: transform .2s cubic-bezier(.34,1.56,.64,1);
}
.em-row:hover::before,
.em-row.ac-expanded::before { transform: scaleY(1); }

/* fired row */
.em-row.fired { background: #fffafa; }
.em-row.fired:hover { background: #fff3f4; }
.em-row.fired::before { background: linear-gradient(180deg, var(--amber), var(--rose)); }
.em-row.fired .av-photo { filter: grayscale(50%) opacity(.7); }
.em-row.fired .nm-name  { color: var(--muted); text-decoration: line-through; text-decoration-color: rgba(255,77,109,.35); }

/* ─── avatar ────────────────────────────────────────────────── */
.col-av { display: flex; align-items: center; }
.av-shell {
  position: relative;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
}
.av-photo {
  width: 44px;
  height: 44px;
  border-radius: 13px;
  object-fit: cover;
  display: block;
  transition: filter .2s;
}
.av-fallback {
  width: 44px;
  height: 44px;
  border-radius: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .78rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: .02em;
}
.av-dot {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2.5px solid #fff;
}
.av-dot.on  { background: var(--green); }
.av-dot.off { background: var(--rose); }

/* ─── name ────────────────────────────────────────────────────── */
.nm-name {
  font-size: .88rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 2px;
  white-space: nowrap;

  text-overflow: ellipsis;
  letter-spacing: -.015em;
}
.nm-id {
  font-size: .68rem;
  font-weight: 600;
  color: var(--faint);
  letter-spacing: .02em;
}

/* ─── role chip ───────────────────────────────────────────────── */
.role-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: .7rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 8px;
  letter-spacing: .02em;
  white-space: nowrap;
}
.role-chip i { font-size: .68rem; }
.rc-admin   { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
.rc-manager { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.rc-cashier { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.rc-staff   { background: #fdf4ff; color: #9333ea; border: 1px solid #e9d5ff; }
.rc-default { background: var(--bg); color: var(--muted); border: 1px solid var(--border); }

/* ─── contact ─────────────────────────────────────────────────── */
.ct-row {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: .76rem;
  color: var(--muted);
  white-space: nowrap;

  text-overflow: ellipsis;
  line-height: 2;
}
.ct-row i { font-size: .72rem; color: var(--faint); flex-shrink: 0; }

/* ─── date ────────────────────────────────────────────────────── */
.dt-day  { font-size: .84rem; font-weight: 700; color: var(--text); letter-spacing: -.01em; }
.dt-year { font-size: .7rem; color: var(--faint); }

/* ─── status ────────────────────────────────────────────────── */
.st-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: .7rem;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 20px;
  white-space: nowrap;
  letter-spacing: .02em;
}
.st-chip .st-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.st-active     { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.st-active .st-dot     { background: var(--green); box-shadow: 0 0 0 3px rgba(0,200,150,.2); }
.st-terminated { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
.st-terminated .st-dot { background: var(--rose); box-shadow: 0 0 0 3px rgba(255,77,109,.18); }

/* ─── action bar (expandable) ───────────────────────────────────── */
.ac-toggle {
  width: 28px; height: 28px;
  border-radius: 7px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  color: var(--blue);
  cursor: pointer;
  transition: all .15s;
  padding: 0;
  margin-left: auto;
  font-size: .7rem;
  text-decoration: none;
  position: relative;
}

.ac-toggle:hover {
  background: var(--blue);
  color: #fff;
}

/* Expandable action bar container */
.ac-bar {
  display: none;
  flex-direction: row;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  background: linear-gradient(180deg, rgba(64,153,255,.04) 0%, transparent 100%);
  padding: 12px 20px;
  width: 100%;
  box-sizing: border-box;
}

.em-row.ac-expanded .ac-bar,
.ac-bar.show {
  display: flex;
}

/* Action sections */
.ac-section {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ac-section-label {
  font-size: .65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--faint);
  padding-right: 8px;
  border-right: 1px solid var(--border);
}

.ac-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 12px;
  height: auto;
  border-radius: 7px;
  background: var(--surface);
  border: 1.5px solid var(--border);
  color: var(--text);
  cursor: pointer;
  transition: all .15s;
  font-size: .75rem;
  white-space: nowrap;
  text-decoration: none;
  position: relative;
  font-weight: 600;
  font-family: var(--font);
}

.ac-btn i {
  font-size: .75rem;
}

/* Action button variants */
.ac-btn-primary {
  color: var(--blue);
  border-color: var(--blue);
  background: rgba(64,153,255,.08);
}

.ac-btn-primary:hover {
  background: var(--blue);
  color: #fff;
}

.ac-btn-danger {
  color: var(--rose);
  border-color: var(--rose);
  background: rgba(255,77,109,.08);
}

.ac-btn-danger:hover {
  background: var(--rose);
  color: #fff;
}

.ac-btn-success {
  color: var(--green);
  border-color: var(--green);
  background: rgba(0,200,150,.08);
}

.ac-btn-success:hover {
  background: var(--green);
  color: #fff;
}

.ac-btn-warning {
  color: var(--amber);
  border-color: var(--amber);
  background: rgba(249,168,37,.08);
}

.ac-btn-warning:hover {
  background: var(--amber);
  color: #fff;
}

.ac-btn-info {
  color: var(--cyan);
  border-color: var(--cyan);
  background: rgba(0,180,216,.08);
}

.ac-btn-info:hover {
  background: var(--cyan);
  color: #fff;
}



/* ─── empty ───────────────────────────────────────────────────── */
.em-empty {
  padding: 80px 24px;
  text-align: center;
}
.em-empty-ico {
  width: 64px; height: 64px;
  margin: 0 auto 16px;
  border-radius: 18px;
  background: var(--bg);
  border: 1.5px solid var(--border);
  display: flex; align-items: center; justify-content: center;
}
.em-empty-ico i { font-size: 1.6rem; color: var(--faint); }
.em-empty h6 { font-weight: 800; font-size: .9rem; color: var(--text); margin: 0 0 5px; }
.em-empty p  { font-size: .79rem; color: var(--muted); margin: 0; }

/* ─── row animation ───────────────────────────────────────────── */
@keyframes rowSlide {
  from { opacity: 0; transform: translateX(-6px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ─── responsive ─────────────────────────────────────────────── */
@media (max-width: 1020px) { .col-dt, .em-col-head .col-dt { display: none; } }
@media (max-width: 780px)  { .col-ct, .em-col-head .col-ct { display: none; } .em-stat-pill { display: none; } }
@media (max-width: 580px)  { .col-rl, .em-col-head .col-rl { display: none; } .em-page { padding: 16px; } }
</style>

<div class="pcoded-main-container">
 <div class="pcoded-wrapper">
  <div class="pcoded-content">
   <div class="pcoded-inner-content">
    <div class="main-body">
     <div class="page-wrapper">
      <div class="main-content">
       <div class="em-page">

        <!-- ═══ BANNER ═══ -->
        <div class="em-banner">
          <div class="em-banner-dot-grid"></div>
          <div class="em-banner-left">
            <div class="em-banner-tag">
              <i class="feather icon-users"></i>
              <?php echo __('employee_management'); ?>
            </div>
            <h1 class="em-banner-h1"><?php echo $user_id ? __('manage_employee') : 'Team Directory'; ?></h1>
            <p class="em-banner-sub"><?php echo count($employees); ?> total members &middot; managed by branch admin</p>
          </div>
          <div class="em-banner-right">
            <div class="em-stat-pill">
              <div class="em-stat-n"><?php echo count($employees); ?></div>
              <div class="em-stat-l">Total</div>
            </div>
            <div class="em-stat-pill">
              <div class="em-stat-n accent"><?php echo count($active_employees); ?></div>
              <div class="em-stat-l">Active</div>
            </div>
            <div class="em-stat-pill">
              <div class="em-stat-n danger"><?php echo count($fired_employees); ?></div>
              <div class="em-stat-l">Terminated</div>
            </div>
            <button class="em-banner-add-btn" onclick="showAddEmployeeModal()">
              <i class="feather icon-user-plus"></i><?php echo __('add_employee'); ?>
            </button>
          </div>
        </div>

        <!-- ═══ TOOLBAR ═══ -->
        <div class="em-toolbar">
          <form method="GET" id="filterForm">
            <div class="em-toolbar-card">
              <!-- tabs -->
              <div class="em-tabs">
                <div class="em-tab active" id="tab-btn-active" onclick="switchTab('active',this)">
                  <i class="feather icon-user"></i>
                  <?php echo __('active'); ?>
                  <span class="em-tab-badge"><?php echo count($active_employees); ?></span>
                </div>
                <div class="em-tab" id="tab-btn-fired" onclick="switchTab('fired',this)">
                  <i class="feather icon-user-x"></i>
                  <?php echo __('terminated'); ?>
                  <span class="em-tab-badge"><?php echo count($fired_employees); ?></span>
                </div>
              </div>
              <div class="em-divider"></div>
              <!-- search -->
              <div class="em-search">
                <i class="feather icon-search"></i>
                <input type="text" name="search"
                       placeholder="Search name, email, phone…"
                       value="<?php echo htmlspecialchars($search); ?>"
                       oninput="dbSearch()">
              </div>
              <!-- status -->
              <select name="status" class="em-select" onchange="this.form.submit()">
                <option value="all"        <?php echo $status_filter==='all'        ?'selected':''; ?>>All Statuses</option>
                <option value="active"     <?php echo $status_filter==='active'     ?'selected':''; ?>><?php echo __('active'); ?></option>
                <option value="terminated" <?php echo $status_filter==='terminated' ?'selected':''; ?>><?php echo __('terminated'); ?></option>
              </select>
              <!-- role -->
              <select name="role" class="em-select" onchange="this.form.submit()">
                <option value="all" <?php echo $role_filter==='all'?'selected':''; ?>>All Roles</option>
                <?php foreach ($roles as $role): ?>
                  <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter===$role?'selected':''; ?>>
                    <?php echo ucfirst(htmlspecialchars($role)); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="em-btn-apply">
                <i class="feather icon-filter"></i>Apply
              </button>
            </div>
          </form>
        </div>

        <!-- ═══ LIST ═══ -->
        <div class="em-list-wrap">

          <!-- Active tab -->
          <div id="tab-active" class="em-tabpane">
            <?php if (empty($active_employees)): ?>
              <div class="em-empty">
                <div class="em-empty-ico"><i class="feather icon-users"></i></div>
                <h6>No active employees</h6>
                <p>Add your first team member to get started.</p>
              </div>
            <?php else: ?>
              <!-- col headers -->
              <div class="em-col-head">
                <span class="col-av"></span>
                <span class="col-nm"><?php echo __('name'); ?></span>
                <span class="col-rl"><?php echo __('role'); ?></span>
                <span class="col-ct">Contact</span>
                <span class="col-dt"><?php echo __('join_date'); ?></span>
                <span class="col-st"><?php echo __('status'); ?></span>
                <span class="col-ac"></span>
              </div>
              <?php
              $ri = ['admin'=>'icon-shield','manager'=>'icon-briefcase','cashier'=>'icon-credit-card','staff'=>'icon-user'];
              foreach ($active_employees as $i => $emp):
                $av  = htmlspecialchars(basename($emp['profile_pic'] ?? ''));
                $rl  = strtolower($emp['role'] ?? '');
                $rc  = in_array($rl,['admin','manager','cashier','staff']) ? "rc-$rl" : 'rc-default';
                $ric = $ri[$rl] ?? 'icon-user';
                $clr = avatarColor($emp['name'], $palette);
                $ini = initials($emp['name']);
                $delay = round(min($i, 12) * 0.035, 3);
              ?>
              <div class="em-row" style="animation:rowSlide .3s ease both <?php echo $delay; ?>s">
                <!-- Row content -->
                <div class="em-row-content">
                  <!-- avatar -->
                  <div class="col-av">
                    <div class="av-shell">
                      <?php if ($av && $av !== 'default-avatar.jpg'): ?>
                        <img class="av-photo" src="../assets/images/user/<?= $av ?>" alt=""
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="av-fallback" style="display:none;background:<?= $clr ?>"><?= $ini ?></div>
                      <?php else: ?>
                        <div class="av-fallback" style="background:<?= $clr ?>"><?= $ini ?></div>
                      <?php endif; ?>
                      <span class="av-dot on"></span>
                    </div>
                  </div>
                  <!-- name -->
                  <div class="col-nm">
                    <p class="nm-name"><?php echo htmlspecialchars($emp['name']); ?></p>
                    <span class="nm-id">#<?php echo str_pad($emp['id'],4,'0',STR_PAD_LEFT); ?></span>
                  </div>
                  <!-- role -->
                  <div class="col-rl">
                    <span class="role-chip <?php echo $rc; ?>">
                      <i class="feather <?php echo $ric; ?>"></i>
                      <?php echo ucfirst(htmlspecialchars($emp['role'] ?? '')); ?>
                    </span>
                  </div>
                  <!-- contact -->
                  <div class="col-ct">
                    <div class="ct-row"><i class="feather icon-mail"></i><?php echo htmlspecialchars($emp['email']); ?></div>
                    <div class="ct-row"><i class="feather icon-phone"></i><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></div>
                  </div>
                  <!-- date -->
                  <div class="col-dt">
                    <div class="dt-day"><?php echo date('d M', strtotime($emp['created_at'])); ?></div>
                    <div class="dt-year"><?php echo date('Y', strtotime($emp['created_at'])); ?></div>
                  </div>
                  <!-- status -->
                  <div class="col-st">
                    <span class="st-chip st-active"><span class="st-dot"></span><?php echo __('active'); ?></span>
                  </div>
                  <!-- actions toggle -->
                  <div class="col-ac">
                    <button class="ac-toggle" type="button" onclick="toggleActionBar(this)">
                      <i class="feather icon-chevron-down"></i>
                    </button>
                  </div>
                </div>
                <!-- Expandable action bar -->
                <div class="ac-bar">
                  <!-- Primary Actions -->
                  <div class="ac-section">
                    <span class="ac-section-label"><?php echo __('actions'); ?></span>
                    <a href="employee_details.php?id=<?php echo $emp['id']; ?>" class="ac-btn ac-btn-primary">
                      <i class="feather icon-eye"></i><?php echo __('view_details'); ?>
                    </a>
                    <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" class="ac-btn ac-btn-primary">
                      <i class="feather icon-edit-2"></i><?php echo __('edit'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-danger" onclick="terminateEmployee(<?php echo $emp['id']; ?>,'<?php echo htmlspecialchars($emp['name']); ?>'); return false;">
                      <i class="feather icon-user-x"></i><?php echo __('terminate'); ?>
                    </a>
                  </div>
                  <!-- HR Documents -->
                  <div class="ac-section">
                    <span class="ac-section-label"><?php echo __('documents'); ?></span>
                    <a href="#" class="ac-btn ac-btn-info" onclick="showLanguageModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-file-plus"></i><?php echo __('employment_agreement'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-info" onclick="showGuarantorLanguageModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-user-check"></i><?php echo __('guarantor_letter'); ?>
                    </a>
                  </div>
                  <!-- Warnings & Letters -->
                  <div class="ac-section">
                    <span class="ac-section-label"><?php echo __('letters'); ?></span>
                    <a href="#" class="ac-btn ac-btn-warning" onclick="showTawseahModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-alert-circle"></i><?php echo __('tawseah'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-warning" onclick="showIkhtarModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-alert-triangle"></i><?php echo __('official_warning'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-warning" onclick="showFineLetterModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="fa fa-money-bill-alt"></i><?php echo __('fine_letter'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-danger" onclick="showTerminationLetterModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-file-text"></i><?php echo __('termination_letter'); ?>
                    </a>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- Fired tab -->
          <div id="tab-fired" class="em-tabpane" style="display:none">
            <?php if (empty($fired_employees)): ?>
              <div class="em-empty">
                <div class="em-empty-ico"><i class="feather icon-user-check"></i></div>
                <h6>No terminated employees</h6>
                <p>All employees are currently active.</p>
              </div>
            <?php else: ?>
              <!-- col headers -->
              <div class="em-col-head">
                <span class="col-av"></span>
                <span class="col-nm"><?php echo __('name'); ?></span>
                <span class="col-rl"><?php echo __('role'); ?></span>
                <span class="col-ct">Contact</span>
                <span class="col-dt"><?php echo __('join_date'); ?></span>
                <span class="col-st"><?php echo __('status'); ?></span>
                <span class="col-ac"></span>
              </div>
              <?php
              foreach ($fired_employees as $i => $emp):
                $av  = htmlspecialchars(basename($emp['profile_pic'] ?? ''));
                $rl  = strtolower($emp['role'] ?? '');
                $rc  = in_array($rl,['admin','manager','cashier','staff']) ? "rc-$rl" : 'rc-default';
                $ric = $ri[$rl] ?? 'icon-user';
                $clr = avatarColor($emp['name'], $palette);
                $ini = initials($emp['name']);
                $delay = round(min($i, 12) * 0.035, 3);
              ?>
              <div class="em-row fired" style="animation:rowSlide .3s ease both <?php echo $delay; ?>s">
                <!-- Row content -->
                <div class="em-row-content">
                  <div class="col-av">
                    <div class="av-shell">
                      <?php if ($av && $av !== 'default-avatar.jpg'): ?>
                        <img class="av-photo" src="../assets/images/user/<?= $av ?>" alt=""
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="av-fallback" style="display:none;background:<?= $clr ?>"><?= $ini ?></div>
                      <?php else: ?>
                        <div class="av-fallback" style="background:<?= $clr ?>"><?= $ini ?></div>
                      <?php endif; ?>
                      <span class="av-dot off"></span>
                    </div>
                  </div>
                  <div class="col-nm">
                    <p class="nm-name"><?php echo htmlspecialchars($emp['name']); ?></p>
                    <span class="nm-id">#<?php echo str_pad($emp['id'],4,'0',STR_PAD_LEFT); ?></span>
                  </div>
                  <div class="col-rl">
                    <span class="role-chip <?php echo $rc; ?>">
                      <i class="feather <?php echo $ric; ?>"></i>
                      <?php echo ucfirst(htmlspecialchars($emp['role'] ?? '')); ?>
                    </span>
                  </div>
                  <div class="col-ct">
                    <div class="ct-row"><i class="feather icon-mail"></i><?php echo htmlspecialchars($emp['email']); ?></div>
                    <div class="ct-row"><i class="feather icon-phone"></i><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></div>
                  </div>
                  <div class="col-dt">
                    <div class="dt-day"><?php echo date('d M', strtotime($emp['created_at'])); ?></div>
                    <div class="dt-year"><?php echo date('Y', strtotime($emp['created_at'])); ?></div>
                  </div>
                  <div class="col-st">
                    <span class="st-chip st-terminated"><span class="st-dot"></span><?php echo __('terminated'); ?></span>
                  </div>
                  <div class="col-ac">
                    <button class="ac-toggle" type="button" onclick="toggleActionBar(this)">
                      <i class="feather icon-chevron-down"></i>
                    </button>
                  </div>
                </div>
                <!-- Expandable action bar -->
                <div class="ac-bar">
                  <!-- Primary Actions -->
                  <div class="ac-section">
                    <span class="ac-section-label"><?php echo __('actions'); ?></span>
                    <a href="employee_details.php?id=<?php echo $emp['id']; ?>" class="ac-btn ac-btn-primary">
                      <i class="feather icon-eye"></i><?php echo __('view_details'); ?>
                    </a>
                    <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" class="ac-btn ac-btn-primary">
                      <i class="feather icon-edit-2"></i><?php echo __('edit'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-success" onclick="reinstateEmployee(<?php echo $emp['id']; ?>,'<?php echo htmlspecialchars($emp['name']); ?>'); return false;">
                      <i class="feather icon-user-check"></i><?php echo __('reinstate'); ?>
                    </a>
                    <a href="#" class="ac-btn ac-btn-danger" onclick="showTerminationLetterModal(<?php echo $emp['id']; ?>); return false;">
                      <i class="feather icon-file-text"></i><?php echo __('termination_letter'); ?>
                    </a>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        </div><!-- /.em-list-wrap -->

       </div><!-- /.em-page -->
      </div>
     </div>
    </div>
   </div>
  </div>
 </div>
</div>

<!-- Termination Modal -->
<div class="modal fade" id="terminationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:20px;overflow:hidden;border:none;box-shadow:0 32px 80px rgba(13,19,33,.22)">
      <div class="modal-header" style="background:linear-gradient(135deg,#4099ff,#2ed8b6);border:none;padding:22px 26px">
        <h5 class="modal-title" style="color:#fff;font-weight:800;margin:0;font-family:var(--font);font-size:1rem"><?php echo __('terminate_employee'); ?></h5>
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8"><span>&times;</span></button>
      </div>
      <form id="terminationForm">
        <div class="modal-body" style="padding:26px">
          <input type="hidden" id="terminateEmployeeId" name="employee_id">
          <div class="form-group">
            <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--faint)"><?php echo __('employee_name'); ?></label>
            <p id="terminateEmployeeName" style="font-weight:700;font-size:.95rem;margin:5px 0 0;color:var(--text);font-family:var(--font)"></p>
          </div>
          <div class="form-group mb-0">
            <label style="font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--faint)"><?php echo __('termination_reason'); ?></label>
            <textarea class="form-control" id="termination_reason" name="reason" rows="3" required
              style="border-radius:10px;border:1.5px solid var(--border);margin-top:6px;font-size:.84rem;resize:none;font-family:var(--font)"></textarea>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 26px;gap:8px">
          <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius:9px;font-size:.83rem;font-family:var(--font)"><?php echo __('cancel'); ?></button>
          <button type="submit" class="btn btn-danger" style="border-radius:9px;background:#ff4d6d;border-color:#ff4d6d;font-weight:700;font-size:.83rem;font-family:var(--font)"><?php echo __('terminate_employee'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../modals/employee/language_selection_modal.php'; ?>
<?php include '../modals/employee/gurantor_modal.php'; ?>
<?php include '../modals/employee/tawseah_modal.php'; ?>
<?php include '../modals/employee/fine_modal.php'; ?>
<?php include '../modals/employee/ikhtar_modal.php'; ?>
<?php include '../modals/employee/termination_modal.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/employee/employee_management.js"></script>
<script>
function switchTab(tab, el) {
  document.querySelectorAll('.em-tabpane').forEach(t => t.style.display = 'none');
  document.querySelectorAll('.em-tab').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).style.display = 'block';
  el.classList.add('active');
  document.querySelectorAll('#tab-' + tab + ' .em-row').forEach((r, i) => {
    r.style.animation = 'none';
    r.offsetHeight;
    r.style.animation = `rowSlide .3s ease both ${Math.min(i,12)*0.035}s`;
  });
}

let _dt;
function dbSearch() {
  clearTimeout(_dt);
  _dt = setTimeout(() => document.getElementById('filterForm').submit(), 480);
}

// Toggle action bar
function toggleActionBar(btn) {
  const row = btn.closest('.em-row');
  const bar = row.querySelector('.ac-bar');
  
  // Close all other bars
  document.querySelectorAll('.em-row.ac-expanded').forEach(r => {
    if (r !== row) {
      r.classList.remove('ac-expanded');
      r.querySelector('.ac-toggle').style.transform = 'rotate(0deg)';
    }
  });
  
  // Toggle current row
  row.classList.toggle('ac-expanded');
  
  // Rotate chevron
  btn.style.transform = row.classList.contains('ac-expanded') ? 'rotate(180deg)' : 'rotate(0deg)';
}

// Close bar when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.em-row')) {
    document.querySelectorAll('.em-row.ac-expanded').forEach(r => {
      r.classList.remove('ac-expanded');
      r.querySelector('.ac-toggle').style.transform = 'rotate(0deg)';
    });
  }
});
</script>
<?php include '../includes/admin_footer.php'; ?>