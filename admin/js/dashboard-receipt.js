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

        // Validate input
        if (!receiptNumber && !remarks) {
            alert("Please enter a receipt number and remarks.");
            return;
        }

        // Send the data to approve_notification.php
        $.ajax({
            url: "approve_notification.php", 
            type: "POST",
            data: {
                notification_id: notificationId,  // Send notification ID
                receipt_number: receiptNumber,    // Send receipt number
                remarks: remarks
            },
            success: function(response) {
                // Parse the server response
                var data = JSON.parse(response);

                if (data.status === "success") {
                    alert(data.message);
                    location.reload(); // Refresh the page to show updated status
                } else {
                    alert(data.message || 'Failed to update notification status');
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                alert("An error occurred while processing your request.");
            }
        });

        // Close the modal after submission
        $("#receiptModal").modal("hide");
    });
}); 