/**
 * Booking Management Module
 */

// Calculate profit automatically
function calculateProfit() {
    const baseAmount = parseFloat($('input[name="base_amount"]').val()) || 0;
    const soldAmount = parseFloat($('input[name="sold_amount"]').val()) || 0;
    $('input[name="profit"]').val((soldAmount - baseAmount).toFixed(2));
}

// Populate dropdowns when add modal opens
function populateDropdowns() {
    // Fetch suppliers
    $.ajax({
        url: 'fetch_suppliers.php',
        type: 'GET',
        dataType: 'json',
        success: function(suppliers) {
            let options = '<option value="">Select Supplier</option>';
            suppliers.forEach(supplier => {
                options += `<option value="${supplier.id}">${supplier.name}</option>`;
            });
            $('select[name="supplier_id"]').html(options);

            // Add event listener for supplier change to auto-load currency
            $('select[name="supplier_id"]').on('change', function() {
                const supplierId = $(this).val();
                if (supplierId) {
                    $.ajax({
                        url: '../admin/fetch_supplier_by_id.php',
                        type: 'GET',
                        data: { id: supplierId },
                        dataType: 'json',
                        success: function(supplier) {
                            if (supplier && supplier.currency) {
                                $('#currency').val(supplier.currency);
                            }
                        },
                        error: function() {
                            console.error('Error fetching supplier currency');
                        }
                    });
                } else {
                    // Reset currency if no supplier selected
                    $('#currency').val('');
                }
            });
        }
    });

    // Fetch clients
    $.ajax({
        url: 'fetch_clients.php',
        type: 'GET',
        dataType: 'json',
        success: function(clients) {
            let options = '<option value="">Select Client</option>';
            clients.forEach(client => {
                options += `<option value="${client.id}">${client.name}</option>`;
            });
            $('select[name="sold_to"]').html(options);
        }
    });

    // Fetch main accounts
    $.ajax({
        url: 'fetch_main_accounts.php',
        type: 'GET',
        dataType: 'json',
        success: function(accounts) {
            let options = '<option value="">Select Account</option>';
            accounts.forEach(account => {
                options += `<option value="${account.id}">${account.name}</option>`;
            });
            $('select[name="paid_to"]').html(options);
        }
    });
}

// Add new hotel booking
function addHotelBookingForm() {
    const form = $('#addHotelBookingForm')[0];
    const formData = new FormData(form);
    
    if (!formData.get('title') || !formData.get('first_name') || !formData.get('last_name')) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please fill in all required fields'
            });
        } else {
            alert('Please fill in all required fields');
        }
        return;
    }

    $.ajax({
        url: 'add_hotel_booking.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    $('#addBookingModal').modal('hide');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: result.message || 'Hotel booking added successfully'
                        }).then((result) => {
                            if (result.isConfirmed || result.isDismissed) {
                                window.location.reload();
                            }
                        });
                    } else {
                        alert(result.message || 'Hotel booking added successfully');
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.message || 'Failed to add hotel booking'
                        });
                    } else {
                        alert(result.message || 'Failed to add hotel booking');
                    }
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred'
                    });
                } else {
                    alert('An unexpected error occurred');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to add hotel booking'
                });
            } else {
                alert('Failed to add hotel booking');
            }
        }
    });
}

// Delete hotel booking
function deleteBooking(id) {
    if (!id) {
        console.error('No booking ID provided');
        return;
    }

    if (confirm('Are you sure you want to delete this booking?')) {
        $.ajax({
            url: 'delete_hotel_booking.php',
            type: 'POST',
            data: JSON.stringify({ id: id }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Booking deleted successfully');
                    location.reload();
                } else {
                    showToast('Error deleting booking');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Error deleting booking');
            }
        });
    }
}

// Format date for display
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// View booking details
window.viewBooking = function(id) {
    if (!id) {
        console.error('No booking ID provided');
        return;
    }

    $.ajax({
        url: 'get_hotel_bookings.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.bookings && response.bookings.length > 0) {
                const booking = response.bookings[0];

                $('#bookingDetails').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Guest Name:</strong> ${booking.title} ${booking.first_name} ${booking.last_name}</p>
                            <p><strong>Order ID:</strong> ${booking.order_id || 'N/A'}</p>
                            <p><strong>Contact:</strong> ${booking.contact_no || 'N/A'}</p>
                            <p><strong>Check-in Date:</strong> ${booking.check_in_date}</p>
                            <p><strong>Check-out Date:</strong> ${booking.check_out_date}</p>
                            <p><strong>Issue Date:</strong> ${booking.issue_date}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Supplier:</strong> ${booking.supplier_name || 'N/A'}</p>
                            <p><strong>Client:</strong> ${booking.client_name || 'N/A'}</p>
                            <p><strong>Paid To:</strong> ${booking.paid_to_name || 'N/A'}</p>
                            <p><strong>Base Amount:</strong> ${booking.currency} ${parseFloat(booking.base_amount).toFixed(2)}</p>
                            <p><strong>Sold Amount:</strong> ${booking.currency} ${parseFloat(booking.sold_amount).toFixed(2)}</p>
                            <p><strong>Exchange Rate:</strong> ${booking.exchange_rate}</p>
                            <p><strong>Profit:</strong> ${booking.currency} ${parseFloat(booking.profit).toFixed(2)}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Accommodation Details:</strong></p>
                            <p>${booking.accommodation_details || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Remarks:</strong></p>
                            <p>${booking.remarks || 'No remarks'}</p>
                        </div>
                    </div>
                `);

                window.currentBookingId = id;
                $('#detailsModal').modal('show');
            } else {
                showToast('Booking not found');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Response:', xhr.responseText);
            showToast('Error fetching booking details');
        }
    });
};

// Edit booking
window.editBooking = function(id) {
    $.ajax({
        url: 'get_hotel_booking.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            try {
                const booking = JSON.parse(response);

                // Load dropdowns and then populate form
                $.ajax({
                    url: 'fetch_suppliers.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(suppliersResponse) {
                        let supplierOptions = '<option value="">Select Supplier</option>';
                        suppliersResponse.forEach(supplier => {
                            supplierOptions += `<option value="${supplier.id}">${supplier.name}</option>`;
                        });
                        $('#editBookingForm #supplier_id').html(supplierOptions);
                        $('#editBookingForm #supplier_id').val(booking.supplier_id);

                        $.ajax({
                            url: 'fetch_clients.php',
                            type: 'GET',
                            dataType: 'json',
                            success: function(clientsResponse) {
                                let clientOptions = '<option value="">Select Client</option>';
                                clientsResponse.forEach(client => {
                                    clientOptions += `<option value="${client.id}">${client.name}</option>`;
                                });
                                $('#editBookingForm #sold_to').html(clientOptions);
                                $('#editBookingForm #sold_to').val(booking.sold_to);

                                $.ajax({
                                    url: 'fetch_main_accounts.php',
                                    type: 'GET',
                                    dataType: 'json',
                                    success: function(accountsResponse) {
                                        let accountOptions = '<option value="">Select Account</option>';
                                        accountsResponse.forEach(account => {
                                            accountOptions += `<option value="${account.id}">${account.name}</option>`;
                                        });
                                        $('#editBookingForm #paid_to').html(accountOptions);
                                        $('#editBookingForm #paid_to').val(booking.paid_to);

                                        // Populate all other form fields
                                        $('#editBookingForm #edit_booking_id').val(booking.id);
                                        $('#editBookingForm #title').val(booking.title);
                                        $('#editBookingForm #first_name').val(booking.first_name);
                                        $('#editBookingForm #last_name').val(booking.last_name);
                                        $('#editBookingForm #gender').val(booking.gender);
                                        $('#editBookingForm #order_id').val(booking.order_id);
                                        $('#editBookingForm #check_in_date').val(booking.check_in_date);
                                        $('#editBookingForm #check_out_date').val(booking.check_out_date);
                                        $('#editBookingForm #accommodation_details').val(booking.accommodation_details);
                                        $('#editBookingForm #issue_date').val(booking.issue_date);
                                        $('#editBookingForm #contact_no').val(booking.contact_no);
                                        $('#editBookingForm #base_amount').val(booking.base_amount);
                                        $('#editBookingForm #sold_amount').val(booking.sold_amount);
                                        $('#editBookingForm #profit').val(booking.profit);
                                        $('#editBookingForm #currency').val(booking.currency);
                                        $('#editBookingForm [name="exchangeRate"]').val(booking.exchange_rate);
                                        $('#editBookingForm #remarks').val(booking.remarks);

                                        $('#editBookingModal').modal('show');
                                    },
                                    error: function() {
                                        showToast('Error loading account data');
                                    }
                                });
                            },
                            error: function() {
                                showToast('Error loading client data');
                            }
                        });
                    },
                    error: function() {
                        showToast('Error loading supplier data');
                    }
                });
            } catch (e) {
                console.error('Error parsing booking data:', e);
                showToast('Error loading booking details');
            }
        },
        error: function() {
            showToast('Error fetching booking details');
        }
    });

    // Add event listeners for amount calculations
    $('#editBookingForm #base_amount, #editBookingForm #sold_amount').on('input', function() {
        const baseAmount = parseFloat($('#editBookingForm #base_amount').val()) || 0;
        const soldAmount = parseFloat($('#editBookingForm #sold_amount').val()) || 0;
        $('#editBookingForm #profit').val((soldAmount - baseAmount).toFixed(2));
    });
};

// Submit edit form
function submitEditForm() {
    const formData = new FormData($('#editBookingForm')[0]);

    $.ajax({
        url: 'update_hotel_booking.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    showToast('Booking updated successfully');
                    $('#editBookingModal').modal('hide');
                    location.reload();
                } else {
                    showToast('Error updating booking');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                showToast('Error processing update request');
            }
        },
        error: function() {
            showToast('Error updating booking');
        }
    });
}

// Initialize event handlers when document is ready
$(document).ready(function() {
    // Modal event handlers
    $('#addBookingModal').on('show.bs.modal', function() {
        populateDropdowns();
        $('input[name="issue_date"]').val(new Date().toISOString().split('T')[0]);
    });

    // Form submission handlers
    $('#editBookingForm').on('submit', function(e) {
        e.preventDefault();
        submitEditForm();
    });

    // Amount calculation handlers
    $('input[name="base_amount"], input[name="sold_amount"]').on('input', calculateProfit);
});

// Global toast notification function
function showToast(message, type = 'success') {
    // Use SweetAlert2 if available, otherwise fallback to alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    } else {
        alert(message);
    }
}