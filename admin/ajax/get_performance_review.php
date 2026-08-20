<?php
require_once '../../includes/language_helpers.php';
require_once '../../includes/db.php';
require_once '../security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

enforce_auth();
require_permission('hr.performance');

if (!isset($_GET['review_id'])) {
    http_response_code(400);
    exit('Review ID required');
}

$review_id = (int)$_GET['review_id'];
$edit = isset($_GET['edit']) && $_GET['edit'] == '1';

try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.name as employee_name, u.email as employee_email,
               reviewer.name as reviewer_name
        FROM performance_reviews pr
        JOIN users u ON pr.user_id = u.id AND u.tenant_id = ? AND u.branch_id = ?
        LEFT JOIN users reviewer ON pr.reviewer_id = reviewer.id AND reviewer.tenant_id = ? AND reviewer.branch_id = ?
        WHERE pr.id = ? AND pr.tenant_id = ? AND pr.branch_id = ?
    ");
    $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $review_id, $tenant_id, $branch_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        http_response_code(404);
        exit('Review not found');
    }

    if ($edit) {
        // Return JSON for editing
        header('Content-Type: application/json');
        echo json_encode($review);
        exit;
    }

    // Display review details
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><?php echo __('performance_review_details'); ?></h5>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="printReview()">
            <i class="feather icon-printer mr-1"></i><?php echo __('print'); ?>
        </button>
    </div>
    <div id="reviewContent">
        <div class="row">
            <div class="col-md-6">
                <h6><?php echo __('employee_information'); ?></h6>
                <p><strong><?php echo __('name'); ?>:</strong> <?php echo htmlspecialchars($review['employee_name']); ?></p>
                <p><strong><?php echo __('email'); ?>:</strong> <?php echo htmlspecialchars($review['employee_email']); ?></p>
            </div>
            <div class="col-md-6">
                <h6><?php echo __('review_information'); ?></h6>
                <p><strong><?php echo __('reviewer'); ?>:</strong> <?php echo htmlspecialchars($review['reviewer_name'] ?: 'N/A'); ?></p>
                <p><strong><?php echo __('review_date'); ?>:</strong> <?php echo date('M d, Y', strtotime($review['review_date'])); ?></p>
                <p><strong><?php echo __('status'); ?>:</strong>
                    <span class="badge badge-<?php
                        echo $review['status'] == 'approved' ? 'success' :
                             ($review['status'] == 'submitted' ? 'info' :
                             ($review['status'] == 'rejected' ? 'danger' : 'secondary'));
                    ?>">
                        <?php echo __($review['status']); ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-6">
                <h6><?php echo __('review_period'); ?></h6>
                <p><strong><?php echo __('from'); ?>:</strong> <?php echo date('M d, Y', strtotime($review['period_start'])); ?></p>
                <p><strong><?php echo __('to'); ?>:</strong> <?php echo date('M d, Y', strtotime($review['period_end'])); ?></p>
            </div>
            <div class="col-md-6">
                <h6><?php echo __('overall_rating'); ?></h6>
                <div class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="feather icon-star <?php echo $i <= $review['overall_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                    <?php endfor; ?>
                    <span class="ml-2"><?php echo $review['overall_rating'] ? $review['overall_rating'] . '/5' : __('not_rated'); ?></span>
                </div>
            </div>
        </div>

        <?php if ($review['comments']): ?>
        <div class="mt-3">
            <h6><?php echo __('comments'); ?></h6>
            <p><?php echo nl2br(htmlspecialchars($review['comments'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($review['achievements']): ?>
        <div class="mt-3">
            <h6><?php echo __('achievements'); ?></h6>
            <p><?php echo nl2br(htmlspecialchars($review['achievements'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($review['areas_for_improvement']): ?>
        <div class="mt-3">
            <h6><?php echo __('areas_for_improvement'); ?></h6>
            <p><?php echo nl2br(htmlspecialchars($review['areas_for_improvement'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($review['goals']): ?>
        <div class="mt-3">
            <h6><?php echo __('goals'); ?></h6>
            <p><?php echo nl2br(htmlspecialchars($review['goals'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($review['recommendations']): ?>
        <div class="mt-3">
            <h6><?php echo __('recommendations'); ?></h6>
            <p><?php echo nl2br(htmlspecialchars($review['recommendations'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-3 text-muted">
            <small><?php echo __('created_at'); ?>: <?php echo date('M d, Y H:i', strtotime($review['created_at'])); ?>
            <?php if ($review['updated_at'] != $review['created_at']): ?>
                | <?php echo __('updated_at'); ?>: <?php echo date('M d, Y H:i', strtotime($review['updated_at'])); ?>
            <?php endif; ?>
            </small>
        </div>
    </div>

    <script>
    function printReview() {
        var printWindow = window.open('', '_blank');
        var content = document.getElementById('reviewContent').innerHTML;
        var styles = `
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .row { display: flex; margin-bottom: 20px; }
                .col-md-6 { flex: 1; padding: 0 10px; }
                h6 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; margin-bottom: 10px; }
                p { margin: 5px 0; }
                strong { color: #555; }
                .rating-stars { margin: 10px 0; }
                .text-warning { color: #ffc107; }
                .text-muted { color: #6c757d; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .badge-success { background-color: #28a745; color: white; }
                .badge-info { background-color: #17a2b8; color: white; }
                .badge-danger { background-color: #dc3545; color: white; }
                .badge-secondary { background-color: #6c757d; color: white; }
                .text-muted { color: #6c757d; font-size: 12px; }
                @media print {
                    body { margin: 0; }
                    .row { page-break-inside: avoid; }
                }
            </style>
        `;
        printWindow.document.write('<html><head><title><?php echo __('performance_review'); ?></title>' + styles + '</head><body>');
        printWindow.document.write('<h2 style="text-align: center; margin-bottom: 30px;"><?php echo __('performance_review'); ?></h2>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    </script>
    <?php

} catch (Exception $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>