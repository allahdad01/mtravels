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