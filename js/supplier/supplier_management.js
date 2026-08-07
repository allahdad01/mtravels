(function() {
    // Logging utility
    const Logger = {
        log: function(message, ...args) {

        },
        error: function(message, ...args) {

        }
    };

    // Utility function to safely get element
    function safeGetElement(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            Logger.error(`Element not found: ${selector}`);
        }
        return element;
    }

    // Utility function to safely add event listener
    function safeAddEventListener(selector, event, handler) {
        const element = safeGetElement(selector);
        if (element) {
            try {
                element.addEventListener(event, handler);
            } catch (error) {
                Logger.error(`Failed to add event listener to ${selector}`, error);
            }
        }
    }

    // Main supplier management object
    const SupplierManagement = {
        // Cached references to improve performance and reduce DOM queries
        elements: {
            activeSupplierTable: null,
            inactiveSupplierTable: null,
            searchInput: null,
            filterType: null
        },
        
        // Pagination settings
        pagination: {
            itemsPerPage: 10,
            currentPage: 1
        },
        
        // Stored data
        allSuppliers: [],
        activeSuppliers: [],
        inactiveSuppliers: [],

        init: function() {
            Logger.log('Initializing SupplierManagement');
            
            // Cache element references
            this.cacheElements();

            // Ensure DOM is fully loaded before initializing
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.setupEventListeners.bind(this));
            } else {
                this.setupEventListeners();
            }
        },

        cacheElements: function() {
            this.elements.activeSupplierTable = safeGetElement('#activeSupplierTableBody');
            this.elements.inactiveSupplierTable = safeGetElement('#inactiveSupplierTableBody');
            this.elements.searchInput = safeGetElement('#searchSupplier');
            this.elements.filterType = safeGetElement('#filterType');

            Logger.log('Cached elements', this.elements);
        },

        setupEventListeners: function() {
            Logger.log('Setting up event listeners');

            // Add Supplier Form
            safeAddEventListener('#addSupplierForm', 'submit', this.handleAddSupplier.bind(this));

            // Edit Supplier Form
            safeAddEventListener('#editSupplierForm', 'submit', this.handleEditSupplier.bind(this));

            // Search and filter
            if (this.elements.searchInput) {
                this.elements.searchInput.addEventListener('input', this.handleSearch.bind(this));
            }

            if (this.elements.filterType) {
                this.elements.filterType.addEventListener('change', this.handleSearch.bind(this));
            }

            // Initial load of suppliers
            this.loadSuppliers();
        },

        handleSearch: function() {
            Logger.log('Handling search');
            if (!this.activeSuppliers || !this.inactiveSuppliers) {
                Logger.error('Suppliers not loaded yet');
                return;
            }

            const searchTerm = this.elements.searchInput ? 
                this.elements.searchInput.value.toLowerCase() : '';

            const filteredActiveSuppliers = this.activeSuppliers.filter(supplier => 
                supplier.name.toLowerCase().includes(searchTerm) ||
                supplier.id.toString().includes(searchTerm)
            );

            const filteredInactiveSuppliers = this.inactiveSuppliers.filter(supplier => 
                supplier.name.toLowerCase().includes(searchTerm) ||
                supplier.id.toString().includes(searchTerm)
            );

            this.updateActiveTable(filteredActiveSuppliers);
            this.updateInactiveTable(filteredInactiveSuppliers);
        },

        handleAddSupplier: function(e) {
        e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

        fetch('../api/supplier/add_supplier.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('', 'success');
                $('#addSupplierModal').modal('hide');
                    this.loadSuppliers(); // Refresh table
            } else {
                showToast("<?= __('error') ?>: " + data.message, 'error');
            }
        })

        },

        handleEditSupplier: function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            fetch('../api/supplier/update_supplier.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('', 'success');
                    $('#editSupplierModal').modal('hide');
                    this.loadSuppliers(); // Refresh supplier table
                } else {
                    showToast('error_updating_supplier: ' + data.message, 'error');
                }
            })

        },

        loadSuppliers: function() {
            Logger.log('Loading suppliers');

            fetch('../api/supplier/getSupplier.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Extract suppliers array from the response
                    const allSuppliers = data.suppliers || [];
                    
                    // Store suppliers for later filtering
                    this.activeSuppliers = allSuppliers.filter(supplier => supplier.status === 'active');
                    this.inactiveSuppliers = allSuppliers.filter(supplier => supplier.status === 'inactive');
                    
                    Logger.log('Suppliers loaded', {
                        total: allSuppliers.length,
                        active: this.activeSuppliers.length,
                        inactive: this.inactiveSuppliers.length
                    });

                    this.updateActiveTable(this.activeSuppliers);
                    this.updateInactiveTable(this.inactiveSuppliers);
                })
                .catch(this.handleSupplierLoadError.bind(this));
        },

        updateActiveTable: function(suppliers) {
            Logger.log('Updating active suppliers table');

            if (!this.elements.activeSupplierTable) {
                Logger.error('Active supplier table not found');
                return;
            }

            if (!Array.isArray(suppliers)) {
                Logger.error('Invalid suppliers data', suppliers);
                return;
            }

            if (suppliers.length === 0) {
                this.elements.activeSupplierTable.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center"><?= __('no_matching_active_suppliers_found') ?></td>
                </tr>
            `;
                // Update count
                const activeCountEl = document.getElementById('activeCount');
                if (activeCountEl) activeCountEl.textContent = '0';
            return;
        }

            // Update count
            const activeCountEl = document.getElementById('activeCount');
            if (activeCountEl) activeCountEl.textContent = suppliers.length;

            // Calculate pagination
            const itemsPerPage = this.pagination.itemsPerPage;
            const totalPages = Math.ceil(suppliers.length / itemsPerPage);
            const offset = (this.pagination.currentPage - 1) * itemsPerPage;
            const paginatedSuppliers = suppliers.slice(offset, offset + itemsPerPage);

            // Render table rows
            this.elements.activeSupplierTable.innerHTML = paginatedSuppliers.map((supplier, index) => `
                <tr>
                    <td>${offset + index + 1}</td>
                    <td>
                        <div>
                            Name: <span class="fw-medium">${supplier.name}</span><br>
                            Contact Person: <span class="fw-medium">${supplier.contact_person || '-'}</span><br>
                            Email: <span class="fw-medium">${supplier.email || '-'}</span><br>
                            Phone: <span class="fw-medium">${supplier.phone || '-'}</span>
                        </div>
                    </td>
                    <td>${supplier.supplier_type || '-'}</td>
                    <td>${supplier.balance || '0'}</td>
                    <td>${supplier.currency || '-'}</td>
                    <td style="max-width: 300px; word-wrap: break-word; white-space: normal;">${supplier.address || '-'}</td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="dropdownMenuActive${supplier.id}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="feather icon-more-vertical"></i> Actions
                            </button>
                            <div class="dropdown-menu" aria-labelledby="dropdownMenuActive${supplier.id}">
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.editSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-edit-2 mr-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.deleteSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-trash-2 mr-2"></i>Delete
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.deactivateSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-x mr-2"></i>Deactivate
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            `).join('');

            // Add pagination controls
            this.renderPagination('activeSuppliers', suppliers.length);
        },

        updateInactiveTable: function(suppliers) {
            Logger.log('Updating inactive suppliers table');

            if (!this.elements.inactiveSupplierTable) {
                Logger.error('Inactive supplier table not found');
                return;
            }

            if (!Array.isArray(suppliers)) {
                Logger.error('Invalid suppliers data', suppliers);
                return;
            }

            if (suppliers.length === 0) {
                this.elements.inactiveSupplierTable.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center"><?= __('no_matching_inactive_suppliers_found') ?></td>
                    </tr>
                `;
                // Update count
                const inactiveCountEl = document.getElementById('inactiveCount');
                if (inactiveCountEl) inactiveCountEl.textContent = '0';
                return;
            }

            // Update count
            const inactiveCountEl = document.getElementById('inactiveCount');
            if (inactiveCountEl) inactiveCountEl.textContent = suppliers.length;

            // Calculate pagination
            const itemsPerPage = this.pagination.itemsPerPage;
            const totalPages = Math.ceil(suppliers.length / itemsPerPage);
            const offset = (this.pagination.currentPage - 1) * itemsPerPage;
            const paginatedSuppliers = suppliers.slice(offset, offset + itemsPerPage);

            // Render table rows
            this.elements.inactiveSupplierTable.innerHTML = paginatedSuppliers.map((supplier, index) => `
                <tr>
                    <td>${offset + index + 1}</td>
                    <td>
                        <div>
                            Name: <span class="fw-medium">${supplier.name}</span><br>
                            Contact Person: <span class="fw-medium">${supplier.contact_person || '-'}</span><br>
                            Email: <span class="fw-medium">${supplier.email || '-'}</span><br>
                            Phone: <span class="fw-medium">${supplier.phone || '-'}</span>
                        </div>
                    </td>
                    <td>${supplier.supplier_type || '-'}</td>
                    <td>${supplier.balance || '0'}</td>
                    <td>${supplier.currency || '-'}</td>
                    <td style="max-width: 300px; word-wrap: break-word; white-space: normal;">${supplier.address || '-'}</td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="dropdownMenuInactive${supplier.id}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="feather icon-more-vertical"></i> Actions
                            </button>
                            <div class="dropdown-menu" aria-labelledby="dropdownMenuInactive${supplier.id}">
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.editSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-edit-2 mr-2"></i>Edit
                                </a>
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.deleteSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-trash-2 mr-2"></i>Delete
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" onclick="SupplierManagement.activateSupplier(${supplier.id}); return false;">
                                    <i class="feather icon-check mr-2"></i>Activate
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            `).join('');

            // Add pagination controls
            this.renderPagination('inactiveSuppliers', suppliers.length);
        },

        handleSupplierLoadError: function(error) {
            Logger.error('Error loading suppliers', error);
            
            // Attempt to show error in both tables
            const errorMessage = `
                <tr>
                    <td colspan="9" class="text-center text-danger">
                        Error loading suppliers: ${error.message}
                    </td>
                </tr>
            `;

            if (this.elements.activeSupplierTable) {
                this.elements.activeSupplierTable.innerHTML = errorMessage;
            }
            
            if (this.elements.inactiveSupplierTable) {
                this.elements.inactiveSupplierTable.innerHTML = errorMessage;
            }
        },

        editSupplier: function(id) {
            // Fetch supplier details
            fetch(`../api/supplier/fetch_supplier_by_id.php?id=${id}`)
                .then(response => response.json())
                .then(supplier => {
                    // Populate modal fields
                    const editSupplierId = safeGetElement('#editSupplierId');
                    const editSupplierName = safeGetElement('#editSupplierName');
                    const editContactPerson = safeGetElement('#editContactPerson');
                    const editPhone = safeGetElement('#editPhone');
                    const editEmail = safeGetElement('#editEmail');
                    const editAddress = safeGetElement('#editAddress');
                    const editCurrency = safeGetElement('#editCurrency');
                    const editBalance = safeGetElement('#editBalance');
                    const editSupplierType = safeGetElement('#editSupplierType');
                    const editRoutePaymentToMainAccount = safeGetElement('#editRoutePaymentToMainAccount');

                    if (editSupplierId) editSupplierId.value = supplier.id;
                    if (editSupplierName) editSupplierName.value = supplier.name;
                    if (editContactPerson) editContactPerson.value = supplier.contact_person || '';
                    if (editPhone) editPhone.value = supplier.phone;
                    if (editEmail) editEmail.value = supplier.email || '';
                    if (editAddress) editAddress.value = supplier.address || '';
                    if (editCurrency) editCurrency.value = supplier.currency || '';
                    if (editBalance) editBalance.value = supplier.balance;
                    if (editSupplierType) editSupplierType.value = supplier.supplier_type || 'Internal';
                    if (editRoutePaymentToMainAccount) editRoutePaymentToMainAccount.checked = (supplier.route_payment_to_main_account == 1);

                    // Show the modal
                    $('#editSupplierModal').modal('show');
                })

        },

        deleteSupplier: function(id) {
    if (confirm('are_you_sure_you_want_to_delete_this_supplier')) {
        fetch('../api/supplier/delete_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('', 'success');
                        this.loadSuppliers(); // Refresh table
            } else {
                showToast('error: ' + data.message, 'error');
            }
        })

    }
        },

        deactivateSupplier: function(id) {
            if (confirm('are_you_sure_you_want_to_deactivate_this_supplier')) {
                fetch('../api/supplier/deactivate_supplier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                })
        .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('', 'success');
                        this.loadSuppliers(); // Refresh table
                    } else {
                        showToast('error: ' + data.message, 'error');
                    }
                })

            }
        },

        activateSupplier: function(id) {
            if (confirm('are_you_sure_you_want_to_activate_this_supplier')) {
                fetch('../api/supplier/activate_supplier.php', {
        method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                        showToast('', 'success');
                        this.loadSuppliers(); // Refresh table
            } else {
                        showToast('error: ' + data.message, 'error');
                    }
                })

            }
        },

        renderPagination: function(tabType, totalItems) {
            const itemsPerPage = this.pagination.itemsPerPage;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const currentPage = this.pagination.currentPage;

            const containerId = tabType === 'activeSuppliers' ? 'activePaginationContainer' : 'inactivePaginationContainer';
            const container = document.getElementById(containerId);
            
            if (!container) return;

            // Clear container
            container.innerHTML = '';

            if (totalPages <= 1) return;

            // Create pagination HTML
            let paginationHtml = `
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <small class="text-muted">Page ${currentPage} of ${totalPages}</small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
            `;

            if (currentPage > 1) {
                paginationHtml += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="SupplierManagement.goToPage(1, '${tabType}'); return false;">
                            <i class="feather icon-chevrons-left"></i> First
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="SupplierManagement.goToPage(${currentPage - 1}, '${tabType}'); return false;">
                            <i class="feather icon-chevron-left"></i> Prev
                        </a>
                    </li>
                `;
            }

            const start = Math.max(1, currentPage - 2);
            const end = Math.min(totalPages, currentPage + 2);

            for (let i = start; i <= end; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                paginationHtml += `
                    <li class="page-item ${activeClass}">
                        <a class="page-link" href="#" onclick="SupplierManagement.goToPage(${i}, '${tabType}'); return false;">
                            ${i}
                        </a>
                    </li>
                `;
            }

            if (currentPage < totalPages) {
                paginationHtml += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="SupplierManagement.goToPage(${currentPage + 1}, '${tabType}'); return false;">
                            Next <i class="feather icon-chevron-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="SupplierManagement.goToPage(${totalPages}, '${tabType}'); return false;">
                            Last <i class="feather icon-chevrons-right"></i>
                        </a>
                    </li>
                `;
            }

            paginationHtml += `
                        </ul>
                    </nav>
                </div>
            `;

            // Insert pagination into container
            container.innerHTML = paginationHtml;
        },

        goToPage: function(page, tabType) {
            this.pagination.currentPage = page;
            const suppliers = tabType === 'activeSuppliers' ? this.activeSuppliers : this.inactiveSuppliers;
            
            if (tabType === 'activeSuppliers') {
                this.updateActiveTable(suppliers);
            } else {
                this.updateInactiveTable(suppliers);
            }
        }
    };

    // Expose the object globally so it can be called from inline event handlers
    window.SupplierManagement = SupplierManagement;

    // Initialize the supplier management
    SupplierManagement.init();
})();
