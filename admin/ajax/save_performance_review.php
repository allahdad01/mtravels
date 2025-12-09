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
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $user_id = (int)$_POST['user_id'];
    $review_id = isset($_POST['review_id']) && !empty($_POST['review_id']) ? (int)$_POST['review_id'] : null;
    $review_date = $_POST['review_date'];
    $period_start = $_POST['period_start'];
    $period_end = $_POST['period_end'];
    $overall_rating = !empty($_POST['overall_rating']) ? (float)$_POST['overall_rating'] : null;
    $comments = trim($_POST['comments']);
    $achievements = trim($_POST['achievements']);
    $areas_for_improvement = trim($_POST['areas_for_improvement']);
    $goals = trim($_POST['goals']);
    $recommendations = trim($_POST['recommendations']);
    $status = $_POST['status'];

    // Validate required fields
    if (!$user_id || !$review_date || !$period_start || !$period_end) {
        echo json_encode(['success' => false, 'message' => __('required_fields_missing')]);
        exit;
    }

    // Validate rating range
    if ($overall_rating !== null && ($overall_rating < 1 || $overall_rating > 5)) {
        echo json_encode(['success' => false, 'message' => __('invalid_rating_range')]);
        exit;
    }

    // Validate dates
    if (strtotime($period_end) < strtotime($period_start)) {
        echo json_encode(['success' => false, 'message' => __('invalid_period_dates')]);
        exit;
    }

    if ($review_id) {
        // Update existing review
        $stmt = $pdo->prepare("
            UPDATE performance_reviews SET
                review_date = ?,
                period_start = ?,
                period_end = ?,
                overall_rating = ?,
                comments = ?,
                achievements = ?,
                areas_for_improvement = ?,
                goals = ?,
                recommendations = ?,
                status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND tenant_id = ? And branch_id = ?
        ");
        $stmt->execute([
            $review_date, $period_start, $period_end, $overall_rating,
            $comments, $achievements, $areas_for_improvement, $goals, $recommendations,
            $status, $review_id, $tenant_id, $branch_id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => __('review_updated_successfully')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('review_not_found_or_no_changes')]);
        }
    } else {
        // Check if review already exists for this user in the same period
        $stmt = $pdo->prepare("
            SELECT id FROM performance_reviews
            WHERE user_id = ? AND tenant_id = ? And branch_id = ? AND (
                (period_start BETWEEN ? AND ?) OR
                (period_end BETWEEN ? AND ?) OR
                (period_start <= ? AND period_end >= ?)
            )
        ");
        $stmt->execute([$user_id, $tenant_id, $branch_id, $period_start, $period_end, $period_start, $period_end, $period_start, $period_end]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode(['success' => false, 'message' => __('review_already_exists_for_period')]);
            exit;
        }

        // Create new review
        $stmt = $pdo->prepare("
            INSERT INTO performance_reviews (
                user_id, tenant_id, reviewer_id, review_date, period_start, period_end,
                overall_rating, comments, achievements, areas_for_improvement,
                goals, recommendations, status, created_at, updated_at, branch_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?)
        ");
        $stmt->execute([
            $user_id, $tenant_id, $_SESSION['user_id'], $review_date, $period_start, $period_end,
            $overall_rating, $comments, $achievements, $areas_for_improvement,
            $goals, $recommendations, $status, $branch_id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => __('review_created_successfully')]);
        } else {
            echo json_encode(['success' => false, 'message' => __('error_creating_review')]);
        }
    }

} catch (Exception $e) {
    error_log('Database error in save_performance_review.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving the review. Please try again.']);
}
?>