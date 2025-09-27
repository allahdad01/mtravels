<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';
// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

/* Status badges */
.status-badge {
    font-size: 0.85em;
    padding: 0.25em 0.5em;
}

.status-Pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-Approved {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-Rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.status-Completed {
    background-color: #d4edda;
    color: #155724;
}

/* Fix SweetAlert2 z-index to appear above Bootstrap modals */
.swal2-container {
    z-index: 1200 !important;
}



/* Ensure SweetAlert2 inputs are focusable and interactive */
.swal2-container input,
.swal2-container textarea,
.swal2-container select {
    pointer-events: auto !important;
    z-index: 1201 !important;
}

.swal2-container .form-group {
    margin-bottom: 1rem;
}

.swal2-container label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10"><?= __('umrah_date_changes') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="umrah.php"><i class="feather icon-users"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('date_changes') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="feather icon-calendar mr-2"></i><?= __('date_change_requests') ?></h5>
                                        <div class="card-header-right">
                                            <button class="btn btn-sm btn-light" onclick="location.reload()">
                                                <i class="feather icon-refresh-cw"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Status Filter Tabs -->
                                        <div class="row mb-4">
                                            <div class="col-md-12">
                                                <ul class="nav nav-tabs" id="statusTabs" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                                                            <?= __('all') ?> <span class="badge badge-light" id="all-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                                                            <?= __('pending') ?> <span class="badge badge-warning" id="pending-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                                                            <?= __('approved') ?> <span class="badge badge-info" id="approved-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completed" role="tab">
                                                            <?= __('completed') ?> <span class="badge badge-success" id="completed-count">0</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Requests Table -->
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="dateChangesTable">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th><?= __('request_id') ?></th>
                                                        <th><?= __('passenger_name') ?></th>
                                                        <th><?= __('family') ?></th>
                                                        <th><?= __('current_dates') ?></th>
                                                        <th><?= __('requested_dates') ?></th>
                                                        <th><?= __('price_change') ?></th>
                                                        <th><?= __('status') ?></th>
                                                        <th><?= __('requested_on') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dateChangesTableBody">
                                                    <!-- Data will be loaded here -->
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Loading indicator -->
                                        <div id="loadingIndicator" class="text-center py-4">
                                            <i class="feather icon-loader spinning"></i> <?= __('loading') ?>...
                                        </div>

                                        <!-- No data message -->
                                        <div id="noDataMessage" class="text-center py-4 d-none">
                                            <i class="feather icon-calendar text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-3"><?= __('no_date_change_requests') ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Date Change Details Modal -->
<div class="modal fade" id="dateChangeDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-calendar mr-2"></i><?= __('date_change_request_details') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="dateChangeDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('close') ?>
                </button>
                <div id="actionButtons">
                    <!-- Action buttons will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Penalty Input Modal -->
<div class="modal fade" id="penaltyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-dollar-sign mr-2"></i>Enter Penalty Amounts
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Please enter the penalty amounts for this date change:</p>
                <form id="penaltyForm">
                    <input type="hidden" id="penaltyRequestId" value="">
                    <div class="form-group">
                        <label for="modal_supplier_penalty">Supplier Penalty ($)</label>
                        <input type="number" class="form-control" id="modal_supplier_penalty" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="modal_service_penalty">Service Penalty ($)</label>
                        <input type="number" class="form-control" id="modal_service_penalty" min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="modal_penalty_remarks">Penalty Remarks (Optional)</label>
                        <textarea class="form-control" id="modal_penalty_remarks" rows="2" placeholder="Reason for penalties..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitPenaltyApproval()">
                    <i class="feather icon-check mr-2"></i>Approve with Penalties
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
// Load date change requests
function loadDateChangeRequests(status = 'all') {
    $('#loadingIndicator').show();
    $('#noDataMessage').addClass('d-none');

    $.ajax({
        url: 'ajax/get_date_change_requests.php',
        type: 'GET',
        data: { status: status },
        success: function(response) {
            $('#loadingIndicator').hide();

            if (response.success && response.requests) {
                renderDateChangeRequests(response.requests);
                updateStatusCounts(response.counts);
            } else {
                $('#noDataMessage').removeClass('d-none');
            }
        },
        error: function() {
            $('#loadingIndicator').hide();
            $('#noDataMessage').removeClass('d-none');
        }
    });
}

// Render requests table
function renderDateChangeRequests(requests) {
    var tbody = $('#dateChangesTableBody');
    tbody.empty();

    if (requests.length === 0) {
        $('#noDataMessage').removeClass('d-none');
        return;
    }

    requests.forEach(function(request) {
        var priceChange = '';
        if (request.price_difference != 0) {
            var changeClass = request.price_difference > 0 ? 'text-danger' : 'text-success';
            var changeSymbol = request.price_difference > 0 ? '+' : '';
            priceChange = `<span class="${changeClass}">${changeSymbol}${request.price_difference} ${request.currency}</span>`;
        } else {
            priceChange = '<span class="text-muted">-</span>';
        }

        var row = `
            <tr>
                <td>#${request.id}</td>
                <td>
                    <strong>${request.passenger_name}</strong><br>
                    <small class="text-muted">Booking #${request.umrah_booking_id}</small>
                </td>
                <td>${request.family_name || 'N/A'}</td>
                <td>
                    <small>
                        <strong>Flight:</strong> ${request.old_flight_date || 'N/A'}<br>
                        <strong>Return:</strong> ${request.old_return_date || 'N/A'}<br>
                        <strong>Duration:</strong> ${request.old_duration || 'N/A'}
                    </small>
                </td>
                <td>
                    <small>
                        <strong>Flight:</strong> ${request.new_flight_date}<br>
                        <strong>Return:</strong> ${request.new_return_date}<br>
                        <strong>Duration:</strong> ${request.new_duration}
                    </small>
                </td>
                <td>${priceChange}</td>
                <td>
                    <span class="badge status-badge status-${request.status}">${request.status}</span>
                </td>
                <td>
                    <small>${new Date(request.created_at).toLocaleDateString()}</small>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewDateChangeDetails(${request.id})">
                        <i class="feather icon-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger ml-1" onclick="deleteDateChangeRequest(${request.id})">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Update status counts
function updateStatusCounts(counts) {
    $('#all-count').text(counts.all || 0);
    $('#pending-count').text(counts.pending || 0);
    $('#approved-count').text(counts.approved || 0);
    $('#completed-count').text(counts.completed || 0);
}

// View date change details
function viewDateChangeDetails(requestId) {
    $.ajax({
        url: 'ajax/get_date_change_details.php',
        type: 'GET',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                $('#dateChangeDetailsContent').html(response.html);
                $('#actionButtons').html(response.action_buttons);
                $('#dateChangeDetailsModal').modal('show');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to load request details',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load request details',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Approve date change request
function approveDateChangeRequest(requestId) {
    // Set the request ID in the modal
    $('#penaltyRequestId').val(requestId);

    // Clear previous values
    $('#modal_supplier_penalty').val('');
    $('#modal_service_penalty').val('');
    $('#modal_penalty_remarks').val('');

    // Show the penalty modal
    $('#penaltyModal').modal('show');
}

// Submit penalty approval
function submitPenaltyApproval() {
    const requestId = $('#penaltyRequestId').val();
    const supplierPenalty = parseFloat($('#modal_supplier_penalty').val()) || 0;
    const servicePenalty = parseFloat($('#modal_service_penalty').val()) || 0;
    const penaltyRemarks = $('#modal_penalty_remarks').val().trim();

    const totalPenalty = supplierPenalty + servicePenalty;

    // Show penalty summary as toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Processing Approval',
        html: `
            <div class="text-left">
                <p><strong>Penalty Summary:</strong></p>
                <ul class="list-unstyled">
                    <li>Supplier Penalty: $${supplierPenalty.toFixed(2)}</li>
                    <li>Service Penalty: $${servicePenalty.toFixed(2)}</li>
                    <li><strong>Total Penalty: $${totalPenalty.toFixed(2)}</strong></li>
                </ul>
                ${penaltyRemarks ? `<p><strong>Remarks:</strong> ${penaltyRemarks}</p>` : ''}
            </div>
        `,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    // Hide the penalty modal
    $('#penaltyModal').modal('hide');

    // Proceed with approval
    $.ajax({
        url: 'ajax/approve_date_change_request.php',
        type: 'POST',
        data: {
            id: requestId,
            supplier_penalty: supplierPenalty,
            service_penalty: servicePenalty,
            penalty_remarks: penaltyRemarks
        },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Approved',
                    text: response.message || 'Date change request approved successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                $('#dateChangeDetailsModal').modal('hide');
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Approval Failed',
                    text: response.message || 'Failed to approve request',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while approving the request',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Reject date change request
function rejectDateChangeRequest(requestId) {
    Swal.fire({
        title: 'Reject Date Change Request',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Please provide a reason for rejecting this request...',
        inputValidator: (value) => {
            if (!value) {
                return 'Reason is required!';
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Reject Request',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show processing toast
            Swal.fire({
                icon: 'info',
                title: 'Processing Rejection',
                text: 'Rejecting date change request...',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true
            });

            $.ajax({
                url: 'ajax/reject_date_change_request.php',
                type: 'POST',
                data: {
                    id: requestId,
                    rejection_reason: result.value
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Rejected',
                            text: response.message || 'Date change request rejected',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        $('#dateChangeDetailsModal').modal('hide');
                        loadDateChangeRequests();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Rejection Failed',
                            text: response.message || 'Failed to reject request',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while rejecting the request',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }
            });
        }
    });
}

// Process date change (apply the changes)
function processDateChangeRequest(requestId) {
    // Show processing toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Processing Changes',
        text: 'Applying date changes to booking...',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    });

    $.ajax({
        url: 'ajax/process_date_change_request.php',
        type: 'POST',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Processed',
                    text: response.message || 'Date changes applied successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                $('#dateChangeDetailsModal').modal('hide');
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Processing Failed',
                    text: response.message || 'Failed to process date changes',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while processing the changes',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Delete date change request
function deleteDateChangeRequest(requestId) {
    // Show processing toast and proceed
    Swal.fire({
        icon: 'info',
        title: 'Deleting Request',
        text: 'Deleting date change request...',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true
    });

    $.ajax({
        url: 'ajax/delete_date_change_request.php',
        type: 'POST',
        data: { id: requestId },
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Deleted',
                    text: response.message || 'Date change request deleted successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                loadDateChangeRequests();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Deletion Failed',
                    text: response.message || 'Failed to delete date change request',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while deleting the request',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        }
    });
}

// Initialize page
$(document).ready(function() {
    loadDateChangeRequests();

    // Handle tab clicks
    $('#statusTabs a').on('click', function(e) {
        e.preventDefault();
        var status = $(this).attr('href').substring(1); // Remove the # from href
        loadDateChangeRequests(status);
    });

    // Handle Enter key in penalty modal
    $('#penaltyModal input, #penaltyModal textarea').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            submitPenaltyApproval();
        }
    });
});
</script>

</body>
</html>