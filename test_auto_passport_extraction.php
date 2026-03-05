<?php
/**
 * Auto Passport Photo Extraction Test
 * Test automatic photo extraction from Afghan passports
 */

require_once 'admin/security.php';
enforce_auth();

$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: login.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Passport Photo Extraction Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container-test {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .header {
            border-bottom: 3px solid #2ed8b6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            margin: 0;
        }

        .upload-box {
            border: 3px dashed #2ed8b6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px 0;
        }

        .upload-box:hover {
            background: #f0fdfb;
            border-color: #17d9b8;
        }

        .upload-box i {
            font-size: 60px;
            color: #2ed8b6;
            margin-bottom: 20px;
            display: block;
        }

        .upload-box h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #999;
            margin: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2ed8b6, #17d9b8);
            border: none;
            padding: 10px 25px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #25c9a6, #0ecaa8);
            transform: translateY(-2px);
        }

        .demo-box {
            background: #f9f9f9;
            border-left: 4px solid #2ed8b6;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }

        .demo-box strong {
            color: #2ed8b6;
        }

        .result-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            display: none;
        }

        .result-box.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .preview-img {
            max-width: 100%;
            max-height: 400px;
            margin-top: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .loading {
            display: none;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2ed8b6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container-test">
        <div class="header">
            <h1><i class="fas fa-camera"></i> Auto Passport Photo Extraction</h1>
            <p>Automatically extract photos from Afghan passport documents</p>
        </div>

        <!-- Upload Area -->
        <div class="upload-box" id="uploadZone">
            <i class="fas fa-image"></i>
            <h4>Upload Afghan Passport Image</h4>
            <p>Click or drag a passport image here</p>
            <small style="color: #999;">PNG or JPG • Up to 10MB</small>
        </div>

        <input type="file" id="fileInput" style="display: none;" accept="image/png,image/jpeg,image/jpg">

        <!-- Loading -->
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="text-align: center; color: #666;">Processing passport...</p>
        </div>

        <!-- Result -->
        <div class="result-box" id="resultBox">
            <div id="resultContent"></div>
        </div>

        <!-- How It Works -->
        <div class="demo-box" style="margin-top: 30px;">
            <strong><i class="fas fa-info-circle"></i> How It Works</strong>
            <p style="margin-top: 10px; margin-bottom: 0;">
                The system automatically detects the photo area in Afghan passports:
            </p>
            <ul style="margin-top: 10px; margin-bottom: 0;">
                <li>Photo detected in <strong>left-top portion</strong> of passport</li>
                <li>Automatically crops to <strong>standard dimensions</strong></li>
                <li>Resizes to <strong>max 400px width</strong></li>
                <li>Compresses as <strong>85% quality JPEG</strong></li>
                <li>Saves to database (if booking ID provided)</li>
            </ul>
        </div>

        <!-- Test Booking ID -->
        <div class="demo-box">
            <label><strong>Test with Booking ID:</strong></label>
            <div class="input-group mt-2">
                <input type="number" id="bookingId" class="form-control" placeholder="Enter booking ID (optional)" value="1">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="button" onclick="useBookingId()">
                        Set
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p class="text-muted"><small>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Unknown'); ?></strong></small></p>
            <a href="umrah.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Umrah
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="js/umrah/auto-passport-extractor.js"></script>

    <script>
        let currentBookingId = 1;

        // Setup upload zone
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');

        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.style.backgroundColor = '#e8f4f8';
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.style.backgroundColor = '';
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.style.backgroundColor = '';
            if (e.dataTransfer.files.length > 0) {
                extractPhoto(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                extractPhoto(e.target.files[0]);
            }
        });

        // Extract photo
        async function extractPhoto(file) {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('resultBox').style.display = 'none';

            try {
                const result = await autoPassportExtractor.extract(file, currentBookingId);

                if (result.success) {
                    showResult(result, false);
                } else {
                    showResult(result, true);
                }
            } catch (error) {
                showResult({
                    success: false,
                    message: error.message
                }, true);
            }

            document.getElementById('loading').style.display = 'none';
        }

        // Show result
        function showResult(result, isError) {
            const resultBox = document.getElementById('resultBox');
            const resultContent = document.getElementById('resultContent');

            let html = '';

            if (!isError) {
                html += '<p><i class="fas fa-check-circle" style="color: #28a745; margin-right: 10px;"></i><strong>Photo Extracted Successfully!</strong></p>';
                html += '<div style="margin-top: 15px;">';
                html += `<p><strong>File:</strong> ${result.filename}</p>`;
                html += `<p><strong>Size:</strong> ${result.width}x${result.height}px</p>`;
                html += `<p><strong>Path:</strong> ${result.photoPath}</p>`;
                if (result.detectedArea) {
                    html += `<p><strong>Detected Area:</strong> X:${result.detectedArea.x}px Y:${result.detectedArea.y}px W:${result.detectedArea.width}px H:${result.detectedArea.height}px</p>`;
                }
                html += '</div>';
                
                // Show preview if we have the path
                if (result.photoPath) {
                    html += `<img src="${result.photoPath}" class="preview-img" alt="Extracted Photo">`;
                }
            } else {
                resultBox.classList.add('error');
                html += `<i class="fas fa-times-circle" style="color: #dc3545; margin-right: 10px;"></i><strong>Error:</strong> ${result.message || 'Unknown error occurred'}`;
            }

            resultContent.innerHTML = html;
            resultBox.style.display = 'block';
            resultBox.classList.remove('error');
            if (isError) resultBox.classList.add('error');
        }

        // Set booking ID
        function useBookingId() {
            const input = document.getElementById('bookingId');
            currentBookingId = parseInt(input.value) || 1;
            alert(`Booking ID set to: ${currentBookingId}`);
        }

        // Setup extractor message
        console.log('Auto Passport Extractor loaded');
        console.log('Available functions:');
        console.log('- autoPassportExtractor.extract(file, bookingId)');
        console.log('- autoPassportExtractor.extractFromInput(elementId, bookingId)');
        console.log('- autoPassportExtractor.setupDropZone(zoneId, bookingId)');
    </script>
</body>
</html>
