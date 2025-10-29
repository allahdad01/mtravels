$(document).ready(function() {
    let currentPeriod = '';
    let currentFilteredDate = null;

    // Handle click on sales cards
    $('.sales-card').on('click', function() {
        const type = $(this).data('type');
        const usd = $(this).data('usd');
        const afs = $(this).data('afs');
        
        // Store current period
        currentPeriod = type;
        
        // Get filtered date if available
        const filteredDate = $(this).data('filtered-date');
        
        // Set title based on type
        let title = '';
        if (type === 'daily') {
            if (filteredDate) {
                // Format the date for display
                const formattedDate = new Date(filteredDate).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                title = 'Daily Sales Details (' + formattedDate + ')';
            } else {
                title = 'Daily Sales Details (Today)';
            }
        } else if (type === 'monthly') {
            if (filteredDate) {
                // For monthly, filteredDate will be in format YYYY-MM
                const [year, month] = filteredDate.split('-');
                const date = new Date(year, month - 1, 1);
                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long'
                });
                title = 'Monthly Sales Details (' + formattedDate + ')';
            } else {
                title = 'Monthly Sales Details (' + new Date().toLocaleDateString('en-US', {month: 'short', year: 'numeric'}) + ')';
            }
        } else if (type === 'yearly') {
            if (filteredDate) {
                title = 'Yearly Sales Details (' + filteredDate + ')';
            } else {
                title = 'Yearly Sales Details (' + new Date().getFullYear() + ')';
            }
        }
        
        // Populate modal with summary data
        $('#salesDetailsModalLabel').text(title);
        $('#salesPeriod').text(title);
        $('#salesUsd').text('$' + usd);
        $('#salesAfs').text('؋' + afs);
        
        // Hide transaction details section when opening the modal
        $('#transactionDetailsSection').hide();
        
        // Store filtered date parameter for later use in AJAX calls
        if (filteredDate) {
            $(this).data('modal-filtered-date', filteredDate);
            currentFilteredDate = filteredDate;
        } else {
            currentFilteredDate = null;
        }
        
        // Show loading in transaction table
        $('#transactionTableBody').html('<tr><td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading data...</td></tr>');
        
        // Show the modal while we're fetching data
        $('#salesDetailsModal').modal('show');
        
        // Fetch profit sources data for the filtered date
        $.ajax({
            url: 'ajax/get_profit_sources.php',
            type: 'POST',
            data: {
                period: type,
                filtered_date: filteredDate || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Render the profit sources data
                    renderProfitSources(response.data, type);
                } else {
                    // Show error
                    $('#transactionTableBody').html('<tr><td colspan="3" class="text-center text-danger">' + (response.message || 'Failed to load data') + '</td></tr>');
                }
            },
            error: function() {
                $('#transactionTableBody').html('<tr><td colspan="3" class="text-center text-danger">Failed to load data</td></tr>');
            }
        });
    });
    
    // Function to render profit sources data
    function renderProfitSources(data, type) {
        let transactionsHtml = '';
        
        if (data && data.length > 0) {
            $.each(data, function(index, item) {
                // Format the numbers
                const usdFormatted = parseFloat(item.usd).toFixed(2);
                const afsFormatted = parseFloat(item.afs).toFixed(2);
                
                // Skip items with zero profits in both currencies
                if (parseFloat(item.usd) === 0 && parseFloat(item.afs) === 0) {
                    return true; // Skip this iteration (continue in jQuery each)
                }
                
                // Add clickable class for all sources
                const clickableClass = 'source-clickable';
                
                transactionsHtml += `
                    <tr class="${clickableClass}" data-source="${item.source}" data-type="${item.type}">
                        <td>
                            <span class="badge badge-${getBadgeClass(item.source)}">${item.source}</span>
                            <small class="ml-2 text-primary"><i class="feather icon-external-link"></i> View Details</small>
                        </td>
                        <td>$${usdFormatted}</td>
                        <td>؋${afsFormatted}</td>
                    </tr>
                `;
            });
        } else {
            transactionsHtml = '<tr><td colspan="3" class="text-center">No data available</td></tr>';
        }
        
        $('#transactionTableBody').html(transactionsHtml);
    }
    
    // Handle click on source row
    $(document).on('click', '.source-clickable', function() {
        const source = $(this).data('source');
        const type = $(this).data('type');
        
        // Set the details section title
        $('#detailsSectionTitle').text(source + ' Details');
        
        // Show loading in transaction details section
        $('#transactionDetailsBody').html('<tr><td colspan="5" class="text-center">Loading details</td></tr>');
        
        // Set up the table header based on transaction type
        setupTableHeader(type);
        
        // Show the details section
        $('#transactionDetailsSection').fadeIn();
        
        // Prepare AJAX request data
        const requestData = { 
            period: currentPeriod,
            type: type
        };
        
        // Add filtered date to request if available
        if (currentFilteredDate) {
            requestData.filtered_date = currentFilteredDate;
        }
        
        // Fetch transaction details
        $.ajax({
            url: `ajax/get_${type}_details.php`,
            type: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let detailsHtml = generateDetailsHtml(response.data, type);
                    $('#transactionDetailsBody').html(detailsHtml);
                } else {
                    $('#transactionDetailsBody').html(`<tr><td colspan="5" class="text-center text-danger">${response.message || 'Failed to load data'}</td></tr>`);
                }
            },
            error: function() {
                $('#transactionDetailsBody').html(`<tr><td colspan="5" class="text-center text-danger">Could not load ${source.toLowerCase()} details</td></tr>`);
            }
        });
    });
    
    // Helper function to set up table header based on transaction type
    function setupTableHeader(type) {
        let headerHtml = '<tr>';
        
        switch(type) {
            case 'ticket':
                headerHtml += `
                    <th>Passenger Name</th>
                    <th>PNR</th>
                    <th>Airline</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'visa':
                headerHtml += `
                    <th>Applicant Name</th>
                    <th>Passport</th>
                    <th>Visa Type</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'umrah':
                headerHtml += `
                    <th>Name</th>
                    <th>Passport</th>
                    <th>Package</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'hotel':
                headerHtml += `
                    <th>Name</th>
                    <th>Room Type</th>
                    <th>Order ID</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'payment':
                headerHtml += `
                    <th>Name</th>
                    <th>Description</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'refund':
                headerHtml += `
                    <th>Passenger Name</th>
                    <th>PNR</th>
                    <th>Refund Date</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'datechange':
                headerHtml += `
                    <th>Passenger Name</th>
                    <th>PNR</th>
                    <th>Change Date</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'ticket_reservations':
                headerHtml += `
                    <th>Passenger Name</th>
                    <th>PNR</th>
                    <th>Airline</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            case 'weight_sale':
                headerHtml += `
                    <th>Passenger Name</th>
                    <th>PNR</th>
                    <th>Airline</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
                break;
            default:
                headerHtml += `
                    <th>Name</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Paid Account</th>
                    <th>USD Profit</th>
                    <th>AFS Profit</th>
                `;
        }
        
        headerHtml += '</tr>';
        $('#transactionDetailsHeader').html(headerHtml);
    }
    
    // Helper function to generate HTML for details based on transaction type
    function generateDetailsHtml(data, type) {
        if (!data || data.length === 0) {
            return `<tr><td colspan="5" class="text-center">No ${type} details found for this period</td></tr>`;
        }
        
        let html = '';
        
        $.each(data, function(index, item) {
            // Format profit values
            const profitUSD = item.currency === 'USD' ? parseFloat(item.profit).toFixed(2) : '-';
            const profitAFS = item.currency === 'AFS' ? parseFloat(item.profit).toFixed(2) : '-';
            
            html += '<tr>';
            
            switch(type) {
                case 'ticket':
                    html += `
                        <td>${item.passenger_name}</td>
                        <td>${item.pnr}</td>
                        <td>${item.airline}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'visa':
                    html += `
                        <td>${item.applicant_name}</td>
                        <td>${item.passport_number}</td>
                        <td>${item.visa_type}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'umrah':
                    html += `
                        <td>${item.name}</td>
                        <td>${item.passport_number}</td>
                        <td>${item.package_type || '-'}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'hotel':
                    html += `
                        <td>${item.name}</td>
                        <td>${item.accommodation_details}</td>
                        <td>${item.order_id}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'payment':
                    html += `
                        <td>${item.payment_type}</td>
                        <td>${item.description || '-'}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'refund':
                    html += `
                        <td>${item.passenger_name}</td>
                        <td>${item.pnr}</td>
                        <td>${formatDate(item.created_at)}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'datechange':
                    html += `
                        <td>${item.passenger_name}</td>
                        <td>${item.pnr}</td>
                        <td>${formatDate(item.created_at)}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'ticket_reservations':
                    html += `
                        <td>${item.passenger_name}</td>
                        <td>${item.pnr}</td>
                        <td>${item.airline}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                case 'weight_sale':
                    html += `
                        <td>${item.passenger_name}</td>
                        <td>${item.pnr}</td>
                        <td>${item.airline}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
                    break;
                default:
                    html += `
                        <td>${item.name || '-'}</td>
                        <td>${item.reference || '-'}</td>
                        <td>${item.description || '-'}</td>
                        <td>${item.paid_to}</td>
                        <td>${item.currency === 'USD' ? '$' + profitUSD : '-'}</td>
                        <td>${item.currency === 'AFS' ? '؋' + profitAFS : '-'}</td>
                    `;
            }
            
            html += '</tr>';
        });
        
        return html;
    }
    
    // Handle print button click
    $('#printProfitDetails').on('click', function() {
        printProfitDetails();
    });
    
    // Function to print profit details
    function printProfitDetails() {
        // Store the modal content
        const modalTitle = $('#salesPeriod').text();
        const summaryTable = $('.modal-body .table-responsive:first').html();
        const profitSourcesTable = $('.modal-body .table-responsive:eq(1)').html();
        let detailsSection = '';
        
        // Calculate totals from details section
        let detailsUsdTotal = 0;
        let detailsAfsTotal = 0;
        
        // Check if transaction details section is visible
        if ($('#transactionDetailsSection').is(':visible')) {
            const detailsTitle = $('#detailsSectionTitle').text();
            const detailsTable = $('#transactionDetailsSection .table-responsive').html();
            
            // Calculate totals from the details table
            $('#transactionDetailsBody tr').each(function() {
                const usdCell = $(this).find('td:nth-last-child(2)').text();
                const afsCell = $(this).find('td:last-child').text();
                
                // Extract numeric values
                if (usdCell && usdCell.includes('$')) {
                    detailsUsdTotal += parseFloat(usdCell.replace(/[^\d.-]/g, '')) || 0;
                }
                
                if (afsCell && afsCell.includes('؋')) {
                    detailsAfsTotal += parseFloat(afsCell.replace(/[^\d.-]/g, '')) || 0;
                }
            });
            
            // Create details section with totals row
            detailsSection = `
                <div class="mt-4">
                    <h5 class="border-top pt-3">${detailsTitle}</h5>
                    ${detailsTable}
                    <div class="details-total">
                        <table class="table table-bordered mt-3">
                            <tr class="bg-light">
                                <th colspan="4" class="text-right">Total</th>
                                <th>$${detailsUsdTotal.toFixed(2)}</th>
                                <th>؋${detailsAfsTotal.toFixed(2)}</th>
                            </tr>
                        </table>
                    </div>
                </div>
            `;
        }
        
        // Create print content
        const printContent = `
            <html>
            <head>
                <title>${modalTitle}</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        padding: 20px;
                        color: #333;
                    }
                    h2 {
                        color: #4099ff;
                        margin-bottom: 20px;
                    }
                    h5 {
                        color: #4099ff;
                        margin-top: 20px;
                        margin-bottom: 10px;
                        padding-top: 15px;
                        border-top: 1px solid #ddd;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                    }
                    th, td {
                        border: 1px solid #ddd;
                        padding: 8px 12px;
                        text-align: left;
                    }
                    th {
                        background-color: #f5f5f5;
                    }
                    .company-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 30px;
                        border-bottom: 2px solid #4099ff;
                        padding-bottom: 10px;
                    }
                    .print-date {
                        text-align: right;
                        color: #666;
                        font-size: 0.8em;
                        margin-top: 5px;
                    }
                    .page-footer {
                        margin-top: 30px;
                        text-align: center;
                        font-size: 0.8em;
                        color: #666;
                        border-top: 1px solid #ddd;
                        padding-top: 10px;
                    }
                    .period-summary, .profit-sources, .details-total {
                        margin-bottom: 20px;
                    }
                    .text-right {
                        text-align: right;
                    }
                    .mt-3 {
                        margin-top: 15px;
                    }
                    .bg-light {
                        background-color: #f8f9fa;
                        font-weight: bold;
                    }
                </style>
            </head>
            <body>
                <div class="company-header">
                    <div>
                        <h2>Financial Report</h2>
                    </div>
                    <div class="print-date">
                        Printed on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                    </div>
                </div>
                
                <h2>${modalTitle}</h2>
                
                ${detailsSection}
                
                <div class="page-footer">
                    &copy; ${new Date().getFullYear()}
                </div>
            </body>
            </html>
        `;
        
        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // Wait for content to load before printing
        printWindow.onload = function() {
            printWindow.print();
            // Close the print window/tab after printing or if user cancels
            printWindow.onafterprint = function() {
                printWindow.close();
            };
        };
    }
    
    // Helper function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }
    
    // Helper function to get badge class based on transaction type
    function getBadgeClass(source) {
        switch(source) {
            case 'Ticket Bookings':
                return 'primary';
            case 'Visa Applications':
                return 'success';
            case 'Umrah Bookings':
                return 'info';
            case 'Hotel Bookings':
                return 'warning';
            case 'Refunded Tickets':
                return 'danger';
            case 'Date Changed Tickets':
                return 'secondary';
            case 'Ticket Weights':
                return 'danger';
            case 'Additional Payments':
                return 'dark';
            default:
                return 'light';
        }
    }
}); 