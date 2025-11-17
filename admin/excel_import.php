<?php
// Excel Import UI for Tenant Data Migration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/db.php';
require_once 'excel_import_handler.php';
// Include security module
require_once 'security.php';
// Enforce authentication
enforce_auth();

$isAjax = isset($_GET['is_ajax']) && $_GET['is_ajax'] === '1';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['Tenant ID not found in session. Please log in again.'], 'success_count' => 0, 'processed_sheets' => []]);
        exit;
    }
    $message = "Error: Tenant ID not found in session. Please log in again.";
    $messageType = 'error';
    // Don't process further
    return;
}

if (!$isAjax) {
    include '../includes/header.php';
}
// Handle file upload and processing
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }

        // Check file type
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Please upload a valid Excel file (.xlsx or .xls)');
        }

        // Check file size (50MB limit)
        if ($file['size'] > 52428800) {
            throw new Exception('File size must be less than 50MB');
        }

        // Process the import
        $importHandler = new ExcelImportHandler($tenant_id);
        $result = $importHandler->importFromExcel($file['tmp_name']);

        if ($isAjax) {
            // Return JSON for AJAX requests
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        if ($result['success']) {
            $message = "Import completed successfully! Processed {$result['success_count']} records.";
            if (!empty($result['processed_sheets'])) {
                $message .= "<br>Processed sheets: " . implode(', ', $result['processed_sheets']);
            }
            $messageType = 'success';
        } else {
            $message = "Import completed with errors. Processed {$result['success_count']} records.";
            if (!empty($result['errors'])) {
                $message .= "<br><br>Errors:<br>" . implode('<br>', array_slice($result['errors'], 0, 10));
                if (count($result['errors']) > 10) {
                    $message .= "<br>... and " . (count($result['errors']) - 10) . " more errors";
                }
            }
            if (!empty($result['processed_sheets'])) {
                $message .= "<br>Processed sheets: " . implode(', ', $result['processed_sheets']);
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
    <?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Data Import - Travel Agency System</title>

    <link href="../assets/plugins/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <style>
        /* Apply gradient background to card headers matching the sidebar */
        .card-header {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
            color: #ffffff !important;
            border-bottom: none !important;
        }

        .card-header h5 {
            color: #ffffff !important;
            margin-bottom: 0 !important;
        }

        .card-header .card-header-right {
            color: #ffffff !important;
        }

        .card-header .card-header-right .btn {
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
        }

        .card-header .card-header-right .btn:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: rgba(255, 255, 255, 0.5) !important;
        }

        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        .upload-area:hover {
            border-color: #0056b3;
            background: #e3f2fd;
        }
        .upload-area.dragover {
            border-color: #28a745;
            background: #d4edda;
        }
        .template-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .feature-list h5 {
            color: #495057;
            margin-bottom: 15px;
        }
        .feature-list ul {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 5px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .feature-list li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }

        /* Modern Card Styling */
        .custom-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .custom-card:hover {
            transform: translateY(-5px);
        }

        .card-header.bg-gradient {
            background: linear-gradient(45deg, #4776E6, #8E54E9);
            border-radius: 15px 15px 0 0;
            padding: 1.5rem 1.5rem !important;
            position: relative;
            overflow: hidden;
        }

        .card-header.bg-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .icon-wrapper {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }

        .icon-wrapper i {
            font-size: 1.5rem;
        }

        .header-decoration {
            opacity: 0.6;
            transform: rotate(15deg);
        }

        .header-decoration i {
            font-size: 3rem;
        }

        /* Form Sections */
        .form-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-section:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transform: translateY(-1px);
        }

        .section-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.75rem;
        }

        .section-header h6 {
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Enhanced Form Controls */
        .form-select-lg, .form-control-lg {
            border-radius: 10px !important;
            border: 2px solid #e0e6ed !important;
            font-size: 1rem !important;
            padding: 0.875rem 1.25rem !important;
            transition: all 0.3s ease !important;
            background-color: #fff !important;
        }

        .form-select-lg:focus, .form-control-lg:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15) !important;
            transform: translateY(-1px);
        }

        /* Enhanced Button */
        .custom-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            position: relative !important;
            overflow: hidden !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
        }

        .custom-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
        }

        .btn-hover-effect {
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent) !important;
            transition: left 0.5s ease !important;
        }

        .custom-btn:hover .btn-hover-effect {
            left: 100% !important;
        }

        /* Enhanced Alerts */
        .alert {
            border-radius: 10px !important;
            border: none !important;
            font-size: 0.95rem !important;
        }

        .alert i {
            font-size: 1rem;
        }
    </style>
</head>
<body>


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
                                        <h5 class="m-b-10">Excel Data Import</h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item">Excel Import</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->

                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">

                                    <div class="card custom-card shadow-lg">
                                        <div class="card-header overflow-hidden">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-wrapper me-3">
                                                        <i class="feather icon-upload-cloud text-white"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-0 text-white fw-bold">
                                                            Excel Data Import
                                                        </h5>
                                                        <small class="text-white-50 mb-0">
                                                            Import tenant data from Excel spreadsheets
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="header-decoration">
                                                    <i class="feather icon-file-text text-white opacity-25"></i>
                                                </div>
                                            </div>
                                            <div class="header-pattern"></div>
                                        </div>
                                        <div class="card-body p-4 p-lg-5">
                                            <!-- Template Download Section -->
                                            <div class="template-download">
                                                <div class="row align-items-center">
                                                    <div class="col-md-8">
                                                        <h5 class="mb-1">Download Excel Template</h5>
                                                        <p class="mb-0">Get the properly formatted Excel template with all required sheets and columns</p>
                                                    </div>
                                                    <div class="col-md-4 text-right">
                                                        <a href="generate_excel_template.php" class="btn btn-light btn-lg">
                                                            <i class="fas fa-download"></i> Download Template
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Features Section -->
                                            <div class="feature-list">
                                                <h5>Supported Data Types</h5>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <ul>
                                                            <li>Ticket Bookings</li>
                                                            <li>Ticket Refunds</li>
                                                            <li>Ticket Date Changes</li>
                                                            <li>Ticket Weights</li>
                                                            <li>Ticket Reservations</li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <ul>
                                                            <li>Visa Applications</li>
                                                            <li>Hotel Bookings</li>
                                                            <li>Families</li>
                                                            <li>Umrah Bookings</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Message Display -->
                                            <?php if (!empty($message)): ?>
                                                <div id="importMessage" class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?> alert-dismissible fade show" role="alert">
                                                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                                                    <?php echo $message; ?>
                                                    <button type="button" class="close" data-dismiss="alert">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Upload Form -->
                                            <form id="importForm" method="POST" enctype="multipart/form-data">
                                                <div class="upload-area" id="uploadArea">
                                                    <div class="mb-3">
                                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                                        <h5>Drop Excel File Here</h5>
                                                        <p class="text-muted">or click to browse</p>
                                                    </div>
                                                    <input type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" style="display: none;" required>
                                                    <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('excelFile').click()">
                                                        <i class="fas fa-folder-open"></i> Choose File
                                                    </button>
                                                    <div class="mt-3">
                                                        <small class="text-muted">Supported formats: .xlsx, .xls (Max: 50MB)</small>
                                                    </div>
                                                </div>

                                                <div class="text-center mt-4">
                                                    <button type="submit" class="btn btn-success btn-lg" id="importBtn" disabled>
                                                        <i class="fas fa-upload"></i> Start Import
                                                    </button>
                                                </div>
                                            </form>

                                            <!-- Progress Bar (hidden initially) -->
                                            <div class="progress mt-4" id="progressContainer" style="display: none;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                            </div>

                                            <!-- Import Instructions -->
                                            <div class="mt-4">
                                                <h5>Import Instructions</h5>
                                                <ol class="text-muted">
                                                    <li>Download the Excel template using the button above</li>
                                                    <li>Fill in your data following the column structure in each sheet</li>
                                                    <li><strong>Important:</strong> For Supplier Name, Client Name, Account Name, Family Name - enter the actual NAMES, not IDs. The system will handle ID conversion automatically.</li>
                                                    <li>Ensure dates are in YYYY-MM-DD format or Excel date format</li>
                                                    <li>Upload the completed Excel file</li>
                                                    <li>Review the import results and any error messages</li>
                                                </ol>
                                                <div class="alert alert-info mt-3">
                                                    <i class="fas fa-info-circle"></i>
                                                    <strong>Name vs ID Handling:</strong> The database stores IDs internally, but you should enter human-readable names in the Excel template. The system will automatically find existing entities by name or create new ones if they don't exist.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


                             <!-- Required Js -->
                             <script src="../assets/js/vendor-all.min.js"></script>
   <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
   <script src="../assets/js/pcoded.min.js"></script>
   <script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

   <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('excelFile');
        const importBtn = document.getElementById('importBtn');
        const importForm = document.getElementById('importForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = progressContainer.querySelector('.progress-bar');

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.classList.add('dragover');
        }

        function unhighlight(e) {
            uploadArea.classList.remove('dragover');
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay(files[0]);
            }
        }

        // File selection handling
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                updateFileDisplay(this.files[0]);
            }
        });

        function updateFileDisplay(file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';

            uploadArea.innerHTML = `
                <div class="mb-3">
                    <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                    <h5>File Selected</h5>
                    <p class="mb-1"><strong>${fileName}</strong></p>
                    <p class="text-muted">Size: ${fileSize}</p>
                </div>
                <button type="button" class="btn btn-outline-secondary" onclick="clearFile()">
                    <i class="fas fa-times"></i> Remove
                </button>
            `;

            importBtn.disabled = false;
        }

        function clearFile() {
            fileInput.value = '';
            uploadArea.innerHTML = `
                <div class="mb-3">
                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                    <h5>Drop Excel File Here</h5>
                    <p class="text-muted">or click to browse</p>
                </div>
                <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('excelFile').click()">
                    <i class="fas fa-folder-open"></i> Choose File
                </button>
                <div class="mt-3">
                    <small class="text-muted">Supported formats: .xlsx, .xls (Max: 50MB)</small>
                </div>
            `;
            importBtn.disabled = true;
        }

        // Form submission handling
        importForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Show confirmation dialog
            Swal.fire({
                title: 'Start Import?',
                text: 'This will import data from the Excel file into your system. Make sure you have a backup.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Start Import',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show progress
                    progressContainer.style.display = 'block';
                    progressBar.style.width = '0%';
                    importBtn.disabled = true;
                    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';

                    // Create FormData and manually append the file
                    const formData = new FormData();
                    if (fileInput.files.length > 0) {
                        formData.append('excel_file', fileInput.files[0]);
                    }

                    // Send AJAX request
                    fetch('?is_ajax=1', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        // Update progress to 100%
                        progressBar.style.width = '100%';

                        if (result.success) {
                            let message = `Import completed successfully! Processed ${result.success_count} records.`;
                            if (result.processed_sheets && result.processed_sheets.length > 0) {
                                message += `<br>Processed sheets: ${result.processed_sheets.join(', ')}`;
                            }

                            Swal.fire({
                                title: 'Import Completed',
                                html: message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            let message = `Import completed with errors. Processed ${result.success_count} records.`;
                            if (result.errors && result.errors.length > 0) {
                                message += '<br><br>Errors:<br>' + result.errors.slice(0, 10).join('<br>');
                                if (result.errors.length > 10) {
                                    message += '<br>... and ' + (result.errors.length - 10) + ' more errors';
                                }
                            }
                            if (result.processed_sheets && result.processed_sheets.length > 0) {
                                message += '<br>Processed sheets: ' + result.processed_sheets.join(', ');
                            }

                            Swal.fire({
                                title: 'Import Result',
                                html: message,
                                icon: result.success_count > 0 ? 'warning' : 'error',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Import error:', error);
                        progressBar.style.width = '0%';
                        progressContainer.style.display = 'none';
                        importBtn.disabled = false;
                        importBtn.innerHTML = '<i class="fas fa-upload"></i> Start Import';

                        Swal.fire({
                            title: 'Import Failed',
                            text: 'An error occurred during import. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        });
    </script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>