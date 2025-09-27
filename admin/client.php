<?php
session_start();
$tenant_id = $_SESSION['tenant_id'];
require_once 'security.php';
require_once '../includes/db.php';

// Enforce authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

include '../includes/header.php';
?>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
    <div class="pcoded-main-container">
            <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('client_management') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="dashboard.php"><?= __('dashboard') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('client_management') ?></a></li>
                        </ul>
                    </div>
                    <div class="col-md-6 text-right">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addClientModal">
                            <i class="fas fa-plus mr-2"></i><?= __('add_new_client') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalClients">0</h3>
                                        <p class="text-muted mb-0"><?= __('total_clients') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalAgencies">0</h3>
                                        <p class="text-muted mb-0"><?= __('agencies') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalBalance">$0</h3>
                                        <p class="text-muted mb-0"><?= __('total_usd_balance') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalAfs">₳0</h3>
                                        <p class="text-muted mb-0"><?= __('total_afs_balance') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Filter -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="search-box">
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-transparent">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-0" id="searchClient" 
                                   placeholder="<?= __('search_clients') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control" id="filterType">
                            <option value=""><?= __('all_types') ?></option>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Clients Tabs -->
                <ul class="nav nav-tabs mb-3" id="clientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="activeClients-tab" data-toggle="tab" href="#activeClients" role="tab">
                            <?= __('active_clients') ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="inactiveClients-tab" data-toggle="tab" href="#inactiveClients" role="tab">
                            <?= __('inactive_clients') ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="clientTabContent">
                    <div class="tab-pane fade show active" id="activeClients" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="activeClientsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('email') ?></th>
                                        <th><?= __('phone') ?></th>
                                        <th><?= __('usd_balance') ?></th>
                                        <th><?= __('afs_balance') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th class="text-end"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="activeClientsTableBody">
                                    <!-- Active Client rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="inactiveClients" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="inactiveClientsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('email') ?></th>
                                        <th><?= __('phone') ?></th>
                                        <th><?= __('usd_balance') ?></th>
                                        <th><?= __('afs_balance') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th class="text-end"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="inactiveClientsTableBody">
                                    <!-- Inactive Client rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><?= __('add_new_client') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addClientForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('email') ?></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('password') ?></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                 
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('usd_balance') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" step="0.01" class="form-control" name="usd_balance" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('afs_balance') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₳</span>
                                </div>
                                <input type="number" step="0.01" class="form-control" name="afs_balance" value="0.00">
                            </div>
                    </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('client_type') ?></label>
                        <select class="form-control" name="client_type" required>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-control" name="status" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="inactive"><?= __('inactive') ?></option>
                        </select>
                    </div>
                  
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus mr-2"></i><?= __('add_client') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Client Modal -->
<div class="modal fade" id="editClientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><?= __('edit_client') ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editClientForm">
                <div class="modal-body">
                    <input type="hidden" id="editClientId">
                    <div class="mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="editName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('email') ?></label>
                        <input type="email" class="form-control" id="editEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" class="form-control" id="editPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" id="editAddress" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('client_type') ?></label>
                        <select class="form-control" id="editType" required>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('status') ?></label>
                        <select class="form-control" id="editStatus" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="inactive"><?= __('inactive') ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-save mr-2"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    /* Preloader Styles */
    .loader-bg {
        position: fixed;
        z-index: 999999;
        background: #fff;
        width: 100%;
        height: 100%;
        transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
    }
    
    .loader-bg.fade-out {
        opacity: 0;
        visibility: hidden;
    }

    /* SweetAlert2 Custom Styles */
    .colored-toast.swal2-icon-success {
        background-color: #a5dc86 !important;
    }
    
    .colored-toast .swal2-title,
    .colored-toast .swal2-content {
        color: #fff !important;
    }
    
    .colored-toast .swal2-success {
        border-color: #fff !important;
    }
    
    .colored-toast .swal2-success [class^='swal2-success-line'] {
        background-color: #fff !important;
    }
    
    .colored-toast .swal2-success-ring {
        border-color: #fff !important;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .client-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
    }

    .search-box {
        background: white;
        border-radius: 10px;
        padding: 0.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin: 0 0.2rem;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
    }

    .badge-regular {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .badge-agency {
        background-color: #fce4ec;
        color: #c2185b;
    }

    /* Bootstrap 4 Utility Classes */
    .mr-2 {
        margin-right: 0.5rem !important;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }

    .text-white {
        color: #fff !important;
    }

    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }

        .btn-action {
            width: 28px;
            height: 28px;
        }
    }
</style>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Your existing JavaScript code here -->
<script>
const clientTypeTranslations = {
    'regular': '<?= __("regular") ?>',
    'agency': '<?= __("agency") ?>'
};

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

        fetch('getClients.php')
            .then(response => response.json())
            .then(data => {
                clients = data;
                updateDashboardStats();
                renderClients();
            })
            .catch(error => {
                console.error('Error:', error);
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
        document.getElementById('totalAfs').textContent = `₳${totalAfs.toFixed(2)}`;
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

            // Get translated client type and extract the main part (before parenthesis)
            const typeText = clientTypeTranslations[client.client_type] || client.client_type;
            const displayType = typeText.split('(')[0];

            row.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <div class="client-avatar mr-3" style="background-color: ${bgColor}">
                            ${client.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h6 class="mb-0">${client.name}</h6>
                            <small class="text-muted">${client.address || ''}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge badge-${client.client_type.toLowerCase()}">
                        ${displayType}
                    </span>
                </td>
                <td>${client.email}</td>
                <td>${client.phone || '-'}</td>
                <td>$${parseFloat(client.usd_balance || 0).toFixed(2)}</td>
                <td>₳${parseFloat(client.afs_balance || 0).toFixed(2)}</td>
                <td>${client.status}</td>
                <td class="text-right">
                    <button class="btn btn-info btn-action" onclick="editClient(${client.id})">
                        <i class="fas fa-edit text-white"></i>
                    </button>
                    <button class="btn btn-danger btn-action" onclick="deleteClient(${client.id})">
                        <i class="fas fa-trash-alt"></i>
                    </button>
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

        fetch('add_clients.php', {
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

        fetch('../api/update_client.php', {
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
            title: '<?= __('are_you_sure') ?>',
            text: "<?= __('this_action_cannot_be_undone') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?= __('yes_delete_it') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../api/delete_client.php', {
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
                                title: '<?= __('deleted') ?>',
                                text: '<?= __('client_has_been_deleted') ?>',
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
            title: '<?= __('error') ?>',
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
</script>

<?php include '../includes/admin_footer.php'; ?>