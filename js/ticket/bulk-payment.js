/**
 * Bulk Ticket Payment Manager
 * Handles bulk payment modal, ticket selection, allocation, and submission.
 */
const bulkPaymentManager = {
    isSubmitting: false,
    tickets: [],
    selectedTicketIds: [],

    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    bindEvents: function() {
        // Open modal
        $(document).on('click', '#launchBulkPayment, #fabBulkPayment', function() {
            bulkPaymentManager.openModal();
        });

        // Select all / deselect all checkboxes
        $('#bulkSelectAllCheck').on('change', function() {
            const checked = this.checked;
            $('#bulkTicketTableBody input.ticket-check').prop('checked', checked);
            bulkPaymentManager.updateSelectedTickets();
        });

        $('#bulkSelectAll').on('click', function() {
            $('#bulkTicketTableBody input.ticket-check').prop('checked', true);
            bulkPaymentManager.updateSelectedTickets();
        });

        $('#bulkDeselectAll').on('click', function() {
            $('#bulkTicketTableBody input.ticket-check').prop('checked', false);
            bulkPaymentManager.updateSelectedTickets();
        });

        // Individual ticket checkbox
        $(document).on('change', '#bulkTicketTableBody input.ticket-check', function() {
            bulkPaymentManager.updateSelectedTickets();
        });

        // Allocation input change
        $(document).on('input', '.bulk-alloc-input', function() {
            bulkPaymentManager.updateAllocationTotal();
        });

        // Currency change
        $('#bulkPaymentCurrency').on('change', function() {
            bulkPaymentManager.toggleExchangeRate();
        });

        // Total amount change — auto-distribute across tickets
        $('#bulkPaymentTotalAmount').on('input', function() {
            bulkPaymentManager.autoDistribute();
        });

        // PNR search filter
        $('#bulkPnrSearch').on('input', function() {
            bulkPaymentManager.applyFilters();
        });

        // Issue date filters
        $('#bulkDateFrom, #bulkDateTo').on('change', function() {
            bulkPaymentManager.applyFilters();
        });

        // Clear filters
        $('#bulkClearFilters').on('click', function() {
            $('#bulkPnrSearch').val('');
            $('#bulkDateFrom').val('');
            $('#bulkDateTo').val('');
            bulkPaymentManager.applyFilters();
        });

        // Submit
        $('#bulkSubmitPayment').on('click', function() {
            bulkPaymentManager.submitPayment();
        });
    },

    setDefaultDateTime: function() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        $('#bulkPaymentDate').val(today);

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        $('#bulkPaymentTime').val(`${hours}:${minutes}:${seconds}`);
    },

    openModal: function() {
        // Reset state
        this.tickets = [];
        this.selectedTicketIds = [];
        $('#bulkTicketTableBody').html('<tr><td colspan="7" class="text-center text-muted"><i class="feather icon-loader"></i> Loading agency tickets...</td></tr>');
        $('#bulkAllocationBody').empty();
        $('#bulkPaymentTotalAmount').val('');
        $('#bulkPaymentDescription').val('');
        $('#bulkReceiptNumber').val('');
        $('#bulkExchangeRateField').hide();
        $('#bulkSubmitPayment').prop('disabled', true);
        $('#bulkAllocationValidation').text('');
        $('#bulkAllocationSection').hide();
        this.setDefaultDateTime();

        $('#bulkPaymentModal').modal({ backdrop: 'static', keyboard: false });

        // Auto-load agency tickets
        this.loadTickets();
    },

    loadTickets: function() {
        showToast('Loading agency tickets...', 'info');

        // Fetch all agency client tickets
        $.ajax({
            url: '../api/ticket/fetch_tickets_for_bulk_payment.php',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    bulkPaymentManager.tickets = response.tickets;
                    bulkPaymentManager.renderTicketTable();
                    $('#bulkTicketSection').show();
                    $('#bulkPaymentSection').show();
                    showToast(`Loaded ${response.tickets.length} tickets`, 'success');
                } else {
                    showToast(response.message || 'Failed to load tickets', 'error');
                }
            },
            error: function() {
                showToast('Error loading tickets', 'error');
            }
        });
    },

    renderTicketTable: function() {
        const tbody = $('#bulkTicketTableBody');
        tbody.empty();

        if (this.tickets.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center text-muted">No unpaid/partial tickets found for this client</td></tr>');
            $('#bulkTicketTableFoot').hide();
            return;
        }

        this.tickets.forEach(ticket => {
            const outstanding = Math.max(0, parseFloat(ticket.sold) - parseFloat(ticket.total_paid));
            const row = $(`
                <tr data-issue-date="${ticket.issue_date || ''}" data-departure-date="${ticket.departure_date || ''}">
                    <td class="text-center align-middle">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input ticket-check"
                                   id="bulkTicket-${ticket.id}"
                                   data-ticket-id="${ticket.id}"
                                   data-sold="${ticket.sold}"
                                   data-paid="${ticket.total_paid}"
                                   data-outstanding="${outstanding}"
                                   data-currency="${ticket.currency}"
                                   data-pnr="${ticket.pnr}"
                                   data-passenger="${ticket.title} ${ticket.passenger_name}">
                            <label class="custom-control-label" for="bulkTicket-${ticket.id}"></label>
                        </div>
                    </td>
                    <td>${escapeHtml(ticket.pnr)}</td>
                    <td>${escapeHtml(ticket.title)} ${escapeHtml(ticket.passenger_name)}</td>
                    <td>${escapeHtml(ticket.origin)} → ${escapeHtml(ticket.destination)}${ticket.trip_type === 'round_trip' && ticket.return_destination ? '<br><small>Return: ' + escapeHtml(ticket.return_destination) + '</small>' : ''}</td>
                    <td>${parseFloat(ticket.sold).toFixed(2)} <small class="text-muted">${escapeHtml(ticket.currency)}</small></td>
                    <td>${parseFloat(ticket.total_paid).toFixed(2)} <small class="text-muted">${escapeHtml(ticket.currency)}</small></td>
                    <td class="text-danger font-weight-bold">${outstanding.toFixed(2)} <small class="text-muted">${escapeHtml(ticket.currency)}</small></td>
                </tr>
            `);
            tbody.append(row);
        });

        this.updateTotals();
        $('#bulkTicketTableFoot').show();
    },

    updateTotals: function() {
        let totalSold = 0, totalPaid = 0, totalOutstanding = 0;

        $('#bulkTicketTableBody input.ticket-check').each(function() {
            totalSold += parseFloat($(this).data('sold')) || 0;
            totalPaid += parseFloat($(this).data('paid')) || 0;
            totalOutstanding += parseFloat($(this).data('outstanding')) || 0;
        });

        $('#bulkTotalSold').text(totalSold.toFixed(2));
        $('#bulkTotalPaid').text(totalPaid.toFixed(2));
        $('#bulkTotalOutstanding').text(totalOutstanding.toFixed(2));
    },

    updateSelectedTickets: function() {
        this.selectedTicketIds = [];
        $('#bulkTicketTableBody input.ticket-check:checked').each(function() {
            bulkPaymentManager.selectedTicketIds.push(parseInt($(this).data('ticket-id')));
        });

        const hasSelection = this.selectedTicketIds.length > 0;
        $('#bulkAllocationSection').toggle(hasSelection);

        if (hasSelection) {
            this.renderAllocationTable();
            this.toggleExchangeRate();
        } else {
            $('#bulkAllocationBody').empty();
            $('#bulkSubmitPayment').prop('disabled', true);
            $('#bulkAllocationValidation').text('');
        }
    },

    applyFilters: function() {
        const searchTerm = $('#bulkPnrSearch').val().toLowerCase().trim();
        const dateFrom = $('#bulkDateFrom').val();
        const dateTo = $('#bulkDateTo').val();

        let visibleCount = 0;

        $('#bulkTicketTableBody tr').each(function() {
            const row = $(this);
            // Skip the "no tickets" placeholder row
            if (row.find('td[colspan]').length > 0) {
                row.show();
                return;
            }

            const pnr = (row.find('td:eq(1)').text() || '').toLowerCase();
            const passenger = (row.find('td:eq(2)').text() || '').toLowerCase();
            const departureDate = row.data('departure-date') || '';

            let show = true;

            // PNR or passenger search
            if (searchTerm) {
                if (!pnr.includes(searchTerm) && !passenger.includes(searchTerm)) {
                    show = false;
                }
            }

            // Date from filter
            if (show && dateFrom && departureDate) {
                if (departureDate < dateFrom) {
                    show = false;
                }
            }

            // Date to filter
            if (show && dateTo && departureDate) {
                if (departureDate > dateTo) {
                    show = false;
                }
            }

            if (show) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
                // Uncheck hidden rows
                row.find('input.ticket-check').prop('checked', false);
            }
        });

        // Update select-all checkbox state
        const allVisible = $('#bulkTicketTableBody input.ticket-check:visible');
        const allChecked = allVisible.length > 0 && allVisible.length === $('#bulkTicketTableBody input.ticket-check:visible:checked').length;
        $('#bulkSelectAllCheck').prop('checked', allChecked);

        this.updateSelectedTickets();
    },

    renderAllocationTable: function() {
        const tbody = $('#bulkAllocationBody');
        tbody.empty();

        let totalOutstanding = 0;

        this.selectedTicketIds.forEach(ticketId => {
            const checkbox = $(`#bulkTicket-${ticketId}`);
            const pnr = checkbox.data('pnr');
            const passenger = checkbox.data('passenger');
            const outstanding = parseFloat(checkbox.data('outstanding'));
            totalOutstanding += outstanding;

            const row = $(`
                <tr data-ticket-id="${ticketId}">
                    <td>${escapeHtml(pnr)}</td>
                    <td>${escapeHtml(passenger)}</td>
                    <td class="text-right outstanding-val">${outstanding.toFixed(2)}</td>
                    <td class="text-right">
                        <input type="number" class="bulk-alloc-input"
                               data-ticket-id="${ticketId}"
                               data-outstanding="${outstanding}"
                               step="0.01" min="0" max="${outstanding}"
                               value="${outstanding.toFixed(2)}">
                    </td>
                </tr>
            `);
            tbody.append(row);
        });

        $('#bulkAllocTotalOutstanding').text(totalOutstanding.toFixed(2));
        this.autoDistribute();
    },

    autoDistribute: function() {
        const totalAmount = parseFloat($('#bulkPaymentTotalAmount').val()) || 0;
        let remaining = totalAmount;

        $('.bulk-alloc-input').each(function() {
            const outstanding = parseFloat($(this).data('outstanding')) || 0;
            const alloc = Math.min(remaining, outstanding);
            $(this).val(alloc.toFixed(2));
            remaining -= alloc;
            if (remaining < 0) remaining = 0;
        });

        this.updateAllocationTotal();
    },

    updateAllocationTotal: function() {
        let totalAlloc = 0;
        $('.bulk-alloc-input').each(function() {
            totalAlloc += parseFloat($(this).val()) || 0;
        });
        $('#bulkAllocTotalAllocate').text(totalAlloc.toFixed(2));

        // Update submit button state
        const totalAmount = parseFloat($('#bulkPaymentTotalAmount').val()) || 0;
        const isValid = this.selectedTicketIds.length > 0 && totalAmount > 0 && totalAlloc > 0;
        $('#bulkSubmitPayment').prop('disabled', !isValid);

        // Show validation hint
        if (totalAmount > 0 && Math.abs(totalAlloc - totalAmount) > 0.01) {
            $('#bulkAllocationValidation').html(`<span class="text-danger">Allocation (${totalAlloc.toFixed(2)}) does not match payment amount (${totalAmount.toFixed(2)})</span>`);
        } else if (totalAmount > 0) {
            $('#bulkAllocationValidation').html(`<span class="text-success"><i class="feather icon-check-circle mr-1"></i>Allocation matches payment amount</span>`);
        } else {
            $('#bulkAllocationValidation').text('');
        }
    },

    toggleExchangeRate: function() {
        const selectedCurrency = $('#bulkPaymentCurrency').val();
        // Check if any selected ticket has a different currency
        let hasDifferent = false;
        this.selectedTicketIds.forEach(id => {
            const ticketCurrency = $(`#bulkTicket-${id}`).data('currency');
            if (ticketCurrency && ticketCurrency !== selectedCurrency) {
                hasDifferent = true;
            }
        });

        if (hasDifferent && selectedCurrency) {
            $('#bulkExchangeRateField').show();
            const baseDisplay = transactionManager ? transactionManager.getCurrencyDisplay(selectedCurrency) : selectedCurrency;
            $('#bulkExchangeRateHint').text(`Enter exchange rate for ${baseDisplay}`);
        } else {
            $('#bulkExchangeRateField').hide();
            $('#bulkExchangeRate').val('');
        }
    },

    submitPayment: function() {
        if (this.isSubmitting) return;
        if (this.selectedTicketIds.length === 0) {
            showToast('Please select at least one ticket', 'warning');
            return;
        }

        // Gather allocation amounts
        const amounts = {};
        let totalAlloc = 0;
        let totalOutstanding = 0;

        $('.bulk-alloc-input').each(function() {
            const ticketId = $(this).data('ticket-id');
            const outstanding = parseFloat($(this).data('outstanding'));
            const alloc = parseFloat($(this).val()) || 0;
            amounts[ticketId] = alloc;
            totalAlloc += alloc;
            totalOutstanding += outstanding;
        });

        const totalAmount = parseFloat($('#bulkPaymentTotalAmount').val()) || 0;

        if (totalAmount <= 0) {
            showToast('Please enter a valid payment amount', 'warning');
            return;
        }

        if (Math.abs(totalAlloc - totalAmount) > 0.01) {
            showToast('Allocation total does not match payment amount', 'warning');
            return;
        }

        // Validate required fields
        const date = $('#bulkPaymentDate').val();
        const time = $('#bulkPaymentTime').val();
        const currency = $('#bulkPaymentCurrency').val();
        const description = $('#bulkPaymentDescription').val().trim();

        if (!date || !time || !currency || !description) {
            showToast('Please fill in all required fields', 'warning');
            return;
        }

        // Set submitting state
        this.isSubmitting = true;
        const $btn = $('#bulkSubmitPayment');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="feather icon-loader spin"></i> Processing...');

        const payload = {
            ticket_ids: this.selectedTicketIds,
            amounts: amounts,
            currency: currency,
            date: `${date} ${time}`,
            description: description,
            receipt_number: $('#bulkReceiptNumber').val().trim(),
            exchange_rate: $('#bulkExchangeRateField').is(':visible') ? $('#bulkExchangeRate').val() : null,
            csrf_token: $('#bulkCsrfToken').val()
        };

        $.ajax({
            url: '../api/ticket/bulk_ticket_payment.php',
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json',
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    showToast(response.message || 'Bulk payment processed successfully', 'success');
                    $('#bulkPaymentModal').modal('hide');
                    setTimeout(() => {
                        if (typeof refreshTicketTable === 'function') {
                            refreshTicketTable();
                        }
                    }, 1000);
                } else {
                    showToast('Error: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showToast('Request timed out. Please try again.', 'error');
                } else {
                    showToast('Error processing bulk payment', 'error');
                }
            },
            complete: function() {
                bulkPaymentManager.isSubmitting = false;
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }
};

// Escape HTML helper (reuse from refresh-table.js if available)
if (typeof escapeHtml === 'undefined') {
    window.escapeHtml = function(text) {
        if (text === null || text === undefined) return '';
        text = String(text);
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    };
} else {
    var _origEscapeHtml = window.escapeHtml;
    window.escapeHtml = function(text) {
        if (text === null || text === undefined) return '';
        return _origEscapeHtml(String(text));
    };
}

$(document).ready(function() {
    bulkPaymentManager.init();
});
