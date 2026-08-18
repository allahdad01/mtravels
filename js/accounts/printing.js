document.addEventListener('DOMContentLoaded', function() {
    // Mark negative balance cells (red) on the table being printed
    function txnMarkNegativeBalances(table) {
        table.find('td.txn-balance').each(function () {
            const text = $(this).text().replace(/[^0-9.-]/g, '');
            const num = parseFloat(text);
            if (!isNaN(num) && num < 0) {
                $(this).addClass('txn-neg');
            }
        });
    }

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
            txnMarkNegativeBalances(table);

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
                            td.txn-neg { color: #c00; font-weight: 700; }
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
            txnMarkNegativeBalances(table);

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
                            td.txn-neg { color: #c00; font-weight: 700; }
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
            const supplierName = modal.find('#supplierTransNameDisplay').text();
            const table = modal.find('table').clone();
            
            // Remove action column for printing
            table.find('tr').each(function() {
                $(this).find('th:last, td:last').remove();
            });
            txnMarkNegativeBalances(table);

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
                            td.txn-neg { color: #c00; font-weight: 700; }
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

    // Print section summaries (all accounts in a section with balances)
    function acFmtCurrency(v) {
        const n = parseFloat(v) || 0;
        return Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function acBalCell(v, prefix) {
        const n = parseFloat(v) || 0;
        const cls = n < 0 ? 'neg' : '';
        return '<td class="' + cls + '">' + (n < 0 ? '-' : '') + prefix + acFmtCurrency(n) + '</td>';
    }
    function acNameCell(name) {
        return '<td class="name">' + name + '</td>';
    }
    function acPrintSectionSummary(title, headers, buildRows) {
        const rows = buildRows();
        if (!rows.length) {
            alert('No accounts to print in this section.');
            return;
        }
        let html = '';
        rows.forEach(function(row) {
            html += '<tr>' + row.join('') + '</tr>';
        });
        const now = new Date().toLocaleString();
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>${title}</title>
                    <style>
                        body { padding: 20px; font-family: Arial, Helvetica, sans-serif; color: #222; }
                        .print-header { text-align: center; margin-bottom: 20px; }
                        .print-header h3 { margin: 0 0 4px 0; font-size: 18px; }
                        .print-header p { margin: 2px 0; color: #666; font-size: 12px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
                        th { background-color: #f5f5f5; padding: 8px; border: 1px solid #ddd; text-align: right; font-size: 12px; }
                        td { padding: 7px 8px; border: 1px solid #ddd; text-align: right; font-family: 'Courier New', monospace; }
                        td.name { text-align: left; font-family: Arial, Helvetica, sans-serif; font-weight: 600; }
                        td.neg { color: #c00; font-weight: 700; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h3>${title}</h3>
                        <p>Generated on ${now}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>${headers.map(function(h, i) { return '<th' + (i === 0 ? ' style="text-align:left"' : '') + '>' + h + '</th>'; }).join('')}</tr>
                        </thead>
                        <tbody>${html}</tbody>
                    </table>
                    <div class="no-print" style="margin-top: 20px; text-align: center;">
                        <button onclick="window.print();return false;" style="padding: 10px 20px;">Print</button>
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
    }

    document.querySelectorAll('.ac-print-summary').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const section = this.dataset.section;
            if (section === 'main') {
                const cards = document.querySelectorAll('#acMainBody .ac-main-card');
                const rows = [];
                cards.forEach(function(card) {
                    const usd = parseFloat(card.dataset.usd) || 0;
                    const afs = parseFloat(card.dataset.afs) || 0;
                    const eur = parseFloat(card.dataset.euro) || 0;
                    const aed = parseFloat(card.dataset.aed) || 0;
                    const sar = parseFloat(card.dataset.sar) || 0;
                    rows.push([acNameCell(card.dataset.accountName), acBalCell(usd, '$'), acBalCell(afs, '؋'), acBalCell(eur, '€'), acBalCell(aed, 'AED '), acBalCell(sar, 'SAR ')]);
                });
                acPrintSectionSummary('Main Accounts Summary', ['Account', 'USD', 'AFS', 'EUR', 'AED', 'SAR'],
                    function() { return rows; });
            } else if (section === 'supplier') {
                const cards = document.querySelectorAll('#acSupplierList .ac-list-card');
                const rows = [];
                function acSym(cur) { return cur === 'USD' ? '$' : cur === 'EUR' ? '€' : cur === 'AED' ? 'AED ' : cur === 'SAR' ? 'SAR ' : '؋'; }
                cards.forEach(function(card) {
                    const cur = card.dataset.currency || 'AFS';
                    const bal = parseFloat(card.dataset.balance) || 0;
                    rows.push([acNameCell(card.dataset.supplierName), '<td>' + cur + '</td>', acBalCell(bal, acSym(cur))]);
                });
                acPrintSectionSummary('Supplier Accounts Summary', ['Supplier', 'Currency', 'Balance'],
                    function() { return rows; });
            } else if (section === 'client') {
                const cards = document.querySelectorAll('#acClientList .ac-list-card');
                const rows = [];
                cards.forEach(function(card) {
                    const usd = parseFloat(card.dataset.usdBalance) || 0;
                    const afs = parseFloat(card.dataset.afsBalance) || 0;
                    rows.push([acNameCell(card.dataset.clientName), acBalCell(usd, '$'), acBalCell(afs, '؋')]);
                });
                acPrintSectionSummary('Client Accounts Summary', ['Client', 'USD Balance', 'AFS Balance'],
                    function() { return rows; });
            }
        });
    });
}); 
