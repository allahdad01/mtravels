<?php
/**
 * AJAX endpoint to load family members
 * This file dynamically loads and displays family members in card format
 */

// Set error handling FIRST before includes
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors directly
header('Content-Type: application/json');

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/admin/security.php';
require_once $base_path . '/includes/language_helpers.php';
require_once $base_path . '/includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);
$isAdmin = $_SESSION['role'] === 'admin';

// Get family ID and filter
$family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : 0;
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

if ($family_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid family ID']);
    exit;
}

try {
    // Fetch members
    $sqlMembers = "SELECT um.*, c.name as client_name, ma.name as main_account_name, u.name as created_by,
        GROUP_CONCAT(CONCAT(
            CASE ubs.service_type
                WHEN 'all' THEN 'All Services'
                WHEN 'ticket' THEN 'Ticket'
                WHEN 'visa' THEN 'Visa'
                WHEN 'hotel' THEN 'Hotel'
                WHEN 'transport' THEN 'Transport'
                WHEN 'ticket+visa' THEN 'Ticket + Visa'
                WHEN 'ticket+hotel' THEN 'Ticket + Hotel'
                WHEN 'ticket+transport' THEN 'Ticket + Transport'
                WHEN 'visa+services' THEN 'Visa + Services'
                WHEN 'visa+hotel' THEN 'Visa + Hotel'
                WHEN 'visa+transport' THEN 'Visa + Transport'
                WHEN 'hotel+transport' THEN 'Hotel + Transport'
                ELSE ubs.service_type
            END,
            ': ', s.name) SEPARATOR '|') as services_info
    FROM umrah_bookings um
    LEFT JOIN clients c ON um.sold_to = c.id
    LEFT JOIN main_account ma ON um.paid_to = ma.id
    LEFT JOIN umrah_booking_services ubs ON um.booking_id = ubs.booking_id
    LEFT JOIN suppliers s ON ubs.supplier_id = s.id
    LEFT JOIN users u ON um.created_by = u.id
    WHERE um.family_id = ? AND um.tenant_id = ? AND um.branch_id = ?";
    
    $membersParams = [$family_id, $tenant_id, $branch_id];
    
    // Apply filter if specified
    if ($filter === 'refunded' || $filter === 'cancelled') {
        $sqlMembers .= " AND um.status = ?";
        $membersParams[] = $filter;
    }
    
    $sqlMembers .= " GROUP BY um.booking_id
    ORDER BY um.created_at DESC";
    
    $membersStmt = $pdo->prepare($sqlMembers);
    $membersStmt->execute($membersParams);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($members)) {
        echo json_encode([
            'success' => true,
            'html' => '<div class="no-members-message"><i class="fas fa-info-circle"></i> ' . __('no_members_found') . '</div>'
        ]);
        exit;
    }

    // Build HTML for members
    ob_start();
    
    // Check if any family member has regular client type
    $hasRegularClient = false;
    $checkClientTypeStmt = $pdo->prepare("
        SELECT COUNT(*) as regular_count 
        FROM umrah_bookings ub
        JOIN clients c ON ub.sold_to = c.id
        WHERE ub.family_id = ? AND c.client_type = 'regular' 
        AND ub.tenant_id = ? AND ub.branch_id = ?
    ");
    $checkClientTypeStmt->bindParam(1, $family_id, PDO::PARAM_INT);
    $checkClientTypeStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $checkClientTypeStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $checkClientTypeStmt->execute();
    $clientTypeResult = $checkClientTypeStmt->fetch(PDO::FETCH_ASSOC);
    $hasRegularClient = $clientTypeResult && $clientTypeResult['regular_count'] > 0;
    ?>
    <!-- Family-level Bulk Actions -->
    <div class="family-bulk-actions mb-3">
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary selectAllGroupTicketBtn" data-family-id="<?= $family_id ?>" onclick="selectAllFamilyForGroupTicket(<?= $family_id ?>)">
                <i class="fas fa-plane mr-1"></i><?= __('select_all_for_group_ticket') ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-info selectAllIdCardBtn" data-family-id="<?= $family_id ?>" onclick="selectAllFamilyForIdCard(<?= $family_id ?>)">
                <i class="fas fa-id-card mr-1"></i><?= __('select_all_for_id_cards') ?>
            </button>
        </div>
    </div>

    <div class="members-list">
        <?php foreach ($members as $member): 
            $isRefunded = isset($member['status']) && $member['status'] === 'refunded';
            $isCancelled = isset($member['status']) && $member['status'] === 'cancelled';
            $isDisabled = $isRefunded || $isCancelled;
            
            // Get main account transactions
            $transactionSql = "SELECT SUM(payment_amount / COALESCE(exchange_rate, 1)) as main_account_total
                            FROM umrah_transactions
                            WHERE umrah_booking_id = ?
                            AND transaction_to = 'Internal Account'
                            AND tenant_id = ? AND branch_id = ?";
            $transStmt = $pdo->prepare($transactionSql);
            $transStmt->execute([$member['booking_id'], $tenant_id, $branch_id]);
            $transResult = $transStmt->fetch(PDO::FETCH_ASSOC);
            $mainAccountTotal = $transResult ? ($transResult['main_account_total'] ?: 0) : 0;
        ?>
        <div class="member-card <?= $isRefunded ? 'member-refunded' : '' ?>">
            <div class="member-card-header">
                <div class="member-checkbox-wrapper">
                    <input type="checkbox" 
                           class="member-checkbox" 
                           value="<?= $member['booking_id'] ?>"
                           data-booking-id="<?= $member['booking_id'] ?>"
                           data-base-price="<?= $member['price'] ?>"
                           data-sold-price="<?= $member['sold_price'] ?>"
                           data-current-profit="<?= $member['profit'] ?>"
                           data-status="<?= $member['status'] ?>"
                           data-currency="<?= $member['currency'] ?>"
                           <?= in_array($member['status'], ['active', 'refunded']) ? 'disabled title="' . ucfirst($member['status']) . ' bookings cannot be selected"' : '' ?>>
                </div>
                <div class="member-info">
                    <h5 class="member-name"><?= htmlspecialchars($member['name'] ?? '') ?></h5>
                    <?php 
                    $status = $member['status'] ?? '';
                    if ($status === 'refunded'): ?>
                        <span class="status-badge badge-danger">
                            <i class="fas fa-times-circle"></i> <?= __('refunded') ?>
                        </span>
                    <?php elseif ($status === 'cancelled'): ?>
                        <span class="status-badge badge-secondary">
                            <i class="fas fa-ban"></i> <?= __('cancelled') ?>
                        </span>
                    <?php elseif ($status === 'pending'): ?>
                        <span class="status-badge badge-warning">
                            <i class="fas fa-clock"></i> <?= __('pending') ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge badge-success">
                            <i class="fas fa-check-circle"></i> <?= __('active') ?>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Flight Status Badge -->
                    <?php if ($member['flight_date'] && $member['return_date']): ?>
                        <span class="status-badge badge-flight-done">
                            <i class="fas fa-plane"></i> <?= __('flight_done') ?>
                        </span>
                    <?php else: ?>
                        <span class="status-badge badge-flight-pending">
                            <i class="fas fa-calendar"></i> Flight Pending
                        </span>
                    <?php endif; ?>
                    
                    <!-- Financial Details - Below Name/Status -->
                     <div class="member-financial-row" style="margin-top: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                         <span style="font-size: 0.75rem;"><strong><?= __('sold_price') ?>:</strong> <?= number_format($member['sold_price'] ?? 0, 2) ?> <?= htmlspecialchars($member['currency'] ?? 'USD') ?></span>
                         <?php if (!$hasRegularClient): ?>
                         <span style="font-size: 0.75rem; color: #059669;"><strong><?= __('paid') ?>:</strong> <?= number_format($member['paid'] ?? 0, 2) ?> <?= htmlspecialchars($member['currency'] ?? 'USD') ?></span>
                         <span style="font-size: 0.75rem; <?= (($member['due'] ?? 0) > 0) ? 'color: #ef4444;' : 'color: #059669;' ?>"><strong><?= __('due') ?>:</strong> <?= number_format($member['due'] ?? 0, 2) ?> <?= htmlspecialchars($member['currency'] ?? 'USD') ?></span>
                         <?php endif; ?>
                     </div>
                </div>
                <div class="member-actions">
                    <button class="btn-icon-sm" onclick="viewMemberDetails(<?= $member['booking_id'] ?>)" title="<?= __('view_details') ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="dropdown">
                        <button class="btn-icon-sm" type="button" data-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <h6 class="dropdown-header"><?= __('primary_actions') ?></h6>
                            <a class="dropdown-item" href="#" onclick="viewMemberDetails(<?= $member['booking_id'] ?>); return false;">
                                 <i class="fas fa-eye"></i><?= __('view_details') ?>
                             </a>
                             <?php if ($member['status'] !== 'active' || $canEdit): ?>
                             <a class="dropdown-item" href="#" onclick="openEditMemberModal(<?= $member['booking_id'] ?>); return false;">
                                  <i class="fas fa-edit"></i><?= __('edit') ?>
                              </a>
                             <?php endif; ?>
                             <?php if ($canEdit && !$isDisabled): ?>
                             <a class="dropdown-item" href="#" onclick="openTransactionTab(<?= $member['booking_id'] ?>, <?= $member['sold_price'] ?>); return false;">
                                 <i class="fas fa-credit-card"></i><?= __('transaction') ?>
                             </a>
                             <?php endif; ?>
                            
                            <?php if (!$isDisabled): ?>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header"><?= __('documents') ?></h6>
                            <a class="dropdown-item" href="#" onclick="generateTazminAgreement(<?= $member['booking_id'] ?>); return false;">
                                <i class="fas fa-shield-alt"></i><?= __('generate_tazmin') ?>
                            </a>
                            <a class="dropdown-item" href="#" onclick="generateAgreement(<?= $member['booking_id'] ?>); return false;">
                                <i class="fas fa-file-contract"></i><?= __('generate_agreement') ?>
                            </a>
                            <a class="dropdown-item" href="#" onclick="generateCompletionForm(<?= $member['booking_id'] ?>); return false;">
                                <i class="fas fa-check-circle"></i><?= __('generate_completion_form') ?>
                            </a>
                            <a class="dropdown-item" href="#" onclick="selectForIdCard(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                <i class="fas fa-id-card"></i><?= __('select_for_id_card') ?>
                            </a>
                            <a class="dropdown-item" href="#" onclick="selectForGroupTicket(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                <i class="fas fa-plane"></i><?= __('select_for_group_ticket') ?>
                            </a>
                            <a class="dropdown-item" href="#" onclick="openMemberDocumentsModal(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                <i class="fas fa-file-upload"></i>Photo & Passport & Visa
                            </a>
                            <?php endif; ?>
                            
                            <?php if (empty($member['status']) || $member['status'] === 'pending'): ?>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header"><?= __('approval') ?></h6>
                            <a class="dropdown-item" href="#" onclick="approveMemberBooking(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name']) ?>'); return false;">
                                <i class="fas fa-check"></i><?= __('approve_booking') ?>
                            </a>
                            <?php endif; ?>
                            
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header"><?= __('advanced_actions') ?></h6>
                            <?php if ($member['status'] === 'active'): ?>
                            <a class="dropdown-item" href="#" onclick="openRefundModal(<?= $member['booking_id'] ?>, <?= $member['sold_price'] ?>, <?= $member['price'] ?? 0 ?>, '<?= $member['currency'] ?>'); return false;">
                                 <i class="fas fa-undo"></i><?= __('process_refund') ?>
                              </a>
                            <?php endif; ?>
                              <a class="dropdown-item" href="#" onclick="openCancellationReapplyModal(<?= $member['booking_id'] ?>, <?= $member['price'] ?>, <?= $member['sold_price'] ?>, <?= $member['profit'] ?>, '<?= $member['currency'] ?>', '<?= $member['status'] ?>'); return false;">
                                  <i class="fas fa-cog"></i>Manage Booking Status
                              </a>
                             <?php if (!$isDisabled): ?>
                             <a class="dropdown-item" href="#" onclick="openDateChangeModal(<?= $member['booking_id'] ?>, '<?= htmlspecialchars($member['name'] ?? '') ?>', '<?= htmlspecialchars($member['flight_date'] ?? '') ?>', '<?= htmlspecialchars($member['return_date'] ?? '') ?>', '<?= htmlspecialchars($member['duration'] ?? '') ?>', <?= $member['price'] ?>, '<?= $member['currency'] ?>'); return false;">
                                 <i class="fas fa-calendar"></i><?= __('request_date_change') ?>
                             </a>
                             <?php endif; ?>
                             <a class="dropdown-item" href="#" onclick="generateCancellationForm(<?= $member['booking_id'] ?>); return false;">
                                 <i class="fas fa-times-circle"></i><?= __('generate_cancellation_form') ?>
                             </a>
                             
                             <?php if ($canEdit && ($member['status'] !== 'active' || $isAdmin)): ?>
                             <div class="dropdown-divider"></div>
                             <h6 class="dropdown-header text-danger"><?= __('danger_zone') ?></h6>
                             <a class="dropdown-item text-danger" href="#" onclick="deleteBooking(<?= $member['booking_id'] ?>); return false;">
                                 <i class="fas fa-trash"></i><?= __('delete') ?>
                             </a>
                             <?php endif; ?>
                        </div>
                    </div>
            </div>
            
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Bulk Actions -->
    <div class="bulk-actions-bar">
        <div class="bulk-actions-content">
            <label class="bulk-select-all">
                <input type="checkbox" id="selectAllMembers" onchange="toggleAllMembers()">
                <span><?= __('select_all') ?></span>
            </label>
            <div class="bulk-action-buttons">
                <button type="button" class="btn btn-warning btn-sm" onclick="bulkCancelSelected()">
                    <i class="fas fa-times-circle"></i> Cancel Selected
                </button>
                <button type="button" class="btn btn-success btn-sm" onclick="bulkReapplySelected()">
                    <i class="fas fa-redo"></i> Re-apply Selected
                </button>
            </div>
        </div>
    </div>
    
    <style>
    :root {
        --primary-color: #2563eb;
        --danger-color: #ef4444;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-500: #6b7280;
        --gray-700: #374151;
        --gray-900: #111827;
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
    }
    
    /* Family Bulk Actions */
    .family-bulk-actions {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(59, 130, 246, 0.05));
        border: 1px solid rgba(37, 99, 235, 0.1);
        border-radius: var(--radius-lg);
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
    }
    
    .family-bulk-actions .d-flex {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .family-bulk-actions .btn {
        transition: all var(--transition-fast);
    }
    
    .family-bulk-actions .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    /* Member Card Styles */
    .members-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
        align-items: start;
    }
    
    .member-card {
        background: white;
        border-radius: var(--radius-md);
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border: 1px solid var(--gray-200);
        transition: all var(--transition-fast);
        display: flex;
        flex-direction: column;
        min-height: 100px;
        overflow: visible;
    }
    
    .member-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
        border-bottom: 1px solid var(--gray-200);
        border-radius: var(--radius-md) var(--radius-md) 0 0;
        flex-shrink: 0;
    }
    
    .member-card-body {
        padding: 0.5rem;
        border-radius: 0 0 var(--radius-md) var(--radius-md);
        flex: 1;
    }
    
    .member-actions {
        position: relative;
        z-index: 100;
    }
    
    .member-card .dropdown {
        position: relative;
        display: flex;
        align-items: center;
        z-index: 100;
    }
    
    .member-card .dropdown-menu {
        position: fixed;
        min-width: 280px;
        width: 280px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 9999;
        box-shadow: var(--shadow-xl);
        border-radius: var(--radius-lg);
        border: 1px solid var(--gray-200);
        background: white;
        padding: 0.5rem 0;
        display: none;
    }
    
    .member-card .dropdown-menu::before {
        content: '';
        position: absolute;
        bottom: 100%;
        right: 12px;
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid white;
        z-index: 10001;
    }
    
    .member-card .dropdown-menu::after {
        content: '';
        position: absolute;
        bottom: 100%;
        right: 11px;
        width: 0;
        height: 0;
        border-left: 9px solid transparent;
        border-right: 9px solid transparent;
        border-bottom: 9px solid var(--gray-200);
        z-index: 10000;
        margin-bottom: 1px;
    }
    
    .member-card .dropdown-menu.show {
        display: block !important;
    }
    
    .member-card .dropdown-menu::-webkit-scrollbar {
        width: 6px;
    }
    
    .member-card .dropdown-menu::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: var(--radius-md);
    }
    
    .member-card .dropdown-menu::-webkit-scrollbar-thumb {
        background: var(--gray-300);
        border-radius: var(--radius-md);
    }
    
    .member-card .dropdown-menu::-webkit-scrollbar-thumb:hover {
        background: var(--gray-400);
    }
    
    .member-card .dropdown-menu .dropdown-item {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        color: var(--gray-700);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        text-decoration: none;
        transition: all var(--transition-fast);
        border-radius: var(--radius-md);
    }
    
    .member-card .dropdown-menu .dropdown-item:hover {
        background-color: var(--gray-100);
        color: var(--gray-900);
    }
    
    .member-card .dropdown-menu .dropdown-item.text-danger {
        color: var(--danger-color);
    }
    
    .member-card .dropdown-menu .dropdown-item.text-danger:hover {
        color: #b91c1c;
        background-color: #fee2e2;
    }
    
    .member-card .dropdown-menu .dropdown-header {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--gray-500);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .member-card .dropdown-menu .dropdown-divider {
        margin: 0.5rem 0;
        border-top: 1px solid var(--gray-200);
    }
    
    .member-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-1px);
    }
    
    .member-card.member-refunded {
        border-color: var(--danger-color);
        background: #fef2f2;
    }
    
    .member-checkbox-wrapper {
        flex-shrink: 0;
    }
    
    .member-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .member-info {
        flex: 1;
        min-width: 0;
    }
    
    .member-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }
    
    .badge-secondary {
        background: var(--gray-200);
        color: var(--gray-700);
    }
    
    .member-actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    
    .btn-icon-sm {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-md);
        border: 1px solid var(--gray-300);
        background: white;
        color: var(--gray-500);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
    }
    
    .btn-icon-sm:hover {
        background: var(--gray-100);
        color: var(--gray-900);
        border-color: var(--gray-400);
    }
    
    .member-card-body {
        padding: 0.5rem;
    }
    
    .member-detail-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .detail-section {
        background: var(--gray-50);
        border-radius: var(--radius-md);
        padding: 0.5rem;
        border: 1px solid var(--gray-200);
    }
    
    .section-title {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--gray-700);
        margin: 0 0 0.4rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .section-title i {
        color: var(--primary-color);
    }
    
    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.2rem 0;
        font-size: 0.75rem;
    }
    
    .detail-item .label {
        color: var(--gray-500);
        font-weight: 500;
    }
    
    .detail-item .value {
        color: var(--gray-900);
        font-weight: 600;
    }
    
    .detail-item.success .value {
        color: #059669;
    }
    
    .detail-item.warning .value {
        color: #d97706;
    }
    
    .detail-item.danger .value {
        color: var(--danger-color);
    }
    
    .detail-item.info .value {
        color: var(--primary-color);
    }
    
    .services-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-200);
    }
    
    .services-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .service-tag {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #dbeafe;
        color: #1e40af;
        border-radius: var(--radius-md);
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .bulk-actions-bar {
        margin-top: 1rem;
        background: white;
        border-radius: var(--radius-lg);
        padding: 1rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
    }
    
    .bulk-actions-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    
    .bulk-select-all {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        cursor: pointer;
        font-weight: 500;
        color: var(--gray-700);
    }
    
    .bulk-action-buttons {
        display: flex;
        gap: 0.5rem;
    }
    
    .no-members-message {
        text-align: center;
        padding: 2rem;
        color: var(--gray-500);
        font-size: 1rem;
    }
    
    .no-members-message i {
        font-size: 2rem;
        display: block;
        margin-bottom: 0.5rem;
        color: var(--gray-400);
    }
    
    .badge-flight-done {
        background-color: #d1fae5 !important;
        color: #065f46 !important;
        border: 1px solid #a7f3d0 !important;
    }
    
    .badge-flight-done:hover {
        background-color: #a7f3d0 !important;
    }
    
    .badge-flight-pending {
        background-color: #fef3c7 !important;
        color: #92400e !important;
        border: 1px solid #fde68a !important;
    }
    
    .badge-flight-pending:hover {
        background-color: #fde68a !important;
    }
    
    @media (max-width: 768px) {
        .members-list {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        
        .member-card-header {
            padding: 0.5rem;
            gap: 0.5rem;
        }
        
        .member-detail-grid {
            grid-template-columns: 1fr;
        }
        
        .bulk-actions-content {
            flex-direction: column;
            align-items: stretch;
        }
        
        .bulk-action-buttons {
            flex-direction: column;
        }
        
        .btn-icon-sm {
            width: 28px;
            height: 28px;
            font-size: 0.875rem;
        }
    }
    </style>
    
    <script>
    // Position fixed dropdowns near their trigger buttons
    document.addEventListener('click', function(e) {
        const dropdownBtn = e.target.closest('[data-toggle="dropdown"]');
        if (!dropdownBtn) return;
        
        const dropdown = dropdownBtn.closest('.dropdown');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (!menu) return;
        
        // Toggle show class
        menu.classList.toggle('show');
        
        if (menu.classList.contains('show')) {
            // Position the dropdown menu near the button
            const btnRect = dropdownBtn.getBoundingClientRect();
            const menuHeight = 400; // max-height from CSS
            
            // Position below button
            let top = btnRect.bottom + 8;
            let left = btnRect.right - menu.offsetWidth;
            
            // Adjust if menu goes off screen
            const windowHeight = window.innerHeight;
            if (top + menuHeight > windowHeight) {
                top = btnRect.top - menuHeight - 8;
            }
            
            const windowWidth = window.innerWidth;
            if (left < 0) {
                left = btnRect.right - 50;
            } else if (left + menu.offsetWidth > windowWidth) {
                left = windowWidth - menu.offsetWidth - 20;
            }
            
            menu.style.top = top + 'px';
            menu.style.left = left + 'px';
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
                m.style.top = '';
                m.style.left = '';
            });
        }
    });
    </script>
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    // Clear any output that might have been buffered
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error loading members: ' . $e->getMessage(),
        'error_details' => $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
?>