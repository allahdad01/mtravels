// ── Helpers ────────────────────────────────────────────────────────────────

function txnCurrencySymbol(currency) {
    const map = { USD: '$', AFS: '؋', EUR: '€', DARHAM: 'AED' };
    return map[currency] || '';
}

function txnCurrencyBadge(currency) {
    const key = currency === 'DARHAM' ? 'AED' : (currency || 'OTHER');
    const valid = ['USD', 'AFS', 'EUR', 'AED'];
    const cls = valid.includes(key) ? `txn-badge-${key}` : 'txn-badge-OTHER';
    return `<span class="txn-badge ${cls}">${key || '—'}</span>`;
}

function txnFormatDate(dateField) {
    if (!dateField) return '—';
    return new Date(dateField).toLocaleString();
}

function txnFormatOf(raw) {
    if (!raw || raw === '-') return '—';
    return raw.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

// ── Row builders ────────────────────────────────────────────────────────────

function txnBuildActionsCell(transaction, accountType, amount, dateField) {
    let showDelete = false;
    let showEdit   = false;
    let showReceipt = false;
    let showPrintReceipt = false;

    if (accountType === 'main') {
        const tof = (transaction.transaction_of || '').toLowerCase();
        showDelete  = isUserAdmin && ['fund', 'transfer', 'supplier_bonus', 'withdraw_fund'].includes(tof);
        showEdit    = tof === 'fund' || tof === 'withdraw_fund';
        showReceipt = true;
        showPrintReceipt = false;
    } else if (accountType === 'supplier') {
        const tof = (transaction.transaction_of || '').toLowerCase();
        showDelete = isUserAdmin && ['supplier_bonus', 'fund', 'fund_withdrawal'].includes(tof);
        showReceipt = true;
        showPrintReceipt = false;
    } else if (accountType === 'client') {
        const tof = (transaction.transaction_of || '').toLowerCase();
        showDelete = isUserAdmin && (tof === 'fund' || tof === 'client_withdrawal');
        showEdit   = tof === 'fund' || tof === 'client_withdrawal';
        showReceipt = true;
        showPrintReceipt = true;
    }

    if (!showDelete && !showEdit && !showReceipt) {
        return `<td class="txn-td-num"><span class="txn-empty" style="font-size:12px">—</span></td>`;
    }

    const abs  = Math.abs(amount).toFixed(3);
    const type = transaction.type || transaction.transaction_type || '';

    let html = `<td><div class="txn-actions">`;

    if (showEdit) {
        html += `<button class="txn-btn txn-btn-edit edit-transaction-btn"
            data-transaction-id="${transaction.id}"
            data-transaction-type="${accountType}"
            data-amount="${abs}"
            data-transaction-date="${dateField || ''}"
            data-description="${transaction.description || ''}"
            data-currency="${transaction.currency || ''}"
            data-remarks="${transaction.remarks || ''}"
            data-receipt="${transaction.receipt || ''}"
            data-type="${type}"
            title="Edit transaction">
            <i class="feather icon-edit-2"></i>
        </button>`;
    }

    if (showReceipt) {
        html += `<button class="txn-btn txn-btn-receipt edit-receipt-btn"
            data-transaction-id="${transaction.id}"
            data-transaction-type="${accountType}"
            data-receipt="${transaction.receipt || ''}"
            data-transaction-date="${dateField || ''}"
            title="Edit receipt">
            <i class="feather icon-file-text"></i>
        </button>`;
    }
    
    if (showPrintReceipt) {
        html += `<button class="txn-btn txn-btn-print print-receipt-btn"
            data-transaction-id="${transaction.id}"
            data-transaction-type="${accountType}"
            title="Print receipt">
            <i class="feather icon-printer"></i>
        </button>`;
    }

    if (showDelete) {
        html += `<button class="txn-btn txn-btn-del delete-transaction-btn"
            data-transaction-id="${transaction.id}"
            data-transaction-type="${accountType}"
            title="Delete transaction">
            <i class="feather icon-trash-2"></i>
        </button>`;
    }

    html += `</div></td>`;
    return html;
}

function txnBuildRow(transaction, accountType, rowNumber) {
    const dateField     = transaction.transaction_date || transaction.created_at;
    const formattedDate = txnFormatDate(dateField);
    const amount        = parseFloat(transaction.amount || 0);
    const absFormatted  = Math.abs(amount).toFixed(3);
    const sym           = txnCurrencySymbol(transaction.currency);
    const badge         = txnCurrencyBadge(transaction.currency);
    const actionsCell   = txnBuildActionsCell(transaction, accountType, amount, dateField);

    const isCredit = (v) => v && v.toLowerCase() === 'credit';
    const isDebit  = (v) => v && v.toLowerCase() === 'debit';
    const typeRaw  = transaction.type || transaction.transaction_type || '';

    const creditVal = isCredit(typeRaw) ? `<span class="txn-credit">${sym}${absFormatted}</span>` : `<span class="txn-empty">—</span>`;
    const debitVal  = isDebit(typeRaw)  ? `<span class="txn-debit">${sym}${absFormatted}</span>`  : `<span class="txn-empty">—</span>`;

    const row = document.createElement('tr');

    if (accountType === 'main') {
        const bal = transaction.balance != null ? `${sym}${parseFloat(transaction.balance).toFixed(3)}` : '—';
        row.innerHTML = `
            <td class="txn-td-rn">${rowNumber}</td>
            <td class="txn-td-date">${formattedDate}</td>
            <td class="txn-td-desc" style="word-wrap:break-word;white-space:normal;max-width:250px">${transaction.description || '—'}</td>
            <td class="txn-td-muted">${transaction.receipt || '—'}</td>
            <td class="txn-td-muted" style="word-wrap:break-word;white-space:normal;max-width:200px">${transaction.reference_name || transaction.reference_id || '—'}</td>
            <td class="txn-td-num">${debitVal}</td>
            <td class="txn-td-num">${creditVal}</td>
            <td class="txn-td-num txn-balance">${bal}</td>
            <td>${badge}</td>
            ${actionsCell}
        `;

    } else if (accountType === 'supplier') {
        const bal = transaction.balance != null ? `${sym}${parseFloat(transaction.balance).toFixed(3)}` : '—';
        // Supplier uses Credit/Debit (capital)
        const sCreditVal = isCredit(typeRaw) ? `<span class="txn-credit">${sym}${absFormatted}</span>` : `<span class="txn-empty">—</span>`;
        const sDebitVal  = isDebit(typeRaw)  ? `<span class="txn-debit">${sym}${absFormatted}</span>`  : `<span class="txn-empty">—</span>`;

        row.innerHTML = `
            <td class="txn-td-rn">${rowNumber}</td>
            <td class="txn-td-date">${formattedDate}</td>
            <td class="txn-td-desc" style="word-wrap:break-word;white-space:normal;max-width:250px">${transaction.remarks || '—'}</td>
            <td class="txn-td-muted">${transaction.receipt || '—'}</td>
            <td class="txn-td-muted">${txnFormatOf(transaction.transaction_of)}</td>
            <td class="txn-td-muted" style="word-wrap:break-word;white-space:normal;max-width:200px">${transaction.reference_name || transaction.reference_id || '—'}</td>
            <td class="txn-td-num">${sDebitVal}</td>
            <td class="txn-td-num">${sCreditVal}</td>
            <td class="txn-td-num txn-balance">${bal}</td>
            ${actionsCell}
        `;

    } else if (accountType === 'client') {
        const bal = transaction.balance || '—';
        row.innerHTML = `
            <td class="txn-td-rn">${rowNumber}</td>
            <td class="txn-td-date">${formattedDate}</td>
            <td class="txn-td-desc" style="word-wrap:break-word;white-space:normal;max-width:250px">${transaction.description || '—'}</td>
            <td class="txn-td-muted">${transaction.receipt || transaction.receipt_number || '—'}</td>
            <td class="txn-td-muted">${txnFormatOf(transaction.transaction_of)}</td>
            <td class="txn-td-muted" style="word-wrap:break-word;white-space:normal;max-width:200px">${transaction.reference_name || transaction.reference_id || '—'}</td>
            <td class="txn-td-num">${debitVal}</td>
            <td class="txn-td-num">${creditVal}</td>
            <td class="txn-td-num txn-balance">${bal}</td>
            <td>${badge}</td>
            ${actionsCell}
        `;
    }

    return row;
}

// ── Pagination builder ──────────────────────────────────────────────────────

function txnBuildPagination(accountType, accountId, accountName, pagination, listId, containerId, totalCount) {
    const list      = document.getElementById(listId);
    const container = document.getElementById(containerId);
    if (!list || !container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.classList.add('d-none');
        return;
    }

    const cur   = pagination.current_page;
    const total = pagination.total_pages;
    const perPage = pagination.per_page || 20;
    const from  = (cur - 1) * perPage + 1;
    const to    = Math.min(cur * perPage, totalCount || pagination.total_records || '?');

    // Build inner pagination list (Bootstrap)
    list.innerHTML = '';

    const addLi = (label, page, active = false, disabled = false) => {
        const li = document.createElement('li');
        li.className = `page-item${active ? ' active' : ''}${disabled ? ' disabled' : ''}`;
        li.innerHTML = disabled
            ? `<span class="page-link">${label}</span>`
            : `<a class="page-link" href="javascript:void(0);"
                onclick="loadTransactionsPage('${accountType}',${accountId},'${accountName}',${page})">${label}</a>`;
        list.appendChild(li);
    };

    if (cur > 1) addLi('‹', cur - 1);

    let start = Math.max(1, cur - 2);
    let end   = Math.min(total, cur + 2);

    if (start > 1) { addLi('1', 1); if (start > 2) addLi('…', 0, false, true); }
    for (let p = start; p <= end; p++) addLi(p, p, p === cur);
    if (end < total) { if (end < total - 1) addLi('…', 0, false, true); addLi(total, total); }

    if (cur < total) addLi('›', cur + 1);

    // Info text (injected before the <ul> if a sibling element exists)
    const infoEl = container.querySelector('.txn-page-info');
    if (infoEl) infoEl.textContent = `Showing ${from}–${to} of ${pagination.total_records || totalCount || '?'}`;

    container.classList.remove('d-none');
}

// ── IDs / elements map ──────────────────────────────────────────────────────

const TXN_CONFIG = {
    main: {
        tableBody:          'transactionsTableBody',
        loader:             'transactionsLoader',
        noMsg:              'noTransactionsMessage',
        nameDisplay:        'accountNameDisplay',
        idField:            'mainAccountTransactionId',
        modal:              'transactionHistoryModal',
        skeletonCols:       10,
        paginationList:     'mainTransactionsPaginationList',
        paginationContainer:'transactionsPagination',
        endpoint:           (id) => `../api/accounts/get_main_account_transactions.php?account_id=${id}`,
        filters: () => ({
            currency: document.getElementById('mainAccountCurrencyFilter')?.value,
            receipt:  document.getElementById('receiptSearch')?.value,
            dateRange:document.getElementById('dateRangeFilter')?.value,
        }),
    },
    supplier: {
        tableBody:          'supplierTransactionsTableBody',
        loader:             'supplierTransactionsLoader',
        noMsg:              'noSupplierTransactionsMessage',
        nameDisplay:        'supplierTransNameDisplay',
        idField:            'supplierTransactionId',
        modal:              'supplierTransactionHistoryModal',
        skeletonCols:       9,
        paginationList:     'supplierTransactionsPaginationList',
        paginationContainer:'supplierTransactionsPagination',
        endpoint:           (id) => `../api/accounts/get_supplier_transactions_main.php?supplier_id=${id}`,
        filters: () => ({
            receipt:  document.getElementById('supplierReceiptSearch')?.value,
            dateRange:document.getElementById('supplierDateRangeFilter')?.value,
        }),
    },
    client: {
        tableBody:          'clientTransactionsTableBody',
        loader:             'clientTransactionsLoader',
        noMsg:              'noClientTransactionsMessage',
        nameDisplay:        'clientNameDisplay',
        idField:            'clientTransactionId',
        modal:              'clientTransactionHistoryModal',
        skeletonCols:       10,
        paginationList:     'clientTransactionsPaginationList',
        paginationContainer:'clientTransactionsPagination',
        endpoint:           (id) => `../api/accounts/get_client_transactions.php?client_id=${id}`,
        filters: () => ({
            currency: document.getElementById('clientCurrencyFilter')?.value,
            receipt:  document.getElementById('clientReceiptSearch')?.value,
            dateRange:document.getElementById('clientDateRangeFilter')?.value,
        }),
    },
};

function txnBuildEndpoint(base, filters, page) {
    let url = base;
    const { currency, receipt, dateRange } = filters;
    if (currency && currency !== 'all') url += '&currency=' + encodeURIComponent(currency);
    if (receipt)   url += '&receipt='   + encodeURIComponent(receipt);
    if (dateRange) {
        const parts = dateRange.split(' - ');
        if (parts.length === 2)
            url += '&startDate=' + encodeURIComponent(parts[0].trim()) + '&endDate=' + encodeURIComponent(parts[1].trim());
    }
    if (page) url += '&page=' + page;
    return url;
}

// ── Skeleton rows for the transaction table ────────────────────────────────

function txnBuildSkeleton(cols, rows) {
    const widths = [42, 58, 78, 66, 52, 44, 38, 62, 48, 30];
    let html = '';
    for (let r = 0; r < rows; r++) {
        html += '<tr class="txn-skel-row">';
        for (let c = 0; c < cols; c++) {
            html += '<td class="txn-skel-cell"><div class="txn-skel-bar" style="width:' + (widths[c % widths.length]) + '%"></div></td>';
        }
        html += '</tr>';
    }
    return html;
}

// ── Core loader ─────────────────────────────────────────────────────────────

function loadTransactions(accountType, accountId, accountName, page) {
    const cfg = TXN_CONFIG[accountType];
    if (!cfg) return;

    const tableBody = document.getElementById(cfg.tableBody);
    const loader    = document.getElementById(cfg.loader);
    const noMsg     = document.getElementById(cfg.noMsg);

    // Store account id for filter reuse
    let idField = document.getElementById(cfg.idField);
    if (!idField) {
        idField = document.createElement('input');
        idField.type = 'hidden';
        idField.id   = cfg.idField;
        document.body.appendChild(idField);
    }
    idField.value = accountId;

    document.getElementById(cfg.nameDisplay).textContent = accountName;

    // Reset UI
    loader.classList.add('d-none');
    noMsg.classList.add('d-none');
    tableBody.innerHTML = txnBuildSkeleton(cfg.skeletonCols || 10, 8);

    // Open modal
    const modal = new bootstrap.Modal(document.getElementById(cfg.modal));
    modal.show();

    // Build URL
    const base     = cfg.endpoint(accountId);
    const filters  = cfg.filters();
    const endpoint = txnBuildEndpoint(base, filters, page);

    fetch(endpoint)
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            loader.classList.add('d-none');
            tableBody.innerHTML = '';

            const transactions = Array.isArray(data) ? data : (data?.data ?? []);
            const pagination   = data?.pagination ?? null;

            if (!transactions.length) {
                noMsg.classList.remove('d-none');
                noMsg.innerHTML = `
                    <i class="feather icon-inbox" style="font-size:2rem;color:#ced4da;display:block;margin-bottom:8px"></i>
                    <span>No transactions found</span>`;
                return;
            }

            let rowNumber = 1;
            transactions.forEach(t => {
                tableBody.appendChild(txnBuildRow(t, accountType, rowNumber++));
            });

            txnBuildPagination(
                accountType, accountId, accountName,
                pagination,
                cfg.paginationList,
                cfg.paginationContainer,
                transactions.length
            );

            attachDeleteButtonListeners();
            attachEditButtonListeners();
            attachEditReceiptButtonListeners();
            attachPrintReceiptButtonListeners();
        })
        .catch(err => {
            loader.classList.add('d-none');
            noMsg.classList.remove('d-none');
            noMsg.innerHTML = `
                <i class="feather icon-alert-circle text-danger" style="font-size:2rem;display:block;margin-bottom:8px"></i>
                <span class="text-danger">Error loading transactions: ${err.message}</span>`;
            showErrorToast('error_fetching_transactions: ' + err);
        });
}

// Alias used by pagination links
function loadTransactionsPage(accountType, accountId, accountName, page) {
    loadTransactions(accountType, accountId, accountName, page);
}

// ── Event listeners ─────────────────────────────────────────────────────────

function attachDeleteButtonListeners() {
    document.querySelectorAll('.delete-transaction-btn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', function (e) {
            e.stopPropagation();
            const id   = this.dataset.transactionId;
            const type = this.dataset.transactionType;
            document.getElementById('deleteTransactionId').value   = id;
            document.getElementById('deleteTransactionType').value = type;
            const modalId = type === 'main'     ? 'transactionHistoryModal'
                          : type === 'supplier' ? 'supplierTransactionHistoryModal'
                          :                       'clientTransactionHistoryModal';
            $(`#${modalId}`).modal('hide');
            setTimeout(() => deleteTransaction(id, type), 300);
        });
    });
}

function attachEditButtonListeners() {
    document.querySelectorAll('.edit-transaction-btn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', function (e) {
            e.stopPropagation();
            const d = this.dataset;
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
            set('editTransactionId',         d.transactionId);
            set('editTransactionType',       d.transactionType);
            set('originalAmount',            d.amount);
            set('originalType',              d.type);
            set('editTransactionAmount',     d.amount);
            set('editTransactionCurrency',   d.currency);
            set('editTransactionDescription',d.description);
            set('editTransactionReceipt',    d.receipt);
            set('editTransactionCurrencyHidden', d.currency);
            set('editTransactionTypeHidden',     d.type.toLowerCase());
            const typeSelect = document.getElementById('editTransactionTypeSelect');
            if (typeSelect) typeSelect.value = d.type.toLowerCase();
            if (d.transactionDate) {
                const el = document.getElementById('editTransactionDate');
                if (el) el.value = new Date(d.transactionDate).toISOString().slice(0, 16);
            }
            $('#transactionHistoryModal').modal('hide');
            setTimeout(() => new bootstrap.Modal(document.getElementById('editTransactionModal')).show(), 500);
        });
    });
}

function attachEditReceiptButtonListeners() {
    document.querySelectorAll('.edit-receipt-btn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', function (e) {
            e.stopPropagation();
            const d = this.dataset;
            const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
            set('editReceiptTransactionId',   d.transactionId);
            set('editReceiptTransactionType', d.transactionType);
            set('editReceiptNumber',          d.receipt);
            $('#transactionHistoryModal').modal('hide');
            setTimeout(() => $('#editReceiptModal').modal('show'), 500);
        });
    });
}

function attachPrintReceiptButtonListeners() {
    document.querySelectorAll('.print-receipt-btn').forEach(btn => {
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', function (e) {
            e.stopPropagation();
            const transactionId = this.dataset.transactionId;
            const transactionType = this.dataset.transactionType;
            
            let printUrl = '';
            if (transactionType === 'main') {
                printUrl = `../api/ticket/print_receipt.php?id=${transactionId}`;
            } else if (transactionType === 'supplier') {
                printUrl = `../api/accounts/print_fund_receipt.php?id=${transactionId}&type=supplier`;
            } else if (transactionType === 'client') {
                printUrl = `../api/accounts/print_fund_receipt.php?id=${transactionId}&type=client`;
            }
            
            if (printUrl) {
                window.open(printUrl, '_blank', 'width=1000,height=800');
            }
        });
    });
}

// ── Delete + save handlers ──────────────────────────────────────────────────

function deleteTransaction(transactionId, transactionType) {
    const endpoints = {
        main:     '../api/accounts/delete_main_account_transaction.php',
        supplier: '../api/accounts/delete_supplier_transaction.php',
        client:   '../api/accounts/delete_client_transaction.php',
    };
    const endpoint = endpoints[transactionType];
    if (!endpoint) { showErrorToast('invalid_transaction_type'); return; }

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            transaction_id:   transactionId,
            transaction_type: transactionType,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showSuccessToast('transaction_deleted_successfully');
            location.reload();
        } else {
            showErrorToast('error: ' + data.message);
        }
    })
    .catch(err => showErrorToast('error_deleting_transaction: ' + err));
}

// ── DOMContentLoaded ────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.view-transactions-btn').forEach(btn =>
        btn.addEventListener('click', function () {
            loadTransactions('main', this.dataset.accountId, this.dataset.accountName);
        })
    );

    document.querySelectorAll('.view-supplier-transactions-btn').forEach(btn =>
        btn.addEventListener('click', function () {
            loadTransactions('supplier', this.dataset.supplierId, this.dataset.supplierName);
        })
    );

    document.querySelectorAll('.view-client-transactions-btn').forEach(btn =>
        btn.addEventListener('click', function () {
            loadTransactions('client', this.dataset.clientId, this.dataset.clientName);
        })
    );

    // Save edit transaction
    document.getElementById('saveEditTransactionBtn')?.addEventListener('click', function () {
        const form     = document.getElementById('editTransactionForm');
        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        formData.append('csrf_token', csrfToken);

        this.disabled   = true;
        this.innerHTML  = '<span class="spinner-border spinner-border-sm"></span> Saving…';

        fetch('../api/accounts/update_transaction.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                this.disabled  = false;
                this.innerHTML = 'Save changes';
                if (data.success) {
                    $('#editTransactionModal').modal('hide');
                    showSuccessToast('transaction_updated_successfully');
                    loadTransactions(
                        document.getElementById('editTransactionType').value,
                        data.account_id,
                        data.account_name
                    );
                } else {
                    showErrorToast('error: ' + data.message);
                }
            })
            .catch(err => {
                this.disabled  = false;
                this.innerHTML = 'Save changes';
                showErrorToast('error_updating_transaction: ' + err);
            });
    });

    // Clear error when edit receipt modal opens
    $('#editReceiptModal').on('show.bs.modal', function () {
        document.getElementById('editReceiptError')?.classList.add('d-none');
    });

    // Save edit receipt
    document.getElementById('saveEditReceiptBtn')?.addEventListener('click', function () {
        const id      = document.getElementById('editReceiptTransactionId').value;
        const type    = document.getElementById('editReceiptTransactionType').value;
        const receipt = document.getElementById('editReceiptNumber').value.trim();

        document.getElementById('editReceiptError')?.classList.add('d-none');

        if (!receipt) { showErrorToast('please_enter_a_receipt_number'); return; }

        this.disabled  = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

        fetch('../api/accounts/update_receipt.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transaction_id:   id,
                transaction_type: type,
                receipt,
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }),
        })
        .then(r => r.json())
        .then(data => {
             this.disabled  = false;
             this.innerHTML = '<i class="feather icon-save mr-1"></i>Save Receipt';
             if (data.success) {
                 showSuccessToast('receipt_updated_successfully');
                 $('#editReceiptModal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                     $(this).off('hidden.bs.modal');
                     loadTransactions(type, data.account_id, data.account_name);
                 }).modal('hide');
             } else {
                 const errEl = document.getElementById('editReceiptError');
                 if (errEl) { errEl.textContent = data.message; errEl.classList.remove('d-none'); }
                 showErrorToast('error: ' + data.message);
             }
         })
        .catch(err => {
            this.disabled  = false;
            this.innerHTML = '<i class="feather icon-save mr-1"></i>Save Receipt';
            const errEl = document.getElementById('editReceiptError');
            if (errEl) { errEl.textContent = err.message || err; errEl.classList.remove('d-none'); }
            showErrorToast('error_updating_receipt: ' + err);
        });
    });

    // Modal z-index stacking fix
    $(document).on('show.bs.modal', '.modal', function () {
        const zIndex = 1040 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(() => {
            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
        }, 0);
    });
});