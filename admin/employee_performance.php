<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';

$query = "
    SELECT u.*, sm.base_salary, sm.currency as salary_currency,
           pr.id as review_id, pr.overall_rating, pr.review_date, pr.status as review_status,
           pr.period_start, pr.period_end
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
    LEFT JOIN performance_reviews pr ON u.id = pr.user_id AND pr.tenant_id = u.tenant_id AND pr.branch_id = u.branch_id
        AND pr.id = (SELECT MAX(id) FROM performance_reviews WHERE user_id = u.id AND tenant_id = u.tenant_id AND branch_id = u.branch_id)
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
";

$params = [$tenant_id, $branch_id];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $query .= " AND u.fired = 0";
    } elseif ($status_filter === 'terminated') {
        $query .= " AND u.fired = 1";
    }
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$evaluated = count(array_filter($employees, function($e) { return $e['review_id'] && $e['review_status'] == 'approved'; }));
$ratings = array_filter(array_column($employees, 'overall_rating'));
$avg_rating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
$pending = count(array_filter($employees, function($e) { return !$e['review_id']; }));
$drafts = count(array_filter($employees, function($e) { return $e['review_id'] && $e['review_status'] == 'draft'; }));
$total = count($employees);

// Rating distribution
$rating_dist = [0, 0, 0, 0, 0];
foreach ($ratings as $r) {
    $idx = min(4, max(0, ceil($r) - 1));
    $rating_dist[$idx]++;
}

$page_title = __('performance_reviews');
include '../includes/header.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">

                            <!-- ========== HERO HEADER ========== -->
                            <div class="pr-hero">
                                <div class="pr-hero-content">
                                    <div class="pr-hero-left">
                                        <a href="hr_management.php" class="pr-back-link">
                                            <i class="feather icon-arrow-left"></i>
                                            <?php echo __('back_to_hr'); ?>
                                        </a>
                                        <h1>
                                            <i class="feather icon-trending-up"></i>
                                            <?php echo __('performance_reviews'); ?>
                                        </h1>
                                        <p><?php echo __('track_evaluate_and_improve_employee_performance'); ?></p>
                                    </div>
                                    <div class="pr-hero-right">
                                        <div class="pr-hero-stat">
                                            <span class="pr-hero-stat-number"><?php echo number_format($avg_rating, 1); ?></span>
                                            <span class="pr-hero-stat-label"><?php echo __('avg_rating'); ?></span>
                                            <div class="pr-hero-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="feather icon-star <?php echo $i <= round($avg_rating) ? 'filled' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pr-hero-shape"></div>
                                <div class="pr-hero-shape-2"></div>
                            </div>

                            <!-- ========== STAT CARDS ========== -->
                            <div class="pr-stats-grid">
                                <div class="pr-stat-card">
                                    <div class="pr-stat-ring">
                                        <svg viewBox="0 0 40 40">
                                            <circle cx="20" cy="20" r="16" class="pr-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="pr-ring-fill evaluated"
                                                style="--pct: <?php echo $total > 0 ? ($evaluated / $total) * 100 : 0; ?>"/>
                                        </svg>
                                    </div>
                                    <div class="pr-stat-info">
                                        <span class="pr-stat-number"><?php echo $evaluated; ?></span>
                                        <span class="pr-stat-label"><?php echo __('evaluated'); ?></span>
                                    </div>
                                </div>

                                <div class="pr-stat-card">
                                    <div class="pr-stat-ring">
                                        <svg viewBox="0 0 40 40">
                                            <circle cx="20" cy="20" r="16" class="pr-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="pr-ring-fill pending"
                                                style="--pct: <?php echo $total > 0 ? ($pending / $total) * 100 : 0; ?>"/>
                                        </svg>
                                    </div>
                                    <div class="pr-stat-info">
                                        <span class="pr-stat-number"><?php echo $pending; ?></span>
                                        <span class="pr-stat-label"><?php echo __('pending_reviews'); ?></span>
                                    </div>
                                </div>

                                <div class="pr-stat-card">
                                    <div class="pr-stat-ring">
                                        <svg viewBox="0 0 40 40">
                                            <circle cx="20" cy="20" r="16" class="pr-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="pr-ring-fill drafts"
                                                style="--pct: <?php echo $total > 0 ? ($drafts / $total) * 100 : 0; ?>"/>
                                        </svg>
                                    </div>
                                    <div class="pr-stat-info">
                                        <span class="pr-stat-number"><?php echo $drafts; ?></span>
                                        <span class="pr-stat-label"><?php echo __('drafts'); ?></span>
                                    </div>
                                </div>

                                <div class="pr-stat-card">
                                    <div class="pr-stat-ring">
                                        <svg viewBox="0 0 40 40">
                                            <circle cx="20" cy="20" r="16" class="pr-ring-bg"/>
                                            <circle cx="20" cy="20" r="16" class="pr-ring-fill total"
                                                style="--pct: 100"/>
                                        </svg>
                                    </div>
                                    <div class="pr-stat-info">
                                        <span class="pr-stat-number"><?php echo $total; ?></span>
                                        <span class="pr-stat-label"><?php echo __('total_employees'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== SEARCH & FILTERS ========== -->
                            <div class="pr-filters">
                                <form method="GET" class="pr-filters-form">
                                    <div class="pr-search-box">
                                        <i class="feather icon-search"></i>
                                        <input type="text" name="search" placeholder="<?php echo __('search_by_name_or_email'); ?>"
                                            value="<?php echo htmlspecialchars($search); ?>">
                                        <?php if (!empty($search)): ?>
                                            <a href="employee_performance.php" class="pr-search-clear">
                                                <i class="feather icon-x"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pr-filter-pills">
                                        <button type="submit" name="status" value="all"
                                            class="pr-pill <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                                            <i class="feather icon-layers"></i> <?php echo __('all'); ?>
                                            <span class="pr-pill-count"><?php echo $total; ?></span>
                                        </button>
                                        <button type="submit" name="status" value="active"
                                            class="pr-pill <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                                            <i class="feather icon-check-circle"></i> <?php echo __('active'); ?>
                                        </button>
                                        <button type="submit" name="status" value="terminated"
                                            class="pr-pill <?php echo $status_filter === 'terminated' ? 'active' : ''; ?>">
                                            <i class="feather icon-x-circle"></i> <?php echo __('terminated'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- ========== RATING DISTRIBUTION ========== -->
                            <?php if (count($ratings) > 0): ?>
                            <div class="pr-rating-dist">
                                <div class="pr-rating-dist-header">
                                    <h3><?php echo __('rating_distribution'); ?></h3>
                                    <span class="pr-rating-dist-total"><?php echo count($ratings); ?> <?php echo __('reviews'); ?></span>
                                </div>
                                <div class="pr-rating-bars">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <?php $count = $rating_dist[$i - 1]; $pct = count($ratings) > 0 ? ($count / count($ratings)) * 100 : 0; ?>
                                        <div class="pr-rating-bar-row">
                                            <span class="pr-rating-bar-label">
                                                <?php echo $i; ?> <i class="feather icon-star"></i>
                                            </span>
                                            <div class="pr-rating-bar-track">
                                                <div class="pr-rating-bar-fill star-<?php echo $i; ?>" style="width: <?php echo $pct; ?>%"></div>
                                            </div>
                                            <span class="pr-rating-bar-count"><?php echo $count; ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ========== EMPLOYEE TABLE ========== -->
                            <div class="pr-table-card">
                                <div class="pr-table-header">
                                    <h3>
                                        <i class="feather icon-users"></i>
                                        <?php echo __('employees'); ?>
                                        <span class="pr-table-count"><?php echo count($employees); ?></span>
                                    </h3>
                                </div>

                                <?php if (empty($employees)): ?>
                                    <div class="pr-empty-state">
                                        <div class="pr-empty-icon">
                                            <i class="feather icon-search"></i>
                                        </div>
                                        <h4><?php echo __('no_employees_found'); ?></h4>
                                        <p><?php echo __('try_adjusting_filters'); ?></p>
                                        <a href="employee_performance.php" class="pr-empty-btn">
                                            <i class="feather icon-refresh-cw"></i> <?php echo __('clear_filters'); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="pr-table-body">
                                        <table class="pr-table">
                                            <thead>
                                                <tr>
                                                    <th><?php echo __('employee'); ?></th>
                                                    <th><?php echo __('role'); ?></th>
                                                    <th><?php echo __('hire_date'); ?></th>
                                                    <th><?php echo __('status'); ?></th>
                                                    <th><?php echo __('performance'); ?></th>
                                                    <th class="text-right"><?php echo __('actions'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employees as $employee): ?>
                                                    <tr class="pr-table-row">
                                                        <td>
                                                            <div class="pr-employee-cell">
                                                                <div class="pr-avatar">
                                                                    <?php if (!empty($employee['profile_pic']) && $employee['profile_pic'] !== 'avatar-1.jpg'): ?>
                                                                        <img src="../assets/images/user/<?= htmlspecialchars(basename($employee['profile_pic'])); ?>"
                                                                            alt="<?php echo htmlspecialchars($employee['name']); ?>">
                                                                    <?php else: ?>
                                                                        <span><?php echo strtoupper(substr($employee['name'], 0, 2)); ?></span>
                                                                    <?php endif; ?>
                                                                    <div class="pr-avatar-status <?php echo $employee['fired'] ? 'offline' : 'online'; ?>"></div>
                                                                </div>
                                                                <div class="pr-employee-info">
                                                                    <h5><?php echo htmlspecialchars($employee['name']); ?></h5>
                                                                    <p><?php echo htmlspecialchars($employee['email']); ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="pr-role-badge">
                                                                <?php echo htmlspecialchars(ucfirst($employee['role'] ?: 'N/A')); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="pr-date-cell">
                                                                <i class="feather icon-calendar"></i>
                                                                <?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : '—'; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($employee['fired']): ?>
                                                                <span class="pr-status-badge terminated">
                                                                    <span class="pr-status-dot"></span>
                                                                    <?php echo __('terminated'); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="pr-status-badge active">
                                                                    <span class="pr-status-dot"></span>
                                                                    <?php echo __('active'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($employee['review_id'] && $employee['review_status'] == 'approved'): ?>
                                                                <div class="pr-performance-cell">
                                                                    <div class="pr-rating-display">
                                                                        <div class="pr-rating-stars">
                                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                <i class="feather icon-star <?php echo $i <= round($employee['overall_rating']) ? 'filled' : ''; ?>"></i>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                        <span class="pr-rating-value"><?php echo $employee['overall_rating']; ?></span>
                                                                    </div>
                                                                    <small class="pr-review-date">
                                                                        <?php echo $employee['review_date'] ? date('M d, Y', strtotime($employee['review_date'])) : ''; ?>
                                                                    </small>
                                                                </div>
                                                            <?php elseif ($employee['review_id'] && $employee['review_status'] == 'draft'): ?>
                                                                <span class="pr-review-badge draft">
                                                                    <i class="feather icon-edit-3"></i>
                                                                    <?php echo __('draft'); ?>
                                                                </span>
                                                            <?php elseif ($employee['review_id'] && $employee['review_status'] == 'submitted'): ?>
                                                                <span class="pr-review-badge submitted">
                                                                    <i class="feather icon-send"></i>
                                                                    <?php echo __('submitted'); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="pr-review-badge none">
                                                                    <i class="feather icon-minus-circle"></i>
                                                                    <?php echo __('not_evaluated'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right">
                                                            <div class="pr-actions">
                                                                <?php if ($employee['review_id']): ?>
                                                                    <button type="button" class="pr-action-btn view" onclick="viewReview(<?php echo $employee['review_id']; ?>)" title="<?php echo __('view_review'); ?>">
                                                                        <i class="feather icon-eye"></i>
                                                                    </button>
                                                                    <button type="button" class="pr-action-btn edit" onclick="editReview(<?php echo $employee['review_id']; ?>)" title="<?php echo __('edit_review'); ?>">
                                                                        <i class="feather icon-edit-2"></i>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="pr-action-btn add" onclick="addReview(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars(addslashes($employee['name'])); ?>')" title="<?php echo __('add_review'); ?>">
                                                                        <i class="feather icon-plus"></i>
                                                                        <span><?php echo __('add_review'); ?></span>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== VIEW REVIEW MODAL ========== -->
<div class="modal fade" id="viewReviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content pr-modal">
            <div class="pr-modal-header view">
                <div class="pr-modal-header-content">
                    <i class="feather icon-clipboard"></i>
                    <h5><?php echo __('performance_review_details'); ?></h5>
                </div>
                <button type="button" class="pr-modal-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>
            <div class="pr-modal-body" id="reviewDetails">
                <div class="pr-modal-loading">
                    <div class="pr-spinner"></div>
                    <p><?php echo __('loading'); ?>...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== ADD/EDIT REVIEW MODAL ========== -->
<div class="modal fade" id="addReviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content pr-modal">
            <div class="pr-modal-header edit">
                <div class="pr-modal-header-content">
                    <i class="feather icon-edit-3"></i>
                    <h5><?php echo __('add_performance_review'); ?></h5>
                </div>
                <button type="button" class="pr-modal-close" data-dismiss="modal">
                    <i class="feather icon-x"></i>
                </button>
            </div>
            <form id="performanceReviewForm">
                <div class="pr-modal-body">
                    <!-- Basic Info Section -->
                    <div class="pr-form-section">
                        <div class="pr-form-section-title">
                            <i class="feather icon-info"></i>
                            <h4><?php echo __('basic_information'); ?></h4>
                        </div>
                        <div class="pr-form-grid">
                            <div class="pr-form-group">
                                <label><?php echo __('employee'); ?></label>
                                <div class="pr-form-static">
                                    <i class="feather icon-user"></i>
                                    <input type="text" id="employeeName" readonly>
                                </div>
                                <input type="hidden" id="userId" name="user_id">
                                <input type="hidden" id="reviewId" name="review_id">
                            </div>
                            <div class="pr-form-group">
                                <label><?php echo __('review_date'); ?></label>
                                <div class="pr-form-input-wrap">
                                    <i class="feather icon-calendar"></i>
                                    <input type="date" id="reviewDate" name="review_date" required>
                                </div>
                            </div>
                            <div class="pr-form-group">
                                <label><?php echo __('period_start'); ?></label>
                                <div class="pr-form-input-wrap">
                                    <i class="feather icon-calendar"></i>
                                    <input type="date" id="periodStart" name="period_start" required>
                                </div>
                            </div>
                            <div class="pr-form-group">
                                <label><?php echo __('period_end'); ?></label>
                                <div class="pr-form-input-wrap">
                                    <i class="feather icon-calendar"></i>
                                    <input type="date" id="periodEnd" name="period_end" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rating Section -->
                    <div class="pr-form-section">
                        <div class="pr-form-section-title">
                            <i class="feather icon-star"></i>
                            <h4><?php echo __('rating_assessment'); ?></h4>
                        </div>
                        <div class="pr-form-grid cols-2">
                            <div class="pr-form-group">
                                <label><?php echo __('overall_rating'); ?></label>
                                <div class="pr-rating-input">
                                    <div class="pr-star-selector" id="starSelector">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="feather icon-star" data-value="<?php echo $i; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <input type="number" id="overallRating" name="overall_rating" min="1" max="5" step="0.1" class="pr-rating-number">
                                </div>
                            </div>
                            <div class="pr-form-group">
                                <label><?php echo __('reviewer'); ?></label>
                                <div class="pr-form-static">
                                    <i class="feather icon-shield"></i>
                                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Review Section -->
                    <div class="pr-form-section">
                        <div class="pr-form-section-title">
                            <i class="feather icon-file-text"></i>
                            <h4><?php echo __('detailed_review'); ?></h4>
                        </div>
                        <div class="pr-form-stack">
                            <div class="pr-form-group">
                                <label>
                                    <i class="feather icon-message-square"></i>
                                    <?php echo __('comments'); ?>
                                </label>
                                <textarea id="comments" name="comments" rows="3" placeholder="<?php echo __('general_comments_placeholder'); ?>"></textarea>
                            </div>
                            <div class="pr-form-group">
                                <label>
                                    <i class="feather icon-award"></i>
                                    <?php echo __('achievements'); ?>
                                </label>
                                <textarea id="achievements" name="achievements" rows="3" placeholder="<?php echo __('achievements_placeholder'); ?>"></textarea>
                            </div>
                            <div class="pr-form-group">
                                <label>
                                    <i class="feather icon-alert-triangle"></i>
                                    <?php echo __('areas_for_improvement'); ?>
                                </label>
                                <textarea id="areasForImprovement" name="areas_for_improvement" rows="3" placeholder="<?php echo __('improvement_placeholder'); ?>"></textarea>
                            </div>
                            <div class="pr-form-group">
                                <label>
                                    <i class="feather icon-target"></i>
                                    <?php echo __('goals'); ?>
                                </label>
                                <textarea id="goals" name="goals" rows="3" placeholder="<?php echo __('goals_placeholder'); ?>"></textarea>
                            </div>
                            <div class="pr-form-group">
                                <label>
                                    <i class="feather icon-thumbs-up"></i>
                                    <?php echo __('recommendations'); ?>
                                </label>
                                <textarea id="recommendations" name="recommendations" rows="3" placeholder="<?php echo __('recommendations_placeholder'); ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Status Section -->
                    <div class="pr-form-section">
                        <div class="pr-form-section-title">
                            <i class="feather icon-flag"></i>
                            <h4><?php echo __('review_status'); ?></h4>
                        </div>
                        <div class="pr-status-selector">
                            <label class="pr-status-option">
                                <input type="radio" name="status" value="draft" checked>
                                <div class="pr-status-card draft">
                                    <i class="feather icon-edit-3"></i>
                                    <span><?php echo __('draft'); ?></span>
                                    <small><?php echo __('save_for_later'); ?></small>
                                </div>
                            </label>
                            <label class="pr-status-option">
                                <input type="radio" name="status" value="submitted">
                                <div class="pr-status-card submitted">
                                    <i class="feather icon-send"></i>
                                    <span><?php echo __('submitted'); ?></span>
                                    <small><?php echo __('ready_for_approval'); ?></small>
                                </div>
                            </label>
                            <label class="pr-status-option">
                                <input type="radio" name="status" value="approved">
                                <div class="pr-status-card approved">
                                    <i class="feather icon-check-circle"></i>
                                    <span><?php echo __('approved'); ?></span>
                                    <small><?php echo __('finalize_review'); ?></small>
                                </div>
                            </label>
                            <label class="pr-status-option">
                                <input type="radio" name="status" value="rejected">
                                <div class="pr-status-card rejected">
                                    <i class="feather icon-x-circle"></i>
                                    <span><?php echo __('rejected'); ?></span>
                                    <small><?php echo __('needs_revision'); ?></small>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="pr-modal-footer">
                    <button type="button" class="pr-btn secondary" data-dismiss="modal">
                        <i class="feather icon-x"></i>
                        <?php echo __('cancel'); ?>
                    </button>
                    <button type="submit" class="pr-btn primary" id="submitBtn">
                        <i class="feather icon-save"></i>
                        <?php echo __('save_review'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ============================================
   PERFORMANCE REVIEWS - COMPLETE REDESIGN
   ============================================ */

:root {
    --pr-primary: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --pr-primary-light: rgba(64, 153, 255, 0.1);
    --pr-primary-dark: #2a7acc;
    --pr-success: #10b981;
    --pr-success-light: #ecfdf5;
    --pr-danger: #ef4444;
    --pr-danger-light: #fef2f2;
    --pr-warning: #f59e0b;
    --pr-warning-light: #fffbeb;
    --pr-info: #3b82f6;
    --pr-info-light: #eff6ff;
    --pr-orange: #f97316;
    --pr-gray-50: #f9fafb;
    --pr-gray-100: #f3f4f6;
    --pr-gray-200: #e5e7eb;
    --pr-gray-300: #d1d5db;
    --pr-gray-400: #9ca3af;
    --pr-gray-500: #6b7280;
    --pr-gray-600: #4b5563;
    --pr-gray-700: #374151;
    --pr-gray-800: #1f2937;
    --pr-gray-900: #111827;
    --pr-radius: 16px;
    --pr-radius-sm: 10px;
    --pr-radius-xs: 6px;
    --pr-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --pr-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04);
    --pr-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.03);
    --pr-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== HERO ===== */
.pr-hero {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-radius: var(--pr-radius);
    padding: 32px 36px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    color: #fff;
}

.pr-hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.pr-hero-shape {
    position: absolute;
    top: -40%;
    right: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    z-index: 1;
}

.pr-hero-shape-2 {
    position: absolute;
    bottom: -60%;
    right: 15%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
    z-index: 1;
}

.pr-back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255,255,255,0.7);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    margin-bottom: 12px;
    transition: var(--pr-transition);
}

.pr-back-link:hover {
    color: #fff;
    gap: 8px;
    text-decoration: none;
}

.pr-hero h1 {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pr-hero h1 i {
    font-size: 22px;
    opacity: 0.9;
}

.pr-hero p {
    margin: 0;
    opacity: 0.75;
    font-size: 14px;
}

.pr-hero-stat {
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: var(--pr-radius);
    padding: 20px 28px;
    text-align: center;
}

.pr-hero-stat-number {
    display: block;
    font-size: 36px;
    font-weight: 800;
    line-height: 1;
}

.pr-hero-stat-label {
    display: block;
    font-size: 12px;
    opacity: 0.75;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

.pr-hero-stars {
    margin-top: 8px;
    display: flex;
    justify-content: center;
    gap: 2px;
}

.pr-hero-stars i {
    font-size: 14px;
    color: rgba(255,255,255,0.3);
}

.pr-hero-stars i.filled {
    color: #fbbf24;
}

/* ===== STAT CARDS ===== */
.pr-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.pr-stat-card {
    background: #fff;
    border-radius: var(--pr-radius);
    padding: 20px;
    box-shadow: var(--pr-shadow);
    border: 1px solid var(--pr-gray-100);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--pr-transition);
}

.pr-stat-card:hover {
    box-shadow: var(--pr-shadow-md);
    transform: translateY(-2px);
}

.pr-stat-ring {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}

.pr-stat-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.pr-ring-bg {
    fill: none;
    stroke: var(--pr-gray-100);
    stroke-width: 4;
}

.pr-ring-fill {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-dasharray: 100.53;
    stroke-dashoffset: calc(100.53 - (100.53 * var(--pct, 0) / 100));
    transition: stroke-dashoffset 1.5s ease;
}

.pr-ring-fill.evaluated { stroke: var(--pr-success); }
.pr-ring-fill.pending { stroke: var(--pr-warning); }
.pr-ring-fill.drafts { stroke: var(--pr-info); }
.pr-ring-fill.total { stroke: var(--pr-primary); }

.pr-stat-number {
    display: block;
    font-size: 24px;
    font-weight: 800;
    color: var(--pr-gray-900);
    line-height: 1;
}

.pr-stat-label {
    display: block;
    font-size: 12px;
    color: var(--pr-gray-500);
    font-weight: 500;
    margin-top: 2px;
}

/* ===== FILTERS ===== */
.pr-filters {
    background: #fff;
    border-radius: var(--pr-radius);
    padding: 16px 20px;
    box-shadow: var(--pr-shadow);
    border: 1px solid var(--pr-gray-100);
    margin-bottom: 24px;
}

.pr-filters-form {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.pr-search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
    display: flex;
    align-items: center;
}

.pr-search-box i {
    position: absolute;
    left: 14px;
    color: var(--pr-gray-400);
    font-size: 16px;
    pointer-events: none;
}

.pr-search-box input {
    width: 100%;
    padding: 10px 14px 10px 42px;
    border: 2px solid var(--pr-gray-200);
    border-radius: var(--pr-radius-sm);
    font-size: 14px;
    color: var(--pr-gray-800);
    transition: var(--pr-transition);
    background: var(--pr-gray-50);
}

.pr-search-box input:focus {
    outline: none;
    border-color: var(--pr-primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.pr-search-box input::placeholder {
    color: var(--pr-gray-400);
}

.pr-search-clear {
    position: absolute;
    right: 12px;
    color: var(--pr-gray-400);
    transition: var(--pr-transition);
    display: flex;
}

.pr-search-clear:hover {
    color: var(--pr-danger);
}

.pr-filter-pills {
    display: flex;
    gap: 8px;
}

.pr-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 2px solid var(--pr-gray-200);
    border-radius: 50px;
    background: #fff;
    color: var(--pr-gray-600);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--pr-transition);
}

.pr-pill:hover {
    border-color: var(--pr-primary);
    color: var(--pr-primary);
    background: var(--pr-primary-light);
}

.pr-pill.active {
    background: var(--pr-primary);
    border-color: var(--pr-primary);
    color: #fff;
}

.pr-pill-count {
    background: rgba(0,0,0,0.1);
    padding: 1px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.pr-pill.active .pr-pill-count {
    background: rgba(255,255,255,0.2);
}

/* ===== RATING DISTRIBUTION ===== */
.pr-rating-dist {
    background: #fff;
    border-radius: var(--pr-radius);
    padding: 24px;
    box-shadow: var(--pr-shadow);
    border: 1px solid var(--pr-gray-100);
    margin-bottom: 24px;
}

.pr-rating-dist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.pr-rating-dist-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--pr-gray-800);
    margin: 0;
}

.pr-rating-dist-total {
    font-size: 13px;
    color: var(--pr-gray-400);
    font-weight: 500;
}

.pr-rating-bars {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pr-rating-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pr-rating-bar-label {
    width: 40px;
    font-size: 13px;
    font-weight: 600;
    color: var(--pr-gray-600);
    display: flex;
    align-items: center;
    gap: 3px;
    flex-shrink: 0;
}

.pr-rating-bar-label i {
    font-size: 12px;
    color: #fbbf24;
}

.pr-rating-bar-track {
    flex: 1;
    height: 8px;
    background: var(--pr-gray-100);
    border-radius: 4px;
    overflow: hidden;
}

.pr-rating-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease;
}

.pr-rating-bar-fill.star-5 { background: var(--pr-success); }
.pr-rating-bar-fill.star-4 { background: #34d399; }
.pr-rating-bar-fill.star-3 { background: var(--pr-warning); }
.pr-rating-bar-fill.star-2 { background: var(--pr-orange); }
.pr-rating-bar-fill.star-1 { background: var(--pr-danger); }

.pr-rating-bar-count {
    width: 24px;
    font-size: 13px;
    font-weight: 600;
    color: var(--pr-gray-500);
    text-align: right;
    flex-shrink: 0;
}

/* ===== TABLE CARD ===== */
.pr-table-card {
    background: #fff;
    border-radius: var(--pr-radius);
    box-shadow: var(--pr-shadow);
    border: 1px solid var(--pr-gray-100);
    overflow: hidden;
    margin-bottom: 24px;
}

.pr-table-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--pr-gray-100);
}

.pr-table-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--pr-gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pr-table-header h3 i {
    color: var(--pr-primary);
}

.pr-table-count {
    background: var(--pr-primary-light);
    color: var(--pr-primary);
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.pr-table-body {
    overflow-x: auto;
}

.pr-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.pr-table thead th {
    padding: 14px 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--pr-gray-500);
    background: var(--pr-gray-50);
    border-bottom: 1px solid var(--pr-gray-100);
    white-space: nowrap;
}

.pr-table tbody td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--pr-gray-50);
    vertical-align: middle;
}

.pr-table-row {
    transition: var(--pr-transition);
}

.pr-table-row:hover {
    background: var(--pr-gray-50);
}

.pr-table-row:last-child td {
    border-bottom: none;
}

/* Employee Cell */
.pr-employee-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.pr-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
    background: linear-gradient(135deg, var(--pr-primary), #8b5cf6);
}

.pr-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pr-avatar span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}

.pr-avatar-status {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.pr-avatar-status.online { background: var(--pr-success); }
.pr-avatar-status.offline { background: var(--pr-gray-400); }

.pr-employee-info h5 {
    font-size: 14px;
    font-weight: 600;
    color: var(--pr-gray-800);
    margin: 0 0 1px;
}

.pr-employee-info p {
    font-size: 12px;
    color: var(--pr-gray-400);
    margin: 0;
}

/* Role Badge */
.pr-role-badge {
    display: inline-flex;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    background: var(--pr-primary-light);
    color: var(--pr-primary);
}

/* Date Cell */
.pr-date-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--pr-gray-600);
}

.pr-date-cell i {
    font-size: 14px;
    color: var(--pr-gray-400);
}

/* Status Badge */
.pr-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.pr-status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.pr-status-badge.active {
    background: var(--pr-success-light);
    color: var(--pr-success);
}

.pr-status-badge.active .pr-status-dot { background: var(--pr-success); }

.pr-status-badge.terminated {
    background: var(--pr-danger-light);
    color: var(--pr-danger);
}

.pr-status-badge.terminated .pr-status-dot { background: var(--pr-danger); }

/* Performance Cell */
.pr-performance-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pr-rating-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pr-rating-stars {
    display: flex;
    gap: 1px;
}

.pr-rating-stars i {
    font-size: 13px;
    color: var(--pr-gray-200);
}

.pr-rating-stars i.filled {
    color: #fbbf24;
}

.pr-rating-value {
    font-size: 13px;
    font-weight: 700;
    color: var(--pr-gray-700);
}

.pr-review-date {
    font-size: 11px;
    color: var(--pr-gray-400);
}

/* Review Badges */
.pr-review-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.pr-review-badge.draft {
    background: var(--pr-info-light);
    color: var(--pr-info);
}

.pr-review-badge.submitted {
    background: var(--pr-warning-light);
    color: var(--pr-warning);
}

.pr-review-badge.none {
    background: var(--pr-gray-100);
    color: var(--pr-gray-400);
}

/* Action Buttons */
.pr-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.pr-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    border: 1px solid var(--pr-gray-200);
    background: #fff;
    color: var(--pr-gray-600);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--pr-transition);
    white-space: nowrap;
}

.pr-action-btn:hover {
    border-color: var(--pr-primary);
    color: var(--pr-primary);
    background: var(--pr-primary-light);
}

.pr-action-btn.view:hover {
    border-color: var(--pr-info);
    color: var(--pr-info);
    background: var(--pr-info-light);
}

.pr-action-btn.edit:hover {
    border-color: var(--pr-warning);
    color: var(--pr-warning);
    background: var(--pr-warning-light);
}

.pr-action-btn.add {
    border-color: var(--pr-success);
    color: var(--pr-success);
    background: var(--pr-success-light);
}

.pr-action-btn.add:hover {
    background: var(--pr-success);
    color: #fff;
}

/* ===== EMPTY STATE ===== */
.pr-empty-state {
    padding: 60px 20px;
    text-align: center;
}

.pr-empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--pr-gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.pr-empty-icon i {
    font-size: 32px;
    color: var(--pr-gray-400);
}

.pr-empty-state h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--pr-gray-700);
    margin: 0 0 8px;
}

.pr-empty-state p {
    font-size: 14px;
    color: var(--pr-gray-400);
    margin: 0 0 20px;
}

.pr-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border-radius: var(--pr-radius-sm);
    background: var(--pr-primary);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--pr-transition);
}

.pr-empty-btn:hover {
    background: var(--pr-primary-dark);
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
}

/* ===== MODAL ===== */
.pr-modal {
    border: none;
    border-radius: var(--pr-radius);
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
}

.pr-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 28px;
    border-bottom: none;
}

.pr-modal-header.view {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
}

.pr-modal-header.edit {
    background: linear-gradient(135deg, #f59e0b, #f97316);
    color: #fff;
}

.pr-modal-header-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pr-modal-header-content i {
    font-size: 20px;
}

.pr-modal-header-content h5 {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    color: #fff;
}

.pr-modal-close {
    background: rgba(255,255,255,0.15);
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    cursor: pointer;
    transition: var(--pr-transition);
}

.pr-modal-close:hover {
    background: rgba(255,255,255,0.25);
}

.pr-modal-body {
    padding: 28px;
    max-height: 65vh;
    overflow-y: auto;
}

.pr-modal-body::-webkit-scrollbar {
    width: 6px;
}

.pr-modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.pr-modal-body::-webkit-scrollbar-thumb {
    background: var(--pr-gray-200);
    border-radius: 3px;
}

.pr-modal-footer {
    padding: 16px 28px;
    border-top: 1px solid var(--pr-gray-100);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: var(--pr-gray-50);
}

/* Loading State */
.pr-modal-loading {
    text-align: center;
    padding: 40px;
}

.pr-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--pr-gray-200);
    border-top-color: var(--pr-primary);
    border-radius: 50%;
    margin: 0 auto 16px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.pr-modal-loading p {
    color: var(--pr-gray-400);
    font-size: 14px;
}

/* ===== FORM SECTIONS ===== */
.pr-form-section {
    margin-bottom: 28px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--pr-gray-100);
}

.pr-form-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.pr-form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}

.pr-form-section-title i {
    color: var(--pr-primary);
    font-size: 18px;
}

.pr-form-section-title h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--pr-gray-800);
    margin: 0;
}

.pr-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.pr-form-grid.cols-2 {
    grid-template-columns: repeat(2, 1fr);
}

.pr-form-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pr-form-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--pr-gray-700);
    margin-bottom: 6px;
}

.pr-form-group label i {
    font-size: 14px;
    color: var(--pr-gray-400);
}

.pr-form-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.pr-form-input-wrap i {
    position: absolute;
    left: 12px;
    color: var(--pr-gray-400);
    font-size: 15px;
    pointer-events: none;
}

.pr-form-input-wrap input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 2px solid var(--pr-gray-200);
    border-radius: var(--pr-radius-sm);
    font-size: 14px;
    color: var(--pr-gray-800);
    transition: var(--pr-transition);
}

.pr-form-input-wrap input:focus {
    outline: none;
    border-color: var(--pr-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.pr-form-static {
    position: relative;
    display: flex;
    align-items: center;
}

.pr-form-static i {
    position: absolute;
    left: 12px;
    color: var(--pr-gray-400);
    font-size: 15px;
    pointer-events: none;
}

.pr-form-static input {
    width: 100%;
    padding: 10px 14px 10px 40px;
    border: 2px solid var(--pr-gray-100);
    border-radius: var(--pr-radius-sm);
    font-size: 14px;
    color: var(--pr-gray-500);
    background: var(--pr-gray-50);
}

.pr-form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid var(--pr-gray-200);
    border-radius: var(--pr-radius-sm);
    font-size: 14px;
    color: var(--pr-gray-800);
    resize: vertical;
    transition: var(--pr-transition);
    font-family: inherit;
    min-height: 80px;
}

.pr-form-group textarea:focus {
    outline: none;
    border-color: var(--pr-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.pr-form-group textarea::placeholder {
    color: var(--pr-gray-400);
}

/* Rating Input */
.pr-rating-input {
    display: flex;
    align-items: center;
    gap: 16px;
}

.pr-star-selector {
    display: flex;
    gap: 4px;
}

.pr-star-selector i {
    font-size: 28px;
    color: var(--pr-gray-200);
    cursor: pointer;
    transition: var(--pr-transition);
}

.pr-star-selector i:hover,
.pr-star-selector i.active {
    color: #fbbf24;
    transform: scale(1.1);
}

.pr-rating-number {
    width: 70px;
    padding: 10px;
    border: 2px solid var(--pr-gray-200);
    border-radius: var(--pr-radius-sm);
    font-size: 16px;
    font-weight: 700;
    color: var(--pr-gray-800);
    text-align: center;
}

.pr-rating-number:focus {
    outline: none;
    border-color: var(--pr-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Status Selector */
.pr-status-selector {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.pr-status-option {
    cursor: pointer;
    margin: 0;
}

.pr-status-option input {
    display: none;
}

.pr-status-card {
    padding: 16px;
    border-radius: var(--pr-radius-sm);
    border: 2px solid var(--pr-gray-200);
    text-align: center;
    transition: var(--pr-transition);
    background: #fff;
}

.pr-status-card i {
    font-size: 22px;
    display: block;
    margin-bottom: 6px;
}

.pr-status-card span {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--pr-gray-700);
}

.pr-status-card small {
    display: block;
    font-size: 11px;
    color: var(--pr-gray-400);
    margin-top: 2px;
}

.pr-status-card.draft i { color: var(--pr-info); }
.pr-status-card.submitted i { color: var(--pr-warning); }
.pr-status-card.approved i { color: var(--pr-success); }
.pr-status-card.rejected i { color: var(--pr-danger); }

.pr-status-option input:checked + .pr-status-card.draft {
    border-color: var(--pr-info);
    background: var(--pr-info-light);
}

.pr-status-option input:checked + .pr-status-card.submitted {
    border-color: var(--pr-warning);
    background: var(--pr-warning-light);
}

.pr-status-option input:checked + .pr-status-card.approved {
    border-color: var(--pr-success);
    background: var(--pr-success-light);
}

.pr-status-option input:checked + .pr-status-card.rejected {
    border-color: var(--pr-danger);
    background: var(--pr-danger-light);
}

.pr-status-card:hover {
    border-color: var(--pr-gray-300);
    transform: translateY(-1px);
}

/* Modal Buttons */
.pr-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 22px;
    border-radius: var(--pr-radius-sm);
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: var(--pr-transition);
}

.pr-btn.primary {
    background: var(--pr-primary);
    color: #fff;
}

.pr-btn.primary:hover {
    background: var(--pr-primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.pr-btn.secondary {
    background: var(--pr-gray-100);
    color: var(--pr-gray-600);
}

.pr-btn.secondary:hover {
    background: var(--pr-gray-200);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1200px) {
    .pr-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .pr-status-selector {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .pr-hero-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }

    .pr-back-link {
        justify-content: center;
    }

    .pr-form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .pr-stats-grid {
        grid-template-columns: 1fr;
    }

    .pr-filters-form {
        flex-direction: column;
    }

    .pr-search-box {
        min-width: 100%;
    }

    .pr-filter-pills {
        width: 100%;
        justify-content: center;
    }

    .pr-hero {
        padding: 24px 20px;
    }

    .pr-status-selector {
        grid-template-columns: 1fr 1fr;
    }

    .pr-actions {
        flex-direction: column;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.pr-hero { animation: fadeInUp 0.4s ease forwards; }
.pr-stat-card { animation: fadeInUp 0.4s ease forwards; opacity: 0; }
.pr-stat-card:nth-child(1) { animation-delay: 0.05s; }
.pr-stat-card:nth-child(2) { animation-delay: 0.1s; }
.pr-stat-card:nth-child(3) { animation-delay: 0.15s; }
.pr-stat-card:nth-child(4) { animation-delay: 0.2s; }
.pr-filters { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s; opacity: 0; }
.pr-rating-dist { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.25s; opacity: 0; }
.pr-table-card { animation: fadeInUp 0.5s ease forwards; animation-delay: 0.3s; opacity: 0; }
</style>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ===== STAR SELECTOR =====
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#starSelector i');
    const ratingInput = document.getElementById('overallRating');

    stars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.dataset.value);
            stars.forEach((s, i) => {
                s.classList.toggle('active', i < val);
            });
        });

        star.addEventListener('click', function() {
            const val = parseInt(this.dataset.value);
            ratingInput.value = val;
            stars.forEach((s, i) => {
                s.classList.toggle('active', i < val);
            });
        });
    });

    document.getElementById('starSelector').addEventListener('mouseleave', function() {
        const val = parseFloat(ratingInput.value) || 0;
        stars.forEach((s, i) => {
            s.classList.toggle('active', i < Math.round(val));
        });
    });

    ratingInput.addEventListener('input', function() {
        const val = parseFloat(this.value) || 0;
        stars.forEach((s, i) => {
            s.classList.toggle('active', i < Math.round(val));
        });
    });
});

// ===== MODAL FUNCTIONS =====
function viewReview(reviewId) {
    $('#reviewDetails').html('<div class="pr-modal-loading"><div class="pr-spinner"></div><p><?php echo __("loading"); ?>...</p></div>');
    $('#viewReviewModal').modal('show');

    $.ajax({
        url: 'ajax/get_performance_review.php',
        type: 'GET',
        data: { review_id: reviewId },
        success: function(response) {
            $('#reviewDetails').html(response);
        },
        error: function() {
            $('#reviewDetails').html('<div class="pr-empty-state"><div class="pr-empty-icon"><i class="feather icon-alert-triangle"></i></div><h4><?php echo __("error_loading_review"); ?></h4></div>');
        }
    });
}

function addReview(userId, employeeName) {
    $('#performanceReviewForm')[0].reset();
    $('#userId').val(userId);
    $('#employeeName').val(employeeName);
    $('#reviewId').val('');
    $('#reviewDate').val(new Date().toISOString().split('T')[0]);
    $('#addReviewModal .pr-modal-header-content h5').text('<?php echo __("add_performance_review"); ?>');

    // Reset stars
    document.querySelectorAll('#starSelector i').forEach(s => s.classList.remove('active'));

    // Reset status
    document.querySelector('.pr-status-option input[value="draft"]').checked = true;

    $('#addReviewModal').modal('show');
}

function editReview(reviewId) {
     $.ajax({
         url: 'ajax/get_performance_review.php',
         type: 'GET',
         data: { review_id: reviewId, edit: 1 },
         dataType: 'json',
         success: function(response) {
             var data = response;
            $('#userId').val(data.user_id);
            $('#employeeName').val(data.employee_name);
            $('#reviewId').val(data.id);
            $('#reviewDate').val(data.review_date);
            $('#periodStart').val(data.period_start);
            $('#periodEnd').val(data.period_end);
            $('#overallRating').val(data.overall_rating);
            $('#comments').val(data.comments);
            $('#achievements').val(data.achievements);
            $('#areasForImprovement').val(data.areas_for_improvement);
            $('#goals').val(data.goals);
            $('#recommendations').val(data.recommendations);

            // Set status radio
            var statusRadio = document.querySelector('.pr-status-option input[value="' + data.status + '"]');
            if (statusRadio) statusRadio.checked = true;

            // Update stars
            var rating = Math.round(parseFloat(data.overall_rating) || 0);
            document.querySelectorAll('#starSelector i').forEach((s, i) => {
                s.classList.toggle('active', i < rating);
            });

            $('#addReviewModal .pr-modal-header-content h5').text('<?php echo __("edit_performance_review"); ?>');
            $('#addReviewModal').modal('show');
        },
        error: function() {
            alert('<?php echo __("error_loading_review"); ?>');
        }
    });
}

$('#performanceReviewForm').on('submit', function(e) {
    e.preventDefault();

    var btn = $('#submitBtn');
    var originalHtml = btn.html();
    btn.html('<div class="pr-spinner" style="width:16px;height:16px;border-width:2px;margin:0 auto;"></div>').prop('disabled', true);

    $.ajax({
         url: 'ajax/save_performance_review.php',
         type: 'POST',
         data: new FormData(this),
         processData: false,
         contentType: false,
         dataType: 'json',
         success: function(response) {
             var result = response;
            if (result.success) {
                $('#addReviewModal').modal('hide');
                location.reload();
            } else {
                alert(result.message || '<?php echo __("error_saving_review"); ?>');
                btn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function() {
            alert('<?php echo __("error_saving_review"); ?>');
            btn.html(originalHtml).prop('disabled', false);
        }
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>