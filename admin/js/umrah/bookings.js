// Use event delegation to prevent duplicate event handlers
$(document).off('submit', '#umrahForm').on('submit', '#umrahForm', function(event) {
    event.preventDefault();
    console.log("Form inside modal submitted!");

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="feather icon-loader"></i> adding...';

    let formData = new FormData(event.target);

    fetch("add_umrah.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        console.log("Server Response:", data);
        if (data.success) {
            alert("umrah_record_added_successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "failed_to_add_record"));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("an_error_occurred");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});

// Use .off() to remove any existing handlers before attaching new ones
$('#editFamilyForm').off('submit').on('submit', function(e) {
    e.preventDefault();

    let form = $(this);
    let submitButton = form.find('button[type="submit"]');
    submitButton.prop('disabled', true);
    submitButton.html('<i class="fa fa-spinner fa-spin"></i> saving...');

    $.ajax({
        url: 'update_family.php',
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === "success") {
                alert(response.message);
                location.reload();
            } else {
                alert("error: " + response.message);
                console.error("Update failed:", response);
                submitButton.prop('disabled', false);
                submitButton.html('save_changes');
            }
        },
        error: function(xhr, status, error) {
            alert("an_error_occurred");
            console.error("AJAX Error:", status, error, xhr.responseText);
            submitButton.prop('disabled', false);
            submitButton.html('save_changes');
        }
    });
});

// Replace the document.addEventListener with jQuery's one-time event binding
$(document).ready(function() {
    // Form submission handler with .off() to prevent duplicates
    $('#umrahTransactionForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true);
        submitBtn.html('<i class="feather icon-loader"></i> adding...');
        
        const formData = new FormData(this);
        const umrahId = $('#transactionUmrahIdInput').val();
        
        $.ajax({
            url: 'add_umrah_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        alert('transaction_added_successfully');
                        $('#addTransactionForm').collapse('hide');
                        $('#umrahTransactionForm')[0].reset();
                        fetchTransactions(umrahId, parseFloat($('#totalAmount').text().replace('$', '')));
                    } else {
                        alert('error: ' + (result.message || 'failed_to_add_transaction'));
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalHtml);
                    }
                } catch (e) {
                    console.error('Error processing response:', e);
                    alert('error_processing_the_request');
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('error_adding_transaction');
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);
            }
        });
    });
    
    // Use one() for the profile form to ensure it only runs once
    $('#updateProfileForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="feather icon-loader"></i> saving...';
        
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const currentPassword = document.getElementById('currentPassword').value;

        // Password validation logic
        if (newPassword || confirmPassword || currentPassword) {
            if (!currentPassword) {
                alert('please_enter_your_current_password');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
                return;
            }
            // Other password validations...
        }
        
        const formData = new FormData(this);
        
        fetch('update_client_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                location.reload();
            } else {
                alert(data.message || 'failed_to_update_profile');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('an_error_occurred_while_updating_the_profile');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        });
    });
});

// Modify the createFamilyForm submission to use jQuery's off().on() pattern
function submitCreateFamilyForm() {
    var formData = new FormData(document.getElementById("createFamilyForm"));
    
    const submitBtn = document.querySelector('#createFamilyForm button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="feather icon-loader"></i> creating...';
    
    fetch('create_family.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if(data.success) {
                alert("family_created_successfully");
              location.reload();
          } else {
              alert("error_creating_family");
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalHtml;
          }
      })
      .catch(error => {
          console.error("Error:", error);
          alert("an_error_occurred_while_creating_the_family");
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalHtml;
      });
    return false;
}

// Keep these functions as they are since they're called directly from HTML
function openEditFamilyModal(familyId, headOfFamily, contact,
     address, packageType, location, tazmin, visa_status, 
     province, district) {
    $('#editFamilyId').val(familyId);
    $('#editHeadOfFamily').val(headOfFamily);
    $('#editContact').val(contact);
    $('#editAddress').val(address);
    $('#editPackageType').val(packageType);
    $('#editLocation').val(location);
    $('#editTazmin').val(tazmin);
    $('#editStatus').val(visa_status).change();
    $('#editProvince').val(province);
    $('#editDistrict').val(district);
    $('#editFamilyModal').modal('show');
}

function deleteFamily(familyId) {
    if (confirm('are_you_sure_you_want_to_delete_this_family')) {
        const deleteBtn = event.target.closest('button');
        if (deleteBtn) {
            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="feather icon-loader"></i> deleting...';
        }
        
        fetch('delete_family.php', {
            method: 'POST',
            body: JSON.stringify({ family_id: familyId }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('family_deleted_successfully');
                location.reload();
            } else {
                alert('error_deleting_family: ' + data.message);
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalHtml;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('an_error_occurred_while_deleting_the_family');
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        });
    }
}

function deleteBooking(bookingId) {
    if (!confirm("are_you_sure_you_want_to_delete_this_booking")) {
        return;
    }

    const deleteBtn = event.target.closest('button');
    if (deleteBtn) {
        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="feather icon-loader"></i> deleting...';
    }

    fetch("delete_booking.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "booking_id=" + encodeURIComponent(bookingId),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("booking_deleted_successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "failed_to_delete_booking"));
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("an_error_occurred");
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        }
    });
}

function deleteTransaction(transactionId) {
    if (confirm('are_you_sure_you_want_to_delete_this_transaction')) {
        const deleteBtn = event.target.closest('button');
        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="feather icon-loader"></i>';
        
        fetch('delete_umrah_transaction.php', {
            method: 'POST',
            body: JSON.stringify({ transaction_id: transactionId }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('transaction_deleted_successfully');
                const umrahId = document.getElementById('transactionUmrahId').textContent;
                const soldAmount = parseFloat(document.getElementById('totalAmount').textContent.replace('$', ''));
                fetchTransactions(umrahId, soldAmount);
            } else {
                alert('error_deleting_transaction: ' + data.message);
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        })
        .catch(error => {
            console.error('Error deleting transaction:', error);
            alert('an_error_occurred_while_deleting_the_transaction');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        });
    }
}