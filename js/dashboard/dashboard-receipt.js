$(document).ready(function() {
    // Handle modal trigger click
    $(document).on("click", ".approve-button", function() {
        // Get the notification ID from the button's data attributes
        var notificationId = $(this).data("id");

        // Pass the notification ID to the modal's hidden field
        $("#hiddenNotificationId").val(notificationId);

        // Show the modal
        $("#receiptModal").modal("show");
    });

    // Submit the receipt number and notification ID via AJAX
    $("#submitReceipt").on("click", function() {
        var receiptNumber = $("#receiptNumber").val();
        var remarks = $("#remarks").val();
        var notificationId = $("#hiddenNotificationId").val();
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Validate input
        if (!receiptNumber || !remarks) {
            showToast('warning', 'Please enter both receipt number and remarks.');
            return;
        }

        // Send the data to approve_notification.php
        $.ajax({
            url: "../api/dashboard/approve_notification.php", 
            type: "POST",
            data: {
                notification_id: notificationId,
                receipt_number: receiptNumber,
                remarks: remarks,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === "success") {
                    showToast('success', response.message || 'Notification approved successfully');
                    
                    // Close the modal
                    $("#receiptModal").modal("hide");
                    
                    // Remove the notification item from the UI
                    var notifItem = $('[data-id="' + notificationId + '"]').closest('.tl-item');
                    if (notifItem.length) {
                        notifItem.fadeOut(400, function() {
                            $(this).remove();
                            // Update the unread count
                            var currentCount = parseInt($('#unreadNotifCount').text()) || 0;
                            $('#unreadNotifCount').text(currentCount > 0 ? currentCount - 1 : 0);
                        });
                    }
                    
                    // Clear form fields
                    $("#receiptNumber").val('');
                    $("#remarks").val('');
                } else {
                    showToast('error', response.message || 'Failed to approve notification');
                }
            },
            error: function(xhr, status, error) {
                showToast('error', 'An error occurred while processing your request.');
            }
        });
    });
    
    // Simple toast notification function
    function showToast(type, message) {
        // Remove any existing toasts
        $('.toast-notification').remove();
        
        // Create toast element
        var toastClass = 'bg-' + (type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'warning'));
        var toastIcon = type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'alert-triangle');
        
        var toast = $('<div class="toast-notification ' + toastClass + '">' +
            '<i class="feather icon-' + toastIcon + ' mr-2"></i>' +
            '<span>' + message + '</span>' +
            '<button type="button" class="close ml-2 text-white">&times;</button>' +
            '</div>');
        
        // Add to body
        $('body').append(toast);
        
        // Show with animation
        setTimeout(function() {
            toast.addClass('show');
        }, 100);
        
        // Hide after 3 seconds
        setTimeout(function() {
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
        
        // Close on click
        toast.find('.close').on('click', function() {
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        });
    }
}); 
