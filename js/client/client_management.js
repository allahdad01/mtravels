document.addEventListener('DOMContentLoaded', function() {
    // Hide preloader when page is fully loaded
    window.addEventListener('load', function() {
        const preloader = document.querySelector('.loader-bg');
        if (preloader) {
            preloader.classList.add('fade-out');
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 300);
        }
    });

    let clients = [];
    const searchInput = document.getElementById('searchClient');
    const filterType = document.getElementById('filterType');
    
    // Initialize Bootstrap modals
    const addClientModal = $('#addClientModal');
    const editClientModal = $('#editClientModal');

    // Load Clients
    function loadClients() {
        // Show preloader while loading data
        const preloader = document.querySelector('.loader-bg');
        if (preloader) {
            preloader.style.display = 'block';
        }

        fetch('../api/client/getClients.php')
            .then(response => response.json())
            .then(data => {
                clients = data;
                updateDashboardStats();
                renderClients();
            })
            .catch(error => {

                showError('Failed to load clients');
            })
            .finally(() => {
                // Hide preloader after data is loaded or if there's an error
                if (preloader) {
                    preloader.classList.add('fade-out');
                    setTimeout(() => {
                        preloader.style.display = 'none';
                    }, 300);
                }
            });
    }

    // Update Dashboard Statistics
    function updateDashboardStats() {
        const totalClients = clients.length;
        const agencies = clients.filter(c => c.client_type === 'agency').length;
        // Calculate total USD - only sum negative balances (money owed to us)
        const totalUsd = clients.reduce((sum, c) => {
            const balance = parseFloat(c.usd_balance || 0);
            return sum + (balance < 0 ? Math.abs(balance) : 0);
        }, 0);
        const totalAfs = clients.reduce((sum, c) => sum + parseFloat(c.afs_balance || 0), 0);

        document.getElementById('totalClients').textContent = totalClients;
        document.getElementById('totalAgencies').textContent = agencies;
        document.getElementById('totalBalance').textContent = `$${totalUsd.toFixed(2)}`;
        document.getElementById('totalAfs').textContent = `AFN${totalAfs.toFixed(2)}`;
    }

    // Render Clients Table
    function renderClients(filteredClients = clients) {
        // Separate active and inactive clients
        const activeClients = filteredClients.filter(c => c.status === 'active');
        const inactiveClients = filteredClients.filter(c => c.status === 'inactive');

        // Render Active Clients
        renderClientTable(activeClients, 'activeClientsTableBody');

        // Render Inactive Clients
        renderClientTable(inactiveClients, 'inactiveClientsTableBody');
    }

    // Render Client Table
    function renderClientTable(clientList, tableBodyId) {
        const tbody = document.getElementById(tableBodyId);
        tbody.innerHTML = '';

        if (clientList.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search fa-2x mb-3"></i>
                            <p class="mb-0"><?= __('no_clients_found') ?></p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        clientList.forEach(client => {
            const row = document.createElement('tr');
            const bgColor = getRandomColor();

            // Get translated client type
            const typeText = clientTypeTranslations[client.client_type] || client.client_type;
            const displayType = typeText;

            row.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0">${client.name}</h6>
                            <small class="text-muted">${client.address || ''}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge-${client.client_type.toLowerCase()}">
                        ${displayType}
                    </span>
                </td>
                <td>${client.email}</td>
                <td>${client.phone || '-'}</td>
                <td>$${parseFloat(client.usd_balance || 0).toFixed(2)}</td>
                <td>AFN${parseFloat(client.afs_balance || 0).toFixed(2)}</td>
                <td>${client.status}</td>
                <td class="text-right">
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="editClient(${client.id})">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a class="dropdown-item text-danger" href="#" onclick="deleteClient(${client.id})">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });
    }

    // Filter Clients
    function filterClients() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedType = filterType.value.toLowerCase();

        const filtered = clients.filter(client => {
            const matchesSearch = 
                client.name.toLowerCase().includes(searchTerm) ||
                client.email.toLowerCase().includes(searchTerm) ||
                (client.phone && client.phone.toLowerCase().includes(searchTerm));

            const matchesType = !selectedType || client.client_type.toLowerCase() === selectedType;

            return matchesSearch && matchesType;
        });

        renderClients(filtered);
    }

    // Add Client
    document.getElementById('addClientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('../api/client/add_clients.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success" || data.success) {
                    // First hide the modal
                    addClientModal.modal('hide');
                    
                    // Wait for modal to finish hiding
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'success',
                            title: '<?= __("success") ?>',
                            text: '<?= __("client_added_successfully") ?>',
                            timer: 1500,
                            showConfirmButton: false,
                            customClass: {
                                popup: 'colored-toast'
                            }
                        }).then(() => {
                            this.reset();
                            loadClients();
                        });
                    }, 300);
                } else {
                    addClientModal.modal('hide');
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= __("error") ?>',
                            text: data.message || '<?= __("failed_to_add_client") ?>',
                        });
                    }, 300);
                }
            })
            .catch(error => {
                addClientModal.modal('hide');
                setTimeout(() => {
                    Swal.fire({
                        icon: 'error',
                        title: '<?= __("error") ?>',
                        text: error.message || '<?= __("failed_to_add_client") ?>',
                    });
                }, 300);
            });
    });

    // Edit Client
    window.editClient = function(clientId) {
        const client = clients.find(c => c.id === clientId);
        if (!client) return;

        document.getElementById('editClientId').value = client.id;
        document.getElementById('editName').value = client.name;
        document.getElementById('editEmail').value = client.email;
        document.getElementById('editPhone').value = client.phone || '';
        document.getElementById('editAddress').value = client.address || '';
        document.getElementById('editType').value = client.client_type;
        document.getElementById('editStatus').value = client.status;

        editClientModal.modal('show');
    };

    // Handle Edit Form Submit
    document.getElementById('editClientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const clientData = {
            id: document.getElementById('editClientId').value,
            name: document.getElementById('editName').value,
            email: document.getElementById('editEmail').value,
            phone: document.getElementById('editPhone').value,
            address: document.getElementById('editAddress').value,
            client_type: document.getElementById('editType').value,
            status: document.getElementById('editStatus').value
        };

        fetch('../api/client/update_client.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(clientData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Client updated successfully',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    editClientModal.modal('hide');
                    loadClients();
                } else {
                    throw new Error(data.message || 'Failed to update client');
                }
            })
            .catch(error => {
                showError(error.message);
            });
    });

    // Delete Client
    window.deleteClient = function(clientId) {
        Swal.fire({
            title: 'Are you sure',
            text: "<?= __('this_action_cannot_be_undone') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../api/client/delete_client.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: clientId })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted',
                                text: 'Client has been deleted',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadClients();
                        } else {
                            throw new Error(data.message || 'Failed to delete client');
                        }
                    })
                    .catch(error => {
                        showError(error.message);
                    });
            }
        });
    };

    // Utility Functions
    function showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    function getRandomColor() {
        const colors = [
            '#4361ee', '#3f37c9', '#4cc9f0', '#4895ef',
            '#f72585', '#e63946', '#2a9d8f', '#e76f51'
        ];
        return colors[Math.floor(Math.random() * colors.length)];
    }

    // Event Listeners
    searchInput.addEventListener('input', filterClients);
    filterType.addEventListener('change', filterClients);

    // Initial Load
    loadClients();
});
