// Use event delegation to prevent duplicate event handlers
$(document).off('submit', '#umrahForm').on('submit', '#umrahForm', function(event) {
    event.preventDefault();


    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Booking...';

    let formData = new FormData(event.target);

    fetch("../api/umrah/add_umrah.php", {
        method: "POST",
        body: formData,
    })
    .then(response => response.json())
    .then(data => {
         if (data.success) {
             showToast('success', 'Umrah record added successfully');
             const familyId = $('#familyId').val();
             event.target.reset();
             $('#umrahModal').modal('hide');
             setTimeout(() => {
                 // Reload the family members section
                 if (familyId && typeof loadFamilyMembers === 'function') {
                     loadFamilyMembers(familyId);
                 }
                 // Also refresh the main families table for updated counts
                 if (typeof refreshFamiliesTable === 'function') {
                     refreshFamiliesTable();
                 }
             }, 500);
         } else {
             showToast('error', data.message || 'Failed to add record');
             submitBtn.disabled = false;
             submitBtn.innerHTML = originalHtml;
         }
     })
     .catch(error => {
         showToast('error', 'An error occurred');
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
        url: '../api/umrah/update_family.php',
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
             if (response.status === "success") {
                 showToast('success', response.message);
                 $('#editFamilyModal').modal('hide');
                 setTimeout(() => {
                     refreshFamiliesTable();
                 }, 1000);
             } else {
                 showToast('error', response.message);
                 submitButton.prop('disabled', false);
                 submitButton.html('Save changes');
             }
         },
         error: function(xhr, status, error) {
             showToast('error', 'An error occurred');
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
            url: '../api/umrah/add_umrah_transaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                     const result = JSON.parse(response);
                     if (result.success) {
                         showToast('success', 'Transaction added successfully');
                         $('#addTransactionForm').collapse('hide');
                         $('#umrahTransactionForm')[0].reset();
                         fetchTransactions(umrahId, parseFloat($('#totalAmount').text().replace('$', '')));
                         setTimeout(() => {
                             refreshFamiliesTable();
                         }, 1000);
                     } else {
                         showToast('error', result.message || 'Failed to add transaction');
                         submitBtn.prop('disabled', false);
                         submitBtn.html(originalHtml);
                     }
                 } catch (e) {
                     showToast('error', 'Error processing the request');
                     submitBtn.prop('disabled', false);
                     submitBtn.html(originalHtml);
                 }
            },
            error: function(xhr, status, error) {

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
    
    fetch('../api/umrah/create_family.php', {
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
        
        fetch('../api/umrah/delete_family.php', {
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

    const deleteBtn = event.target.closest('a');
    if (deleteBtn) {
        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.style.pointerEvents = 'none';
        deleteBtn.style.opacity = '0.5';
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }

    fetch("../api/umrah/delete_booking.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-CSRF-Token": window.csrfToken || ''
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
                deleteBtn.style.pointerEvents = 'auto';
                deleteBtn.style.opacity = '1';
                deleteBtn.innerHTML = originalHtml;
            }
        }
    })
    .catch(error => {

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
        
        fetch('../api/umrah/delete_umrah_transaction.php', {
            method: 'POST',
            body: JSON.stringify({ 
                transaction_id: transactionId,
                csrf_token: window.csrfToken || ''
            }),
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

            alert('An error occurred while deleting the transaction');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        });
    }
}
