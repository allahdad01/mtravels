$(document).ready(function() {
    // Handle Read button click with modern UI update
    $(document).on('click', '.read-button', function() {
        var notificationId = $(this).data('id');
        var button = $(this);
        var notificationCard = button.closest('.notification-card');
        
        $.ajax({
            url: 'update_notification_status.php',
            type: 'POST',
            data: {
                notification_id: notificationId,
                status: 'read'
            },
            dataType: 'json',
            success: function(response) {
                try {
                    if (typeof response === 'string') {
                        response = JSON.parse(response);
                    }
                    
                    if (response.success) {
                        // Fade out the notification with animation
                        notificationCard.fadeOut(400, function() {
                            $(this).remove();
                            
                            // Check if unread tab is empty
                            if ($('#unread .notification-card').length === 0) {
                                $('#unread .px-3').html('<div class="empty-state text-center py-4">' +
                                    '<i class="feather icon-bell-off text-muted" style="font-size: 48px;"></i>' +
                                    '<p class="text-muted mt-2">No unread notifications available</p>' +
                                    '</div>');
                            }
                            
                            // Update the notification count badge
                            var currentCount = parseInt($('.notification-count').text());
                            $('.notification-count').text(currentCount > 0 ? currentCount - 1 : 0);
                        });
                        
                        // Show success message with toast notification
                        showToast('success', response.message || 'Notification marked as read');
                    } else {
                        showToast('error', response.message || 'Failed to update notification status');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('error', 'Error processing response');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showToast('error', 'Error updating notification status');
            }
        });
    });
    
    // Handle Approve button click with modern UI update
    $(document).on('click', '.approve-button', function() {
        var notificationId = $(this).data('id');
        var amount = $(this).data('amount');
        var currency = $(this).data('currency');
        var type = $(this).data('type');
        
        // Open receipt modal with data
        $("#hiddenNotificationId").val(notificationId);
        $("#receiptModal").modal("show");
    });
    
    // Handle read notifications date filter with modern UI
    $('#applyReadDateFilter').on('click', function() {
        const selectedDate = $('#readNotificationsDate').val();
        if (!selectedDate) {
            showToast('warning', 'Please select a date');
            return;
        }
        
        // Show loading indicator
        $('#readNotificationsBody').html('<div class="d-flex justify-content-center py-5"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
        
        // Fetch filtered notifications
        $.ajax({
            url: 'ajax/get_filtered_notifications.php',
            type: 'POST',
            data: {
                date: selectedDate,
                status: 'read'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (response.html) {
                        $('#readNotificationsBody').html(response.html);
                    } else {
                        $('#readNotificationsBody').html('<div class="empty-state text-center py-4">' +
                            '<i class="feather icon-inbox text-muted" style="font-size: 48px;"></i>' +
                            '<p class="text-muted mt-2">No read notifications for selected date</p>' +
                            '</div>');
                    }
                } else {
                    $('#readNotificationsBody').html('<div class="alert alert-danger">' + (response.message || 'Error loading notifications') + '</div>');
                }
            },
            error: function() {
                $('#readNotificationsBody').html('<div class="alert alert-danger">Error loading notifications</div>');
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