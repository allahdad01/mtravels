<?php
// Simple test to verify tabs work
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Tab Test</h1>
        
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier-report" role="tab" aria-controls="supplier-report" aria-selected="true">
                    Supplier Report
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-report" role="tab" aria-controls="general-report" aria-selected="false">
                    General Report
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- SUPPLIER REPORT TAB -->
            <div class="tab-pane fade show active" id="supplier-report" role="tabpanel" aria-labelledby="supplier-tab">
                <p class="mt-3">This is the Supplier Report Tab</p>
            </div>

            <!-- GENERAL REPORT TAB -->
            <div class="tab-pane fade" id="general-report" role="tabpanel" aria-labelledby="general-tab">
                <p class="mt-3">This is the General Report Tab</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        console.log('Tab test loaded successfully');
    </script>
</body>
</html>
