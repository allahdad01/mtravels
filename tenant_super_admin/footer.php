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