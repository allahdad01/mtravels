<?php
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';


// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch customers with their total balances
$stmt = $pdo->prepare("
    SELECT
        c.*,
        COALESCE(SUM(w.balance), 0) as current_balance,
        w.currency
    FROM customers c
    LEFT JOIN customer_wallets w ON c.id = w.customer_id AND w.tenant_id = c.tenant_id AND w.branch_id = c.branch_id
    WHERE c.status = 'active' AND c.tenant_id = ? AND c.branch_id = ?
    GROUP BY c.id, w.currency
    ORDER BY c.created_at DESC
");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();
$customers = [];

// Organize customer data
foreach ($result as $row) {
    $customerId = $row['id'];
    if (!isset($customers[$customerId])) {
        $customers[$customerId] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'created_at' => $row['created_at'],
            'balances' => []
        ];
    }
    if ($row['currency']) {
        $customers[$customerId]['balances'][$row['currency']] = $row['current_balance'];
    }
}

?>


   
    <?php if (in_array($_SESSION['lang'] ?? 'en', ['fa', 'ps'])): ?>
    <style>
        .card-header {
            flex-direction: row-reverse !important;
        }
        .card-header .title-section {
            margin-right: 0;
            margin-left: auto;
        }
        .card-header .button-section {
            margin-left: 0;
            margin-right: auto;
        }
        .feather {
            margin-left: 8px;
            margin-right: 0;
        }
    </style>
    <?php endif; ?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- Customer Stats Cards -->
                                <div class="col-xl-3 col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <i class="feather icon-users f-30 text-primary"></i>
                                                <div class="ml-3">
                                                    <h6 class="mb-1"><?= __('total_customers') ?></h6>
                                                    <h3 class="mb-0"><?= count($customers) ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div class="title-section d-flex align-items-center">
                                                <i class="feather icon-users mr-2"></i>
                                                <h5 class="mb-0"><?= __('customer_management') ?></h5>
                                            </div>
                                            <div class="button-section">
                                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#customerModal">
                                                    <i class="feather icon-user-plus"></i> <?= __('new_customer') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Success/Error Messages -->
                                            <?php if (isset($success_message)): ?>
                                                <div class="alert alert-success alert-dismissible fade show">
                                                    <i class="feather icon-check-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($success_message); ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($error_message)): ?>
                                                <div class="alert alert-danger alert-dismissible fade show">
                                                    <i class="feather icon-alert-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($error_message); ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Search and Filter Section -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-primary border-primary text-white">
                                                                <i class="feather icon-search"></i>
                                                            </span>
                                                        </div>
                                                        <input type="text" class="form-control" id="customerSearch" 
                                                               placeholder="<?= __('search_customers') ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Customers Table -->
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="customersTable">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('customer_name') ?></th>
                                                            <th><?= __('customer_contact') ?></th>
                                                            <th><?= __('customer_current_balance') ?></th>
                                                            <th><?= __('customer_created') ?></th>
                                                            <th><?= __('customer_actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($customers as $customer): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="customer-name">
                                                                    <?= htmlspecialchars($customer['name']) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="customer-contact">
                                                                    <div><i class="feather icon-phone mr-1"></i><?= htmlspecialchars($customer['phone']) ?></div>
                                                                    <?php if ($customer['email']): ?>
                                                                    <small><i class="feather icon-mail mr-1"></i><?= htmlspecialchars($customer['email']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                if (!empty($customer['balances'])) {
                                                                    foreach ($customer['balances'] as $currency => $balance) {
                                                                        if (floatval($balance) != 0) {
                                                                            $badgeClass = floatval($balance) > 0 ? 'badge-success' : 'badge-danger';
                                                                            echo "<div class='badge {$badgeClass} mr-1'>" . 
                                                                                htmlspecialchars(number_format($balance, 2) . " " . $currency) . 
                                                                                "</div>";
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo "<span class='text-muted'>" . __('no_balance') . "</span>";
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="feather icon-calendar mr-1 text-muted"></i>
                                                                    <?= date('Y-m-d', strtotime($customer['created_at'])) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group-actions">
                                                                    <a href="customer_detail.php?id=<?= $customer['id'] ?>" 
                                                                       class="btn btn-info btn-sm">
                                                                        <i class="feather icon-eye"></i> <?= __('view_customer') ?>
                                                                    </a>
                                                                    <button class="btn btn-warning btn-sm" 
                                                                            onclick="editCustomer(<?= $customer['id'] ?>)">
                                                                        <i class="feather icon-edit"></i> <?= __('edit_customer') ?>
                                                                    </button>
                                                                    <a href="print_statement.php?id=<?= $customer['id'] ?>" 
                                                                       class="btn btn-primary btn-sm" target="_blank">
                                                                        <i class="feather icon-printer"></i> <?= __('print_statement') ?>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Customer Modal -->
    <?php include 'includes/sarafi_modals.php'; ?>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('edit_customer') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editCustomerForm" method="POST" action="handlers/edit_customer.php">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="edit_customer_id">
                        <div class="form-group">
                            <label for="edit_name"><?= __('customer_name') ?></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone"><?= __('customer_phone') ?></label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email"><?= __('customer_email') ?></label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="edit_address"><?= __('customer_address') ?></label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('update_customer') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <script>
        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // Customer search functionality
        document.getElementById('customerSearch').addEventListener('keyup', function(e) {
            const searchValue = e.target.value.toLowerCase();
            const tableRows = document.querySelectorAll('#customersTable tbody tr');
            
            tableRows.forEach(row => {
                const name = row.querySelector('.customer-name').textContent.toLowerCase();
                const contact = row.querySelector('.customer-contact').textContent.toLowerCase();
                
                if (name.includes(searchValue) || contact.includes(searchValue)) {
                    row.style.display = '';
                    // Highlight matching text
                    if (searchValue) {
                        highlightText(row, searchValue);
                    } else {
                        removeHighlight(row);
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Text highlighting functions
        function highlightText(element, searchText) {
            const nodes = element.querySelectorAll('.customer-name, .customer-contact');
            nodes.forEach(node => {
                const text = node.textContent;
                const highlightedText = text.replace(
                    new RegExp(searchText, 'gi'),
                    match => `<span class="highlight">${match}</span>`
                );
                if (text !== highlightedText) {
                    node.innerHTML = highlightedText;
                }
            });
        }

        function removeHighlight(element) {
            const highlights = element.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const text = highlight.textContent;
                highlight.replaceWith(text);
            });
        }

        // Add loading state to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    const originalHtml = this.innerHTML;
                    this.setAttribute('data-original-html', originalHtml);
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
                    this.classList.add('disabled');
                }
            });
        });

        // Customer edit functionality
        function editCustomer(customerId) {
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            button.classList.add('disabled');

            fetch(`handlers/get_customer.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_customer_id').value = data.customer.id;
                        document.getElementById('edit_name').value = data.customer.name;
                        document.getElementById('edit_phone').value = data.customer.phone;
                        document.getElementById('edit_email').value = data.customer.email || '';
                        document.getElementById('edit_address').value = data.customer.address || '';
                        
                        $('#editCustomerModal').modal('show');
                    } else {
                        showToast('error', '<?= __('error_fetching_customer') ?>');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', '<?= __('error_fetching_customer') ?>');
                })
                .finally(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('disabled');
                });
        }

        // Toast notification function
        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }, 100);
        }

        // Form submission handling
        document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            submitBtn.classList.add('disabled');
            
            fetch('handlers/edit_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                showToast(data.success ? 'success' : 'error', data.message);

                if (data.success) {
                    $('#editCustomerModal').modal('hide');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', '<?= __('error_updating_customer') ?>');
            })
            .finally(() => {
                submitBtn.innerHTML = originalHtml;
                submitBtn.classList.remove('disabled');
            });
        });

        // Add these styles for toast notifications
        const style = document.createElement('style');
        style.textContent = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem;
                border-radius: 8px;
                background: white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                z-index: 9999;
                transform: translateX(120%);
                transition: transform 0.3s ease;
            }

            .toast.show {
                transform: translateX(0);
            }

            .toast-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .toast-success {
                background: var(--success-gradient);
                color: white;
            }

            .toast-error {
                background: var(--danger-gradient);
                color: white;
            }

            .highlight {
                background: #fff3cd;
                padding: 2px;
                border-radius: 3px;
            }

            .btn.disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .fa-spin {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html> 