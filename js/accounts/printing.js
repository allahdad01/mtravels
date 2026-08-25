document.addEventListener('DOMContentLoaded', function() {
    function txnMarkNegativeBalances(table) {
        table.find('td.txn-balance').each(function () {
            const text = $(this).text().replace(/[^0-9.-]/g, '');
            const num = parseFloat(text);
            if (!isNaN(num) && num < 0) {
                $(this).addClass('txn-neg');
            }
        });
    }

    function printWithAllTransactions(title, accountName, accountType, accountId, filters, columns) {
        const printBtn = event.target.closest('button');
        const originalHtml = printBtn.innerHTML;
        printBtn.disabled = true;
        printBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1"></span>Loading all transactions...';

        const baseUrl = accountType === 'main'
            ? `../api/accounts/get_main_account_transactions.php?account_id=${accountId}`
            : accountType === 'supplier'
            ? `../api/accounts/get_supplier_transactions_main.php?supplier_id=${accountId}`
            : `../api/accounts/get_client_transactions.php?client_id=${accountId}`;

        let url = baseUrl + '&per_page=100000';
        if (filters) {
            if (filters.currency && filters.currency !== 'all') url += '&currency=' + encodeURIComponent(filters.currency);
            if (filters.receipt) url += '&receipt=' + encodeURIComponent(filters.receipt);
            if (filters.dateRange) {
                const parts = filters.dateRange.split(' - ');
                if (parts.length === 2) {
                    url += '&startDate=' + encodeURIComponent(parts[0].trim()) + '&endDate=' + encodeURIComponent(parts[1].trim());
                }
            }
        }

        fetch(url)
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(data => {
                printBtn.disabled = false;
                printBtn.innerHTML = originalHtml;

                const transactions = Array.isArray(data) ? data : (data?.data ?? []);
                if (!transactions.length) {
                    alert('No transactions to export.');
                    return;
                }

                // Sort ascending (oldest first) by id for printed report
                transactions.sort(function(a, b) {
                    return (a.id || 0) - (b.id || 0);
                });

                let rows = '';
                transactions.forEach(function(t, i) {
                    const dateField = t.transaction_date || t.created_at;
                    const formattedDate = dateField ? new Date(dateField).toLocaleString() : '—';
                    const amount = parseFloat(t.amount || 0);
                    const absFormatted = Math.abs(amount).toFixed(3);
                    const sym = txnCurrencySymbol(t.currency);

                    const isCredit = (v) => v && v.toLowerCase() === 'credit';
                    const isDebit  = (v) => v && v.toLowerCase() === 'debit';
                    const typeRaw = t.type || t.transaction_type || '';

                    const debitVal = isDebit(typeRaw) ? sym + absFormatted : '—';
                    const creditVal = isCredit(typeRaw) ? sym + absFormatted : '—';

                    let desc = '', category = '', reference = '', balance = '';

                    if (accountType === 'main') {
                        desc = t.description || '—';
                        category = t.receipt || '—';
                        reference = t.reference_name || t.reference_id || '—';
                        balance = t.balance != null ? sym + parseFloat(t.balance).toFixed(3) : '—';
                    } else if (accountType === 'supplier') {
                        desc = t.remarks || '—';
                        category = txnFormatOf(t.transaction_of);
                        reference = t.reference_name || t.reference_id || '—';
                        balance = t.balance != null ? sym + parseFloat(t.balance).toFixed(3) : '—';
                    } else {
                        desc = t.description || '—';
                        category = txnFormatOf(t.transaction_of);
                        reference = t.reference_name || t.reference_id || '—';
                        balance = t.balance || '—';
                    }

                    let row = '<tr>';
                    columns.forEach(function(col) {
                        switch(col) {
                            case '#': row += '<td>' + (i + 1) + '</td>'; break;
                            case 'Date': row += '<td>' + formattedDate + '</td>'; break;
                            case 'Description': row += '<td>' + desc + '</td>'; break;
                            case 'Receipt': row += '<td>' + (t.receipt || t.receipt_number || '—') + '</td>'; break;
                            case 'Category': row += '<td>' + category + '</td>'; break;
                            case 'Reference': row += '<td>' + reference + '</td>'; break;
                            case 'Debit': row += '<td style="color:#c00;font-weight:700">' + debitVal + '</td>'; break;
                            case 'Credit': row += '<td style="color:#16a34a;font-weight:700">' + creditVal + '</td>'; break;
                            case 'Balance': row += '<td>' + balance + '</td>'; break;
                            case 'Currency': row += '<td>' + (t.currency || '—') + '</td>'; break;
                        }
                    });
                    row += '</tr>';
                    rows += row;
                });

                // Calculate totals
                let totalDebit = 0, totalCredit = 0, lastBalance = 0;
                let primaryCurrency = transactions[0]?.currency || 'USD';
                let lastCurrency = primaryCurrency;
                transactions.forEach(function(t) {
                    const amount = parseFloat(t.amount || 0);
                    const typeRaw = (t.type || t.transaction_type || '').toLowerCase();
                    const tCurrency = t.currency || primaryCurrency;
                    if (typeRaw === 'debit') {
                        totalDebit += Math.abs(amount);
                    } else if (typeRaw === 'credit') {
                        totalCredit += Math.abs(amount);
                    }
                    if (t.balance != null) {
                        lastBalance = parseFloat(t.balance);
                        lastCurrency = tCurrency;
                    }
                });

                const s = 'padding:8px;border:1px solid #ddd;text-align:right;font-family:\'Courier New\',monospace;font-size:12px;font-weight:700;background:#f8f9fa;';

                let summaryRow = '<tr class="summary-row">';
                columns.forEach(function(col) {
                    switch(col) {
                        case 'Debit':
                            summaryRow += '<td style="' + s + 'color:#c00">Debit: ' + txnCurrencySymbol(primaryCurrency) + totalDebit.toFixed(3) + '</td>';
                            break;
                        case 'Credit':
                            summaryRow += '<td style="' + s + 'color:#16a34a">Credit: ' + txnCurrencySymbol(primaryCurrency) + totalCredit.toFixed(3) + '</td>';
                            break;
                        case 'Balance':
                            summaryRow += '<td style="' + s + 'color:' + (lastBalance < 0 ? '#c00' : '#16a34a') + '"><strong>Balance: ' + txnCurrencySymbol(lastCurrency) + lastBalance.toFixed(3) + '</strong></td>';
                            break;
                        case '#':
                            summaryRow += '<td colspan="1" style="' + s.replace('text-align:right', 'text-align:left') + 'font-family:Arial,Helvetica,sans-serif;" rowspan="1"><strong>Total</strong></td>';
                            break;
                        default:
                            summaryRow += '<td style="' + s + '"></td>';
                            break;
                    }
                });
                summaryRow += '</tr>';

                const headers = columns.map(function(c) {
                    return '<th style="background:#f5f5f5;padding:8px;border:1px solid #ddd;text-align:right;font-size:12px;white-space:nowrap">' + c + '</th>';
                }).join('');

                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${title} - ${accountName}</title>
                            <style>
                                body { padding: 20px; font-family: Arial, Helvetica, sans-serif; color: #222; }
                                .print-header { text-align: center; margin-bottom: 20px; }
                                .print-header h3 { margin: 0 0 4px 0; font-size: 18px; }
                                .print-header h4 { margin: 0 0 4px 0; font-size: 15px; color: #555; }
                                .print-header p { margin: 2px 0; color: #666; font-size: 12px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                                th { background-color: #f5f5f5; padding: 8px; border: 1px solid #ddd; text-align: right; font-size: 11px; }
                                td { padding: 7px 8px; border: 1px solid #ddd; text-align: right; font-family: 'Courier New', monospace; font-size: 12px; word-wrap: break-word; }
                                tr.summary-row td { background: #f8f9fa; font-weight: 700; border-top: 2px solid #333; }
                                @media print { .no-print { display: none; } }
                            </style>
                        </head>
                        <body>
                            <div class="print-header">
                                <h3>${title}</h3>
                                <h4>${accountName}</h4>
                                <p>Generated on ${new Date().toLocaleString()} &mdash; ${transactions.length} transactions</p>
                            </div>
                            <table>
                                <thead><tr>${headers}</tr></thead>
                                <tbody>${rows}${summaryRow}</tbody>
                            </table>
                            <div class="no-print" style="margin-top: 20px; text-align: center;">
                                <button onclick="window.print();return false;" style="padding: 10px 20px;">Print</button>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
            })
            .catch(err => {
                printBtn.disabled = false;
                printBtn.innerHTML = originalHtml;
                alert('Error loading transactions: ' + err.message);
            });
    }

    // Print main account transactions
    const printTransactionsBtn = document.getElementById('printTransactionsBtn');
    if (printTransactionsBtn) {
        printTransactionsBtn.addEventListener('click', function(e) {
            const modal = $(this).closest('.modal');
            const accountName = modal.find('#accountNameDisplay').text();
            const accountId = document.getElementById('mainAccountTransactionId')?.value;
            if (!accountId) { alert('No account selected'); return; }

            const filters = {};
            const curEl = document.getElementById('mainAccountCurrencyFilter');
            const recEl = document.getElementById('receiptSearch');
            const dateEl = document.getElementById('dateRangeFilter');
            if (curEl) filters.currency = curEl.value;
            if (recEl) filters.receipt = recEl.value;
            if (dateEl) filters.dateRange = dateEl.value;

            printWithAllTransactions(
                'Transaction History', accountName, 'main', accountId, filters,
                ['#', 'Date', 'Description', 'Receipt', 'Reference', 'Debit', 'Credit', 'Balance', 'Currency']
            );
        });
    }

    // Print client transactions
    const printClientTransactionsBtn = document.getElementById('printClientTransactionsBtn');
    if (printClientTransactionsBtn) {
        printClientTransactionsBtn.addEventListener('click', function(e) {
            const modal = $(this).closest('.modal');
            const clientName = modal.find('#clientNameDisplay').text();
            const clientId = document.getElementById('clientTransactionId')?.value;
            if (!clientId) { alert('No client selected'); return; }

            const filters = {};
            const curEl = document.getElementById('clientCurrencyFilter');
            const recEl = document.getElementById('clientReceiptSearch');
            const dateEl = document.getElementById('clientDateRangeFilter');
            if (curEl) filters.currency = curEl.value;
            if (recEl) filters.receipt = recEl.value;
            if (dateEl) filters.dateRange = dateEl.value;

            printWithAllTransactions(
                'Client Transaction History', clientName, 'client', clientId, filters,
                ['#', 'Date', 'Description', 'Receipt', 'Category', 'Reference', 'Debit', 'Credit', 'Balance', 'Currency']
            );
        });
    }

    // Print supplier transactions
    const printSupplierTransactionsBtn = document.getElementById('printSupplierTransactionsBtn');
    if (printSupplierTransactionsBtn) {
        printSupplierTransactionsBtn.addEventListener('click', function(e) {
            const modal = $(this).closest('.modal');
            const supplierName = modal.find('#supplierTransNameDisplay').text();
            const supplierId = document.getElementById('supplierTransactionId')?.value;
            if (!supplierId) { alert('No supplier selected'); return; }

            const filters = {};
            const recEl = document.getElementById('supplierReceiptSearch');
            const dateEl = document.getElementById('supplierDateRangeFilter');
            if (recEl) filters.receipt = recEl.value;
            if (dateEl) filters.dateRange = dateEl.value;

            printWithAllTransactions(
                'Supplier Transaction History', supplierName, 'supplier', supplierId, filters,
                ['#', 'Date', 'Description', 'Receipt', 'Category', 'Reference', 'Debit', 'Credit', 'Balance']
            );
        });
    }

    // Print section summaries
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
                        @media print { .no-print { display: none; } }
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
