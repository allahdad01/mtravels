<!-- Admin Footer -->
<div class="footer-wrapper">
    <div class="footer-content">
        <p class="m-0 text-center">
            &copy; <?php echo date('Y'); ?> <?php echo $settings['agency_name']; ?>. All rights reserved.
            <span class="ml-2">Developed by <a href="https://github.com/allahdad01" target="_blank">Allahdad Muhammadi</a></span>
        </p>
    </div>
</div>

<script>
    // Flag to identify admin users for detailed error messages
    var isAdminUser = true;
</script>
<link rel="stylesheet" href="../css/modal-scrollable.css">
<!-- Include AI Chatbot -->

<style>
    .footer-wrapper {
        padding: 15px 0;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        position: relative;
        z-index: 9;
        margin-top: 30px;
    }
    
    .footer-content {
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 0.85rem;
        color: #6c757d;
    }
    
    .footer-content a {
        color: #007bff;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .footer-content a:hover {
        text-decoration: underline;
        color: #0056b3;
    }
</style> 
<!-- [ Main Content ] end -->

<!-- Warning section start -->
<!-- Older IE warning message -->
<!--[if lt IE 10]>
<div class="ie-warning">
    <h1>Warning!!</h1>
    <p>You are using an outdated version of Internet Explorer, please upgrade <a href="http://www.google.com/intl/en/chrome/browser/" target="_blank">Chrome</a> or <a href="http://www.mozilla.com/en-US/firefox/new/" target="_blank">Firefox</a> browser.</p>
</div>
<![endif]-->
<!-- Warning section end -->

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom JS -->
<script>
// Legacy DataTables initialization removed - using PHP pagination instead

// Full screen toggle function
function toggleFullScreen() {
    if (!document.fullscreenElement && !document.mozFullScreenElement &&
        !document.webkitFullscreenElement && !document.msFullscreenElement) {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.documentElement.msRequestFullscreen) {
            document.documentElement.msRequestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
            document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
            document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }
}

// Global AJAX error handler
$(document).ajaxError(function(event, xhr, settings, thrownError) {
    console.error('AJAX Error:', thrownError);
    if (xhr.status === 403) {
        window.location.href = '../login.php';
    }
});

// Search functionality
$('#m-search').on('keyup', function() {
    var searchTerm = $(this).val().toLowerCase();

    // Search in tables
    $('.table tbody tr').each(function() {
        var rowText = $(this).text().toLowerCase();
        if (rowText.indexOf(searchTerm) === -1) {
            $(this).hide();
        } else {
            $(this).show();
        }
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    $('.alert').fadeOut('slow');
}, 5000);
</script>

</body>
</html>