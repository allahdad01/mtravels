<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once('../includes/db.php');

// First, define pagination variables
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Initialize variables
$bookings = [];


// Fetch bookings data with all necessary fields
try {
    $stmt = $pdo->prepare("
        SELECT
            hb.id,
            CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) as guest_name,
            hb.gender,
            hb.order_id,
            hb.check_in_date,
            hb.check_out_date,
            hb.accommodation_details,
            hb.issue_date,
            hb.supplier_id,
            hb.contact_no,
            hb.base_amount,
            hb.sold_amount,
            hb.profit,
            hb.currency,
            hb.remarks,
            hb.receipt,
            s.name as supplier_name,
            c.name as client_name,
            ma.name as paid_to_name
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        WHERE hb.sold_to = ? AND hb.tenant_id = ?
        ORDER BY hb.id DESC
        LIMIT :offset, :itemsPerPage
    ");

    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For debugging
    error_log("Bookings fetched successfully: " . count($bookings) . " records");
} catch (PDOException $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $bookings = [];
}

// Get total number of records for pagination
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM hotel_bookings WHERE sold_to = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $itemsPerPage);
    
    // Calculate start and end record numbers
    $startRecord = $offset + 1;
    $endRecord = min($offset + $itemsPerPage, $totalRecords);

} catch (PDOException $e) {
    error_log("Error fetching pagination data: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 1;
    $startRecord = 0;
    $endRecord = 0;
}

// Validate current page
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    // Recalculate offset
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Ensure all variables have default values
$totalPages = $totalPages ?? 1;
$startRecord = $startRecord ?? 0;
$endRecord = $endRecord ?? 0;


// Add this function definition after your database queries and before the HTML
/**
 * Safely get array value with default
 * @param array $array The array to get value from
 * @param string $key The key to look for
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The value or default
 */
function getValue($array, $key, $default = 'N/A') {
    if (!is_array($array)) {
        return $default;
    }
    
    if (!isset($array[$key])) {
        return $default;
    }
    
    if (empty($array[$key]) && $array[$key] !== 0 && $array[$key] !== '0') {
        return $default;
    }
    
    return htmlspecialchars($array[$key]);
}

/**
 * Get status badge class
 * @param string $status The status value
 * @return string The corresponding CSS class
 */

/**
 * Format date in a consistent way
 * @param string $date The date string
 * @param string $format The desired format
 * @return string Formatted date or N/A
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Format currency amount
 * @param float $amount The amount to format
 * @param string $currency The currency code
 * @return string Formatted amount with currency
 */
function formatAmount($amount, $currency = 'USD') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern for pagination links
 * @return string Generated pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern = '?page=') {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<ul class="pagination pagination-sm mb-0">';

    // Previous button
    $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage - 1) . '" tabindex="-1">
                    <i class="feather icon-chevron-left"></i>
                </a>
              </li>';

    // Page numbers
    $maxPages = 5; // Maximum number of page links to show
    $startPage = max(1, min($currentPage - floor($maxPages / 2), $totalPages - $maxPages + 1));
    $endPage = min($startPage + $maxPages - 1, $totalPages);

    // First page
    if ($startPage > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . '1">1</a>
                  </li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>';
        }
    }

    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $currentPage) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">
                    <a class="page-link" href="' . $urlPattern . $i . '">' . $i . '</a>
                  </li>';
    }

    // Last page
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>';
        }
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . $totalPages . '">' . $totalPages . '</a>
                  </li>';
    }

    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage + 1) . '">
                    <i class="feather icon-chevron-right"></i>
                </a>
              </li>';

    $html .= '</ul>';

    return $html;
}

/**
 * Generate page info text
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @param int $totalItems Total number of items
 * @return string Page info text
 */
function generatePageInfo($currentPage, $itemsPerPage, $totalItems) {
    $startItem = (($currentPage - 1) * $itemsPerPage) + 1;
    $endItem = min($startItem + $itemsPerPage - 1, $totalItems);
    
    return "Showing {$startItem} to {$endItem} of {$totalItems} entries";
}

?>
<?php include '../includes/header_client.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">

                    <div class="main-body">
                        <div class="page-wrapper">

                            <!-- Main Card -->
                            <div class="card shadow-sm">
                                <!-- Card Header with Actions -->
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="feather icon-list mr-2"></i>Hotel Bookings</h5>
                                </div>

                                <!-- Table Container -->
                                        <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="bookingsTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="border-0">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                                        <label class="custom-control-label" for="selectAll"></label>
                                                    </div>
                                                </th>
                                                <th class="border-0">Booking ID</th>
                                                <th class="border-0">Guest</th>
                                                <th class="border-0">Check In/Out</th>
                                                <th class="border-0">Room Details</th>
                                                <th class="border-0">Amount</th>
                                                <th class="border-0">Status</th>
                                                <th class="border-0 text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                        <tbody id="bookingsTableBody">
                                            <?php if (!empty($bookings)): ?>
                                                <?php foreach ($bookings as $booking): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" 
                                                                       id="booking<?= getValue($booking, 'id') ?>">
                                                                <label class="custom-control-label" 
                                                                       for="booking<?= getValue($booking, 'id') ?>"></label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="font-weight-bold">#<?= getValue($booking, 'order_id') ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar avatar-sm bg-light text-primary mr-2">
                                                                    <?= strtoupper(substr(getValue($booking, 'first_name'), 0, 1)) ?>
                                                                </div>
                                                                <div>
                                                                    <span class="d-block"><?= getValue($booking, 'guest_name') ?></span>
                                                                    <small class="text-muted"><?= getValue($booking, 'contact_no') ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span><?= getValue($booking, 'check_in_date') ? date('M d, Y', strtotime($booking['check_in_date'])) : 'N/A' ?></span>
                                                                <small class="text-muted"><?= getValue($booking, 'check_in_date') ? date('D', strtotime($booking['check_in_date'])) : '' ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span><?= getValue($booking, 'check_out_date') ? date('M d, Y', strtotime($booking['check_out_date'])) : 'N/A' ?></span>
                                                                <small class="text-muted"><?= getValue($booking, 'check_out_date') ? date('D', strtotime($booking['check_out_date'])) : '' ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <h6 class="mb-1 text-primary">
                                                                    <?= getValue($booking, 'currency') ?> <?= number_format(getValue($booking, 'sold_amount', 0), 2) ?>
                                                                </h6>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span>Sold to: <?= getValue($booking, 'client_name') ?></span>
                                                                <small class="text-muted">Paid to: <?= getValue($booking, 'paid_to_name') ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex justify-content-center">
                                                                <button type="button" class="btn btn-icon btn-sm btn-info mr-2" 
                                                                        onclick="viewBooking(<?= $booking['id'] ?>)" 
                                                                        title="View Details">
                                                                    <i class="feather icon-eye"></i>
                                                                    </button>
                                                            </div>
                                                                </td>
                                                            </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">No bookings found</td>
                                                </tr>
                                            <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>


                                <!-- Pagination -->
                                <?php if (!empty($bookings)): ?>
                                <div class="card-footer bg-white">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="small text-muted mb-0">
                                                <?= generatePageInfo($currentPage, $itemsPerPage, $totalRecords) ?>
                                            </p>
                                                            </div>
                                        <div class="col-auto">
                                            <nav>
                                                <?= generatePagination($currentPage, $totalPages) ?>
                                            </nav>
                                                            </div>
                                                        </div>
                                                            </div>
                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                            </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

    
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>



<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="bookingDetails">
                    <!-- Details will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Place this script section right after your jQuery and Bootstrap includes, but before your table HTML -->
<script type="text/javascript">
// Define all functions in the global scope
window.viewBooking = function(id) {
    if (!id) {
        console.error('No booking ID provided');
        return;
    }

    console.log('Viewing booking:', id);

    $.ajax({
        url: 'get_hotel_bookings.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(bookings) {
            console.log('Response:', bookings);
            
            if (bookings && bookings.length > 0) {
                const booking = bookings[0];
                
                $('#bookingDetails').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Guest Name:</strong> ${booking.title} ${booking.first_name} ${booking.last_name}</p>
                            <p><strong>Order ID:</strong> ${booking.order_id || 'N/A'}</p>
                            <p><strong>Contact:</strong> ${booking.contact_no || 'N/A'}</p>
                            <p><strong>Check In:</strong> ${booking.check_in_date}</p>
                            <p><strong>Check Out:</strong> ${booking.check_out_date}</p>
                            <p><strong>Issue Date:</strong> ${booking.issue_date}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Client:</strong> ${booking.client_name || 'N/A'}</p>
                            <p><strong>Paid To:</strong> ${booking.paid_to_name || 'N/A'}</p>
                            <p><strong>Sold Amount:</strong> ${booking.currency} ${parseFloat(booking.sold_amount).toFixed(2)}</p>
                            </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Accommodation Details:</strong></p>
                            <p>${booking.accommodation_details || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Remarks:</strong></p>
                            <p>${booking.remarks || 'No remarks'}</p>
                        </div>
                    </div>
                `);

                window.currentBookingId = id;
                $('#detailsModal').modal('show');
            } else {
                alert('Booking not found');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Response:', xhr.responseText);
            alert('Error fetching booking details');
        }
    });
};


</script>

</body>
</html>
