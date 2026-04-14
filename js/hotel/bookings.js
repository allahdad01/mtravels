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
        url: '../api/hotel/fetch_suppliers.php',
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
                        url: '../api/hotel/fetch_supplier_by_id.php',
                        type: 'GET',
                        data: { id: supplierId },
                        dataType: 'json',
                        success: function(supplier) {
                            if (supplier && supplier.currency) {
                                $('#currency').val(supplier.currency);
                            }
                        },
                        error: function() {

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
        url: '../api/hotel/fetch_clients.php',
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
        url: '../api/hotel/fetch_main_accounts.php',
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
    const submitButton = $('#addBookingModal button[data-submit]')[0];

    // Disable button immediately to prevent multiple clicks
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Adding Booking...';
    }

    if (!formData.get('title') || !formData.get('first_name') || !formData.get('last_name')) {
        showToast('Please fill in all required fields', 'error');
        // Re-enable button on validation error
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="feather icon-check mr-2"></i>Add Booking';
        }
        return;
    }

    $.ajax({
        url: '../api/hotel/add_hotel_booking.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;

                if (result.success) {
                    $('#addBookingModal').modal('hide');
                    showToast(result.message || 'Hotel booking added successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(result.message || 'Failed to add hotel booking', 'error');
                    // Re-enable button on error
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<i class="feather icon-check mr-2"></i>Add Booking';
                    }
                }
            } catch (e) {

                showToast('An unexpected error occurred', 'error');
                // Re-enable button on error
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="feather icon-check mr-2"></i>Add Booking';
                }
            }
        },
        error: function(xhr, status, error) {

            showToast('Failed to add hotel booking', 'error');
            // Re-enable button on error
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="feather icon-check mr-2"></i>Add Booking';
            }
        }
    });
}

// Delete hotel booking
function deleteBooking(id) {
    if (!id) {

        return;
    }

    if (confirm('Are you sure you want to delete this booking?')) {
        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                         document.querySelector('input[name="csrf_token"]')?.value;
        
        $.ajax({
            url: '../api/hotel/delete_hotel_booking.php',
            type: 'POST',
            data: JSON.stringify({ id: id, csrf_token: csrfToken }),
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

        return;
    }

    $.ajax({
        url: '../api/hotel/get_hotel_bookings.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.bookings && response.bookings.length > 0) {
                const booking = response.bookings[0];

                const guestName = `${booking.title || ''} ${booking.first_name || ''} ${booking.last_name || ''}`.trim() || 'N/A';
                const orderId   = booking.order_id || 'N/A';
                const contact   = booking.contact_no || 'N/A';
                const checkIn   = formatDate(booking.check_in_date);
                const checkOut  = formatDate(booking.check_out_date);
                const issueDate = formatDate(booking.issue_date);

                const supplier  = booking.supplier_name || 'N/A';
                const client    = booking.client_name || 'N/A';
                const paidTo    = booking.paid_to_name || 'N/A';
                const currency  = booking.currency || '';

                const baseAmount  = booking.base_amount  !== null && booking.base_amount  !== undefined && booking.base_amount  !== '' ? parseFloat(booking.base_amount).toFixed(2)  : '0.00';
                const soldAmount  = booking.sold_amount  !== null && booking.sold_amount  !== undefined && booking.sold_amount  !== '' ? parseFloat(booking.sold_amount).toFixed(2)  : '0.00';
                const profit      = booking.profit       !== null && booking.profit       !== undefined && booking.profit       !== '' ? parseFloat(booking.profit).toFixed(2)      : '0.00';
                const exchange    = booking.exchange_rate || 'N/A';

                const accommodation = booking.accommodation_details || 'N/A';
                const remarks       = booking.remarks || 'No remarks';

                $('#bookingDetails').html(`
                    <div class="hotel-details-modal">
                        <div class="hdm-header">
                            <div class="hdm-header-main">
                                <div class="hdm-guest-name">${guestName}</div>
                                <div class="hdm-order-pill">#${orderId}</div>
                            </div>
                            <div class="hdm-header-meta">
                                <div class="hdm-meta-item">
                                    <span class="hdm-meta-label">Check-in</span>
                                    <span class="hdm-meta-value">${checkIn}</span>
                                </div>
                                <div class="hdm-meta-item">
                                    <span class="hdm-meta-label">Check-out</span>
                                    <span class="hdm-meta-value">${checkOut}</span>
                                </div>
                                <div class="hdm-meta-item">
                                    <span class="hdm-meta-label">Issue Date</span>
                                    <span class="hdm-meta-value">${issueDate}</span>
                                </div>
                            </div>
                        </div>

                        <div class="hdm-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="hdm-section">
                                        <h6 class="hdm-section-title">Guest & Booking</h6>
                                        <dl class="hdm-definition-list">
                                            <div class="hdm-definition-row">
                                                <dt>Guest Name</dt>
                                                <dd>${guestName}</dd>
                                            </div>
                                            <div class="hdm-definition-row">
                                                <dt>Order ID</dt>
                                                <dd>${orderId}</dd>
                                            </div>
                                            <div class="hdm-definition-row">
                                                <dt>Contact</dt>
                                                <dd>${contact}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="hdm-section">
                                        <h6 class="hdm-section-title">Parties</h6>
                                        <dl class="hdm-definition-list">
                                            <div class="hdm-definition-row">
                                                <dt>Supplier</dt>
                                                <dd>${supplier}</dd>
                                            </div>
                                            <div class="hdm-definition-row">
                                                <dt>Client</dt>
                                                <dd>${client}</dd>
                                            </div>
                                            <div class="hdm-definition-row">
                                                <dt>Paid To</dt>
                                                <dd>${paidTo}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <div class="row hdm-row-spacing">
                                <div class="col-md-6">
                                    <div class="hdm-section">
                                        <h6 class="hdm-section-title">Financial Summary</h6>
                                        <div class="hdm-financial-grid">
                                            <div class="hdm-financial-item">
                                                <span class="hdm-financial-label">Base Amount</span>
                                                <span class="hdm-financial-value">${currency} ${baseAmount}</span>
                                            </div>
                                            <div class="hdm-financial-item">
                                                <span class="hdm-financial-label">Sold Amount</span>
                                                <span class="hdm-financial-value">${currency} ${soldAmount}</span>
                                            </div>
                                            <div class="hdm-financial-item">
                                                <span class="hdm-financial-label">Profit</span>
                                                <span class="hdm-financial-value hdm-profit">${currency} ${profit}</span>
                                            </div>
                                            <div class="hdm-financial-item">
                                                <span class="hdm-financial-label">Exchange Rate</span>
                                                <span class="hdm-financial-value">${exchange}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="hdm-section">
                                        <h6 class="hdm-section-title">Stay Details</h6>
                                        <p class="hdm-text-block">${accommodation}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="hdm-section hdm-section-full">
                                <h6 class="hdm-section-title">Remarks</h6>
                                <p class="hdm-text-block">${remarks}</p>
                            </div>
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


            showToast('Error fetching booking details');
        }
    });
};

// Edit booking
window.editBooking = function(id) {
    $.ajax({
        url: '../api/hotel/get_hotel_booking.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            try {
                const booking = JSON.parse(response);

                // Load dropdowns and then populate form
                $.ajax({
                    url: '../api/hotel/fetch_suppliers.php',
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
                            url: '../api/hotel/fetch_clients.php',
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
                                    url: '../api/hotel/fetch_main_accounts.php',
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

                                        // Add event listener for supplier change in edit modal
                                        $('#editBookingForm #supplier_id').off('change').on('change', function() {
                                            const supplierId = $(this).val();
                                            if (supplierId) {
                                                $.ajax({
                                                    url: '../api/hotel/fetch_supplier_by_id.php',
                                                    type: 'GET',
                                                    data: { id: supplierId },
                                                    dataType: 'json',
                                                    success: function(supplier) {
                                                        if (supplier && supplier.currency) {
                                                            $('#editBookingForm #edit_currency').val(supplier.currency);
                                                        }
                                                    },
                                                    error: function() {
                                                        console.error('Error fetching supplier currency');
                                                    }
                                                });
                                            } else {
                                                // Reset currency if no supplier selected
                                                $('#editBookingForm #edit_currency').val('');
                                            }
                                        });

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
        url: '../api/hotel/update_hotel_booking.php',
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

// Toast notifications are handled by the global showToast function
