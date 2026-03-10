<?php
// Excel Import UI for Tenant Data Migration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once 'excel_import_handler.php';
require_once 'security.php';
enforce_auth();

$isAjax = isset($_GET['is_ajax']) && $_GET['is_ajax'] === '1';

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['Tenant ID not found in session. Please log in again.'], 'success_count' => 0, 'processed_sheets' => []]);
        exit;
    }
    $message = "Error: Tenant ID not found in session. Please log in again.";
    $messageType = 'error';
    return;
}

if (!$isAjax) {
    include '../includes/header.php';
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload failed');
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowedTypes)) throw new Exception('Please upload a valid Excel file (.xlsx or .xls)');
        if ($file['size'] > 52428800) throw new Exception('File size must be less than 50MB');

        $importHandler = new ExcelImportHandler($tenant_id);
        $result = $importHandler->importFromExcel($file['tmp_name']);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        if ($result['success']) {
            $message = "Import completed successfully! Processed {$result['success_count']} records.";
            if (!empty($result['processed_sheets'])) $message .= "<br>Sheets: " . implode(', ', $result['processed_sheets']);
            $messageType = 'success';
        } else {
            $message = "Import completed with errors. Processed {$result['success_count']} records.";
            if (!empty($result['errors'])) {
                $message .= "<br>" . implode('<br>', array_slice($result['errors'], 0, 10));
                if (count($result['errors']) > 10) $message .= "<br>... and " . (count($result['errors']) - 10) . " more errors";
            }
            $messageType = 'warning';
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => [$e->getMessage()], 'success_count' => 0, 'processed_sheets' => []]);
            exit;
        }
        $message = "Import failed: " . $e->getMessage();
        $messageType = 'error';
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Invalid request'], 'success_count' => 0, 'processed_sheets' => []]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Data Import — Travel Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="../assets/plugins/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root {
            --ink:       #0f1117;
            --ink-soft:  #4a5568;
            --ink-mute:  #a0aec0;
            --surface:   #f7f8fc;
            --white:     #ffffff;
            --border:    #e2e8f0;
            --accent:    #2563eb;
            --accent-lt: #eff6ff;
            --accent-dk: #1d4ed8;
            --green:     #059669;
            --green-lt:  #ecfdf5;
            --amber:     #d97706;
            --amber-lt:  #fffbeb;
            --red:       #dc2626;
            --red-lt:    #fef2f2;
            --radius:    12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow:    0 4px 16px rgba(0,0,0,.07);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Page Shell ── */
        .page-shell {

            max-width: 1400px; margin: 0 auto; padding: 32px 28px 60px; 
        }

        /* ── Breadcrumb ── */
        .breadcrumb-bar {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .8rem;
            color: var(--ink-mute);
            margin-bottom: 2rem;
        }
        .breadcrumb-bar a { color: var(--ink-soft); text-decoration: none; }
        .breadcrumb-bar a:hover { color: var(--accent); }
        .breadcrumb-bar .sep { color: var(--border); }
        .breadcrumb-bar .current { color: var(--ink); font-weight: 500; }

        /* ── Page Header ── */
        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        .page-head__left h1 {
            font-family: 'Syne', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -.5px;
            color: var(--ink);
            line-height: 1.2;
        }
        .page-head__left p {
            margin-top: .35rem;
            font-size: .9rem;
            color: var(--ink-soft);
        }
        .btn-template {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .625rem 1.125rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            background: var(--white);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            font-weight: 500;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
            transition: border-color .2s, box-shadow .2s, transform .15s;
            white-space: nowrap;
        }
        .btn-template:hover {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37,99,235,.08);
            transform: translateY(-1px);
            color: var(--accent);
        }
        .btn-template svg { flex-shrink: 0; }

        /* ── Card ── */
        .card {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ── Upload Zone ── */
        .upload-zone {
            position: relative;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            background: var(--surface);
        }
        .upload-zone:hover, .upload-zone.drag-active {
            border-color: var(--accent);
            background: var(--accent-lt);
        }
        .upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-zone__icon {
            width: 56px; height: 56px;
            background: var(--accent-lt);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            transition: transform .2s;
        }
        .upload-zone:hover .upload-zone__icon { transform: scale(1.08); }
        .upload-zone h3 {
            font-family: 'Syne', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .35rem;
        }
        .upload-zone p { font-size: .85rem; color: var(--ink-soft); }
        .upload-zone .hint {
            display: inline-block;
            margin-top: 1rem;
            font-size: .75rem;
            color: var(--ink-mute);
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: .25rem .75rem;
        }

        /* File selected state */
        .upload-zone.has-file {
            border-style: solid;
            border-color: var(--green);
            background: var(--green-lt);
        }
        .upload-zone.has-file .upload-zone__icon {
            background: var(--green-lt);
        }

        /* ── Section divider ── */
        .section-label {
            font-family: 'Syne', sans-serif;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--ink-mute);
            margin-bottom: 1rem;
        }

        /* ── Data Types Grid ── */
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: .6rem;
        }
        .data-chip {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .6rem .9rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: .82rem;
            color: var(--ink-soft);
            font-weight: 500;
        }
        .data-chip__dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--accent);
            flex-shrink: 0;
        }

        /* ── Steps ── */
        .steps { display: flex; flex-direction: column; gap: 0; }
        .step {
            display: flex;
            gap: 1rem;
            padding: .75rem 0;
            border-bottom: 1px solid var(--border);
        }
        .step:last-child { border-bottom: none; }
        .step__num {
            width: 26px; height: 26px; min-width: 26px;
            background: var(--ink);
            color: var(--white);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            font-family: 'Syne', sans-serif;
            margin-top: 1px;
        }
        .step__text { font-size: .87rem; color: var(--ink-soft); line-height: 1.5; }
        .step__text strong { color: var(--ink); }

        /* ── Info callout ── */
        .callout {
            display: flex;
            gap: .75rem;
            padding: 1rem 1.125rem;
            border-radius: var(--radius);
            font-size: .85rem;
            line-height: 1.55;
        }
        .callout--info  { background: var(--accent-lt); color: #1e3a8a; }
        .callout--info svg { color: var(--accent); flex-shrink: 0; margin-top: 1px; }

        /* ── Submit Button ── */
        .btn-import {
            width: 100%;
            padding: .9rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-family: 'Syne', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .3px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 12px rgba(37,99,235,.3);
        }
        .btn-import:hover:not(:disabled) {
            background: var(--accent-dk);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37,99,235,.35);
        }
        .btn-import:disabled {
            background: var(--border);
            color: var(--ink-mute);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* ── Progress ── */
        .progress-wrap {
            display: none;
            margin-top: 1rem;
        }
        .progress-track {
            height: 5px;
            background: var(--border);
            border-radius: 99px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--accent), #60a5fa);
            border-radius: 99px;
            transition: width .4s ease;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0%   { filter: brightness(1); }
            50%  { filter: brightness(1.2); }
            100% { filter: brightness(1); }
        }
        .progress-label {
            margin-top: .5rem;
            font-size: .78rem;
            color: var(--ink-mute);
            text-align: center;
        }

        /* ── Alert ── */
        .alert-box {
            display: flex;
            gap: .75rem;
            padding: 1rem 1.125rem;
            border-radius: var(--radius);
            font-size: .87rem;
            margin-bottom: 1.5rem;
            animation: fadeSlide .3s ease;
        }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-box--success { background: var(--green-lt); color: #065f46; }
        .alert-box--warning { background: var(--amber-lt); color: #92400e; }
        .alert-box--error   { background: var(--red-lt);   color: #991b1b; }
        .alert-box svg { flex-shrink: 0; margin-top: 1px; }
        .alert-box .close-btn {
            margin-left: auto; background: none; border: none; cursor: pointer;
            color: inherit; opacity: .6; padding: 0;
        }
        .alert-box .close-btn:hover { opacity: 1; }

        /* ── Layout panels ── */
        .panel { padding: 1.75rem 2rem; }
        .panel + .panel { border-top: 1px solid var(--border); }

        @media (max-width: 600px) {
            .panel { padding: 1.25rem; }
            .page-head { gap: 1rem; }
            .data-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">

                <div class="page-shell">

                    <!-- Breadcrumb -->
                    <nav class="breadcrumb-bar">
                        <a href="dashboard.php">Home</a>
                        <span class="sep">/</span>
                        <span class="current">Excel Import</span>
                    </nav>

                    <!-- Page Header -->
                    <div class="page-head">
                        <div class="page-head__left">
                            <h1>Excel Data Import</h1>
                            <p>Bulk-import tenant records from a formatted spreadsheet</p>
                        </div>
                        <a href="generate_excel_template.php" class="btn-template">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m0 12-4-4m4 4 4-4M2 17v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2"/></svg>
                            Download Template
                        </a>
                    </div>

                    <!-- Alert (PHP-rendered) -->
                    <?php if (!empty($message)): ?>
                    <div class="alert-box alert-box--<?php echo $messageType; ?>">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <?php if ($messageType === 'success'): ?>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.707-9.293a1 1 0 0 0-1.414-1.414L9 10.586 7.707 9.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4z"/>
                            <?php else: ?>
                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-1-9a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1z"/>
                            <?php endif; ?>
                        </svg>
                        <div><?php echo $message; ?></div>
                        <button class="close-btn" onclick="this.closest('.alert-box').remove()">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414z"/></svg>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Main Card -->
                    <div class="card">

                        <!-- Upload Panel -->
                        <div class="panel">
                            <form id="importForm" method="POST" enctype="multipart/form-data">
                                <div class="upload-zone" id="uploadZone">
                                    <input type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" required>
                                    <div class="upload-zone__icon" id="zoneIcon">
                                        <svg width="24" height="24" fill="none" stroke="#2563eb" stroke-width="1.75" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    </div>
                                    <h3 id="zoneTitle">Drop your file here</h3>
                                    <p id="zoneSubtitle">or click anywhere to browse your computer</p>
                                    <span class="hint">.xlsx or .xls &nbsp;·&nbsp; max 50 MB</span>
                                </div>

                                <!-- Progress -->
                                <div class="progress-wrap" id="progressWrap">
                                    <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
                                    <div class="progress-label" id="progressLabel">Uploading & processing…</div>
                                </div>

                                <div style="margin-top: 1.25rem;">
                                    <button type="submit" class="btn-import" id="importBtn" disabled>
                                        <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                                        Start Import
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Supported Types -->
                        <div class="panel">
                            <p class="section-label">Supported data types</p>
                            <div class="data-grid">
                                <?php
                                $types = ['Ticket Bookings','Ticket Refunds','Date Changes','Ticket Weights','Reservations','Visa Applications','Hotel Bookings','Families','Umrah Bookings'];
                                foreach ($types as $t): ?>
                                <div class="data-chip">
                                    <span class="data-chip__dot"></span>
                                    <?php echo $t; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="panel">
                            <p class="section-label">How it works</p>
                            <div class="steps">
                                <div class="step">
                                    <div class="step__num">1</div>
                                    <div class="step__text">Download the Excel template above — it includes all required sheets and column headers.</div>
                                </div>
                                <div class="step">
                                    <div class="step__num">2</div>
                                    <div class="step__text">Fill in your data. Use <strong>actual names</strong> for Supplier, Client, Account, and Family fields — the system resolves IDs automatically.</div>
                                </div>
                                <div class="step">
                                    <div class="step__num">3</div>
                                    <div class="step__text">Ensure dates are in <strong>YYYY-MM-DD</strong> format or standard Excel date format.</div>
                                </div>
                                <div class="step">
                                    <div class="step__num">4</div>
                                    <div class="step__text">Upload the file and review the import results. Any errors will be listed clearly.</div>
                                </div>
                            </div>

                            <div class="callout callout--info" style="margin-top:1.25rem;">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="margin-top:2px"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM9 9a1 1 0 0 0 0 2v3a1 1 0 0 0 1 1h1a1 1 0 1 0 0-2v-3a1 1 0 0 0-1-1H9z"/></svg>
                                <div>
                                    <strong>Name vs. ID:</strong> The database stores numeric IDs internally, but you should enter human-readable names in the template. If an entity doesn't exist yet, the system will create it automatically.
                                </div>
                            </div>
                        </div>

                    </div><!-- /.card -->

                </div><!-- /.page-shell -->

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
(function () {
    const zone       = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('excelFile');
    const importBtn  = document.getElementById('importBtn');
    const form       = document.getElementById('importForm');
    const progressWrap = document.getElementById('progressWrap');
    const progressBar  = document.getElementById('progressBar');
    const zoneTitle    = document.getElementById('zoneTitle');
    const zoneSub      = document.getElementById('zoneSubtitle');
    const zoneIcon     = document.getElementById('zoneIcon');

    // Drag-and-drop
    ['dragenter','dragover','dragleave','drop'].forEach(ev => {
        zone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
    });
    ['dragenter','dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('drag-active')));
    ['dragleave','drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('drag-active')));
    zone.addEventListener('drop', e => {
        const files = e.dataTransfer.files;
        if (files.length) { fileInput.files = files; onFileSelected(files[0]); }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) onFileSelected(fileInput.files[0]);
    });

    function onFileSelected(file) {
        const sizeMB = (file.size / 1024 / 1024).toFixed(1);
        zone.classList.add('has-file');
        zoneIcon.innerHTML = `<svg width="24" height="24" fill="none" stroke="#059669" stroke-width="1.75" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
        zoneTitle.textContent  = file.name;
        zoneSub.innerHTML      = `<span style="color:#059669;font-weight:500">${sizeMB} MB</span> &nbsp;·&nbsp; <a href="#" onclick="clearFile(event)" style="color:#2563eb;">Remove</a>`;
        importBtn.disabled = false;
    }

    window.clearFile = function(e) {
        if (e) e.preventDefault();
        fileInput.value = '';
        zone.classList.remove('has-file');
        zoneIcon.innerHTML = `<svg width="24" height="24" fill="none" stroke="#2563eb" stroke-width="1.75" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>`;
        zoneTitle.textContent = 'Drop your file here';
        zoneSub.textContent   = 'or click anywhere to browse your computer';
        importBtn.disabled = true;
    };

    form.addEventListener('submit', e => {
        e.preventDefault();

        Swal.fire({
            title: 'Start import?',
            html: 'This will import data from your Excel file.<br>Make sure you have a recent backup.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, import',
            cancelButtonText: 'Cancel',
            borderRadius: '12px',
        }).then(res => {
            if (!res.isConfirmed) return;

            progressWrap.style.display = 'block';
            progressBar.style.width = '30%';
            importBtn.disabled = true;
            importBtn.innerHTML = `<svg width="17" height="17" style="animation:spin .8s linear infinite" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-4.22-7.64"/></svg> Importing…`;

            const fd = new FormData();
            if (fileInput.files.length) fd.append('excel_file', fileInput.files[0]);

            fetch('?is_ajax=1', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(result => {
                    progressBar.style.width = '100%';
                    setTimeout(() => {
                        progressWrap.style.display = 'none';
                        if (result.success) {
                            let msg = `Processed <strong>${result.success_count}</strong> records successfully.`;
                            if (result.processed_sheets?.length) msg += `<br><small style="color:#6b7280">Sheets: ${result.processed_sheets.join(', ')}</small>`;
                            Swal.fire({ title: 'Import complete', html: msg, icon: 'success', confirmButtonColor: '#2563eb' }).then(() => location.reload());
                        } else {
                            let msg = `Processed <strong>${result.success_count}</strong> records.`;
                            if (result.errors?.length) {
                                msg += `<br><br><div style="text-align:left;font-size:.85rem;max-height:160px;overflow-y:auto;background:#f8f9fa;padding:.75rem;border-radius:8px">${result.errors.slice(0,10).join('<br>')}${result.errors.length>10?`<br>…and ${result.errors.length-10} more`:''}</div>`;
                            }
                            Swal.fire({ title: 'Import result', html: msg, icon: result.success_count > 0 ? 'warning' : 'error', confirmButtonColor: '#2563eb' }).then(() => location.reload());
                        }
                    }, 400);
                })
                .catch(() => {
                    progressWrap.style.display = 'none';
                    importBtn.disabled = false;
                    importBtn.innerHTML = `<svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg> Start Import`;
                    Swal.fire({ title: 'Import failed', text: 'An unexpected error occurred. Please try again.', icon: 'error', confirmButtonColor: '#2563eb' });
                });
        });
    });

    // CSS spin keyframe
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);
})();
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>