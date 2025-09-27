$(document).ready(function() {
    // Handle filter icon click explicitly to toggle the filter options
    $('.filter-icon').on('click', function(e) {
        e.stopPropagation();
        const targetId = $(this).data('target');
        $(targetId).collapse('toggle');
    });

    // Prevent filter elements from triggering card click
    $('.date-filter, .apply-daily-filter, .apply-monthly-filter, .apply-yearly-filter').on('click', function(e) {
        e.stopPropagation();
    });

    // Handle daily filter
    $('.apply-daily-filter').on('click', function() {
        const selectedDate = $('#dailyDateInput').val();
        if (!selectedDate) {
            alert('Please select a date');
            return;
        }
        
        applySalesFilter('daily', selectedDate);
    });
    
    // Handle monthly filter
    $('.apply-monthly-filter').on('click', function() {
        const selectedMonth = $('#monthlyDateInput').val();
        if (!selectedMonth) {
            alert('Please select a month');
            return;
        }
        
        applySalesFilter('monthly', selectedMonth);
    });
    
    // Handle yearly filter
    $('.apply-yearly-filter').on('click', function() {
        const selectedYear = $('#yearlyDateInput').val();
        if (!selectedYear) {
            alert('Please select a year');
            return;
        }
        
        applySalesFilter('yearly', selectedYear);
    });
    
    // Function to apply the filter and update the card
    function applySalesFilter(filterType, filterDate) {
        // Show loading indicator
        const cardPrefix = filterType === 'daily' ? 'daily' : (filterType === 'monthly' ? 'monthly' : 'yearly');
        $(`#${cardPrefix}UsdProfit`).html('<small><i class="fa fa-spinner fa-spin"></i></small>');
        $(`#${cardPrefix}AfsProfit`).html('<small><i class="fa fa-spinner fa-spin"></i></small>');
        
        // Send AJAX request
        $.ajax({
            url: 'ajax/get_filtered_sales.php',
            type: 'POST',
            data: {
                filter_type: filterType,
                filter_date: filterDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Get the raw values (not formatted)
                    const usdProfit = response.data.usd_profit;
                    const afsProfit = response.data.afs_profit;
                    
                    // Update card with filtered data
                    $(`#${cardPrefix}UsdProfit`).text(formatNumber(usdProfit));
                    $(`#${cardPrefix}AfsProfit`).text(formatNumber(afsProfit));
                    $(`#${cardPrefix}DateDisplay`).text(response.display_date);
                    
                    // Update card data attributes for click handling - critical for modal to show correct data
                    // Use attr() instead of data() to ensure the HTML attribute is updated
                    $(`.${filterType}-sales`).attr('data-usd', formatNumber(usdProfit));
                    $(`.${filterType}-sales`).attr('data-afs', formatNumber(afsProfit));
                    
                    // Store raw values in custom data attributes for calculations
                    $(`.${filterType}-sales`).data('raw-usd', usdProfit);
                    $(`.${filterType}-sales`).data('raw-afs', afsProfit);
                    $(`.${filterType}-sales`).data('filtered-date', filterDate);
                    
                    // Hide the filter after applying
                    $(`#${cardPrefix}DateFilter`).collapse('hide');
                    
                    // Add visual indication that filter is applied
                    $(`.filter-controls .filter-icon[data-target="#${cardPrefix}DateFilter"]`).addClass('text-success').removeClass('text-primary');
                } else {
                    // Show error
                    alert(response.message || 'Failed to retrieve data');
                    
                    // Reset to original values
                    $(`#${cardPrefix}UsdProfit`).text('0.00');
                    $(`#${cardPrefix}AfsProfit`).text('0.00');
                }
            },
            error: function(xhr, status, error) {
                console.error(error);
                alert('An error occurred while processing your request.');
                
                // Reset to original values
                $(`#${cardPrefix}UsdProfit`).text('0.00');
                $(`#${cardPrefix}AfsProfit`).text('0.00');
            }
        });
    }
    
    // Helper function to format numbers
    function formatNumber(number) {
        return parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
            
    // Add hover effect for filter icons
    $('.filter-icon').hover(
        function() { $(this).addClass('text-primary-hover'); },
        function() { $(this).removeClass('text-primary-hover'); }
    );


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
                            <th colspan="4" class="text-right"><?= __('total') ?></th>
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
                    <h2><?= htmlspecialchars($settings['agency_name']) ?></h2>
                    <p>Financial Report</p>
                </div>
                <div class="print-date">
                    Printed on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                </div>
            </div>
            
            <h2>${modalTitle}</h2>
            
         
            
            ${detailsSection}
            
            <div class="page-footer">
                &copy; ${new Date().getFullYear()} <?= htmlspecialchars($settings['agency_name']) ?>
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
}); 