<?php
/**
 * Test Page for Passport Photo Extraction
 * Simple testing interface for the photo extractor
 */

require_once 'admin/security.php';
enforce_auth();

// Check permission
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
    <title>Passport Photo Extraction Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/passport-photo-extractor.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .test-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .test-header {
            border-bottom: 2px solid #2ed8b6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .test-header h1 {
            color: #333;
            font-weight: 600;
        }
        
        .test-section {
            margin-bottom: 30px;
        }
        
        .test-section h4 {
            color: #2ed8b6;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .test-card {
            background: #f9f9f9;
            border-left: 4px solid #2ed8b6;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .test-card code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #2ed8b6, #17d9b8);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 216, 182, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .status-box {
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .api-response {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1><i class="fas fa-camera"></i> Passport Photo Extraction Test</h1>
            <p class="text-muted">Test the passport photo extraction functionality</p>
        </div>

        <!-- Status Section -->
        <div class="test-section">
            <h4><i class="fas fa-check-circle"></i> System Status</h4>
            
            <div id="statusArea">
                <p class="text-muted">Checking system status...</p>
            </div>
            
            <button class="quick-action-btn" onclick="checkStatus(); return false;" style="margin-top: 10px;">
                <i class="fas fa-sync"></i> Refresh Status
            </button>
            
            <button class="quick-action-btn" onclick="debugExtractor(); return false;" style="margin-top: 10px; background: #666;">
                <i class="fas fa-bug"></i> Debug (Check Console)
            </button>
        </div>

        <!-- Quick Test Section -->
        <div class="test-section">
            <h4><i class="fas fa-bolt"></i> Quick Test</h4>
            
            <div class="test-card">
                <p>Test the photo extractor without a specific booking ID.</p>
                <button class="quick-action-btn" onclick="testExtractor()">
                    <i class="fas fa-play"></i> Open Extractor
                </button>
            </div>
        </div>

        <!-- Interactive Test Section -->
        <div class="test-section">
            <h4><i class="fas fa-sliders-h"></i> Interactive Test</h4>
            
            <div class="test-card">
                <label>Test with Booking ID:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="number" id="testBookingId" class="form-control" 
                           placeholder="Enter booking ID (optional)" value="1">
                    <button class="quick-action-btn" onclick="testWithBookingId()">
                        <i class="fas fa-test"></i> Test
                    </button>
                </div>
            </div>
        </div>

        <!-- API Test Section -->
        <div class="test-section">
            <h4><i class="fas fa-code"></i> API Test</h4>
            
            <div class="test-card">
                <p>Test the API endpoint directly.</p>
                <button class="quick-action-btn" onclick="testAPI()">
                    <i class="fas fa-server"></i> Test API
                </button>
                <div id="apiResponse" style="margin-top: 15px; display: none;">
                    <p><strong>API Response:</strong></p>
                    <div class="api-response" id="apiResponseContent"></div>
                </div>
            </div>
        </div>

        <!-- Documentation Section -->
        <div class="test-section">
            <h4><i class="fas fa-book"></i> Documentation</h4>
            
            <div class="test-card">
                <p>Read the complete documentation:</p>
                <ul style="margin-bottom: 0;">
                    <li><a href="PASSPORT_PHOTO_QUICK_START.txt" target="_blank">
                        Quick Start Guide</a></li>
                    <li><a href="PASSPORT_PHOTO_EXTRACTION.md" target="_blank">
                        Complete Documentation</a></li>
                    <li><a href="PASSPORT_PHOTO_INTEGRATION_EXAMPLE.md" target="_blank">
                        Integration Examples</a></li>
                </ul>
            </div>
        </div>

        <!-- Usage Section -->
        <div class="test-section">
            <h4><i class="fas fa-question-circle"></i> How to Use</h4>
            
            <div class="test-card">
                <h5>Basic Usage:</h5>
                <code style="display: block; margin-top: 10px;">
window.passportPhotoExtractor.openFor(bookingId);
                </code>
            </div>

            <div class="test-card">
                <h5>Add Button to HTML:</h5>
                <code style="display: block; margin-top: 10px;">
&lt;button onclick="if(window.passportPhotoExtractor) 
                window.passportPhotoExtractor.openFor(bookingId);"&gt;
    Extract Photo
&lt;/button&gt;
                </code>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
            <p class="text-muted">
                <small>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Unknown'); ?></strong> 
                | Role: <strong><?php echo htmlspecialchars($_SESSION['role'] ?? 'Unknown'); ?></strong></small>
            </p>
            <a href="umrah.php" class="btn btn-sm btn-secondary mt-2">
                <i class="fas fa-arrow-left"></i> Back to Umrah
            </a>
        </div>
    </div>

    <!-- Required Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="js/umrah/passport-photo-extractor.js"></script>

    <script>
        // Check system status
        function checkStatus() {
            const statusArea = document.getElementById('statusArea');
            let html = '';
            
            // Check extractor loaded
            if (window.passportPhotoExtractor) {
                html += '<div class="status-box status-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>✓ Photo Extractor Loaded</strong>';
                html += '</div>';
            } else {
                html += '<div class="status-box status-error">';
                html += '<i class="fas fa-times-circle"></i> <strong>✗ Photo Extractor Not Loaded</strong>';
                html += '</div>';
            }
            
            // Check jQuery
            if (typeof jQuery !== 'undefined') {
                html += '<div class="status-box status-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>✓ jQuery Loaded</strong>';
                html += '</div>';
            }
            
            // Check SweetAlert
            if (typeof Swal !== 'undefined') {
                html += '<div class="status-box status-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>✓ SweetAlert Loaded</strong>';
                html += '</div>';
            }
            
            statusArea.innerHTML = html;
        }

        // Test extractor without booking ID
        function testExtractor() {
            if (!window.passportPhotoExtractor) {
                Swal.fire('Error', 'Photo extractor not loaded', 'error');
                return;
            }
            
            window.passportPhotoExtractor.resetState();
            window.passportPhotoExtractor.showStep(1);
            $('#photoExtractorModal').modal('show');
        }

        // Test with booking ID
        function testWithBookingId() {
            const bookingId = document.getElementById('testBookingId').value;
            
            if (!bookingId) {
                Swal.fire('Error', 'Please enter a booking ID', 'error');
                return;
            }
            
            if (!window.passportPhotoExtractor) {
                Swal.fire('Error', 'Photo extractor not loaded', 'error');
                return;
            }
            
            window.passportPhotoExtractor.openFor(parseInt(bookingId));
        }

        // Test API
        function testAPI() {
            // Create a simple test image
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 300;
            const ctx = canvas.getContext('2d');
            
            // Draw test pattern
            ctx.fillStyle = '#cccccc';
            ctx.fillRect(0, 0, 200, 300);
            ctx.fillStyle = '#333333';
            ctx.font = '14px Arial';
            ctx.fillText('Test Image', 50, 150);
            
            const imageData = canvas.toDataURL('image/jpeg');
            
            // Send to API
            fetch('api/umrah/extract_passport_photo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    image_data: imageData,
                    booking_id: 1,
                    crop_data: {
                        x: 0, y: 0, width: 200, height: 300
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                const responseDiv = document.getElementById('apiResponse');
                const content = document.getElementById('apiResponseContent');
                
                responseDiv.style.display = 'block';
                content.textContent = JSON.stringify(data, null, 2);
                
                if (data.success) {
                    Swal.fire('Success', 'API test successful', 'success');
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', error.message, 'error');
                console.error(error);
            });
        }

        // Run status check on load
        document.addEventListener('DOMContentLoaded', checkStatus);
        
        // Also check after delay for async initialization
        setTimeout(checkStatus, 500);
        setTimeout(checkStatus, 2000);
        
        // Debug helper
        window.debugExtractor = function() {
            console.log('=== Debug Info ===');
            console.log('passportPhotoExtractor:', window.passportPhotoExtractor);
            console.log('Modal exists:', !!document.getElementById('photoExtractorModal'));
            console.log('jQuery:', typeof jQuery !== 'undefined');
            console.log('Swal:', typeof Swal !== 'undefined');
            return 'Check console for details';
        };
    </script>
</body>
</html>
