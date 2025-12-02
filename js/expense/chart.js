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
        console.error('Income chart canvas not found');
        return;
    }

    incomeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['tickets', 'reservations', 'refunds', 'date_changes', 'visa', 'umrah', 'hotel', 'additional_payments'],
            datasets: [
                {
                    label: 'total_income (USD)',
                    data: [
                        data.tickets.USD || 0,
                        data.reservations.USD || 0,
                        data.refunds.USD || 0,
                        data.dateChanges.USD || 0,
                        data.visa.USD || 0,
                        data.umrah.USD || 0,
                        data.hotel.USD || 0,
                        data.additionalPayments.USD || 0
                    ],
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'total_income (AFS)',
                    data: [
                        data.tickets.AFS || 0,
                        data.reservations.AFS || 0,
                        data.refunds.AFS || 0,
                        data.dateChanges.AFS || 0,
                        data.visa.AFS || 0,
                        data.umrah.AFS || 0,
                        data.hotel.AFS || 0,
                        data.additionalPayments.AFS || 0
                    ],
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
                        text: 'total_income'
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
        console.error('Expense chart canvas not found');
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
                    label: 'total_expenses (USD)',
                    data: usdData,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                },
                {
                    label: 'total_expenses (AFS)',
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
                        text: 'total_expenses'
                    }
                }
            }
        }
    });
}

function createProfitLossChart(data) {
    const ctx = document.getElementById('profitLossChart');
    if (!ctx) {
        console.error('Profit/Loss chart canvas not found');
        return;
    }

    profitLossChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['profit', 'loss'],
            datasets: [
                {
                    label: 'total (USD)',
                    data: [data.USD.profit, -data.USD.loss],
                    backgroundColor: ['rgba(75, 192, 192, 0.6)', 'rgba(255, 99, 132, 0.6)'],
                    borderColor: ['rgba(75, 192, 192, 1)', 'rgba(255, 99, 132, 1)'],
                    borderWidth: 1
                },
                {
                    label: 'total (AFS)',
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
                        text: 'total_profit_loss'
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
        url: 'export_comprehensive_report.php',
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
                alert('error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('error: ' + response.message);
        }
    });
}

// Function to export data to Excel
function exportToExcel(type) {
    const startDate = $('#startDate').val();
    const endDate = $('#endDate').val();
    
    let url = 'export_financial_data.php';
    let data = {
        type: type,
        startDate: startDate,
        endDate: endDate
    };

    // If exporting expenses, use a different endpoint
    if (type === 'expenses') {
        url = 'export_expenses.php';
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
                alert('error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('error: ' + response.message);
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
    
    console.log('Filtering expenses with date range:', {
        filterStartDate: filterStartDate ? filterStartDate.toISOString() : 'none',
        filterEndDate: filterEndDate ? filterEndDate.toISOString() : 'none'
    });
    
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
            console.error('No created_at date found');
            $row.show(); // Show row with no date
            return;
        }
        
        console.log('Row created_at:', createdAtStr);
        
        try {
            // Parse the created_at date
            const rowDate = new Date(createdAtStr);
            
            console.log('Comparing dates:', {
                rowCreatedAt: rowDate.toISOString(),
                filterStartDate: filterStartDate ? filterStartDate.toISOString() : 'none',
                filterEndDate: filterEndDate ? filterEndDate.toISOString() : 'none'
            });
            
            // Check date range against created_at date
            const dateMatch = (!filterStartDate || rowDate >= filterStartDate) && (!filterEndDate || rowDate <= filterEndDate);
            
            // Show/hide based on date match
            if (dateMatch) {
                $row.show();
            } else {
                $row.hide();
            }
        } catch (e) {
            console.error('Error parsing created_at date:', e);
            $row.show(); // Show row with invalid date format
        }
    });
    
    // Always show all categories, even if they have no matching expenses
    $('.category-section').each(function() {
        const $section = $(this);
        const $visibleRows = $section.find('tbody tr:visible');
        
        console.log('Category visible rows:', {
            category: $section.find('.category-header h6').text(),
            visibleRows: $visibleRows.length
        });
        
        // Always show the category, but show a message if no matching expenses
        if ($visibleRows.length === 0) {
            // Get the expense list table body
            const $tbody = $section.find('.expense-list tbody');
            
            // Check if we already added a "no matches" message
            if ($tbody.find('.no-matches-row').length === 0) {
                // Add a row indicating no matching expenses
                $tbody.append('<tr class="no-matches-row text-muted"><td colspan="5" class="text-center">no_expenses_match_the_selected_date_range</td></tr>');
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
        url: 'get_financial_data.php',
        type: 'GET',
        data: {
            startDate: startDate,
            endDate: endDate
        },
        dataType: 'json',
        success: function(response) {
            console.log('Financial data received:', response); // Debug log
            if(response.success) {
                destroyExistingCharts(); // Destroy existing charts

                // Calculate totals for USD
                const totalIncomeUSD = response.income.tickets.USD + response.income.reservations.USD + response.income.refunds.USD + 
                    response.income.dateChanges.USD + response.income.visa.USD + 
                    response.income.umrah.USD + response.income.hotel.USD + 
                    response.income.additionalPayments.USD;
                const totalExpensesUSD = response.expenses.USD.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossUSD = response.profitLoss.USD.profit - response.profitLoss.USD.loss;

                // Calculate totals for AFS
                const totalIncomeAFS = response.income.tickets.AFS + response.income.reservations.AFS + response.income.refunds.AFS + 
                    response.income.dateChanges.AFS + response.income.visa.AFS + 
                    response.income.umrah.AFS + response.income.hotel.AFS + 
                    response.income.additionalPayments.AFS;
                const totalExpensesAFS = response.expenses.AFS.amounts.reduce((acc, amount) => acc + amount, 0);
                const totalProfitLossAFS = response.profitLoss.AFS.profit - response.profitLoss.AFS.loss;

                // Update HTML elements for USD
                document.getElementById('totalIncomeUSD').textContent = totalIncomeUSD.toLocaleString();
                document.getElementById('totalExpensesUSD').textContent = totalExpensesUSD.toLocaleString();
                document.getElementById('totalProfitLossUSD').textContent = totalProfitLossUSD.toLocaleString();

                // Update HTML elements for AFS
                document.getElementById('totalIncomeAFS').textContent = totalIncomeAFS.toLocaleString();
                document.getElementById('totalExpensesAFS').textContent = totalExpensesAFS.toLocaleString();
                document.getElementById('totalProfitLossAFS').textContent = totalProfitLossAFS.toLocaleString();

                // Create charts
                createIncomeChart(response.income);
                createExpenseChart(response.expenses);
                createProfitLossChart(response.profitLoss);
            } else {
                console.error('Error loading financial data:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Ajax error:', error);
        }
    });
}