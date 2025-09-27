(function() {
    // Logging utility
    const Logger = {
        log: function(message, ...args) {
            console.log(`[SupplierManagement] ${message}`, ...args);
        },
        error: function(message, ...args) {
            console.error(`[SupplierManagement] ERROR: ${message}`, ...args);
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
        
        fetch('add_supplier.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("<?= __('supplier_added_successfully') ?>");
                $('#addSupplierModal').modal('hide');
                    this.loadSuppliers(); // Refresh table
            } else {
                alert("<?= __('error') ?>: " + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
        },

        handleEditSupplier: function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            fetch('update_supplier.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('supplier_updated_successfully');
                    $('#editSupplierModal').modal('hide');
                    this.loadSuppliers(); // Refresh supplier table
                } else {
                    alert('error_updating_supplier: ' + data.message);
                }
            })
            .catch(error => console.error('error_updating_supplier:', error));
        },

        loadSuppliers: function() {
            Logger.log('Loading suppliers');

            fetch('getSupplier.php')
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
            return;
        }

            this.elements.activeSupplierTable.innerHTML = suppliers.map((supplier, index) => `
                <tr>
                    <td>${index + 1}</td>
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
                        <button class="btn btn-sm btn-warning" onclick="SupplierManagement.editSupplier(${supplier.id})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="SupplierManagement.deleteSupplier(${supplier.id})">Delete</button>
                        <button class="btn btn-sm btn-secondary" onclick="SupplierManagement.deactivateSupplier(${supplier.id})">Deactivate</button>
                    </td>
                </tr>
            `).join('');
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
                return;
            }

            this.elements.inactiveSupplierTable.innerHTML = suppliers.map((supplier, index) => `
                <tr>
                    <td>${index + 1}</td>
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
                        <button class="btn btn-sm btn-warning" onclick="SupplierManagement.editSupplier(${supplier.id})">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="SupplierManagement.deleteSupplier(${supplier.id})">Delete</button>
                        <button class="btn btn-sm btn-success" onclick="SupplierManagement.activateSupplier(${supplier.id})">Activate</button>
                    </td>
                </tr>
            `).join('');
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
            fetch(`fetch_supplier_by_id.php?id=${id}`)
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

                    if (editSupplierId) editSupplierId.value = supplier.id;
                    if (editSupplierName) editSupplierName.value = supplier.name;
                    if (editContactPerson) editContactPerson.value = supplier.contact_person || '';
                    if (editPhone) editPhone.value = supplier.phone;
                    if (editEmail) editEmail.value = supplier.email || '';
                    if (editAddress) editAddress.value = supplier.address || '';
                    if (editCurrency) editCurrency.value = supplier.currency || '';
                    if (editBalance) editBalance.value = supplier.balance;
                    if (editSupplierType) editSupplierType.value = supplier.supplier_type || 'Internal';

                    // Show the modal
                    $('#editSupplierModal').modal('show');
                })
                .catch(error => console.error('Error fetching supplier details:', error));
        },

        deleteSupplier: function(id) {
    if (confirm('are_you_sure_you_want_to_delete_this_supplier')) {
        fetch('delete_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('supplier_deleted_successfully');
                        this.loadSuppliers(); // Refresh table
            } else {
                alert('error: ' + data.message);
            }
        })
        .catch(error => console.error('error:', error));
    }
        },

        deactivateSupplier: function(id) {
            if (confirm('are_you_sure_you_want_to_deactivate_this_supplier')) {
                fetch('deactivate_supplier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                })
        .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('supplier_deactivated_successfully');
                        this.loadSuppliers(); // Refresh table
                    } else {
                        alert('error: ' + data.message);
                    }
                })
                .catch(error => console.error('error:', error));
            }
        },

        activateSupplier: function(id) {
            if (confirm('are_you_sure_you_want_to_activate_this_supplier')) {
                fetch('activate_supplier.php', {
        method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                        alert('supplier_activated_successfully');
                        this.loadSuppliers(); // Refresh table
            } else {
                        alert('error: ' + data.message);
                    }
                })
                .catch(error => console.error('error:', error));
            }
        }
    };

    // Expose the object globally so it can be called from inline event handlers
    window.SupplierManagement = SupplierManagement;

    // Initialize the supplier management
    SupplierManagement.init();
})();
