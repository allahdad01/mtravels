document.addEventListener('DOMContentLoaded', function() {
    // Print main account transactions
    const printTransactionsBtn = document.getElementById('printTransactionsBtn');
    if (printTransactionsBtn) {
        printTransactionsBtn.addEventListener('click', function() {
            const modal = $(this).closest('.modal');
            const accountName = modal.find('#accountNameDisplay').text();
            const table = modal.find('table').clone();
            
            // Remove action column for printing
            table.find('tr').each(function() {
                $(this).find('th:last, td:last').remove();
            });

            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Transaction History - ${accountName}</title>
                        <link href="assets/css/style.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            .print-header { text-align: center; margin-bottom: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 8px; border: 1px solid #ddd; }
                            th { background-color: #f5f5f5; }
                            @media print {
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h3>Transaction History</h3>
                            <h4>${accountName}</h4>
                            <p>Generated on ${new Date().toLocaleString()}</p>
                        </div>
                        ${table[0].outerHTML}
                        <div class="no-print" style="margin-top: 20px; text-align: center;">
                            <button onclick="window.print();return false;" style="padding: 10px 20px;">Print</button>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
        });
    }

    // Print client transactions
    const printClientTransactionsBtn = document.getElementById('printClientTransactionsBtn');
    if (printClientTransactionsBtn) {
        printClientTransactionsBtn.addEventListener('click', function() {
            const modal = $(this).closest('.modal');
            const clientName = modal.find('#clientNameDisplay').text();
            const table = modal.find('table').clone();
            
            // Remove action column for printing
            table.find('tr').each(function() {
                $(this).find('th:last, td:last').remove();
            });

            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Client Transaction History - ${clientName}</title>
                        <link href="assets/css/style.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            .print-header { text-align: center; margin-bottom: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 8px; border: 1px solid #ddd; }
                            th { background-color: #f5f5f5; }
                            @media print {
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h3>Client Transaction History</h3>
                            <h4>${clientName}</h4>
                            <p>Generated on ${new Date().toLocaleString()}</p>
                        </div>
                        ${table[0].outerHTML}
                        <div class="no-print" style="margin-top: 20px; text-align: center;">
                            <button onclick="window.print();return false;" style="padding: 10px 20px;">Print</button>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
        });
    }

    // Print supplier transactions
    const printSupplierTransactionsBtn = document.getElementById('printSupplierTransactionsBtn');
    if (printSupplierTransactionsBtn) {
        printSupplierTransactionsBtn.addEventListener('click', function() {
            const modal = $(this).closest('.modal');
            const supplierName = modal.find('#supplierNameDisplay').text();
            const table = modal.find('table').clone();
            
            // Remove action column for printing
            table.find('tr').each(function() {
                $(this).find('th:last, td:last').remove();
            });

            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Supplier Transaction History - ${supplierName}</title>
                        <link href="assets/css/style.css" rel="stylesheet">
                        <style>
                            body { padding: 20px; }
                            .print-header { text-align: center; margin-bottom: 20px; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { padding: 8px; border: 1px solid #ddd; }
                            th { background-color: #f5f5f5; }
                            @media print {
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h3>Supplier Transaction History</h3>
                            <h4>${supplierName}</h4>
                            <p>Generated on ${new Date().toLocaleString()}</p>
                        </div>
                        ${table[0].outerHTML}
                        <div class="no-print" style="margin-top: 20px; text-align: center;">
                            <button onclick="window.print();return false;" style="padding: 10px 20px;">Print</button>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
        });
    }
}); 