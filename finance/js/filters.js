/**
 * Enhanced Filtering and Search functionality for Accounts Page
 */

document.addEventListener('DOMContentLoaded', function() {
    initializeSearchAndFilters();
    initializeSupplierFilters();
    initializeClientFilters();
});

/**
 * Initialize all search and filter functionality
 */
function initializeSearchAndFilters() {
    const searchInput = document.getElementById('accountSearchInput');
    const typeFilter = document.getElementById('accountTypeFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            applyMainFilters();
        });
    }
    
    if (typeFilter) {
        typeFilter.addEventListener('change', function() {
            applyMainFilters();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            applyMainFilters();
            });
        }
    }
    
/**
 * Apply main filters across all account sections
 */
function applyMainFilters() {
    const searchQuery = document.getElementById('accountSearchInput').value.toLowerCase();
    const accountType = document.getElementById('accountTypeFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    // Main accounts section
    const mainAccounts = document.querySelectorAll('.account-card');
    mainAccounts.forEach(function(account) {
        const accountName = account.querySelector('h5').textContent.toLowerCase();
        const accountStatus = account.querySelector('.status-badge') ? 
            account.querySelector('.status-badge').textContent.toLowerCase() : 'active';
        
        let shouldShow = (accountName.includes(searchQuery) || searchQuery === '') && 
                         (accountType === 'all' || accountType === 'main') &&
                         (status === 'all' || accountStatus.includes(status));
        
        account.closest('.col-md-4').style.display = shouldShow ? 'block' : 'none';
    });
    
    // Filter the supplier section if showing all or suppliers
    if (accountType === 'all' || accountType === 'supplier') {
        const supplierSection = document.querySelector('.modern-card:has(#supplierTable)');
        if (supplierSection) {
            supplierSection.style.display = 'block';
            applySupplierFilters(searchQuery, status);
            }
        } else {
        const supplierSection = document.querySelector('.modern-card:has(#supplierTable)');
        if (supplierSection) {
            supplierSection.style.display = 'none';
        }
    }
    
    // Filter the client section if showing all or clients
    if (accountType === 'all' || accountType === 'client') {
        const clientCards = document.querySelectorAll('.client-card');
        clientCards.forEach(function(clientCard) {
            const clientName = clientCard.querySelector('h5').textContent.toLowerCase();
            const clientStatus = clientCard.querySelector('.status-badge') ? 
                clientCard.querySelector('.status-badge').textContent.toLowerCase() : 'active';
            
            let shouldShow = (clientName.includes(searchQuery) || searchQuery === '') && 
                            (status === 'all' || clientStatus.includes(status));
            
            clientCard.closest('.col-md-4').style.display = shouldShow ? 'block' : 'none';
        });
                } else {
        const clientCards = document.querySelectorAll('.client-card');
        clientCards.forEach(function(clientCard) {
            clientCard.closest('.col-md-4').style.display = 'none';
        });
    }
    
    // Display a "no results" message if all sections are filtered out
    checkAndDisplayNoResults();
}

/**
 * Initialize supplier-specific filters
 */
function initializeSupplierFilters() {
    const supplierSearch = document.getElementById('supplierSearchInput');
    const supplierCurrencyFilter = document.getElementById('supplierCurrencyFilter');
    const supplierBalanceFilter = document.getElementById('supplierBalanceFilter');
    
    if (supplierSearch) {
        supplierSearch.addEventListener('keyup', function() {
            const searchQuery = this.value.toLowerCase();
            applySupplierFilters(searchQuery);
        });
    }
    
    if (supplierCurrencyFilter) {
        supplierCurrencyFilter.addEventListener('change', function() {
            applySupplierFilters();
        });
    }
    
    if (supplierBalanceFilter) {
        supplierBalanceFilter.addEventListener('change', function() {
            applySupplierFilters();
        });
    }
}

/**
 * Apply filters specifically to supplier table
 */
function applySupplierFilters(globalSearchQuery = '') {
    const localSearchQuery = document.getElementById('supplierSearchInput')?.value.toLowerCase() || '';
    const searchQuery = globalSearchQuery || localSearchQuery;
    const currencyFilter = document.getElementById('supplierCurrencyFilter')?.value || 'all';
    const balanceFilter = document.getElementById('supplierBalanceFilter')?.value || 'all';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    
    // Apply filters to supplier rows
    const supplierRows = document.querySelectorAll('.supplier-row');
    let visibleCount = 0;
    
    supplierRows.forEach(function(row) {
        const supplierName = row.getAttribute('data-supplier-name').toLowerCase();
        const currency = row.getAttribute('data-supplier-currency');
        const balance = parseFloat(row.getAttribute('data-supplier-balance'));
        const status = row.querySelector('.status-badge').textContent.toLowerCase();
        
        // Check all filter conditions
        const matchesSearch = supplierName.includes(searchQuery);
        const matchesCurrency = currencyFilter === 'all' || currency === currencyFilter;
        
        let matchesBalance = true;
        if (balanceFilter === 'positive') {
            matchesBalance = balance >= 0;
        } else if (balanceFilter === 'negative') {
            matchesBalance = balance < 0;
        }
        
        const matchesStatus = statusFilter === 'all' || status.includes(statusFilter);
        
        // Show/hide row based on filter conditions
        const shouldShow = matchesSearch && matchesCurrency && matchesBalance && matchesStatus;
        row.style.display = shouldShow ? '' : 'none';
        
        if (shouldShow) visibleCount++;
    });
    
    // Show/hide no results message
    const noResultsRow = document.querySelector('#noSupplierResults');
    if (visibleCount === 0) {
        if (!noResultsRow) {
            const tbody = document.querySelector('#supplierTable tbody');
            const tr = document.createElement('tr');
            tr.id = 'noSupplierResults';
            tr.innerHTML = `<td colspan="6" class="text-center py-4">${__('no_matching_suppliers_found')}</td>`;
            tbody.appendChild(tr);
            } else {
            noResultsRow.style.display = '';
        }
    } else if (noResultsRow) {
        noResultsRow.style.display = 'none';
    }
}

/**
 * Initialize client-specific filters
 */
function initializeClientFilters() {
    const clientSearch = document.getElementById('clientSearchInput');
    const clientBalanceFilter = document.getElementById('clientBalanceFilter');
    const clientCurrencyFilter = document.getElementById('clientCurrencyType');

    if (clientSearch) {
        clientSearch.addEventListener('keyup', function() {
            applyClientFilters();
        });
    }
    
    if (clientBalanceFilter) {
        clientBalanceFilter.addEventListener('change', function() {
            applyClientFilters();
        });
    }
    
    if (clientCurrencyFilter) {
        clientCurrencyFilter.addEventListener('change', function() {
            applyClientFilters();
        });
    }
}

/**
 * Apply filters specifically to client cards
 */
function applyClientFilters(globalSearchQuery = '') {
    const localSearchQuery = document.getElementById('clientSearchInput')?.value.toLowerCase() || '';
    const searchQuery = globalSearchQuery || localSearchQuery;
    const balanceFilter = document.getElementById('clientBalanceFilter')?.value || 'all';
    const currencyFilter = document.getElementById('clientCurrencyType')?.value || 'all';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    
    const clientCards = document.querySelectorAll('.client-card');
    let visibleCount = 0;
    
    clientCards.forEach(function(card) {
        const clientName = card.getAttribute('data-client-name').toLowerCase();
        const clientStatus = card.getAttribute('data-client-status').toLowerCase();
        const usdBalance = parseFloat(card.getAttribute('data-usd-balance'));
        const afsBalance = parseFloat(card.getAttribute('data-afs-balance'));
        
        // Check all filter conditions
        const matchesSearch = clientName.includes(searchQuery);
        const matchesStatus = statusFilter === 'all' || clientStatus === statusFilter;
        
        let matchesBalance = true;
        let matchesCurrency = true;
        
        // Check balance filter
        if (balanceFilter === 'positive') {
            if (currencyFilter === 'USD' || currencyFilter === 'all') {
                matchesBalance = usdBalance > 0;
            } 
            if (currencyFilter === 'AFS' || (currencyFilter === 'all' && !matchesBalance)) {
                matchesBalance = afsBalance > 0;
            }
        } else if (balanceFilter === 'negative') {
            if (currencyFilter === 'USD' || currencyFilter === 'all') {
                matchesBalance = usdBalance < 0;
            }
            if (currencyFilter === 'AFS' || (currencyFilter === 'all' && !matchesBalance)) {
                matchesBalance = afsBalance < 0;
            }
        } else if (balanceFilter === 'zero') {
            if (currencyFilter === 'USD') {
                matchesBalance = usdBalance === 0;
            } else if (currencyFilter === 'AFS') {
                matchesBalance = afsBalance === 0;
            } else {
                matchesBalance = usdBalance === 0 && afsBalance === 0;
            }
        }
        
        // Check currency filter if balance filter is 'all'
        if (balanceFilter === 'all') {
            matchesCurrency = true; // Already handled in balance filter combinations
        }
        
        // Show/hide card based on filter conditions
        const shouldShow = matchesSearch && matchesStatus && matchesBalance && matchesCurrency;
        card.closest('.col-md-4').style.display = shouldShow ? 'block' : 'none';
        
        if (shouldShow) visibleCount++;
    });
    
    // Show/hide no results message
    const noResultsMessage = document.getElementById('noClientsMessage');
    if (noResultsMessage) {
        if (visibleCount === 0) {
            noResultsMessage.classList.remove('d-none');
        } else {
            noResultsMessage.classList.add('d-none');
        }
    }
}

/**
 * Check if there are any visible results and display a message if none
 */
function checkAndDisplayNoResults() {
    const visibleMainAccounts = document.querySelectorAll('.account-card:not([style*="display: none"])').length;
    const visibleSuppliers = document.querySelectorAll('.supplier-row:not([style*="display: none"])').length;
    const visibleClients = document.querySelectorAll('.client-card:not([style*="display: none"])').length;
    
    const noResultsContainer = document.getElementById('noResultsMessage');
    
    if (visibleMainAccounts === 0 && visibleSuppliers === 0 && visibleClients === 0) {
        if (!noResultsContainer) {
            const container = document.createElement('div');
            container.id = 'noResultsMessage';
            container.className = 'alert alert-info text-center m-3 animated-item';
            container.innerHTML = `
                <i class="feather icon-search mb-2" style="font-size: 2rem;"></i>
                <p>${__('no_accounts_match_your_search')}</p>
            `;
            
            // Insert after the filter container
            const filterContainer = document.querySelector('.filter-container');
            filterContainer.parentNode.insertBefore(container, filterContainer.nextSibling);
        } else {
            noResultsContainer.style.display = 'block';
        }
    } else if (noResultsContainer) {
        noResultsContainer.style.display = 'none';
    }
}

/**
 * Translation function placeholder
 * Replace with actual translation function from your system
 */
function __(key) {
    // This should be replaced with your actual translation function
    const translations = {
        'no_matching_suppliers_found': 'No matching suppliers found',
        'no_accounts_match_your_search': 'No accounts match your search criteria'
    };
    
    return translations[key] || key;
} 