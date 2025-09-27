$(document).ready(function() {
    // Constants
    const DEBOUNCE_DELAY = 300;
    const MIN_SEARCH_LENGTH = 3;
    const CSRF_TOKEN = $('input[name="csrf_token"]').val();
    
    // Translations object - populate this from PHP
    const translations = window.dateChangeTranslations || {
        loading: 'Loading',
        please_wait: 'Please wait',
        enter_search_criteria: 'Please enter search criteria',
        pnr: 'PNR',
        no_tickets_found: 'No tickets found',
        search_error: 'Search error occurred',
        search_failed: 'Search failed',
        select_this_ticket: 'Select this ticket',
        select: 'Select',
        saving: 'Saving',
        success: 'Success',
        date_change_saved_successfully: 'Date change saved successfully',
        error: 'Error',
        failed_to_save_date_change: 'Failed to save date change',
        ok: 'OK',
        save_date_change: 'Save Date Change'
    };
    
    // Cache DOM elements
    const $modal = $('#addDateChangeModal');
    const $form = $('#addDateChangeForm');
    const $searchPNR = $('#searchPNR');
    const $searchPassenger = $('#searchPassenger');
    const $searchResultsContainer = $('#searchResultsContainer');
    const $dateChangeDetailsContainer = $('#dateChangeDetailsContainer');
    const $saveDateChangeBtn = $('#saveDateChangeBtn');
    const $modalAlertContainer = $('#modalAlertContainer');
    
    // Helper Functions
    function showAlert(message, type = 'danger') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        $modalAlertContainer.html(alertHtml);
    }

    function showLoading(container) {
        container.html(`
            <div class="text-center p-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">${translations.loading}...</span>
                </div>
                <p class="mt-2 mb-0">${translations.please_wait}...</p>
            </div>
        `);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Search functionality
    function searchTickets(params) {
        if (!params.pnr && !params.passenger) {
            showAlert(translations.enter_search_criteria);
            return;
        }

        $searchResultsContainer.show();
        showLoading($('#searchResultsTable tbody'));

        $.ajax({
            url: 'ajax/search_tickets.php',
            type: 'GET',
            data: {
                ...params,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(response) {
                console.log('Search response:', response);
                
                if (response.success) {
                    const tbody = $('#searchResultsTable tbody');
                    tbody.empty();

                    if (response.tickets && response.tickets.length > 0) {
                        response.tickets.forEach(ticket => {
                            const row = `
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light-primary">
                                                <span>${(ticket.passenger_name || 'N').charAt(0).toUpperCase()}</span>
                                            </div>
                                            <div class="ml-3">
                                                <h6 class="mb-0">${escapeHtml(ticket.passenger_name)}</h6>
                                                <small class="text-muted">
                                                    ${translations.pnr}: ${escapeHtml(ticket.pnr)}
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>${escapeHtml(ticket.pnr)}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="font-weight-bold">${escapeHtml(ticket.airline || 'N/A')}</span>
                                            <small>${escapeHtml(ticket.origin || 'N/A')} - ${escapeHtml(ticket.destination || 'N/A')}</small>
                                        </div>
                                    </td>
                                    <td>${formatDate(ticket.departure_date)}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary select-ticket" 
                                                data-ticket-id="${ticket.id}"
                                                data-toggle="tooltip"
                                                data-price="${ticket.price || 0}"
                                                data-sold="${ticket.sold || 0}"
                                                data-currency="${ticket.currency || 'USD'}"
                                                title="${translations.select_this_ticket}">
                                            <i class="feather icon-check mr-1"></i>${translations.select}
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });

                        // Initialize tooltips
                        $('[data-toggle="tooltip"]').tooltip();
                    } else {
                        tbody.html(`
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="feather icon-info mr-1"></i>
                                    ${translations.no_tickets_found}
                                </td>
                            </tr>
                        `);
                    }
                } else {
                    showAlert(response.message || translations.search_error);
                    $('#searchResultsTable tbody').html(`
                        <tr>
                            <td colspan="5" class="text-center text-danger">
                                <i class="feather icon-alert-circle mr-1"></i>
                                ${escapeHtml(response.message || translations.search_error)}
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Search Error:', {xhr, status, error});
                showAlert(translations.search_failed);
                $('#searchResultsTable tbody').html(`
                    <tr>
                        <td colspan="5" class="text-center text-danger">
                            <i class="feather icon-alert-circle mr-1"></i>
                            ${translations.search_failed}
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Event Handlers
    const debouncedSearch = debounce((params) => searchTickets(params), DEBOUNCE_DELAY);

    $searchPNR.on('input', function() {
        const pnr = $(this).val().trim().toUpperCase();
        if (pnr.length >= MIN_SEARCH_LENGTH) {
            debouncedSearch({ pnr });
        } else if (pnr.length === 0) {
            // Clear results when input is empty
            $searchResultsContainer.hide();
        }
    });

    $searchPassenger.on('input', function() {
        const passenger = $(this).val().trim();
        if (passenger.length >= MIN_SEARCH_LENGTH) {
            debouncedSearch({ passenger });
        } else if (passenger.length === 0) {
            // Clear results when input is empty
            $searchResultsContainer.hide();
        }
    });

    $('#searchPNRBtn').on('click', function() {
        const pnr = $searchPNR.val().trim();
        if (pnr) {
            searchTickets({ pnr });
        }
    });

    $('#searchPassengerBtn').on('click', function() {
        const passenger = $searchPassenger.val().trim();
        if (passenger) {
            searchTickets({ passenger });
        }
    });

    // Select ticket handler
    $(document).on('click', '.select-ticket', function() {
        const $btn = $(this);
        const ticketId = $btn.data('ticket-id');
        const price = $btn.data('price') || 0;
        const sold = $btn.data('sold') || 0;
        const currency = $btn.data('currency') || 'USD';
        
        // Clear any previous alerts
        $modalAlertContainer.empty();
        
        // Set form values - using correct field names from your HTML
        $('#selectedTicketId').val(ticketId);
        $('#base').val(price);
        $('#sold').val(sold);
        
        // Set default values for other required fields
        $('#supplier_penalty').val('0.00');
        $('#service_penalty').val('0.00');
        
        // Highlight selected ticket
        $('.select-ticket').removeClass('btn-success').addClass('btn-primary');
        $('.select-ticket').html(`<i class="feather icon-check mr-1"></i>${translations.select}`);
        $btn.removeClass('btn-primary').addClass('btn-success').html('<i class="feather icon-check mr-1"></i>Selected');
        
        // Show the date change details section
        $dateChangeDetailsContainer.show();
        $saveDateChangeBtn.show();
        
        // Set default exchange rate if currency is USD
        if (currency === 'USD') {
            $('#exchange_rate').val('1.0000');
        } else {
            // You might want to fetch the current exchange rate here
            $('#exchange_rate').val('70.0000'); // Default AFS rate
        }
        
        // Scroll to the details section
        $dateChangeDetailsContainer[0].scrollIntoView({ behavior: 'smooth' });
        
        showAlert(`Ticket selected for ${$btn.closest('tr').find('h6').text()}`, 'success');
    });

    // Form validation and submission
    $form.on('submit', function(e) {
        e.preventDefault();
        
        console.log('Form submission started');
        
        // Check if a ticket is selected
        if (!$('#selectedTicketId').val()) {
            showAlert('Please select a ticket first', 'warning');
            return;
        }
        
        // Basic form validation
        let isValid = true;
        const requiredFields = [
            { id: 'selectedTicketId', name: 'Ticket' },
            { id: 'departureDate', name: 'Departure Date' },
            { id: 'exchange_rate', name: 'Exchange Rate' },
            { id: 'supplier_penalty', name: 'Supplier Penalty' },
            { id: 'service_penalty', name: 'Service Penalty' },
            { id: 'base', name: 'Base Price' },
            { id: 'sold', name: 'Sold Price' },
            { id: 'description', name: 'Description' }
        ];
        
        const errors = [];
        
        requiredFields.forEach(field => {
            const $field = $(`#${field.id}`);
            const fieldValue = $field.val();
            
            if (!fieldValue || (fieldValue && fieldValue.trim() === '')) {
                $field.addClass('is-invalid').removeClass('is-valid');
                errors.push(field.name);
                isValid = false;
            } else {
                $field.removeClass('is-invalid').addClass('is-valid');
            }
        });
        
        // Additional validation for numeric fields
        const numericFields = ['exchange_rate', 'supplier_penalty', 'service_penalty', 'base', 'sold'];
        numericFields.forEach(fieldId => {
            const $field = $(`#${fieldId}`);
            const value = parseFloat($field.val());
            
            if (isNaN(value) || value < 0) {
                $field.addClass('is-invalid').removeClass('is-valid');
                if (!errors.includes($field.prev('label').text())) {
                    errors.push($field.prev('label').text() + ' must be a valid positive number');
                }
                isValid = false;
            }
        });
        
        // Date validation
        const departureDate = $('#departureDate').val();
        if (departureDate) {
            const selectedDate = new Date(departureDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                $('#departureDate').addClass('is-invalid').removeClass('is-valid');
                errors.push('Departure date must be in the future');
                isValid = false;
            }
        }
        
        if (!isValid) {
            showAlert('Please fix the following errors: ' + errors.join(', '), 'warning');
            return;
        }

        // Clear previous validation classes
        $(this).removeClass('was-validated');
        
        const formData = new FormData(this);
        
        // Add debug logging
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        
        // Disable submit button and show loading state
        const originalButtonHtml = $saveDateChangeBtn.html();
        $saveDateChangeBtn.prop('disabled', true)
            .html(`<span class="spinner-border spinner-border-sm mr-2"></span>${translations.saving}...`);

        $.ajax({
            url: 'insert_ticket_record_dc.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Server response:', response);
                
                // Handle different response types
                let responseText = typeof response === 'string' ? response.trim() : '';
                
                if (responseText === 'success') {
                    // Show success message with SweetAlert2 if available, otherwise use alert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: translations.success + '!',
                            text: translations.date_change_saved_successfully,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        showAlert(translations.date_change_saved_successfully, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                } else if (responseText.includes('error:')) {
                    const errorMsg = responseText.replace('error:', '').trim();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: translations.error,
                            text: errorMsg,
                            confirmButtonText: translations.ok
                        });
                    } else {
                        showAlert(errorMsg, 'danger');
                    }
                } else {
                    // Try to parse as JSON in case it's a structured response
                    try {
                        const jsonResponse = JSON.parse(responseText);
                        if (jsonResponse.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: translations.success + '!',
                                    text: jsonResponse.message || translations.date_change_saved_successfully,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                showAlert(jsonResponse.message || translations.date_change_saved_successfully, 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: translations.error,
                                    text: jsonResponse.message || translations.failed_to_save_date_change,
                                    confirmButtonText: translations.ok
                                });
                            } else {
                                showAlert(jsonResponse.message || translations.failed_to_save_date_change, 'danger');
                            }
                        }
                    } catch (e) {
                        // Not JSON, treat as plain text error
                        console.error('Unexpected response format:', responseText);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: translations.error,
                                text: translations.failed_to_save_date_change,
                                confirmButtonText: translations.ok
                            });
                        } else {
                            showAlert(translations.failed_to_save_date_change + ': ' + responseText, 'danger');
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax Error:', {xhr, status, error});
                console.error('Response Text:', xhr.responseText);
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: translations.error,
                        text: translations.failed_to_save_date_change + ' (Status: ' + xhr.status + ')',
                        confirmButtonText: translations.ok
                    });
                } else {
                    showAlert(translations.failed_to_save_date_change + ' (Status: ' + xhr.status + ')', 'danger');
                }
            },
            complete: function() {
                // Re-enable submit button and restore original text
                $saveDateChangeBtn.prop('disabled', false).html(originalButtonHtml);
            }
        });
    });

    // Reset form when modal is closed
    $modal.on('hidden.bs.modal', function() {
        $form[0].reset();
        $form.removeClass('was-validated');
        $('.form-control').removeClass('is-valid is-invalid');
        $searchResultsContainer.hide();
        $dateChangeDetailsContainer.hide();
        $saveDateChangeBtn.hide();
        $('#selectedTicketId').val('');
        $modalAlertContainer.empty();
        
        // Reset button states
        $('.select-ticket').removeClass('btn-success').addClass('btn-primary').html(`<i class="feather icon-check mr-1"></i>${translations.select}`);
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Alt + N to open new date change modal
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            $modal.modal('show');
        }
        
        // Escape to close modal
        if (e.key === 'Escape' && $modal.is(':visible')) {
            $modal.modal('hide');
        }
    });

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
});