document.addEventListener('DOMContentLoaded', function() {
    // Initial fetch of financial data
    fetchFinancialData('monthly', 'USD');
    
    // Add event listeners to filter selectors
    document.getElementById('financeChartPeriod').addEventListener('change', updateFinancialChart);
    document.getElementById('financeChartCurrency').addEventListener('change', updateFinancialChart);
    
    function updateFinancialChart() {
        const period = document.getElementById('financeChartPeriod').value;
        const currency = document.getElementById('financeChartCurrency').value;
        fetchFinancialData(period, currency);
    }
    
    function fetchFinancialData(period, currency) {
        // Show loading state
        document.getElementById('financeFlowChart').innerHTML = '<div class="d-flex justify-content-center align-items-center" style="height: 350px;"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>';
        
        // Fetch data using AJAX
        $.ajax({
            url: 'ajax/get_financial_data.php',
            type: 'POST',
            data: {
                period: period,
                currency: currency
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderFinancialChart(response.data, currency);
                    updateWealthSummary(response.data, currency);
                } else {
                    document.getElementById('financeFlowChart').innerHTML = '<div class="alert alert-danger">Error loading financial data</div>';
                }
            },
            error: function() {
                document.getElementById('financeFlowChart').innerHTML = '<div class="alert alert-danger">Failed to load financial data</div>';
            }
        });
    }
    
    function renderFinancialChart(data, currency) {
        // Helper to safely convert to number
        const toNumber = (val) => {
            const num = parseFloat(val);
            return isFinite(num) ? num : 0;
        };
    
        const dates = [];
        const creditData = [];
        const debitData = [];
        const netFlowData = [];
    
        if (Array.isArray(data.transactions) && data.transactions.length > 0) {
            const creditsByDate = {};
            const debitsByDate = {};
    
            data.transactions.forEach(transaction => {
                const date = transaction.date;
                const amount = toNumber(transaction.amount);
    
                if (!dates.includes(date)) {
                    dates.push(date);
                }
    
                if (transaction.type === 'credit') {
                    creditsByDate[date] = (creditsByDate[date] || 0) + amount;
                } else if (transaction.type === 'debit') {
                    debitsByDate[date] = (debitsByDate[date] || 0) + amount;
                }
            });
    
            dates.sort(); // Sort chronologically
            let cumulativeNet = 0;
    
            dates.forEach(date => {
                const credits = toNumber(creditsByDate[date]);
                const debits = toNumber(debitsByDate[date]);
                const netFlow = credits - debits;
    
                cumulativeNet += netFlow;
    
                creditData.push(credits);
                debitData.push(debits);
                netFlowData.push(cumulativeNet);
            });
        }
    
        const formattedDates = dates.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
    
        if (dates.length === 0) {
            document.getElementById('financeFlowChart').innerHTML =
                '<div class="d-flex justify-content-center align-items-center" style="height: 350px;"><p class="text-muted">No financial data available</p></div>';
            return;
        }
    
        const currencySymbol = getCurrencySymbol(currency);
    
        const options = {
            series: [
                {
                    name: 'Credit (Income)',
                    type: 'column',
                    data: creditData
                },
                {
                    name: 'Debit (Expense)',
                    type: 'column',
                    data: debitData.map(val => -toNumber(val))
                },
                {
                    name: 'Net Cash Flow',
                    type: 'line',
                    data: netFlowData
                }
            ],
            chart: {
                height: 400,
                type: 'line',
                stacked: false,
                toolbar: { show: true }
            },
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '50%' }
            },
            dataLabels: { enabled: false },
            stroke: {
                width: [0, 0, 3],
                curve: 'smooth',
                colors: ['#4099ff', '#FF5370', '#00E396']
            },
            xaxis: {
                categories: formattedDates
            },
            yaxis: [
                {
                    labels: {
                        formatter: (value) => currencySymbol + Math.abs(value).toFixed(2)
                    },
                    title: { text: "Cash Flow Amount" }
                },
                {
                    opposite: true,
                    labels: {
                        formatter: (value) => currencySymbol + value.toFixed(2)
                    },
                    title: { text: "Cumulative Flow" }
                }
            ],
            tooltip: {
                shared: true,
                y: {
                    formatter: (value, { seriesIndex }) =>
                        seriesIndex === 1
                            ? currencySymbol + Math.abs(value).toFixed(2)
                            : currencySymbol + value.toFixed(2)
                }
            },
            colors: ['#4099ff', '#FF5370', '#00E396']
        };
    
        if (window.financialChart) {
            window.financialChart.destroy();
        }
        document.getElementById('financeFlowChart').innerHTML = '';
    
        const chart = new ApexCharts(document.getElementById('financeFlowChart'), options);
        window.financialChart = chart;
        chart.render();
    }
    
    
    function updateWealthSummary(data, currency) {
        const currencySymbol = getCurrencySymbol(currency);
        
        // Format and display the summary values
        document.getElementById('mainAccountBalance').textContent = 
            currencySymbol + parseFloat(data.main_accounts).toFixed(2);
            
        document.getElementById('supplierBalance').textContent = 
            currencySymbol + parseFloat(data.supplier_credits).toFixed(2);
            
        document.getElementById('clientBalance').textContent = 
            currencySymbol + parseFloat(data.client_credits).toFixed(2);
            
        document.getElementById('debtorBalance').textContent = 
            currencySymbol + parseFloat(data.debtor_balance || 0).toFixed(2);
            
        // Calculate and display total net worth
        // For suppliers, positive balance means WE HAVE money in those accounts (add to net worth)
        // Ignore negative balances completely
        const supplierBalance = parseFloat(data.supplier_credits || 0);
        const supplierContribution = supplierBalance > 0 ? supplierBalance : 0;
        
        // For clients, handle negative balances specially:
        // - Positive balance (clients owe us): Add to net worth
        // - Negative balance (we owe clients): ALSO add to net worth (absolute value)
        // This means we always add the absolute value of client balances to net worth
        const clientBalance = parseFloat(data.client_credits || 0);
        const clientCredits = Math.abs(clientBalance);
        
        const totalNetWorth = parseFloat(data.main_accounts) +
                             clientCredits +
                             parseFloat(data.debtor_balance || 0) +
                             supplierContribution;
                             
        document.getElementById('totalNetWorth').textContent = 
            currencySymbol + totalNetWorth.toFixed(2);
            
        // Add color coding based on values
        const formatElement = (elementId, value) => {
            const element = document.getElementById(elementId);
            if (value > 0) {
                element.classList.remove('text-danger');
                element.classList.add('text-success');
            } else if (value < 0) {
                element.classList.remove('text-success');
                element.classList.add('text-danger');
            } else {
                element.classList.remove('text-success', 'text-danger');
            }
        };
        
        // Format account balances with appropriate colors
        formatElement('mainAccountBalance', data.main_accounts);

        // For suppliers, positive balance is good (we have money in those accounts)
        // Only show positive supplier balances in green, ignore negative ones
        formatElement('supplierBalance', supplierBalance > 0 ? supplierBalance : 0);

        // For clients, we always show positive color regardless of balance
        // since both positive and negative balances contribute positively to net worth
        formatElement('clientBalance', Math.abs(data.client_credits));

        // For debtors, positive balance is good (they owe us money)
        formatElement('debtorBalance', data.debtor_balance || 0);

        formatElement('totalNetWorth', totalNetWorth);
    }
});

// Helper function to get currency symbol
function getCurrencySymbol(currency) {
    switch(currency) {
        case 'USD':
            return '$';
        case 'AFS':
            return '؋';
        case 'EUR':
            return '€';
        case 'AED':
            return 'د.إ';
        default:
            return '$';
    }
} 