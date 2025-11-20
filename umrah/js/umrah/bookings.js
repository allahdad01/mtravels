// Use event delegation to prevent duplicate event handlers
$(document).off('submit', '#umrahForm').on('submit', '#umrahForm', function(event) {
    event.preventDefault();
    console.log("Form inside modal submitted!");

    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Booking...';

    let formData = new FormData(event.target);

    fetch("add_umrah.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
        console.log("Server Response:", data);
        if (data.success) {
            alert("Umrah record added successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "Failed to add record"));
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
    submitButton.html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving Changes...');

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
                submitButton.html('Save changes');
            }
        },
        error: function(xhr, status, error) {
            alert("an_error_occurred");
            console.error("AJAX Error:", status, error, xhr.responseText);
            submitButton.prop('disabled', false);
            submitButton.html('Save changes');
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
        submitBtn.html('<i class="feather icon-loader"></i> Adding...');
        
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
                        alert('Transaction added successfully');
                        $('#addTransactionForm').collapse('hide');
                        $('#umrahTransactionForm')[0].reset();
                        fetchTransactions(umrahId, parseFloat($('#totalAmount').text().replace('$', '')));
                    } else {
                        alert('error: ' + (result.message || 'Failed to add transaction'));
                        submitBtn.prop('disabled', false);
                        submitBtn.html(originalHtml);
                    }
                } catch (e) {
                    console.error('Error processing response:', e);
                    alert('Error processing the request');
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error adding transaction');
                submitBtn.prop('disabled', false);
                submitBtn.html(originalHtml);
            }
        });
    });
    
});

// Modify the createFamilyForm submission to use jQuery's off().on() pattern
function submitCreateFamilyForm() {
    var formData = new FormData(document.getElementById("createFamilyForm"));
    
    const submitBtn = document.querySelector('#createFamilyForm button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="feather icon-loader"></i> Creating...';
    
    fetch('create_family.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if(data.success) {
                alert("Family created successfully");
              location.reload();
          } else {
              alert("error_creating_family");
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalHtml;
          }
      })
      .catch(error => {
          console.error("Error:", error);
          alert("An error occurred while creating the family");
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
    if (confirm('Are you sure you want to delete this family')) {
        const deleteBtn = event.target.closest('button');
        if (deleteBtn) {
            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="feather icon-loader"></i> Deleting...';
        }
        
        fetch('delete_family.php', {
            method: 'POST',
            body: JSON.stringify({ family_id: familyId }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Family deleted successfully');
                location.reload();
            } else {
                alert('Error deleting family: ' + data.message);
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalHtml;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the family');
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        });
    }
}

function deleteBooking(bookingId) {
    if (!confirm("Are you sure you want to delete this booking")) {
        return;
    }

    const deleteBtn = event.target.closest('button');
    if (deleteBtn) {
        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="feather icon-loader"></i> Deleting...';
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
            alert("Booking deleted successfully");
            location.reload();
        } else {
            alert("error: " + (data.message || "Failed to delete booking"));
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("An error occurred");
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        }
    });
}

function deleteTransaction(transactionId) {
    if (confirm('Are you sure you want to delete this transaction')) {
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
                alert('Transaction deleted successfully');
                const umrahId = document.getElementById('transactionUmrahId').textContent;
                const soldAmount = parseFloat(document.getElementById('totalAmount').textContent.replace('$', ''));
                fetchTransactions(umrahId, soldAmount);
            } else {
                alert('Error deleting transaction: ' + data.message);
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        })
        .catch(error => {
            console.error('Error deleting transaction:', error);
            alert('An error occurred while deleting the transaction');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        });
    }
}