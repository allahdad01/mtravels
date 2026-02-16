// Main Expense Management JavaScript functionality

// Pass allowed features to JavaScript
var allowedFeatures = []; // This will be set from PHP

// Declare chart variables at a higher scope
let incomeChart, expenseChart, profitLossChart;

function destroyExistingCharts() {
    if (incomeChart) {
        incomeChart.destroy();
        incomeChart = null;
    }
    if (expenseChart) {
        expenseChart.destroy();
        expenseChart = null;
    }
    if (profitLossChart) {
        profitLossChart.destroy();
        profitLossChart = null;
    }
}

function createIncomeChart(data) {
    const ctx = document.getElementById('incomeChart');
    if (!ctx) {

        return;
    }

    // Define feature mappings
    const featureMappings = [
        { label: 'Tickets', feature: 'ticket_bookings', usdKey: 'tickets', afsKey: 'tickets' },
        { label: 'Ticket Weights', feature: 'ticket_weights', usdKey: 'ticket_weights', afsKey: 'ticket_weights' },
        { label: 'Reservations', feature: 'ticket_reservations', usdKey: 'reservations', afsKey: 'reservations' },
        { label: 'Refunds', feature: 'refunded_tickets', usdKey: 'refunds', afsKey: 'refunds' },
        { label: 'Date Changes', feature: 'date_change_tickets', usdKey: 'dateChanges', afsKey: 'dateChanges' },
        { label: 'Visa', feature: 'visa_applications', usdKey: 'visa', afsKey: 'visa' },
        { label: 'Umrah', feature: 'umrah_bookings', usdKey: 'umrah', afsKey: 'umrah' },
        { label: 'Hotel', feature: 'hotel_bookings', usdKey: 'hotel', afsKey: 'hotel' },
        { label: 'Additional Payments', feature: 'additional_payments', usdKey: 'additionalPayments', afsKey: 'additionalPayments' }
    ];

    // Filter features based on allowed features
    const allowedMappings = featureMappings.filter(mapping => {
        return allowedFeatures.includes(mapping.feature);
    });

    // Build labels and data arrays based on allowed features
    const labels = allowedMappings.map(mapping => mapping.label);
    const usdData = allowedMappings.map(mapping => data[mapping.usdKey]?.USD || 0);
    const afsData = allowedMappings.map(mapping => data[mapping.afsKey]?.AFS || 0);

    incomeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Income (USD)',
                    data: usdData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Total Income (AFS)',
                    data: afsData,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Income'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            return `${label}: ${value.toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });
}

function createExpenseChart(data) {
    const ctx = document.getElementById('expenseChart');
    if (!ctx) {

        return;
    }

    const labels = [];
    const usdData = [];
    const afsData = [];

    data.USD.categories.forEach((category, index) => {
        labels.push(category);
        usdData.push(data.USD.amounts[index]);
        afsData.push(0);
    });

    data.AFS.categories.forEach((category, index) => {
        labels.push(category + ' (AFS)');
        usdData.push(0);
        afsData.push(data.AFS.amounts[index]);
    });

    expenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Expenses (USD)',
                    data: usdData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Total Expenses (AFS)',
                    data: afsData,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Expenses'
                    }
                }
            }
        }
    });
}

function createProfitLossChart(data) {
    const ctx = document.getElementById('profitLossChart');
    if (!ctx) {

        return;
    }

    profitLossChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Profit', 'Loss'],
            datasets: [
                {
                    label: 'Total (USD)',
                    data: [data.USD.profit, -data.USD.loss],
                    backgroundColor: ['rgba(75, 192, 192, 0.6)', 'rgba(255, 99, 132, 0.6)'],
                    borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 99, 132, 1)'],
                    borderWidth: 1
                },
                {
                    label: 'Total (AFS)',
                    data: [data.AFS.profit, -data.AFS.loss],
                    backgroundColor: ['rgba(54, 162, 235, 0.6)', 'rgba(255, 159, 64, 0.6)'],
                    borderColor: ['rgba(54, 162, 235, 1)', 'rgba(255, 159, 64, 1)'],
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Profit/Loss'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            const category = context.label;
                            return `${label} ${category}: ${Math.abs(value).toLocaleString()}`;
                        }
                    }
                }
            }
        }
    });
}

// Function to export chart as image
function exportChart(chartId, filename) {
    const canvas = document.getElementById(chartId);
    const link = document.createElement('a');
    link.download = `${filename}_${formatDate(new Date())}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// Function to export comprehensive financial report
function exportComprehensiveReport() {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    $.ajax({
        url: '../api/expense/export_comprehensive_report.php',
        type: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        success: function(response) {
            if(response.success) {
                // Convert base64 to blob
                const binary = atob(response.file);
                const array = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    array[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([array], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});

                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Financial_Report_${startDate}_to_${endDate}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {

            alert('Error: ' + response.message);
        }
    });
}

// Function to export data to Excel
function exportToExcel(type) {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    let url = '../api/expense/export_financial_data.php';
    let data = {
        type: type,
        startDate: startDate,
        endDate: endDate
    };

    // If exporting expenses, use a different endpoint
    if (type === 'expenses') {
        url = '../api/expense/export_expenses.php';
        data = {
            startDate: startDate,
            endDate: endDate
        };
    }
    
    $.ajax({
        url: url,
        type: 'GET',
        data: data,
        success: function(response) {
            if(response.success) {
                // Convert base64 to blob
                const binary = atob(response.file);
                const array = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    array[i] = binary.charCodeAt(i);
                }
                const blob = new Blob([array], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});

                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${type}_report_${formatDate(new Date())}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {

            alert('Error: ' + response.message);
        }
    });
}

// Helper function to format date for filenames
function formatDate(date) {
    return date.toISOString().split('T')[0];
}

// Function to filter expenses based on created_at date
function filterExpenses() {
    // Get the selected date range from the filter
    const filterStartDate = $('#filterStartDate').val() ? new Date($('#filterStartDate').val() + 'T00:00:00') : null;
    const filterEndDate = $('#filterEndDate').val() ? new Date($('#filterEndDate').val() + 'T23:59:59') : null;
    
    // Make sure all categories are visible
    $('.category-section').show();
    $('.expense-list').show();
    
    // Remove any previous "no matches" messages
    $('.no-matches-row').remove();
    
    // No date filter selected, show all expenses
    if (!filterStartDate && !filterEndDate) {
        $('.expense-list tbody tr').show();
        return;
    }
    
    // Initially show all rows, then hide non-matching ones
    $('.expense-list tbody tr:not(.no-matches-row)').show();
    
    // Filter each row based on created_at date
    $('.expense-list tbody tr').each(function() {
        const $row = $(this);
        
        // Get the created_at date from data attribute
        const createdAtStr = $row.data('created');
        
        if (!createdAtStr) {

            $row.show(); // Show row with no date
            return;
        }
        

        
        try {
            // Parse the created_at date
            const rowDate = new Date(createdAtStr);
            
            // Check date range against created_at date
            const dateMatch = (!filterStartDate || rowDate >= filterStartDate) && (!filterEndDate || rowDate <= filterEndDate);
            
            // Show/hide based on date match
            if (dateMatch) {
                $row.show();
            } else {
                $row.hide();
            }
        } catch (e) {

            $row.show(); // Show row with invalid date format
        }
    });
    
    // Always show all categories, even if they have no matching expenses
    $('.category-section').each(function() {
        const $section = $(this);
        const $visibleRows = $section.find('tbody tr:visible');
        
        // Always show the category, but show a message if no matching expenses
        if ($visibleRows.length === 0) {
            // Get the expense list table body
            const $tbody = $section.find('.expense-list tbody');
            
            // Check if we already added a "no matches" message
            if ($tbody.find('.no-matches-row').length === 0) {
                // Add a row indicating no matching expenses
                $tbody.append('<tr class="no-matches-row text-muted"><td colspan="5" class="text-center">No expenses match the selected date range</td></tr>');
            }
        } else {
            // Remove any "no matches" message if we have visible rows
            $section.find('.no-matches-row').remove();
        }
    });
}

function convertDateFormat(dateStr) {
    const parts = dateStr.split('/');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return dateStr;
}

function loadFinancialData() {
    // Get dates from the main date range picker, not the expense filter
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();

    $.ajax({
        url: '../api/expense/get_financial_data.php',
        type: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        dataType: 'json',
        success: function(response) {

            if(response.success) {
                destroyExistingCharts(); // Destroy existing charts

                // Calculate totals for USD
                const totalIncomeUSD = response.income.tickets.USD + response.income.ticket_weights.USD + response.income.reservations.USD + response.income.refunds.USD + 
                    response.income.dateChanges.USD + response.income.visa.USD + 
                    response.income.umrah.USD + response.income.hotel.USD + 
                    response.income.additionalPayments.USD;
                const totalExpensesUSD = response.expenses.USD.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossUSD = response.profitLoss.USD.profit - response.profitLoss.USD.loss;

                // Calculate totals for AFS
                const totalIncomeAFS = response.income.tickets.AFS + response.income.ticket_weights.AFS + response.income.reservations.AFS + response.income.refunds.AFS + 
                    response.income.dateChanges.AFS + response.income.visa.AFS + 
                    response.income.umrah.AFS + response.income.hotel.AFS + 
                    response.income.additionalPayments.AFS;
                const totalExpensesAFS = response.expenses.AFS.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossAFS = response.profitLoss.AFS.profit - response.profitLoss.AFS.loss;

                // Update HTML elements for USD with animation
                updateAmountWithAnimation('totalIncomeUSD', totalIncomeUSD);
                updateAmountWithAnimation('totalExpensesUSD', totalExpensesUSD);
                updateAmountWithAnimation('totalProfitLossUSD', totalProfitLossUSD);

                // Update HTML elements for AFS with animation
                updateAmountWithAnimation('totalIncomeAFS', totalIncomeAFS);
                updateAmountWithAnimation('totalExpensesAFS', totalExpensesAFS);
                updateAmountWithAnimation('totalProfitLossAFS', totalProfitLossAFS);

                // Update profit/loss card styling based on values
                updateProfitLossCardStyling(totalProfitLossUSD, totalProfitLossAFS);

                // Create charts
                createIncomeChart(response.income);
                createExpenseChart(response.expenses);
                createProfitLossChart(response.profitLoss);
            } else {

            }
        },
        error: function(xhr, status, error) {

        }
    });
}

// Function to update amount with animation
function updateAmountWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (element) {
        // Add updating class for animation
        element.classList.add('updating');

        // Update the value
        element.textContent = newValue.toLocaleString();

        // Remove animation class after animation completes
        setTimeout(() => {
            element.classList.remove('updating');
        }, 600);
    }
}

// Function to update profit/loss card styling based on values
function updateProfitLossCardStyling(usdValue, afsValue) {
    const profitLossCard = document.getElementById('profitLossCard');
    const profitLossIcon = document.getElementById('profitLossIcon');
    const profitLossTitle = document.getElementById('profitLossTitle');

    if (profitLossCard && profitLossIcon && profitLossTitle) {
        // Determine if it's profit or loss based on USD value (primary currency)
        const isProfit = usdValue >= 0;
        const isLoss = usdValue < 0;

        // Remove existing classes
        profitLossCard.classList.remove('profit', 'loss');

        if (isProfit) {
            profitLossCard.classList.add('profit');
            profitLossIcon.className = 'feather icon-trending-up';
            profitLossTitle.textContent = usdValue === 0 ? 'Break Even' : 'Profit';
        } else {
            profitLossCard.classList.add('loss');
            profitLossIcon.className = 'feather icon-trending-down';
            profitLossTitle.textContent = 'Loss';
        }

        // Update USD value (show negative for loss)
        const usdElement = document.getElementById('totalProfitLossUSD');
        if (usdElement) {
            usdElement.textContent = usdValue.toLocaleString();
        }

        // Update AFS value (show negative for loss)
        const afsElement = document.getElementById('totalProfitLossAFS');
        if (afsElement) {
            afsElement.textContent = afsValue.toLocaleString();
        }
    }
}
